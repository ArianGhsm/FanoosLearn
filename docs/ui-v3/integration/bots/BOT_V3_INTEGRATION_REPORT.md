# FANOOS Bot V3 — Integration Report

Design Lock: `FANOOS-UX-2026.09-R1`

Scope: GitHub source integration only. No deployment, production database access, live Telegram/Bale call or protected-media provider smoke is part of this phase.

## Canonical runtime architecture

The Telegram and Bale entrypoints continue to use the established backend client, application, local transport state, receipt outbox and notification pump. The user-facing presentation path is now V3:

1. Existing application/backend code performs the canonical business read or mutation exactly once.
2. `fanoos_bot.integrated_application.BotApplication` converts product output into the bot-01 semantic contract or preserves protection-sensitive legacy payload bytes/text unchanged.
3. `ui_v3.wiring` resolves semantic intents. Oversized callback state becomes a short, expiring, platform+subject-bound presentation route reference.
4. `ui_v3.providers.core_adapter` maps bot-01 semantics to bot-04's provider-neutral render contract without deriving authorization.
5. `TelegramV3Renderer` or `BaleV3Renderer` creates the provider-native delivery plan.
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

Telegram protection-sensitive content is one plain `protect_content=true` provider operation. There is no Rich→plain second-send fallback for that content. Bale remains fail-closed when equivalent forward protection is unavailable. Existing derivative issue/redeem and receipt semantics are unchanged.

### Owner management

Deployment controls require the existing backend `deployment.manage` decision and private Telegram context. Bale does not expose the original management action even when presentation receives an already-verified permission fact.

### Commerce

Order state, payment state and entitlement are separate semantic facts. The integration does not infer entitlement from a successful payment page or callback and does not create a bot-local product authority.

## Compatibility retained

- signed internal service API semantics;
- messaging account link/revoke semantics;
- canonical workspace membership checks;
- delivery receipt outbox and processed-update dedupe;
- protected-media derivative flow;
- notification claim/receipt model;
- deployment request/status semantics;
- current Telegram/Bale polling loops and offset persistence;
- existing technical commands for backward compatibility while button-led V3 remains the intended UX.

## Validation

`tests/ux-v3/test_bot_integration.py` adds deterministic assertions for workspace selection, zero-workspace shell completeness, callback route isolation, raw-ID hiding, Telegram RTL rich rendering, protected Telegram atomicity, Bale fail-closed protection, owner-management gating and commerce fact separation.

`ops/stage7-bots/run-deterministic-tests.sh` includes the V3 suite in addition to the existing bot, worker, UX and V2 regression suites.

The authoritative pass/fail result is the exact-head GitHub Actions CI run on the integration pull request. Live provider validation remains a later deployment-stage requirement.
