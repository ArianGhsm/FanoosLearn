/*
 * The exam runner's conversation with the server.
 *
 * Three jobs, none of which belong in the view:
 *
 *   1. Fetch one question at a time -- the API never sends a whole paper, by
 *      design, and this keeps it that way.
 *   2. Keep a small window of upcoming questions in memory so a brief
 *      connection drop does not stop the student mid-exam. The window is
 *      deliberately small and in memory only: nothing is written to the
 *      service worker cache or IndexedDB, because a cached paper is a
 *      downloadable paper.
 *   3. Save answers, coalescing and retrying, so answering fast does not
 *      queue up a request per tap and a dropped connection does not lose
 *      what was typed.
 */
import { api, ApiError } from '../foundation/api.js';

/** How far ahead to prefetch. Small on purpose -- see the note above. */
export const PREFETCH_AHEAD = 2;

/** Debounce before a save; long enough to coalesce a burst of answers. */
const SAVE_DELAY_MS = 900;

export class ExamTransport {
    constructor(workspaceId, { request = api } = {}) {
        this.base = `/workspaces/${encodeURIComponent(workspaceId)}`;
        this.api = request;
        this.inFlight = new Map();
        this.saveTimer = null;
        this.savePromise = null;
    }

    start(assessmentId, mode) {
        return this.api.post(
            `${this.base}/assessments/${encodeURIComponent(assessmentId)}/attempts`,
            mode === null || mode === undefined ? {} : { mode },
        );
    }

    reveal(attemptId, position) {
        return this.api.get(`${this.base}/attempts/${encodeURIComponent(attemptId)}/questions/${position}/reveal`);
    }

    /**
     * One question. Concurrent requests for the same position share a single
     * network call -- otherwise a prefetch and a navigation racing each other
     * would burn two tokens from the server's pacing budget for one question.
     */
    question(attemptId, position) {
        const key = `q:${attemptId}:${position}`;
        if (this.inFlight.has(key)) return this.inFlight.get(key);
        const promise = this.api
            .get(`${this.base}/attempts/${encodeURIComponent(attemptId)}/questions/${position}`)
            .finally(() => this.inFlight.delete(key));
        this.inFlight.set(key, promise);
        return promise;
    }

    reviewQuestion(attemptId, position) {
        const key = `r:${attemptId}:${position}`;
        if (this.inFlight.has(key)) return this.inFlight.get(key);
        const promise = this.api
            .get(`${this.base}/attempts/${encodeURIComponent(attemptId)}/review/questions/${position}`)
            .finally(() => this.inFlight.delete(key));
        this.inFlight.set(key, promise);
        return promise;
    }

    review(attemptId) {
        return this.api.get(`${this.base}/attempts/${encodeURIComponent(attemptId)}/review`);
    }

    save(attemptId, revision, answers) {
        return this.api.patch(`${this.base}/attempts/${encodeURIComponent(attemptId)}`, {
            expected_revision: revision,
            answers,
        });
    }

    submit(attemptId, revision, answers) {
        return this.api.post(`${this.base}/attempts/${encodeURIComponent(attemptId)}/submit`, {
            expected_revision: revision,
            answers,
        });
    }
}

/**
 * Loads questions on demand and keeps a short read-ahead window.
 *
 * Prefetch failures are swallowed on purpose: a prefetch is an optimisation,
 * and surfacing its failure would put an error in front of a student whose
 * current question loaded perfectly well. A failure on the question the
 * student is actually looking at is never swallowed.
 */
export class QuestionWindow {
    constructor(transport, attemptId, questionCount, { ahead = PREFETCH_AHEAD } = {}) {
        this.transport = transport;
        this.attemptId = attemptId;
        this.questionCount = questionCount;
        this.ahead = ahead;
        this.cache = new Map();
        this.rateLimitedUntil = 0;
    }

    has(position) {
        return this.cache.has(position);
    }

    get(position) {
        return this.cache.get(position) ?? null;
    }

    /** Fetches `position`, then quietly warms the next few. */
    async load(position) {
        if (this.cache.has(position)) {
            this.warm(position);
            return this.cache.get(position);
        }
        const payload = await this.transport.question(this.attemptId, position);
        this.cache.set(position, payload.question ?? payload);
        this.warm(position);
        return this.cache.get(position);
    }

    warm(from) {
        if (Date.now() < this.rateLimitedUntil) return;
        for (let step = 1; step <= this.ahead; step++) {
            const position = from + step;
            if (position > this.questionCount || this.cache.has(position)) continue;
            this.transport.question(this.attemptId, position).then(
                (payload) => this.cache.set(position, payload.question ?? payload),
                (error) => {
                    // Backing off here is what keeps read-ahead from eating
                    // the pacing budget the student needs to move forward.
                    if (error instanceof ApiError && error.isRateLimited) {
                        const wait = (error.retryAfterSeconds ?? 20) * 1000;
                        this.rateLimitedUntil = Date.now() + wait;
                    }
                },
            );
        }
    }
}

/**
 * Debounced, coalescing answer saving with an explicit flush.
 *
 * Every save sends the whole answer set rather than a delta, so a save that
 * never arrived cannot leave the server holding a partial picture -- the next
 * one that lands is complete on its own.
 */
export class AnswerSync {
    constructor(transport, state, { onStateChange = () => {}, delay = SAVE_DELAY_MS } = {}) {
        this.transport = transport;
        this.state = state;
        this.onStateChange = onStateChange;
        this.delay = delay;
        this.timer = null;
        this.saving = false;
        this.pendingAgain = false;
        this.status = 'idle';
    }

    schedule() {
        this.setStatus('pending');
        if (this.timer !== null) clearTimeout(this.timer);
        this.timer = setTimeout(() => this.flush(), this.delay);
    }

    async flush() {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        if (this.saving) {
            this.pendingAgain = true;
            return;
        }
        this.saving = true;
        this.setStatus('saving');
        try {
            const result = await this.transport.save(
                this.state.attemptId,
                this.state.revision,
                this.state.answers,
            );
            this.state.savedAnswers = { ...this.state.answers };
            this.state.revision = result.revision ?? this.state.revision;
            this.setStatus('saved');
        } catch (error) {
            // Offline is not a failure the student must act on: the answers
            // are still in memory and the next successful save carries them.
            this.setStatus(error instanceof ApiError && error.isOffline ? 'offline' : 'failed');
            throw error;
        } finally {
            this.saving = false;
            if (this.pendingAgain) {
                this.pendingAgain = false;
                this.schedule();
            }
        }
    }

    setStatus(status) {
        this.status = status;
        this.onStateChange(status);
    }
}
