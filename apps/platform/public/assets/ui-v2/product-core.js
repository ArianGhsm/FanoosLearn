(function (global) {
  'use strict';

  const ux = global.FanoosDomainUX;
  const locale = 'fa-IR';
  const maxExcerpt = 220;
  const resourceTypes = ['lecture_note', 'discipline_note', 'summary', 'cheat_sheet', 'flashcards', 'question_bank', 'past_exam', 'audio', 'other'];
  const UI = global.FanoosProductUI || {};

  function text(value) {
      return ux && typeof ux.normalizeText === 'function'
        ? ux.normalizeText(value)
        : String(value == null ? '' : value).replace(/[\u202A-\u202E\u2066-\u2069]/g, '').trim();
    }

  function bounded(value, limit = maxExcerpt) {
      const clean = text(value);
      if (clean.length <= limit) return clean;
      return `${clean.slice(0, Math.max(0, limit - 1)).trim()}…`;
    }

  function el(tag, options = {}, ...children) {
      const node = document.createElement(tag);
      if (options.className) node.className = options.className;
      if (options.text != null) node.textContent = String(options.text);
      if (options.attrs) {
        Object.entries(options.attrs).forEach(([key, value]) => {
          if (value != null && value !== false) node.setAttribute(key, value === true ? '' : String(value));
        });
      }
      if (options.dataset) {
        Object.entries(options.dataset).forEach(([key, value]) => {
          if (value != null) node.dataset[key] = String(value);
        });
      }
      if (options.on) {
        Object.entries(options.on).forEach(([eventName, handler]) => node.addEventListener(eventName, handler));
      }
      node.append(...children.filter(Boolean));
      return node;
    }

  function button(label, options = {}) {
      const classes = ['button', options.variant ? `button-${options.variant}` : 'button-secondary'];
      const node = el('button', {
        className: classes.join(' '),
        text: label,
        attrs: { type: options.type || 'button', disabled: options.disabled || null },
        on: options.onClick ? { click: options.onClick } : undefined,
      });
      return node;
    }

  function clear(container) { container.replaceChildren(); }

  function faNumber(value) {
      return ux && typeof ux.formatNumber === 'function' ? ux.formatNumber(value) : new Intl.NumberFormat(locale).format(Number(value || 0));
    }

  function faDate(value, context = {}) {
      return ux && typeof ux.formatDate === 'function'
        ? ux.formatDate(value, { assumeUtc: true, timeZone: context.workspaceTimezone })
        : '—';
    }

  function faDateTime(value, context = {}) {
      return ux && typeof ux.formatDateTime === 'function'
        ? ux.formatDateTime(value, { assumeUtc: true, timeZone: context.workspaceTimezone })
        : '—';
    }

  function faTime(value, context = {}) {
      if (!value) return '—';
      const raw = String(value).trim();
      const iso = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw) ? `${raw.replace(' ', 'T')}Z` : raw;
      const parsed = new Date(iso);
      if (!Number.isFinite(parsed.getTime())) return '—';
      try {
        return new Intl.DateTimeFormat(locale, {
          hour: '2-digit', minute: '2-digit', hour12: false,
          timeZone: context.workspaceTimezone || 'UTC',
        }).format(parsed);
      } catch (_error) { return '—'; }
    }

  function money(amount, currency) {
      return ux && typeof ux.formatMoney === 'function' ? ux.formatMoney(amount, currency) : '—';
    }

  function status(value) {
      return ux && typeof ux.localizeStatus === 'function' ? ux.localizeStatus(value) : 'وضعیت نامشخص';
    }

  function resourceType(value) {
      return ux && typeof ux.localizeResourceType === 'function' ? ux.localizeResourceType(value) : 'منبع آموزشی';
    }

  function assessmentType(value) {
      return ux && typeof ux.localizeAssessmentType === 'function' ? ux.localizeAssessmentType(value) : 'آزمون';
    }

  function resultRows(result) {
      if (!result || !result.ok) return [];
      return Array.isArray(result.data) ? result.data : [];
    }

  function skeleton(container, count = 4) {
      clear(container);
      const grid = el('div', { className: 'card-grid', attrs: { 'aria-label': 'در حال دریافت اطلاعات' } });
      for (let i = 0; i < count; i += 1) grid.append(el('div', { className: 'skeleton skeleton-card' }));
      container.append(grid);
    }

  function stateBlock(container, title, description, options = {}) {
      clear(container);
      const inner = el('div', { className: 'state-block__inner' },
        el('h3', { text: title }),
        el('p', { text: description })
      );
      if (options.actionLabel && options.onAction) inner.append(button(options.actionLabel, { variant: 'secondary', onClick: options.onAction }));
      container.append(el('div', { className: `state-block ${options.kind ? `state-block--${options.kind}` : ''}` }, inner));
    }

  function partialState(title, retry) {
      const node = el('div', { className: 'partial-state', text: title });
      if (retry) node.append(button('تلاش دوباره', { variant: 'quiet', onClick: retry }));
      return node;
    }

  function heading(title, description, action) {
      const copy = el('div', {}, el('h3', { text: title }));
      if (description) copy.append(el('p', { text: description }));
      const node = el('div', { className: 'product-section__heading' }, copy);
      if (action) node.append(action);
      return node;
    }

  function routeFromHash(hashValue) {
      const raw = String(hashValue || '').replace(/^#\/?/, '');
      const [pathPart, queryPart = ''] = raw.split('?');
      const parts = pathPart.split('/').filter(Boolean);
      const query = {};
      new URLSearchParams(queryPart).forEach((value, key) => { query[key] = value; });
      const primary = parts[0] || 'home';
      const aliases = {
        academics: 'courses', resources: 'resources', assessments: 'assessments', grades: 'grades',
        announcements: 'announcements', schedule: 'schedule', forms: 'forms', orders: 'orders',
      };
      return { name: aliases[primary] || primary, subview: parts[1] || '', query };
    }

  function routeHash(name, query = {}, subview = '') {
      const path = `#/${name}${subview ? `/${subview}` : ''}`;
      const params = new URLSearchParams();
      Object.entries(query).forEach(([key, value]) => { if (value != null && String(value) !== '') params.set(key, String(value)); });
      return params.toString() ? `${path}?${params}` : path;
    }

  function courseGroups(rows) {
      const groups = new Map();
      (Array.isArray(rows) ? rows : []).forEach((row) => {
        const key = text(row.id || row.course_code || row.title);
        if (!key) return;
        if (!groups.has(key)) {
          groups.set(key, {
            id: row.id || null,
            code: text(row.course_code),
            title: text(row.title) || 'درس',
            credit: row.credit_value,
            terms: new Set(),
            offeringIds: new Set(),
            sessions: [],
          });
        }
        const course = groups.get(key);
        if (row.term_name) course.terms.add(text(row.term_name));
        if (row.offering_id) course.offeringIds.add(String(row.offering_id));
        if (row.session_id || row.session_title || row.sequence_no != null) {
          course.sessions.push({
            id: row.session_id || null,
            title: text(row.session_title) || `جلسه ${faNumber(row.sequence_no)}`,
            sequence: row.sequence_no,
            startsAt: row.starts_at,
            endsAt: row.ends_at,
            status: row.session_status,
          });
        }
      });
      const values = [...groups.values()];
      values.forEach((course) => course.sessions.sort((a, b) => {
        const sa = Number(a.sequence); const sb = Number(b.sequence);
        if (Number.isFinite(sa) && Number.isFinite(sb) && sa !== sb) return sa - sb;
        return String(a.startsAt || '').localeCompare(String(b.startsAt || ''));
      }));
      return values.sort((a, b) => a.title.localeCompare(b.title, 'fa'));
    }

  function findCourse(courses, code) {
      const target = text(code).toLocaleLowerCase('en-US');
      return courses.find((course) => course.code.toLocaleLowerCase('en-US') === target) || null;
    }

  function courseLabelForId(courses, id) {
      const course = courses.find((item) => String(item.id || '') === String(id || ''));
      return course ? course.title : '';
    }

  function localDateKey(value, timezone) {
      const raw = String(value || '').trim();
      const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw) ? `${raw.replace(' ', 'T')}Z` : raw;
      const parsed = new Date(normalized);
      if (!Number.isFinite(parsed.getTime())) return '';
      try {
        const parts = new Intl.DateTimeFormat('en-CA', { timeZone: timezone || 'UTC', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(parsed);
        const pick = (type) => parts.find((part) => part.type === type)?.value || '';
        return `${pick('year')}-${pick('month')}-${pick('day')}`;
      } catch (_error) { return ''; }
    }

  function todayKey(timezone) { return localDateKey(new Date().toISOString(), timezone); }

  function addNeutralDays(key, days) {
      const parsed = new Date(`${key}T00:00:00Z`);
      if (!Number.isFinite(parsed.getTime())) return key;
      parsed.setUTCDate(parsed.getUTCDate() + days);
      return parsed.toISOString().slice(0, 10);
    }

  function weekBounds(timezone) {
      const key = todayKey(timezone);
      const parsed = new Date(`${key}T00:00:00Z`);
      const day = parsed.getUTCDay();
      const sinceSaturday = (day + 1) % 7;
      const start = addNeutralDays(key, -sinceSaturday);
      return { start, end: addNeutralDays(start, 7) };
    }

  function rowMatchesCourse(row, course) {
      if (!course) return true;
      if (row.course_code && text(row.course_code).toLocaleLowerCase('en-US') === course.code.toLocaleLowerCase('en-US')) return true;
      if (row.course_id && course.id && String(row.course_id) === String(course.id)) return true;
      if (row.offering_id && course.offeringIds.has(String(row.offering_id))) return true;
      return false;
    }

  const routeMeta = {
      home: ['امروز', 'خانه', 'خلاصه وضعیت فضای آموزشی و کارهای مهم امروز'],
      courses: ['یادگیری', 'درس‌ها', 'همه درس‌ها، جلسات و محتوای مرتبط'],
      schedule: ['برنامه آموزشی', 'برنامه', 'کلاس‌ها، آزمون‌ها و رویدادهای پیش رو'],
      resources: ['یادگیری', 'منابع', 'جزوه‌ها، خلاصه‌ها، بانک سؤال و محتوای آموزشی'],
      assessments: ['ارزیابی', 'آزمون‌ها', 'تمرین‌ها، آزمون‌های فعال و آزمون‌های گذشته'],
      grades: ['عملکرد', 'نمرات', 'نمره‌های منتشرشده به تفکیک درس'],
      announcements: ['ارتباطات', 'اطلاعیه‌ها', 'پیام‌های رسمی فضای آموزشی شما'],
      forms: ['فعالیت‌ها', 'فرم‌ها', 'فرم‌های فعال و مهلت‌های پاسخ'],
      orders: ['دسترسی', 'خرید و دسترسی', 'سفارش‌ها، وضعیت پرداخت و دسترسی‌های مرتبط'],
      account: ['حساب', 'حساب و فضاهای آموزشی', 'عضویت‌ها و فضای آموزشی فعال'],
      search: ['جست‌وجو', 'نتایج جست‌وجو', 'جست‌وجو در محتوای مجاز فضای آموزشی'],
      management: ['مدیریت', 'مدیریت فضای آموزشی', 'ابزارهای مجاز بر اساس سطح دسترسی فعلی'],
    };

  function pageMeta(route) { const key = typeof route === 'string' ? route : route?.name; const row = routeMeta[key] || routeMeta.home; return { eyebrow: row[0], title: row[1], subtitle: row[2], description: row[2] }; }

  Object.assign(UI, { text, bounded, el, clear, skeleton, stateBlock, routeFromHash, routeHash, pageMeta, courseGroups, findCourse, courseLabelForId, localDateKey, weekBounds });
  UI._internal = Object.freeze({ text, bounded, el, button, clear, faNumber, faDate, faDateTime, faTime, money, status, resourceType, assessmentType, resultRows, skeleton, stateBlock, partialState, heading, routeFromHash, routeHash, courseGroups, findCourse, courseLabelForId, localDateKey, todayKey, addNeutralDays, weekBounds, rowMatchesCourse, pageMeta, resourceTypes });
  global.FanoosProductUI = UI;
  if (typeof module !== 'undefined' && module.exports) module.exports = UI;
})(typeof window !== 'undefined' ? window : globalThis);
