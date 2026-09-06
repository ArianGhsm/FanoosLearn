# FANOOS content engine

Status: implemented locally for Prompt 6

Date: 2026-09-07 (Asia/Tehran)

## Outcome

FANOOS now has one tenant-aware engine for lecture notes, discipline formats, summaries, cheat sheets, flashcards, question banks, past exams, audio and other resources. Resource type and format are database values; no faculty, discipline, university, course, cohort or year selects an application branch.

The implementation is local and committed to the FANOOS repository only. It does not call a legacy runtime or database, and no production/cPanel host was changed. The cPanel URL supplied during this stage remains candidate infrastructure evidence, not deployment authorization.

## Common resource model

Migration `0007_content_engine.sql` extends the Prompt 3/5 resource aggregate without creating parallel stores:

- `content_resources` remains the aggregate and publication pointer;
- `content_resource_versions` remains the immutable structured/uploaded version record;
- `content_resource_metadata` attaches optional term, course, session, topic and professor metadata plus data-driven `format_key` and access level;
- `content_version_reviews` records independent approval/rejection evidence;
- `content_derivations` records source-version to product-version lineage;
- `content_access_policies` remains the entitlement and TTL policy;
- `content_resource_bindings` remains the academic placement;
- `content_objects` and Prompt 4 filesystem storage remain the byte authority.

The generic types seeded by Prompt 3 and 6 are:

| Type key | Intended product |
| --- | --- |
| `lecture_note` | Lecture/session note |
| `discipline_note` | Discipline-specific structured format |
| `summary` | Summary |
| `cheat_sheet` | Compact revision sheet |
| `flashcards` | Flashcard collection |
| `question_bank` | Question source/resource |
| `past_exam` | Historical/sample exam resource |
| `audio` | Audio/source material |
| `other` | Explicit fallback type |

DentNote is represented by `type_key=discipline_note` and a tenant-controlled `format_key` such as `dentnote`. This preserves the product format without putting Dentistry conditions into the service, routes, schema or storage paths.

## Production workflow

```text
source material
   -> authored/uploaded/imported resource version
   -> optional derived structured note
   -> summary / question bank / other products
   -> review request
   -> independent reviewer decision
   -> explicit publisher action
   -> protected view/delivery
```

`ContentService` implements draft creation, version addition, metadata updates, derivation lineage, review and publication. A creator cannot approve their own version. A rejected version returns to draft without taking an already-published version offline. Publication changes the current version only after durable approval evidence exists.

`ContentPayload` canonicalizes JSON before hashing, rejects empty/oversized payloads and blocks active script/PHP signatures. Branding/rendering is not stored in business content; a future renderer can consume the structured payload without changing lifecycle or entitlement logic.

No external AI credential was requested. The pipeline accepts generated output as a version with explicit `source_kind=generated`, producer and transformation lineage, but it never auto-publishes it.

## Upload and object storage

`ContentUploadService` adapts the Prompt 4 primitives:

1. scoped `resource.create` is required;
2. protected objects additionally require `resource.manage_protected`;
3. `UploadInspector` verifies size, MIME, signature and checksum;
4. `FilesystemObjectStore` writes an immutable tenant/object/version address outside the public tree;
5. object metadata is marked verified only with its byte count and checksum;
6. an immutable draft resource version references that verified object.

The file is written before the database transaction. If the database operation fails, the unreferenced private object is not addressable; an operations garbage-collection job should later remove old unreferenced objects after a retention delay.

## Resource library and API

The responsive RTL shell now exposes “کتابخانه منابع” and “تمرین و آزمون”. The library endpoint supports tenant-scoped query, type, course and lifecycle filters and `newest`, `oldest` or `title` sorting. Students see published records only; managers can inspect the workflow backlog. Content bytes/JSON still require the authoritative resource decision.

Primary web/bot contracts in `contracts/openapi/core-v1.yaml` include:

- `GET/POST /api/v1/workspaces/{workspaceId}/resources`
- `GET /api/v1/workspaces/{workspaceId}/resources/{resourceId}`
- version review-request, review and publish routes
- protected delivery issue/consume routes
- assessment catalog/start/progress/submit/review routes

## Exams and quizzes

`ExamService` adapts the existing assessment/version/attempt tables instead of introducing a browser-only exam store.

- assessment kinds are `practice`, `mock_exam` and `past_exam`;
- definitions are immutable canonical JSON with stable question IDs;
- manager and reviewer are separate scoped permissions (`exam.manage`, `exam.review`);
- questions hydrate only after RBAC and optional entitlement checks;
- correct answers and explanations are removed from the start response;
- progress writes require the current optimistic revision;
- submission scores against the immutable server version inside one transaction;
- results and per-question review are durable and owner-scoped;
- an attempt in one workspace cannot be read or submitted through another workspace.

## Bulk import

The importer consumes an operator-provided JSON manifest; it never connects to or writes into a legacy application.

```powershell
php scripts/import/content-manifest.php <source-key> <import-key> <actor-user-uuid> <workspace-uuid> <manifest.json>
```

The source must already exist in `migration_source_systems` with `mode=read_only`. Source item identifiers are stored only as HMAC digests. The importer provides:

- exact manifest replay by import key;
- stable source-to-resource mapping through `LegacyIdMap`;
- duplicate detection across different batches;
- a new immutable version when source content changes;
- per-batch and per-item counts/outcomes;
- atomic rollback if any item fails validation;
- audit events without raw source identifiers or payloads.

The example manifest is `scripts/import/content-manifest.example.json`. Real legacy content must not be imported until ownership/licensing, source snapshot and mapping approval are documented.

## Configuration

Secure delivery requires two independent secrets outside Git:

- `FANOOS_DELIVERY_SIGNING_KEY`
- `FANOOS_DOWNLOAD_SIGNING_KEY`

Each must contain at least 32 bytes. If they are absent, only delivery endpoints return a fail-closed `503`; other platform modules remain available.

## Validation coverage

`ContentEngineTest` covers:

- manager create and reviewer approval;
- self-review denial;
- authorized view and outsider denial;
- two tenants with the same course/resource names;
- immutable version update and current-version switch;
- discipline format/DentNote as data;
- summary, question bank and past exam resources;
- library search/filter/sort;
- inspected PDF upload to private object storage;
- paid entitlement plus protected delivery;
- issuance watermark/tracing output and access log;
- bot forward-protection contract;
- question hydration, progress, stale-revision denial, submission and server score;
- cross-tenant assessment denial;
- initial import, exact replay, duplicate detection and changed-source versioning.

The integration suite runs against MySQL 8.4 in CI. Local static validation does not claim a production/staging rehearsal.

## Prompt 7 handoff

Prompt 7 should consume the canonical account/session and workspace APIs. A bot must never copy payment, entitlement or resource state into its own authority. It should request an issuance, consume it immediately before sending, honor `forward_protection_required`, and retain only channel delivery/cache metadata. The detailed contract is in `06_SECURE_DELIVERY_CONTRACT.md`.
