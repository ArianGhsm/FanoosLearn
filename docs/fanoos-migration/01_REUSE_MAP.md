# FANOOS reuse map

## Decision vocabulary

- `REUSE_AS_IS`: behavior and implementation are sufficiently bounded to move with only path/package integration and verification.
- `EXTRACT_AND_ADAPT`: proven behavior should be lifted behind a FANOOS contract; tenant/config/storage dependencies must change.
- `REFACTOR_IN_PLACE`: keep the implementation lineage and improve it before or while extracting it.
- `REPLACE_WITH_JUSTIFICATION`: the behavior is needed, but the implementation cannot satisfy multi-tenancy, consistency, security, or scale. Tests/fixtures become the compatibility oracle.
- `RETIRE_LEGACY_ONLY`: do not reproduce in FANOOS; retain temporarily only for evidence, data migration, or cutover.

No status authorizes copying secrets, production data, generated caches, private content, or personally identifiable roster data into Git.

These statuses describe technical reuse potential, not permission to copy a legacy file. The default is behavior-level inspiration and a separate FANOOS implementation. Any literal code extraction requires later explicit, file/scope-specific owner permission; repositories, histories, databases, configuration, credentials, and deployments remain separate.

## Capability decisions

| Capability | Primary source evidence | Status | Preserve | Adapt/replace boundary | Exit evidence |
| --- | --- | --- | --- | --- | --- |
| Password/session authentication | `DENT/public_html/api/{bootstrap,auth_api,auth_store}.php` | `EXTRACT_AND_ADAPT` | PBKDF2 migration, session rotation/revocation, CSRF, public-user projection | Canonical user IDs, transactional storage, cookie/config names, tenant memberships | Legacy hash fixtures authenticate once and rehash; revoked sessions fail |
| OTP enrollment/login/reset | Same auth modules and `.env.example` contract | `EXTRACT_AND_ADAPT` | Expiry, cooldown, attempt limits, one-time verification | Provider adapter, normalized phone model, audit/rate limits | Replay/expiry/brute-force/provider-failure tests |
| Dentistry cohort catalog | `auth_store.php`, `dentistry_curriculum.php` | `REPLACE_WITH_JUSTIFICATION` | Access outcomes and service-enable semantics | Replace source constants/routes with country→workspace data model from Prompt 3 | New workspace added without code change; tenant isolation tests |
| Role/representative policy | `auth_store.php`; feature manager guards | `EXTRACT_AND_ADAPT` | Owner vs scoped manager vs member outcomes | Capability-based RBAC scoped to workspace/resource, not product role strings | Permission matrix and cross-tenant denial tests |
| Static legacy page/route forks | `public_html/notes/**`, `prosthesis-1402/**`, generated exam route trees | `RETIRE_LEGACY_ONLY` | Redirect/cutover mapping only | FANOOS routes resolve by tenant/resource IDs | Redirect inventory and no active internal references |
| Notes metadata and user interactions | `notes_api.php`, `notes_store.php`, notes JS | `EXTRACT_AND_ADAPT` | Term/item CRUD, order, favorites, opens, requests, link reports | Tenant/resource schema, explicit visibility, separate telemetry | Contract tests match legacy flows and anonymous policy |
| Direct chunked upload | `notes_api.php`, `notes_download_host.php`, `notes-files.js`, `check_upload_pipeline_config.php` | `EXTRACT_AND_ADAPT` | Bounded chunks, scoped tokens, size/checksum/finalize rules | Object-storage adapter, resumability, tenant quotas, malware/type policy | Interrupted/resumed/oversize/checksum/auth tests |
| Legacy full-file/relay upload | Branches in `notes_api.php` | `RETIRE_LEGACY_ONLY` | Compatibility telemetry during cutover only | Remove after usage proves zero and direct path covers clients | Access-log proof and migration smoke suite |
| Generic file/paste sharing | `content_tools_*`, `files/**`, `paste/**` | `EXTRACT_AND_ADAPT` | Tokenized sharing, audit, download behavior | Workspace ownership, expiry/revocation, object storage | Token entropy/revocation/tenant/access tests |
| Raw HTML one-use uploader | `html_uploader_*`, sandboxed viewer | `REFACTOR_IN_PLACE` | One-use capability token and strict sandbox concept | Threat model, CSP, isolated origin, retention; omit if no product owner | Security review and browser sandbox regression |
| Chat/domain collaboration | `chat/chat_api.php`, `chat.js` | `REPLACE_WITH_JUSTIFICATION` | DM/group/saved/messages, receipts, search, presence, reactions, polls, moderation outcomes | Replace giant JSON endpoint with tenant-aware transactional/realtime service | Behavior fixtures, ordering/idempotency, membership and load tests |
| DIS request workflow | `dis_request_api.php`, DIS pages/JS | `RETIRE_LEGACY_ONLY` | Requirement/question/export evidence if still needed | Model any approved generic workflow in Forms rather than a dedicated Dentistry API | Active-use/data-owner review and form migration mapping |
| Message/card generator | `msg_{api,store}.php`, message pages/JS | `RETIRE_LEGACY_ONLY` | Active token/card migration only | Use generic content/paste sharing with expiry/revocation | Active-reference inventory and redirect/expiry plan |
| EndoSim catalog | `endosim_catalog.php`, EndoSim pages/assets | `RETIRE_LEGACY_ONLY` | Order history/item snapshots required for migration | Do not seed Dentistry physical products into generic source; use target product records | Commerce reconciliation and product-owner decision |
| Search | `search_api.php`, `search_store.php` | `REPLACE_WITH_JUSTIFICATION` | Query/result shape and access-filter expectation | Tenant-aware index/read model with authoritative ACL filtering | Cross-tenant leakage and stale-index tests |
| Exam UI behavior | `exam-quiz.js`, `exam-bootstrap.js`, `exams-course.js` | `EXTRACT_AND_ADAPT` | Navigation, flags, timer, review, mistakes, report UX | FANOOS visual system and API contract | Browser contract/E2E parity |
| Exam scoring/report logic | `exams_api.php`, `exams_store.php` | `REFACTOR_IN_PLACE` | Server scoring, access recheck, bounded history, reset | Revisioned server-owned active attempt and transactional writes | Retry/concurrency/revision/scoring fixtures |
| Question import/normalization | `exams_bank_helpers.php`, text parsers, `scripts/build_*` | `EXTRACT_AND_ADAPT` | Parsers, validators, overrides, deterministic builds | Canonical content entities, provenance/license, generic course IDs | Repeatable import, checksum/count and quality checks |
| Embedded/generated PHP question bank | `exams_bank.php`, `exams_*_data.php`, cache | `RETIRE_LEGACY_ONLY` | Source-to-target content mapping and parity fixtures | Store normalized questions outside executable source | Licensed-source manifest and count/hash reconciliation |
| Grades behavior | `grades_store.php`, `grades/grades_api.php`, `grades.js` | `EXTRACT_AND_ADAPT` | Own-grade projection, manager import/delete/reset semantics | Tenant/course/enrollment schema; immutable import/audit records | CSV migration fixtures and row-level isolation tests |
| TUMS/Navid connector | `navid_{api,service,store}.php`, runner/OCR scripts; `BOT/dent_bot/navid.py` | `EXTRACT_AND_ADAPT` | Feed normalization, reconnect/captcha/operator workflow | Institution connector interface and secrets/session isolation | Recorded connector fixtures and fail-closed tenant routing |
| Term 7 academic constants/schedule | `academic_term7.php`, `dentistry_curriculum.php` | `RETIRE_LEGACY_ONLY` as code; migrate valid data | Current dates/groups/content where legally current | Prompt 3 academic/calendar entities; timezone-aware recurrence/date model | Data migration review and schedule parity |
| Forms | `forms_api.php`, `forms_store.php`, form JS | `EXTRACT_AND_ADAPT` | Audience modes, form lifecycle, submit, response management, receipts | Form/version/submission tables, object storage, explicit guest token | Audience, duplicate submit, privacy, upload and payment tests |
| Payment state machine | `payments_api.php`, `payments_store.php` | `EXTRACT_AND_ADAPT` | Server totals, immutable snapshots, verified callbacks, idempotency, result token, reconciliation | Transactional DB, tenant/product/entitlement model, outbox | Provider fixtures, duplicate callback, crash/retry, reconciliation tests |
| Zibal/Zarinpal adapters | `payment_gateway.php`, payment handoff tests | `EXTRACT_AND_ADAPT` | Request/verify interpretation and normalized result | Secret references, timeouts/retries, typed provider boundary | Recorded/sandbox responses without real charges |
| Stored gateway credentials/admin echo | `payments_store.php`; owner gateway payload/save action | `RETIRE_LEGACY_ONLY` | Migration detection only; never echo secret | Secret manager/environment reference with write-only rotation | Secret scanning and admin response tests |
| Entitlement checks | Exam/payment/forms site code; `BOT/dent_bot/{payments,subscriptions,site_api}.py` | `REFACTOR_IN_PLACE` conceptually | Fail-closed access and verified-payment grant | One canonical entitlement API/data authority; bot holds cache/receipts only | Cross-channel consistency and revocation tests |
| Bot account linking | Website bot modules; `BOT/dent_bot/{onboarding,site_api,state}.py` | `EXTRACT_AND_ADAPT` | Short-lived nonce, signed service call, canonical site identity | FANOOS user/channel identity and workspace selection | Replay/expiry/wrong-account/cross-tenant tests |
| Signed website↔bot protocol | `DENT/api/bot_api.php`, bot modules; `BOT/dent_bot/site_api.py` | `EXTRACT_AND_ADAPT` | HMAC, timestamp/nonce, action-specific authorization | Versioned service API, key rotation, idempotency and observability | Signature/replay/skew/rotation/compatibility suite |
| Protected-delivery authorization/queue | `BOT/dent_bot/protected_media.py`, `state.py` | `EXTRACT_AND_ADAPT` | Authorize before queue/work/send, bounded queue/rate/cooldown, receipts | FANOOS entitlement client, generic channel adapters, job queue | Revocation-race, overload, retry and duplicate-send tests |
| Secure-raster fingerprint engine | `BOT/dent_bot/{pdf_fingerprint,forensic_detector}.py` | `REUSE_AS_IS` initially | Current algorithm/version fixtures, image-only output, visible+opaque marks, validation | Wrap as isolated worker; only configuration/packaging changes before algorithm changes | Existing unit, attack-regression and benchmark corpus passes |
| Telegram protected send/file-ID cache | `booklets.py`, `protected_media.py`, `state.py` | `EXTRACT_AND_ADAPT` | `protect_content`, issuance binding, authorized cache reuse | Generic content IDs, workspace policies, channel adapter | Cached and uncached authorization parity |
| Website private reader | `DENT/public_html/api/private_notes_*` and viewer assets | `RETIRE_LEGACY_ONLY` | Data/reference inventory only | Bot/authorized delivery is the current protected path | No active links, no unique unmigrated metadata, cutover approval |
| Notification inbox/preferences | `notifications_{api,store}.php`, UI | `EXTRACT_AND_ADAPT` | Read/unread, preferences, snooze, audiences | Tenant-aware notification/outbox/read-receipt model | Fan-out/idempotency/preference/isolation tests |
| Durable multi-channel deploy notifier | `BOT/deploy_notifier/**`, contract/tests | `EXTRACT_AND_ADAPT` | Queue-before-send, stable event ID, per-channel receipt/retry | FANOOS service names/config and supported channels | Partial-channel failure and replay tests |
| Web push | `push_{api,store}.php`, `push.js`, service worker | `REFACTOR_IN_PLACE` | Subscribe/unsubscribe/public-key semantics | Notification outbox, tenant/user/device lifecycle | Expired endpoint and preference tests |
| App shell/PWA/offline behavior | `app/**`, `shell.js`, `theme.js`, `pwa.js`, `sw.js`, manifest/version stamp | `EXTRACT_AND_ADAPT` | install/update/offline/cache behavior and feature visibility lessons | New FANOOS branding/routes/design system and safe cache manifest | Offline/update/logout/cache-partition E2E tests |
| Voice transcription/content transforms | `VOICE/voice_transcriber/{stt,providers,ai,document_exports,artifacts}.py` | `EXTRACT_AND_ADAPT` | Provider boundary, prepaid reservation/refund, encrypted output, deterministic artifacts | Service API; no Voice wallet/user/database merge | Existing provider/AI/export/privacy tests against adapter |
| Voice product database and wallet | `VOICE/voice_transcriber/database.py`, payments/pricing | `RETIRE_LEGACY_ONLY` for FANOOS | Contract examples only | FANOOS commerce/user authority remains separate | Architecture review finds no dual balance/identity authority |
| Persian text integrity checker | `DENT/scripts/check_text_integrity.py`; BOT equivalent | `REUSE_AS_IS` | UTF-8/mojibake detection behavior | Only configure FANOOS paths and CI entrypoint if needed | Known bad/good fixture run |
| Website deploy pipeline | `DENT/DEPLOY.md`, `scripts/{complete_task,deploy_public_html}.*` | `EXTRACT_AND_ADAPT` | preflight, snapshot, validate, delta limits, health, manifest, event lifecycle | New FANOOS hosting/release mechanism from Prompt 4; do not copy FTP assumptions blindly | dry-run, rollback and no-runtime-data deployment tests |
| Bot/Voice release-switch rollback | `VOICE/scripts/deploy-production.ps1`; BOT deploy scripts/systemd | `EXTRACT_AND_ADAPT` | immutable release dirs, `current` symlink, pre/post backup, health rollback | FANOOS host/container/runtime configuration | failed-release rehearsal returns prior healthy version |
| Backup/restore verification | Dentistry verified snapshots; BOT and Voice backup/restore scripts | `EXTRACT_AND_ADAPT` | consistent snapshot, hashes, schema/integrity, round-trip verify, pre-restore backup | Portable encryption/key custody, new data inventory, off-host retention | scheduled restore rehearsal with RPO/RTO evidence |
| Legacy visual design/branding | page HTML/CSS/assets | `RETIRE_LEGACY_ONLY` | Accessibility and interaction lessons only | New FANOOS design system and tenant branding | UI acceptance; no Dentistry/TUMS brand residue |

