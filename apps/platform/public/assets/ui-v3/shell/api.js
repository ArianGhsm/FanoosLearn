const TOKEN_KEY = 'fanoos_token';
const CSRF_KEY = 'fanoos_csrf';
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);

function storage(windowRef) {
  try { return windowRef?.sessionStorage || null; } catch (_error) { return null; }
}

function readStored(windowRef, key) {
  try { return storage(windowRef)?.getItem(key) || ''; } catch (_error) { return ''; }
}

function writeStored(windowRef, key, value) {
  try {
    const target = storage(windowRef);
    if (!target) return;
    if (value) target.setItem(key, value);
    else target.removeItem(key);
  } catch (_error) { /* session bridge is best-effort browser state */ }
}

function normalizeFailure(error, fallbackStatus = 0) {
  return Object.freeze({
    status: Number(error?.status ?? fallbackStatus) || 0,
    code: String(error?.code || 'request_failed').slice(0, 80),
  });
}

function apiError(status, code) {
  const error = new Error('fanoos_api_error');
  error.status = Number(status) || 0;
  error.code = String(code || 'request_failed').slice(0, 80);
  return error;
}

function unwrapDelegate(result) {
  if (result && typeof result === 'object' && Object.prototype.hasOwnProperty.call(result, 'ok')) {
    if (result.ok === false) throw apiError(result.status || 0, result.error?.code || result.code);
    if (Object.prototype.hasOwnProperty.call(result, 'data')) return result.data;
  }
  return result;
}

export function shellErrorMessage(error, action = 'request') {
  const failure = normalizeFailure(error);
  if (failure.status === 401 || failure.code === 'unauthenticated') return 'نشست شما پایان یافته است. دوباره وارد شوید.';
  if (failure.code === 'invalid_credentials') return 'شناسه یا رمز عبور درست نیست.';
  if (failure.status === 429 || failure.code === 'login_throttled') return 'تعداد تلاش‌ها زیاد بوده است. مدتی بعد دوباره تلاش کنید.';
  if (failure.status === 403 && failure.code === 'workspace_forbidden') return 'این فضای آموزشی برای حساب شما در دسترس نیست.';
  if (failure.status === 403 || failure.code === 'csrf_failed') return 'اجازه انجام این عملیات تأیید نشد. صفحه را تازه کنید و دوباره تلاش کنید.';
  if (failure.code === 'network_error' || failure.status === 0) return 'ارتباط با فانوس برقرار نشد. اتصال خود را بررسی کنید و دوباره تلاش کنید.';
  if (action === 'login') return 'ورود انجام نشد. اطلاعات ورود را بررسی کنید و دوباره تلاش کنید.';
  if (action === 'workspace') return 'تغییر فضای آموزشی انجام نشد. دوباره تلاش کنید.';
  if (action === 'logout') return 'پایان نشست تأیید نشد. دوباره تلاش کنید.';
  return 'انجام این درخواست ممکن نشد. دوباره تلاش کنید.';
}

