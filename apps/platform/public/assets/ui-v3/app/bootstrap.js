import * as foundationUi from '../foundation/ui.js';
import { assertRuntimeContext } from '../foundation/runtime-contract.js';
import shellDefinition from '../shell/index.js';
import { icon as shellIcon } from '../shell/ui.js';
import coursesDefinition from '../courses/index.js';
import scheduleDefinition from '../schedule/index.js';
import learningDefinition, { createCourseLearningEmbed } from '../learning/index.js';
import progressDefinition, { renderCourseGradesSlot } from '../progress/module.js';
import { moduleDefinition as operationsDefinition, renderCourseAnnouncementsSlot } from '../operations/operations.js';
import homeDefinition from './home-module.js';

const DESIGN_LOCK_ID = 'FANOOS-UX-2026.09-R1';
const TOKEN_KEY = 'fanoos_token';
const CSRF_KEY = 'fanoos_csrf';
const SAFE_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);
const COURSE_SLOTS = new Set(['course.schedule', 'course.resources', 'course.assessments', 'course.grades', 'course.announcements']);
const DOMAIN_MODULES = Object.freeze({
  home: homeDefinition,
  courses: coursesDefinition,
  schedule: scheduleDefinition,
  resources: learningDefinition,
  assessments: progressDefinition,
  grades: progressDefinition,
  announcements: operationsDefinition,
  notifications: operationsDefinition,
  forms: operationsDefinition,
  orders: operationsDefinition,
  management: operationsDefinition,
});

function clean(value, fallback = '') {
  if (value === null || value === undefined) return fallback;
  const normalized = String(value).normalize('NFKC').replace(/[\u202A-\u202E\u2066-\u2069]/g, '').trim();
  return normalized || fallback;
}

function storage() {
  try { return window.sessionStorage; } catch (_error) { return null; }
}

function stored(key) {
  try { return storage()?.getItem(key) || ''; } catch (_error) { return ''; }
}

function writeStored(key, value) {
  try {
    const target = storage();
    if (!target) return;
    if (value) target.setItem(key, value);
    else target.removeItem(key);
  } catch (_error) { /* session bridge is best effort */ }
}

function apiError(status, code) {
  const error = new Error('fanoos_api_error');
  error.status = Number(status || 0);
  error.code = clean(code, 'request_failed').slice(0, 80);
  return error;
}

