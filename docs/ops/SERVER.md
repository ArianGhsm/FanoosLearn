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

The host's php-fpm runs under an AppArmor profile shared with the other
workload. Its local allowances (`/etc/apparmor.d/local/php-fpm`) must include
`/srv/fanoos/shared/storage/** rwk,`, or every exam image answers 404 (the pool
log says "Permission denied"). `scripts/ops/allow-storage-apparmor.sh` adds that
one rule, keeps a dated copy of the file and reloads only the php-fpm profile;
it was applied on 2026-10-08.

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
| `scripts/ops/reference-library-inventory.php` | read-only inventory of the private dental reference PDF library, storage capacity and Google Drive mounts |
| `scripts/ops/reference-library-source-info.php` | checksum and size check for one PDF already on a mounted Google Drive |
| `scripts/ops/import-reference-library.php` | dry-run or audited import of the complete official reference PDF catalog into the private dental library |
| `scripts/ops/import-reference-library.ps1` | laptop-to-FANOOS transfer; sends only PDFs the server dry run says it needs |

## Dental reference PDFs

The official edition list is `data/bank/catalog.json`. Reference PDFs are
production data: they are never committed to Git and are not converted to
text during this import. The library stores each PDF as a private immutable
object, with the catalog edition key in `content_resource_metadata.topic` and
`format_key=reference_pdf`.

After the matching `main` release is deployed, an operator first runs the
read-only inventory and the importer's dry run. The manifest has
`format=fanoos.reference-library.v1`; each available local or Drive source
entry names the PDF and its SHA-256 and byte size. A Drive source may point
to a mounted Drive file; it is read in place and imported without staging
another copy. The dry run also reports
catalog editions without a source and recognizes editions already registered
as verified private PDFs. Apply is blocked until every official edition is
either already present or has a verified PDF source. A staged file must be
inside the staging directory, have a PDF signature, and match its checksum.
The importer reuses an exact private-object checksum without storing another
copy. It publishes verified official reference PDFs through a dedicated
audited import path: the imported version is marked approved and published
without fabricating a human review record. This path requires resource
creation, protected-resource management and publishing permissions, and
accepts only the latest verified private PDF attached to a private
`reference_pdf` resource whose topic matches the catalog edition key.

The Windows transfer script reads the ignored owner-only
`.local/reference-library/sources.json`, whose `editions` entries contain an
`edition_key`, `kind` (`local` or `drive_mount`) and `path`. It hashes local
PDFs without extracting text, reads mounted Drive PDFs in place, runs the
server dry run, and transfers only missing local PDFs. `-Apply` then makes a
verified full backup, imports, checks the final private inventory and removes
the temporary staging directory. Without `-Apply`, it only reports the dry
run.

The apply command requires a verified full database-and-storage backup and
checks that the private storage filesystem has enough free space for every
new byte. For example, from the deployed release as the FANOOS updater
operator:

```sh
php scripts/ops/reference-library-inventory.php
php scripts/ops/import-reference-library.php --dry-run --staging=/srv/fanoos/staging/reference-import/<batch>
php scripts/ops/verify-backup.php /var/backups/fanoos/<verified-backup>
php scripts/ops/import-reference-library.php --apply --verified-backup=/var/backups/fanoos/<verified-backup> --staging=/srv/fanoos/staging/reference-import/<batch>
php scripts/ops/reference-library-inventory.php
```

The operator provisions staging through the approved FANOOS import transport;
it must not write reference files directly into object storage. A Drive mount
is only an input source: the PDFs still need to be imported into FANOOS private
object storage for the reference library to be complete.

## Retention

Nothing prunes itself: every deploy adds a release and every import or deploy
adds a backup. `scripts/ops/prune-retention.sh` (run as root, `--dry-run`
first) keeps the live release and the five newest others, the 14 newest
backups plus the archive backup `20261003T170919Z-6fbfdcba` (the last full
copy before the medical bank was purged; the owner's decision of 2026-10-08),
and removes the rest and the `/var/lib/fanoos/bank-import-*` staging folders.
On 2026-10-08 it took the disk from 85% to 77%. Run it when the disk passes
80%.
