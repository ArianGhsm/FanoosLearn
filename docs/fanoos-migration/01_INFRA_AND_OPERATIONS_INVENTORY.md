# FANOOS infrastructure and operations inventory

## Scope and safety

This document records the legacy operational controls that should inform FANOOS. It does not authorize deployment to legacy hosts, reuse of legacy credentials, or copying production data. No server was contacted and no live deploy, backup, restore, upload, payment, bot-send, or health mutation was run during Prompt 1.

The user has stated that a new FANOOS server is not available yet. Prompt 4 must inspect the actual new host when access is provided and adapt the controls below to observed reality.

## Current topology

| System | Documented/implemented runtime | State authority | Delivery/network notes |
| --- | --- | --- | --- |
| Dentistry website | PHP 8.x multi-page app on shared web hosting under `/public_html`; browser JS/CSS/PWA | Host-side `storage/` JSON/CSV/files and PHP sessions | Deploy through guarded FTP/SFTP-oriented scripts; separate download host for large/public resources |
| Integrated Telegram bot | Python process under systemd on the documented sole Iran VPS | Private SQLite plus temporary/cache files | Telegram egress through loopback Xray proxy; website calls are direct/signed |
| Integrated Bale bot | Separate Python/systemd service on the same VPS | Shared/related bot SQLite with channel-specific identity/delivery state | Bale is direct according to current project logic |
| Deploy notifier | Python durable spool + transports, systemd service/timer | Queue and per-channel delivery receipts | Independently delivers each event to Telegram, Bale and signed owner-only website inbox |
| Protected delivery worker | In-process bounded worker in Integrated bot runtime | Source/issuance/cache/delivery records in bot SQLite | Private Telegram source, PyMuPDF/qpdf processing, protected Telegram send |
| VoiceTranscriberBot | Python/systemd release deployment with loopback health | Independent SQLite under `/var/lib/voice-transcriber` plus cache/artifacts | Separate Telegram egress service/proxy and external STT/AI/payment providers |

The Integrated operational document describes an Ubuntu 24.04 ParsPack host with 2 vCPU, 2 GB RAM, and 50 GB disk. Treat this as time-stamped legacy evidence, not a FANOOS capacity decision. No IP address, credential, token, or channel identifier is included here.

## Runtime components and dependencies

### Dentistry website

- PHP 8.2 is the CI reference, with `mbstring`, `openssl`, and `curl`.
- Browser runtime is vanilla JavaScript/CSS with a service worker, manifest, offline page, and shared site shell.
- Writable runtime roots hold PHP sessions, JSON/CSV state, locks, logs, uploads and caches. `server-only/README.md` defines `DENT_SERVER_ONLY_ROOT`, `DENT_STORAGE_ROOT`, and `DENT_SESSION_SAVE_PATH` as the intended boundary.
- External adapters include Faraz SMS, Zibal, Zarinpal, notification webhook, Navid/TUMS, the download host, and optional ffmpeg for chat media.
- Deployment scripts also require Git, Python, PowerShell or Bash, network upload tooling, and local ignored deploy configuration.

### Integrated bot/protected delivery

- Python application services run under systemd as `integrated-dent-bot.service` and `integrated-dent-bale-bot.service`.
- The bot persists operational state in SQLite and exposes/uses loopback health checks.
- Telegram network egress depends on a loopback Xray proxy in the current host topology; direct website/Bale paths are intentionally distinct.
- Protected PDF processing uses PyMuPDF, qpdf validation, fonts/assets, bounded temporary storage and optional OpenCV-based forensic analysis.
- The durable notifier has its own flush service/timer contract and transport-specific receipts/retries.

### VoiceTranscriberBot

- Python application under `voice-transcriber.service` with an independent `voice-telegram-egress.service`.
- Immutable releases are created under `/opt/voice-transcriber/releases`; `/opt/voice-transcriber/current` identifies the active release.
- SQLite, runtime environment and cache/artifacts are separated under service-owned paths; health is served on a loopback port.
- Functional dependencies include Telegram/MTProto, STT/AI providers, media tooling, document/PDF/DOCX/PPTX export libraries, and payment bridge integration.

## Configuration and secret inventory

The tracked Dentistry `.env.example` exposes structure, not values:

