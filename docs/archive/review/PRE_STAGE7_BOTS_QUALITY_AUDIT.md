# Pre-Stage 7 Telegram + Bale quality audit

Status: audit-only; no production bot implementation or deployment was performed  
Audit date: 2026-09-07  
FANOOS repository: `ArianGhsm/FanoosLearn`  
Audited `main` SHA: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`  
Historical baseline requested by the audit: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`  
Result: current `main` is exactly the requested historical baseline.

## Executive conclusion

Stage 1–6 domain foundations are materially usable for Stage 7: canonical accounts, memberships, RBAC, schedule, grades, announcements, content, commerce, entitlements, notification persistence, secure-delivery issuance/consume semantics, idempotency, generic jobs, audit, deploy guards, backup/restore and tenant-isolation tests already exist.

Stage 7 bot implementation is **not ready to begin against the current `main` contract surface**. The blocking dependency is the missing platform-to-bot handoff, not a need for the bot to invent its own domain model. `contracts/REGISTRY.md` already requires the missing Stage 7 boundary to be machine-testable before bot implementation. Current `core-v1` and `ApiKernel` expose browser/session-oriented APIs and do not provide the required service-authenticated bot identity/linking/update/delivery-worker boundary.

Overall readiness gate: **WAIT_FOR_PLATFORM**.

This is intentionally not marked `BLOCKED`: the intended security model is clear in the architecture and legacy references. The work is waiting on concrete platform contracts/implementations rather than an unresolved architectural ambiguity. The Telegram Update Server feature itself is **not ready** until the platform control plane exists.

## Repositories and refs inspected

| Repository | Mode | Ref inspected | Notes |
| --- | --- | --- | --- |
| `ArianGhsm/FanoosLearn` | read/write, audit docs only | `5695d3ebcff52aa9bebe2782617e749bb093bdfb` | Current `main`; only this repository was modified. |
| `ArianGhsm/VoiceMatnAIBot` | read-only | `5903509e94b272b44ad81c8ba69ea62e07a80b73` | Messaging/runtime reference only. No wallet/product DB model is reusable for FANOOS. |
| `ArianGhsm/Dentistry1402TUMS` | read-only | `e78fc96cd975c1275da5a65a5240d616f5840e10` | Identity/payment/delivery/deploy behavior reference only. No JSON canonical store, cohort hard-code, path, token or secret is reusable. |

### Inaccessible / deliberately uninspected areas

An independent `Dent1402Bot` / `IntegratedDent1402Tums` repository was not inspected. Under the repository lock, the audit used only references, APIs and deploy documentation available inside `ArianGhsm/Dentistry1402TUMS`; no behavior from an unavailable independent repository is assumed.

The following optional Site/Platform Chat handoff files do **not** exist at the audited FANOOS SHA:

- `docs/review/PRE_STAGE7_PLATFORM_QUALITY_AUDIT.md`
- `docs/review/STAGE7_PLATFORM_GAP_MAP.md`
- `docs/fanoos-migration/07_PLATFORM_BACKEND_CONTRACTS.md`
- `docs/fanoos-migration/07_UPDATE_CONTROL_PLANE.md`
- `docs/fanoos-migration/07_PLATFORM_HANDOFF_TO_BOTS.md`

No open FANOOS pull request existed when this audit branch was created. This must be checked again before any merge because Site/Platform work may start concurrently after this audit.

## FANOOS material inspected

Mandatory architecture/workflow/contracts:

- `AGENTS.md`
- `docs/DEVELOPMENT_WORKFLOW.md`
- `docs/CODEX_RUNTIME_HANDOFF.md`
- `contracts/REGISTRY.md`
- `docs/workflow/INTEGRATION_ONLY_PATHS.md`
- `docs/fanoos-migration/02_TARGET_ARCHITECTURE.md`
- `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md`
- `docs/fanoos-migration/03_RBAC_SCOPE_MATRIX.md`
- `docs/fanoos-migration/04_DEPLOY_RUNBOOK.md`
- `docs/fanoos-migration/05_FEATURE_PARITY_MATRIX.md`
- `docs/fanoos-migration/06_SECURE_DELIVERY_CONTRACT.md`
- `docs/fanoos-migration/06_CONTENT_PARITY_MATRIX.md`
- `contracts/openapi/core-v1.yaml`

Implementation/schema evidence additionally inspected:

- `apps/platform/src/Http/ApiKernel.php`
- `apps/platform/src/Identity/*`
- `apps/platform/src/Commerce/CommerceService.php`
- `apps/platform/src/Content/SecureDeliveryService.php`
- `apps/platform/src/Content/ProtectedResourceAuthorizer.php`
- `database/migrations/0002_identity_and_rbac.sql`
- `database/migrations/0004_domain_foundations.sql`
- `database/migrations/0005_platform_operations_and_migration.sql`
- current repository tree and test layout

