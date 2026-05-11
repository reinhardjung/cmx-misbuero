#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"
TMP_DIR="${ROOT_DIR}/tmp/plugin-build"

mkdir -p "${DIST_DIR}" "${TMP_DIR}"
rm -rf "${TMP_DIR:?}/"*

copy_common() {
	local target="$1"

	mkdir -p "${target}/includes" "${target}/src/Core"
	cp "${ROOT_DIR}/includes/globales.ini" "${target}/includes/globales.ini"
	cp -R "${ROOT_DIR}/packages/core/src/." "${target}/src/Core/"

	if [ -d "${ROOT_DIR}/vendor" ]; then
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

copy_package() {
	local package="$1"
	local target="$2"

	cp "${ROOT_DIR}/packages/${package}/${package}.php" "${target}/${package}.php"
	if [ -d "${ROOT_DIR}/packages/${package}/src" ]; then
		cp -R "${ROOT_DIR}/packages/${package}/src/." "${target}/src/"
	fi
}

build_plugin() {
	local package="$1"
	shift
	local plugin_dir="${TMP_DIR}/${package}"
	local zip_file="${DIST_DIR}/${package}.zip"

	rm -rf "${plugin_dir}" "${zip_file}"
	mkdir -p "${plugin_dir}/src"

	copy_common "${plugin_dir}"

	for source_package in "$@"; do
		if [ "${source_package}" = "${package}" ]; then
			copy_package "${source_package}" "${plugin_dir}"
		elif [ -d "${ROOT_DIR}/packages/${source_package}/src" ]; then
			cp -R "${ROOT_DIR}/packages/${source_package}/src/." "${plugin_dir}/src/"
		fi
	done

	write_dist_composer_json "${package}" "${plugin_dir}"
	refresh_dist_autoload "${plugin_dir}"

	(
		cd "${TMP_DIR}"
		zip -qr "${zip_file}" "${package}" \
			-x "*/.git/*" \
			-x "*/.github/*" \
			-x "*/.vscode/*" \
			-x "*/node_modules/*" \
			-x "*/test/*" \
			-x "*/Test/*" \
			-x "*/tests/*" \
			-x "*/Tests/*" \
			-x "*/dist/*" \
			-x "*/.DS_Store" \
			-x "*/.env" \
			-x "*/.env.*"
	)
}

build_plugin "mis-buero" "mis-buero"
build_plugin "mis-buero-trial" "mis-buero-trial" "mis-buero" "mis-buero-business" "mis-buero-modules"
build_plugin "mis-buero-business" "mis-buero-business"
build_plugin "mis-buero-modules" "mis-buero-modules"

printf 'Created plugin ZIPs in %s\n' "${DIST_DIR}"
