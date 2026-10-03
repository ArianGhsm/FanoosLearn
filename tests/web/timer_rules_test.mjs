import { test } from 'node:test';
import assert from 'node:assert/strict';
import { clock, elapsedMinutes, nextPhase, remaining } from '../../apps/platform/public/assets/web/pages/timer-rules.js';
import { studyTime } from '../../apps/platform/public/assets/web/pages/progress-rules.js';
import { faDigits } from '../../apps/platform/public/assets/web/pages/question-stats.js';

test('the clock reads minutes and seconds in Persian digits and never goes negative', () => {
    assert.equal(clock(25 * 60, faDigits), '۲۵:۰۰');
    assert.equal(clock(309, faDigits), '۰۵:۰۹');
    assert.equal(clock(0.2, faDigits), '۰۰:۰۱');
    assert.equal(clock(-5, faDigits), '۰۰:۰۰');
});

test('time left is measured from the end instant, so a sleeping tab is still right', () => {
    assert.equal(remaining(10_000, 4_000), 6);
    assert.equal(remaining(10_000, 20_000), 0);
});

test('a block stopped early records whole minutes, never more than the block', () => {
    assert.equal(elapsedMinutes(0, 59_000, 25), 0);
    assert.equal(elapsedMinutes(0, 12 * 60_000 + 30_000, 25), 12);
    assert.equal(elapsedMinutes(0, 90 * 60_000, 25), 25);
    assert.equal(nextPhase('focus'), 'break');
    assert.equal(nextPhase('break'), 'focus');
});

test('study time reads in hours and minutes', () => {
    assert.equal(studyTime(0, faDigits), '—');
    assert.equal(studyTime(45, faDigits), '۴۵ دقیقه');
    assert.equal(studyTime(120, faDigits), '۲ ساعت');
    assert.equal(studyTime(130, faDigits), '۲ ساعت و ۱۰ دقیقه');
});
