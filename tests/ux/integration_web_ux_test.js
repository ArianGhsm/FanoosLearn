const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..', '..');
const appJs = fs.readFileSync(path.join(root, 'apps/platform/public/assets/app.js'), 'utf8');
const appCss = fs.readFileSync(path.join(root, 'apps/platform/public/assets/app.css'), 'utf8');
const domainCss = fs.readFileSync(path.join(root, 'apps/platform/public/assets/domain-ux.css'), 'utf8');
const domainJs = fs.readFileSync(path.join(root, 'apps/platform/public/assets/domain-ux.js'), 'utf8');
const indexPhp = fs.readFileSync(path.join(root, 'apps/platform/public/index.php'), 'utf8');

for (const legacy of ['--line', '--muted', '--ink', '--card', '--green']) {
  assert.equal(domainCss.includes(`var(${legacy})`), false, `legacy CSS token still used: ${legacy}`);
}

for (const token of ['--color-border', '--color-text-muted', '--color-text', '--color-surface', '--color-primary']) {
  assert.equal(appCss.includes(`${token}:`), true, `shell token is missing: ${token}`);
  assert.equal(domainCss.includes(`var(${token})`), true, `domain UX does not consume shell token: ${token}`);
}

assert.match(appJs, /function ensureDomainAssets\(\)/);
assert.equal((appJs.match(/data-fanoos-domain-ux/g) || []).length >= 2, true);
assert.equal(indexPhp.includes('/assets/domain-ux.css'), false, 'domain CSS must not also be statically loaded');
assert.equal(indexPhp.includes('/assets/domain-ux.js'), false, 'domain JS must not also be statically loaded');
assert.match(appJs, /setAttribute\('aria-pressed',active\?'true':'false'\)/);
assert.match(appJs, /clearActiveView\(\)/);

for (const view of ['schedule', 'grades', 'announcements', 'academics', 'resources', 'assessments', 'forms', 'orders']) {
  assert.equal(indexPhp.includes(`data-view="${view}"`), true, `missing shell view: ${view}`);
}

for (const renderer of ['schedule', 'grades', 'announcements', 'academics', 'resources', 'assessments', 'forms', 'orders', 'search']) {
  assert.equal(domainJs.includes(renderer), true, `domain renderer coverage missing: ${renderer}`);
}

assert.equal(/Object\.entries\([^)]*row/.test(domainJs), false, 'raw row object iteration must not return to user-facing rendering');
assert.equal(domainJs.includes('.innerHTML='), false, 'domain renderer must not assign untrusted innerHTML');

console.log('integration web UX: PASS');
