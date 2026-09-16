/*
 * The exam runner's offline persistence: what survives a reload (answers,
 * position, flags, struck-out choices, a queued offline submission) and --
 * just as important -- what structurally cannot reach localStorage no
 * matter what a caller passes in (question/choice/explanation text). No DOM
 * is involved; only the storage discipline and the pure reconciliation rule.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

function fakeStorage() {
    const data = new Map();
    return {
        getItem(key) { return data.has(key) ? data.get(key) : null; },
        setItem(key, value) { data.set(key, String(value)); },
        removeItem(key) { data.delete(key); },
        _dump() { return new Map(data); },
    };
}

function throwingStorage() {
    return {
        get getItem() { throw new Error('SecurityError: storage disabled in a private window'); },
        get setItem() { throw new Error('SecurityError: storage disabled in a private window'); },
        get removeItem() { throw new Error('SecurityError: storage disabled in a private window'); },
    };
}

globalThis.window = globalThis.window ?? {};
window.localStorage = fakeStorage();

const P = await import('../../apps/platform/public/assets/web/pages/runner-persistence.js');

test('an attempt snapshot round-trips through storage: answers, position, flags and struck choices all survive', () => {
    window.localStorage = fakeStorage();

    const saved = P.savePersistedAttempt('attempt-1', {
        revision: 5,
        answers: { 1: 0, 3: 2 },
        position: 3,
        flagged: [2, 5],
        struck: { 1: [1, 2] },
        savedAt: 123456,
    });
    assert.equal(saved, true);

    const loaded = P.loadPersistedAttempt('attempt-1');
    assert.deepEqual(loaded, {
        attemptId: 'attempt-1',
        revision: 5,
        answers: { 1: 0, 3: 2 },
        position: 3,
        flagged: [2, 5],
        struck: { 1: [1, 2] },
        savedAt: 123456,
    });
});

test('loadPersistedAttempt returns null for a missing key, a different attempt id, or a malformed value', () => {
    window.localStorage = fakeStorage();
    assert.equal(P.loadPersistedAttempt('nothing-saved-yet'), null);

    P.savePersistedAttempt('attempt-1', { revision: 1, answers: { 1: 0 }, position: 1, flagged: [], struck: {}, savedAt: 1 });
    // Asking for a different attempt's snapshot under its own key finds
    // nothing -- but even if a caller mixed up keys, the attemptId field
    // inside the payload is checked too.
    assert.equal(P.loadPersistedAttempt('attempt-2'), null);

    window.localStorage.setItem('fanoos.examAttempt.v1.attempt-3', 'not json at all {{{');
    assert.equal(P.loadPersistedAttempt('attempt-3'), null, 'invalid JSON must not throw, and must be treated as nothing usable');

    window.localStorage.setItem('fanoos.examAttempt.v1.attempt-4', JSON.stringify({ attemptId: 'attempt-4', answers: 'not an object' }));
    assert.equal(P.loadPersistedAttempt('attempt-4'), null, 'a non-object answers field is malformed');

    window.localStorage.setItem('fanoos.examAttempt.v1.attempt-5', JSON.stringify([1, 2, 3]));
    assert.equal(P.loadPersistedAttempt('attempt-5'), null, 'a non-object top-level value is malformed');
});

test('clearPersistedAttempt removes only that attempt\'s snapshot', () => {
    window.localStorage = fakeStorage();
    P.savePersistedAttempt('attempt-1', { revision: 1, answers: { 1: 0 }, position: 1, flagged: [], struck: {}, savedAt: 1 });
    P.savePersistedAttempt('attempt-2', { revision: 1, answers: { 1: 1 }, position: 1, flagged: [], struck: {}, savedAt: 1 });

    assert.equal(P.clearPersistedAttempt('attempt-1'), true);
    assert.equal(P.loadPersistedAttempt('attempt-1'), null);
    assert.notEqual(P.loadPersistedAttempt('attempt-2'), null, 'clearing one attempt must not touch another');
});

test('every persistence function degrades gracefully when storage access itself throws', () => {
    // A private/incognito window throws on the access itself, not only on a
    // missing key -- every function here must swallow it and hand back a
    // safe default rather than letting the exception reach the caller.
    window.localStorage = throwingStorage();

    assert.equal(P.savePersistedAttempt('a1', { revision: 1, answers: {}, position: 1, flagged: [], struck: {}, savedAt: 1 }), false);
    assert.equal(P.loadPersistedAttempt('a1'), null);
    assert.equal(P.clearPersistedAttempt('a1'), false);

    assert.equal(P.saveQueuedSubmission('a1', { revision: 1, answers: {}, queuedAt: 1 }), false);
    assert.equal(P.loadQueuedSubmission('a1'), null);
    assert.equal(P.clearQueuedSubmission('a1'), false);
});

test('savePersistedAttempt writes only the allow-listed fields, even if the caller widens what it passes', () => {
    // This is the property that protects the question bank: the save
    // function takes named parameters, not "the whole state object", so a
    // future caller cannot accidentally leak content just by adding a field
    // to `state` elsewhere and forwarding it through. Simulate exactly that
    // mistake here and prove nothing extra survives to the raw stored
    // string.
    const storage = fakeStorage();
    window.localStorage = storage;

    P.savePersistedAttempt('attempt-9', {
        revision: 3,
        answers: { 1: 0, 2: 2 },
        position: 2,
        flagged: [1, 4],
        struck: { 1: [2] },
        savedAt: 1000,
        // A future caller mistakenly forwarding whole-state-shaped fields
        // alongside the allow-listed ones:
        questions: new Map([[1, { prompt: 'متن یک سؤال دندانپزشکی', choices: ['الف', 'ب'] }]]),
        reveals: new Map([[1, { answer: 0, explanation: 'دلیل پاسخ درست این است که...' }]]),
        prompt: 'متن سؤال',
        choices: ['گزینه یک', 'گزینه دو'],
        explanation: 'توضیح پاسخ',
    });

    const raw = storage._dump().get('fanoos.examAttempt.v1.attempt-9');
    assert.ok(raw, 'a snapshot must have been written');
    const parsed = JSON.parse(raw);

    assert.deepEqual(
        Object.keys(parsed).sort(),
        ['answers', 'attemptId', 'flagged', 'position', 'revision', 'savedAt', 'struck'].sort(),
        'only the allow-listed fields may ever reach storage, regardless of what the caller also passed',
    );

    const lowered = raw.toLowerCase();
    for (const forbidden of ['question', 'prompt', 'choices', 'explanation', 'دندانپزشکی', 'گزینه', 'توضیح']) {
        assert.ok(!lowered.includes(forbidden.toLowerCase()), `raw storage payload must never contain "${forbidden}"`);
    }
});

test('queued submission save/load/clear round trip', () => {
    window.localStorage = fakeStorage();
    assert.equal(P.loadQueuedSubmission('attempt-1'), null);

    const saved = P.saveQueuedSubmission('attempt-1', { revision: 7, answers: { 1: 0, 2: 1 }, queuedAt: 99 });
    assert.equal(saved, true);

    const loaded = P.loadQueuedSubmission('attempt-1');
    assert.deepEqual(loaded, { attemptId: 'attempt-1', revision: 7, answers: { 1: 0, 2: 1 }, queuedAt: 99 });

    assert.equal(P.clearQueuedSubmission('attempt-1'), true);
    assert.equal(P.loadQueuedSubmission('attempt-1'), null);
});

test('queued submission storage is independent of the in-progress answer snapshot', () => {
    window.localStorage = fakeStorage();
    P.savePersistedAttempt('attempt-1', { revision: 1, answers: { 1: 0 }, position: 1, flagged: [], struck: {}, savedAt: 1 });
    P.saveQueuedSubmission('attempt-1', { revision: 1, answers: { 1: 0 }, queuedAt: 1 });

    P.clearQueuedSubmission('attempt-1');
    assert.notEqual(P.loadPersistedAttempt('attempt-1'), null, 'clearing the submit queue must not clear the answer snapshot');

    P.savePersistedAttempt('attempt-1', { revision: 1, answers: { 1: 0 }, position: 1, flagged: [], struck: {}, savedAt: 1 });
    P.saveQueuedSubmission('attempt-1', { revision: 1, answers: { 1: 0 }, queuedAt: 1 });
    P.clearPersistedAttempt('attempt-1');
    assert.notEqual(P.loadQueuedSubmission('attempt-1'), null, 'clearing the answer snapshot must not clear a queued submission');
});

test('reconcileAnswers: with no persisted snapshot, the server answers pass through unchanged', () => {
    const result = P.reconcileAnswers({ answers: { 1: 0, 2: 1 }, revision: 4, attemptId: 'a1' }, null);
    assert.deepEqual(result, { answers: { 1: 0, 2: 1 }, position: null, flagged: [], struck: {} });
});

test('reconcileAnswers: a persisted snapshot with a matching revision -- local wins per key', () => {
    const server = { answers: { 1: 0, 2: 1, 3: 2 }, revision: 4, attemptId: 'a1' };
    const persisted = {
        attemptId: 'a1', revision: 4, answers: { 2: 3, 4: 1 }, position: 2, flagged: [1], struck: { 2: [0] }, savedAt: 1,
    };
    const result = P.reconcileAnswers(server, persisted);
    assert.deepEqual(result.answers, { 1: 0, 2: 3, 3: 2, 4: 1 }, 'local overrides the server for every key it has; positions absent locally keep the server value');
    assert.equal(result.position, 2);
    assert.deepEqual(result.flagged, [1]);
    assert.deepEqual(result.struck, { 2: [0] });
});

test('reconcileAnswers: a persisted snapshot with a DIFFERENT revision than the server -- local still wins per key', () => {
    // This is the case a naive implementation gets wrong by trusting
    // revision equality as a gate. A mismatch does not prove the local copy
    // is stale garbage -- it is exactly as likely to mean the local save
    // landed under a revision the client never learned about (its response
    // was the thing lost to a dropped connection). Dropping local data here
    // would be the single most dangerous place to do it.
    const server = { answers: { 1: 0, 2: 1 }, revision: 9, attemptId: 'a1' };
    const persisted = {
        attemptId: 'a1', revision: 4, answers: { 2: 3, 5: 0 }, position: 5, flagged: [], struck: {}, savedAt: 1,
    };
    const result = P.reconcileAnswers(server, persisted);
    assert.deepEqual(result.answers, { 1: 0, 2: 3, 5: 0 }, 'local still overrides the server despite the revision mismatch');
    assert.equal(result.position, 5);
});

test('reconcileAnswers: a persisted snapshot for a different attempt id is ignored', () => {
    const server = { answers: { 1: 0 }, revision: 1, attemptId: 'a1' };
    const persisted = { attemptId: 'a2', revision: 1, answers: { 1: 9 }, position: 1, flagged: [], struck: {}, savedAt: 1 };
    const result = P.reconcileAnswers(server, persisted);
    assert.deepEqual(result, { answers: { 1: 0 }, position: null, flagged: [], struck: {} }, 'a snapshot for a different attempt must never be merged in');
});

test('reconcileAnswers: a malformed persisted snapshot (no usable answers) falls back to the server unchanged', () => {
    const server = { answers: { 1: 0 }, revision: 1, attemptId: 'a1' };
    assert.deepEqual(P.reconcileAnswers(server, { attemptId: 'a1', answers: null }), { answers: { 1: 0 }, position: null, flagged: [], struck: {} });
    assert.deepEqual(P.reconcileAnswers(server, {}), { answers: { 1: 0 }, position: null, flagged: [], struck: {} });
});
