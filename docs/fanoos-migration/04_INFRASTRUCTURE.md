# Fanoos infrastructure foundation

Status: local implementation complete; production activation blocked by the gates below

Last updated: 2026-09-06

## Scope and boundaries

This infrastructure belongs only to the Fanoos repository. The earlier dentistry and transcription systems remain read-only evidence sources: no code, state, credentials, deploy target, process, or runtime dependency is shared with them. The useful operational ideas identified during the forensic audit—immutable releases, pre-change backups, checksummed manifests, atomic pointers, and restore rehearsal—were reimplemented for Fanoos rather than copied.

GitHub is the source of truth. A deployable release is an exact 40-character commit SHA that has passed CI. Runtime data and secrets never belong in Git, a release directory, or the public web root.

## Runtime shape

```text
GitHub commit -> CI -> immutable release -> current symlink -> web document-root symlink
                                    |             |
                                    |             +-> PHP API / health
                                    +-> migration +-> MySQL 8
                                                   +-> private object root
                                                   +-> JSON stderr logs
```

The current candidate host can run the PHP web/API surface and MySQL. It is not approved for long-lived Telegram/Bale bots or background workers. Those processes need a later worker host with a supervisor, outbound network access, and separate least-privilege credentials.

## Environment model

| Environment | Purpose | Database | Storage | Deployment |
|---|---|---|---|---|
| development | Laptop implementation | Dedicated `*_dev` DB | Local non-public directory | Direct checkout; no external deploy |
| staging | Migration, upload, restore and release rehearsal | Dedicated staging DB | Dedicated staging root | Exact commit, same release script |
| production | Real Fanoos traffic | Production-only account/schema | Private account-home path | CI-approved exact commit only |

Never share a database, storage root, signing key, bot token, or backup directory between environments. `FANOOS_ENV` labels an environment; it is not a security boundary by itself.

Configuration precedence is process environment first, then an optional PHP array selected with `FANOOS_CONFIG_FILE`. On cPanel the intended ignored file is `~/fanoos/shared/config.php`; its tracked, redacted counterpart is `ops/cpanel/config.php.example`. `.env.example` documents laptop variables. No loader reads the operator specification file.

Required production runtime:

- PHP 8.2 or newer with `json`, `fileinfo`, and `pdo_mysql`; `mbstring` is required by CI tooling.
- MySQL 8 with InnoDB, foreign keys, CHECK constraints, and utf8mb4.
- Private writable object and backup directories outside `public_html`.
- `mysql` and `mysqldump`, plus a mode-0600 client defaults file outside Git.
- Git, Bash, tar, atomic symlink support, cron for scheduled web maintenance, and HTTPS.

## CI contract

`.github/workflows/ci.yml` grants `contents: read` only, disables persisted checkout credentials, pins third-party actions to commit hashes, and cancels superseded runs. It performs:

1. PHP 8.2 and 8.4 syntax checks.
2. Schema, storage-security, backup-integrity, encoding, and tracked-secret checks.
3. A clean, exact-commit release build.
4. Migration reruns and tenant-isolation integration tests against MySQL 8.4.

CI does not deploy and has no production credentials. Production deployment remains a separately approved cPanel operation.

## Upload and object-storage contract

`UploadInspector` treats the client filename as display metadata only. It requires a readable non-symlink regular file, applies a configured byte limit, detects server-side MIME with `fileinfo`, validates PDF/image/JSON structure, rejects active-content signatures, and calculates SHA-256.

`ObjectAddress` generates this server-owned layout:

```text
<classification>/<workspace-shard>/<workspace-uuid>/<object-shard>/<object-uuid>/<version-uuid>.bin
```

The three classifications are `private`, `protected`, and `public`. All bytes remain outside the document root even when classified public; publication is an application policy, not a filesystem exposure rule. `FilesystemObjectStore` uses restrictive permissions, stages in the destination directory, verifies the digest, and atomically renames. A same-key/same-digest retry is idempotent; different bytes at the same key are rejected.

