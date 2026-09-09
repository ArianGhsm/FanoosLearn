import {
  renderAssessmentAttempt,
  renderAssessmentDetail,
  renderAssessmentLanding,
  renderAssessmentResult,
  renderFailure,
  renderGrades,
  renderLoading,
  renderPartialNotice,
} from './progress-ui.js';

const controllers = new WeakMap();

function clean(value) {
  return value === null || value === undefined ? '' : String(value).trim();
}

function workspaceId(ctx) {
  return clean(ctx?.state?.workspaceId || ctx?.state?.workspace?.id || ctx?.state?.selectedWorkspaceId || ctx?.state?.account?.selected_workspace_id);
}

function currentRoute(ctx) {
  const route = ctx?.state?.route || {};
  const candidate = clean(route.id || route.name || route.path || ctx?.state?.routeName);
  if (/grade/i.test(candidate)) return 'grades';
  if (/assessment|exam/i.test(candidate)) return 'assessments';
  if (typeof window !== 'undefined' && /grades/.test(window.location.hash || window.location.pathname)) return 'grades';
  return 'assessments';
}

function currentQuery(ctx) {
  const routeQuery = ctx?.state?.route?.query;
  if (routeQuery && typeof routeQuery === 'object') return { ...routeQuery };
  if (typeof window === 'undefined') return {};
  const source = (window.location.hash || '').split('?')[1] || window.location.search.replace(/^\?/, '');
  return Object.fromEntries(new URLSearchParams(source));
}

function queryString(values) {
  const search = new URLSearchParams();
  Object.entries(values || {}).forEach(([key, value]) => {
    const normalized = clean(value);
    if (normalized) search.set(key, normalized);
  });
  const encoded = search.toString();
  return encoded ? `?${encoded}` : '';
}

function apiPath(workspace, suffix) {
  return `/api/v1/workspaces/${encodeURIComponent(workspace)}${suffix}`;
}

function unwrap(result) {
  if (result && result.ok === false) {
    const error = new Error('Canonical request failed.');
    error.code = clean(result.error?.code);
    error.status = Number(result.status || result.error?.status || 0);
    throw error;
  }
  if (result && result.ok === true && Object.prototype.hasOwnProperty.call(result, 'data')) return result.data;
  if (result && Object.prototype.hasOwnProperty.call(result, 'data') && result.meta?.api_version) return result.data;
  return result;
}

async function apiRequest(ctx, path, { method = 'GET', body, signal } = {}) {
  const api = ctx?.api;
  if (!api) throw Object.assign(new Error('API client unavailable.'), { code: 'api_client_unavailable' });
  const upper = method.toUpperCase();
  let result;
  if (typeof api === 'function') {
    result = await api(path, { method: upper, ...(body === undefined ? {} : { body }), signal });
  } else if (typeof api.request === 'function') {
    result = await api.request(path, { method: upper, ...(body === undefined ? {} : { body }), signal });
  } else {
    const verb = upper.toLowerCase();
    if (typeof api[verb] !== 'function') throw Object.assign(new Error('API method unavailable.'), { code: 'api_method_unavailable' });
    if (upper === 'GET') result = await api[verb](path, { signal });
    else result = await api[verb](path, body ?? {}, { signal });
  }
  return unwrap(result);
}

function errorShape(error) {
  const status = Number(error?.status || error?.response?.status || 0);
  const code = clean(error?.code || error?.error?.code || error?.response?.data?.error?.code);
  if (status === 401 || code === 'unauthenticated' || code === 'session_expired') return { kind: 'session', status, code };
  if (status === 403 || /forbidden|denied|permission/.test(code)) return { kind: 'permission', status, code };
  return { kind: 'error', status, code };
}

function rows(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.items)) return value.items;
  if (Array.isArray(value?.results)) return value.results;
  return [];
}

