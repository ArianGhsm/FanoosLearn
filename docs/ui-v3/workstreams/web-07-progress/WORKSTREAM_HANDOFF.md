# WORKSTREAM_HANDOFF — WEB 07 Progress / Assessments / Grades

## Workstream identity

- Design Lock ID: `FANOOS-UX-2026.09-R1`
- `PARALLEL_REBUILD_BASE_SHA`: `9e72ef32b331ad41626229c28ed24dcc43a49292`
- Branch: `rebuild-v3/web-07-progress`
- Owned source path: `apps/platform/public/assets/ui-v3/progress/**`
- Owned handoff path: `docs/ui-v3/workstreams/web-07-progress/**`

## Files created

- `apps/platform/public/assets/ui-v3/progress/module.js`
- `apps/platform/public/assets/ui-v3/progress/progress-ui.js`
- `apps/platform/public/assets/ui-v3/progress/progress.css`
- `docs/ui-v3/workstreams/web-07-progress/WORKSTREAM_HANDOFF.md`

## Screens and components delivered

### Assessments and question-bank discovery

- V3 assessments destination with Persian-first page hierarchy.
- Canonical assessment-kind filters for `practice`, `mock_exam`, and `past_exam`.
- Canonical course filter using `/academics` only for human course presentation and internal filter matching.
- Assessment list rows with title, type, course when resolvable, canonical max-attempt ceiling, entitlement requirement and one primary follow-up action.
- Assessment detail with safe metadata, optional future canonical instructions/deadline fields, server-authority explanation and canonical start action.
- Question-bank discovery shelf backed by the canonical resource library filtered with `type=question_bank`; resource details remain owned by the Learning/Resources workstream.
- Partial-failure behavior so a resource/question-bank failure does not make the canonical assessment catalog appear unavailable.

### Attempt shell

- Question-by-question attempt layout with desktop question navigator and mobile horizontal navigation.
- Answer selection kept as presentation state only; correct answers are never present before canonical review.
- Revision-aware temporary save wired to the canonical attempt PATCH action.
- Save pending/success/error/revision-conflict states.
- Deliberate final-submit confirmation showing answered/total question count before submission.
- Canonical final submit followed by canonical review retrieval.
- Result presentation based only on server `score_basis_points`, `correct_count`, `question_count` and review output.
- Correct choice/explanation rendering occurs only after the canonical review endpoint returns those values.
- No browser scoring logic.

### Grades

- V3 grades destination using dense data groups/tables rather than large statistic cards.
- Course grouping based on canonical self-grade rows.
- Optional term grouping only when a grade row itself carries an explicit canonical term presentation field; no join-based term inference is used for current rows.
- Published grade rows with item/gradebook title, score, max score and update date when format context can present it.
- Mobile conversion from desktop table structure to stacked no-horizontal-scroll rows.
- Human-readable missing-score/max/date states.
- Explicit no-GPA/no-average summary copy.
- Exported `renderCourseGradesSlot(ctx, { root, courseCode, courseTitle })` for the Course Detail workstream (`web-04`) to consume without duplicating grade logic.

### Shared domain states

- Loading skeletons.
- Empty states.
- Safe read errors with retry only for safe reads.
- Permission state.
- Session-expired state.
- Partial projection failure state.
- Mutation pending, success, failure and revision-conflict presentation.
- Reduced-motion and visible-focus behavior.

## Current/legacy source inspected and conceptually reused

Current FANOOS source was treated as semantic/security reference rather than presentation authority:

- `apps/platform/public/assets/ui-v2/product-learning.js`
  - reused canonical assessment start/save/submit/review semantics;
  - reused published-grade-only semantics;
  - preserved the rule that answer keys are absent before submission;
  - replaced the V2 all-questions-at-once attempt presentation with a question-navigation shell and deliberate final-submit confirmation.
- `apps/platform/public/assets/app.js`
  - reused browser endpoint semantics, revision handling and server-result flow;
  - did not copy the shared V2 entrypoint or mutate it.
- `apps/platform/src/Content/ExamService.php`
  - authoritative source for catalog shape, assessment kinds, entitlement/max-attempt rules, safe questions, revision concurrency, server scoring and post-submit review.
