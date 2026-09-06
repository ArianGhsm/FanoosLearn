#!/usr/bin/env bash
set -Eeuo pipefail

fail() {
  printf 'Deploy failed: %s\n' "$1" >&2
  exit 1
}

[[ "${FANOOS_DEPLOY_CONFIRMED:-}" == "1" ]] || fail 'FANOOS_DEPLOY_CONFIRMED=1 is required.'
[[ $# -eq 1 && "$1" =~ ^[0-9a-f]{40}$ ]] || fail 'Pass one exact 40-character commit SHA.'
[[ -n "${HOME:-}" && -n "${FANOOS_DEPLOY_ROOT:-}" && -n "${FANOOS_PUBLIC_LINK:-}" ]] || fail 'Deployment paths are required.'
[[ "$FANOOS_DEPLOY_ROOT" == "$HOME"/* && "$FANOOS_PUBLIC_LINK" == "$HOME"/* ]] || fail 'Deployment paths must stay within the account home.'

RELEASE_SHA="$1"
REPO_ROOT="$(git rev-parse --show-toplevel)"
[[ "$(git -C "$REPO_ROOT" rev-parse HEAD)" == "$RELEASE_SHA" ]] || fail 'Checked-out HEAD does not match the requested release.'
[[ -z "$(git -C "$REPO_ROOT" status --porcelain --untracked-files=no)" ]] || fail 'Tracked worktree is not clean.'

RELEASES_DIR="$FANOOS_DEPLOY_ROOT/releases"
SHARED_DIR="$FANOOS_DEPLOY_ROOT/shared"
RELEASE_DIR="$RELEASES_DIR/$RELEASE_SHA"
STAGING_DIR="$RELEASES_DIR/.${RELEASE_SHA}.partial"
CURRENT_LINK="$FANOOS_DEPLOY_ROOT/current"
NEXT_LINK="$FANOOS_DEPLOY_ROOT/.current-next"
PUBLIC_NEXT="${FANOOS_PUBLIC_LINK}.next"
CONFIG_FILE="$SHARED_DIR/config.php"

[[ -f "$CONFIG_FILE" ]] || fail 'Shared config.php is missing.'
[[ ! -e "$RELEASE_DIR" && ! -e "$STAGING_DIR" ]] || fail 'Release or staging path already exists.'
[[ ! -e "$FANOOS_PUBLIC_LINK" || -L "$FANOOS_PUBLIC_LINK" ]] || fail 'Public link target exists and is not a symlink.'
[[ ! -e "$NEXT_LINK" && ! -L "$NEXT_LINK" && ! -e "$PUBLIC_NEXT" && ! -L "$PUBLIC_NEXT" ]] || fail 'A prior pointer staging link still exists.'
mkdir -p "$RELEASES_DIR" "$SHARED_DIR"

export FANOOS_CONFIG_FILE="$CONFIG_FILE"
export FANOOS_RELEASE_SHA="$RELEASE_SHA"

# A database/object snapshot is mandatory before every schema or pointer change.
php "$REPO_ROOT/scripts/ops/backup.php"

mkdir "$STAGING_DIR"
git -C "$REPO_ROOT" archive "$RELEASE_SHA" | tar -x -C "$STAGING_DIR"
mv "$STAGING_DIR" "$RELEASE_DIR"

php "$RELEASE_DIR/scripts/db/check.php"
php "$RELEASE_DIR/scripts/db/migrate.php"
php "$RELEASE_DIR/scripts/db/seed.php"
php "$RELEASE_DIR/scripts/ops/health.php"
printf '%s\n' "$RELEASE_SHA" > "$RELEASE_DIR/READY"

PREVIOUS_TARGET=""
if [[ -L "$CURRENT_LINK" ]]; then
  PREVIOUS_TARGET="$(readlink "$CURRENT_LINK")"
fi

rollback_pointer() {
  trap - ERR
  if [[ -n "$PREVIOUS_TARGET" ]]; then
    ln -s "$PREVIOUS_TARGET" "$NEXT_LINK"
    mv -Tf "$NEXT_LINK" "$CURRENT_LINK"
    ln -s "$CURRENT_LINK/apps/platform/public" "$PUBLIC_NEXT"
    mv -Tf "$PUBLIC_NEXT" "$FANOOS_PUBLIC_LINK"
  fi
}
trap rollback_pointer ERR

ln -s "$RELEASE_DIR" "$NEXT_LINK"
mv -Tf "$NEXT_LINK" "$CURRENT_LINK"
ln -s "$CURRENT_LINK/apps/platform/public" "$PUBLIC_NEXT"
mv -Tf "$PUBLIC_NEXT" "$FANOOS_PUBLIC_LINK"
php "$CURRENT_LINK/scripts/ops/health.php"
trap - ERR

printf 'Deployed %s\n' "$RELEASE_SHA"
