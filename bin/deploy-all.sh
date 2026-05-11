#!/usr/bin/env bash
set -euo pipefail

# bin/deploy-all.sh --list
# bin/deploy-all.sh --dry-run
# bin/deploy-all.sh
# bin/deploy-all.sh --only sh


HOST="${CMX_DEPLOY_HOST:-root@sites.misbuero.ch}"
IDENTITY="${CMX_DEPLOY_IDENTITY:-$HOME/.ssh/cmx_plesk_ed25519}"
REMOTE_BASE="${CMX_DEPLOY_REMOTE_BASE:-/var/www/vhosts}"
PLUGIN_PATH="${CMX_DEPLOY_PLUGIN_PATH:-httpdocs/wp-content/plugins/cmx-misbuero}"

INSTANCES=()
UPDATED_INSTANCES=()

DELETE=0
DRY_RUN=0
ONLY=""
VERBOSE=0
LIST_ONLY=0
SKIP_LANGUAGE_UPDATES=0
LANGUAGE_UPDATES_ONLY=0

usage() {
  cat <<'EOF'
Usage:
  bin/deploy-all.sh [options]

Options:
  --dry-run          Show what would be copied, without changing the server.
  --delete           Delete remote files that no longer exist locally.
  --only name        Deploy only one instance, e.g. --only sh.
  --verbose          Show changed files during rsync.
  --list             Discover and print deployable instances, then exit.
  --skip-language-updates
                     Do not update WordPress language packs after deploy.
  --language-updates-only
                     Update language packs for matching instances, without deploy.
  -h, --help         Show this help.

Environment overrides:
  CMX_DEPLOY_HOST         Default: root@sites.misbuero.ch
  CMX_DEPLOY_IDENTITY     Default: ~/.ssh/cmx_plesk_ed25519
  CMX_DEPLOY_REMOTE_BASE  Default: /var/www/vhosts
  CMX_DEPLOY_PLUGIN_PATH  Default: httpdocs/wp-content/plugins/cmx-misbuero

Examples:
  bin/deploy-all.sh
  bin/deploy-all.sh --dry-run
  bin/deploy-all.sh --only sh
  bin/deploy-all.sh --delete
  bin/deploy-all.sh --language-updates-only
EOF
}

