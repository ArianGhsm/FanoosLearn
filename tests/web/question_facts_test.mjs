import { test } from 'node:test';
import assert from 'node:assert/strict';
import { questionFacts } from '../../apps/platform/public/assets/web/pages/question-facts.js';

const chapter = { book: 'Endodontics: Principles and Practice', edition: '6th edition', number: '14', title: 'Cleaning and Shaping', title_fa: 'پاک‌سازی و شکل‌دهی', pdf_page: 271 };

test('a classified bank question names its exam, year, number and chapter', () => {
    const facts = questionFacts({
        topic: 'طول کارکرد', tags: ['اندودانتیکس'],
        bank: { exam: 'دستیاری', exam_key: 'residency', year: 1404, round: 1, number: 12, subject: 'اندودانتیکس', chapter },
    });
    assert.equal(facts.exam, 'دستیاری ۱۴۰۴');
    assert.equal(facts.number, 'سؤال ۱۲ دفترچه');
    assert.equal(facts.subject, 'اندودانتیکس');
    assert.equal(facts.topic, 'طول کارکرد');
    assert.equal(facts.chapter, 'فصل ۱۴ · پاک‌سازی و شکل‌دهی');
    assert.equal(facts.chapterEn, 'Cleaning and Shaping');
    assert.equal(facts.book, 'Endodontics: Principles and Practice · 6th edition');
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
