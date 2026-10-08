#!/bin/sh
# Lets the FANOOS PHP-FPM pool read and write its object storage under AppArmor.
#
# The host's php-fpm AppArmor profile is shared with another workload. Its local
# allowances (/etc/apparmor.d/local/php-fpm) list the FANOOS releases and config
# but not /srv/fanoos/shared/storage, so every stored exam image is denied to
# the site ("Permission denied" in the pool log, apparmor "DENIED" in the kernel
# log) and the browser gets a 404.
#
# This adds one rule scoped to the FANOOS storage tree, keeps a dated copy of
# the previous file, and reloads only the php-fpm profile. Safe to run again.
#
#   sudo sh scripts/ops/allow-storage-apparmor.sh [--dry-run]
set -eu

LOCAL=/etc/apparmor.d/local/php-fpm
PROFILE=/etc/apparmor.d/php-fpm
RULE='/srv/fanoos/shared/storage/** rwk,'

if [ ! -f "$LOCAL" ] || [ ! -f "$PROFILE" ]; then
    echo "no php-fpm AppArmor profile on this host; nothing to do"
    exit 0
fi
if grep -qxF "$RULE" "$LOCAL"; then
    echo "already allowed: $RULE"
    exit 0
fi
if [ "${1:-}" = "--dry-run" ]; then
    echo "would add to $LOCAL: $RULE"
    exit 0
fi

cp -p "$LOCAL" "$LOCAL.pre-fanoos-storage-$(date -u +%Y%m%d%H%M%S)"
printf '\n# FANOOS object storage (exam images, protected media).\n%s\n' "$RULE" >> "$LOCAL"
apparmor_parser -r "$PROFILE"
echo "added and reloaded: $RULE"