## Stage 1–6 bot-dependency re-audit

### What is genuinely ready

The Stage 1–6 implementation provides strong canonical ownership boundaries rather than legacy bot-owned state:

- `iam_users` and verified identifiers are canonical; Telegram/Bale identifier types can be represented in schema.
- `tenant_workspace_memberships` is the membership authority.
- RBAC is scoped and deny-by-default; there is no valid reason for a bot to maintain an `is_admin` mirror.
- schedule, grades and announcements are tenant-scoped backend projections.
- content/resource/version/object metadata is backend-owned.
- commerce orders/payment attempts are server-owned and idempotent.
- entitlement decisions are backend-owned and fail closed.
- secure delivery already has issuance and consume semantics with reauthorization requirements.
- `notification_messages` / `notification_recipients` include Telegram and Bale channels.
- `platform_idempotency_keys`, `outbox_events`, generic leased `job_jobs` and service-capable audit records provide appropriate primitives for Stage 7.
- deployment/rollback docs require exact approved main SHA, verified backup, readiness and rollback.

This validates the bot invariants in the prompt: bots should be adapters/conversation UI, never an alternate canonical database.

### What is not yet a bot-safe contract

The existing HTTP boundary is browser/session oriented. `ApiKernel` authenticates normal calls with a bearer session, and non-GET mutations require CSRF. There is no service-auth middleware that binds a bot runtime to a linked canonical user projection. A Telegram/Bale bot must not fabricate browser sessions or bypass CSRF to compensate.

`contracts/REGISTRY.md` explicitly identifies the Stage 7 freeze checklist: messaging link challenge/confirm/revoke, active-workspace projection, delivery issue/consume/receipt, payment order/deep-link/result, notification delivery/receipt, protected-media job input/result and service auth/idempotency. At the audited SHA, several of these are absent from OpenAPI/runtime or only exist as domain primitives.

## Backend dependency readiness

Status definitions for this audit:

- **READY**: canonical domain implementation and stable current API behavior exist; the bot may consume it once the common service/link identity boundary is supplied.
- **CONTRACT_ONLY**: domain primitives/design exist but the Stage 7 machine-testable bot boundary is not complete.
- **MISSING**: required Stage 7 behavior has neither a current bot-facing contract nor an equivalent runtime route.
- **BLOCKED**: design is too ambiguous/high-risk to implement safely without a product/security decision.

| Dependency | Status | Evidence / gap |
| --- | --- | --- |
| Account-link challenge / confirm / revoke | **MISSING** | Identifier schema can represent Telegram/Bale, but no secure one-time challenge lifecycle or bot-facing routes exist. Architecture requires account proof rather than trusting platform ID. |
| Service authentication | **CONTRACT_ONLY** | Target architecture defines key-id + timestamp + nonce + body-digest signing and idempotency semantics; current HTTP runtime has bearer-session/CSRF only. No implemented service identity/replay boundary was found. |
| Workspace list/select | **READY** | Canonical memberships and `/workspaces` / selection behavior exist. Bot consumption remains transitively dependent on service auth + linked-user projection; the bot must not create its own workspace state authority. |
| Schedule | **READY** | Tenant-bounded backend projection exists. |
| Grades | **READY** | Own-grade backend projection exists with enrollment/tenant controls. |
| Announcements | **READY** | Canonical inbox/read behavior exists; push transport claim/receipt is separate and incomplete. |
| Content library | **READY** | Generic content library/resource engine and access filtering exist. |
| Payment order / deep link / status | **CONTRACT_ONLY** | Commerce implementation can create idempotent orders and returns a server-generated redirect URL; current Stage 7 bot projection/result contract is not frozen and live provider parity remains deferred. |
| Entitlement | **READY** | Backend entitlement grant/revoke/check is canonical and tested. A chat payment message can never substitute for this decision. |
| Protected delivery issue / consume | **READY** | Secure-delivery contract and implementation exist and require reauthorization. Bot send receipt and protected-media production step are not complete. |
| Notification delivery / receipt | **CONTRACT_ONLY** | Canonical notification/recipient tables exist, but there is no frozen service claim/lease/ack/receipt API for Telegram/Bale workers. |
| Protected-media job | **CONTRACT_ONLY** | Generic leased jobs and secure-delivery worker boundary are present conceptually; no frozen job input/result/claim contract exists. |
| Update status / request | **MISSING** | No `07_UPDATE_CONTROL_PLANE.md`, no update request/status API, no dedicated critical permission/control-plane contract. The bot must not call shell/deploy scripts directly. |

## Highest-severity findings

### P0 — Stage 7 service identity boundary is absent

A production bot cannot safely call the current mutation APIs by pretending to be a browser session. Adding a bot-side database/session shortcut would violate canonical ownership and account-link invariants.