## Reuse constraints

1. Do not copy legacy code without explicit owner permission. If a later prompt grants file/scope-specific permission, extract only the smallest coherent unit with its tests and provenance; never bulk-copy `public_html`, a database, runtime state, configuration, credentials, or generated artifacts.
2. Every extracted website write path must receive an explicit `workspace_id` or a server-resolved scope; client-supplied tenant context alone is insufficient.
3. Preserve legacy identifiers in migration aliases, not as FANOOS primary keys.
4. Payment, entitlement, identity, and grades each have one canonical target authority. Bot and search read models must be disposable/rebuildable projections.
5. Binary content belongs in object/file storage with database metadata; secrets belong in a secret store; neither belongs in Git or general JSON domain documents.
6. Preserve public behavior only after Prompt 2/3 makes visibility explicit. Current anonymous notes behavior must not silently become a global FANOOS default.
7. Keep protected delivery isolated: untrusted PDFs are parsed/rasterized outside the web request, with bounded resources and no protected original on the public web host.

## Source-to-target map for Prompts 3–7

The following map uses the actual stage titles found in the provided Prompt 3–7 files. Each later prompt should begin from these sources and the decision above, then follow the Prompt 2 architecture rather than inventing parallel components.

### Prompt 3 — Multi-Tenant Database + RBAC/Scope Model

