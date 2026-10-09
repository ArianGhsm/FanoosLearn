import assert from 'node:assert/strict';
import { test } from 'node:test';
import { bidiTextSegments, readableBidiSegments } from '../../apps/platform/public/assets/web/pages/runner-view.js';

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

test('inserts display-only separators at Persian/Latin boundaries', () => {
    const original = 'Toll-like receptorsصحیح است؟ TLR-4هم بیان می‌شود.';
    const chunks = readableBidiSegments(original);
    const shown = chunks.map((part) => part.text).join('');
    assert.equal(shown, 'Toll-like receptors صحیح است؟ TLR-4 هم بیان می‌شود.');
    assert.equal(bidiTextSegments(original).map((part) => part.text).join(''), original);
});

test('keeps embedded alphanumeric medical terms intact', () => {
    const original = 'CD20 و BCL6 و 3D CT';
    assert.equal(readableBidiSegments(original).map((part) => part.text).join(''), original);
});

test('separates a section number and an English sentence in reading passages', () => {
    assert.equal(
        readableBidiSegments('Passage 1The goals of this study').map((part) => part.text).join(''),
        'Passage 1 The goals of this study',
    );
});
