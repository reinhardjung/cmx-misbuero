#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
TMP_DIR="${ROOT_DIR}/tmp/plugin-build"

mkdir -p "${DIST_DIR}" "${TMP_DIR}"
rm -rf "${TMP_DIR:?}/"*

copy_common() {
	local target="$1"
	local include_vendor="${2:-1}"

	mkdir -p "${target}/includes" "${target}/src/Core"
	cp "${ROOT_DIR}/includes/globales.ini" "${target}/includes/globales.ini"
	cp -R "${ROOT_DIR}/packages/core/src/." "${target}/src/Core/"

	if [ "${include_vendor}" = "1" ] && [ -d "${ROOT_DIR}/vendor" ]; then
		cp -R "${ROOT_DIR}/vendor" "${target}/vendor"
		find "${target}/vendor" \( -type d -name 'test' -o -type d -name 'tests' -o -type d -name 'Test' -o -type d -name 'Tests' \) -prune -exec rm -rf {} +
	fi
}


write_dist_composer_json() {
	local package="$1"
	local target="$2"
	local psr4
	local autoloader_suffix

	case "${package}" in
		mis-buero)
			autoloader_suffix='MisBueroStandard'
			psr4='      "CLOUDMEISTER\\MisBuero\\Core\\": "src/Core/",
      "CLOUDMEISTER\\MisBuero\\Standard\\": "src/"'
			;;
		mis-buero-trial)
			autoloader_suffix='MisBueroTrial'
			psr4='      "CLOUDMEISTER\\MisBuero\\Core\\": "src/Core/",
      "CLOUDMEISTER\\MisBuero\\Standard\\": "src/",
      "CLOUDMEISTER\\MisBuero\\Trial\\": "src/",
      "CLOUDMEISTER\\MisBuero\\Business\\": "src/",
      "CLOUDMEISTER\\MisBuero\\Modules\\": "src/"'
			;;
		mis-buero-business)
			autoloader_suffix='MisBueroBusiness'
			psr4='      "CLOUDMEISTER\\MisBuero\\Core\\": "src/Core/",
      "CLOUDMEISTER\\MisBuero\\Business\\": "src/"'
			;;
		mis-buero-modules)
			autoloader_suffix='MisBueroModules'
			psr4='      "CLOUDMEISTER\\MisBuero\\Core\\": "src/Core/",
      "CLOUDMEISTER\\MisBuero\\Modules\\": "src/"'
			;;
		*)
			autoloader_suffix='MisBueroPackage'
			psr4='      "CLOUDMEISTER\\MisBuero\\Core\\": "src/Core/"'
			;;
	esac

	cat > "${target}/composer.json" <<JSON
{
  "name": "cloudmeister/${package}",
  "type": "wordpress-plugin",
  "config": {
    "autoloader-suffix": "${autoloader_suffix}"
  },
  "autoload": {
    "psr-4": {
${psr4}
    }
  },
  "require": {
    "php": ">=8.1",
    "endroid/qr-code": "^5",
    "sabre/dav": "^4.7",
    "sabre/uri": "^2.2",
    "dompdf/dompdf": "^3.1",
    "mikuspetr/charts-php": "^1.0",
    "horstoeko/zugferd": "^1.0",
    "symfony/validator": "^6.4",
    "symfony/finder": "^6.4",
    "symfony/process": "^6.4",
    "symfony/yaml": "^6.4"
  }
}
JSON
}

refresh_dist_autoload() {
	local plugin_dir="$1"

	if command -v composer >/dev/null 2>&1 && [ -d "${plugin_dir}/vendor/composer" ]; then
		(
			cd "${plugin_dir}"
			composer dump-autoload -o --no-dev --quiet
		)
	fi

	rm -f "${plugin_dir}/composer.json"
}

validate_free_plugin_zip() {
	local zip_file="$1"
	local first_entry
	local header_files
	local header_count

	first_entry="$(unzip -Z1 "${zip_file}" | sed -n '1p')"
	if [ "${first_entry}" != "mis-buero/mis-buero.php" ]; then
		printf 'Invalid free plugin ZIP: first entry is "%s", expected "mis-buero/mis-buero.php"\n' "${first_entry}" >&2
		exit 1
	fi

	header_files="$(
		while IFS= read -r zip_entry; do
			if unzip -p "${zip_file}" "${zip_entry}" 2>/dev/null | grep -q 'Plugin Name:'; then
				printf '%s\n' "${zip_entry}"
			fi
		done < <(unzip -Z1 "${zip_file}")
	)"
	header_count="$(printf '%s\n' "${header_files}" | sed '/^$/d' | wc -l | tr -d ' ')"

	if [ "${header_count}" != "1" ] || [ "${header_files}" != "mis-buero/mis-buero.php" ]; then
		printf 'Invalid free plugin ZIP: expected exactly one plugin header in mis-buero/mis-buero.php, found:\n%s\n' "${header_files}" >&2
		exit 1
	fi
}

