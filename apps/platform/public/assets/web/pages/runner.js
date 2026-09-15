/*
 * The exam runner's controller: intent in, state change out, redraw.
 *
 * It owns no rendering and no truth of its own -- runner-state decides what
 * is true, runner-view draws it, runner-transport talks to the server. This
 * file is only the wiring, which is what keeps the three testable.
 */
import { ApiError, describeError, watchConnection } from '../foundation/api.js';
import {
    afterAnswer, clampPosition, clearAnswer, createAttemptState, hasUnsavedAnswers,
    nextUnanswered, setAnswer, showExplanation as markExplanationShown, toggleFlag, toggleStrike, unansweredPositions,
} from './runner-state.js';
import { AnswerSync, ExamTransport, QuestionWindow } from './runner-transport.js';
import {
    notice, renderIntro, renderMap, renderQuestion, renderReport,
    renderReviewQuestion, renderSubmitDialog,
} from './runner-view.js';

const root = document.getElementById('runner');
const assessmentId = document.querySelector('.x-runner')?.dataset.assessment ?? '';
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';

const transport = new ExamTransport(workspaceId);

/** 'intro' | 'question' | 'report' | 'review' */
let phase = 'intro';
let assessment = null;
let state = null;
let questions = null;
let sync = null;
let summary = null;
let reviewPosition = 1;
let reviewEntry = null;
let dialog = null;
let banner = null;
let pageError = null;

function draw() {
    const frame = document.createDocumentFragment();
    if (banner) frame.append(banner);
    if (pageError) frame.append(pageError);

    if (phase === 'intro' && assessment) {
        frame.append(renderIntro(assessment, { start }));
    } else if (phase === 'question' && state) {
        const question = questions.get(state.position);
        frame.append(question
            ? renderQuestion(state, question, sync.status, questionActions, state.reveals.get(state.position) ?? null)
            : loading('در حال گرفتن سؤال…'));
    } else if (phase === 'report' && summary) {
        frame.append(renderReport(summary, { review: startReview }));
    } else if (phase === 'review') {
        frame.append(reviewEntry
            ? renderReviewQuestion(reviewEntry, reviewPosition, summary.question_count, reviewActions)
            : loading('در حال گرفتن پاسخ تشریحی…'));
    } else {
        frame.append(loading('در حال آماده‌سازی آزمون…'));
    }

    if (dialog) frame.append(dialog);
    root.replaceChildren(frame);

    // Focus mode: while an attempt is open the site chrome is a distraction
    // and a stray tap on it loses the student's place.
    document.body.classList.toggle('x-focus', phase === 'question');
    if (dialog) {
        const panel = root.querySelector('.x-dialog');
        if (panel) panel.focus({ preventScroll: true });
    }
}

function loading(message) {
    const wrap = document.createElement('div');
    wrap.className = 'f-card x-runner__loading';
    const skeleton = document.createElement('div');
    skeleton.className = 'x-skeleton';
    skeleton.setAttribute('aria-hidden', 'true');
    const text = document.createElement('p');
    text.className = 'f-muted';
    text.textContent = message;
    wrap.append(skeleton, text);
    return wrap;
}

function showError(error, retry) {
    if (error instanceof ApiError && error.isRateLimited) {
        const seconds = error.retryAfterSeconds ?? 20;
        pageError = notice('warning', 'کمی آهسته‌تر', `چند لحظه صبر کن و دوباره تلاش کن (حدود ${seconds} ثانیه).`, retry ? { label: 'تلاش دوباره', onClick: retry } : null);
    } else {
        pageError = notice('error', 'انجام نشد', describeError(error), retry ? { label: 'تلاش دوباره', onClick: retry } : null);
    }
}

function clearError() {
    pageError = null;
}

/* ------------------------------------------------------------------ intro */

async function loadAssessment() {
    try {
        const catalog = await transport.api.get(`/workspaces/${encodeURIComponent(workspaceId)}/assessments`);
        assessment = (Array.isArray(catalog) ? catalog : []).find((item) => item.id === assessmentId) ?? null;
        if (!assessment) {
            pageError = notice('error', 'این آزمون در دسترس نیست', 'ممکن است منتشر نشده باشد یا به فضای آموزشی دیگری تعلق داشته باشد.');
        }
    } catch (error) {
        showError(error, loadAssessment);
    }
    draw();
}

