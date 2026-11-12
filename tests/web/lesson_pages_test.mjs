import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EMPHASIS, inlineRuns, isTableRule, tableCells } from '../../apps/platform/public/assets/web/pages/markdown-rules.js';
import {
    clampPage, pageCount, pageFromHash, paginate, progressPercent, storageKey, tocEntries,
} from '../../apps/platform/public/assets/web/pages/lesson-pages.js';

const LESSON = [
    'این درسنامه فصل ۱۰ را مرور می‌کند.',
    '',
    '## مکانیسم',
    '- {{term:اسمیر لایر}} لایه‌ای است که {{key:بعد از تراش}} می‌ماند.',
    '### جزئیات',
    '- متن',
    '## انواع',
    '```',
    '## این تیتر داخل کد است',
    '```',
    '## خلاصه و جمع‌بندی',
    '| مبحث | نکات کلیدی |',
    '|---|---|',
    '| مکانیسم | {{warn:به‌جز}} یک مورد |',
].join('\n');

test('a lesson is paged at its ## sections, after a contents page', () => {
    const book = paginate(LESSON);
    assert.equal(book.intro, 'این درسنامه فصل ۱۰ را مرور می‌کند.');
    assert.deepEqual(book.pages.map((p) => p.title), ['مکانیسم', 'انواع', 'خلاصه و جمع‌بندی']);
    assert.ok(book.pages[0].body.includes('### جزئیات'), 'a ### heading stays inside its page');
    assert.ok(book.pages[1].body.includes('## این تیتر داخل کد است'), 'a heading inside a code fence is not a page');
    assert.deepEqual(book.pages.map((p) => p.summary), [false, false, true]);
    assert.equal(pageCount(book), 4);
    assert.deepEqual(tocEntries(book).map((e) => e.page), [2, 3, 4]);
});

test('a lesson with fewer than two sections is not paged', () => {
    assert.equal(paginate('## تنها بخش\nمتن'), null);
    assert.equal(paginate('بدون تیتر'), null);
    assert.equal(paginate(''), null);
    assert.equal(pageCount(null), 1);
    assert.deepEqual(tocEntries(null), []);
});

test('the summary page is found with or without a half-space', () => {
    const book = paginate('## الف\nا\n## جمع بندی\nب\n## جمع‌بندی نهایی\nج\n## خلاصه\nد');
    assert.deepEqual(book.pages.map((p) => p.summary), [false, true, true, true]);
});

test('page numbers are clamped, read in Persian digits and taken from #pN', () => {
    assert.equal(clampPage('3', 5), 3);
    assert.equal(clampPage('۴', 5), 4);
    assert.equal(clampPage('99', 5), 5);
    assert.equal(clampPage('0', 5), 1);
    assert.equal(clampPage(null, 5), 1);
    assert.equal(clampPage('abc', 5), 1);
    assert.equal(pageFromHash('#p2', 5), 2);
    assert.equal(pageFromHash('#p77', 5), 5);
    assert.equal(pageFromHash('#top', 5), null);
    assert.equal(pageFromHash('', 5), null);
});

test('progress runs from 0 on the contents page to 100 on the last', () => {
    assert.equal(progressPercent(1, 5), 0);
    assert.equal(progressPercent(3, 5), 50);
    assert.equal(progressPercent(5, 5), 100);
    assert.equal(progressPercent(1, 1), 100);
    assert.equal(storageKey('abc'), 'fanoos.lesson.abc.page');
});

test('inline runs: bold and the four emphasis kinds; anything else stays literal', () => {
    assert.deepEqual(inlineRuns('الف **ب** {{key:ج}} و {{term: د }}'), [
        { kind: 'text', text: 'الف ' },
        { kind: 'bold', text: 'ب' },
        { kind: 'text', text: ' ' },
        { kind: 'key', text: 'ج' },
        { kind: 'text', text: ' و ' },
        { kind: 'term', text: 'د' },
    ]);
    assert.deepEqual(inlineRuns('{{note:x}}{{warn:y}}').map((r) => r.kind), ['note', 'warn']);
    assert.deepEqual(inlineRuns('{{bad:x}} {{key:}} {{key: }}'), [
        { kind: 'text', text: '{{bad:x}} {{key:}} ' },
        { kind: 'text', text: '{{key: }}' },
    ]);
    assert.deepEqual(inlineRuns('<b>x</b>'), [{ kind: 'text', text: '<b>x</b>' }]);
    assert.deepEqual(inlineRuns(''), []);
    assert.deepEqual(Object.keys(EMPHASIS), ['term', 'key', 'note', 'warn']);
});

test('table rows and the header rule', () => {
    assert.deepEqual(tableCells('| مبحث | نکات کلیدی |'), ['مبحث', 'نکات کلیدی']);
    assert.deepEqual(tableCells('|a||b|'), ['a', '', 'b']);
    assert.equal(tableCells('not | a row'), null);
    assert.equal(tableCells('|'), null);
    assert.ok(isTableRule(tableCells('|---|:---:|')));
    assert.ok(!isTableRule(tableCells('| - | x |')));
    assert.ok(!isTableRule([]));
});
