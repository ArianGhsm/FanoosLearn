import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fold, matches, share, sittingLabel, yearSpan } from '../../apps/platform/public/assets/web/pages/bank-rules.js';
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
