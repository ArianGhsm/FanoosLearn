# FANOOS schema migration runbook

Status: local development procedure; production deployment is blocked on Prompt 4 verification

## What exists

- Ordered SQL migrations: `database/migrations/*.sql`
- Idempotent generic seed: `database/seeds/0001_generic_rbac.sql`
- PHP migration and seed runners: `scripts/db/migrate.php`, `scripts/db/seed.php`
- Static contract check: `scripts/db/check.php`
- Full static/MariaDB integration suite: `scripts/test.php`
- Disposable local MariaDB: `database/docker-compose.test.yml`

No production host or bot server was accessed. No database was created on the website host. The local operator specification file is Git-ignored and none of its values are embedded in code, docs, compose definitions, commands, or test output.

## Runtime requirements

- PHP 8.2 or newer with `PDO` and `pdo_mysql` enabled.
- MariaDB 10.6+ or a verified compatible MySQL version with InnoDB, JSON, CHECK constraints, recursive CTEs, microsecond timestamps, and `GET_LOCK`/`RELEASE_LOCK`.
- A database user with DDL permission for migrations and normal DML for seeds. Later production operation should use a separate least-privilege application user.
- Docker Desktop plus Compose is optional and used only for the disposable local integration database.

Composer is not required by this Prompt 3 baseline; the small runner uses a repository-local autoloader. Framework/dependency selection remains an explicit later decision.

## Environment contract

Set values in the process environment or an ignored local `.env` loader when one is introduced:

```text
FANOOS_DB_DSN=mysql:host=<host>;port=<port>;dbname=<database>;charset=utf8mb4
FANOOS_DB_USER=<migration-or-application-user>
FANOOS_DB_PASSWORD=<secret>
FANOOS_LEGACY_ID_HMAC_KEY=<secret-with-managed-backup>
```

Tests additionally require `FANOOS_ALLOW_TEST_DB=1`, and the DSN database name must end in `_test`. That double guard prevents the integration suite from running against a normal database.

## Local static check

From the repository root:

```powershell
php scripts/db/check.php
```

This validates required schema families, InnoDB declarations, tenant columns, non-destructive initial DDL, required documentation, the SQL splitter, and UUIDv7 generation. It needs no database.

## Disposable local integration test

Start the loopback-only database:

```powershell
docker compose -p fanoos-prompt3 -f database/docker-compose.test.yml up -d --wait
```

Set only the disposable test values and run the suite:

```powershell
$env:FANOOS_DB_DSN = 'mysql:host=127.0.0.1;port=33079;dbname=fanoos_test;charset=utf8mb4'
$env:FANOOS_DB_USER = 'fanoos_test'
$env:FANOOS_DB_PASSWORD = 'local_test_only'
$env:FANOOS_LEGACY_ID_HMAC_KEY = 'local-integration-key-change-me'
$env:FANOOS_ALLOW_TEST_DB = '1'
php scripts/test.php
```

If the local PHP installation contains `pdo_mysql` but does not enable it in `php.ini`, it can be enabled for this command only. The exact extension directory must match the installed PHP build:

```powershell
php -d extension_dir=C:\php\ext -d extension=pdo_mysql scripts/test.php
```

Remove the disposable database afterward:

```powershell
docker compose -p fanoos-prompt3 -f database/docker-compose.test.yml down -v --remove-orphans
```

The test creates synthetic generic fixtures only: two city/university/faculty/program/cohort/workspace branches, a user in two workspaces, a restricted representative, and an explicit global administrator. It runs migrations and seeds twice, proves scoped read decisions, rejects cross-tenant writes, and checks legacy-map rerun/conflict behavior.

## Applying a schema and seed manually

After the target database and secret injection are verified:

```powershell
php scripts/db/migrate.php
php scripts/db/seed.php
```

Expected behavior:

- the first migration run applies all unapplied files and writes checksums;
- subsequent runs skip unchanged files;
- changing an already-applied migration causes a hard failure;
- seed reruns update generic labels/configuration and do not duplicate roles, permissions, links, the platform scope, or resource types;
- a database advisory lock serializes migration runners.

