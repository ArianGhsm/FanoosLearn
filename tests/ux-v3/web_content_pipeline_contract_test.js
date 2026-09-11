const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

const service = read('apps/platform/src/Content/ContentService.php');
const kernel = read('apps/platform/src/Http/ApiKernel.php');
const platform = read('apps/platform/src/Core/WorkspacePlatformService.php');
const operations = read('apps/platform/public/assets/ui-v3/operations/operations.js');
const operationsCss = read('apps/platform/public/assets/ui-v3/operations/operations.css');
const openApi = read('contracts/openapi/core-v1.yaml');
const handoff = read('docs/rebuild/07_CONTENT_PIPELINE_HANDOFF.md');
const seed = `${read('database/seeds/0001_generic_rbac.sql')}\n${read('database/seeds/0003_content_engine.sql')}`;

for (const field of ['latest_version_id', 'latest_version_status', 'current_version_id', 'current_version_status']) {
  assert.ok(service.includes(field), `library projection missing ${field}`);
}
for (const method of ['public function versions', 'submitForReview', 'reviewVersion', 'publishVersion', 'deriveResource']) {
  assert.ok(service.includes(method), `content workflow method missing: ${method}`);
}
for (const type of ['discipline_note', 'summary', 'question_bank', 'past_exam', 'flashcards', 'audio', 'transcript', 'slide_reference']) {
  assert.ok(seed.includes(`'${type}'`), `canonical resource type missing: ${type}`);
}
assert.ok(service.includes('resource.create') && service.includes('resource.review'), 'version access must remain scoped');
assert.ok(service.includes('content_json') && service.includes('unset($row[\'content_json\'])'), 'version projection must decode safe structured content');
assert.equal(/SELECT[\s\S]{0,900}storage_key[\s\S]{0,900}FROM content_resource_versions/i.test(service), false, 'version projection must not expose storage keys');

assert.ok(kernel.includes("/resources/([0-9a-f-]+)/versions$"), 'resource version route missing');
assert.ok(kernel.includes('/derive'), 'resource derivation route missing');
assert.ok(kernel.includes("$this->requireContent()->updateMetadata"), 'resource metadata update route missing');
assert.ok(openApi.includes('listResourceVersions') && openApi.includes('deriveResourceOutput') && openApi.includes('updateResourceMetadata'), 'OpenAPI content workflow routes missing');

assert.ok(platform.includes("'capabilities' => $capabilities"), 'management capability projection missing');
assert.ok(operations.includes('function contentQueue'), 'producer/reviewer queue missing');
assert.ok(operations.includes('function resourceComposer'), 'producer draft composer missing');
for (const action of ['ارسال برای بررسی', 'تأیید نسخه', 'بازگشت برای اصلاح', 'انتشار نسخه', 'تاریخچه نسخه‌ها']) {
  assert.ok(operations.includes(action), `content action missing: ${action}`);
}
assert.ok(operations.includes("hasCapability(ctx, 'resource.review', dashboard)"), 'review UI must be capability-gated');
assert.ok(operations.includes("hasCapability(ctx, 'resource.publish', dashboard)"), 'publish UI must be capability-gated');
assert.ok(operations.includes("pathFor(ctx, '/resources?sort=newest')"), 'management queue must use canonical resource library');
assert.equal(operations.includes('storage_key'), false, 'content UI must not render storage keys');
assert.equal(operations.includes('.innerHTML'), false, 'content UI must not build HTML from API data');
for (const className of ['f3-ops-content-queue', 'f3-ops-content-row', 'f3-ops-content-versions']) assert.ok(operationsCss.includes(className), `responsive content UI style missing: ${className}`);

for (const phrase of ['draft', 'review', 'approved', 'published', 'idempotent', 'storage keys', 'producer', 'reviewer']) assert.ok(handoff.toLowerCase().includes(phrase), `handoff missing ${phrase}`);

console.log('ui-v3 content pipeline contract: PASS');
