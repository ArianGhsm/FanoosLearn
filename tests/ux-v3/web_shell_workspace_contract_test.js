const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

const shell = read('apps/platform/public/assets/ui-v3/shell/module.js');
const shellEntry = read('apps/platform/public/assets/ui-v3/shell/index.js');
const router = read('apps/platform/public/assets/ui-v3/shell/router.js');
const shellUi = read('apps/platform/public/assets/ui-v3/shell/ui.js');
const shellCss = read('apps/platform/public/assets/ui-v3/shell/shell.css');
const handoff = read('docs/rebuild/04_SHELL_WORKSPACE_HANDOFF.md');
const navigation = `${shell}\n${router}`;

for (const label of ['خانه', 'درس‌ها', 'برنامه', 'منابع', 'آزمون‌ها', 'نمرات', 'اطلاعیه‌ها', 'بیشتر']) {
  assert.ok(navigation.includes(`label: '${label}'`) || navigation.includes(`text: '${label}'`), `shell navigation label missing: ${label}`);
}
for (const method of [
  'renderUninitializedAccountDestination',
  'renderWorkspaceSelectionDestination(workspaces)',
  'renderZeroWorkspaceDestination',
  'renderAccountDestination',
  'renderMoreDestination',
]) assert.ok(shell.includes(method), `onboarding/account method missing: ${method}`);

assert.ok(shell.includes("accountState()"), 'account readiness state machine missing');
for (const state of ['uninitialized', 'zero-memberships', 'memberships-no-selection', 'active-workspace']) {
  assert.ok(shell.includes(`'${state}'`), `account readiness state missing: ${state}`);
}
assert.ok(shell.includes("if (this.accountState() === 'uninitialized')"));
assert.ok(shell.includes("if (!Array.isArray(account.workspaces) || !account.workspaces.length) return 'zero-memberships'"));
assert.ok(shell.includes("if (!this.state.selectedWorkspaceId) return 'memberships-no-selection'"));

// Zero-membership users can still open every product route and receive a truthful gate/help view.
assert.ok(shell.includes("on: { click: () => this.router.navigate(id) }"));
assert.equal(/disabled:\s*locked/.test(shell), false, 'workspace-less navigation must not become a dead end');
assert.ok(shell.includes("if (!this.state.selectedWorkspaceId) return outlet.append(this.renderWorkspaceGate())"));
assert.ok(shell.includes('راهنمای شروع'));

// Account projection and channel-link handling remain read-only and bounded.
for (const field of ['channel_links', 'messaging_links', 'channels']) assert.ok(shell.includes(field), `public channel projection field missing: ${field}`);
assert.ok(shell.includes('function channelLabel'));
assert.ok(shell.includes('function channelStatusLabel'));
assert.ok(shell.includes("'تلگرام'"));
assert.ok(shell.includes("'بله'"));
assert.equal(shell.includes('/api/internal/'), false, 'shell must not consume internal APIs');
assert.equal(/\.innerHTML\s*=/.test(shell), false, 'shell must keep API/user data out of innerHTML');
assert.ok(shell.includes("onClick: (event) => this.handleLogout(event.currentTarget)"));

// Workspace changes invalidate old route work before mutation and revalidate canonical state.
assert.ok(shell.includes('const epoch = ++this.state.workspaceEpoch'));
assert.ok(shell.includes("phase: 'start'"));
assert.ok(shell.includes('replaceOutletForWorkspaceSwitch()'));
assert.ok(shell.includes('await this.api.selectWorkspace(requested)'));
assert.ok(shell.includes('const freshAccount = normalizeAccount(await this.api.account())'));
assert.ok(shell.includes("phase: 'commit'"));
assert.ok(shell.includes('workspaceEpoch: this.state.workspaceEpoch'));
assert.ok(shellEntry.includes('this.api.clearSessionBridge()'));
assert.ok(router.includes("addEventListener(mode === 'history' ? 'popstate' : 'hashchange'"));
assert.equal((router.match(/addEventListener\(mode === 'history' \? 'popstate' : 'hashchange'/g) || []).length, 1);

// Sheet focus behavior and responsive navigation are explicit contracts.
for (const hook of ['focusables(node)', "event.key === 'Escape'", "event.key !== 'Tab'", 'returnFocus.focus()']) assert.ok(shellUi.includes(hook), `sheet accessibility hook missing: ${hook}`);
for (const hook of ['.f3-shell-sidebar', '.f3-shell-mobile-nav', '.f3-shell-sheet', 'safe-area-inset-bottom', '@media (max-width: 840px)', '@media (max-width: 360px)']) assert.ok(shellCss.includes(hook), `responsive shell hook missing: ${hook}`);
assert.ok(shellCss.includes('prefers-reduced-motion: reduce'));

for (const phrase of ['uninitialized', 'zero-memberships', 'memberships-no-selection', 'active-workspace', 'channel_links', 'fanoos:v3:workspace-switch']) assert.ok(handoff.includes(phrase), `handoff missing ${phrase}`);

console.log('ui-v3 authenticated shell/workspace contract: PASS');
