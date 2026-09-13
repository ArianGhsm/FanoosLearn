# Stage 7 — Platform Handoff to Telegram / Bale Bots

Status: **PLATFORM CONTRACTS FROZEN / BOT RE-GATE REQUIRED / RUNTIME VALIDATION REQUIRED**

Chat 2 owns Telegram/Bale adapter runtime and UX. Platform remains the authority for identity, membership/RBAC, payments, entitlements, academics, content, notifications, protected delivery/media and deployment authorization. Bot-local state is transport-only and non-authoritative.

## Signed internal service authentication

All `/api/internal/v1/*` requests use `contracts/openapi/internal-v1.yaml` and the existing `fanoos-service-v1` HMAC contract.

```text
X-Fanoos-Key-Id
X-Fanoos-Timestamp
X-Fanoos-Nonce
X-Fanoos-Content-SHA256
X-Fanoos-Signature
```

Canonical signing input:

```text
fanoos-service-v1
<METHOD>
<PATH>
<TIMESTAMP>
<NONCE>
<SHA256_EXACT_RAW_BODY>
```

Use a fresh nonce for every HTTP attempt. Business idempotency keys may be reused where the operation contract allows it. Telegram and Bale use separate service identities/action allowlists.

## Account linking and unlinking

Human challenge creation remains:
- `POST /api/v1/messaging/link-challenges`

Adapter confirmation:
- `POST /api/internal/v1/messaging/link-challenges/consume`
- action: `messaging.link.consume`

Bot-safe unlink is now frozen:
- `POST /api/internal/v1/messaging/links/revoke`
- action: `messaging.link.revoke`
- body: `platform`, `subject`

Unlink is subject-bound and idempotent. It does not accept `user_id` or `link_id`, does not delete the canonical user, does not revoke the other messaging platform, and removes the selected-workspace context for the revoked link.

## Workspace projection

- `POST /api/internal/v1/messaging/workspaces/list` — `messaging.workspace.read`
- `POST /api/internal/v1/messaging/workspaces/select` — `messaging.workspace.select`

Every bot read below still supplies `platform + subject + workspace_id`; backend resolves the canonical linked user and rechecks active membership. Cached selected workspace is UX state only.

## Native bot academic/content reads

### Schedule / Today / Tomorrow

- `POST /api/internal/v1/academics/schedule`
- action: `academic.schedule.read`

Input includes `from_date`, `to_date` as `YYYY-MM-DD`, optional bounded `limit` and opaque `cursor`. Dates are interpreted in the canonical `tenant_workspaces.timezone_name`. Response includes the timezone and ISO-8601 localized timestamps. Maximum date range is bounded by Platform.

For Today/Tomorrow UX, derive the date labels using the timezone returned/canonicalized by Platform; do not use the bot host timezone as authority.

### Self grades

- `POST /api/internal/v1/academics/grades`
- action: `grade.self.read`

Only published grade results belonging to the linked canonical user's active enrollment are projected. Stable result/course/gradebook/item IDs are returned with bounded pagination.

### Announcements

- `POST /api/internal/v1/announcements/list`
- action: `announcement.read`

Returns the linked user's canonical workspace announcement inbox with stable IDs and bounded pagination.
An optional canonical `course_id` may scope the read to an active course in the same workspace; the platform rechecks that relation and never trusts the bot as an authorization source.

### Accessible resources/content

- `POST /api/internal/v1/content/resources/list`
- action: `content.catalog.read`

Each candidate is rechecked by canonical resource/RBAC/entitlement authorization. Response contains safe catalog metadata plus stable `resource_id` and authorized current `resource_version_id`; no object ID, storage key or filesystem path is a delivery authority. Use the existing protected-delivery flow for actual delivery.

## Commerce / payment

Unchanged:
- create: `POST /api/internal/v1/commerce/orders` — `commerce.order.create`
- status: `POST /api/internal/v1/commerce/orders/status` — `commerce.order.read`

Server title/amount/currency/status are authoritative. Redirect/callback messages are not payment proof. Bots never grant entitlements locally.

## Notifications

Unchanged:
- project: `/notifications/project`
- claim: `/notifications/claim`
- receipt: `/notifications/receipt`

Use backend lease/idempotency semantics. Protected-delivery receipt outbox hardening in bot source remains required.

## Protected delivery

Unchanged core sequence:
1. `/deliveries/issue`
2. `/deliveries/consume` immediately before provider send
3. provider send with required channel protection
4. `/deliveries/receipt`

Cached provider file/message IDs never bypass consume/reauthorization.

## Protected-media worker — complete transport contract

### Claim

- `POST /api/internal/v1/protected-media/claim`
- action: `protected_media.claim`

Claim returns job/lease/completion identifiers, resource/version/issuance binding, forensic/watermark metadata, hard limits and an opaque short-lived `object_capability`. No raw path/storage key is returned.

### Source redemption

- `POST /api/internal/v1/protected-media/source/redeem`
- action: `protected_media.source.redeem`
- worker service type only

Signed JSON body contains `job_id`, `lease_token`, `object_capability`. Backend validates the current lease, issuance, capability object/version/classification, current authorization, object verification, MIME and byte limit, then streams `application/pdf` bytes. Exact service-request replay is rejected by the nonce ledger; a fresh request may retry the same capability only while the same lease/capability remains valid.

The worker never receives a path, bucket key, unrestricted URL or storage credential.

### Personalized derivative publication

Publication is intentionally two-step so job/lease/completion metadata is cryptographically bound rather than placed in unsigned metadata headers.

