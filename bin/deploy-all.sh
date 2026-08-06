#!/usr/bin/env bash
set -euo pipefail

if [[ -t 1 ]] && command -v clear >/dev/null 2>&1; then
  clear
  echo ""
  echo ""
fi

# Deploy this plugin to every customer WordPress instance listed in manage.misbuero.ch.
#
# Examples:
#   bin/deploy-all.sh --list
#   bin/deploy-all.sh --only reiny
#   bin/deploy-all.sh --only reiny --dry-run --skip-language-updates
#   bin/deploy-all.sh --all


HOST="${CMX_DEPLOY_HOST:-}"
IDENTITY="${CMX_DEPLOY_IDENTITY:-${HOME}/.ssh/kunden.misbuero.ch}"
SSH_CONNECT_TIMEOUT="${CMX_DEPLOY_SSH_CONNECT_TIMEOUT:-10}"
PLUGIN_REL="${CMX_DEPLOY_PLUGIN_REL:-wp-content/plugins/cmx-misbuero}"
ALLOWED_THEMES="${CMX_DEPLOY_ALLOWED_THEMES:-twentytwentyfive,mis-buero-online}"
WP_CLI="${CMX_DEPLOY_WP_CLI:-/usr/local/bin/wp}"
WP_CLI_FLAGS="${CMX_DEPLOY_WP_CLI_FLAGS:-}"
MANAGE_HOST="${CMX_DEPLOY_MANAGE_HOST:-b89xv1_public_cloud@b89xv1.ftp.infomaniak.com}"
MANAGE_IDENTITY="${CMX_DEPLOY_MANAGE_IDENTITY:-${HOME}/.ssh/manage.misbuero.ch}"
MANAGE_WP_CLI="${CMX_DEPLOY_MANAGE_WP_CLI:-/usr/bin/wp-cli}"
MANAGE_WP_ROOT="${CMX_DEPLOY_MANAGE_WP_ROOT:-/home/clients/41844abaa870f0807b8c38b73ceee6c4/sites/manage.misbuero.ch}"
MANAGE_POST_TYPE="${CMX_DEPLOY_MANAGE_POST_TYPE:-instances}"
DOMAIN_SUFFIX="${CMX_DEPLOY_DOMAIN_SUFFIX:-.misbuero.ch}"
WP_ROOT_TEMPLATE="${CMX_DEPLOY_WP_ROOT_TEMPLATE:-}"
MANAGE_DOMAIN_META_KEYS="${CMX_DEPLOY_MANAGE_DOMAIN_META_KEYS:-_mb_domain,_domain,domain,host,hostname,url,site_url,home_url,instance_url,subdomain,slug}"
MANAGE_WP_ROOT_META_KEYS="${CMX_DEPLOY_MANAGE_WP_ROOT_META_KEYS:-wp_root,wordpress_root,document_root,root_path,path,instance_path}"
MANAGE_PLUGIN_DIR_META_KEYS="${CMX_DEPLOY_MANAGE_PLUGIN_DIR_META_KEYS:-plugin_dir,plugin_path,cmx_plugin_dir}"
MANAGE_SSH_HOST_META_KEYS="${CMX_DEPLOY_MANAGE_SSH_HOST_META_KEYS:-ssh_host,deploy_host,host_ssh,server}"

INSTANCES=()
UPDATED_INSTANCES=()
FAILED_INSTANCES=()

DELETE=0
DRY_RUN=0
ONLY=""
VERBOSE=0
LIST_ONLY=0
SKIP_LANGUAGE_UPDATES=0
LANGUAGE_UPDATES_ONLY=0
DEPLOY_ALL=0

