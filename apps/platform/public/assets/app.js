'use strict';
(() => {
  const UI = window.FanoosProductUI;
  const UX = window.FanoosDomainUX;
  if (!UI || !UX) return;

  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => [...document.querySelectorAll(selector)];
  const ambiguousLogout={title:'وضعیت خروج تأیید نشد'};
  const viewMap = { academics: 'courses', schedule: 'schedule', resources: 'resources', assessments: 'assessments', grades: 'grades', announcements: 'announcements', forms: 'forms', orders: 'orders' };
  const state = {
    token: sessionStorage.getItem('fanoos_token') || '',
    csrf: sessionStorage.getItem('fanoos_csrf') || '',
    workspace: null,
    account: null,
    route: UI.routeFromHash(location.hash),
    requestSerial: 0,
    workspaceMutation: false,
    selectedAnnouncement: null,
    selectedForm: null,
    selectedResource: null,
    selectedAssessment: null,
    assessmentAttempt: null,
    assessmentReview: null,
    management: null,
    managementCheckedFor: null,
  };

  function currentWorkspace() {
    const workspaces = Array.isArray(state.account?.workspaces) ? state.account.workspaces : [];
    return workspaces.find((workspace) => String(workspace.id) === String(state.workspace)) || null;
  }
  function workspaceTimezone() {
    const value = currentWorkspace()?.timezone_name;
    return typeof value === 'string' && value.trim() ? value.trim() : '';
  }
  function workspaceName() { return currentWorkspace()?.name || currentWorkspace()?.label || 'فضای آموزشی'; }
  function routeContext() {
    let todayLabel = '';
    try { todayLabel = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long', day: 'numeric', month: 'long', timeZone: workspaceTimezone() || undefined }).format(new Date()); } catch (_error) {}
    return {workspaceTimezone:workspaceTimezone(),workspaceName:workspaceName(),todayLabel};
  }

  function safeError(error) {
    return { status: Number(error?.status) || 0, code: String(error?.code || 'request_failed').slice(0, 80) };
  }

  async function api(path, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && state.csrf) headers['X-CSRF-Token'] = state.csrf;
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    let response;
    try {
      response = await fetch(path, { method, headers, credentials: 'same-origin', body: options.body === undefined ? undefined : JSON.stringify(options.body) });
    } catch (_error) {
      const failure = new Error('network'); failure.status = 0; failure.code = 'network_error'; throw failure;
    }
    let payload = null;
    try { payload = await response.json(); } catch (_error) { /* safe generic error below */ }
    if (!response.ok || !payload?.ok) {
      const failure = new Error('api');
      failure.status = response.status;
      failure.code = String(payload?.error?.code || 'request_failed').slice(0, 80);
      if (response.status === 401) handleSessionExpired();
      throw failure;
    }
    return payload.data;
  }

  async function apiBinary(path, body) {
    const headers = { Accept: 'application/pdf,application/octet-stream' };
    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    if (state.csrf) headers['X-CSRF-Token'] = state.csrf;
    headers['Content-Type'] = 'application/json';
    let response;
    try {
      response = await fetch(path, { method: 'POST', headers, credentials: 'same-origin', body: JSON.stringify(body || {}) });
    } catch (_error) {
      const failure = new Error('network'); failure.status = 0; failure.code = 'network_error'; throw failure;
    }
    if (!response.ok) {
      let payload = null;
      try { payload = await response.json(); } catch (_error) {}
      const failure = new Error('api');
      failure.status = response.status;
      failure.code = String(payload?.error?.code || 'request_failed').slice(0, 80);
      if (response.status === 401) handleSessionExpired();
      throw failure;
    }
    return response.blob();
  }

  async function safeRead(path) {
    try { return { ok: true, data: await api(path) }; }
    catch (error) { return { ok: false, error: safeError(error) }; }
  }

  function handleSessionExpired() {
    state.token = ''; state.csrf = ''; state.workspace = null; state.account = null;
    sessionStorage.removeItem('fanoos_token'); sessionStorage.removeItem('fanoos_csrf');
    showLoggedOut('نشست شما منقضی شده است. دوباره وارد شوید.');
  }

  function showLoggedOut(message = '') {
    $('#login-panel').hidden = false;
    $('#dashboard').hidden = true;
    $('#workspace-picker').hidden = true;
    $('#header-actions').hidden = true;
    $('#mobile-bottom-nav').hidden = true;
    closeDrawer(false);
    if (message) $('#login-message').textContent = message;
  }

  function showLoggedIn(account, focus = false) {
    $('#login-panel').hidden = true;
    $('#dashboard').hidden = false;
    $('#workspace-picker').hidden = false;
    $('#header-actions').hidden = false;
    $('#mobile-bottom-nav').hidden = false;
    $('#header-user-name').textContent = account?.user?.display_name || 'حساب';
    $('#greeting').textContent = 'خانه';
    const select = $('#workspace-select');
    select.replaceChildren();
    (Array.isArray(account?.workspaces) ? account.workspaces : []).forEach((workspace) => {
      const option = document.createElement('option');
      option.value = String(workspace.id || '');
      option.textContent = String(workspace.name || workspace.label || workspace.slug || 'فضای آموزشی');
      option.selected = String(workspace.id || '') === String(state.workspace || '');
      select.append(option);
    });
    renderWorkspaceContext();
    if(focus)$('#dashboard').focus();
  }

  function renderWorkspaceContext() {
    const workspace = currentWorkspace();
    $('#sidebar-workspace-name').textContent = workspace?.name || workspace?.label || '—';
    const path = workspace?.path_label || workspace?.directory_path || workspace?.slug || '';
    $('#workspace-path').textContent = path || '—';
    const now = new Date();
    try {
      $('#today').textContent = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long', day: 'numeric', month: 'long', timeZone: workspaceTimezone() || undefined }).format(now);
    } catch (_error) {
      $('#today').textContent = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long' }).format(now);
    }
  }

  async function loadAccount(focus = false) {
    const account = await api('/api/v1/account');
    state.account = account;
    const selected = account?.selected_workspace_id;
    const workspaces = Array.isArray(account?.workspaces) ? account.workspaces : [];
    state.workspace = selected && workspaces.some((workspace) => String(workspace.id) === String(selected)) ? selected : (workspaces[0]?.id || null);
    showLoggedIn(account, focus);
    if (!state.workspace) {
      UI.stateBlock($('#result-list'), 'فضای آموزشی فعالی وجود ندارد', 'برای این حساب هنوز عضویت فعالی در یک فضای آموزشی ثبت نشده است.');
      return;
    }
    await probeManagement();
    await renderRoute();
  }

  function setActiveView(key) {
    $$('[data-view]').forEach(button => {
      const active = button.dataset.view === key;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed',active?'true':'false');
      if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
    $$('[data-route]').forEach(button => {
      const active = button.dataset.route === state.route.name;
      button.classList.toggle('active', active);
      if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
  }
  function clearActiveView() {
    $$('[data-view]').forEach(button => { button.classList.remove('active'); button.setAttribute('aria-pressed', 'false'); button.removeAttribute('aria-current'); });
  }

  function applyRouteMeta() {
    const meta = UI.pageMeta(state.route.name);
    $('#page-eyebrow').textContent = meta.eyebrow || 'فانوس';
    $('#greeting').textContent = meta.title;
    $('#page-subtitle').textContent = meta.subtitle || '';
    $('#view-title').textContent = meta.title;
    $('#view-description').textContent = meta.description || meta.subtitle || '';
    document.title = `${meta.title} | فانوس`;
    const legacy = Object.entries(viewMap).find(([, route]) => route === state.route.name)?.[0];
    if (legacy) setActiveView(legacy); else { clearActiveView(); setActiveView(''); }
    if (state.route.name === 'home') $$('[data-route="home"]').forEach(node => { node.classList.add('active'); node.setAttribute('aria-current', 'page'); });
  }

  function clearAssessmentState() {
    state.selectedAssessment = null;
    state.assessmentAttempt = null;
    state.assessmentReview = null;
  }

  function navigate(name, query = {}, subview = '') {
    closeDrawer(false);
    state.selectedAnnouncement = null; state.selectedForm = null; state.selectedResource = null; clearAssessmentState();
    const hash = UI.routeHash(name, query, subview);
    if (location.hash === hash) { state.route = UI.routeFromHash(hash); renderRoute(); }
    else location.hash = hash;
  }

  function encodeQuery(params) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => { if (value != null && String(value).trim() !== '') query.set(key, String(value)); });
    return query.toString();
  }

  function dateRange(days = 120) {
    const timezone = workspaceTimezone() || 'UTC';
    const fallback = new Date().toISOString().slice(0, 10);
    const today = UI.localDateKey(new Date().toISOString(), timezone) || fallback;
    const move = UI._internal?.addNeutralDays || ((key, delta) => { const value = new Date(`${key}T00:00:00Z`); value.setUTCDate(value.getUTCDate() + delta); return value.toISOString().slice(0, 10); });
    return { from: move(today, -7), to: move(today, days) };
  }

  function courseIdForCode(academics, code) {
    if (!code || !academics?.ok) return '';
    const courses = UI.courseGroups(academics.data?.courses || []);
    return UI.findCourse(courses, code)?.id || '';
  }

  function handlers(serial) {
    return {
      navigate,
      retry: () => renderRoute(),
      switchWorkspace,
      openAnnouncement: (row) => { state.selectedAnnouncement = row; renderRoute({ reuse: true }); },
      closeAnnouncement: () => { state.selectedAnnouncement = null; renderRoute({ reuse: true }); },
      markAnnouncementRead: (row) => markAnnouncementRead(row, serial),
      openForm: (row) => { state.selectedForm = row; renderRoute({ reuse: true }); },
      closeForm: () => { state.selectedForm = null; renderRoute({ reuse: true }); },
      submitForm: (row, answers, form) => submitForm(row, answers, form, serial),
      openResource: (row) => openResource(row, serial),
      closeResource: () => { state.selectedResource = null; renderRoute({ reuse: true }); },
      requestDelivery: (row) => requestDelivery(row, serial),
      openAssessment: (row) => {
        const sameAttempt = String(state.assessmentAttempt?.assessment_id || '') === String(row?.id || '');
        const sameReview = String(state.assessmentReview?.assessment_id || '') === String(row?.id || '');
        state.selectedAssessment = row;
        if (!sameAttempt) state.assessmentAttempt = null;
        if (!sameReview) state.assessmentReview = null;
        renderRoute({ reuse: true });
      },
      closeAssessment: () => { state.selectedAssessment = null; renderRoute({ reuse: true }); },
      startAssessment: (row) => startAssessment(row, serial),
      saveAssessment: (answers, form) => saveAssessment(answers, form, serial),
      submitAssessment: (answers, form) => submitAssessment(answers, form, serial),
    };
  }

  function setBusy(busy) {
    $('#result-list').setAttribute('aria-busy', busy ? 'true' : 'false');
  }

  async function renderRoute(options = {}) {
    if (!state.workspace || state.workspaceMutation) return;
    const serial = options.reuse ? state.requestSerial : ++state.requestSerial;
    applyRouteMeta(); renderWorkspaceContext();
    const container = $('#result-list');
    if (!options.reuse) { setBusy(true); UI.skeleton(container, state.route.name === 'home' ? 5 : 4); }
    const context = routeContext();
    const h = handlers(serial);
    try {
      if (state.route.name === 'home') {
        const range = dateRange(90);
        const [schedule, assessments, announcements, resources, grades] = await Promise.all([
          safeRead(`/api/v1/workspaces/${state.workspace}/schedule?${encodeQuery(range)}`),
          safeRead(`/api/v1/workspaces/${state.workspace}/assessments`),
          safeRead(`/api/v1/workspaces/${state.workspace}/announcements`),
          safeRead(`/api/v1/workspaces/${state.workspace}/resources?sort=newest`),
          safeRead(`/api/v1/workspaces/${state.workspace}/grades/me`),
        ]);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderHome(container, { parts: { schedule, assessments, announcements, resources, grades }, context, handlers: h });
        updateAnnouncementBadge(announcements);
      } else if (state.route.name === 'courses') {
        const academics = await safeRead(`/api/v1/workspaces/${state.workspace}/academics`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        let parts = null;
        if (state.route.query.course && academics.ok) {
          const courseId = courseIdForCode(academics, state.route.query.course);
          const range = dateRange(180);
          const [schedule, resources, assessments, grades] = await Promise.all([
            safeRead(`/api/v1/workspaces/${state.workspace}/schedule?${encodeQuery(range)}`),
            safeRead(`/api/v1/workspaces/${state.workspace}/resources?${encodeQuery({ sort: 'newest', course_id: courseId })}`),
            safeRead(`/api/v1/workspaces/${state.workspace}/assessments?${encodeQuery({ course_id: courseId })}`),
            safeRead(`/api/v1/workspaces/${state.workspace}/grades/me`),
          ]);
          if (serial !== state.requestSerial || state.workspaceMutation) return;
          parts = { schedule, resources, assessments, grades };
        }
        UI.renderCourses(container, { academics, parts, route: state.route, context, handlers: h });
      } else if (state.route.name === 'schedule') {
        const range = dateRange(state.route.subview === 'upcoming' ? 180 : 35);
        const schedule = await safeRead(`/api/v1/workspaces/${state.workspace}/schedule?${encodeQuery(range)}`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderSchedule(container, { schedule, route: state.route, context, handlers: h });
      } else if (state.route.name === 'resources') {
        const academics = await safeRead(`/api/v1/workspaces/${state.workspace}/academics`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        const courseId = courseIdForCode(academics, state.route.query.course);
        const query = encodeQuery({ q: state.route.query.q, type: state.route.query.type, course_id: courseId, sort: 'newest' });
        const resources = await safeRead(`/api/v1/workspaces/${state.workspace}/resources?${query}`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        if (state.selectedResource) UI.renderResourceDetail(container, state.selectedResource, { route: state.route, context, handlers: h });
        else UI.renderResources(container, { resources, academics, route: state.route, context, handlers: h });
      } else if (state.route.name === 'assessments') {
        const [academics, assessments] = await Promise.all([
          safeRead(`/api/v1/workspaces/${state.workspace}/academics`),
          safeRead(`/api/v1/workspaces/${state.workspace}/assessments?${encodeQuery({ kind: state.route.query.kind })}`),
        ]);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        if (state.selectedAssessment && state.assessmentReview) UI.renderAssessmentResult(container, state.selectedAssessment, state.assessmentAttempt, state.assessmentReview, { context, handlers: h });
        else if (state.selectedAssessment && state.assessmentAttempt) UI.renderAssessmentAttempt(container, state.selectedAssessment, state.assessmentAttempt, { context, handlers: h });
        else if (state.selectedAssessment) UI.renderAssessmentDetail(container, state.selectedAssessment, { context, handlers: h });
        else UI.renderAssessments(container, { assessments, academics, route: state.route, context, handlers: h });
      } else if (state.route.name === 'grades') {
        const grades = await safeRead(`/api/v1/workspaces/${state.workspace}/grades/me`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderGrades(container, { grades, context, handlers: h });
      } else if (state.route.name === 'announcements') {
        const announcements = await safeRead(`/api/v1/workspaces/${state.workspace}/announcements`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderAnnouncements(container, { announcements, selectedAnnouncement: state.selectedAnnouncement, context, handlers: h });
        updateAnnouncementBadge(announcements);
      } else if (state.route.name === 'forms') {
        const forms = await safeRead(`/api/v1/workspaces/${state.workspace}/forms`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderForms(container, { forms, selectedForm: state.selectedForm, context, handlers: h });
      } else if (state.route.name === 'orders') {
        const orders = await safeRead(`/api/v1/workspaces/${state.workspace}/orders`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderOrders(container, { orders, context, handlers: h });
      } else if (state.route.name === 'search') {
        const q = String(state.route.query.q || '').trim();
        if(!state.workspace||state.workspaceMutation||q.length<2) { UI.renderSearch(container, { route: state.route, search: { ok: true, data: [] }, context, handlers: h }); return; }
        const search = await safeRead(`/api/v1/workspaces/${state.workspace}/search?${encodeQuery({ q })}`);
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderSearch(container, { route: state.route, search, context, handlers: h });
      } else if (state.route.name === 'account') {
        UI.renderAccount(container, { account: state.account || {}, context, handlers: h });
      } else if (state.route.name === 'management') {
        await probeManagement();
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        UI.renderManagement(container, { management: state.management, context, handlers: h });
      } else {
        navigate('home');
      }
    } finally {
      if (serial === state.requestSerial) setBusy(false);
    }
  }

  // Compatibility shim for existing UI checks and old view hooks; V2 routing owns presentation.
  function loadView(view) {
    if(!view||state.workspaceMutation)return;
    navigate(viewMap[view] || 'home');
  }

  async function probeManagement() {
    if (!state.workspace || state.managementCheckedFor === state.workspace) return;
    const result = await safeRead(`/api/v1/workspaces/${state.workspace}/admin/dashboard`);
    state.managementCheckedFor = state.workspace;
    const allowed = !!result.ok && result.data?.management_available === true;
    state.management = allowed ? result : null;
    $('#management-nav').hidden = !allowed;
    $('#mobile-management-nav').hidden = !allowed;
    if (!allowed && state.route.name === 'management') navigate('home');
  }

  async function switchWorkspace(workspaceId) {
    const select = $('#workspace-select');
    if (!workspaceId || state.workspaceMutation || String(workspaceId) === String(state.workspace)) return;
    const previous = state.workspace;
    state.workspaceMutation=true;select.disabled=true;++state.requestSerial;
    try {
      await api('/api/v1/workspaces/select', { method: 'POST', body: { workspace_id: workspaceId } });
      state.workspace = workspaceId;
      if (state.account) state.account.selected_workspace_id = workspaceId;
      state.management = null; state.managementCheckedFor = null;
      state.selectedAnnouncement = null; state.selectedForm = null; state.selectedResource = null; clearAssessmentState();
      renderWorkspaceContext();
      toast('فضای آموزشی تغییر کرد.', 'success');
    } catch (_error) {
      state.workspace = previous;
      select.value = String(previous || '');
      toast('تغییر فضای آموزشی انجام نشد.', 'error');
    } finally {
      state.workspaceMutation = false; select.disabled = false;
      if (state.workspace) { await probeManagement(); await renderRoute(); }
    }
  }

  async function markAnnouncementRead(row, serial) {
    if (!row?.id) return;
    try {
      await api(`/api/v1/workspaces/${state.workspace}/announcements/${encodeURIComponent(row.id)}/read`, { method: 'POST', body: {} });
      if (serial !== state.requestSerial) return;
      state.selectedAnnouncement = { ...row, status: 'read', read_at: new Date().toISOString() };
      toast('اطلاعیه خوانده‌شده ثبت شد.', 'success');
      await renderRoute();
    } catch (_error) { toast('ثبت وضعیت اطلاعیه انجام نشد.', 'error'); }
  }

  async function submitForm(row, answers, form, serial) {
    if (!row?.id || !form) return;
    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    const idempotencyKey = (globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`).slice(0, 128);
    try {
      await api(`/api/v1/workspaces/${state.workspace}/forms/${encodeURIComponent(row.id)}/submissions`, { method: 'POST', body: { answers, idempotency_key: idempotencyKey } });
      if (serial !== state.requestSerial) return;
      toast('پاسخ فرم ثبت شد.', 'success'); state.selectedForm = null; await renderRoute();
    } catch (_error) { toast('ثبت فرم انجام نشد. پاسخ‌ها را بررسی کنید و دوباره تلاش کنید.', 'error'); }
    finally { if (submit) submit.disabled = false; }
  }

  async function openResource(row, serial) {
    if (!row?.id) return;
    $('#result-list').setAttribute('aria-busy', 'true');
    const detail = await safeRead(`/api/v1/workspaces/${state.workspace}/resources/${encodeURIComponent(row.id)}`);
    if (serial !== state.requestSerial || state.workspaceMutation) return;
    state.selectedResource = detail;
    UI.renderResourceDetail($('#result-list'), detail, { context: routeContext(), handlers: handlers(serial) });
    setBusy(false);
  }

  async function requestDelivery(row, serial) {
    if (!row?.id) return;
    try {
      const issued = await api(`/api/v1/workspaces/${state.workspace}/resources/${encodeURIComponent(row.id)}/deliveries`, { method: 'POST', body: { channel: 'web' } });
      const served = await api(`/api/v1/workspaces/${state.workspace}/deliveries/consume`, { method: 'POST', body: { delivery_token: issued.delivery_token } });
      if (serial !== state.requestSerial || state.workspaceMutation) return;
      if (served?.content && typeof served.content === 'object') {
        toast('دسترسی منبع تأیید شد. محتوای ساختاریافته از مسیر امن دریافت شد.', 'success');
      } else if (served?.download_token) {
        const blob = await apiBinary(`/api/v1/workspaces/${state.workspace}/downloads/consume`, { download_token: served.download_token });
        if (serial !== state.requestSerial || state.workspaceMutation) return;
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = 'fanoos-protected.pdf';
        link.hidden = true;
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(objectUrl), 30000);
        toast('فایل پس از بازاعتبارسنجی دسترسی از مسیر امن دریافت شد.', 'success');
      } else {
        toast('دسترسی منبع تأیید شد، اما محتوای قابل تحویل در projection فعلی وجود ندارد.', 'info');
      }
    } catch (_error) { toast('دسترسی یا تحویل این منبع تأیید نشد.', 'error'); }
  }

  async function startAssessment(row, serial) {
    if (!row?.id || state.assessmentAttempt) return;
    setBusy(true);
    try {
      const attempt = await api(`/api/v1/workspaces/${state.workspace}/assessments/${encodeURIComponent(row.id)}/attempts`, { method: 'POST', body: {} });
      if (serial !== state.requestSerial || state.workspaceMutation) return;
      state.selectedAssessment = row;
      state.assessmentAttempt = attempt;
      state.assessmentReview = null;
      toast('تلاش آزمون روی سرور آغاز شد.', 'success');
      await renderRoute({ reuse: true });
    } catch (error) {
      const code = String(error?.code || '');
      if (code === 'entitlement_required') toast('برای شروع این آزمون دسترسی فعال لازم است.', 'error');
      else if (code === 'attempt_limit_reached') toast('حداکثر تعداد تلاش مجاز برای این آزمون استفاده شده است.', 'error');
      else toast('شروع آزمون انجام نشد.', 'error');
    } finally { setBusy(false); }
  }

  async function saveAssessment(answers, form, serial) {
    const attempt = state.assessmentAttempt;
    if (!attempt?.attempt_id || !form) return;
    const buttons = [...form.querySelectorAll('button')];
    buttons.forEach((item) => { item.disabled = true; });
    try {
      const saved = await api(`/api/v1/workspaces/${state.workspace}/attempts/${encodeURIComponent(attempt.attempt_id)}`, { method: 'PATCH', body: { revision: Number(attempt.revision || 0), answers } });
      if (serial !== state.requestSerial || state.workspaceMutation) return;
      state.assessmentAttempt = { ...attempt, revision: saved.revision, status: saved.status };
      toast('پاسخ‌ها روی سرور ذخیره شدند.', 'success');
    } catch (error) {
      toast(String(error?.code || '') === 'attempt_revision_conflict' ? 'نسخه تلاش تغییر کرده است؛ آزمون را دوباره باز کنید.' : 'ذخیره پاسخ‌ها انجام نشد.', 'error');
    } finally { buttons.forEach((item) => { item.disabled = false; }); }
  }

  async function submitAssessment(answers, form, serial) {
    const attempt = state.assessmentAttempt;
    if (!attempt?.attempt_id || !form) return;
    const buttons = [...form.querySelectorAll('button')];
    buttons.forEach((item) => { item.disabled = true; });
    try {
      const result = await api(`/api/v1/workspaces/${state.workspace}/attempts/${encodeURIComponent(attempt.attempt_id)}/submit`, { method: 'POST', body: { revision: Number(attempt.revision || 0), answers } });
      if (serial !== state.requestSerial || state.workspaceMutation) return;
      const review = await safeRead(`/api/v1/workspaces/${state.workspace}/attempts/${encodeURIComponent(attempt.attempt_id)}/review`);
      if (serial !== state.requestSerial || state.workspaceMutation) return;
      state.assessmentAttempt = { ...attempt, ...result };
      state.assessmentReview = review.ok ? review.data : result;
      toast('آزمون ثبت و توسط سرور امتیازدهی شد.', 'success');
      await renderRoute({ reuse: true });
    } catch (error) {
      toast(String(error?.code || '') === 'attempt_revision_conflict' ? 'نسخه تلاش تغییر کرده است؛ پاسخ‌ها ثبت نشدند.' : 'ثبت نهایی آزمون انجام نشد.', 'error');
    } finally { buttons.forEach((item) => { item.disabled = false; }); }
  }

  function updateAnnouncementBadge(result) {
    const rows = result?.ok && Array.isArray(result.data) ? result.data : [];
    const unread = rows.filter((row) => String(row.status || '').toLowerCase() !== 'read' && !row.read_at).length;
    const badge = $('#announcement-nav-badge');
    badge.hidden = unread < 1; badge.textContent = unread ? UX.formatNumber(unread) : '';
  }

  function toast(message, kind = 'info') {
    const region = $('#toast-region');
    const item = document.createElement('div');
    item.className = `toast toast--${kind}`; item.setAttribute('role', 'status'); item.textContent = String(message || '').slice(0, 240);
    region.append(item); setTimeout(() => item.remove(), 4500);
  }

  function openDrawer(trigger) {
    const drawer = $('#mobile-more-drawer'); drawer.hidden = false; $('#drawer-backdrop').hidden = false; document.body.classList.add('drawer-open');
    drawer.dataset.returnFocus = trigger?.dataset?.route || 'more';
    drawer.querySelector('[data-drawer-close]')?.focus();
  }
  function closeDrawer(restore = true) {
    const drawer = $('#mobile-more-drawer'); if (!drawer || drawer.hidden) return;
    drawer.hidden = true; $('#drawer-backdrop').hidden = true; document.body.classList.remove('drawer-open');
    if (restore) $('[data-route="more"]')?.focus();
  }

  $('#login-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget; const submit = form.querySelector('[type="submit"]');
    $('#login-message').textContent = ''; submit.disabled = true;
    try {
      const result = await api('/api/v1/auth/login', { method: 'POST', body: { identifier: $('#login-identifier').value.trim(), password: $('#login-password').value } });
      state.token = String(result.token || ''); state.csrf = String(result.csrf_token || ''); state.account = result.account || null;
      sessionStorage.setItem('fanoos_token', state.token); sessionStorage.setItem('fanoos_csrf', state.csrf);
      await loadAccount(true);
    } catch (_error) { $('#login-message').textContent = 'ورود انجام نشد. شناسه و رمز عبور را بررسی کنید.'; }
    finally { submit.disabled = false; }
  });

  $('#logout').addEventListener('click', async () => {
    const button = $('#logout'); button.disabled = true;
    try {
      await api('/api/v1/auth/logout', { method: 'POST', body: {} });
      state.token = ''; state.csrf = ''; state.workspace = null; state.account = null;
      sessionStorage.removeItem('fanoos_token'); sessionStorage.removeItem('fanoos_csrf');
      showLoggedOut('از حساب خارج شدید.');
    } catch (error) {
      if (Number(error?.status) === 401) {
        state.token = ''; state.csrf = ''; sessionStorage.removeItem('fanoos_token'); sessionStorage.removeItem('fanoos_csrf'); showLoggedOut('نشست شما پایان یافته است.');
      } else {
        UI.stateBlock($('#result-list'), ambiguousLogout.title, 'ارتباط با سرور قطع شد و نمی‌توان پایان نشست را تأیید کرد. برای جلوگیری از نمایش وضعیت نادرست، نشست محلی پاک نشد.', { actionLabel: 'تلاش دوباره', onAction: () => button.click() });
      }
    } finally { button.disabled = false; }
  });

  $('#workspace-select').addEventListener('change', (event) => switchWorkspace(event.target.value));

  $$('[data-view]').forEach(button => button.addEventListener('click', () => loadView(button.dataset.view)));
  $$('[data-route]').forEach(button => button.addEventListener('click', (event) => {
    const name = button.dataset.route;
    if (name === 'more') { event.preventDefault(); openDrawer(button); return; }
    event.preventDefault(); navigate(name);
  }));
  $('[data-drawer-close]').addEventListener('click', () => closeDrawer(true));
  $('#drawer-backdrop').addEventListener('click', () => closeDrawer(true));

  $('#search-form').addEventListener('submit', (event) => {
    event.preventDefault(); const q = $('#search-query').value.trim();
    if (q.length < 2) { toast('برای جست‌وجو حداقل دو نویسه وارد کنید.', 'info'); return; }
    navigate('search', { q });
  });
  $('.global-search-trigger').addEventListener('click', () => { navigate('search'); requestAnimationFrame(() => $('#search-query')?.focus()); });

  window.addEventListener('hashchange', () => {
    state.route = UI.routeFromHash(location.hash);
    state.selectedAnnouncement = null; state.selectedForm = null; state.selectedResource = null; clearAssessmentState();
    if (state.token && state.workspace) renderRoute();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeDrawer(true);
    if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) { event.preventDefault(); $('#search-query')?.focus(); }
  });

  async function boot() {
    if (!location.hash) history.replaceState(null, '', '#/home');
    state.route = UI.routeFromHash(location.hash);
    if (!state.token) { showLoggedOut(); return; }
    try { await loadAccount(false); }
    catch (_error) { handleSessionExpired(); }
  }
  boot();
})();