Required Platform Chat outcome: a signed, replay-resistant, idempotent service boundary that resolves the already-linked platform principal to a canonical user and applies the same RBAC/workspace/entitlement checks as web.

### P0 — Secure account linking is absent from current FANOOS runtime

Telegram/Bale account ID is a channel identifier, not proof of a FANOOS account. The Platform Chat must define and implement one-time challenge issue/confirmation/revocation, bound to platform + platform user, with expiry/replay/conflict handling and canonical web/account proof.

The Dentistry reference demonstrates a useful pattern: website remains identity authority and a platform link becomes permanent only after website OTP/login proof. Its 1402-specific matching and JSON store must not be copied.

### P0 — Update Server control plane does not exist

The Telegram owner button cannot be implemented safely as a callback that executes Git, shell, branch or SHA input. The current deploy runbook is a human/operator path, not a bot control plane.

Before the button exists, Platform Chat must define durable update request/status ownership, critical platform permission, idempotency, one-active-update locking, updater privilege separation and terminal failure/rollback reporting. See `UPDATE_SERVER_BOT_UX_SECURITY_PLAN.md`.

### P1 — Notification and delivery receipts are incomplete for a restart-safe bot

Canonical notification persistence exists, but Stage 7 still needs a leased claim/ack protocol or equivalent durable delivery contract. The same is true for protected-send receipt semantics. Without it, restarts can cause duplicate or falsely acknowledged sends.

Dentistry's durable claim/lease/ack delivery queues are a behavior oracle; FANOOS must implement equivalent semantics in its canonical backend, not reuse Dent JSON files.

### P1 — Protected-media worker protocol is not frozen

FANOOS already defines trace/watermark identity and secure issuance/consume, but byte-level derivative production is intentionally deferred to Stage 7. A worker must receive canonical job input, produce a per-user/per-issuance derivative, return checksum/object metadata and never own entitlement state.

### P1 — Bale cannot currently satisfy FANOOS `forward_protection_required` direct-send semantics

The official Bale bot documentation checked on 2026-09-07 documents normal file delivery but does not document Telegram-equivalent `protect_content`. Therefore a protected direct Bale send must fail closed when the backend says forward protection is required. An alternate controlled web delivery is acceptable only if Platform/Product explicitly contracts it; this audit does not invent one.

### P2 — Current Voice platform constants must not be copied literally

Voice has an excellent adapter/capability pattern, but its current Telegram file-size constants reflect that project's transport/runtime choices. FANOOS should use an explicit capability registry based on its actual configured transport. For the ordinary Telegram Cloud Bot API, current official limits are 20 MB download and 50 MB general multipart document upload.

## Legacy reuse conclusions

### VoiceMatnAIBot — reuse patterns, not its data model

Reusable:

- shared semantic application layer + thin Telegram/Bale adapters
- explicit platform capability registry
- Telegram rich UI only where officially supported/stable
- Bale semantic fallback instead of fake ASCII tables
- separate tokens/processes/update streams/env/cache
- Persian/Unicode-safe message chunking
- callback acknowledgment before expensive I/O
- no fabricated progress percentage or ETA
- restart-safe durable operations
- owner-only controls
- deploy health/rollback and disaster-recovery completeness
- explicit unsupported capability status
- live smoke before claiming parity

Do not reuse:

- Voice wallet/product database or any separate canonical product/user state
- its environment, token, cache/session data or server paths
- transport-specific numeric constants without validating FANOOS's actual transport

### Dentistry1402TUMS — reuse security/operations behavior, not cohort state

Reusable patterns observed:

- signed service calls with timestamp/nonce/body digest and replay protection
- one-time account-link challenge with website identity authority
- strict platform identity validation
- fail-closed durable persistence behavior
- idempotent server-owned payment result handling
- leased notification/payment-result delivery and acknowledgments
- canonical protected-booklet identity inputs for watermarking
- owner deploy lifecycle notifications
- host-to-backup safety and no runtime state in Git

Do not reuse:

- Dent JSON canonical stores
- student-number/cohort-1402 hard-code or name matching as identity proof
- legacy secrets, sessions, tokens, paths or server service names

## Recommended Stage 7 layout

The current FANOOS tree has only `apps/platform/`; `packages/` does not yet exist. The target architecture already names `apps/telegram-bot/`, `apps/workers/` and `packages/python/`. Do not replace that top-level design for aesthetics.

Recommended minimal extension:

```text
apps/
  platform/                  # existing canonical backend
  telegram-bot/              # Telegram runtime + adapter only
  bale-bot/                  # Bale runtime + adapter only
  workers/
    protected-media/         # watermark/raster/derivative worker
packages/
  python/
    fanoos_bot/               # shared semantic use-cases, DTOs, backend client
```

