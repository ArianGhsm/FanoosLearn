# FANOOS hard-coding and technical-debt register

## Severity and handling

- `P0`: can expose secrets/data, grant incorrect access, accept an invalid payment, or make migration/cutover unsafe. Resolve before the affected capability is enabled.
- `P1`: blocks credible multi-tenancy, transactional correctness, recovery, or protected delivery. Resolve during Prompts 2–7 as assigned.
- `P2`: maintainability, observability, performance, or product coupling that can be migrated incrementally.
- `P3`: legacy cleanup after parity/reference checks.

Values containing credentials, personal data, student rosters, phone numbers, payment identifiers, or chat identifiers are intentionally omitted.

## Hard-coded domain assumptions

| Severity | Assumption | Evidence | Why it is unsafe for FANOOS | Disposition |
| --- | --- | --- | --- | --- |
| P1 | Fixed legacy cohort keys such as Dentistry entry years, prosthesis, and site-user cohorts | `DENT/public_html/api/auth_store.php`; cohort-specific notes/grades/chat paths | Cannot add city/university/faculty/program/workspace without code/file forks | Prompt 3 data-driven hierarchy and memberships; retain old keys as migration aliases |
| P1 | A fixed owner student identifier and source-coded owner/global-role rules | `auth_store.php` | Couples platform administration to one legacy person/identifier | Seed bootstrap owner securely, then assign scoped/global capabilities in data |
| P1 | Student number treated as primary/global identity | Auth, grades, exam participant, notes and chat records | Student numbers may collide across institutions and can change | Canonical user ID plus institution-scoped identifiers/verified aliases |
| P1 | Large rosters, names, student numbers and DIS mappings embedded in source | `auth_store.php` and import helpers | PII in code/Git and code deployment required for membership changes | Extract through controlled migration, purge source constants after reconciliation |
| P1 | Fixed role names encode product and cohort behavior | `dent_default_role_permissions` and guards across APIs | Roles do not express workspace/resource scope and invite branching | Capability-based RBAC with scoped assignments and policy tests |
| P1 | Cohort route forks and storage filenames | `public_html/notes/**`, `prosthesis-1402/**`, notes/grades/chat stores | Tenant identity is encoded in URL/tree/file names; parity fixes must be repeated | Generic routes and IDs; compatibility redirects only |
| P2 | Service availability and highlights special-case primary/legacy cohorts | Cohort catalog and exam highlight code | Product navigation changes require source edits | Workspace feature configuration and content publication state |
| P1 | Course, term, professor, book, group and exam catalog constants | `dentistry_curriculum.php`, `academic_term7.php`, `exams_modules.php`, generated exam modules | Only represents one discipline/institution/calendar | Prompt 3 academic entities and Prompt 6 content provenance/import |
| P2 | Legacy price transition and product/offer assumptions in exam/payment code | Exam pricing helpers, payment catalog/defaults, bot offers | Price/currency/effective dates are product policy in code | Versioned tenant product/price records with immutable order snapshots |
| P1 | Anonymous notes reads are implicit endpoint behavior | `notes_api.php` terms/term/proxy paths | One tenant’s public policy could become the platform default | Explicit resource/workspace visibility; server-side tenant resolution |
| P2 | Persian/Iran phone, dates, currency and labels are implicit | Auth/SMS, payment UI, Persian date helpers, Navid | Valid current market assumptions but not platform-wide invariants | Locale, calendar, phone region and currency configuration per deployment/workspace |

## Infrastructure and integration hard-coding

