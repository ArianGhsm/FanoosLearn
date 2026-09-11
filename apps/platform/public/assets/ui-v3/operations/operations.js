const ROUTES = Object.freeze({
  announcements: 'announcements',
  notifications: 'notifications',
  forms: 'forms',
  orders: 'orders',
  management: 'management',
});

const mountedControllers = new WeakMap();

const STATUS_LABELS = Object.freeze({
  pending: 'در انتظار',
  payment_pending: 'در انتظار پرداخت',
  paid: 'پرداخت تأیید شده',
  failed: 'ناموفق',
  canceled: 'لغوشده',
  cancelled: 'لغوشده',
  created: 'ایجادشده',
  redirected: 'در انتظار تکمیل پرداخت',
  verifying: 'در حال بررسی پرداخت',
  refunded: 'بازپرداخت‌شده',
  active: 'دسترسی فعال',
  expired: 'منقضی‌شده',
  revoked: 'دسترسی لغوشده',
  open: 'باز',
  closed: 'بسته',
  draft: 'پیش‌نویس',
  review: 'در انتظار بررسی',
  approved: 'تأییدشده',
  rejected: 'ردشده',
  published: 'منتشرشده',
  read: 'خوانده‌شده',
  delivered: 'تحویل‌شده',
  submitted: 'ثبت‌شده',
});

const ROLE_LABELS = Object.freeze({
  owner: 'مالک',
  'platform-owner': 'مالک سامانه',
  administrator: 'مدیر',
  admin: 'مدیر',
  'workspace-admin': 'مدیر فضای آموزشی',
  'cohort-representative': 'نماینده ورودی',
  representative: 'نماینده',
  instructor: 'مدرس',
  teacher: 'مدرس',
  student: 'دانشجو',
  member: 'عضو',
});

const FORM_TYPES = Object.freeze([
  ['text', 'پاسخ کوتاه'],
  ['textarea', 'پاسخ بلند'],
  ['number', 'عدد'],
  ['choice', 'انتخاب یک گزینه'],
  ['multi_choice', 'انتخاب چند گزینه'],
  ['date', 'تاریخ'],
  ['boolean', 'بله / خیر'],
]);

function text(value, fallback = '') {
  if (value === null || value === undefined) return fallback;
  return String(value).normalize('NFKC').replace(/[\u202A-\u202E\u2066-\u2069]/g, '').trim();
}

function bounded(value, max = 220) {
  const valueText = text(value).replace(/\s+/g, ' ');
  return valueText.length > max ? `${valueText.slice(0, Math.max(0, max - 1)).trimEnd()}…` : valueText;
}

function element(tag, options = {}, ...children) {
  const node = document.createElement(tag);
  if (options.className) node.className = options.className;
  if (options.text !== undefined) node.textContent = text(options.text);
  if (options.attrs) {
    Object.entries(options.attrs).forEach(([key, value]) => {
      if (value === false || value === null || value === undefined) return;
      if (value === true) node.setAttribute(key, '');
      else node.setAttribute(key, String(value));
    });
  }
  if (options.dataset) {
    Object.entries(options.dataset).forEach(([key, value]) => {
      if (value !== null && value !== undefined) node.dataset[key] = String(value);
    });
  }
  if (options.on) {
    Object.entries(options.on).forEach(([eventName, handler]) => node.addEventListener(eventName, handler));
  }
  children.flat().filter(Boolean).forEach((child) => node.append(child instanceof Node ? child : document.createTextNode(String(child))));
  return node;
}

function icon(name) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('class', 'f3-ops-icon');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('fill', 'none');
  svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', '1.8');
  svg.setAttribute('stroke-linecap', 'round');
  svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('aria-hidden', 'true');
  const paths = {
    announcement: ['M4 13V8.5a2 2 0 0 1 2-2h3l7-3v14l-7-3H6a2 2 0 0 1-2-1.5Z', 'M8 14.5 9.5 20'],
    bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 8.5h18C21 15 18 15 18 8Z', 'M10 20h4'],
    form: ['M6 3h9l3 3v15H6z', 'M15 3v4h4', 'M9 11h6', 'M9 15h6'],
    wallet: ['M4 6.5h14a2 2 0 0 1 2 2V18H4a2 2 0 0 1-2-2V6.5a2 2 0 0 1 2-2h12', 'M15 11h5v4h-5a2 2 0 0 1 0-4Z'],
    manage: ['M4 5h16', 'M7 5v5', 'M4 12h16', 'M16 12v5', 'M4 19h16'],
    arrow: ['M5 12h14', 'm13 6 6 6-6 6'],
    check: ['m5 12 4 4L19 6'],
    clock: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z', 'M12 7v5l3 2'],
    users: ['M16 20v-1.5c0-2-1.8-3.5-4-3.5H7c-2.2 0-4 1.5-4 3.5V20', 'M9.5 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M18 7a3 3 0 0 1 0 6'],
    content: ['M5 4h14v16H5z', 'M8 8h8', 'M8 12h8', 'M8 16h5'],
    plus: ['M12 5v14', 'M5 12h14'],
    close: ['m7 7 10 10', 'm17 7-10 10'],
  };
  (paths[name] || paths.arrow).forEach((d) => {
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', d);
    svg.append(path);
  });
  return svg;
}

function button(label, { variant = 'secondary', type = 'button', onClick, disabled = false, iconName = null } = {}) {
  return element('button', {
    className: `f3-ops-button f3-ops-button--${variant}`,
    attrs: { type, disabled: disabled || null },
    on: onClick ? { click: onClick } : null,
  }, iconName ? icon(iconName) : null, element('span', { text: label }));
}

function workspaceId(ctx) {
  return text(ctx?.state?.workspace?.id || ctx?.state?.workspaceId || ctx?.state?.activeWorkspaceId);
}

function workspaceName(ctx) {
  return text(ctx?.state?.workspace?.name || ctx?.state?.workspace?.label || ctx?.state?.workspaceName, 'فضای آموزشی فعال');
}

function workspaceTimezone(ctx) {
  return text(ctx?.state?.workspace?.timezone_name || ctx?.state?.workspaceTimezone, 'UTC');
}

function hasCapability(ctx, permission) {
  const capabilities = ctx?.capabilities;
  if (!capabilities) return false;
  if (typeof capabilities.has === 'function') return capabilities.has(permission) === true;
  if (Array.isArray(capabilities)) return capabilities.includes(permission);
  if (Array.isArray(capabilities.permissions)) return capabilities.permissions.includes(permission);
  if (typeof capabilities === 'object') return capabilities[permission] === true;
  return false;
}

function normalizeApiResult(value) {
  if (value && typeof value === 'object' && value.ok === true && Object.prototype.hasOwnProperty.call(value, 'data')) return value.data;
  if (value && typeof value === 'object' && value.ok === false) {
    const error = new Error('request_failed');
    error.status = Number(value.status || value.error?.status || 0);
    error.code = text(value.error?.code || value.code, 'request_failed');
    throw error;
  }
  return value;
}

