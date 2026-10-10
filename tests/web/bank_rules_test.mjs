import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ALL_EXAMS, chapterCoverage, goalLabel, groupSittings, normalizeGoal, specialtiesByType, specialtyFirst, droppedReferences, fold, matches, previousYear, referenceChange, scopeText, share, sittingLabel, yearSpan } from '../../apps/platform/public/assets/web/pages/bank-rules.js';
import { faDigits } from '../../apps/platform/public/assets/web/pages/question-stats.js';

test('search folds Arabic letter forms, Persian digits and ZWNJ', () => {
    assert.equal(fold('پريودانتيكس'), fold('پریودانتیکس'));
    assert.equal(fold('۱۴۰۴'), '1404');
    assert.ok(matches('بیماری های', 'بیماری‌های دهان'));
    assert.ok(matches('1404', 'دستیاری ۱۴۰۴'));
    assert.ok(matches('', 'anything'));
    assert.ok(!matches('ارتو', 'اندودانتیکس'));
    assert.ok(matches('endo', 'اندودانتیکس', 'Endodontics'));
});

test('a year span reads as one year or a range, and nothing without questions', () => {
    assert.equal(yearSpan(1397, 1404, faDigits), '۱۳۹۷ تا ۱۴۰۴');
    assert.equal(yearSpan(1404, 1404, faDigits), '۱۴۰۴');
    assert.equal(yearSpan(null, null, faDigits), null);
});

test('a topic bar is its share of the subject, never invisible', () => {
    assert.equal(share(50, 200), 25);
    assert.equal(share(1, 1000), 2);
    assert.equal(share(3, 0), 0);
});

test('a sitting names its round only when there is more than one', () => {
    assert.equal(sittingLabel({ type: 'دستیاری', year: 1404, round: 1 }, faDigits), 'دستیاری ۱۴۰۴');
    assert.equal(sittingLabel({ type: 'دستیاری', year: 1404, round: 2 }, faDigits), 'دستیاری ۱۴۰۴ · نوبت ۲');
});

const ref = (editionRef, scope = null) => ({ edition_ref: editionRef, scope });
const yearsList = [
    { type: 'دستیاری', year: 1405, subjects: [] },
    { type: 'بورد', year: 1404, subjects: [] },
    { type: 'دستیاری', year: 1403, subjects: [] },
];

test('the year before is the next older year of the same exam type', () => {
    assert.equal(previousYear(yearsList, 0).year, 1403);
    assert.equal(previousYear(yearsList, 1), null);
    assert.equal(previousYear(yearsList, 2), null);
    assert.equal(previousYear(yearsList, 9), null);
});

test('a reference is new, a new edition, a new scope, or unchanged', () => {
    const before = { references: [ref('white@7e', 'همه'), ref('craig@14e', 'فصل 1')] };
    assert.equal(referenceChange(ref('white@8e', 'همه'), before), 'edition');
    assert.equal(referenceChange(ref('craig@14e', 'فصل 2'), before), 'scope');
    assert.equal(referenceChange(ref('craig@14e', 'فصل 1'), before), null);
    assert.equal(referenceChange(ref('powers@11e'), before), 'new');
    assert.equal(referenceChange(ref('powers@11e'), null), null);
});

test('a scope change is read from the named chapters, not from how the words are spelt', () => {
    const chapters = (...inside) => ['1', '2', '3'].map((number) => ({ number, in_scope: inside.includes(number), partial: null }));
    const named = (scope, ...inside) => ({ edition_ref: 'carr@12e', scope, chapters: chapters(...inside) });
    const before = { references: [named('فصول 1 و 2', '1', '2')] };
    assert.equal(referenceChange(named('فصل‌های ۱ و ۲', '1', '2'), before), null);
    assert.equal(referenceChange(named('فصول 1 تا 3', '1', '2', '3'), before), 'scope');
    assert.equal(referenceChange({ edition_ref: 'carr@12e', scope: 'تمام فصول', chapters: [] }, before), null);
    assert.equal(referenceChange(ref('carr@12e', 'تمام  فصول'), { references: [ref('carr@12e', 'تمام فصول')] }), null);
});

