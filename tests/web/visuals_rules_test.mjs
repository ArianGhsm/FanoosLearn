import { test } from 'node:test';
import assert from 'node:assert/strict';
import { inline, parseMarkdown, treeSize } from '../../apps/platform/public/assets/web/pages/visuals-rules.js';

test('bold parts of a line are found, nothing else is interpreted', () => {
    assert.deepEqual(inline('طول **کارکرد** تا <b>اپکس</b>'), [
        { text: 'طول ', bold: false }, { text: 'کارکرد', bold: true }, { text: ' تا <b>اپکس</b>', bold: false },
    ]);
});

test('a capsule reads as headings, lists, tables and paragraphs', () => {
    const blocks = parseMarkdown([
        '# طول کارکرد',
        'تنگی اپیکالی', 'محل ایده‌آل است.',
        '',
        '- آپکس رادیوگرافیک',
        '- فورامن',
        '1. اول',
        '2. دوم',
        '| روش | دقت |',
        '|---|---|',
        '| آپکس‌یاب | بالا |',
    ].join('\n'));
    assert.deepEqual(blocks.map((b) => b.type), ['heading', 'paragraph', 'list', 'list', 'table']);
    assert.equal(blocks[1].text, 'تنگی اپیکالی محل ایده‌آل است.');
    assert.equal(blocks[2].ordered, false);
    assert.deepEqual(blocks[3], { type: 'list', ordered: true, items: ['اول', 'دوم'] });
    assert.deepEqual(blocks[4], { type: 'table', header: true, rows: [['روش', 'دقت'], ['آپکس‌یاب', 'بالا']] });
});

test('a mind map counts its nodes and depth', () => {
    const tree = { text: 'ریشه', children: [{ text: 'الف', children: [{ text: 'الف-۱' }] }, { text: 'ب' }] };
    assert.deepEqual(treeSize(tree), { nodes: 4, depth: 3 });
    assert.deepEqual(treeSize({ text: 'تنها' }), { nodes: 1, depth: 1 });
});
