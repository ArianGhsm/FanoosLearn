# Stage 7 — Platform / Backend Contracts

## Status and scope

Stage 7 makes the FANOOS backend the single canonical authority consumed by Telegram, Bale and protected-media/notification workers. It does not add an independent bot product database and does not import legacy runtime state.

Canonical invariants:
- identity is `iam_users` plus FANOOS authentication; a Telegram/Bale subject is only a linked identifier after a one-time FANOOS challenge;
- workspace authorization always rechecks `tenant_workspace_memberships` and scoped RBAC;
- entitlement remains a separate canonical decision;
- payment status is server/provider verified; adapter-visible redirect/callback state is not payment proof;
- protected delivery reauthorizes every consume;
- channel-local cached file/message IDs are delivery optimizations only and never authorization evidence.

## Audit integration reconciliation

The historical pre-Stage 7 platform audit and Telegram/Bale audit are now part of the `main` integration baseline. Their HIGH/WAIT_FOR_PLATFORM findings were reconciled before this implementation was accepted: interrupted migration recovery, verified-backup gating, OpenAPI/service-auth drift, account linking, notification/protected-delivery receipts, protected-media worker protocol, bot payment projection and the safe Update Server control plane all have machine-testable contracts in this branch. The audit documents remain historical evidence; production runtime/bootstrap validation remains a separate gate and is not claimed by source CI.

## Human messaging linking

Human endpoints use the existing FANOOS bearer/session and CSRF contract:

| Method | Path | Purpose |
| --- | --- | --- |
| POST | `/api/v1/messaging/link-challenges` | Create a short-lived one-time challenge for `telegram` or `bale`. |
| POST | `/api/v1/messaging/links/{platform}/revoke` | Revoke the current canonical link for that platform. |

A challenge token is returned once to the authenticated human client; SQL stores only its SHA-256 digest. It has a short TTL, one-time consumption, cooldown/rate protection and audit events. Phone number, student number and chat ID are not implicit identity proof.

The adapter consumes the challenge through the signed internal endpoint and supplies the platform subject observed directly from that platform. FANOOS stores a keyed subject digest for lookup and encrypted subject bytes for outbound addressing. A subject cannot be linked to two canonical users at the same time.

## Service authentication

Internal service authentication is separate from human sessions and browser CSRF. Shared contract: `contracts/openapi/internal-v1.yaml`.

Required headers:
- `X-Fanoos-Key-Id`
- `X-Fanoos-Timestamp`
- `X-Fanoos-Nonce`
- `X-Fanoos-Content-SHA256`
- `X-Fanoos-Signature`

Canonical string, including exact raw request-body digest:

```text
fanoos-service-v1
<METHOD>
<PATH>
<UNIX_SECONDS>
<NONCE>
<SHA256_RAW_BODY>
```

`X-Fanoos-Signature = HMAC-SHA256(canonical_string, service_secret)` as lowercase hex.

Rules:
- key ID identifies metadata only; secret values stay outside Git and SQL and are resolved by configured environment name;
- timestamp skew is bounded;
- nonce digests are durably persisted and replay is rejected;
- each service identity has an allowlisted action set;
- Telegram adapter identities cannot impersonate Bale and vice versa;
- safe authentication errors do not echo signatures, secrets or canonical strings.

Dentistry's timestamp/nonce/body-bound signing behavior was used as the oracle. Its JSON persistence was not reused because FANOOS SQL is canonical.

## Workspace projection

Internal endpoints:
- `POST /api/internal/v1/messaging/workspaces/list`
- `POST /api/internal/v1/messaging/workspaces/select`

The adapter supplies its linked platform subject. FANOOS resolves the canonical user, lists only current active memberships, and rechecks membership at every selection/use. `messaging_channel_contexts` stores only a bounded channel preference; it is deleted/ignored as soon as canonical membership becomes invalid. It is not a duplicate membership store.

## Payment projection

Internal endpoints:
- `POST /api/internal/v1/commerce/orders`
- `POST /api/internal/v1/commerce/orders/status`

`BotCommerceService` reuses the existing Commerce/Entitlements authorities. Product title, current price, currency and payment URL come from the server. Bot responses intentionally omit callback tokens/provider authority material. Entitlement projection is read from canonical entitlements; only verified provider settlement can create the order-derived entitlement.

## Notifications

Internal endpoints:
- `POST /api/internal/v1/notifications/project`
- `POST /api/internal/v1/notifications/claim`
- `POST /api/internal/v1/notifications/receipt`

Domain code still writes FANOOS outbox events. The notification projector materializes Telegram/Bale recipient deliveries only for active memberships, active links and enabled channel preferences. Unique boundaries prevent duplicate fan-out. Adapters lease a delivery, return an idempotent receipt and can request bounded retry. Payloads contain the notification content plus the platform subject needed for dispatch; phone/student identifiers and secrets are not emitted.

## Protected delivery

Internal endpoints:
- `POST /api/internal/v1/deliveries/issue`
- `POST /api/internal/v1/deliveries/consume`
- `POST /api/internal/v1/deliveries/receipt`

Issue is exact-resource/current-version authorization. Consume validates the signed short-lived issuance and then rechecks canonical RBAC/entitlement and exact version again. The response provides a short-lived scoped object capability, never a raw storage path. Telegram/Bale responses keep `forward_protection_required=true`. A platform cached file ID can be recorded as an opaque receipt reference but cannot bypass consume/reauthorize.

## Protected-media worker

Internal endpoints:
- `POST /api/internal/v1/protected-media/enqueue`
- `POST /api/internal/v1/protected-media/claim`
- `POST /api/internal/v1/protected-media/complete`
- `POST /api/internal/v1/protected-media/fail`

The backend owns job identity, exact workspace/resource/version/object/issuance binding, forensic identity and watermark label. Claim reauthorizes before returning:
- job ID and completion key;
- workspace/resource/version IDs;
- short-lived scoped object capability;
- canonical watermark label and forensic ID;
- renderer algorithm version;
- max input bytes/pages/time;
- bounded lease.

Worker completion returns only checksum, size, `application/pdf` MIME and opaque artifact reference. Raw unscoped paths and secrets are forbidden. Failure codes are a bounded taxonomy. Full Python rendering remains a bot/worker workstream responsibility.

## Reuse decisions

- Dent signed request/nonces: behavior adapted to SQL-backed FANOOS service identities and durable nonce replay protection.
- Dent JSON persistence: intentionally not reused.
- Voice Telegram/Bale independent product databases: intentionally not reused; FANOOS shares canonical identity/workspace/payment/content/entitlement state.
- Existing FANOOS Commerce, Entitlements and SecureDelivery: reused rather than redesigned.

## Runtime-only validation

Before production use, bootstrap must provision independent Stage 7 secrets, service identities and the required PHP/OpenSSL/runtime support. Platform adapters must validate their official forward-protection behavior in the actual channel runtime. No production server validation is claimed by this repository change.
