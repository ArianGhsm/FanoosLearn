const DEFAULT_ROUTE_REFERENCES = Object.freeze([
  { id: 'home', path: '/home', label: 'خانه', eyebrow: 'امروز', slot: 'primary', owner: 'web-03-home-today', icon: 'home', workspaceRequired: true },
  { id: 'courses', path: '/courses', label: 'درس‌ها', eyebrow: 'یادگیری', slot: 'primary', owner: 'web-04-courses', icon: 'courses', workspaceRequired: true },
  { id: 'schedule', path: '/schedule', label: 'برنامه', eyebrow: 'برنامه آموزشی', slot: 'primary', owner: 'web-05-schedule', icon: 'calendar', workspaceRequired: true },
  { id: 'resources', path: '/resources', label: 'منابع', longLabel: 'یادگیری و منابع', eyebrow: 'یادگیری', slot: 'primary', owner: 'web-06-learning-resources', icon: 'resources', workspaceRequired: true },
  { id: 'assessments', path: '/assessments', label: 'آزمون‌ها', eyebrow: 'ارزیابی', slot: 'primary', owner: 'web-07-progress-assessments-grades', icon: 'assessment', workspaceRequired: true },
  { id: 'grades', path: '/grades', label: 'نمرات', eyebrow: 'عملکرد', slot: 'primary', owner: 'web-07-progress-assessments-grades', icon: 'grades', workspaceRequired: true },
  { id: 'announcements', path: '/announcements', label: 'اطلاعیه‌ها', eyebrow: 'ارتباطات', slot: 'primary', owner: 'web-08-operations-communication-commerce', icon: 'announcement', workspaceRequired: true },
  { id: 'forms', path: '/forms', label: 'فرم‌ها', eyebrow: 'فعالیت‌ها', slot: 'secondary', owner: 'web-08-operations-communication-commerce', icon: 'forms', workspaceRequired: true },
  { id: 'orders', path: '/orders', label: 'خرید و دسترسی', eyebrow: 'دسترسی', slot: 'secondary', owner: 'web-08-operations-communication-commerce', icon: 'commerce', workspaceRequired: true },
  { id: 'account', path: '/account', label: 'حساب', longLabel: 'حساب و فضاهای آموزشی', eyebrow: 'حساب', slot: 'account', owner: 'web-02-shell', icon: 'account', workspaceRequired: false },
  { id: 'management', path: '/management', label: 'مدیریت', eyebrow: 'مدیریت', slot: 'secondary', owner: 'integration', icon: 'management', workspaceRequired: true, capability: 'management' },
  { id: 'more', path: '/more', label: 'بیشتر', eyebrow: 'فانوس', slot: 'mobile-more', owner: 'web-02-shell', icon: 'more', workspaceRequired: false },
]);

