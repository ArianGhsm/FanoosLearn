import {
  EVENT_TYPE_LABELS,
  SCHEDULE_MODES,
  addDays,
  buildRange,
  compareDateKeys,
  deriveCourseOptions,
  deriveEventTypeOptions,
  filterRows,
  formatDateKey,
  formatDateTime,
  formatTime,
  groupByDate,
  isDateKey,
  normalizeRows,
  normalizeText,
  parseBackendInstant,
  rangeLabel,
  temporalCues,
  validTimeZone,
  weekKeys,
  workspaceTodayKey,
} from './schedule-model.js';

const UPCOMING_PAGE_SIZE = 24;
const SCHEDULE_STYLE_HREF = '/assets/ui-v3/schedule/schedule.css';

function node(tag, options = {}, ...children) {
  const element = document.createElement(tag);
  if (options.className) element.className = options.className;
  if (options.text !== undefined) element.textContent = String(options.text);
  if (options.attrs) {
    Object.entries(options.attrs).forEach(([name, value]) => {
      if (value !== null && value !== undefined && value !== false) {
        element.setAttribute(name, value === true ? '' : String(value));
      }
    });
  }
  if (options.on) {
    Object.entries(options.on).forEach(([eventName, handler]) => element.addEventListener(eventName, handler));
  }
  children.flat().filter(Boolean).forEach((child) => element.append(child));
  return element;
}

function textNode(value) {
  return document.createTextNode(String(value ?? ''));
}

function ensureStyleLink() {
  if (document.querySelector(`link[data-f3-schedule-style="${SCHEDULE_STYLE_HREF}"]`)) return;
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = SCHEDULE_STYLE_HREF;
  link.dataset.f3ScheduleStyle = SCHEDULE_STYLE_HREF;
  document.head.append(link);
}

function routeState(ctx) {
  const route = ctx?.state?.route && typeof ctx.state.route === 'object'
    ? ctx.state.route
    : (ctx?.route && typeof ctx.route === 'object' ? ctx.route : {});
  const mode = Object.hasOwn(SCHEDULE_MODES, route.subview) ? route.subview : 'today';
  const query = route.query && typeof route.query === 'object' ? route.query : {};
  return { mode, query };
}

function activeWorkspace(ctx) {
  const state = ctx?.state || {};
  if (state.activeWorkspace && typeof state.activeWorkspace === 'object') return state.activeWorkspace;
  if (state.workspace && typeof state.workspace === 'object') return state.workspace;

  const account = state.account && typeof state.account === 'object' ? state.account : {};
  const workspaces = Array.isArray(account.workspaces) ? account.workspaces : [];
  const requestedId = typeof state.workspace === 'string'
    ? state.workspace
    : (state.workspaceId || state.selectedWorkspaceId || account.selected_workspace_id || '');
  return workspaces.find((workspace) => String(workspace?.id || '') === String(requestedId || '')) || null;
}

function workspaceContract(ctx) {
  const workspace = activeWorkspace(ctx);
  const state = ctx?.state || {};
  const id = normalizeText(workspace?.id || state.workspaceId || (typeof state.workspace === 'string' ? state.workspace : ''), 80);
  const timeZone = validTimeZone(workspace?.timezone_name || workspace?.timezoneName || state.workspaceTimezone || '');
  const name = normalizeText(workspace?.name || workspace?.label || state.workspaceName || 'فضای آموزشی', 120) || 'فضای آموزشی';
  return { id, timeZone, name };
}

function buildUrl(path, query = {}) {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value !== null && value !== undefined && String(value) !== '') params.set(key, String(value));
  });
  const serialized = params.toString();
  return serialized ? `${path}?${serialized}` : path;
}

function unwrapRows(result) {
  if (Array.isArray(result)) return result;
  if (result && Array.isArray(result.data)) return result.data;
  if (result?.ok === true && Array.isArray(result.data)) return result.data;
  return [];
}

