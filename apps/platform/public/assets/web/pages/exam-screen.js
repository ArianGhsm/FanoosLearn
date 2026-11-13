/*
 * صفحه‌ی آزمون (2026-11-13): the question screen, redesigned from its
 * structure up. runner-view.js keeps every behaviour (state, handlers, the
 * study tools, highlights, the report form) and hands its pieces in through
 * `kit`; this module only decides what the student sees, where, and when.
 *
 * The shape, top to bottom:
 *   a slim header -- leave, where you are, the clock, saving, map, settings --
 *     with the paper's progress as one line under it;
 *   the sheet -- the question's number and the two tools, the stem, the
 *     choices as cards; nothing about the question before it is answered
 *     (blind: no subject, chapter, numbers or study tools);
 *   once answered (or revealed), the verdict, then one place to learn from it,
 *     in tabs so the page never turns into a scroll of everything at once:
 *     پاسخ تشریحی · نقشه‌ی مبحث · آمار · یادداشت;
 *   stepping -- a bar under the sheet on a desktop, in the thumb zone on a phone;
 *   on a wide screen, a side panel with the paper at a glance.
 *
 * Text from the API goes in through textContent only (kit.el / bidiNodes).
 */

const tabsByQuestion = new Map();

const TABS = [
    ['explain', 'پاسخ تشریحی', 'book'],
    ['topic', 'نقشه‌ی مبحث', 'map'],
    ['stats', 'آمار', 'chart'],
    ['note', 'یادداشت', 'note'],
];

export function renderExamScreen(view, kit) {
    const { state, question, saveStatus, actions, reveal, study, enter, flags } = view;
    const { el, icon, faDigits } = kit;
    const position = state.position;
    const selected = state.answers[String(position)];
    const answered = selected !== undefined;
    const learned = reveal !== null && reveal !== undefined;

    return el('div', { className: `e-exam${learned ? ' is-learned' : ''}` },
        header(state, saveStatus, actions, flags, kit),
        el('div', { className: 'e-body' },
            el('div', { className: 'e-main' },
                sheet(state, question, actions, reveal, study, enter, kit, { position, selected, answered, learned }),
                stepping(state, actions, kit)),
            side(state, actions, kit)));
}

/* ----------------------------------------------------------------- header */

function header(state, saveStatus, actions, flags, kit) {
    const { el, icon, faDigits } = kit;
    const remaining = kit.remainingSeconds(state);
    const answeredNow = kit.answeredCount(state);
    return el('header', { className: 'e-top' },
        el('div', { className: 'e-top__row' },
            el('button', {
                className: 'e-icon e-top__leave', type: 'button',
                attrs: { 'aria-label': 'خروج از آزمون بدون ثبت', title: 'خروج' },
                on: { click: actions.leave },
            }, icon('close')),
            el('div', { className: 'e-top__where' },
                el('strong', { className: 'e-top__count', text: `سؤال ${faDigits(state.position)}` }),
                el('span', { className: 'e-top__of', text: `از ${faDigits(state.questionCount)}` }),
                el('span', { className: 'e-top__done', text: `${faDigits(answeredNow)} پاسخ‌داده`, attrs: { role: 'status' } })),
            flags.turbo ? el('button', {
                className: 'e-chip e-chip--mode', type: 'button', text: 'مرور سریع',
                attrs: { title: 'پاسخ هر سؤال همان لحظه نشان داده می‌شود؛ برای خاموش کردن بزن' },
                on: { click: actions.openSettings },
            }) : null,
            el('span', {
                className: `e-save is-${state.submitQueued ? 'queued' : saveStatus}`,
                attrs: { role: 'status', title: state.submitQueued ? 'ثبت آزمون آفلاین در صف ماند — به‌محض اتصال دوباره ارسال می‌شود' : kit.saveLabel(saveStatus) },
            }, el('span', { className: 'e-save__dot', attrs: { 'aria-hidden': 'true' } }),
                el('span', { className: 'e-save__text', text: state.submitQueued ? 'در صف ارسال' : kit.saveLabel(saveStatus) })),
            remaining === null ? null : el('span', {
                className: `e-timer${kit.isTimeCritical(state) ? ' is-critical' : ''}`,
                attrs: { role: 'timer', 'aria-live': 'polite', 'aria-label': 'زمان باقی‌مانده' },
            }, icon('clock'), el('span', { text: kit.formatRemaining(remaining) })),
            el('button', {
                className: 'e-icon e-top__map', type: 'button',
                attrs: { 'aria-label': 'نقشه سؤال‌ها', title: 'نقشه سؤال‌ها' },
                on: { click: actions.openMap },
            }, icon('grid')),
            el('button', {
                className: 'e-icon', type: 'button',
                attrs: { 'aria-label': 'تنظیمات', title: 'تنظیمات' },
                on: { click: actions.openSettings },
            }, icon('settings'))),
        el('div', { className: 'e-progress', attrs: { 'aria-hidden': 'true' } },
            el('span', { className: 'e-progress__fill', attrs: { style: `inline-size:${kit.progressPercent(state)}%` } })));
}

