# The production server

What runs where on `fanooslearn.ir`, recorded on 2026-10-03. Access details
(SSH user, keys, the pinned host key) are deliberately not here: this
repository is public. They live in the owner's local, git-ignored
`.local/SERVER_ACCESS.md`.

## Host

| | |
|---|---|
| Domain | `fanooslearn.ir` (DNS points straight at the host) |
| OS | Ubuntu 24.04 LTS |
| Size | 2 vCPU, 3.8 GB RAM, 58 GB disk |
| Location | Iran. Outbound access to Telegram goes through a local proxy; GitHub and the Bale API are reached directly |
| Software | nginx 1.24, PHP 8.3 (FPM), MySQL 8.0, Python 3.12 |
| Neighbours | The host also runs another, unrelated workload. FANOOS work never stops, reconfigures or restarts anything that is not `fanoos-*` or the shared PHP-FPM pool reload the updater is allowed |

## Layout

```
/srv/fanoos/
├── releases/<sha>/        one directory per deployed commit (immutable)
├── current -> releases/<sha>   the live release
├── staging/               where the updater builds and tests a candidate
├── updater-checkout/      the updater's clone of GitHub; fast-forwarded by the operator
├── shared/storage/        object storage (exam images, protected media)
└── public/                web root served by nginx
/etc/fanoos/               runtime config, root-owned, one file per consumer
├── platform-config.php    the web app (FPM)
├── updater-config.php     the updater
├── telegram.env, bale.env the bots
├── backup-bale.env        the daily Bale backup
└── mysql-migrator.cnf     the migration database account
/var/backups/fanoos/       verified backups (one per deploy) and db-daily/
```

The database is `fanoos_prod` on the local MySQL.

nginx serves everything under `/assets/` as immutable for a year
(`ops/nginx/fanoos-performance.conf`). A release reaches browsers only
because every asset URL carries `?v=<release sha>`: page links get it from
`Web\AssetVersioner::url()`, and modules imported by other modules get it
from the import map each page emits (`AssetVersioner::moduleMap()`).

## Users

| User | Runs |
|---|---|
| `fanoosweb` | the PHP-FPM pool serving the site and API; owns `shared/storage` |
| `fanoosupd` | the updater (deploy, migrations, backups) |
| `fanoosbot` | the Telegram bot |
| `fanoosbale` | the Bale bot |
| group `fanoosrt` | shared read access to releases and storage for all of the above |

## Services and timers

| Unit | What | When |
|---|---|---|
| `php8.3-fpm` (pool `fanoos`) | site and API | always |
| `fanoos-telegram-bot` | Telegram bot (`apps/telegram-bot/runtime.py`) | always; restarts every 10 minutes by design, to rotate its proxy connection |
| `fanoos-bale-bot` | Bale bot | always |
| `fanoos-updater.timer` | claims a queued deploy request and runs it | every minute |
| `fanoos-update-status.timer` | refreshes the owner's "is an update available" view | every 5 minutes |
| `fanoos-reconcile-payments.timer` | settles payments nobody came back to settle (does nothing while payments are off) | every 5 minutes |
| `fanoos-db-to-bale.timer` | database dump, sent to the owner on Bale | daily 04:00 Tehran |

Unit templates are in `ops/` (`ops/updater`, `ops/backup`, `ops/payments`,
`ops/stage7-bots`).

## Deploying

Only GitHub `main`, only after its CI is green, only through the updater
(`ops/updater/README.md`):

1. Fast-forward the updater checkout to `origin/main` (it is pinned and does
   not move by itself).
2. Queue a deploy: `scripts/ops/request-deployment.php --actor=<owner uuid>`,
   run as `fanoosupd` with `FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php`.
3. Within a minute the updater checks CI for that exact commit, takes and
   verifies a backup, tests the candidate, runs migrations, switches
   `current`, reloads PHP-FPM and restarts the bots, then runs the smoke check
   (`scripts/ops/update-smoke.php`). Any failure rolls back to the previous
   release.

`scripts/dev/check-sync.sh` confirms afterwards that the server runs GitHub
`main`.

## Backups

- **Per deploy:** a full backup (database and storage), verified before any
  migration runs, kept in `/var/backups/fanoos/<timestamp>-<id>/`.
- **Daily:** a database dump at 04:00 Tehran in `/var/backups/fanoos/db-daily/`,
  also sent to the owner on Bale.
- Verify any backup with `scripts/ops/verify-backup.php <dir>`; rehearse a
  restore with `scripts/ops/restore-test.php`.

## Operator scripts

| Script | Use |
|---|---|
| `scripts/ops/request-deployment.php` | queue a deploy of `main` |
| `scripts/ops/backup.php`, `verify-backup.php`, `restore-test.php` | backups |
| `scripts/ops/rebuild-question-stats.php` | recompute the per-question counters from attempts |
| `scripts/ops/purge-workspace.php` | remove a workspace (dry run by default); `--images-only` as `fanoosweb` |
| `scripts/ops/bootstrap-owner.php` | create the first owner account |
| `scripts/ops/import-question-bank*.php` | import question banks |
