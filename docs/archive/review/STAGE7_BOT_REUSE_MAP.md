# Stage 7 bot reuse map

Audit date: 2026-09-07  
FANOOS audited SHA: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`  
Voice reference SHA: `5903509e94b272b44ad81c8ba69ea62e07a80b73`  
Dentistry reference SHA: `e78fc96cd975c1275da5a65a5240d616f5840e10`

This map treats legacy projects as behavior/security references only. FANOOS remains a separate multi-tenant product with one canonical backend.

## Status meanings

- **REUSE_PATTERN** — reuse architecture/operational behavior, not literal domain state.
- **ADAPT_LOGIC** — adapt a proven algorithm/flow to FANOOS contracts and entities.
- **NEW_FANOOS_GLUE** — build a thin FANOOS bot use-case around existing canonical backend behavior.
- **DO_NOT_REUSE** — legacy behavior/state is incompatible with FANOOS ownership/security.
- **DEFER** — do not implement until the named platform/product dependency exists.

## Feature map

| Desired FANOOS bot feature | FANOOS existing | Voice reference | Dentistry reference | Reuse strategy | Risk / dependency |
| --- | --- | --- | --- | --- | --- |
| `/start` / onboarding | Canonical account + memberships; no bot link contract | Shared semantic routing, capability-based UI | Website-authoritative onboarding/link proof | **NEW_FANOOS_GLUE** | **High** — account-link challenge/confirm/revoke missing. Telegram deep payload can carry only opaque challenge reference; Bale start payload is undocumented. |
| Account linking | Telegram/Bale identifier types exist in IAM schema; no secure lifecycle | Keep transport IDs platform-scoped; separate runtimes | One-time site-auth/OTP link pattern, strict platform ID validation | **ADAPT_LOGIC** | **Critical** — platform ID alone is not identity proof. Requires Platform Chat contract. |
| Profile | `/account` canonical projection exists | Presentation-neutral shared semantic view | Do not copy 1402 profile JSON | **NEW_FANOOS_GLUE** | Low after linked-user service projection exists. |
| Workspace switch | Canonical memberships + workspace select behavior | Thin adapter pattern | Legacy cohort selection is too specific | **NEW_FANOOS_GLUE** | Medium — bot must derive choices from memberships, never store canonical active cohort independently. |
| Schedule / tomorrow brief | Tenant-scoped schedule API | Semantic rendering, Unicode chunking, no fake ETA | Schedule behavior may be used only as UX oracle | **ADAPT_LOGIC** | Low/medium after service auth. Tomorrow brief scheduling/delivery must be durable and canonical. |
| Grades | Own-grade projection exists | Shared view + platform formatting | Do not copy student/cohort identity shortcuts | **NEW_FANOOS_GLUE** | Medium — high privacy; cross-workspace negative tests mandatory. |
| Announcements | Canonical announcement inbox/read | Semantic formatting + platform parity | Canonical visibility + durable delivery behavior | **ADAPT_LOGIC** | Medium — direct user query is ready; proactive notification claim/receipt is not frozen. |
| Resources/library | Generic content engine/library | Telegram rich UI + Bale semantic fallback | Legacy booklet UX only as behavioral oracle | **ADAPT_LOGIC** | Medium — protected resources require secure delivery path, not raw object/file ID. |
| Secure delivery | Issue/consume + entitlement reauthorization + watermark identity contract | Restart-safe delivery/recovery, explicit unsupported capabilities | Protected identity/watermark and fail-closed patterns | **ADAPT_LOGIC** | **High** — protected worker + send receipt incomplete; Bale direct forward-protected send unsupported by current official docs. |
| Payments | Canonical idempotent order/payment/entitlement services; fake/live provider caveat | **Do not reuse Voice wallet/product DB** | Signed server-owned payment and verified fulfillment pattern | **ADAPT_LOGIC** | High — bot-facing payment result/status contract not frozen; chat message never grants entitlement. |
| Payment result | Canonical order/payment status exists | Restart-safe operation reporting | Durable payment-result claim/ack queue | **ADAPT_LOGIC** | High — FANOOS needs canonical delivery/receipt semantics before proactive result messages. |
| Notifications | Notification messages/recipients with Telegram/Bale channels | Separate transports, restart/retry, live smoke | Leased claim/ack/retry behavior | **ADAPT_LOGIC** | **High** — current FANOOS claim/lease/receipt service boundary missing. |
| Reminders | Preference foundation/outbox/jobs exist | Durable scheduling/retry principles | Notification lifecycle pattern | **DEFER** | Medium — do not let a bot-local scheduler become canonical. Needs canonical reminder/scheduling contract/product scope. |
| Deep links | No bot-specific contract | Capability registry | Site remains account authority | **ADAPT_LOGIC** | Telegram supported; Bale bot-start payload undocumented. Always use opaque backend references, not identity/entitlement data. |
| Owner/admin panel | Scoped RBAC exists | Owner-only controls and capability-specific UI | Owner deploy lifecycle notifications | **REUSE_PATTERN** | High — every action rechecks canonical permission; no Telegram-ID allowlist as sole authorization. |
| Update Server | Existing deploy/rollback runbook only | Owner control + restart-safe terminal status pattern | Deploy lifecycle started/terminal notification pattern | **DEFER** | **Critical** — platform update request/status/permission/updater control plane missing. Telegram only by default. |
| Health/status | Backend health/readiness + deploy runbook | Per-service health, rollback, live smoke | Deploy lifecycle/health evidence | **REUSE_PATTERN** | Medium — distinguish bot/API health from business readiness; no secret/runtime path leakage. |

## Shared semantic layer patterns to reuse from Voice

The strongest reusable Voice decision is structural:

```text
incoming platform update
        |
        v