`SignedDownloadToken` binds workspace, object, version, classification, expiry, and nonce with HMAC-SHA-256. Prompt 5 must check RBAC, publication, entitlement, lifecycle, and object status before issuing a short-lived token. Token verification does not replace authorization. Downloads should use a controlled endpoint with `Content-Disposition: attachment`, `nosniff`, exact content length/type, rate limits, and audit events.

## Health and logging

- `GET /health.php` is liveness: PHP version/extensions only.
- `GET /health.php?mode=ready` is readiness: runtime, database query, and private storage readability/writability.
- The response contains booleans and a sanitized release ID only. It never returns paths, hosts, DSNs, exceptions, or credentials.
- `php scripts/ops/health.php` is the deploy/cron readiness probe and exits nonzero on failure.
- `JsonLogger` writes UTC JSON lines to stderr and redacts keys whose names indicate credentials. Web/runtime log routing and retention remain host configuration.

Prompt 5 should carry a request/correlation UUID through HTTP, database audit events, outbox/jobs, and bot work. Metrics and tracing exporters are deliberately not selected until the worker/server topology exists.

## Release and rollback model

`scripts/ops/build-release.php` resolves a requested Git ref to a full SHA, requires no tracked changes, uses `git archive`, and writes the ignored artifact plus a SHA-256 manifest under `var/releases`.

`scripts/ops/cpanel-deploy.sh` is guarded by `FANOOS_DEPLOY_CONFIRMED=1` and account-home path checks. It requires a clean checkout at the requested SHA, takes a database/object backup before changes, creates a new immutable release, runs static checks, migrations, seeds and readiness, then atomically changes `current` and the public symlink. A post-switch failure restores the previous application pointer. Database migrations are not rolled back automatically, so every deploy migration must remain backward-compatible with the previous release.

`scripts/ops/cpanel-rollback.sh` can point the application to a prior completed release only. It does not reverse schema or data.

The cPanel deployment file is intentionally tracked as `ops/cpanel/cpanel.yml.example`, not active as `.cpanel.yml`. Activating it before the host gates pass could cause an unintended deployment.

## Production gates

Production remains blocked until all of these are evidenced:

- Provider resolves the server root filesystem at 89% utilization and confirms sufficient account/backup capacity.
- Exact PHP version, required extensions, effective `upload_max_filesize`, `post_max_size`, `memory_limit`, and execution timeout are verified.
- Dedicated database/user, storage root, backup root, client defaults file, and random signing key exist with least privilege.
- cPanel Git pull/deploy, cron execution, symlink behavior, PHP CLI parity, and web document-root mapping pass in staging.
- HTTPS headers, error routing, log rotation/redaction, backup export, and off-host retention are configured.
- A full backup verification and isolated restore rehearsal pass, followed by application-level tenant/download smoke tests.
- A separate server/runtime is selected for bot and background worker processes.

## Prompt 5 handoff

Stable interfaces available to application work:

- Database: `DatabaseConnection::fromEnvironment()` and the Prompt 3 migration/RBAC contracts.
- Upload/storage: `UploadInspector`, `ObjectAddress`, `FilesystemObjectStore`, and `SignedDownloadToken`.
- Operations: `HealthCheck`, `JsonLogger`, `BackupManifest`, `FileTreeSnapshot`, and `ProcessRunner`.
- Endpoints: liveness and readiness at `/health.php` with the modes documented above.
- Commands: `scripts/db/{check,migrate,seed}.php`, `scripts/ops/{health,backup,verify-backup,restore-test,build-release}.php`, and guarded cPanel deploy/rollback scripts.

Still missing by design: authenticated upload/download controllers, object metadata transactions, payment gateway/webhook credentials, email/SMS providers, bot/worker host and supervisors, production database credentials, signing keys, and observability destinations. Those choices must be supplied per environment and must never be pasted into source or documentation.
