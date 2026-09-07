# Pre-Stage 7 Platform Quality Audit

Audit date: 2026-09-07

Repository: `ArianGhsm/FanoosLearn`

Audited baseline: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`

Audit branch: `audit/pre-stage7-platform-quality`

Stage 7 readiness: **BLOCKED**

## Executive conclusion

Stages 1–6 produced a credible multi-tenant platform foundation. The strongest parts are the canonical workspace model, scoped RBAC without an `is_admin` bypass, server-owned payment proof, entitlement separation, immutable content versions, protected-delivery authorization, and tenant/object isolation tests.

Stage 7 must not start as a feature implementation yet. Four classes of HIGH-risk defects remain in already-delivered foundations:

1. interrupted SQL migrations are not reliably rerunnable because `MigrationRunner` records a file only after all statements complete while `0006_core_platform.sql` contains several MySQL `ALTER TABLE ... ADD ...` statements that can auto-commit independently;
2. the canonical deploy script creates a backup but does not run the repository's backup verifier before migration/activation, despite the fail-closed runbook claim;
3. `contracts/openapi/core-v1.yaml` is materially behind `ApiKernel`, and there is no machine-testable internal service-auth/replay contract for Stage 7 clients/workers;
4. there is no durable, locked deployment-control lifecycle suitable for a remote owner-triggered “Update Server” request, nor a machine-enforced canonical-main/CI/forward-migration compatibility gate.

No CRITICAL finding was identified in the inspected baseline. The HIGH findings above are sufficient to set the Stage 7 quality gate to `BLOCKED`.

## Pre-flight verification

| Item | Result |
| --- | --- |
| Repository lock | PASS — write scope is only `ArianGhsm/FanoosLearn` |
| Main SHA | `5695d3ebcff52aa9bebe2782617e749bb093bdfb` |
| Expected baseline | PASS — expected and actual main SHA match |
| Recent history | Workflow Migration 0 is latest main commit; Stage 1–6 commits precede it |
| Open PRs before audit | none |
| CI on main | PASS — latest GitHub Actions run for the baseline completed successfully |
| Main protection | GAP — branch reports `protected: false`; repository workflow rules remain procedural rather than GitHub-enforced |
| Production changes | none |
| Deploy performed | no |

## Evidence method

Every material claim was evaluated in the order:

`DOCUMENTATION -> CODE -> TEST -> CONTRACT -> RUNTIME ASSUMPTION`

Statuses used: `PASS`, `PASS_WITH_GAPS`, `PARTIAL`, `FAIL`, `DEFERRED_BY_DESIGN`, `NOT_VERIFIABLE_WITH_CURRENT_ACCESS`.

Severities used: `CRITICAL`, `HIGH`, `MEDIUM`, `LOW`, `INFO`.

## Reference repositories

### Dentistry1402TUMS

Read-only evidence was inspected from `ArianGhsm/Dentistry1402TUMS`, including repository rules, deploy policy, and `public_html/api/bot_store.php`.

The useful behavioral oracles are confirmed:

- code and runtime data are different authorities;
- runtime state is not deployed from Git;
- bot/site service requests use timestamp + nonce + body-bound HMAC with replay rejection;
- protected delivery derives traceable user-bound identity;
- deploy lifecycle is expected to be bounded, health-checked and observable.

A standalone GitHub repository named `Dent1402Bot` or `IntegratedDent1402Tums` was not found through the connected GitHub account search. Direct standalone-repository verification is therefore `NOT_VERIFIABLE_WITH_CURRENT_ACCESS`; this audit uses committed Dentistry references as allowed by the audit contract and does not request legacy secrets/runtime state.

### VoiceMatnAIBot

Read-only evidence was inspected from `ArianGhsm/VoiceMatnAIBot`, including architecture, deployment, backup/restore, server migration, Telegram/Bale capability/parity, processing UX, repository control, and server-state documentation.

The relevant oracles are confirmed:

- Git is code authority, not runtime-state backup;
- releases are exact/immutable and activation is atomic;
- backup acceptance includes restore verification;
- critical paid/active operations constrain restart/update;
- Telegram/Bale adapters share semantics while transport capability differences are explicit;
- live/capability evidence is required before claiming platform parity;
- durable status must survive UI/progress failure and restart.

FANOOS correctly does **not** copy Voice's separate Telegram/Bale product databases. FANOOS remains designed around one canonical backend identity/workspace/payment/content/entitlement authority.

## Stage 1 — Forensic / Reuse

Status: **PASS_WITH_GAPS**

### Verified strengths

- `01_REUSE_MAP.md`, `01_FEATURE_INVENTORY.md`, `01_FORENSIC_AUDIT.md`, and `01_HARDCODING_AND_TECH_DEBT.md` consistently treat legacy code as read-only behavioral evidence rather than a FANOOS runtime dependency.
- Source-to-target provenance is explicit for identity, tenancy, payment, content, exams, protected delivery, operations and bot linking.
- The target repository does not expose legacy repository paths as runtime dependencies.
- Active inspected FANOOS application/test code uses generic workspace, institution, course and fixture identities; legacy TUMS/Dentistry/1402 constants are not part of the implemented tenant model.
- DentNote is deliberately represented as data (`discipline_note` + `format_key`) rather than a Dentistry-specific code branch.

### Gaps

- Historical Stage 1 evidence for the integrated bot came from a local tree without a directly verifiable standalone GitHub repository. This limits provenance verification for that source to the recorded forensic evidence.
- Stage 1 reference commit snapshots can naturally drift from today's reference-repository heads. Any future literal behavior extraction must re-pin the exact source commit before implementation.

Risk: LOW/MEDIUM. No evidence of an active legacy runtime dependency was found.

## Stage 2 — Architecture

Status: **PASS_WITH_GAPS**

### Verified strengths

- One FANOOS-only monorepo and one canonical PHP platform boundary are present.
- One canonical relational authority is used for shared domain state.
- Workspace is consistently the tenant root; user identity is global and access is scoped.
- Bot/worker applications have not created a parallel domain database.
- Web/bot/future-app direction is explicitly backend-contract-first.

### Architecture drift

`02_MODULE_AND_DATA_OWNERSHIP.md` and ADR-002 describe enforceable Identity, Tenancy/Authorization, Academics, Content, Exams, Grades, Forms, Commerce, Entitlements, Notifications, Search, Audit, Integrations and Jobs ownership boundaries and prohibit cross-module SQL leakage.

Current code is less modular than that documentation. `apps/platform/src/Core/WorkspacePlatformService.php` directly queries/writes directory, tenant, academic, grade, notification, form, search, RBAC/outbox/audit families. `ExamService` also lives under Content. This does not currently create a second authority, but module ownership is convention/documentation rather than an enforceable code boundary.

Severity: MEDIUM. Required before scale/extraction, but not by itself a Stage 7 safety blocker.

## Stage 3 — Database / Multi-Tenancy / RBAC

Status: **PARTIAL**

### Verified strengths

- Geography/institution/faculty/program/cohort/workspace hierarchy is data-driven.
- Tenant-owned tables carry `workspace_id` and many cross-aggregate relationships use composite tenant-aware FKs.
- Same course/resource names can exist in different workspaces without collision.
- `ScopeAuthorizer` is deny-by-default, reconstructs canonical ancestry, validates exact workspace/scope, checks assignment validity/revocation and membership requirements, and has no hidden `is_admin` bypass.
- `TenantIsolationTest` proves representative isolation, route/workspace mismatch denial, valid multi-workspace membership, explicit platform administration, cross-workspace FK rejection, same-workspace natural-key rejection, cross-workspace same-name allowance, migration rerun after a successful run, and legacy-map conflict behavior.
- Migration ledger stores name + SHA-256 and is guarded by a database advisory lock.

### HIGH — interrupted migration is not guaranteed rerunnable

`MigrationRunner` executes SQL statements sequentially and writes the migration ledger only after the whole file completes. MySQL DDL may auto-commit. `0006_core_platform.sql` contains multiple `ALTER TABLE ... ADD COLUMN/CONSTRAINT/KEY` operations without `IF NOT EXISTS`.

Failure after an early `ALTER TABLE` but before the file is recorded can leave a partially applied schema. A rerun then encounters already-created columns/constraints and may fail again. Existing CI proves only successful first-run + successful no-op rerun, not mid-file fault recovery.

This contradicts a broad interpretation of “idempotent/rerunnable migrations” and is unsafe for unattended owner-triggered deployment.

Required remediation before Stage 7 update control:

- establish an explicit migration safety policy for MySQL DDL;
- make each new migration resumable or single-effect where practical;
- add a deterministic fault-injection test for partial DDL application/recovery;
- preflight schema compatibility before release activation;
- never infer application rollback means schema rollback.

### Additional test gaps

- explicit revoked/expired assignment and ended-membership negative tests are not yet first-class release cases, although runtime predicates exist.
- service identities/replay state are intentionally deferred to Stage 7 contract preparation.

## Stage 4 — Infra / Backup / Deploy

Status: **PARTIAL**

### Verified strengths

- release artifacts are built from an exact commit and hashed;
- runtime state/secrets are excluded from Git/release paths;
- private object addressing, upload inspection and signed object access exist;
- backup creation stages SQL + object state and writes a manifest/READY marker;
- a separate verifier recomputes backup integrity;
- isolated restore-test tooling validates the backup before restore and uses explicit destructive-test guards;
- deploy uses immutable release directories, exact 40-char SHA, health checks, atomic symlink activation and application-pointer rollback on post-switch failure;
- production host activation is honestly still gated by unresolved hosting/runtime/restore evidence.

### HIGH — canonical deploy does not verify its newly created backup

`04_BACKUP_RESTORE_RUNBOOK.md` requires creation **and verification** before destructive/release work. `scripts/ops/cpanel-deploy.sh` calls `backup.php` but does not invoke `verify-backup.php` before migrations and activation.

The verifier existing elsewhere is not equivalent to a fail-closed deploy gate. A remote update path must not rely on an operator remembering a second command.

### HIGH — no durable deployment-control lifecycle

The current shell path has filesystem release markers and console output, but no durable deployment operation record with request ID, requested SHA, current SHA, phase, timestamps, backup ID, test/health results, terminal outcome and rollback linkage. There is also no updater lock/idempotency contract designed for an authenticated remote request.

This is required before an owner bot can safely initiate an update and survive bot/backend/updater restart.

### HIGH — canonical-main/CI/update candidate verification is incomplete

Current deploy validates an exact SHA in the checked-out repository but does not itself prove that the SHA is the approved current `main`, a descendant of the active release, or associated with a successful required CI run. For a future owner update, arbitrary callback-provided refs must never be accepted.

### HIGH — application rollback does not prove schema compatibility

Migrations run before activation and are not reversed by `cpanel-rollback.sh`. The runbooks correctly prefer expand/contract, but backward compatibility with the previous application is policy, not a machine gate. The interrupted-migration defect above increases this risk.

### MEDIUM findings

- `main` is not protected by GitHub branch protection at the audited baseline; direct-write prevention is procedural/tooling-based.
- CI does not perform a real restore rehearsal or release activation/rollback test.
- cPanel staging/production capability remains partially unverified, including exact PHP/runtime limits and a dated isolated restore rehearsal.
- current Codex runtime-handoff docs still describe Codex as routine deploy operator; this is documentation drift from the new one-time-bootstrap + owner-updater direction.

## Stage 5 — Core

Status: **PARTIAL**

### Verified implemented behavior

Evidence supports the implemented/core rows for:

- canonical account, session, revocation, CSRF and login throttling;
- workspace memberships/selection;
- scoped representative/admin behavior;
- academics, schedule and own published grades;
- announcements/forms/search;
- server-priced order creation and immutable order snapshots;
- provider-verified/idempotent payment callback semantics and reconciliation;
- entitlement grant/revoke/check;
- protected-resource authorization foundation;
- audit events and scoped/global dashboards;
- API envelope and RTL shell foundation.

The fake payment adapter remains correctly limited to development/test. OTP/recovery, full grade import, form export, live payment provider, institution connector and browser E2E remain foundation/deferred and are **not** production-ready claims.

### HIGH — OpenAPI is not implementation-complete

`ApiKernel` implements routes absent from `contracts/openapi/core-v1.yaml`, including examples such as:

- announcement read receipt;
- representative assignment;
- payment reconciliation;
- entitlement grant/revoke;
- resource version creation/review-request/review/publish;
- assessment review/publish and attempt-review paths.

Conversely, `06_CONTENT_ENGINE.md` claims some review/publish routes are in the OpenAPI contract when the audited YAML does not define them. The OpenAPI success schema is also deliberately generic and does not provide operation-specific payload contracts.

Because Stage 7 clients are required to consume stable shared APIs, this is a HIGH pre-Stage-7 contract blocker.

### HIGH — browser session contract is being reused as non-browser auth

The current API supports bearer/session-token authentication, but `ApiKernel` requires CSRF for every authenticated mutating request regardless of whether the caller is a browser cookie client or a service/bot bearer client. There is no separate least-privilege service identity, key ID, timestamp, nonce, body digest, replay store or service-action authorization comparable to the Dentistry oracle.

Stage 7 must not reuse a human session + CSRF token as worker/bot service authentication.

## Stage 6 — Content

Status: **PASS_WITH_GAPS**

### Verified implemented behavior

- one resource aggregate and immutable versions;
- structured lecture/discipline/DentNote/summary/question-bank/past-exam paths;
- derivation lineage;
- independent review and explicit publish;
- uploaded-object inspection/checksum/private storage;
- entitlement-aware access;
- signed issuance/consume flow with reauthorization;
- issuance-bound visible/forensic identity;
- delivery/access event logging;
- bulk import replay/duplicate/change behavior;
- revisioned exam attempts, answer hiding, server scoring and review;
- cross-tenant same-name resources and cross-tenant assessment denial.

### Correctly deferred / contract-only

The following are not production implementations and must remain marked accordingly:

- Telegram/Bale actual forward-protected send behavior;
- byte-level PDF rasterization/fingerprinting and attack-regression worker;
- protected-media worker runtime;
- transcription/rendering worker;
- channel delivery receipts.

This deferment is healthy. The Stage 6 backend security contract is useful; the Stage 7 transport/worker contracts still need to be frozen before coding.

## API / contract audit

The core contract is usable for the current web slice but not yet an adequate multi-client/service contract.

Key required pre-Stage-7 additions/fixes:

- reconcile OpenAPI paths with `ApiKernel` bidirectionally;
- operation-specific request/response schemas for Stage 7-consumed endpoints;
- separate browser-cookie CSRF rules from service-auth rules;
- explicit service authentication with key ID, timestamp, nonce/replay prevention, body digest and allowed action/scope;
- explicit idempotency semantics for external writes;
- stable error envelope/code catalogue where bots depend on retry/fallback behavior;
- no secret fields or raw storage paths in any service response.

## Test-quality audit

Current deterministic CI is meaningful but incomplete.

PASS coverage includes PHP lint, DB contract checks, text/secret/runtime-state guards, exact release artifact building, MySQL integration, storage security, backup contract, tenant isolation, core platform and content engine.

Claims that remain weaker than release-grade proof:

- interrupted migration recovery;
- real backup restore rehearsal in CI/staging;
- activation + automatic rollback lifecycle;
- browser E2E/session-cookie behavior;
- service-auth replay/key-rotation behavior;
- RBAC expiry/revocation negative matrix;
- live payment adapter/provider callback fixtures;
- Telegram/Bale capability/live-smoke behavior;
- worker restart/lease/idempotency behavior;
- production-shaped load/query-plan tests.

## Security audit summary

| Area | Result |
| --- | --- |
| Secret/runtime-state Git guards | PASS_WITH_GAPS — good path/pattern guard; not a full entropy/provider-secret scanner |
| Tenant authorization | PASS |
| Object path traversal/isolation | PASS for inspected storage adapter/tests |
| Authorization before protected serve | PASS — issue and consume reauthorize |
| Payment proof | PASS — provider verification is authoritative |
| Payment callback idempotency | PASS for tested state transitions |
| Service auth/replay | DEFERRED / HIGH pre-Stage-7 gap |
| Audit leakage | PASS_WITH_GAPS — current callers are restrained, but `AuditLogger` has no central metadata allowlist/redactor |
| Deploy/update attack surface | PARTIAL / HIGH until control plane is designed |

## New operations direction reconciliation

The new target operating model is accepted as the future architecture direction:

`ChatGPT + GitHub -> source/review/CI/integration`

`Codex -> one-time server bootstrap/setup + exceptional troubleshooting`

`Owner Telegram action -> authenticated backend/control service -> privileged updater -> exact approved main SHA`

This audit does **not** implement that feature. Existing Codex handoff documentation must later be rewritten so routine releases no longer depend on manual Codex deployment.

## Quality gate

**Stage 7 readiness = BLOCKED**

Blocking remediation categories:

1. migration interruption/recovery safety;
2. verified-backup integration into canonical deploy;
3. shared API/OpenAPI reconciliation plus service-auth/replay contract;
4. durable update-control lifecycle, canonical-main candidate policy and migration-compatible rollback semantics.

Once those HIGH findings are closed with deterministic tests, Stage 7 can be re-gated without redesigning the canonical tenant/payment/content model.
