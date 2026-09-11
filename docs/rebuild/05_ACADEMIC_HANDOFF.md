# FANOOS Website Stage 5 — Academic Core Handoff

## Status

Stage 5 is implemented locally on the existing FANOOS V3 foundation. No deployment, server change, migration execution, live messaging or production data mutation was performed.

The implementation reuses the existing canonical academic schema and the V3 course-first module. It does not create a second academic store or duplicate the legacy project runtime.

## Canonical academic surface

The database migration already defines the workspace-owned academic entities:

- `academic_terms`
- `academic_courses`
- `academic_course_offerings`
- `academic_course_sessions`
- `academic_enrollments`

The browser reads the canonical projection at:

`GET /api/v1/workspaces/{workspaceId}/academics`

`WorkspacePlatformService::academicNavigation()` remains the source for the Web academic directory, terms, courses, offerings and sessions. Bot and schedule read projections use the same workspace and lifecycle boundaries.

## Projection hygiene completed

Academic rows are filtered by both their lifecycle status and archive timestamp:

- terms require `status <> 'archived'` and `archived_at IS NULL`;
- courses require `status = 'active'` and `archived_at IS NULL`;
- offerings and sessions require non-archived status and a null archive timestamp;
- schedule joins keep workspace-wide events (`offering_id IS NULL`) but exclude events linked to archived offerings or inactive/archived courses.

This closes the legacy-data edge case where a row had an archived status but no `archived_at`. Every query remains workspace-scoped; no cross-tenant fallback is introduced.

## Website route and presentation contract

The existing `ui-v3/courses` module provides:

- `#/courses` with canonical term filter and local search (`term`, `q`);
- `#/courses/:courseCode` using the canonical human `course_code`, never a raw UUID;
- detail tabs for `overview`, `sessions`, `schedule`, `resources`, `assessments`, `grades` and `announcements`;
- course-first cards with canonical title/code/credit/term/section/session facts;
- stable Persian-safe rendering for null, long and bidirectional metadata;
- workspace-timezone-aware date presentation when the shared formatter/context provides it;
- cross-workstream slots for schedule, resources, assessments, grades and announcements.

The module uses `textContent`-based DOM construction and treats internal course/offering/session identifiers as relation keys only. It does not display or bookmark UUIDs.

## Deliberate non-additions and gaps

No new academic CRUD UI or endpoint was invented. Although the permission registry contains `academic.manage`, the current public canonical API does not expose audited create/update/archive operations for terms, courses, offerings or sessions. A future backend contract must define those operations, authorization, validation and audit semantics before a representative/admin action is added.

Course-scoped announcements remain unavailable because the current notification projection has no trustworthy `course_id` or `offering_id` binding. The UI keeps that capability off instead of guessing from message text. Instructor fields, authoritative GPA/average and assessment progress are likewise not fabricated from unrelated metadata.

## Validation and next handoff

The Stage 5 contract test checks the schema/query lifecycle boundaries, tenant-scoped academic endpoint, route/tab contract, human course routing, safe DOM rendering and documented gaps. Existing V3 shell/course, schoolhouse and UX safety contracts remain the regression baseline.

The next integration step is to mount the dedicated schedule/resources/assessment/communication modules into the documented course slots. If academic management is required, add a backend-owned contract first, then expose only actions backed by that contract.
