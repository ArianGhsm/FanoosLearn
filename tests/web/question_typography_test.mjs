import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (name) => readFileSync(
    new URL(`../../apps/platform/public/assets/web/pages/${name}`, import.meta.url),
    'utf8',
);
const runner = read('runner.css');
const skin = read('runner-skin.css');
const saved = read('saved.css');
const type = readFileSync(
    new URL('../../apps/platform/public/assets/web/foundation/type.css', import.meta.url),
    'utf8',
);

const escapeRegex = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
function blocks(css, selector) {
    const pattern = new RegExp(`${escapeRegex(selector)}\\s*\\{([^}]+)\\}`, 'g');
    return [...css.matchAll(pattern)].map((match) => match[1]);
}
function weight(block) {
    const values = [...block.matchAll(/font-weight:\s*([^;]+);/g)];
    return values.at(-1)?.[1]?.trim();
}

test('self-hosted Vazirmatn provides real 600 and 700 weight files', () => {
    assert.match(type, /Vazirmatn-SemiBold[.]woff2/);
    assert.match(type, /Vazirmatn-Bold[.]woff2/);
});

test('question stems use bold weight in base, desktop, and mobile styles', () => {
    const base = blocks(runner, '.x-question__prompt');
    const overridden = blocks(skin, '.x-runner-page .x-question__prompt');
    assert.equal(base.length, 1);
    assert.equal(weight(base[0]), 'var(--weight-strong)');
    assert.equal(overridden.length, 2, 'desktop and mobile must both specify weight');
    for (const block of overridden) assert.equal(weight(block), 'var(--weight-strong)');
});

test('answer options use semibold weight in base and runner overrides', () => {
    const base = blocks(runner, '.x-choice__text');
    assert.equal(base.length, 1);
    assert.equal(weight(base[0]), 'var(--weight-medium)');
    for (const selector of ['.x-runner-page .x-choice', '.x-runner-page .x-choice__text']) {
        const matches = blocks(skin, selector);
        assert.ok(matches.length > 0, `${selector} missing`);
        assert.equal(weight(matches[0]), 'var(--weight-medium)');
    }
});

test('saved question previews and question-report details have matching hierarchy', () => {
    assert.match(saved, /[.]s-item__preview,[\s\S]*?[.]s-report__question > p\s*\{\s*font-weight:\s*var\(--weight-strong\)/);
    const answerLists = blocks(saved, '.s-report__choices');
    assert.ok(answerLists.some((b) => weight(b) === 'var(--weight-medium)'));
});