| Target area | Start from | Required transformation |
| --- | --- | --- |
| Geographic/institution/workspace hierarchy | Legacy cohort catalog/routes in `DENT/api/auth_store.php`, `dentistry_curriculum.php`, notes/exam cohort selectors | Treat values as migration seeds only; create data-driven hierarchy and workspace scope |
| Users and identities | `auth_store.php` user normalization/password/phone logic; bot link records | Preserve legacy IDs as aliases; separate user from membership and channel identity |
| Membership, roles, capabilities | Role/permission and same-cohort manager functions in `auth_store.php`; guards in notes/forms/grades/payments | Translate observed outcomes to scoped RBAC assignments/policies |
| Academic model | `dentistry_curriculum.php`, `academic_term7.php`, exam course registry, notes term files, grade CSV headers | Normalize semester/course/session/resource/date entities with source provenance |
| Commerce/entitlement schema | `payments_store.php`; exam access helpers; BOT offer/subscription/entitlement tables in `state.py` | One transactional order/payment/entitlement authority; bot tables become projections/receipts |
| Migration tooling | `scripts/migrate_legacy_data.php`, `migrate_dm_store.php`, notes migration and integrity checks | Build idempotent, checkpointed importers with counts/checksums and alias tables |

### Prompt 4 — Infrastructure + Server + CI/CD + Backup/Restore + Upload/Storage

