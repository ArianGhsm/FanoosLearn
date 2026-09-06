# FANOOS forensic audit

Audit date: 2026-09-05 (Asia/Tehran)

## Executive conclusion

FANOOS must be a new multi-tenant product boundary, not a renamed copy of Dentistry1402TUMS. The legacy estate nevertheless contains proven behavior worth preserving: password/OTP sessions, cohort-scoped authorization, notes administration, examination scoring, verified payment callbacks, bot account linking, protected PDF delivery, operational notifications, and guarded backup/deployment routines.

The reusable unit is usually a behavior, contract, or algorithm—not the legacy page tree or its JSON/SQLite ownership model. Dentistry1402TUMS is a custom PHP multi-page application with browser JavaScript and JSON/CSV file stores. IntegratedDent1402Tums is a Python Telegram/Bale service with SQLite. VoiceTranscriberBot is an independent Python/SQLite product whose provider, idempotency, artifact, privacy, and recovery patterns are useful, but whose product state must remain separate.

The main architectural risk is accidental creation of parallel authorities. FANOOS needs one canonical identity and tenancy model, one entitlement/payment authority, explicit content metadata ownership, and signed adapters for bot and notification workers. Bot delivery/issuance state and VoiceTranscriberBot state remain bounded service data.

No production implementation was rewritten, no legacy repository was mutated, and no production endpoint was exercised during this stage.

Per the project-owner clarification, legacy reuse in this audit means behavioral evidence and architectural inspiration. No legacy code file is authorized for copying into FANOOS unless the owner later grants explicit, file/scope-specific permission. The products, repositories, state, credentials, and deployments remain completely separate.

## Audit scope and evidence baseline

| Label | Inspected source | Baseline | Role in this audit |
| --- | --- | --- | --- |
| `DENT` | `ArianGhsm/Dentistry1402TUMS`, inspected from a clean temporary clone | `589ff3d5e791414b52bc2acb1d69d0d1ce1bc09c` on `origin/main` | Canonical website behavior, APIs, file-store schemas, CI, deployment |
| `DENT-WIP` | Local `dentistry1402tums` sibling | local HEAD `9901d5354bccb2d72933e612d9c4f7a1b6c8cfe3`; 159 local-only and 224 remote-only commits; 22 deleted, 438 modified, 206 untracked entries | Evidence of uncommitted/local operational history only; not a migration baseline |
| `BOT` | Local `IntegratedDent1402Tums` sibling | local-only working tree; no Git metadata found | Telegram/Bale integration, subscriptions, protected delivery, notification spool, VPS operations |
| `VOICE` | Local `VoiceTranscriberBot` sibling, `ArianGhsm/VoiceMatnAIBot` | clean local repository; HEAD `d6dfa71b203a008ebdd782e57e8e3f1c82ba8018` | Independent transcription/content pipeline and mature release/backup patterns |
| `TYPO-STUB` | Local `IntergratedDent1402Tums` sibling | compatibility folder whose instructions point to `IntegratedDent1402Tums` | Not a source implementation; retire from discovery after references are corrected |
| `FANOOS` | `FanoosLearn/FanoosLearn` | empty remote; local target initially contained only an untracked specification text file | Destination for audit artifacts |

The GitHub organization repository was empty at pre-flight. A standalone Dent1402Bot source tree was not found among accessible local candidates; the active implementation is inside `IntegratedDent1402Tums/dent_bot`. Generated files, caches, backups, compiled Python bytecode, runtime uploads, SQLite databases, and rendered PDF/image samples were excluded as source code.

## Method

The audit followed the chain `documentation -> route/UI -> API -> store/helper -> tests/scripts -> deployment`. Root instructions and project logic were read before source inspection. Claims were accepted only when supported by implementation or were marked as operational documentation claims. Searches covered account/auth, cohorts, admin, notes, file management, exams, grades, schedule/Navid, notifications, forms, search, payments, bot links, protected delivery, storage, CI, deploy, backup, restore, and health operations.

Sensitive values were neither requested nor copied. The audit records configuration names and storage shapes, not credentials, student numbers, phone numbers, chat IDs, or production content.

## Current implementation map