function createCanonicalApi() {
  let accountSnapshot = null;
  let unauthorizedHandler = null;

  async function parseJsonResponse(response) {
    let payload = null;
    try { payload = await response.json(); } catch (_error) { /* normalized below */ }
    if (!response.ok || payload?.ok === false) throw apiError(response.status, payload?.error?.code || 'request_failed');
    if (payload && Object.prototype.hasOwnProperty.call(payload, 'data')) return payload.data;
    return payload;
  }

  function headersFor(method, extra = {}) {
    const headers = { Accept: 'application/json', ...extra };
    const token = stored(TOKEN_KEY);
    const csrf = stored(CSRF_KEY);
    if (token) headers.Authorization = `Bearer ${token}`;
    if (!SAFE_METHODS.has(method) && csrf) headers['X-CSRF-Token'] = csrf;
    return headers;
  }

  function maybeUnauthorized(path, error) {
    if (Number(error?.status) !== 401 || !String(path).startsWith('/api/v1/workspaces/')) return;
    if (typeof unauthorizedHandler === 'function') unauthorizedHandler(error);
  }

  async function request(path, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = headersFor(method, options.headers || {});
    let body;
    if (options.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(options.body);
    }
    let response;
    try {
      response = await fetch(path, { method, headers, body, credentials: 'same-origin', signal: options.signal });
    } catch (error) {
      if (error?.name === 'AbortError') throw error;
      throw apiError(0, 'network_error');
    }
    try {
      return await parseJsonResponse(response);
    } catch (error) {
      maybeUnauthorized(path, error);
      throw error;
    }
  }

  async function binary(path, options = {}) {
    const method = String(options.method || 'POST').toUpperCase();
    const headers = headersFor(method, options.headers || {});
    let body;
    if (options.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(options.body);
    }
    let response;
    try {
      response = await fetch(path, { method, headers, body, credentials: 'same-origin', signal: options.signal });
    } catch (error) {
      if (error?.name === 'AbortError') throw error;
      throw apiError(0, 'network_error');
    }
    if (!response.ok) {
      let payload = null;
      try { payload = await response.json(); } catch (_error) { /* normalized below */ }
      const error = apiError(response.status, payload?.error?.code || 'request_failed');
      maybeUnauthorized(path, error);
      throw error;
    }
    return response.blob();
  }

  const api = async (path, options = {}) => request(path, options);
  Object.assign(api, {
    request,
    get: (path, options = {}) => request(path, { ...options, method: 'GET' }),
    post: (path, body = {}, options = {}) => request(path, { ...options, method: 'POST', body }),
    patch: (path, body = {}, options = {}) => request(path, { ...options, method: 'PATCH', body }),
    binary,
    requestBinary: binary,
    async login({ identifier, password, signal } = {}) {
      const result = await request('/api/v1/auth/login', { method: 'POST', body: { identifier, password }, signal });
      writeStored(TOKEN_KEY, clean(result?.token));
      writeStored(CSRF_KEY, clean(result?.csrf_token));
      return result;
    },
    logout({ signal } = {}) {
      return request('/api/v1/auth/logout', { method: 'POST', body: {}, signal });
    },
    async account(options = {}) {
      const account = await request('/api/v1/account', { method: 'GET', signal: options.signal });
      accountSnapshot = account && typeof account === 'object' ? account : null;
      return account;
    },
    workspaces(options = {}) {
      return request('/api/v1/workspaces', { method: 'GET', signal: options.signal });
    },
    selectWorkspace(workspaceId, options = {}) {
      return request('/api/v1/workspaces/select', { method: 'POST', body: { workspace_id: String(workspaceId || '') }, signal: options.signal });
    },
    management(workspaceId, options = {}) {
      return request(`/api/v1/workspaces/${encodeURIComponent(workspaceId)}/admin/dashboard`, { method: 'GET', signal: options.signal });
    },
    clearSession() {
      writeStored(TOKEN_KEY, '');
      writeStored(CSRF_KEY, '');
      accountSnapshot = null;
    },
    accountSnapshot: () => accountSnapshot,
    setUnauthorizedHandler(handler) { unauthorizedHandler = typeof handler === 'function' ? handler : null; },
  });
  return api;
}

let activeTimeZone = 'UTC';
function validTimeZone(value) {
  const candidate = clean(value);
  if (!candidate) return '';
  try { new Intl.DateTimeFormat('en', { timeZone: candidate }).format(0); return candidate; } catch (_error) { return ''; }
}

function backendDate(value) {
  if (!value) return null;
  const raw = clean(value);
  const canonical = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw) ? `${raw.replace(' ', 'T')}Z` : raw;
  const date = new Date(canonical);
  return Number.isFinite(date.getTime()) ? date : null;
}

function createFormatters() {
  const dateFormat = (value, options) => {
    const date = backendDate(value);
    if (!date) return '';
    const zone = validTimeZone(activeTimeZone);
    if (!zone) return '';
    try { return new Intl.DateTimeFormat('fa-IR-u-ca-persian', { ...options, timeZone: zone }).format(date); } catch (_error) { return ''; }
  };
  const statusMap = { scheduled: 'برنامه‌ریزی‌شده', completed: 'تکمیل‌شده', cancelled: 'لغوشده', active: 'فعال', published: 'منتشرشده', pending: 'در انتظار', paid: 'پرداخت تأیید شده', failed: 'ناموفق', expired: 'منقضی‌شده', revoked: 'لغوشده' };
  const resourceMap = { lecture_note: 'جزوه', discipline_note: 'یادداشت درسی', summary: 'خلاصه', cheat_sheet: 'مرور سریع', flashcards: 'فلش‌کارت', question_bank: 'بانک سؤال', past_exam: 'آزمون گذشته', audio: 'صوت', transcript: 'متن پیاده‌سازی‌شده', slide_reference: 'اسلاید / مرجع', other: 'منبع آموزشی' };
  const assessmentMap = { practice: 'تمرین', mock_exam: 'آزمون آزمایشی', past_exam: 'آزمون گذشته', quiz: 'آزمون کوتاه', exam: 'آزمون' };
  return Object.freeze({
    text: (value) => clean(value),
    number: (value) => Number.isFinite(Number(value)) ? new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(Number(value)) : clean(value),
    date: (value) => dateFormat(value, { year: 'numeric', month: 'long', day: 'numeric' }),
    dateTime: (value) => dateFormat(value, { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }),
    time: (value) => dateFormat(value, { hour: '2-digit', minute: '2-digit', hour12: false }),
    money: (amount, currency) => {
      const number = Number(amount);
      if (!Number.isFinite(number)) return '—';
      const rendered = new Intl.NumberFormat('fa-IR').format(number);
      return clean(currency).toUpperCase() === 'IRR' ? `${rendered} ریال` : `${rendered}${currency ? ` ${clean(currency).toUpperCase()}` : ''}`;
    },
    status: (value) => statusMap[clean(value).toLowerCase()] || clean(value),
    resourceType: (value) => resourceMap[clean(value).toLowerCase()] || 'منبع آموزشی',
    assessmentType: (value) => assessmentMap[clean(value).toLowerCase()] || 'آزمون',
  });
}