| Target area | Start from | Required transformation |
| --- | --- | --- |
| Git/CI | `DENT/.github/workflows/*.yml`, `run_static_checks.sh`, text/instruction guards; `VOICE/scripts/repository_guard.py` | Update paths/runtimes, add target tests/security scans, keep least permissions |
| Release/deploy | `DENT/DEPLOY.md` and deploy scripts; `VOICE/scripts/deploy-production.ps1`; BOT deploy lifecycle scripts | Preserve gates/events/rollback, adapt only after inspecting new FANOOS host |
| Backup/restore | Dentistry verified host snapshot; BOT/Voice backup, verification and protected restore scripts | Enumerate new authorities, choose portable encryption/off-host retention, rehearse restore |
| Upload/object storage | notes direct gateway/chunks, download-host helpers, verified upload script, Voice artifact/cache registry | Introduce tenant prefixes, signed object operations, checksum/size/type/quota policy |
| Services/health/logs | BOT/Voice systemd units, loopback health endpoints, deploy notifier | Adapt service manager/topology; define metrics, logs, alerts, RPO/RTO |

### Prompt 5 — Core Platform Adaptation / Migration

| Target area | Start from | Required transformation |
| --- | --- | --- |
| Auth/account/onboarding | Website auth/account modules; BOT onboarding and signed profile calls | Implement against Prompt 3 model; preserve login/security fixtures |
| Workspace selection and scoped admin | cohort resolver, service flags, owner/representative APIs, bot keyboard/context behavior | Replace route/product branching with workspace context and capability checks |
| Academic navigation/schedule | curriculum/term constants, Navid feed/service, BOT student assistant | Render generic hierarchy; isolate Navid as an institution connector |
| Grades | grades store/API/UI and signed bot grade calls | Import CSV semantics into target schema; maintain own-grade and manager outcomes |
| Announcements/forms/search | notifications, Navid, forms, search modules | Extract contracts; rebuild storage/index with tenant isolation |
| Payments/entitlements | payments state machine/gateway adapters, account/buy/form/exam clients, BOT subscription client | Centralize transactional authority and expose idempotent shared API |