async function apiRequest(ctx, path, { method = 'GET', body, signal } = {}) {
  const api = ctx?.api;
  const verb = method.toUpperCase();
  if (!api) throw new Error('ops_api_adapter_missing');
  if (typeof api.request === 'function') return normalizeApiResult(await api.request(path, { method: verb, body, signal }));
  if (verb === 'GET' && typeof api.get === 'function') return normalizeApiResult(await api.get(path, { signal }));
  if (verb === 'POST' && typeof api.post === 'function') return normalizeApiResult(await api.post(path, body || {}, { signal }));
  throw new Error('ops_api_adapter_missing');
}

function safeError(error) {
  return {
    status: Number(error?.status || 0),
    code: text(error?.code || error?.error?.code, 'request_failed').slice(0, 80),
  };
}

async function safeRead(ctx, path, signal) {
  try {
    return { ok: true, data: await apiRequest(ctx, path, { signal }) };
  } catch (error) {
    return { ok: false, error: safeError(error) };
  }
}

function rowsOf(result) {
  if (!result?.ok) return [];
  if (Array.isArray(result.data)) return result.data;
  if (Array.isArray(result.data?.items)) return result.data.items;
  if (Array.isArray(result.data?.rows)) return result.data.rows;
  return [];
}

function dedupeOrders(rows) {
  const seen = new Set();
  return rows.filter((row, index) => {
    const id = text(row?.id || row?.order_id);
    if (!id) return true;
    if (seen.has(id)) return false;
    seen.add(id);
    return true;
  });
}

function formatDate(ctx, value, withTime = false) {
  if (!value) return '';
  const source = String(value);
  const isoLike = source.includes('T') ? source : `${source.replace(' ', 'T')}Z`;
  const date = new Date(isoLike);
  if (Number.isNaN(date.getTime())) return '';
  if (ctx?.format?.dateTime && withTime) return text(ctx.format.dateTime(value));
  if (ctx?.format?.date && !withTime) return text(ctx.format.date(value));
  try {
    const options = withTime
      ? { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: workspaceTimezone(ctx) }
      : { year: 'numeric', month: 'long', day: 'numeric', timeZone: workspaceTimezone(ctx) };
    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', options).format(date);
  } catch (_error) {
    return '';
  }
}

function formatNumber(ctx, value) {
  if (ctx?.format?.number) return text(ctx.format.number(value));
  const number = Number(value);
  return Number.isFinite(number) ? new Intl.NumberFormat('fa-IR').format(number) : '—';
}

function formatMoney(ctx, amount, currency) {
  if (ctx?.format?.money) return text(ctx.format.money(amount, currency));
  const number = Number(amount);
  const unit = text(currency).toUpperCase();
  if (!Number.isFinite(number)) return '—';
  const rendered = new Intl.NumberFormat('fa-IR').format(number);
  if (unit === 'IRR') return `${rendered} ریال`;
  return unit ? `${rendered} ${unit}` : rendered;
}

function statusLabel(value, fallback = 'وضعیت نامشخص') {
  const key = text(value).toLowerCase();
  return STATUS_LABELS[key] || fallback;
}

function statusTone(value) {
  const key = text(value).toLowerCase();
  if (['paid', 'active', 'approved', 'published', 'read', 'delivered', 'submitted'].includes(key)) return 'success';
  if (['failed', 'rejected', 'revoked', 'canceled', 'cancelled'].includes(key)) return 'danger';
  if (['pending', 'payment_pending', 'created', 'redirected', 'verifying', 'review', 'expired'].includes(key)) return 'warning';
  return 'neutral';
}

function statusChip(value, label = null) {
  return element('span', {
    className: `f3-ops-status f3-ops-status--${statusTone(value)}`,
    text: label || statusLabel(value),
  });
}

function roleLabels(value) {
  const keys = text(value).split(',').map((item) => item.trim()).filter(Boolean);
  if (!keys.length) return 'عضو';
  return keys.map((key) => ROLE_LABELS[key.toLowerCase()] || 'نقش اختصاصی').join('، ');
}

function stateBlock(title, description, { tone = 'neutral', actionLabel, onAction } = {}) {
  return element('section', { className: `f3-ops-state f3-ops-state--${tone}`, attrs: { role: tone === 'danger' ? 'alert' : 'status' } },
    element('div', { className: 'f3-ops-state__mark', attrs: { 'aria-hidden': 'true' } }),
    element('div', { className: 'f3-ops-state__copy' },
      element('h2', { className: 'f3-ops-state__title', text: title }),
      element('p', { className: 'f3-ops-state__text', text: description }),
      actionLabel && onAction ? button(actionLabel, { variant: 'secondary', onClick: onAction }) : null,
    ),
  );
}

function skeleton(count = 4) {
  const wrap = element('div', { className: 'f3-ops-skeletons', attrs: { 'aria-busy': 'true', 'aria-label': 'در حال دریافت اطلاعات' } });
  for (let i = 0; i < count; i += 1) {
    wrap.append(element('div', { className: 'f3-ops-skeleton' },
      element('span', { className: 'f3-ops-skeleton__line f3-ops-skeleton__line--short' }),
      element('span', { className: 'f3-ops-skeleton__line' }),
      element('span', { className: 'f3-ops-skeleton__line f3-ops-skeleton__line--mid' }),
    ));
  }
  return wrap;
}

function pageHeader(eyebrow, title, subtitle, action = null) {
  return element('header', { className: 'f3-ops-page-header' },
    element('div', { className: 'f3-ops-page-header__copy' },
      element('span', { className: 'f3-ops-eyebrow', text: eyebrow }),
      element('h1', { className: 'f3-ops-page-title', text: title }),
      subtitle ? element('p', { className: 'f3-ops-page-subtitle', text: subtitle }) : null,
    ),
    action ? element('div', { className: 'f3-ops-page-header__action' }, action) : null,
  );
}

function metaRow(items) {
  const values = items.filter((item) => text(item?.value));
  if (!values.length) return null;
  return element('div', { className: 'f3-ops-meta' }, ...values.map((item) => element('span', { className: 'f3-ops-meta__item' },
    item.icon ? icon(item.icon) : null,
    element('span', { text: item.value }),
  )));
}

function safeLink(value) {
  const raw = text(value);
  if (!raw) return null;
  try {
    const url = new URL(raw, window.location.origin);
    if (!['https:', 'http:'].includes(url.protocol)) return null;
    if (url.protocol === 'http:' && url.origin !== window.location.origin) return null;
    return url;
  } catch (_error) {
    return null;
  }
}

function announcementActionUrl(row) {
  return safeLink(row?.safe_url || row?.action_url || row?.url);
}

function announcementScope(ctx, row) {
  const course = text(row?.course_title || row?.scope_course_title);
  const scope = text(row?.scope_label || row?.audience_label);
  return course || scope || workspaceName(ctx);
}

function orderPaymentState(row) {
  const raw = text(row?.payment_status || row?.status).toLowerCase();
  if (raw === 'pending') return 'payment_pending';
  return raw || 'unknown';
}

function explicitAccessState(row) {
  const raw = text(row?.entitlement_status || row?.access_status || row?.entitlement?.status).toLowerCase();
  if (['active', 'expired', 'revoked'].includes(raw)) return raw;
  if (row?.entitlement?.granted === true) return 'active';
  return 'unknown';
}

function accessLabel(value) {
  if (value === 'active') return 'دسترسی فعال';
  if (value === 'expired') return 'دسترسی منقضی‌شده';
  if (value === 'revoked') return 'دسترسی لغوشده';
  return 'وضعیت دسترسی ارائه نشده است';
}

