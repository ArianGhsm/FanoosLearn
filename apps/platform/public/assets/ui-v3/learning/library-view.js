import {
  RESOURCE_TYPE_FILTERS,
  appendHighlightedText,
  catalogAccessPresentation,
  isTrustworthyCount,
  safeText,
} from './learning-contract.js';
import { MODULE_ID, button, formatDate, formatNumber, node } from './runtime.js';

function pageFrame(state) {
  const root = node('section', `f3-learning-page${state.embedded ? ' f3-learning-page--embedded' : ''}`);
  root.dataset.module = MODULE_ID;
  root.setAttribute('aria-labelledby', state.embedded ? 'f3-learning-embedded-title' : 'f3-learning-title');
  const hero = node('header', 'f3-learning-hero');
  const copy = node('div', 'f3-learning-hero__copy');
  copy.append(
    node('div', 'f3-learning-eyebrow', state.embedded ? 'منابع این درس' : 'کتابخانه یادگیری'),
  );
  const title = node('h1', 'f3-learning-title', state.embedded ? (state.embeddedCourseLabel || 'منابع درس') : 'یادگیری و منابع');
  title.id = state.embedded ? 'f3-learning-embedded-title' : 'f3-learning-title';
  copy.append(title, node('p', 'f3-learning-subtitle', state.embedded
    ? 'جزوه‌ها، خلاصه‌ها، سؤال‌ها و منابع منتشرشده این درس.'
    : 'منابع درسی را جست‌وجو و فیلتر کنید و نسخه‌های مجاز را از مسیر امن دریافت کنید.'));
  hero.append(copy);
  root.append(hero);
  return root;
}

export function statePanel(title, body, actionLabel, onAction, tone = 'neutral') {
  const panel = node('div', `f3-learning-state f3-learning-state--${tone}`);
  panel.setAttribute('role', tone === 'danger' ? 'alert' : 'status');
  panel.append(node('h2', 'f3-learning-state__title', title), node('p', 'f3-learning-state__body', body));
  if (actionLabel && onAction) {
    const action = button(actionLabel, 'f3-learning-button f3-learning-button--secondary');
    action.addEventListener('click', onAction);
    panel.append(action);
  }
  return panel;
}

function renderSkeleton(root) {
  const skeleton = node('div', 'f3-learning-skeleton-list');
  skeleton.setAttribute('aria-hidden', 'true');
  for (let i = 0; i < 5; i += 1) {
    const row = node('div', 'f3-learning-skeleton');
    row.append(node('span', 'f3-learning-skeleton__short'), node('span', 'f3-learning-skeleton__long'), node('span', 'f3-learning-skeleton__mid'));
    skeleton.append(row);
  }
  root.append(skeleton);
}

function filtersActive(state) {
  return Boolean(state.filters.q || state.filters.type || (!state.embedded && state.filters.courseId));
}

function selectControl(labelText, emptyText, options, value, onChange) {
  const field = node('label', 'f3-learning-filter');
  field.append(node('span', 'f3-learning-filter__label', labelText));
  const select = node('select', 'f3-learning-filter__select');
  const empty = node('option', '', emptyText);
  empty.value = '';
  select.append(empty);
  options.forEach(([key, label]) => {
    const option = node('option', '', label);
    option.value = key;
    option.selected = key === value;
    select.append(option);
  });
  select.addEventListener('change', () => onChange(select.value));
  field.append(select);
  return field;
}

function typeSelect(state, refresh) {
  return selectControl('نوع منبع', 'همه نوع‌ها', RESOURCE_TYPE_FILTERS, state.filters.type, (value) => {
    state.filters.type = value;
    refresh();
  });
}

function buildDesktopFilters(state, refresh) {
  const bar = node('div', 'f3-learning-filterbar');
  if (!state.embedded) {
    bar.append(selectControl('درس', 'همه درس‌ها', state.courses.map((c) => [c.id, c.label]), state.filters.courseId, (value) => {
      state.filters.courseId = value;
      refresh();
    }));
  } else {
    bar.classList.add('f3-learning-filterbar--embedded');
  }
  bar.append(typeSelect(state, refresh));
  if (filtersActive(state)) {
    const reset = button(state.embedded ? 'پاک کردن' : 'حذف فیلترها', 'f3-learning-button f3-learning-button--quiet');
    reset.addEventListener('click', () => {
      state.filters = { q: '', type: '', courseId: state.embedded ? state.embeddedCourseId : '' };
      refresh();
    });
    bar.append(reset);
  }
  return bar;
}

