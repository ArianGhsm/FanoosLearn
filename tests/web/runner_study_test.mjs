/*
 * The in-question study tools: notes and stem highlights.
 *
 * The assertion this file exists for is the last one -- that highlighting a
 * question never writes any part of that question to storage. A highlight is
 * "the bit the student thought mattered", so storing it as text would
 * reconstruct the stem in localStorage a fragment at a time, defeating the
 * per-question pacing that protects the bank. Offsets carry the same meaning
 * and are useless without the question in front of them.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    addHighlight, clearHighlights, highlightSegments, loadHighlights, loadNotes, mergeRanges, saveNote,
} from '../../apps/platform/public/assets/web/pages/runner-study.js';

function withStorage(store = new Map()) {
    global.window = {
        localStorage: {
            getItem: (key) => (store.has(key) ? store.get(key) : null),
            setItem: (key, value) => store.set(key, String(value)),
            removeItem: (key) => store.delete(key),
        },
    };
    return store;
}

function withThrowingStorage() {
    global.window = {
        localStorage: {
            getItem() { throw new Error('private window'); },
            setItem() { throw new Error('private window'); },
            removeItem() { throw new Error('private window'); },
        },
    };
}

test('a note round-trips, keyed by question id rather than position', () => {
    withStorage();
    saveNote('a1', 'q7', '  پانکراس، نه معده  ');
    assert.deepEqual(loadNotes('a1'), { q7: 'پانکراس، نه معده' });
    // A different assessment must not see it.
    assert.deepEqual(loadNotes('a2'), {});
});

test('clearing a note removes the key instead of storing an empty string', () => {
    const store = withStorage();
    saveNote('a1', 'q7', 'چیزی');
    saveNote('a1', 'q7', '   ');
    assert.deepEqual(loadNotes('a1'), {});
    assert.ok(!String(store.get('fanoos.examNotes.v1.a1')).includes('q7'), 'an emptied note left its key behind');
});

test('overlapping and touching highlight ranges merge into one', () => {
    assert.deepEqual(mergeRanges([{ start: 0, end: 5 }, { start: 3, end: 9 }], 20), [{ start: 0, end: 9 }]);
    // Touching, not overlapping: one continuous highlight to the eye.
    assert.deepEqual(mergeRanges([{ start: 0, end: 5 }, { start: 5, end: 8 }], 20), [{ start: 0, end: 8 }]);
    // The same selection twice is a no-op rather than a darker overlap.
    assert.deepEqual(mergeRanges([{ start: 2, end: 6 }, { start: 2, end: 6 }], 20), [{ start: 2, end: 6 }]);
    // Disjoint stays disjoint.
    assert.deepEqual(mergeRanges([{ start: 8, end: 9 }, { start: 0, end: 2 }], 20), [{ start: 0, end: 2 }, { start: 8, end: 9 }]);
});

test('ranges are clamped to the text and empty ones dropped', () => {
    assert.deepEqual(mergeRanges([{ start: -4, end: 999 }], 10), [{ start: 0, end: 10 }]);
    assert.deepEqual(mergeRanges([{ start: 5, end: 5 }], 10), []);
    assert.deepEqual(mergeRanges([{ start: 7, end: 3 }], 10), []);
    assert.deepEqual(mergeRanges([null, 'nonsense', { start: 'x', end: 2 }], 10), []);
});

test('segments cover the whole stem exactly once, in order', () => {
    const text = 'مرد ۴۵ ساله با درد اپی‌گاستر';
    const segments = highlightSegments(text, [{ start: 4, end: 10 }]);
    assert.equal(segments.map((segment) => segment.text).join(''), text);
    assert.deepEqual(segments.map((segment) => segment.highlighted), [false, true, false]);
    // No highlights at all is still one full run, not an empty list.
    assert.deepEqual(highlightSegments(text, []), [{ text, highlighted: false }]);
});

test('highlights round-trip and can be cleared per question', () => {
    withStorage();
    addHighlight('a1', 'q7', { start: 4, end: 10 }, 40);
    addHighlight('a1', 'q7', { start: 8, end: 14 }, 40);
    assert.deepEqual(loadHighlights('a1'), { q7: [{ start: 4, end: 14 }] });
    clearHighlights('a1', 'q7');
    assert.deepEqual(loadHighlights('a1'), {});
});

test('storage that throws on access leaves every study tool usable', () => {
    withThrowingStorage();
    assert.deepEqual(loadNotes('a1'), {});
    assert.deepEqual(loadHighlights('a1'), {});
    assert.equal(saveNote('a1', 'q7', 'چیزی'), false);
    assert.equal(addHighlight('a1', 'q7', { start: 0, end: 3 }, 10), false);
    assert.equal(clearHighlights('a1', 'q7'), false);
});

test('NO part of a question ever reaches storage, however it is highlighted', () => {
    const store = withStorage();
    const stem = 'مرد ۴۵ ساله‌ای با درد اپی‌گاستر و کاهش وزن مراجعه کرده است.';

    // Highlight the whole stem, one word at a time -- the worst case, and the
    // one a determined student would actually try.
    let offset = 0;
    for (const word of stem.split(' ')) {
        addHighlight('a1', 'q7', { start: offset, end: offset + word.length }, stem.length);
        offset += word.length + 1;
    }

    const written = [...store.values()].join('');
    for (const word of stem.split(' ').filter((word) => word.length > 2)) {
        assert.ok(!written.includes(word), `storage contains question text: "${word}"`);
    }

    // What it does contain is offsets, which say nothing on their own. The
    // words stay separate ranges rather than merging into one, because the
    // spaces between them were never selected -- highlighting every word is
    // not the same as highlighting the sentence, and storage says so.
    const ranges = loadHighlights('a1').q7;
    assert.equal(ranges.length, stem.split(' ').length);
    assert.equal(ranges[0].start, 0);
    assert.equal(ranges[ranges.length - 1].end, stem.length);
});
