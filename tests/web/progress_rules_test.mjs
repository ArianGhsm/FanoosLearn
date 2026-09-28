import { test } from 'node:test';
import assert from 'node:assert/strict';
import { direction, heatLevel, recentAverage, trendPoints, weekColumns } from '../../apps/platform/public/assets/web/pages/progress-rules.js';
import { choiceShareLabel, relativeDay, statsLines } from '../../apps/platform/public/assets/web/pages/question-stats.js';

test('a day with anything done is never blank, and the busiest day is the darkest', () => {
    assert.equal(heatLevel(0, 40), 0);
    assert.equal(heatLevel(1, 400), 1);
    assert.equal(heatLevel(40, 40), 4);
    assert.equal(heatLevel(20, 40), 2);
    assert.equal(heatLevel(5, 0), 0);
});

test('weeks start on Saturday and every column has seven cells', () => {
    // 2026-09-28 is a Monday: Saturday and Sunday before it are padding.
    const days = ['2026-09-28', '2026-09-29', '2026-09-30', '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04']
        .map((date) => ({ date }));
    const columns = weekColumns(days);
    assert.equal(columns.length, 2);
    assert.deepEqual(columns[0].slice(0, 2), [null, null]);
    assert.equal(columns[0][2].date, '2026-09-28');
    assert.equal(columns[1][0].date, '2026-10-03'); // Saturday opens the next week
    assert.ok(columns.every((column) => column.length === 7));
    assert.deepEqual(weekColumns([]), []);
});

test('the score line runs from 0% at the bottom to 100% at the top', () => {
    const [low, high] = trendPoints([0, 100], 100, 100, 10);
    assert.deepEqual(low, { x: 10, y: 90 });
    assert.deepEqual(high, { x: 90, y: 10 });
    assert.equal(trendPoints([50], 100, 100, 10)[0].x, 50);
    assert.equal(trendPoints([150], 100, 100, 10)[0].y, 10, 'scores are clamped');
});

test('direction compares the last five with the five before, ignoring small wobble', () => {
    assert.equal(direction([40, 40, 40, 40, 40, 60, 60, 60, 60, 60]), 'up');
    assert.equal(direction([60, 60, 60, 60, 60, 40, 40, 40, 40, 40]), 'down');
    assert.equal(direction([50, 50, 50, 50, 50, 51, 52, 50, 50, 51]), 'flat');
    assert.equal(direction([50, 60]), null);
    assert.equal(recentAverage([10, 20, 30]), 20);
    assert.equal(recentAverage([]), null);
});

test('question stats read "—" rather than zero when there is nothing to say', () => {
    assert.deepEqual(statsLines(null), { peer: null, attempts: null, correct: null, last: null });
    const fresh = statsLines({ peer_answered: null, peer_correct_percent: null, answered: 0, correct: 0, last_correct: null, last_answered_at: null });
    assert.equal(fresh.peer, null);
    assert.equal(fresh.correct, null);
    assert.equal(fresh.attempts, 'هنوز نه');
});

test('question stats word the student\'s record and everyone else\'s share', () => {
    const now = Date.parse('2026-09-28T12:00:00Z');
    const lines = statsLines({
        peer_answered: 12, peer_correct_percent: 64, answered: 3, correct: 2,
        last_correct: false, last_answered_at: '2026-09-25T08:00:00Z',
    }, now);
    assert.equal(lines.peer, '٪۶۴ از ۱۲ پاسخ');
    assert.equal(lines.attempts, '۳ بار');
    assert.equal(lines.correct, '۲ از ۳');
    assert.equal(lines.last, '۳ روز پیش · نادرست');
    assert.equal(relativeDay('2026-09-28T01:00:00Z', now), 'امروز');
    assert.equal(relativeDay('2026-09-27T01:00:00Z', now), 'دیروز');
    assert.equal(relativeDay('2026-09-10T01:00:00Z', now), '۲ هفته پیش');
    assert.equal(relativeDay('2026-08-01T01:00:00Z', now), '۱ ماه پیش');
    assert.equal(relativeDay('not a date', now), null);
});

test('choice shares are shown only when the server sent them', () => {
    assert.equal(choiceShareLabel(null, 0), null);
    assert.equal(choiceShareLabel({ answered: 10, percent: [70, 30] }, 1), '٪۳۰');
    assert.equal(choiceShareLabel({ answered: 10, percent: [70, 30] }, 2), null);
});
