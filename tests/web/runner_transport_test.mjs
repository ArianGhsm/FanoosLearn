/*
 * TurboRevealer is what keeps فوق‌سریع from being an automated answer-key
 * download: it is the single thing standing between "reveal on arrival" and
 * a burst of requests that would exhaust ExamQuestionRateGuard's bucket (or,
 * worse, not exhaust it and hand over the paper). This asserts the request
 * count directly rather than trusting the code to behave -- see
 * docs/product/01_FRONT_DOOR.md's exam runner work.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

const T = await import('../../apps/platform/public/assets/web/pages/runner-transport.js');

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => { resolve = res; reject = rej; });
    return { promise, resolve, reject };
}

async function flush() {
    // Two microtask turns is enough to let a resolved promise's `await`
    // continuation run and, if it immediately starts another request,
    // for that call to have happened synchronously within the continuation.
    await Promise.resolve();
    await Promise.resolve();
}

test('never more than one reveal request is in flight, and a superseded position is never requested', async () => {
    const calls = [];
    const pending = [];
    const fakeTransport = {
        reveal(attemptId, position) {
            calls.push(position);
            const entry = deferred();
            pending.push(entry);
            return entry.promise;
        },
    };
    const revealer = new T.TurboRevealer(fakeTransport);
    const settled = [];
    const onSettled = (error, position, payload) => settled.push({ error, position, payload });

    revealer.arrive('a1', 1, onSettled);
    assert.deepEqual(calls, [1], 'arriving at the first question did not request its reveal');

    // The student moves on to 2 and then 3 before question 1's reveal has
    // resolved. Only one request may be outstanding at a time, so neither
    // of these may fire yet.
    revealer.arrive('a1', 2, onSettled);
    revealer.arrive('a1', 3, onSettled);
    assert.equal(calls.length, 1, 'a second reveal request was sent while one was still in flight');

    pending[0].resolve({ answer: 0, explanation: null });
    await flush();
    assert.equal(settled.length, 1, 'the first reveal did not settle');
    assert.equal(settled[0].position, 1);

    // Position 2 was left behind before it was ever requested -- turbo shows
    // the question the student is *on*, not a queue of the ones they passed
    // through, so it must never be asked for at all.
    assert.deepEqual(calls, [1, 3], 'a skipped-past position was requested instead of being superseded');

    pending[1].resolve({ answer: 1, explanation: 'because' });
    await flush();
    assert.equal(settled.length, 2);
    assert.equal(settled[1].position, 3);
    assert.equal(calls.length, 2, 'no further request was made after the queue drained');
});

test('a reveal failure settles with the error rather than throwing or hanging', async () => {
    const pending = [];
    const fakeTransport = {
        reveal() {
            const entry = deferred();
            pending.push(entry);
            return entry.promise;
        },
    };
    const revealer = new T.TurboRevealer(fakeTransport);
    const settled = [];
    revealer.arrive('a1', 1, (error, position, payload) => settled.push({ error, position, payload }));

    const rateLimited = new Error('rate limited');
    pending[0].reject(rateLimited);
    await flush();

    assert.equal(settled.length, 1);
    assert.equal(settled[0].error, rateLimited, '429/other errors must surface to the caller, never be swallowed');
    assert.equal(settled[0].payload, null);
});
