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
    nextUnanswered, remainingSeconds, setAnswer, showExplanation as markExplanationShown,
    toggleFlag, toggleStrike, unansweredPositions,
} from './runner-state.js';
import { AnswerSync, ExamTransport, QuestionWindow, TurboRevealer } from './runner-transport.js';
import {
    clearPersistedAttempt, clearQueuedSubmission, loadPersistedAttempt, loadQueuedSubmission,
    reconcileAnswers, saveQueuedSubmission, savePersistedAttempt,
} from './runner-persistence.js';
import {
    notice, renderHistory, renderIntro, renderMap, renderQuestion, renderReport,
    renderReviewQuestion, renderSubmitDialog,
} from './runner-view.js';
import {
    actionForKey, bindShortcut, clearShortcut as clearSettingsShortcut, effectiveSpeed, loadSettings,
    resetShortcuts as resetSettingsShortcuts, saveSettings, setFontSize, setNavigation as setSettingsNavigation,
    setSound as setSettingsSound, setSpeed as setSettingsSpeed, setTheme as setSettingsTheme,
    shortcutsAreLive, stepFontSize,
} from './runner-settings.js';
import { addHighlight, clearHighlights, loadHighlights, loadNotes, saveNote } from './runner-study.js';
import { applyFontSize, applyTheme, playFeedbackTone } from './runner-settings-effects.js';
import { renderSettings } from './runner-settings-view.js';

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
let turbo = null;
let autoAdvanceTimer = null;
let deadlineTimer = null;
let summary = null;
let reviewPosition = 1;
let reviewEntry = null;
let dialog = null;
/** 'map' | 'submit' | 'settings' | null -- which dialog `dialog` currently holds, so Escape and the shortcut guard know what they are closing. */
let dialogKind = null;
let banner = null;
let pageError = null;

/*
 * Study tools, keyed by question id so they survive into the next attempt --
 * see runner-study.js on why position-keying would silently reattach a note
 * to a different question once the paper is shuffled.
 */
let studyNotes = {};
let studyHighlights = {};
let studyHint = '';
let studyOpen = false;

let settings = loadSettings();
let settingsUi = { tab: 'general', rebinding: null };
applyFontSize(settings);
applyTheme(settings);