function cleanPath(value) {
  const raw = String(value || '').trim().replace(/^#/, '');
  const path = raw.startsWith('/') ? raw : `/${raw}`;
  const withoutQuery = path.split('?')[0] || '/home';
  return withoutQuery.replace(/\/{2,}/g, '/').replace(/\/$/, '') || '/home';
}

function safeDecode(value) {
  try { return decodeURIComponent(value); } catch (_error) { return value; }
}

function parseQuery(value) {
  const query = {};
  const source = String(value || '').replace(/^\?/, '');
  new URLSearchParams(source).forEach((item, key) => {
    if (Object.prototype.hasOwnProperty.call(query, key)) {
      query[key] = Array.isArray(query[key]) ? [...query[key], item] : [query[key], item];
    } else {
      query[key] = item;
    }
  });
  return query;
}

function routeLocation(windowRef, mode) {
  if (!windowRef) return { path: '/home', query: {} };
  if (mode === 'history') {
    return { path: cleanPath(windowRef.location.pathname), query: parseQuery(windowRef.location.search) };
  }
  const raw = String(windowRef.location.hash || '#/home').replace(/^#/, '');
  const [path, query = ''] = raw.split('?');
  return { path: cleanPath(path), query: parseQuery(query) };
}

function toSearch(query = {}) {
  const params = new URLSearchParams();
  Object.entries(query).forEach(([key, value]) => {
    if (value == null || value === '') return;
    if (Array.isArray(value)) value.forEach((item) => params.append(key, String(item)));
    else params.set(key, String(value));
  });
  const encoded = params.toString();
  return encoded ? `?${encoded}` : '';
}

function normalizeRoute(route, fallback = {}) {
  const id = String(route?.id || fallback.id || '').trim();
  if (!id) return null;
  return Object.freeze({
    ...fallback,
    ...route,
    id,
    path: cleanPath(route?.path || fallback.path || `/${id}`),
    label: String(route?.label || fallback.label || id),
    workspaceRequired: route?.workspaceRequired ?? fallback.workspaceRequired ?? true,
  });
}

export function createRouteRegistry(seed = DEFAULT_ROUTE_REFERENCES) {
  const routes = new Map();
  const pathIndex = new Map();

  function register(route, fallback = {}) {
    const normalized = normalizeRoute(route, fallback);
    if (!normalized) return null;
    routes.set(normalized.id, normalized);
    pathIndex.set(normalized.path, normalized.id);
    return normalized;
  }

  seed.forEach((route) => register(route));

  return Object.freeze({
    register,
    registerModule(moduleDefinition) {
      const incoming = Array.isArray(moduleDefinition?.routes) ? moduleDefinition.routes : [];
      incoming.forEach((route) => register(route, { owner: moduleDefinition?.id || route?.owner }));
      return this;
    },
    get(id) { return routes.get(String(id || '')) || null; },
    match(path) {
      const normalizedPath = cleanPath(path);
      const exact = pathIndex.get(normalizedPath);
      if (exact) return routes.get(exact) || null;
      const firstSegment = `/${normalizedPath.split('/').filter(Boolean)[0] || 'home'}`;
      const parent = pathIndex.get(firstSegment);
      return parent ? routes.get(parent) || null : null;
    },
    all() { return [...routes.values()]; },
    bySlot(slot) { return [...routes.values()].filter((route) => route.slot === slot); },
  });
}

export function createShellRouter(options = {}) {
  const windowRef = options.windowRef || (typeof window !== 'undefined' ? window : null);
  const mode = options.mode === 'history' ? 'history' : 'hash';
  const registry = options.registry || createRouteRegistry();
  const onChange = typeof options.onChange === 'function' ? options.onChange : () => {};
  const delegateNavigate = typeof options.navigate === 'function' ? options.navigate : null;
  let listening = false;
  let current = null;

  function resolve(location = routeLocation(windowRef, mode)) {
    const matched = registry.match(location.path) || registry.get('home');
    return Object.freeze({
      ...matched,
      path: location.path,
      query: location.query || {},
      segments: location.path.split('/').filter(Boolean).map(safeDecode),
    });
  }

  function read({ notify = true } = {}) {
    current = resolve();
    if (notify) onChange(current);
    return current;
  }

  function targetFor(id, query = {}) {
    const route = registry.get(id) || registry.get('home');
    return `${route.path}${toSearch(query)}`;
  }

  function navigate(id, query = {}, navigation = {}) {
    const route = registry.get(id);
    if (!route) return false;
    if (delegateNavigate) {
      delegateNavigate(route.id, { ...navigation, path: route.path, query });
      current = Object.freeze({ ...route, query, segments: route.path.split('/').filter(Boolean) });
      onChange(current);
      return true;
    }
    if (!windowRef) return false;
    const target = targetFor(route.id, query);
    if (mode === 'history') {
      const method = navigation.replace ? 'replaceState' : 'pushState';
      windowRef.history[method](null, '', target);
      read();
      return true;
    }
    const hash = `#${target}`;
    if (navigation.replace) {
      windowRef.history.replaceState(null, '', `${windowRef.location.pathname}${windowRef.location.search}${hash}`);
      read();
    } else if (windowRef.location.hash === hash) {
      read();
    } else {
      windowRef.location.hash = hash;
    }
    return true;
  }

  function handleNavigation() { read(); }

  function start() {
    if (listening || !windowRef) return read();
    listening = true;
    windowRef.addEventListener(mode === 'history' ? 'popstate' : 'hashchange', handleNavigation);
    if (mode === 'hash' && !windowRef.location.hash) {
      windowRef.history.replaceState(null, '', `${windowRef.location.pathname}${windowRef.location.search}#/home`);
    }
    return read();
  }

  function stop() {
    if (!listening || !windowRef) return;
    windowRef.removeEventListener(mode === 'history' ? 'popstate' : 'hashchange', handleNavigation);
    listening = false;
  }

  return Object.freeze({ registry, mode, start, stop, read, navigate, resolve, targetFor, get current() { return current; } });
}

export { DEFAULT_ROUTE_REFERENCES };
