import {
  append,
  asText,
  clear,
  createElement,
  focusSafely,
  setAttributes,
} from './dom.js';

export const ICON_SPRITE_URL = new URL('./icons.svg', import.meta.url).href;

export const ICON_IDS = Object.freeze({
  home: 'f3-icon-home',
  courses: 'f3-icon-courses',
  calendar: 'f3-icon-calendar',
  learning: 'f3-icon-learning',
  assessment: 'f3-icon-assessment',
  grades: 'f3-icon-grades',
  announcement: 'f3-icon-announcement',
  notification: 'f3-icon-notification',
  forms: 'f3-icon-forms',
  payment: 'f3-icon-payment',
  workspace: 'f3-icon-workspace',
  account: 'f3-icon-account',
  search: 'f3-icon-search',
  chevron: 'f3-icon-chevron',
  arrow: 'f3-icon-arrow',
  close: 'f3-icon-close',
  menu: 'f3-icon-menu',
  more: 'f3-icon-more',
  filter: 'f3-icon-filter',
  download: 'f3-icon-download',
  lock: 'f3-icon-lock',
  externalLink: 'f3-icon-external-link',
  success: 'f3-icon-success',
  warning: 'f3-icon-warning',
  error: 'f3-icon-error',
  info: 'f3-icon-info',
});

const DIRECTIONAL_ICONS = new Set(['chevron', 'arrow']);
const TONES = new Set(['neutral', 'success', 'warning', 'danger', 'info', 'accent', 'attention']);
const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

let sequence = 0;
function uid(prefix = 'f3') {
  sequence += 1;
  return `${prefix}-${sequence}`;
}

function eventOptions(signal, extra = {}) {
  return signal ? { ...extra, signal } : extra;
}

function tone(value) {
  return TONES.has(value) ? value : 'neutral';
}

export function icon(name, options = {}) {
  const symbolId = ICON_IDS[name];
  if (!symbolId) throw new RangeError(`Unknown FANOOS icon: ${asText(name)}`);

  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  const classes = ['f3-icon'];
  if (options.size === 'sm') classes.push('f3-icon--sm');
  if (options.size === 'lg') classes.push('f3-icon--lg');
  if (options.directional ?? DIRECTIONAL_ICONS.has(name)) classes.push('f3-icon--directional');
  if (options.className) classes.push(asText(options.className));
  svg.setAttribute('class', classes.join(' '));
  svg.setAttribute('focusable', 'false');

  if (options.label) {
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', asText(options.label));
  } else {
    svg.setAttribute('aria-hidden', 'true');
  }

  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  use.setAttribute('href', `${ICON_SPRITE_URL}#${symbolId}`);
  svg.append(use);
  return svg;
}

export function button(label, options = {}) {
  const variant = ['primary', 'secondary', 'quiet', 'danger'].includes(options.variant) ? options.variant : 'secondary';
  const classes = ['f3-button', `f3-button--${variant}`];
  if (options.block) classes.push('f3-button--block');
  if (options.compact) classes.push('f3-button--compact');
  if (options.className) classes.push(asText(options.className));

  const node = createElement('button', {
    className: classes.join(' '),
    attrs: {
      type: options.type || 'button',
      disabled: options.disabled || null,
      'aria-busy': options.busy === true ? 'true' : null,
      ...options.attrs,
    },
  });
  if (options.icon && options.iconPosition !== 'end') node.append(icon(options.icon, { size: 'sm' }));
  node.append(createElement('span', { text: label }));
  if (options.icon && options.iconPosition === 'end') node.append(icon(options.icon, { size: 'sm' }));
  if (typeof options.onClick === 'function') node.addEventListener('click', options.onClick, eventOptions(options.signal));
  return node;
}

export function iconButton(name, label, options = {}) {
  if (!label) throw new TypeError('Icon buttons require an accessible label');
  const classes = ['f3-icon-button'];
  if (options.quiet) classes.push('f3-icon-button--quiet');
  if (options.className) classes.push(asText(options.className));

  const node = createElement('button', {
    className: classes.join(' '),
    attrs: {
      type: options.type || 'button',
      'aria-label': label,
      title: options.title || null,
      disabled: options.disabled || null,
      ...options.attrs,
    },
  }, icon(name, { size: options.size }));
  if (typeof options.onClick === 'function') node.addEventListener('click', options.onClick, eventOptions(options.signal));
  return node;
}

