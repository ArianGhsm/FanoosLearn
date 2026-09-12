# FANOOS Shared Contract Registry

This registry identifies shared contracts that must not change silently during parallel development. The authoritative architecture/module ownership rules remain in `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` and `02_TARGET_ARCHITECTURE.md`.

| Contract | Version / source | Owner | Consumers | Stability | Change responsibility |
| --- | --- | --- | --- | --- | --- |
| Public/core HTTP API | `contracts/openapi/core-v1.yaml` / v1 | Platform API integration owner | Web/human clients, tests | Stable v1; backward-compatible additions preferred | Integration/baseline stage |
| Internal service HTTP API | `contracts/openapi/internal-v1.yaml` / v1.1 | Platform integration owner | Telegram, Bale, notification/protected-media workers, updater-facing adapters | Stage 7 frozen; compatible additions require integration review | Integration/baseline stage |
| Service request authentication | `fanoos-service-v1` canonical string + SQL service identity/nonce ledger | Security/integration | All `/api/internal/v1/*` callers | Frozen signing semantics; key rotation is runtime metadata | Integration/security owner |
| Messaging account linking | `07_PLATFORM_BACKEND_CONTRACTS.md` + messaging SQL/service | Identity/Messaging integration | Telegram, Bale, human linking UI | One-time challenge + canonical-user binding invariant | Integration/baseline stage |
| Messaging subject unlink | `POST /api/internal/v1/messaging/links/revoke` | Identity/Messaging integration | Telegram, Bale | Signed adapter platform + subject only; idempotent; never accepts user/link authority input | Integration/security owner |
| Workspace/tenant authorization boundary | module ownership + DB schema + API context | Authorization + Directory/Tenancy | All tenant-scoped modules/clients | Frozen semantic invariant | Integration/baseline stage only |
| Messaging workspace projection/context | internal v1 workspace endpoints + canonical membership recheck | Messaging + Tenancy | Telegram, Bale | Adapter context is non-authoritative; membership recheck frozen | Integration/baseline stage |
| Bot academic/content reads | internal v1 schedule/grades/announcements/resource catalog endpoints (announcements optionally course-scoped) | Academic/Content integration | Telegram, Bale | Canonical linked user + active workspace membership + scoped authorization on every request; bounded pagination; no storage paths | Integration/baseline stage |
| Module/data ownership | `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` | Architecture/integration | All modules/workers | Architectural invariant | Architecture/integration review |
| Secure delivery | `docs/fanoos-migration/06_SECURE_DELIVERY_CONTRACT.md` + Stage 7 internal delivery/receipt contract | Content + Authorization/Entitlements integration | Telegram, Bale, protected-media worker, platform API | Reauthorize-on-consume invariant frozen | Integration/baseline stage |
| Protected-media job | internal v1 protected-media endpoints + `ProtectedMediaJobService` | Content/Storage integration | Protected-media worker | Exact workspace/resource/version + scoped capability + bounded result frozen | Integration/baseline stage |
| Protected-media source redemption | `/protected-media/source/redeem` + `ProtectedMediaTransferService` | Content/Storage integration | Protected-media worker | Opaque source capability + active job lease + current authorization; PDF bytes only; no path/key/URL | Integration/security owner |
| Protected-media artifact publication | authorize-publish → checksum-bound upload capability → raw signed PDF publish → complete | Content/Storage integration | Protected-media worker | Worker gets no general storage credential; one canonical artifact per job; retry-idempotent | Integration/security owner |
| Personalized derivative delivery | `/protected-media/derivatives/issue|redeem` + `protected_media_artifacts` | Content/Storage/Authorization | Telegram, Bale | User/workspace/platform/job-bound short TTL capability; fresh reauthorization; never fall back to source | Integration/security owner |
| SQL schema/migration boundary | ordered `database/migrations/*.sql` + migration ledger + `MigrationPreflight` | Owning domain module, integrated centrally | Platform, CI, updater/migration tooling | Forward-only; unattended update requires explicit expand compatibility | Integration stage for shared schema/migration index |
| Object storage boundary | Content/Storage ports + Stage 4/6 docs | Content/Storage | Platform, content/protected workers | Stable semantic boundary; adapter can vary by environment | Integration for shared contract; worker owns adapter-local code |
| Commerce/payment verification | Commerce service + internal bot projections | Commerce | Entitlements, Web/Bot clients | Provider callbacks never equal payment proof; server verification required | Commerce owner + integration review |
| Entitlement decision | Entitlements service contract | Entitlements | Content, Exams, Commerce orchestration, clients via API | Authorization and entitlement remain separate decisions | Entitlements owner + integration review |
| Job/worker lifecycle | module ownership document / Jobs model | Jobs + owning domain module | Content/protected workers, notification adapters | Lease/idempotent-result semantics frozen | Integration/baseline stage |
| Notification authority | Notifications/outbox + channel lease/receipt model | Notifications | Web inbox, Telegram/Bale adapters, domain event producers | One canonical notification authority; duplicate fan-out prohibited | Notifications owner + integration review |
| Deployment authorization | `deployment.manage` at platform scope | Authorization/Operations integration | Owner/operator control surfaces | High-risk permission; not implied by representative/workspace admin | Integration/security owner |
| Owner deployment overview | `/deployments/overview` + `release_update_snapshots` | Operations/SRE integration | Telegram owner control | Permission-aware read-only projection; updater writes safe canonical-main snapshot; web/API runs no shell and sees no updater credential | Integration/SRE owner |
| Deployment control plane | `07_UPDATE_CONTROL_PLANE.md` + deployment SQL/services | Operations/SRE integration | Owner bot, updater runner | Durable/idempotent/serialized; no arbitrary shell/ref/SHA invariant frozen | Integration/SRE owner |
| Class (workspace) provisioning | `platform-scoped` `workspace.provision` + `ClassProvisioningService` (internal v1 `POST /classes`) | Directory/Tenancy integration | Owner bot (future), web (future) | Owner-only; find-or-create the full directory chain and its workspace in one transaction; identical identity is idempotent and never duplicates a directory row; not inherited from any workspace-scoped role | Integration/baseline stage |
| Onboarding directory read | `DirectoryReadService` (internal v1 `POST /onboarding/directory/{provinces,institutions,faculties,programs,cohorts}`) | Directory/Tenancy integration | Telegram, Bale (join wizard), web (future) | Open catalog read, no linked account required; paginated 10/page by default. `provinces/institutions/faculties/programs` never return cohort or workspace identity, so they cannot be used to enumerate another workspace's data; `cohorts` is the one deliberate exception, returning only cohorts under an already-chosen program that have a live class, for a caller resolving their own join target | Integration/baseline stage |
| Onboarding phone verification | `OnboardingPhoneVerificationService` (internal v1 `POST /onboarding/otp/{request,resend,verify,status}`) | Identity/Messaging integration | Telegram, Bale (join wizard) | Pre-workspace: no canonical account or workspace membership exists yet, identity is scoped to (platform, subject) only; phone stored as digest+ciphertext only, raw number never in a response; 180s code TTL, 60s resend cooldown, 5 max attempts, 6 sends/hour, single-use | Integration/security owner |
| Onboarding join / tiered membership | `ClassMembershipService` (internal v1 `POST /onboarding/join`, `POST /onboarding/upgrade-request`, `POST /onboarding/class-creation-requests`) | Identity/Messaging + Directory/Tenancy integration | Telegram, Bale (join wizard) | Join requires a phone already verified for the caller's (platform, subject); finds-or-creates the canonical account by that verified phone (`iam_user_identifiers`) and links the subject via `MessagingLinkService::establishLink` regardless of outcome, then resolves (program, entry_year) to a live workspace and, only on a match, creates a `tenant_workspace_memberships` row at the limited `workspace-limited-member` role (`database/seeds/0008_workspace_limited_member_role.sql`), never full `student`; idempotent per (subject, program, entry_year). No match returns `class_not_found` (not an error) rather than throwing. Upgrade-request is a durable, idempotent request per (user, workspace) toward the full role; approval is the separate representative appointment/approval contract below | Integration/security owner |
| Representative appointment and approval | `WorkspacePlatformService::assignRepresentative` (core v1 `POST /workspaces/{id}/admin/representatives`, internal v1 `POST /representatives/{workspaces,candidates,appoint}`) + `ClassMembershipService::{pendingUpgradeRequests,approveUpgradeRequest,declineUpgradeRequest}` (internal v1 `POST /representatives/requests/{list,approve,decline}`) | Authorization + Identity/Messaging integration | Web, Telegram, Bale | Appointment is owner-only (`membership.manage`, workspace-scoped; the platform scope is always an ancestor, so a platform owner is authorized the same way); the target must already be an active member, idempotent, no second identity mechanism. Approval/decline need the narrow `membership.approve` permission (`database/seeds/0009_membership_approve_permission.sql`), deliberately not `membership.manage` -- a representative can admit classmates without gaining wholesale roster control. Approve closes the request and promotes limited→student in one transaction; a request id is always looked up scoped to its own workspace_id, so a representative of one class can never see, approve or decline another class's request even by id | Integration/security owner |
| Canonical release selection | updater resolves `ArianGhsm/FanoosLearn` `origin/main` → exact SHA + exact-SHA CI | Operations/SRE | Updater | No user/bot ref/remote selection | Integration/SRE owner |
| Bot/worker deterministic CI | `.github/workflows/ci.yml` → `ops/stage7-bots/run-deterministic-tests.sh` | Integration owner | Telegram/Bale/worker source | Required alongside PHP/MySQL/safety/release gates | Integration owner |