async function readSchedule(ctx, workspaceId, range) {
  const url = buildUrl(`/api/v1/workspaces/${encodeURIComponent(workspaceId)}/schedule`, {
    from: range.from,
    to: range.to,
  });
  if (typeof ctx?.api === 'function') return unwrapRows(await ctx.api(url, { method: 'GET', signal: ctx.signal }));
  if (typeof ctx?.api?.get === 'function') return unwrapRows(await ctx.api.get(url, { signal: ctx.signal }));
  if (typeof ctx?.api?.request === 'function') return unwrapRows(await ctx.api.request(url, { method: 'GET', signal: ctx.signal }));
  const error = new Error('schedule_api_unavailable');
  error.code = 'schedule_api_unavailable';
  throw error;
}

function navigateSchedule(ctx, mode, query = {}) {
  const compact = {};
  Object.entries(query).forEach(([key, value]) => {
    if (value !== null && value !== undefined && String(value).trim() !== '') compact[key] = String(value);
  });
  if (typeof ctx?.navigate === 'function') ctx.navigate('schedule', compact, mode);
}

function navigateCourse(ctx, courseCode) {
  const code = normalizeText(courseCode, 64);
  if (!code || typeof ctx?.navigate !== 'function') return;
  ctx.navigate('courses', { course: code, tab: 'schedule' });
}

function errorStatus(error) {
  return Number(error?.status || error?.response?.status || 0) || 0;
}

function renderState(root, title, description, options = {}) {
  const content = node('div', { className: 'f3-schedule-state__content' },
    node('h2', { className: 'f3-schedule-state__title', text: title }),
    node('p', { className: 'f3-schedule-state__description', text: description }),
  );
  if (options.actionLabel && typeof options.onAction === 'function') {
    content.append(node('button', {
      className: 'f3-schedule-button f3-schedule-button--secondary',
      text: options.actionLabel,
      attrs: { type: 'button' },
      on: { click: options.onAction },
    }));
  }
  root.replaceChildren(node('section', {
    className: `f3-schedule-state${options.kind ? ` f3-schedule-state--${options.kind}` : ''}`,
    attrs: { role: options.kind === 'error' ? 'alert' : 'status' },
  }, content));
}

function renderLoading(root) {
  const skeletons = Array.from({ length: 5 }, () => node('div', { className: 'f3-schedule-skeleton__row' },
    node('span', { className: 'f3-schedule-skeleton__time' }),
    node('span', { className: 'f3-schedule-skeleton__body' }),
  ));
  root.replaceChildren(node('section', {
    className: 'f3-schedule-loading',
    attrs: { 'aria-live': 'polite', 'aria-busy': 'true', 'aria-label': 'در حال دریافت برنامه' },
  }, node('div', { className: 'f3-schedule-skeleton' }, skeletons)));
}

function button(label, options = {}) {
  return node('button', {
    className: `f3-schedule-button${options.variant ? ` f3-schedule-button--${options.variant}` : ''}`,
    text: label,
    attrs: {
      type: 'button',
      disabled: options.disabled || null,
      'aria-label': options.ariaLabel || null,
      'aria-pressed': options.pressed === undefined ? null : String(Boolean(options.pressed)),
    },
    on: options.onClick ? { click: options.onClick } : undefined,
  });
}

function buildModeSwitch(ctx, route) {
  const control = node('div', {
    className: 'f3-schedule-modes',
    attrs: { role: 'group', 'aria-label': 'حالت نمایش برنامه' },
  });
  Object.entries(SCHEDULE_MODES).forEach(([key, config]) => {
    control.append(button(config.label, {
      variant: 'mode',
      pressed: route.mode === key,
      onClick: () => navigateSchedule(ctx, key, route.query),
    }));
  });
  return control;
}

function navigationQuery(route, dateKey, extra = {}) {
  const query = { ...route.query, ...extra, date: dateKey };
  if (route.mode !== 'week') delete query.day;
  return query;
}