async function start(mode) {
    clearError();
    try {
        const attempt = await transport.start(assessmentId, mode);
        state = createAttemptState({
            attemptId: attempt.attempt_id,
            assessmentId,
            title: attempt.title,
            questionCount: Number(attempt.question_count || 0),
            revision: Number(attempt.revision || 1),
            answers: attempt.answers || {},
            mode: attempt.mode || 'assessment',
        });
        questions = new QuestionWindow(transport, state.attemptId, state.questionCount);
        sync = new AnswerSync(transport, state, { onStateChange: () => { if (phase === 'question') draw(); } });
        // Resuming lands on the first gap, not on question one: that is where
        // the student actually stopped.
        state.position = nextUnanswered(state) ?? 1;
        phase = 'question';
        draw();
        await showQuestion(state.position);
    } catch (error) {
        showError(error, start);
        draw();
    }
}

async function showQuestion(position) {
    state.position = clampPosition(state, position);
    draw();
    if (questions.has(state.position)) return;
    try {
        await questions.load(state.position);
        clearError();
    } catch (error) {
        showError(error, () => showQuestion(state.position));
    }
    draw();
}

/* --------------------------------------------------------------- question */

const questionActions = {
    choose(index) {
        setAnswer(state, state.position, index);
        sync.schedule();

        // Learning and practice both answer immediately: choosing shows
        // whether it was right and stays put so it can be read (learning
        // alongside the explanation, practice with the explanation a tap
        // away). Auto-advancing here would sweep the student past the one
        // thing they came for.
        if (state.mode === 'learning' || state.mode === 'practice') {
            draw();
            questionActions.reveal();
            return;
        }

        const target = afterAnswer(state, state.position);
        if (target.kind === 'review') {
            openSubmit();
            return;
        }
        showQuestion(target.position);
    },
    strike(index) {
        toggleStrike(state, state.position, index);
        sync.schedule();
        draw();
    },
    clear() {
        clearAnswer(state, state.position);
        sync.schedule();
        draw();
    },
    toggleFlag() {
        toggleFlag(state, state.position);
        draw();
    },
    previous() {
        showQuestion(state.position - 1);
    },
    next() {
        showQuestion(state.position + 1);
    },
    firstUnanswered() {
        const target = nextUnanswered(state, 1);
        if (target !== null) showQuestion(target);
    },
    async reveal() {
        if ((state.mode !== 'learning' && state.mode !== 'practice') || state.reveals.has(state.position)) return;
        try {
            const payload = await transport.reveal(state.attemptId, state.position);
            state.reveals.set(state.position, payload);
            clearError();
        } catch (error) {
            showError(error, () => questionActions.reveal());
        }
        draw();
    },
    showExplanation() {
        markExplanationShown(state, state.position);
        draw();
    },
    openMap() {
        dialog = renderMap(state, mapActions);
        draw();
    },
    requestSubmit: openSubmit,
    leave() {
        // Answers are saved, so leaving loses nothing but the place -- say so
        // rather than letting the student guess.
        const message = hasUnsavedAnswers(state)
            ? 'بعضی پاسخ‌ها هنوز ذخیره نشده‌اند. اگر الان خارج شوی ممکن است از دست بروند. خارج می‌شوی؟'
            : 'از آزمون خارج می‌شوی؟ پاسخ‌هایت ذخیره شده و بعداً می‌توانی ادامه بدهی.';
        if (window.confirm(message)) window.location.assign('/app/exams');
    },
};

const mapActions = {
    setFilter(filter) {
        state.filter = filter;
        dialog = renderMap(state, mapActions);
        draw();
    },
    goTo(position) {
        dialog = null;
        showQuestion(position);
    },
    close() {
        dialog = null;
        draw();
    },
};

/* ----------------------------------------------------------------- submit */

