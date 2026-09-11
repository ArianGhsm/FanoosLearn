# FANOOS Stage 9 — Commerce & Protected Delivery Handoff

## Scope completed

Stage 9 connects the existing server-authoritative commerce and protected-content contracts to a usable student purchase/access surface. No production migration, deployment or service restart was performed.

- Active products are projected from `commerce_products` and the currently valid `commerce_price_versions` row. Each product is tied to an existing RBAC target scope; resource, course, assessment and workspace scope types receive a safe human label and optional resource/course title.
- `CommerceService::createOrder` remains the only order-creation authority. The browser sends only a product id and idempotency key; amount and currency are re-quoted from the server.
- Payment verification remains adapter-owned and server-side. A provider callback verifies the canonical amount/currency, settles the order once and grants the target entitlement only after verified payment. Duplicate callbacks and idempotent order attempts remain no-ops.
- Order history now returns independent `order_status`, `payment_status` and `entitlement_status` fields. The UI never infers access from a paid order.
- `EntitlementService::library` exposes a tenant/member-scoped access library with active, pending, expired and revoked states. Raw storage keys and target scope ids are not part of the student projection.
- `/catalog` and `/entitlements` are authenticated workspace routes. The operations surface renders server-priced product cards, truthful payment-provider-unavailable states, an access library and a three-state order history.
- The existing signed delivery and object-capability chain is reused unchanged: issue-time authorization, issuance watermark fingerprint, short-lived signed token, consume-time reauthorization, exact version/object checks and a private filesystem object root. The object store rejects traversal and non-canonical keys.
- Existing protected-media jobs continue to receive only bounded object capabilities plus deterministic issuance-derived forensic metadata for personalized PDF/resource processing.

## Security validation

The Stage 7 integration path was extended to assert canonical catalog price/currency, reject provider payload amount/currency tampering, keep duplicate callback/order behavior idempotent, expose separate order/payment/access states, and surface a verified entitlement in the access library. Existing revoke/expiry, subject-mismatch, protected-download, private-object and delivery-reauthorization checks remain in place.

The fake payment adapter is a development adapter only. It accepts the minimal existing success fixture but rejects optional provider payload amount/currency values that disagree with the server quote. Production adapters must perform equivalent provider-side verification.

## Deliberate limits

FANOOS does not claim impossible DRM. Signed delivery, short-lived capabilities, private object roots, per-user watermark/tracing metadata and reauthorization reduce casual sharing and preserve auditability; once authorized bytes reach a client, absolute copying prevention is not promised.

No new product/bundle table was introduced: the established `commerce_products.target_scope_id` is the canonical product-to-resource/course/workspace/assessment binding. A future bundle model should be additive and must preserve the same server quote, entitlement and delivery gates.

## Changed areas

- `apps/platform/src/Commerce/CommerceService.php`: catalog projection; safe product/resource scope projection; separated order/payment/access history fields.
- `apps/platform/src/Commerce/FakePaymentGateway.php`: development verification rejects supplied amount/currency mismatches.
- `apps/platform/src/Entitlements/EntitlementService.php`: canonical access status, tenant-scoped access library and human scope labels.
- `apps/platform/src/Http/ApiKernel.php`: `/catalog` and `/entitlements` routes.
- `apps/platform/public/assets/ui-v3/operations/operations.js` and `operations.css`: purchase catalog, access library, human labels and independent order/payment/access states.
- `contracts/openapi/core-v1.yaml`: catalog and access-library contracts.
- `tests/Integration/Stage7PlatformTest.php` and `tests/ux-v3/web_integration_contract_test.js`: commerce/security and browser-contract coverage.

## Next-stage handoff

Before deployment, run the full PHP integration runner with the production-like MySQL extensions (`pdo_mysql`, `mbstring`, `fileinfo`) available, verify a real payment adapter in a non-production sandbox, and exercise signed delivery against the deployed private object root. Do not expose object paths or payment proof in browser URLs, and preserve the exact-release/backup gates from the deployment runbook.