function buildDateNavigation(ctx, route, range, todayKey) {
  const step = SCHEDULE_MODES[route.mode]?.stepDays || 1;
  const previousDate = addDays(range.anchor, -step);
  const nextDate = addDays(range.anchor, step);
  const upcomingAtToday = route.mode === 'upcoming' && compareDateKeys(range.anchor, todayKey) <= 0;
  const nav = node('div', { className: 'f3-schedule-date-nav', attrs: { 'aria-label': 'جابجایی تاریخ' } });
  nav.append(button('قبلی', {
    variant: 'quiet',
    disabled: upcomingAtToday,
    onClick: () => navigateSchedule(ctx, route.mode, navigationQuery(route, previousDate)),
  }));
  nav.append(node('div', { className: 'f3-schedule-date-nav__label', attrs: { 'aria-live': 'polite' } },
    node('strong', { text: rangeLabel(route.mode, range) }),
    node('span', { text: 'بر اساس منطقه زمانی فضای آموزشی' }),
  ));
  nav.append(button('بعدی', {
    variant: 'quiet',
    onClick: () => navigateSchedule(ctx, route.mode, navigationQuery(route, nextDate)),
  }));
  nav.append(button('امروز', {
    variant: 'secondary',
    disabled: range.anchor === todayKey,
    onClick: () => {
      const query = { ...route.query, date: todayKey };
      delete query.day;
      navigateSchedule(ctx, route.mode, query);
    },
  }));
  return nav;
}

function selectField(label, value, options, onChange, ariaLabel) {
  const id = `f3-schedule-${Math.random().toString(36).slice(2, 9)}`;
  const select = node('select', {
    className: 'f3-schedule-select',
    attrs: { id, 'aria-label': ariaLabel || label },
    on: { change: (event) => onChange(event.currentTarget.value) },
  });
  select.append(node('option', { text: `همه ${label}`, attrs: { value: '' } }));
  options.forEach((option) => {
    select.append(node('option', {
      text: option.label,
      attrs: { value: option.value, selected: String(option.value) === String(value || '') || null },
    }));
  });
  return node('label', { className: 'f3-schedule-filter' },
    node('span', { className: 'f3-schedule-filter__label', text: label }),
    select,
  );
}

function buildFilters(ctx, route, allRows) {
  const courseOptions = deriveCourseOptions(allRows);
  const typeOptions = deriveEventTypeOptions(allRows);
  const selectedCourse = normalizeText(route.query.course, 64);
  const selectedType = normalizeText(route.query.type, 32).toLowerCase();

  if (selectedCourse && !courseOptions.some((option) => option.value.toLocaleLowerCase('en-US') === selectedCourse.toLocaleLowerCase('en-US'))) {
    courseOptions.push({ value: selectedCourse, label: selectedCourse });
  }
  if (selectedType && EVENT_TYPE_LABELS[selectedType] && !typeOptions.some((option) => option.value === selectedType)) {
    typeOptions.push({ value: selectedType, label: EVENT_TYPE_LABELS[selectedType] });
  }

  const showCourse = courseOptions.length > 1 || Boolean(selectedCourse);
  const showType = typeOptions.length > 1 || Boolean(selectedType);
  if (!showCourse && !showType) return null;

  const bar = node('section', { className: 'f3-schedule-filters', attrs: { 'aria-label': 'فیلتر برنامه' } });
  const fields = node('div', { className: 'f3-schedule-filters__fields' });
  if (showCourse) {
    fields.append(selectField('درس', selectedCourse, courseOptions, (course) => {
      const query = { ...route.query, course };
      if (!course) delete query.course;
      navigateSchedule(ctx, route.mode, query);
    }, 'فیلتر بر اساس درس'));
  }
  if (showType) {
    fields.append(selectField('نوع رویداد', selectedType, typeOptions, (type) => {
      const query = { ...route.query, type };
      if (!type) delete query.type;
      navigateSchedule(ctx, route.mode, query);
    }, 'فیلتر بر اساس نوع رویداد'));
  }
  bar.append(fields);
  if (selectedCourse || selectedType) {
    bar.append(button('پاک‌کردن فیلترها', {
      variant: 'quiet',
      onClick: () => {
        const query = { ...route.query };
        delete query.course;
        delete query.type;
        navigateSchedule(ctx, route.mode, query);
      },
    }));
  }
  return bar;
}