usage() {
  cat <<'EOF'
Usage:
  bin/deploy-all.sh [options]

Options:
  --all              Deploy all instances intentionally. Required when --only is omitted.
  --dry-run          Show what would be copied, without changing the server.
  --delete           Deprecated; ignored because deploy runs via GitLab worker jobs.
  --only name        Deploy only one instance. Matches slug, host, WP root, or plugin path.
  --verbose          Deprecated; ignored because deploy runs via GitLab worker jobs.
  --list             Print instances from manage.misbuero.ch, then exit.
  --skip-language-updates
                     No-op for GitLab worker deploys; kept for compatibility.
  --language-updates-only
                     Update language packs for matching instances, without deploy.
  -h, --help         Show this help.

Environment overrides:
  CMX_DEPLOY_HOST             Fallback target SSH host if an instance has no SSH metadata.
  CMX_DEPLOY_IDENTITY         Target SSH identity file.
                              Default: ~/.ssh/kunden.misbuero.ch
  CMX_DEPLOY_SSH_CONNECT_TIMEOUT
                              SSH connect timeout in seconds. Default: 10
  CMX_DEPLOY_PLUGIN_REL       Plugin path below each WP root.
                              Default: wp-content/plugins/cmx-misbuero
  CMX_DEPLOY_ALLOWED_THEMES   Comma-separated theme directory names kept on each instance.
                              Default: twentytwentyfive,mis-buero-online
  CMX_DEPLOY_WP_CLI           WP-CLI command on target instances. Default: /usr/local/bin/wp
  CMX_DEPLOY_WP_CLI_FLAGS     Extra WP-CLI flags, e.g. --allow-root
  CMX_DEPLOY_MANAGE_HOST      SSH host for manage.misbuero.ch.
                              Default: b89xv1_public_cloud@b89xv1.ftp.infomaniak.com
  CMX_DEPLOY_MANAGE_IDENTITY  SSH identity for manage.misbuero.ch.
                              Default: ~/.ssh/manage.misbuero.ch
  CMX_DEPLOY_MANAGE_WP_CLI    WP-CLI command on manage.misbuero.ch. Default: /usr/bin/wp-cli
  CMX_DEPLOY_MANAGE_WP_ROOT   WP root of manage.misbuero.ch.
                              Default: /home/clients/41844abaa870f0807b8c38b73ceee6c4/sites/manage.misbuero.ch
  CMX_DEPLOY_MANAGE_POST_TYPE Default: instances
  CMX_DEPLOY_DOMAIN_SUFFIX    Default suffix for slug-only instances. Default: .misbuero.ch
  CMX_DEPLOY_WP_ROOT_TEMPLATE Fallback target WP root, e.g. /srv/www/{host}/public
                              Variables: {id}, {slug}, {host}, {title}

Instance metadata:
  If manage instances store paths directly, set these comma-separated meta keys:
  CMX_DEPLOY_MANAGE_DOMAIN_META_KEYS
  CMX_DEPLOY_MANAGE_WP_ROOT_META_KEYS
  CMX_DEPLOY_MANAGE_PLUGIN_DIR_META_KEYS
  CMX_DEPLOY_MANAGE_SSH_HOST_META_KEYS

Examples:
  bin/deploy-all.sh --only kunde
  bin/deploy-all.sh --only kunde --dry-run
  bin/deploy-all.sh --list
  bin/deploy-all.sh --all --dry-run
  bin/deploy-all.sh --all
  bin/deploy-all.sh --only kunde --language-updates-only
  bin/deploy-all.sh --all --language-updates-only
EOF
}

while (($#)); do
  case "$1" in
    --all)
      DEPLOY_ALL=1
      shift
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    --delete)
      DELETE=1
      shift
      ;;
    --only)
      ONLY="${2:-}"
      if [[ -z "$ONLY" ]]; then
        echo "Missing value for --only." >&2
        exit 2
      fi
      shift 2
      ;;
    --verbose)
      VERBOSE=1
      shift
      ;;
    --list)
      LIST_ONLY=1
      shift
      ;;
    --skip-language-updates)
      SKIP_LANGUAGE_UPDATES=1
      shift
      ;;
    --language-updates-only)
      LANGUAGE_UPDATES_ONLY=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 2
      ;;
  esac
done

if ! command -v ssh >/dev/null 2>&1; then
  echo "ssh is not installed or not in PATH." >&2
  exit 1
fi

if [[ -z "$MANAGE_HOST" ]]; then
  echo "CMX_DEPLOY_MANAGE_HOST is required." >&2
  exit 1
fi

if [[ -z "$MANAGE_WP_ROOT" ]]; then
  echo "CMX_DEPLOY_MANAGE_WP_ROOT is required, e.g. /srv/manage.misbuero.ch/public." >&2
  exit 1
