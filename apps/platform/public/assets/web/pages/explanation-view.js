import { el, faOptionLetters, faText, icon } from './runner-view.js';
import { renderMarkdown } from './markdown.js';
import { chosenLetter, explanationParts } from './explanation-rules.js';

/*
 * One explanation, drawn in the runner's own vocabulary: the reasoning first,
 * then what the book says, one row per other choice, the exam tip and the
 * common trap as callouts, and the book line last. The same block serves the
 * learning-mode reveal, the post-exam review and the mistakes review, so a
 * student meets one shape everywhere.
 *
 * Text is untrusted API content: every part goes through renderMarkdown or
 * textContent, never innerHTML.
 */

const LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح', 'ط', 'ی'];
const fa = (text) => faText(faOptionLetters(text));

const CALLOUTS = {
    tip: { icon: 'bulb', label: 'نکته‌ی آزمون' },
    trap: { icon: 'alert', label: 'دام رایج' },
};

function head(iconName, label) {
    return el('div', { className: 'x-exp__head' }, icon(iconName), el('span', { text: label }));
}

function optionRows(options, picked) {
    const list = el('ul', { className: 'x-exp__options' });
    for (const option of options) {
        const isPicked = picked !== null && option.letter === picked;
        list.append(el('li', {
            className: `x-exp__option is-${option.verdict}${isPicked ? ' is-picked' : ''}`,
        },
        el('span', { className: 'x-exp__letter', text: fa(option.letter) }),
        el('div', { className: 'x-exp__option-text' },
            el('span', { className: 'x-exp__tag', text: option.verdict === 'true' ? 'درست است' : 'رد' }),
            isPicked ? el('span', { className: 'x-exp__tag is-picked', text: 'انتخاب تو' }) : null,
            renderMarkdown(option.text))));
    }
    return list;
}

/**
 * @param {string} markdown the explanation as BankPublisher wrote it
 * @param {{chosen?: number|null, showAnswer?: boolean}} options chosen is the
 *   0-based choice the student picked; showAnswer draws the answer line
 *   (the reveal already says it in its own head, the reviews do not).
 */
export function renderExplanation(markdown, { chosen = null, showAnswer = true } = {}) {
    const parts = explanationParts(markdown);
    const root = el('div', { className: 'x-exp' });

    if (showAnswer && parts.answer) {
        root.append(el('p', { className: 'x-exp__answer' },
            icon('check'),
            el('strong', { text: fa(`پاسخ: ${parts.answer}`) }),
            parts.note ? el('span', { className: 'x-exp__answer-note', text: fa(parts.note) }) : null));
    }
    if (parts.body) {
        root.append(el('div', { className: 'x-exp__body' }, renderMarkdown(parts.body)));
    }

    const picked = chosenLetter(LETTERS, chosen);
    for (const section of parts.sections) {
        if (section.kind === 'reference') {
            root.append(el('section', { className: 'x-exp__ref' },
                head('book', 'از رفرنس'), renderMarkdown(section.body)));
        } else if (section.kind === 'options') {
            if (parts.options.length === 0 && section.body === '') continue;
            root.append(el('section', { className: 'x-exp__others' },
                head('list', 'بررسی گزینه‌های دیگر'),
                parts.options.length ? optionRows(parts.options, picked) : null,
                section.body ? renderMarkdown(section.body) : null));
        } else {
            const callout = CALLOUTS[section.kind];
            root.append(el('aside', { className: `x-exp__callout is-${section.kind}` },
                head(callout.icon, callout.label), renderMarkdown(section.body)));
        }
    }

    if (parts.source) {
        root.append(el('p', { className: 'x-exp__source' },
            icon('book'),
            el('span', { className: 'x-exp__source-label', text: 'منبع' }),
            el('span', { className: 'x-exp__source-text', attrs: { dir: 'auto' }, text: parts.source })));
    }
    root.append(el('p', { className: 'x-explanation__origin', text: 'این توضیح با کمک هوش مصنوعی و از روی صفحه‌ی منبع رسمی نوشته شده است.' }));
    return root;
}