| Severity | Assumption | Evidence | Risk | Disposition |
| --- | --- | --- | --- | --- |
| P0 | Payment merchant/API credentials can be stored with payment domain data and returned in owner payloads | `payments_store.php` credential fields; `payments_api.php` owner save/payload | Secret disclosure through backups/admin response/logging and weak rotation boundary | Migrate to write-only secret references; never serialize secret values to client or domain snapshot |
| P1 | Website deploy targets shared-host `/public_html` via local FTP/SFTP configuration | `DEPLOY.md`, `deploy_public_html.ps1/.sh`, config examples | New FANOOS host may use a different release/runtime model | Prompt 4 inspects new host and retains gates, not target-specific transport |
| P1 | Current production paths and service names are fixed | BOT/VOICE systemd units, deploy/restore scripts; `/opt/...`, `/var/lib/...`, loopback ports | Cannot safely reuse across environments/hosts without collision | Environment inventory plus templated units/config; one explicit state root per service |
| P1 | Telegram egress uses a host-local Xray port; Voice uses a separate local egress | BOT/VOICE operational state and units | Proxy availability/topology is host-specific | Channel transport setting with startup/health checks; direct path where allowed |
| P1 | Private source and destination chat/user identifiers exist in runtime config | BOT config/source admin/notifier contracts | Accidental cross-tenant delivery or secret leakage if copied | New FANOOS token/channel IDs only in secret/config store; workspace-scoped bindings |
| P2 | Website hostname, download host and Navid URLs are embedded/defaulted in code/config | notes host helpers, `.env.example`, Navid service | Environment and institution coupling | Validated URL configuration plus per-institution connector records |
| P1 | The Voice payment return bridge permits only a fixed Dentistry production URL | `DENT/public_html/api/bot_voice_payment_bridge.php` | Cannot point safely to a FANOOS environment and couples two independent products to a legacy host | Versioned allow-listed callback/service registration per environment; preserve signed return semantics |
| P2 | Windows-specific local backup roots and PowerShell orchestration | BOT/VOICE backup/deploy scripts | Operator/laptop dependency and reduced portability | Preserve control flow; implement portable server/off-site copy and platform-neutral verification |
| P1 | Laptop backup encryption uses current-user Windows DPAPI | BOT/VOICE backup scripts | Backups may be unrecoverable without that Windows profile/machine context | Managed recovery key/portable encryption plus tested off-host restore; DPAPI may remain an extra local layer |
| P2 | Runtime/tool executable names and limits are fixed (`python`, ffmpeg, qpdf, PDF size/platform bounds) | `.env.example`, deploy scripts, protected/Voice pipelines | Failures when packaged/runtime versions differ | Dependency lock/container or verified host packages; configurable policy limits |
| P2 | Notification service/channel labels and owner-only routes are legacy-specific | deploy notifier, website deploy notice API | Event routing cannot generalize to tenant operators | Versioned event schema with service/environment/audience fields |

## Data consistency and scalability debt

| Severity | Debt | Evidence/impact | Required treatment |
| --- | --- | --- | --- |
| P1 | JSON files are multi-domain authorities | Auth, notes, exams, forms, payments, notifications, chat and integration stores use JSON with flock/atomic writes | Preserve migration readers and normalization, but move canonical multi-tenant writes to transactions |
| P1 | Chat is a large monolithic PHP endpoint and file document | `chat/chat_api.php` contains authentication compatibility, membership, media, presence, polls, reactions, receipts and message actions | Use behavior fixtures; replace persistence/realtime implementation with isolated tenant-aware service |
| P1 | Exam active work is not a fully server-owned revisioned aggregate | Browser/client state plus reports/flags/study state in `exams/store.json` | Introduce attempt ID, revision, optimistic/idempotent writes and resume contract before bot sharing |
| P1 | Entitlement knowledge is duplicated across website exam/payment/forms and bot offers/subscriptions | Site payment store, bot SQLite payment/term tables, signed actions | Declare website/backend canonical; bot stores only cache, receipt and delivery state |
| P1 | Question sources, generated PHP and caches are mixed | `api/data/**`, `exams_*_data.php`, `exams_bank.php`, cache paths | Create provenance manifest; migrate authored/licensed source, regenerate derivatives |
| P1 | Binary uploads and metadata cross web host/download host through multiple transports | Notes/content tools/direct gateway/relay/fallback | One object-storage contract with multipart lifecycle, checksums, quotas and tenant prefixes |
| P2 | Payment store contains catalog, gateway config, orders, notifications and uploads | Versioned `payments/store.json` | Split transactional tables/secret references/outbox while preserving immutable snapshots |
| P2 | Form definition, submissions, receipt metadata and guest identity share file-centric storage | `forms/store.php` and upload roots | Versioned form definitions, submission records and private objects with retention policies |
| P2 | Search assembles results from legacy-specific store shapes | `search_store.php` | Build a rebuildable tenant-scoped read model; authoritative ACL checked on result access |
| P2 | Analytics and audit retention/privacy are undefined | Analytics store and per-domain audit arrays/logs | Classify events, minimize PII, define retention, immutable security audit and operator access |
| P2 | Bot SQLite includes both authoritative operational state and remote projections | `BOT/dent_bot/state.py` | Label tables by ownership; make projections rebuildable and version the sync contract |

