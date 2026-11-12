/*
 * The parts of an explanation, read from the Markdown BankPublisher writes.
 *
 * BankPublisher::explanation() turns the structured row (short answer, the
 * reference, why each other choice is out, the tip, the trap, the source)
 * into one Markdown string with fixed headings. The runner draws those parts
 * as separate blocks -- the reasoning, a quoted reference, one row per other
 * choice, a tip and a trap callout, the book line -- so this module finds
 * them again. Pure: no DOM, so node --test can check it.
 *
 * Anything it does not recognise stays in the reasoning as plain Markdown,
 * so an older or hand-written explanation still renders in full.
 */

export const SECTION_KINDS = Object.freeze({
    'از رفرنس': 'reference',
    'چرا بقیه نه': 'options',
    'نکته‌ی آزمون': 'tip',
    'دام رایج': 'trap',
});

const ANSWER = /^\*\*پاسخ:\s*(.+?)\*\*\s*(.*)$/;
const SOURCE = /^\*\*منبع:\*\*\s*(.+)$/;
const HEADING = /^##\s+(.+?)\s*$/;
const OPTION = /^[-*]\s+\*\*(.+?):\*\*\s*(.*)$/;
// A why_wrong line that opens with «درست» belongs to a negative question
// («همه صحیح است بجز»): the choice is a true statement, which is exactly
// why it is not the answer. The row is drawn as "true", not as "rejected".
const TRUE_STATEMENT = /^درست\s*[—–-]\s*/;

/**
 * @returns {{answer: string|null, note: string, body: string,
 *   sections: Array<{kind: string, title: string, body: string}>,
 *   options: Array<{letter: string, text: string, verdict: 'true'|'wrong'}>,
 *   source: string|null}}
 */
export function explanationParts(markdown) {
    const parts = { answer: null, note: '', body: '', sections: [], options: [], source: null };
    const lines = String(markdown || '').replace(/\r\n/g, '\n').split('\n');
    const body = [];
    let current = null;

    for (const raw of lines) {
        const line = raw.trim();
        if (parts.answer === null && body.length === 0 && current === null) {
            const answer = ANSWER.exec(line);
            if (answer) {
                parts.answer = answer[1].trim();
                parts.note = answer[2].trim();
                continue;
            }
        }
        const source = SOURCE.exec(line);
        if (source) {
            parts.source = source[1].trim();
            current = null;
            continue;
        }
        const heading = HEADING.exec(line);
        if (heading && SECTION_KINDS[heading[1]]) {
            current = { kind: SECTION_KINDS[heading[1]], title: heading[1], lines: [] };
            parts.sections.push(current);
            continue;
        }
        if (heading) {
            // An unknown heading ends any known section and stays in the body.
            current = null;
        }
        if (current && current.kind === 'options') {
            const option = OPTION.exec(line);
            if (option) {
                const text = option[2].trim();
                parts.options.push({
                    letter: option[1].trim(),
                    text: text.replace(TRUE_STATEMENT, ''),
                    verdict: TRUE_STATEMENT.test(text) ? 'true' : 'wrong',
                });
                continue;
            }
        }
        (current ? current.lines : body).push(raw);
    }

    parts.body = body.join('\n').trim();
    parts.sections = parts.sections
        .map(({ kind, title, lines: own }) => ({ kind, title, body: own.join('\n').trim() }))
        .filter((section) => section.kind === 'options' || section.body !== '');
    return parts;
}

/** The letter the student picked, as the options section writes it. */
export function chosenLetter(letters, chosen) {
    return Number.isInteger(chosen) && chosen >= 0 ? (letters[chosen] ?? null) : null;
}
