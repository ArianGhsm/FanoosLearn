# FANOOS secure delivery contract

## Security objective

Secure delivery reduces accidental redistribution, makes authorized issuances traceable and prevents direct tenant/entitlement bypass. It is not absolute DRM. Screen capture, camera recording and a determined authorized recipient remain outside the guarantees of web or messaging platforms.

## Authorities

| Decision | Canonical authority |
| --- | --- |
| User identity | `iam_users` plus the authenticated FANOOS session/account link |
| Workspace access | active membership plus scoped RBAC |
| Paid access | FANOOS `entitlement_grants`, created only by verified payment/admin flow |
| Published bytes/version | `content_resources.current_version_no`, approved immutable version and verified object |
| Delivery trace | `content_delivery_issuances` and `content_delivery_events` |
| Bot send/cache state | Prompt 7 bot-local bounded state only |

Bots must not treat a Telegram/Bale account, cached file ID, prior successful send or callback query as an entitlement.

## Issue flow

`POST /api/v1/workspaces/{workspaceId}/resources/{resourceId}/deliveries`

Authenticated input:

```json
{ "channel": "telegram" }
```

The backend checks published state, resource scope, membership, `resource.view`, configured entitlement and verified object. It then records an issuance and returns:

```json
{
  "issuance_id": "uuid",
  "delivery_token": "short-lived-signed-token",
  "expires_at": "UTC timestamp",
  "watermark": {
    "visible_label": "display name plus short issuance suffix",
    "forensic_id": "truncated issuance-bound HMAC",
    "algorithm": "issuance-hmac-sha256-v1"
  },
  "delivery_contract": {
    "channel": "telegram",
    "forward_protection_required": true,
    "reauthorize_on_serve": true
  }
}
```

The HMAC material binds issuance, workspace, resource, immutable version and user. Raw signing keys, payment data and legacy IDs are never returned or stored in logs.

## Consume flow

`POST /api/v1/workspaces/{workspaceId}/deliveries/consume`

```json
{ "delivery_token": "value from issue" }
```

Consume requires the same canonical user/workspace context. The backend verifies signature and expiry, matches the stored token digest, rejects revoked issuances, and then repeats resource/RBAC/entitlement authorization. If the current publication or entitlement changed, delivery is denied even if a token or cached platform file exists.

Successful output contains the exact resource/version, structured content or a short-lived tenant-bound object token, and `forward_protection_required`. The serve event and audit event are persisted. Object storage keys and filesystem paths are not exposed.

## Bot-facing rules for Prompt 7

1. Link a messaging account to one canonical FANOOS user through a single-use, short-lived backend challenge; the bot account is not identity authority.
2. Ask the backend for available workspaces and require explicit active workspace selection.
3. Issue and consume immediately before every protected send, including cache hits.
4. If `forward_protection_required=true`, use the messaging platform's protected-content flag. Fail closed if the client/channel cannot honor it.
5. Apply the visible label and forensic material in the isolated rendering worker before uploading/sending a protected PDF.
6. Cache only a derivative keyed by resource version plus watermark algorithm/recipient issuance policy. Never reuse a recipient-specific derivative for another user.
7. Keep platform file IDs, send status and bounded retry state locally; do not copy catalog, entitlement or payment authority.
8. Record delivery success/failure through a signed backend notification hook. Do not include raw document bytes or secrets in event payloads.

## Account-linking contract for Prompt 7

The backend contract must use:

- `challenge_id`: random, single-use, short-lived and stored as a digest;
- `channel`: `telegram` or `bale`;
- platform subject ID stored only as a protected digest plus separately encrypted value if outbound sends require it;
- confirmed canonical `user_id`;
- revocation timestamp and audit event;
- no student-number/phone/chat-ID matching as automatic proof of ownership.

The final link endpoints belong to Prompt 7; Prompt 6 only fixes the content-delivery dependency.

## Payment deep-link and callback expectations

- A bot requests a server-created order/deep link; it never submits price or grants access.
- Payment provider callback verification remains the Prompt 5 backend responsibility.
- The bot polls/receives only the canonical order result and entitlement projection.
- A successful-looking redirect, message or bot callback is not proof of payment.

## Notification hooks

Content publication should emit a transactional outbox event with resource/workspace/version identifiers only. Prompt 7 may consume it to enqueue notifications after rechecking audience and channel preference. Delivery failures must be retryable and must not roll back publication.

## Key and token handling

- `FANOOS_DELIVERY_SIGNING_KEY` and `FANOOS_DOWNLOAD_SIGNING_KEY` are distinct, minimum 32-byte secrets outside Git/release artifacts.
- Token TTL is bounded to 30–900 seconds.
- Stored delivery tokens are SHA-256 digests, not bearer values.
- Every token is user-, workspace-, resource- and version-bound.
- Denial responses do not reveal whether another tenant owns a resource.

## Rendering boundary

Prompt 6 generates a visible watermark label and opaque forensic ID. Actual PDF rasterization/fingerprinting is intentionally not run on the unapproved shared host. Prompt 7 must place the proven algorithm behind an isolated worker, verify qpdf/PDF dependencies, bound file/page/time limits, remove active attachments/text when policy requires, and retain attack-regression fixtures before claiming byte-level watermark parity.