## Security and privacy debt

| Severity | Finding | Evidence | Required control |
| --- | --- | --- | --- |
| P0 | Gateway credentials are readable domain fields/admin output | Payment store/API paths above | External secret boundary, write-only rotation, redaction tests and secret scan |
| P1 | No explicit password-login throttle was found adjacent to the login action | `auth_api.php` calls credential verification directly; OTP paths do have cooldown/attempt state | Per-identifier and per-source adaptive rate limiting, generic errors, monitoring; confirm at architecture implementation time |
| P1 | Personally identifiable roster data is source-coded | `auth_store.php` | Controlled import, repository-history review, least-privilege storage and retention policy |
| P1 | Public notes/resource and token-link behavior lack a unified visibility policy | Notes and content-tools endpoints | Explicit policy values, expiration/revocation, tenant-bound signed tokens and authorization tests |
| P1 | Raw HTML hosting is inherently high risk | HTML uploader and public sandbox viewer | Separate origin, strict CSP/sandbox/download headers, content limits and abuse lifecycle—or omit |
| P1 | Guest form identity is derived from request/browser context when no key exists | Forms guest-key flow | Random server-issued opaque guest token, expiry, rate limit and privacy notice |
| P1 | Protected content still leaves residual web reader code | `private_notes_*` versus bot-only operational contract | Prove no live dependency, migrate metadata, remove endpoints/routes and scan deployment |
| P1 | Platform `protect_content` and watermarking reduce leakage but are not absolute DRM | Telegram send and raster fingerprint code | Document threat model, retain forensic/attack tests, avoid claims of prevention |
| P2 | HMAC service integration needs explicit key rotation/versioning | Website bot API and `site_api.py` | Key IDs, overlap rotation, nonce retention, clock-skew monitoring, least-action authorization |
| P2 | Uploaded receipts, chat media, forms and analytics need explicit retention/deletion policy | Domain stores/upload trees | Data classification, encryption, access logging, retention jobs and subject/admin deletion procedure |

## Reliability, operations, and observability debt

| Severity | Finding | Evidence | Required treatment |
| --- | --- | --- | --- |
| P1 | Local Dentistry working tree is not a trustworthy migration source | 159 local-only/224 remote-only commits; 871 files differ from `origin/main`; hundreds of dirty/untracked entries | Freeze both baselines, inventory intentional changes, never reset, and reconcile before importing non-Git evidence |
| P1 | IntegratedDent1402Tums has no Git metadata in the inspected source directory | Local source mixed with tests, bytecode, output, backups and operational records | Create/locate canonical private repository, `.gitignore` runtime artifacts, record initial provenance before extraction |
| P1 | Website deployment couples host success, notification and Git synchronization in one large lifecycle | `deploy_public_html.*` and `DEPLOY.md` | Preserve checkpoints/idempotent resume; separate deploy artifact promotion from source-control publication in target design |
| P1 | Existing monitoring is health/script based rather than an SLO/metrics stack | loopback health, systemd checks, live probes, notifier | Define structured logs, metrics, alert ownership, availability/data-freshness SLOs and trace IDs |
| P1 | Disaster recovery depends heavily on an operator laptop copy | host→laptop snapshots and DPAPI backups | Add independent encrypted off-site storage, retention, restore schedule and documented key recovery |
| P2 | BOT deployment is represented by numerous host-specific PowerShell probes/patch scripts | `BOT/scripts/*-iran.ps1` and server-state chronology | Consolidate into versioned deploy/configuration tooling after preserving useful assertions |
| P2 | CI coverage is uneven | Dentistry has two workflows; BOT local tree has tests but no inspected Git/CI; Voice relies on tests/guards and operational smokes | FANOOS CI gates all packages, migrations, tenant isolation, secret scan, content integrity and deterministic builds |
| P2 | Live checks and operational docs may describe state rather than reproducible configuration | `ops/SERVER_STATE.md` files | Convert only stable requirements to code/config; keep runtime observations time-stamped |

