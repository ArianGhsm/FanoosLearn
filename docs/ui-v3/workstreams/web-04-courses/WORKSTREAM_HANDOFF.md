# WORKSTREAM HANDOFF — WEB 04 COURSES

## Identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/web-04-courses`
- Owned source path: `apps/platform/public/assets/ui-v3/courses/**`
- Owned docs path: `docs/ui-v3/workstreams/web-04-courses/**`

## Files created

- `apps/platform/public/assets/ui-v3/courses/index.js`
- `apps/platform/public/assets/ui-v3/courses/course-model.js`
- `apps/platform/public/assets/ui-v3/courses/course-view.js`
- `apps/platform/public/assets/ui-v3/courses/courses.css`
- `docs/ui-v3/workstreams/web-04-courses/WORKSTREAM_HANDOFF.md`

## Screens and components delivered

- Courses index with Persian-first page hierarchy, canonical term filter, local presentation search and term-grouped course cards.
- Compact course cards with title-dominant hierarchy, isolated LTR course code, canonical credit/term/section/session facts and one clear open action.
- Bookmarkable course route model based on canonical human `course_code`; raw course UUIDs never appear in visible UI or URLs.
- Sticky course context header and compact horizontally scrollable secondary navigation.
- Course overview with next canonical academic session, latest course resource, first published course assessment, latest published self-grade item and course metadata.
- Dense semester-scale session list with sequence, title, canonical time/status, term/section context and a single “next” temporal marker.
- Cross-workstream integration surfaces for schedule, resources, assessments, grades and course announcements.
- Loading, empty, partial-data, missing-course, workspace unavailable, permission denied and session-expired-safe presentation states.
- Responsive CSS under the `.f3-course-*` namespace only, including visible focus and reduced-motion behavior.

## Current / old implementation inspected and conceptually reused

The V3 implementation was rebuilt from first principles under the Design Lock. The following current behavior was reused only where it represents proven semantics:

- `apps/platform/public/assets/ui-v2/product-core.js`
  - canonical grouping of repeated academic projection rows into course/session presentation objects;
  - course-code lookup and course relation matching semantics;
  - LTR isolation of course codes.
- `apps/platform/public/assets/ui-v2/product-academic.js`
  - course-first navigation concept;
  - term-aware course browsing;
  - course detail sections and session ordering;
  - partial composition rather than collapsing the whole page when a secondary projection fails.
- `apps/platform/public/assets/app.js`
  - canonical workspace-scoped API reads;
  - course UUID used internally only to filter resources/assessments;
  - no client-side grade/scoring authority.
- `apps/platform/src/Core/WorkspacePlatformService.php`
  - authoritative academic, schedule and self-grade projection semantics.
- `apps/platform/src/Content/ContentService.php`
  - canonical resource `course_id` filtering and `sort=newest` behavior.
- `apps/platform/src/Content/ExamService.php`
  - canonical published assessment catalog and `course_id` filtering.
- `docs/ui-v2/web/**`, `docs/ux-parallel/worker01_web_visual_shell.md`, `docs/ux-parallel/worker02_web_domain_presentation.md`
  - safety, Persian presentation, accessibility, timezone and course IA lessons; V2 visual composition was not preserved as authority.

## Canonical course fields consumed

### `/api/v1/workspaces/{workspaceId}/academics`

Directory is accepted as context but not rendered as course truth.

Term projection:
- internal `id` for row association only;
- `term_key` as canonical human route/filter key when available;
- `name`;
- `starts_on` / `ends_on`;
- `status`.

Repeated course/offering/session rows:
- internal `id` (`course_id`) for authorized downstream API filters only;
- `course_code` — human course route key and visible secondary metadata;
- `title`;
- `credit_value`;
- internal `offering_id` for cross-module matching only;
- `section_key`;
- `offering_status`;
- internal `term_id` for term association only;
- `term_name`;
- internal `session_id` for de-duplication only;
- `sequence_no`;
- `session_title`;
- `starts_at` / `ends_at`;
- `session_status`.

The schema at the parallel base makes `academic_courses.course_code` `NOT NULL` and unique on `(workspace_id, course_code)`. This is why the V3 bookmark route uses course code rather than a raw UUID.

### `/api/v1/workspaces/{workspaceId}/resources?course_id=…&sort=newest`

Consumed for overview summary only:
- `title`;
- `type_key`;
- `topic`;
- `current_version_no`;
- `updated_at`.

`course_id`, `term_id`, `session_id` remain internal relation fields. `professor_name` is deliberately **not** promoted to “course instructor”; it describes resource metadata and is not authoritative course-offering instructor truth.

### `/api/v1/workspaces/{workspaceId}/assessments?course_id=…`

Consumed for overview summary only:
- `title`;
- `assessment_kind`;
- `current_version_no`;
- `requires_entitlement` only as canonical metadata if a future integrated component needs it;
- `max_attempts`.

No attempt percentage or progress is synthesized.

### `/api/v1/workspaces/{workspaceId}/grades/me`

Consumed and matched by canonical `course_code`:
- `course_code` / `course_title`;
- `gradebook_title`;
- `item_title`;
- `score`;
- `max_score`;
- `updated_at`.