```text
Browser / PWA
  |-- shared PHP session + CSRF --------------------------.
  |-- page-specific JavaScript                            |
  v                                                       |
Dentistry PHP APIs                                        |
  |-- auth/cohort/role authority <------------------------'
  |-- notes/forms/grades/exams/chat/search
  |-- order creation + gateway verification
  |-- signed bot API + account-link nonces
  |-- owner notification inbox + web-push subscriptions
  v
Web-host file storage (JSON/CSV/files, flock + atomic writes)
  |
  | signed HMAC requests / stable event IDs
  v
Integrated bot service (Telegram + Bale)
  |-- SQLite: bot UI, delivery receipts, offers/subscriptions,
  |           protected source metadata, issuance/cache records
  |-- durable deploy-event spool -> Telegram/Bale/site
  |-- private Telegram source -> fingerprinted PDF -> protected send
  v
Telegram/Bale and private bot artifacts

Payment gateways <---- server-side request/verify ---- Dentistry payment API
Download host    <---- chunked/direct upload --------- notes/content tools
Navid/TUMS       <---- browser snapshot/OCR runner ---- Navid service
```

This topology already encodes an important boundary: website data is authoritative for site identity, grades, public notes metadata, and canonical payments; bot SQLite is authoritative for bot runtime/delivery only. Preserve that separation while replacing the legacy transport and persistence details.

## Code-level trace: login and identity

1. `DENT/public_html/assets/site/scripts/auth.js` and `account.js` call `POST /api/auth_api.php?action=login`.
2. `auth_api.php` normalizes the student identifier and password, then calls `dent_verify_credentials` in `api/auth_store.php`.
3. `auth_store.php` reads `storage/auth/users.json`, verifies the PBKDF2 password representation, and can rehash older credentials.
4. On success, `dent_login_user` rotates the PHP session ID. `api/bootstrap.php` configures the `dent1402_session` cookie and optional server-only session path.
5. A hashed session record is maintained in `storage/auth/active_sessions.json`; the response exposes only a public user/cohort/service view.
6. `me`, logout, password change, and session-revocation actions share this authority. OTP login, phone enrollment, password reset, and external signup use `storage/auth/meta.json`, an ignored secret key, and the Faraz SMS adapter with OTP cooldown/attempt controls.
7. Roles and service flags are resolved centrally, but default cohort records, owner identity, rosters, role names, and route forks are Dentistry-specific source constants.

Preserve session rotation, password hashing migration, revocation, CSRF, OTP replay/cooldown rules, and a public-user projection. Replace student-number-as-global-identity, source-coded rosters, and product-specific role branching with tenant memberships and policy-driven permissions. A password-login rate limiter was not found adjacent to the password action and must be designed explicitly.

## Code-level trace: notes and resources

1. Notes pages (`public_html/notes/**`) use `assets/site/scripts/notes-home.js`, `notes-term.js`, `notes-files.js`, and `notes-host-picker.js`.
2. `POST/GET /api/notes_api.php` serves terms, term detail, resource state/open tracking, favorites, access requests, issue reports, and owner/representative CRUD.
3. Term metadata is stored per legacy cohort in files such as `storage/notes/1402_terms.json`, `1403_terms.json`, `1404_terms.json`, and `prosthesis_1402_terms.json`.
4. Public term/resource reads are intentionally possible in the current implementation; management and cohort insights require an authenticated same-cohort manager. Favorites, requests, and issue reports require a user.
5. Large resources are sent to a separate download host. The preferred path prepares a scoped direct-upload gateway and streams bounded browser chunks; cPanel/FTP configuration is loaded outside tracked source. The API still contains fallback full-file/relay paths, which conflict with the newer stream-only operational rule.
6. The metadata layer also contains legacy external links and cohort-specific seed content. This is data to migrate, not a reusable tenant model.

Preserve resource CRUD semantics, ordering, favorites, open telemetry, reports, bounded streaming, checksum/size verification, and distinct metadata/binary storage. Make public visibility an explicit tenant/content policy. Do not carry static route forks or external URLs into source code.

## Code-level trace: examinations

1. `exams-home.js`, `exams-course.js`, `exam-bootstrap.js`, `exam-quiz.js`, and `exam-payment.js` call `api/exams_api.php`.
2. Catalog/course actions can return guest-safe projections. The exam action computes access from authentication, course configuration, and payment/order state; it returns 401 for login and 403 for missing entitlement.
3. Question content is hydrated only after access. The current bank is split across `exams_bank.php`, `exams_modules.php`, generated `exams_*_data.php`/override modules, `api/data/**`, and cache files.
4. Submission rechecks access, scores answers on the server, and writes report, bounded attempt history, flags, study state, mistakes, and activity under participant keys in `storage/exams/store.json`.
5. Browser-side quiz behavior includes navigation, flags, timing, review, and payment handoff. Some in-progress state remains client-oriented rather than a canonical revisioned attempt.

