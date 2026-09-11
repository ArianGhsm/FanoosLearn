# FANOOS Bot Stage 7 — Notifications, Admin Controls and Reliability Handoff

## Scope

Stage 7 completes the safe operational boundary for bot notifications and the
Telegram owner update flow. This is local source plus deterministic validation;
no migration, deployment, restart or live provider message was performed.

The existing canonical paths were reused and tightened:

- notification claim/send/receipt keeps the delivery lease and idempotency key;
  successful provider sends are remembered before the receipt call, so a receipt
  outage retries the receipt without sending the notification again;
- provider failures are translated to retry/failed receipts, while unexpected
  renderer/transport failures close the lease with the non-sensitive
  `notification_delivery_failed` code;
- all update messages and callbacks with an event id are restart-safe and
  subject to durable local dedupe; once application logic has run, a rendering
  failure cannot replay the business action;
- update status ids are accepted only as canonical UUIDs, preventing arbitrary
  branch/ref/SHA input from reaching the control plane;
- owner deployment controls remain Telegram-private and backend-capability
  gated; Bale never exposes deployment controls.

## Authority boundary

```text
Provider update
  → BotRuntime durable event gate
  → BotApplication canonical permission/business call
  → semantic screen or notification transport
  → canonical receipt / local correlation state
```

`LocalState` contains bounded offsets, event ids, receipt outbox entries,
confirmation refs and deployment request correlation only. It never grants a
role, entitlement or deployment permission. The backend remains authoritative
for notification leases/receipts, scoped RBAC, deployment targets/candidate
SHA and all admin/representative operations.

## Journey notes

### Notifications

Personal inbox history is not fabricated in the bot because internal-v1 has no
personal notification-list projection. The bot exposes the canonical website
handoff and the worker delivers claimed channel notifications through the
shared semantic notification renderer. Receipt retry is durable across process
restart; duplicate delivery ids are acknowledged from local state without a
second provider send.

### Representative/admin actions

No new bot mutation surface was invented. The current internal contract does
not expose safe representative/member/admin actions, so the bot does not copy
website admin endpoints or create a role shadow store. Existing workspace
management remains visible only when the canonical deployment capability is
returned for the owner path.

### Telegram owner update

`management → update_begin → confirmation → update_confirm → update_status`
uses the configured target key and backend-provided release data. The
confirmation is short-lived, subject-bound and single-use; the backend still
enforces authorization and idempotency when the request is submitted. Status
refresh uses the saved canonical request UUID after restart. User-provided
shell commands, branches, refs and SHAs are not accepted.

### Bale

Bale receives no deployment action. The application and provider renderer both
keep the capability boundary, even when a context is artificially populated
with owner permissions.

## Files

- `packages/python/fanoos_bot/runtime.py`: durable event consumption for all
  messages/callbacks and robust notification failure/receipt sequencing.
- `packages/python/fanoos_bot/state.py`: restart-safe processed-update writer,
  atomically reused by delivery receipts.
- `packages/python/fanoos_bot/application.py`: reject arbitrary deployment
  status identifiers before contacting the control plane.
- `tests/ux-v3/test_bot_admin_reliability_journeys.py`: notification,
  duplicate-callback, private owner and Bale/status safety coverage.
- `docs/rebuild/bots/07_ADMIN_RELIABILITY_HANDOFF.md`: this handoff.

## Validation and next handoff

Run the full bot, worker, UX-v2 and UX-v3 suites, Node contracts, Python AST,
PHP lint, text/secret guards and whitespace checks. `php tests/run.php` still
requires the workstation PHP `finfo` extension for the complete integration
runner. A deployment-stage owner must later verify the exact pushed SHA,
production backup gate, live notification leases/receipts and owner control
behavior. Do not deploy this stage automatically.

Bot Stage 8 can build additional provider journeys or additive bot-safe admin
read contracts. Keep notification receipts and event dedupe durable, and add
any representative/admin mutation only alongside canonical RBAC and tenant
isolation tests.