- `apps/platform/src/Core/WorkspacePlatformService.php`
  - authoritative source for current self-grade row shape and published-only filtering.
- `apps/platform/src/Http/ApiKernel.php`
  - verified exact public browser routes/query keys for resources, assessments and attempts.
- `docs/ui-v2/web/**`, `docs/ui-v2/FANOOS_PRODUCT_IA.md`, `docs/ui-v2/FANOOS_PRESENTATION_CONTRACT.md`, `docs/ui-v2/FANOOS_CROSS_CHANNEL_MATRIX.md`
  - reused safe Persian vocabulary, course-centric behavior, error principles and authority boundaries.
- `docs/ux-parallel/worker01_web_visual_shell.md`, `worker02_web_domain_presentation.md`, and `UX_INTEGRATION_REPORT.md`
  - reused safe-DOM, local/system-font, Persian-number/date, focus, responsive and non-raw-error principles.
- `contracts/REGISTRY.md`, `contracts/openapi/core-v1.yaml`, `contracts/openapi/internal-v1.yaml`
  - public Core API remains the only browser contract consumed; internal service API is not consumed by this module.

No legacy repository state, cohort hardcode, secret, storage/runtime state or legacy architecture authority was copied.

## Canonical endpoints / projections consumed

### Assessments

- `GET /api/v1/workspaces/{workspaceId}/assessments`
  - current catalog fields used: `id`, `title`, `current_version_no`, `assessment_kind`, `course_id`, `requires_entitlement`, `max_attempts`.
  - supported canonical kinds currently: `practice`, `mock_exam`, `past_exam`.
- `POST /api/v1/workspaces/{workspaceId}/assessments/{assessmentId}/attempts`
  - starts a server-owned attempt and returns safe questions without answer keys.
- `PATCH /api/v1/workspaces/{workspaceId}/attempts/{attemptId}`
  - saves `{revision, answers}` and advances canonical revision.
- `POST /api/v1/workspaces/{workspaceId}/attempts/{attemptId}/submit`
  - canonical server scoring.
- `GET /api/v1/workspaces/{workspaceId}/attempts/{attemptId}/review`
  - post-submit review only.

The assessment analytics endpoint is intentionally not consumed because it is manager-authorized (`exam.manage`) and is not student progress authority.

### Question bank / course labels

- `GET /api/v1/workspaces/{workspaceId}/resources?type=question_bank&sort=newest`
  - canonical question-bank resource discovery only; resource detail/access stays with the Resources workstream.
- `GET /api/v1/workspaces/{workspaceId}/academics`
  - course titles/codes and internal course relation for assessment/resource presentation filtering.

### Grades

- `GET /api/v1/workspaces/{workspaceId}/grades/me`
  - current fields: `course_code`, `course_title`, `gradebook_title`, `item_key`, `item_title`, `max_score`, `score`, `updated_at`.
  - backend already filters to published gradebooks/results and active/completed enrollment relation.

## Integration imports / dependencies

### Foundation / Shell

Merge worker must load `progress.css` with this module and register `moduleDefinition` through the V3 module registry defined by the Design Lock.

The module expects the Design-Lock `ctx` contract:

- `ctx.root`
- `ctx.api`
- `ctx.state` with canonical selected workspace context
- `ctx.navigate` when cross-destination navigation is available
- `ctx.format` for canonical Persian/workspace-timezone presentation
- `ctx.capabilities`
- `ctx.signal`

`ctx.api` is adapted for a callable client, `.request()`, or verb methods, but the merge worker must normalize this to the Foundation implementation rather than adding a second API authority.

### `web-04` Courses

- Import `renderCourseGradesSlot` for the Course Detail grade slot.
- Course code is the current human/canonical matching key available in self-grade rows.
- Do not add a term selector to the course grade slot unless the grade projection itself gains an unambiguous offering/term relation.

### `web-06` Learning / Resources

- Question-bank discovery is read-only in this workstream.
- The resource destination remains `/resources` conceptually; the merge worker should align the navigation target with the final registered web-06 route and preserve `type=question_bank` plus optional human course-code filter semantics.
- Resource detail/protected delivery must remain owned by web-06 and canonical resource APIs.

## INTEGRATION_GAPS

### GAP-PROGRESS-01 — No student attempt resume/list projection

