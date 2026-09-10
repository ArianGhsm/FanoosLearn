const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');
const readBinary = (file) => fs.readFileSync(path.join(ROOT, file));

function assertWoff2Integrity(file) {
  const data = readBinary(file);
  assert.equal(data.subarray(0, 4).toString('ascii'), 'wOF2', `${file} must be WOFF2`);
  assert.equal(data.readUInt32BE(8), data.length, `${file} WOFF2 header length must match actual bytes`);
  assert.ok(data.length > 4096, `${file} is unexpectedly small`);
}

const index = read('apps/platform/public/index.php');
const tokens = read('apps/platform/public/assets/ui-v3/foundation/tokens.css');
const fontCss = read('apps/platform/public/assets/fonts/yekanbakh/fonts.css');
const theme = read('apps/platform/public/assets/ui-v3/app/web-schoolhouse-r1.css');
const designLock = read('docs/ui-v3/FANOOS_WEB_SCHOOLHOUSE_DESIGN_LOCK.md');

const runtimeSource = [index, tokens, fontCss, theme].join('\n');

assert.equal((index.match(/class="f3-public-home"/g) || []).length, 1, 'one public landing root required');
assert.equal((index.match(/id="fanoos-v3-root"/g) || []).length, 1, 'one V3 app root required');
assert.equal((index.match(/\/assets\/ui-v3\/app\/bootstrap\.js/g) || []).length, 1, 'one V3 bootstrap required');
assert.equal(index.includes('/assets/ui-v2/'), false, 'V2 cannot be a primary boot dependency');
assert.ok(index.includes('data-web-design-lock="FANOOS-WEB-UX-2026.09-SCHOOLHOUSE-R1"'));
assert.ok(designLock.includes('FANOOS-WEB-UX-2026.09-SCHOOLHOUSE-R1'));

for (const id of ['top', 'capabilities', 'course-first', 'how-it-works', 'fanoos-auth']) {
  assert.ok(index.includes(`id="${id}"`), `public Home missing ${id}`);
}
for (const text of ['دانشگاهت،', 'درس‌ها، برنامه، جزوه و منابع', 'ورود به فضای من', 'هر چیزی را فقط در همان جایی می‌بینی']) {
  assert.ok(index.includes(text), `public Home missing expected truthful copy: ${text}`);
}
assert.equal(/testimonial|partner logo|دانشجوی فعال|هزار دانشجو|میلیون دانشجو/i.test(index), false, 'public Home must not fabricate social proof');

assert.ok(index.includes('/assets/fonts/yekanbakh/fonts.css'));
assert.ok(index.includes('/assets/ui-v3/app/web-schoolhouse-r1.css'));
assert.ok(index.indexOf('/assets/ui-v3/app/integration.css') < index.indexOf('/assets/ui-v3/app/web-schoolhouse-r1.css'), 'Website presentation authority must load after structural V3 CSS');
assert.ok(fontCss.includes('font-family: "YekanBakh Fanoos"'));
assert.ok(fontCss.includes('YekanBakh-Regular-fa.woff2'));
assert.ok(fontCss.includes('YekanBakh-Bold-fa.woff2'));
assert.ok(fontCss.includes('font-weight: 400'));
assert.ok(fontCss.includes('font-weight: 700'));
assert.ok(fontCss.includes('unicode-range:'));
assert.ok(fontCss.includes('font-display: swap'));
assert.ok(tokens.includes('--f3-font-sans: "YekanBakh Fanoos"'));
assertWoff2Integrity('apps/platform/public/assets/fonts/yekanbakh/YekanBakh-Regular-fa.woff2');
assertWoff2Integrity('apps/platform/public/assets/fonts/yekanbakh/YekanBakh-Bold-fa.woff2');

assert.ok(tokens.includes('--f3-bg: #F7F4EC'));
assert.ok(tokens.includes('--f3-primary: #4954D6'));
assert.ok(tokens.includes('--f3-lantern: #F0B43C'));
assert.ok(tokens.includes('--f3-control-min: 44px'));
assert.ok(theme.includes('.f3-public-lantern'));
assert.ok(theme.includes('body:has(.f3-shell[data-f3-shell-root])'));
assert.ok(theme.includes('.f3-home-hero'));
assert.ok(theme.includes('.f3-course-card'));
assert.ok(theme.includes('.f3-schedule-toolbar'));
assert.ok(theme.includes('.f3-learning-resource'));

for (const width of ['1023px', '840px', '560px', '430px', '390px', '360px']) {
  assert.ok(theme.includes(`max-width: ${width}`), `responsive redesign guard missing: ${width}`);
}
assert.ok(theme.includes('min-width: var(--f3-viewport-min)'));
assert.ok(tokens.includes('--f3-viewport-min: 320px'));
assert.ok(theme.includes('@media (min-width: 1440px)'));
assert.ok(theme.includes('env(safe-area-inset-bottom'));

assert.ok(theme.includes('@media (prefers-reduced-motion: reduce)'));
assert.ok(index.includes('class="f3-public-skip"'));
assert.ok(index.includes('<header class="f3-public-header">'));
assert.ok(index.includes('<nav class="f3-public-nav"'));
assert.ok(index.includes('<main id="main-content">'));
assert.ok(index.includes('<footer class="f3-public-footer">'));

assert.equal(/https?:\/\//i.test(runtimeSource), false, 'redesign runtime must remain local-only');
assert.equal(/schoolhouse\.world/i.test(runtimeSource), false, 'no Schoolhouse runtime dependency is allowed');
const visibleIndex = index.replace(/FANOOS-WEB-UX-2026\.09-SCHOOLHOUSE-R1/g, 'DESIGN_LOCK');
assert.equal(/>\s*Schoolhouse\s*</i.test(visibleIndex), false, 'Schoolhouse branding must never appear as user-facing HTML text');
assert.equal(/!important/.test(theme), false, 'redesign layer must not use important carpet-bombing');
assert.ok(theme.split('\n').length < 1200, 'Website presentation layer must remain bounded rather than becoming a second framework');

console.log('ui-v3 schoolhouse-inspired web redesign contract: PASS');