function cueLabel(cue) {
  if (cue === 'current') return 'در حال برگزاری';
  if (cue === 'next') return 'بعدی';
  return '';
}

function timeRail(event, timeZone, compact = false) {
  const start = formatTime(event.startsAt, timeZone);
  const end = event.endsAt ? formatTime(event.endsAt, timeZone) : '';
  const rail = node('span', { className: `f3-schedule-event__time${compact ? ' f3-schedule-event__time--compact' : ''}` },
    node('bdi', { text: start, attrs: { dir: 'ltr' } }),
  );
  if (end && end !== '—') rail.append(node('small', {}, textNode('تا '), node('bdi', { text: end, attrs: { dir: 'ltr' } })));
  return rail;
}

function eventBadges(event, cue) {
  const badges = node('span', { className: 'f3-schedule-event__badges' });
  if (cueLabel(cue)) badges.append(node('span', { className: `f3-schedule-badge f3-schedule-badge--${cue}`, text: cueLabel(cue) }));
  if (event.eventTypeLabel) badges.append(node('span', { className: 'f3-schedule-badge', text: event.eventTypeLabel }));
  if (event.status === 'completed') badges.append(node('span', { className: 'f3-schedule-badge f3-schedule-badge--muted', text: event.statusLabel }));
  return badges.childNodes.length ? badges : null;
}

function eventMeta(event) {
  const meta = node('span', { className: 'f3-schedule-event__meta' });
  if (event.courseTitle) meta.append(node('span', { text: event.courseTitle }));
  if (event.location) meta.append(node('span', { text: event.location }));
  return meta.childNodes.length ? meta : null;
}

function eventButton(event, ctx, timeZone, cue = '', compact = false) {
  const body = node('span', { className: 'f3-schedule-event__body' },
    eventBadges(event, cue),
    node('strong', { className: 'f3-schedule-event__title', text: event.title }),
    eventMeta(event),
  );
  return node('button', {
    className: `f3-schedule-event${compact ? ' f3-schedule-event--compact' : ''}${cue ? ` f3-schedule-event--${cue}` : ''}`,
    attrs: { type: 'button', 'aria-label': `جزئیات ${event.title}` },
    on: { click: () => openEventDetail(ctx, event, timeZone) },
  }, timeRail(event, timeZone, compact), body);
}

function detailRow(label, value, options = {}) {
  if (!value) return null;
  return node('div', { className: 'f3-schedule-detail__row' },
    node('dt', { text: label }),
    node('dd', options.ltr ? { attrs: { dir: 'ltr' } } : {}, textNode(value)),
  );
}

function openEventDetail(ctx, event, timeZone) {
  const previousFocus = document.activeElement;
  const dialog = node('dialog', {
    className: 'f3-schedule-detail',
    attrs: { 'aria-labelledby': 'f3-schedule-detail-title' },
  });
  const close = () => {
    if (dialog.open && typeof dialog.close === 'function') dialog.close();
    else dialog.remove();
  };
  const header = node('div', { className: 'f3-schedule-detail__header' },
    node('div', {},
      event.eventTypeLabel ? node('span', { className: 'f3-schedule-detail__eyebrow', text: event.eventTypeLabel }) : null,
      node('h2', { className: 'f3-schedule-detail__title', text: event.title, attrs: { id: 'f3-schedule-detail-title' } }),
    ),
    button('بستن', { variant: 'quiet', ariaLabel: 'بستن جزئیات رویداد', onClick: close }),
  );

  const details = node('dl', { className: 'f3-schedule-detail__list' });
  [
    detailRow('زمان', event.endsAt
      ? `${formatDateTime(event.startsAt, timeZone)} تا ${formatTime(event.endsAt, timeZone)}`
      : formatDateTime(event.startsAt, timeZone)),
    detailRow('درس', event.courseTitle || event.courseCode),
    detailRow('محل', event.location),
    detailRow('نوع', event.eventTypeLabel),
    detailRow('وضعیت', event.statusLabel),
    detailRow('مدرس', event.instructor),
    detailRow('یادداشت', event.notes),
  ].filter(Boolean).forEach((row) => details.append(row));

  const actions = node('div', { className: 'f3-schedule-detail__actions' });
  if (event.courseCode) {
    actions.append(button('باز کردن درس', {
      variant: 'primary',
      onClick: () => {
        close();
        navigateCourse(ctx, event.courseCode);
      },
    }));
  }
  actions.append(button('بستن', { variant: 'secondary', onClick: close }));
  dialog.append(header, details, actions);
  dialog.addEventListener('click', (eventObject) => { if (eventObject.target === dialog) close(); });
  dialog.addEventListener('close', () => {
    dialog.remove();
    if (previousFocus instanceof HTMLElement && previousFocus.isConnected) previousFocus.focus();
  }, { once: true });
  document.body.append(dialog);
  if (typeof dialog.showModal === 'function') dialog.showModal();
  else dialog.setAttribute('open', '');
}