fi

if ((LANGUAGE_UPDATES_ONLY && DRY_RUN)); then
  echo "--language-updates-only cannot be combined with --dry-run." >&2
  exit 2
fi

if [[ -n "$ONLY" && "$DEPLOY_ALL" -eq 1 ]]; then
  echo "--only and --all cannot be combined." >&2
  exit 2
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

SSH_CMD=(ssh)
SSH_CMD+=(-o "ConnectTimeout=${SSH_CONNECT_TIMEOUT}" -o BatchMode=yes)
if [[ -n "$IDENTITY" ]]; then
  SSH_CMD+=(-i "$IDENTITY")
fi

MANAGE_SSH_CMD=(ssh)
MANAGE_SSH_CMD+=(-o "ConnectTimeout=${SSH_CONNECT_TIMEOUT}" -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o WarnWeakCrypto=no)
if [[ -n "$MANAGE_IDENTITY" ]]; then
  MANAGE_SSH_CMD+=(-i "$MANAGE_IDENTITY")
fi

ssh_remote_to() {
  local ssh_host="$1"
  shift
  "${SSH_CMD[@]}" "$ssh_host" "$@"
}

ssh_remote() {
  ssh_remote_to "$HOST" "$@"
}

ssh_manage() {
  "${MANAGE_SSH_CMD[@]}" "$MANAGE_HOST" "$@"
}

remote_quote() {
  printf "'%s'" "${1//\'/\'\\\'\'}"
}

instance_field() {
  local entry="$1"
  local field="$2"
  IFS='|' read -r id slug host wp_root plugin_dir ssh_host title <<<"$entry"
  case "$field" in
    id) printf '%s\n' "$id" ;;
    slug) printf '%s\n' "$slug" ;;
    host) printf '%s\n' "$host" ;;
    wp_root) printf '%s\n' "$wp_root" ;;
    plugin_dir) printf '%s\n' "$plugin_dir" ;;
    ssh_host) printf '%s\n' "$ssh_host" ;;
    title) printf '%s\n' "$title" ;;
  esac
}

