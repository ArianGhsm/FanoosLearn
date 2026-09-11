import {
  cleanText,
  courseMatchesTerm,
  filterCourses,
  findNearestSession,
  firstAssessment,
  latestGrade,
  latestResource,
  localizeStatus,
  routeTermKey,
  sessionsForTerm,
} from './course-model.js';

const VALID_TABS = new Set(['overview', 'sessions', 'schedule', 'resources', 'assessments', 'grades', 'announcements']);

function node(tag, options = {}, ...children) {
  const element = document.createElement(tag);
  if (options.className) element.className = options.className;
  if (options.text != null) element.textContent = String(options.text);
  if (options.attrs) {
    Object.entries(options.attrs).forEach(([key, value]) => {
      if (value !== false && value != null) element.setAttribute(key, value === true ? '' : String(value));
    });
  }
  if (options.on) {
    Object.entries(options.on).forEach(([eventName, handler]) => element.addEventListener(eventName, handler));
  }
  element.append(...children.filter(Boolean));
  return element;
}

function number(ctx, value) {
  if (value == null || value === '') return '';
  if (typeof ctx.format?.number === 'function') return ctx.format.number(value);
  try { return new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(Number(value)); }
  catch (_error) { return cleanText(value); }
}

function normalizedDate(value) {
  const raw = cleanText(value);
  if (!raw) return '';
  return /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw)
    ? `${raw.replace(' ', 'T')}Z`
    : raw;
}

function dateTime(ctx, value) {
  if (!value) return '';
  if (typeof ctx.format?.dateTime === 'function') return ctx.format.dateTime(value);
  const timezone = cleanText(ctx.state?.workspace?.timezoneName || ctx.state?.workspace?.timezone_name || ctx.state?.activeWorkspace?.timezone_name);
  if (!timezone) return '';
  const parsed = new Date(normalizedDate(value));
  if (!Number.isFinite(parsed.getTime())) return '';
  try {
    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
      weekday: 'short', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', hour12: false, timeZone: timezone,
    }).format(parsed);
  } catch (_error) { return ''; }
}

function dateOnly(ctx, value) {
  if (!value) return '';
  if (typeof ctx.format?.date === 'function') return ctx.format.date(value);
  const timezone = cleanText(ctx.state?.workspace?.timezoneName || ctx.state?.workspace?.timezone_name || ctx.state?.activeWorkspace?.timezone_name);
  if (!timezone) return '';
  const parsed = new Date(normalizedDate(value));
  if (!Number.isFinite(parsed.getTime())) return '';
  try {
    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long', year: 'numeric', timeZone: timezone }).format(parsed);
  } catch (_error) { return ''; }
}

function resourceType(value) {
  const labels = {
    lecture_note: 'جزوه', discipline_note: 'یادداشت درسی', summary: 'خلاصه', cheat_sheet: 'مرور سریع',
    flashcards: 'فلش‌کارت', question_bank: 'بانک سؤال', past_exam: 'آزمون گذشته', audio: 'صوت', transcript: 'متن پیاده‌سازی‌شده', slide_reference: 'اسلاید / مرجع', other: 'منبع آموزشی',
  };
  return labels[cleanText(value).toLowerCase()] || 'منبع آموزشی';
}

function assessmentType(value) {
  const labels = { practice: 'تمرین', mock_exam: 'آزمون آزمایشی', past_exam: 'آزمون گذشته', quiz: 'کوییز', exam: 'آزمون' };
  return labels[cleanText(value).toLowerCase()] || 'آزمون';
}

function statusText(ctx, value) {
  if (!value) return '';
  if (typeof ctx.format?.status === 'function') {
    const formatted = cleanText(ctx.format.status(value));
    if (formatted) return formatted;
  }
  return localizeStatus(value);
}

