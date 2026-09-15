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

test('digits that are part of an identifier are left alone', () => {
    // A Latin letter before the digits names the thing; converting mangles it.
    assert.equal(faText('ویتامین B12 پایین'), 'ویتامین B12 پایین');
    assert.equal(faText('در تصویر T2 دیده می‌شود'), 'در تصویر T2 دیده می‌شود');
    assert.equal(faText('COVID-19'), 'COVID-19');
});

test('a measurement converts even though its unit is Latin', () => {
    // These are the real content: the digits are a value and the Latin
    // letters after them are the unit. An earlier rule kept both Latin and
    // so left every lab value in the bank unconverted.
    assert.equal(faText('AST: 100IU/L'), 'AST: ۱۰۰IU/L');
    assert.equal(faText('Bili T: 6.2mg/dl'), 'Bili T: ۶.۲mg/dl');
    assert.equal(faText('Hb: 6gr/dl'), 'Hb: ۶gr/dl');
    assert.equal(faText('Alp:753U/l'), 'Alp:۷۵۳U/l');
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

test('an explanation names the option the way the interface labels it', async () => {
    const { faOptionLetters } = await import('../../apps/platform/public/assets/web/pages/runner-view.js');

    // Left alone, the page marks ج correct while the explanation underneath
    // argues for C, and the student has to work out they are the same.
    assert.equal(faOptionLetters('گزینه C صحیح است.'), 'گزینه ج صحیح است.');
    assert.equal(faOptionLetters('گزینه‌ی A نادرست است'), 'گزینه‌ی الف نادرست است');
    assert.equal(faOptionLetters('رد گزینه B:'), 'رد گزینه ب:');

    // A Latin letter that is part of the medicine must survive: this fires
    // only after the word گزینه, never on a bare letter.
    assert.equal(faOptionLetters('هپاتیت C مزمن'), 'هپاتیت C مزمن');
    assert.equal(faOptionLetters('ویتامین B خوراکی'), 'ویتامین B خوراکی');
});
