/*
 * Pure rules of the lessons pages (tests/web/lessons_rules_test.mjs).
 */

const KINDS = {
    lecture_note: 'lesson', discipline_note: 'lesson',
    summary: 'summary', cheat_sheet: 'summary',
    flashcards: 'flashcards',
};

const LABELS = {
    lecture_note: 'درسنامه', discipline_note: 'درسنامه', summary: 'خلاصه', cheat_sheet: 'جمع‌بندی',
    flashcards: 'فلش‌کارت', slide_reference: 'اسلاید', audio: 'صوت', transcript: 'متن جلسه',
    past_exam: 'آزمون گذشته', question_bank: 'بانک سؤال', other: 'سایر',
};

/** The tab a resource type falls under. */
export function kindOf(typeKey) {
    return KINDS[typeKey] ?? 'other';
}

/** A resource type in Persian. */
export function typeLabel(typeKey) {
    return LABELS[typeKey] ?? 'سایر';
}

/**
 * What a lesson's stored content can be shown as: written text (a body, or
 * sections of heading + body), flashcards (front/back pairs), or nothing
 * written -- then it is a file, fetched through delivery.
 */
export function lessonShape(content) {
    if (!content || typeof content !== 'object') return { kind: 'file' };
    if (Array.isArray(content.cards)) {
        const cards = content.cards
            .filter((c) => c && typeof c.front === 'string' && typeof c.back === 'string')
            .map((c) => ({ front: c.front, back: c.back }));
        if (cards.length > 0) return { kind: 'cards', cards };
    }
    if (typeof content.body === 'string' && content.body.trim() !== '') return { kind: 'text', body: content.body };
    if (Array.isArray(content.sections)) {
        const body = content.sections
            .filter((s) => s && (typeof s.body === 'string' || typeof s.heading === 'string'))
            .map((s) => `${s.heading ? `## ${s.heading}\n` : ''}${s.body ?? ''}`)
            .join('\n\n');
        if (body.trim() !== '') return { kind: 'text', body };
    }
    return { kind: 'file' };
}

/** Flashcards as written in the composer: one card per line, "front :: back". */
export function parseCards(text) {
    return String(text ?? '')
        .split('\n')
        .map((line) => line.split('::'))
        .filter((parts) => parts.length >= 2 && parts[0].trim() !== '' && parts.slice(1).join('::').trim() !== '')
        .map((parts) => ({ front: parts[0].trim(), back: parts.slice(1).join('::').trim() }));
}