copy_package() {
	local package="$1"
	local target="$2"

	cp "${ROOT_DIR}/packages/${package}/${package}.php" "${target}/${package}.php"
	for package_file in readme.txt LICENSE license.txt uninstall.php; do
		if [ -f "${ROOT_DIR}/packages/${package}/${package_file}" ]; then
			cp "${ROOT_DIR}/packages/${package}/${package_file}" "${target}/${package_file}"
		fi
	done
	if [ -d "${ROOT_DIR}/packages/${package}/src" ]; then
		cp -R "${ROOT_DIR}/packages/${package}/src/." "${target}/src/"
	fi
	if [ -d "${ROOT_DIR}/packages/${package}/languages" ]; then
		mkdir -p "${target}/languages"
		cp -R "${ROOT_DIR}/packages/${package}/languages/." "${target}/languages/"
	fi
}

copy_free_legacy_source() {
	local target="$1"
	local module
	local include_file

	for include_file in \
		cmx_version.php helpers.php functions.php notizen.php projekte.php featured_images.php dokumente.php \
		uploads.php upload_form.php startseite_fix.php help_screens.php layout_defaults.php \
		index.php globales.php public_box.php permalink.php excerpt.php messages.php admin_ui.php login_manager.php \
		call.php datas.php user_ui.php user_switch.php system_users.php login_ui.php untrashed.php dublicate.php \
		SettingsPage.php globales.ini; do
		if [ -f "${ROOT_DIR}/includes/${include_file}" ]; then
			cp "${ROOT_DIR}/includes/${include_file}" "${target}/includes/${include_file}"
		fi
	done

	if [ -d "${ROOT_DIR}/includes/help" ]; then
		mkdir -p "${target}/includes/help"
		cp -R "${ROOT_DIR}/includes/help/." "${target}/includes/help/"
	fi

	if [ -d "${ROOT_DIR}/assets/icons" ]; then
		mkdir -p "${target}/assets/icons"
		cp -R "${ROOT_DIR}/assets/icons/." "${target}/assets/icons/"
	fi

	if [ -f "${ROOT_DIR}/assets/layout_defaults.json" ]; then
		mkdir -p "${target}/assets"
		cp "${ROOT_DIR}/assets/layout_defaults.json" "${target}/assets/layout_defaults.json"
	fi

	if [ -f "${ROOT_DIR}/vendor/mikuspetr/chartjs/chart.umd.min.js" ]; then
		mkdir -p "${target}/vendor/mikuspetr/chartjs"
		cp "${ROOT_DIR}/vendor/mikuspetr/chartjs/chart.umd.min.js" "${target}/vendor/mikuspetr/chartjs/chart.umd.min.js"
	fi

	for module in kontakte artikel belege dokumente projekte budget; do
		if [ -d "${ROOT_DIR}/src/${module}" ]; then
			mkdir -p "${target}/src/${module}"
			cp -R "${ROOT_DIR}/src/${module}/." "${target}/src/${module}/"
		fi
	done

	mkdir -p "${target}/src/cockpit"
	cp "${ROOT_DIR}/src/cockpit/start.php" "${target}/src/cockpit/start.php"
}

build_plugin() {
	local package="$1"
	shift
	local plugin_dir="${TMP_DIR}/${package}"
	local zip_file="${DIST_DIR}/${package}.zip"
	local include_vendor="1"

	rm -rf "${plugin_dir}" "${zip_file}" "${DIST_DIR}/${package}-flat.zip"
	mkdir -p "${plugin_dir}/src"

	if [ "${package}" = "mis-buero" ]; then
		include_vendor="0"
	fi

	copy_common "${plugin_dir}" "${include_vendor}"

	for source_package in "$@"; do
		if [ "${source_package}" = "${package}" ]; then
			copy_package "${source_package}" "${plugin_dir}"
		elif [ -d "${ROOT_DIR}/packages/${source_package}/src" ]; then
			cp -R "${ROOT_DIR}/packages/${source_package}/src/." "${plugin_dir}/src/"
		fi
	done

	if [ "${package}" = "mis-buero" ]; then
		copy_free_legacy_source "${plugin_dir}"
	fi

	if [ "${include_vendor}" = "1" ]; then
		write_dist_composer_json "${package}" "${plugin_dir}"
		refresh_dist_autoload "${plugin_dir}"
	fi

	(
		cd "${TMP_DIR}"
		{
			printf '%s\n' "${package}/${package}.php"
			find "${package}" -type f \
				! -path "${package}/${package}.php" \
				! -path "*/.git/*" \
				! -path "*/.github/*" \
				! -path "*/.vscode/*" \
				! -path "*/node_modules/*" \
				! -path "*/test/*" \
				! -path "*/Test/*" \
				! -path "*/tests/*" \
				! -path "*/Tests/*" \
				! -path "*/dist/*" \
				! -name ".DS_Store" \
				! -name ".env" \
				! -name ".env.*" \
				! -name "*.sh" \
				! -name "*.webloc" \
				-print | LC_ALL=C sort
		} | zip -q -X "${zip_file}" -@
	)

	if [ "${package}" = "mis-buero" ]; then
		validate_free_plugin_zip "${zip_file}"
	fi
}

build_plugin "mis-buero" "mis-buero"
build_plugin "mis-buero-trial" "mis-buero-trial" "mis-buero" "mis-buero-business" "mis-buero-modules"
build_plugin "mis-buero-business" "mis-buero-business"
build_plugin "mis-buero-modules" "mis-buero-modules"

printf 'Created plugin ZIPs in %s\n' "${DIST_DIR}"
