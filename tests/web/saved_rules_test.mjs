import { test } from 'node:test';
import assert from 'node:assert/strict';
import { groupByTopic, highlightCards } from '../../apps/platform/public/assets/web/pages/saved-rules.js';

test('saved questions group by topic, biggest first, untopiced last, order kept', () => {
    const groups = groupByTopic([
        { id: 1, topic: 'الف' },
        { id: 2, topic: null },
        { id: 3, topic: 'ب' },
        { id: 4, topic: 'ب' },
        { id: 5, topic: '  ' },
    ]);
    assert.deepEqual(groups.map((g) => g.topic), ['ب', 'الف', null]);
    assert.deepEqual(groups[0].items.map((i) => i.id), [3, 4]);
    assert.deepEqual(groups[2].items.map((i) => i.id), [2, 5]);
    assert.deepEqual(groupByTopic([]), []);
});

test('each highlighted phrase becomes a card with its question behind it', () => {
    const cards = highlightCards([
        { fragments: ['تنگه‌ی اپیکال', '  '], preview: 'سؤال ۱', topic: 'اندو', assessment_title: 'دستیاری ۱۴۰۴' },
        { fragments: [], preview: 'سؤال ۲' },
        { preview: 'سؤال ۳' },
    ]);
    assert.deepEqual(cards, [{ front: 'تنگه‌ی اپیکال', back: 'سؤال ۱', topic: 'اندو', source: 'دستیاری ۱۴۰۴' }]);
});