function academicCourses(payload) {
  const raw = Array.isArray(payload?.courses) ? payload.courses : [];
  const found = new Map();
  raw.forEach((row) => {
    const id = clean(row?.id || row?.course_id);
    const code = clean(row?.course_code);
    const title = clean(row?.title || row?.course_title);
    if (!id || !title || found.has(id)) return;
    found.set(id, { id, code, title, termName: clean(row?.term_name), termId: clean(row?.term_id) });
  });
  return [...found.values()].map((course, index) => ({ ...course, presentationKey: `course-${index + 1}` }));
}

function explicitPresentationState(row) {
  const attemptState = clean(row?.attempt_status || row?.attempt_state || row?.user_attempt_status).toLowerCase();
  if (['scored', 'completed', 'submitted'].includes(attemptState)) return 'completed';
  if (attemptState === 'in_progress') return 'in_progress';
  const opensAt = row?.opens_at || row?.starts_at || null;
  const closesAt = row?.deadline_at || row?.closes_at || row?.ends_at || null;
  if (!opensAt && !closesAt) return '';
  const now = Date.now();
  const opens = opensAt ? new Date(opensAt).getTime() : Number.NEGATIVE_INFINITY;
  const closes = closesAt ? new Date(closesAt).getTime() : Number.POSITIVE_INFINITY;
  if (Number.isFinite(opens) && now < opens) return 'upcoming';
  if (Number.isFinite(closes) && now > closes) return 'completed';
  return 'active';
}

function decorateAssessments(raw, courses, filters) {
  const selectedCourse = courses.find((course) => course.presentationKey === filters.courseKey);
  return rows(raw)
    .filter((row) => !filters.kind || clean(row?.assessment_kind || row?.type) === filters.kind)
    .filter((row) => !selectedCourse || clean(row?.course_id) === selectedCourse.id)
    .map((row) => ({ row: { ...row, presentation_state: explicitPresentationState(row) || clean(row?.presentation_state) }, course: courses.find((course) => course.id === clean(row?.course_id)) || null }));
}

function filterQuestionBanks(raw, courses, filters) {
  if (raw === null) return null;
  const selectedCourse = courses.find((course) => course.presentationKey === filters.courseKey);
  return rows(raw).filter((row) => !selectedCourse || clean(row?.course_id) === selectedCourse.id);
}

function routeTo(ctx, path, query = {}) {
  if (typeof ctx?.navigate !== 'function') return false;
  const target = `${path}${queryString(query)}`;
  try {
    ctx.navigate(target);
    return true;
  } catch (_error) {
    return false;
  }
}

class ProgressController {
  constructor(ctx) {
    this.ctx = ctx;
    this.root = ctx.root;
    this.route = currentRoute(ctx);
    const query = currentQuery(ctx);
    this.filters = { kind: clean(query.kind), courseKey: '' };
    this.catalog = null;
    this.questionBanks = null;
    this.questionBankFailed = false;
    this.courses = [];
    this.selectedAssessment = null;
    this.attempt = null;
    this.answers = {};
    this.activeQuestion = 0;
    this.result = null;
    this.mutation = { pending: false, saveState: 'idle' };
    this.abort = new AbortController();
    this.destroyed = false;
    if (ctx.signal) {
      if (ctx.signal.aborted) this.abort.abort();
      else ctx.signal.addEventListener('abort', () => this.abort.abort(), { once: true });
    }
  }

  async mount() {
    this.root.classList.add('f3-progress-root');
    this.root.setAttribute('dir', 'rtl');
    this.root.setAttribute('lang', 'fa');
    if (this.route === 'grades') await this.loadGrades();
    else await this.loadAssessmentLanding();
  }

  destroy() {
    this.destroyed = true;
    this.abort.abort();
    this.root.classList.remove('f3-progress-root');
    this.root.removeAttribute('aria-busy');
  }