Only the latest matching published item is summarized. No GPA, average, weighting or completeness policy is inferred.

## Route contract

Merge-time router expectation:

- index: `#/courses`
- detail: `#/courses/:courseCode`
- index query: `term`, `q`
- detail query: `tab=overview|sessions|schedule|resources|assessments|grades|announcements`

`courseCode` is URL-encoded canonical `course_code`, never `course_id`.

The module also accepts the V2-style transitional `query.course` shape from `ctx.state.route` so the merge worker can integrate without a brittle cutover, but the target V3 route is the path-param form above.

`ctx.navigate(href)` is expected to accept the same hash-route hrefs. If the shell worker establishes a structured navigation call instead, adapt only the local navigation helper during merge; do not change course identity semantics.

## V3 module / integration dependencies

`moduleDefinition` is exported by `index.js` with:

- `id: "courses"`;
- route descriptors for `/courses` and `/courses/:courseCode`;
- one `درس‌ها` nav item;
- stylesheet declaration `/assets/ui-v3/courses/courses.css`;
- `mount(ctx)` / `unmount(ctx)`.

Expected shared context:

- `ctx.root`: mount element;
- `ctx.api.get(path, { signal })` or a compatible callable `ctx.api(path, options)`; it must preserve existing session/CSRF/workspace authorization semantics and return canonical response data;
- `ctx.state.workspace.id` (compatible fallbacks are accepted for merge flexibility);
- `ctx.state.workspace.timezoneName` or shared `ctx.format.date/dateTime` for authoritative workspace-local display;
- `ctx.state.route.params.courseCode` and `ctx.state.route.query`;
- `ctx.navigate`;
- `ctx.signal`;
- optional `ctx.ui.mountSlot` / `ctx.ui.slots.mount` for cross-workstream detail content;
- optional `ctx.ui.hasSlot` for course overview announcement insertion;
- `ctx.capabilities.courseSchedule`, `courseResources`, `courseAssessments`, `courseGrades`, `courseAnnouncements` when the shared capability layer chooses to expose them.

The worker does not provide or mutate shared shell state, auth state, workspace selection or backend truth.

## Cross-workstream slots

These slot names are the merge contract for other V3 website workers:

| Slot | Expected owner | Course context passed |
| --- | --- | --- |
| `course.schedule` | web-05 Schedule | internal course id, human code/title, offering IDs, term refs |
| `course.resources` | web-06 Learning Resources | internal course id, human code/title, term refs |
| `course.assessments` | web-07 Progress / Assessments / Grades | internal course id, human code/title |
| `course.grades` | web-07 Progress / Assessments / Grades | internal course id, human code/title |
| `course.announcements` | web-08 Communication | only when a canonical course relation exists |
| `course.overview.announcement` | web-08 Communication | optional overview-only latest course announcement |

If a slot is not integrated, the course shell renders a truthful fallback and links to the corresponding global destination; it does not duplicate another worker’s full domain UI.

## INTEGRATION_GAPS

1. **Course-scoped announcements:** current public announcement rows have no trustworthy `course_id` / `offering_id` relation. `courseAnnouncements` therefore defaults off, and both announcement surfaces remain hidden unless integration provides a canonical binding.
2. **Instructor metadata:** the audited academic projection does not expose canonical course/offering instructor fields. Resource `professor_name` is not equivalent. Instructor is omitted unless an additive canonical academic field later arrives.
3. **Authoritative grade summary:** published self-grade rows do not define GPA, weighting, term completeness or authoritative course average. V3 shows an individual published item only.
4. **Assessment progress:** current catalog does not expose a user attempt/progress summary suitable for a course overview percentage. V3 shows available assessment metadata only and never fabricates progress.
5. **Shared V3 foundation physically arrives later:** this branch cannot wire itself into shared entrypoints by ownership rule. Merge worker must register `moduleDefinition`, load `courses.css`, provide route state/API adapter and reconcile the exact `ctx` shape with the foundation branch.
6. **Cross-workstream tab bodies:** full schedule/resources/progress/announcement experiences belong to their dedicated workers and must be mounted into the documented slots during integration.
7. **Workspace-timezone formatter:** the module refuses to infer a device/server timezone when no authoritative workspace timezone/formatter is supplied. Merge worker must preserve the canonical workspace timezone in `ctx.state` or `ctx.format`.

## Source assumptions for merge verification

- `course_code` remains non-null and workspace-unique as defined at the parallel base.
- Public browser API remains the source for Website reads; internal HMAC service endpoints are not used by this module.
- Course UUID and offering/session UUIDs stay presentation-internal and are never exposed as user labels or bookmark keys.
- Backend remains authority for workspace isolation, `academic.view`, resource/exam/grade permissions, scoring and publication state.
- Academic navigation and schedule projections exclude courses, offerings, terms and sessions whose canonical status is `archived`, even when legacy data has a missing `archived_at`; course availability remains workspace-scoped.
- The V3 foundation keeps the Design Lock token names or compatible CSS variables. This module supplies Design Lock fallback values but defines no global/raw-element styles.
- The shell must avoid rendering a second H1 around the mounted Course destination, or adapt the module page heading during integration so each route retains one semantic H1.