platform adapter/parser
        |
        v
shared semantic use-case + FANOOS API client
        |
        v
platform-neutral result/view model
        |
        +--> Telegram renderer/runtime
        +--> Bale renderer/runtime
```

Stage 7 should preserve the following Voice patterns:

1. **One semantic use-case, two adapters.** Do not fork payment, resource, profile, schedule or notification rules by messenger.
2. **Capability registry.** Unsupported fields are omitted deliberately; capabilities are not inferred from Telegram API similarity.
3. **Persian/Unicode-safe chunking.** Prefer paragraph/newline/sentence boundaries and never split by encoded byte slices that corrupt Unicode.
4. **Callback ack before expensive work.** Acknowledgement is UX transport behavior, not permission proof.
5. **No invented progress/ETA.** Show actual durable stage/status only.
6. **Separate process state.** Telegram/Bale token, update stream, offset, cache, logs and service lifecycle are isolated.
7. **Live smoke before parity.** Unit tests cannot establish that an external messenger currently accepts an optional field.
8. **Restart-safe operations.** Long-running work is owned by canonical jobs/status, not an in-memory handler.

## Dentistry patterns worth adapting

### Signed service requests

Dentistry demonstrates an appropriate service-auth behavior oracle: timestamp, nonce, request-body digest and HMAC, strict platform identifiers and durable replay rejection. FANOOS target architecture already asks for the same category of protection, so Stage 7 should consume the Platform Chat's final FANOOS contract rather than copy Dent's function names/secrets/storage.

### Account linking

Dentistry keeps website identity authoritative and establishes the bot link only after canonical site proof. Preserve that security outcome while removing all 1402-specific student matching and JSON storage.

### Payment result / notification delivery

Dentistry's leased durable claim/ack queues solve a real restart/duplicate problem. FANOOS should reproduce the **semantics** in its canonical backend/outbox/job system:

- canonical pending delivery;
- exclusive bounded lease;
- idempotent claim/ack;
- retry attempt accounting;
- terminal success/failure state;
- no false delivery acknowledgment.

Do not port the file-backed JSON implementation.

### Protected content identity

Dentistry's protected-booklet flow reinforces that watermark input must be derived from verified canonical identity, not from a Telegram display name or user-entered label. FANOOS already has issuance-bound visible/forensic identity semantics; Stage 7 worker should consume those canonical fields.

### Deployment lifecycle

Dentistry's started/terminal lifecycle messaging is useful UX. FANOOS Update Server must still be driven by a separate privileged canonical control plane rather than direct bot shell access.

## Explicit anti-reuse list

The following are **DO_NOT_REUSE**:

| Legacy artifact/pattern | Reason |
| --- | --- |
| Voice separate user/wallet/product/payment DB | FANOOS backend is canonical for users, commerce and entitlements. |
| Voice Telegram/Bale numeric file-size constants | Transport-specific; FANOOS must use current official limits for its configured transport. |
| Voice/Dent bot tokens, envs, sessions, cache or server paths | Secrets/runtime state must be newly provisioned and isolated. |
| Dent JSON user/link/payment/delivery stores | Would create a second canonical database and regress concurrency/tenant behavior. |
| Dent cohort-1402/student-number/name assumptions | FANOOS is multi-tenant and identity must be canonical/verified. |
| Legacy owner Telegram-ID allowlist as sole permission proof | FANOOS requires linked canonical identity + scoped backend permission. |
| Legacy payment-success text as fulfillment proof | Only canonical verified payment/entitlement authorizes delivery. |
| Legacy cached `file_id` as access proof | `file_id` is a transport optimization only. |
| Any direct bot shell/Git command update path | Violates privilege separation, idempotency, rollback and audit invariants. |

## Recommended code ownership boundary after the platform gate

Do not create these paths in this audit; they are the Stage 7 implementation recommendation.

```text
packages/python/fanoos_bot/
  api/             # signed backend client and DTOs
  application/     # shared use-cases/conversation semantics
  presentation/    # platform-neutral view models
  callbacks/       # compact opaque callback codec, no authorization state

apps/telegram-bot/
  adapter/         # Telegram update -> shared event; view -> Telegram payload
  runtime/         # polling/webhook, offset, retry, local bounded cache

apps/bale-bot/
  adapter/         # Bale payload allowlist/capability fallback
  runtime/         # separate token/update stream/offset/retry/cache

apps/workers/protected-media/
  worker/          # leased canonical jobs; derivative render; result handoff
```

Business/domain rules remain in `apps/platform/` and its canonical database. The Python shared package is a client/application layer, not a replacement domain authority.

## Prompt 2 reuse gate

Prompt 2 may use this reuse map only after the Platform Chat has merged the Stage 7 handoff contracts identified in `PRE_STAGE7_BOTS_QUALITY_AUDIT.md`. Until then, account linking, proactive delivery, protected-media processing and Update Server remain `DEFER` rather than being filled with bot-local substitutes.