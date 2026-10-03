# WORKSTREAM HANDOFF — BOT 03 LEARNING / COMMERCE

## Identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/bot-03-learning`
- Owned source: `packages/python/fanoos_bot/ui_v3/learning/**`
- Owned docs: `docs/ui-v3/workstreams/bot-03-learning/**`

## Files created

Source:

- `packages/python/fanoos_bot/ui_v3/learning/__init__.py`
- `packages/python/fanoos_bot/ui_v3/learning/intents.py`
- `packages/python/fanoos_bot/ui_v3/learning/screens.py`
- `packages/python/fanoos_bot/ui_v3/learning/filters.py`

Docs:

- `docs/ui-v3/workstreams/bot-03-learning/screen_specs.md`
- `docs/ui-v3/workstreams/bot-03-learning/WORKSTREAM_HANDOFF.md`

## Current / legacy source inspected and conceptually reused

Repository guidance and V3 lock:

- `AGENTS.md`
- `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md`

Cross-channel/V2 product contracts:

- `docs/ui-v2/FANOOS_PRODUCT_IA.md`
- `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`
- `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
- `docs/ui-v2/bots/BOT_INFORMATION_ARCHITECTURE.md`
- `docs/ui-v2/bots/BOT_SCREEN_MAP.md`
- `docs/ui-v2/bots/BOT_SEMANTIC_MODEL.md`
- `docs/ui-v2/bots/BOT_BACKEND_UI_GAPS.md`
- `docs/ui-v2/bots/TELEGRAM_PRESENTATION.md`
- `docs/ui-v2/bots/BALE_PRESENTATION.md`
- `docs/ui-v2/bots/BOT_PRODUCT_AUDIT.md`
- `docs/ui-v2/bots/BOT_HANDOFF.md`
- `docs/ux-parallel/worker03_bot_semantic_presentation.md`
- `docs/ux-parallel/worker04_channel_native_presentation.md`

Canonical contracts and current implementation:

- `contracts/REGISTRY.md`
- `contracts/openapi/core-v1.yaml`
- `contracts/openapi/internal-v1.yaml`
- `packages/python/fanoos_bot/api.py`
- `packages/python/fanoos_bot/models.py`
- `packages/python/fanoos_bot/application.py`
- `apps/platform/src/Core/BotReadProjectionService.php`
- `apps/platform/src/Commerce/BotCommerceService.php`

Parallel dependency inspected read-only after it completed:

- bot-01 branch commit: `faead96a4bedca34151562c81c712ae42b2a7693`
- `packages/python/fanoos_bot/ui_v3/core/contracts.py`
- `packages/python/fanoos_bot/ui_v3/core/navigation.py`
- `packages/python/fanoos_bot/ui_v3/core/__init__.py`

Reuse was semantic only: canonical authorization, pagination/correlation rules, secure delivery, protected-content fail-closed behavior, order/payment/entitlement separation, currency truth and existing browser handoff boundaries. V2 layout/copy hierarchy was not treated as V3 authority.

## Canonical endpoints / projections consumed or expected by integration

Native resource journey:

- `POST /api/internal/v1/content/resources/list`
- `POST /api/internal/v1/deliveries/issue`
- `POST /api/internal/v1/deliveries/consume`
- `POST /api/internal/v1/deliveries/receipt`
- `POST /api/internal/v1/protected-media/enqueue`
- `POST /api/internal/v1/protected-media/derivatives/issue`
- `POST /api/internal/v1/protected-media/derivatives/redeem`

Commerce status path already available:

- `POST /api/internal/v1/commerce/orders`
- `POST /api/internal/v1/commerce/orders/status`

Browser/canonical website handoffs already represented in `core-v1`:

- resource library/detail/authorization/delivery/download routes;
- `GET /workspaces/{workspaceId}/assessments` and assessment attempt/review routes;
- `GET/POST /workspaces/{workspaceId}/orders` and payment flows;
- entitlement checks/actions;
- `GET /workspaces/{workspaceId}/forms` and form submissions.

This workstream does not call these endpoints itself. It emits presentation screens/intents for application integration.

## Integration imports / dependencies

Required merge order/dependency:

1. bot-01/core semantic package must exist before these source imports are wired.
2. `learning/screens.py` and `learning/filters.py` import the exact bot-01 primitives from `..core`.
3. Current application/integration layer must bind `LearningIntent` callback names to canonical reads/actions and create any subject-bound route references required by provider payload size limits.
4. bot-04/providers renders the resulting `Screen` and enforces provider capability behavior.

The source was aligned against bot-01 `CallbackIntent`, `Screen`, `Action`, `ActionRow`, `Pagination`, `ProtectContent`, `EditPolicy`, `Breadcrumb` and navigation callback names `home` / `back`.

## Screens / components delivered

Resources:

- bounded resource hub;
- canonical course filter picker;
- canonical resource-type filter picker;
- recent entry intent;
- opaque-cursor pagination;
- resource detail with title/course/type/version/access/protection;
- secure-delivery action intent;
- no visible UUIDs/storage identifiers.

Protected delivery:

- checking access;
- preparing;
- ready;
- expired;
- denied;
- unsupported channel;
- temporary failure.

Assessments:

- active/upcoming/completed/practice/past grouping when a bot-safe projection exists;
- course/state filter pickers for future canonical input;
- assessment detail metadata;
- safe Website continuation when native attempt flow is not exposed;
- no answer/scoring authority.

Purchase/access:

- human purchase/access hub that can consume canonical summaries when they exist;
- explicit current catalog/order-history/access-list gaps;
- order/payment/access detail with three separate statuses;
- amount + canonical currency;
- refresh, checkout and browser retry intents only when supplied safely;
- no `/buy <product_id>` primary UX.

Forms/services:

- bot-safe list/detail rendering path for a future canonical projection;
- explicit current web handoff when projection is absent;
- no local submission authority.

Shared:

- empty/error/denied/unavailable state family;
- bounded two-per-row action composition;
- Persian/RTL microcopy and Design-Lock emoji semantics.

## Protected action intents for bot-04

- `learning.resource.deliver`: begin canonical secure-delivery journey; never send before backend result.
- `learning.protected.check`: re-check current authorization.
- `learning.protected.refresh`: check protected derivative readiness without replaying business actions.
- `learning.protected.retry`: retry temporary failure through application exactly once per user action.
- `learning.protected.resource`: restart/return from resource context; expired capabilities are not reused.

Provider capability requirements:

- `ProtectContent.REQUIRED` may never be silently downgraded.
- Telegram may deliver only through verified native protected-send behavior when required.
- Bale must render `unsupported_channel` and fail closed when equivalent protection is not available.
- provider rich-render fallback may alter formatting only; it must not re-run authorization/order/delivery mutations.
- protected delivery outcomes continue through existing receipt/idempotency semantics.

## Website handoff requirements

Integration must inject canonical HTTPS routes, not provider-derived or capability URLs, for:

- safe resource web delivery/detail when needed;
- assessment catalog/detail/attempt;
- purchase/access center, browser checkout/retry;
- forms list/detail/submission.

No storage capability, permanent signed download link, checkout secret, CSRF data, HMAC material or provider identifier belongs in a button URL.

## INTEGRATION_GAPS

### GAP-01 — bot-safe assessments

`internal-v1` has no assessment catalog/detail/attempt/result projection. `core-v1` has the canonical browser flow. Until an internal contract is added, current integration must use structured website handoff; native scoring/answers are forbidden.

### GAP-02 — commerce catalog / order history / access center

Current internal commerce is create-order-by-known-product and status-by-known-order. It does not provide a bot-safe product catalog, human order list/history or complete entitlement/access list. The V3 hub therefore cannot truthfully become a complete native commerce center yet.

### GAP-03 — distinct payment status

`BotCommerceService::project()` exposes `order.status` and `entitlement.granted`, but no separate current payment-attempt status. `order_access_detail_screen` deliberately shows payment as separately unavailable unless integration supplies a canonical payment status. It must not duplicate order status and call it payment truth.

### GAP-04 — bot-safe forms

`internal-v1` has no forms list/detail/submission contract. Current forms journey must hand off to the canonical website. The native list/detail builders are dormant until a safe projection exists.

### GAP-05 — resource human version/protected-state labels

The resource bot catalog exposes an authorized `resource_version_id` and `delivery_supported`, but no human version number/name and no standalone human protected-state field. V3 displays `نسخهٔ جاری مجاز` and defers the actual protection decision to delivery time rather than exposing UUIDs or inventing labels.

### GAP-06 — resource filter backend semantics

The current internal resource list request is cursor/limit based and has no canonical course/type query fields. Filter picker screens are delivered, but integration must only enable them when it can supply complete canonical filter semantics (for example through an integrated course/resource projection or a future backend filter). Do not overfetch a partial page and present it as a complete filtered result.

## Merge-worker verification assumptions

- Preserve `PARALLEL_REBUILD_BASE_SHA`; do not treat bot-01’s later commit as this branch’s base.
- Merge/integrate bot-01 before bot-03 so `..core` exists.
- Confirm bot-04 consumes `ProtectContent.REQUIRED` and the protected intents above without weakening Bale fail-closed behavior.
- Bind callback correlation params to subject/workspace-scoped routes; IDs remain non-authoritative.
- Enable course/type/assessment filter controls only when the integration path can provide complete canonical semantics.
- Keep browser destinations HTTPS and generated by canonical application configuration/router.
- Do not wire a raw product-ID purchase command as normal UX.