discover_instances() {
  local manage_root_q plugin_rel_q manage_wp_cli_q wp_flags_q post_type_q suffix_q template_q domain_keys_q wp_root_keys_q plugin_dir_keys_q ssh_host_keys_q
  manage_root_q="$(remote_quote "$MANAGE_WP_ROOT")"
  plugin_rel_q="$(remote_quote "$PLUGIN_REL")"
  manage_wp_cli_q="$(remote_quote "$MANAGE_WP_CLI")"
  wp_flags_q="$(remote_quote "$WP_CLI_FLAGS")"
  post_type_q="$(remote_quote "$MANAGE_POST_TYPE")"
  suffix_q="$(remote_quote "$DOMAIN_SUFFIX")"
  template_q="$(remote_quote "$WP_ROOT_TEMPLATE")"
  domain_keys_q="$(remote_quote "$MANAGE_DOMAIN_META_KEYS")"
  wp_root_keys_q="$(remote_quote "$MANAGE_WP_ROOT_META_KEYS")"
  plugin_dir_keys_q="$(remote_quote "$MANAGE_PLUGIN_DIR_META_KEYS")"
  ssh_host_keys_q="$(remote_quote "$MANAGE_SSH_HOST_META_KEYS")"

  ssh_manage "MANAGE_WP_ROOT=${manage_root_q} PLUGIN_REL=${plugin_rel_q} WP_CLI=${manage_wp_cli_q} WP_CLI_FLAGS=${wp_flags_q} MANAGE_POST_TYPE=${post_type_q} DOMAIN_SUFFIX=${suffix_q} WP_ROOT_TEMPLATE=${template_q} DOMAIN_META_KEYS=${domain_keys_q} WP_ROOT_META_KEYS=${wp_root_keys_q} PLUGIN_DIR_META_KEYS=${plugin_dir_keys_q} SSH_HOST_META_KEYS=${ssh_host_keys_q} bash -s" <<'REMOTE'
set -euo pipefail

if [[ ! -d "$MANAGE_WP_ROOT" ]]; then
  echo "Manage WP root does not exist: $MANAGE_WP_ROOT" >&2
  exit 1
fi

cd "$MANAGE_WP_ROOT"
"$WP_CLI" $WP_CLI_FLAGS eval-file - <<'PHP'
<?php
$post_type = getenv("MANAGE_POST_TYPE") ?: "instances";
$plugin_rel = trim((string) getenv("PLUGIN_REL"), "/");
$suffix = (string) getenv("DOMAIN_SUFFIX");
$template = (string) getenv("WP_ROOT_TEMPLATE");
$domain_keys = array_filter(array_map("trim", explode(",", (string) getenv("DOMAIN_META_KEYS"))));
$wp_root_keys = array_filter(array_map("trim", explode(",", (string) getenv("WP_ROOT_META_KEYS"))));
$plugin_dir_keys = array_filter(array_map("trim", explode(",", (string) getenv("PLUGIN_DIR_META_KEYS"))));
$ssh_host_keys = array_filter(array_map("trim", explode(",", (string) getenv("SSH_HOST_META_KEYS"))));

$pick_meta = static function (int $post_id, array $keys): string {
	foreach ($keys as $key) {
		$value = get_post_meta($post_id, $key, true);
		if (is_array($value)) {
			$value = reset($value);
		}
		$value = trim((string) $value);
		if ($value !== "") {
			return $value;
		}
	}
	return "";
};

$host_from_value = static function (string $value): string {
	$value = trim($value);
	if ($value === "") {
		return "";
	}
	if (preg_match("~^[a-z][a-z0-9+.-]*://~i", $value) === 1) {
		$host = parse_url($value, PHP_URL_HOST);
		return is_string($host) ? strtolower($host) : "";
	}
	$value = preg_replace("~^//~", "", $value);
	$value = preg_replace("~/.*$~", "", $value);
	$value = preg_replace("~:.*$~", "", $value);
	return strtolower(trim((string) $value));
};

$apply_template = static function (string $template, array $vars): string {
	foreach ($vars as $key => $value) {
		$template = str_replace("{" . $key . "}", (string) $value, $template);
	}
	return rtrim($template, "/");
};

$print_field = static function (string $value): string {
	$value = str_replace(["\t", "\r", "\n", "|"], " ", $value);
	return trim($value);
};

$posts = get_posts([
	"post_type" => $post_type,
	"post_status" => "any",
	"numberposts" => -1,
	"orderby" => "title",
	"order" => "ASC",
]);

foreach ($posts as $post) {
	$id = (int) $post->ID;
	$title = trim((string) $post->post_title);
	$name = trim((string) $post->post_name);
	$raw_host = $pick_meta($id, $domain_keys);
	$host = $host_from_value($raw_host);

	if ($host === "" && strpos($name, ".") !== false) {
		$host = $host_from_value($name);
	}
	if ($host === "" && strpos($title, ".") !== false) {
		$host = $host_from_value($title);
	}

	$slug_source = $name !== "" ? $name : $title;
	$slug = sanitize_title($slug_source);
	if ($host === "" && $slug !== "") {
		$host = $slug . $suffix;
	}
	if ($host !== "") {
		$slug = explode(".", $host)[0] ?: $slug;
	}

	$wp_root = rtrim($pick_meta($id, $wp_root_keys), "/");
	$plugin_dir = rtrim($pick_meta($id, $plugin_dir_keys), "/");
	$ssh_host = $pick_meta($id, $ssh_host_keys);
	$vps_ip = $pick_meta($id, ["_mb_vps_ip", "vps_ip", "ip"]);
	$ssh_user = $pick_meta($id, ["_mb_ssh_user", "ssh_user", "user"]);
	if ($ssh_host === "" && $vps_ip !== "") {
		$ssh_host = ($ssh_user !== "" ? $ssh_user : "ubuntu") . "@" . $vps_ip;
	}

	if ($wp_root === "" && $template !== "") {
		$wp_root = $apply_template($template, [
			"id" => $id,
			"slug" => $slug,
			"host" => $host,
			"title" => $title,
		]);
	}
	if ($wp_root === "" && $host !== "") {
		$wp_root = "/var/www/" . $host . "/public";
	}
	if ($plugin_dir === "" && $wp_root !== "") {
		$plugin_dir = rtrim($wp_root, "/") . "/" . $plugin_rel;
	}

	if ($slug === "" || $host === "") {
		continue;
	}

	echo implode("|", [
		$print_field((string) $id),
		$print_field($slug),
		$print_field($host),
		$print_field($wp_root),
		$print_field($plugin_dir),
		$print_field($ssh_host),
		$print_field($title),
	]) . PHP_EOL;
}
PHP
REMOTE
}

