import { test } from 'node:test';
import assert from 'node:assert/strict';
import { fullDay, itemLabel, localInputToIso, planDay, weeks } from '../../apps/platform/public/assets/web/pages/plan-rules.js';

test('plan items read as what to do', () => {
    assert.equal(itemLabel({ kind: 'topic', subject: 'اندودانتیکس', topic: 'طول کارکرد' }), 'اندودانتیکس: طول کارکرد');
    assert.equal(itemLabel({ kind: 'reading', subject: 'پریودانتیکس' }), 'مطالعه‌ی پریودانتیکس از منابع');
    assert.ok(itemLabel({ kind: 'mock' }).startsWith('آزمون جامع'));
    assert.equal(itemLabel({ kind: 'nonsense' }), '');
    assert.equal(itemLabel(null), '');
});

test('days group into weeks of seven, the last one partial', () => {
    const days = Array.from({ length: 16 }, (_, i) => ({ day_no: i + 1 }));
    assert.deepEqual(weeks(days).map((w) => w.length), [7, 7, 2]);
    assert.deepEqual(weeks([]), []);
});

test('dates are shown in the Persian calendar', () => {
    assert.ok(fullDay('2026-10-03').includes('۱۴۰۵'), fullDay('2026-10-03'));
    assert.ok(planDay('2026-10-03').includes('مهر'), planDay('2026-10-03'));
});

test('a datetime-local value becomes an ISO instant, or nothing', () => {
    assert.match(localInputToIso('2026-10-10T18:30'), /^2026-10-1\dT\d\d:\d\d:00\.000Z$/);
    assert.equal(localInputToIso(''), '');
    assert.equal(localInputToIso('not a date'), '');
});
