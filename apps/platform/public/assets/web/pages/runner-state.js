/*
 * The exam runner's state, with no DOM and no network in it.
 *
 * Everything that decides *what is true* about an attempt lives here so it
 * can be tested directly: which questions are answered, which are flagged,
 * which choices the student struck out, what the filter is showing, where
 * "next unanswered" goes, and whether the answer queue is in sync.
 *
 * The rendering layer reads this and draws; it never derives its own truth.
 * That split is what keeps a question-map cell and the question itself from
 * ever disagreeing.
 */

/** Positions are 1-based everywhere, matching the API's question positions. */
export function createAttemptState({ attemptId, assessmentId, title, questionCount, revision, answers = {}, mode = 'assessment', deadlineAt = null }) {
    return {
        attemptId,
        assessmentId,
        title,
        questionCount,
        revision,
        /** ISO timestamp fixed by the server when the attempt started, or null for an untimed assessment. A browser timer only mirrors this. */
        deadlineAt,
        /**
         * 'assessment' (a real sitting, no feedback until submitted),
         * 'practice' (right/wrong shown at once, explanation on demand) or
         * 'learning' (answer and explanation shown together, on demand)
         */
        mode,
        /** position -> {answer, explanation} for questions revealed so far */
        reveals: new Map(),
        /** positions whose explanation the student has chosen to read (practice mode gates it; learning shows it as soon as revealed) */
        explanationShown: new Set(),
        /** position -> chosen choice index, in the order the student saw them */
        answers: { ...answers },
        /** position -> question payload from the API, cached once fetched */
        questions: new Map(),
        /** positions the student marked to come back to */
        flagged: new Set(),
        /** position -> Set of choice indices struck out */
        struck: new Map(),
        position: 1,
        filter: 'all',
        /** answers saved to the server; used to know what is still pending */
        savedAnswers: { ...answers },
        submitting: false,
        submitted: false,
        /** A submit was attempted while offline and is waiting to go out on reconnect. No server-side meaning; purely local bookkeeping. */
        submitQueued: false,
    };
}

export function isAnswered(state, position) {
    return Object.prototype.hasOwnProperty.call(state.answers, String(position));
}

export function answeredCount(state) {
    return Object.keys(state.answers).length;
}

export function isExplanationShown(state, position) {
    return state.explanationShown.has(position);
}

export function showExplanation(state, position) {
    state.explanationShown.add(position);
}

export function isFlagged(state, position) {
    return state.flagged.has(position);
}

export function toggleFlag(state, position) {
    if (state.flagged.has(position)) state.flagged.delete(position);
    else state.flagged.add(position);
}

export function isStruck(state, position, choiceIndex) {
    const set = state.struck.get(position);
    return set ? set.has(choiceIndex) : false;
}

/**
 * Striking a choice out is the paper habit of crossing off what you have
 * ruled out. It never changes the answer -- but striking the choice that is
 * currently selected clears the selection, because leaving an answer on a
 * choice the student just rejected is not what they meant.
 */
export function toggleStrike(state, position, choiceIndex) {
    const key = String(position);
    const set = state.struck.get(position) ?? new Set();
    if (set.has(choiceIndex)) {
        set.delete(choiceIndex);
    } else {
        set.add(choiceIndex);
        if (state.answers[key] === choiceIndex) delete state.answers[key];
    }
    state.struck.set(position, set);
}

export function setAnswer(state, position, choiceIndex) {
    state.answers[String(position)] = choiceIndex;
    // Choosing a struck-out option means the student changed their mind about
    // ruling it out; keeping the strike would render the selected answer
    // crossed through.
    const set = state.struck.get(position);
    if (set) set.delete(choiceIndex);
}

export function clearAnswer(state, position) {
    delete state.answers[String(position)];
}

/** Answers written since the last successful save. */
export function pendingAnswers(state) {
    const pending = {};
    for (const [key, value] of Object.entries(state.answers)) {
        if (state.savedAnswers[key] !== value) pending[key] = value;
    }
    for (const key of Object.keys(state.savedAnswers)) {
        if (!Object.prototype.hasOwnProperty.call(state.answers, key)) pending[key] = null;
    }
    return pending;
}

export function hasUnsavedAnswers(state) {
    return Object.keys(pendingAnswers(state)).length > 0;
}

export function markSaved(state, revision) {
    state.savedAnswers = { ...state.answers };
    state.revision = revision;
}

/** The filters the question map offers while an attempt is in progress. */
export const ATTEMPT_FILTERS = ['all', 'answered', 'unanswered', 'flagged'];

export function matchesFilter(state, position, filter = state.filter) {
    if (filter === 'answered') return isAnswered(state, position);
    if (filter === 'unanswered') return !isAnswered(state, position);
    if (filter === 'flagged') return isFlagged(state, position);
    return true;
}

export function visiblePositions(state, filter = state.filter) {
    const positions = [];
    for (let position = 1; position <= state.questionCount; position++) {
        if (matchesFilter(state, position, filter)) positions.push(position);
    }
    return positions;
}

/**
 * The next unanswered question at or after `from`, wrapping once. Returns
 * null when everything is answered -- the caller shows the submit step
 * rather than moving.
 */
export function nextUnanswered(state, from = state.position) {
    for (let step = 0; step < state.questionCount; step++) {
        const position = ((from - 1 + step) % state.questionCount) + 1;
        if (!isAnswered(state, position)) return position;
    }
    return null;
}

export function clampPosition(state, position) {
    if (!Number.isFinite(position)) return state.position;
    return Math.min(Math.max(Math.round(position), 1), state.questionCount);
}

/**
 * Where auto-advance should go after answering `position`.
 *
 * Plain "position + 1" is wrong on the last question and wrong when the
 * student is revisiting gaps: after filling the last blank they should land
 * on the submit step, not be bounced back to a question they already
 * answered.
 */
export function afterAnswer(state, position) {
    if (position < state.questionCount) return { kind: 'question', position: position + 1 };
    const remaining = nextUnanswered(state);
    return remaining === null ? { kind: 'review' } : { kind: 'question', position: remaining };
}

/** Positions with no answer, in order -- the final check before submitting. */
export function unansweredPositions(state) {
    const positions = [];
    for (let position = 1; position <= state.questionCount; position++) {
        if (!isAnswered(state, position)) positions.push(position);
    }
    return positions;
}

export function progressPercent(state) {
    if (state.questionCount === 0) return 0;
    return Math.round((answeredCount(state) / state.questionCount) * 100);
}

/** Seconds until state.deadlineAt, floored and never negative; null for an untimed attempt. */
export function remainingSeconds(state, nowMs = Date.now()) {
    if (!state.deadlineAt) return null;
    const deadlineMs = Date.parse(state.deadlineAt);
    if (Number.isNaN(deadlineMs)) return null;
    return Math.max(0, Math.floor((deadlineMs - nowMs) / 1000));
}

/** The runner bar switches to a warning style at or under this much time left. */
export const TIME_WARNING_SECONDS = 120;

export function isTimeCritical(state, nowMs = Date.now()) {
    const remaining = remainingSeconds(state, nowMs);
    return remaining !== null && remaining <= TIME_WARNING_SECONDS;
}

export function isTimeExpired(state, nowMs = Date.now()) {
    return remainingSeconds(state, nowMs) === 0;
}