function routeName(ctx, requested) {
  const raw = text(requested || ctx?.state?.route?.name || ctx?.state?.routeName || ctx?.state?.route);
  if (Object.values(ROUTES).includes(raw)) return raw;
  return ROUTES.announcements;
}

function pathFor(ctx, suffix) {
  const id = workspaceId(ctx);
  if (!id) throw new Error('ops_workspace_missing');
  return `/api/v1/workspaces/${encodeURIComponent(id)}${suffix}`;
}

function notify(ctx, message, tone = 'info') {
  if (typeof ctx?.ui?.toast === 'function') ctx.ui.toast({ message, tone });
  else if (typeof ctx?.ui?.announce === 'function') ctx.ui.announce(message, tone);
}

function renderAnnouncementList(ctx, result, controller) {
  const root = controller.root;
  root.replaceChildren(pageHeader('ارتباطات', 'اطلاعیه‌ها', 'پیام‌های رسمی فضای آموزشی، با وضعیت خوانده‌شدن حساب شما'));
  if (!result.ok) {
    const denied = result.error?.status === 403;
    root.append(stateBlock(
      denied ? 'دسترسی به اطلاعیه‌ها ندارید' : 'اطلاعیه‌ها دریافت نشدند',
      denied ? 'این مقصد برای عضویت فعلی شما در دسترس نیست.' : 'ارتباط با فهرست اطلاعیه‌ها برقرار نشد. می‌توانید دوباره تلاش کنید.',
      denied ? { tone: 'warning' } : { tone: 'danger', actionLabel: 'تلاش دوباره', onAction: () => controller.render(ROUTES.announcements) },
    ));
    return;
  }
  const rows = rowsOf(result);
  if (!rows.length) {
    root.append(stateBlock('هنوز اطلاعیه‌ای منتشر نشده است', 'پیام‌های رسمی این فضای آموزشی پس از انتشار در این صفحه قرار می‌گیرند.'));
    return;
  }
  const list = element('section', { className: 'f3-ops-list', attrs: { 'aria-label': 'فهرست اطلاعیه‌ها' } });
  rows.forEach((row) => {
    const unread = text(row.status).toLowerCase() !== 'read' && !row.read_at;
    const open = button('مشاهده', { variant: 'quiet', iconName: 'arrow', onClick: () => renderAnnouncementDetail(ctx, row, controller) });
    list.append(element('article', { className: `f3-ops-list-row${unread ? ' f3-ops-list-row--unread' : ''}` },
      element('div', { className: 'f3-ops-list-row__body' },
        element('div', { className: 'f3-ops-list-row__titleline' },
          element('h2', { className: 'f3-ops-list-row__title', text: text(row.title, 'اطلاعیه') }),
          unread ? element('span', { className: 'f3-ops-unread', text: 'خوانده‌نشده' }) : null,
        ),
        element('p', { className: 'f3-ops-list-row__preview', text: bounded(row.body, 180) || 'بدون متن پیش‌نمایش' }),
        metaRow([
          { icon: 'clock', value: formatDate(ctx, row.published_at, true) },
          { value: announcementScope(ctx, row) },
        ]),
      ),
      element('div', { className: 'f3-ops-list-row__action' }, open),
    ));
  });
  root.append(list);
}

function renderAnnouncementDetail(ctx, row, controller) {
  const root = controller.root;
  const unread = text(row.status).toLowerCase() !== 'read' && !row.read_at;
  const actionUrl = announcementActionUrl(row);
  const back = button('بازگشت به اطلاعیه‌ها', { variant: 'quiet', onClick: () => controller.render(ROUTES.announcements) });
  root.replaceChildren(pageHeader('اطلاعیه', text(row.title, 'اطلاعیه'), 'متن کامل پیام رسمی', back));
  const article = element('article', { className: 'f3-ops-detail' },
    metaRow([
      { icon: 'clock', value: formatDate(ctx, row.published_at, true) },
      { value: announcementScope(ctx, row) },
    ]),
    element('div', { className: 'f3-ops-prose', text: text(row.body, 'متنی برای این اطلاعیه ثبت نشده است.') }),
  );
  const actions = element('div', { className: 'f3-ops-actions' });
  if (unread) {
    actions.append(button('ثبت به‌عنوان خوانده‌شده', {
      variant: 'secondary',
      iconName: 'check',
      onClick: async (event) => {
        const control = event.currentTarget;
        control.disabled = true;
        try {
          await apiRequest(ctx, pathFor(ctx, `/announcements/${encodeURIComponent(row.id)}/read`), { method: 'POST', body: {}, signal: controller.signal });
          row.status = 'read';
          row.read_at = new Date().toISOString();
          notify(ctx, 'وضعیت خوانده‌شدن اطلاعیه ثبت شد.', 'success');
          renderAnnouncementDetail(ctx, row, controller);
        } catch (_error) {
          control.disabled = false;
          notify(ctx, 'ثبت وضعیت اطلاعیه انجام نشد.', 'danger');
        }
      },
    }));
  }
  if (actionUrl) {
    const link = element('a', {
      className: 'f3-ops-button f3-ops-button--primary',
      text: text(row.action_label, 'باز کردن پیوند'),
      attrs: { href: actionUrl.href, target: actionUrl.origin === window.location.origin ? '_self' : '_blank', rel: 'noopener noreferrer' },
    });
    actions.append(link);
  }
  if (actions.childElementCount) article.append(actions);
  root.append(article);
}

export async function renderCourseAnnouncementsSlot(ctx, { root, courseId, courseTitle } = {}) {
  if (!(root instanceof Element) || !text(courseId)) return;
  const heading = element('h2', { className: 'f3-ops-slot-title', text: 'اطلاعیه‌های درس' });
  root.replaceChildren(heading, stateBlock('در حال دریافت اطلاعیه‌های درس…', 'فقط اطلاعیه‌های دارای ارتباط canonical با این درس نمایش داده می‌شوند.'));
  try {
    const result = await apiRequest(ctx, pathFor(ctx, `/announcements?course_id=${encodeURIComponent(text(courseId))}`), { signal: ctx?.signal });
    const items = rowsOf(result);
    if (!items.length) {
      root.replaceChildren(heading, stateBlock('اطلاعیه‌ای برای این درس منتشر نشده است', courseTitle ? `برای «${bounded(courseTitle, 120)}» اطلاعیهٔ مرتبطی پیدا نشد.` : 'اطلاعیهٔ مرتبطی پیدا نشد.'));
      return;
    }
    const list = element('div', { className: 'f3-ops-list', attrs: { 'aria-label': 'اطلاعیه‌های درس' } });
    items.forEach((row) => list.append(element('article', { className: 'f3-ops-list-row' },
      element('div', { className: 'f3-ops-list-row__body' },
        element('h3', { className: 'f3-ops-list-row__title', text: text(row.title, 'اطلاعیه') }),
        element('p', { className: 'f3-ops-list-row__preview', text: bounded(row.body, 180) || 'بدون متن پیش‌نمایش' }),
        metaRow([{ icon: 'clock', value: formatDate(ctx, row.published_at, true) }, { value: row.scope_course_title || courseTitle || 'درس' }]),
      ),
      row.status !== 'read' && !row.read_at
        ? button('خواندم', { variant: 'quiet', onClick: async (event) => {
          const control = event.currentTarget;
          control.disabled = true;
          try {
            await apiRequest(ctx, pathFor(ctx, `/announcements/${encodeURIComponent(row.id)}/read`), { method: 'POST', body: {}, signal: ctx?.signal });
            row.status = 'read';
            row.read_at = new Date().toISOString();
            control.replaceWith(statusChip('read', 'خوانده‌شده'));
          } catch (_error) {
            control.disabled = false;
          }
        } })
        : statusChip('read', 'خوانده‌شده'),
    )));
    root.replaceChildren(heading, list);
  } catch (_error) {
    root.replaceChildren(heading, stateBlock('اطلاعیه‌های درس دریافت نشدند', 'ارتباط با اطلاعیه‌های مرتبط برقرار نشد.', { tone: 'warning' }));
  }
}