function openSubmit() {
    dialog = renderSubmitDialog(state, submitActions);
    draw();
}

const submitActions = {
    close() {
        dialog = null;
        draw();
    },
    firstUnanswered() {
        dialog = null;
        const target = unansweredPositions(state)[0];
        if (target !== undefined) showQuestion(target);
    },
    async confirm() {
        if (state.submitting) return;
        state.submitting = true;
        dialog = renderSubmitDialog(state, submitActions);
        draw();
        try {
            // Flush first: submitting with answers still queued would score a
            // paper the server has not seen in full.
            if (hasUnsavedAnswers(state)) await sync.flush();
            summary = await transport.submit(state.attemptId, state.revision, state.answers);
            state.submitted = true;
            dialog = null;
            phase = 'report';
            clearError();
        } catch (error) {
            showError(error, null);
        } finally {
            state.submitting = false;
            draw();
        }
    },
};

/* ----------------------------------------------------------------- review */

async function startReview() {
    phase = 'review';
    reviewPosition = 1;
    await showReview(1);
}

async function showReview(position) {
    reviewPosition = Math.min(Math.max(position, 1), Number(summary.question_count || 1));
    reviewEntry = null;
    draw();
    try {
        reviewEntry = await transport.reviewQuestion(state.attemptId, reviewPosition);
        clearError();
    } catch (error) {
        showError(error, () => showReview(reviewPosition));
    }
    draw();
}

const reviewActions = {
    previous() { showReview(reviewPosition - 1); },
    next() { showReview(reviewPosition + 1); },
};

/* ------------------------------------------------------------------ setup */

/*
 * Keyboard shortcuts. Answering forty questions is a keyboard task, and
 * reaching for the mouse for every one of them is the difference between a
 * tool and a chore.
 *
 * Deliberately not bound while a dialog is open or a field has focus: a
 * shortcut that fires while someone is typing is worse than no shortcut.
 */
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && dialog) {
        event.preventDefault();
        dialog = null;
        draw();
        return;
    }
    if (phase !== 'question' || dialog || event.ctrlKey || event.metaKey || event.altKey) return;
    const target = event.target;
    if (target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))) return;

    const question = questions.get(state.position);
    // Arrows follow the text direction: this is an RTL page, so "next" is the
    // left arrow and "previous" the right one. Binding them the Latin way
    // round would send the student backwards every time.
    if (event.key === 'ArrowLeft') { event.preventDefault(); questionActions.next(); return; }
    if (event.key === 'ArrowRight') { event.preventDefault(); questionActions.previous(); return; }
    if (event.key.toLowerCase() === 'f') { event.preventDefault(); questionActions.toggleFlag(); return; }
    if (event.key.toLowerCase() === 'm') { event.preventDefault(); questionActions.openMap(); return; }
    if (event.key.toLowerCase() === 'r' && state.mode === 'learning') { event.preventDefault(); questionActions.reveal(); return; }

    // 1-9 pick a choice, in the order shown.
    const choiceIndex = Number(event.key) - 1;
    if (question && Number.isInteger(choiceIndex) && choiceIndex >= 0 && choiceIndex < (question.choices || []).length) {
        event.preventDefault();
        questionActions.choose(choiceIndex);
    }
});

// Leaving mid-attempt with answers still queued would lose them; the browser
// asks on our behalf.
window.addEventListener('beforeunload', (event) => {
    if (phase === 'question' && state && hasUnsavedAnswers(state)) {
        event.preventDefault();
        event.returnValue = '';
    }
});

watchConnection((online) => {
    if (online) {
        banner = null;
        // Answers written while offline are still in memory; push them now.
        if (state && hasUnsavedAnswers(state)) sync.flush().catch(() => {});
    } else {
        const node = document.createElement('div');
        node.className = 'f-offline-banner';
        node.setAttribute('role', 'status');
        node.textContent = 'اینترنت قطع است. پاسخ‌هایت نگه داشته می‌شود و به‌محض وصل شدن ذخیره می‌شود.';
        banner = node;
    }
    draw();
});

if (root && assessmentId) loadAssessment();
