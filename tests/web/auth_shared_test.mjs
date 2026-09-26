import { test } from 'node:test';
import assert from 'node:assert/strict';
import { currentJalaliYear, faDigits, passwordStrength, usernameProblem } from '../../apps/platform/public/assets/web/pages/auth-shared.js';

test('the username rule matches the server rule', () => {
    for (const ok of ['sara', 'sara.ahmadi', 'Sara_Ahmadi92', 'a12']) {
        assert.equal(usernameProblem(ok), '', ok);
    }
    for (const bad of ['', 'ab', '1sara', 'سارا', 'sara ahmadi', 'sara@x', 'a'.repeat(33)]) {
        assert.notEqual(usernameProblem(bad), '', bad);
    }
});

test('password strength rises with length and variety and never passes a short one', () => {
    assert.equal(passwordStrength(''), 0);
    assert.equal(passwordStrength('Ab1!'), 1);
    assert.equal(passwordStrength('abcdefgh'), 2);
    assert.equal(passwordStrength('abcdefgh1234'), 3);
    assert.equal(passwordStrength('Hello-World-2026'), 4);
});

test('entry years are Jalali and shown in Persian digits', () => {
    // 2026-09-26 is in 1405; 2026-01-10 is still 1404.
    assert.equal(currentJalaliYear(new Date(Date.UTC(2026, 8, 26))), 1405);
    assert.equal(currentJalaliYear(new Date(Date.UTC(2026, 0, 10))), 1404);
    assert.equal(faDigits(1405), '۱۴۰۵');
});
