/*
 * The boxes above a question: which exam and year it was asked in, its
 * subject and topic, and the reference chapter it comes from (owner,
 * 2026-10-11 -- "نوع آزمون و سالش و فصل رفرنس هم نوشته بشه"). Pure: no
 * DOM, so it is tested directly.
 *
 * `question.bank` is sent by the server for a bank question only
 * (BankQuestionFacts); its chapter is already filtered to what may be shown
 * as fact, so a missing chapter means "not classified yet" and nothing is
 * drawn for it -- never a guess.
 */

export const faDigits = (value) => String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);

/** ملی is held twice a year; its round is the month, not a number. */
const NATIONAL_ROUNDS = { 1: 'مرداد', 2: 'دی' };

const text = (value) => (typeof value === 'string' ? value.trim() : '');

/**
 * @returns {{exam: ?string, number: ?string, subject: ?string, topic: ?string,
 *   chapter: ?string, chapterEn: ?string, book: ?string}}
 */
export function questionFacts(question) {
    const bank = question && typeof question.bank === 'object' ? question.bank : null;
    const subject = text(bank?.subject) || text((Array.isArray(question?.tags) ? question.tags : [])[0]) || null;
    // A bank question's topic falls back to its subject: shown once.
    const topic = text(question?.topic);

    let exam = null;
    if (bank && text(bank.exam) !== '' && Number.isInteger(bank.year)) {
        exam = `${text(bank.exam)} ${faDigits(bank.year)}`;
        if (bank.exam_key === 'national' && NATIONAL_ROUNDS[bank.round]) exam += ` · ${NATIONAL_ROUNDS[bank.round]}`;
    }

    let chapter = null;
    let chapterEn = null;
    let book = null;
    const c = bank?.chapter;
    if (c && typeof c === 'object' && text(c.title) !== '') {
        const label = text(c.number) !== '' ? `فصل ${faDigits(text(c.number))}` : 'فصل';
        const fa = text(c.title_fa);
        chapter = `${label} · ${fa || text(c.title)}`;
        chapterEn = fa ? text(c.title) : null;
        book = [text(c.book), text(c.edition)].filter((part) => part !== '').join(' · ') || null;
    }

    return {
        exam,
        number: bank && Number.isInteger(bank.number) ? `سؤال ${faDigits(bank.number)} دفترچه` : null,
        subject,
        topic: topic !== '' && topic !== subject ? topic : null,
        chapter,
        chapterEn,
        book,
    };
}