function mergeQuery(path, query = {}) {
  const [pathname, search = ''] = String(path).split('?');
  const params = new URLSearchParams(search);
  Object.entries(query || {}).forEach(([key, value]) => {
    if (value === null || value === undefined || String(value).trim() === '') params.delete(key);
    else params.set(key, String(value));
  });
  const encoded = params.toString();
  return `${pathname}${encoded ? `?${encoded}` : ''}`;
}

function canonicalTarget(target, query = {}, subview = '') {
  let raw = clean(target, 'home').replace(/^#/, '');
  if (raw === '/learning' || raw === 'learning') raw = '/resources';
  if (raw.startsWith('/')) return mergeQuery(raw, query);
  if (raw === 'schedule') return mergeQuery(`/schedule/${['today', 'week', 'upcoming'].includes(subview) ? subview : 'today'}`, query);
  if (raw === 'courses' && query?.course) {
    const next = { ...query };
    const code = next.course;
    delete next.course;
    return mergeQuery(`/courses/${encodeURIComponent(String(code))}`, next);
  }
  return mergeQuery(`/${raw}`, query);
}

function navigateCanonical(target, query = {}, subview = '') {
  const path = canonicalTarget(target, query, subview);
  const hash = `#${path}`;
  if (window.location.hash === hash) return;
  window.location.hash = hash;
}

function selectedWorkspace(api, workspaceId) {
  const account = api.accountSnapshot?.() || {};
  const workspaces = Array.isArray(account.workspaces) ? account.workspaces : [];
  return workspaces.find((workspace) => String(workspace?.id || '') === String(workspaceId || '')) || { id: workspaceId || '', name: 'فضای آموزشی', timezone_name: '' };
}

function normalizeRoute(route = {}) {
  const id = clean(route.id, 'home');
  const segments = Array.isArray(route.segments) ? route.segments.map((item) => clean(item)).filter(Boolean) : [];
  const normalized = { ...route, id, name: id, path: clean(route.path, `/${id}`), query: route.query && typeof route.query === 'object' ? { ...route.query } : {}, segments, params: route.params && typeof route.params === 'object' ? { ...route.params } : {} };
  if (id === 'courses' && segments[1]) normalized.params.courseCode = segments[1];
  if (id === 'schedule') normalized.subview = ['today', 'week', 'upcoming'].includes(segments[1]) ? segments[1] : 'today';
  return normalized;
}

function structuredText(content, depth = 0) {
  if (depth > 2) return [];
  if (typeof content === 'string') return [clean(content)].filter(Boolean);
  if (Array.isArray(content)) return content.flatMap((item) => structuredText(item, depth + 1)).slice(0, 40);
  if (!content || typeof content !== 'object') return [];
  const values = [];
  ['title', 'heading', 'description', 'summary', 'body', 'text'].forEach((key) => { if (typeof content[key] === 'string') values.push(clean(content[key])); });
  ['sections', 'items', 'blocks'].forEach((key) => { if (Array.isArray(content[key])) values.push(...structuredText(content[key], depth + 1)); });
  return [...new Set(values.filter(Boolean))].slice(0, 40);
}

function openStructuredContent(content) {
  const previousFocus = document.activeElement;
  const dialog = document.createElement('dialog');
  dialog.className = 'f3-integration-structured';
  dialog.setAttribute('aria-labelledby', 'f3-integration-structured-title');
  const panel = document.createElement('section');
  panel.className = 'f3-integration-structured__panel';
  const heading = document.createElement('h2');
  heading.id = 'f3-integration-structured-title';
  heading.textContent = 'محتوای آموزشی';
  panel.append(heading);
  const values = structuredText(content);
  if (values.length) values.forEach((value) => { const paragraph = document.createElement('p'); paragraph.textContent = value; panel.append(paragraph); });
  else { const message = document.createElement('p'); message.textContent = 'محتوا دریافت شد، اما قالب نمایشی آن در این نسخه پشتیبانی نمی‌شود.'; panel.append(message); }
  const close = foundationUi.button('بستن', { variant: 'secondary', onClick: () => dialog.close() });
  panel.append(close);
  dialog.append(panel);
  dialog.addEventListener('close', () => {
    dialog.remove();
    if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
  }, { once: true });
  document.body.append(dialog);
  if (typeof dialog.showModal === 'function') dialog.showModal();
  else { dialog.setAttribute('open', ''); close.focus(); }
}

function saveBlob(blob) {
  if (!(blob instanceof Blob)) throw new TypeError('download_blob_invalid');
  const objectUrl = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = objectUrl;
  link.download = 'فانوس-منبع';
  link.hidden = true;
  document.body.append(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(objectUrl), 30000);
}

function createCapabilities(api, signal) {
  const learning = Object.freeze({
    async deliverResource({ workspaceId, resourceId, channel = 'web' } = {}) {
      const workspace = clean(workspaceId);
      const resource = clean(resourceId);
      if (!workspace || !resource) throw new Error('delivery_subject_missing');
      const base = `/api/v1/workspaces/${encodeURIComponent(workspace)}`;
      const issued = await api(`${base}/resources/${encodeURIComponent(resource)}/deliveries`, { method: 'POST', body: { channel }, signal });
      const deliveryToken = clean(issued?.delivery_token);
      if (!deliveryToken) throw new Error('delivery_token_unavailable');
      const served = await api(`${base}/deliveries/consume`, { method: 'POST', body: { delivery_token: deliveryToken }, signal });
      if (served?.content !== null && served?.content !== undefined) { openStructuredContent(served.content); return; }
      const downloadToken = clean(served?.download_token);
      if (!downloadToken) throw new Error('download_unavailable');
      const blob = await api.binary(`${base}/downloads/consume`, { method: 'POST', body: { download_token: downloadToken }, signal });
      saveBlob(blob);
    },
    saveBlob,
    openStructuredContent,
  });
  return Object.freeze({
    has: () => false,
    courseSchedule: true,
    courseResources: true,
    courseAssessments: true,
    courseGrades: true,
    courseAnnouncements: true,
    routes: Object.freeze({ resources: '/resources' }),
    progress: Object.freeze({ resourcesRoute: '/resources' }),
    learning,
  });
}

function demoteEmbeddedHeadings(host, signal) {
  const demote = () => {
    host.querySelectorAll('h1').forEach((heading) => {
      const replacement = document.createElement('h2');
      [...heading.attributes].forEach((attribute) => replacement.setAttribute(attribute.name, attribute.value));
      while (heading.firstChild) replacement.append(heading.firstChild);
      heading.replaceWith(replacement);
    });
  };
  demote();
  const observer = new MutationObserver(demote);
  observer.observe(host, { childList: true, subtree: true });
  if (signal) {
    if (signal.aborted) observer.disconnect();
    else signal.addEventListener('abort', () => observer.disconnect(), { once: true });
  }
  return observer;
}

async function progressCourseKey(ctx, course) {
  const workspaceId = clean(ctx.state?.workspaceId);
  const courseId = clean(course?.id);
  if (!workspaceId || !courseId) return '';
  const payload = await ctx.api(`/api/v1/workspaces/${encodeURIComponent(workspaceId)}/academics`, { signal: ctx.signal });
  const raw = Array.isArray(payload?.courses) ? payload.courses : [];
  const seen = new Set();
  let position = 0;
  for (const row of raw) {
    const id = clean(row?.id || row?.course_id);
    const title = clean(row?.title || row?.course_title);
    if (!id || !title || seen.has(id)) continue;
    seen.add(id);
    position += 1;
    if (id === courseId) return `course-${position}`;
  }
  return '';
}

function childContext(parent, root, route) {
  return { ...parent, root, state: { ...parent.state, route: normalizeRoute(route) } };
}

async function mountCourseSlot(parentCtx, slotName, host, payload = {}) {
  if (!COURSE_SLOTS.has(slotName) || !(host instanceof Element)) return false;
  const course = payload.course || {};
  host.classList.add('f3-course-slot--integrated');
  demoteEmbeddedHeadings(host, parentCtx.signal);

  if (slotName === 'course.resources') {
    const child = childContext(parentCtx, host, { id: 'resources', path: '/resources', query: {} });
    const instance = createCourseLearningEmbed(child, { courseId: course.id, courseLabel: course.title });
    if (parentCtx.signal?.aborted) instance?.unmount?.();
    else parentCtx.signal?.addEventListener('abort', () => instance?.unmount?.(), { once: true });
    return true;
  }
  if (slotName === 'course.grades') {
    await renderCourseGradesSlot(parentCtx, { root: host, courseCode: course.code, courseTitle: course.title });
    return true;
  }
  if (slotName === 'course.announcements') {
    await renderCourseAnnouncementsSlot(parentCtx, { root: host, courseId: course.id, courseCode: course.code, courseTitle: course.title });
    return true;
  }
  if (slotName === 'course.schedule') {
    const child = childContext(parentCtx, host, { id: 'schedule', path: '/schedule/upcoming', segments: ['schedule', 'upcoming'], subview: 'upcoming', query: { course: course.code } });
    if (parentCtx.signal?.aborted) return true;
    parentCtx.signal?.addEventListener('abort', () => scheduleDefinition.unmount(child), { once: true });
    await scheduleDefinition.mount(child);
    return true;
  }
  if (slotName === 'course.assessments') {
    host.style.visibility = 'hidden';
    const child = childContext(parentCtx, host, { id: 'assessments', path: '/assessments', query: {} });
    parentCtx.signal?.addEventListener('abort', () => progressDefinition.unmount(child), { once: true });
    await progressDefinition.mount(child);
    if (parentCtx.signal?.aborted) return true;
    const key = await progressCourseKey(parentCtx, course);
    const select = host.querySelector('.f3-progress-course-filter select');
    const option = select && [...select.options].find((item) => item.value === key);
    if (!select || !option) {
      progressDefinition.unmount(child);
      host.style.visibility = '';
      host.replaceChildren(foundationUi.stateView({ kind: 'warning', title: 'فیلتر دقیق درس در دسترس نیست', description: 'برای جلوگیری از نمایش آزمون‌های درس دیگر، این نمای تعبیه‌شده نمایش داده نشد.', action: foundationUi.button('باز کردن همه آزمون‌ها', { variant: 'secondary', onClick: () => parentCtx.navigate('/assessments') }) }));
      return true;
    }
    select.value = key;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    host.style.visibility = '';
    return true;
  }
  return false;
}

function focusRoute(outlet) {
  requestAnimationFrame(() => {
    if (!outlet.isConnected) return;
    const target = outlet.querySelector('h1') || outlet;
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
  });
}

function createRouteManager({ appRoot, api, format }) {
  let active = null;
  function stopActive() {
    if (!active) return;
    const current = active;
    active = null;
    current.abort.abort();
    try { current.definition?.unmount?.(current.ctx); } catch (_error) { /* cleanup remains fail-safe */ }
  }
  function buildContext(outlet, detail, abort) {
    const route = normalizeRoute(detail.route);
    const workspace = selectedWorkspace(api, detail.workspaceId);
    activeTimeZone = validTimeZone(workspace.timezone_name || workspace.timezoneName) || 'UTC';
    let context;
    const capabilities = createCapabilities(api, abort.signal);
    const ui = {
      ...foundationUi,
      hasSlot: (slotName) => COURSE_SLOTS.has(slotName),
      mountSlot: (slotName, host, payload) => mountCourseSlot(context, slotName, host, payload),
      announce: (message) => {
        const live = appRoot.querySelector('#f3-shell-live');
        if (live) live.textContent = clean(message);
      },
      toast: (payload) => {
        const live = appRoot.querySelector('#f3-shell-live');
        if (live) live.textContent = clean(typeof payload === 'string' ? payload : payload?.message);
      },
      handoff: ({ url } = {}) => {
        const candidate = new URL(String(url || ''), window.location.origin);
        if (!['https:', 'http:'].includes(candidate.protocol) || (candidate.protocol === 'http:' && candidate.origin !== window.location.origin)) throw new Error('unsafe_handoff_url');
        window.location.assign(candidate.href);
      },
    };
    context = {
      root: outlet,
      api,
      state: { route, account: api.accountSnapshot?.() || null, workspace, activeWorkspace: workspace, workspaceId: detail.workspaceId || '', selectedWorkspaceId: detail.workspaceId || '', workspaceEpoch: detail.workspaceEpoch || 0 },
      navigate: navigateCanonical,
      format,
      ui,
      capabilities,
      signal: abort.signal,
    };
    assertRuntimeContext(context);
    return context;
  }
  async function mountRoute(detail = {}) {
    stopActive();
    const definition = DOMAIN_MODULES[detail.route?.id];
    if (!definition || typeof detail.claim !== 'function') return false;
    const outlet = detail.claim();
    if (!(outlet instanceof Element)) return false;
    const abort = new AbortController();
    const ctx = buildContext(outlet, detail, abort);
    active = { abort, ctx, definition };
    try {
      await definition.mount(ctx);
      if (abort.signal.aborted || active?.ctx !== ctx) return true;
      outlet.setAttribute('aria-busy', 'false');
      focusRoute(outlet);
      return true;
    } catch (error) {
      if (error?.name === 'AbortError' || abort.signal.aborted) return true;
      outlet.replaceChildren(foundationUi.stateView({ kind: 'error', title: 'این بخش نمایش داده نشد', description: 'یک خطای یکپارچه‌سازی رخ داد. صفحه را دوباره باز کنید.' }));
      outlet.setAttribute('aria-busy', 'false');
      return true;
    }
  }
  const workspaceListener = (event) => { if (event.detail?.phase === 'start') stopActive(); };
  const sessionListener = (event) => { if (['expired', 'signed-out'].includes(event.detail?.phase)) stopActive(); };
  appRoot.addEventListener('fanoos:v3:workspace-switch', workspaceListener);
  appRoot.addEventListener('fanoos:v3:session', sessionListener);
  return Object.freeze({ mountRoute, destroy() { stopActive(); appRoot.removeEventListener('fanoos:v3:workspace-switch', workspaceListener); appRoot.removeEventListener('fanoos:v3:session', sessionListener); } });
}

function installNotificationNavigation(appRoot) {
  const add = () => {
    const groups = appRoot.querySelectorAll('.f3-shell-more-group');
    if (groups.length < 1 || appRoot.querySelector('[data-f3-notifications-link]')) return;
    const button = document.createElement('button');
    button.className = 'f3-shell-more-link f3-integration-notification-link';
    button.type = 'button';
    button.dataset.f3NotificationsLink = 'true';
    button.append(shellIcon('announcement'));
    const label = document.createElement('span');
    label.textContent = 'اعلان‌های شخصی';
    button.append(label, shellIcon('chevron'));
    button.addEventListener('click', () => navigateCanonical('/notifications'));
    groups[0].append(button);
  };
  appRoot.addEventListener('fanoos:v3:shell-ready', add);
  return () => appRoot.removeEventListener('fanoos:v3:shell-ready', add);
}

const appRoot = document.getElementById('fanoos-v3-root');
if (!(appRoot instanceof Element)) throw new Error('fanoos_v3_root_missing');
appRoot.dataset.designLock = DESIGN_LOCK_ID;

const api = createCanonicalApi();
const format = createFormatters();
const routeManager = createRouteManager({ appRoot, api, format });
installNotificationNavigation(appRoot);

const shellController = shellDefinition.mount({
  root: appRoot,
  api,
  ui: { mountRoute: routeManager.mountRoute },
  routes: [{ id: 'notifications', path: '/notifications', label: 'اعلان‌های شخصی', longLabel: 'اعلان‌های شخصی', eyebrow: 'پیگیری', slot: 'secondary', icon: 'announcement', workspaceRequired: true }],
  signal: new AbortController().signal,
});

api.setUnauthorizedHandler(() => shellController?.handleSessionExpired?.());
window.addEventListener('pagehide', () => routeManager.destroy(), { once: true });
