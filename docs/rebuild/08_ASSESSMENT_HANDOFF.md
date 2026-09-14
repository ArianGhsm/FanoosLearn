# FANOOS Stage 8 — Assessment Handoff

## Scope completed

Stage 8 completes the assessment/study-practice path on the existing FANOOS content and academic contracts:

- `ExamService` keeps the assessment definition canonical and validates authored questions with prompt, choices, answer, explanation, optional topic, tags, difficulty (`easy|medium|hard`) and bounded question provenance (`source_question_id`, `source_locator`, `note`). Supported assessment kinds are practice, quiz, mock exam and past exam.
- Assessment-level `source_resource_id` remains the durable link to the published question-bank or other source resource; `course_id` remains the academic binding.
- Student attempts receive a safe question projection. Correct answers never leave the server before submission. The server normalizes answers, advances a revision, locks submission and computes the score.
- Starting an assessment is resumable for the same published version: an existing open attempt is returned instead of creating a duplicate. The catalog now projects the current user’s open attempt id/revision/status and consumed attempt count.
- The student assessment surface presents published practice/quiz/mock/past-exam assessments, course filters, a searchable question-bank shelf and a structured past-exam shelf. Empty and partial-resource states remain explicit.
- Result review is sourced from the canonical scored result; the browser does not calculate a score.

The repository audit found no standalone multi-tenant question-generation adapter to wire in safely. Stage 8 therefore reuses the existing `ContentService::deriveResource`/version-review pipeline for question-bank provenance rather than introducing a second generator or runtime dependency on legacy projects.

## Flashcard decision

The content registry already accepts a `flashcards` resource type, but the repository does not contain a complete, server-authoritative flashcard study interaction (card ordering, progress, review state and persistence). Stage 8 therefore does **not** add a fake flashcard screen. Published flashcard resources remain discoverable through the resource library and are explicitly deferred until a complete contract is designed.

## Changed areas

- `apps/platform/src/Content/ExamService.php`
  - question metadata normalization and validation;
  - hidden-answer safe projection;
  - resumable/idempotent open attempts;
  - catalog projection for source resource and current-user attempt state.
- `apps/platform/public/assets/ui-v3/progress/module.js`
  - past-exam resource loading, course/search filtering, resource navigation and resume answer hydration.
- `apps/platform/public/assets/ui-v3/progress/progress-ui.js`
  - searchable study controls, separate question-bank/past-exam shelves and truthful resume/server-authority states.
- `contracts/openapi/core-v1.yaml`
  - published-assessment `course_id` and supported-kind query filters.
- `database/migrations/0012_stage8_assessment_variants.sql`
  - additive `assessment_variant` column/index so quiz is first-class without rewriting the established CHECK constraint; not applied to production in this stage.
- `tests/Integration/ContentEngineTest.php`
  - question metadata, hidden-answer, resume/idempotency and catalog-state assertions.
- `tests/ux-v3/web_student_operations_contract_test.js`
  - static contract checks for filters, past exams, resume, hidden answers and server scoring.

## Follow-up: single-question pacing closes the bulk-export hole

Superseding the "student attempts receive a safe question projection" line
above: `startAttempt` (fresh or resumed) and `attemptReview` no longer return
question/review content in bulk. `ExamService::readQuestion()` and
`attemptReviewQuestion()` serve one question/explanation at a time, by
position, paced by a per-user token bucket (`ExamQuestionRateGuard`,
`database/migrations/0018_exam_question_read_pacing.sql`) and audited via
`AuditLogger`. Question and choice order are permuted per attempt
(`ExamAttemptShuffle`, seeded from the attempt's own UUIDv7 id) so a leaked
set of reads carries the specific permutation of the attempt it came from;
scoring still uses stable question ids and the canonical (authored) choice
index, so this does not change what counts as correct. See
`contracts/REGISTRY.md`'s "Exam question-bank bulk-export protection" entry
for the full contract and the known course-scope entitlement gap it flags.
This is a breaking core-v1 change (bumped 1.2.0 -> 1.3.0); the web UI has not
been updated to the new per-question shape yet (`apps/platform/public/assets/ui-v3/progress/`
already degrades to its existing "no question received" / "review not
available" empty states rather than crashing, so nothing needs to change
there for correctness, only for the exam-taking experience to work again).

## Validation and follow-up

Run the existing local integration and UX checks before deployment. The repository’s PHP integration runner may still report the workstation’s missing `finfo` extension; that is an environment prerequisite, not a Stage 8 code path. No production migration, service restart or deployment was performed for this stage. The next stage should add a dedicated flashcard contract only when server-owned review persistence is available, and should preserve the current attempt revision/idempotency semantics.
