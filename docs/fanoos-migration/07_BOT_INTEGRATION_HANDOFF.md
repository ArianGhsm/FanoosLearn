# Stage 7 Bot → Platform / Integration Handoff

Status: **SOURCE INTEGRATED / RUNTIME VALIDATION REQUIRED**

Source baseline audited for this handoff: `c8ece207bb6f6555e283c593020fdedf518b6420`.

The Telegram + Bale Stage 7 consumer workstream is merged. PR #5 added the bot/worker source surface and PR #6 hardened protected-delivery receipt replay safety. This document lists only the remaining work that cannot be completed safely inside the bot-owned paths without a new/frozen Platform or Integration contract.

## Already integrated on main

- shared Python `internal-v1` HMAC client; exact raw-body digest/signature, nonce/timestamp and bounded safe retries;
- separate Telegram and Bale runtimes, tokens, offsets and disposable local state;
- secure link-challenge consume and canonical workspace selection;
- server-owned order creation/status and entitlement projection;
- notification projection/lease/send/receipt flow;
- protected delivery issue → consume/re-authorize → provider send → receipt;
- Telegram `protect_content` and explicit Bale fail-closed behavior when forward protection is required;
- Telegram-only two-step Update Server requester/status flow without arbitrary repository/ref/SHA/path/shell input;
- bounded protected-PDF processing engine and private temporary/spool handling;
- restart-safe protected-delivery receipt outbox with processed-update dedupe, bounded fail-closed capacity and poison-row starvation protection;
- bot/worker service templates, health/smoke entrypoints and one-time Codex runtime bootstrap documentation.

## Platform contract work still required

The route names below are deliberately **not invented here**. Platform owns the names, schemas, service-action identifiers, error codes and OpenAPI freeze. Bots must consume only the subsequently merged `internal-v1` contract.

### 1. Messaging unlink / revoke

Current `internal-v1` exposes link challenge **consume** but no bot-safe unlink/revoke operation.

Platform must freeze a service-authenticated operation that:
- identifies the channel with canonical `platform + subject`, never by an inferred user id;
- revokes only the requested messaging identifier/link;
- does not delete the canonical user or unrelated channel links;
- invalidates any channel-local selected-workspace projection as appropriate;
- is idempotent and auditable;
- returns a safe machine-testable result/error envelope.

Acceptance tests must cover repeated revoke, already-revoked link, cross-channel isolation, and inability to revoke another subject.

### 2. Bot-safe academic and content read projections

Current `internal-v1` has no read operation for the Stage 7 student UX covering schedule/today/tomorrow, grades, announcements, or accessible resource/catalog listing.

Platform must freeze read-only projections for the linked canonical user and selected/explicit workspace. At minimum the semantic surface must support:
- schedule by canonical date/range and workspace timezone;
- the linked user's grades/results only;
- workspace announcements with stable ids and bounded pagination;
- accessible resource/catalog metadata with stable canonical `resource_id` suitable for the existing secure delivery flow.

Requirements:
- linked subject + canonical membership/workspace authorization on every request;
- no browser session or CSRF impersonation;
- no bot-local clone of academic/content domain state;
- bounded result sizes/pagination;
- stable ids and explicit timezone/date semantics;
- no raw storage path/key in any response;
- least-privilege service actions selected and frozen by Platform.

After this is merged, the bot workstream can replace the current web/navigation fallback with native quick views without duplicating the website domain model.

### 3. Protected-media source capability redemption

`protected-media/claim` currently returns an opaque `object_capability`, and `ProtectedMediaJobService` intentionally does not expose a storage key/path. There is no frozen service/HTTP operation that redeems that capability into bounded source bytes, so the production worker correctly stops before claiming jobs.

Platform must freeze the redemption boundary with all of these properties:
- capability is opaque and short-lived and cannot be converted by the worker into a raw storage address;
- redemption is bound to the leased job/source and expires no later than the permitted processing window;
- server enforces an upper byte bound and safe MIME/content expectations;
- no directory traversal, arbitrary filesystem path, bucket key or unrestricted URL is accepted from the worker;
- replay/expiry and authorization-change behavior is defined and tested;
- logs contain ids/hashes/sizes/timings, not source bytes or secrets.