function renderNotifications(ctx, controller) {
  const root = controller.root;
  const openAnnouncements = button('رفتن به اطلاعیه‌ها', { variant: 'secondary', onClick: () => controller.navigate(ROUTES.announcements) });
  root.replaceChildren(
    pageHeader('ارتباطات', 'اعلان‌های شخصی', 'اعلان با اطلاعیه رسمی یکی نیست.'),
    element('section', { className: 'f3-ops-notification-gap' },
      element('div', { className: 'f3-ops-feature-icon' }, icon('bell')),
      element('div', {},
        element('h2', { text: 'تاریخچهٔ شخصی اعلان‌ها هنوز در وب ارائه نمی‌شود' }),
        element('p', { text: 'برای جلوگیری از نمایش سابقهٔ ناقص، رسیدهای ارسال پیام در تلگرام یا بله به‌جای صندوق اعلان استفاده نمی‌شوند. اطلاعیه‌های رسمی همچنان از بخش اطلاعیه‌ها در دسترس‌اند.' }),
        openAnnouncements,
      ),
    ),
  );
}

function parseFormSchema(row) {
  const raw = row?.schema || row?.schema_json;
  if (!raw) return null;
  if (typeof raw === 'object') return raw;
  try {
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === 'object' ? parsed : null;
  } catch (_error) {
    return null;
  }
}

function schemaFields(row) {
  const schema = parseFormSchema(row);
  return Array.isArray(schema?.fields) ? schema.fields.filter((field) => field && typeof field === 'object' && text(field.id)) : [];
}

function formAvailability(row) {
  const submitted = text(row?.submission_status).toLowerCase() === 'submitted';
  if (submitted) return { key: 'submitted', label: 'پاسخ ثبت شده' };
  return { key: 'open', label: 'باز' };
}

function renderForms(ctx, result, controller) {
  const root = controller.root;
  root.replaceChildren(pageHeader('عملیات آموزشی', 'فرم‌ها', 'فرم‌های فعال برای این حساب و فضای آموزشی'));
  if (!result.ok) {
    const denied = result.error?.status === 403;
    root.append(stateBlock(
      denied ? 'دسترسی به فرم‌ها ندارید' : 'فرم‌ها دریافت نشدند',
      denied ? 'این بخش برای عضویت فعلی شما فعال نیست.' : 'فهرست فرم‌های فعال فعلاً در دسترس نیست.',
      denied ? { tone: 'warning' } : { tone: 'danger', actionLabel: 'تلاش دوباره', onAction: () => controller.render(ROUTES.forms) },
    ));
    return;
  }
  const rows = rowsOf(result);
  if (!rows.length) {
    root.append(stateBlock('فرم فعالی وجود ندارد', 'فرم‌هایی که برای شما باز باشند در این صفحه نمایش داده می‌شوند.'));
    return;
  }
  const list = element('section', { className: 'f3-ops-card-list', attrs: { 'aria-label': 'فرم‌های فعال' } });
  rows.forEach((row) => {
    const availability = formAvailability(row);
    const submitted = availability.key === 'submitted';
    const allowMultiple = row.allow_multiple === true || row.allow_multiple === 1 || row.allow_multiple === '1';
    const singleSubmission = !allowMultiple;
    list.append(element('article', { className: 'f3-ops-form-card' },
      element('div', { className: 'f3-ops-form-card__top' },
        element('div', { className: 'f3-ops-feature-icon f3-ops-feature-icon--small' }, icon('form')),
        element('div', { className: 'f3-ops-form-card__copy' },
          element('h2', { text: text(row.title, 'فرم') }),
          row.description ? element('p', { text: bounded(row.description, 180) }) : null,
        ),
        statusChip(availability.key, availability.label),
      ),
      metaRow([
        { value: row.closes_at ? `مهلت: ${formatDate(ctx, row.closes_at, true)}` : 'بدون مهلت اعلام‌شده' },
        { value: allowMultiple ? 'امکان ثبت چند پاسخ' : 'یک پاسخ برای هر عضو' },
        { value: row.submitted_at ? `آخرین ثبت: ${formatDate(ctx, row.submitted_at, true)}` : '' },
      ]),
      element('div', { className: 'f3-ops-form-card__action' }, button(submitted && singleSubmission ? 'پاسخ ثبت شده' : 'باز کردن فرم', {
        variant: 'secondary', disabled: submitted && singleSubmission,
        onClick: () => renderFormDetail(ctx, row, controller),
      })),
    ));
  });
  root.append(list);
}

function optionValue(option) {
  return typeof option === 'object' ? text(option.value || option.id || option.label) : text(option);
}

function optionLabel(option) {
  return typeof option === 'object' ? text(option.label || option.value || option.id) : text(option);
}

function renderFormField(field) {
  const id = text(field.id);
  const label = text(field.label || field.title || field.question, 'پاسخ');
  const type = text(field.type, 'text').toLowerCase();
  const required = field.required === true;
  const help = text(field.help || field.description);
  const wrapper = element('div', { className: 'f3-ops-field' });
  if (['choice', 'multi_choice'].includes(type)) {
    const group = element('fieldset', { className: 'f3-ops-choice-group' },
      element('legend', { text: `${label}${required ? ' *' : ''}` }),
    );
    const options = Array.isArray(field.options) ? field.options : [];
    options.forEach((option, index) => {
      const value = optionValue(option);
      if (!value) return;
      const controlId = `f3-ops-form-${id}-${index}`;
      group.append(element('label', { className: 'f3-ops-choice', attrs: { for: controlId } },
        element('input', { attrs: { id: controlId, name: `field-${id}`, type: type === 'choice' ? 'radio' : 'checkbox', value, required: required && type === 'choice' ? true : null } }),
        element('span', { text: optionLabel(option) || value }),
      ));
    });
    if (help) group.append(element('small', { className: 'f3-ops-field__help', text: help }));
    wrapper.append(group);
    return wrapper;
  }
  const labelNode = element('label', { attrs: { for: `f3-ops-form-${id}` }, text: `${label}${required ? ' *' : ''}` });
  wrapper.append(labelNode);
  if (type === 'textarea') {
    wrapper.append(element('textarea', { attrs: { id: `f3-ops-form-${id}`, name: `field-${id}`, rows: '5', required: required || null } }));
  } else if (type === 'boolean') {
    wrapper.append(element('label', { className: 'f3-ops-switch-row' },
      element('input', { attrs: { id: `f3-ops-form-${id}`, name: `field-${id}`, type: 'checkbox' } }),
      element('span', { text: 'بله' }),
    ));
  } else {
    const htmlType = ['number', 'date'].includes(type) ? type : 'text';
    wrapper.append(element('input', { attrs: { id: `f3-ops-form-${id}`, name: `field-${id}`, type: htmlType, required: required || null } }));
  }
  if (help) wrapper.append(element('small', { className: 'f3-ops-field__help', text: help }));
  return wrapper;
}

