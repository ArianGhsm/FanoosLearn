# Stage 7 — Platform Handoff to Telegram / Bale Bots

## Ownership boundary

Chat 2 owns Telegram/Bale adapter runtime and UX. This document freezes the platform contract it consumes.

Bots are adapters over canonical FANOOS state. They must not create independent canonical identity, membership, payment, entitlement, content or notification databases. Transport-local cursor/cache/message/file IDs are allowed only as non-authoritative adapter state.

## Internal service authentication

All `/api/internal/v1/*` requests use the service-auth contract in `contracts/openapi/internal-v1.yaml`.

Headers:

```text
X-Fanoos-Key-Id: <registered key id>
X-Fanoos-Timestamp: <unix seconds>
X-Fanoos-Nonce: <fresh opaque nonce>
X-Fanoos-Content-SHA256: <lowercase SHA256 of exact raw body bytes>
X-Fanoos-Signature: <lowercase HMAC-SHA256 hex>
```

Canonical signing input:

```text
fanoos-service-v1
<METHOD>
<PATH>
<TIMESTAMP>
<NONCE>
<SHA256_RAW_BODY>
```

Do not normalize/re-serialize JSON after computing the body hash. Sign the exact UTF-8 bytes sent. Nonces are one-time and timestamps are bounded. Safe auth failure must be treated as terminal for that attempt; do not retry with the same nonce.

Each adapter has its own service identity/action allowlist. Telegram credentials/actions cannot be used as Bale identity and vice versa.

## Account linking

### Human side

Authenticated browser/app user creates a challenge:

`POST /api/v1/messaging/link-challenges`

Body:

```json
{"platform":"telegram"}
```

or `bale`.

The returned challenge is short-lived and shown once; FANOOS stores only its digest.

Human unlink:

`POST /api/v1/messaging/links/{platform}/revoke`

uses normal FANOOS session + CSRF.

### Adapter confirmation

`POST /api/internal/v1/messaging/link-challenges/consume`

The signed adapter sends the challenge token plus the platform subject observed from Telegram/Bale. Subject ID alone, phone number, student number, username or chat ID is not identity proof.

Confirmation is one-time, platform-bound and replay-protected. A platform subject cannot be actively linked to two FANOOS users.

## Canonical workspace projection

List:

`POST /api/internal/v1/messaging/workspaces/list`

Select/switch:

`POST /api/internal/v1/messaging/workspaces/select`

The adapter identifies its linked platform subject. The backend resolves the canonical user and rechecks active membership. Client-provided workspace ID is a requested context only; it never proves membership.

The adapter may cache the selected workspace for UX, but authorization must use backend results. If membership is suspended/ended, FANOOS invalidates/ignores the channel context.

## Commerce / payment

Create an order/deep link:

`POST /api/internal/v1/commerce/orders`

Read canonical status:

`POST /api/internal/v1/commerce/orders/status`

Rules:
- send product/workspace identifiers and an idempotency key, never a trusted amount;
- display server-returned title/amount/currency/payment URL;
- adapter callback/redirect text is not payment proof;
- never grant entitlement locally;
- success is the backend canonical order/payment projection after provider verification;
- duplicate create/callback paths are expected and idempotent.

Provider callback tokens/authority material are intentionally not part of bot projection.

## Notifications

Project eligible outbox events:

`POST /api/internal/v1/notifications/project`

Claim a Telegram/Bale delivery:

`POST /api/internal/v1/notifications/claim`

Return delivery result:

`POST /api/internal/v1/notifications/receipt`

The adapter must treat the lease/delivery ID as the idempotency boundary. A successful retry must not fan out a second canonical recipient delivery. Failures use bounded retry semantics from the backend.

Payloads must not be copied into verbose logs. Avoid logging subjects, message bodies or identifiers unless the structured safe logging contract explicitly allows them.

## Protected delivery

Issue:

`POST /api/internal/v1/deliveries/issue`

Consume immediately before send:

`POST /api/internal/v1/deliveries/consume`

Receipt:

`POST /api/internal/v1/deliveries/receipt`

