/*
 * Local persistence for an in-progress exam attempt, and nothing else.
 *
 * The exam runner keeps every question, choice and explanation in memory
 * only (see runner-transport.js's QuestionWindow) -- a cached paper is a
 * downloadable paper, and this module must never be the exception that
 * leaks one to disk. So the two save functions below do not take "the
 * state object" and pick fields out of it; they take *only* the
 * allow-listed fields as named parameters. A future caller that widens what
 * it passes cannot widen what gets written, because anything not named in
 * the signature is silently dropped by destructuring -- there is no `...rest`
 * anywhere in this file.
 *
 * What is persisted, and why it is safe to persist:
 *   - attemptId, revision, answers, position, flagged, struck, savedAt --
 *     all of it is the student's own input plus bookkeeping about it, never
 *     question/choice/explanation content. Flags and struck-out choices in
 *     particular have no server-side representation at all today, so this
 *     is the only place they can survive a reload -- that is in scope and
 *     expected, not a leak.
 *   - a queued offline submission (revision, answers, queuedAt) -- again,
 *     only the student's own answers, kept separately from the in-progress
 *     snapshot so "answers still being edited" and "a submission waiting to
 *     go out" can be read and cleared independently.
 *
 * Same storage discipline as runner-settings.js, the one other place this
 * codebase touches localStorage: every access is wrapped in try/catch,
 * because a private/incognito window can throw on the access itself (not
 * only return a missing key), and every write returns a boolean rather than
 * throwing, so a caller that cares can react without a try/catch of its own.
 * No IndexedDB, Cache API or service worker is introduced here -- this repo
 * has none of those anywhere, and localStorage is the proven pattern.
 */

const ATTEMPT_KEY_PREFIX = 'fanoos.examAttempt.v1.';
const QUEUE_KEY_PREFIX = 'fanoos.examSubmitQueue.v1.';

function attemptKey(attemptId) {
    return `${ATTEMPT_KEY_PREFIX}${attemptId}`;
}

function queueKey(attemptId) {
    return `${QUEUE_KEY_PREFIX}${attemptId}`;
}

/**
 * Saves an in-progress attempt's answers, position, flags and struck-out
 * choices -- exactly these fields, named explicitly so nothing else can ride
 * along. Returns whether the write landed; never throws.
 */
export function savePersistedAttempt(attemptId, { revision = null, answers = {}, position = null, flagged = [], struck = {}, savedAt = Date.now() } = {}) {
    try {
        window.localStorage.setItem(attemptKey(attemptId), JSON.stringify({
            attemptId, revision, answers, position, flagged, struck, savedAt,
        }));
        return true;
    } catch {
        return false;
    }
}

/**
 * Loads a previously persisted attempt snapshot, or null when there is
 * nothing usable -- missing, corrupted, half-written, or for a different
 * attempt id than asked for. Never throws.
 */
export function loadPersistedAttempt(attemptId) {
    try {
        const raw = window.localStorage.getItem(attemptKey(attemptId));
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return null;
        if (parsed.attemptId !== attemptId) return null;
        if (!parsed.answers || typeof parsed.answers !== 'object' || Array.isArray(parsed.answers)) return null;
        return {
            attemptId: parsed.attemptId,
            revision: Number.isFinite(parsed.revision) ? parsed.revision : null,
            answers: { ...parsed.answers },
            position: Number.isFinite(parsed.position) ? parsed.position : null,
            flagged: Array.isArray(parsed.flagged) ? [...parsed.flagged] : [],
            struck: parsed.struck && typeof parsed.struck === 'object' && !Array.isArray(parsed.struck) ? { ...parsed.struck } : {},
            savedAt: Number.isFinite(parsed.savedAt) ? parsed.savedAt : null,
        };
    } catch {
        return null;
    }
}

/** Drops a persisted attempt snapshot. Call once the attempt is genuinely finished -- nothing left to resume. Never throws. */
export function clearPersistedAttempt(attemptId) {
    try {
        window.localStorage.removeItem(attemptKey(attemptId));
        return true;
    } catch {
        return false;
    }
}

