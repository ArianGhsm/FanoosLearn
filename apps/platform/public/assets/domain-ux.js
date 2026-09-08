(function (global) {
  'use strict';

  const FA_LOCALE = 'fa-IR';
  const DEFAULT_EMPTY = 'موردی برای نمایش نیست.';
  const UNKNOWN_STATUS = 'وضعیت نامشخص';

  const STATUS_LABELS = Object.freeze({
    pending: 'در انتظار',
    payment_pending: 'در انتظار پرداخت',
    paid: 'پرداخت‌شده',
    failed: 'ناموفق',
    canceled: 'لغوشده',
    cancelled: 'لغوشده',
    active: 'فعال',
    expired: 'منقضی‌شده',
    published: 'منتشرشده',
    hidden: 'نمایش‌داده‌نشده',
    draft: 'پیش‌نویس',
    archived: 'بایگانی‌شده',
    open: 'فعال',
    closed: 'بسته',
    read: 'خوانده‌شده',
    delivered: 'دریافت‌شده',
    completed: 'تکمیل‌شده',
    in_progress: 'در حال انجام',
    review: 'در حال بررسی',
    approved: 'تأییدشده',
    rejected: 'ردشده',
    private: 'خصوصی',
    workspace: 'فضای آموزشی',
    entitled: 'نیازمند دسترسی',
    restricted: 'محدود',
    redirected: 'انتقال به پرداخت',
    created: 'ایجادشده',
    verifying: 'در حال بررسی پرداخت',
    refunded: 'بازپرداخت‌شده',
    telegram: 'تلگرام',
    bale: 'بله'
  });

  const RESOURCE_TYPE_LABELS = Object.freeze({
    booklet: 'جزوه',
    lecture_note: 'جزوه',
    note: 'جزوه',
    summary: 'خلاصه',
    dentnote: 'DentNote',
    discipline_note: 'یادداشت تخصصی',
    question_bank: 'بانک سؤال',
    past_exam: 'آزمون گذشته',
    past_questions: 'سؤالات آزمون گذشته',
    flashcard: 'فلش‌کارت',
    audio: 'فایل صوتی',
    video: 'فایل ویدیویی',
    document: 'فایل آموزشی',
    file: 'فایل آموزشی'
  });

  const ASSESSMENT_TYPE_LABELS = Object.freeze({
    practice: 'تمرین',
    mock_exam: 'آزمون آزمایشی',
    past_exam: 'آزمون گذشته',
    quiz: 'آزمون کوتاه',
    exam: 'آزمون'
  });

  const EVENT_TYPE_LABELS = Object.freeze({
    class: 'کلاس',
    lecture: 'کلاس',
    session: 'جلسه',
    exam: 'آزمون',
    quiz: 'آزمون کوتاه',
    deadline: 'مهلت',
    clinic: 'کلینیک',
    lab: 'کارگاه / آزمایشگاه'
  });

  const SEARCH_TYPE_LABELS = Object.freeze({
    course: 'درس',
    resource: 'منبع',
    announcement: 'اطلاعیه',
    assessment: 'آزمون',
    exam: 'آزمون',
    form: 'فرم',
    schedule: 'برنامه',
    grade: 'نمره'
  });

  const CURRENCY_LABELS = Object.freeze({
    IRR: 'ریال',
    IRT: 'تومان'
  });

  const EMPTY_MESSAGES = Object.freeze({
    schedule: 'برای این بازه برنامه‌ای ثبت نشده است.',
    grades: 'نمره‌ای برای نمایش منتشر نشده است.',
    announcements: 'اطلاعیه‌ای برای نمایش وجود ندارد.',
    academics: 'درس یا جلسه‌ای برای نمایش ثبت نشده است.',
    resources: 'منبعی برای نمایش پیدا نشد.',
    assessments: 'تمرین یا آزمونی برای نمایش وجود ندارد.',
    forms: 'فرم فعالی برای نمایش وجود ندارد.',
    orders: 'هنوز خریدی در این فضای آموزشی ثبت نشده است.',
    search: 'نتیجه‌ای برای این جست‌وجو پیدا نشد.'
  });

  function normalizeText(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .normalize('NFKC')
      .replace(/\u064A/g, '\u06CC')
      .replace(/\u0643/g, '\u06A9')
      .replace(/[\u200e\u200f\u202a-\u202e\u2066-\u2069]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function safeTruncate(value, max = 180) {
    const text = normalizeText(value);
    if (!text || text.length <= max) return text;
    const chars = Array.from(text);
    return chars.length <= max ? text : `${chars.slice(0, Math.max(1, max - 1)).join('')}…`;
  }

  function hasValue(value) {
    return value !== null && value !== undefined && normalizeText(value) !== '';
  }

  function firstValue(row, keys) {
    for (const key of keys) if (hasValue(row && row[key])) return row[key];
    return null;
  }

  function formatNumber(value, options = {}) {
    if (value === null || value === undefined || value === '') return '—';
    const number = typeof value === 'number' ? value : Number(String(value).replace(/,/g, ''));
    if (!Number.isFinite(number)) return '—';
    return new Intl.NumberFormat(FA_LOCALE, options).format(number);
  }

  function parseDate(value) {
    if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;
    const text = normalizeText(value);
    if (!text) return null;
    const dateOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (dateOnly) {
      return new Date(Date.UTC(Number(dateOnly[1]), Number(dateOnly[2]) - 1, Number(dateOnly[3]), 12, 0, 0));
    }
    const isoLike = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(text) ? text.replace(' ', 'T') : text;
    const parsed = new Date(isoLike);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
  }

  function formatDate(value, options = {}) {
    const date = parseDate(value);
    if (!date) return '—';
    const base = { year: 'numeric', month: 'long', day: 'numeric', ...options };
    try { return new Intl.DateTimeFormat(FA_LOCALE, base).format(date); } catch { return '—'; }
  }

  function formatTime(value, options = {}) {
    const date = parseDate(value);
    if (!date) return '—';
    try {
      return new Intl.DateTimeFormat(FA_LOCALE, { hour: '2-digit', minute: '2-digit', ...options }).format(date);
    } catch { return '—'; }
  }

  function formatDateTime(value, options = {}) {
    const date = parseDate(value);
    if (!date) return '—';
    try {
      return new Intl.DateTimeFormat(FA_LOCALE, {
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', ...options
      }).format(date);
    } catch { return '—'; }
  }

  function currencyFractionDigits(currency) {
    try {
      return new Intl.NumberFormat('en-US', { style: 'currency', currency }).resolvedOptions().maximumFractionDigits;
    } catch { return 0; }
  }

  function formatMoney(amountMinor, currency) {
    if (amountMinor === null || amountMinor === undefined || amountMinor === '') return '—';
    const amount = Number(amountMinor);
    if (!Number.isFinite(amount)) return '—';
    const code = normalizeText(currency).toUpperCase();
    if (code === 'IRT') return `${formatNumber(amount)} ${CURRENCY_LABELS.IRT}`;
    if (code === 'IRR') return `${formatNumber(amount)} ${CURRENCY_LABELS.IRR}`;
    if (/^[A-Z]{3}$/.test(code)) {
      try {
        const factor = 10 ** currencyFractionDigits(code);
        return new Intl.NumberFormat(FA_LOCALE, { style: 'currency', currency: code }).format(amount / factor);
      } catch { }
    }
    return `${formatNumber(amount)} ${CURRENCY_LABELS[code] || 'واحد پول'}`;
  }

  function localizeStatus(value) {
    const key = normalizeText(value).toLowerCase();
    return STATUS_LABELS[key] || UNKNOWN_STATUS;
  }

  function localizeResourceType(value) {
    const key = normalizeText(value).toLowerCase();
    return RESOURCE_TYPE_LABELS[key] || 'محتوای آموزشی';
  }

  function localizeAssessmentType(value) {
    const key = normalizeText(value).toLowerCase();
    return ASSESSMENT_TYPE_LABELS[key] || 'آزمون / تمرین';
  }

  function localizeEventType(value) {
    const key = normalizeText(value).toLowerCase();
    return EVENT_TYPE_LABELS[key] || '';
  }

  function localizeSearchType(value) {
    const key = normalizeText(value).toLowerCase();
    return SEARCH_TYPE_LABELS[key] || 'نتیجه';
  }

  function createElement(tag, options = {}, children = []) {
    const node = global.document.createElement(tag);
    if (options.className) node.className = options.className;
    if (options.text !== undefined) node.textContent = normalizeText(options.text);
    if (options.attrs) {
      Object.entries(options.attrs).forEach(([name, value]) => {
        if (value !== null && value !== undefined) node.setAttribute(name, String(value));
      });
    }
    for (const child of Array.isArray(children) ? children : [children]) if (child) node.append(child);
    return node;
  }

  function technicalToken(value) {
    const text = normalizeText(value);
    if (!text) return null;
    return createElement('bdi', { className: 'domain-token', text, attrs: { dir: 'ltr' } });
  }

  function chip(label, tone = 'neutral') {
    return createElement('span', { className: `domain-chip domain-chip--${tone}`, text: label });
  }

  function statusChip(status) {
    const key = normalizeText(status).toLowerCase();
    const tone = ['paid', 'published', 'active', 'approved', 'completed', 'read'].includes(key)
      ? 'positive'
      : ['failed', 'cancelled', 'canceled', 'rejected', 'expired'].includes(key)
        ? 'negative'
        : ['pending', 'payment_pending', 'review', 'verifying', 'draft'].includes(key)
          ? 'pending' : 'neutral';
    return chip(localizeStatus(status), tone);
  }

  function metaLine(label, value, options = {}) {
    if (!hasValue(value)) return null;
    const line = createElement('div', { className: 'domain-meta-line' });
    line.append(createElement('span', { className: 'domain-meta-label', text: label }));
    if (options.token) line.append(technicalToken(value));
    else line.append(createElement('span', { className: 'domain-meta-value', text: value }));
    return line;
  }

  function replace(container, nodes) {
    container.replaceChildren();
    for (const node of nodes) if (node) container.append(node);
  }

  function renderLoading(container, message = 'در حال دریافت اطلاعات…') {
    replace(container, [createElement('div', { className: 'domain-state domain-state--loading', text: message, attrs: { role: 'status', 'aria-live': 'polite' } })]);
  }

  function renderEmpty(container, viewKey, message) {
    replace(container, [createElement('div', {
      className: 'domain-state domain-state--empty',
      text: message || EMPTY_MESSAGES[viewKey] || DEFAULT_EMPTY,
      attrs: { role: 'status' }
    })]);
  }

  function renderError(container, options = {}) {
    const box = createElement('div', { className: 'domain-state domain-state--error', attrs: { role: 'alert' } });
    box.append(createElement('strong', { text: options.title || 'دریافت اطلاعات ممکن نشد' }));
    box.append(createElement('p', { text: options.message || 'ارتباط با سامانه با مشکل روبه‌رو شد. دوباره تلاش کنید.' }));
    if (typeof options.onRetry === 'function') {
      const button = createElement('button', { className: 'domain-retry', text: 'تلاش دوباره', attrs: { type: 'button' } });
      button.addEventListener('click', options.onRetry);
      box.append(button);
    }
    replace(container, [box]);
  }

  function cardBase(title, status) {
    const card = createElement('article', { className: 'result-card domain-card' });
    const head = createElement('div', { className: 'domain-card__head' });
    head.append(createElement('h3', { text: title || 'بدون عنوان' }));
    if (hasValue(status)) head.append(statusChip(status));
    card.append(head);
    return card;
  }

  function appendMeta(card, lines) {
    const available = lines.filter(Boolean).slice(0, 3);
    if (!available.length) return;
    const box = createElement('div', { className: 'domain-meta' });
    available.forEach(line => box.append(line));
    card.append(box);
  }

  function appendExcerpt(card, value, max = 240) {
    const excerpt = safeTruncate(value, max);
    if (excerpt) card.append(createElement('p', { className: 'domain-excerpt', text: excerpt }));
  }

  function dateGroupKey(value) {
    const raw = normalizeText(value);
    const match = /^(\d{4}-\d{2}-\d{2})/.exec(raw);
    if (match) return match[1];
    const date = parseDate(value);
    return date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` : 'unknown';
  }

  function renderSchedule(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'schedule');
    const groups = new Map();
    rows.forEach(row => {
      const key = dateGroupKey(row.starts_at);
      if (!groups.has(key)) groups.set(key, []);
      groups.get(key).push(row);
    });
    const nodes = [];
    groups.forEach((items, key) => {
      const section = createElement('section', { className: 'schedule-group' });
      const firstStart = items[0] && items[0].starts_at;
      section.append(createElement('h3', { className: 'schedule-group__date', text: key === 'unknown' ? 'زمان ثبت‌نشده' : formatDate(firstStart, { weekday: 'long' }) }));
      items.forEach(row => {
        const title = firstValue(row, ['course_title', 'title']) || 'رویداد آموزشی';
        const card = cardBase(title, row.status);
        card.classList.add('schedule-card');
        const timeText = hasValue(row.starts_at)
          ? (hasValue(row.ends_at) ? `${formatTime(row.starts_at)} تا ${formatTime(row.ends_at)}` : formatTime(row.starts_at))
          : '—';
        const type = localizeEventType(row.event_type);
        const chips = createElement('div', { className: 'domain-chip-row' });
        if (type) chips.append(chip(type));
        if (hasValue(row.course_code)) chips.append(technicalToken(row.course_code));
        if (chips.childNodes.length) card.append(chips);
        appendMeta(card, [
          metaLine('زمان', timeText),
          metaLine('مکان', row.location_text),
          metaLine('استاد', firstValue(row, ['professor_name', 'instructor_name']))
        ]);
        section.append(card);
      });
      nodes.push(section);
    });
    replace(container, nodes);
  }

  function renderGrades(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'grades');
    const nodes = rows.map(row => {
      const title = firstValue(row, ['item_title', 'gradebook_title', 'course_title']) || 'نمره';
      const card = cardBase(title, row.status || 'published');
      card.classList.add('grade-card');
      const score = createElement('div', { className: 'grade-score' });
      score.append(createElement('strong', { text: formatNumber(row.score) }));
      if (hasValue(row.max_score)) score.append(createElement('span', { text: `از ${formatNumber(row.max_score)}` }));
      card.append(score);
      appendMeta(card, [
        metaLine('درس', row.course_title),
        metaLine('کد درس', row.course_code, { token: true }),
        metaLine('به‌روزرسانی', row.updated_at ? formatDateTime(row.updated_at) : null)
      ]);
      return card;
    });
    replace(container, nodes);
  }

  function safeActionUrl(value) {
    const text = normalizeText(value);
    if (!text) return null;
    try {
      const url = new URL(text, global.location && global.location.origin ? global.location.origin : 'https://invalid.local');
      if (url.protocol !== 'https:' && url.protocol !== 'http:') return null;
      if (text.startsWith('/') && !text.startsWith('//')) return text;
      if (global.location && url.origin === global.location.origin) return url.href;
      return url.protocol === 'https:' ? url.href : null;
    } catch { return null; }
  }

  function renderAnnouncements(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'announcements');
    const nodes = rows.map(row => {
      const card = cardBase(row.title || 'اطلاعیه', row.status || 'published');
      card.classList.add('announcement-card');
      appendExcerpt(card, row.body, 320);
      appendMeta(card, [
        metaLine('انتشار', row.published_at ? formatDateTime(row.published_at) : null),
        metaLine('خوانده‌شدن', row.read_at ? formatDateTime(row.read_at) : null)
      ]);
      const actionUrl = safeActionUrl(firstValue(row, ['safe_url', 'action_url', 'url']));
      if (actionUrl) {
        const link = createElement('a', { className: 'domain-link', text: 'مشاهده', attrs: { href: actionUrl } });
        card.append(link);
      }
      return card;
    });
    replace(container, nodes);
  }

  function renderAcademics(container, rows, context = {}) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'academics');
    const nodes = rows.map(row => {
      const card = cardBase(row.title || 'درس', row.session_status || row.offering_status || row.status);
      card.classList.add('academic-card');
      const chips = createElement('div', { className: 'domain-chip-row' });
      if (hasValue(row.course_code)) chips.append(technicalToken(row.course_code));
      if (hasValue(row.term_name)) chips.append(chip(row.term_name));
      if (chips.childNodes.length) card.append(chips);
      appendMeta(card, [
        metaLine('جلسه', row.session_title || (hasValue(row.sequence_no) ? `جلسه ${formatNumber(row.sequence_no)}` : null)),
        metaLine('زمان جلسه', row.starts_at ? formatDateTime(row.starts_at) : null),
        metaLine('گروه', row.section_key, { token: true })
      ]);
      if (!hasValue(row.session_title) && hasValue(context.workspaceName)) appendMeta(card, [metaLine('فضای آموزشی', context.workspaceName)]);
      return card;
    });
    replace(container, nodes);
  }

  function resourceAccessLabel(row) {
    const access = normalizeText(firstValue(row, ['access_level', 'visibility'])).toLowerCase();
    if (access === 'entitled' || access === 'restricted' || row.requires_entitlement === true || Number(row.requires_entitlement) === 1) return 'نیازمند دسترسی';
    if (access === 'private') return 'خصوصی';
    if (access === 'workspace') return 'فضای آموزشی';
    return '';
  }

  function renderResources(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'resources');
    const nodes = rows.map(row => {
      const card = cardBase(row.title || 'منبع آموزشی', row.lifecycle_status || row.status || 'published');
      card.classList.add('resource-card');
      const chips = createElement('div', { className: 'domain-chip-row' });
      chips.append(chip(localizeResourceType(firstValue(row, ['type_key', 'resource_type', 'type']))));
      const access = resourceAccessLabel(row);
      if (access) chips.append(chip(access, access === 'نیازمند دسترسی' ? 'pending' : 'neutral'));
      card.append(chips);
      appendExcerpt(card, row.description, 220);
      appendMeta(card, [
        metaLine('موضوع', row.topic),
        metaLine('استاد', row.professor_name),
        metaLine('نسخه', hasValue(row.current_version_no) ? formatNumber(row.current_version_no) : (hasValue(row.version_no) ? formatNumber(row.version_no) : null))
      ]);
      return card;
    });
    replace(container, nodes);
  }

  function renderAssessments(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'assessments');
    const nodes = rows.map(row => {
      const card = cardBase(row.title || 'آزمون / تمرین', row.status || 'published');
      card.classList.add('assessment-card');
      const chips = createElement('div', { className: 'domain-chip-row' });
      chips.append(chip(localizeAssessmentType(firstValue(row, ['assessment_kind', 'assessment_type', 'type']))));
      if (row.requires_entitlement === true || Number(row.requires_entitlement) === 1) chips.append(chip('نیازمند دسترسی', 'pending'));
      card.append(chips);
      const attemptText = hasValue(row.attempt_count) && hasValue(row.max_attempts)
        ? `${formatNumber(row.attempt_count)} از ${formatNumber(row.max_attempts)} تلاش`
        : (hasValue(row.max_attempts) ? `حداکثر ${formatNumber(row.max_attempts)} تلاش` : null);
      const progress = hasValue(row.progress_percent) ? `${formatNumber(row.progress_percent)}٪` : null;
      appendMeta(card, [
        metaLine('تلاش', attemptText),
        metaLine('پیشرفت', progress),
        metaLine('نسخه', hasValue(row.current_version_no) ? formatNumber(row.current_version_no) : null)
      ]);
      return card;
    });
    replace(container, nodes);
  }

  function renderForms(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'forms');
    const nodes = rows.map(row => {
      const card = cardBase(row.title || 'فرم', row.status || 'open');
      card.classList.add('form-card');
      appendExcerpt(card, row.description, 220);
      appendMeta(card, [
        metaLine('مهلت', row.closes_at ? formatDateTime(row.closes_at) : 'بدون مهلت ثبت‌شده'),
        metaLine('شروع', row.opens_at ? formatDateTime(row.opens_at) : null),
        metaLine('ارسال چندباره', row.allow_multiple === true || Number(row.allow_multiple) === 1 ? 'مجاز' : 'یک پاسخ')
      ]);
      return card;
    });
    replace(container, nodes);
  }

  function entitlementLabel(row) {
    if (row.has_entitlement === true || Number(row.has_entitlement) === 1) return 'دسترسی فعال';
    const status = normalizeText(row.entitlement_status).toLowerCase();
    if (status === 'active') return 'دسترسی فعال';
    if (status === 'expired') return 'دسترسی منقضی‌شده';
    if (status) return localizeStatus(status);
    return '';
  }

  function renderOrders(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'orders');
    const nodes = rows.map(row => {
      const card = cardBase(row.product_name_snapshot || row.title || 'سفارش', row.status);
      card.classList.add('order-card');
      card.append(createElement('div', { className: 'order-amount', text: formatMoney(firstValue(row, ['total_minor', 'amount_minor']), row.currency) }));
      const entitlement = entitlementLabel(row);
      const chips = createElement('div', { className: 'domain-chip-row' });
      if (entitlement) chips.append(chip(entitlement, entitlement === 'دسترسی فعال' ? 'positive' : 'neutral'));
      if (chips.childNodes.length) card.append(chips);
      appendMeta(card, [
        metaLine('ثبت سفارش', row.created_at ? formatDateTime(row.created_at) : null),
        metaLine('پرداخت', row.paid_at ? formatDateTime(row.paid_at) : null)
      ]);
      return card;
    });
    replace(container, nodes);
  }

  function renderSearch(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'search');
    const nodes = rows.map(row => {
      const card = cardBase(row.title || 'نتیجه جست‌وجو', null);
      card.classList.add('search-card');
      const chips = createElement('div', { className: 'domain-chip-row' });
      chips.append(chip(localizeSearchType(firstValue(row, ['source_type', 'type']))));
      card.append(chips);
      appendMeta(card, [metaLine('به‌روزرسانی', row.updated_at ? formatDateTime(row.updated_at) : null)]);
      appendExcerpt(card, firstValue(row, ['excerpt', 'description', 'context']), 180);
      return card;
    });
    replace(container, nodes);
  }

  function renderRows(container, rows) {
    if (!Array.isArray(rows) || !rows.length) return renderEmpty(container, 'unknown');
    const nodes = rows.map(row => {
      const title = firstValue(row, ['title', 'course_title', 'item_title', 'product_name_snapshot']) || 'جزئیات';
      const card = cardBase(title, row.status);
      appendExcerpt(card, firstValue(row, ['description', 'body', 'message']), 180);
      return card;
    });
    replace(container, nodes);
  }

  const renderers = Object.freeze({
    schedule: renderSchedule,
    grades: renderGrades,
    announcements: renderAnnouncements,
    academics: renderAcademics,
    resources: renderResources,
    assessments: renderAssessments,
    forms: renderForms,
    orders: renderOrders,
    search: renderSearch
  });

  function renderView(key, container, rows, context = {}) {
    const renderer = renderers[key] || renderRows;
    return renderer(container, rows, context);
  }

  const exported = {
    STATUS_LABELS,
    RESOURCE_TYPE_LABELS,
    ASSESSMENT_TYPE_LABELS,
    CURRENCY_LABELS,
    EMPTY_MESSAGES,
    normalizeText,
    safeTruncate,
    formatNumber,
    formatDate,
    formatTime,
    formatDateTime,
    formatMoney,
    localizeStatus,
    localizeResourceType,
    localizeAssessmentType,
    localizeSearchType,
    technicalToken,
    createElement,
    renderLoading,
    renderEmpty,
    renderError,
    renderRows,
    renderers,
    renderView
  };

  global.FanoosDomainUX = exported;
  if (typeof module !== 'undefined' && module.exports) module.exports = exported;
})(typeof window !== 'undefined' ? window : globalThis);