/* ------------------------------------------------------------------ sheet */

function sheet(state, question, actions, reveal, study, enter, kit, ctx) {
    const { el, icon, faDigits } = kit;
    const flagged = kit.isFlagged(state, ctx.position);

    const head = el('div', { className: 'e-sheet__head' },
        el('span', { className: 'e-qno', text: faDigits(ctx.position) }),
        el('div', { className: 'e-tools' },
            !study ? null : el('button', {
                className: `e-icon e-tool${study.bookmarked ? ' is-on' : ''}`, type: 'button',
                attrs: { 'aria-pressed': study.bookmarked ? 'true' : 'false', 'aria-label': study.bookmarked ? 'ذخیره شد' : 'ذخیره برای مرور', title: 'ذخیره برای مرور' },
                on: { click: () => actions.toggleBookmark(question.id) },
            }, icon('bookmark', { filled: study.bookmarked === true })),
            el('button', {
                className: `e-icon e-tool e-tool--flag${flagged ? ' is-on' : ''}`, type: 'button',
                attrs: { 'aria-pressed': flagged ? 'true' : 'false', 'aria-label': flagged ? 'نشان‌دار' : 'نشان‌دار کن', title: 'نشان‌دار کن تا بعداً برگردی' },
                on: { click: actions.toggleFlag },
            }, icon('flag', { filled: flagged }))));

    // Blind until answered: the question's facts arrive with the answer,
    // unless the set was opened by chapter and the student knows them.
    const facts = (state.sourceVisible === true || ctx.answered || ctx.learned) ? kit.renderQuestionMeta(question, true) : null;

    const choices = el('ol', { className: 'e-choices', attrs: { role: 'radiogroup', 'aria-label': 'گزینه‌ها' } });
    (question.choices || []).forEach((choice, index) => {
        const struck = kit.isStruck(state, ctx.position, index);
        const isSelected = ctx.selected === index;
        const correct = ctx.learned && kit.isAccepted(reveal.answer, reveal.also_correct, index);
        const wrong = ctx.learned && isSelected && !correct;
        const letter = kit.CHOICE_LETTERS[index] ?? faDigits(index + 1);
        const imageUrl = kit.choiceImageUrl(state.assessmentId, question, index);
        const classes = ['e-choice'];
        if (isSelected) classes.push('is-selected');
        if (struck) classes.push('is-struck');
        if (correct) classes.push('is-correct');
        if (wrong) classes.push('is-wrong');
        if (ctx.learned && !correct && !wrong) classes.push('is-other');

        choices.append(el('li', { className: 'e-choices__item' },
            el('button', {
                className: classes.join(' '), type: 'button',
                attrs: { role: 'radio', 'aria-checked': isSelected ? 'true' : 'false' },
                on: { click: () => (ctx.learned ? undefined : actions.choose(index)) },
            },
                el('span', { className: 'e-choice__letter', text: letter }),
                el('span', { className: 'e-choice__text', attrs: { dir: 'auto' } }, ...kit.bidiNodes(kit.faText(choice)),
                    imageUrl ? el('img', {
                        className: 'e-choice__img',
                        attrs: { src: imageUrl, alt: `تصویر گزینه‌ی ${letter}`, loading: 'lazy', decoding: 'async', draggable: 'false' },
                    }) : null),
                correct ? el('span', { className: 'e-choice__state' }, icon('check'), el('span', { text: 'پاسخ درست' })) : null,
                wrong ? el('span', { className: 'e-choice__state' }, icon('close'), el('span', { text: 'انتخاب تو' })) : null),
            imageUrl ? el('button', {
                className: 'e-icon e-choice__zoom', type: 'button',
                attrs: { 'aria-label': `بزرگ‌نمایی تصویر گزینه‌ی ${letter}` },
                on: { click: () => kit.openLightbox(imageUrl, `تصویر گزینه‌ی ${letter}`) },
            }, icon('zoom')) : null,
            ctx.learned ? null : el('button', {
                className: 'e-icon e-choice__strike', type: 'button',
                attrs: { 'aria-label': struck ? `برگرداندن گزینه ${letter}` : `خط زدن گزینه ${letter}`, title: struck ? 'برگرداندن' : 'خط زدن' },
                on: { click: () => actions.strike(index) },
            }, icon(struck ? 'undo' : 'strike'))));
    });

    const foot = ctx.learned ? null : el('div', { className: 'e-sheet__foot' },
        ctx.answered ? el('button', { className: 'e-link', type: 'button', text: 'پاک کردن پاسخ', on: { click: actions.clear } }) : null,
        state.mode !== 'learning' ? null : el('button', {
            className: 'e-ghost', type: 'button', on: { click: actions.reveal },
        }, icon('eye'), el('span', { text: 'بلد نیستم؛ پاسخ را نشانم بده' })));

    return el('article', {
        className: `e-sheet${enter ? ` is-entering is-${enter}` : ''}`,
        on: {
            wheel: actions.cardWheel,
            touchstart: actions.cardTouchStart,
            touchmove: actions.cardTouchMove,
            touchend: actions.cardTouchEnd,
        },
    },
        head,
        kit.renderStem(question, study),
        kit.stemFigure(state.assessmentId, question),
        choices,
        foot,
        ctx.learned ? after(state, question, actions, reveal, study, kit, ctx) : null,
        facts ? el('div', { className: 'e-facts' }, facts) : null,
        kit.renderWatermark());
}

