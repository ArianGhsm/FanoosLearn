# Stage 7 bot dependency and ownership matrix

Audit date: 2026-09-07  
Audited FANOOS SHA: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`

This document fixes ownership boundaries for Telegram, Bale and the protected-media worker. It does **not** add or name new backend endpoints. Where the current platform contract is incomplete, the dependency is recorded rather than invented.

## Core rule

Telegram and Bale are channel adapters and conversation UIs. They do not own FANOOS identity, workspace membership, roles, payments, entitlements, content, notifications or protected-delivery authorization.

A platform `user_id`, callback payload, payment-success message or cached `file_id` is never a substitute for canonical backend proof.

## Stage 7 dependency matrix

| Capability | Canonical FANOOS foundation | Current bot-safe contract state | Stage 7 dependency action |
| --- | --- | --- | --- |
| Resolve linked Telegram/Bale principal | IAM can represent Telegram/Bale identifiers | **MISSING** secure challenge/confirm/revoke and linked-user service projection | Platform Chat must freeze/implement before handlers. |
| Authenticate bot runtime to backend | Target architecture documents signed service calls; idempotency/job/audit primitives exist | **CONTRACT_ONLY**; current HTTP boundary is bearer session + CSRF | Platform Chat must supply service identity/signature/replay/idempotency contract. |
| List canonical workspaces | Memberships + account/workspace API exist | **READY**, transitively gated by linked service principal | Shared client consumes canonical list; no bot DB mirror. |
| Select active workspace | Canonical session behavior exists | **READY** domain behavior; bot-specific projection semantics must be confirmed | Selection must be membership-validated and backend authoritative. |
| Schedule | Canonical tenant-scoped projection exists | **READY** | Read through shared client after service/link auth. |
| Grades | Canonical own-grade projection exists | **READY** | Read through shared client; strict cross-tenant/user tests. |
| Announcements | Canonical inbox/read behavior exists | **READY** for user query | Proactive send additionally needs notification claim/receipt contract. |
| Content library/resource | Generic content engine/access filtering exists | **READY** | Use canonical resource IDs and access decisions. |
| Create payment order | Idempotent commerce order + gateway start returns redirect URL | **CONTRACT_ONLY** for Stage 7 bot use; live provider caveat remains | Platform handoff must freeze bot-facing order/result/status shape. |
| Payment status/result | Canonical orders/payment attempts/history exist | **CONTRACT_ONLY** for proactive result delivery | Never infer from messenger payment/result text. |
| Entitlement | Canonical fail-closed entitlement service exists | **READY** | Recheck immediately before protected delivery. |
| Protected-delivery issue/consume | Secure-delivery contract + implementation exist | **READY** | Every protected send issues/consumes/rechecks even on `file_id` cache hit. |
| Protected send receipt | Delivery events exist conceptually; Prompt 7 dependency requires receipt | **CONTRACT_ONLY** | Platform Chat must freeze signed success/failure receipt semantics. |
| Notification claim/lease/receipt | Notification tables + outbox/job primitives exist | **CONTRACT_ONLY** | Platform Chat must expose restart-safe claim/ack/receipt semantics. |
| Protected-media render job | Generic leased jobs + secure-delivery worker boundary | **CONTRACT_ONLY** | Platform Chat must freeze input/result/lease/retry protocol. |
| Update request/status | Deploy/rollback scripts/docs exist | **MISSING** durable control-plane contract/permission | Do not implement owner update button until platform gate passes. |

## Exact state ownership matrix

| Data/state | Canonical owner | Telegram local? | Bale local? | Rebuildable? / recovery rule |
| --- | --- | --- | --- | --- |
| FANOOS user identity | Backend IAM | No | No | Canonical DB backup/restore. |
| Telegram↔FANOOS link | Backend IAM/link contract | No | No | Canonical DB. Telegram ID alone cannot recreate proof. |
| Bale↔FANOOS link | Backend IAM/link contract | No | No | Canonical DB. Bale ID alone cannot recreate proof. |
| Link challenge/expiry/consume state | Backend | No | No | Canonical durable security state; replay-safe. |
| Workspace membership | Backend | No | No | Canonical DB. |
| Active workspace selection/projection | Backend according to final Platform contract | Optional short-lived display cache only | Optional short-lived display cache only | Cache is disposable; membership is always revalidated. |
| Roles / permissions | Backend RBAC | No | No | Canonical DB. |
| Schedule | Backend academics | No canonical copy | No canonical copy | Any read cache must be bounded/disposable and tenant-keyed. |
| Grades | Backend | No | No | Do not persist bot-side grade mirrors. |
| Announcements | Backend | No canonical copy | No canonical copy | Local render cache only if bounded. |
| Products / prices | Backend commerce | No | No | Canonical DB. |
| Orders / payment attempts / provider status | Backend commerce | No | No | Canonical DB. |
| Entitlements | Backend | No | No | Canonical DB; every sensitive action rechecks. |
| Content/resource/version metadata | Backend content | No | No | Canonical DB/object storage. |
| Source protected object | Backend-controlled object storage | No permanent copy | No permanent copy | Worker scratch only under final job policy. |
| Watermark/tracing identity | Backend secure-delivery issuance | No | No | Canonical issuance/audit. Never derive from display name alone. |
| Protected derivative | Backend-controlled delivery object/job result | Transport may temporarily read/send | Transport may temporarily read/send | Per final retention policy; never cross-user derivative reuse. |
| Notification intent/recipient state | Backend notifications | No | No | Canonical DB. |
| Notification delivery claim/lease/receipt | Backend | Adapter holds only current lease/reference | Adapter holds only current lease/reference | Recover from canonical pending/lease state. |
| Protected-delivery issuance/consume/receipt | Backend | Only current opaque reference | Only current opaque reference | Canonical DB/audit. |
| Protected-media job | Backend job system | No | No | Canonical durable job + result. |
| Update request/status/active lock | Backend/update control plane | Only current request reference for UI | None by default | Canonical durable status must survive bot/service restart. |
| Telegram bot token | Secret runtime config | Yes, Telegram env only | No | Protected secret backup/provisioning; never Git/logs. |
| Bale bot token | Secret runtime config | No | Yes, Bale env only | Protected secret backup/provisioning; never Git/logs. |
| Telegram update offset | Telegram runtime state | **Yes** | No | Persist separately. Exact reconstruction is not guaranteed; duplicate-safe backend actions are required. |
| Bale update offset | Bale runtime state | No | **Yes** | Persist separately. Never share offset/update stream with Telegram. |
| Telegram `file_id` cache | Bounded transport cache | **Yes** | No | Rebuildable/disposable; bot-specific. Never entitlement. |
| Bale `file_id` cache | Bounded transport cache | No | **Yes** | Rebuildable/disposable; bot-specific. Never entitlement. |
| Callback/view cache | Bounded transport/UI cache | Yes | Yes, separate | Disposable; callback must be reauthorized. |
| Transient send retry cursor | Adapter state only if needed | Yes, separate | Yes, separate | Prefer reconstruction from canonical claim/receipt. If locally durable, classify/back up explicitly; never canonical business state. |
| Process health/metrics/log cursor | Runtime/operations | Yes, separate | Yes, separate | Rebuildable except audit-worthy external logs retained by ops policy. |

## Service-auth dependency

FANOOS target architecture already establishes the correct family of controls: service key identity, timestamp, nonce, body digest and idempotency. Dentistry proves the pattern operationally. Stage 7 must wait for the FANOOS-native implementation and test vectors.

Required properties of the final Platform contract, without prescribing endpoint names:

- service key ID identifies a specific runtime/service, not a human;
- signature covers canonical request data including timestamp, nonce and body digest;
- bounded clock skew;
- nonce replay is durably rejected for the replay window;
- request body tamper changes the signature;
- mutating operations accept an idempotency key and reject conflicting reuse;
- service authentication does not bypass user RBAC/tenant/entitlement checks;
- linked platform principal is resolved to a canonical FANOOS user by the backend;
- secret material is never returned in errors/logs.

A bot must not create a browser bearer session or maintain a table mapping platform IDs to privileged roles as a workaround.

## Account-link ownership and flow requirements

The secure flow should preserve these semantics while leaving exact endpoint names/payloads to Platform Chat:

1. interaction occurs in a private bot chat for account linking;
2. bot supplies platform + platform user identity to the canonical backend through service auth;
3. backend issues an opaque, short-lived, one-time challenge bound to that platform principal;
4. the user proves the FANOOS account through canonical web/login/approved account proof;
5. backend atomically confirms the exact link and rejects active-conflict/replay/expiry;
6. bot reads the linked canonical account projection;
7. revoke is a backend operation with audit and immediate effect on later requests.

Telegram deep linking can transport an opaque challenge reference within its official 64-character limit. It cannot carry a trusted account/role/entitlement assertion. Bale start payload is undocumented in current official docs, so the shared linking design must not depend on it; a normal HTTPS link/button is acceptable as transport once the backend challenge exists.

## Callback ownership

Callbacks are transport UI state only.

Allowed callback contents:

- compact version/type discriminator;
- opaque resource/action reference or short server-issued action token;
- optional non-security presentation cursor.

Disallowed callback contents as trusted state:

- role/permission decisions;
- entitlement status;
- payment success;
- canonical workspace membership;
- arbitrary branch/SHA/path/shell command;
- raw secrets;
- user identity proof.

Both Telegram and Bale document a 1–64 **byte** callback limit. Tests must use UTF-8 byte length, including Persian labels/data where relevant.

## Protected delivery boundary

### Common sequence

For a protected resource send, the shared application layer must follow the canonical secure-delivery flow rather than send a raw stored object:

1. resolve linked canonical user and selected workspace;
2. request a fresh backend delivery issuance for the intended channel/resource;
3. canonical backend checks publication, RBAC, membership and entitlement;
4. if a per-user derivative is needed, wait on the canonical protected-media job/result rather than generating identity locally;
5. consume/redeem the issuance immediately before platform send, causing the required reauthorization;
6. send the approved derivative/object with platform-specific restrictions;
7. report actual send outcome through the final canonical receipt contract;
8. discard/expire local scratch and retain only bounded platform transport metadata allowed by policy.

A cached `file_id` can optimize step 6 only. Steps 2–5 and 7 still occur.

### Telegram

When the canonical issuance requires forward/save protection, Telegram adapter must set official `protect_content=true`. This is defense in depth, not DRM. FANOOS must still watermark/trace protected derivatives where required and must not promise prevention of screenshots, external cameras or every extraction technique.

### Bale

The current official Bale bot documentation does not document `protect_content` or another equivalent forward/save restriction. Therefore:

- `forward_protection_required=false`: normal Bale document/media delivery may proceed if all other authorization requirements pass;
- `forward_protection_required=true`: direct Bale protected-media send **fails closed**;
- no Telegram-only `protect_content` field is forwarded to Bale;
- an alternate controlled web-delivery path can only be enabled by a future explicit platform/product contract.

### Derivative/cache rule

Protected derivatives carrying user/issuance watermark identity must never be reused across users. A platform `file_id` that points to such a derivative inherits that restriction. Cross-user `file_id` reuse for protected derivatives is prohibited even if the external platform technically accepts it.

## Payment boundary

FANOOS commerce owns quote, order, gateway state and entitlement. The bot may present a backend-generated redirect URL and later render canonical status, but it cannot finalize access based on:

- a user screenshot;
- a Telegram/Bale successful-looking message;
- a callback data flag;
- a locally stored “paid” marker;
- a provider authority copied from another order.

The current commerce implementation is idempotent and returns a redirect URL, but Stage 7 needs the Platform Chat to freeze the bot-facing result/status projection and proactive payment-result delivery semantics before production handlers are written.

## Notification boundary

The desired restart-safe model is canonical claim/lease/send/receipt:

```text
backend pending recipient
  -> service-authenticated bounded claim/lease
  -> platform adapter sends
  -> signed success/failure receipt
  -> backend terminal/retry state
