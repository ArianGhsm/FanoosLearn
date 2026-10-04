import { test } from 'node:test';
import assert from 'node:assert/strict';
import { kindOf, lessonShape, parseCards, typeLabel } from '../../apps/platform/public/assets/web/pages/lessons-rules.js';
import { watermarkImage } from '../../apps/platform/public/assets/web/pages/watermark.js';

test('resource types fall under the right tab and read in Persian', () => {
    assert.equal(kindOf('lecture_note'), 'lesson');
    assert.equal(kindOf('cheat_sheet'), 'summary');
    assert.equal(kindOf('flashcards'), 'flashcards');
    assert.equal(kindOf('slide_reference'), 'other');
    assert.equal(typeLabel('summary'), 'خلاصه');
    assert.equal(typeLabel('nonsense'), 'سایر');
});

test('a lesson is text, cards, or a file', () => {
    assert.deepEqual(lessonShape({ format: 'markdown', body: '## تیتر' }), { kind: 'text', body: '## تیتر' });
    assert.equal(lessonShape({ sections: [{ heading: 'الف', body: 'متن' }] }).body, '## الف\nمتن');
    assert.deepEqual(lessonShape({ cards: [{ front: 'رو', back: 'پشت' }, { front: 1 }] }), { kind: 'cards', cards: [{ front: 'رو', back: 'پشت' }] });
    assert.deepEqual(lessonShape(null), { kind: 'file' });
    assert.deepEqual(lessonShape({ object: 'x' }), { kind: 'file' });
    assert.deepEqual(lessonShape({ body: '   ' }), { kind: 'file' });
});

test('flashcards are written one per line as front :: back', () => {
    assert.deepEqual(parseCards('رو ۱ :: پشت ۱\n\nبدون جداکننده\nرو ۲ :: پشت :: با دو\n :: خالی'), [
        { front: 'رو ۱', back: 'پشت ۱' },
        { front: 'رو ۲', back: 'پشت :: با دو' },
    ]);
});

test('the watermark escapes the name and tiles as an SVG', () => {
    const image = watermarkImage('<آرین> & co');
    assert.ok(image.startsWith('url("data:image/svg+xml'));
    assert.ok(decodeURIComponent(image).includes('&lt;آرین&gt; &amp; co'));
});