function navigate(ctx, href) {
  if (typeof ctx.navigate === 'function') ctx.navigate(href);
  else if (typeof window !== 'undefined') window.location.hash = href.replace(/^#/, '');
}

function routeLink(ctx, href, label, className = 'f3-course-link') {
  return node('a', {
    className,
    text: label,
    attrs: { href },
    on: {
      click: (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (typeof ctx.navigate === 'function') {
          event.preventDefault();
          navigate(ctx, href);
        }
      },
    },
  });
}

function indexHref(query = {}) {
  const params = new URLSearchParams();
  if (query.term) params.set('term', query.term);
  if (query.q) params.set('q', query.q);
  const suffix = params.toString();
  return `#/courses${suffix ? `?${suffix}` : ''}`;
}

function detailHref(courseCode, tab = 'overview') {
  const params = new URLSearchParams();
  if (tab && tab !== 'overview') params.set('tab', tab);
  const suffix = params.toString();
  return `#/courses/${encodeURIComponent(courseCode)}${suffix ? `?${suffix}` : ''}`;
}

function destinationHref(name, courseCode) {
  const params = new URLSearchParams();
  params.set('course', courseCode);
  return `#/${name}?${params}`;
}

function stateView(title, description, options = {}) {
  const box = node('section', { className: `f3-course-state f3-course-state--${options.kind || 'neutral'}`, attrs: { role: options.kind === 'error' ? 'alert' : 'status' } },
    node('div', { className: 'f3-course-state__copy' },
      node('h2', { className: 'f3-course-state__title', text: title }),
      node('p', { className: 'f3-course-state__description', text: description }),
    ),
  );
  if (options.action) box.append(node('div', { className: 'f3-course-state__actions' }, options.action));
  return box;
}

function replace(root, ...children) {
  root.replaceChildren(...children.filter(Boolean));
}

function metaItem(label, value, options = {}) {
  if (!value && value !== 0) return null;
  return node('div', { className: 'f3-course-meta-item' },
    node('span', { className: 'f3-course-meta-item__label', text: label }),
    options.ltr
      ? node('bdi', { className: 'f3-course-meta-item__value', text: value, attrs: { dir: 'ltr' } })
      : node('strong', { className: 'f3-course-meta-item__value', text: value }),
  );
}

function chip(label, tone = 'neutral') {
  return node('span', { className: `f3-course-chip f3-course-chip--${tone}`, text: label });
}

function termLabel(ctx, term) {
  if (!term) return '';
  return term.name || term.key || '';
}

function courseCard(ctx, course, term = null) {
  const sessions = term ? sessionsForTerm(course, routeTermKey(term)) : course.sessions;
  const offerings = term
    ? course.offerings.filter((offering) => courseMatchesTerm({ terms: offering.term ? [offering.term] : [] }, routeTermKey(term)))
    : course.offerings;
  const sections = [...new Set(offerings.map((offering) => offering.sectionKey).filter(Boolean))];
  const statuses = [...new Set(offerings.map((offering) => statusText(ctx, offering.status)).filter(Boolean))];
  const article = node('article', { className: 'f3-course-card' });
  const heading = node('div', { className: 'f3-course-card__heading' },
    node('h3', { className: 'f3-course-card__title', text: course.title }),
    node('bdi', { className: 'f3-course-card__code', text: course.code, attrs: { dir: 'ltr' } }),
  );
  article.append(heading);

  const facts = node('div', { className: 'f3-course-card__facts' });
  if (term) facts.append(chip(termLabel(ctx, term)));
  else course.terms.slice(0, 2).forEach((item) => facts.append(chip(termLabel(ctx, item))));
  if (course.creditValue != null) facts.append(chip(`${number(ctx, course.creditValue)} واحد`));
  if (sections.length === 1) facts.append(chip(`گروه ${sections[0]}`));
  if (sessions.length) facts.append(chip(`${number(ctx, sessions.length)} جلسه`));
  if (statuses.length === 1) facts.append(chip(statuses[0], statuses[0] === 'فعال' ? 'success' : 'neutral'));
  if (facts.childElementCount) article.append(facts);

  article.append(node('div', { className: 'f3-course-card__action' }, routeLink(ctx, detailHref(course.code), 'باز کردن درس', 'f3-course-open-link')));
  return article;
}

function termSection(ctx, term, courses) {
  const section = node('section', { className: 'f3-course-term-section', attrs: { 'aria-labelledby': `f3-term-${safeFragment(routeTermKey(term))}` } });
  const status = statusText(ctx, term.status);
  const dateRange = [dateOnly(ctx, term.startsOn), dateOnly(ctx, term.endsOn)].filter(Boolean).join(' تا ');
  section.append(node('div', { className: 'f3-course-term-section__heading' },
    node('div', {},
      node('h2', { className: 'f3-course-term-section__title', text: term.name || term.key || 'ترم', attrs: { id: `f3-term-${safeFragment(routeTermKey(term))}` } }),
      dateRange ? node('p', { className: 'f3-course-term-section__meta', text: dateRange }) : null,
    ),
    status ? chip(status, status === 'فعال' ? 'success' : 'neutral') : null,
  ));
  const grid = node('div', { className: 'f3-course-card-grid' });
  courses.forEach((course) => grid.append(courseCard(ctx, course, term)));
  section.append(grid);
  return section;
}

function safeFragment(value) {
  const raw = cleanText(value).toLowerCase();
  let hash = 0;
  for (let index = 0; index < raw.length; index += 1) hash = ((hash << 5) - hash + raw.charCodeAt(index)) | 0;
  return `x${Math.abs(hash)}`;
}

export function renderLoading(root, detail = false) {
  const page = node('div', { className: 'f3-course-page', attrs: { 'aria-busy': 'true', 'aria-label': 'در حال دریافت اطلاعات درس‌ها' } });
  page.append(node('div', { className: `f3-course-skeleton ${detail ? 'f3-course-skeleton--detail' : ''}` },
    node('div', { className: 'f3-course-skeleton__line f3-course-skeleton__line--title' }),
    node('div', { className: 'f3-course-skeleton__line' }),
    node('div', { className: 'f3-course-skeleton__grid' },
      node('div', { className: 'f3-course-skeleton__card' }),
      node('div', { className: 'f3-course-skeleton__card' }),
      node('div', { className: 'f3-course-skeleton__card' }),
    ),
  ));
  replace(root, page);
}

export function renderCoursesFailure(root, ctx, error, retry) {
  const status = Number(error?.status || 0);
  let title = 'درس‌ها دریافت نشدند';
  let description = 'ارتباط با اطلاعات آموزشی این فضا برقرار نشد. می‌توانید دوباره تلاش کنید.';
  let kind = 'error';
  let action = node('button', { className: 'f3-course-button f3-course-button--secondary', text: 'تلاش دوباره', attrs: { type: 'button' }, on: { click: retry } });
  if (status === 401) {
    title = 'نشست شما پایان یافته است';
    description = 'برای ادامه، دوباره وارد حساب خود شوید.';
    action = routeLink(ctx, '#/login', 'ورود دوباره', 'f3-course-button f3-course-button--primary');
  } else if (status === 403) {
    title = 'دسترسی به درس‌ها تأیید نشد';
    description = 'حساب فعلی اجازه مشاهده درس‌های این فضای آموزشی را ندارد.';
    kind = 'warning';
    action = routeLink(ctx, '#/account', 'بررسی فضای آموزشی', 'f3-course-button f3-course-button--secondary');
  } else if (status === 404) {
    title = 'فضای آموزشی در دسترس نیست';
    description = 'این فضای آموزشی پیدا نشد یا دیگر برای حساب فعلی فعال نیست.';
    kind = 'warning';
    action = routeLink(ctx, '#/account', 'انتخاب فضای آموزشی', 'f3-course-button f3-course-button--secondary');
  }
  replace(root, node('div', { className: 'f3-course-page' }, stateView(title, description, { kind, action })));
}

export function renderIntegrationFailure(root, message) {
  replace(root, node('div', { className: 'f3-course-page' }, stateView(
    'اتصال بخش درس‌ها کامل نیست',
    message || 'این ماژول برای نمایش اطلاعات به context مشترک V3 نیاز دارد.',
    { kind: 'warning' },
  )));
}

export function renderCourseMissing(root, ctx) {
  replace(root, node('div', { className: 'f3-course-page' }, stateView(
    'درس پیدا نشد',
    'این مسیر دیگر به یک درس قابل‌دسترسی در فضای آموزشی فعال اشاره نمی‌کند. ممکن است درس حذف یا بایگانی شده باشد، یا دسترسی شما تغییر کرده باشد.',
    { kind: 'warning', action: routeLink(ctx, '#/courses', 'بازگشت به درس‌ها', 'f3-course-button f3-course-button--secondary') },
  )));
}

export function renderCoursesIndex(root, ctx, catalog, route = {}) {
  const page = node('section', { className: 'f3-course-page f3-course-index', attrs: { 'aria-labelledby': 'f3-courses-title' } });
  page.append(node('header', { className: 'f3-course-page-header' },
    node('p', { className: 'f3-course-eyebrow', text: 'یادگیری' }),
    node('h1', { className: 'f3-course-page-title', text: 'درس‌ها', attrs: { id: 'f3-courses-title' } }),
    node('p', { className: 'f3-course-page-subtitle', text: 'هر درس، نقطه ورود به جلسات و محتوای مرتبط با همان درس است.' }),
  ));

  if (!catalog.courses.length) {
    page.append(stateView('هنوز درسی ثبت نشده است', 'پس از ثبت درس در این فضای آموزشی، اینجا نمایش داده می‌شود.', { kind: 'neutral' }));
    replace(root, page);
    return;
  }

  const routeTerm = cleanText(route.term);
  const routeQuery = cleanText(route.q);
  const filter = node('form', { className: 'f3-course-filter', attrs: { role: 'search' } });
  const searchId = 'f3-course-search';
  const search = node('input', {
    className: 'f3-course-search-input',
    attrs: { id: searchId, type: 'search', value: routeQuery, placeholder: 'نام یا کد درس', autocomplete: 'off' },
  });
  const searchField = node('div', { className: 'f3-course-filter__search' }, node('label', { className: 'f3-course-filter__label', text: 'جست‌وجوی درس', attrs: { for: searchId } }), search);
  filter.append(searchField);

  let termSelect = null;
  if (catalog.terms.length > 1) {
    const termId = 'f3-course-term-filter';
    termSelect = node('select', { className: 'f3-course-select', attrs: { id: termId } }, node('option', { text: 'همه ترم‌ها', attrs: { value: '' } }));
    catalog.terms.forEach((term) => {
      const key = routeTermKey(term);
      termSelect.append(node('option', { text: term.name || term.key, attrs: { value: key, selected: key === routeTerm || null } }));
    });
    filter.append(node('div', { className: 'f3-course-filter__term' }, node('label', { className: 'f3-course-filter__label', text: 'ترم', attrs: { for: termId } }), termSelect));
  }
  filter.append(node('button', { className: 'f3-course-button f3-course-button--primary', text: 'اعمال', attrs: { type: 'submit' } }));
  page.append(filter);

  const resultMeta = node('div', { className: 'f3-course-result-meta', attrs: { 'aria-live': 'polite' } });
  const content = node('div', { className: 'f3-course-index__content' });
  page.append(resultMeta, content);

  const renderResults = (query, selectedTerm) => {
    const visible = filterCourses(catalog.courses, query, selectedTerm);
    resultMeta.textContent = `${number(ctx, visible.length)} درس`;
    content.replaceChildren();
    if (!visible.length) {
      content.append(stateView(
        'درسی با این فیلتر پیدا نشد',
        'عبارت جست‌وجو یا ترم انتخاب‌شده را تغییر دهید.',
        {
          kind: 'neutral',
          action: node('button', {
            className: 'f3-course-button f3-course-button--quiet', text: 'پاک کردن فیلترها', attrs: { type: 'button' },
            on: { click: () => navigate(ctx, '#/courses') },
          }),
        },
      ));
      return;
    }

    const selected = catalog.terms.find((term) => routeTermKey(term) === selectedTerm) || null;
    if (selected) {
      content.append(termSection(ctx, selected, visible));
      return;
    }

    if (catalog.terms.length > 1) {
      catalog.terms.forEach((term) => {
        const termCourses = visible.filter((course) => courseMatchesTerm(course, routeTermKey(term)));
        if (termCourses.length) content.append(termSection(ctx, term, termCourses));
      });
      const unassigned = visible.filter((course) => !course.terms.length);
      if (unassigned.length) {
        const section = node('section', { className: 'f3-course-term-section' }, node('h2', { className: 'f3-course-term-section__title', text: 'بدون ترم' }));
        const grid = node('div', { className: 'f3-course-card-grid' });
        unassigned.forEach((course) => grid.append(courseCard(ctx, course)));
        section.append(grid); content.append(section);
      }
      return;
    }

    const grid = node('div', { className: 'f3-course-card-grid' });
    visible.forEach((course) => grid.append(courseCard(ctx, course, catalog.terms[0] || null)));
    content.append(grid);
  };

  filter.addEventListener('submit', (event) => {
    event.preventDefault();
    navigate(ctx, indexHref({ q: cleanText(search.value), term: cleanText(termSelect?.value) }));
  });
  search.addEventListener('input', () => renderResults(search.value, cleanText(termSelect?.value || routeTerm)));
  if (termSelect) termSelect.addEventListener('change', () => navigate(ctx, indexHref({ q: cleanText(search.value), term: cleanText(termSelect.value) })));

  renderResults(routeQuery, routeTerm);
  replace(root, page);
}

function courseHeader(ctx, course) {
  const termNames = course.terms.map((term) => term.name || term.key).filter(Boolean);
  const sections = [...new Set(course.offerings.map((offering) => offering.sectionKey).filter(Boolean))];
  const header = node('header', { className: 'f3-course-context' },
    node('div', { className: 'f3-course-context__inner' },
      node('div', { className: 'f3-course-context__copy' },
        routeLink(ctx, '#/courses', 'همه درس‌ها', 'f3-course-back-link'),
        node('h1', { className: 'f3-course-context__title', text: course.title }),
        node('div', { className: 'f3-course-context__identity' },
          node('bdi', { className: 'f3-course-context__code', text: course.code, attrs: { dir: 'ltr' } }),
          termNames.length ? node('span', { text: termNames.slice(0, 2).join(' · ') }) : null,
          course.creditValue != null ? node('span', { text: `${number(ctx, course.creditValue)} واحد` }) : null,
          sections.length === 1 ? node('span', { text: `گروه ${sections[0]}` }) : null,
        ),
      ),
    ),
  );
  return header;
}

function capability(ctx, key, defaultValue = true) {
  const value = ctx.capabilities?.[key];
  return value == null ? defaultValue : value !== false;
}

function tabNav(ctx, course, activeTab) {
  const tabs = [
    ['overview', 'نمای کلی', true],
    ['sessions', 'جلسات', true],
    ['schedule', 'برنامه', capability(ctx, 'courseSchedule')],
    ['resources', 'منابع', capability(ctx, 'courseResources')],
    ['assessments', 'آزمون‌ها', capability(ctx, 'courseAssessments')],
    ['grades', 'نمرات', capability(ctx, 'courseGrades')],
    ['announcements', 'اطلاعیه‌ها', capability(ctx, 'courseAnnouncements', false)],
  ].filter(([, , enabled]) => enabled);
  const nav = node('nav', { className: 'f3-course-tabs', attrs: { 'aria-label': `بخش‌های درس ${course.title}` } });
  tabs.forEach(([key, label]) => {
    const link = routeLink(ctx, detailHref(course.code, key), label, 'f3-course-tab');
    if (key === activeTab) link.setAttribute('aria-current', 'page');
    nav.append(link);
  });
  return nav;
}

function summarySection(title, eyebrow = '') {
  return node('section', { className: 'f3-course-summary-card' },
    eyebrow ? node('p', { className: 'f3-course-summary-card__eyebrow', text: eyebrow }) : null,
    node('h2', { className: 'f3-course-summary-card__title', text: title }),
  );
}

function partState(part, emptyText, errorText) {
  if (!part || part.state === 'loading') return node('div', { className: 'f3-course-inline-state', text: 'در حال دریافت…' });
  if (part.state === 'denied') return node('div', { className: 'f3-course-inline-state', text: 'این بخش برای حساب فعلی در دسترس نیست.' });
  if (part.state === 'error') return node('div', { className: 'f3-course-inline-state', text: errorText });
  if (part.state === 'ready' && !part.data?.length) return node('div', { className: 'f3-course-inline-state', text: emptyText });
  return null;
}

function renderNextSession(ctx, course) {
  const section = summarySection('جلسه بعدی', 'بعدی');
  const session = findNearestSession(course);
  if (!session) {
    section.append(node('p', { className: 'f3-course-summary-card__empty', text: 'جلسه پیش‌روی زمان‌دار برای این درس ثبت نشده است.' }));
    return section;
  }
  const when = dateTime(ctx, session.startsAt);
  section.classList.add('f3-course-summary-card--featured');
  section.append(node('strong', { className: 'f3-course-summary-card__primary', text: session.title }),
    when ? node('p', { className: 'f3-course-summary-card__meta', text: when }) : node('p', { className: 'f3-course-summary-card__meta', text: 'زمان دقیق در دسترس نیست.' }),
  );
  const details = [session.term?.name, session.sectionKey ? `گروه ${session.sectionKey}` : '', statusText(ctx, session.status)].filter(Boolean);
  if (details.length) section.append(node('p', { className: 'f3-course-summary-card__meta', text: details.join(' · ') }));
  section.append(routeLink(ctx, detailHref(course.code, 'sessions'), 'مشاهده جلسات', 'f3-course-inline-link'));
  return section;
}

function renderResourceSummary(ctx, course, part) {
  const section = summarySection('منبع تازه', 'یادگیری');
  const state = partState(part, 'برای این درس هنوز منبعی منتشر نشده است.', 'منابع این درس فعلاً دریافت نشدند.');
  if (state) { section.append(state); return section; }
  const resource = latestResource(part.data);
  section.append(node('strong', { className: 'f3-course-summary-card__primary', text: cleanText(resource.title) || 'منبع آموزشی' }));
  const meta = [resourceType(resource.type_key), resource.topic ? cleanText(resource.topic) : '', resource.current_version_no ? `نسخه ${number(ctx, resource.current_version_no)}` : ''].filter(Boolean);
  if (meta.length) section.append(node('p', { className: 'f3-course-summary-card__meta', text: meta.join(' · ') }));
  section.append(routeLink(ctx, detailHref(course.code, 'resources'), 'منابع درس', 'f3-course-inline-link'));
  return section;
}

function renderAssessmentSummary(ctx, course, part) {
  const section = summarySection('آزمون در دسترس', 'ارزیابی');
  const state = partState(part, 'آزمونی برای این درس منتشر نشده است.', 'فهرست آزمون‌های این درس فعلاً دریافت نشد.');
  if (state) { section.append(state); return section; }
  const assessment = firstAssessment(part.data);
  section.append(node('strong', { className: 'f3-course-summary-card__primary', text: cleanText(assessment.title) || 'آزمون' }));
  const meta = [assessmentType(assessment.assessment_kind), assessment.max_attempts != null ? `حداکثر ${number(ctx, assessment.max_attempts)} تلاش` : ''].filter(Boolean);
  if (meta.length) section.append(node('p', { className: 'f3-course-summary-card__meta', text: meta.join(' · ') }));
  section.append(routeLink(ctx, detailHref(course.code, 'assessments'), 'آزمون‌های درس', 'f3-course-inline-link'));
  return section;
}

function renderGradeSummary(ctx, course, part) {
  const section = summarySection('آخرین نمره منتشرشده', 'عملکرد');
  const state = partState(part, 'نمره منتشرشده‌ای برای این درس پیدا نشد.', 'نمرات این درس فعلاً دریافت نشدند.');
  if (state) { section.append(state); return section; }
  const grade = latestGrade(part.data, course);
  if (!grade) {
    section.append(node('p', { className: 'f3-course-summary-card__empty', text: 'نمره منتشرشده‌ای برای این درس پیدا نشد.' }));
    return section;
  }
  const score = grade.score != null
    ? `${number(ctx, grade.score)}${grade.max_score != null ? ` از ${number(ctx, grade.max_score)}` : ''}`
    : '';
  section.append(node('strong', { className: 'f3-course-summary-card__primary', text: cleanText(grade.item_title || grade.gradebook_title) || 'نمره' }));
  if (score) section.append(node('div', { className: 'f3-course-score', text: score }));
  const updated = dateOnly(ctx, grade.updated_at);
  if (updated) section.append(node('p', { className: 'f3-course-summary-card__meta', text: `به‌روزرسانی ${updated}` }));
  section.append(routeLink(ctx, detailHref(course.code, 'grades'), 'نمرات درس', 'f3-course-inline-link'));
  return section;
}

function renderCourseMetadata(ctx, course) {
  const section = node('section', { className: 'f3-course-metadata', attrs: { 'aria-labelledby': 'f3-course-metadata-title' } },
    node('div', { className: 'f3-course-section-heading' },
      node('h2', { text: 'مشخصات درس', attrs: { id: 'f3-course-metadata-title' } }),
      node('p', { text: 'اطلاعات ثبت‌شده در فضای آموزشی' }),
    ),
  );
  const grid = node('div', { className: 'f3-course-metadata__grid' });
  grid.append(metaItem('کد درس', course.code, { ltr: true }));
  if (course.creditValue != null) grid.append(metaItem('تعداد واحد', number(ctx, course.creditValue)));
  if (course.terms.length) grid.append(metaItem('ترم', course.terms.map((term) => term.name || term.key).filter(Boolean).join('، ')));
  const sections = [...new Set(course.offerings.map((offering) => offering.sectionKey).filter(Boolean))];
  if (sections.length) grid.append(metaItem('گروه', sections.join('، '), { ltr: true }));
  if (course.instructors.length) grid.append(metaItem('استاد', course.instructors.join('، ')));
  section.append(grid);
  return section;
}

function renderOverview(root, ctx, course, summary) {
  const content = node('div', { className: 'f3-course-overview' });
  const allEmpty = !findNearestSession(course)
    && summary.resources?.state === 'ready' && !summary.resources.data.length
    && summary.assessments?.state === 'ready' && !summary.assessments.data.length
    && summary.grades?.state === 'ready' && !latestGrade(summary.grades.data, course);
  if (allEmpty) {
    content.append(stateView('نمای کلی این درس هنوز خلوت است', 'جلسه پیش‌رو، منبع، آزمون یا نمره‌ای برای نمایش در این بخش ثبت نشده است.', { kind: 'neutral' }));
  }
  const grid = node('div', { className: 'f3-course-summary-grid' },
    renderNextSession(ctx, course),
    renderResourceSummary(ctx, course, summary.resources),
    renderAssessmentSummary(ctx, course, summary.assessments),
    renderGradeSummary(ctx, course, summary.grades),
  );
  content.append(grid, renderCourseMetadata(ctx, course));
  maybeMountOverviewAnnouncement(ctx, content, course);
  root.append(content);
}

function maybeMountOverviewAnnouncement(ctx, content, course) {
  const hasSlot = typeof ctx.ui?.hasSlot === 'function' && ctx.ui.hasSlot('course.overview.announcement');
  if (!capability(ctx, 'courseAnnouncements', false) || !hasSlot) return;
  const host = node('section', { className: 'f3-course-integration-slot', attrs: { 'data-f3-course-slot': 'course.overview.announcement' } });
  content.append(host);
  mountSlot(ctx, 'course.overview.announcement', host, course);
}

function renderSessions(root, ctx, course) {
  const section = node('section', { className: 'f3-course-sessions', attrs: { 'aria-labelledby': 'f3-course-sessions-title' } },
    node('div', { className: 'f3-course-section-heading' },
      node('h2', { text: 'جلسات', attrs: { id: 'f3-course-sessions-title' } }),
      node('p', { text: 'جلسات ثبت‌شده این درس به ترتیب زمان و شماره جلسه' }),
    ),
  );
  if (!course.sessions.length) {
    section.append(stateView('جلسه‌ای ثبت نشده است', 'برای این درس هنوز جلسه‌ای در ساختار آموزشی ثبت نشده است.', { kind: 'neutral' }));
    root.append(section); return;
  }
  const next = findNearestSession(course);
  const list = node('div', { className: 'f3-course-session-list' });
  course.sessions.forEach((session) => {
    const status = statusText(ctx, session.status);
    const when = dateTime(ctx, session.startsAt);
    const row = node('article', { className: `f3-course-session-row ${next?.key === session.key ? 'f3-course-session-row--next' : ''}` },
      node('div', { className: 'f3-course-session-row__sequence' },
        node('span', { text: session.sequence != null ? `جلسه ${number(ctx, session.sequence)}` : 'جلسه' }),
        next?.key === session.key ? chip('بعدی', 'attention') : null,
      ),
      node('div', { className: 'f3-course-session-row__main' },
        node('h3', { text: session.title }),
        node('p', { text: when || 'زمان جلسه ثبت نشده است.' }),
        (session.term?.name || session.sectionKey) ? node('p', { className: 'f3-course-session-row__context', text: [session.term?.name, session.sectionKey ? `گروه ${session.sectionKey}` : ''].filter(Boolean).join(' · ') }) : null,
      ),
      status ? chip(status, status === 'لغوشده' ? 'danger' : status === 'برگزارش‌شده' ? 'success' : 'neutral') : null,
    );
    list.append(row);
  });
  section.append(list); root.append(section);
}

function slotApi(ctx) {
  if (typeof ctx.ui?.mountSlot === 'function') return ctx.ui.mountSlot.bind(ctx.ui);
  if (typeof ctx.ui?.slots?.mount === 'function') return ctx.ui.slots.mount.bind(ctx.ui.slots);
  return null;
}

function mountSlot(ctx, slotName, host, course) {
  const mount = slotApi(ctx);
  if (!mount) return false;
  try {
    const result = mount(slotName, host, {
      course: {
        id: course.id,
        code: course.code,
        title: course.title,
        offeringIds: [...course.offeringIds],
        terms: course.terms.map((term) => ({ id: term.id, key: term.key, name: term.name })),
      },
      signal: ctx.signal,
    });
    if (result && typeof result.catch === 'function') result.catch(() => renderSlotFallback(host, ctx, slotName, course));
    return result !== false;
  } catch (_error) {
    return false;
  }
}

function slotFallbackConfig(slotName, courseCode) {
  const map = {
    'course.schedule': ['برنامه درس', 'نمای یکپارچه برنامه این درس هنوز به ماژول برنامه متصل نشده است.', destinationHref('schedule', courseCode), 'باز کردن برنامه'],
    'course.resources': ['منابع درس', 'نمای کامل منابع این درس هنوز به ماژول یادگیری متصل نشده است.', destinationHref('resources', courseCode), 'باز کردن منابع'],
    'course.assessments': ['آزمون‌های درس', 'نمای کامل آزمون‌های این درس هنوز به ماژول ارزیابی متصل نشده است.', destinationHref('assessments', courseCode), 'باز کردن آزمون‌ها'],
    'course.grades': ['نمرات درس', 'نمای کامل نمرات این درس هنوز به ماژول پیشرفت متصل نشده است.', destinationHref('grades', courseCode), 'باز کردن نمرات'],
    'course.announcements': ['اطلاعیه‌های درس', 'نمای اطلاعیه‌های درس فقط پس از وجود ارتباط canonical بین اطلاعیه و درس فعال می‌شود.', destinationHref('announcements', courseCode), 'باز کردن اطلاعیه‌ها'],
  };
  return map[slotName];
}

function renderSlotFallback(host, ctx, slotName, course) {
  const [title, description, href, actionLabel] = slotFallbackConfig(slotName, course.code);
  host.replaceChildren(stateView(title, description, { kind: 'neutral', action: routeLink(ctx, href, actionLabel, 'f3-course-button f3-course-button--secondary') }));
}

function renderSlotDestination(root, ctx, course, slotName) {
  const host = node('section', { className: 'f3-course-integration-slot', attrs: { 'data-f3-course-slot': slotName, 'aria-live': 'polite' } });
  root.append(host);
  if (!mountSlot(ctx, slotName, host, course)) renderSlotFallback(host, ctx, slotName, course);
}

export function renderCourseDetail(root, ctx, course, summary, route = {}) {
  const tab = VALID_TABS.has(route.tab) ? route.tab : 'overview';
  const page = node('article', { className: 'f3-course-page f3-course-detail' }, courseHeader(ctx, course), tabNav(ctx, course, tab));
  const body = node('div', { className: 'f3-course-detail__body' });
  page.append(body);

  if (tab === 'sessions') renderSessions(body, ctx, course);
  else if (tab === 'schedule') renderSlotDestination(body, ctx, course, 'course.schedule');
  else if (tab === 'resources') renderSlotDestination(body, ctx, course, 'course.resources');
  else if (tab === 'assessments') renderSlotDestination(body, ctx, course, 'course.assessments');
  else if (tab === 'grades') renderSlotDestination(body, ctx, course, 'course.grades');
  else if (tab === 'announcements') renderSlotDestination(body, ctx, course, 'course.announcements');
  else renderOverview(body, ctx, course, summary || {
    resources: { state: 'loading', data: [] }, assessments: { state: 'loading', data: [] }, grades: { state: 'loading', data: [] },
  });

  replace(root, page);
}