/**
 * Saves a submission the student tried to send while offline, so it can be
 * retried on reconnect or on the next page load without the student having
 * to notice and act on it themselves.
 */
export function saveQueuedSubmission(attemptId, { revision = null, answers = {}, queuedAt = Date.now() } = {}) {
    try {
        window.localStorage.setItem(queueKey(attemptId), JSON.stringify({ attemptId, revision, answers, queuedAt }));
        return true;
    } catch {
        return false;
    }
}

export function loadQueuedSubmission(attemptId) {
    try {
        const raw = window.localStorage.getItem(queueKey(attemptId));
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return null;
        if (parsed.attemptId !== attemptId) return null;
        if (!parsed.answers || typeof parsed.answers !== 'object' || Array.isArray(parsed.answers)) return null;
        return {
            attemptId: parsed.attemptId,
            revision: Number.isFinite(parsed.revision) ? parsed.revision : null,
            answers: { ...parsed.answers },
            queuedAt: Number.isFinite(parsed.queuedAt) ? parsed.queuedAt : null,
        };
    } catch {
        return null;
    }
}

export function clearQueuedSubmission(attemptId) {
    try {
        window.localStorage.removeItem(queueKey(attemptId));
        return true;
    } catch {
        return false;
    }
}

/**
 * Reconciles a fresh server response against whatever was persisted
 * locally, with no storage and no DOM -- pure data in, data out, so the
 * rule can be tested directly.
 *
 * `server` is `{ answers, revision }` (and may carry `attemptId`, which is
 * checked against `persisted.attemptId` when both are present -- a belt on
 * top of loadPersistedAttempt() already filtering by the key it was asked
 * to read, in case a caller ever hands this function a snapshot read under
 * a different id than the one it is reconciling against).
 *
 * The rule, and why: local answers always win, merged on top of the
 * server's. Every save the runner ever makes sends the *whole* answer set,
 * never a delta (see AnswerSync/transport.save), so resending an answer the
 * server already has is a harmless no-op. But the reverse -- discarding the
 * local copy in favour of the server's -- can lose real work: if the save
 * that recorded an answer actually landed but its *response* was the thing
 * lost to a dropped connection, the client never learns the server has it,
 * and if that local answer also never made it into this reload's server
 * snapshot for some other reason, trusting the server here would erase an
 * answer the student watched the UI accept. Local-wins is the only rule
 * that cannot silently do that.
 *
 * This is deliberately NOT gated on `persisted.revision === server.revision`.
 * A mismatch does not mean the local copy is stale garbage -- it is exactly
 * as likely to mean the local save landed under a revision number the
 * client's last-known `state.revision` never caught up to (its response was
 * the thing that got lost). That is precisely the ambiguous case, and
 * ambiguity is not a reason to prefer the version that is provably a strict
 * subset of what the student typed.
 *
 * position/flagged/struck have no server-side representation at all, so
 * there is nothing to reconcile for them -- they come straight from
 * `persisted` when present.
 */
export function reconcileAnswers(server, persisted) {
    const serverAnswers = server && server.answers && typeof server.answers === 'object' ? server.answers : {};
    const unchanged = { answers: { ...serverAnswers }, position: null, flagged: [], struck: {} };

    if (!persisted || typeof persisted !== 'object') return unchanged;
    if (!persisted.answers || typeof persisted.answers !== 'object') return unchanged;
    if (server && persisted.attemptId !== undefined && server.attemptId !== undefined && persisted.attemptId !== server.attemptId) {
        return unchanged;
    }

    return {
        answers: { ...serverAnswers, ...persisted.answers },
        position: Number.isFinite(persisted.position) ? persisted.position : null,
        flagged: Array.isArray(persisted.flagged) ? [...persisted.flagged] : [],
        struck: persisted.struck && typeof persisted.struck === 'object' ? { ...persisted.struck } : {},
    };
}
