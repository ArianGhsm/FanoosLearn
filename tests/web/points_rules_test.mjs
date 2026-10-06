// امتیاز روزانه: the wording and arithmetic the points page shows.
import { test } from 'node:test';
import assert from 'node:assert/strict';

const { barHeights, goalLine, goalPercent, rankLine, ruleLines } = await import('../../apps/platform/public/assets/web/pages/points-rules.js');

test('the goal share is clamped to 0-100', () => {
    assert.equal(goalPercent(250, 500), 50);
    assert.equal(goalPercent(900, 500), 100);
    assert.equal(goalPercent(0, 0), 0);
});

test('the goal line says what is left, or that it is done', () => {
    assert.equal(goalLine(0, 500), 'هنوز امتیازی نگرفته‌ای؛ هدف امروز ۵۰۰ امتیاز است');
    assert.equal(goalLine(320, 500), '۱۸۰ امتیاز تا هدف امروز');
    assert.equal(goalLine(510, 500), 'به هدف امروز رسیدی — ۵۱۰ امتیاز');
});

test('a place is shown only with points of one\'s own', () => {
    assert.equal(rankLine({ rank: 3, active: 45 }), 'رتبه‌ی ۳ از ۴۵ نفر');
    assert.equal(rankLine({ rank: null, active: 45 }), 'رتبه‌ای نداری · ۴۵ نفر امتیاز گرفته‌اند');
    assert.equal(rankLine({ rank: null, active: 0 }), 'هنوز کسی امتیاز نگرفته');
});

test('bars are scaled to the largest value, and flat when there is nothing', () => {
    assert.deepEqual(barHeights([0, 50, 100]), [0, 50, 100]);
    assert.deepEqual(barHeights([0, 0]), [0, 0]);
});

test('the rules use the server\'s numbers', () => {
    const lines = ruleLines({ goal: 500, points_per_answer: { easy: 5, medium: 10, hard: 20 } });
    assert.ok(lines[0].includes('۲۰'));
    assert.ok(lines.some((line) => line.includes('۵۰۰')));
});