function draw() {
    const frame = document.createDocumentFragment();
    if (banner) frame.append(banner);
    if (pageError) frame.append(pageError);

    if (phase === 'intro' && assessment) {
        frame.append(renderIntro(assessment, { start, openSettings, openHistory }));
    } else if (phase === 'question' && state) {
        const question = questions.get(state.position);
        frame.append(question
            ? renderQuestion(state, question, sync.status, questionActions, state.reveals.get(state.position) ?? null, {
                note: studyNotes[question.id] ?? '',
                ranges: studyHighlights[question.id] ?? [],
                hint: studyHint,
                open: studyOpen,
            })
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

    // draw() rebuilds the whole tree, so the rail's scroll position is gone
    // every time -- on a forty-question paper it would sit at question one
    // while the student worked in the thirties. Bring the current pill back
    // into view, scrolling only the rail itself: `block: 'nearest'` leaves it
    // alone when the pill is already visible, and preventing the page from
    // scrolling matters because the rail is sticky beside a card the student
    // may have scrolled down inside.
    const currentPill = root.querySelector('.x-rail__pill.is-current');
    if (currentPill && typeof currentPill.scrollIntoView === 'function') {
        currentPill.scrollIntoView({ block: 'nearest', inline: 'nearest' });
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

/**
 * The server embeds this assessment's catalogue row directly into the page
 * (ExamAttemptPage / PageRenderer::embedJson) whenever it can resolve one, so
 * the common case needs no network call at all here. The whole-catalogue GET
 * below only runs as a fallback -- when the server could not resolve the row
 * (e.g. the exam service was unavailable) or chose not to embed it -- and it
 * is the exact same lookup this page used to do unconditionally.
 */
function readEmbeddedIntro() {
    const node = document.getElementById('assessment-intro');
    if (!node) return null;
    try {
        return JSON.parse(node.textContent);
    } catch {
        return null;
    }
}

async function fetchAssessmentFromCatalog() {
    const catalog = await transport.api.get(`/workspaces/${encodeURIComponent(workspaceId)}/assessments`);
    return (Array.isArray(catalog) ? catalog : []).find((item) => item.id === assessmentId) ?? null;
}

async function loadAssessment() {
    try {
        assessment = readEmbeddedIntro() ?? await fetchAssessmentFromCatalog();
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
        const serverAnswers = attempt.answers || {};
        const serverRevision = Number(attempt.revision || 1);
        // Reload/resume: merge whatever this browser had saved locally back
        // on top of the server's own answers before anything is drawn. See
        // runner-persistence.js's reconcileAnswers() doc comment for why
        // local always wins here.
        const persisted = loadPersistedAttempt(attempt.attempt_id);
        const reconciled = reconcileAnswers(
            { answers: serverAnswers, revision: serverRevision, attemptId: attempt.attempt_id },
            persisted,
        );
        state = createAttemptState({
            attemptId: attempt.attempt_id,
            assessmentId,
            title: attempt.title,
            questionCount: Number(attempt.question_count || 0),
            revision: serverRevision,
            answers: reconciled.answers,
            mode: attempt.mode || 'assessment',
            deadlineAt: attempt.deadline_at ?? null,
        });
        // createAttemptState seeded savedAnswers from the (merged) answers it
        // was given; pin it back to what the server actually confirmed, so
        // pendingAnswers()/hasUnsavedAnswers() correctly see any local-only
        // edit the merge introduced as still needing a save.
        state.savedAnswers = { ...serverAnswers };
        state.flagged = new Set(reconciled.flagged);
        state.struck = new Map(Object.entries(reconciled.struck).map(([position, indices]) => [
            Number(position), new Set(Array.isArray(indices) ? indices : []),
        ]));

        questions = new QuestionWindow(transport, state.attemptId, state.questionCount);
        // start-attempt collapses the old start-then-read round trip by
        // returning position 1 already shaped (ExamService::startAttempt()'s
        // rate-guard-permitting bonus). Seeding it here means the first
        // render of question 1 needs no network call at all. When the guard
        // was exhausted, first_question is simply absent and the normal
        // QuestionWindow.load(1) below runs exactly as it always has.
        if (attempt.first_question) {
            questions.cache.set(1, attempt.first_question);
        }
        sync = new AnswerSync(transport, state, {
            onStateChange: (status) => {
                if (status === 'saved') persistNow();
                if (phase === 'question') draw();
            },
        });
        turbo = new TurboRevealer(transport);
        // Resuming lands on the first gap, not on question one: that is where
        // the student actually stopped -- unless the local snapshot recorded
        // exactly where the student was, which is more precise than "first
        // gap" whenever the answers happened to be complete around it.
        state.position = Number.isFinite(reconciled.position)
            ? clampPosition(state, reconciled.position)
            : (nextUnanswered(state) ?? 1);
        phase = 'question';
        // Notes and highlights belong to the assessment, not to this attempt,
        // so they are read once here and carry whatever the student wrote in
        // earlier attempts at the same questions.
        studyNotes = loadNotes(state.assessmentId);
        studyHighlights = loadHighlights(state.assessmentId);
        studyHint = '';
        studyOpen = false;
        startDeadlineTimer();
        persistNow();
        // The merge above may have introduced answers the server has not
        // seen yet (a local edit whose earlier save response never arrived).
        // Fire the normal save machinery now rather than waiting for the
        // student's next keystroke.
        if (hasUnsavedAnswers(state)) sync.schedule();
        draw();
        await showQuestion(state.position);
        await checkQueuedSubmission();
    } catch (error) {
        showError(error, start);
        draw();
    }
}

/** Writes the current answers/position/flags/struck to local storage. Content (questions, choices, explanations) never passes through here -- see runner-persistence.js. */
function persistNow() {
    if (!state) return;
    savePersistedAttempt(state.attemptId, {
        revision: state.revision,
        answers: state.answers,
        position: state.position,
        flagged: [...state.flagged],
        struck: Object.fromEntries([...state.struck].map(([position, indices]) => [position, [...indices]])),
        savedAt: Date.now(),
    });
}

async function showQuestion(position) {
    cancelAutoAdvance();
    state.position = clampPosition(state, position);
    persistNow();
    draw();
    arriveForTurbo(state.position);
    if (questions.has(state.position)) return;
    try {
        await questions.load(state.position);
        clearError();
    } catch (error) {
        showError(error, () => showQuestion(state.position));
    }
    draw();
}

/**
 * فوق‌سریع: the current question's answer is fetched the moment the student
 * lands on it, one request at a time (TurboRevealer), only in learning mode,
 * and never for a question the student is not currently on. Compare
 * ExamQuestionRateGuard, which this deliberately never outruns.
 */
function arriveForTurbo(position) {
    if (effectiveSpeed(settings, state.mode) !== 'turbo' || state.mode !== 'learning') return;
    if (state.reveals.has(position)) return;
    turbo.arrive(state.attemptId, position, (error, revealedPosition, payload) => {
        if (error) {
            if (revealedPosition === state.position) showError(error, null);
            if (phase === 'question') draw();
            return;
        }
        state.reveals.set(revealedPosition, payload);
        if (revealedPosition === state.position) draw();
    });
}

function cancelAutoAdvance() {
    if (autoAdvanceTimer === null) return;
    clearTimeout(autoAdvanceTimer);
    autoAdvanceTimer = null;
}

/**
 * سریع: after a correct answer, move on by itself after a short beat. A
 * wrong answer always stops here -- the student reads it instead.
 */
function scheduleAutoAdvance() {
    cancelAutoAdvance();
    autoAdvanceTimer = setTimeout(() => {
        autoAdvanceTimer = null;
        if (phase !== 'question') return;
        const target = afterAnswer(state, state.position);
        if (target.kind === 'review') { openSubmit(); return; }
        showQuestion(target.position);
    }, 900);
}

/* ----------------------------------------------------------------- timer */

/**
 * آزمون زمان‌دار: the countdown in the bar. This is a display, not a rule --
 * the deadline it mirrors was already fixed server-side when the attempt
 * started, and the server refuses/closes a late submission on its own even
 * if this timer never ran at all (ExamService::isExpired). Ticking is
 * skipped while a dialog is open so it never steals focus from one.
 */
function startDeadlineTimer() {
    stopDeadlineTimer();
    if (!state.deadlineAt) return;
    deadlineTimer = setInterval(() => {
        if (phase !== 'question' || !state) return;
        const remaining = remainingSeconds(state);
        if (remaining === 0) {
            stopDeadlineTimer();
            autoSubmitOnTimeout();
            return;
        }
        if (dialog === null) draw();
    }, 1000);
}

function stopDeadlineTimer() {
    if (deadlineTimer === null) return;
    clearInterval(deadlineTimer);
    deadlineTimer = null;
}

/**
 * A submit can be refused with attempt_deadline_passed even though the
 * server already scored and closed the attempt -- from the last answers it
 * had saved, not this request's (see ExamService::submitAttempt()'s late
 * path). That leaves the attempt genuinely gone: any further read, save or
 * submit against it fails too, and a background tab's throttled timer is
 * enough to trigger this even for a student who finished on time. So this
 * is not a plain error to show and stop -- it is treated as "the exam is
 * over", by fetching the summary the server already computed and moving on
 * to the report, the same place a normal submit would have landed.
 *
 * @returns {Promise<boolean>} whether the closure was recovered from (the caller should not also show `error` as a failure)
 */
async function recoverFromDeadlineClosure(error) {
    if (!(error instanceof ApiError) || error.code !== 'attempt_deadline_passed') return false;
    try {
        summary = await transport.review(state.attemptId);
    } catch {
        return false; // the server's own account could not be fetched either; fall through to the generic error.
    }
    state.submitted = true;
    state.submitQueued = false;
    // The attempt is closed server-side either way; nothing local is left
    // to resume or retry.
    clearPersistedAttempt(state.attemptId);
    clearQueuedSubmission(state.attemptId);
    dialog = null;
    dialogKind = null;
    phase = 'report';
    stopDeadlineTimer();
    pageError = notice(
        'warning', 'زمان آزمون تمام شد',
        'پیش از آن‌که این درخواست به سرور برسد، زمان آزمون تمام شده و آزمون با آخرین پاسخ‌های ذخیره‌شده بسته و نمره‌گذاری شده بود.',
    );
    return true;
}

/** Records that a submit could not reach the server because the connection is down, so it can be retried without the student having to notice or act. */
function queueOfflineSubmission() {
    saveQueuedSubmission(state.attemptId, {
        revision: state.revision,
        answers: state.answers,
        queuedAt: Date.now(),
    });
    state.submitQueued = true;
}

/**
 * Retries a submission that was queued while offline. Mirrors
 * AnswerSync.flush()'s "offline is not a failure to act on" rule: a fresh
 * offline error here just leaves the queue marker in place for the next
 * reconnect or page load. A genuine server rejection (deadline passed,
 * revision conflict, ...) is surfaced the same way an ordinary submit
 * failure already is -- recoverFromDeadlineClosure/showError -- and the
 * queue marker is dropped, since retrying an already-rejected payload again
 * on every future reconnect would not help.
 */
async function attemptQueuedSubmit(queued) {
    try {
        summary = await transport.submit(state.attemptId, queued.revision, queued.answers);
        state.submitted = true;
        state.submitQueued = false;
        clearPersistedAttempt(state.attemptId);
        clearQueuedSubmission(state.attemptId);
        dialog = null;
        dialogKind = null;
        phase = 'report';
        stopDeadlineTimer();
        clearError();
    } catch (error) {
        if (error instanceof ApiError && error.isOffline) return;
        if (await recoverFromDeadlineClosure(error)) return;
        state.submitQueued = false;
        clearQueuedSubmission(state.attemptId);
        showError(error, null);
    } finally {
        draw();
    }
}

/**
 * Checked once right after start() resolves, covering the case the
 * in-memory-only reconnect handler below cannot: the whole tab reloaded
 * while a submission was queued offline.
 */
async function checkQueuedSubmission() {
    if (!state) return;
    const queued = loadQueuedSubmission(state.attemptId);
    if (!queued) return;
    state.submitQueued = true;
    draw();
    if (hasUnsavedAnswers(state)) {
        try {
            await sync.flush();
        } catch {
            // Still offline (or another failure) -- attemptQueuedSubmit
            // below will find the same condition and leave the queue marker
            // in place rather than doing anything drastic.
        }
    }
    await attemptQueuedSubmit(queued);
}

/** The client's mirror of the deadline hit zero: submit whatever is saved rather than making the student click through, since every extra second is now server-refused anyway. */
async function autoSubmitOnTimeout() {
    if (!state || state.submitting || state.submitted) return;
    cancelAutoAdvance();
    state.submitting = true;
    dialog = null;
    dialogKind = null;
    draw();
    try {
        if (hasUnsavedAnswers(state)) await sync.flush().catch(() => {});
        summary = await transport.submit(state.attemptId, state.revision, state.answers);
        state.submitted = true;
        phase = 'report';
        pageError = notice('warning', 'زمان آزمون تمام شد', 'آزمون به‌صورت خودکار با آخرین پاسخ‌های ذخیره‌شده ثبت شد.');
        clearPersistedAttempt(state.attemptId);
        clearQueuedSubmission(state.attemptId);
    } catch (error) {
        if (error instanceof ApiError && error.isOffline) {
            // The deadline hit while offline: do not lie about having
            // submitted. Queue it so it goes out the moment the connection
            // returns, same as a student-initiated submit would.
            queueOfflineSubmission();
            pageError = notice(
                'warning', 'زمان آزمون تمام شد',
                'اتصال اینترنت قطع است. ثبت خودکار آزمون در صف ماند و به‌محض وصل شدن انجام می‌شود.',
            );
        } else if (!(await recoverFromDeadlineClosure(error))) {
            // Most likely a genuine failure; show it rather than failing
            // silently.
            showError(error, null);
        }
    } finally {
        state.submitting = false;
        draw();
    }
}

/* --------------------------------------------------------------- question */

const questionActions = {
    setStudyOpen(open) {
        studyOpen = open === true;
    },

    saveNote(questionId, text) {
        saveNote(state.assessmentId, questionId, text);
        studyNotes[questionId] = typeof text === 'string' ? text.trim() : '';
        if (studyNotes[questionId] === '') delete studyNotes[questionId];
        // Deliberately no draw(): redrawing on every keystroke would rebuild
        // the textarea and drop the caret to the end of the note.
    },

    /*
     * Turns the browser's current selection into a pair of offsets.
     *
     * The offsets have to be measured against the *whole* stem, but a
     * selection reports its position within whichever text node it happens
     * to start in -- and once a question has highlights, the stem is several
     * nodes rather than one. Walking the paragraph's child nodes and
     * accumulating their lengths converts the node-local offsets into
     * stem-wide ones, so highlighting works the same on the second highlight
     * as on the first.
     */
    highlightSelection(questionId) {
        const selection = window.getSelection ? window.getSelection() : null;
        const paragraph = document.querySelector('.x-question__prompt');
        if (!selection || selection.rangeCount === 0 || !paragraph) return;

        const range = selection.getRangeAt(0);
        if (range.collapsed || !paragraph.contains(range.commonAncestorContainer)) {
            // Says so in the drawer rather than raising a page-level banner:
            // this is a nudge about how the control works, not a failure, and
            // the answer is two lines below the button that was just pressed.
            studyHint = 'اول بخشی از صورت سؤال را انتخاب کن، بعد این دکمه را بزن.';
            draw();
            return;
        }
        studyHint = '';

        const offsetOf = (node, offset) => {
            let total = 0;
            const walker = document.createTreeWalker(paragraph, NodeFilter.SHOW_TEXT);
            let current = walker.nextNode();
            while (current) {
                if (current === node) return total + offset;
                total += current.textContent.length;
                current = walker.nextNode();
            }
            return total;
        };

        const start = offsetOf(range.startContainer, range.startOffset);
        const end = offsetOf(range.endContainer, range.endOffset);
        if (end <= start) return;

        addHighlight(state.assessmentId, questionId, { start, end }, paragraph.textContent.length);
        studyHighlights = loadHighlights(state.assessmentId);
        selection.removeAllRanges();
        draw();
    },

    clearHighlights(questionId) {
        clearHighlights(state.assessmentId, questionId);
        studyHighlights = loadHighlights(state.assessmentId);
        draw();
    },

    choose(index) {
        setAnswer(state, state.position, index);
        sync.schedule();
        persistNow();

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
        persistNow();
        draw();
    },
    clear() {
        clearAnswer(state, state.position);
        sync.schedule();
        persistNow();
        draw();
    },
    toggleFlag() {
        toggleFlag(state, state.position);
        persistNow();
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
        const position = state.position;
        try {
            const payload = await transport.reveal(state.attemptId, position);
            state.reveals.set(position, payload);
            clearError();
            afterReveal(position, payload);
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
        dialogKind = 'map';
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
    openSettings,
    cardWheel(event) { handleHorizontalWheel(event); },
    marginWheel(event) { handleVerticalMarginWheel(event); },
    cardTouchStart(event) { handleTouchStart(event); },
    cardTouchMove(event) { handleTouchMove(event); },
    cardTouchEnd(event) { handleTouchEnd(event); },
};

/**
 * Sound and سریع auto-advance both key off "an answer was actually given and
 * we now know if it was right" -- true only when reveal() ran because the
 * student chose something, never for the pre-answer "بلد نیستم" reveal or a
 * فوق‌سریع arrival, both of which pass through the same reveal machinery
 * without a selection yet.
 */
function afterReveal(position, payload) {
    const chosen = state.answers[String(position)];
    if (chosen === undefined) return;
    const correct = chosen === payload.answer;
    if (state.mode !== 'assessment') playFeedbackTone(settings, correct ? 'correct' : 'incorrect');
    if (correct && effectiveSpeed(settings, state.mode) === 'fast') scheduleAutoAdvance();
}

const mapActions = {
    setFilter(filter) {
        state.filter = filter;
        dialog = renderMap(state, mapActions);
        draw();
    },
    goTo(position) {
        dialog = null;
        dialogKind = null;
        showQuestion(position);
    },
    close() {
        dialog = null;
        dialogKind = null;
        draw();
    },
};

/* ----------------------------------------------------------------- submit */

function openSubmit() {
    dialogKind = 'submit';
    dialog = renderSubmitDialog(state, submitActions);
    draw();
}

const submitActions = {
    close() {
        dialog = null;
        dialogKind = null;
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
            dialogKind = null;
            phase = 'report';
            stopDeadlineTimer();
            clearError();
            clearPersistedAttempt(state.attemptId);
            clearQueuedSubmission(state.attemptId);
        } catch (error) {
            if (error instanceof ApiError && error.isOffline) {
                // Not a failure to dismiss and retry by hand: the answers are
                // safe (saved locally and, once a connection exists again,
                // to the server) and the submission itself is queued rather
                // than lost. Saying "submitted" here would be a lie the
                // report screen could never take back.
                queueOfflineSubmission();
                dialog = null;
                dialogKind = null;
                clearError();
            } else if (!(await recoverFromDeadlineClosure(error))) {
                showError(error, null);
            }
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

/* ---------------------------------------------------------------- settings */

function openSettings() {
    dialogKind = 'settings';
    settingsUi = { ...settingsUi, rebinding: null };
    dialog = renderSettings(settings, settingsUi, state?.mode ?? null, settingsActions);
    draw();
}

function closeSettings() {
    dialog = null;
    dialogKind = null;
    settingsUi = { ...settingsUi, rebinding: null };
    draw();
}

/** Every settings mutation goes through here: persist, apply, and redraw whichever dialog is open. */
function updateSettings(next) {
    settings = next;
    saveSettings(settings);
    applyFontSize(settings);
    applyTheme(settings);
    if (dialogKind === 'settings') dialog = renderSettings(settings, settingsUi, state?.mode ?? null, settingsActions);
    draw();
}

const settingsActions = {
    close: closeSettings,
    setTab(tab) {
        settingsUi = { ...settingsUi, tab, rebinding: null };
        dialog = renderSettings(settings, settingsUi, state?.mode ?? null, settingsActions);
        draw();
    },
    setFont(target, percent) { updateSettings(setFontSize(settings, target, percent)); },
    // Dragging the slider only paints the CSS variable; it does not persist
    // or redraw, so the native drag gesture is never interrupted mid-way.
    previewFont(target, percent) {
        applyFontSize({ ...settings, fontSize: { ...settings.fontSize, [target]: percent } });
    },
    stepFont(target, direction) { updateSettings(stepFontSize(settings, target, direction)); },
    setTheme(theme) { updateSettings(setSettingsTheme(settings, theme)); },
    setSound(enabled) { updateSettings(setSettingsSound(settings, enabled)); },
    setSpeed(speed) { updateSettings(setSettingsSpeed(settings, speed)); },
    setNavigation(key, enabled) { updateSettings(setSettingsNavigation(settings, key, enabled)); },
    startRebind(action) {
        settingsUi = { ...settingsUi, rebinding: action };
        dialog = renderSettings(settings, settingsUi, state?.mode ?? null, settingsActions);
        draw();
    },
    clearShortcut(action) { updateSettings(clearSettingsShortcut(settings, action)); },
    resetShortcuts() { updateSettings(resetSettingsShortcuts(settings)); },
};

/* ----------------------------------------------------------------- history */

/** تاریخچه‌ی تلاش‌ها, opened from the intro card. */
async function openHistory() {
    dialogKind = 'history';
    dialog = renderHistory([], historyActions, { loading: true });
    draw();
    try {
        const attempts = await transport.attemptHistory(assessmentId);
        if (dialogKind !== 'history') return; // closed while the request was in flight
        dialog = renderHistory(Array.isArray(attempts) ? attempts : [], historyActions);
        draw();
    } catch (error) {
        if (dialogKind !== 'history') return;
        dialog = null;
        dialogKind = null;
        showError(error, openHistory);
        draw();
    }
}

const historyActions = {
    close() {
        dialog = null;
        dialogKind = null;
        draw();
    },
};

/* --------------------------------------------------------- question gestures */

/*
 * اسکرول افقی روی سؤال، اسکرول عمودی کنار سؤال، سوایپ لمسی روی سؤال. Every
 * one of these is a convenience layered on top of ordinary browser behaviour
 * -- ordinary vertical scrolling, ordinary text selection -- and each guards
 * against swallowing it, per setting, before doing anything.
 */

const WHEEL_THRESHOLD = 24;
const WHEEL_COOLDOWN_MS = 500;
const SWIPE_INTENT_PX = 10;
const SWIPE_DISTANCE_PX = 60;

let lastWheelNav = 0;

function wheelNavigate(deltaSign) {
    const now = Date.now();
    if (now - lastWheelNav < WHEEL_COOLDOWN_MS) return;
    lastWheelNav = now;
    if (deltaSign < 0) questionActions.next(); else questionActions.previous();
}

/** Over the card: only a genuinely horizontal gesture is taken, so ordinary vertical scrolling never stops working. */
function handleHorizontalWheel(event) {
    if (!settings.navigation.horizontalScroll) return;
    if (Math.abs(event.deltaX) <= Math.abs(event.deltaY)) return;
    if (Math.abs(event.deltaX) < WHEEL_THRESHOLD) return;
    event.preventDefault();
    wheelNavigate(event.deltaX);
}

/** In the empty gutter beside the card: nothing there needs to scroll, so any vertical wheel input navigates instead. */
function handleVerticalMarginWheel(event) {
    if (!settings.navigation.verticalScroll) return;
    if (Math.abs(event.deltaY) < WHEEL_THRESHOLD) return;
    event.preventDefault();
    wheelNavigate(-event.deltaY);
}

let touchStartX = null;
let touchStartY = null;
let touchIsHorizontal = false;

function handleTouchStart(event) {
    if (!settings.navigation.swipe || event.touches.length !== 1) {
        touchStartX = null;
        return;
    }
    touchStartX = event.touches[0].clientX;
    touchStartY = event.touches[0].clientY;
    touchIsHorizontal = false;
    // No preventDefault here on purpose: a long-press text selection must
    // still be able to start.
}

function handleTouchMove(event) {
    if (touchStartX === null) return;
    const dx = event.touches[0].clientX - touchStartX;
    const dy = event.touches[0].clientY - touchStartY;
    if (!touchIsHorizontal) {
        if (Math.abs(dx) < SWIPE_INTENT_PX && Math.abs(dy) < SWIPE_INTENT_PX) return;
        touchIsHorizontal = Math.abs(dx) > Math.abs(dy);
        if (!touchIsHorizontal) {
            // A vertical drag: this is a scroll or a selection, not a swipe.
            // Stop tracking and leave the browser's own handling alone.
            touchStartX = null;
            return;
        }
    }
    // Confirmed horizontal: stop the page from also panning under the
    // finger while the gesture plays out.
    event.preventDefault();
}

function handleTouchEnd(event) {
    if (touchStartX === null || !touchIsHorizontal) {
        touchStartX = null;
        return;
    }
    const endX = event.changedTouches[0]?.clientX ?? touchStartX;
    const dx = endX - touchStartX;
    touchStartX = null;
    if (Math.abs(dx) < SWIPE_DISTANCE_PX) return;
    if (dx < 0) questionActions.next(); else questionActions.previous();
}

/* ------------------------------------------------------------------ setup */

/*
 * Keyboard shortcuts. Answering forty questions is a keyboard task, and
 * reaching for the mouse for every one of them is the difference between a
 * tool and a chore. Bindings come from settings.shortcuts (رفتار پیش‌فرض:
 * 1-9, arrows, F, M, R) so the student can rebind them from پیشرفته.
 *
 * Deliberately not bound while a dialog is open or a field has focus (see
 * shortcutsAreLive): a shortcut that fires while someone is typing is worse
 * than no shortcut.
 */
document.addEventListener('keydown', (event) => {
    // Capturing a new binding for the settings sheet takes priority over
    // everything else, including Escape -- while rebinding, Escape cancels
    // the rebind in progress rather than closing the whole sheet.
    if (settingsUi.rebinding) {
        if (['Shift', 'Control', 'Alt', 'Meta', 'Tab'].includes(event.key)) return;
        event.preventDefault();
        if (event.key !== 'Escape') {
            updateSettings(bindShortcut(settings, settingsUi.rebinding, event.key));
        }
        settingsUi = { ...settingsUi, rebinding: null };
        if (dialogKind === 'settings') dialog = renderSettings(settings, settingsUi, state?.mode ?? null, settingsActions);
        draw();
        return;
    }
    if (event.key === 'Escape' && dialog) {
        event.preventDefault();
        dialog = null;
        dialogKind = null;
        draw();
        return;
    }
    const target = event.target;
    const live = shortcutsAreLive({
        phase, hasDialog: dialog !== null,
        targetTagName: target instanceof HTMLElement ? target.tagName : null,
        isContentEditable: target instanceof HTMLElement && target.isContentEditable,
    });
    if (!live || event.ctrlKey || event.metaKey || event.altKey) return;

    const action = actionForKey(settings.shortcuts, event.key);
    if (action === null) return;
    if (action === 'reveal' && state.mode !== 'learning') return;

    if (action.startsWith('choice_')) {
        const question = questions.get(state.position);
        const choiceIndex = Number(action.slice('choice_'.length)) - 1;
        if (!question || choiceIndex >= (question.choices || []).length) return;
        event.preventDefault();
        questionActions.choose(choiceIndex);
        return;
    }

    const handler = {
        previous: questionActions.previous,
        next: questionActions.next,
        flag: questionActions.toggleFlag,
        map: questionActions.openMap,
        reveal: questionActions.reveal,
    }[action];
    if (handler) {
        event.preventDefault();
        handler();
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
        // A submission queued while offline gets the same treatment.
        if (state && state.submitQueued) {
            const queued = loadQueuedSubmission(state.attemptId);
            if (queued) attemptQueuedSubmit(queued);
            else state.submitQueued = false;
        }
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
