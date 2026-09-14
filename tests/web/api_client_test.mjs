/*
 * Behavioural tests for the web API client.
 *
 * The layer this replaces was "tested" by grepping its own source text for
 * forbidden substrings, which proves nothing about what the code does. These
 * drive the real module with a stubbed fetch and assert what it actually
 * sends and what it actually throws.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

function installDom({ csrf = 'csrf-token-value' } = {}) {
    globalThis.document = {
        querySelector(selector) {
            if (selector !== 'meta[name="fanoos-csrf"]') return null;
            if (csrf === null) return null;
            return { getAttribute: () => csrf };
        },
    };
}

function stubFetch(handler) {
    const calls = [];
    globalThis.fetch = async (url, init) => {
        calls.push({ url, init });
        return handler(url, init);
    };
    return calls;
}

function jsonResponse(status, payload, headers = {}) {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: { get: (name) => headers[name.toLowerCase()] ?? null },
        json: async () => payload,
    };
}

const { api, ApiError, describeError } = await import(
    '../../apps/platform/public/assets/web/foundation/api.js'
);

test('unwraps the data envelope rather than handing back the whole body', async () => {
    installDom();
    stubFetch(() => jsonResponse(200, { ok: true, data: { id: 'abc' }, meta: {} }));

    const result = await api.get('/account');

    assert.deepEqual(result, { id: 'abc' });
});

test('sends the CSRF token on a mutating request and omits it on a read', async () => {
    installDom({ csrf: 'token-42' });
    const calls = stubFetch(() => jsonResponse(200, { ok: true, data: {}, meta: {} }));

    await api.post('/workspaces/select', { workspace_id: 'w1' });
    await api.get('/account');

    assert.equal(calls[0].init.headers['X-CSRF-Token'], 'token-42');
    assert.equal(calls[0].init.body, JSON.stringify({ workspace_id: 'w1' }));
    assert.equal('X-CSRF-Token' in calls[1].init.headers, false);
});

test('a page with no CSRF meta still sends the request, without the header', async () => {
    // A signed-out page carries no token. Throwing here would turn "you are
    // signed out" into a client-side crash on the sign-in form itself.
    installDom({ csrf: null });
    const calls = stubFetch(() => jsonResponse(200, { ok: true, data: {}, meta: {} }));

    await api.post('/auth/login', { identifier: 'a', password: 'b' });

    assert.equal('X-CSRF-Token' in calls[0].init.headers, false);
});

test('an error envelope becomes an ApiError carrying code and status', async () => {
    installDom();
    stubFetch(() => jsonResponse(403, {
        ok: false,
        error: { code: 'entitlement_required', message: 'An active entitlement is required.' },
        meta: {},
    }));

    const error = await api.get('/x').then(() => null, (caught) => caught);

    assert.ok(error instanceof ApiError);
    assert.equal(error.code, 'entitlement_required');
    assert.equal(error.status, 403);
});

test('a dropped connection is status 0, distinguishable from any server answer', async () => {
    // The exam runner depends on this: "the server refused" and "we never
    // reached the server" must never be confused, or a network blip looks
    // like a permission failure.
    installDom();
    globalThis.fetch = async () => {
        throw new TypeError('Failed to fetch');
    };

    const error = await api.get('/x').then(() => null, (caught) => caught);

    assert.ok(error instanceof ApiError);
    assert.equal(error.isOffline, true);
    assert.equal(error.isSessionExpired, false);
    assert.equal(error.status, 0);
});

test('a rate-limited response exposes Retry-After so the caller can wait the right amount', async () => {
    installDom();
    stubFetch(() => jsonResponse(
        429,
        { ok: false, error: { code: 'question_read_rate_limited', message: 'slow down' }, meta: {} },
        { 'retry-after': '20' },
    ));

    const error = await api.get('/x').then(() => null, (caught) => caught);

    assert.equal(error.isRateLimited, true);
    assert.equal(error.retryAfterSeconds, 20);
});

test('an abort is propagated untouched, not reported as a network failure', async () => {
    installDom();
    globalThis.fetch = async () => {
        const abort = new Error('aborted');
        abort.name = 'AbortError';
        throw abort;
    };

    const error = await api.get('/x').then(() => null, (caught) => caught);

    assert.equal(error.name, 'AbortError');
    assert.equal(error instanceof ApiError, false);
});

test('a malformed (non-JSON) error response still produces an ApiError with the status', async () => {
    installDom();
    stubFetch(() => ({
        ok: false,
        status: 502,
        headers: { get: () => null },
        json: async () => {
            throw new SyntaxError('not json');
        },
    }));

    const error = await api.get('/x').then(() => null, (caught) => caught);

    assert.ok(error instanceof ApiError);
    assert.equal(error.status, 502);
});

test('every error description is Persian and never leaks a raw code to the reader', async () => {
    const cases = [
        new ApiError('network_unavailable', '', 0, null),
        new ApiError('session_expired', '', 401, null),
        new ApiError('rate_limited', '', 429, 10),
        new ApiError('denied', '', 403, null),
        new ApiError('missing', '', 404, null),
        new ApiError('boom', '', 500, null),
    ];

    for (const error of cases) {
        const message = describeError(error);
        assert.match(message, /[؀-ۿ]/, `expected Persian text for status ${error.status}`);
        assert.equal(message.includes('_'), false, `raw error code leaked for status ${error.status}`);
    }
});