Do not manually mark a failed migration as applied. MySQL/MariaDB DDL can auto-commit, so inspect the exact failed statement, correct it in a new/unapplied migration when appropriate, and rerun. Initial migrations use `IF NOT EXISTS` to resume safe table creation, but a partially created table must be compared with the expected definition before proceeding.

## Production release sequence

Production is not authorized until Prompt 4 completes environment validation. The intended sequence is:

1. Verify engine/version, extensions, timezone, charset/collation, TLS, database user grants, disk capacity, cron, and backup restore.
2. Put a release artifact and configuration on the target without activating traffic.
3. Take and verify a pre-migration logical backup plus provider-level snapshot when available.
4. Run schema migrations with the dedicated migration identity and retain output/checksums.
5. Run generic seeds.
6. Run schema/invariant smoke checks against the target.
7. Deploy compatible application code using expand-first changes.
8. Enable traffic/jobs in the documented order and monitor errors, locks, queue age, and database resources.
9. Keep the prior artifact and restore procedure available through the observation window.

For future changes use expand → deploy/backfill → verify → contract. Destructive contraction is a separate release after all readers are compatible and a verified backup exists. DDL rollback should normally be a forward compensating migration; data restore is reserved for declared disaster recovery because it can overwrite newer writes.

## Backup and recovery targets

Prompt 4 must choose actual tools/locations, but the required targets are already fixed:

- encrypted SQL logical backup of schema plus canonical rows;
- provider/database snapshot when supported;
- private object-store version/snapshot with a manifest of object key, size, checksum, and owning workspace;
- migration ledger and release commit captured with the backup manifest;
- secret configuration backed up only in the approved secret manager, never inside SQL/object archives;
- RPO/RTO and retention approved before first production data;
- a restore rehearsal into an isolated database that runs migrations/checks and tenant-isolation smoke tests.

## Incident stops

Stop deployment/import when a migration checksum differs, a required engine feature is unavailable, a backup cannot be restored, a cross-workspace invariant fails, an import mapping conflicts, storage checksums differ, or credentials appear in an artifact/log. Do not bypass constraints or edit the ledger to continue.

## HANDOFF_TO_PROMPT_4

Prompt 4 must verify and record all of the following before production deployment decisions:

1. Exact website-host OS/control panel, PHP binary/version/SAPI, enabled `PDO`/`pdo_mysql`, extension paths, memory/upload/execution limits, document root, writable private paths, cron behavior, TLS/proxy headers, SSH/CLI availability, and release/symlink support.
2. Exact MariaDB/MySQL engine/version, hostname/socket/port, database/username creation authority, InnoDB/JSON/CHECK/recursive-CTE/advisory-lock compatibility, charset/collation/timezone, connection/TLS limits, DDL privileges, storage quota, slow-query/monitoring access, and backup/export/restore facilities.
3. Canonical storage adapter for private objects; absolute private staging/final paths or bucket/prefix, permissions, quota, atomic move/multipart behavior, checksum support, lifecycle/versioning, backup target, restore path, and public-delivery boundary. Nothing protected may live directly under the public web root.
4. Concrete backup destinations and ownership for SQL, provider snapshots, object manifests/versions, retention, encryption, RPO/RTO, alerting, and a dated restore rehearsal.
5. Environment/secret injection for `FANOOS_DB_DSN`, `FANOOS_DB_USER`, `FANOOS_DB_PASSWORD`, `FANOOS_LEGACY_ID_HMAC_KEY`, application/session keys, object-store credentials, payment provider credentials, and channel tokens—without committing or printing their values.
6. Available long-running worker/bot host, Python/runtime/process supervisor, outbound network/DNS, webhook or polling route, queue/cron cadence, logging, restart policy, and secret boundary. Because no bot server currently exists, bot deployment remains blocked while local code work may continue.
7. Deployment ordering constraints among schema, platform web/API, workers, bot/channel adapters, cron/outbox consumers, and future backfills; include maintenance/read-only needs and rollback/forward-fix criteria.
8. A host-compatible command sheet for migrate, seed, smoke check, backup, restore, release activation, worker start/stop, and observation—using placeholders or secret references only.

If the verified environment cannot safely provide the required transactional SQL or private storage boundary, Prompt 4 must stop and propose an ADR-backed hosting change. It must not create a second canonical database or silently weaken tenant constraints.
