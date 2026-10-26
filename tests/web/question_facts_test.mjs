import { test } from 'node:test';
import assert from 'node:assert/strict';
import { questionFacts } from '../../apps/platform/public/assets/web/pages/question-facts.js';

const chapter = { book: 'Endodontics: Principles and Practice', edition: '6th edition', number: '14', title: 'Cleaning and Shaping', title_fa: 'پاک‌سازی و شکل‌دهی', pdf_page: 271 };

test('a classified bank question names its exam, year, number and chapter', () => {
    const facts = questionFacts({
        topic: 'طول کارکرد', tags: ['اندودانتیکس'],
        bank: { exam: 'دستیاری', exam_key: 'residency', year: 1404, round: 1, number: 12, subject: 'اندودانتیکس',
            chapter: { ...chapter, reference_year: 1404, classification_basis: 'official_exam_year' } },
    });
    assert.equal(facts.exam, 'دستیاری ۱۴۰۴');
    assert.equal(facts.number, 'سؤال ۱۲ دفترچه');
    assert.equal(facts.subject, 'اندودانتیکس');
    assert.equal(facts.topic, 'طول کارکرد');
    assert.equal(facts.chapter, 'فصل ۱۴ · پاک‌سازی و شکل‌دهی');
    assert.equal(facts.chapterEn, 'Cleaning and Shaping');
    assert.equal(facts.book, 'Endodontics: Principles and Practice · 6th edition');
    assert.equal(facts.scopeNote, null);
    assert.equal(facts.referenceNote, null);
});

test('an outside-scope citation keeps its chapter and shows the explicit warning', () => {
    const note = 'محل طراحی سؤال خارج از محدودهٔ رسمی آزمون ۱۴۰۵ بوده است.';
    const facts = questionFacts({ bank: { exam: 'دستیاری', exam_key: 'residency', year: 1405, round: 1,
        number: 44, subject: 'آسیب‌شناسی دهان', chapter: { ...chapter,
            official_scope_status: 'outside_scope_exam_question', official_scope_note: note } } });
    assert.equal(facts.chapter, 'فصل ۱۴ · پاک‌سازی و شکل‌دهی');
    assert.equal(facts.referenceNote, null);
    assert.equal(facts.scopeNote, note);
});

test('proxy provenance note stays separate from official syllabus-scope note', () => {
    const scopeNote = 'محل طراحی سؤال خارج از محدودهٔ رسمی آزمون بوده است.';
    const referenceNote = 'این فصل‌بندی با نسخهٔ پروکسیِ سال ۱۳۹۷ انجام شده است؛ رسمی بودن آن برای آزمون ۱۳۹۶ تأیید نشده است.';
    const facts = questionFacts({ bank: { exam: 'دستیاری', exam_key: 'residency', year: 1396, round: 1,
        number: 204, subject: 'دندان‌پزشکی اجتماعی', chapter: { ...chapter,
            reference_year: 1397, classification_basis: 'same_lineage_proxy',
            classification_note: referenceNote, official_scope_status: 'outside_scope_exam_question',
            official_scope_note: scopeNote } } });
    assert.equal(facts.referenceNote, referenceNote);
    assert.equal(facts.scopeNote, scopeNote);
});

test('an unverified partial-scope page shows a warning without claiming it is outside', () => {
    const note = 'فصل ۹ تا صفحهٔ چاپی ۲۱۷ در سرفصل است؛ شمارهٔ چاپی این صفحه تأیید نشده است.';
    const facts = questionFacts({ bank: { exam: 'دستیاری', exam_key: 'residency', year: 1404, round: 1,
        number: 164, subject: 'دندانپزشکی ترمیمی', chapter: { ...chapter, number: '9', title: 'Bonding', title_fa: 'باندینگ',
            official_scope_status: 'in_scope', scope_unverified: true, official_scope_note: note } } });
    assert.equal(facts.chapter, 'فصل ۹ · باندینگ');
    assert.equal(facts.scopeNote, note);
});

test('a same-lineage proxy citation is visibly labeled with its actual source year', () => {
    const note = 'این فصل‌بندی با نسخهٔ پروکسیِ سال ۱۳۹۷ انجام شده است؛ رسمی بودن آن برای آزمون ۱۳۹۶ تأیید نشده است.';
    const facts = questionFacts({ bank: { exam: 'دستیاری', exam_key: 'residency', year: 1396, round: 1,
        number: 204, subject: 'دندان‌پزشکی اجتماعی', chapter: { ...chapter,
            reference_year: 1397, classification_basis: 'same_lineage_proxy',
            classification_note: note } } });
    assert.equal(facts.book, 'Endodontics: Principles and Practice · 6th edition');
    assert.equal(facts.referenceNote, note);
    assert.equal(facts.scopeNote, null);
});

test('a national round is its month', () => {
    const facts = questionFacts({ bank: { exam: 'ملی', exam_key: 'national', year: 1405, round: 2, number: null, subject: 'پریو', chapter: null } });
    assert.equal(facts.exam, 'ملی ۱۴۰۵ · دی');
    assert.equal(facts.number, null);
});

test('an unclassified question draws no chapter, and the topic is not repeated', () => {
    const facts = questionFacts({ topic: 'اندودانتیکس', tags: ['اندودانتیکس'], bank: { exam: 'بورد', exam_key: 'board', year: 1401, round: 1, number: 3, subject: 'اندودانتیکس', chapter: null } });
    assert.equal(facts.chapter, null);
    assert.equal(facts.book, null);
    assert.equal(facts.topic, null);
});

test('a chapter without a Persian title shows its own title', () => {
    const facts = questionFacts({ bank: { exam: 'دستیاری', exam_key: 'residency', year: 1400, round: 1, number: 1, subject: 'x', chapter: { ...chapter, title_fa: null, number: null } } });
    assert.equal(facts.chapter, 'فصل · Cleaning and Shaping');
    assert.equal(facts.chapterEn, null);
});

test('an authored question has only its topic and tags', () => {
    const facts = questionFacts({ topic: 'قلب', tags: ['داخلی'] });
    assert.deepEqual([facts.exam, facts.number, facts.chapter, facts.subject, facts.topic], [null, null, null, 'داخلی', 'قلب']);
});

test('a key-conflict citation keeps the official key and shows the book answer as a note', () => {
    const note = 'کلید رسمی: گزینهٔ ۳. طبق متن کتاب پاسخ گزینهٔ ۱ است.';
    const facts = questionFacts({ bank: { exam_type: 'national', year: 1397, round: 2, number: 162,
        chapter: { book: 'Summit', edition: '4e', number: '7', title: 'Classification', title_fa: 'طبقه‌بندی',
            official_scope_status: 'in_scope', official_scope_note: null, key_conflict_note: note } } });
    assert.equal(facts.keyConflictNote, note);
    assert.equal(facts.scopeNote, null);
});