Preserve the scoring and report behavior, access-before-hydration rule, question normalization, flag/review UX behavior, and payment gate. Extract question import/normalization; move catalogs, prices, and banks to tenant-scoped persistence. A server-owned, revisioned active-attempt contract is required before web and bot exams share state.

## Code-level trace: payments

1. `assets/site/scripts/buy.js`, `payment-collection.js`, `exam-payment.js`, account payment UI, and form flows call `api/payments_api.php` or the payment handoff endpoint.
2. Item, collection, quote, cart, and order actions derive the payable amount on the server, validate item state/capacity/discount rules, and create a pending order with a random public result token.
3. `api/payments_store.php` normalizes a versioned JSON store and serializes writes with a lock/atomic replacement.
4. Zibal and Zarinpal adapters create a gateway request. Callback input is not accepted as payment proof: the server calls the provider verify endpoint before marking an order successful. Existing successful orders are handled idempotently; owner verification and reconciliation are available.
5. Verified success feeds exam/form entitlements and notification hooks. Receipt upload is represented as a synthetic successful receipt order.
6. Gateway credentials can be accepted by `ownerSaveGateway`, stored in the same payment JSON structure, and returned in the owner gateway payload. This configuration/data mixing must not be retained.

Preserve server-side totals, immutable order snapshots, public result tokens, verified callback semantics, idempotency, reconciliation, and audit transitions. Replace the JSON store with transactional tenant-scoped persistence and use a secret manager/environment references rather than returning credential material to admin clients.

## Code-level trace: bot linking and protected delivery

1. The site stores short-lived account-link nonces and linked channel identities through `api/bot_api.php`, `bot_store.php`, `bot_persistence.php`, and `bot_delivery_store.php` under `storage/integrations/**`.
2. `BOT/dent_bot/site_api.py` calls signed website endpoints using an HMAC timestamp/nonce contract. The bot does not become the canonical identity provider.
3. A protected source posted to the configured private Telegram source is parsed by `dent_bot/booklets.py`; source metadata and Telegram file IDs are recorded in bot SQLite by `dent_bot/state.py`.
4. `ProtectedMediaDispatcher` in `dent_bot/protected_media.py` authorizes before queueing, enforces bounded queue/rate/cooldown limits, and reauthorizes before processing and again before sending.
5. For PDFs, the bot obtains a canonical watermark identity from the signed site API, derives issuance-bound material from user/document/source/version inputs, records issuance, and invokes `dent_bot/pdf_fingerprint.py`.
6. The current secure-raster path renders small page batches, removes live text/attachments, applies visible recipient identity and opaque forensic marks, validates output (including qpdf when available), enforces time/size bounds, and sends with Telegram `protect_content=true`.
7. Reusable Telegram file IDs are cached per authorized issuance. Temporary directories are removed; cached sends still recheck authorization. `forensic_detector.py` and regression/attack tests support tracing.

Preserve the fail-closed authorization sequence, bounded worker, issuance ledger, deterministic identity binding, protected-send flag, temporary-file discipline, and forensic regression corpus. Extract this as a protected-delivery service. Do not restore the retired website private reader or store protected originals on the web host.

## Documentation-to-code reconciliation

| Claim | Code evidence | Result |
| --- | --- | --- |
| Shared authentication is the only website session authority | Pages call `api/auth_api.php`; chat rejects old chat-local login/logout/me with HTTP 410 | Confirmed; retire remaining compatibility references |
| Runtime web data flows host -> laptop and never laptop -> host/Git | `DEPLOY.md`, `deploy_public_html.*`, `snapshot_remote_storage_verified.py`, ignores/server-only structure | Confirmed as deploy policy; not exercised during this audit |
| Protected PDFs are bot-only and website reader is retired | `BOT` contains current issuance/raster/send pipeline and retirement script | Confirmed target direction |
| Website has no private-reader implementation | `DENT/public_html/api/private_notes_*` and related viewer files still exist in canonical code | Contradiction/dead surface; mark `RETIRE_LEGACY_ONLY` after reference/data confirmation |
| New large notes uploads are stream/chunk only | Direct-gateway/chunk implementation exists, but legacy full `$_FILES`/relay branches remain | Partial drift; retain new behavior, remove fallback only after usage evidence |
| Multi-cohort behavior exists | Cohort resolver, cohort stores, scoped managers and route flags exist | Confirmed behavior, but catalog/routes are source-coded—not true multi-tenancy |
| Payment callback is verified server-side | Provider verification precedes success transition and reconciliation exists | Confirmed |
| Bot/site state has clear ownership | Site API and bot project logic state the boundary; SQLite tables contain bot-only operational state | Mostly confirmed; duplicated payment offer/cache/delivery structures need an explicit contract |

