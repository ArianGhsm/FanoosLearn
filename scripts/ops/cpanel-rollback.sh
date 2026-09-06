#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
  printf 'Rollback failed: %s\n' "$1" >&2
  exit 1
}

[[ "${FANOOS_DEPLOY_CONFIRMED:-}" == "1" ]] || fail 'FANOOS_DEPLOY_CONFIRMED=1 is required.'
[[ $# -eq 1 && "$1" =~ ^[0-9a-f]{40}$ ]] || fail 'Pass one exact release SHA.'
[[ -n "${HOME:-}" && -n "${FANOOS_DEPLOY_ROOT:-}" && -n "${FANOOS_PUBLIC_LINK:-}" ]] || fail 'Deployment paths are required.'
[[ "$FANOOS_DEPLOY_ROOT" == "$HOME"/* && "$FANOOS_PUBLIC_LINK" == "$HOME"/* ]] || fail 'Deployment paths must stay within the account home.'

RELEASE_DIR="$FANOOS_DEPLOY_ROOT/releases/$1"
CURRENT_LINK="$FANOOS_DEPLOY_ROOT/current"
NEXT_LINK="$FANOOS_DEPLOY_ROOT/.current-next"
PUBLIC_NEXT="${FANOOS_PUBLIC_LINK}.next"
[[ -f "$RELEASE_DIR/READY" ]] || fail 'Target is not a completed release.'
[[ -L "$CURRENT_LINK" ]] || fail 'Current release pointer is missing.'
[[ -f "$FANOOS_DEPLOY_ROOT/shared/config.php" ]] || fail 'Shared config.php is missing.'
[[ ! -e "$FANOOS_PUBLIC_LINK" || -L "$FANOOS_PUBLIC_LINK" ]] || fail 'Public link target exists and is not a symlink.'
[[ ! -e "$NEXT_LINK" && ! -L "$NEXT_LINK" && ! -e "$PUBLIC_NEXT" && ! -L "$PUBLIC_NEXT" ]] || fail 'A prior pointer staging link still exists.'

export FANOOS_CONFIG_FILE="$FANOOS_DEPLOY_ROOT/shared/config.php"
export FANOOS_RELEASE_SHA="$1"
php "$RELEASE_DIR/scripts/ops/health.php"
php "$CURRENT_LINK/scripts/ops/backup.php"
ln -s "$RELEASE_DIR" "$NEXT_LINK"
mv -Tf "$NEXT_LINK" "$CURRENT_LINK"
ln -s "$CURRENT_LINK/apps/platform/public" "$PUBLIC_NEXT"
mv -Tf "$PUBLIC_NEXT" "$FANOOS_PUBLIC_LINK"
php "$CURRENT_LINK/scripts/ops/health.php"
printf 'Rolled back application pointers to %s; database changes were not reversed.\n' "$1"