while (($#)); do
  case "$1" in
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

if ! command -v rsync >/dev/null 2>&1; then
  echo "rsync is not installed or not in PATH." >&2
  exit 1
fi

if ! command -v ssh >/dev/null 2>&1; then
  echo "ssh is not installed or not in PATH." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
SSH_CMD="ssh -i ${IDENTITY}"

discover_instances() {
  local remote_script
  remote_script='for path in '"$REMOTE_BASE"'/*.misbuero.ch/'"$PLUGIN_PATH"'; do [ -d "$path" ] || continue; domain="${path#'"$REMOTE_BASE"'/}"; domain="${domain%%.misbuero.ch/*}"; printf "%s\n" "$domain"; done'
  "$SHELL" -c "$SSH_CMD '$HOST' '$remote_script'" 2>/dev/null | sort -u
}

while IFS= read -r instance; do
  [[ -n "$instance" ]] && INSTANCES+=("$instance")
done < <(discover_instances)

if ((${#INSTANCES[@]} == 0)); then
  echo "Could not discover any deployable instances on ${HOST}." >&2
  echo "Expected directories matching: ${REMOTE_BASE}/*.misbuero.ch/${PLUGIN_PATH}" >&2
  exit 1
fi

if ((LIST_ONLY)); then
  printf '%s\n' "${INSTANCES[@]}"
  exit 0
fi

if ((LANGUAGE_UPDATES_ONLY && DRY_RUN)); then
  echo "--language-updates-only cannot be combined with --dry-run." >&2
  exit 2
fi

RSYNC_OPTS=(
  -rz
  --human-readable
  --stats
  --delay-updates
  --no-perms
  --no-owner
  --no-group
  --exclude .git/
  --exclude .gitignore
  --exclude .gitlab-ci.yml
  --exclude .DS_Store
  --exclude .ddev/
  --exclude .vscode/
  --exclude tmp/
)

if ((DRY_RUN)); then
  RSYNC_OPTS+=(--dry-run)
fi

if ((DELETE)); then
  RSYNC_OPTS+=(--delete)
fi

if ((VERBOSE)); then
  RSYNC_OPTS+=(--itemize-changes)
fi

update_language_packs_instance() {
  local instance="$1"
  local remote_wp_root="${REMOTE_BASE}/${instance}.misbuero.ch/httpdocs"

  echo "    Update WordPress language packs"
  $SSH_CMD "$HOST" "cd '${remote_wp_root}' && PATH=/opt/plesk/php/8.4/bin:\$PATH wp language core update --allow-root && PATH=/opt/plesk/php/8.4/bin:\$PATH wp language plugin update --all --allow-root && PATH=/opt/plesk/php/8.4/bin:\$PATH wp language theme update --all --allow-root"
}

deploy_instance() {
  local instance="$1"
  local remote_wp_root="${REMOTE_BASE}/${instance}.misbuero.ch/httpdocs"
  local remote_plugin_dir="${remote_wp_root}/wp-content/plugins/cmx-misbuero"
  local remote="${HOST}:${remote_plugin_dir}/"

  echo
  echo "==> Deploy ${instance}.misbuero.ch"
  rsync "${RSYNC_OPTS[@]}" -e "$SSH_CMD" "$PLUGIN_DIR/" "$remote"

  if ((DRY_RUN)); then
    return
  fi

  echo "    Fix ownership and permissions"
  $SSH_CMD "$HOST" "chown -R '${instance}:psacln' '${remote_plugin_dir}' && find '${remote_plugin_dir}' -type d -exec chmod 755 {} + && find '${remote_plugin_dir}' -type f -exec chmod 644 {} + && chmod 755 '${remote_plugin_dir}/bin/deploy-all.sh' 2>/dev/null || true"

  echo "    Validate plugin header"
  $SSH_CMD "$HOST" "test -f '${remote_plugin_dir}/cmx-misbuero.php' && grep -q 'Plugin Name:' '${remote_plugin_dir}/cmx-misbuero.php'"

  echo "    Ensure plugin is active"
  $SSH_CMD "$HOST" "cd '${remote_wp_root}' && PATH=/opt/plesk/php/8.4/bin:\$PATH wp plugin is-active cmx-misbuero --allow-root >/dev/null 2>&1 || PATH=/opt/plesk/php/8.4/bin:\$PATH wp plugin activate cmx-misbuero --allow-root"

  if ((SKIP_LANGUAGE_UPDATES == 0)); then
    update_language_packs_instance "$instance"
  fi
}

process_instance() {
  local instance="$1"

  if ((LANGUAGE_UPDATES_ONLY)); then
    echo
    echo "==> Language updates ${instance}.misbuero.ch"
    update_language_packs_instance "$instance"
  else
    deploy_instance "$instance"
  fi

  UPDATED_INSTANCES+=("$instance")
}

if [[ -n "$ONLY" ]]; then
  found=0
  for instance in "${INSTANCES[@]}"; do
    if [[ "$instance" == "$ONLY" ]]; then
      found=1
      process_instance "$instance"
      break
    fi
  done
  if ((found == 0)); then
    echo "Unknown instance: $ONLY" >&2
    echo "Known instances: ${INSTANCES[*]}" >&2
    exit 2
  fi
else
  for instance in "${INSTANCES[@]}"; do
    process_instance "$instance"
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
  for instance in "${UPDATED_INSTANCES[@]}"; do
    echo "- ${instance}.misbuero.ch"
  done
  echo "Anzahl: ${#UPDATED_INSTANCES[@]}"
else
  echo "Aktualisierte Instanzen: keine"
  echo "Anzahl: 0"
fi
