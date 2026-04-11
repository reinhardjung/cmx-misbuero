#!/usr/bin/env bash
set -u

DOMAIN="${1:-rentify.misbuero.ch}"
STAMP="$(date '+%Y%m%d-%H%M%S')"
SAFE_DOMAIN="${DOMAIN//[^A-Za-z0-9._-]/_}"
OUTDIR="${2:-./tmp/timeout-diagnose-${SAFE_DOMAIN}-${STAMP}}"
BASE_URL="https://${DOMAIN}"
VHOST_LOG_DIR="/var/www/vhosts/system/${DOMAIN}/logs"

mkdir -p "${OUTDIR}"

write_file_header() {
	local title="$1"
	{
		printf '# %s\n' "${title}"
		printf '# time=%s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')"
		printf '# host=%s\n\n' "${DOMAIN}"
	} > "${OUTDIR}/${title}.txt"
}

append_cmd() {
	local title="$1"
	shift
	{
		printf '$'
		printf ' %q' "$@"
		printf '\n\n'
		"$@" 2>&1 || true
		printf '\n'
	} >> "${OUTDIR}/${title}.txt"
}

append_shell() {
	local title="$1"
	shift
	local script="$1"
	{
		printf '$ bash -lc %q\n\n' "${script}"
		bash -lc "${script}" 2>&1 || true
		printf '\n'
	} >> "${OUTDIR}/${title}.txt"
}

for file in meta dns http_timings http_headers services journal_recent kernel_oom vhost_logs mysql_processlist; do
	write_file_header "${file}"
done

append_shell meta '
date
hostname -f 2>/dev/null || hostname
id
uname -a
uptime
'

append_shell dns "
command -v getent >/dev/null 2>&1 && getent ahosts '${DOMAIN}' || true
command -v dig >/dev/null 2>&1 && dig +short '${DOMAIN}' || true
"

append_shell http_timings "
for path in '/' '/wp-json/' '/wp-admin/index.php' '/wp-cron.php?doing_wp_cron=1' '/katalog/'; do
	/usr/bin/curl -sS -o /dev/null -w \"path=\$path code=%{http_code} remote_ip=%{remote_ip} connect=%{time_connect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\\n\" '${BASE_URL}'\"\$path\" || true
done
"

append_shell http_headers "
/usr/bin/curl -vkI --max-time 20 '${BASE_URL}/'
"

append_shell services '
systemctl --no-pager --type=service --all 2>/dev/null | grep -Ei "nginx|apache2|httpd|mariadb|mysql|php.*fpm|fpm.*php" || true
printf "\n"
free -m 2>/dev/null || true
printf "\n"
df -h 2>/dev/null || true
printf "\n"
vmstat 1 5 2>/dev/null || true
printf "\n"
ps aux --sort=-%mem 2>/dev/null | head -n 25 || true
printf "\n"
ps aux --sort=-%cpu 2>/dev/null | head -n 25 || true
'

append_shell journal_recent '
journalctl --no-pager --since "-30 min" 2>/dev/null | grep -Ei "nginx|apache|httpd|php.*fpm|mariadb|mysql|gateway timeout|upstream timed out|maximum execution time|allowed memory size|fatal error|oom|out of memory|killed process" | tail -n 400 || true
'

append_shell kernel_oom '
journalctl -k --no-pager --since "-6 hours" 2>/dev/null | grep -Ei "oom|out of memory|killed process" || true
'

append_shell vhost_logs "
if [ -d '${VHOST_LOG_DIR}' ]; then
	ls -lah '${VHOST_LOG_DIR}'
	printf '\\n'
	for file in error_log proxy_error_log access_log proxy_access_log; do
		if [ -f '${VHOST_LOG_DIR}/'\${file} ]; then
			printf '== %s ==\\n' \"\${file}\"
			tail -n 200 '${VHOST_LOG_DIR}/'\${file}
			printf '\\n'
		fi
	done
else
	printf 'Vhost log dir not found: %s\\n' '${VHOST_LOG_DIR}'
fi
"

append_shell mysql_processlist '
if command -v mysqladmin >/dev/null 2>&1; then
	printf "== mysqladmin processlist ==\n"
	mysqladmin processlist || true
	printf "\n"
fi
if command -v mysql >/dev/null 2>&1; then
	printf "== mysql SHOW FULL PROCESSLIST ==\n"
	mysql -e "SHOW FULL PROCESSLIST;" || true
	printf "\n"
fi
if command -v plesk >/dev/null 2>&1; then
	printf "== plesk db SHOW FULL PROCESSLIST ==\n"
	plesk db -Ne "SHOW FULL PROCESSLIST;" || true
	printf "\n"
fi
'

cat > "${OUTDIR}/README.txt" <<EOF
Timeout-Diagnose fuer ${DOMAIN}
Zeitpunkt: $(date '+%Y-%m-%d %H:%M:%S %Z')

Empfohlene Nutzung:
1. Beim naechsten Timeout moeglichst vor einem Server-Neustart ausfuehren.
2. Am besten mit Root-Rechten starten, sonst fehlen oft Journal- und Vhost-Logs.
3. Danach besonders diese Dateien ansehen:
   - ${OUTDIR}/http_timings.txt
   - ${OUTDIR}/services.txt
   - ${OUTDIR}/journal_recent.txt
   - ${OUTDIR}/vhost_logs.txt
   - ${OUTDIR}/mysql_processlist.txt

Startbeispiel:
  sudo bash includes/help/timeout_diagnose.sh ${DOMAIN}
EOF

printf 'Diagnose gespeichert in: %s\n' "${OUTDIR}"
