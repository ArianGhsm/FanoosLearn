#!/bin/sh
# Are the laptop, GitHub and the server the same project right now?
# (docs/PROJECT_PRINCIPLES.md §2)
#
# Reads only. Reports every gap it finds and exits 1 if there is any.
#
# The server check needs SSH access. Set:
#   FANOOS_SSH_TARGET   user@host of the server
#   FANOOS_SSH_KEY      path to the private key (optional)
#   FANOOS_KNOWN_HOSTS  pinned known_hosts file (optional)
# Without FANOOS_SSH_TARGET the server is reported as not checked.

set -u
cd "$(dirname "$0")/../.." || exit 2

gaps=0
say() { printf '%s\n' "$*"; }
gap() { printf 'GAP  %s\n' "$*"; gaps=$((gaps + 1)); }

git fetch --quiet --prune origin || { say "cannot reach GitHub"; exit 2; }

branch=$(git rev-parse --abbrev-ref HEAD)
if [ -n "$(git status --porcelain)" ]; then
    gap "laptop: uncommitted changes on '$branch'"
fi

local_main=$(git rev-parse main 2>/dev/null)
github_main=$(git rev-parse origin/main)
if [ "$local_main" != "$github_main" ]; then
    gap "laptop main $(git rev-parse --short main) != GitHub main $(git rev-parse --short origin/main)"
else
    say "ok   laptop main = GitHub main ($(git rev-parse --short origin/main))"
fi

# Local branches with commits GitHub does not have. A branch whose every
# commit is already in main is finished work, not a gap -- only listed.
stale=0
for b in $(git for-each-ref --format='%(refname:short)' refs/heads/); do
    [ "$b" = main ] && continue
    if git merge-base --is-ancestor "$b" origin/main; then
        stale=$((stale + 1))
    elif ! git rev-parse --verify --quiet "origin/$b" >/dev/null; then
        gap "laptop: branch '$b' has work that exists only on this laptop"
    elif [ -n "$(git rev-list "origin/$b..$b")" ]; then
        gap "laptop: branch '$b' has unpushed commits"
    fi
done
[ "$stale" -gt 0 ] && say "note laptop keeps $stale local branch(es) already merged into main (safe to delete)"

# Branches already merged into main but still on GitHub.
merged=$(git branch -r --merged origin/main | grep -v -e 'origin/HEAD' -e 'origin/main$' | wc -l | tr -d ' ')
[ "$merged" -gt 0 ] && say "note GitHub keeps $merged branch(es) already merged into main"

if [ -z "${FANOOS_SSH_TARGET:-}" ]; then
    say "skip server not checked (FANOOS_SSH_TARGET is not set)"
else
    set -- -o BatchMode=yes
    [ -n "${FANOOS_SSH_KEY:-}" ] && set -- "$@" -i "$FANOOS_SSH_KEY"
    if [ -n "${FANOOS_KNOWN_HOSTS:-}" ]; then
        # ssh cannot take a path with a quote in it (the owner's folder has
        # one), so the pinned file is read through a temporary copy.
        hosts=$(mktemp)
        cp "$FANOOS_KNOWN_HOSTS" "$hosts"
        trap 'rm -f "$hosts"' EXIT
        set -- "$@" -o "UserKnownHostsFile=$hosts" -o StrictHostKeyChecking=yes
    fi
    remote=$(ssh "$@" "$FANOOS_SSH_TARGET" 'basename "$(readlink /srv/fanoos/current)"; sudo -n -u fanoosupd git -C /srv/fanoos/updater-checkout rev-parse HEAD 2>/dev/null || echo unknown') || {
        gap "server: unreachable"; remote=""
    }
    if [ -n "$remote" ]; then
        live=$(printf '%s\n' "$remote" | sed -n 1p)
        updater=$(printf '%s\n' "$remote" | sed -n 2p)
        if [ "$live" = "$github_main" ]; then
            say "ok   server runs GitHub main ($(printf '%s' "$live" | cut -c1-7))"
        else
            gap "server runs $(printf '%s' "$live" | cut -c1-7), GitHub main is $(git rev-parse --short origin/main) (merged but not deployed)"
        fi
        if [ "$updater" != "$github_main" ] && [ "$updater" != "unknown" ]; then
            gap "server: updater checkout at $(printf '%s' "$updater" | cut -c1-7), not fast-forwarded to main"
        fi
    fi
fi

if [ "$gaps" -eq 0 ]; then
    say "in sync"
    exit 0
fi
say "$gaps gap(s)"
exit 1
