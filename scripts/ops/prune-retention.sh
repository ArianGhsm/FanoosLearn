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
#   - the KEEP_BACKUPS newest backups, and the backup named in KEEP_ARCHIVE:
#     by default 20261003T170919Z-6fbfdcba, the last full backup taken before
#     the medical bank was purged on 2026-10-03 (178 MB; the next ones are
#     75 MB and then 10 MB) -- the owner's decision of 2026-10-08.
# Removed:
#   - older releases and backups;
#   - /var/lib/fanoos/bank-import-* staging folders (copies of local sittings).
set -eu

RELEASES=/srv/fanoos/releases
BACKUPS=/var/backups/fanoos
KEEP_RELEASES=${KEEP_RELEASES:-5}
KEEP_BACKUPS=${KEEP_BACKUPS:-14}
KEEP_ARCHIVE=${KEEP_ARCHIVE:-20261003T170919Z-6fbfdcba}
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

# Backups: names start with a UTC timestamp (20261008T101112Z-…), so they sort by time.
archive=$KEEP_ARCHIVE
if [ ! -d "$BACKUPS/$archive" ]; then echo "the archive backup $archive is missing; stopping"; exit 1; fi
newest=$(ls -1 "$BACKUPS" | grep -E '^[0-9]{8}T' | sort | tail -n "$KEEP_BACKUPS")
for name in $(ls -1 "$BACKUPS" | grep -E '^[0-9]{8}T' | sort); do
    if [ "$name" = "$archive" ] || echo "$newest" | grep -qxF "$name"; then continue; fi
    remove "$BACKUPS/$name"
done
[ -n "$archive" ] && echo "archive kept: $BACKUPS/$archive"

for dir in /var/lib/fanoos/bank-import-*; do
    [ -d "$dir" ] && remove "$dir"
done

df -h / | tail -1