Consume is mandatory even if Telegram/Bale has a cached platform `file_id`; the cached ID is only an optimization. FANOOS rechecks user, workspace, entitlement, resource and exact version on every consume.

Important response fields include:
- exact resource/version;
- short-lived object capability when an object is present;
- `forward_protection_required`;
- watermark/forensic metadata;
- expiry.

Telegram adapter should use the official protected-content send capability when required. The bot audit found no equivalent official Bale capability at audit time; required direct Bale forward-protection must therefore fail closed or use the approved protected-media/document fallback defined by the bot workstream rather than silently downgrading protection.

## Protected-media worker

Enqueue:

`POST /api/internal/v1/protected-media/enqueue`

Worker claim:

`POST /api/internal/v1/protected-media/claim`

Complete:

`POST /api/internal/v1/protected-media/complete`

Fail:

`POST /api/internal/v1/protected-media/fail`

Claim contract provides:
- `job_id`;
- workspace/resource/version/issuance binding;
- short-lived scoped object capability;
- watermark label;
- forensic ID;
- renderer algorithm version;
- input byte/page/time limits;
- lease/completion key.

It does not provide a raw unscoped storage path or secret.

Completion returns only bounded output metadata: SHA-256, size, MIME and opaque artifact reference. The renderer algorithm version is fixed by the claimed job and cannot be overridden by worker completion. Full Python rendering is owned by Chat 2; the backend job contract is the authority.

## Update Server button

### Required bot gates

The Telegram owner UX must additionally require private chat and explicit owner-facing confirmation. These are adapter gates. Backend authorization remains mandatory.

Permission name:

`deployment.manage`

It is platform-scoped and is not granted to ordinary representatives/workspace admins.

### Request

`POST /api/internal/v1/deployments/request`

Allowed body fields only:
- `platform` — must be `telegram` for Stage 7 deployment control;
- `subject`;
- `target_key`;
- `idempotency_key`.

The bot must never send or offer UI for:
- shell/command;
- filesystem path;
- repository URL;
- branch/ref/tag;
- candidate SHA;
- restart command;
- environment overrides.

Candidate SHA is resolved by the privileged updater from canonical FANOOS `origin/main`.

### Status

`POST /api/internal/v1/deployments/status`

Callback-safe states to display:

```text
REQUESTED
PREFLIGHT
BACKUP
TESTING
MIGRATING
ACTIVATING
RESTARTING
HEALTHCHECK
SUCCEEDED
FAILED
ROLLED_BACK
```

Do not invent percentages. Duplicate callback/request retries must reuse an idempotency key and display the canonical existing request rather than create parallel deployments.

`FAILED` must display a safe failure code/message only. Never expose command output, tokens, environment variables or deploy credential details.

## No-cost bot/worker smoke hooks

The updater supports server-configured fixed hooks. Chat 2 may provide deployment-time hook commands/scripts for:
- Telegram API/process identity smoke;
- Bale official equivalent;
- adapter process health;
- protected-delivery no-cost authorization smoke;
- protected-media worker process health.

These hooks are installed by one-time runtime bootstrap and are never supplied by a bot callback.

## Error/retry guidance

- 401/403 service-auth or authorization: do not blind-retry; fix identity/scope/link state.
- stale timestamp/nonce replay: create a new signed request with a fresh nonce.
- idempotent create/receipt endpoints: reuse the business idempotency key but use a fresh service-auth nonce.
- 409 lock/in-progress: show the existing deployment/delivery state; do not create a second operation.
- entitlement/workspace denial: fail closed and do not use cached adapter state to bypass it.

## Secrets and logs

Bot source/config must not contain FANOOS production HMAC keys, updater credentials, DB credentials or legacy secrets. Service secrets are injected at runtime. Responses and logs must not contain them.

## Stage 7 integration readiness for Chat 2

Once this platform branch is accepted as the integration baseline and its CI is green, Chat 2 can implement adapters/workers against `contracts/openapi/internal-v1.yaml` and this document. If a bot implementation needs a contract change, return it to the integration owner rather than inventing an adapter-local variant.