- Auth/session secret: `DENT_AUTH_SECRET_KEY`.
- SMS adapter: `DENT_SMS_FARAZ_ENABLED`, API key, pattern, sender, domain and parameter names.
- Public site base: `DENT_SITE_DOMAIN`.
- Payment selection and Zarinpal/Zibal request/verify/start configuration.
- Payment notification webhook.
- Chat media target and ffmpeg binary.
- Navid secret and Python binary.

Additional deploy/upload settings are loaded from ignored local configuration or environment variables. Integrated/Voice bot tokens, signed-site keys, Telegram/Bale recipient/source identifiers, proxy settings, server access, AI/STT secrets, database paths and notifier settings likewise belong outside source.

### Required target rules

1. Store secret values in the Prompt 4-selected secret boundary; Git contains only examples and secret identifiers.
2. Never expose gateway credential values through an admin read API. The legacy payment owner payload currently does so and is not reusable.
3. Scope integration keys by environment/service, support key IDs and overlap rotation, and audit use without logging signatures/tokens.
4. Keep runtime data, uploads, sessions, locks, logs, caches, databases and backups out of source-control and deployment artifacts.
5. Run secret scanning in pre-commit/CI and scan imported legacy history/artifacts before publication.

## Website deployment lifecycle

Canonical legacy entrypoints are `DENT/scripts/complete_task.ps1` and `deploy_public_html.ps1` (with a Bash counterpart). `DENT/DEPLOY.md` defines this order:

1. Snapshot host `storage/` one-way to the laptop and mirror the verified active snapshot to `server-only/storage`.
2. Run local validation.
3. Build a delta plan and deploy code beneath `/public_html`.
4. Run live health/freshness checks.
5. Record deployment state/manifest, emit owner lifecycle notification, and synchronize code to GitHub.

Important implemented safeguards:

- Remote host data is authoritative; runtime sync direction is host -> laptop only.
- Snapshot promotion requires valid JSON, required schemas, matching size/hash manifest and stable consecutive reads for high-churn bot/payment/notification files.
- Runtime/storage/session/lock/backup/cache/env files are excluded from web deploy and Git synchronization, including full sync.
- Delta planning compares Git and the last deployed-content manifest so an unchanged dirty file is not repeatedly uploaded.
- Normal deployment fails before transfer above the configured upload/delete count thresholds unless a large/full operation is explicitly requested.
- PWA version stamping is constrained to shared PWA artifacts rather than rewriting the entire site.
- Successful host state is checkpointed before later notification/Git failure so retries do not repeat uploads.
- Lifecycle events have a stable ID and exactly one terminal state; notification channel failure must not repeat the deployment.
- Dry-run, path-scoped deploy, explicit full-sync, and freshness audit modes exist.

These are valuable controls. The FTP/shared-host transport, `/public_html` target, local configuration paths, and coupling of deployment with Git publication are legacy details. FANOOS should keep the state machine and gates but select the artifact/release transport after inspecting the new host.

## Bot and Voice release/rollback patterns

IntegratedDent1402Tums contains separate deploy scripts for Telegram, Bale, notifier, forensic runtime, shared offers and host repairs. The scripts are rich in live assertions but fragmented and host-specific. Consolidate them after extracting their checks:

- service state and restart count;
- correct environment/config ownership and permissions;
- signed website reachability;
- proxy/egress behavior;
- SQLite schema/integrity;
- bot account/profile and authorization smoke tests;
- protected-delivery dependency and benchmark checks;
- start/succeeded/failed/rolled_back notification lifecycle.

VoiceTranscriberBot provides a clearer target pattern:

1. Check for active paid/queued/processing work before disruptive release actions.
2. Record the previous release and create a verified pre-deploy backup.
3. Stage a timestamped immutable release and atomically update `current`.
4. Restart and run release verification plus loopback health.
5. Create a verified post-deploy backup.
6. On failure, atomically restore the previous release, restart, verify health, and emit `rolled_back` when successful.

FANOOS can adapt this pattern whether the target uses systemd, containers, or a managed platform. The invariant is an immutable, identifiable artifact plus a tested rollback—not a specific directory name.

## Backup and restore inventory

### Website file-store snapshots

`DENT/scripts/snapshot_remote_storage_verified.py` and the deploy wrapper implement:

