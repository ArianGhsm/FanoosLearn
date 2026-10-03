# Stage 7 Platform Gap Map

Baseline: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`

Readiness: **BLOCKED**

This document defines gaps only. It does not authorize or implement Stage 7 bot/worker/update features.

## Entry gate blockers inherited from Stages 1–6

| Gap | Current evidence | Severity | Gate |
| --- | --- | --- | --- |
| Interrupted MySQL migration can leave an unledgered partial schema | `MigrationRunner.php` + multi-effect `0006_core_platform.sql` | HIGH | Must close before unattended update |
| Deploy backup is created but not verified inside canonical deploy path | `cpanel-deploy.sh` vs backup runbook/`verify-backup.php` | HIGH | Must close before update control plane |
| OpenAPI is behind `ApiKernel` | `contracts/openapi/core-v1.yaml` vs implementation routes | HIGH | Must reconcile before bot client coding |
| No least-privilege service authentication/replay contract | no service identity/key-id/nonce middleware in current platform | HIGH | Must freeze before bot/worker calls |
| No durable deployment request/status/lock model | current release scripts use shell output/filesystem markers | HIGH | Must exist before owner-triggered update |
| No machine-enforced canonical-main/CI/previous-app compatibility gate | operator exact-SHA only | HIGH | Must exist before owner-triggered update |

## Stage 7 shared-contract gap matrix

| Contract | Existing foundation | Missing contract/behavior | Status | Severity | Required before implementation |
| --- | --- | --- | --- | --- | --- |
| Messaging link challenge | canonical users/sessions; Stage 6 design text | challenge ID/digest, expiry, single use, channel binding, initiating user context | MISSING | HIGH | Yes |
| Messaging link confirm | canonical identity/RBAC | proof-of-possession flow; protected platform subject storage; idempotent confirm; audit | MISSING | HIGH | Yes |
| Messaging link revoke | revocation patterns exist elsewhere | link revocation endpoint, reason, audit, channel cache invalidation semantics | MISSING | HIGH | Yes |
| Channel identity projection | none canonical yet | user↔channel projection with protected subject value/digest and status | MISSING | HIGH | Yes |
| Bot workspace list | account projection contains memberships | bot-safe operation-specific workspace schema and permission filtering | PARTIAL | MEDIUM | Yes |
| Bot workspace select | human session selected workspace exists | channel/session-local selected-workspace contract without mutating a human browser session implicitly | PARTIAL | HIGH | Yes |
| Bot/service authentication | bearer human session only | service identity, key ID, HMAC/signature, timestamp, nonce, body digest, replay retention, action scopes, key rotation | MISSING | HIGH | Yes |
| Service idempotency | generic DB table + feature-specific keys | transport contract: required key location, request digest, conflict/replay response, retention | PARTIAL | HIGH | Yes |
| Payment order/deep link | server-owned order creation exists | bot-safe order request/redirect projection; no client amount; channel return semantics | PARTIAL | HIGH | Yes |
| Payment status | commerce history/callback exists | narrow order status lookup/event projection for bot; stable terminal/retry states | PARTIAL | MEDIUM | Yes |
| Notification claim | outbox + recipients foundations | worker lease/claim schema, audience/channel eligibility, preference recheck | MISSING | HIGH | Yes |
| Notification deliver/receipt | recipient status columns | idempotent send result, platform message/file IDs, retry class, terminal failure, receipt dedupe | MISSING | HIGH | Yes |
| Protected delivery issue | `SecureDeliveryService.issue` | OpenAPI operation-specific response schema + service-auth rule | PARTIAL | HIGH | Yes |
| Protected delivery consume | `SecureDeliveryService.consume` | service caller semantics; channel identity/user binding; operation-specific schema | PARTIAL | HIGH | Yes |
| Protected delivery receipt | issuance/events exist | idempotent delivered/failed channel receipt; current event enum has no `delivered`/`failed` types | MISSING | HIGH | Yes |
| Protected-media job input | generic jobs foundation | versioned payload: issuance/resource version/object address/watermark policy/channel limits; capability scope | MISSING | HIGH | Yes |
| Protected-media job result | generic jobs foundation | checksum/size/render algorithm/version, output object/artifact reference, failure taxonomy, idempotency | MISSING | HIGH | Yes |
| Telegram forward protection | Stage 6 boolean contract | adapter mapping to verified official Telegram primitive + live no-cost smoke | DEFERRED | MEDIUM | Before parity claim |
| Bale forward protection | Stage 6 boolean contract | verify actual official Bale capability; honest PARTIAL/fail-closed fallback if absent | DEFERRED | MEDIUM | Before parity claim |
| Worker restart semantics | generic lease table | lease recovery, orphan policy, no duplicate paid/provider/render operation, shutdown behavior | PARTIAL | HIGH | Yes |
| Release update request | exact-SHA deploy scripts only | authenticated operator request that never accepts arbitrary ref from callback | MISSING | HIGH | Before Update Server |
| Release update status | filesystem/current pointer | durable request/phase/result/backup/tests/health/rollback record | MISSING | HIGH | Before Update Server |

## Required Stage 7 contract freeze

Before parallel Stage 7 implementation starts, `contracts/REGISTRY.md` and machine-readable contracts must freeze at least the following concepts.

### 1. Service authentication envelope

Required fields/semantics:

- service identity / key ID;
- exact HTTP method/path;
- UTC timestamp with bounded skew;
- cryptographically random nonce;
- digest of exact request bytes;
- signature over a versioned canonical string;
- server-side nonce replay rejection;
- explicit allowed service action/scope;
- rotation with overlapping key IDs and revocation;
- generic denial without secret/signature diagnostics.

Human session credentials must not be reused as worker credentials.

### 2. Messaging account link

Required lifecycle:

`challenge -> confirm -> linked -> revoked/expired`

Properties:

- random single-use challenge, digest stored;
- short expiry;
- channel fixed to Telegram or Bale;
- canonical FANOOS user is authenticated before challenge issuance;
- messaging platform ownership is confirmed through the channel, not inferred from student number/phone/chat ID;
- platform subject ID is treated as protected identity data;
- retries are idempotent;
- revocation immediately prevents future canonical actions while bot-local stale cache grants nothing.

### 3. Bot active workspace

The bot must obtain active memberships from the backend and make workspace context explicit for every domain action. Channel-local selection may be cached locally, but the backend remains authoritative and revalidates membership/scope on every request.

Never create a Telegram/Bale-specific canonical user, entitlement, payment, content or grade database.

### 4. Payment projection

Bot flow:

`request product/order -> backend prices -> backend creates canonical order -> bot displays approved deep/redirect link -> backend verifies provider callback -> bot reads canonical status/entitlement`

The bot never submits price, marks payment successful, or grants access.

### 5. Notifications

Backend owns message, audience and recipient intent. Worker owns only delivery execution/transport receipts.

Need explicit claim leases and idempotent receipts so callback/progress/UI retries cannot repeat domain operations or sends unintentionally.

### 6. Protected delivery

Preserve Stage 6 sequence:

`authorize -> issue -> worker/render if needed -> consume/reauthorize immediately before send -> protected send -> receipt`

A cached Telegram/Bale file ID is never proof of authorization.

### 7. Protected-media worker

Input/result contract must be versioned and contain only references/checksums/policy, not unrestricted filesystem paths or arbitrary shell arguments. Worker capability must be limited to necessary object/job operations.

The proven legacy/Voice PDF patterns may be used as behavioral oracle, but literal source reuse remains subject to repository reuse rules.

## API contract repair required before bot SDK/client

The current OpenAPI file must be reconciled with `ApiKernel` first. At minimum:

1. every implemented route intended for continued use must either be documented or deliberately removed before Stage 7;
2. every Stage 7-consumed route must have operation-specific request and response schemas;
3. browser-cookie/CSRF authentication must be separated from service authentication;
4. error codes used for retry, auth, entitlement, conflict and payment state must be stable;
5. idempotency requirements must be explicit per mutating edge;
6. workspace scope must be explicit and server-resolved;
7. secret fields, raw keys and server filesystem paths must be impossible in response schemas.

## Test gaps to close before Stage 7 implementation branches

Mandatory deterministic tests:

- service signature success/failure;
- stale timestamp;
- nonce replay;
- key ID unknown/revoked/rotation overlap;
- body mutation after signing;
- service action/scope denial;
- account-link challenge expiry/single-use/cross-channel misuse/revoke;
- two-workspace bot context and cross-tenant denial;
- notification double-claim and duplicate receipt;
- payment order retry with same/different request digest;
- protected delivery reauthorization after entitlement/link revocation;
- worker lease expiry/recovery and duplicate result;
- updater duplicate request/restart recovery (when Update Server is implemented later).

## Suggested sequencing after blockers are closed

1. Repair Stage 3 migration interruption semantics and tests.
2. Repair Stage 4 verified-backup/release lifecycle prerequisites.
3. Reconcile `ApiKernel` and OpenAPI.
4. Add/freeze service-auth and Stage 7 DTO/error/idempotency contracts.
5. Implement account linking + bot workspace projection.
6. Implement thin Telegram/Bale API clients over canonical backend.
7. Implement notification claim/receipt.
8. Implement protected-media worker and protected send adapters.
9. Add payment bot projection.
10. Run official capability/live no-cost parity checks.
11. Re-gate Stage 7 before any production activation.

## Decision

Stage 7 feature coding is **not authorized from this audit baseline**. Contract/remediation work is authorized and should be completed first.
