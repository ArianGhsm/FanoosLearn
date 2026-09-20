/*
 * Rendering for the exam runner. Pure DOM construction from state -- no
 * network, no state mutation. Every handler is passed in, so the same views
 * can be driven by a test without a server.
 *
 * Nothing here uses innerHTML with data that came from the API. Question and
 * choice text is written through textContent, always.
 */
import {
    ATTEMPT_FILTERS, answeredCount, isAnswered, isExplanationShown, isFlagged, isStruck, isTimeCritical,
    progressPercent, remainingSeconds, unansweredPositions, visiblePositions,
} from './runner-state.js';
import { renderMarkdown } from './markdown.js';
import { highlightSegments } from './runner-study.js';

const CHOICE_LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح', 'ط', 'ی'];

const FILTER_LABELS = {
    all: 'همه',
    answered: 'پاسخ‌داده',
    unanswered: 'بی‌پاسخ',
    flagged: 'نشان‌دار',
};

const PERSIAN_DIGITS = '۰۱۲۳۴۵۶۷۸۹';

/** Persian digits, for numbers this interface produces itself. */
export function faDigits(value) {
    return String(value).replace(/[0-9]/g, (digit) => PERSIAN_DIGITS[Number(digit)]);
}

/**
 * Persian digits inside authored text -- a question, a choice, an
 * explanation.
 *
 * Not the same job as faDigits. Imported banks write Persian prose with
 * Latin digits ("خانم 40 ساله"), which reads as foreign to this audience,
 * so the site converts them rather than anyone editing content by hand.
 *
 * One narrow exception, narrower than it first looks. A Latin letter
 * *before* the digits makes an identifier -- B12, T2, COVID-19 -- where the
 * digits are part of the name and converting mangles it. A Latin letter
 * *after* the digits is a unit: in "AST: 100IU/L" or "Bili T: 6.2mg/dl" the
 * number is a measurement that belongs in Persian while the unit stays
 * Latin.
 *
 * An earlier version treated both sides alike and so left every lab value in
 * Latin -- 66 of them in the first imported bank, which is every such case
 * in it, against not one real identifier. Guarding a hypothesis at the cost
 * of the actual content.
 */
export function faText(value) {
    const text = String(value ?? '');
    return text.replace(/[0-9]+/g, (digits, index) =>
        precededByLatinIdentifier(text, index) ? digits : faDigits(digits));
}

/**
 * Whether a Latin letter runs into the digits from the left, allowing for
 * the characters that join an identifier together (B-12, COVID-19).
 */
function precededByLatinIdentifier(text, start) {
    let index = start - 1;
    while (index >= 0 && '-_.'.includes(text[index])) index--;

    return index >= 0 && /[A-Za-z]/.test(text[index]);
}

const OPTION_LETTER_FA = { A: 'الف', B: 'ب', C: 'ج', D: 'د', E: 'ه' };

/**
 * Rewrites "گزینه C" to "گزینه ج" in authored text.
 *
 * Explanations name the option they are about using the Latin letter the
 * bank was authored with, while the interface labels choices الف/ب/ج/د as
 * Iranian exams do. Left alone the two contradict each other on screen --
 * the page marks ج correct while the explanation underneath argues for C --
 * and the student has to work out that they are the same thing.
 *
 * Rewriting the label is safe where replacing every stray Latin letter would
 * not be: it only fires after the word گزینه, so a Latin letter that is part
 * of the medicine (vitamin B, hepatitis C) is never touched.
 */
export function faOptionLetters(value) {
    return String(value ?? '').replace(
        /(گزینه[‌\s]*(?:ی[‌\s]*)?)([A-E])\b/gu,
        (whole, prefix, letter) => prefix + (OPTION_LETTER_FA[letter] ?? letter),
    );
}

export function el(tag, options = {}, ...children) {
    const node = document.createElement(tag);
    if (options.className) node.className = options.className;
    if (options.text !== undefined) node.textContent = options.text;
    if (options.type) node.type = options.type;
    for (const [name, value] of Object.entries(options.attrs || {})) {
        if (value === null || value === undefined || value === false) continue;
        node.setAttribute(name, value === true ? '' : String(value));
    }
    for (const [event, handler] of Object.entries(options.on || {})) {
        node.addEventListener(event, handler);
    }
    node.append(...children.filter(Boolean));
    return node;
}


/*
 * Inline SVG rather than a glyph. Symbols like U+2690 and U+25A6 are absent
 * from the Persian faces this site ships, so the browser substitutes -- an
 * outlined flag came out as a literal "P" on the first render. An inline
 * path cannot be substituted.
 */
const ICONS = {
    grid: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    flag: 'M5 3v18M5 4h11l-2 3 2 3H5',
    // A hex nut with a hole -- reads as "settings/tools" without relying on
    // U+2699, which the note above already explains gets substituted.
    settings: 'M18.93 16L12 20L5.07 16L5.07 8L12 4L18.93 8ZM12 9a3 3 0 100 6 3 3 0 000-6z',
    clock: 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l4 2',
};