The exact transport (streaming internal response, a separately constrained object capability, or another Platform-owned mechanism) belongs to Platform and must be frozen before the worker adapter is enabled.

### 4. Protected derivative publication and retrieval

`MediaComplete` currently records checksum, size, MIME and opaque `artifact_ref`, but there is no frozen end-to-end contract for publishing the derivative into canonical protected storage and later retrieving that exact derivative for a re-authorized bot delivery.

Platform must define:
- how the worker publishes the derivative without exposing a general-purpose storage credential;
- canonical ownership/lifetime of the resulting artifact;
- how `artifact_ref` is validated and associated with the correct job/issuance/user/resource-version;
- how a bot receives only a short-lived capability for the completed derivative after a fresh delivery re-authorization;
- checksum/size/MIME verification before provider upload;
- idempotent completion/retry and no cross-user derivative reuse;
- cleanup/expiry semantics for failed, superseded and expired artifacts.

The bot must never fall back to the original protected source merely because derivative retrieval is unavailable.

### 5. Owner control-plane read projection

The current control plane safely supports deployment **request** and **status**, but there is no side-effect-free bot-safe projection for the owner panel sections requested by Stage 7: current release, candidate/update availability, system/update health, last deployment and whether the linked user may see deployment controls.

Platform must freeze a read-only, permission-filtered owner projection. It must:
- reveal no secret/env/raw log or unrelated PII;
- resolve the deploy candidate server-side from the canonical approved source only;
- expose real states only, never invented percentage/ETA;
- allow the Telegram adapter to hide/deny Update Server controls for users without canonical deployment permission without performing a deployment request as a permission probe.

Bale must remain without deployment controls unless a later explicit product/security decision changes that policy.

## Integration-only CI wiring requested

`.github/workflows/**` is Integration-Only and was intentionally not edited by the bot workstream.

Integration should add the smallest Python bot/worker CI job that:
1. checks out the exact PR commit;
2. provides a supported Python 3 runtime;
3. runs `bash ops/stage7-bots/run-deterministic-tests.sh`;
4. fails the PR if compileall or any `tests/bots` / `tests/workers` unittest fails.

Do not weaken the existing PHP/MySQL, repository-safety or exact-commit artifact gates. The deterministic Python tests do not require live Telegram/Bale tokens, paid providers, or production secrets.

## Runtime/bootstrap work after the contracts merge

Only after Sections 1–5 that are required for the chosen launch scope are merged and bot adapters are updated:
- execute `07_CODEX_ONE_TIME_RUNTIME_BOOTSTRAP.md` against one exact approved main SHA;
- create separate runtime identities/env/state for Telegram, Bale, notification projector and protected-media worker;
- install and verify qpdf, Poppler and required Pillow/font shaping support for protected media;
- run backend/API health plus Telegram and Bale `getMe` smoke;
- run safe message/callback/workspace/payment/notification smoke without paid-provider side effects;
- verify Telegram protected-content behavior with an authorized disposable fixture;
- verify Bale required-protection case fails closed;
- rehearse protected-media malformed/oversize/page/time/cleanup cases;
- exercise Update Server dry/supervised flow only if the user explicitly authorizes the real update step;
- keep live results as `RUNTIME_VALIDATION_REQUIRED` until actually observed.

## Re-entry gate for the bot workstream

Resume bot feature coding only after the relevant new Platform contracts are merged to `main` and visible in `contracts/openapi/internal-v1.yaml` plus the Platform handoff docs. At re-entry:
1. read the new `main` SHA and open PRs;
2. compare the new internal contract to this handoff;
3. update only bot/worker consumers first;
4. add deterministic contract/regression tests;
5. open a scoped PR and require green repository CI;
6. do not claim production parity before live runtime smoke.

Until then the correct product status is **PARTIAL / RUNTIME_VALIDATION_REQUIRED**, not production-complete.