function partialNotice(skipped) {
  if (!skipped) return null;
  return node('div', { className: 'f3-schedule-partial', attrs: { role: 'status' } },
    node('strong', { text: 'بخشی از برنامه نمایش داده نشد.' }),
    node('span', { text: `${new Intl.NumberFormat('fa-IR').format(skipped)} رویداد به‌دلیل زمان نامعتبر قابل نمایش نبود.` }),
  );
}

function emptyForFilters(route) {
  return Boolean(route.query.course || route.query.type);
}

function renderNoEvents(container, route, mode) {
  container.append(node('section', { className: 'f3-schedule-empty' },
    node('h2', { text: emptyForFilters(route) ? 'رویدادی با این فیلتر پیدا نشد.' : 'برای این بازه برنامه‌ای ثبت نشده است.' }),
    node('p', { text: emptyForFilters(route)
      ? 'فیلترها را تغییر دهید یا پاک کنید.'
      : (mode === 'today' ? 'می‌توانید روز دیگری را بررسی کنید.' : 'بازه دیگری را انتخاب کنید یا بعداً دوباره بررسی کنید.') }),
  ));
}

function renderTimeline(container, events, ctx, timeZone, cues, route) {
  if (!events.length) return renderNoEvents(container, route, 'today');
  const list = node('div', { className: 'f3-schedule-timeline' });
  events.forEach((event) => list.append(eventButton(event, ctx, timeZone, cues.get(event.key) || '')));
  container.append(list);
}

function dayHeader(dateKey, todayKey, count) {
  return node('div', { className: `f3-schedule-day__header${dateKey === todayKey ? ' f3-schedule-day__header--today' : ''}` },
    node('strong', { text: formatDateKey(dateKey) }),
    node('span', { text: count ? `${new Intl.NumberFormat('fa-IR').format(count)} رویداد` : 'بدون رویداد' }),
  );
}

function renderWeek(container, events, ctx, timeZone, cues, route, range, todayKey) {
  const keys = weekKeys(range.anchor);
  const byDate = new Map(groupByDate(events));
  const grid = node('div', { className: 'f3-schedule-week-grid', attrs: { 'aria-label': 'نمای هفتگی برنامه' } });
  keys.forEach((dateKey) => {
    const items = byDate.get(dateKey) || [];
    const column = node('section', { className: `f3-schedule-day${dateKey === todayKey ? ' f3-schedule-day--today' : ''}` }, dayHeader(dateKey, todayKey, items.length));
    const body = node('div', { className: 'f3-schedule-day__events' });
    if (!items.length) body.append(node('span', { className: 'f3-schedule-day__empty', text: '—' }));
    items.forEach((event) => body.append(eventButton(event, ctx, timeZone, cues.get(event.key) || '', true)));
    column.append(body);
    grid.append(column);
  });

  const requestedDay = isDateKey(route.query.day) && keys.includes(route.query.day) ? route.query.day : '';
  const selectedDay = requestedDay || (keys.includes(todayKey) ? todayKey : keys[0]);
  const strip = node('div', { className: 'f3-schedule-day-strip', attrs: { role: 'tablist', 'aria-label': 'روزهای هفته' } });
  keys.forEach((dateKey) => {
    const count = (byDate.get(dateKey) || []).length;
    strip.append(node('button', {
      className: `f3-schedule-day-tab${dateKey === selectedDay ? ' f3-schedule-day-tab--active' : ''}`,
      attrs: { type: 'button', role: 'tab', 'aria-selected': String(dateKey === selectedDay) },
      on: { click: () => navigateSchedule(ctx, 'week', { ...route.query, day: dateKey }) },
    },
    node('strong', { text: formatDateKey(dateKey, { month: 'short' }) }),
    count ? node('span', { text: new Intl.NumberFormat('fa-IR').format(count) }) : null));
  });
  const mobile = node('div', { className: 'f3-schedule-week-mobile' }, strip);
  const mobileAgenda = node('div', { className: 'f3-schedule-week-mobile__agenda' });
  const mobileEvents = byDate.get(selectedDay) || [];
  if (mobileEvents.length) renderTimeline(mobileAgenda, mobileEvents, ctx, timeZone, cues, route);
  else renderNoEvents(mobileAgenda, route, 'week');
  mobile.append(mobileAgenda);

  container.append(grid, mobile);
}

