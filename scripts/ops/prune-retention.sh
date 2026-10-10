#!/bin/sh
# Keeps the server's disk from filling with old releases, backups and import
# staging folders. Run as root; always look at --dry-run first.
#
#   sudo sh scripts/ops/prune-retention.sh --dry-run
#   sudo sh scripts/ops/prune-retention.sh
#
# Kept, always:
#   - the live release (/srv/fanoos/current) and the KEEP_RELEASES newest others
#     (a release can be rebuilt from its Git commit);
#   - the KEEP_BACKUPS newest completed, verified FANOOS backup sets across
#     full backups and private research archives. Incomplete sets do not count.
# Removed:
#   - older releases and backups;
#   - /var/lib/fanoos/bank-import-* staging folders (copies of local sittings).
set -eu

RELEASES=/srv/fanoos/releases
SCRIPT_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
KEEP_RELEASES=${KEEP_RELEASES:-5}
KEEP_BACKUPS=${KEEP_BACKUPS:-5}
ONLY_BACKUPS=${ONLY_BACKUPS:-0}
DRY=0
[ "${1:-}" = "--dry-run" ] && DRY=1

case "$ONLY_BACKUPS" in
    0|1) ;;
    *) echo "ONLY_BACKUPS must be 0 or 1" >&2; exit 2 ;;
esac

case "$KEEP_BACKUPS" in
    ''|*[!0-9]*) echo "KEEP_BACKUPS must be an integer from 1 through 5" >&2; exit 2 ;;
esac
[ "$KEEP_BACKUPS" -ge 1 ] && [ "$KEEP_BACKUPS" -le 5 ] || {
    echo "KEEP_BACKUPS must be an integer from 1 through 5" >&2
    exit 2
}

remove() {
    if [ "$DRY" = 1 ]; then echo "would remove $1"; else rm -rf -- "$1"; echo "removed $1"; fi
}

if [ "$ONLY_BACKUPS" != 1 ]; then
    # Releases: newest first by modification time, never the live one.
    live=$(readlink -f /srv/fanoos/current)
    kept=0
    for dir in $(ls -1dt "$RELEASES"/*/ 2>/dev/null); do
        dir=${dir%/}
        if [ "$dir" = "$live" ]; then continue; fi
        if [ "$kept" -lt "$KEEP_RELEASES" ]; then kept=$((kept + 1)); continue; fi
        remove "$dir"
    done
fi

retention_mode=--apply
[ "$DRY" = 1 ] && retention_mode=--dry-run
FANOOS_CONFIG_FILE=${FANOOS_CONFIG_FILE:-/etc/fanoos/updater-config.php} \
    KEEP_BACKUPS="$KEEP_BACKUPS" php "$SCRIPT_ROOT/scripts/ops/prune-completed-backups.php" "$retention_mode"

if [ "$ONLY_BACKUPS" != 1 ]; then
    for dir in /var/lib/fanoos/bank-import-*; do
        [ -d "$dir" ] && remove "$dir"
    done
fi

df -h / | tail -1
