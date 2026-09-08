# Stage 8 — One-Time Codex Bootstrap / Staging / Cutover Handoff

This is the canonical **one-time** runtime handoff after Stage 8 repository closure. It does not authorize ad-hoc redesign or production mutation before staging gates pass.

`RUNTIME_VALIDATION_REQUIRED=true`

## A) Exact release candidate SHA

Repository identity is fixed: `ArianGhsm/FanoosLearn`.

```text
STAGE8_RELEASE_CANDIDATE_SHA=<SET_FROM_FINAL_MERGED_MAIN_SHA>
```

The exact 40-character value is supplied in the final Stage 8 merge handoff/PR evidence after this document is merged. It cannot safely self-embed its own final commit hash. Codex must reject a missing/placeholder value, branch name, short SHA, PR head, “latest main”, dirty checkout, wrong repository, or SHA whose exact main CI is not successful.

Starting accepted UX state for provenance: `aab498bee84f08979ec6bc6c5f4f0ffc03707b21`.

Before any environment mutation, Codex returns: resolved repository full name, `origin` URL classification, current HEAD, supplied candidate SHA, ancestry/main membership, clean-tree result, and exact GitHub CI evidence.

## B) One-time bootstrap

Codex performs only environment/runtime work that cannot be proven from GitHub source. No legacy repository is writable.

### Runtime packages

Verify/install supported PHP CLI/web SAPI and required extensions (`PDO`, `pdo_mysql`, `fileinfo`, `mbstring` and project-required extensions), compatible MySQL/MariaDB client/server, Python supported by repository CI, Git/archive tools, and for protected-media runtime qpdf/pdfinfo/pdftoppm plus Python Pillow and an approved Persian-capable font when required. Record versions, not package-manager secrets.

### Database and storage

Provision Fanoos-only staging then production SQL identities with least privilege. Production SQL is canonical data. Provision a private object root/bucket outside public web root and immutable releases; verify quota, atomic/write semantics, SHA-256 capability, backup/versioning and restore path. Do not populate production state from Git or a development database.

### Service users and processes

Create/separate runtime identities as practical for Platform/web, Telegram, Bale, protected-media worker, notification worker/projector and privileged updater. Telegram/Bale use independent token/process/offset/local-state paths. Web/bots/workers cannot obtain updater private Git credential or arbitrary root shell.

Use tracked updater templates `ops/updater/fanoos-updater.service.example` and `ops/updater/fanoos-updater.timer.example`. The updater executes `scripts/ops/update-runner.php` without positional request commands.

### GitHub deploy credential

Install a read-only credential scoped to `ArianGhsm/FanoosLearn` for the privileged updater checkout. It stays outside PHP/bot API environments and is never returned through Telegram/Bale/status APIs.

### Fanoos secrets

Generate **new Fanoos** values outside Git for DB, service HMAC identities, subject lookup/encryption, session/application signing, object/download/protected-delivery, payment provider if enabled, Telegram/Bale tokens and updater/runtime needs. Do not reuse Dentistry1402TUMS or VoiceMatnAIBot tokens, keys, databases or env files. Register service identities/action allowlists using `scripts/ops/register-service.php`; register the fixed deployment target using `scripts/ops/register-deployment-target.php`.

### Durable/private directories

Create private shared config/env, object/content, bot/worker local state, logs, backups, updater checkout, immutable releases, temporary media processing and atomic current-pointer roots. Runtime state/backups/logs/env never enter Git or release artifacts.

### Backup directories and policy

Configure SQL dump/provider snapshot and object backup destinations, retention/quota/ownership, encryption where available and alerting. Run `scripts/ops/backup.php`, then independently run `scripts/ops/verify-backup.php`. No migration/activation proceeds if verification fails.

## C) Staging rehearsal

Follow `08_STAGING_REHEARSAL.md` in order. Mandatory minimum:

1. checkout exact supplied SHA and prove clean canonical repository;
2. bootstrap runtime dependencies/new secrets/staging DB/private storage/services;
3. migrate/seed and run repository checks;
4. create immutable read-only legacy snapshot or sanitized rehearsal snapshot;
5. generate normalized version-1 private bundle using runtime HMAC target-ID derivation;
6. `--validate`, `--dry-run`, explicit `--apply`;
7. run `legacy-reconcile.php` and content/object reconciliation;
8. run deterministic suite/runtime compatibility checks;
9. real web smoke including Persian RTL/workspace/tenant-safe reads;
10. live Telegram + Bale staging smokes;
11. protected-media safe synthetic PDF flow;
12. SQL/object backup + independent verification + isolated restore rehearsal;
13. updater authorization/candidate/backup/test/migration/activation/health dry-run/rehearsal;
14. process restart/recovery and safe rollback rehearsal;
15. return evidence packet.

