# FANOOS Shared Contract Registry

This registry identifies shared contracts that must not change silently during parallel development. The authoritative architecture/module ownership rules remain in `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` and `02_TARGET_ARCHITECTURE.md`.

| Contract | Version / source | Owner | Consumers | Stability | Change responsibility |
| --- | --- | --- | --- | --- | --- |
| Public/core HTTP API | `contracts/openapi/core-v1.yaml` / v1 | Platform API integration owner | Web/human clients, tests | Stable v1; backward-compatible additions preferred | Integration/baseline stage |
| Internal service HTTP API | `contracts/openapi/internal-v1.yaml` / v1 | Platform integration owner | Telegram, Bale, notification/protected-media workers, updater-facing adapters | Stage 7 frozen; compatible additions require integration review | Integration/baseline stage |
| Service request authentication | `fanoos-service-v1` canonical string + SQL service identity/nonce ledger | Security/integration | All `/api/internal/v1/*` callers | Frozen signing semantics; key rotation is runtime metadata | Integration/security owner |
| Messaging account linking | `07_PLATFORM_BACKEND_CONTRACTS.md` + messaging SQL/service | Identity/Messaging integration | Telegram, Bale, human linking UI | One-time challenge + canonical-user binding invariant | Integration/baseline stage |
| Workspace/tenant authorization boundary | module ownership + DB schema + API context | Authorization + Directory/Tenancy | All tenant-scoped modules/clients | Frozen semantic invariant | Integration/baseline stage only |
| Messaging workspace projection/context | internal v1 workspace endpoints + canonical membership recheck | Messaging + Tenancy | Telegram, Bale | Adapter context is non-authoritative; membership recheck frozen | Integration/baseline stage |
| Module/data ownership | `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` | Architecture/integration | All modules/workers | Architectural invariant | Architecture/integration review |
| Secure delivery | `docs/fanoos-migration/06_SECURE_DELIVERY_CONTRACT.md` + Stage 7 internal delivery/receipt contract | Content + Authorization/Entitlements integration | Telegram, Bale, protected-media worker, platform API | Reauthorize-on-consume invariant frozen | Integration/baseline stage |
| Protected-media job | internal v1 protected-media endpoints + `ProtectedMediaJobService` | Content/Storage integration | Protected-media worker | Exact workspace/resource/version + scoped capability + bounded result frozen | Integration/baseline stage |
| SQL schema/migration boundary | ordered `database/migrations/*.sql` + migration ledger + `MigrationPreflight` | Owning domain module, integrated centrally | Platform, CI, updater/migration tooling | Forward-only; unattended update requires explicit expand compatibility | Integration stage for shared schema/migration index |
| Object storage boundary | Content/Storage ports + Stage 4/6 docs | Content/Storage | Platform, content/protected workers | Stable semantic boundary; adapter can vary by environment | Integration for shared contract; worker owns adapter-local code |
| Commerce/payment verification | Commerce service + internal bot projections | Commerce | Entitlements, Web/Bot clients | Provider callbacks never equal payment proof; server verification required | Commerce owner + integration review |
| Entitlement decision | Entitlements service contract | Entitlements | Content, Exams, Commerce orchestration, clients via API | Authorization and entitlement remain separate decisions | Entitlements owner + integration review |
| Job/worker lifecycle | module ownership document / Jobs model | Jobs + owning domain module | Content/protected workers, notification adapters | Lease/idempotent-result semantics frozen | Integration/baseline stage |
| Notification authority | Notifications/outbox + channel lease/receipt model | Notifications | Web inbox, Telegram/Bale adapters, domain event producers | One canonical notification authority; duplicate fan-out prohibited | Notifications owner + integration review |
| Deployment authorization | `deployment.manage` at platform scope | Authorization/Operations integration | Owner/operator control surfaces | High-risk permission; not implied by representative/workspace admin | Integration/security owner |
| Deployment control plane | `07_UPDATE_CONTROL_PLANE.md` + deployment SQL/services | Operations/SRE integration | Owner bot, updater runner | Durable/idempotent/serialized; no arbitrary shell/ref/SHA invariant frozen | Integration/SRE owner |
| Canonical release selection | updater resolves `ArianGhsm/FanoosLearn` `origin/main` → exact SHA + exact-SHA CI | Operations/SRE | Updater | No user/bot ref/remote selection | Integration/SRE owner |

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

## Stage 7 contract freeze checkpoint

The Integration Chat has now added machine-testable definitions for:
- messaging account link challenge/confirm/revoke;
- active workspace selection/projection with canonical membership recheck;
- service authentication with key ID, timestamp, nonce, exact-body digest, HMAC and durable replay protection;
- payment order/deep-link/status/entitlement projection for bots;
- outbox-driven notification projection, lease and idempotent receipt;
- protected delivery issue/consume/receipt with reauthorization;
- protected-media job input/result contract with scoped object capability;
- platform-scoped `deployment.manage`;
- durable deployment request/status lifecycle and per-target serialization;
- canonical-main exact-SHA/CI/backup/migration/health/rollback policy.

The canonical Stage 7 handoff documents are:
- `docs/fanoos-migration/07_PLATFORM_BACKEND_CONTRACTS.md`
- `docs/fanoos-migration/07_UPDATE_CONTROL_PLANE.md`
- `docs/fanoos-migration/07_CODEX_ONE_TIME_BOOTSTRAP.md`
- `docs/fanoos-migration/07_PLATFORM_HANDOFF_TO_BOTS.md`

`PARALLEL_BASE_SHA` for Bot/worker implementation must be recorded only after this integration branch is accepted/merged and CI is green. Workers must start from that accepted SHA and must not invent incompatible local variants.
