# Fanoos backup and restore runbook

Status: code and integrity tests complete; database restore rehearsal awaits staging

Last updated: 2026-09-06

## Backup set and threat model

A usable Fanoos backup is one completed directory containing:

- `database.sql`: a MySQL-native logical dump;
- `objects/`: a non-symlink snapshot of uploaded bytes;
- `manifest.json`: exact relative paths, byte sizes, and SHA-256 digests plus release/database metadata;
- `READY`: completion marker containing the manifest digest.

Release source is not duplicated because GitHub retains it by commit SHA. Secrets are not included: private config, MySQL client defaults, bot tokens, and signing keys require a separately protected credential-recovery procedure. Losing the download signing key invalidates tokens but not stored bytes.

A backup on the same hosting account protects against application/operator mistakes, not account loss, provider failure, or ransomware. Copy only verified completed sets to an encrypted off-host repository with independent credentials and retention controls.

## Consistency and command safety

`scripts/ops/backup.php` reads runtime configuration without printing secrets. It uses a MySQL defaults file rather than password arguments and calls `mysqldump` with single-transaction, quick streaming, binary-safe hex blobs, routines, triggers, events, no tablespaces, and GTID suppression. This is consistent for InnoDB tables; schema-changing maintenance must not overlap the dump.

The script rejects a backup root nested inside object storage, writes to a unique `.partial` directory, snapshots files without following symlinks, writes the manifest last, and atomically renames the set. A failed `.partial` directory is not restorable and should be retained for diagnosis until the incident is understood.

## Configure

Create private, environment-specific values corresponding to:

```text
FANOOS_DB_DSN
FANOOS_STORAGE_ROOT
FANOOS_BACKUP_ROOT
FANOOS_MYSQL_DEFAULTS_FILE
FANOOS_MYSQLDUMP_BIN
FANOOS_MYSQL_BIN
FANOOS_RELEASE_SHA
```

The MySQL client defaults file must use a backup-capable least-privilege identity and restrictive filesystem permissions. Test binary locations and database privileges in staging. Never add this file or its content to Git, a ticket, command line, or log.

## Create and verify

Run a backup before every deploy, before any manual data correction, and on the approved schedule:

```bash
php scripts/ops/backup.php
php scripts/ops/verify-backup.php <completed-backup-directory>
```

The first command prints the completed directory only after finalization. The second recomputes every inventory entry and rejects missing, added, resized, or changed payload files. Also compare the `READY` digest with the manifest digest before off-host transfer.

Provisional objectives are daily RPO and a four-hour restore rehearsal RTO; these are goals, not demonstrated guarantees. Begin with daily plus pre-deploy backups. Decide retention only after measuring real database/object growth against the 2 GB account quota; no automated deletion is included because an unreviewed retention command could destroy the last good copy.

## Isolated restore rehearsal

Use a staging-only empty database whose name ends in `_restore_test` and a nonexistent target storage path. Set a dedicated restore DSN and two explicit safety confirmations:

```bash
export FANOOS_RESTORE_TEST_DSN='mysql:host=...;dbname=fanoos_restore_test;charset=utf8mb4'
export FANOOS_ALLOW_TEST_RESTORE=1
export FANOOS_RESTORE_TEST_EMPTY_DB_CONFIRMED=1
php scripts/ops/restore-test.php <completed-backup-directory> <nonexistent-target-storage-path>
```

The script verifies the complete manifest before invoking `mysql`, refuses a database without the `_restore_test` suffix, refuses an existing object target, imports the dump, then copies object bytes without symlinks. The operator must independently prove that the target database is empty before setting the second confirmation.

After the script succeeds:

1. Run the current release against the restored DB/storage with staging-only secrets.
2. Run readiness, schema contracts, record counts, foreign-key/orphan checks, and migration ledger inspection.
3. Sample objects across tenants and compare stored checksums/byte sizes to metadata.
4. Test cross-tenant denial, RBAC, signed-download expiry/tampering, Persian text, and one full domain workflow.
5. Record backup ID, source release, timings, row/object counts, failures, and sign-off. Destroy the isolated target only through the approved staging cleanup process.

Perform a rehearsal before launch, monthly after launch, after backup-tool changes, and after material schema/storage changes.

## Production recovery

The provided restore automation intentionally cannot target production. For a real incident:

1. Freeze writes and capture incident timing/evidence.
2. Take and verify a new pre-restore backup of the damaged state.
3. Select a verified off-host set and its exact source release.
4. Restore into an isolated database/storage target first and complete the rehearsal checks.
5. Prepare reviewed, environment-specific cutover commands and obtain explicit approval.
6. Prefer pointer/credential cutover to overwriting the damaged source.
7. Run readiness and tenant smoke tests, reopen writes gradually, and retain both pre-restore and restored evidence.

Never import a dump into the live schema or copy objects over the live tree with the test script. Never treat provider backup-enabled status as proof until an actual export and restore have succeeded.

## Alert conditions

Alert and block deployment on backup process failure, missing `READY`, manifest mismatch, zero/implausibly small database dump, object count/size discontinuity, insufficient free space, off-host transfer failure, overdue restore rehearsal, or credential exposure in logs.