/* ------------------------------------------------------- after the answer */

function verdict(reveal, chosen, kit) {
    const { el, icon, faDigits } = kit;
    const accepted = [reveal.answer, ...(reveal.also_correct || [])].sort((a, b) => a - b);
    const letters = accepted.map((index) => kit.CHOICE_LETTERS[index] ?? faDigits(index + 1));
    const answerText = letters.length === 1 ? `گزینه‌ی ${letters[0]}` : `گزینه‌های ${letters.join(' و ')}`;
    const has = chosen !== null && chosen !== undefined;
    const right = has && accepted.includes(chosen);
    const kind = !has ? 'seen' : (right ? 'right' : 'wrong');
    const title = kind === 'right' ? 'درست جواب دادی' : (kind === 'wrong' ? 'این بار نه' : 'پاسخ را دیدی');
    return el('div', { className: `e-verdict is-${kind}`, attrs: { role: 'status' } },
        el('span', { className: 'e-verdict__icon' }, icon(kind === 'right' ? 'check' : (kind === 'wrong' ? 'close' : 'eye'))),
        el('div', { className: 'e-verdict__body' },
            el('strong', { className: 'e-verdict__title', text: title }),
            el('span', { className: 'e-verdict__text', text: `پاسخ درست: ${answerText}` })),
        kind === 'seen' ? el('span', { className: 'e-verdict__note', text: 'در کارنامه «پاسخ دیده‌شده» ثبت می‌شود' }) : null);
}

