/*
 * Pure rules of the small Markdown renderer (tests/web/lesson_pages_test.mjs).
 * No DOM here: markdown.js turns these runs and cells into nodes.
 */

/**
 * Semantic emphasis inside a lesson or an explanation, written
 * {{kind:text}}. Each kind has its own colour and, for readers who cannot
 * tell the colours apart, its own shape (11_STUDY_CONTENT_1406_PLAN.md §5.1,
 * 12_LESSON_FORMAT.md). The label is what a screen reader announces.
 */
export const EMPHASIS = Object.freeze({
    term: 'اصطلاح',
    key: 'نکته‌ی کلیدی',
    note: 'نکته',
    warn: 'استثنا',
});

const INLINE = /\*\*([^*]+)\*\*|\{\{(term|key|note|warn):([^{}]+)\}\}/g;

/**
 * Splits one line into runs: {kind: 'text' | 'bold' | 'term' | 'key' |
 * 'note' | 'warn', text}. Anything that is not exactly one of these forms
 * stays literal text, so a typo shows instead of disappearing.
 */
export function inlineRuns(line) {
    const text = String(line ?? '');
    const runs = [];
    let index = 0;
    let match;
    INLINE.lastIndex = 0;
    while ((match = INLINE.exec(text)) !== null) {
        if (match.index > index) runs.push({ kind: 'text', text: text.slice(index, match.index) });
        if (match[1] !== undefined) {
            runs.push({ kind: 'bold', text: match[1] });
        } else {
            const inner = match[3].trim();
            runs.push(inner === '' ? { kind: 'text', text: match[0] } : { kind: match[2], text: inner });
        }
        index = match.index + match[0].length;
    }
    if (index < text.length) runs.push({ kind: 'text', text: text.slice(index) });
    return runs;
}

/** The cells of a table row written `| a | b |`, or null if the line is not one. */
export function tableCells(line) {
    const text = String(line ?? '').trim();
    if (text.length < 2 || !text.startsWith('|') || !text.endsWith('|')) return null;
    return text.slice(1, -1).split('|').map((cell) => cell.trim());
}

/** Whether a row is the `|---|---|` line under a table's header. */
export function isTableRule(cells) {
    return Array.isArray(cells) && cells.length > 0 && cells.every((cell) => /^:?-{3,}:?$/.test(cell));
}
