import { test } from 'node:test';
import assert from 'node:assert/strict';
import { groupDigits, rialFromTyped, tomanText } from '../../apps/platform/public/assets/web/pages/products-format.js';

test('a price typed in Tomans, in any digits or separators, becomes Rials', () => {
    assert.equal(rialFromTyped('150000'), 1500000);
    assert.equal(rialFromTyped('۱۵۰٬۰۰۰'), 1500000);
    assert.equal(rialFromTyped('١٥٠,٠٠٠'), 1500000);
    assert.equal(rialFromTyped(' 99 000 '), 990000);
});

test('anything that is not a number is refused, not guessed', () => {
    for (const bad of ['', 'رایگان', '150k', '-5000', '1.5e6']) {
        assert.equal(rialFromTyped(bad), null, bad);
    }
});

test('prices read in Tomans with Persian grouping', () => {
    assert.equal(groupDigits(150000), '۱۵۰٬۰۰۰');
    assert.equal(tomanText(1500000), '۱۵۰٬۰۰۰ تومان');
    assert.equal(tomanText(null), 'بدون قیمت');
});