function after(state, question, actions, reveal, study, kit, ctx) {
    const { el, icon } = kit;
    const key = question.id ?? question.question_id;
    const active = tabsByQuestion.get(key) ?? 'explain';
    const panels = el('div', { className: 'e-panels' });
    const tabs = el('div', { className: 'e-tabs', attrs: { role: 'tablist', 'aria-label': 'یادگیری از این سؤال' } });

    const show = (name) => {
        tabsByQuestion.set(key, name);
        for (const button of tabs.children) {
            const on = button.dataset.tab === name;
            button.classList.toggle('is-active', on);
            button.setAttribute('aria-selected', on ? 'true' : 'false');
        }
        for (const panel of panels.children) panel.hidden = panel.dataset.tab !== name;
    };

    const explanationVisible = state.mode !== 'practice' || kit.isExplanationShown(state, ctx.position);
    const explain = !explanationVisible
        ? el('button', { className: 'e-ghost', type: 'button', on: { click: actions.showExplanation } }, icon('book'), el('span', { text: 'نمایش پاسخ تشریحی' }))
        : (reveal.explanation
            ? kit.renderExplanation(reveal.explanation, { chosen: ctx.selected ?? null, showAnswer: false })
            : el('p', { className: 'e-empty', text: 'برای این سؤال هنوز پاسخ تشریحی نوشته نشده است.' }));

    const topic = kit.renderTopicVisuals(key);
    const content = {
        explain,
        topic: el('div', {}, topic, el('p', { className: 'e-empty e-empty--topic', text: 'برای مبحث این سؤال هنوز نقشه‌ی ذهنی ساخته نشده است.' })),
        stats: kit.renderQuestionStats(question.stats ?? null),
        note: kit.renderStudy(question, study ? { ...study, open: true } : null, actions)
            ?? el('p', { className: 'e-empty', text: 'یادداشت برای این آزمون در دسترس نیست.' }),
    };

    for (const [name, label, iconName] of TABS) {
        tabs.append(el('button', {
            className: 'e-tab', type: 'button',
            attrs: { role: 'tab', 'data-tab': name, 'aria-selected': 'false' },
            on: { click: () => show(name) },
        }, icon(iconName), el('span', { text: label })));
        panels.append(el('section', { className: `e-panel e-panel--${name}`, attrs: { role: 'tabpanel', 'data-tab': name, 'aria-label': label } }, content[name]));
    }
    show(active);

    return el('section', { className: 'e-after' }, verdict(reveal, ctx.selected, kit), tabs, panels);
}

/* --------------------------------------------------------------- stepping */

function stepping(state, actions, kit) {
    const { el, icon } = kit;
    const last = state.position >= state.questionCount;
    return el('nav', { className: 'e-step', attrs: { 'aria-label': 'پیمایش سؤال‌ها' } },
        el('button', {
            className: 'e-step__prev', type: 'button',
            attrs: { disabled: state.position <= 1, 'aria-label': 'سؤال قبلی' },
            on: { click: actions.previous },
        }, icon('previous'), el('span', { className: 'e-step__label', text: 'قبلی' })),
        el('button', {
            className: 'e-step__gap', type: 'button', text: 'اولین بی‌پاسخ',
            attrs: { disabled: kit.unansweredPositions(state).length === 0 },
            on: { click: actions.firstUnanswered },
        }),
        last
            ? el('button', { className: 'e-step__next is-finish', type: 'button', text: 'پایان و ثبت آزمون', on: { click: actions.requestSubmit } })
            : el('button', { className: 'e-step__next', type: 'button', on: { click: actions.next } },
                el('span', { text: 'سؤال بعدی' }), icon('next')));
}

/* ------------------------------------------------------------------- side */

function side(state, actions, kit) {
    const { el, faDigits } = kit;
    let answered = 0;
    let flagged = 0;
    const grid = el('ol', { className: 'e-paper__grid' });
    for (let position = 1; position <= state.questionCount; position += 1) {
        const isDone = kit.isAnswered(state, position);
        const isFlag = kit.isFlagged(state, position);
        if (isDone) answered += 1;
        if (isFlag) flagged += 1;
        const classes = ['e-paper__cell'];
        if (isDone) classes.push('is-answered');
        if (isFlag) classes.push('is-flagged');
        if (position === state.position) classes.push('is-current');
        grid.append(el('li', {}, el('button', {
            className: classes.join(' '), type: 'button', text: faDigits(position),
            attrs: {
                'aria-label': `سؤال ${faDigits(position)}، ${isDone ? 'پاسخ‌داده‌شده' : 'بی‌پاسخ'}${isFlag ? '، نشان‌دار' : ''}`,
                'aria-current': position === state.position ? 'true' : null,
            },
            on: { click: () => actions.goTo(position) },
        })));
    }
    const left = state.questionCount - answered;
    const stat = (kind, value, label) => el('div', { className: `e-paper__stat is-${kind}` },
        el('strong', { text: faDigits(value) }), el('span', { text: label }));
    return el('aside', { className: 'e-side', attrs: { 'aria-label': 'نمای کلی آزمون' } },
        el('div', { className: 'e-paper' },
            el('h2', { className: 'e-paper__title', text: state.title || 'آزمون' }),
            el('div', { className: 'e-paper__stats' },
                stat('done', answered, 'پاسخ‌داده'),
                stat('flag', flagged, 'نشان‌دار'),
                stat('left', left, 'مانده')),
            grid));
}
