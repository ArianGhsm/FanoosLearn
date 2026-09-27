# The updater on the production host

`fanoos-updater.timer` runs `scripts/ops/update-runner.php` every minute
from the updater's own checkout (`/srv/fanoos/updater-checkout`). It claims a
queued deployment request and carries it through these gates, in order:

1. The checkout is clean and canonical, and `main` is a fast-forward from
   the live release (its `READY` marker).
2. Every required CI job is green for that exact SHA.
3. The full backup completes and verifies.
4. The candidate passes `db/check` and migration preflight.
5. Migrations and seeds run.
6. `current` and `public` are swapped atomically.
7. `FANOOS_UPDATER_RESTART_UNITS` are reloaded or restarted.
8. Health (and the smoke check, if present) passes; otherwise the updater
   rolls back.

`fanoos-update-status.timer` refreshes the "is there an update" status the
bot shows the owner.

## What it needs

- It runs as `fanoosupd` with `FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php`.
  The config holds the migrator database identity plus these keys:

  | Key | Value |
  |---|---|
  | `FANOOS_UPDATER_TARGET_KEY` | `platform-primary` |
  | `FANOOS_DEPLOY_ROOT` | `/srv/fanoos` |
  | `FANOOS_PUBLIC_LINK` | `/srv/fanoos/public` |
  | `FANOOS_UPDATER_REPO_ROOT` | `/srv/fanoos/updater-checkout` |
  | `FANOOS_GITHUB_UPDATER_TOKEN` | `anonymous` while the repository is public; otherwise a read-only token |
  | `FANOOS_UPDATER_RESTART_UNITS` | `php8.3-fpm.service,fanoos-bale-bot.service,fanoos-telegram-bot.service` |

- `/srv/fanoos` is owned by `fanoosupd` so it can swap the pointers, and the
  live release carries a `READY` file containing its SHA.
- `50-fanoos-updater.rules.example`, copied to `/etc/polkit-1/rules.d/`,
  lets `fanoosupd` reload or restart exactly those units and nothing else.
- The target is registered once with
  `php scripts/ops/register-deployment-target.php platform-primary <node> <service>`.

## The checkout is pinned

The runner executes the checkout's own code, so a change to
`apps/platform/src/Operations` or `scripts/ops` takes effect only after the
checkout is fast-forwarded:

```
sudo -u fanoosupd git -C /srv/fanoos/updater-checkout merge --ff-only origin/main
```

## Requesting a deploy

The owner uses the bot's deploy control. An operator runs:

```
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php /srv/fanoos/current/scripts/ops/request-deployment.php --actor=<owner uuid>
```