- one-way remote capture;
- manifest size/hash verification;
- JSON parse and critical-schema checks;
- stability checks on selected changing files;
- complete-snapshot promotion to active/latest;
- exclusion of transient `tmp`, session, cache and backup directories from authoritative state;
- local `server-only/storage` mirror that is not committed or uploaded.

Risk: the operational copy is laptop-centered and the legacy system has many independently written JSON documents. A snapshot can be internally valid but not a transactionally consistent point across every domain.

### Integrated bot backup/restore

Scripts include `backup-vps-state.ps1`, `run-scheduled-vps-backup.ps1`, `verify_vps_backup.py`, `restore-vps-state.ps1`, and `restore-vps-state-to-server.ps1`. Evidence shows collection of SQLite/config/runtime state, checksums, encrypted laptop artifacts, restore verification, and service-aware restore procedures.

Risk: local encryption/recovery is tied to Windows DPAPI/current-user context and the source directory itself is not under an inspected Git authority.

### Voice backup/restore

`backup-production.ps1` uses SQLite's consistent backup command, captures runtime environment and optional egress configuration, creates SHA-256 manifests, encrypts with Windows DPAPI, decrypts a round trip, extracts it, and runs SQLite integrity verification. `restore-production.ps1` validates the artifact, creates a mandatory pre-restore backup, stages files, checks hashes and SQLite integrity, stops the service, retains a rollback copy, restores, restarts, verifies health/current release/database, and rolls back on failure.

Risk: DPAPI remains machine/user-bound, and environment files inside a backup demand strict custody and retention.

### FANOOS backup requirements for Prompt 4

- Inventory every authority: transactional DB, object storage, job/outbox data, bot delivery DB, search index, secrets/config and audit logs.
- Define RPO, RTO, retention, encryption/key custody, off-host/off-account copies and deletion policy.
- Use database-native consistency and versioned object storage where supported.
- Back up secret references/config separately with appropriate access; do not place plaintext secrets in general archives.
- Produce signed/checksummed manifests and test a restore into an isolated environment on a schedule.
- Require a pre-migration/pre-restore backup and a measured rollback rehearsal.
- Make caches/search indexes rebuildable so they need not be authoritative backup blockers.

## Upload and download operations

### Current website behavior

- Notes metadata lives on the web host while large resource binaries can live on a distinct download host.
- The preferred notes path creates a scoped direct-upload session/gateway and accepts bounded browser chunks before finalize/resolve.
- File manager operations include browse, create directory, rename and delete under authorized roots.
- Content tools provide owner-managed file/paste sharing using public tokens.
- HTML uploader accepts one file per token and renders it in a sandboxed viewer.
- Forms and chat have separate upload paths; receipt/form files are private, while chat media has optional ffmpeg processing.
- A verified public-file upload helper and an upload-configuration check exist.

### Target controls

- One object abstraction with tenant/workspace prefixes and server-generated object keys.
- Multipart sessions with expiry, maximum size/chunk count, idempotent part/finalize, checksum and content-length enforcement.
- MIME detection independent of client extension; malware/active-content policy; image/PDF parsing in isolated bounded workers.
- Private-by-default objects, signed short-lived downloads, explicit public publication, range support where needed.
- Metadata version/provenance, retention/legal status, uploader identity and immutable audit event.
- Quota/rate/concurrency controls at user/workspace/product levels.
- No pass-through of server credentials to browser and no arbitrary path construction.
- Protected originals never served from the public website/download host.

## CI and validation inventory

### Dentistry GitHub Actions

`DENT/.github/workflows/ci.yml` runs deterministic static checks on Ubuntu with PHP 8.2, Python 3.11 and Node 20 for relevant push/PR paths. `persian-text-integrity.yml` separately runs UTF-8/Persian text validation. Workflow permissions are read-only and concurrency cancels superseded runs.

`scripts/run_static_checks.sh` and sibling checks cover PHP/JS/Python syntax/contracts, authentication resilience, bot integration, exam catalog/content/timeline, term schedule, upload config, text integrity and selected smokes/fixtures.

### Integrated bot

Seventeen source test modules were found outside compiled cache. They cover onboarding, account/auth behavior, payments/subscriptions, reminders, Navid/student assistant, bot exams, keyboard invariants, Bale identity, protected booklet delivery, PDF fingerprinting, forensic attacks and deploy notifier behavior. Numerous live probes/benchmarks are host-specific and must not run in CI without safe fakes/fixtures.