## Contract rules

1. A worker or adapter may consume a shared contract but must not silently redefine it.
2. Additive implementation behind an existing contract is allowed within owned paths.
3. A required shared-contract change must include the contract source change, compatibility impact, tests/fixtures and consumer review in the integration/baseline stage.
4. Breaking changes require an explicit versioning/migration decision; do not repurpose an existing v1 field/meaning.
5. Client-supplied workspace/resource/payment identifiers never become authorization/payment proof merely because a contract accepts them as input.
6. Generated/runtime state is not a contract source of truth.
7. Internal signed service identity is not a human session and does not weaken browser CSRF; internal action scope is independently enforced.
8. Telegram/Bale cached message/file IDs are transport optimizations only and never entitlement or protected-delivery proof.
9. A deployment request cannot carry command/path/remote/branch/ref/SHA input. Candidate selection occurs inside the privileged updater.
10. Git is code source of truth; production database/object storage remain data source of truth and are never overwritten from releases.
11. A worker source or derivative capability is a bounded transport capability, not domain authorization. Current membership/RBAC/entitlement/resource-version state is rechecked at the relevant redemption boundary.
12. Personalized derivative unavailability is fail-closed. Adapters must not substitute the original protected source as a fallback.
13. Owner overview is read-only. Update availability is computed only by the updater-side canonical-main verifier and persisted as a safe snapshot; HTTP handlers must not execute Git or privileged process commands.

