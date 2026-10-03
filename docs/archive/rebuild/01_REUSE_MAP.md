# FANOOS Stage 1 reuse map

This map records behavior-level reuse decisions for the product reset. It does not authorize copying a legacy repository, database, credential, runtime artifact or private content. `KEEP` means the current FANOOS baseline is sound; `ADAPT` means preserve the behavior behind a FANOOS contract; `REPLACE` means the behavior needs a new tenant-safe authority; `RETIRE` means do not carry the process/product artifact forward except for migration evidence.

## Current FANOOS baseline

| Area/evidence | Decision | Why / next boundary |
| --- | --- | --- |
| `apps/platform` module ownership, API boundary and PHP runtime | KEEP | It is the canonical backend direction. Continue enforcing owner-module writes, explicit workspace context and contract tests. |
| `apps/platform/public/assets/ui-v3` web shell, learning, courses, progress and operations modules | KEEP as implementation baseline; ADAPT as product evolves | Preserve current feature behavior and Schoolhouse-inspired visual work. Generalize data/configuration; no Dentistry labels or tenant assumptions. This stage makes no UI redesign. |
| `apps/telegram-bot` and `apps/bale-bot` transport/retry/idempotency seams | KEEP/ADAPT | Keep them as channel clients. Move every durable domain decision to the shared platform API. |
| `apps/workers` job/capability boundary | KEEP/ADAPT | Keep least-privilege jobs and checksumed results; workers must not write canonical tables directly. |
| `contracts/`, database migrations, ownership docs and existing deterministic checks | KEEP | They are the release/compatibility guardrails. Contract changes remain explicit and versioned. |
| `.gitignore` and `.env.example` | KEEP | The ignore rules already exclude env/runtime/storage/backups and the example contains placeholders only. No normalization was necessary in this stage. |

## Legacy/reference behavior

| Capability/source | Decision | Preserve | Replace/adapt boundary |
| --- | --- | --- | --- |
| Dentistry auth/session/OTP/CSRF behavior | ADAPT | Hash migration, rotation, revocation, cooldowns and replay resistance | FANOOS identity IDs, transactional persistence, provider adapters and scoped memberships |
| Notes/resource catalog and direct chunked upload | ADAPT | Catalog interactions, resumability, checksums, size/type validation and audit | Tenant-aware resource/version/publication model and private object adapter |
| Exam navigation, flags, timer, reports and server scoring | ADAPT | User-facing study behavior and fail-closed access checks | Revisioned attempts, canonical assessment versions and transactional writes |
| Grades, forms, notifications and search outcomes | ADAPT | Useful workflows, audience/read state and own-data views | Workspace-bound tables, explicit audiences, outbox and rebuildable ACL-filtered index |
| Payment state machine and Zibal/Zarinpal adapters | ADAPT | Server totals, immutable order snapshot, verification, idempotency and reconciliation | Commerce/Entitlements authority, secret references and outbox transaction |
| Website ↔ Telegram/Bale account linking and signed calls | ADAPT | Short-lived nonce, signature/timestamp/replay protections and link UX | Versioned FANOOS service identities, active workspace and canonical user projection |
| Protected media raster/fingerprint/delivery queue | ADAPT (algorithm initially KEEP) | Bounded processing, visible/opaque marks, issuance ledger, reauthorization and receipts | FANOOS content IDs, worker capabilities and generic channel adapters |
| VoiceMatn provider/artifact/idempotency patterns | ADAPT | Provider ports, prepaid reservation/refund, encrypted artifacts and deterministic exports | Separate service contract; never merge Voice wallet/user/database into FANOOS |
| PWA/offline and deploy/backup safeguards | ADAPT | Cache partitioning, preflight, immutable release/rollback, snapshot/health gates | FANOOS host/storage/secret topology chosen in the infrastructure stage |

## Replace or retire

| Artifact/behavior | Decision | Evidence and reason |
| --- | --- | --- |
| Source-coded Dentistry/TUMS cohorts, routes, products, course constants and role strings | REPLACE | They cannot express arbitrary institutions/programs/workspaces without code branches. Preserve migration aliases and outcomes, not the constants. |
| Monolithic JSON stores that mix tenants/users/payments/content | REPLACE | They lack transactional isolation and become contention/authority risks. Use module-owned relational records plus object metadata. |
| Client/route-driven authorization, bot cache entitlement and callback-as-payment-proof | REPLACE | Only server-resolved actor/workspace, verified payment and Entitlements may grant access. |
| Embedded/generated executable question banks and raw protected web readers | RETIRE | Keep licensed source mappings and parity fixtures; publish normalized versioned content through Content and protected delivery. |
| Dentistry-specific DIS, EndoSim, token-card and product catalogs | RETIRE | Migrate only active records/requirements into generic Forms/Commerce when an owner approves; do not seed legacy product data. |
| Duplicate bot/worker domain databases and direct SQL from channel processes | REPLACE | Channel/runtime state may remain bounded and disposable; canonical state is platform-owned. |
| Old GitHub-first parallel-worker manifests, mandatory `PARALLEL_BASE_SHA` ceremony and integration-chat process | RETIRE as daily process | The local-first workflow is now canonical for ordinary tasks. `docs/workflow/*` remains historical reference for explicitly coordinated parallel work. |

## Provenance and inspection limits

The map is grounded in the current FANOOS tree and the checked-in migration/audit reports. Local reference folders such as `IntegratedDent1402Tums`, `VoiceMatnAIBot` and Dentistry variants exist beside this checkout, but they remain read-only and were not made dependencies or copied into FANOOS. A later extraction must name exact files, owner permission, tests, license/provenance and the target contract before any literal code move.