### Prompt 6 — Content Production & Learning Modules Adaptation

| Target area | Start from | Required transformation |
| --- | --- | --- |
| Resource/content model | notes store/API/UI, content-tools tokens, forms uploads, exam content records | One resource/version/product model with explicit type, provenance, ACL and workspace |
| DentNote/summary formats | No standalone DentNote implementation found; use observed notes metadata plus `VOICE` AI transforms/prompts/exports as evidence | Define the missing canonical format; adapt transforms without importing Voice state/pricing |
| Question generation/import | text/essay/answer parsers, `scripts/build_*`, overrides and quality checks | Convert to deterministic jobs producing versioned normalized questions |
| Exam engine | exam API/store and quiz JS; Integrated bot exam tests | Add revisioned server attempt; share web/bot contract; preserve scoring/report fixtures |
| Secure delivery | BOT protected media, fingerprint/detector/booklet source and issuance tables | Package worker/service, bind to target entitlement/content IDs, retain attack tests |
| File delivery | notes direct upload/download host and Voice artifact registry/cache | Use Prompt 4 object storage and signed delivery; never public web protected originals |

### Prompt 7 — Telegram Bot + Shared Backend Integration

| Target area | Start from | Required transformation |
| --- | --- | --- |
| Bootstrap/config/health | `BOT/dent_bot/{config,app,runtime,service,health,site_health}.py`, systemd units | New package/config/secret names; no old tokens/paths/tenant assumptions |
| Linking and workspace context | `onboarding.py`, `site_api.py`, `state.py`, website bot link modules | Canonical FANOOS user/channel link plus selectable workspace context |
| Menus and feature clients | `ui.py`, `keyboard_invariant.py`, `student_assistant.py`, `navid.py`, payments/subscriptions | Preserve navigation invariants; handlers call shared APIs and hold no domain authority |
| Notifications/reminders | `reminder_policy.py`, subscriptions, notification references/receipts, deploy notifier patterns | Tenant preferences, idempotent delivery, per-channel receipt/retry |
| Exams/content/payment | `test_exam_bot.py`, site API actions, payment return/offer code, protected delivery stack | Consume Prompt 5/6 contracts; no parallel orders, attempts, entitlements or content catalog |
| Telegram delivery | protected dispatcher, fingerprinting, forensic detector, booklet source admin | Keep bounded fail-closed worker; replace source chat and product strings with configuration/data |

## Sequencing guardrails

- Prompt 2 must settle boundaries and authority before Prompt 3 commits schema.
- Prompt 3 must land tenant identity/RBAC and migration aliases before core feature extraction.
- Prompt 4 must provide safe storage, secrets, jobs, deploy and recovery foundations before uploading legacy content or enabling payment.
- Prompt 5 should establish canonical auth/workspace/payment/entitlement contracts before Prompt 6 content sales and Prompt 7 bot clients.
- Prompt 6 should expose protected-delivery jobs and exam attempts through shared contracts; Prompt 7 consumes them and must not recreate them.

## HANDOFF_TO_PROMPT_2

