# FANOOS Bot V3 — Integration Report

Design Lock: `FANOOS-UX-2026.09-R1`

Scope: GitHub source integration only. No deployment, production database access, live Telegram/Bale call or protected-media provider smoke is part of this phase.

## Canonical runtime architecture

The Telegram and Bale entrypoints continue to use the established backend client, application, local transport state, receipt outbox and notification pump. The user-facing presentation path is now V3:

1. Existing application/backend code performs the canonical business read or mutation exactly once.
2. `fanoos_bot.integrated_application.BotApplication` converts V3-owned product output into the bot-01 semantic contract and preserves protection-sensitive accepted Stage 7 payloads where transport compatibility is required.
3. `ui_v3.wiring` resolves semantic intents. Oversized callback state becomes a short, expiring, platform+subject-bound presentation route reference.
4. `ui_v3.providers.core_adapter` maps bot-01 semantic screens to bot-04's provider-neutral render contract without deriving authorization.
5. `TelegramV3Renderer` or `BaleV3Renderer` creates the provider-native delivery plan for V3 screens; the narrow protected legacy seam retains the already-accepted Stage 7 atomic transport behavior.
6. Existing transport receipt/idempotency logic records the result without replaying the business action.

## Reconciled integration conflicts

### Provider contract vs final bot-01 Screen

The provider worker predated the final concrete bot-01 contract. A dedicated adapter now handles `Action.intent`, `Screen.identifier`, semantic sections, pagination, edit policy and `ProtectContent.REQUIRED` explicitly. Protection cannot be silently downgraded.

### Workspace selection

The previous application could automatically select the only workspace membership. V3 removes that mutation. A membership may be displayed, but active workspace selection is an explicit user action and is revalidated by the canonical backend.

### Zero-workspace state

A linked user with no workspace is not reduced to a dead-end warning. The V3 shell provides workspace, account, help, home and configured website paths while making clear that the bot cannot invent workspace membership.

### Callback size and authority

Provider callback limits are presentation constraints only. Long semantic intents are stored in the existing bounded `presentation_routes` state with platform, subject, kind and expiry binding. Route contents are correlation data; destination methods re-read/re-authorize canonical backend state.

### Protected delivery

Protection-sensitive output remains one atomic provider operation and never receives an ambiguous second-send fallback. Canonical V3 Telegram protected plans use `protect_content=true`; the accepted Stage 7 legacy protected-text seam may retain one Rich protected send when supported, still without fallback/replay. Bale never sends a protected original without an equivalent protection capability: legacy protected originals fail before network, while canonical V3 provider plans can render a safe explanatory fail-closed screen. Existing derivative issue/redeem and receipt semantics are unchanged.

### Owner management

Deployment controls require the existing backend `deployment.manage` decision and private Telegram context. Bale does not expose the original management action even when presentation receives an already-verified permission fact.

### Commerce

Order state, payment state and entitlement are separate semantic facts. The integration does not infer entitlement from a successful payment page or callback and does not create a bot-local product authority.

### Course authority

Course truth comes only from the authorized canonical `schedule.courses` projection. Activity, grade and resource rows are not merged to invent enrollment/course truth. Course-detail actions are limited to domains supported by current bot-safe/course-bound contracts; assessment and course-announcement actions remain hidden until such contracts exist.

## Compatibility retained

- signed internal service API semantics;
- messaging account link/revoke semantics;
- canonical workspace membership checks;
- delivery receipt outbox and processed-update dedupe;
- protected-media derivative flow;
- notification claim/receipt model;
- deployment request/status semantics;
- current Telegram/Bale polling loops and offset persistence;
- accepted Stage 7 protected transport behavior at the narrow migration seam;
- existing technical commands for backward compatibility while button-led V3 remains the intended UX.

## Validation

`tests/ux-v3/test_bot_integration.py` adds deterministic assertions for workspace selection, zero-workspace shell completeness, callback route isolation, raw-ID hiding, Telegram RTL rich rendering, protected Telegram atomicity, Bale fail-closed protection, owner-management gating and commerce fact separation.

Additional V3 tests cover canonical course projection/action availability, active-Home empty-vs-unavailable states and legacy secondary-screen provider compatibility. Existing cross-channel parity tests were migrated from V2 implementation details to semantic V3 `Screen`, pagination and action contracts while retaining the original parity/security assertions.

`ops/stage7-bots/run-deterministic-tests.sh` includes the V3 suite in addition to the existing bot, worker, UX and V2 regression suites.

The authoritative pass/fail result is the exact-head GitHub Actions CI run on the integration pull request. Live provider validation remains a later deployment-stage requirement.
