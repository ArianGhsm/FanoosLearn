import { test } from 'node:test';
import assert from 'node:assert/strict';
import { chosenLetter, explanationParts } from '../../apps/platform/public/assets/web/pages/explanation-rules.js';

// The shape BankPublisher::explanation() writes.
const PUBLISHED = [
    '**پاسخ: گزینه‌ی ب**',
    '',
    'کراتوهیالین در لایه‌ی {{key:گرانولار}} است.',
    '',
    '## از رفرنس',
    'Keratohyalin granules in the granulous layer.',
    '',
    '## چرا بقیه نه',
    '- **الف:** لایه‌ی شاخی هسته ندارد.',
    '- **ج:** درست — این گزینه جمله‌ی درستی است.',
    '',
    '## نکته‌ی آزمون',
    'ارتوکراتین = گرانولار مشخص.',
    '',
    '## دام رایج',
    'پاراکراتین هم گرانول دارد ولی پراکنده.',
    '',
    '**منبع:** Carranza 14e · فصل ۴ · ص ۳۴',
].join('\n');

test('every published part is found and kept apart', () => {
    const parts = explanationParts(PUBLISHED);
    assert.equal(parts.answer, 'گزینه‌ی ب');
    assert.equal(parts.body, 'کراتوهیالین در لایه‌ی {{key:گرانولار}} است.');
    assert.deepEqual(parts.sections.map((s) => s.kind), ['reference', 'options', 'tip', 'trap']);
    assert.equal(parts.sections[0].body, 'Keratohyalin granules in the granulous layer.');
    assert.equal(parts.source, 'Carranza 14e · فصل ۴ · ص ۳۴');
});

test('other choices become rows; a «درست —» line is a true statement', () => {
    const { options } = explanationParts(PUBLISHED);
    assert.deepEqual(options, [
        { letter: 'الف', text: 'لایه‌ی شاخی هسته ندارد.', verdict: 'wrong' },
        { letter: 'ج', text: 'این گزینه جمله‌ی درستی است.', verdict: 'true' },
    ]);
});

test('several accepted answers keep the key note', () => {
    const parts = explanationParts('**پاسخ: گزینه‌های الف و ب** (کلید نهایی هر دو را پذیرفته است)\n\nمتن');
    assert.equal(parts.answer, 'گزینه‌های الف و ب');
    assert.equal(parts.note, '(کلید نهایی هر دو را پذیرفته است)');
    assert.equal(parts.body, 'متن');
});

test('plain or older Markdown stays whole in the body', () => {
    const source = 'یک توضیح ساده.\n\n## عنوان دیگر\n- مورد';
    const parts = explanationParts(source);
    assert.equal(parts.answer, null);
    assert.equal(parts.body, source);
    assert.deepEqual(parts.sections, []);
    assert.equal(parts.source, null);
    assert.deepEqual(explanationParts(null).sections, []);
});

test('the picked letter is looked up only for a real choice', () => {
    const letters = ['الف', 'ب', 'ج', 'د'];
    assert.equal(chosenLetter(letters, 2), 'ج');
    assert.equal(chosenLetter(letters, null), null);
    assert.equal(chosenLetter(letters, -1), null);
    assert.equal(chosenLetter(letters, 9), null);
});
