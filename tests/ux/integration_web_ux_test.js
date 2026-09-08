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

const definitions = new Set([...appCss.matchAll(/(--[a-z0-9-]+)\s*:/gi)].map(match => match[1]));
for (const match of `${appCss}\n${domainCss}`.matchAll(/var\(\s*(--[a-z0-9-]+)/gi)) {
  assert.equal(definitions.has(match[1]), true, `unresolved CSS custom property: ${match[1]}`);
}

assert.equal(appJs.includes('function ensureDomainAssets()'), false, 'obsolete dynamic asset loader must be removed');
assert.equal((indexPhp.match(/\/assets\/domain-ux\.css/g) || []).length, 1, 'domain CSS must load exactly once');
assert.equal((indexPhp.match(/\/assets\/domain-ux\.js/g) || []).length, 1, 'domain JS must load exactly once');
assert.ok(indexPhp.indexOf('/assets/domain-ux.js') < indexPhp.indexOf('/assets/app.js'), 'domain JS must execute before app.js');

assert.match(appJs, /setAttribute\('aria-pressed',active\?'true':'false'\)/);
assert.match(appJs, /clearActiveView\(\)/);
assert.equal(indexPhp.includes('id="dashboard" aria-labelledby="greeting" tabindex="-1"'), true, 'dashboard focus target missing');
assert.equal(appJs.includes("if(focus)$('#dashboard').focus()"), true, 'post-login focus handoff missing');

assert.equal(appJs.includes("workspace:sessionStorage.getItem('fanoos_workspace')"), false, 'sessionStorage must not choose canonical workspace');
assert.equal(appJs.includes("account?.selected_workspace_id"), true, 'canonical selected workspace must come from backend account projection');
assert.equal(appJs.includes('workspaceMutation=true;select.disabled=true;++state.requestSerial'), true, 'workspace switch must invalidate stale GETs before mutation');
assert.equal(appJs.includes('if(!view||state.workspaceMutation)return'), true, 'view loads must not race a workspace mutation');
assert.equal(appJs.includes('!state.workspace||state.workspaceMutation||q.length<2'), true, 'search must not race a workspace mutation');
assert.equal(appJs.includes("title:'خروج انجام نشد'"), true, 'logout failure must be visible');
assert.equal(/finally\s*\{\s*sessionStorage\.clear\(\)/.test(appJs), false, 'logout failure must not masquerade as success');

for (const view of ['schedule', 'grades', 'announcements', 'academics', 'resources', 'assessments', 'forms', 'orders']) {
  assert.equal(indexPhp.includes(`data-view="${view}"`), true, `missing shell view: ${view}`);
}

for (const renderer of ['schedule', 'grades', 'announcements', 'academics', 'resources', 'assessments', 'forms', 'orders', 'search']) {
  assert.equal(domainJs.includes(renderer), true, `domain renderer coverage missing: ${renderer}`);
}

assert.equal(/Object\.entries\([^)]*row/.test(domainJs), false, 'raw row object iteration must not return to user-facing rendering');
assert.equal(domainJs.includes('.innerHTML='), false, 'domain renderer must not assign untrusted innerHTML');
assert.equal(/https?:\/\/(?:fonts|cdn|unpkg|jsdelivr)\./i.test(indexPhp + appCss + domainCss), false, 'remote visual dependency detected');

console.log('integration web UX: PASS');
