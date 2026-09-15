/*
 * Behavioural tests for the account page's sign-out handling.
 *
 * account.js used to swallow every logout failure (a bare try/catch with no
 * branch), so a 403 from the server -- the actual symptom of the CSRF token
 * being empty on every rendered page -- looked to the owner like "logout
 * does nothing": the page navigated to the signed-out front door while the
 * session was, in fact, still live. These drive the real module against a
 * stubbed fetch and assert what it actually does: an already-gone session
 * (401, nothing left to revoke) still navigates away, but any other
 * refusal is shown and the page stays put.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

function makeElement() {
    const listeners = {};
    return {
        hidden: true,
        disabled: false,
        textContent: '',
        addEventListener(type, handler) {
            listeners[type] = handler;
        },
        setAttribute() {},
        focus() {},
        async dispatch(type, event = {}) {
            await listeners[type]?.(event);
        },
    };
}

function jsonResponse(status, payload) {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: { get: () => null },
        json: async () => payload,
    };
}

const elements = {
    signout: makeElement(),
    'signout-error': makeElement(),
    'signout-error-text': makeElement(),
};

globalThis.document = {
    getElementById: (id) => elements[id] ?? null,
    querySelector: () => null,
};

const navigations = [];
globalThis.window = { location: { assign: (url) => navigations.push(url) } };

let fetchImpl = async () => {
    throw new Error('fetch not stubbed for this test');
};
globalThis.fetch = (...args) => fetchImpl(...args);

// account.js wires its listeners once, at import time, against whatever
// document.getElementById returns then -- exactly like a real page load.
await import('../../apps/platform/public/assets/web/pages/account.js');

function reset() {
    navigations.length = 0;
    elements.signout.hidden = false;
    elements.signout.disabled = false;
    elements['signout-error'].hidden = true;
    elements['signout-error-text'].textContent = '';
}

test('a refused logout is shown, not hidden, and does not navigate away', async () => {
    reset();
    fetchImpl = async () => jsonResponse(403, {
        ok: false,
        error: { code: 'csrf_failed', message: 'The CSRF token is invalid.' },
        meta: {},
    });

    await elements.signout.dispatch('click');

    assert.deepEqual(navigations, [], 'A refusal the user could act on must not silently navigate away.');
    assert.equal(elements['signout-error'].hidden, false, 'The refusal must be surfaced.');
    assert.match(elements['signout-error-text'].textContent, /[؀-ۿ]/, 'The surfaced message must be Persian.');
    assert.equal(elements.signout.disabled, false, 'The button must be re-enabled so the person can try again.');
});

test('an already-gone session (401) still navigates to the signed-out front door', async () => {
    reset();
    fetchImpl = async () => jsonResponse(401, {
        ok: false,
        error: { code: 'unauthenticated', message: 'The session is invalid or expired.' },
        meta: {},
    });

    await elements.signout.dispatch('click');

    assert.deepEqual(navigations, ['/'], 'A session already gone server-side has nothing left to revoke.');
    assert.equal(elements['signout-error'].hidden, true, 'No error should be shown for an already-gone session.');
});

test('a successful logout navigates to the signed-out front door', async () => {
    reset();
    fetchImpl = async () => jsonResponse(200, { ok: true, data: { logged_out: true }, meta: {} });

    await elements.signout.dispatch('click');

    assert.deepEqual(navigations, ['/']);
    assert.equal(elements['signout-error'].hidden, true);
});

test('a network failure while signing out is shown, not hidden', async () => {
    reset();
    fetchImpl = async () => {
        throw new TypeError('Failed to fetch');
    };

    await elements.signout.dispatch('click');

    assert.deepEqual(navigations, [], 'A dropped connection must not be treated as a successful sign-out.');
    assert.equal(elements['signout-error'].hidden, false);
});
