# Stage 7 — Bot Backend Contract Consumption

Baseline: `a2e99953b9c93d97a7302f24627563487c0e4ab1`.

Telegram, Bale and worker code in this workstream are **consumers** of `contracts/openapi/internal-v1.yaml`; they do not redefine it. Every internal request signs the exact UTF-8 body with `fanoos-service-v1`, method, path, Unix timestamp, fresh nonce and SHA-256 body digest. A transport retry reuses business idempotency but creates a fresh service-auth nonce.

## Implemented internal operations

- account-link challenge consume;
- workspace list/select with canonical membership recheck;
- commerce create/status;
- notification project/claim/receipt;
- protected delivery issue/consume/receipt;
- protected-media enqueue and worker claim/result client methods;
- Telegram deployment request/status.

## Non-authoritative local state

SQLite stores only messenger update offsets, short-lived update confirmations, deployment request references, bounded notification send recovery and platform file cache. It never stores canonical identity, membership, role, price, payment, entitlement, content or notification authority.

## Deliberate contract gaps

The frozen internal v1 surface does not expose schedule, grades, announcements or resource-list read projections. Bots therefore do not synthesize browser sessions or query SQL. Resource discovery remains a web navigation fallback until Platform integration adds compatible internal projections.

The protected-media claim contains `object_capability`, but no canonical HTTP redemption operation from that capability to bytes is frozen, and no bot-facing final derivative retrieval operation is frozen. The worker therefore remains fail-closed and does not claim production jobs.
