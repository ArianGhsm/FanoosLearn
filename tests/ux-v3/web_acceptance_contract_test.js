const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const PUBLIC = path.join(ROOT, 'apps/platform/public');
const V3 = path.join(PUBLIC, 'assets/ui-v3');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');
const index = read('apps/platform/public/index.php');
const htaccess = read('apps/platform/public/.htaccess');
const baseCss = read('apps/platform/public/assets/ui-v3/foundation/base.css');
const tokens = read('apps/platform/public/assets/ui-v3/foundation/tokens.css');

assert.ok(index.includes('function fanoosAsset'), 'HTML shell must provide release-compatible asset versioning');
assert.ok(index.includes('FANOOS_ASSET_VERSION'), 'deployments must be able to pin one release asset version');
assert.ok(index.includes("fanoosAsset('/assets/ui-v3/app/bootstrap.js')"), 'bootstrap must use a cache-busting version');
assert.equal((index.match(/fanoosAsset\('\/assets\/ui-v3\/app\/bootstrap\.js'\)/g) || []).length, 1, 'exactly one application bootstrap may load');

const stylesheetPaths = [...index.matchAll(/fanoosAsset\('(\/assets\/[^']+\.css)'\)/g)].map((match) => match[1]);
assert.equal(new Set(stylesheetPaths).size, stylesheetPaths.length, 'duplicate versioned stylesheet references detected');
assert.equal((index.match(/data-f3-schedule-style=/g) || []).length, 1, 'schedule stylesheet guard must remain unique');

assert.ok(htaccess.includes('X-Content-Type-Options "nosniff"'));
assert.ok(htaccess.includes('X-Frame-Options "SAMEORIGIN"'));
assert.ok(htaccess.includes('Permissions-Policy'));
assert.ok(htaccess.includes('Cache-Control "public, max-age=31536000, immutable"'));

const files = [];
function walk(directory) {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const full = path.join(directory, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (/\.(?:js|css)$/.test(entry.name)) files.push(full);
  }
}
walk(V3);
const source = files.map((file) => fs.readFileSync(file, 'utf8')).join('\n');
assert.equal(/\.innerHTML\s*=|insertAdjacentHTML|document\.write|\beval\s*\(/.test(source), false, 'Website must not inject untrusted markup or dynamic code');
assert.equal(/https?:\/\/(?:fonts|cdn|unpkg|jsdelivr)\./i.test(source), false, 'Website must not depend on remote asset CDNs');
assert.ok(files.reduce((sum, file) => sum + fs.statSync(file).size, 0) < 800000, 'V3 asset budget exceeded');
assert.ok(files.every((file) => fs.statSync(file).size < 120000), 'single V3 asset is too large for a responsive route');

assert.ok(baseCss.includes(':focus-visible'), 'focus-visible styling is required');
assert.ok(baseCss.includes('prefers-reduced-motion: reduce'), 'reduced-motion handling is required');
assert.ok(tokens.includes('--f3-control-min: 44px'), 'touch target token is required');
assert.ok(index.includes('<html lang="fa" dir="rtl">'), 'public shell must retain Persian RTL semantics');
assert.ok(index.includes('f3-public-skip'), 'public shell must expose a keyboard skip link');

console.log('ui-v3 web acceptance contract: PASS');