export function createShellApi(ctx = {}) {
  const windowRef = ctx.windowRef || (typeof window !== 'undefined' ? window : null);
  const fetchImpl = ctx.fetch || windowRef?.fetch?.bind(windowRef) || globalThis.fetch?.bind(globalThis);
  const delegate = ctx.api || null;
  const hasRequestDelegate = typeof delegate === 'function' || typeof delegate?.request === 'function';
  let token = readStored(windowRef, TOKEN_KEY);
  let csrf = readStored(windowRef, CSRF_KEY);

  async function delegatedRequest(path, options) {
    if (typeof delegate === 'function') return unwrapDelegate(await delegate(path, options));
    return unwrapDelegate(await delegate.request(path, options));
  }

  async function browserRequest(path, options = {}) {
    if (!fetchImpl) throw apiError(0, 'network_error');
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (token) headers.Authorization = `Bearer ${token}`;
    if (!SAFE_METHODS.has(method) && csrf) headers['X-CSRF-Token'] = csrf;
    let body;
    if (options.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(options.body);
    }

    let response;
    try {
      response = await fetchImpl(path, { method, headers, body, credentials: 'same-origin', signal: options.signal });
    } catch (error) {
      if (error?.name === 'AbortError') throw error;
      throw apiError(0, 'network_error');
    }

    let payload = null;
    try { payload = await response.json(); } catch (_error) { /* normalized below */ }
    if (!response.ok || payload?.ok === false || !payload) {
      throw apiError(response.status, payload?.error?.code || 'request_failed');
    }
    return payload.data;
  }

  async function request(path, options = {}) {
    if (hasRequestDelegate) return delegatedRequest(path, options);
    return browserRequest(path, options);
  }

  function rememberSession(result) {
    token = String(result?.token || '');
    csrf = String(result?.csrf_token || '');
    writeStored(windowRef, TOKEN_KEY, token);
    writeStored(windowRef, CSRF_KEY, csrf);
  }

  function clearSessionBridge() {
    token = '';
    csrf = '';
    writeStored(windowRef, TOKEN_KEY, '');
    writeStored(windowRef, CSRF_KEY, '');
    if (typeof delegate?.clearSession === 'function') delegate.clearSession();
  }

  return Object.freeze({
    async login(identifier, password, options = {}) {
      if (typeof delegate?.login === 'function') {
        const result = unwrapDelegate(await delegate.login({ identifier, password, signal: options.signal }));
        if (result?.token || result?.csrf_token) rememberSession(result);
        return result;
      }
      const result = await request('/api/v1/auth/login', { method: 'POST', body: { identifier, password }, signal: options.signal });
      if (result?.token || result?.csrf_token) rememberSession(result);
      return result;
    },

    async logout(options = {}) {
      try {
        const result = typeof delegate?.logout === 'function'
          ? unwrapDelegate(await delegate.logout({ signal: options.signal }))
          : await request('/api/v1/auth/logout', { method: 'POST', body: {}, signal: options.signal });
        clearSessionBridge();
        return { result, confirmed: true };
      } catch (error) {
        if (Number(error?.status) === 401) {
          clearSessionBridge();
          return { result: null, confirmed: true, alreadyExpired: true };
        }
        throw error;
      }
    },

    account(options = {}) {
      if (typeof delegate?.account === 'function') return Promise.resolve(delegate.account(options)).then(unwrapDelegate);
      return request('/api/v1/account', { signal: options.signal });
    },

    workspaces(options = {}) {
      if (typeof delegate?.workspaces === 'function') return Promise.resolve(delegate.workspaces(options)).then(unwrapDelegate);
      return request('/api/v1/workspaces', { signal: options.signal });
    },

    selectWorkspace(workspaceId, options = {}) {
      if (typeof delegate?.selectWorkspace === 'function') return Promise.resolve(delegate.selectWorkspace(workspaceId, options)).then(unwrapDelegate);
      return request('/api/v1/workspaces/select', { method: 'POST', body: { workspace_id: String(workspaceId || '') }, signal: options.signal });
    },

    async management(workspaceId, options = {}) {
      if (!workspaceId) return { available: false, projection: null };
      try {
        const projection = typeof delegate?.management === 'function'
          ? unwrapDelegate(await delegate.management(workspaceId, options))
          : await request(`/api/v1/workspaces/${encodeURIComponent(workspaceId)}/admin/dashboard`, { signal: options.signal });
        return { available: projection?.management_available === true, projection: projection || null };
      } catch (error) {
        if (Number(error?.status) === 403) return { available: false, projection: null };
        throw error;
      }
    },

    request,
    clearSessionBridge,
    failure: normalizeFailure,
    get hasSessionBridge() { return Boolean(token); },
    get hasCsrfBridge() { return Boolean(csrf); },
  });
}
