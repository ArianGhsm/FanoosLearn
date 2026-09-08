# Stage 7 — Bot Migration / Implementation Report

## Status

**SOURCE INTEGRATION COMPLETE / RUNTIME VALIDATION REQUIRED**

Bot/worker contract-consumption baseline: `e44145c01ac26880264d369f6c70f3dd952a6526`, the merged Platform handoff main SHA. FANOOS remains the only runtime/domain authority. Legacy repositories were behavioral references only and are not runtime dependencies.

## Architecture delivered

- `packages/python/fanoos_bot`: presentation-neutral signed backend client, application flow, capability registry, callback codec, Unicode chunking, bounded disposable local state and atomic protected-document delivery model.
- `apps/telegram-bot`: independent Telegram process/token/update offset, native academic/content reads, secure protected delivery and private owner update UX.
- `apps/bale-bot`: independent Bale process/token/update offset with explicit capability downgrade and fail-closed forward-protection behavior.
- `apps/workers/notification-projector`: canonical outbox projection process.
- `apps/workers/protected-media`: active source-level worker implementation using only Platform source/publish capabilities; no private-spool production publication path.

## Platform contracts consumed

The bot package now consumes the frozen `internal-v1` endpoints for:
- signed subject unlink/revoke;
- workspace list/select;
- schedule, self grades, announcements and authorized resource catalog;
- order/status and entitlement projection;
- notification lease/receipt;
- delivery issue/consume/receipt;
- protected-media enqueue/claim/source redeem/authorize publish/raw PDF publish/complete/fail;
- personalized derivative issue/redeem;
- Telegram-only deployment overview/request/status.

Binary PDF responses and raw PDF uploads are covered by the same exact-body HMAC contract as JSON requests. MIME, PDF magic and byte limits are validated client-side as defense in depth.

## Protected PDF flow

1. Bot obtains a canonical delivery issuance and consumes it, forcing current authorization.
2. For object-backed protected content, Platform enqueues one idempotent media job.
3. Bot stores only a bounded disposable correlation of job/subject/workspace/issuance so a later button press can report the correct delivery receipt. This state grants no access.
4. Worker claims the job and redeems exact source PDF bytes through the leased source capability.
5. Worker validates/rasterizes/watermarks within page/byte/time limits.
6. Worker requests an upload capability bound to job + lease + completion key + checksum + size + MIME, then uploads those exact HMAC-signed PDF bytes.
7. Platform creates the canonical personalized `pma:<uuid>` artifact and validates completion metadata.
8. Bot asks Platform to issue a user/workspace/platform-bound derivative capability, redeems the PDF, and performs one provider document-send operation with forward protection where supported.
9. Delivery outcome is persisted in the bounded local receipt outbox before backend receipt retry. Callback replay after a successful provider send does not resend the protected document.
10. There is never an original-source fallback.

## Owner Update Server flow

Telegram private chat first calls the read-only `deployment.overview` contract. A user without canonical `deployment.manage` receives no deployment metadata and no confirmation is created. An authorized user sees only safe current/candidate/health fields, then must pass the existing two-step confirmation before a canonical update request can be created. Bale remains disabled for deployment controls. No bot endpoint accepts repository, ref, SHA, shell, path or environment input.

## Security outcomes

- exact-body HMAC service authentication with fresh nonce on retries;
- no browser-session/CSRF impersonation;
- no bot-local canonical domain database;
- no storage path, unrestricted URL or general storage credential in bot/worker state;
- callback data remains within the 64-byte platform contract;
- protected document send is one provider operation;
- receipt failure does not cause an already-sent protected document to be sent again on the same update replay;
- Bale forward-protection downgrade remains fail closed;
- transient protected-media backend failures do not convert a leased job into a false terminal failure;
- production worker runtime uses `ApiCapabilitySource` + `ApiArtifactSink`, not `PrivateSpoolArtifactSink`.

## Validation boundary

Deterministic source tests are part of central GitHub CI and cover the shared API client, Telegram/Bale application/runtime behavior and protected-media worker adapters. The exact passing count is taken from the final PR CI rather than hard-coded here.

Still required before a production-complete claim:
- one-time runtime bootstrap of service identities/action allowlists and private runtime paths;
- installation/verification of qpdf, pdfinfo, pdftoppm, Pillow and optional runtime font;
- live Telegram/Bale `getMe` + safe non-production message smoke;
- protected-media source/publish/complete/derivative live smoke;
- updater status timer and Update Server live permission/read/request smoke;
- observed restart/recovery behavior.

No server deployment or production mutation is performed by this source-integration workstream.
