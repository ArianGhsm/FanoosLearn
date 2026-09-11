# FANOOS Bot Stage 6 — Learning, Commerce and Protected Delivery Handoff

## Scope

Stage 6 connects the provider-neutral bot journeys to the canonical FANOOS
resource projection and keeps assessment, commerce and protected-delivery
authority in the backend. This is a local source change only: no migration,
deployment, restart or live Telegram/Bale message was performed.

The integrated application now provides:

- a resource hub with canonical course/type/recent filters, bounded pagination,
  detail and access/delivery markers;
- subject-bound resource/assessment/order callbacks, with malformed or foreign
  identifiers rejected before a backend mutation/read;
- assessment list/detail rendering when an adapter exposes a reviewed
  bot-safe projection, otherwise an explicit website continuation (no local
  attempt, answer or score authority);
- commerce projection rendering for adapters that provide canonical orders,
  access and catalog data, with separate order, payment and entitlement facts;
- `/buy` treated as a non-UX technical alias that opens the canonical purchase
  center instead of accepting a raw product identifier;
- continued canonical protected issue → consume → derivative/receipt flow,
  Telegram protected sends and Bale fail-closed behavior from the existing
  delivery implementation.

## Authority boundary

```text
Telegram/Bale update
  → BotRuntime
  → integrated BotApplication
  → canonical workspace-scoped projection / delivery API
  → shared learning Screen + intent
  → provider renderer and one transport operation
```

Resource membership, entitlement, payment verification, assessment attempts,
protected object capabilities and delivery receipts remain backend-owned. The
bot stores only bounded subject-bound callback correlation in `LocalState`.
Product/resource/order UUIDs are callback correlation values, never user copy
or a purchase command. A paid order is not presented as active access unless
the entitlement projection independently says so.

## Journey notes

### Resources

`learning.resource_hub` reads the authorized resource page, derives filter
choices only from canonical courses/current page data, and re-fetches the page
for each filter. Recent sorting is presentation-only over the returned
canonical rows. `learning.resource_detail` shows human metadata and access
state; delivery remains a separate action that re-authorizes against the
backend.

### Protected delivery

The existing application path is intentionally reused: issue and consume are
performed before content is returned, derivative jobs are correlated to the
subject/workspace, and delivery receipts retain idempotency keys. Telegram can
send a protected text/document only when the canonical result requires it. If
Bale cannot enforce required forward protection, the original content is not
sent and a safe explanation is rendered. The V3 state family covers checking,
preparing, ready, expired, denied, unsupported and temporary-unavailable
  presentation states; the integrated delivery path keeps its existing
  receipt-bearing runtime envelope and does not claim local authorization.

### Assessments

The current internal-v1 client has no dedicated bot-safe assessment catalog or
attempt endpoint. The live client therefore renders the website continuation
until such a contract exists. An adapter may opt in by exposing
`assessments`/`assessment_catalog`/`exam_assessments`; only then are canonical
list/detail metadata rendered. Native attempt/scoring actions are deliberately
absent.

### Purchase and access

The existing order-status endpoint remains the source for an order detail. An
optional adapter projection may supply catalog, orders and access rows for the
hub; absent that projection, the hub explains the boundary and links to the
website. Payment status and entitlement status are displayed as independent
facts, with wording aligned to the website semantics. No local entitlement is
ever granted, and raw product IDs are not exposed in the normal UX.

## Files and contracts

- `packages/python/fanoos_bot/integrated_application.py`: resource journeys,
  filters, optional canonical assessment/commerce projections and safe order
  routing.
- `packages/python/fanoos_bot/ui_v3/learning/screens.py`: pagination filter
  correlation and resource access-state presentation.
- `packages/python/fanoos_bot/runtime.py`: `/buy` opens purchase/access center.
- `docs/rebuild/bots/06_LEARNING_COMMERCE_HANDOFF.md`: this handoff.

No new backend route or migration was invented because internal-v1 does not yet
publish bot-safe assessment/catalog/access-list contracts. That gap is explicit
so a future additive contract can carry authorization and tenant-isolation
tests instead of creating a shadow bot authority.

## Validation and next handoff

Run the deterministic bot, worker, UX-v2 and UX-v3 suites plus Python AST,
PHP lint, text/secret guards and whitespace checks. The deployment-stage owner
must later verify the exact pushed SHA, production backup gate, live protected
delivery receipts and provider capability configuration. Do not deploy this
stage automatically.

Bot Stage 7 can add further learning/content journeys on these same intents;
keep all new assessment, catalog or access data behind reviewed canonical
contracts and preserve the protected receipt/idempotency path.
