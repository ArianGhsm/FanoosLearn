import { test } from 'node:test';
import assert from 'node:assert/strict';
import { availableCount, suggestedMinutes } from '../../apps/platform/public/assets/web/pages/custom-practice-rules.js';

const options = {
    total: 30, unseen: 20, wrong: 4,
    topics: [
        { topic: 'قلب', total: 12, unseen: 8, wrong: 3 },
        { topic: 'ریه', total: 10, unseen: 7, wrong: 1 },
        { topic: '', total: 8, unseen: 5, wrong: 0 },
    ],
};

test('with no topic chosen, the whole courses count for the chosen source', () => {
    assert.equal(availableCount(options, [], 'all'), 30);
    assert.equal(availableCount(options, [], 'unseen'), 20);
    assert.equal(availableCount(options, [], 'wrong'), 4);
});

test('chosen topics add up, including the no-topic group', () => {
    assert.equal(availableCount(options, ['قلب', 'ریه'], 'all'), 22);
    assert.equal(availableCount(options, ['قلب', ''], 'wrong'), 3);
    assert.equal(availableCount(null, [], 'all'), 0);
});

test('a timed exam defaults to a minute and a half a question, rounded to five', () => {
    assert.equal(suggestedMinutes(20), 30);
    assert.equal(suggestedMinutes(5), 10);
    assert.equal(suggestedMinutes(1), 5);
});