Current public API can create a new attempt, save it, submit it and review it by known attempt ID, but there is no public user-scoped endpoint to list attempts or retrieve an existing in-progress attempt with its safe questions/current answers/revision.

Consequences:

- V3 can maintain an attempt while the current mounted interaction owns the canonical attempt ID/revision.
- A reload/new session cannot safely discover and resume that attempt.
- The UI must not claim reliable `resume` across reloads until a canonical projection exists.
- Revision conflict is fail-safe; this workstream does not invent local recovery authority.

Needed projection: user/workspace/assessment-scoped current-attempt read with attempt ID, revision, status, safe questions, saved answers and allowed actions.

### GAP-PROGRESS-02 — No student attempt history / completed-results list

The assessment catalog does not include per-user attempt count, remaining attempts, last result or result reference; there is no public user-scoped attempt history endpoint.

Consequences:

- `max_attempts` is shown only as a ceiling, never as remaining attempts.
- No fabricated “تلاش ۱ از ۳”, completed tab count, recent result list or best score is produced.
- A completed/results landing section should only activate after an authoritative user result projection exists.

### GAP-PROGRESS-03 — Assessment timing/status/instructions are absent from current catalog

Current `ExamService::catalog()` returns published items but does not return open/close/deadline fields, explicit active/upcoming state, instructions, duration, question count or timing policy.

Consequences:

- Current source presents these rows as published/available rather than guessing active/upcoming/deadline.
- Optional presentation hooks only become meaningful when canonical fields are added.
- No countdown/timer/ETA is fabricated.

Needed projection: presentation-safe assessment availability metadata with workspace-timezone semantics and explicit allowed actions.

### GAP-PROGRESS-04 — Grade term/completeness/weighting authority is incomplete

Current `/grades/me` rows do not include `term_id`, `term_name`, offering identity, item weight, completeness, finalization policy, GPA or an authoritative aggregate.

The separate `/academics` projection has terms/offerings, but joining grade rows to it by title/code would be ambiguous across repeated offerings and is therefore not treated as academic truth.

Consequences:

- Current grades group reliably by course only.
- Term grouping is feature-gated to a future explicit term field on the grade row/projection.
- No GPA, weighted average, term average or final-course grade is calculated in the browser.

Needed projection: unambiguous term/offering relation plus server-declared completeness/weighting or server-calculated summaries if those summaries are product requirements.

### GAP-PROGRESS-05 — Course-grade slot cannot disambiguate repeated course offerings

`renderCourseGradesSlot` can match the current self-grade projection by `course_code`. If the same course code is present across multiple offerings/terms, current grade rows provide no offering/term key to scope it further.

Merge worker must preserve this caveat and must not infer offering identity from display text.

### GAP-PROGRESS-06 — Final cross-workstream route registration

This parallel worker cannot edit shared entrypoints or the Foundation registry. The merge worker must register `/assessments` and `/grades`, load `progress.css`, connect web-06 resource navigation and place the web-04 course-grade slot.

## Source assumptions the merge worker must verify

1. The Foundation supplies the exact Design-Lock module `ctx` contract and does not require this domain module to own session, CSRF, workspace selection or router authority.
2. Browser mutations continue through the existing canonical Core API/CSRF layer.
3. `ctx.format` formats backend dates using the canonical workspace timezone; browser-local timezone must not become academic time authority.
4. The shared shell provides the page/root spacing around the module; this stylesheet only owns `.f3-progress-*` classes and does not redefine global Foundation primitives.
5. `web-06` keeps `question_bank` as a canonical resource type and owns resource detail/access.
6. `web-04` uses human course code for its current route and imports the course-grade slot rather than cloning grade projection logic.
7. If the Core API gains richer assessment/grade projections before merge, integrate those explicit fields and remove the corresponding gap rather than deriving equivalent truth client-side.
8. Correct answers/explanations remain absent from start/save payloads and visible only from post-submit canonical review.

## Ownership finish gate

Source diff was reviewed against the parallel base before handoff creation. All source changes are confined to `apps/platform/public/assets/ui-v3/progress/**`; this handoff is confined to `docs/ui-v3/workstreams/web-07-progress/**`. No shared entrypoint, UI V2 path, backend, schema, workflow, bot, ops or deployment file is modified by this workstream.