export function icon(name, { filled = false } = {}) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', '16');
    svg.setAttribute('height', '16');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', ICONS[name]);
    path.setAttribute('fill', filled ? 'currentColor' : 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '1.8');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('stroke-linecap', 'round');
    svg.append(path);
    return svg;
}

export function notice(kind, title, message, action) {
    return el('div', { className: `f-notice f-notice--${kind}` },
        el('div', { className: 'f-notice__body' },
            el('div', { className: 'f-notice__title', text: title }),
            message ? el('p', { text: message }) : null,
            action ? el('button', {
                className: 'f-btn f-btn--ghost', type: 'button', text: action.label,
                on: { click: action.onClick },
            }) : null));
}

/** The card shown before an attempt starts, or to resume one. */
export function renderIntro(assessment, actions) {
    const used = Number(assessment.attempts_used || 0);
    const max = Number(assessment.max_attempts || 0);
    const resumable = Boolean(assessment.active_attempt_id);
    const exhausted = !resumable && max > 0 && used >= max;

    const facts = el('dl', { className: 'x-intro__facts' });
    const addFact = (label, value) => {
        facts.append(el('div', {}, el('dt', { text: label }), el('dd', { text: value })));
    };
    if (assessment.course_title) addFact('درس', String(assessment.course_title));
    if (assessment.term_name) addFact('ترم', String(assessment.term_name));
    if (max > 0) addFact('تلاش', `${faDigits(used)} از ${faDigits(max)}`);
    if (Number(assessment.time_limit_minutes || 0) > 0) addFact('زمان', `${faDigits(assessment.time_limit_minutes)} دقیقه`);

    return el('div', { className: 'f-card x-intro' },
        el('div', { className: 'x-intro__head-row' },
            el('p', { className: 'x-intro__eyebrow', text: kindLabel(assessment.assessment_kind) }),
            el('button', {
                className: 'x-settings-trigger', type: 'button',
                attrs: { 'aria-label': 'تنظیمات' },
                on: { click: actions.openSettings },
            }, icon('settings'), el('span', { text: 'تنظیمات' }))),
        el('h1', { className: 'x-intro__title', text: String(assessment.title || 'آزمون') }),
        facts,
        el('div', { className: 'x-intro__notes' },
            el('p', { className: 'f-muted', text: 'سؤال‌ها یکی‌یکی از سرور می‌آیند. پاسخ‌هایت خودکار ذخیره می‌شود، پس اگر اینترنت لحظه‌ای قطع شود چیزی از دست نمی‌رود.' }),
            el('p', { className: 'f-muted', text: 'پاسخ درست و توضیح هر سؤال فقط بعد از ثبت نهایی نشان داده می‌شود.' })),
        exhausted
            ? notice('warning', 'سقف تلاش‌ها استفاده شده است', 'برای این آزمون تلاش تازه‌ای باقی نمانده.')
            : (resumable
                ? el('button', {
                    className: 'f-btn f-btn--primary x-intro__start', type: 'button', text: 'ادامه آزمون',
                    on: { click: () => actions.start(null) },
                })
                : el('div', { className: 'x-intro__modes' },
                    el('button', {
                        className: 'x-mode', type: 'button',
                        on: { click: () => actions.start('learning') },
                    },
                        el('strong', { text: 'یادگیری' }),
                        el('span', { className: 'f-muted', text: 'هر وقت خواستی پاسخ و توضیح همان سؤال را ببین.' })),
                    el('button', {
                        className: 'x-mode', type: 'button',
                        on: { click: () => actions.start('practice') },
                    },
                        el('strong', { text: 'تمرین' }),
                        el('span', { className: 'f-muted', text: 'بلافاصله می‌فهمی درست گفتی یا نه؛ توضیح را هر وقت خواستی ببین.' })),
                    el('button', {
                        className: 'x-mode', type: 'button',
                        on: { click: () => actions.start('assessment') },
                    },
                        el('strong', { text: 'آزمون' }),
                        el('span', { className: 'f-muted', text: 'مثل جلسه‌ی واقعی؛ پاسخ درست را بعد از ثبت می‌بینی.' })))),
        used > 0 ? el('button', {
            className: 'f-btn f-btn--ghost x-intro__history', type: 'button', text: 'تاریخچه‌ی تلاش‌ها',
            on: { click: actions.openHistory },
        }) : null,
        el('p', { className: 'x-intro__back' },
            el('a', { attrs: { href: '/app/exams' }, text: '‹ بازگشت به فهرست آزمون‌ها' })));
}

export function kindLabel(kind) {
    if (kind === 'practice') return 'تمرین';
    if (kind === 'mock_exam') return 'آزمون آزمایشی';
    if (kind === 'past_exam') return 'آزمون گذشته';
    return 'آزمون';
}