function renderUpcoming(container, events, ctx, timeZone, cues, route, viewState) {
  if (!events.length) return renderNoEvents(container, route, 'upcoming');
  const visible = events.slice(0, viewState.upcomingLimit);
  const groups = groupByDate(visible);
  const stack = node('div', { className: 'f3-schedule-upcoming' });
  groups.forEach(([dateKey, items]) => {
    const section = node('section', { className: 'f3-schedule-upcoming__group' },
      node('header', { className: 'f3-schedule-upcoming__date' },
        node('h2', { text: formatDateKey(dateKey, { year: true }) }),
        node('span', { text: `${new Intl.NumberFormat('fa-IR').format(items.length)} رویداد` }),
      ),
    );
    const list = node('div', { className: 'f3-schedule-upcoming__events' });
    items.forEach((event) => list.append(eventButton(event, ctx, timeZone, cues.get(event.key) || '')));
    section.append(list);
    stack.append(section);
  });
  container.append(stack);
  if (events.length > visible.length) {
    container.append(node('div', { className: 'f3-schedule-more' }, button('نمایش موارد بیشتر', {
      variant: 'secondary',
      onClick: () => {
        viewState.upcomingLimit += UPCOMING_PAGE_SIZE;
        viewState.renderBody();
      },
    })));
  }
}

function pageHeading(workspaceName, mode) {
  const subtitles = {
    today: 'رویدادهای یک روز با زمان‌بندی قابل اسکن',
    week: 'نمای هفتگی برای دیدن تراکم و توزیع روزها',
    upcoming: 'رویدادهای آینده به‌ترتیب تاریخ',
  };
  return node('header', { className: 'f3-schedule-heading' },
    node('div', {},
      node('span', { className: 'f3-schedule-heading__eyebrow', text: 'برنامه آموزشی' }),
      node('h1', { className: 'f3-schedule-heading__title', text: 'برنامه' }),
      node('p', { className: 'f3-schedule-heading__subtitle', text: subtitles[mode] || subtitles.today }),
    ),
    node('div', { className: 'f3-schedule-heading__workspace' },
      node('span', { text: 'فضای فعال' }),
      node('strong', { text: workspaceName }),
    ),
  );
}

function scheduleError(root, error, retry) {
  const status = errorStatus(error);
  if (status === 401) {
    renderState(root, 'نشست شما پایان یافته است.', 'برای دیدن برنامه دوباره وارد حساب خود شوید.', { kind: 'error' });
    return;
  }
  if (status === 403) {
    renderState(root, 'برنامه برای این حساب در دسترس نیست.', 'اجازه مشاهده برنامه این فضای آموزشی را ندارید.', { kind: 'error' });
    return;
  }
  renderState(root, 'برنامه دریافت نشد.', 'ارتباط با برنامه آموزشی برقرار نشد. دوباره تلاش کنید.', {
    kind: 'error',
    actionLabel: 'تلاش دوباره',
    onAction: retry,
  });
}