export function badge(label, options = {}) {
  const node = createElement('span', {
    className: `f3-badge${options.className ? ` ${asText(options.className)}` : ''}`,
    attrs: { 'data-tone': tone(options.tone) },
  });
  if (options.icon) node.append(icon(options.icon, { size: 'sm' }));
  node.append(createElement('span', { text: label }));
  return node;
}

export function chip(label, options = {}) {
  const interactive = typeof options.onClick === 'function';
  const node = createElement(interactive ? 'button' : 'span', {
    className: `f3-chip${options.className ? ` ${asText(options.className)}` : ''}`,
    attrs: {
      type: interactive ? 'button' : null,
      'data-tone': tone(options.tone),
      'data-selected': options.selected === true ? 'true' : null,
      'aria-pressed': interactive ? (options.selected === true ? 'true' : 'false') : null,
      ...options.attrs,
    },
  });
  if (options.icon) node.append(icon(options.icon, { size: 'sm' }));
  node.append(createElement('span', { text: label }));
  if (interactive) node.addEventListener('click', options.onClick, eventOptions(options.signal));
  return node;
}

export function field(options = {}) {
  const control = options.control || createElement(options.multiline ? 'textarea' : 'input', {
    className: options.multiline ? 'f3-field__control' : 'f3-input',
    attrs: options.inputAttrs || {},
  });
  if (control.tagName === 'SELECT' && !control.classList.contains('f3-select')) control.classList.add('f3-select');
  const hasName = Boolean(options.label || control.getAttribute('aria-label') || control.getAttribute('aria-labelledby'));
  if (!hasName) throw new TypeError('Fields require a visible label or an accessible name');

  const controlId = control.id || options.id || uid('f3-field');
  control.id = controlId;
  const root = createElement('div', { className: `f3-field${options.className ? ` ${asText(options.className)}` : ''}` });
  let label = null;

  if (options.label) {
    label = createElement('label', { className: 'f3-field__label', attrs: { for: controlId } }, asText(options.label));
    if (options.required) label.append(createElement('span', { className: 'f3-field__required', text: ' *', attrs: { 'aria-hidden': 'true' } }));
    root.append(label);
  }
  root.append(control);

  const describedBy = [];
  if (options.hint) {
    const id = uid('f3-hint');
    describedBy.push(id);
    root.append(createElement('p', { className: 'f3-field__hint', text: options.hint, attrs: { id } }));
  }
  if (options.error) {
    const id = uid('f3-error');
    describedBy.push(id);
    control.setAttribute('aria-invalid', 'true');
    root.append(createElement('p', { className: 'f3-field__error', text: options.error, attrs: { id } }));
  }
  if (describedBy.length) control.setAttribute('aria-describedby', describedBy.join(' '));
  if (options.required) control.required = true;
  return { root, control, label };
}

export function searchField(options = {}) {
  const input = createElement('input', {
    className: 'f3-input',
    attrs: {
      id: options.id || uid('f3-search'),
      type: 'search',
      inputmode: 'search',
      autocomplete: options.autocomplete || 'off',
      placeholder: options.placeholder || '',
      'aria-label': options.label || 'جست‌وجو',
      ...options.attrs,
    },
  });
  const root = createElement('div', { className: `f3-search${options.className ? ` ${asText(options.className)}` : ''}` },
    createElement('span', { className: 'f3-search__icon' }, icon('search', { size: 'sm' })),
    input,
  );

  let clearButton = null;
  const syncClear = () => { if (clearButton) clearButton.hidden = input.value.length === 0; };
  if (options.clearable !== false) {
    clearButton = iconButton('close', options.clearLabel || 'پاک کردن جست‌وجو', {
      quiet: true,
      className: 'f3-search__clear',
      signal: options.signal,
      onClick: () => {
        input.value = '';
        syncClear();
        input.dispatchEvent(new Event('input', { bubbles: true }));
        focusSafely(input);
      },
    });
    root.append(clearButton);
    syncClear();
  }

  input.addEventListener('input', (event) => {
    syncClear();
    if (typeof options.onInput === 'function') options.onInput(event);
  }, eventOptions(options.signal));
  if (typeof options.onSubmit === 'function') {
    input.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      options.onSubmit(input.value, event);
    }, eventOptions(options.signal));
  }

  return {
    root,
    input,
    clearButton,
    setValue(value) { input.value = asText(value); syncClear(); },
    clear() { input.value = ''; syncClear(); },
    focus() { return focusSafely(input); },
  };
}

