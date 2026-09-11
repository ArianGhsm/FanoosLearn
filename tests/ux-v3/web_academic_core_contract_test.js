const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

const schema = read('database/migrations/0003_academics_and_content.sql');
const api = read('apps/platform/src/Http/ApiKernel.php');
const platform = read('apps/platform/src/Core/WorkspacePlatformService.php');
const bot = read('apps/platform/src/Core/BotReadProjectionService.php');
const scheduleProjection = read('apps/platform/src/Core/ScheduleProjectionService.php');
const courses = read('apps/platform/public/assets/ui-v3/courses/index.js');
const model = read('apps/platform/public/assets/ui-v3/courses/course-model.js');
const view = read('apps/platform/public/assets/ui-v3/courses/course-view.js');
const handoff = read('docs/rebuild/05_ACADEMIC_HANDOFF.md');
const workstream = read('docs/ui-v3/workstreams/web-04-courses/WORKSTREAM_HANDOFF.md');

for (const table of [
  'academic_terms', 'academic_courses', 'academic_course_offerings',
  'academic_course_sessions', 'academic_enrollments',
]) {
  assert.ok(schema.includes(table), `academic schema missing ${table}`);
}

assert.ok(api.includes("$suffix === '/academics'"), 'workspace academics endpoint missing');
assert.ok(api.includes('academicNavigation'), 'academics endpoint must use canonical projection');
assert.ok(platform.includes("course.status = 'active'"), 'web course projection must expose active courses only');
assert.ok(platform.includes("status <> 'archived' AND archived_at IS NULL"), 'web academic projection must apply status and archive gates');
assert.ok(platform.includes("session.status <> 'archived'"), 'web session projection must hide archived sessions');
assert.ok(platform.includes("offering.status <> 'archived'"), 'web offering projection must hide archived offerings');
assert.ok(platform.includes('event.offering_id IS NULL OR (offering.id IS NOT NULL AND term.id IS NOT NULL)'), 'web schedule must retain workspace events but reject stale academic links');
assert.ok(bot.includes("course.status = 'active'"), 'bot course projection must expose active courses only');
assert.ok(bot.includes("term.status <> 'archived'"), 'bot course projection must hide archived terms');
assert.ok(bot.includes("offering.status <> 'archived'"), 'bot projections must hide archived offerings');
assert.ok(scheduleProjection.includes("course.status = 'active'"), 'schedule projection must hide inactive courses');
assert.ok(scheduleProjection.includes("offering.status <> 'archived'"), 'schedule projection must hide archived offerings');
assert.ok(scheduleProjection.includes('event.offering_id IS NULL OR (offering.id IS NOT NULL AND term.id IS NOT NULL)'), 'schedule must reject stale academic links');

assert.ok(courses.includes("pattern: '/courses/:courseCode'"), 'course route must use human course code');
assert.ok(courses.includes('/api/v1/workspaces/${encodeURIComponent(workspace)}/academics'), 'course UI must use canonical workspace endpoint');
for (const tab of ['overview', 'sessions', 'schedule', 'resources', 'assessments', 'grades', 'announcements']) {
  assert.ok(courses.includes(`'${tab}'`), `course route missing ${tab} tab`);
}
assert.ok(courses.includes("listQuery: ['term', 'q']"), 'course list must expose term/search query state');
assert.ok(model.includes('course_code'), 'course model must consume canonical course code');
assert.ok(model.includes('cleanText'), 'course model must normalize nullable/long text');
assert.ok(view.includes('function detailHref(courseCode'), 'detail route must be keyed by course code');
assert.ok(view.includes("courseAnnouncements', false"), 'course announcements must fail closed without a canonical relation');
assert.ok(view.includes('textContent'), 'course view must render user data through textContent');
assert.equal(/\.innerHTML\s*=/.test(courses + model + view), false, 'course UI must not assign user data through innerHTML');
assert.ok(!/\/api\/internal\//.test(courses + model + view), 'course UI must not use internal service endpoints');
assert.ok(view.includes('id: course.id'), 'internal course identity may be passed only to integration slots');
assert.ok(view.includes('course.code'), 'human course code must remain the visible/detail identity');

for (const phrase of [
  'No new academic CRUD UI or endpoint was invented',
  'Stage 5 time course-scoped announcements were unavailable',
  'workspace-scoped',
  'archived',
]) {
  assert.ok(handoff.includes(phrase), `academic handoff missing ${phrase}`);
}
assert.ok(workstream.includes('course UUID used internally only'), 'course workstream must preserve UUID presentation boundary');
assert.ok(workstream.includes('status is `archived`'), 'course workstream must document archived status filtering');

console.log('ui-v3 academic core contract: PASS');
