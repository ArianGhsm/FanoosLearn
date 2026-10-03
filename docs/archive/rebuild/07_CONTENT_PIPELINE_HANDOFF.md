# FANOOS Website Stage 7 — Content Pipeline Handoff

## Status

Stage 7 is implemented locally on the existing canonical FANOOS content engine. No deployment, production migration, service restart, live Telegram/Bale message or production-data mutation was performed.

The implementation reuses `ContentService`, `ContentImportService`, `ContentUploadService`, the existing object-storage boundary and secure delivery services. No second resource database or client-side storage path was introduced.

## Resource model and access

Resources remain workspace-owned and data-driven. The active resource types include lecture notes, structured/discipline notes (DentNote-style through `format_key`), summaries, question banks, past exams, flashcards, audio/transcript and other registered types. Course/term/session metadata is validated against the same workspace before a binding is written.

The library remains searchable and filterable by query, course, type, lifecycle status and recency. Students receive published resources only; producers/reviewers receive the scoped management projection. The projection contains opaque resource/version identifiers and safe status metadata, never `storage_key` (storage keys), filesystem paths or object capabilities.

## Version and review workflow

The canonical state machine is:

`draft → review → approved/rejected → published`

Each structured or uploaded version is immutable. Review requires `resource.review`, publication requires `resource.publish`, and the creator cannot approve their own version. Publishing records an outbox event and audit entry; the previous published version remains the safe current version until the new approved version is explicitly published. The new versions endpoint returns bounded metadata and a short content preview only to scoped producers/reviewers.

## Production pipeline

The existing derivation boundary records source resource/version, transformation key, producer and generated output version. It supports the reusable path:

`source → transcript/structured source → full note → summary/questions → review → publish`

`ContentImportService` keeps manifest imports idempotent by source/import key and checksum. `ContentUploadService` continues to inspect filename/MIME/size/checksum before private object persistence. Browser management creates safe structured drafts; pipeline workers and import tools remain the owners of automated transcription/derivation rather than duplicating processing logic in the UI.

## Producer/reviewer web surface

The management destination now exposes a capability-gated content workspace:

- producers with `resource.create` can create a structured draft, attach an active course and send draft/rejected versions to review;
- reviewers with `resource.review` can inspect the bounded version history and approve or return a version for correction;
- publishers with `resource.publish` can publish only an approved version;
- the queue shows truthful empty/error/loading states, Persian labels, responsive actions and no client-side authorization inference.

The server `adminDashboard` projection now returns explicit permission booleans for the management surface. These booleans are presentation hints only; every mutation still rechecks scoped RBAC in the backend.

## Validation and remaining gaps

Validation covers tenant/resource isolation, search and course/type/status projection, immutable version transitions, self-review denial, import idempotency, upload inspection, active-content/file-name safety, protected delivery and UI DOM safety. Existing content-engine and secure-delivery integration tests remain the source of truth for persistence and capability behavior.

The browser does not claim a live transcription worker, arbitrary file upload widget, publication-to-other-workspace UI, export/history analytics or generated-answer correctness. Those require a canonical worker/contract decision and remain next-stage work. The derivation API is intentionally bounded to an approved source version and creates a draft; it does not silently publish generated content.

## Files and next handoff

Backend: `ContentService`, `ApiKernel`, `WorkspacePlatformService` and the core OpenAPI contract. Web: V3 operations management queue/composer, responsive operations styles and existing learning library/detail surfaces. Tests should continue to run the content-engine, tenant-isolation, protected-delivery and V3 static/DOM suites before integration.

Next stage can add worker-backed transcription/rendering and richer version review only after those contracts expose idempotency keys, bounded capabilities and durable status projections.