`fanoos_bot` owns presentation-neutral conversation/use-case logic and the signed backend client. Telegram/Bale packages own platform payload parsing, keyboards, edits, chat actions, file IDs, callback acknowledgement, rate-limit handling and update offsets. The protected-media worker is a separate process and receives durable backend jobs; it does not import bot runtime state.

Separate Telegram and Bale processes/tokens/update streams are mandatory even when they import the same shared semantic package.

## Security disposition

| Threat / invariant | Audit disposition |
| --- | --- |
| Platform ID treated as global identity proof | Prohibited; secure link challenge required. |
| Link replay / account takeover | Platform must bind challenge to platform principal, expiry, one-time consume and canonical account proof. |
| Service request replay | Signed request + timestamp + nonce + body digest + durable replay defense required. |
| Callback tampering | Callback is untrusted input; compact opaque/signed reference only, backend reauthorization for every sensitive action. |
| Callback size | Both current official APIs document 1–64 byte callback data; codec/test must enforce bytes, not characters. |
| Cross-workspace leak | Workspace always derived/validated against canonical membership; resource/order IDs must be reauthorized in target workspace. |
| Payment spoof | Only canonical verified payment/entitlement can grant access. |
| Cached file ID used as entitlement | Prohibited; issue/consume/recheck on every protected send. |
| Forward protection | Telegram `protect_content` when required; Bale direct protected send fail closed while official equivalent is undocumented. |
| Owner impersonation | Platform ID alone is insufficient; linked canonical identity + critical platform permission + private chat required. |
| Update callback injection | No shell/ref/SHA input in callback; durable backend request only. |
| Token/log leakage | Separate envs, redaction, least privilege, no tokens or runtime state in Git. |

## Test gate for Prompt 2

Prompt 2 should not implement production handlers until Platform Chat supplies the missing handoff. Once supplied, Stage 7 tests must cover at minimum:

- secure link issue/confirm/revoke, expiry, replay and cross-platform binding
- service signature success/failure, timestamp skew, nonce replay, body tamper and idempotency
- canonical workspace selection and cross-tenant denial
- schedule/grades/announcements/content projections
- payment order idempotency, canonical status and entitlement grant only after verified payment
- secure delivery issue/consume on every send, stale/reused file ID denial and receipt behavior
- notification claim/lease/ack/retry/restart without duplicate canonical action
- Telegram parsing/callback acknowledgement/rich fallback/protect-content/rate retry/owner-update authorization
- Bale official payload compatibility/edit/callback/file limits/semantic fallback/unsupported protection path
- Persian and Unicode chunking and 64-byte callback enforcement
- strict Telegram/Bale token/update-offset/state separation
- updater private-chat authorization, two-step confirmation, idempotency, lock, restart-visible terminal result, failure/rollback and absence of arbitrary shell/ref input

Live smoke is required before Telegram/Bale parity is claimed; repository tests alone cannot prove current platform behavior.

## One-time Codex/runtime bootstrap — after code/contracts are merged

Codex should receive one exact approved FANOOS `main` SHA and perform only runtime-specific setup/verification:

1. inspect actual OS/Python/process/service/storage state and install a release-local Python virtual environment with locked dependencies;
2. provision separate restricted Telegram and Bale token env files, service users, service units, update streams and local state directories;
3. install/version-lock protected-media binaries and validate resource limits and a safe scratch directory;
4. connect the privileged updater service only after the platform update-control-plane contract exists; the bot process itself gets no shell/sudo/deploy privilege;
5. provision least-privilege repository/deploy read access for FANOOS only and enforce approved exact-SHA deployment;
6. install health/smoke checks for backend reachability, service auth, each bot API, protected Telegram delivery, Bale fallback behavior and updater status;
7. configure separate redacted logs/correlation IDs/rotation;
8. classify runtime state for backup: durable offsets/retry state if used and secret configuration require protected recovery handling; file-ID caches and rebuildable scratch/cache do not belong in canonical backups; no runtime state enters Git;
9. stage first, verify live smoke, then follow the existing backup/readiness/rollback runbook.

No server action belongs in this audit.

## Readiness decision

**Stage 7 bot readiness: `WAIT_FOR_PLATFORM`.**

Prompt 2 may start only after a merged Site/Platform handoff makes the following machine-testable and stable:

1. signed service authentication + replay/idempotency behavior;
2. account link challenge/confirm/revoke + linked-user projection;
3. notification delivery claim/receipt semantics;
4. protected delivery send receipt and protected-media job input/result semantics;
5. payment bot order/deep-link/result/status projection sufficient for the desired UX;
6. Telegram owner Update Server control-plane request/status contract, critical permission and updater ownership;
7. a clear Bale policy for content that requires forward protection (fail-closed direct send remains the default unless an alternate is explicitly approved).

The bot must consume those platform contracts; it must not invent replacements locally.