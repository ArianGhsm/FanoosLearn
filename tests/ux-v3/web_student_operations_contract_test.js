const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

const schema = [
  read('database/migrations/0004_domain_foundations.sql'),
  read('database/migrations/0006_core_platform.sql'),
  read('database/migrations/0007_content_engine.sql'),
].join('\n');
const api = read('apps/platform/src/Http/ApiKernel.php');
const platform = read('apps/platform/src/Core/WorkspacePlatformService.php');
const bot = read('apps/platform/src/Core/BotReadProjectionService.php');
const schedule = read('apps/platform/src/Core/ScheduleProjectionService.php');
const windowResolver = read('apps/platform/src/Core/ScheduleWindowResolver.php');
const exam = read('apps/platform/src/Content/ExamService.php');
const scheduleUi = read('apps/platform/public/assets/ui-v3/schedule/index.js');
const scheduleModelSource = read('apps/platform/public/assets/ui-v3/schedule/schedule-model.js');
const progress = read('apps/platform/public/assets/ui-v3/progress/module.js');
const progressUi = read('apps/platform/public/assets/ui-v3/progress/progress-ui.js');
const operations = read('apps/platform/public/assets/ui-v3/operations/operations.js');
const bootstrap = read('apps/platform/public/assets/ui-v3/app/bootstrap.js');
const openApi = read('contracts/openapi/core-v1.yaml');
const handoff = read('docs/rebuild/06_STUDENT_OPERATIONS_HANDOFF.md');

for (const table of ['schedule_events', 'grade_gradebooks', 'grade_items', 'grade_results', 'notification_messages', 'form_definitions', 'form_submissions']) {
  assert.ok(schema.includes(table), `student operations schema missing ${table}`);
}

// Schedule boundaries are resolved from the workspace IANA timezone, never host/browser time.
assert.ok(windowResolver.includes('timezone_name'), 'schedule window must load workspace timezone');
assert.ok(windowResolver.includes("createFromFormat('!Y-m-d', $fromDate, $timezone)"), 'schedule start must be parsed in workspace timezone');
assert.ok(windowResolver.includes("setTimezone($utc)"), 'schedule API must query UTC storage bounds');
assert.ok(platform.includes('workspaceTimezone($workspaceId)'), 'fallback schedule must use workspace timezone');
assert.ok(schedule.includes("event.offering_id IS NULL OR (offering.id IS NOT NULL AND term.id IS NOT NULL)"), 'schedule must reject stale academic links');
for (const type of ['class', 'exam', 'deadline', 'event', 'other']) assert.ok(scheduleModelSource.includes(`${type}:`), `schedule UI missing canonical event type ${type}`);
for (const mode of ['today', 'week', 'upcoming']) assert.ok(scheduleUi.includes(`#\/schedule\/${mode}`), `schedule route missing ${mode}`);
assert.ok(scheduleModelSource.includes('workspaceTodayKey'), 'schedule date model must use workspace-local today');
assert.equal(/resolvedOptions\(\)\.timeZone/.test(scheduleUi + scheduleModelSource), false, 'browser timezone must not become authority');

// Grades expose explicit term/course/item metadata and only published results.
for (const source of [platform, bot]) {
  assert.ok(source.includes('term.term_key'), 'grade projection must expose canonical term key');
  assert.ok(source.includes('term.name AS term_name'), 'grade projection must expose canonical term name');
  assert.ok(source.includes("gradebook.status = 'published'"), 'grade projection must filter published gradebooks');
  assert.ok(source.includes("result.status = 'published'"), 'grade projection must filter published results');
  assert.ok(source.includes("course.status = 'active'"), 'grade projection must hide inactive courses');
}
assert.ok(progressUi.includes('میانگین، معدل یا نمره نهایی محاسبه نمی‌شود'), 'grades UI must not invent GPA policy');
assert.equal(/\bGPA\b|میانگین کل|average/i.test(progressUi), false, 'grades UI must not fabricate aggregates');

// Exam catalog/attempt reads remain tenant and academic-lifecycle scoped; score math stays server-owned.
assert.ok(schema.includes("score_basis_points <= 10000"), 'schema score bound must remain enforced');
assert.ok(exam.includes("round(($correct / $questionCount) * 10000)"), 'server owns assessment score math');
assert.ok(exam.includes("offering.status <> 'archived'"), 'exam catalog must hide archived offerings');
assert.ok(exam.includes("term.status <> 'archived'"), 'exam catalog must hide archived terms');
assert.ok(exam.includes("course.status = 'active'"), 'exam catalog must hide inactive courses');
assert.ok(exam.includes('active_attempt_id'), 'exam catalog must expose resumable attempt state');
assert.ok(exam.includes('metadata.source_resource_id'), 'assessment catalog must retain source-resource linkage');
assert.ok(exam.includes("attempt.status = 'in_progress'"), 'attempt resume must be scoped to open attempts');
assert.ok(exam.includes("'resumed' => true"), 'starting an existing attempt must be idempotent/resumable');
for (const field of ['question_topic_invalid', 'question_tags_invalid', 'question_difficulty_invalid', 'question_provenance_invalid']) {
  assert.ok(exam.includes(field), `question model validation missing ${field}`);
}
assert.ok(api.includes("$suffix === '/assessments'"), 'public assessment catalog route missing');
assert.ok(progress.includes("type: 'past_exam'"), 'assessment page must load structured past-exam resources');
assert.ok(progress.includes('resourceQuery'), 'assessment page must expose resource browsing/filter state');
assert.ok(progressUi.includes('آزمون‌های گذشته'), 'assessment UI must render a past-exam shelf');
assert.ok(progressUi.includes('ادامه تلاش'), 'assessment UI must render resume action');
assert.ok(progressUi.includes('پاسخ صحیح پیش از ثبت نهایی'), 'assessment UI must preserve hidden-answer authority notice');
assert.ok(progressUi.includes('نتیجه از سرور دریافت شده است'), 'assessment result UI must identify server-owned result');
assert.ok(progressUi.includes('منبع ساختاریافتهٔ متصل'), 'assessment detail must show source linkage without exposing an internal id');
assert.ok(exam.includes("'quiz'"), 'quiz assessment kind must be server-supported');
assert.ok(schema.includes("assessment_kind IN ('practice', 'mock_exam', 'past_exam')"), 'baseline assessment kind constraint must remain documented');
assert.ok(read('database/migrations/0012_stage8_assessment_variants.sql').includes('assessment_variant'), 'quiz assessment variant migration is missing');