function openFilterSheet(state, refresh, trigger) {
  if (state.sheetOpen) return;
  state.sheetOpen = true;
  state.lastFocus = trigger;
  const backdrop = node('div', 'f3-learning-sheet-backdrop');
  const sheet = node('section', 'f3-learning-sheet');
  sheet.setAttribute('role', 'dialog');
  sheet.setAttribute('aria-modal', 'true');
  sheet.setAttribute('aria-labelledby', 'f3-learning-sheet-title');
  const header = node('header', 'f3-learning-sheet__header');
  const title = node('h2', 'f3-learning-sheet__title', 'فیلتر منابع');
  title.id = 'f3-learning-sheet-title';
  const close = button('بستن', 'f3-learning-sheet__close');
  header.append(title, close);
  const body = node('div', 'f3-learning-sheet__body');
  let courseValue = state.filters.courseId;
  let typeValue = state.filters.type;
  if (!state.embedded) body.append(selectControl('درس', 'همه درس‌ها', state.courses.map((c) => [c.id, c.label]), courseValue, (v) => { courseValue = v; }));
  body.append(selectControl('نوع منبع', 'همه نوع‌ها', RESOURCE_TYPE_FILTERS, typeValue, (v) => { typeValue = v; }));
  const footer = node('footer', 'f3-learning-sheet__footer');
  const reset = button('حذف فیلترها', 'f3-learning-button f3-learning-button--quiet');
  const apply = button('نمایش نتایج', 'f3-learning-button f3-learning-button--primary');
  footer.append(reset, apply);
  sheet.append(header, body, footer);
  backdrop.append(sheet);
  document.body.append(backdrop);
  const dismiss = () => {
    state.sheetOpen = false;
    backdrop.remove();
    state.lastFocus?.focus?.();
  };
  close.addEventListener('click', dismiss);
  backdrop.addEventListener('click', (event) => { if (event.target === backdrop) dismiss(); });
  reset.addEventListener('click', () => {
    state.filters.type = '';
    state.filters.courseId = state.embedded ? state.embeddedCourseId : '';
    dismiss();
    refresh();
  });
  apply.addEventListener('click', () => {
    state.filters.type = typeValue;
    state.filters.courseId = state.embedded ? state.embeddedCourseId : courseValue;
    dismiss();
    refresh();
  });
  const focusables = () => [...sheet.querySelectorAll('button, select, input, [tabindex]:not([tabindex="-1"])')].filter((el) => !el.disabled && !el.hidden);
  sheet.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { event.preventDefault(); dismiss(); return; }
    if (event.key !== 'Tab') return;
    const list = focusables();
    if (!list.length) return;
    const first = list[0]; const last = list[list.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  close.focus();
}

function buildSearch(state, refresh) {
  const section = node('section', 'f3-learning-discovery');
  section.setAttribute('aria-label', 'جست‌وجو و فیلتر منابع');
  const form = node('form', 'f3-learning-search');
  form.setAttribute('role', 'search');
  const label = node('label', 'f3-learning-sr-only', 'جست‌وجوی منابع');
  label.htmlFor = 'f3-learning-search-input';
  const inputWrap = node('div', 'f3-learning-search__field');
  const input = node('input', 'f3-learning-search__input');
  input.id = 'f3-learning-search-input';
  input.type = 'search';
  input.name = 'q';
  input.autocomplete = 'off';
  input.placeholder = 'عنوان، موضوع یا توضیحات منبع…';
  input.value = state.filters.q;
  const clear = button('پاک کردن', 'f3-learning-search__clear');
  clear.hidden = !state.filters.q;
  clear.setAttribute('aria-label', 'پاک کردن جست‌وجو');
  clear.addEventListener('click', () => {
    input.value = '';
    state.filters.q = '';
    refresh({ focusSearch: true });
  });
  inputWrap.append(label, input, clear);
  const submit = button('جست‌وجو', 'f3-learning-button f3-learning-button--primary', 'submit');
  const mobileFilter = button('فیلترها', 'f3-learning-button f3-learning-button--filter');
  mobileFilter.setAttribute('aria-haspopup', 'dialog');
  mobileFilter.addEventListener('click', () => openFilterSheet(state, refresh, mobileFilter));
  form.append(inputWrap, submit, mobileFilter);
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const q = safeText(input.value, 120);
    if (q && q.length < 2) {
      input.setCustomValidity('برای جست‌وجو حداقل دو نویسه وارد کنید.');
      input.reportValidity();
      input.setCustomValidity('');
      return;
    }
    state.filters.q = q;
    refresh();
  });
  section.append(form, buildDesktopFilters(state, refresh));
  return section;
}