export function pageHeader(options = {}) {
  const copy = createElement('div', { className: 'f3-page-header__copy' });
  if (options.eyebrow) copy.append(createElement('div', { className: 'f3-page-header__eyebrow', text: options.eyebrow }));
  copy.append(createElement(options.headingTag || 'h1', { className: 'f3-page-header__title', text: options.title || '' }));
  if (options.subtitle) copy.append(createElement('p', { className: 'f3-page-header__subtitle', text: options.subtitle }));
  const root = createElement('header', { className: `f3-page-header${options.className ? ` ${asText(options.className)}` : ''}` }, copy);
  if (options.actions) root.append(createElement('div', { className: 'f3-page-header__actions' }, options.actions));
  return root;
}

export function surface(options = {}) {
  const classes = ['f3-surface'];
  if (['subtle', 'strong', 'hero'].includes(options.variant)) classes.push(`f3-surface--${options.variant}`);
  if (options.className) classes.push(asText(options.className));
  const root = createElement(options.tag || 'section', { className: classes.join(' '), attrs: options.attrs || {} });
  if (options.header) root.append(createElement('div', { className: 'f3-surface__header' }, options.header));
  if (options.body != null) root.append(createElement('div', { className: 'f3-surface__body' }, options.body));
  if (options.footer) root.append(createElement('div', { className: 'f3-surface__footer' }, options.footer));
  return root;
}

export function dataRow(options = {}) {
  const main = createElement('div', { className: 'f3-data-row__main' });
  main.append(createElement(options.headingTag || 'div', { className: 'f3-data-row__title', text: options.title || '' }));
  if (options.meta) main.append(createElement('div', { className: 'f3-data-row__meta' }, options.meta));
  const root = createElement(options.tag || 'div', { className: `f3-data-row${options.className ? ` ${asText(options.className)}` : ''}`, attrs: options.attrs || {} }, main);
  if (options.end) root.append(createElement('div', { className: 'f3-data-row__end' }, options.end));
  return root;
}

const STATE_ICON = Object.freeze({ empty: 'info', info: 'info', error: 'error', success: 'success', warning: 'warning' });
export function stateView(options = {}) {
  const kind = ['empty', 'info', 'error', 'success', 'warning'].includes(options.kind) ? options.kind : 'empty';
  const root = createElement('section', {
    className: `f3-state${options.className ? ` ${asText(options.className)}` : ''}`,
    attrs: { 'data-kind': kind === 'empty' ? null : kind, ...options.attrs },
  });
  root.append(createElement('div', { className: 'f3-state__icon' }, icon(options.icon || STATE_ICON[kind])));
  root.append(createElement(options.headingTag || 'h3', { className: 'f3-state__title', text: options.title || '' }));
  if (options.description) root.append(createElement('p', { className: 'f3-state__description', text: options.description }));
  if (options.action) root.append(options.action);
  return root;
}

export function skeleton(options = {}) {
  const classes = ['f3-skeleton', options.variant === 'card' ? 'f3-skeleton--card' : 'f3-skeleton--line'];
  if (options.small) classes.push('f3-skeleton--line-sm');
  if (options.className) classes.push(asText(options.className));
  return createElement('div', { className: classes.join(' '), attrs: { 'aria-hidden': 'true' } });
}

export function skeletonStack(options = {}) {
  const count = Math.max(1, Math.min(12, Number(options.count) || 4));
  const root = createElement('div', {
    className: `f3-skeleton-stack${options.className ? ` ${asText(options.className)}` : ''}`,
    attrs: { role: 'status', 'aria-live': 'polite', 'aria-label': options.label || 'در حال دریافت اطلاعات' },
  });
  for (let index = 0; index < count; index += 1) root.append(skeleton({ variant: options.variant || 'card' }));
  return root;
}

