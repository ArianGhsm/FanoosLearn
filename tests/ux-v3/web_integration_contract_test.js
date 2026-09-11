const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const PUBLIC = path.join(ROOT, 'apps/platform/public');
const V3 = path.join(PUBLIC, 'assets/ui-v3');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');
const index = read('apps/platform/public/index.php');
const bootstrap = read('apps/platform/public/assets/ui-v3/app/bootstrap.js');
const homeAdapter = read('apps/platform/public/assets/ui-v3/app/home-module.js');
const notificationNav = read('apps/platform/public/assets/ui-v3/app/notification-nav.js');
const shell = read('apps/platform/public/assets/ui-v3/shell/module.js');
const router = read('apps/platform/public/assets/ui-v3/shell/router.js');
const courses = read('apps/platform/public/assets/ui-v3/courses/index.js');
const courseView = read('apps/platform/public/assets/ui-v3/courses/course-view.js');
const schedule = read('apps/platform/public/assets/ui-v3/schedule/index.js');
const scheduleModel = read('apps/platform/public/assets/ui-v3/schedule/schedule-model.js');
const learning = read('apps/platform/public/assets/ui-v3/learning/index.js');
const learningDetail = read('apps/platform/public/assets/ui-v3/learning/detail-view.js');
const progress = read('apps/platform/public/assets/ui-v3/progress/module.js');
const operations = read('apps/platform/public/assets/ui-v3/operations/operations.js');
const foundation = read('apps/platform/public/assets/ui-v3/foundation/runtime-contract.js');
const tokens = read('apps/platform/public/assets/ui-v3/foundation/tokens.css');
const baseCss = read('apps/platform/public/assets/ui-v3/foundation/base.css');
const integrationCss = read('apps/platform/public/assets/ui-v3/app/integration.css');

assert.match(index, /<html lang="fa" dir="rtl">/);
assert.equal((index.match(/id="fanoos-v3-root"/g) || []).length, 1, 'one V3 application root is required');
assert.ok(index.includes('class="f3-root"'));
assert.ok(index.includes('FANOOS-UX-2026.09-R1'));
assert.equal((index.match(/\/assets\/ui-v3\/app\/bootstrap\.js/g) || []).length, 1, 'bootstrap must load once');
assert.match(index, /<script type="module" src="\/assets\/ui-v3\/app\/bootstrap\.js"><\/script>/);
assert.equal(index.includes('/assets/ui-v2/'), false, 'V2 must not be part of the primary entrypoint');
assert.equal(index.includes('/assets/app.js'), false, 'legacy app.js must not boot from the primary entrypoint');
assert.equal(index.includes('/assets/domain-ux.js'), false, 'legacy domain UX must not boot from the primary entrypoint');

const cssAssets = [
  'foundation/tokens.css', 'foundation/base.css', 'foundation/components.css', 'shell/shell.css',
  'home/home.css', 'courses/courses.css', 'schedule/schedule.css', 'learning/learning.css',
  'progress/progress.css', 'operations/operations.css', 'app/integration.css',
];
for (const asset of cssAssets) {
  const needle = `/assets/ui-v3/${asset}`;
  const literal = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  assert.equal((index.match(new RegExp(`href="${literal}"`, 'g')) || []).length, 1, `${asset} must load exactly once as a stylesheet`);
}
assert.ok(index.indexOf('/foundation/tokens.css') < index.indexOf('/foundation/base.css'));
assert.ok(index.indexOf('/foundation/base.css') < index.indexOf('/foundation/components.css'));
assert.ok(index.indexOf('/foundation/components.css') < index.indexOf('/shell/shell.css'));
assert.equal((index.match(/data-f3-schedule-style=/g) || []).length, 1, 'schedule duplicate-loader guard missing');