### Actual current stack

- Website: custom PHP 8.x multi-page application, vanilla browser JavaScript/CSS, PHP sessions, JSON/CSV/files with locks and atomic replacement, PWA/service worker, shared-host FTP/SFTP-style deployment.
- Bot: Python Telegram and Bale processes, SQLite operational state, systemd, HMAC site API, loopback health endpoints, Telegram routed through a local Xray proxy in the documented production topology.
- Protected content: private Telegram source, bot-side PDF raster/fingerprint worker, SQLite issuance/cache records, protected Telegram sends; website private reader is retired by architecture but residual code remains.
- Voice product: independent Python/SQLite service with STT/AI/provider adapters, Telegram/MTProto, artifacts/exports and mature release/backup scripts.
- CI/ops: GitHub Actions static/Persian-text checks for Dentistry; PowerShell/Bash deploy and validation scripts; verified file snapshots and SQLite backups; durable three-channel deploy notifier.

### Most valuable reuse candidates

1. Auth security behavior and fixtures: rotation, revocation, hashing migration, OTP controls and CSRF.
2. Payment state machine: server totals, provider verification, idempotency, result tokens and reconciliation.
3. Notes/exam/form/grade domain behavior and front-end interaction contracts.
4. Signed site↔bot protocol, account-link nonce flow, durable notification receipt model.
5. Protected delivery queue, issuance ledger, fingerprint engine, forensic regression suite and reauthorization sequence.
6. Verified backup/restore, guarded deployment, rollback and text/content-quality checks.
7. Voice provider/artifact/privacy/idempotency patterns, used only through extracted interfaces.

### Problematic components

- Source-coded cohort/owner/roster/course/route model and product-specific roles.
- Large monolithic PHP APIs and JSON stores that combine unrelated tenants/users and will contend or grow without transaction/isolation guarantees.
- Embedded/generated executable question banks without a clean source/provenance boundary.
- Multiple upload transports and residual website private-reader code.
- Payment credential material mixed with domain data/admin payloads.
- Local Dentistry WIP is massively diverged; IntegratedDent1402Tums lacks an inspected Git authority and mixes runtime/generated artifacts with source.

### Data and storage boundaries Prompt 2 must preserve

- Canonical FANOOS backend owns users, memberships, RBAC, academic entities, resource metadata, grades, forms, orders, verified payments and entitlements.
- Object/file storage owns binaries; database records own metadata, checksum, version, visibility and lifecycle.
- Bot database owns only channel/runtime state, delivery jobs/receipts, protected source references, issuances and disposable projections.
- Search indexes, caches and browser offline data are rebuildable projections, never authorities.
- VoiceTranscriberBot retains its independent users/wallet/jobs/transcripts/AI state unless a future explicit service contract is approved.
- Secrets and gateway credentials are referenced from an external secret/config boundary and never returned in admin read payloads.

### Deployment realities

- Existing website production uses guarded delta deployment to shared hosting, host→laptop runtime-data snapshot/mirror, live health verification, and Git sync after host success.
- Current bot/Voice production patterns use Ubuntu systemd, SSH, immutable release directories/current symlink where implemented, loopback health, pre/post backups and rollback.
- Current laptop backups use Windows DPAPI and are machine/user-bound; this is not sufficient as the only FANOOS disaster-recovery mechanism.
- The user has stated that no new FANOOS server is available yet. Prompt 2 should design local/testable boundaries; Prompt 4 should inspect and adapt to the new host when credentials are provided.

### Real unresolved questions

1. Which target application/database/object-storage/job stack best fits the not-yet-provisioned FANOOS host and expected tenant scale?
2. Is anonymous resource browsing a tenant-configurable option, or should all learning resources require membership?
3. What is the exact tenant/workspace ownership hierarchy when one institution/program participates in multiple workspaces?
4. Which roles are global support roles versus workspace-scoped owner/manager/content/finance roles?
5. Which legacy question/note assets have confirmed provenance and permission for migration?
6. Are Telegram and Bale both target channels, and is protected fingerprint delivery enabled per tenant/product?
7. What retention/privacy requirements apply to analytics, chat, forms, receipts, bot identities, issuances and audit logs?
8. Which local Dentistry WIP changes, if any, are intentional production evidence not represented by canonical GitHub?
9. Where should IntegratedDent1402Tums be versioned before extraction?

Prompt 2 should resolve these as architecture decisions or explicit deferred decisions. It should not silently choose new authorities or rewrite proven behavior.