1. `POST /api/internal/v1/protected-media/artifacts/authorize-publish`
   - action: `protected_media.artifact.authorize`
   - signed JSON: `job_id`, `lease_token`, `completion_key`, expected `checksum_sha256`, `size`, `mime=application/pdf`
   - returns a short-lived upload capability bound to the exact job/checksum/size/MIME.

2. `POST /api/internal/v1/protected-media/artifacts/publish`
   - action: `protected_media.artifact.publish`
   - worker service type only
   - `Content-Type: application/pdf`
   - `X-Fanoos-Upload-Capability: <opaque capability>`
   - exact PDF bytes are also covered by normal service HMAC body digest.

Platform stores one canonical personalized artifact per job. Same-byte retry is idempotent; conflicting bytes for the same job fail closed. `artifact_ref` is server-generated (`pma:<uuid>`) and never a client storage address.

Completion remains:
- `POST /api/internal/v1/protected-media/complete`
- action: `protected_media.complete`

Completion succeeds only when checksum/size/MIME/artifact_ref match the canonically published artifact for that job.

### Derivative issue + retrieval

After worker completion:
- issue: `POST /api/internal/v1/protected-media/derivatives/issue` — `protected_media.derivative.issue`
- redeem: `POST /api/internal/v1/protected-media/derivatives/redeem` — `protected_media.derivative.redeem`

Issue is tied to linked user/workspace/platform/job and rechecks current resource authorization + exact resource version. It returns a short-lived artifact capability plus checksum/size/MIME metadata. Redeem reauthorizes again and streams only the personalized PDF.

If entitlement/membership/resource-version authorization changes, or the artifact expires/is cleaned up, retrieval fails closed. **Never fall back to the original protected source.**

## Owner control plane

### Read-only overview

Telegram-only:
- `POST /api/internal/v1/deployments/overview`
- action: `deployment.overview`

Body: `platform=telegram`, `subject`, `target_key`.

For a linked user without canonical platform-scoped `deployment.manage`, response is only:

```json
{"can_manage_deployments":false}
```

This lets Telegram hide Update Server without creating a deployment request as a permission probe.

For an authorized operator the response includes only safe fields:
- target key/node/service;
- current release SHA;
- server-resolved canonical-main candidate SHA;
- `update_available` (`true|false|null` when not yet observed);
- health state/safe check code/check time;
- last deployment state and safe SHA/failure/timestamps.

HTTP does **not** run Git/shell/preflight. `scripts/ops/refresh-update-status.php`, executed by the separately privileged updater-side timer, resolves canonical `ArianGhsm/FanoosLearn origin/main`, exact-SHA CI and health using the existing preflight, then writes `release_update_snapshots`. The API only reads that safe snapshot. Updater credentials never enter bot/PHP response state.

Bale remains without deployment controls.

### Update request/status

Telegram-only and unchanged:
- request: `/deployments/request` — `deployment.request`
- status: `/deployments/status` — `deployment.status`

Request accepts only `platform`, `subject`, `target_key`, `idempotency_key`; no command/path/remote/branch/ref/SHA input.

Observable states:
`REQUESTED → PREFLIGHT → BACKUP → TESTING → MIGRATING → ACTIVATING → RESTARTING → HEALTHCHECK → SUCCEEDED`, with terminal `FAILED` or `ROLLED_BACK`. Never display fake progress percentages/ETA.

## Central CI gate

Integration CI now also runs:

```bash
bash ops/stage7-bots/run-deterministic-tests.sh
```

under a supported Python 3 runtime. This is additive to PHP 8.2/8.4, MySQL 8.4, repository-safety and exact-commit artifact gates.

## Runtime/bootstrap delta

One-time runtime bootstrap must additionally provision:
- `FANOOS_PROTECTED_MEDIA_ROOT` outside public/release directories;
- independent `FANOOS_PROTECTED_MEDIA_CAPABILITY_KEY` outside Git;
- bounded `FANOOS_INTERNAL_BINARY_MAX_BYTES`;
- new internal service action allowlists above;
- updater-side `fanoos-update-status.service/timer` templates;
- workspace timezone values for production tenants;
- cleanup scheduling for expired personalized artifacts.

Live Telegram/Bale/provider/worker/systemd/storage behavior remains `RUNTIME_VALIDATION_REQUIRED` until observed on the supervised runtime.

### Protected-media secure-raster micro watermark (renderer `fanoos-raster-v2`)

Same rollout rule as `FANOOS_PROTECTED_MEDIA_CAPABILITY_KEY` above, plus one
that is specific to this key: provision an independent, >=32-byte
`FANOOS_PROTECTED_MEDIA_FINGERPRINT_KEY` in the worker's own runtime env
before the worker is (re)started -- `build()` fails closed with no default.
Never rotate it once in use; rotating it orphans every mark already burned
into a distributed derivative. Deploy the worker at the same time as, or
before, any bot build that starts enqueuing `renderer_algorithm_version:
fanoos-raster-v2` -- the worker accepts exactly one renderer version at a
time, so a job enqueued under a version the running worker does not accept
fails cleanly (`render_failed`) and simply needs re-enqueuing once both
sides agree on the version.

## Re-entry gate for Chat 2

After this Platform branch is merged and repository CI is green, Chat 2 should:
1. re-read exact `main` SHA and `contracts/openapi/internal-v1.yaml`;
2. update only adapter/worker consumers to these frozen paths/actions;
3. replace web/navigation fallbacks with native reads where desired;
4. replace the private-spool worker publication adapter with authorize-publish/raw-upload/complete;
5. use source/derivative binary redemption contracts without storage credentials;
6. use owner overview only for Telegram private owner UX;
7. add deterministic regression tests and merge through repository CI;
8. keep production status as runtime validation required until live smoke is complete.
