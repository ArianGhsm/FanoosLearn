# FANOOS Shared Contract Registry

This registry identifies shared contracts that must not change silently during parallel development. The authoritative architecture/module ownership rules remain in `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` and `02_TARGET_ARCHITECTURE.md`.

| Contract | Version / source | Owner | Consumers | Stability | Change responsibility |
| --- | --- | --- | --- | --- | --- |
| Public/core HTTP API | `contracts/openapi/core-v1.yaml` / v1 | Platform API integration owner | Web, Telegram bot, future app, tests | Stable v1; backward-compatible additions preferred | Integration/baseline stage |
| Workspace/tenant authorization boundary | module ownership + DB schema + API context | Authorization + Directory/Tenancy | All tenant-scoped modules/clients | Frozen semantic invariant | Integration/baseline stage only |
| Module/data ownership | `docs/fanoos-migration/02_MODULE_AND_DATA_OWNERSHIP.md` | Architecture/integration | All modules/workers | Architectural invariant | Architecture/integration review |
| Secure delivery | `docs/fanoos-migration/06_SECURE_DELIVERY_CONTRACT.md` | Content + Authorization/Entitlements integration | Telegram bot, protected-media worker, platform API | Frozen for Stage 7 unless contract tests prove required change | Integration/baseline stage |
| SQL schema/migration boundary | ordered `database/migrations/*.sql` + migration ledger | Owning domain module, integrated centrally | Platform, CI, deploy/migration tooling | Forward-only; compatibility required | Integration stage for shared schema/migration index |
| Object storage boundary | Content/Storage ports + Stage 4/6 docs | Content/Storage | Platform, content/protected workers | Stable semantic boundary; adapter can vary by environment | Integration for shared contract; worker owns adapter-local code |
| Commerce/payment verification | Commerce service + API projections | Commerce | Entitlements, Web/Bot clients | Provider callbacks never equal payment proof; server verification required | Commerce owner + integration review |
| Entitlement decision | Entitlements service contract | Entitlements | Content, Exams, Commerce orchestration, clients via API | Authorization and entitlement remain separate decisions | Entitlements owner + integration review |
| Job/worker lifecycle | module ownership document / Jobs model | Jobs + owning domain module | Content/protected workers, notification adapters | Lease/idempotent-result semantics frozen | Integration/baseline stage |
| Notification authority | Notifications/outbox model | Notifications | Web inbox, Telegram/Bale adapters, domain event producers | One canonical notification authority | Notifications owner + integration review |

## Contract rules

1. A worker may consume a shared contract but must not silently redefine it.
2. Additive implementation behind an existing contract is allowed within owned paths.
3. A required shared-contract change must include the contract source change, compatibility impact, tests/fixtures and consumer review in the integration/baseline stage.
4. Breaking changes require an explicit versioning/migration decision; do not repurpose an existing v1 field/meaning.
5. Client-supplied workspace/resource/payment identifiers never become authorization/payment proof merely because a contract accepts them as input.
6. Generated/runtime state is not a contract source of truth.

## Stage 7 contract freeze checklist

Before parallel Stage 7 bot/worker development begins, the Integration Chat must verify or add machine-testable definitions for:
- messaging account link challenge/confirm/revoke;
- active workspace selection/projection;
- protected delivery issue/consume/receipt;
- payment order/deep-link/result projection used by the bot;
- notification delivery/receipt boundary;
- protected-media job input/result contract;
- service authentication/idempotency requirements.

After that checkpoint, record the exact base SHA as `PARALLEL_BASE_SHA` in the workstream manifests. Workers start from that SHA and do not invent incompatible local variants.