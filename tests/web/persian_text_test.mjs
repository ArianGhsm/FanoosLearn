/*
 * Persian digits inside authored text.
 *
 * Imported banks write Persian prose with Latin digits, which reads as
 * foreign, but the same sentences carry Latin terminology whose digits
 * belong to the term. Getting this wrong in either direction is visible on
 * every question, so it is worth pinning down precisely.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

globalThis.document = { createElement: () => ({ append() {}, setAttribute() {}, addEventListener() {} }) };
const { faText, faDigits } = await import('../../apps/platform/public/assets/web/pages/runner-view.js');

test('numbers standing alone in Persian text become Persian', () => {
    assert.equal(faText('خانم 40 ساله'), 'خانم ۴۰ ساله');
    assert.equal(faText('از 7 سال پیش'), 'از ۷ سال پیش');
    assert.equal(faText('(15 سال)'), '(۱۵ سال)');
});

test('digits belonging to a Latin term are left alone', () => {
    // Converting these mangles the terminology a medical question depends on.
    assert.equal(faText('ویتامین B12 پایین'), 'ویتامین B12 پایین');
    assert.equal(faText('در تصویر T2 دیده می‌شود'), 'در تصویر T2 دیده می‌شود');
    assert.equal(faText('COVID-19'), 'COVID-19');
});

test('a mixed sentence converts only the free-standing numbers', () => {
    assert.equal(
        faText('مرد 55 ساله با سطح B12 برابر 180'),
        'مرد ۵۵ ساله با سطح B12 برابر ۱۸۰',
    );
});

test('text with no digits, and empty input, come back unchanged', () => {
    assert.equal(faText('بیمار بدون علامت'), 'بیمار بدون علامت');
    assert.equal(faText(''), '');
    assert.equal(faText(null), '');
    assert.equal(faText(undefined), '');
});

test('faDigits converts unconditionally, for numbers the interface itself writes', () => {
    // Different job from faText: these are counts and positions we produce,
    // never authored text, so there is no terminology to protect.
    assert.equal(faDigits(40), '۴۰');
    assert.equal(faDigits('3 از 40'), '۳ از ۴۰');
});