```

Dentistry's delivery queues validate this behavior pattern. FANOOS should use its relational notification/outbox/job primitives, not Dent's JSON stores.

Minimum final semantics:

- no two live workers simultaneously own the same active lease;
- lease expiry allows recovery after crash;
- ack is idempotent;
- duplicate ack/send result cannot mutate a different recipient;
- failure has bounded retry/error code;
- success is recorded only after actual platform success;
- channel/platform identity is checked against the intended linked user;
- worker restart cannot turn “unknown” into false “delivered”.

## Telegram/Bale runtime separation

Even with one shared semantic package, runtime isolation is mandatory:

| Runtime concern | Telegram | Bale |
| --- | --- | --- |
| Token/env | Telegram-only | Bale-only |
| Process/service | Separate | Separate |
| Polling/webhook stream | Separate | Separate |
| Update offset | Separate | Separate |
| Transport cache / file IDs | Separate | Separate |
| Retry/backoff state | Separate | Separate |
| Platform capability record | Telegram official/configured transport | Bale official/configured transport |
| Logs/health | Separate service label/correlation | Separate service label/correlation |

One platform outage/restart must not corrupt or consume the other platform's update stream.

## Security test matrix required after platform handoff

| Area | Required positive/negative tests |
| --- | --- |
| Service auth | valid signature; unknown service; bad signature; stale/future timestamp; nonce replay; body mutation; idempotent duplicate; conflicting idempotency reuse. |
| Linking | valid challenge; expiry; replay; wrong platform; wrong platform user; already-linked conflict; revoke; revoked principal denied; platform ID without proof denied. |
| Workspace | multi-membership list; valid select; non-member workspace denied; stale cached selection revalidated. |
| Grades/content | own data only; foreign workspace/resource ID denied; same-name cross-tenant fixtures do not leak. |
| Callbacks | tamper; stale opaque reference; 64-byte boundary; Persian UTF-8 byte length; duplicate callback; ack occurs before expensive I/O. |
| Payments | duplicate create idempotent; mismatched buyer/order denied; fake success message ignored; verified backend status grants entitlement once; failed/refunded remains unauthorized per policy. |
| Secure delivery | issuance expiry; consume replay; entitlement revoked between issue/consume; stale `file_id` cannot bypass reauth; cross-user derivative/file ID denied; receipt success/failure correctness. |
| Telegram protection | `protect_content` present only when required/approved; failure to apply required protection fails send; file-size/rate error path. |
| Bale protection | required forward protection refuses direct send; unsupported field never serialized; unprotected allowed resource follows normal send path. |
| Notifications | exclusive lease; lease expiry; crash/restart; retry; duplicate ack; wrong recipient/platform ack denied; no false delivered state. |
| Transport separation | Telegram token/update/offset/cache cannot be read or advanced by Bale runtime and vice versa. |
| Owner/update | group chat denied; unlinked/unauthorized user denied; Telegram ID spoof denied; second confirmation required; callback cannot inject SHA/branch/shell; duplicate request idempotent; one active lock; restart-visible terminal result. |

## Runtime/backup classification for one-time bootstrap

After Stage 7 code and platform contracts are merged, the one-time server bootstrap must classify state rather than copying a legacy server wholesale:

### Must be provisioned/recoverable

- separate Telegram/Bale secret env files;
- service identity/signing credentials;
- separate service users/units and state directories;
- durable local update offsets if polling implementation requires them;
- any non-reconstructable adapter retry metadata explicitly approved by architecture;
- protected-media tool versions/configuration;
- privileged updater service configuration after control-plane approval.

### Rebuildable; exclude from canonical backup

- platform `file_id` caches;
- temporary render scratch;
- Python bytecode/package caches;
- bounded presentation caches;
- downloaded release build artifacts that can be reproduced from the approved SHA.

### Never in Git

- tokens/private keys/signing secrets;
- live offsets/retry databases;
- runtime logs containing user/platform identifiers beyond approved redacted fixtures;
- generated protected user derivatives;
- server session/runtime state.

## Gate

The ownership model is ready. The production bot boundary is not.

**Prompt 2 condition: `WAIT_FOR_PLATFORM` until the missing/contract-only rows above are merged as machine-testable FANOOS contracts.**