function collectAnswers(form, fields) {
  const answers = {};
  fields.forEach((field) => {
    const id = text(field.id);
    const type = text(field.type, 'text').toLowerCase();
    const named = form.elements.namedItem(`field-${id}`);
    if (type === 'multi_choice') {
      answers[id] = [...form.querySelectorAll(`[name="field-${id}"]`)].filter((item) => item.checked).map((item) => item.value);
    } else if (type === 'choice') {
      answers[id] = named?.value || '';
    } else if (type === 'boolean') {
      answers[id] = Boolean(named?.checked);
    } else {
      answers[id] = named?.value ?? '';
    }
  });
  return answers;
}

function idempotencyKey() {
  const generated = globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(16).slice(2)}`;
  return generated.slice(0, 128);
}

function renderFormDetail(ctx, row, controller) {
  const root = controller.root;
  const fields = schemaFields(row);
  const back = button('بازگشت به فرم‌ها', { variant: 'quiet', onClick: () => controller.render(ROUTES.forms) });
  root.replaceChildren(pageHeader('فرم', text(row.title, 'فرم'), row.closes_at ? `مهلت ثبت: ${formatDate(ctx, row.closes_at, true)}` : 'فرم فعال', back));
  if (row.description) root.append(element('p', { className: 'f3-ops-detail-lead', text: text(row.description) }));
  const allowMultiple = row.allow_multiple === true || row.allow_multiple === 1 || row.allow_multiple === '1';
  if (text(row.submission_status).toLowerCase() === 'submitted' && !allowMultiple) {
    root.append(stateBlock('پاسخ این فرم قبلاً ثبت شده است', row.submitted_at ? `آخرین ثبت: ${formatDate(ctx, row.submitted_at, true)}` : 'برای این فرم یک پاسخ برای هر عضو مجاز است.', { tone: 'success' }));
    return;
  }
  if (!fields.length) {
    root.append(stateBlock('ساختار این فرم قابل نمایش نیست', 'فیلدهای قابل‌استفاده از سمت سرور ارائه نشده‌اند. دادهٔ خام فرم نمایش داده نمی‌شود.', { tone: 'warning' }));
    return;
  }
  const form = element('form', { className: 'f3-ops-form' });
  fields.forEach((field) => form.append(renderFormField(field)));
  const submit = button('ثبت پاسخ', { variant: 'primary', type: 'submit' });
  const feedback = element('p', { className: 'f3-ops-form-feedback', attrs: { role: 'status', 'aria-live': 'polite' } });
  form.append(element('div', { className: 'f3-ops-form__footer' }, submit, feedback));
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    submit.disabled = true;
    feedback.textContent = 'در حال ثبت پاسخ…';
    try {
      const answers = collectAnswers(form, fields);
      await apiRequest(ctx, pathFor(ctx, `/forms/${encodeURIComponent(row.id)}/submissions`), {
        method: 'POST',
        body: { answers, idempotency_key: idempotencyKey() },
        signal: controller.signal,
      });
      form.replaceChildren(stateBlock('پاسخ فرم ثبت شد', 'ثبت پاسخ توسط سرور تأیید شد. وضعیت پایدار ارسال‌ها در فهرست فعلی وب ارائه نمی‌شود.', { tone: 'success', actionLabel: 'بازگشت به فرم‌ها', onAction: () => controller.render(ROUTES.forms) }));
      notify(ctx, 'پاسخ فرم ثبت شد.', 'success');
    } catch (error) {
      const safe = safeError(error);
      feedback.textContent = safe.code === 'duplicate_submission'
        ? 'این فرم قبلاً برای حساب شما ثبت شده است.'
        : 'ثبت پاسخ انجام نشد. پاسخ‌ها را بررسی کنید و دوباره تلاش کنید.';
      submit.disabled = false;
    }
  });
  root.append(form);
}

function renderOrders(ctx, result, controller) {
  const root = controller.root;
  root.replaceChildren(pageHeader('خرید و دسترسی', 'خرید و دسترسی', 'سفارش، پرداخت و دسترسی سه وضعیت جداگانه‌اند.'));
  root.append(element('section', { className: 'f3-ops-callout' },
    element('div', { className: 'f3-ops-feature-icon' }, icon('wallet')),
    element('div', {},
      element('strong', { text: 'فهرست محصولات قابل خرید هنوز از سمت سرور ارائه نشده است' }),
      element('p', { text: 'به همین دلیل این صفحه از شما شناسهٔ فنی محصول نمی‌خواهد. در حال حاضر فقط سفارش‌های موجود نمایش داده می‌شوند.' }),
    ),
  ));
  if (!result.ok) {
    const denied = result.error?.status === 403;
    root.append(stateBlock(
      denied ? 'دسترسی به سفارش‌ها ندارید' : 'سفارش‌ها دریافت نشدند',
      denied ? 'خرید و دسترسی برای عضویت فعلی شما فعال نیست.' : 'وضعیت سفارش‌ها فعلاً قابل دریافت نیست.',
      denied ? { tone: 'warning' } : { tone: 'danger', actionLabel: 'تلاش دوباره', onAction: () => controller.render(ROUTES.orders) },
    ));
    return;
  }
  const rows = dedupeOrders(rowsOf(result));
  if (!rows.length) {
    root.append(stateBlock('هنوز سفارشی ثبت نشده است', 'پس از فراهم‌شدن فهرست خرید و ثبت سفارش معتبر، سابقهٔ سفارش‌ها اینجا نمایش داده می‌شود.'));
    return;
  }
  const list = element('section', { className: 'f3-ops-order-list', attrs: { 'aria-label': 'سفارش‌های من' } });
  rows.forEach((row) => {
    const payment = orderPaymentState(row);
    const access = explicitAccessState(row);
    list.append(element('article', { className: 'f3-ops-order-row' },
      element('div', { className: 'f3-ops-order-row__main' },
        element('h2', { text: text(row.product_name_snapshot || row.title, 'سفارش') }),
        metaRow([{ value: formatDate(ctx, row.created_at, true) }]),
      ),
      element('div', { className: 'f3-ops-order-row__states' },
        element('div', {}, element('small', { text: 'وضعیت پرداخت' }), statusChip(payment)),
        element('div', {}, element('small', { text: 'دسترسی' }), access === 'unknown' ? statusChip('unknown', accessLabel(access)) : statusChip(access, accessLabel(access))),
      ),
      element('div', { className: 'f3-ops-order-row__amount' },
        element('strong', { text: formatMoney(ctx, row.total_minor ?? row.amount_minor, row.currency) }),
        button('جزئیات', { variant: 'quiet', onClick: () => renderOrderDetail(ctx, row, controller) }),
      ),
    ));
  });
  root.append(list);
}

function timelineItem(title, description, state = 'neutral') {
  return element('li', { className: `f3-ops-timeline__item f3-ops-timeline__item--${state}` },
    element('span', { className: 'f3-ops-timeline__dot', attrs: { 'aria-hidden': 'true' } }),
    element('div', {}, element('strong', { text: title }), description ? element('p', { text: description }) : null),
  );
}

function renderOrderDetail(ctx, row, controller) {
  const root = controller.root;
  const payment = orderPaymentState(row);
  const access = explicitAccessState(row);
  const back = button('بازگشت به سفارش‌ها', { variant: 'quiet', onClick: () => controller.render(ROUTES.orders) });
  root.replaceChildren(pageHeader('جزئیات سفارش', text(row.product_name_snapshot || row.title, 'سفارش'), formatMoney(ctx, row.total_minor ?? row.amount_minor, row.currency), back));
  const timeline = element('ol', { className: 'f3-ops-timeline', attrs: { 'aria-label': 'روند سفارش' } });
  timeline.append(timelineItem('سفارش ثبت شد', formatDate(ctx, row.created_at, true), 'complete'));
  if (payment === 'paid') {
    timeline.append(timelineItem('پرداخت تأیید شد', row.paid_at ? formatDate(ctx, row.paid_at, true) : 'تأیید پرداخت از سمت سرور ثبت شده است.', 'complete'));
  } else if (['failed', 'canceled', 'cancelled'].includes(payment)) {
    timeline.append(timelineItem('پرداخت تکمیل نشد', statusLabel(payment), 'danger'));
  } else {
    timeline.append(timelineItem('پرداخت در انتظار است', statusLabel(payment), 'current'));
  }
  if (access === 'active') timeline.append(timelineItem('دسترسی فعال است', 'وضعیت دسترسی به‌صورت مستقل تأیید شده است.', 'complete'));
  else if (access === 'expired') timeline.append(timelineItem('دسترسی منقضی شده است', 'پرداخت قبلی به‌تنهایی به معنی دسترسی فعال نیست.', 'warning'));
  else if (access === 'revoked') timeline.append(timelineItem('دسترسی لغو شده است', 'این وضعیت مستقل از نتیجهٔ پرداخت نمایش داده می‌شود.', 'danger'));
  else timeline.append(timelineItem('وضعیت دسترسی جداگانه ارائه نشده است', 'فهرست عمومی سفارش‌ها در حال حاضر وضعیت دسترسی را برنمی‌گرداند؛ از روی پرداخت حدس زده نمی‌شود.', 'neutral'));
  root.append(element('section', { className: 'f3-ops-order-detail' },
    element('div', { className: 'f3-ops-order-summary' },
      element('div', {}, element('span', { text: 'مبلغ' }), element('strong', { text: formatMoney(ctx, row.total_minor ?? row.amount_minor, row.currency) })),
      element('div', {}, element('span', { text: 'پرداخت' }), statusChip(payment)),
      element('div', {}, element('span', { text: 'دسترسی' }), access === 'unknown' ? statusChip('unknown', accessLabel(access)) : statusChip(access, accessLabel(access))),
    ),
    timeline,
  ));
}

function capabilityCard(iconName, title, description, content) {
  return element('section', { className: 'f3-ops-management-card' },
    element('div', { className: 'f3-ops-management-card__header' },
      element('div', { className: 'f3-ops-feature-icon' }, icon(iconName)),
      element('div', {}, element('h2', { text: title }), element('p', { text: description })),
    ),
    content || null,
  );
}

function announcementComposer(ctx, controller) {
  const form = element('form', { className: 'f3-ops-compact-form' });
  const titleInput = element('input', { attrs: { id: 'f3-ops-ann-title', name: 'title', type: 'text', maxlength: '200', required: true } });
  const bodyInput = element('textarea', { attrs: { id: 'f3-ops-ann-body', name: 'body', rows: '6', required: true } });
  const submit = button('انتشار اطلاعیه', { variant: 'primary', type: 'submit' });
  const feedback = element('p', { className: 'f3-ops-form-feedback', attrs: { role: 'status', 'aria-live': 'polite' } });
  form.append(
    element('div', { className: 'f3-ops-field' }, element('label', { attrs: { for: 'f3-ops-ann-title' }, text: 'عنوان' }), titleInput),
    element('div', { className: 'f3-ops-field' }, element('label', { attrs: { for: 'f3-ops-ann-body' }, text: 'متن اطلاعیه' }), bodyInput),
    element('p', { className: 'f3-ops-inline-note', text: 'قرارداد فعلی اطلاعیه را بلافاصله منتشر می‌کند؛ پیش‌نویس یا ویرایش پس از انتشار در حال حاضر وجود ندارد.' }),
    element('div', { className: 'f3-ops-form__footer' }, submit, feedback),
  );
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    submit.disabled = true;
    feedback.textContent = 'در حال انتشار…';
    try {
      await apiRequest(ctx, pathFor(ctx, '/announcements'), { method: 'POST', body: { title: titleInput.value.trim(), body: bodyInput.value.trim() }, signal: controller.signal });
      form.reset();
      feedback.textContent = 'اطلاعیه منتشر شد.';
      notify(ctx, 'اطلاعیه منتشر شد.', 'success');
    } catch (_error) {
      feedback.textContent = 'انتشار اطلاعیه انجام نشد.';
    } finally {
      submit.disabled = false;
    }
  });
  return form;
}

function newFormFieldRow(index) {
  const row = element('div', { className: 'f3-ops-builder-row', dataset: { fieldIndex: index } });
  const label = element('input', { attrs: { type: 'text', placeholder: 'متن پرسش', maxlength: '200', required: true }, dataset: { role: 'label' } });
  const type = element('select', { dataset: { role: 'type' }, attrs: { 'aria-label': 'نوع فیلد' } });
  FORM_TYPES.forEach(([value, caption]) => type.append(element('option', { text: caption, attrs: { value } })));
  const required = element('label', { className: 'f3-ops-builder-required' },
    element('input', { attrs: { type: 'checkbox' }, dataset: { role: 'required' } }),
    element('span', { text: 'اجباری' }),
  );
  const options = element('input', { attrs: { type: 'text', placeholder: 'گزینه‌ها با «،» جدا شوند' }, dataset: { role: 'options' } });
  options.hidden = true;
  type.addEventListener('change', () => { options.hidden = !['choice', 'multi_choice'].includes(type.value); });
  const remove = button('حذف', { variant: 'quiet', iconName: 'close', onClick: () => row.remove() });
  row.append(element('div', { className: 'f3-ops-builder-row__top' }, label, type, required, remove), options);
  return row;
}

function buildManagedFormSchema(fieldContainer) {
  const rows = [...fieldContainer.querySelectorAll('.f3-ops-builder-row')];
  return {
    fields: rows.map((row, index) => {
      const fieldType = row.querySelector('[data-role="type"]').value;
      const field = {
        id: `field_${index + 1}`,
        type: fieldType,
        label: row.querySelector('[data-role="label"]').value.trim(),
        required: row.querySelector('[data-role="required"]').checked,
      };
      if (['choice', 'multi_choice'].includes(fieldType)) {
        field.options = row.querySelector('[data-role="options"]').value.split(/[،,]/).map((item) => item.trim()).filter(Boolean);
      }
      return field;
    }),
  };
}

function formCreator(ctx, controller) {
  const form = element('form', { className: 'f3-ops-compact-form' });
  const title = element('input', { attrs: { id: 'f3-ops-form-title', type: 'text', maxlength: '200', required: true } });
  const openNow = element('input', { attrs: { id: 'f3-ops-form-open', type: 'checkbox' } });
  const fields = element('div', { className: 'f3-ops-builder' });
  fields.append(newFormFieldRow(1));
  const add = button('افزودن پرسش', { variant: 'secondary', iconName: 'plus', onClick: () => fields.append(newFormFieldRow(fields.childElementCount + 1)) });
  const submit = button('ساخت فرم', { variant: 'primary', type: 'submit' });
  const feedback = element('p', { className: 'f3-ops-form-feedback', attrs: { role: 'status', 'aria-live': 'polite' } });
  form.append(
    element('div', { className: 'f3-ops-field' }, element('label', { attrs: { for: 'f3-ops-form-title' }, text: 'عنوان فرم' }), title),
    element('div', { className: 'f3-ops-section-heading' }, element('strong', { text: 'پرسش‌ها' }), add),
    fields,
    element('label', { className: 'f3-ops-switch-row', attrs: { for: 'f3-ops-form-open' } }, openNow, element('span', { text: 'فرم بلافاصله باز شود' })),
    element('p', { className: 'f3-ops-inline-note', text: 'قرارداد فعلی ساخت فرم، مهلت و توضیح را دریافت نمی‌کند؛ این موارد در رابط کاربری جعل نمی‌شوند.' }),
    element('div', { className: 'f3-ops-form__footer' }, submit, feedback),
  );
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const schema = buildManagedFormSchema(fields);
    if (!schema.fields.length || schema.fields.some((field) => !field.label || (['choice', 'multi_choice'].includes(field.type) && !field.options?.length))) {
      feedback.textContent = 'متن همهٔ پرسش‌ها و گزینه‌های لازم را کامل کنید.';
      return;
    }
    submit.disabled = true;
    feedback.textContent = 'در حال ساخت فرم…';
    try {
      await apiRequest(ctx, pathFor(ctx, '/forms'), { method: 'POST', body: { title: title.value.trim(), schema, open: openNow.checked }, signal: controller.signal });
      form.reset();
      fields.replaceChildren(newFormFieldRow(1));
      feedback.textContent = 'فرم ساخته شد.';
      notify(ctx, 'فرم ساخته شد.', 'success');
    } catch (_error) {
      feedback.textContent = 'ساخت فرم انجام نشد.';
    } finally {
      submit.disabled = false;
    }
  });
  return form;
}

function memberTable(ctx, result, controller) {
  if (!result?.ok) {
    return stateBlock('فهرست اعضا در دسترس نیست', result?.error?.status === 403 ? 'مجوز مشاهده اعضا برای این حساب وجود ندارد.' : 'دریافت اعضای فضای آموزشی انجام نشد.', { tone: 'warning' });
  }
  const rows = rowsOf(result);
  if (!rows.length) return stateBlock('عضوی برای نمایش وجود ندارد', 'در دادهٔ مدیریتی فعلی عضوی برای نمایش برگردانده نشد.');
  const table = element('div', { className: 'f3-ops-member-table', attrs: { role: 'table', 'aria-label': 'اعضای فضای آموزشی' } });
  table.append(element('div', { className: 'f3-ops-member-table__head', attrs: { role: 'row' } },
    element('span', { attrs: { role: 'columnheader' }, text: 'عضو' }),
    element('span', { attrs: { role: 'columnheader' }, text: 'نقش' }),
    element('span', { attrs: { role: 'columnheader' }, text: 'وضعیت' }),
    element('span', { attrs: { role: 'columnheader' }, text: 'عملیات' }),
  ));
  rows.forEach((row) => {
    const action = hasCapability(ctx, 'membership.manage')
      ? button('نماینده شود', { variant: 'quiet', onClick: async (event) => {
        const control = event.currentTarget;
        control.disabled = true;
        try {
          await apiRequest(ctx, pathFor(ctx, '/admin/representatives'), { method: 'POST', body: { user_id: row.user_id }, signal: controller.signal });
          notify(ctx, 'نقش نماینده برای عضو ثبت شد.', 'success');
          await controller.render(ROUTES.management);
        } catch (_error) {
          control.disabled = false;
          notify(ctx, 'ثبت نقش نماینده انجام نشد.', 'danger');
        }
      } })
      : element('span', { className: 'f3-ops-muted', text: 'فقط مشاهده' });
    table.append(element('div', { className: 'f3-ops-member-table__row', attrs: { role: 'row' } },
      element('span', { attrs: { role: 'cell' }, text: text(row.display_name, 'عضو') }),
      element('span', { attrs: { role: 'cell' }, text: roleLabels(row.role_keys) }),
      element('span', { attrs: { role: 'cell' } }, statusChip(row.status || 'active', statusLabel(row.status || 'active'))),
      element('span', { attrs: { role: 'cell' } }, action),
    ));
  });
  return table;
}

function managementCount(ctx, dashboard, key) {
  const value = dashboard?.sections?.[key];
  return Number.isFinite(Number(value)) ? formatNumber(ctx, value) : '';
}

async function renderManagement(ctx, dashboardResult, controller) {
  const root = controller.root;
  root.replaceChildren(pageHeader('مدیریت', 'مدیریت فضای آموزشی', 'فقط قابلیت‌هایی نمایش داده می‌شوند که قرارداد و مجوز معتبر دارند.'));
  if (!dashboardResult.ok) {
    root.append(stateBlock(
      dashboardResult.error?.status === 403 ? 'اجازه ورود به مدیریت را ندارید' : 'مدیریت در دسترس نیست',
      dashboardResult.error?.status === 403 ? 'این مقصد برای سطح دسترسی فعلی شما فعال نیست.' : 'دریافت وضعیت مدیریتی انجام نشد.',
      dashboardResult.error?.status === 403 ? { tone: 'warning' } : { tone: 'danger', actionLabel: 'تلاش دوباره', onAction: () => controller.render(ROUTES.management) },
    ));
    return;
  }
  const dashboard = dashboardResult.data || {};
  if (dashboard.management_available !== true) {
    root.append(stateBlock('قابلیت مدیریتی فعالی ندارید', 'برای این فضای آموزشی عملیات مدیریتی قابل انجامی به حساب شما واگذار نشده است.', { tone: 'warning' }));
    return;
  }
  const grid = element('div', { className: 'f3-ops-management-grid' });
  if (hasCapability(ctx, 'notification.broadcast')) {
    grid.append(capabilityCard('announcement', 'انتشار اطلاعیه', 'انتشار مستقیم پیام رسمی برای اعضای فضای آموزشی', announcementComposer(ctx, controller)));
  }
  if (hasCapability(ctx, 'form.manage')) {
    grid.append(capabilityCard('form', 'ساخت فرم', `${managementCount(ctx, dashboard, 'forms') ? `${managementCount(ctx, dashboard, 'forms')} فرم ثبت‌شده · ` : ''}ساخت فرم با فیلدهای پشتیبانی‌شده`, formCreator(ctx, controller)));
  }
  const memberSectionAvailable = Object.prototype.hasOwnProperty.call(dashboard.sections || {}, 'members');
  if (memberSectionAvailable || hasCapability(ctx, 'membership.manage')) {
    const members = await safeRead(ctx, pathFor(ctx, '/admin/members'), controller.signal);
    if (controller.signal.aborted) return;
    grid.append(capabilityCard('users', 'اعضای فضای آموزشی', managementCount(ctx, dashboard, 'members') ? `${managementCount(ctx, dashboard, 'members')} عضو در دادهٔ مدیریتی` : 'مشاهده اعضا و عملیات مجاز نقش‌ها', memberTable(ctx, members, controller)));
  }
  if (hasCapability(ctx, 'resource.review') || hasCapability(ctx, 'resource.publish')) {
    grid.append(capabilityCard('content', 'بررسی و انتشار محتوا', 'عملیات بررسی و انتشار در قرارداد سرور وجود دارد، اما فهرست فعلی شناسهٔ نسخهٔ در انتظار را ارائه نمی‌کند.',
      stateBlock('صف بررسی به دادهٔ کامل‌تری نیاز دارد', 'تا وقتی سرور نسخهٔ هدف را به‌صورت مجاز و مشخص ارائه نکند، دکمهٔ تأیید یا انتشار ساخته نمی‌شود.', { tone: 'warning' })));
  }
  if (hasCapability(ctx, 'payment.reconcile') || hasCapability(ctx, 'commerce.manage_catalog') || hasCapability(ctx, 'entitlement.grant')) {
    grid.append(capabilityCard('wallet', 'تجارت و دسترسی', managementCount(ctx, dashboard, 'orders') ? `${managementCount(ctx, dashboard, 'orders')} سفارش در فضای آموزشی` : 'عملیات مالی و دسترسی',
      stateBlock('مدیریت مالی به دادهٔ عملیاتی مشخص نیاز دارد', 'فهرست سفارش‌های دانشجو، شناسهٔ لازم برای پیگیری پرداخت یا فهرست دسترسی‌های مدیریتی را برنمی‌گرداند؛ ورودی شناسهٔ فنی دستی نمایش داده نمی‌شود.', { tone: 'neutral' })));
  }
  if (!grid.childElementCount) {
    grid.append(stateBlock('عملیات مدیریتی قابل نمایش نیست', 'مجوز کلی مدیریت تأیید شده است، اما قابلیت قابل‌اتکایی برای این ماژول از زیرساخت مشترک مجوزها ارائه نشده است.'));
  }
  root.append(grid);
}

async function renderRoute(ctx, requestedRoute, controller) {
  controller.abort();
  controller.abortController = new AbortController();
  controller.signal = controller.abortController.signal;
  const route = routeName(ctx, requestedRoute);
  controller.currentRoute = route;
  controller.root.replaceChildren(skeleton(route === ROUTES.management ? 5 : 4));
  if (!workspaceId(ctx)) {
    controller.root.replaceChildren(pageHeader('فضای آموزشی', 'فضای آموزشی فعال لازم است', 'این مقصد بدون فضای آموزشی فعال قابل استفاده نیست.'), stateBlock('فضای آموزشی انتخاب نشده است', 'ابتدا یک فضای آموزشی فعال انتخاب کنید.', { tone: 'warning' }));
    return;
  }
  if (route === ROUTES.notifications) {
    renderNotifications(ctx, controller);
    return;
  }
  if (route === ROUTES.announcements) {
    const result = await safeRead(ctx, pathFor(ctx, '/announcements'), controller.signal);
    if (!controller.signal.aborted) renderAnnouncementList(ctx, result, controller);
    return;
  }
  if (route === ROUTES.forms) {
    const result = await safeRead(ctx, pathFor(ctx, '/forms'), controller.signal);
    if (!controller.signal.aborted) renderForms(ctx, result, controller);
    return;
  }
  if (route === ROUTES.orders) {
    const result = await safeRead(ctx, pathFor(ctx, '/orders'), controller.signal);
    if (!controller.signal.aborted) renderOrders(ctx, result, controller);
    return;
  }
  if (route === ROUTES.management) {
    const result = await safeRead(ctx, pathFor(ctx, '/admin/dashboard'), controller.signal);
    if (!controller.signal.aborted) await renderManagement(ctx, result, controller);
  }
}

function navigate(ctx, route, controller) {
  if (typeof ctx?.navigate === 'function') {
    ctx.navigate(route);
    return;
  }
  controller.render(route);
}

function createController(ctx) {
  const root = ctx?.root;
  if (!(root instanceof Element)) throw new Error('ops_root_missing');
  const controller = {
    root,
    signal: null,
    abortController: null,
    currentRoute: null,
    abort() {
      if (this.abortController) this.abortController.abort();
    },
    navigate(route) {
      navigate(ctx, route, this);
    },
    render(route) {
      return renderRoute(ctx, route, this);
    },
    destroy() {
      this.abort();
      this.root.replaceChildren();
    },
  };
  return controller;
}

export function createPurchaseHandoff(ctx) {
  return function handoff(serverUrl) {
    const url = safeLink(serverUrl);
    if (!url) throw new Error('ops_payment_handoff_invalid');
    if (typeof ctx?.ui?.handoff === 'function') return ctx.ui.handoff({ kind: 'payment', url: url.href });
    window.location.assign(url.href);
    return undefined;
  };
}

export async function beginPurchase(ctx, product, { signal } = {}) {
  const productId = text(product?.id || product?.product_id);
  if (!productId) throw new Error('ops_catalog_product_missing');
  const order = await apiRequest(ctx, pathFor(ctx, '/orders'), {
    method: 'POST',
    body: { product_id: productId, idempotency_key: idempotencyKey() },
    signal,
  });
  if (order?.redirect_url) createPurchaseHandoff(ctx)(order.redirect_url);
  return order;
}

export const operationsCapabilityMap = Object.freeze({
  announcementPublish: 'notification.broadcast',
  formManage: 'form.manage',
  memberRead: 'membership.view',
  memberManage: 'membership.manage',
  contentCreate: 'resource.create',
  contentReview: 'resource.review',
  contentPublish: 'resource.publish',
  catalogManage: 'commerce.manage_catalog',
  paymentReconcile: 'payment.reconcile',
  entitlementGrant: 'entitlement.grant',
});

export const moduleDefinition = Object.freeze({
  id: 'operations',
  routes: [ROUTES.announcements, ROUTES.notifications, ROUTES.forms, ROUTES.orders, ROUTES.management],
  navItems: [
    { id: ROUTES.announcements, label: 'اطلاعیه‌ها', group: 'more', icon: 'announcement' },
    { id: ROUTES.notifications, label: 'اعلان‌ها', group: 'more', icon: 'bell' },
    { id: ROUTES.forms, label: 'فرم‌ها', group: 'more', icon: 'form' },
    { id: ROUTES.orders, label: 'خرید و دسترسی', group: 'more', icon: 'wallet' },
    { id: ROUTES.management, label: 'مدیریت', group: 'conditional', icon: 'manage', conditional: true },
  ],
  styles: ['/assets/ui-v3/operations/operations.css'],
  mount(ctx) {
    const controller = createController(ctx);
    mountedControllers.set(ctx.root, controller);
    controller.render();
    return controller;
  },
  unmount(ctx) {
    const controller = ctx?.root ? mountedControllers.get(ctx.root) : null;
    if (controller) {
      controller.destroy();
      mountedControllers.delete(ctx.root);
    }
  },
});

export { ROUTES, renderRoute, statusLabel, explicitAccessState };
