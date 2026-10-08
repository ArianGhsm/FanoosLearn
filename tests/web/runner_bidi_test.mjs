import assert from 'node:assert/strict';
import { test } from 'node:test';
import { bidiTextSegments } from '../../apps/platform/public/assets/web/pages/runner-view.js';

test('mixed Persian/English question keeps source text and groups clinical terms', () => {
    const text = 'منفی شدن کدام مارکر در تمایز Plasmablastic lymphoma از Diffuse large B-cell lymphoma کمک‌کننده است؟';
    const parts = bidiTextSegments(text);
    assert.equal(parts.map((part) => part.text).join(''), text);
    assert.deepEqual(parts.filter((part) => part.latin).map((part) => part.text), [
        'Plasmablastic lymphoma', 'Diffuse large B-cell lymphoma',
    ]);
});

test('Roman markers and all-English choices remain correctly isolated', () => {
    assert.deepEqual(bidiTextSegments('CD20'), [{ text: 'CD20', latin: true }]);
    assert.deepEqual(bidiTextSegments(''), []);
    const text = 'آزمایش MRI و CBCT نشان داد';
    assert.equal(bidiTextSegments(text).map((part) => part.text).join(''), text);
});

test('untrusted question markup remains plain text (no HTML parsing)', () => {
    const text = 'آیا <CD20> از BCL6 کمتر است؟';
    const runs = bidiTextSegments(text);
    assert.equal(runs.map((part) => part.text).join(''), text);
    assert.equal(runs.filter((part) => part.latin).map((part) => part.text).join(','), 'CD20,BCL6');
});
