# The production server

What runs where on `fanooslearn.ir`. The operating model was revised by the
owner on **2026-10-09**: the server and GitHub are the only required
authorities. No laptop is required. This public repository never contains
credentials or private customer/book data. Secrets remain in the server's
root-managed configuration. The production host is provided by **IranServer
(ایران‌سرور)**. Use the authorized route configured for that host; SentinelX
is optional access tooling, not the host or a prerequisite. Authorized direct
operator/SSH access is supported when the host identity and credentials are
verified. A laptop-only `.local/SERVER_ACCESS.md` is not a prerequisite.

## Host

| | |
|---|---|
| Hosting provider | IranServer (ایران‌سرور) |
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
├── shared/storage/        production private objects (exam images and reference PDFs)
├── shared/research/       protected classification workspace (non-release; provision/backup separately)
└── public/                web root served by nginx
/etc/fanoos/               runtime config, root-owned, one file per consumer
├── platform-config.php    the web app (FPM)
├── updater-config.php     the updater
├── telegram.env, bale.env the bots
├── backup-bale.env        the daily Bale backup
└── mysql-migrator.cnf     the migration database account
/var/backups/fanoos/       up to five verified full backups
```

The database is `fanoos_prod` on the local MySQL.

**Separation:** `shared/research/` is a protected, server-only *working*
directory for unique temporary PDF-processing jobs and durable, access-
controlled sittings, decisions, QA receipts and job/checkpoint records; it is not the
content-addressed storage or an immutable release. The directory skeleton was provisioned on 2026-10-09 for
`fanoosupd:fanoosupd` with mode `0700`; verified contents were empty.
Keep book/question content out of Git, and back
it up explicitly with verified recovery before treating working files as
durable. Until the backup/recovery mechanism is verified, do not claim this
working directory is backed up. `research/tmp/<operation-id>/` contains only
disposable active-job indexes and scratch outputs; never treat them as durable
inputs or backup material.

Reference scripts accept `--local` for the protected workspace. Put any
generated page index and temporary reports under
`tmp/<operation-id>/references/`; put durable site-matching sittings in
`bank-sittings/<year>/` and validated decisions/receipts in `classification/`.
Protected PDFs already in object storage are **not** duplicated. The extractor
reads the current approved PDF object in place and verifies its SHA-256. A
page-text index is only a cache for active work; after the validator and audit
receipts are recorded and no concurrent batch uses it, delete the index and
its cache receipt. Preserve the PDF SHA-256 and other concise provenance in the
durable audit. After an operation ends, remove its generated indexes, search
text, one-off scripts and bytecode when no running batch uses them and the
recovery path is verified. Recreate an index from the approved PDF when a
pending review resumes; preserve the review decisions and handoff. Keep unique
source material until a verified recovery exists. Never leave temporary
checkouts under `shared/research` after their task ends.

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
   not move by itself); no laptop is involved.
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

- **Shared-host retention:** across all of a project's server-local backup
  destinations, keep at most the five newest completed, verified sets. Project
  jobs and retention timers prune older completed sets after verification. Restic/Arvan
  object-storage backups are governed separately and excluded from this cap.
- **Per deploy:** a full backup (database and non-reference object storage),
  verified before any migration runs, kept in
  `/var/backups/fanoos/<timestamp>-<id>/`. PDF objects used only by resources
  marked `format_key=reference_pdf` are omitted; shared objects also used by a
  non-reference resource remain included. The PDFs remain in live private
  storage. A restore that needs them requires an audited repair that restores
  the original bytes to their existing object IDs and storage keys. The normal
  library importer does not verify that those object bytes exist, so it is not
  a substitute; the dedicated rehydration operation is not yet automated.
- **Daily:** a database dump at 04:00 Tehran is sent to the owner on Bale.
  Its temporary server copy is removed after successful delivery; it is not a
  substitute for the full database-and-object backup.
- **Retention:** the five-set FANOOS limit includes completed full backups in
  `/var/backups/fanoos/` and verified research recovery archives in
  `/var/backups/fanoos/research/`. `backup.php` applies the shared cap after a
  verified full backup, and `fanoos-backup-retention.timer` reapplies it hourly
  for manual research checkpoints. A research archive counts only when its
  `.sha256` receipt verifies. Incomplete `.partial` directories, missing or
  invalid checksum pairs, and publication receipts are left untouched and do
  not count as recovery sets. Reference-only PDF objects are removed from
  retained full snapshots and their manifests are refreshed.
  Before a deploy that would add a sixth completed set, use
  `ONLY_BACKUPS=1 KEEP_BACKUPS=4 sh scripts/ops/prune-retention.sh --dry-run`
  and then apply the same command without `--dry-run`; this leaves room for
  the required pre-deploy backup without exceeding five.
- Verify any full backup with `scripts/ops/verify-backup.php <dir>`; rehearse a
  restore with `scripts/ops/restore-test.php`. Research archive checksums are
  validated by `scripts/ops/prune-completed-backups.php` before retention.

After the first green updater deployment containing the retention units, install
and enable the hourly timer from the trusted updater checkout:

```sh
sudo install -o root -g root -m 0644 /srv/fanoos/updater-checkout/ops/backup/fanoos-backup-retention.service.example /etc/systemd/system/fanoos-backup-retention.service
sudo install -o root -g root -m 0644 /srv/fanoos/updater-checkout/ops/backup/fanoos-backup-retention.timer.example /etc/systemd/system/fanoos-backup-retention.timer
sudo systemd-analyze verify /etc/systemd/system/fanoos-backup-retention.service /etc/systemd/system/fanoos-backup-retention.timer
sudo systemctl daemon-reload
sudo systemctl enable --now fanoos-backup-retention.timer
sudo systemctl is-active fanoos-backup-retention.timer
```

## Operator scripts

| Script | Use |
|---|---|
| `scripts/ops/request-deployment.php` | queue a deploy of `main` |
| `scripts/ops/backup.php`, `verify-backup.php`, `restore-test.php` | backups |
| `scripts/ops/prune-completed-backups.php` | dry-run by default; enforce the five-set full/research backup limit with `--apply` |
| `scripts/ops/cancel-backup-deployment.php` | cancel a stopped deployment request still in `BACKUP`, with an audit event |
| `scripts/ops/prune-reference-pdfs-from-backups.php` | dry-run by default; remove verified reference-only PDFs from completed backups with `--apply` |
| `scripts/ops/rebuild-question-stats.php` | recompute the per-question counters from attempts |
| `scripts/ops/purge-workspace.php` | remove a workspace (dry run by default); `--images-only` as `fanoosweb` |
| `scripts/ops/bootstrap-owner.php` | create the first owner account |
| `scripts/ops/import-question-bank*.php` | import question banks |
| `scripts/ops/reference-library-inventory.php` | read-only inventory of the private dental reference PDF library, storage capacity and Google Drive mounts |
| `scripts/ops/reference-library-source-info.php` | checksum and size check for one PDF already on a mounted Google Drive |
| `scripts/ops/import-reference-library.php` | dry-run or audited import of official reference PDFs into the private dental library; apply is complete-catalog by default and supports explicit `--allow-partial` |
| `scripts/ops/import-reference-library.ps1` | laptop-to-FANOOS transfer; sends only PDFs the server dry run needs, can copy mapped Drive PDFs on the server, and supports explicit `-AllowPartial` |

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
as verified private PDFs. Apply is blocked by default until every official
edition is either already present or has a verified PDF source.
`-AllowPartial` / `--allow-partial` imports the available editions and reports
the remaining official editions as pending; it does not change the default. A
staged file must be inside the staging directory, have a PDF signature, and
match its checksum.
The importer reuses an exact private-object checksum without storing another
copy. It publishes verified official reference PDFs through a dedicated
audited import path: the imported version is marked approved and published
without fabricating a human review record. This path requires resource
creation, protected-resource management and publishing permissions, and
accepts only the latest verified private PDF attached to a private
`reference_pdf` resource whose topic matches the catalog edition key.

**Optional legacy ingestion channel:** The Windows transfer script reads the ignored owner-only
`.local/reference-library/sources.json`, whose `editions` entries contain an
`edition_key`, `kind` (`local`, `drive_mount`, or `drive_remote`) and `path`.
It hashes local PDFs without extracting text, reads mounted Drive PDFs in
place, and can copy a mapped PDF from the configured `gdrive` remote directly
into FANOOS staging. Local SCP uploads first land in a per-run mode-0700
temporary directory, then the server installs them into protected staging and
removes the temporary copy. The script runs the server dry run and transfers only PDFs the library
needs. `-Apply` makes a verified full backup, imports, checks the
final private inventory for every available edition, reports editions still
pending, and removes the temporary staging directory. `-AllowPartial` is
required to apply when the catalog has unavailable editions. Without
`-Apply`, the script only reports the dry run.

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
is only an optional ingestion source: the PDFs still need to be imported into
FANOOS private object storage for the reference library to be complete.
The Windows script is **not** the mandatory path, and no task waits for a
laptop when the verified edition is already in the server library.

### Current ready-edition inventory and classification operations

The following was **measured after the audited Telegram and Konkur.in
imports on 2026-10-09 and the web-source import on 2026-10-10**, not assumed
from a local folder: official catalog **44 editions**, verified/published
private PDF resources **42**, currently without a verified PDF **2**; all 42
registered objects reported `ready=true`. The library holds
**4,712,822,240 bytes** of registered reference PDFs. The filesystem was
**87% used** (~7.7 GiB available).

A read-only inventory, run by the authorized server operator:

```sh
sudo -n -u fanoosupd sh -c 'FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php /srv/fanoos/current/scripts/ops/reference-library-inventory.php'
```

The PDF inventory says **what objects are approved**, *not* whether their
complete searchable page index and correct chapter map exist. The index is
generated from the exact PDF by `extract_server_reference.py` and stored with
its matching provenance receipt; it is not an independent source file.
To make an edition eligible for chapter classification:
(1) check that exact official edition in the year's catalog;
(2) confirm verified PDF, sufficient disk and authenticated access to
the private object;
(3) run the read-only PDF extraction preflight, then generate its page-text
index and SHA-bound receipt in the backed-up protected research workspace;
(4) visually check the cited page in the exact original PDF and validate
`data/bank/reference-chapter-pages.json` boundaries;
(5) run `scripts/references/find_in_books.py` and
`apply_classification.py` with `--local` pointed at that workspace.
Do not infer that existing private PDF registration implies steps 3–4 are
already complete. Do not copy the whole 3.29-GB library or run large
conversions while the disk is constrained.

Process questions of other *ready exact official editions* while a book
is absent, unreadable or incomplete; classify no new question using a
nearest-edition substitute by default. Pending editions get an explicit
reason and retry trigger. Preserve previously verified decisions.
The rest of the procedure and pre-import safety checks live in
`docs/product/09_CHAPTER_CLASSIFICATION.md`.

### Server-first GitHub sync

After a green merge-commit CI run and updater deployment, run:

```sh
sh /srv/fanoos/updater-checkout/scripts/dev/check-sync.sh
```

The checker reads GitHub `main`, updater HEAD and live release SHA on the
**server**. It requires no laptop, SSH back to an owner's machine, or
local `main` branch. A mismatch is a recorded deployment gap and cannot
be fixed by editing a deployed release.

## Retention

Every deploy adds a release and every import or deploy adds a backup. The
backup script keeps at most the five newest completed FANOOS backup sets across
full backups and verified research archives. `scripts/ops/prune-retention.sh`
(`--dry-run` first) keeps the live release and the five newest others, enforces
the project-wide five-set cap, and removes older eligible backups and the
`/var/lib/fanoos/bank-import-*` staging folders. A full backup counts only
after its `READY` marker is written; an in-progress `.partial` backup is left
untouched. Research archives count only with a valid adjacent SHA-256 sidecar;
missing or corrupt pairs and publication receipts remain untouched. The
`fanoos-backup-retention.timer` runs this backup-only retention hourly.
`KEEP_BACKUPS` cannot be set above five. `ONLY_BACKUPS=1` limits a retention
run to the backup sets, leaving releases and staging alone. Daily database
dumps sent to Bale are temporary and are removed after delivery.