### VoiceTranscriberBot

Twenty-eight source test modules cover database, payments/continuations, providers, prepaid STT, privacy, artifacts/cache/recovery, Telegram media/parallel transport, AI transforms/budget/catalog, presentation/PPT audio and user analytics. Live smoke scripts and production probes are separate from ordinary tests.

### Required FANOOS CI gates

1. Formatting/syntax/static analysis and unit tests for every package.
2. Migration dry-run/idempotency/count/checksum tests on sanitized fixtures.
3. Tenant isolation/RBAC negative matrix, including cross-tenant object, search, notification, payment and bot access.
4. Payment duplicate-callback/crash/reconcile and entitlement-revocation tests.
5. Upload/property/security tests and protected-delivery resource/forensic regression.
6. UTF-8/Persian text and deterministic content-build checks.
7. Secret/dependency/license/security scanning and generated-artifact guard.
8. Build one immutable deploy artifact, attach provenance/checksums, and promote the same artifact across environments.

## Health, monitoring, restart, and notifications

Current controls include website live health/freshness probes, systemd `is-active`/restart-count checks, bot/site loopback health endpoints, deployment verification scripts, journal inspection commands, and durable lifecycle notifications.

The durable notifier contract is worth preserving:

- enqueue before delivery;
- stable event ID;
- independent Telegram/Bale/website attempts;
- per-channel receipt and retry;
- exactly one terminal lifecycle state;
- owner-only website record with no notification feedback loop;
- secrets outside command arguments, logs and manifests.

Missing from inspected sources is a coherent metrics/tracing/SLO platform. Prompt 4 should define:

- availability, latency, queue lag, payment verification, object transfer, delivery and backup-age metrics;
- structured correlation IDs spanning browser/API/job/bot/provider;
- safe logs with redaction and retention;
- alert thresholds, routing, maintenance mode and runbook ownership;
- readiness vs liveness and dependency-degraded status;
- restart policies with crash-loop alerts rather than infinite silent restart.

## Operational source map

| Concern | Primary legacy sources |
| --- | --- |
| Website deploy contract | `DENT/DEPLOY.md`, `scripts/complete_task.ps1`, `deploy_public_html.ps1/.sh` |
| Runtime-data boundary | `DENT/server-only/README.md`, ignores, `snapshot_remote_storage_verified.py` |
| Website validation | `DENT/.github/workflows/**`, `scripts/run_static_checks.sh`, `check_*`, `test_*` |
| Upload/download | notes/content-tools download-host modules, direct-upload actions, `check_upload_pipeline_config.php`, `upload_public_file_verified.py` |
| Bot runtime | `BOT/ops/systemd/**`, `dent_bot/{config,runtime,service,health,site_health}.py` |
| Bot deploy/diagnosis | `BOT/scripts/deploy-*.ps1`, `check-*.ps1`, `diagnose-*.ps1`, `run-*-smoke*` |
| Bot backup/restore | `BOT/scripts/backup-vps-state.ps1`, `restore-vps-state*.ps1`, `verify_vps_backup.py` |
| Notification lifecycle | `BOT/deploy_notifier/**`, `docs/DEPLOY_STATUS_NOTIFIER.md`, installer/systemd unit |
| Protected worker ops | `BOT/dent_bot/{protected_media,pdf_fingerprint,forensic_detector}.py`, forensic/benchmark scripts/tests |
| Mature release rollback | `VOICE/scripts/deploy-production.ps1`, `ops/verify-release.sh`, systemd units |
| Mature SQLite backup/restore | `VOICE/scripts/{backup-production,restore-production,run-scheduled-backup}.ps1` |

## Prompt 4 readiness checklist

Before connecting to a new FANOOS environment, Prompt 4 should request only the new access inputs named by that prompt, then inspect:

- OS/version, CPU/RAM/disk and expected growth;
- web/reverse proxy/TLS/DNS/firewall/network/proxy constraints;
- service manager or container/orchestrator availability;
- database, object storage, queue/cache and backup targets;
- filesystem ownership, secret injection, log/journal locations and clock synchronization;
- deployment identity/least privileges and GitHub environment protections;
- capacity for PDF/media/content workers and failure isolation.

Do not ask the user to reconstruct legacy infrastructure; the sources above are the baseline. Do not run the legacy canonical deploy scripts against the new repository or host.
