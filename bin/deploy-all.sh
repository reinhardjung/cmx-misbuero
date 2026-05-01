#!/usr/bin/env bash
set -euo pipefail

HOST="${CMX_DEPLOY_HOST:-root@sites.misbuero.ch}"
IDENTITY="${CMX_DEPLOY_IDENTITY:-$HOME/.ssh/cmx_plesk_ed25519}"
REMOTE_BASE="${CMX_DEPLOY_REMOTE_BASE:-/var/www/vhosts}"
PLUGIN_PATH="${CMX_DEPLOY_PLUGIN_PATH:-httpdocs/wp-content/plugins/cmx-misbuero}"

INSTANCES=(
  af
  cmx
  demo
  mb
  primeride
  rentify
  ricco
  rj
  sh
)

DELETE=0
DRY_RUN=0
ONLY=""

usage() {
  cat <<'EOF'
Usage:
  bin/deploy-all.sh [options]

Options:
  --dry-run          Show what would be copied, without changing the server.
  --delete           Delete remote files that no longer exist locally.
  --only name        Deploy only one instance, e.g. --only sh.
  --list             Print configured instances and exit.
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
    --list)
      printf '%s\n' "${INSTANCES[@]}"
      exit 0
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

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

RSYNC_OPTS=(
  -az
  --human-readable
  --itemize-changes
  --exclude .git/
  --exclude .DS_Store
  --exclude .ddev/
  --exclude tmp/
)

if ((DRY_RUN)); then
  RSYNC_OPTS+=(--dry-run)
fi

if ((DELETE)); then
  RSYNC_OPTS+=(--delete)
fi

SSH_CMD="ssh -i ${IDENTITY}"

deploy_instance() {
  local instance="$1"
  local remote="${HOST}:${REMOTE_BASE}/${instance}.misbuero.ch/${PLUGIN_PATH}/"

  echo
  echo "==> Deploy ${instance}.misbuero.ch"
  rsync "${RSYNC_OPTS[@]}" -e "$SSH_CMD" "$PLUGIN_DIR/" "$remote"
}

if [[ -n "$ONLY" ]]; then
  found=0
  for instance in "${INSTANCES[@]}"; do
    if [[ "$instance" == "$ONLY" ]]; then
      found=1
      deploy_instance "$instance"
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
    deploy_instance "$instance"
  done
fi

echo
if ((DRY_RUN)); then
  echo "Dry-run complete. Nothing was changed."
else
  echo "Deploy complete."
fi