const ATTEMPT_MODE_LABELS = { assessment: 'آزمون', learning: 'یادگیری', practice: 'تمرین' };

function historyRow(attempt) {
    const total = Number(attempt.question_count || 0);
    const percent = total === 0 ? 0 : Math.round((Number(attempt.correct_count || 0) / total) * 100);
    const date = attempt.submitted_at ? new Date(attempt.submitted_at) : null;
    const dateLabel = date && !Number.isNaN(date.getTime())
        ? faDigits(new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(date))
        : '';
    return el('li', { className: 'x-history__row' },
        el('span', { className: 'x-history__score', text: `٪${faDigits(percent)}` }),
        el('div', { className: 'x-history__meta' },
            el('span', { text: `${faDigits(attempt.correct_count)} از ${faDigits(total)} پاسخ درست` }),
            el('span', { className: 'f-tiny', text: `${ATTEMPT_MODE_LABELS[attempt.mode] ?? attempt.mode} · ${dateLabel}` })));
}

/** تاریخچه‌ی تلاش‌ها: this student's own past scored attempts, newest first. */
export function renderHistory(attempts, actions, { loading = false } = {}) {
    const body = loading
        ? el('p', { className: 'f-muted', text: 'در حال گرفتن تاریخچه…' })
        : (attempts.length === 0
            ? el('p', { className: 'f-muted', text: 'هنوز تلاشی ثبت نشده.' })
            : el('ul', { className: 'x-history__list' }, ...attempts.map((attempt) => historyRow(attempt))));

    return el('div', { className: 'x-dialog', attrs: { role: 'dialog', 'aria-modal': 'true', 'aria-label': 'تاریخچه‌ی تلاش‌ها', tabindex: '-1' } },
        el('div', { className: 'x-dialog__panel x-dialog__panel--narrow' },
            el('header', { className: 'x-dialog__head' },
                el('h2', { text: 'تاریخچه‌ی تلاش‌ها' }),
                el('button', { className: 'x-dialog__close', type: 'button', text: '✕', attrs: { 'aria-label': 'بستن' }, on: { click: actions.close } })),
            body));
}

