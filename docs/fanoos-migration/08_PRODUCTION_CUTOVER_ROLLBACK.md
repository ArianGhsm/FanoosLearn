# Stage 8 — Production Cutover / Rollback Plan

Status: **plan only; this Chat does not deploy or mutate production**.

Production cutover may start only after the exact Stage 8 release-candidate SHA has green GitHub CI and the one-time staging rehearsal is accepted with real backup/restore, migration reconciliation, browser/provider and updater evidence.

## PRE gate

| Gate | Required state |
| --- | --- |
| Release | exact `STAGE8_RELEASE_CANDIDATE_SHA` from merged `main`; clean canonical repo |
| CI | main CI success on that exact SHA |
| Staging | accepted evidence packet; no unresolved Critical/High defect |
| Legacy snapshot | immutable source export + SHA-256 + classification inventory |
| Migration | staging apply/reconciliation PASS; final delta procedure ready |
| Backup | current production SQL/object backup verified |
| Restore | isolated restore rehearsal PASS |
| Domain/TLS | final routing/certificate/readiness verified |
| DB/storage | Fanoos-only canonical SQL/private object storage ready |
| Telegram/Bale | new Fanoos tokens/service identities ready; no legacy token reuse |
| Protected media | runtime tools/limits/private temp paths verified |
| Service auth | least-privilege service registrations + independent secrets ready |
| Updater | dedicated runner/service/timer + canonical GitHub read credential ready |
| Rollback | previous compatible application SHA/pointer known; schema compatibility decision recorded |
| Legacy | legacy product remains available as rollback/reference until observation accepted |
| Paid commerce | real provider validated, or payment initiation deliberately disabled |

## CUTOVER sequence

1. Announce/coordinate the cutover window and decide whether legacy writes must be paused/read-only. If they remain writable, define the exact final delta boundary first.
2. Capture the final legacy incremental export, fingerprint it, transform only approved classes and run validate + dry-run.
3. Take and independently verify the final pre-cutover Fanoos SQL/object backup.
4. Apply the final normalized relational migration/content-object delta with explicit confirmation.
5. Run machine reconciliation and manual high-risk samples (roles, grades, payments, entitlements, protected objects). Stop on any mismatch.
6. Verify the deployment checkout still resolves to the exact approved Fanoos repository and release candidate SHA; never “pull latest” as a substitute.
7. Run migration preflight and canonical schema migrations/seeds for the exact release.
8. Stage the immutable release and run pre-switch readiness.
9. Atomically activate Platform/web/API.
10. Start/enable required workers, then Telegram, then Bale according to dependencies. Each service uses its own runtime state and credentials.
11. Run liveness/readiness, authenticated tenant-safe web smoke, Telegram/Bale smoke, notification receipt, protected-media synthetic smoke and payment no-cost/safe smoke if payments are enabled.
12. Switch final user traffic/routing only after health checks pass. If routing already points at the current pointer, treat pointer activation as the traffic switch.
13. Record exact active SHA, schema migration ledger, service versions/status, migration batch IDs and backup ID in the private cutover record.

No bot/client can bypass these gates. The Telegram Update Server creates a durable intent; the privileged updater still resolves canonical `origin/main`, exact SHA, CI, backup, test/migration and health policy itself.

## POST observation

During the observation window monitor safe operational metrics: HTTP error/readiness state; worker/bot restart loops; notification lease/receipt age; object/media job failures; DB errors/locks; storage/disk growth; login/session failures; tenant-scope denials/anomalies; order/payment status transitions; entitlement grants/revocations; protected-delivery failures; updater terminal state. Compare destination counts to accepted migration reconciliation and re-run high-risk consistency queries after normal writes begin.

Do not expose raw provider payloads, personal identifiers, secrets or full private paths in broadly shared status reports.

The cutover is accepted only after a defined observation window has no unexplained tenant leakage, financial/access inconsistency, data-count drift, unresolved protected-content failure, backup problem or recurring service failure.

## ROLLBACK decision tree

### Application/service regression, schema still compatible

Keep production SQL/object data authoritative. Atomically restore the previous known-good application pointer/release, restart fixed services, run readiness + web/bot smokes, and record the rollback. Do **not** reverse database migrations automatically.

### Bot/worker-only regression

Stop/rollback the affected service artifact/config while leaving canonical Platform data intact. Ensure pending leases/receipts/jobs recover idempotently. Never restore an old bot-local database as domain truth.

### Migration/data corruption or incompatible schema

Stop writes/traffic as required and escalate to supervised data recovery. Evaluate whether a forward repair is safer than restore. If restore is necessary, select the verified backup, quantify writes after that backup, use the destructive restore procedure with explicit confirmation, restore SQL/objects together as required, verify manifests/checksums/schema and rerun reconciliation. Application pointer rollback alone is not a data rollback.

### External provider failure

If Telegram/Bale/payment provider is unavailable but canonical data is intact, fail/degrade only according to capability policy. Do not manufacture success or weaken protected delivery. Paid commerce can be disabled while retaining orders as pending/failed. Bale protected delivery remains fail-closed.

## Absolute stop conditions

Cutover is `BLOCKED` on any Critical/High source/runtime defect, exact-SHA/CI mismatch, dirty/wrong repository checkout, failed or unverifiable backup, failed isolated restore, ambiguous legacy snapshot, failed migration dry-run/reconciliation, cross-tenant mismatch, unverified payment promoted to success, entitlement inconsistency, object checksum gap, protected bytes sent without required protection, arbitrary update-control input, unhealthy activation, or unknown schema compatibility for rollback.

## After successful one-time cutover

Normal future code releases follow the reviewed GitHub `main` → owner Telegram Update Server → fixed privileged updater path. Codex is not required for routine deployment after the updater/bootstrap is proven; it returns only for exceptional runtime troubleshooting or a supervised infrastructure/data recovery operation.

`RUNTIME_VALIDATION_REQUIRED=true`