test('a dropped reference is one whose book is gone in every edition', () => {
    const before = { references: [ref('white@7e'), ref('van-noort@4e')] };
    const now = { references: [ref('white@8e')] };
    assert.deepEqual(droppedReferences(now, before).map((r) => r.edition_ref), ['van-noort@4e']);
    assert.deepEqual(droppedReferences(now, null), []);
});

test('chapter coverage counts announced chapters only when the year names them', () => {
    assert.deepEqual(chapterCoverage([{ in_scope: true }, { in_scope: false }, { in_scope: true }]), { total: 3, inScope: 2 });
    assert.deepEqual(chapterCoverage([{ in_scope: null }, { in_scope: null }]), { total: 2, inScope: null });
    assert.deepEqual(chapterCoverage(undefined), { total: 0, inScope: null });
});

test('a scope reads in Persian digits with room after each comma between numbers', () => {
    assert.equal(scopeText('فصول 4,5،8 و 13–30', faDigits), 'فصول ۴، ۵، ۸ و ۱۳–۳۰');
    assert.equal(scopeText(null, faDigits), '');
});

test('a stored goal is kept only while its exam type exists', () => {
    assert.deepEqual(normalizeGoal({ type: 'board', specialty: 'endodontics' }, ['residency', 'board']), { type: 'board', specialty: 'endodontics' });
    assert.deepEqual(normalizeGoal({ type: 'national' }, ['residency']), ALL_EXAMS);
    assert.deepEqual(normalizeGoal(null, ['residency']), ALL_EXAMS);
    assert.deepEqual(normalizeGoal({ type: 'residency', specialty: 7 }, ['residency']), { type: 'residency', specialty: '' });
});

test('a goal reads as the exam and, for a specialty exam, the specialty', () => {
    const types = [{ key: 'residency', name: 'دستیاری' }, { key: 'board', name: 'بورد' }];
    assert.equal(goalLabel(ALL_EXAMS, types), 'همه‌ی آزمون‌ها');
    assert.equal(goalLabel({ type: 'residency', specialty: '' }, types), 'دستیاری');
    assert.equal(goalLabel({ type: 'board', specialty: 'endodontics' }, types, 'اندودانتیکس'), 'بورد · اندودانتیکس');
});

test('papers group by exam and year, and a specialty goal keeps its own papers', () => {
    const sittings = [
        { type: 'دستیاری', type_key: 'residency', year: 1404, subject_key: null },
        { type: 'بورد', type_key: 'board', year: 1404, subject_key: 'endodontics' },
        { type: 'بورد', type_key: 'board', year: 1404, subject_key: 'periodontics' },
        { type: 'ارتقا', type_key: 'promotion', year: 1405, subject_key: 'endodontics' },
    ];
    const all = groupSittings(sittings);
    assert.deepEqual(all.map((g) => `${g.type_key}:${g.year}:${g.sittings.length}`), ['promotion:1405:1', 'residency:1404:1', 'board:1404:2']);
    const endo = groupSittings(sittings, { type: 'board', specialty: 'endodontics' });
    assert.deepEqual(endo.map((g) => `${g.type_key}:${g.sittings.map((s) => s.subject_key)}`), ['board:endodontics']);
});

test('specialty exams list their specialties; a goal specialty comes first', () => {
    const sittings = [
        { type_key: 'residency', subject_key: null },
        { type_key: 'board', subject_key: 'periodontics', subject_name: 'پریودانتیکس' },
        { type_key: 'board', subject_key: 'endodontics', subject_name: 'اندودانتیکس' },
        { type_key: 'board', subject_key: 'endodontics', subject_name: 'اندودانتیکس' },
    ];
    assert.deepEqual(specialtiesByType(sittings), { board: [{ key: 'endodontics', name: 'اندودانتیکس' }, { key: 'periodontics', name: 'پریودانتیکس' }] });
    assert.deepEqual(specialtyFirst([{ key: 'a' }, { key: 'b' }], 'b').map((r) => r.key), ['b', 'a']);
    assert.deepEqual(specialtyFirst([{ key: 'a' }], '').map((r) => r.key), ['a']);
});