/** mm:ss, Persian digits, seconds zero-padded. */
function formatRemaining(totalSeconds) {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${faDigits(minutes)}:${faDigits(String(seconds).padStart(2, '0'))}`;
}

/** The bar pinned to the top of a running attempt. */
function renderTopBar(state, saveStatus, actions) {
    const percent = progressPercent(state);
    const remaining = remainingSeconds(state);
    return el('div', { className: 'x-bar' },
        el('button', {
            className: 'x-bar__map', type: 'button',
            attrs: { 'aria-label': 'نقشه سؤال‌ها' },
            on: { click: actions.openMap },
        }, icon('grid'), el('span', { text: 'نقشه' })),
        el('div', { className: 'x-bar__progress' },
            el('div', { className: 'x-bar__progress-track' },
                el('div', {
                    className: 'x-bar__progress-fill',
                    attrs: { style: `width:${percent}%` },
                })),
            el('span', {
                className: 'x-bar__progress-label',
                text: `${faDigits(answeredCount(state))} از ${faDigits(state.questionCount)} پاسخ‌داده`,
                attrs: { role: 'status' },
            })),
        remaining === null ? null : el('span', {
            className: `x-bar__timer${isTimeCritical(state) ? ' is-critical' : ''}`,
            attrs: { role: 'timer', 'aria-live': 'polite', 'aria-label': 'زمان باقی‌مانده' },
        }, icon('clock'), el('span', { text: formatRemaining(remaining) })),
        el('span', {
            className: `x-bar__save is-${state.submitQueued ? 'queued' : saveStatus}`,
            text: state.submitQueued ? 'ثبت آزمون آفلاین در صف ماند — به‌محض اتصال دوباره ارسال می‌شود' : saveLabel(saveStatus),
            attrs: { role: 'status' },
        }),
        el('button', {
            className: 'x-bar__settings', type: 'button',
            attrs: { 'aria-label': 'تنظیمات' },
            on: { click: actions.openSettings },
        }, icon('settings')),
        el('button', {
            className: 'f-btn f-btn--ghost x-bar__leave', type: 'button', text: 'خروج',
            attrs: { 'aria-label': 'خروج از آزمون بدون ثبت' },
            on: { click: actions.leave },
        }));
}

function saveLabel(status) {
    if (status === 'saving') return 'در حال ذخیره…';
    if (status === 'saved') return 'ذخیره شد';
    if (status === 'pending') return 'ذخیره‌نشده';
    if (status === 'offline') return 'آفلاین — در حافظه نگه داشته شد';
    if (status === 'failed') return 'ذخیره نشد';
    return '';
}

/** One question with its choices. */
export function renderQuestion(state, question, saveStatus, actions, reveal = null, study = null) {
    const position = state.position;
    const selected = state.answers[String(position)];

    const choices = el('ul', { className: 'x-choices', attrs: { role: 'radiogroup', 'aria-label': 'گزینه‌ها' } });
    (question.choices || []).forEach((choice, index) => {
        const struck = isStruck(state, position, index);
        const isSelected = selected === index;
        const shownCorrect = reveal !== null && reveal.answer === index;
        const shownWrong = reveal !== null && isSelected && reveal.answer !== index;
        choices.append(el('li', { className: 'x-choices__item' },
            el('button', {
                className: `x-choice${isSelected ? ' is-selected' : ''}${struck ? ' is-struck' : ''}${shownCorrect ? ' is-correct' : ''}${shownWrong ? ' is-wrong' : ''}`,
                type: 'button',
                attrs: { role: 'radio', 'aria-checked': isSelected ? 'true' : 'false' },
                on: { click: () => (reveal === null ? actions.choose(index) : undefined) },
            },
                el('span', { className: 'x-choice__letter', text: CHOICE_LETTERS[index] ?? faDigits(index + 1) }),
                el('span', { className: 'x-choice__text', text: faText(choice) })),
            el('button', {
                className: 'x-choice__strike', type: 'button',
                text: struck ? '↺' : '✕',
                attrs: {
                    'aria-label': struck
                        ? `برگرداندن گزینه ${CHOICE_LETTERS[index] ?? index + 1}`
                        : `خط زدن گزینه ${CHOICE_LETTERS[index] ?? index + 1}`,
                    title: struck ? 'برگرداندن' : 'این گزینه را خط بزن',
                },
                on: { click: () => actions.strike(index) },
            })));
    });

    const flagged = isFlagged(state, position);

    const card = el('article', {
        className: 'f-card x-question__card',
        on: {
            wheel: actions.cardWheel,
            touchstart: actions.cardTouchStart,
            touchmove: actions.cardTouchMove,
            touchend: actions.cardTouchEnd,
        },
    },
        el('header', { className: 'x-question__head' },
            el('span', { className: 'x-question__index', text: `سؤال ${faDigits(position)} از ${faDigits(state.questionCount)}` }),
            el('button', {
                className: `x-flag${flagged ? ' is-on' : ''}`, type: 'button',
                attrs: { 'aria-pressed': flagged ? 'true' : 'false' },
                on: { click: actions.toggleFlag },
            }, icon('flag', { filled: flagged }), el('span', { text: flagged ? 'نشان‌دار' : 'نشان‌دار کن' }))),
        renderQuestionMeta(question),
        renderStem(question, study),
        choices,
        el('div', { className: 'x-question__foot' },
            selected === undefined ? null : el('button', {
                className: 'x-question__clear', type: 'button', text: 'پاک کردن پاسخ',
                on: { click: actions.clear },
            }),
            state.mode !== 'learning' || reveal ? null : el('button', {
                className: 'f-btn f-btn--ghost x-question__reveal', type: 'button', text: 'بلد نیستم، پاسخ را نشانم بده',
                on: { click: actions.reveal },
            })),
        reveal ? renderReveal(reveal, question, selected, {
            // Learning always shows the explanation alongside the verdict;
            // practice tells you right/wrong at once but keeps the
            // explanation behind a tap, so the two don't blur into the same
            // mode.
            explanationVisible: state.mode !== 'practice' || isExplanationShown(state, position),
            onShowExplanation: actions.showExplanation,
        }) : null,
        renderStudy(question, study, actions));

    // The end gutter stays empty: اسکرول عمودی کنار سؤال. There is nothing
    // else to scroll in it, so a vertical wheel there always navigates --
    // CSS hides it below the width where there is no real margin to put it
    // in. The start gutter is now the rail, which scrolls itself, so the
    // wheel binding cannot live there without the two fighting.
    const marginEnd = el('div', { className: 'x-question__margin', attrs: { 'aria-hidden': 'true' }, on: { wheel: actions.marginWheel } });

    return el('div', { className: 'x-question' },
        renderTopBar(state, saveStatus, actions),
        el('div', { className: 'x-question__stage' }, renderRail(state, actions), card, marginEnd),
        el('nav', { className: 'x-nav', attrs: { 'aria-label': 'پیمایش سؤال‌ها' } },
            el('button', {
                className: 'f-btn f-btn--ghost', type: 'button', text: '→ قبلی',
                attrs: { disabled: position <= 1 },
                on: { click: actions.previous },
            }),
            el('button', {
                className: 'f-btn f-btn--ghost x-nav__gap', type: 'button', text: 'اولین بی‌پاسخ',
                attrs: { disabled: unansweredPositions(state).length === 0 },
                on: { click: actions.firstUnanswered },
            }),
            position >= state.questionCount
                ? el('button', { className: 'f-btn f-btn--primary', type: 'button', text: 'پایان و ثبت', on: { click: actions.requestSubmit } })
                : el('button', { className: 'f-btn f-btn--primary', type: 'button', text: 'بعدی ←', on: { click: actions.next } })));
}


/**
 * The revealed answer, shown in place under the question during a learning
 * attempt. It says plainly that this one was seen, because the report will
 * say so too and the student should not be surprised by that later.
 */
function renderReveal(reveal, question, chosen, { explanationVisible = true, onShowExplanation = null } = {}) {
    const letter = CHOICE_LETTERS[reveal.answer] ?? faDigits(reveal.answer + 1);
    const verdict = chosen === null || chosen === undefined
        ? `پاسخ درست: گزینه ${letter}`
        : (chosen === reveal.answer ? `درست گفتی — گزینه ${letter}` : `نادرست. پاسخ درست گزینه ${letter} است.`);

    const explanationBody = !explanationVisible
        ? el('button', {
            className: 'f-btn f-btn--ghost x-revealed__show-explanation', type: 'button', text: 'نمایش توضیح',
            on: { click: onShowExplanation },
        })
        : (reveal.explanation
            ? el('div', {}, renderMarkdown(reveal.explanation),
                el('p', { className: 'x-explanation__origin', text: 'این توضیح با کمک هوش مصنوعی نوشته شده و بازبینی انسانی نشده است.' }))
            : el('p', { className: 'f-tiny', text: 'برای این سؤال توضیحی ثبت نشده است.' }));

    return el('section', { className: `x-revealed${chosen === reveal.answer ? ' is-right' : ''}` },
        el('p', { className: 'x-revealed__head', text: verdict }),
        explanationBody,
        el('p', { className: 'f-tiny', text: 'این سؤال در کارنامه به‌عنوان «پاسخ دیده‌شده» علامت می‌خورد.' }));
}

/*
 * The stem, with the student's highlights drawn into it.
 *
 * The offsets are taken against the faText()-normalised string, which is
 * what the student actually sees and selects from -- taking them against
 * question.prompt would put every highlight in a Persian-digit question a
 * few characters out, and only in questions containing numbers, which is
 * the sort of bug that looks like a rendering glitch for months.
 *
 * Built as text nodes and <mark> elements. This could be one innerHTML
 * assignment; it is not, because the stem is API text and this file does
 * not put API text through innerHTML -- a question containing anything
 * angle-bracketed would otherwise be parsed as markup.
 */
function renderStem(question, study) {
    const text = faText(question.prompt);
    const ranges = study && Array.isArray(study.ranges) ? study.ranges : [];
    const paragraph = el('p', { className: 'x-question__prompt' });

    for (const segment of highlightSegments(text, ranges)) {
        paragraph.append(segment.highlighted
            ? el('mark', { className: 'x-highlight', text: segment.text })
            : document.createTextNode(segment.text));
    }

    return paragraph;
}

/*
 * The study drawer: a note on this question, and the highlight controls.
 *
 * Collapsed by default behind <details>. A student sitting a timed paper
 * should not have a textarea competing with the choices for attention, and
 * the browser's own disclosure gives keyboard and screen-reader behaviour
 * that a hand-rolled toggle would have to reimplement.
 *
 * The note is saved on input rather than behind a save button: a note the
 * student typed and lost because they navigated away is worse than no note
 * feature at all, and there is no server round trip to debounce for.
 */
function renderStudy(question, study, actions) {
    if (!study) return null;

    const note = typeof study.note === 'string' ? study.note : '';
    const hasHighlights = Array.isArray(study.ranges) && study.ranges.length > 0;

    const editor = el('textarea', {
        className: 'x-study__note',
        attrs: {
            rows: '3',
            placeholder: 'یادداشت تو برای این سؤال…',
            'aria-label': 'یادداشت این سؤال',
            maxlength: '2000',
        },
        on: { input: (event) => actions.saveNote(question.id, event.target.value) },
    });
    editor.value = note;

    const summaryBits = [];
    if (note !== '') summaryBits.push('یادداشت');
    if (hasHighlights) summaryBits.push('هایلایت');

    // `open` is driven by the caller, not by the element. draw() rebuilds the
    // whole card, so a <details> left to its own state would snap shut every
    // time the student added a highlight -- from inside the very drawer the
    // button lives in.
    return el('details', {
        className: `x-study${summaryBits.length > 0 ? ' has-content' : ''}`,
        attrs: { open: study.open === true },
        on: { toggle: (event) => actions.setStudyOpen(event.target.open) },
    },
        el('summary', { className: 'x-study__summary' },
            el('span', { text: 'ابزار مطالعه' }),
            summaryBits.length === 0
                ? null
                : el('span', { className: 'x-study__badge', text: summaryBits.join(' · ') })),
        el('div', { className: 'x-study__body' },
            el('div', { className: 'x-study__actions' },
                el('button', {
                    className: 'f-btn f-btn--ghost', type: 'button', text: 'هایلایت متنِ انتخاب‌شده',
                    on: { click: () => actions.highlightSelection(question.id) },
                }),
                !hasHighlights ? null : el('button', {
                    className: 'f-btn f-btn--ghost', type: 'button', text: 'پاک کردن هایلایت‌ها',
                    on: { click: () => actions.clearHighlights(question.id) },
                })),
            el('p', {
                className: `f-tiny x-study__hint${study.hint ? ' is-nudge' : ''}`,
                text: study.hint || 'بخشی از صورت سؤال را انتخاب کن، بعد «هایلایت» را بزن.',
            }),
            editor,
            el('p', { className: 'f-tiny x-study__hint', text: 'یادداشت‌ها و هایلایت‌ها فقط در همین مرورگر ذخیره می‌شوند و در تلاش‌های بعدی همین سؤال هم می‌مانند.' })));
}

/*
 * The chips above a question's stem: what it is about, and how hard.
 *
 * `topic`, `tags` and `difficulty` have been travelling in every question
 * payload since ExamService::safeQuestion() was written (it copies each one
 * through when present) and the runner discarded all three. This renders
 * whatever actually arrived and nothing when none did -- today's imported
 * bank carries `topic` alone, because import-question-bank.php maps the
 * chapter to it and sets neither of the others.
 *
 * Difficulty is shown with its label rather than bare. The value's scale is
 * not defined anywhere in this repository, so a chip reading «۳» would be
 * inventing a meaning; «سختی: ۳» states exactly what is known.
 */
function renderQuestionMeta(question) {
    const chips = [];

    const topic = typeof question.topic === 'string' ? question.topic.trim() : '';
    if (topic !== '') {
        chips.push(el('span', { className: 'x-chip x-chip--static', text: faText(topic) }));
    }

    const difficulty = question.difficulty;
    if (difficulty !== undefined && difficulty !== null && String(difficulty).trim() !== '') {
        chips.push(el('span', {
            className: 'x-chip x-chip--static',
            text: `سختی: ${faText(String(difficulty).trim())}`,
        }));
    }

    if (Array.isArray(question.tags)) {
        // Capped: a question carrying a dozen tags would push the stem off
        // the first screen, and the stem is what the student came for.
        for (const tag of question.tags.slice(0, 3)) {
            const label = typeof tag === 'string' ? tag.trim() : '';
            if (label !== '') {
                chips.push(el('span', { className: 'x-chip x-chip--static', text: faText(label) }));
            }
        }
    }

    if (chips.length === 0) {
        return null;
    }

    return el('div', { className: 'x-question__meta' }, ...chips);
}

/*
 * The question rail: every question, always visible beside the card.
 *
 * This replaces an empty 44px gutter that existed only to catch a wheel
 * event. The whole paper was otherwise reachable only through a dialog, so
 * the runner read as one card floating alone -- a student could not see how
 * far along they were without opening something.
 *
 * It deliberately shows *every* question rather than obeying state.filter:
 * the filter belongs to the map, where the student went looking for a subset
 * on purpose. A rail that silently hid questions would make the paper look
 * shorter than it is, which is the one thing a progress surface must never
 * do. Answered/flagged/current come from the same state helpers the map
 * uses, so the two can never disagree about a question.
 */
function renderRail(state, actions) {
    const rail = el('nav', { className: 'x-rail', attrs: { 'aria-label': 'فهرست سؤال‌ها' } });
    const list = el('ol', { className: 'x-rail__list' });

    for (let position = 1; position <= state.questionCount; position += 1) {
        const answered = isAnswered(state, position);
        const flagged = isFlagged(state, position);
        const current = position === state.position;
        const classes = ['x-rail__pill'];
        if (answered) classes.push('is-answered');
        if (flagged) classes.push('is-flagged');
        if (current) classes.push('is-current');

        list.append(el('li', {},
            el('button', {
                className: classes.join(' '),
                type: 'button',
                text: faDigits(position),
                attrs: {
                    'aria-label': `سؤال ${faDigits(position)}، ${answered ? 'پاسخ‌داده‌شده' : 'بی‌پاسخ'}${flagged ? '، نشان‌دار' : ''}`,
                    'aria-current': current ? 'true' : null,
                },
                on: { click: () => actions.goTo(position) },
            })));
    }

    rail.append(list);
    return rail;
}

/** The question map: a grid of every question with its state. */
export function renderMap(state, actions) {
    const filters = el('div', { className: 'x-map__filters', attrs: { role: 'group', 'aria-label': 'فیلتر' } });
    for (const filter of ATTEMPT_FILTERS) {
        filters.append(el('button', {
            className: `x-chip${state.filter === filter ? ' is-active' : ''}`,
            type: 'button', text: FILTER_LABELS[filter],
            attrs: { 'aria-pressed': state.filter === filter ? 'true' : 'false' },
            on: { click: () => actions.setFilter(filter) },
        }));
    }

    const grid = el('div', { className: 'x-map__grid' });
    const shown = visiblePositions(state);
    for (const position of shown) {
        const answered = isAnswered(state, position);
        const flagged = isFlagged(state, position);
        const classes = ['x-map__cell'];
        if (answered) classes.push('is-answered');
        if (flagged) classes.push('is-flagged');
        if (position === state.position) classes.push('is-current');
        grid.append(el('button', {
            className: classes.join(' '), type: 'button', text: faDigits(position),
            attrs: {
                'aria-label': `سؤال ${faDigits(position)}، ${answered ? 'پاسخ‌داده‌شده' : 'بی‌پاسخ'}${flagged ? '، نشان‌دار' : ''}`,
                'aria-current': position === state.position ? 'true' : null,
            },
            on: { click: () => actions.goTo(position) },
        }));
    }
    if (shown.length === 0) {
        grid.append(el('p', { className: 'f-muted', text: 'سؤالی با این فیلتر نیست.' }));
    }

    return el('div', { className: 'x-dialog', attrs: { role: 'dialog', 'aria-modal': 'true', 'aria-label': 'نقشه سؤال‌ها', tabindex: '-1' } },
        el('div', { className: 'x-dialog__panel' },
            el('header', { className: 'x-dialog__head' },
                el('h2', { text: 'نقشه سؤال‌ها' }),
                el('button', { className: 'x-dialog__close', type: 'button', text: '✕', attrs: { 'aria-label': 'بستن' }, on: { click: actions.close } })),
            filters,
            grid,
            el('p', { className: 'f-tiny x-map__legend', text: 'پررنگ: پاسخ‌داده‌شده · با نشان: علامت‌زده' })));
}

/** The confirmation before scoring, with the honest unanswered count. */
export function renderSubmitDialog(state, actions) {
    const blanks = unansweredPositions(state);
    return el('div', { className: 'x-dialog', attrs: { role: 'dialog', 'aria-modal': 'true', 'aria-label': 'ثبت نهایی', tabindex: '-1' } },
        el('div', { className: 'x-dialog__panel x-dialog__panel--narrow' },
            el('h2', { text: 'ثبت نهایی آزمون' }),
            blanks.length === 0
                ? el('p', { text: 'به همه سؤال‌ها پاسخ داده‌ای. بعد از ثبت، پاسخ درست و توضیح هر سؤال را می‌بینی.' })
                : el('div', {},
                    notice('warning', `${faDigits(blanks.length)} سؤال بی‌پاسخ مانده`, 'سؤال بی‌پاسخ غلط حساب می‌شود.'),
                    el('button', {
                        className: 'f-btn f-btn--ghost', type: 'button', text: 'برو به اولین بی‌پاسخ',
                        on: { click: actions.firstUnanswered },
                    })),
            el('div', { className: 'x-dialog__actions' },
                el('button', { className: 'f-btn f-btn--ghost', type: 'button', text: 'برگرد', on: { click: actions.close } }),
                el('button', {
                    className: 'f-btn f-btn--primary', type: 'button',
                    text: state.submitting ? 'در حال ثبت…' : 'ثبت و مشاهده نتیجه',
                    attrs: { disabled: state.submitting },
                    on: { click: actions.confirm },
                }))));
}

/** The score summary after submitting. */
export function renderReport(summary, actions) {
    const total = Number(summary.question_count || 0);
    const correct = Number(summary.correct_count || 0);
    const percent = total === 0 ? 0 : Math.round((correct / total) * 100);

    return el('div', { className: 'x-report' },
        el('div', { className: 'f-card x-report__card' },
            el('div', {
                className: 'x-report__ring',
                attrs: { style: `--percent:${percent}`, role: 'img', 'aria-label': `نمره ${faDigits(percent)} از ۱۰۰` },
            }, el('span', { className: 'x-report__percent', text: `٪${faDigits(percent)}` })),
            el('p', { className: 'x-report__line', text: `${faDigits(correct)} پاسخ درست از ${faDigits(total)} سؤال` }),
            renderReportStats(summary, total, correct),
            Number(summary.revealed_count || 0) === 0 ? null : notice(
                'warning',
                `${faDigits(summary.revealed_count)} پاسخ را قبل از جواب دادن دیدی`,
                'این کارنامه‌ی یک تمرین است، نه یک آزمون واقعی. برای سنجش خودت یک بار در حالت آزمون امتحان کن.',
            ),
            el('div', { className: 'x-report__actions' },
                el('button', { className: 'f-btn f-btn--primary', type: 'button', text: 'مرور پاسخ‌ها', on: { click: actions.review } }),
                el('a', { className: 'f-btn f-btn--ghost', attrs: { href: '/app/exams' }, text: 'فهرست آزمون‌ها' }))));
}

/*
 * The report's four tiles. The card used to say one sentence -- "N correct
 * out of M" -- which folded two different outcomes into the same remainder:
 * a student who answered and got it wrong and one who never reached the
 * question at all read identically, and those call for opposite next steps.
 *
 * `answered_count` is served by ExamService::scoreAndClose(). An older
 * attempt scored before that field existed has no value for it, so the two
 * tiles that depend on it are omitted rather than guessed at -- showing
 * «بی‌پاسخ: ۰» for an attempt where the truth is unknown would be a lie in
 * the exact direction a student would not question.
 */
function renderReportStats(summary, total, correct) {
    const tiles = [el('div', { className: 'x-stat is-correct' },
        el('span', { className: 'x-stat__value', text: faDigits(correct) }),
        el('span', { className: 'x-stat__label', text: 'درست' }))];

    const answered = Number(summary.answered_count);
    if (Number.isFinite(answered)) {
        tiles.push(el('div', { className: 'x-stat is-wrong' },
            el('span', { className: 'x-stat__value', text: faDigits(Math.max(0, answered - correct)) }),
            el('span', { className: 'x-stat__label', text: 'نادرست' })));
        tiles.push(el('div', { className: 'x-stat' },
            el('span', { className: 'x-stat__value', text: faDigits(Math.max(0, total - answered)) }),
            el('span', { className: 'x-stat__label', text: 'بی‌پاسخ' })));
    }

    const revealed = Number(summary.revealed_count || 0);
    if (revealed > 0) {
        tiles.push(el('div', { className: 'x-stat is-revealed' },
            el('span', { className: 'x-stat__value', text: faDigits(revealed) }),
            el('span', { className: 'x-stat__label', text: 'پاسخ دیده‌شده' })));
    }

    return el('div', { className: 'x-stats' }, ...tiles);
}

/** One reviewed question: what was chosen, what was right, and why. */
export function renderReviewQuestion(entry, position, questionCount, actions) {
    const choices = el('ul', { className: 'x-choices x-choices--review' });
    (entry.choices || []).forEach((choice, index) => {
        const isCorrect = index === entry.correct;
        const isChosen = index === entry.selected;
        const classes = ['x-choice', 'x-choice--review'];
        if (isCorrect) classes.push('is-correct');
        if (isChosen && !isCorrect) classes.push('is-wrong');
        choices.append(el('li', { className: 'x-choices__item' },
            el('div', { className: classes.join(' ') },
                el('span', { className: 'x-choice__letter', text: CHOICE_LETTERS[index] ?? faDigits(index + 1) }),
                el('span', { className: 'x-choice__text', text: faText(choice) }),
                el('span', {
                    className: 'x-choice__mark',
                    text: isCorrect ? '✓ پاسخ درست' : (isChosen ? '✕ انتخاب تو' : ''),
                }))));
    });

    const verdict = entry.selected === null || entry.selected === undefined
        ? { kind: 'warning', text: 'بی‌پاسخ' }
        : (entry.is_correct ? { kind: 'success', text: 'درست' } : { kind: 'error', text: 'نادرست' });
    // Saying so is the whole point of recording it: a score that counts a
    // seen answer as an earned one tells the student something untrue about
    // what they know.
    const seenFirst = entry.was_revealed === true;

    return el('div', { className: 'x-review' },
        el('div', { className: 'x-review__bar' },
            el('button', {
                className: 'f-btn f-btn--ghost', type: 'button', text: '→ قبلی',
                attrs: { disabled: position <= 1 }, on: { click: actions.previous },
            }),
            el('span', { className: 'x-review__index', text: `سؤال ${faDigits(position)} از ${faDigits(questionCount)}` }),
            el('button', {
                className: 'f-btn f-btn--ghost', type: 'button', text: 'بعدی ←',
                attrs: { disabled: position >= questionCount }, on: { click: actions.next },
            })),
        el('article', { className: 'f-card x-review__card' },
            el('span', { className: `x-verdict is-${verdict.kind}`, text: verdict.text }),
            seenFirst ? el('span', { className: 'x-verdict is-revealed', text: 'پاسخ را قبل از جواب دادن دیدی' }) : null,
            el('p', { className: 'x-question__prompt', text: faText(entry.prompt) }),
            choices,
            entry.explanation
                ? el('section', { className: 'x-explanation' },
                    el('h3', { text: 'چرا؟' }),
                    renderMarkdown(entry.explanation),
                    el('p', { className: 'x-explanation__origin', text: 'این توضیح با کمک هوش مصنوعی نوشته شده و بازبینی انسانی نشده است.' }))
                : el('p', { className: 'f-tiny', text: 'برای این سؤال توضیحی ثبت نشده است.' })),
        el('p', { className: 'x-review__done' },
            el('a', { attrs: { href: '/app/exams' }, text: 'پایان مرور و بازگشت به فهرست آزمون‌ها' })));
}
