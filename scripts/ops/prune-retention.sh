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
#   - the KEEP_BACKUPS newest completed full backups (directories with READY).
#     Incomplete .partial backups are left untouched and do not count.
# Removed:
#   - older releases and backups;
#   - /var/lib/fanoos/bank-import-* staging folders (copies of local sittings).
set -eu

RELEASES=/srv/fanoos/releases
BACKUPS=/var/backups/fanoos
KEEP_RELEASES=${KEEP_RELEASES:-5}
KEEP_BACKUPS=${KEEP_BACKUPS:-10}
DRY=0
[ "${1:-}" = "--dry-run" ] && DRY=1

remove() {
    if [ "$DRY" = 1 ]; then echo "would remove $1"; else rm -rf -- "$1"; echo "removed $1"; fi
}

# Releases: newest first by modification time, never the live one.
live=$(readlink -f /srv/fanoos/current)
kept=0
for dir in $(ls -1dt "$RELEASES"/*/ 2>/dev/null); do
    dir=${dir%/}
    if [ "$dir" = "$live" ]; then continue; fi
    if [ "$kept" -lt "$KEEP_RELEASES" ]; then kept=$((kept + 1)); continue; fi
    remove "$dir"
done

# Backups: names start with a UTC timestamp (20261008T101112Z-…). Keep only
# complete full snapshots; a READY marker is written after verification.
completed=
for dir in "$BACKUPS"/20*T*Z-*; do
    [ -d "$dir" ] && [ -f "$dir/READY" ] || continue
    completed="$completed\n$(basename "$dir")"
done
completed=$(printf '%b\n' "$completed" | sed '/^$/d' | sort)
newest=$(printf '%s\n' "$completed" | tail -n "$KEEP_BACKUPS")
for name in $completed; do
    if printf '%s\n' "$newest" | grep -qxF "$name"; then continue; fi
    remove "$BACKUPS/$name"
done

for dir in /var/lib/fanoos/bank-import-*; do
    [ -d "$dir" ] && remove "$dir"
done

df -h / | tail -1
