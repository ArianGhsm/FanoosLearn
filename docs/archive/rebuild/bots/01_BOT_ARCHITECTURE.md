# FANOOS Bot Stage 1 — Shared Architecture

## Scope and boundary

The Telegram and Bale bots are two transport adapters for the same FANOOS application. They do not own users, memberships, workspaces, courses, payments, entitlements, resources, grades or notification truth. The canonical backend (`apps/platform`) answers every domain read and mutation through the authenticated internal API; bot-local state is disposable transport/presentation state only.

The legacy Dentistry/VoiceMatn projects were inspected for behavior patterns, not imported as runtime dependencies. The reusable patterns are Persian-first hierarchy, short human copy, two-button rows when readable, explicit Back/Home paths, factual loading/error states, idempotent callbacks and protected delivery. Institution, faculty, program, cohort and course labels are backend data, never application constants.

## Layer model

```text
Telegram update ─┐
                 ├─ provider runtime → BotApplication → canonical FANOOS API
Bale update ─────┘          │                 │
                            │                 └─ domain projection/authorization
                            └─ provider renderer
                               (Telegram rich/plain or Bale markdown/plain)
```

### 1. Shared semantic contract

`packages/python/fanoos_bot/ui_v3/core/contracts.py` is the provider-neutral model:

- `Screen` carries title, intro, context, breadcrumbs, sections, pagination, severity, edit policy and protected-content intent.
- `Action` and `ActionRow` carry product actions, with at most two readable actions per row.
- `CallbackIntent` carries only bounded routing correlation. It cannot grant a role, workspace, entitlement, payment result or deployment permission.
- `Context`, `Fact`, `ListItem` and `Section` keep workspace/course/task context visible without exposing internal identifiers.
- `ProtectContent` and `EditPolicy` make delivery safety and replay behavior explicit.

Builders under `ui_v3/core`, `ui_v3/academic` and `ui_v3/learning` produce this model from canonical projections. Empty, unavailable, error and success builders provide a complete navigation shell rather than a dead end.

### 2. Shared application/dispatch layer

`packages/python/fanoos_bot/integrated_application.py` is the single product application. It selects the active workspace explicitly, calls the canonical backend, builds the semantic screen, and dispatches registered intents. `ui_v3/wiring.py` is the integration seam:

- `INTENT_REGISTRY` is the allow-listed callback/action registry.
- Long or parameterized actions use subject-bound, expiring `LocalState` route references.
- `core_to_runtime` is the compatibility envelope for older runtime transport fields.
- `decode_v3_intent` and `dispatch_v3_intent` reject unknown, expired, cross-subject or malformed routes and recover to a safe screen.

### 3. Provider adapters

`ui_v3/providers/contract.py` adapts semantic screens to a bounded `ProviderScreen`. `policy.py` holds provider capabilities, row packing, density limits, callback validation, callback acknowledgement timing and protected-content decisions.

- `providers/telegram.py` renders escaped rich HTML when available and falls back to the same plain screen once. Protected content is one atomic operation; an ambiguous rich failure never triggers a second protected send.
- `providers/bale.py` renders Bale-safe markdown/plain text. It refuses protected originals because Bale cannot provide the required forward-protection guarantee, and it hides Telegram-only deployment controls.
- Provider context contains only facts supplied by integration (`private_chat`, callback state and canonical permissions); renderers never infer authorization from labels.

### 4. Runtime and local state

`apps/telegram-bot/runtime.py` and `apps/bale-bot/runtime.py` share the same `BotRuntime`, `NotificationPump`, `FanoosApiClient` and `BotApplication`. They acknowledge callbacks, advance update offsets, flush delivery receipts and retry only transport-safe operations. A rendering failure never replays a business action.

`LocalState` stores bounded offsets, processed-update deduplication, subject-bound presentation routes, delivery receipts, file-cache hints and short-lived confirmations. It is restart-safe operational state, not a domain database. It contains no authorization truth, payment state, grade truth or canonical content.

## Reliability and security invariants

- Internal API requests are signed with timestamp, nonce, body digest and a service identity; safe reads/retryable commands use explicit retry policy.
- Callback data is capped at the provider limit (64 bytes); opaque route payloads are bounded, expiring and subject/platform-bound.
- Every mutation remains backend-authorized and receives an idempotency key where the backend contract requires one.
- Notification and protected-delivery receipts are durable and independently retryable.
- Raw UUIDs, storage paths, object capabilities, provider errors and stack traces are not user copy. Human labels are built from safe projections and known error localization.
- Protected content never silently downgrades. Telegram receives the protected original only through the supported atomic path; Bale receives a truthful refusal.
- Owner/deployment controls are Telegram-private and require the canonical `deployment.manage` permission; Bale never exposes them.

## Reuse decision

The current FANOOS V3 core/provider implementation is already the reusable baseline, so this stage adds the architecture contract and screen map rather than a second bot framework. Dent1402 behavior was generalized at the semantic and policy boundaries; no Dent1402 source, runtime state, token, database or storage is imported.

## Next stage

Bot Stage 2 can add domain screens and actions inside the shared semantic contract. A new feature should add a canonical backend projection, one screen/action builder and registry entries plus provider-neutral tests; it should not add a Telegram-only or Bale-only business path.