echo "Discovering instances via ${MANAGE_HOST}:${MANAGE_WP_ROOT}..." >&2
while IFS= read -r entry; do
  [[ -n "$entry" ]] && INSTANCES+=("$entry")
done < <(discover_instances)

if ((${#INSTANCES[@]} == 0)); then
  echo "Could not discover any deployable instances in ${MANAGE_POST_TYPE} on manage.misbuero.ch." >&2
  exit 1
fi

if ((LIST_ONLY)); then
  for entry in "${INSTANCES[@]}"; do
    id="$(instance_field "$entry" id)"
    slug="$(instance_field "$entry" slug)"
    host="$(instance_field "$entry" host)"
    wp_root="$(instance_field "$entry" wp_root)"
    plugin_dir="$(instance_field "$entry" plugin_dir)"
    printf '%s\t%s\t%s\t%s\t%s\n' "$id" "$slug" "$host" "$wp_root" "$plugin_dir"
  done
  exit 0
fi

if [[ -z "$ONLY" && "$DEPLOY_ALL" -ne 1 ]]; then
  echo "Refusing to process all instances without an explicit target." >&2
  echo "Use --only <instance> for a single instance, or --all if you really want every instance." >&2
  echo >&2
  echo "Known instances:" >&2
  for entry in "${INSTANCES[@]}"; do
    echo "- $(instance_field "$entry" slug) ($(instance_field "$entry" host))" >&2
  done
  exit 2
fi

update_language_packs_instance() {
  local wp_root="$1"
  local ssh_host="$2"

  echo "    Update WordPress language packs"
  ssh_remote_to "$ssh_host" "cd $(remote_quote "$wp_root") && $(remote_quote "$WP_CLI") $WP_CLI_FLAGS language core update && $(remote_quote "$WP_CLI") $WP_CLI_FLAGS language plugin update --all && $(remote_quote "$WP_CLI") $WP_CLI_FLAGS language theme update --all"
}

cleanup_themes_instance() {
  local wp_root="$1"
  local ssh_host="$2"
  local themes_dir allowed_q themes_dir_q

  if [[ -z "$ssh_host" ]]; then
    echo "    Theme cleanup skipped: no SSH host configured."
    return 0
  fi

  themes_dir="${wp_root%/}/wp-content/themes"
  themes_dir_q="$(remote_quote "$themes_dir")"
  allowed_q="$(remote_quote "$ALLOWED_THEMES")"

  echo "    Theme cleanup: keep ${ALLOWED_THEMES}"
  ssh_remote_to "$ssh_host" "THEMES_DIR=${themes_dir_q} ALLOWED_THEMES=${allowed_q} bash -s" <<'REMOTE'
set -euo pipefail

if [[ ! -d "$THEMES_DIR" ]]; then
  echo "      Theme directory not found: $THEMES_DIR" >&2
  exit 0
fi

parent_dir="$THEMES_DIR/twentytwentyfive"
child_dir="$THEMES_DIR/mis-buero-online"

fs_cmd=()
if [[ ! -w "$THEMES_DIR" || ( -d "$child_dir" && ! -w "$child_dir" ) ]]; then
  if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    fs_cmd=(sudo -n)
  else
    echo "      Theme cleanup skipped: $THEMES_DIR is not writable and passwordless sudo is not available." >&2
    exit 0
  fi
fi

if [[ -d "$parent_dir" ]]; then
  "${fs_cmd[@]}" mkdir -p "$child_dir"
  if [[ ! -f "$child_dir/style.css" ]] || ! grep -q '^Template:[[:space:]]*twentytwentyfive[[:space:]]*$' "$child_dir/style.css"; then
    "${fs_cmd[@]}" tee "$child_dir/style.css" >/dev/null <<'CSS'
/*
Theme Name: Mis Büro – Online
Template: twentytwentyfive
Author: Mis Büro
Description: Child-Theme für Mis Büro Online auf Basis von Twenty Twenty-Five.
Version: 1.0.0
Text Domain: mis-buero-online
*/
CSS
  fi
  if [[ ! -f "$child_dir/functions.php" ]]; then
    "${fs_cmd[@]}" tee "$child_dir/functions.php" >/dev/null <<'PHP'
<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function (): void {
	$parent = wp_get_theme('twentytwentyfive');
	wp_enqueue_style(
		'twentytwentyfive-style',
		get_template_directory_uri() . '/style.css',
		[],
		$parent->exists() ? $parent->get('Version') : null
	);

	$child = wp_get_theme();
	wp_enqueue_style(
		'mis-buero-online-style',
		get_stylesheet_uri(),
		['twentytwentyfive-style'],
		$child->exists() ? $child->get('Version') : null
	);
});
PHP
  fi
fi

IFS=',' read -r -a allowed_raw <<<"$ALLOWED_THEMES"
allowed=()
for item in "${allowed_raw[@]}"; do
  item="${item#"${item%%[![:space:]]*}"}"
  item="${item%"${item##*[![:space:]]}"}"
  [[ -n "$item" ]] && allowed+=("$item")
done

if ((${#allowed[@]} == 0)); then
  echo "      No allowed themes configured; refusing cleanup." >&2
  exit 1
fi

removed=0
kept=0
shopt -s nullglob
for path in "$THEMES_DIR"/*; do
  [[ -d "$path" ]] || continue
  name="$(basename "$path")"
  keep=0
  for allowed_name in "${allowed[@]}"; do
    if [[ "$name" == "$allowed_name" ]]; then
      keep=1
      break
    fi
  done
  if ((keep)); then
    echo "      keep ${name}"
    kept=$((kept + 1))
  else
    echo "      remove ${name}"
    "${fs_cmd[@]}" rm -rf -- "$path"
    removed=$((removed + 1))
  fi
done

echo "      kept ${kept}, removed ${removed}"
REMOTE
}

start_gitlab_plugin_deploy_job() {
  local instance_id="$1"
  local instance_host="$2"
  local instance_id_q manage_root_q manage_wp_cli_q wp_flags_q
  instance_id_q="$(remote_quote "$instance_id")"
  manage_root_q="$(remote_quote "$MANAGE_WP_ROOT")"
  manage_wp_cli_q="$(remote_quote "$MANAGE_WP_CLI")"
  wp_flags_q="$(remote_quote "$WP_CLI_FLAGS")"

  ssh_manage "INSTANCE_ID=${instance_id_q} MANAGE_WP_ROOT=${manage_root_q} WP_CLI=${manage_wp_cli_q} WP_CLI_FLAGS=${wp_flags_q} bash -s" <<'REMOTE'
set -euo pipefail

cd "$MANAGE_WP_ROOT"
"$WP_CLI" $WP_CLI_FLAGS eval-file - <<'PHP'
<?php
namespace CLOUDMEISTER\MB\Manager;

$instance_id = (int) getenv('INSTANCE_ID');
if ($instance_id <= 0) {
	fwrite(STDERR, "Missing INSTANCE_ID." . PHP_EOL);
	exit(1);
}

if (!function_exists(__NAMESPACE__ . '\\cmx_mb_manager_redeploy_mis_buero_plugin')) {
	fwrite(STDERR, "Manage deploy function is not available." . PHP_EOL);
	exit(1);
}

$result = cmx_mb_manager_redeploy_mis_buero_plugin($instance_id);
if (is_wp_error($result)) {
	fwrite(STDERR, $result->get_error_message() . PHP_EOL);
	exit(1);
}

$job_id = is_array($result) ? (string) ($result['job_id'] ?? '') : '';
echo "GitLab plugin deploy started";
if ($job_id !== '') {
	echo ": " . $job_id;
}
echo PHP_EOL;
PHP
REMOTE
}

deploy_instance() {
  local entry="$1"
  local id slug host wp_root plugin_dir ssh_host
  id="$(instance_field "$entry" id)"
  slug="$(instance_field "$entry" slug)"
  host="$(instance_field "$entry" host)"
  wp_root="$(instance_field "$entry" wp_root)"
  plugin_dir="$(instance_field "$entry" plugin_dir)"
  ssh_host="$(instance_field "$entry" ssh_host)"

  if [[ -z "$wp_root" || -z "$plugin_dir" ]]; then
    echo "Missing target path for ${host}. Set instance meta or CMX_DEPLOY_WP_ROOT_TEMPLATE." >&2
    exit 1
  fi

  echo
  echo "==> GitLab plugin deploy ${host:-$slug}"
  echo "    Target: ${ssh_host:-auto} ${plugin_dir}"
  echo "    Source: git@gitlab.com:cloud-meister/plugins/cmx-misbuero.git main"

  if ((DRY_RUN)); then
    echo "    Dry-run: would start Manage worker job plugin_deploy for instance ${id}."
    return
  fi

  start_gitlab_plugin_deploy_job "$id" "${host:-$slug}"
  cleanup_themes_instance "$wp_root" "${ssh_host:-$HOST}"
}

process_instance() {
  local entry="$1"
  local host ssh_host
  host="$(instance_field "$entry" host)"
  ssh_host="$(instance_field "$entry" ssh_host)"
  [[ -n "$ssh_host" ]] || ssh_host="$HOST"

  if ((LANGUAGE_UPDATES_ONLY)); then
    echo
    echo "==> Language updates ${host}"
    update_language_packs_instance "$(instance_field "$entry" wp_root)" "$ssh_host" || return 1
  else
    deploy_instance "$entry" || return 1
  fi

  UPDATED_INSTANCES+=("$entry")
}

matches_only() {
  local entry="$1"
  local id slug host wp_root plugin_dir title
  id="$(instance_field "$entry" id)"
  slug="$(instance_field "$entry" slug)"
  host="$(instance_field "$entry" host)"
  wp_root="$(instance_field "$entry" wp_root)"
  plugin_dir="$(instance_field "$entry" plugin_dir)"
  title="$(instance_field "$entry" title)"

  [[ "$ONLY" == "$id" || "$ONLY" == "$slug" || "$ONLY" == "$host" || "$ONLY" == "$wp_root" || "$ONLY" == "$plugin_dir" || "$ONLY" == "$title" ]]
}

if [[ -n "$ONLY" ]]; then
  found=0
  for entry in "${INSTANCES[@]}"; do
    if matches_only "$entry"; then
      found=1
      process_instance "$entry"
      break
    fi
  done
  if ((found == 0)); then
    echo "Unknown instance: $ONLY" >&2
    echo "Known instances:" >&2
    for entry in "${INSTANCES[@]}"; do
      echo "- $(instance_field "$entry" slug) ($(instance_field "$entry" host))" >&2
    done
    exit 2
  fi
else
  for entry in "${INSTANCES[@]}"; do
    if ! process_instance "$entry"; then
      FAILED_INSTANCES+=("$entry")
      echo "    Fehlgeschlagen: $(instance_field "$entry" host)" >&2
    fi
  done
fi

echo
if ((DRY_RUN)); then
  echo "Dry-run complete. Nothing was changed."
elif ((LANGUAGE_UPDATES_ONLY)); then
  echo "Language updates complete."
else
  echo "Deploy complete."
fi

echo
if ((${#UPDATED_INSTANCES[@]} > 0)); then
  echo "Aktualisierte Instanzen:"
  for entry in "${UPDATED_INSTANCES[@]}"; do
    echo "- $(instance_field "$entry" host)"
  done
  echo "Anzahl: ${#UPDATED_INSTANCES[@]}"
else
  echo "Aktualisierte Instanzen: keine"
  echo "Anzahl: 0"
fi

if ((${#FAILED_INSTANCES[@]} > 0)); then
  echo
  echo "Fehlgeschlagene Instanzen:"
  for entry in "${FAILED_INSTANCES[@]}"; do
    echo "- $(instance_field "$entry" host)"
  done
  echo "Anzahl Fehler: ${#FAILED_INSTANCES[@]}"
  exit 1
fi