For the actual legacy source, read only documented/live canonical production storage. First record source snapshot SHA-256 and redacted counts, then transform. Do not infer canonical truth from logs, bot cache, analytics or source-code examples. The private source/export/bundle stays outside Git.

## D) Production cutover

Production execution requires explicit user authorization after staging acceptance. Follow `08_PRODUCTION_CUTOVER_ROLLBACK.md` exactly: final source delta/fingerprint, verified pre-cutover backup, migration dry-run/apply/reconciliation, exact-SHA immutable release, migrations, Platform activation, workers, Telegram, Bale, health/smokes, protected-delivery smoke, payment safe smoke only if real provider enabled, traffic switch and observation window.

Legacy remains available as rollback/reference until observation acceptance. If legacy writes continue between staging snapshot and cutover, freeze/coordinate writes or produce a separate fingerprinted final delta; never mutate an old completed batch to absorb changes.

## E) Health / smoke requirements

Codex must observe, not assume: HTTPS liveness/readiness; DB connectivity/schema ledger; private object read/write/checksum; authenticated two-workspace tenant isolation; login/session; schedule/grades/resources/search; Telegram provider identity/send/callback/restart; Bale provider identity/fallback/restart; notification claim/send/receipt/retry; protected-media source/worker/publish/derivative/send; current entitlement reauthorization; Update Server deny/allow/request/status; updater service/timer; backup state; service restart health.

If paid commerce is enabled, use the real provider’s documented safe/test/no-cost path and verify server-authoritative callback/verification. Otherwise payment initiation stays disabled. Never promote `FakePaymentGateway` or a client-visible success page into production payment evidence.

## F) Rollback

Application/service failure with compatible schema: atomically restore previous application pointer, restart fixed services and smoke. Do not reverse SQL automatically. Bot/worker-only failure: roll back that service while retaining canonical DB/object data. Data/schema corruption: stop writes as needed and choose forward repair vs explicitly supervised verified backup restore, quantifying writes that would be lost. Provider outage: degrade/fail closed; do not manufacture payment success or send required-protected content unprotected.

A failed backup, restore rehearsal, migration reconciliation, tenant probe, protected-media authorization, exact-SHA check or health check blocks production.

## G) Evidence Codex must return

Return one concise structured report with no secrets or personal payloads containing:

- repository full name, supplied exact SHA, active exact SHA, clean-tree and main/CI proof;
- OS/PHP/Python/DB/PDF-tool versions and disk/memory headroom;
- DB/storage/service/updater topology and least-privilege identities by safe name only;
- migrations/seeds/checks result;
- legacy snapshot SHA-256, bundle SHA-256, batch IDs and redacted source classification/count summary;
- relational reconciliation JSON summary and content/object count/bytes/checksum summary;
- deterministic suite result;
- web/browser smoke matrix;
- Telegram/Bale provider identity and smoke/restart results;
- protected-media fixture checksum/authorization/send result;
- backup ID/size/checksum/manifest verification and isolated restore result with observed duration;
- updater authorization/candidate/gate/activation/health/rollback rehearsal states;
- production cutover steps actually executed (if explicitly authorized), observation findings and active/rollback SHAs;
- failures with exact safe command/check, bounded error/failure code and redacted log excerpt;
- final `COMPLETE`, `PARTIAL` or `BLOCKED`, plus remaining runtime risks.

Do not claim a step succeeded because its repository test exists. Runtime steps require observed evidence from the target/provider.

## H) Post-bootstrap operating rule

After one supervised bootstrap/staging/cutover succeeds, **normal future updates are requested from the private Telegram owner Update Server and executed by the fixed privileged updater against reviewed canonical GitHub `main`**. Codex is not required for routine deployment.

Codex returns only for exceptional runtime troubleshooting, infrastructure changes, data-recovery/restore operations or a new supervised environment bootstrap. A runtime fix with a source/declarative counterpart must return through a GitHub branch + ChatGPT review/CI before becoming the baseline. Broad architecture/contract/schema changes return to ChatGPT development rather than being patched ad hoc on the server.

NO SECRET VALUES. NO LEGACY WRITE. NO ROUTINE DEPLOY FROM A DIRTY/UNREVIEWED CHECKOUT.