// Announcements support validated course scope while preserving workspace/user recipient isolation.
assert.ok(api.includes("$request->query['course_id'] ?? null"), 'announcement course filter missing');
assert.ok(api.includes("$request->body['course_id']"), 'announcement scoped publish input missing');
assert.ok(platform.includes("JSON_EXTRACT(message.data_json, '$.course_id')"), 'announcement course binding must come from canonical stored metadata');
assert.ok(platform.includes("scope_course.status = 'active'"), 'announcement course join must be lifecycle scoped');
assert.ok(platform.includes("notification.broadcast"), 'announcement publish must remain capability protected');
assert.ok(platform.includes('public function notifications('), 'personal notification projection missing');
assert.ok(platform.includes('public function markNotificationRead('), 'notification read mutation missing');
assert.ok(platform.includes('public function notificationPreferences('), 'notification preference projection missing');
assert.ok(platform.includes('notification.preferences.update'), 'notification preference mutation must be audited');
assert.ok(operations.includes('renderCourseAnnouncementsSlot'), 'course announcement slot missing');
assert.ok(operations.includes('/announcements?course_id='), 'course announcement slot must use public scoped endpoint');
assert.ok(bootstrap.includes("courseAnnouncements: true"), 'course announcement capability must be enabled after backend support');
assert.ok(bootstrap.includes("'course.announcements'"), 'course announcement integration slot missing');

// Forms return current-user submission state; mutations remain validated and idempotent.
assert.ok(platform.includes('submission_status'), 'forms projection must expose current submission status');
assert.ok(platform.includes('submission_user'), 'forms projection must scope submission state to current user');
assert.ok(platform.includes('idempotency_key'), 'form submission must remain idempotent');
assert.ok(platform.includes('validateAnswers'), 'form answers must be validated server-side');
assert.ok(operations.includes('submission_status'), 'forms UI must render canonical submission state');
assert.ok(operations.includes('پاسخ این فرم قبلاً ثبت شده است'), 'single-submit forms must fail closed in the UI');
assert.ok(operations.includes('closes_at'), 'forms UI must render canonical deadline when supplied');
assert.ok(!/schema_json\s*\}\)|JSON\.stringify\(row/.test(operations), 'forms UI must not dump raw schema JSON');
assert.ok(platform.includes('searchDocumentVisible'), 'search must re-check source visibility instead of filtering only by coarse RBAC');
assert.ok(platform.includes('source_label'), 'search projection must provide human-readable source labels');
assert.ok(platform.includes('notification_not_found'), 'notification read must reject another user or workspace inbox');

for (const source of [scheduleUi, progressUi, operations]) {
  assert.equal(/\.innerHTML\s*=/.test(source), false, 'student operations UI must not render through innerHTML');
}
assert.ok(openApi.includes('timezone_name'), 'public schedule contract must retain workspace timezone authority');
for (const phrase of ['timezone', 'course-scoped', 'submission_status', 'No GPA']) {
  assert.ok(handoff.includes(phrase), `Stage 6 handoff missing ${phrase}`);
}

(async () => {
  const model = await import(pathToFileURL(path.join(ROOT, 'apps/platform/public/assets/ui-v3/schedule/schedule-model.js')).href);
  assert.equal(model.validTimeZone('Asia/Tehran'), 'Asia/Tehran');
  assert.equal(model.validTimeZone('Not/AZone'), '');
  assert.deepEqual(model.buildRange('today', '2026-09-11', '2026-09-11'), { from: '2026-09-11', to: '2026-09-11', anchor: '2026-09-11' });
  assert.deepEqual(model.weekKeys('2026-09-11'), ['2026-09-05', '2026-09-06', '2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11']);
  const normalized = model.normalizeRows([{ id: 'event', event_type: 'exam', title: 'آزمون', starts_at: '2026-09-10 20:30:00', status: 'scheduled' }], 'Asia/Tehran');
  assert.equal(normalized.rows[0].dateKey, '2026-09-11', 'UTC instant must cross into workspace-local next day correctly');
  console.log('ui-v3 student operations contract: PASS');
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
