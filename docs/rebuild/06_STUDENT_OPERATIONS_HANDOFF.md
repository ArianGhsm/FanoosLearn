# FANOOS Website Stage 6 — Student Operations Handoff

## Status

Stage 6 is implemented locally on the existing FANOOS V3 foundation. No deployment, production migration, service restart, live Telegram/Bale message or production-data mutation was performed.

The work reuses the existing schedule, progress and operations modules instead of adding a parallel student-operations stack.

## Schedule and academic calendar

The V3 Schedule destination provides Today, Week and Upcoming routes, date navigation, course/type filters, event detail and responsive timeline/week/card presentation. Canonical event types (`class`, `exam`, `deadline`, `event`, `other`) remain server-projected; unknown values are not invented in the UI.

The workspace `timezone_name` is the only time authority. Both the normal schedule projection and the fallback `WorkspacePlatformService` path parse local date boundaries in that IANA timezone, convert query bounds to UTC storage time, and reject invalid dates/ranges. Browser/host timezone is never used as academic truth. Events linked to archived offerings, terms or courses are excluded while workspace-wide events remain available.

## Exams, grades and scoring

Published assessment catalog and attempt reads now hide archived/inactive academic relations and expose canonical course/term metadata when it exists. Review, revision, entitlement and server scoring semantics remain in `ExamService`; the browser never receives answer keys before review and never calculates a score.

The self-grade projection includes explicit term and offering identifiers/names, item and maximum score, published result state and update time. The UI groups by the returned term/course/item facts only. No GPA, weighted average, completeness policy or final-course grade is fabricated.

## Announcements

Workspace announcement reads remain recipient- and workspace-scoped. A publisher may optionally provide a `course_id`; the backend validates that it belongs to the active workspace course and stores only that relation in the canonical `data_json` metadata. Reads join the relation back to an active course in the same workspace and support `GET /announcements?course_id=…`.

The course detail announcement slot consumes that public projection, shows a truthful empty state when no course-scoped message exists and preserves the existing mark-as-read action. Older workspace-wide announcements do not appear in a course slot. Draft/edit/delete lifecycle and a manager course picker are not invented because no canonical contract currently exists for them.

## Forms

The open-form projection now includes `submission_status` and `submitted_at` for the authenticated member through a workspace/user-scoped aggregate. The student UI displays deadline/multiplicity and marks one-response forms as completed after canonical submission; multi-response forms remain actionable. Schema, required answers, supported field types, workspace isolation and idempotency remain server-validated.

There is still no authorized public submission-history/export/analytics endpoint, so manager export controls and fabricated completion history remain out of scope.

## Validation

`tests/ux-v3/web_student_operations_contract_test.js` covers timezone/date-boundary behavior, lifecycle filters, cross-tenant query shape, server-owned grade math, course-scoped announcements, form submission state/security and safe DOM rendering. Existing V3 shell, course, schoolhouse, UX safety and static guards remain regression checks.

## Files and next handoff

Backend changes are in `WorkspacePlatformService`, `BotReadProjectionService`, `ScheduleProjectionService`, `ScheduleWindowResolver`, `ExamService` and `ApiKernel`. Website integration changes are in the V3 schedule/progress/operations modules and bootstrap course-slot registry. The next stage can build on these public projections for resource/content workflow and search without introducing a second timezone, grade or notification authority.

Remaining gaps: academic schedule/grade mutation endpoints and form export/history are not exposed publicly; announcement lifecycle beyond immediate publish is absent; and management UI must wait for canonical action-level capability projections where needed.
