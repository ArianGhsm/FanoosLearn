const URL_ATTRIBUTES = new Set(['href', 'src', 'action', 'formaction', 'poster', 'cite']);
const BLOCKED_ATTRIBUTES = new Set(['innerhtml', 'outerhtml', 'srcdoc', 'style']);
const SAFE_PROTOCOLS = new Set(['http:', 'https:', 'mailto:', 'tel:']);

export function asText(value) {
  return value == null ? '' : String(value);
}

export function textNode(value) {
  return document.createTextNode(asText(value));
}

export function clear(node) {
  if (node && typeof node.replaceChildren === 'function') node.replaceChildren();
  return node;
}

export function normalizeSafeUrl(value, options = {}) {
  const raw = asText(value).trim();
  if (!raw) return '';
  if (raw.startsWith('#')) return raw;

  const base = options.base || document.baseURI;
  let parsed;
  try {
    parsed = new URL(raw, base);
  } catch (_error) {
    throw new TypeError('Invalid URL');
  }

  if (!SAFE_PROTOCOLS.has(parsed.protocol)) {
    throw new TypeError('Unsupported URL protocol');
  }

  if (options.sameOrigin === true) {
    const baseUrl = new URL(base);
    if (parsed.origin !== baseUrl.origin) throw new TypeError('Cross-origin URL is not allowed');
  }

  return parsed.href;
}

export function setAttributes(node, attributes = {}) {
  if (!node || typeof node.setAttribute !== 'function') return node;

  for (const [rawName, value] of Object.entries(attributes || {})) {
    const name = String(rawName).trim();
    const normalized = name.toLowerCase();
    if (!name || normalized.startsWith('on') || BLOCKED_ATTRIBUTES.has(normalized)) continue;

    if (value == null || value === false) {
      node.removeAttribute(name);
      continue;
    }

    if (value === true) {
      node.setAttribute(name, '');
      continue;
    }

    const safeValue = URL_ATTRIBUTES.has(normalized)
      ? normalizeSafeUrl(value)
      : asText(value);
    node.setAttribute(name, safeValue);
  }

  return node;
}

export function setDataset(node, dataset = {}) {
  if (!node || !node.dataset) return node;
  for (const [key, value] of Object.entries(dataset || {})) {
    if (value == null) delete node.dataset[key];
    else node.dataset[key] = asText(value);
  }
  return node;
}

export function append(node, ...children) {
  if (!node || typeof node.append !== 'function') return node;
  const flattened = children.flat(Infinity);

  for (const child of flattened) {
    if (child == null || child === false) continue;
    if (typeof child === 'object' && typeof child.nodeType === 'number') node.append(child);
    else node.append(textNode(child));
  }

  return node;
}

function addListeners(node, listeners = {}) {
  for (const [eventName, definition] of Object.entries(listeners || {})) {
    if (typeof definition === 'function') {
      node.addEventListener(eventName, definition);
      continue;
    }

    if (definition && typeof definition.handler === 'function') {
      node.addEventListener(eventName, definition.handler, definition.options || undefined);
    }
  }
}

export function createElement(tagName, options = {}, ...children) {
  const node = document.createElement(tagName);
  if (options.className) node.className = asText(options.className);
  if (options.text != null) node.textContent = asText(options.text);
  if (options.attrs) setAttributes(node, options.attrs);
  if (options.dataset) setDataset(node, options.dataset);
  if (options.listeners) addListeners(node, options.listeners);
  append(node, ...children);
  return node;
}

export function createBdi(value, options = {}) {
  const dir = ['ltr', 'rtl', 'auto'].includes(options.dir) ? options.dir : 'auto';
  const node = createElement('bdi', {
    className: options.className || 'f3-bdi',
    text: value,
    attrs: { dir },
  });
  return node;
}

export function technicalText(value, options = {}) {
  return createBdi(value, {
    dir: 'ltr',
    className: options.className
      ? `f3-technical ${options.className}`
      : 'f3-technical',
  });
}

export function focusSafely(node, options = {}) {
  if (!node || typeof node.focus !== 'function') return false;
  if (node.hidden || node.getAttribute?.('aria-hidden') === 'true') return false;
  try {
    node.focus({ preventScroll: options.preventScroll !== false });
    return document.activeElement === node;
  } catch (_error) {
    try {
      node.focus();
      return document.activeElement === node;
    } catch (_fallbackError) {
      return false;
    }
  }
}