for (const imported of ['../shell/index.js', '../courses/index.js', '../schedule/index.js', '../learning/index.js', '../progress/module.js', '../operations/operations.js', './home-module.js']) {
  assert.ok(bootstrap.includes(imported), `bootstrap missing ${imported}`);
}
assert.ok(homeAdapter.includes("import './notification-nav.js'"), 'notification navigation integration hook must load through the module graph');
assert.ok(notificationNav.includes(".f3-shell-sidebar__secondary"), 'personal notification gap must be discoverable on desktop');
assert.ok(notificationNav.includes(".f3-shell-more-page__groups .f3-shell-more-group"), 'personal notification gap must be discoverable on the More destination');
assert.ok(notificationNav.includes("aria-current"), 'injected notification destinations must expose active-route state');
assert.ok(bootstrap.includes("notifications: operationsDefinition"), 'personal notification route must map to the operations gap state');
assert.ok(bootstrap.includes("routes: [{ id: 'notifications', path: '/notifications'"), 'notification route registration missing');
assert.ok(bootstrap.includes("search: operationsDefinition"), 'workspace search route must map to the operations module');
assert.ok(router.includes("id: 'search'"), 'workspace search route registration missing');
assert.ok(bootstrap.includes("raw === '/learning' || raw === 'learning'"), '/learning compatibility alias must normalize to /resources');
assert.equal((bootstrap.match(/addEventListener\('hashchange'/g) || []).length, 0, 'integration layer must not create a second hash router');
assert.equal((notificationNav.match(/addEventListener\('hashchange'/g) || []).length, 0, 'notification integration must not create a second hash router');
assert.equal((router.match(/addEventListener\(mode === 'history' \? 'popstate' : 'hashchange'/g) || []).length, 1, 'shell must remain the navigation listener authority');

for (const key of ['root', 'api', 'state', 'navigate', 'format', 'ui', 'capabilities', 'signal']) assert.ok(foundation.includes(`'${key}'`), `runtime context key missing: ${key}`);
assert.ok(bootstrap.includes('const abort = new AbortController()'), 'fresh route abort controller missing');
assert.ok(bootstrap.includes('current.abort.abort()'), 'previous module must abort before replacement');
assert.ok(bootstrap.indexOf('current.abort.abort()') < bootstrap.indexOf('current.definition?.unmount?.(current.ctx)'), 'abort must precede unmount');
assert.ok(bootstrap.includes("event.detail?.phase === 'start'"), 'workspace switch must abort route work at start');
assert.ok(shell.includes("dispatchShellEvent(this.root, 'fanoos:v3:workspace-switch', { phase: 'start'"), 'shell workspace-start event missing');
assert.ok(shell.includes('replaceOutletForWorkspaceSwitch()'), 'old workspace content must be removed before mutation completes');
assert.equal(bootstrap.includes('fanoos_workspace'), false, 'integration must not create a second workspace store');

assert.ok(shell.includes('renderZeroWorkspaceDestination()'));
assert.ok(shell.includes('renderWorkspaceSelectionDestination(workspaces)'));
assert.ok(shell.includes('selected_workspace_id'));
assert.ok(shell.includes('بررسی نشست انجام نشد'));
assert.ok(shell.includes('در حال ورود…'));
assert.ok(shell.includes('نشست قبلی شما پایان یافته است') || read('apps/platform/public/assets/ui-v3/shell/index.js').includes('نشست قبلی شما پایان یافته است'));

assert.ok(homeAdapter.includes('Promise.all(['), 'Home must compose independent safe reads');
for (const endpoint of ['/schedule', '/assessments', '/announcements', '/resources?sort=newest', '/grades/me']) assert.ok(homeAdapter.includes(endpoint), `Home source missing ${endpoint}`);
assert.ok(homeAdapter.includes('safeRead'), 'Home partial failures must be localized');
assert.ok(read('apps/platform/public/assets/ui-v3/home/home.js').includes('partial-api-failure'));

assert.ok(courses.includes("pattern: '/courses/:courseCode'"), 'human course detail route missing');
for (const slot of ['course.schedule', 'course.resources', 'course.assessments', 'course.grades', 'course.announcements']) assert.ok(courseView.includes(slot), `course slot missing: ${slot}`);
assert.ok(bootstrap.includes('courseAnnouncements: true'), 'course announcement slot must be enabled only after canonical binding support');
assert.ok(bootstrap.includes("'course.announcements'"), 'course announcement slot must be registered');
assert.ok(bootstrap.includes('createCourseLearningEmbed'));
assert.ok(bootstrap.includes('renderCourseGradesSlot'));
assert.ok(bootstrap.includes("slotName === 'course.schedule'"));
assert.ok(bootstrap.includes("slotName === 'course.assessments'"));
assert.ok(bootstrap.includes("host.style.visibility = 'hidden'"), 'assessment embed must not flash unrelated-course rows before filtering');

assert.ok(scheduleModel.includes('workspaceTodayKey(timeZone'));
assert.ok(schedule.includes('workspace.timeZone'));
assert.ok(schedule.includes('بر اساس منطقه زمانی فضای آموزشی'));
assert.equal(schedule.includes('resolvedOptions().timeZone'), false, 'browser timezone must not become schedule authority');
assert.ok(scheduleModel.includes('weekStartSaturday'));
assert.ok(scheduleModel.includes("safeMode === 'today'"));
assert.ok(scheduleModel.includes("safeMode === 'week'"));
assert.ok(scheduleModel.includes("addDays(anchor, 89)"));

assert.ok(learning.includes("{ id: 'learning-resources', path: '/resources' }"));
assert.ok(learningDetail.includes('deliveryToken'));
assert.ok(learningDetail.includes('downloadToken'));
assert.equal(learningDetail.includes('sessionStorage'), false);
assert.equal(learningDetail.includes('localStorage'), false);
assert.equal(learningDetail.includes('storage_key'), false);
assert.ok(bootstrap.includes('openStructuredContent'));
assert.ok(bootstrap.includes('structuredText'));
assert.equal(bootstrap.includes('JSON.stringify(content'), false, 'structured resource content must not become a raw JSON dump');

assert.ok(progress.includes('/submit'));
assert.ok(progress.includes('/review'));
assert.ok(progress.includes('revision'));
assert.equal(progress.includes('correct_answer'), false, 'answer key must not be a browser contract');
assert.equal(progress.includes('correctAnswer'), false, 'answer key must not be a browser contract');
assert.ok(read('apps/platform/public/assets/ui-v3/progress/progress-ui.js').includes('امتیازدهی روی سرور انجام می‌شود'));
const gradeUi = read('apps/platform/public/assets/ui-v3/progress/progress-ui.js');
assert.equal(/\bGPA\b|میانگین کل|average/i.test(gradeUi), false, 'grades UI must not fabricate aggregate grades');

assert.ok(operations.includes('function orderPaymentState'));
assert.ok(operations.includes('function explicitAccessState'));
assert.ok(operations.includes("pathFor(ctx, '/catalog')"), 'Purchase UI must read the server catalog projection');
assert.ok(operations.includes("pathFor(ctx, '/entitlements')"), 'Access library must read canonical entitlements');
assert.ok(operations.includes('function orderState'), 'Order state must remain distinct from payment state');
assert.ok(operations.includes('function renderAccessLibrary'), 'Access library UI missing');
assert.ok(operations.includes('function renderCatalog'), 'Purchase catalog UI missing');
assert.ok(operations.includes("row?.entitlement?.granted === true"));
assert.ok(operations.includes("return 'unknown'"), 'paid must not imply entitlement');
assert.ok(operations.includes('پرداخت موفق به‌تنهایی مجوز محتوا نیست'), 'UI must not imply impossible DRM or payment authority');
assert.ok(operations.includes("hasCapability(ctx, 'notification.broadcast', dashboard)"));
assert.ok(operations.includes("hasCapability(ctx, 'form.manage', dashboard)"));
assert.ok(operations.includes("pathFor(ctx, '/notifications?limit=30')"), 'notification inbox must read the persisted web projection');
assert.ok(operations.includes("pathFor(ctx, '/notification-preferences')"), 'notification preferences endpoint missing');
assert.ok(operations.includes("pathFor(ctx, `/search?q=${encodeURIComponent(query)}`)"), 'workspace search endpoint missing');
assert.ok(operations.includes('source_label'), 'search results must use a human source label');
assert.ok(bootstrap.includes('has: () => false'), 'granular management mutations must fail closed until canonical capabilities are projected');
assert.equal(operations.includes('Update Server'), false);
assert.equal(operations.includes('به‌روزرسانی سرور'), false);

function allFiles(dir, suffix) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...allFiles(full, suffix));
    else if (entry.name.endsWith(suffix)) out.push(full);
  }
  return out;
}
const v3Js = allFiles(V3, '.js').map((file) => fs.readFileSync(file, 'utf8')).join('\n');
assert.equal(/\.innerHTML\s*=/.test(v3Js), false, 'V3 must not assign API/user data through innerHTML');
assert.equal(/https?:\/\/(?:fonts|cdn|unpkg|jsdelivr)\./i.test(index + tokens + integrationCss), false, 'remote UI dependency detected');
assert.ok(baseCss.includes(':focus-visible'));
assert.ok(baseCss.includes('prefers-reduced-motion: reduce'));
assert.ok(shell.includes("className: 'f3-shell-skip'"));
assert.ok(shell.includes("'aria-current': active ? 'page' : null"));
assert.ok(bootstrap.includes('previousFocus instanceof HTMLElement'));
assert.ok(bootstrap.includes('MutationObserver'), 'embedded heading reconciliation missing');
assert.ok(tokens.includes('--f3-control-min: 44px'));
assert.ok(baseCss.includes('--f3-viewport-min') || tokens.includes('--f3-viewport-min: 320px'));
for (const width of ['360px', '430px', '767px']) assert.ok(integrationCss.includes(width), `integration responsive guard missing: ${width}`);
assert.ok(tokens.includes('--f3-container-wide: 1440px'));
assert.ok(baseCss.includes('@media (min-width: 768px)'));
assert.ok(baseCss.includes('@media (min-width: 1024px)'));

console.log('ui-v3 web integration contract: PASS');
