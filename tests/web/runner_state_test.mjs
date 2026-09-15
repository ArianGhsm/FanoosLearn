/*
 * Behavioural tests for the exam runner's state.
 *
 * This is where every rule about an attempt lives, so this is where the
 * rules are tested: what counts as answered, what striking a choice does to
 * a selection, where auto-advance goes, and what the question map shows.
 * Getting any of these subtly wrong produces a page that looks fine and
 * quietly loses a student's answer.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

const S = await import('../../apps/platform/public/assets/web/pages/runner-state.js');

function fresh(questionCount = 5, answers = {}) {
    return S.createAttemptState({
        attemptId: 'a1', assessmentId: 'x1', title: 'آزمون',
        questionCount, revision: 1, answers,
    });
}

test('answers are keyed by position and counted', () => {
    const state = fresh();
    S.setAnswer(state, 2, 1);
    S.setAnswer(state, 4, 0);

    assert.equal(S.isAnswered(state, 2), true);
    assert.equal(S.isAnswered(state, 3), false);
    assert.equal(S.answeredCount(state), 2);
    assert.equal(S.progressPercent(state), 40);
});

test('striking the selected choice clears the selection', () => {
    // Leaving an answer on a choice the student just crossed off would record
    // an answer they have visibly rejected.
    const state = fresh();
    S.setAnswer(state, 1, 2);
    S.toggleStrike(state, 1, 2);

    assert.equal(S.isStruck(state, 1, 2), true);
    assert.equal(S.isAnswered(state, 1), false);
});

test('choosing a struck-out choice un-strikes it', () => {
    // Otherwise the selected answer renders with a line through it.
    const state = fresh();
    S.toggleStrike(state, 1, 3);
    S.setAnswer(state, 1, 3);

    assert.equal(S.isStruck(state, 1, 3), false);
    assert.equal(state.answers['1'], 3);
});

test('auto-advance moves on, but lands on the submit step after the last gap', () => {
    const state = fresh(3);
    S.setAnswer(state, 1, 0);
    assert.deepEqual(S.afterAnswer(state, 1), { kind: 'question', position: 2 });

    // Answering the last question while question 2 is still blank should send
    // the student back to the gap, not to a review they are not ready for.
    S.setAnswer(state, 3, 0);
    assert.deepEqual(S.afterAnswer(state, 3), { kind: 'question', position: 2 });

    S.setAnswer(state, 2, 0);
    assert.deepEqual(S.afterAnswer(state, 3), { kind: 'review' });
});

test('next unanswered wraps around and reports null when the paper is full', () => {
    const state = fresh(4);
    S.setAnswer(state, 3, 0);
    S.setAnswer(state, 4, 0);

    assert.equal(S.nextUnanswered(state, 3), 1);

    S.setAnswer(state, 1, 0);
    S.setAnswer(state, 2, 0);
    assert.equal(S.nextUnanswered(state, 1), null);
});

test('the question map filters to answered, unanswered and flagged', () => {
    const state = fresh(4);
    S.setAnswer(state, 1, 0);
    S.setAnswer(state, 3, 1);
    S.toggleFlag(state, 3);

    assert.deepEqual(S.visiblePositions(state, 'all'), [1, 2, 3, 4]);
    assert.deepEqual(S.visiblePositions(state, 'answered'), [1, 3]);
    assert.deepEqual(S.visiblePositions(state, 'unanswered'), [2, 4]);
    assert.deepEqual(S.visiblePositions(state, 'flagged'), [3]);
});

test('flagging toggles rather than only setting', () => {
    const state = fresh();
    S.toggleFlag(state, 2);
    assert.equal(S.isFlagged(state, 2), true);
    S.toggleFlag(state, 2);
    assert.equal(S.isFlagged(state, 2), false);
});

test('pending answers track what the server has not seen, including a clear', () => {
    const state = fresh(3);
    S.setAnswer(state, 1, 0);
    assert.equal(S.hasUnsavedAnswers(state), true);

    S.markSaved(state, 2);
    assert.equal(S.hasUnsavedAnswers(state), false);
    assert.equal(state.revision, 2);

    // Clearing an answer is a change the server must be told about; treating
    // only additions as pending would leave a deleted answer standing.
    S.clearAnswer(state, 1);
    assert.equal(S.hasUnsavedAnswers(state), true);
    assert.deepEqual(S.pendingAnswers(state), { 1: null });
});

test('positions are clamped to the paper', () => {
    const state = fresh(5);
    assert.equal(S.clampPosition(state, 0), 1);
    assert.equal(S.clampPosition(state, 99), 5);
    assert.equal(S.clampPosition(state, Number.NaN), state.position);
});

test('unanswered positions come back in order for the submit dialog', () => {
    const state = fresh(5);
    S.setAnswer(state, 2, 0);
    S.setAnswer(state, 5, 0);

    assert.deepEqual(S.unansweredPositions(state), [1, 3, 4]);
});

test('an explanation stays hidden until the student asks for it', () => {
    // Practice mode tells right/wrong at once but keeps the explanation
    // behind a tap; this is the flag the view reads to decide which.
    const state = fresh();
    assert.equal(S.isExplanationShown(state, 1), false);
    S.showExplanation(state, 1);
    assert.equal(S.isExplanationShown(state, 1), true);
    assert.equal(S.isExplanationShown(state, 2), false);
});

test('resuming with answers already saved starts with nothing pending', () => {
    // A resumed attempt arrives with the server's answers. Treating them as
    // unsaved would fire a pointless write on every resume.
    const state = fresh(3, { 1: 0, 2: 1 });

    assert.equal(S.answeredCount(state), 2);
    assert.equal(S.hasUnsavedAnswers(state), false);
});