async function mountSchedule(ctx) {
  const root = ctx?.root;
  if (!(root instanceof Element)) return;
  ensureStyleLink();
  root.classList.add('f3-schedule-root');
  root.setAttribute('dir', 'rtl');
  renderLoading(root);

  const workspace = workspaceContract(ctx);
  if (!workspace.id) {
    renderState(root, 'فضای آموزشی فعال مشخص نیست.', 'ابتدا یک فضای آموزشی فعال انتخاب کنید.', { kind: 'error' });
    return;
  }
  if (!workspace.timeZone) {
    renderState(root, 'منطقه زمانی فضای آموزشی در دسترس نیست.', 'تا زمانی که منطقه زمانی معتبر از سرور دریافت نشود، برنامه زمانی نمایش داده نمی‌شود.', { kind: 'error' });
    return;
  }

  const route = routeState(ctx);
  const todayKey = workspaceTodayKey(workspace.timeZone);
  const requestedAnchor = isDateKey(route.query.date) ? route.query.date : todayKey;
  const range = buildRange(route.mode, requestedAnchor, todayKey);
  if (!todayKey || !range) {
    renderState(root, 'تاریخ برنامه قابل تعیین نیست.', 'منطقه زمانی یا تاریخ انتخاب‌شده معتبر نیست.', { kind: 'error' });
    return;
  }

  let rawRows;
  try {
    rawRows = await readSchedule(ctx, workspace.id, range);
  } catch (error) {
    if (ctx?.signal?.aborted) return;
    scheduleError(root, error, () => mountSchedule(ctx));
    return;
  }
  if (ctx?.signal?.aborted) return;

  const normalized = normalizeRows(rawRows, workspace.timeZone);
  const viewState = { upcomingLimit: UPCOMING_PAGE_SIZE, renderBody: null };
  const shell = node('div', { className: 'f3-schedule-shell' });
  const heading = pageHeading(workspace.name, route.mode);
  const toolbar = node('section', { className: 'f3-schedule-toolbar', attrs: { 'aria-label': 'کنترل‌های برنامه' } },
    buildModeSwitch(ctx, route),
    buildDateNavigation(ctx, route, range, todayKey),
  );
  const filters = buildFilters(ctx, route, normalized.rows);
  const partial = partialNotice(normalized.skipped);
  const body = node('section', { className: 'f3-schedule-content', attrs: { 'aria-live': 'polite' } });
  shell.append(heading, toolbar);
  if (filters) shell.append(filters);
  if (partial) shell.append(partial);
  shell.append(body);
  root.replaceChildren(shell);

  viewState.renderBody = () => {
    body.replaceChildren();
    const visible = filterRows(normalized.rows, { course: route.query.course, type: route.query.type });
    const cues = temporalCues(visible, range, todayKey);
    if (route.mode === 'week') renderWeek(body, visible, ctx, workspace.timeZone, cues, route, range, todayKey);
    else if (route.mode === 'upcoming') renderUpcoming(body, visible, ctx, workspace.timeZone, cues, route, viewState);
    else renderTimeline(body, visible, ctx, workspace.timeZone, cues, route);
  };
  viewState.renderBody();
}

export const moduleDefinition = Object.freeze({
  id: 'schedule',
  routes: Object.freeze(['#/schedule/today', '#/schedule/week', '#/schedule/upcoming']),
  navItems: Object.freeze([{ id: 'schedule', label: 'برنامه', route: '#/schedule/today', icon: 'calendar' }]),
  styles: Object.freeze([SCHEDULE_STYLE_HREF]),
  mount: mountSchedule,
  unmount(ctx) {
    if (ctx?.root instanceof Element) {
      ctx.root.classList.remove('f3-schedule-root');
      ctx.root.replaceChildren();
    }
    document.querySelectorAll('.f3-schedule-detail[open]').forEach((dialog) => {
      if (typeof dialog.close === 'function') dialog.close();
      else dialog.remove();
    });
  },
});

export default moduleDefinition;
