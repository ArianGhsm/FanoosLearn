// A question whose official key accepts more than one option: any of them
// is right, and nothing else is.
import { test } from 'node:test';
import assert from 'node:assert/strict';

const { isAccepted } = await import('../../apps/platform/public/assets/web/pages/runner-view.js');

test('the answer alone is right when nothing else is accepted', () => {
    assert.equal(isAccepted(2, undefined, 2), true);
    assert.equal(isAccepted(2, [], 1), false);
    assert.equal(isAccepted(0, null, 0), true);
});

test('an option the key also accepts is right too', () => {
    assert.equal(isAccepted(0, [2], 2), true);
    assert.equal(isAccepted(0, [2], 0), true);
    assert.equal(isAccepted(0, [2], 1), false);
    assert.equal(isAccepted(0, [2], undefined), false);
});
