'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');

class FakeElement {
  constructor(tag) {
    this.tagName = tag;
    this.children = [];
    this.attributes = {};
    this.textContent = '';
    this.className = '';
    this.listeners = {};
    this.classList = {
      add: (...names) => {
        const set = new Set(this.className.split(/\s+/).filter(Boolean));
        names.forEach(name => set.add(name));
        this.className = [...set].join(' ');
      },
    };
  }
  append(...nodes) { this.children.push(...nodes.filter(Boolean)); }
  replaceChildren(...nodes) { this.children = nodes.filter(Boolean); this.textContent = ''; }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  addEventListener(name, handler) { this.listeners[name] = handler; }
  get childNodes() { return this.children; }
}

global.document = { createElement: tag => new FakeElement(tag) };
global.location = { origin: 'https://fanoos.test' };

const ux = require(path.resolve(__dirname, '../../apps/platform/public/assets/domain-ux.js'));

function container() { return new FakeElement('div'); }
function allText(node) {
  const own = typeof node.textContent === 'string' ? node.textContent : '';
  return [own, ...node.children.map(allText)].filter(Boolean).join('\n');
}
function tags(node) { return [node.tagName, ...node.children.flatMap(tags)]; }

const malicious = '<img src=x onerror=alert(1)><script>boom()</script>';
const secretWorkspace = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
const secretSource = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
const longMixed = `عنوان خیلی طولانی ${'د'.repeat(500)} English-Token-123`;

const cases = {
  schedule: [{
    title: malicious,
    starts_at: '2026-09-08T23:45:00+03:30',
    ends_at: '2026-09-09T00:30:00+03:30',
    event_type: 'unknown_type',
    course_code: 'DENT-1402-' + 'X'.repeat(400),
    location_text: longMixed,
    workspace_id: secretWorkspace,
  }],
  grades: [{
    item_title: longMixed,
    score: '999999999999999999999999',
    max_score: null,
    status: 'provider_weird_code',
    course_code: 'REST-101',
    updated_at: 'not-a-date',
    workspace_id: secretWorkspace,
  }],
  announcements: [{
    title: malicious,
    body: `${malicious}\u202eABC\u2066DEF`,
    published_at: '2026-09-08 20:15:00',
    action_url: 'javascript:alert(1)',
    source_id: secretSource,
  }],
  academics: [{
    title: 'درس مشترک',
    course_code: 'ABC-123',
    term_name: null,
    sequence_no: 0,
    starts_at: 'invalid',
    section_key: 'A'.repeat(500),
    workspace_id: secretWorkspace,
  }, {
    title: 'درس مشترک',
    workspace_id: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
  }],
  resources: [{
    title: malicious,
    type: 'mystery',
    description: longMixed,
    requires_entitlement: true,
    current_version_no: 'not-a-number',
    source_id: secretSource,
  }],
  assessments: [{
    title: longMixed,
    type: 'mystery',
    progress_percent: Number.MAX_VALUE,
    max_attempts: null,
    requires_entitlement: false,
    workspace_id: secretWorkspace,
  }],
  forms: [{
    title: malicious,
    description: longMixed,
    closes_at: null,
    opens_at: '2026-09-08T10:00:00Z',
    allow_multiple: 0,
    schema_json: malicious,
  }],
  orders: [{
    title: longMixed,
    status: 'paid',
    total_minor: 0,
    currency: 'IRR',
    entitlement_status: null,
    provider_reference: secretSource,
  }, {
    title: 'سفارش خراب',
    status: 'unknown_gateway_state',
    amount_minor: 'not-money',
    currency: '???',
  }],
  search: [{
    title: malicious,
    source_type: 'provider_internal_type',
    source_id: secretSource,
    excerpt: `${longMixed}\u202dLTR`,
    updated_at: 'invalid',
    workspace_id: secretWorkspace,
  }],
};

for (const [key, rows] of Object.entries(cases)) {
  const target = container();
  assert.doesNotThrow(() => ux.renderView(key, target, rows, { workspaceName: 'فضای تست' }), `${key} renderer crashed`);
  const text = allText(target);
  assert.equal(text.includes(secretWorkspace), false, `${key} leaked workspace id`);
  assert.equal(text.includes(secretSource), false, `${key} leaked source/provider id`);
  assert.equal(tags(target).includes('script'), false, `${key} created script element from untrusted text`);
  assert.equal(tags(target).includes('img'), false, `${key} created image element from untrusted text`);
}

for (const key of Object.keys(cases)) {
  const target = container();
  assert.doesNotThrow(() => ux.renderView(key, target, [], {}), `${key} empty state crashed`);
  assert.ok(allText(target).length > 0, `${key} empty state is blank`);
}

assert.equal(ux.formatNumber(null), '—');
assert.equal(ux.formatNumber(Number.POSITIVE_INFINITY), '—');
assert.equal(ux.formatMoney('bad', 'IRR'), '—');
assert.equal(ux.formatMoney(0, 'IRR'), '۰ ریال');
assert.equal(ux.formatDateTime('definitely-not-a-date'), '—');
assert.equal(ux.localizeStatus('gateway_secret_state'), 'وضعیت نامشخص');
assert.equal(ux.normalizeText('الف\u202eABC\u2066DEF'), 'الفABCDEF');
assert.ok(ux.safeTruncate('😀'.repeat(400), 40).length > 0, 'Unicode truncate failed');

const token = ux.technicalToken('ABC-' + 'x'.repeat(1000));
assert.equal(token.tagName, 'bdi');
assert.equal(token.attributes.dir, 'ltr');
assert.ok(token.textContent.length > 900, 'long token was corrupted');

console.log('domain UX fuzz: PASS');