function resourceCard(state, resource, openDetail, compact = false) {
  const article = node('article', `f3-learning-resource${compact ? ' f3-learning-resource--featured' : ''}`);
  const leading = node('div', 'f3-learning-resource__leading');
  leading.append(node('span', 'f3-learning-resource__type', resource.typeLabel));
  const title = node('h3', 'f3-learning-resource__title');
  appendHighlightedText(title, resource.title, state.filters.q);
  leading.append(title);
  const context = node('div', 'f3-learning-resource__context');
  if (resource.courseTitle) context.append(node('span', 'f3-learning-resource__course', resource.courseTitle));
  if (resource.topic) {
    const topic = node('span', 'f3-learning-resource__topic');
    appendHighlightedText(topic, resource.topic, state.filters.q);
    context.append(topic);
  }
  if (context.childNodes.length) leading.append(context);
  const meta = node('div', 'f3-learning-resource__meta');
  if (resource.version) meta.append(node('span', '', `نسخه ${formatNumber(state.ctx, resource.version)}`));
  const date = formatDate(state.ctx, resource.updatedAt);
  if (date) meta.append(node('span', '', `به‌روزرسانی ${date}`));
  const access = catalogAccessPresentation(resource);
  meta.append(node('span', `f3-learning-access f3-learning-access--${access.tone}`, access.label));
  const action = button('جزئیات', 'f3-learning-button f3-learning-button--detail');
  action.addEventListener('click', () => openDetail(resource, action));
  article.append(leading, meta, action);
  return article;
}

function recentSection(state, openDetail) {
  if (state.embedded || filtersActive(state) || !state.resources.length) return null;
  const section = node('section', 'f3-learning-recent');
  const header = node('div', 'f3-learning-section-heading');
  const copy = node('div');
  copy.append(node('p', 'f3-learning-section-kicker', 'بر اساس آخرین به‌روزرسانی'), node('h2', 'f3-learning-section-title', 'تازه‌های کتابخانه'));
  header.append(copy);
  section.append(header);
  const grid = node('div', 'f3-learning-recent__grid');
  state.resources.slice(0, 4).forEach((resource) => grid.append(resourceCard(state, resource, openDetail, true)));
  section.append(grid);
  return section;
}

function librarySection(state, refresh, openDetail) {
  const section = node('section', 'f3-learning-library');
  const heading = node('div', 'f3-learning-section-heading');
  const copy = node('div');
  copy.append(node('p', 'f3-learning-section-kicker', state.embedded ? 'منابع منتشرشده' : 'همه منابع'), node('h2', 'f3-learning-section-title', state.embedded ? 'منابع این درس' : 'کتابخانه'));
  heading.append(copy);
  if (isTrustworthyCount(state.resources)) heading.append(node('span', 'f3-learning-count', `${formatNumber(state.ctx, state.resources.length)} منبع`));
  section.append(heading);
  if (!state.resources.length) {
    const active = filtersActive(state);
    section.append(statePanel(active ? 'منبعی با این فیلتر پیدا نشد' : 'هنوز منبعی منتشر نشده است', active ? 'عبارت جست‌وجو یا فیلترها را تغییر دهید.' : 'پس از انتشار منابع در این فضای آموزشی، این بخش تکمیل می‌شود.', active ? 'حذف فیلترها' : null, active ? () => {
      state.filters = { q: '', type: '', courseId: state.embedded ? state.embeddedCourseId : '' };
      refresh();
    } : null));
    return section;
  }
  const list = node('div', 'f3-learning-list');
  state.resources.forEach((resource) => list.append(resourceCard(state, resource, openDetail)));
  section.append(list);
  if (state.resources.length === 100) section.append(node('p', 'f3-learning-cap-note', 'برای رسیدن سریع‌تر به منبع موردنظر از جست‌وجو یا فیلتر درس و نوع منبع استفاده کنید.'));
  return section;
}

export function renderLibrary(state, host, refresh, openDetail) {
  host.replaceChildren();
  const root = pageFrame(state);
  root.append(buildSearch(state, refresh));
  if (state.partialCourseError && !state.embedded) root.append(statePanel('فهرست درس‌ها کامل نشد', 'منابع قابل مشاهده‌اند، اما فیلتر درس فعلاً در دسترس نیست.', 'بررسی دوباره', () => refresh({ reloadCourses: true }), 'warning'));
  if (state.loading) {
    renderSkeleton(root);
    host.append(root);
    return;
  }
  if (state.error) {
    root.append(statePanel('کتابخانه دریافت نشد', 'اتصال به فهرست منابع کامل نشد. می‌توانید دوباره تلاش کنید.', 'تلاش دوباره', () => refresh(), 'danger'));
    host.append(root);
    return;
  }
  const recent = recentSection(state, openDetail);
  if (recent) root.append(recent);
  root.append(librarySection(state, refresh, openDetail));
  host.append(root);
}
