// اتاق مطالعه گروهی: invite links and the wording of a member's day.
import { test } from 'node:test';
import assert from 'node:assert/strict';

const { answersText, codeFrom, inviteLink, minutesText } = await import('../../apps/platform/public/assets/web/pages/rooms-rules.js');

test('an invite link carries the code, and a pasted link gives it back', () => {
    const link = inviteLink('https://fanooslearn.ir', 'abcd2345wxyz');
    assert.equal(link, 'https://fanooslearn.ir/app/rooms?join=abcd2345wxyz');
    assert.equal(codeFrom(link), 'abcd2345wxyz');
    assert.equal(codeFrom('  ABCD2345WXYZ '), 'abcd2345wxyz');
    assert.equal(codeFrom(''), '');
});

test('study time reads in hours and minutes', () => {
    assert.equal(minutesText(0), 'هنوز نخوانده');
    assert.equal(minutesText(45), '۴۵ دقیقه');
    assert.equal(minutesText(120), '۲ ساعت');
    assert.equal(minutesText(80), '۱ ساعت و ۲۰ دقیقه');
});

test('answers are shown only when there are some', () => {
    assert.equal(answersText({ answered: 0, correct: 0 }), '');
    assert.equal(answersText({ answered: 40, correct: 31 }), '۴۰ سؤال، ۳۱ درست');
});