## Duplicate, obsolete, and generated surfaces

| Severity | Surface | Classification | Removal gate |
| --- | --- | --- | --- |
| P1 | `DENT/public_html/api/private_notes_*` and web private reader assets | Obsolete/contradicts bot-only protected delivery | No active links/API clients; unique metadata migrated; owner approval at cutover |
| P2 | Chat-local auth calls | Deprecated; API returns 410 | Client/reference search confirms shared auth only; compatibility metric reaches zero |
| P2 | Notes direct upload plus relay/full-file fallback | Duplicate migration transports | Direct/multipart behavior covers all supported clients and access logs show no fallback use |
| P2 | Cohort/prosthesis static page copies | Tenant route duplication | Generic routes and compatibility redirects pass parity checks |
| P2 | Generated exam route pages, PHP banks and caches | Build/cache derivatives, not domain source | Provenance manifest and deterministic target regeneration |
| P2 | `__pycache__`, rendered PDFs/images, benchmark output, local DB/backups in Integrated tree | Runtime/generated artifacts | Canonical repo ignore rules and clean source snapshot |
| P3 | `IntergratedDent1402Tums` typo compatibility directory | Discovery stub | All tooling/references use canonical directory/repository |

## Missing or incomplete capabilities found by reasonable search

- No standalone DentNote repository, package, template system, or canonical content schema was found. Notes metadata, exam parsers/builders, and Voice summary/reconstruction/export code are the closest concrete sources. Prompt 6 must define the product format while reusing those transformations.
- No generic calendar domain was found. Current schedule behavior is embedded academic constants plus a TUMS/Navid connector.
- No general metrics/tracing stack, alert definitions, or formal SLO documents were found.
- No dedicated isolated security suite was found for chat, forms, public token sharing, or search ACL behavior.
- No version-controlled authority was found for the inspected Integrated bot directory.
- No standalone Dent1402Bot repository/path distinct from `IntegratedDent1402Tums/dent_bot` was found among accessible local candidates.

These are not requests to build new systems in Prompt 1. They are architecture and implementation obligations for later prompts.

## Remediation ownership by stage

| Stage | Must close |
| --- | --- |
| Prompt 2 | Authorities/boundaries, target stack criteria, privacy/retention, public visibility, consistency and unresolved topology decisions |
| Prompt 3 | Tenant hierarchy, canonical IDs/aliases, membership/RBAC, academic entities, transactional schema and migration checkpoints |
| Prompt 4 | Secrets, object storage, deploy/release, portable backup/restore, health/metrics/logs, CI and new-host configuration |
| Prompt 5 | Auth/admin/onboarding, grades/forms/search/notifications, payment/entitlement consolidation and password throttling |
| Prompt 6 | Resource/DentNote schema, question provenance/import, revisioned exam attempt, upload integration and protected-delivery service |
| Prompt 7 | Generic bot config/workspace context, shared API client, channel identities, notification receipts and removal of bot domain authority |
| Prompt 8/cutover | Legacy WIP reconciliation, data/content count and checksum validation, dead-path retirement, tenant red-team and rollback rehearsal |
