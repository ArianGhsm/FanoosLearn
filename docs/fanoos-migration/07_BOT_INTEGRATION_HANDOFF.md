# Stage 7 Bot → Platform / Integration Handoff

Status: **PLATFORM SOURCE CONTRACTS RESOLVED / BOT RE-GATE REQUIRED / RUNTIME VALIDATION REQUIRED**

Original bot handoff baseline: `e21b606d920ee16bad145ad66deee6fed4852044`.

The bot workstream correctly stopped at Platform-owned boundaries instead of inventing APIs. This document now records how those gaps are resolved by the Platform/Integration workstream. Exact request schemas, actions and response media types are authoritative in `contracts/openapi/internal-v1.yaml`; bot implementation must consume that contract rather than this prose alone.

## 1. Messaging unlink / revoke — RESOLVED

Frozen endpoint:
- `POST /api/internal/v1/messaging/links/revoke`
- service action: `messaging.link.revoke`
- input authority: signed adapter platform + exact platform subject only.

Properties:
- no `user_id` or `link_id` input;
- revokes only the addressed canonical messaging link;
- canonical user remains intact;
- unrelated Telegram/Bale link remains intact;
- selected-workspace context is removed;
- repeated/already-missing revoke is idempotent;
- audit action: `messaging.unlink`.

## 2. Bot-safe academic/content reads — RESOLVED

Frozen endpoints:
- `POST /api/internal/v1/academics/schedule` — `academic.schedule.read`
- `POST /api/internal/v1/academics/grades` — `grade.self.read`
- `POST /api/internal/v1/announcements/list` — `announcement.read`
- `POST /api/internal/v1/content/resources/list` — `content.catalog.read`

All require signed adapter identity plus linked `platform + subject`, explicit workspace, active canonical membership and the relevant existing RBAC/entitlement check.

Schedule uses canonical `tenant_workspaces.timezone_name`, local `YYYY-MM-DD` boundaries, bounded range/pagination and ISO-8601 localized timestamps. Grades are only the linked canonical user's published results. Announcements and resource catalog use stable IDs and bounded pagination. The announcements read accepts an optional `course_id` filter; the platform validates the active course relation within the selected workspace before returning rows. Resource catalog reuses `ProtectedResourceAuthorizer`; it does not return object/storage paths or keys.

## 3. Protected-media source capability redemption — RESOLVED

Frozen endpoint:
- `POST /api/internal/v1/protected-media/source/redeem`
- action: `protected_media.source.redeem`
- caller: `protected_media_worker` service identity only.

Signed JSON binds `job_id`, `lease_token`, `object_capability`. Platform validates current job lease, issuance, exact object/version/classification capability, current resource authorization, verified object state, `application/pdf` MIME and hard byte limit. Response is streamed PDF bytes with no storage address/credential.

Exact signed-request replay is rejected by the service nonce ledger. Fresh transport retry of the same source capability is permitted only while its original capability and the same current job lease remain valid. Expiry or authorization change fails closed.

## 4. Protected derivative publication/retrieval — RESOLVED

Publication is a two-step cryptographic boundary:

1. `POST /api/internal/v1/protected-media/artifacts/authorize-publish`
   - action `protected_media.artifact.authorize`
   - signed JSON binds job, lease, completion key, checksum, size and `application/pdf` MIME;
   - returns a short-lived upload capability bound to exactly that metadata.

2. `POST /api/internal/v1/protected-media/artifacts/publish`
   - action `protected_media.artifact.publish`
   - worker-only;
   - raw `application/pdf` body plus opaque `X-Fanoos-Upload-Capability`;
   - normal service HMAC binds exact PDF bytes, upload capability separately binds expected job/checksum/size/MIME.

Canonical table `protected_media_artifacts` binds one artifact to job/issuance/user/resource/version. Same-byte publication retry is idempotent; conflicting bytes fail closed. Platform-generated `artifact_ref` is opaque and not a storage address.

Completion through `/protected-media/complete` now validates that result checksum/size/MIME/artifact_ref match the canonical published artifact.

Bot retrieval:
- `/protected-media/derivatives/issue` — `protected_media.derivative.issue`
- `/protected-media/derivatives/redeem` — `protected_media.derivative.redeem`

Issue/redeem both enforce canonical user/workspace and current resource/version authorization. Artifact capability is short-lived and user/platform/job bound. Expired/deleted/revoked artifacts fail closed. The original source must never be used as fallback.

## 5. Read-only Owner Control Plane — RESOLVED

Frozen Telegram-only endpoint:
- `POST /api/internal/v1/deployments/overview`
- action: `deployment.overview`

If the linked user lacks platform-scoped `deployment.manage`, the response exposes only `can_manage_deployments=false`. It does not probe by creating an update request.

Authorized overview returns safe current/candidate/update-availability/health/last-deployment fields. Candidate/current health snapshot is produced only by updater-side `scripts/ops/refresh-update-status.php`, which uses existing `CanonicalMainUpdateExecutor` canonical-main/exact-SHA/CI/health preflight and writes `release_update_snapshots`.

The HTTP owner read path never executes Git/shell/process commands and never has updater credentials. Bale deployment controls remain disabled.

## 6. Integration-only Python CI — RESOLVED

Central `.github/workflows/ci.yml` now contains a dedicated `python-bot-worker` job that verifies a supported Python 3 runtime and executes:

```bash
bash ops/stage7-bots/run-deterministic-tests.sh
```

Existing PHP 8.2/8.4, MySQL 8.4, repository-safety and exact-commit artifact gates remain intact.

## New Platform state/runtime requirements

Migration `0010_bot_handoff_contracts.sql` adds:
- canonical workspace timezone field;
- `protected_media_artifacts` for personalized derivative ownership/lifetime;
- `release_update_snapshots` for safe read-only owner status.

One-time runtime/bootstrap must provision:
- `FANOOS_PROTECTED_MEDIA_ROOT` outside public/release roots;
- `FANOOS_PROTECTED_MEDIA_CAPABILITY_KEY` outside Git;
- bounded `FANOOS_INTERNAL_BINARY_MAX_BYTES`;
- service-action allowlists for the new internal operations;
- updater-side status refresh service/timer;
- real workspace timezone values;
- expired derivative cleanup scheduling.

No server changes are performed by this source task.

## Bot re-entry gate

Chat 2 may resume only after this Platform branch is merged to `main` with green repository CI. At re-entry it must:
1. verify the new exact `main` SHA and no conflicting open PR;
2. read `contracts/openapi/internal-v1.yaml` and `07_PLATFORM_HANDOFF_TO_BOTS.md`;
3. update adapter/worker consumers only—do not invent alternate domain APIs;
4. wire native schedule/grades/announcements/resource views;
5. wire subject-bound unlink;
6. replace protected-media private-spool/source placeholders with frozen redeem/authorize-publish/publish/complete/derivative contracts;
7. wire Telegram owner overview without enabling Bale deployment controls;
8. run deterministic bot/worker tests plus repository CI;
9. retain **RUNTIME_VALIDATION_REQUIRED** until one-time runtime bootstrap and live Telegram/Bale/worker smoke are actually observed.