  async loadAssessmentLanding() {
    const workspace = workspaceId(this.ctx);
    if (!workspace) {
      renderFailure(this.root, 'permission', { area: 'assessments' });
      return;
    }
    renderLoading(this.root, 'assessments');
    const [catalogResult, academicsResult, bankResult] = await Promise.allSettled([
      apiRequest(this.ctx, apiPath(workspace, '/assessments'), { signal: this.abort.signal }),
      apiRequest(this.ctx, apiPath(workspace, '/academics'), { signal: this.abort.signal }),
      apiRequest(this.ctx, apiPath(workspace, `/resources${queryString({ type: 'question_bank', sort: 'newest' })}`), { signal: this.abort.signal }),
    ]);
    if (this.destroyed) return;
    if (catalogResult.status === 'rejected') {
      const failure = errorShape(catalogResult.reason);
      renderFailure(this.root, failure.kind, { area: 'assessments', retry: failure.kind === 'error' ? () => this.loadAssessmentLanding() : null });
      return;
    }
    this.catalog = catalogResult.value;
    this.courses = academicsResult.status === 'fulfilled' ? academicCourses(academicsResult.value) : [];
    this.questionBanks = bankResult.status === 'fulfilled' ? bankResult.value : null;
    this.questionBankFailed = bankResult.status === 'rejected';
    this.renderAssessmentLanding();
    if (academicsResult.status === 'rejected') {
      renderPartialNotice(this.root, 'فیلتر درس در دسترس نیست', 'آزمون‌ها از منبع canonical نمایش داده شده‌اند، اما اطلاعات درس‌ها کامل دریافت نشد.', () => this.loadAssessmentLanding());
    }
  }

  renderAssessmentLanding() {
    const assessmentItems = decorateAssessments(this.catalog, this.courses, this.filters);
    const questionBanks = filterQuestionBanks(this.questionBanks, this.courses, this.filters);
    renderAssessmentLanding(this.root, {
      ctx: this.ctx,
      assessments: assessmentItems,
      questionBanks,
      courses: this.courses,
      filters: this.filters,
    }, {
      setKind: (kind) => { this.filters.kind = clean(kind); this.renderAssessmentLanding(); },
      setCourse: (key) => { this.filters.courseKey = clean(key); this.renderAssessmentLanding(); },
      openAssessment: (row) => this.openAssessment(row),
      retryQuestionBanks: this.questionBankFailed ? () => this.loadAssessmentLanding() : null,
      openQuestionBankLibrary: () => this.openQuestionBankLibrary(),
      openQuestionBankResource: (row, course) => this.openQuestionBankResource(row, course),
    });
  }

  openQuestionBankLibrary() {
    const resourcePath = clean(this.ctx?.capabilities?.routes?.resources || this.ctx?.capabilities?.progress?.resourcesRoute) || '/resources';
    const selected = this.courses.find((course) => course.presentationKey === this.filters.courseKey);
    routeTo(this.ctx, resourcePath, { type: 'question_bank', ...(selected?.code ? { course: selected.code } : {}) });
  }

  openQuestionBankResource(_row, course) {
    const resourcePath = clean(this.ctx?.capabilities?.routes?.resources || this.ctx?.capabilities?.progress?.resourcesRoute) || '/resources';
    routeTo(this.ctx, resourcePath, { type: 'question_bank', ...(course?.code ? { course: course.code } : {}) });
  }

  openAssessment(row) {
    this.selectedAssessment = row;
    const course = this.courses.find((item) => item.id === clean(row?.course_id)) || null;
    renderAssessmentDetail(this.root, { ctx: this.ctx, row, course }, {
      back: () => this.backToLanding(),
      start: () => this.startAssessment(),
      startDisabled: this.mutation.pending,
    });
  }

  backToLanding() {
    this.selectedAssessment = null;
    this.attempt = null;
    this.answers = {};
    this.activeQuestion = 0;
    this.result = null;
    this.mutation = { pending: false, saveState: 'idle' };
    this.renderAssessmentLanding();
  }