function focusableElements(panel) {
  return [...panel.querySelectorAll(FOCUSABLE_SELECTOR)].filter((node) => {
    return !node.hidden && node.getAttribute('aria-hidden') !== 'true' && node.getAttribute('aria-disabled') !== 'true';
  });
}

function overlayController(overlay, panel, options = {}) {
  const listeners = new AbortController();
  let restoreTarget = null;
  let hideTimer = 0;
  let openFrame = 0;
  let destroyed = false;

  function destroy() {
    if (destroyed) return;
    destroyed = true;
    listeners.abort();
    window.clearTimeout(hideTimer);
    if (openFrame) cancelAnimationFrame(openFrame);
    overlay.remove();
  }

  function open(trigger = null) {
    if (destroyed || (!overlay.hidden && overlay.dataset.state === 'open')) return;
    window.clearTimeout(hideTimer);
    if (openFrame) cancelAnimationFrame(openFrame);
    restoreTarget = trigger || document.activeElement;
    overlay.hidden = false;
    overlay.dataset.state = 'closed';
    openFrame = requestAnimationFrame(() => {
      if (destroyed) return;
      overlay.dataset.state = 'open';
      const initial = options.initialFocus || focusableElements(panel)[0] || panel;
      focusSafely(initial, { preventScroll: false });
    });
  }

  function close(closeOptions = {}) {
    if (destroyed || overlay.hidden) return;
    if (openFrame) cancelAnimationFrame(openFrame);
    openFrame = 0;
    overlay.dataset.state = 'closed';
    window.clearTimeout(hideTimer);
    hideTimer = window.setTimeout(() => { if (!destroyed) overlay.hidden = true; }, 260);
    if (closeOptions.restoreFocus !== false && restoreTarget && restoreTarget.isConnected) focusSafely(restoreTarget);
    if (typeof options.onClose === 'function') options.onClose();
  }

  function onKeydown(event) {
    if (event.key === 'Escape' && options.closeOnEscape !== false) {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = focusableElements(panel);
    if (!focusable.length) {
      event.preventDefault();
      focusSafely(panel);
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      focusSafely(last);
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      focusSafely(first);
    }
  }

  overlay.addEventListener('keydown', onKeydown, { signal: listeners.signal });
  overlay.addEventListener('pointerdown', (event) => {
    if (event.target === overlay && options.closeOnBackdrop !== false) close();
  }, { signal: listeners.signal });

  if (options.signal) {
    if (options.signal.aborted) destroy();
    else options.signal.addEventListener('abort', destroy, { once: true });
  }

  return { open, close, destroy, isOpen: () => !destroyed && !overlay.hidden && overlay.dataset.state === 'open' };
}

function overlayShell(kind, options = {}) {
  const titleId = uid(`f3-${kind}-title`);
  const descriptionId = options.description ? uid(`f3-${kind}-description`) : null;
  const overlay = createElement('div', {
    className: `f3-overlay${kind === 'sheet' ? ' f3-overlay--sheet' : ''}`,
    attrs: { hidden: true, 'data-state': 'closed' },
  });
  const panel = createElement('section', {
    className: kind === 'sheet' ? 'f3-sheet' : 'f3-dialog',
    attrs: {
      role: 'dialog',
      'aria-modal': 'true',
      'aria-labelledby': titleId,
      'aria-describedby': descriptionId,
      tabindex: '-1',
    },
  });
  const headerClass = kind === 'sheet' ? 'f3-sheet__header' : 'f3-dialog__header';
  const titleClass = kind === 'sheet' ? 'f3-sheet__title' : 'f3-dialog__title';
  const descriptionClass = kind === 'sheet' ? 'f3-sheet__description' : 'f3-dialog__description';
  const bodyClass = kind === 'sheet' ? 'f3-sheet__body' : 'f3-dialog__body';
  const actionsClass = kind === 'sheet' ? 'f3-sheet__actions' : 'f3-dialog__actions';

  const header = createElement('header', { className: headerClass });
  const heading = createElement('div', {}, createElement('h2', { className: titleClass, text: options.title || '', attrs: { id: titleId } }));
  if (options.description) heading.append(createElement('p', { className: descriptionClass, text: options.description, attrs: { id: descriptionId } }));
  header.append(heading);
  const closeButton = iconButton('close', options.closeLabel || 'بستن', { quiet: true });
  header.append(closeButton);
  panel.append(header);
  if (options.content != null) panel.append(createElement('div', { className: bodyClass }, options.content));
  if (options.actions) panel.append(createElement('footer', { className: actionsClass }, options.actions));
  overlay.append(panel);

  const controller = overlayController(overlay, panel, options);
  closeButton.addEventListener('click', () => controller.close(), eventOptions(options.signal));
  return { overlay, panel, closeButton, ...controller };
}

export function createDialog(options = {}) { return overlayShell('dialog', options); }
export function createDrawer(options = {}) { return overlayShell('sheet', options); }

export function createToastRegion(options = {}) {
  const region = createElement('div', {
    className: `f3-toast-region${options.className ? ` ${asText(options.className)}` : ''}`,
    attrs: { 'aria-live': 'polite', 'aria-relevant': 'additions text' },
  });
  const timers = new Set();
  let destroyed = false;

  function show(message, toastOptions = {}) {
    if (destroyed) return null;
    const valueTone = tone(toastOptions.tone);
    const iconName = valueTone === 'danger' ? 'error' : valueTone === 'warning' ? 'warning' : valueTone === 'success' ? 'success' : 'info';
    const body = createElement('div', {}, createElement('p', { className: 'f3-toast__message', text: message }));
    if (toastOptions.action) body.append(toastOptions.action);
    const toast = createElement('div', {
      className: 'f3-toast',
      attrs: { role: valueTone === 'danger' ? 'alert' : 'status', 'data-tone': valueTone },
    }, icon(iconName, { size: 'sm' }), body);
    toast.append(iconButton('close', toastOptions.closeLabel || 'بستن پیام', { quiet: true, onClick: () => toast.remove() }));
    region.append(toast);

    const duration = Number(toastOptions.duration);
    if (duration !== 0) {
      const timeout = Number.isFinite(duration) ? Math.max(1200, duration) : 5000;
      const timer = window.setTimeout(() => {
        timers.delete(timer);
        toast.remove();
      }, timeout);
      timers.add(timer);
    }
    return toast;
  }

  function destroy() {
    if (destroyed) return;
    destroyed = true;
    for (const timer of timers) window.clearTimeout(timer);
    timers.clear();
    region.remove();
  }

  if (options.signal) {
    if (options.signal.aborted) destroy();
    else options.signal.addEventListener('abort', destroy, { once: true });
  }
  return { region, show, destroy };
}

export function createTabs(items, options = {}) {
  const tabs = Array.isArray(items) ? items : [];
  const list = createElement('div', {
    className: `f3-tabs__list${options.className ? ` ${asText(options.className)}` : ''}`,
    attrs: { role: 'tablist', 'aria-label': options.label || 'بخش‌ها' },
  });
  const buttons = [];
  const matched = tabs.findIndex((item) => item.id === options.selectedId);
  let selected = matched >= 0 ? matched : 0;

  function apply(next, emit = false) {
    if (!buttons.length) return;
    selected = Math.max(0, Math.min(buttons.length - 1, next));
    buttons.forEach((node, index) => {
      const active = index === selected;
      node.setAttribute('aria-selected', active ? 'true' : 'false');
      node.tabIndex = active ? 0 : -1;
    });
    if (emit && typeof options.onChange === 'function') options.onChange(tabs[selected], selected);
  }

  tabs.forEach((item, index) => {
    const node = createElement('button', {
      className: 'f3-tab',
      text: item.label,
      attrs: { type: 'button', role: 'tab', id: item.tabId || uid('f3-tab'), 'aria-controls': item.panelId || null },
    });
    node.addEventListener('click', () => apply(index, true), eventOptions(options.signal));
    buttons.push(node);
    list.append(node);
  });

  list.addEventListener('keydown', (event) => {
    if (!buttons.length) return;
    const rtl = getComputedStyle(list).direction === 'rtl';
    let next = selected;
    if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = buttons.length - 1;
    else if (event.key === 'ArrowRight') next = (selected + (rtl ? -1 : 1) + buttons.length) % buttons.length;
    else if (event.key === 'ArrowLeft') next = (selected + (rtl ? 1 : -1) + buttons.length) % buttons.length;
    else return;
    event.preventDefault();
    apply(next, true);
    focusSafely(buttons[next]);
  }, eventOptions(options.signal));

  apply(selected, false);
  return {
    root: createElement('div', { className: 'f3-tabs' }, list),
    list,
    buttons,
    select(id, emit = false) {
      const index = tabs.findIndex((item) => item.id === id);
      if (index >= 0) apply(index, emit);
    },
  };
}

export function createSegmentedControl(items, options = {}) {
  const values = Array.isArray(items) ? items : [];
  const group = createElement('div', {
    className: 'f3-segmented__group',
    attrs: { role: 'group', 'aria-label': options.label || 'انتخاب حالت' },
  });
  const buttons = [];
  let selectedId = options.selectedId ?? values[0]?.id;

  function apply(id, emit = false) {
    selectedId = id;
    buttons.forEach(({ node, item }) => node.setAttribute('aria-pressed', item.id === selectedId ? 'true' : 'false'));
    if (emit && typeof options.onChange === 'function') {
      const item = values.find((candidate) => candidate.id === selectedId);
      if (item) options.onChange(item);
    }
  }

  values.forEach((item) => {
    const node = createElement('button', {
      className: 'f3-segmented__button',
      text: item.label,
      attrs: { type: 'button', 'aria-pressed': item.id === selectedId ? 'true' : 'false', disabled: item.disabled || null },
    });
    node.addEventListener('click', () => apply(item.id, true), eventOptions(options.signal));
    buttons.push({ node, item });
    group.append(node);
  });

  return {
    root: createElement('div', { className: 'f3-segmented' }, group),
    group,
    select(id, emit = false) { if (values.some((item) => item.id === id)) apply(id, emit); },
    get value() { return selectedId; },
  };
}

export function pagination(options = {}) {
  const current = Math.max(1, Number(options.current) || 1);
  const total = Math.max(1, Number(options.total) || 1);
  const root = createElement('nav', { className: 'f3-pagination', attrs: { 'aria-label': options.label || 'صفحه‌بندی' } });
  const pages = createElement('div', { className: 'f3-pagination__pages' });
  const invoke = (page) => {
    if (typeof options.onChange === 'function' && page >= 1 && page <= total && page !== current) options.onChange(page);
  };

  root.append(button(options.previousLabel || 'قبلی', { compact: true, variant: 'secondary', disabled: current <= 1, onClick: () => invoke(current - 1), signal: options.signal }));
  const start = Math.max(1, current - 2);
  const end = Math.min(total, start + 4);
  for (let page = Math.max(1, end - 4); page <= end; page += 1) {
    const display = options.formatPage ? options.formatPage(page) : String(page);
    const node = createElement('button', {
      className: 'f3-pagination__page',
      text: display,
      attrs: { type: 'button', 'aria-label': `صفحه ${display}`, 'aria-current': page === current ? 'page' : null },
    });
    node.addEventListener('click', () => invoke(page), eventOptions(options.signal));
    pages.append(node);
  }
  root.append(pages);
  root.append(button(options.nextLabel || 'بعدی', { compact: true, variant: 'secondary', disabled: current >= total, onClick: () => invoke(current + 1), signal: options.signal }));
  return root;
}

export function divider(options = {}) {
  return createElement('hr', {
    className: `f3-divider${options.strong ? ' f3-divider--strong' : ''}${options.className ? ` ${asText(options.className)}` : ''}`,
    attrs: { 'aria-hidden': options.decorative === false ? null : 'true' },
  });
}

export function setBusy(node, busy, label = '') {
  if (!node) return node;
  setAttributes(node, { 'aria-busy': busy ? 'true' : 'false' });
  if (label) node.setAttribute('aria-label', asText(label));
  return node;
}

export function replaceContent(node, ...children) {
  clear(node);
  append(node, ...children);
  return node;
}