## Stage 7 contract freeze checkpoint

The Integration Chat has machine-testable definitions for:
- messaging account link challenge/confirm/revoke and subject-bound signed unlink;
- active workspace selection/projection with canonical membership recheck;
- service authentication with key ID, timestamp, nonce, exact-body digest, HMAC and durable replay protection;
- bot-safe timezone-aware schedule, self-grade, announcement and accessible-resource reads;
- payment order/deep-link/status/entitlement projection for bots;
- outbox-driven notification projection, lease and idempotent receipt;
- protected delivery issue/consume/receipt with reauthorization;
- protected-media source redemption, checksum-bound publication and personalized derivative retrieval;
- platform-scoped `deployment.manage` and permission-aware read-only owner overview;
- durable deployment request/status lifecycle and per-target serialization;
- canonical-main exact-SHA/CI/backup/migration/health/rollback policy;
- deterministic Python bot/worker tests in central CI.

Canonical Stage 7 documents include:
- `docs/fanoos-migration/07_PLATFORM_BACKEND_CONTRACTS.md`
- `docs/fanoos-migration/07_UPDATE_CONTROL_PLANE.md`
- `docs/fanoos-migration/07_CODEX_ONE_TIME_BOOTSTRAP.md`
- `docs/fanoos-migration/07_PLATFORM_HANDOFF_TO_BOTS.md`
- `docs/fanoos-migration/07_BOT_INTEGRATION_HANDOFF.md`

A bot/worker implementation must re-gate from the accepted main SHA containing these contracts. It must not invent incompatible local variants or bypass the canonical backend with adapter-local domain state.
