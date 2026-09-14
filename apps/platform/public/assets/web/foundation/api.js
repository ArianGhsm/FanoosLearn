/*
 * The one way a page talks to the platform.
 *
 * Every page imports this rather than calling fetch directly, so CSRF, the
 * error envelope, session expiry and offline detection are handled once and
 * identically everywhere. A page that calls fetch itself will get one of
 * those wrong.
 *
 * The session is an HttpOnly cookie the browser sends on its own; the CSRF
 * token is embedded in the page by the server (see PageRenderer) and read
 * from the document, never stored by this module.
 */

const BASE = '/api/v1';

export class ApiError extends Error {
    constructor(code, message, status, retryAfterSeconds) {
        super(message || code || 'request_failed');
        this.name = 'ApiError';
        this.code = code || '';
        this.status = Number(status) || 0;
        this.retryAfterSeconds = retryAfterSeconds ?? null;
    }

    /** The caller's session is gone; the only cure is signing in again. */
    get isSessionExpired() {
        return this.status === 401;
    }

    /** The caller is going too fast. `retryAfterSeconds` says how long to wait. */
    get isRateLimited() {
        return this.status === 429;
    }

    /** The request never reached the server -- almost always a dropped connection. */
    get isOffline() {
        return this.status === 0;
    }
}

function csrfToken() {
    const meta = document.querySelector('meta[name="fanoos-csrf"]');
    return meta ? meta.getAttribute('content') || '' : '';
}

/**
 * Persian-facing text for an error. Pages should prefer a message specific to
 * what the user was doing; this is the honest fallback, and it never invents
 * a cause it does not know.
 */
export function describeError(error) {
    if (!(error instanceof ApiError)) return 'خطای غیرمنتظره‌ای رخ داد.';
    if (error.isOffline) return 'اتصال اینترنت قطع است. وقتی وصل شدی دوباره تلاش کن.';
    if (error.isSessionExpired) return 'نشست شما منقضی شده است. دوباره وارد شوید.';
    if (error.isRateLimited) return 'کمی سریع‌تر از حد مجاز پیش رفتی. چند لحظه صبر کن.';
    if (error.status === 403) return 'اجازه این کار را ندارید.';
    if (error.status === 404) return 'چیزی که دنبالش بودی پیدا نشد.';
    if (error.status >= 500) return 'سرور در پاسخ‌دهی مشکل دارد. کمی بعد دوباره تلاش کن.';
    return error.message || 'درخواست انجام نشد.';
}

async function request(path, { method = 'GET', body = null, signal = null } = {}) {
    const headers = { Accept: 'application/json' };
    const init = { method, headers, credentials: 'same-origin', signal };

    if (body !== null) {
        headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
    }
    if (method !== 'GET' && method !== 'HEAD') {
        const token = csrfToken();
        if (token) headers['X-CSRF-Token'] = token;
    }

    let response;
    try {
        response = await fetch(BASE + path, init);
    } catch (cause) {
        if (cause && cause.name === 'AbortError') throw cause;
        // A network-level failure carries no status. Surfacing it as status 0
        // rather than inventing one keeps "the server said no" and "we never
        // reached the server" distinguishable, which the offline handling
        // in the exam runner depends on.
        throw new ApiError('network_unavailable', 'network unavailable', 0, null);
    }

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        const error = payload && typeof payload.error === 'object' ? payload.error : {};
        const retryAfter = response.headers.get('Retry-After');
        throw new ApiError(
            error.code || '',
            error.message || '',
            response.status,
            retryAfter === null ? null : Number(retryAfter) || null,
        );
    }

    return payload && Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : payload;
}

export const api = {
    get: (path, options) => request(path, { ...options, method: 'GET' }),
    post: (path, body, options) => request(path, { ...options, method: 'POST', body: body ?? {} }),
    patch: (path, body, options) => request(path, { ...options, method: 'PATCH', body: body ?? {} }),
};

/**
 * Calls `onChange(online)` whenever connectivity changes, and once
 * immediately. `navigator.onLine` is only a hint -- it reports "online" for a
 * captive portal or a filtered route -- so pages treat a failed request as
 * the authoritative signal and use this only to drive the banner.
 */
export function watchConnection(onChange) {
    const emit = () => onChange(navigator.onLine !== false);
    window.addEventListener('online', emit);
    window.addEventListener('offline', emit);
    emit();
    return () => {
        window.removeEventListener('online', emit);
        window.removeEventListener('offline', emit);
    };
}