## Duplicates, obsolete paths, and coupling hotspots

- `private_notes_*` and the retired reader overlap with the bot-only protected delivery direction. Keep only long enough to prove no live dependency or unmigrated metadata.
- Chat still contains explicit rejection of deprecated chat-specific authentication. The compatibility guard is useful temporarily; the old auth path itself must not return.
- Notes upload supports direct chunks, host relay, and full-file fallback. This is a migration seam, not three target architectures.
- Forms, exams, and bot subscriptions all consume payment state through partially different adapters. Their entitlement result must converge on one target contract.
- Legacy cohort routes and storage filenames duplicate the cohort dimension at the filesystem and page-tree levels.
- Question-bank content appears in large embedded/generated PHP modules plus source text and runtime caches. Generated artifacts must not be mistaken for canonical authored content.
- IntegratedDent1402Tums contains source beside bytecode, benchmark output, PDFs/images, backups, temporary output, and local SQLite material. Only named source/tests/ops contracts are reuse candidates.
- The typo compatibility folder and static cohort/page copies increase discovery ambiguity and should be retired after reference checks.

## What behavior FANOOS should preserve

- Fail-closed access checks at request time and again before protected delivery.
- Session rotation/revocation, CSRF, password hash migration, OTP expiry/cooldown/attempt limits.
- Scoped representative management, but expressed through tenant membership and capabilities.
- Resource metadata lifecycle, favorites, reports, open-state telemetry, and verified large-file transfer.
- Server-side exam scoring, bounded attempt history, mistake/review behavior, and access-gated question hydration.
- Server-calculated payment totals, provider verification, idempotent transitions, result tokens, reconciliation, and notification hooks.
- Signed service calls with timestamp/nonce replay defense; durable notification event IDs and receipts.
- Issuance-bound watermarking/forensics, protected platform sends, cache reuse only after reauthorization.
- Backup integrity checks, pre-restore backup, release health verification, and automatic rollback patterns.
- Persian UTF-8/text-integrity checks and deterministic content validation.

## What is legacy-only

- Dentistry/TUMS/1402 branding, labels, professor/course lists, student rosters, owner identifier, DIS number maps, route forks, cohort-specific storage filenames, and current visual styling.
- Direct assumptions that a student number is globally unique or that a representative manages exactly one Dentistry cohort.
- Website private PDF reader/device state and web-host protected originals.
- Product-specific bot keyboards, messages, hard-coded source chats, server names/paths, and one-off operational probes.
- VoiceTranscriberBot wallet, jobs, transcripts, AI run state, and Telegram identity. Reuse its patterns through adapters; do not merge its database.

## Unresolved evidence requiring a later decision

1. The canonical GitHub Dentistry branch is much newer than the local dirty/diverged working tree. Before any legacy migration script reads local runtime material, reconcile intent without resetting or overwriting that tree.
2. IntegratedDent1402Tums has no Git history in the inspected directory. Establish an authoritative repository/commit and exclude runtime artifacts before extracting code.
3. Confirm whether public anonymous notes/resources remain a product requirement per tenant; current behavior permits it.
4. Define the FANOOS tenant hierarchy and cross-tenant roles before translating cohort permissions.
5. Define which legacy question/content files are licensed and canonical authored sources rather than generated/cache output.
6. Select the target transactional database, object storage, job queue, and secret manager in Prompt 2; this audit deliberately does not invent them.
7. Confirm whether Telegram and Bale remain delivery channels and whether forensic watermarking is required for every tenant or per product.

## Validation performed

- Read root instructions/project-logic files for all relevant source trees.
- Compared the clean canonical Dentistry clone with the local WIP Git topology and change counts.
- Enumerated website routes, APIs, JavaScript, stores, scripts, workflows, bot modules, tests, and operational units.
- Traced login, notes/resource access, exam access/submission, payment create/verify/reconcile, and protected delivery at code level.
- Cross-checked deployment/storage/private-reader claims against implementation.
- Searched for DentNote, summaries, question generation, schedule/calendar, upload/download, payment, protected media, and bot linking implementations.
- Did not run live, destructive, payment, upload, deploy, restore, or production tests.

Detailed feature evidence is in `01_FEATURE_INVENTORY.md`; file-level disposition is in `01_REUSE_MAP.md`; assumptions and risks are in `01_HARDCODING_AND_TECH_DEBT.md`; operations are in `01_INFRA_AND_OPERATIONS_INVENTORY.md`.