  async startAssessment() {
    if (!this.selectedAssessment?.id || this.mutation.pending) return;
    const workspace = workspaceId(this.ctx);
    this.mutation.pending = true;
    this.openAssessment(this.selectedAssessment);
    try {
      const attempt = await apiRequest(this.ctx, apiPath(workspace, `/assessments/${encodeURIComponent(this.selectedAssessment.id)}/attempts`), { method: 'POST', body: {}, signal: this.abort.signal });
      if (this.destroyed) return;
      this.attempt = attempt;
      this.answers = {};
      this.activeQuestion = 0;
      this.result = null;
      this.mutation = { pending: false, saveState: 'idle' };
      this.renderAttempt();
    } catch (error) {
      if (this.destroyed) return;
      this.mutation.pending = false;
      this.openAssessment(this.selectedAssessment);
      const code = clean(error?.code);
      if (code === 'entitlement_required') renderPartialNotice(this.root, 'دسترسی فعال لازم است', 'شروع این آزمون طبق تصمیم canonical به دسترسی معتبر نیاز دارد.');
      else if (code === 'attempt_limit_reached') renderPartialNotice(this.root, 'سقف تلاش‌ها استفاده شده است', 'سرور اجازه شروع تلاش جدید برای این آزمون را نداد.');
      else if (errorShape(error).kind === 'permission') renderPartialNotice(this.root, 'اجازه شروع این آزمون را ندارید', 'مجوز و محدوده دسترسی توسط سرور بررسی شده است.');
      else renderPartialNotice(this.root, 'شروع آزمون انجام نشد', 'تلاش جدید روی سرور ایجاد نشد. دوباره تلاش کنید.', () => this.startAssessment());
    }
  }

  renderAttempt() {
    const actions = {
      back: () => this.backToLanding(),
      goToQuestion: (index) => { this.activeQuestion = Math.max(0, Math.min(index, (this.attempt?.questions?.length || 1) - 1)); this.renderAttempt(); },
      answer: (questionId, choice) => { this.answers = { ...this.answers, [String(questionId)]: Number(choice) }; this.mutation.saveState = 'idle'; this.renderAttempt(); },
      save: () => this.saveAttempt(),
      submit: () => this.submitAttempt(),
      requestSubmit: () => actions.openSubmit?.(),
      bindSubmitDialog: (callback) => { actions.openSubmit = callback; },
    };
    renderAssessmentAttempt(this.root, {
      ctx: this.ctx,
      assessment: this.selectedAssessment,
      attempt: this.attempt,
      answers: this.answers,
      activeIndex: this.activeQuestion,
      mutation: this.mutation,
    }, actions);
  }

  async saveAttempt() {
    if (!this.attempt?.attempt_id || this.mutation.pending || this.mutation.saveState === 'conflict') return;
    const workspace = workspaceId(this.ctx);
    this.mutation = { pending: true, saveState: 'saving' };
    this.renderAttempt();
    try {
      const saved = await apiRequest(this.ctx, apiPath(workspace, `/attempts/${encodeURIComponent(this.attempt.attempt_id)}`), {
        method: 'PATCH',
        body: { revision: Number(this.attempt.revision || 0), answers: { ...this.answers } },
        signal: this.abort.signal,
      });
      if (this.destroyed) return;
      this.attempt = { ...this.attempt, revision: saved.revision, status: saved.status || this.attempt.status };
      this.mutation = { pending: false, saveState: 'saved' };
      this.renderAttempt();
    } catch (error) {
      if (this.destroyed) return;
      const conflict = clean(error?.code) === 'attempt_revision_conflict';
      this.mutation = { pending: false, saveState: conflict ? 'conflict' : 'error' };
      this.renderAttempt();
    }
  }

