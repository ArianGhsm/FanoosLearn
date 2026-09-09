(function (global) {
  'use strict';

  const DESIGN_LOCK_ID = 'FANOOS-UX-2026.09-R1';

  const ROUTES = Object.freeze({
    courses: 'courses',
    schedule: 'schedule',
    resources: 'resources',
    assessments: 'assessments',
    grades: 'grades',
    announcements: 'announcements',
    account: 'account',
  });

  const STATE_MATRIX = Object.freeze({
    normal_populated: {
      condition: 'workspace + at least one healthy populated section',
      behavior: 'prioritized next-up, today timeline, attention/activity, then shortcuts',
    },
    no_schedule: {
      condition: 'schedule read succeeds with no events for today / no upcoming event in supplied window',
      behavior: 'honest empty hero/timeline with route to schedule',
    },
    no_announcement: {
      condition: 'announcement read succeeds with zero rows',
      behavior: 'compact neutral empty state; other sections remain unchanged',
    },
    no_workspace: {
      condition: 'no canonical active workspace in render context',
      behavior: 'orientation state with workspace/account action; no invented academic data',
    },
    partial_api_failure: {
      condition: 'one or more independent section reads fail while the session remains usable',
      behavior: 'healthy sections render; failed sections degrade locally; safe retry remains available',
    },
    session_permission_issue: {
      condition: 'render phase is session-expired or permission-denied',
      behavior: 'blocking human-readable state; no raw API/provider detail',
    },
    first_use_empty: {
      condition: 'workspace exists and all supplied reads succeed but all row sets are empty',
      behavior: 'calm first-use explanation plus useful navigation; no fake recommendations',
    },
  });

  const DATA_DEPENDENCIES = Object.freeze({
    header: ['/api/v1/account', '/api/v1/workspaces'],
    hero: ['/api/v1/workspaces/{workspaceId}/schedule'],
    todayTimeline: ['/api/v1/workspaces/{workspaceId}/schedule'],
    attention: ['/api/v1/workspaces/{workspaceId}/assessments'],
    announcement: ['/api/v1/workspaces/{workspaceId}/announcements'],
    learning: ['/api/v1/workspaces/{workspaceId}/resources'],
    grade: ['/api/v1/workspaces/{workspaceId}/grades/me'],
  });

  const RESOURCE_TYPE_LABELS = Object.freeze({
    booklet: 'جزوه',
    lecture_note: 'جزوه',
    note: 'یادداشت',
    summary: 'خلاصه',
    question_bank: 'بانک سؤال',
    past_exam: 'آزمون سال‌های قبل',
    past_questions: 'سؤالات سال‌های قبل',
    flashcard: 'فلش‌کارت',
    audio: 'فایل صوتی',
    video: 'ویدئو',
  });

  const ASSESSMENT_TYPE_LABELS = Object.freeze({
    practice: 'تمرین',
    mock_exam: 'آزمون آزمایشی',
    past_exam: 'آزمون سال‌های قبل',
    quiz: 'کوئیز',
    exam: 'آزمون',
  });

  const DEADLINE_FIELDS = Object.freeze([
    'deadline_at',
    'due_at',
    'closes_at',
    'available_until',
    'ends_at',
  ]);

  function text(value, maxLength) {
    if (value === null || value === undefined) return '';
    const normalized = String(value)
      .normalize('NFKC')
      .replace(/[\u202A-\u202E\u2066-\u2069]/g, '')
      .replace(/ي/g, 'ی')
      .replace(/ك/g, 'ک')
      .replace(/\s+/g, ' ')
      .trim();
    if (!maxLength || normalized.length <= maxLength) return normalized;
    return `${Array.from(normalized).slice(0, Math.max(1, maxLength - 1)).join('')}…`;
  }

  function el(tag, options, ...children) {
    const node = document.createElement(tag);
    const opts = options || {};
    if (opts.className) node.className = opts.className;
    if (opts.text !== undefined) node.textContent = text(opts.text);
    if (opts.attrs) {
      Object.entries(opts.attrs).forEach(([name, value]) => {
        if (value !== null && value !== undefined && value !== false) {
          node.setAttribute(name, value === true ? '' : String(value));
        }
      });
    }
    if (opts.on) {
      Object.entries(opts.on).forEach(([eventName, handler]) => {
        if (typeof handler === 'function') node.addEventListener(eventName, handler);
      });
    }
    children.flat().forEach((child) => {
      if (child === null || child === undefined || child === false) return;
      node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    });
    return node;
  }

  function clear(container) {
    container.replaceChildren();
  }

  function actionButton(label, onClick, variant, attributes) {
    const attrs = Object.assign({ type: 'button' }, attributes || {});
    return el('button', {
      className: `f3-home-button f3-home-button--${variant || 'secondary'}`,
      text: label,
      attrs,
      on: { click: onClick },
    });
  }

  function navigate(model, route, query) {
    const handler = model && model.handlers && model.handlers.navigate;
    if (typeof handler === 'function') handler(route, query || {});
  }

  function retry(model) {
    const handler = model && model.handlers && model.handlers.retryHome;
    if (typeof handler === 'function') handler();
  }

  function resultRows(part) {
    if (!part || part.ok !== true) return [];
    if (Array.isArray(part.data)) return part.data;
    if (part.data && Array.isArray(part.data.items)) return part.data.items;
    return [];
  }

  function partFailed(part) {
    return !!part && part.ok === false;
  }

  function validTimeZone(value) {
    const candidate = text(value, 120);
    if (!candidate) return null;
    try {
      new Intl.DateTimeFormat('en', { timeZone: candidate }).format(0);
      return candidate;
    } catch (_error) {
      return null;
    }
  }

  function timeZone(context) {
    if (!context) return null;
    return validTimeZone(
      context.timezoneName ||
      context.timezone_name ||
      (context.workspace && (context.workspace.timezoneName || context.workspace.timezone_name))
    );
  }

  function parseInstant(value) {
    if (value instanceof Date) {
      const epoch = value.getTime();
      return Number.isFinite(epoch) ? epoch : null;
    }
    if (typeof value === 'number') return Number.isFinite(value) ? value : null;
    const raw = text(value, 80);
    if (!raw) return null;

    let canonical = raw;
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw)) {
      canonical = `${raw.replace(' ', 'T')}Z`;
    } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/.test(raw)) {
      return null;
    }

    const epoch = Date.parse(canonical);
    return Number.isFinite(epoch) ? epoch : null;
  }

  function nowEpoch(context) {
    const supplied = Number(context && context.nowEpochMs);
    return Number.isFinite(supplied) ? supplied : Date.now();
  }

  function callFormatter(model, key, value) {
    const formatter = model && model.formatters && model.formatters[key];
    if (typeof formatter !== 'function') return '';
    try {
      return text(formatter(value, model.context || {}), 140);
    } catch (_error) {
      return '';
    }
  }

  function formatInstant(model, value, mode) {
    const formatterKey = mode === 'time' ? 'time' : mode === 'dateTime' ? 'dateTime' : 'date';
    const provided = callFormatter(model, formatterKey, value);
    if (provided) return provided;

    const epoch = parseInstant(value);
    const zone = timeZone(model && model.context);
    if (epoch === null || !zone) return '';

    const options = mode === 'time'
      ? { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: zone }
      : mode === 'dateTime'
        ? { weekday: 'short', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: zone }
        : { weekday: 'short', day: 'numeric', month: 'long', timeZone: zone };
    try {
      return new Intl.DateTimeFormat('fa-IR', options).format(new Date(epoch));
    } catch (_error) {
      return '';
    }
  }

  function formatNumber(model, value) {
    const provided = callFormatter(model, 'number', value);
    if (provided) return provided;
    const numeric = Number(value);
    if (!Number.isFinite(numeric)) return '';
    try {
      return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(numeric);
    } catch (_error) {
      return String(numeric);
    }
  }

  function localDateKey(epoch, zone) {
    if (!Number.isFinite(epoch) || !zone) return '';
    try {
      const parts = new Intl.DateTimeFormat('en-CA', {
        year: 'numeric', month: '2-digit', day: '2-digit', timeZone: zone,
      }).formatToParts(new Date(epoch));
      const values = {};
      parts.forEach((part) => { if (part.type !== 'literal') values[part.type] = part.value; });
      return values.year && values.month && values.day ? `${values.year}-${values.month}-${values.day}` : '';
    } catch (_error) {
      return '';
    }
  }

  function todayKey(context) {
    const supplied = text(context && context.todayKey, 20);
    if (/^\d{4}-\d{2}-\d{2}$/.test(supplied)) return supplied;
    const zone = timeZone(context);
    return localDateKey(nowEpoch(context), zone);
  }

  function eventDateKey(row, context) {
    const epoch = parseInstant(row && row.starts_at);
    return localDateKey(epoch, timeZone(context));
  }

  function sortChronological(rows) {
    return rows.slice().sort((a, b) => {
      const aEpoch = parseInstant(a && a.starts_at);
      const bEpoch = parseInstant(b && b.starts_at);
      if (aEpoch === null && bEpoch === null) return 0;
      if (aEpoch === null) return 1;
      if (bEpoch === null) return -1;
      return aEpoch - bEpoch;
    });
  }

  function sortNewest(rows, field) {
    const withEpoch = rows.map((row, index) => ({ row, index, epoch: parseInstant(row && row[field]) }));
    const hasComparable = withEpoch.some((entry) => entry.epoch !== null);
    if (!hasComparable) return { rows: rows.slice(), comparable: false };
    withEpoch.sort((a, b) => {
      if (a.epoch === null && b.epoch === null) return a.index - b.index;
      if (a.epoch === null) return 1;
      if (b.epoch === null) return -1;
      return b.epoch - a.epoch;
    });
    return { rows: withEpoch.map((entry) => entry.row), comparable: true };
  }

  function nearestSchedule(rows, context) {
    const ordered = sortChronological(rows);
    const now = nowEpoch(context);
    let current = null;
    let next = null;

    ordered.forEach((row) => {
      const start = parseInstant(row && row.starts_at);
      const end = parseInstant(row && row.ends_at);
      if (!current && start !== null && end !== null && start <= now && now < end) current = row;
      if (!next && start !== null && start >= now) next = row;
    });

    if (current) return { row: current, state: 'current' };
    if (next) return { row: next, state: 'next' };
    if (ordered.length) return { row: ordered[0], state: 'window' };
    return { row: null, state: 'empty' };
  }

  function eventState(row, nearest, context) {
    const now = nowEpoch(context);
    const start = parseInstant(row && row.starts_at);
    const end = parseInstant(row && row.ends_at);
    if (start !== null && end !== null && start <= now && now < end) return 'current';
    if (nearest && nearest.row === row && nearest.state === 'next') return 'next';
    return 'normal';
  }

  function sectionHeader(id, eyebrow, title, actionLabel, action) {
    const heading = el('div', { className: 'f3-home-section__heading' },
      el('div', {},
        eyebrow ? el('span', { className: 'f3-home-eyebrow', text: eyebrow }) : null,
        el('h2', { id, text: title })
      )
    );
    if (actionLabel && action) {
      heading.append(actionButton(actionLabel, action, 'quiet', { 'aria-label': actionLabel }));
    }
    return heading;
  }

  function inlineState(message, model, options) {
    const opts = options || {};
    const block = el('div', {
      className: `f3-home-inline-state${opts.kind ? ` f3-home-inline-state--${opts.kind}` : ''}`,
      attrs: { role: opts.kind === 'error' ? 'status' : undefined },
    }, el('p', { text: message }));
    if (opts.retry && model && model.handlers && typeof model.handlers.retryHome === 'function') {
      block.append(actionButton('تلاش دوباره', () => retry(model), 'quiet'));
    }
    return block;
  }

  function renderHeader(root, model) {
    const context = model.context || {};
    const workspaceName = text(
      context.workspaceName ||
      (context.workspace && (context.workspace.name || context.workspace.label)),
      180
    ) || 'فضای آموزشی';
    const dateLabel = text(context.dateLabel, 160) || formatInstant(model, new Date(nowEpoch(context)), 'date');
    const greeting = text(context.greeting, 180);

    const header = el('header', { className: 'f3-home-header' },
      el('div', { className: 'f3-home-header__copy' },
        el('span', { className: 'f3-home-context', text: workspaceName }),
        el('h1', { text: 'امروز' }),
        greeting ? el('p', { className: 'f3-home-header__greeting', text: greeting }) : null
      ),
      dateLabel ? el('div', { className: 'f3-home-date', text: dateLabel }) : null
    );
    root.append(header);
  }

  function renderLoading(root) {
    root.setAttribute('aria-busy', 'true');
    root.append(
      el('div', { className: 'f3-home-sr-status', text: 'اطلاعات امروز در حال دریافت است.', attrs: { role: 'status' } }),
      el('div', { className: 'f3-home-loading' },
        el('div', { className: 'f3-home-skeleton f3-home-skeleton--header', attrs: { 'aria-hidden': 'true' } }),
        el('div', { className: 'f3-home-skeleton f3-home-skeleton--hero', attrs: { 'aria-hidden': 'true' } }),
        el('div', { className: 'f3-home-skeleton f3-home-skeleton--line', attrs: { 'aria-hidden': 'true' } }),
        el('div', { className: 'f3-home-skeleton f3-home-skeleton--line is-short', attrs: { 'aria-hidden': 'true' } })
      )
    );
  }

  function renderBlocking(root, model, kind) {
    const copy = kind === 'session-expired'
      ? {
          title: 'نشست شما پایان یافته است',
          body: 'برای دیدن اطلاعات فضای آموزشی، دوباره وارد حساب شوید.',
          action: 'ورود دوباره',
        }
      : kind === 'permission-denied'
        ? {
            title: 'این بخش برای حساب شما در دسترس نیست',
            body: 'دسترسی‌ها از فضای آموزشی فعال بررسی می‌شوند.',
            action: 'مشاهده حساب',
          }
        : {
            title: 'اطلاعات خانه دریافت نشد',
            body: 'ارتباط با اطلاعات امروز برقرار نشد. می‌توانید دوباره تلاش کنید.',
            action: 'تلاش دوباره',
          };

    const action = kind === 'session-expired'
      ? () => {
          if (model.handlers && typeof model.handlers.reauthenticate === 'function') model.handlers.reauthenticate();
        }
      : kind === 'permission-denied'
        ? () => navigate(model, ROUTES.account)
        : () => retry(model);

    root.append(el('section', { className: 'f3-home-blocking', attrs: { 'aria-labelledby': 'f3-home-blocking-title' } },
      el('span', { className: 'f3-home-eyebrow', text: 'خانه' }),
      el('h1', { id: 'f3-home-blocking-title', text: copy.title }),
      el('p', { text: copy.body }),
      actionButton(copy.action, action, 'primary')
    ));
  }

  function renderNoWorkspace(root, model) {
    root.setAttribute('data-home-state', 'no-workspace');
    const context = model.context || {};
    const dateLabel = text(context.dateLabel, 160) || formatInstant(model, new Date(nowEpoch(context)), 'date');
    root.append(el('header', { className: 'f3-home-header' },
      el('div', { className: 'f3-home-header__copy' },
        el('span', { className: 'f3-home-context', text: 'فانوس' }),
        el('h1', { text: 'خانه' })
      ),
      dateLabel ? el('div', { className: 'f3-home-date', text: dateLabel }) : null
    ));

    const chooseWorkspace = () => {
      if (model.handlers && typeof model.handlers.openWorkspacePicker === 'function') {
        model.handlers.openWorkspacePicker();
      } else {
        navigate(model, ROUTES.account);
      }
    };

    root.append(el('section', { className: 'f3-home-empty-focus', attrs: { 'aria-labelledby': 'f3-home-no-workspace-title' } },
      el('div', {},
        el('span', { className: 'f3-home-eyebrow', text: 'فضای آموزشی' }),
        el('h2', { id: 'f3-home-no-workspace-title', text: 'فضای آموزشی فعالی انتخاب نشده است' }),
        el('p', { text: 'برای دیدن برنامه، درس‌ها و منابع، یک فضای آموزشی فعال انتخاب کنید.' })
      ),
      el('div', { className: 'f3-home-actions' },
        actionButton('انتخاب فضای آموزشی', chooseWorkspace, 'primary'),
        actionButton('مشاهده حساب', () => navigate(model, ROUTES.account), 'secondary')
      )
    ));
  }

  function renderHero(root, model, scheduleRows) {
    const part = model.parts && model.parts.schedule;
    const section = el('section', {
      className: 'f3-home-hero',
      attrs: { 'aria-labelledby': 'f3-home-next-title', 'data-home-slot': 'next-up' },
    });

    if (partFailed(part)) {
      section.append(
        el('span', { className: 'f3-home-eyebrow', text: 'برنامه بعدی' }),
        el('h2', { id: 'f3-home-next-title', text: 'برنامه فعلاً در دسترس نیست' }),
        el('p', { className: 'f3-home-hero__support', text: 'بقیه اطلاعات خانه همچنان قابل استفاده است.' }),
        actionButton('تلاش دوباره', () => retry(model), 'secondary')
      );
      root.append(section);
      return null;
    }

    const nearest = nearestSchedule(scheduleRows, model.context || {});
    if (!nearest.row) {
      section.classList.add('is-empty');
      section.append(
        el('span', { className: 'f3-home-eyebrow', text: 'برنامه بعدی' }),
        el('h2', { id: 'f3-home-next-title', text: 'رویداد آموزشی پیشِ رو ثبت نشده است' }),
        el('p', { className: 'f3-home-hero__support', text: 'برای دیدن بازه‌های دیگر، برنامه را باز کنید.' }),
        actionButton('مشاهده برنامه', () => navigate(model, ROUTES.schedule), 'primary')
      );
      root.append(section);
      return nearest;
    }

    const row = nearest.row;
    const title = text(row.title || row.course_title, 180) || 'رویداد آموزشی';
    const course = text(row.course_title, 180);
    const location = text(row.location_text, 180);
    const time = formatInstant(model, row.starts_at, 'time');
    const date = formatInstant(model, row.starts_at, 'date');
    const label = nearest.state === 'current' ? 'در حال برگزاری' : nearest.state === 'next' ? 'بعدی' : 'برنامه پیش رو';

    const meta = el('div', { className: 'f3-home-hero__meta' });
    if (course && course !== title) meta.append(el('span', { text: course }));
    if (location) meta.append(el('span', { text: location }));
    if (date) meta.append(el('span', { text: date }));

    const actions = el('div', { className: 'f3-home-actions' },
      actionButton('مشاهده برنامه', () => navigate(model, ROUTES.schedule), 'primary')
    );
    const courseCode = text(row.course_code, 80);
    if (courseCode) {
      actions.append(actionButton('باز کردن درس', () => navigate(model, ROUTES.courses, { course: courseCode }), 'quiet'));
    }

    section.append(
      el('div', { className: 'f3-home-hero__top' },
        el('span', { className: `f3-home-status f3-home-status--${nearest.state}`, text: label }),
        time ? el('time', { className: 'f3-home-hero__time', text: time }) : null
      ),
      el('h2', { id: 'f3-home-next-title', text: title }),
      meta,
      actions
    );
    root.append(section);
    return nearest;
  }

  function renderTodayTimeline(model, scheduleRows, nearest) {
    const part = model.parts && model.parts.schedule;
    const section = el('section', {
      className: 'f3-home-section f3-home-timeline-section',
      attrs: { 'aria-labelledby': 'f3-home-today-title', 'data-home-slot': 'today-timeline' },
    });
    section.append(sectionHeader(
      'f3-home-today-title',
      'امروز',
      'برنامه امروز',
      'همه برنامه',
      () => navigate(model, ROUTES.schedule)
    ));

    if (partFailed(part)) {
      section.append(inlineState('برنامه امروز دریافت نشد.', model, { kind: 'error', retry: true }));
      return section;
    }

    const key = todayKey(model.context || {});
    const todayRows = key
      ? sortChronological(scheduleRows.filter((row) => eventDateKey(row, model.context || {}) === key))
      : [];

    if (!todayRows.length) {
      section.append(inlineState('برای امروز برنامه‌ای ثبت نشده است.', model));
      return section;
    }

    const list = el('ol', { className: 'f3-home-timeline' });
    todayRows.slice(0, 6).forEach((row) => {
      const state = eventState(row, nearest, model.context || {});
      const title = text(row.title || row.course_title, 180) || 'رویداد آموزشی';
      const course = text(row.course_title, 160);
      const location = text(row.location_text, 160);
      const start = formatInstant(model, row.starts_at, 'time');
      const end = formatInstant(model, row.ends_at, 'time');
      const statusText = state === 'current' ? 'اکنون' : state === 'next' ? 'بعدی' : '';

      list.append(el('li', { className: `f3-home-timeline__item${state !== 'normal' ? ` is-${state}` : ''}` },
        el('div', { className: 'f3-home-timeline__time' },
          el('span', { text: start || '—' }),
          end ? el('small', { text: `تا ${end}` }) : null
        ),
        el('div', { className: 'f3-home-timeline__body' },
          el('div', { className: 'f3-home-timeline__title' },
            el('strong', { text: title }),
            statusText ? el('span', { className: 'f3-home-status f3-home-status--compact', text: statusText }) : null
          ),
          course && course !== title ? el('span', { className: 'f3-home-meta', text: course }) : null,
          location ? el('span', { className: 'f3-home-meta', text: location }) : null
        )
      ));
    });
    section.append(list);
    if (todayRows.length > 6) {
      section.append(actionButton(`مشاهده ${formatNumber(model, todayRows.length - 6)} مورد دیگر`, () => navigate(model, ROUTES.schedule), 'quiet'));
    }
    return section;
  }

  function assessmentDeadline(row) {
    for (const field of DEADLINE_FIELDS) {
      const epoch = parseInstant(row && row[field]);
      if (epoch !== null) return { field, value: row[field], epoch };
    }
    return null;
  }

  function chooseAssessment(rows, context) {
    if (!rows.length) return { row: null, deadline: null };
    const now = nowEpoch(context || {});
    const withDeadline = rows
      .map((row) => ({ row, deadline: assessmentDeadline(row) }))
      .filter((entry) => entry.deadline && entry.deadline.epoch >= now)
      .sort((a, b) => a.deadline.epoch - b.deadline.epoch);
    if (withDeadline.length) return withDeadline[0];
    return { row: rows[0], deadline: null };
  }

  function assessmentType(row) {
    const key = text(row && (row.assessment_kind || row.type_key), 80).toLowerCase();
    return ASSESSMENT_TYPE_LABELS[key] || 'آزمون';
  }

  function resourceType(row) {
    const key = text(row && (row.type_key || row.type), 80).toLowerCase();
    return RESOURCE_TYPE_LABELS[key] || 'منبع آموزشی';
  }

  function renderAttention(model) {
    const part = model.parts && model.parts.assessments;
    const rows = resultRows(part);
    const section = el('section', {
      className: 'f3-home-rail-section f3-home-attention',
      attrs: { 'aria-labelledby': 'f3-home-attention-title', 'data-home-slot': 'assessment-attention' },
    });
    section.append(sectionHeader(
      'f3-home-attention-title',
      'نیاز به توجه',
      'آزمون‌ها',
      'مشاهده همه',
      () => navigate(model, ROUTES.assessments)
    ));

    if (partFailed(part)) {
      section.append(inlineState('اطلاعات آزمون‌ها فعلاً در دسترس نیست.', model, { kind: 'error', retry: true }));
      return section;
    }
    if (!rows.length) {
      section.append(inlineState('آزمونی برای نمایش نیست.', model));
      return section;
    }

    const selected = chooseAssessment(rows, model.context || {});
    const row = selected.row;
    const meta = el('div', { className: 'f3-home-stack-meta' },
      el('span', { text: assessmentType(row) })
    );
    if (row && row.max_attempts !== null && row.max_attempts !== undefined) {
      const attempts = formatNumber(model, row.max_attempts);
      if (attempts) meta.append(el('span', { text: `${attempts} تلاش مجاز` }));
    }
    if (row && row.requires_entitlement === true) {
      meta.append(el('span', { className: 'f3-home-access-note', text: 'نیازمند دسترسی فعال' }));
    }

    section.append(el('article', { className: 'f3-home-attention__item' },
      el('span', { className: 'f3-home-mini-label', text: selected.deadline ? 'موعد نزدیک' : 'در دسترس' }),
      el('h3', { text: text(row && row.title, 180) || 'آزمون' }),
      selected.deadline
        ? el('p', { className: 'f3-home-deadline', text: formatInstant(model, selected.deadline.value, 'dateTime') || 'زمان پایان اعلام شده است.' })
        : el('p', { className: 'f3-home-meta', text: 'برای جزئیات و زمان‌بندی، بخش آزمون‌ها را باز کنید.' }),
      meta
    ));
    return section;
  }

  function renderAnnouncement(model) {
    const part = model.parts && model.parts.announcements;
    const rows = resultRows(part);
    const section = el('section', {
      className: 'f3-home-rail-section',
      attrs: { 'aria-labelledby': 'f3-home-announcement-title', 'data-home-slot': 'announcement' },
    });
    section.append(sectionHeader(
      'f3-home-announcement-title',
      'اطلاع‌رسانی',
      'آخرین اطلاعیه',
      'همه اطلاعیه‌ها',
      () => navigate(model, ROUTES.announcements)
    ));

    if (partFailed(part)) {
      section.append(inlineState('اطلاعیه‌ها فعلاً در دسترس نیستند.', model, { kind: 'error', retry: true }));
      return section;
    }
    if (!rows.length) {
      section.append(inlineState('اطلاعیه تازه‌ای برای نمایش نیست.', model));
      return section;
    }

    const sorted = sortNewest(rows, 'published_at').rows;
    const row = sorted[0];
    const unread = String(row.status || '').toLowerCase() !== 'read' && !row.read_at;
    section.append(el('article', { className: 'f3-home-activity-item' },
      el('div', { className: 'f3-home-activity-item__top' },
        unread ? el('span', { className: 'f3-home-status f3-home-status--unread', text: 'خوانده‌نشده' }) : null,
        row.published_at ? el('time', { className: 'f3-home-meta', text: formatInstant(model, row.published_at, 'date') }) : null
      ),
      el('h3', { text: text(row.title, 180) || 'اطلاعیه' }),
      row.body ? el('p', { text: text(row.body, 150) }) : null
    ));
    return section;
  }

  function renderLearning(model) {
    const part = model.parts && model.parts.resources;
    const rows = resultRows(part);
    const sorted = sortNewest(rows, 'updated_at');
    const section = el('section', {
      className: 'f3-home-rail-section',
      attrs: { 'aria-labelledby': 'f3-home-learning-title', 'data-home-slot': 'learning' },
    });
    section.append(sectionHeader(
      'f3-home-learning-title',
      'یادگیری',
      sorted.comparable ? 'منابع تازه' : 'منابع در دسترس',
      'همه منابع',
      () => navigate(model, ROUTES.resources)
    ));

    if (partFailed(part)) {
      section.append(inlineState('منابع آموزشی فعلاً در دسترس نیستند.', model, { kind: 'error', retry: true }));
      return section;
    }
    if (!rows.length) {
      section.append(inlineState('برای این فضا هنوز منبعی منتشر نشده است.', model));
      return section;
    }

    const list = el('div', { className: 'f3-home-compact-list' });
    sorted.rows.slice(0, 2).forEach((row) => {
      const details = [resourceType(row), text(row.topic, 90)].filter(Boolean).join(' · ');
      list.append(el('article', { className: 'f3-home-compact-item' },
        el('h3', { text: text(row.title, 180) || 'منبع آموزشی' }),
        details ? el('p', { className: 'f3-home-meta', text: details }) : null,
        sorted.comparable && row.updated_at
          ? el('time', { className: 'f3-home-meta', text: formatInstant(model, row.updated_at, 'date') })
          : null
      ));
    });
    section.append(list);
    return section;
  }

  function renderGrade(model) {
    const part = model.parts && model.parts.grades;
    const rows = resultRows(part);
    const sorted = sortNewest(rows, 'updated_at');
    const section = el('section', {
      className: 'f3-home-rail-section',
      attrs: { 'aria-labelledby': 'f3-home-grade-title', 'data-home-slot': 'grade-update' },
    });
    section.append(sectionHeader(
      'f3-home-grade-title',
      'نمرات',
      sorted.comparable ? 'نمره تازه منتشرشده' : 'نمرات منتشرشده',
      'مشاهده نمرات',
      () => navigate(model, ROUTES.grades)
    ));

    if (partFailed(part)) {
      section.append(inlineState('نمرات منتشرشده فعلاً در دسترس نیستند.', model, { kind: 'error', retry: true }));
      return section;
    }
    if (!rows.length) {
      section.append(inlineState('هنوز نمره‌ای برای شما منتشر نشده است.', model));
      return section;
    }

    const row = sorted.rows[0];
    const score = formatNumber(model, row.score);
    const maxScore = formatNumber(model, row.max_score);
    section.append(el('article', { className: 'f3-home-grade' },
      el('div', { className: 'f3-home-grade__copy' },
        el('h3', { text: text(row.item_title || row.gradebook_title, 180) || 'نمره' }),
        row.course_title ? el('p', { className: 'f3-home-meta', text: text(row.course_title, 160) }) : null,
        sorted.comparable && row.updated_at
          ? el('time', { className: 'f3-home-meta', text: formatInstant(model, row.updated_at, 'date') })
          : null
      ),
      score
        ? el('div', { className: 'f3-home-grade__score', attrs: { 'aria-label': maxScore ? `نمره ${score} از ${maxScore}` : `نمره ${score}` } },
            el('strong', { text: score }),
            maxScore ? el('span', { text: `از ${maxScore}` }) : null
          )
        : null
    ));
    return section;
  }

  function renderQuickActions(model) {
    const section = el('section', {
      className: 'f3-home-section f3-home-quick-section',
      attrs: { 'aria-labelledby': 'f3-home-quick-title', 'data-home-slot': 'quick-actions' },
    });
    section.append(sectionHeader('f3-home-quick-title', 'مسیرهای پرتکرار', 'دسترسی سریع'));
    const actions = [
      [ROUTES.courses, 'درس‌ها', 'جلسات و محتوای هر درس'],
      [ROUTES.schedule, 'برنامه', 'کلاس‌ها و رویدادها'],
      [ROUTES.resources, 'منابع', 'جزوه، خلاصه و بانک سؤال'],
      [ROUTES.assessments, 'آزمون‌ها', 'تمرین‌ها و آزمون‌های منتشرشده'],
      [ROUTES.grades, 'نمرات', 'نتایج منتشرشده'],
      [ROUTES.announcements, 'اطلاعیه‌ها', 'پیام‌های رسمی فضای آموزشی'],
    ];
    const grid = el('div', { className: 'f3-home-quick-actions' });
    actions.forEach(([route, title, description]) => {
      grid.append(el('button', {
        className: 'f3-home-quick-action',
        attrs: { type: 'button', 'data-home-route': route },
        on: { click: () => navigate(model, route) },
      },
      el('span', { className: 'f3-home-quick-action__title', text: title }),
      el('span', { className: 'f3-home-quick-action__description', text: description }),
      el('span', { className: 'f3-home-quick-action__arrow', text: '←', attrs: { 'aria-hidden': 'true' } })
      ));
    });
    section.append(grid);
    return section;
  }

  function resolveState(model) {
    const phase = text(model && model.phase, 40).toLowerCase();
    if (phase === 'loading' || phase === 'initial') return 'loading';
    if (phase === 'session-expired') return 'session-expired';
    if (phase === 'permission-denied' || phase === 'forbidden') return 'permission-denied';
    if (phase === 'error') return 'error';

    const context = (model && model.context) || {};
    const hasWorkspace = !!(
      context.workspaceId || context.workspaceName ||
      (context.workspace && (context.workspace.id || context.workspace.name || context.workspace.label))
    );
    if (!hasWorkspace) return 'no-workspace';

    const parts = (model && model.parts) || {};
    const names = ['schedule', 'assessments', 'announcements', 'resources', 'grades'];
    const failures = names.filter((name) => partFailed(parts[name]));
    if (failures.length) return 'partial-api-failure';

    const allSupplied = names.every((name) => parts[name] && parts[name].ok === true);
    const allEmpty = allSupplied && names.every((name) => resultRows(parts[name]).length === 0);
    if (allEmpty) return 'first-use-empty';
    if (parts.schedule && parts.schedule.ok === true && resultRows(parts.schedule).length === 0) return 'no-schedule';
    if (parts.announcements && parts.announcements.ok === true && resultRows(parts.announcements).length === 0) return 'no-announcement';
    return 'normal-populated';
  }

  function render(container, inputModel) {
    if (!container || typeof container.replaceChildren !== 'function') {
      throw new TypeError('Fanoos V3 Home requires a DOM container.');
    }

    const model = Object.assign({ phase: 'ready', context: {}, parts: {}, handlers: {}, formatters: {} }, inputModel || {});
    clear(container);

    const root = el('div', {
      className: 'f3-home',
      attrs: {
        dir: 'rtl',
        lang: 'fa',
        'data-design-lock': DESIGN_LOCK_ID,
        'data-home-state': resolveState(model),
      },
    });
    container.append(root);

    const state = resolveState(model);
    if (state === 'loading') {
      renderLoading(root);
      return root;
    }
    if (state === 'session-expired' || state === 'permission-denied' || state === 'error') {
      renderBlocking(root, model, state);
      return root;
    }
    if (state === 'no-workspace') {
      renderNoWorkspace(root, model);
      return root;
    }

    renderHeader(root, model);

    const parts = model.parts || {};
    const failedCount = ['schedule', 'assessments', 'announcements', 'resources', 'grades']
      .filter((name) => partFailed(parts[name])).length;
    if (failedCount) {
      root.append(el('div', { className: 'f3-home-partial-banner', attrs: { role: 'status' } },
        el('span', { text: 'بخشی از اطلاعات امروز دریافت نشد؛ بخش‌های دیگر همچنان قابل استفاده‌اند.' }),
        model.handlers && typeof model.handlers.retryHome === 'function'
          ? actionButton('تلاش دوباره', () => retry(model), 'quiet')
          : null
      ));
    }

    const scheduleRows = resultRows(parts.schedule);
    const nearest = renderHero(root, model, scheduleRows);

    if (state === 'first-use-empty') {
      root.append(el('div', { className: 'f3-home-first-use', attrs: { role: 'status' } },
        el('strong', { text: 'این فضای آموزشی هنوز محتوای روزانه‌ای ندارد.' }),
        el('span', { text: 'با اضافه‌شدن برنامه، منابع، آزمون‌ها یا اطلاعیه‌ها، مهم‌ترین موارد همین‌جا نمایش داده می‌شوند.' })
      ));
    }

    const layout = el('div', { className: 'f3-home-layout' });
    const primary = el('div', { className: 'f3-home-layout__primary' },
      renderTodayTimeline(model, scheduleRows, nearest)
    );
    const rail = el('aside', { className: 'f3-home-layout__rail', attrs: { 'aria-label': 'پیگیری‌های امروز' } },
      renderAttention(model),
      renderAnnouncement(model),
      renderLearning(model),
      renderGrade(model)
    );
    layout.append(primary, rail);
    root.append(layout, renderQuickActions(model));
    root.setAttribute('aria-busy', 'false');
    return root;
  }

  global.FanoosV3 = global.FanoosV3 || {};
  global.FanoosV3.home = Object.freeze({
    render,
    resolveState,
    stateMatrix: STATE_MATRIX,
    dataDependencies: DATA_DEPENDENCIES,
    routes: ROUTES,
    designLockId: DESIGN_LOCK_ID,
  });
})(window);
