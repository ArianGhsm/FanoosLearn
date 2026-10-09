#!/bin/sh
# FANOOS server-first sync checker: no laptop or SSH dependency.
# GitHub main is authoritative for deployed code; server for production data.
# Read-only: no Git ref mutation, production write, or automatic deploy.
set -u

server_root=${FANOOS_SERVER_ROOT:-/srv/fanoos}
checkout=${FANOOS_UPDATER_CHECKOUT:-$server_root/updater-checkout}
current=${FANOOS_CURRENT_LINK:-$server_root/current}

gaps=0
say() { printf '%s\n' "$*"; }
gap() { printf 'GAP  %s\n' "$*"; gaps=$((gaps + 1)); }

if [ ! -d "$checkout/.git" ]; then
    say "cannot access updater checkout: $checkout"
    exit 2
fi
if [ ! -L "$current" ]; then
    say "cannot access current release symlink: $current"
    exit 2
fi

# ls-remote checks GitHub without advancing origin refs in the checkout.
github_main=$(git -C "$checkout" ls-remote --exit-code origin refs/heads/main 2>/dev/null | cut -f1)
if [ -z "$github_main" ]; then
    say "cannot verify GitHub origin/main"
    exit 2
fi
updater=$(git -C "$checkout" rev-parse HEAD 2>/dev/null)
if [ -z "$updater" ]; then
    say "cannot read updater checkout HEAD"
    exit 2
fi

live=$(basename "$(readlink -f "$current")")
case "$live" in
    *[!0-9a-f]*|'') say "unexpected release directory name: $live"; exit 2 ;;
esac
if [ "${#live}" -ne 40 ]; then
    say "unexpected release SHA length: $live"
    exit 2
fi

if [ "$updater" = "$github_main" ]; then
    say "ok   updater checkout = GitHub main ($(printf '%s' "$github_main" | cut -c1-7))"
else
    gap "updater checkout $(printf '%s' "$updater" | cut -c1-7) != GitHub main $(printf '%s' "$github_main" | cut -c1-7)"
fi
if [ "$live" = "$github_main" ]; then
    say "ok   live release = GitHub main ($(printf '%s' "$github_main" | cut -c1-7))"
else
    gap "live release $(printf '%s' "$live" | cut -c1-7) != GitHub main $(printf '%s' "$github_main" | cut -c1-7)"
fi

if [ "$gaps" -eq 0 ]; then
    say "in sync"
    exit 0
fi
say "$gaps gap(s); deploy only after merge-commit CI is green"
exit 1