  async submitAttempt() {
    if (!this.attempt?.attempt_id || this.mutation.pending || this.mutation.saveState === 'conflict') return;
    const workspace = workspaceId(this.ctx);
    this.mutation = { pending: true, saveState: this.mutation.saveState };
    this.renderAttempt();
    try {
      const submitted = await apiRequest(this.ctx, apiPath(workspace, `/attempts/${encodeURIComponent(this.attempt.attempt_id)}/submit`), {
        method: 'POST',
        body: { revision: Number(this.attempt.revision || 0), answers: { ...this.answers } },
        signal: this.abort.signal,
      });
      if (this.destroyed) return;
      this.attempt = { ...this.attempt, ...submitted };
      let canonicalResult = submitted;
      try {
        canonicalResult = await apiRequest(this.ctx, apiPath(workspace, `/attempts/${encodeURIComponent(this.attempt.attempt_id)}/review`), { signal: this.abort.signal });
      } catch (_reviewError) {
        canonicalResult = submitted;
      }
      if (this.destroyed) return;
      this.result = canonicalResult;
      this.mutation = { pending: false, saveState: 'saved' };
      renderAssessmentResult(this.root, { ctx: this.ctx, assessment: this.selectedAssessment, attempt: this.attempt, result: this.result }, { back: () => this.backToLanding() });
    } catch (error) {
      if (this.destroyed) return;
      const conflict = clean(error?.code) === 'attempt_revision_conflict';
      this.mutation = { pending: false, saveState: conflict ? 'conflict' : 'error' };
      this.renderAttempt();
      renderPartialNotice(this.root, conflict ? 'نسخه تلاش تغییر کرده است' : 'ثبت نهایی انجام نشد', conflict ? 'برای جلوگیری از ثبت روی نسخه قدیمی، سرور درخواست را نپذیرفت. projection فعلی مسیر بازیابی تلاش موجود را ارائه نمی‌کند.' : 'پاسخ‌ها ثبت نهایی نشدند و امتیازی در مرورگر ساخته نشده است.');
    }
  }

  async loadGrades() {
    const workspace = workspaceId(this.ctx);
    if (!workspace) {
      renderFailure(this.root, 'permission', { area: 'grades' });
      return;
    }
    renderLoading(this.root, 'grades');
    try {
      const payload = await apiRequest(this.ctx, apiPath(workspace, '/grades/me'), { signal: this.abort.signal });
      if (this.destroyed) return;
      renderGrades(this.root, { ctx: this.ctx, rows: rows(payload) });
    } catch (error) {
      if (this.destroyed) return;
      const failure = errorShape(error);
      renderFailure(this.root, failure.kind, { area: 'grades', retry: failure.kind === 'error' ? () => this.loadGrades() : null });
    }
  }
}

export async function renderCourseGradesSlot(ctx, { root, courseCode, courseTitle = '' } = {}) {
  if (!root) throw new TypeError('Course grade slot requires a root element.');
  const workspace = workspaceId(ctx);
  const code = clean(courseCode);
  renderLoading(root, 'grades');
  if (!workspace || !code) {
    renderFailure(root, 'permission', { area: 'grades' });
    return;
  }
  try {
    const payload = await apiRequest(ctx, apiPath(workspace, '/grades/me'), { signal: ctx?.signal });
    const filtered = rows(payload).filter((row) => clean(row?.course_code) === code);
    renderGrades(root, { ctx, rows: filtered, courseScope: code, courseTitle });
  } catch (error) {
    const failure = errorShape(error);
    renderFailure(root, failure.kind, { area: 'grades' });
  }
}

export const moduleDefinition = Object.freeze({
  id: 'progress',
  routes: Object.freeze([
    { id: 'assessments', path: '/assessments', label: 'آزمون‌ها و بانک سؤال' },
    { id: 'grades', path: '/grades', label: 'نمرات' },
  ]),
  navItems: Object.freeze([
    { id: 'assessments', label: 'آزمون‌ها', route: '/assessments' },
    { id: 'grades', label: 'نمرات', route: '/grades' },
  ]),
  integrationSlots: Object.freeze({
    courseGrades: 'renderCourseGradesSlot',
    questionBankDestination: '/resources?type=question_bank',
  }),
  async mount(ctx) {
    if (!ctx?.root) throw new TypeError('Progress module requires ctx.root.');
    controllers.get(ctx.root)?.destroy();
    const controller = new ProgressController(ctx);
    controllers.set(ctx.root, controller);
    await controller.mount();
  },
  unmount(ctx) {
    const root = ctx?.root;
    if (!root) return;
    const controller = controllers.get(root);
    controller?.destroy();
    controllers.delete(root);
  },
});

export default moduleDefinition;
