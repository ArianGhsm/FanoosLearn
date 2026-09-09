const ICON_URL = new URL('./icons.svg', import.meta.url).href;

export function cleanText(value, fallback = '') {
  const normalized = String(value == null ? '' : value)
    .normalize('NFKC')
    .replace(/[\u202A-\u202E\u2066-\u2069]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
  return normalized || fallback;
}

export function element(tag, options = {}, ...children) {
  const node = document.createElement(tag);
  if (options.className) node.className = options.className;
  if (options.text != null) node.textContent = String(options.text);
  if (options.id) node.id = options.id;
  if (options.attrs) {
    Object.entries(options.attrs).forEach(([key, value]) => {
      if (value === false || value == null) return;
      node.setAttribute(key, value === true ? '' : String(value));
    });
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([key, value]) => {
      if (value != null) node.dataset[key] = String(value);
    });
  }
  if (options.on) {
    Object.entries(options.on).forEach(([name, handler]) => node.addEventListener(name, handler));
  }
  children.flat().filter(Boolean).forEach((child) => node.append(child));
  return node;
}

export function icon(name, options = {}) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', options.className || 'f3-shell-icon');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  use.setAttribute('href', `${ICON_URL}#${name}`);
  svg.append(use);
  return svg;
}

export function button(label, options = {}) {
  const node = element('button', {
    className: `f3-shell-button f3-shell-button--${options.variant || 'secondary'}${options.className ? ` ${options.className}` : ''}`,
    attrs: {
      type: options.type || 'button',
      disabled: options.disabled || null,
      'aria-label': options.ariaLabel || null,
      'aria-pressed': options.ariaPressed == null ? null : String(Boolean(options.ariaPressed)),
    },
  });
  if (options.icon) node.append(icon(options.icon));
  node.append(element('span', { text: label }));
  if (options.onClick) node.addEventListener('click', options.onClick);
  return node;
}

export function statusBadge(label, kind = 'neutral') {
  return element('span', { className: `f3-shell-status f3-shell-status--${kind}`, text: label });
}

export function statePanel(title, description, options = {}) {
  const panel = element('section', {
    className: `f3-shell-state f3-shell-state--${options.kind || 'neutral'}`,
    attrs: { 'aria-live': options.live ? 'polite' : null },
  });
  const mark = options.icon ? element('span', { className: 'f3-shell-state__icon' }, icon(options.icon)) : null;
  const copy = element('div', { className: 'f3-shell-state__copy' }, element('h2', { text: title }), element('p', { text: description }));
  panel.append(mark, copy);
  if (options.action) panel.append(element('div', { className: 'f3-shell-state__actions' }, options.action));
  return panel;
}

export function skeletonShell() {
  return element('div', { className: 'f3-shell-boot', attrs: { 'aria-busy': 'true', 'aria-label': 'در حال آماده‌سازی فانوس' } },
    element('div', { className: 'f3-shell-boot__rail' },
      element('span', { className: 'f3-shell-skeleton f3-shell-skeleton--brand' }),
      ...Array.from({ length: 6 }, () => element('span', { className: 'f3-shell-skeleton f3-shell-skeleton--nav' })),
    ),
    element('div', { className: 'f3-shell-boot__main' },
      element('span', { className: 'f3-shell-skeleton f3-shell-skeleton--line' }),
      element('span', { className: 'f3-shell-skeleton f3-shell-skeleton--title' }),
      element('div', { className: 'f3-shell-skeleton f3-shell-skeleton--panel' }),
    ),
  );
}

export function workspacePath(workspace) {
  const parts = [workspace?.institution_name, workspace?.faculty_name, workspace?.program_name, workspace?.cohort_label]
    .map((item) => cleanText(item))
    .filter(Boolean);
  return parts.join(' · ');
}

export function workspaceName(workspace) { return cleanText(workspace?.name, 'فضای آموزشی'); }

export function formatCount(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return '۰';
  try { return new Intl.NumberFormat('fa-IR').format(number); } catch (_error) { return String(number); }
}

export function initials(value) {
  const words = cleanText(value, 'فانوس').split(/\s+/).filter(Boolean);
  return words.slice(0, 2).map((word) => word.slice(0, 1)).join('') || 'ف';
}

function focusables(container) {
  if (!container) return [];
  return [...container.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')]
    .filter((node) => !node.hidden && node.getAttribute('aria-hidden') !== 'true');
}

export function createSheet(node, options = {}) {
  let returnFocus = null;
  let open = false;

  function close({ restoreFocus = true } = {}) {
    if (!open) return;
    open = false;
    node.hidden = true;
    node.setAttribute('aria-hidden', 'true');
    options.backdrop?.setAttribute('hidden', '');
    if (returnFocus?.isConnected) returnFocus.setAttribute('aria-expanded', 'false');
    if (restoreFocus && returnFocus?.isConnected) returnFocus.focus();
    options.onClose?.();
  }

  function show(trigger) {
    if (open) return;
    open = true;
    returnFocus = trigger || document.activeElement;
    if (returnFocus?.isConnected) returnFocus.setAttribute('aria-expanded', 'true');
    node.hidden = false;
    node.setAttribute('aria-hidden', 'false');
    options.backdrop?.removeAttribute('hidden');
    requestAnimationFrame(() => (focusables(node)[0] || node).focus());
    options.onOpen?.();
  }

  function keydown(event) {
    if (!open) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const items = focusables(node);
    if (!items.length) {
      event.preventDefault();
      node.focus();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  node.addEventListener('keydown', keydown);
  options.backdrop?.addEventListener('click', () => close());
  node.querySelectorAll('[data-f3-shell-close]').forEach((item) => item.addEventListener('click', () => close()));

  return Object.freeze({
    show,
    close,
    destroy() {
      close({ restoreFocus: false });
      node.removeEventListener('keydown', keydown);
    },
    get isOpen() { return open; },
  });
}

export function dispatchShellEvent(root, name, detail = {}) {
  const target = root || document;
  target.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: Object.freeze({ ...detail }) }));
}
