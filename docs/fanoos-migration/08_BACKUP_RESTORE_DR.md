# Stage 8 — Backup / Restore / Disaster Recovery Gate

Status: **repository contracts PASS; real restore is `RUNTIME_VALIDATION_REQUIRED`**.

Fanoos keeps three separate authorities: GitHub for code, production SQL/object storage for live data, verified private backups for recovery. Git history is never accepted as a database/object backup.

## Repository-side capabilities

| Control | Evidence | Repository status |
| --- | --- | --- |
| SQL logical backup hook | `scripts/ops/backup.php` + private DB client config contract | PASS_WITH_RUNTIME_VALIDATION |
| Object/content snapshot | private object tree snapshot/manifest support | PASS_WITH_RUNTIME_VALIDATION |
| Backup manifest | `BackupManifest` inventories payload files and SHA-256 | PASS |
| Tamper detection | `BackupContractTest` alters object bytes and requires verification failure | PASS |
| Independent verify command | `scripts/ops/verify-backup.php` | PASS |
| Restore validation mode | `scripts/ops/restore-test.php` | PASS_WITH_RUNTIME_VALIDATION |
| Destructive restore separation | runbooks never treat application rollback as DB restore | PASS |
| Exact release/migration context | backup/deploy manifest contracts | PASS_WITH_RUNTIME_VALIDATION |
| Secrets excluded | env/credential paths outside release/backup payload contract; repository secret checks | PASS |
| Retention/RPO/RTO | policy placeholder requires operator approval | RUNTIME_VALIDATION_REQUIRED |

## Required backup set before migration/cutover

A valid cutover backup packet contains: an SQL logical dump of schema + canonical rows; provider/database snapshot when available; private object/content snapshot or versioned bucket state; object manifest containing key, workspace owner, byte size and SHA-256; schema migration ledger; exact deployed code SHA; backup manifest + manifest checksum; timestamp/operator/environment identifier; and a separate secret-manager recovery procedure. Secret values are never embedded in the SQL/object archive or manifest.

Backups live outside the release/current tree and outside any public document root. Files/directories must be private to the dedicated runtime/operator identity. A backup whose manifest cannot be independently verified is treated as no backup.

## Restore validation sequence

The staging rehearsal must restore to an **isolated empty target**, never over the current production database/storage. Restore SQL, restore/copy object bytes, verify manifest/checksums, inspect schema compatibility/migration ledger, run `php scripts/db/check.php`, full DB integration/tenant invariants against the restored target where safe, run application readiness with the restored assets, and compare object/count/reconciliation evidence.

Only after an isolated restore succeeds may destructive production restore be considered available. The real destructive command/path is runtime-owned and requires an explicit operator confirmation separate from a normal deploy/update request. Telegram Update Server does not expose a generic restore or shell command.

## Schema compatibility and rollback

Database migrations are not blindly reversible. The application may roll back its immutable release pointer only when the current schema is compatible with the previous application. A forward migration remains in place. If data/schema corruption requires restore, the operator first stops writes/traffic as required, selects the verified backup based on incident time/RPO, evaluates writes that would be lost, and performs the supervised restore procedure.

Never execute a reverse SQL script merely to match an older application SHA. Prefer expand → deploy/backfill → verify → later contract migrations; incident recovery uses forward compensation or an explicitly approved data restore.

## Runtime asset inventory

Codex must inventory, without printing secrets: database engine/version and logical-dump tool; object-store/filesystem root and versioning/snapshot capability; PHP/Python runtime; qpdf/poppler/Pillow/font assets; bot/worker service units; updater service/timer; private env/config paths; log/runtime directories; backup destinations/quotas; release/current pointers; domain/TLS routing; and any external payment/provider dependencies. The inventory belongs in the evidence packet, not Git, if it contains private host paths.

## Failure/DR scenarios to rehearse

| Scenario | Expected behavior |
| --- | --- |
| backup command fails | deployment/import activation stops |
| backup verify fails | stop; no migration/activation |
| SQL restore fails | restore rehearsal fails; production cutover blocked |
| object checksum mismatch | restore fails; affected content not published |
| migration ledger/checksum mismatch | stop and investigate; never edit ledger to continue |
| activation health fails | application pointer may roll back if schema compatible |
| bot/worker fails after platform activation | keep canonical data; fix/rollback service code, do not overwrite DB |
| migration partially fails | inspect transaction/DDL state; forward-fix/new migration; no blind reverse |
| production data corruption | supervised restore/forward repair decision; quantify post-backup writes |
| backup disk/quota low | cutover blocked before mutation |

## Evidence required for acceptance

Codex must return backup ID/path alias (redacted where needed), exact release SHA, SQL dump size + checksum, object manifest object count/bytes/checksum summary, manifest verification result, isolated restore target identifier, restore command result, post-restore schema/health/reconciliation result, elapsed time, observed RPO/RTO, and any retained failure evidence. No secret or personal payload is returned.

Final DR status remains `RUNTIME_VALIDATION_REQUIRED=true` until at least one real staging backup + restore rehearsal succeeds on the target topology.
