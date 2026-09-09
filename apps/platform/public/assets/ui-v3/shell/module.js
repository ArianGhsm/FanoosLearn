import { createRouteRegistry, createShellRouter, DEFAULT_ROUTE_REFERENCES } from './router.js';
import { createShellApi, shellErrorMessage } from './api.js';
import {
  button,
  cleanText,
  createSheet,
  dispatchShellEvent,
  element,
  formatCount,
  icon,
  initials,
  skeletonShell,
  statePanel,
  statusBadge,
  workspaceName,
  workspacePath,
} from './ui.js';

const PRIMARY_IDS = Object.freeze(['home', 'courses', 'schedule', 'resources', 'assessments', 'grades', 'announcements']);
const SECONDARY_IDS = Object.freeze(['forms', 'orders']);
const MOBILE_IDS = Object.freeze(['home', 'courses', 'schedule', 'resources']);

const SHELL_ROUTES = Object.freeze([
  { id: 'account', path: '/account', label: 'حساب', longLabel: 'حساب و فضاهای آموزشی', eyebrow: 'حساب', slot: 'account', workspaceRequired: false },
  { id: 'more', path: '/more', label: 'بیشتر', eyebrow: 'فانوس', slot: 'mobile-more', workspaceRequired: false },
]);

const NAV_ITEMS = Object.freeze(DEFAULT_ROUTE_REFERENCES.map(({ id, label, longLabel, slot, icon: iconName, capability, workspaceRequired }) => ({
  id, label, longLabel, slot, icon: iconName, capability, workspaceRequired,
})));

function normalizeAccount(payload) {
  const account = payload && typeof payload === 'object' ? payload : {};
  const user = account.user && typeof account.user === 'object' ? account.user : {};
  const workspaces = Array.isArray(account.workspaces)
    ? account.workspaces.filter((workspace) => workspace && typeof workspace === 'object' && workspace.id)
    : [];
  const selected = account.selected_workspace_id;
  const selectedWorkspaceId = selected && workspaces.some((workspace) => String(workspace.id) === String(selected))
    ? String(selected)
    : null;
  return { ...account, user, workspaces, selected_workspace_id: selectedWorkspaceId };
}

function failureStatus(error) { return Number(error?.status) || 0; }
function userName(account) { return cleanText(account?.user?.display_name, 'حساب کاربری'); }
function pathLine(workspace) { return workspacePath(workspace) || 'فضای آموزشی فانوس'; }

class ShellController {
  constructor(ctx) {
    this.ctx = ctx;
    this.root = ctx.root;
    this.api = createShellApi({ ...ctx, api: ctx.api });
    this.registry = createRouteRegistry();
    if (Array.isArray(ctx.modules)) ctx.modules.forEach((definition) => this.registry.registerModule(definition));
    if (Array.isArray(ctx.routes)) ctx.routes.forEach((route) => this.registry.register(route));
    this.router = createShellRouter({
      registry: this.registry,
      navigate: ctx.navigate,
      mode: ctx.routerMode,
      onChange: (route) => this.onRoute(route),
    });
    this.state = {
      account: null,
      auth: 'checking',
      selectedWorkspaceId: null,
      managementAvailable: false,
      managementProjection: null,
      route: null,
      workspaceEpoch: 0,
      workspaceMutation: false,
      workspaceError: '',
      accountRefreshWarning: false,
    };
    this.refs = {};
    this.sheets = [];
    this.accountAbort = null;
    this.managementAbort = null;
    this.destroyed = false;
    this.boundSignalAbort = () => this.unmount();
  }

  mount() {
    if (!this.root || typeof this.root.replaceChildren !== 'function') throw new Error('FANOOS shell requires ctx.root.');
    this.root.classList.add('f3-shell-host');
    this.root.replaceChildren(skeletonShell());
    if (this.ctx.signal?.aborted) {
      this.unmount();
      return this;
    }
    if (this.ctx.signal) this.ctx.signal.addEventListener('abort', this.boundSignalAbort, { once: true });
    this.bootstrap();
    return this;
  }

  async bootstrap() {
    this.accountAbort?.abort();
    this.accountAbort = new AbortController();
    try {
      const account = normalizeAccount(await this.api.account({ signal: this.accountAbort.signal }));
      if (this.destroyed) return;
      this.acceptAccount(account);
      await this.refreshManagement({ quiet: true });
      if (this.destroyed) return;
      this.state.auth = 'signed-in';
      this.renderApp();
      this.router.start();
      dispatchShellEvent(this.root, 'fanoos:v3:session', { phase: 'signed-in' });
    } catch (error) {
      if (error?.name === 'AbortError' || this.destroyed) return;
      if (failureStatus(error) === 401) {
        this.state.auth = 'signed-out';
        this.renderAuth();
        return;
      }
      this.state.auth = 'unknown';
      this.renderSessionCheckError(error);
    }
  }

  acceptAccount(account) {
    this.state.account = normalizeAccount(account);
    this.state.selectedWorkspaceId = this.state.account.selected_workspace_id || null;
  }

  currentWorkspace() {
    const id = this.state.selectedWorkspaceId;
    return this.state.account?.workspaces?.find((workspace) => String(workspace.id) === String(id)) || null;
  }

  async refreshManagement({ quiet = false } = {}) {
    this.managementAbort?.abort();
    this.state.managementAvailable = false;
    this.state.managementProjection = null;
    if (!this.state.selectedWorkspaceId) return;
    this.managementAbort = new AbortController();
    try {
      const result = await this.api.management(this.state.selectedWorkspaceId, { signal: this.managementAbort.signal });
      if (this.destroyed) return;
      this.state.managementAvailable = result.available === true;
      this.state.managementProjection = result.projection || null;
    } catch (error) {
      if (error?.name === 'AbortError') return;
      if (failureStatus(error) === 401) {
        this.handleSessionExpired();
        return;
      }
      if (!quiet) this.announce('بررسی دسترسی مدیریتی کامل نشد. سایر بخش‌های فانوس همچنان قابل استفاده‌اند.');
    }
  }

  onRoute(route) {
    if (this.destroyed) return;
    this.state.route = route;
    if (route?.id === 'management' && !this.state.managementAvailable) {
      this.router.navigate('home', {}, { replace: true });
      return;
    }
    if (this.state.auth === 'signed-in') this.renderApp();
  }

  destroySheets() {
    this.sheets.forEach((sheet) => sheet.destroy());
    this.sheets = [];
  }

  renderSessionCheckError(error) {
    this.destroySheets();
    const retry = button('تلاش دوباره', { variant: 'primary', icon: 'retry', onClick: () => {
      this.root.replaceChildren(skeletonShell());
      this.bootstrap();
    } });
    const login = button('ورود به حساب', { variant: 'quiet', onClick: () => {
      this.state.auth = 'signed-out';
      this.renderAuth();
    } });
    const panel = statePanel('بررسی نشست انجام نشد', shellErrorMessage(error), {
      kind: 'warning', icon: 'info', action: element('div', { className: 'f3-shell-inline-actions' }, retry, login),
    });
    panel.querySelector('h2').id = 'f3-shell-session-check-title';
    this.root.replaceChildren(element('main', { className: 'f3-shell-session-check', attrs: { 'aria-labelledby': 'f3-shell-session-check-title' } },
      element('div', { className: 'f3-shell-session-check__brand' }, icon('lantern'), element('strong', { text: 'فانوس' })),
      panel,
    ));
  }

  renderAuth(message = '') {
    this.destroySheets();
    const identifier = element('input', {
      id: 'f3-shell-login-identifier', className: 'f3-shell-input',
      attrs: { name: 'identifier', type: 'text', autocomplete: 'username', required: true, 'aria-describedby': 'f3-shell-login-message' },
    });
    const password = element('input', {
      id: 'f3-shell-login-password', className: 'f3-shell-input f3-shell-input--password',
      attrs: { name: 'password', type: 'password', autocomplete: 'current-password', required: true, 'aria-describedby': 'f3-shell-login-message' },
    });
    const reveal = element('button', {
      className: 'f3-shell-password-toggle', attrs: { type: 'button', 'aria-label': 'نمایش رمز عبور', 'aria-pressed': 'false' },
    }, icon('eye'));
    reveal.addEventListener('click', () => {
      const wasVisible = password.type === 'text';
      password.type = wasVisible ? 'password' : 'text';
      reveal.setAttribute('aria-pressed', wasVisible ? 'false' : 'true');
      reveal.setAttribute('aria-label', wasVisible ? 'نمایش رمز عبور' : 'پنهان کردن رمز عبور');
      reveal.replaceChildren(icon(wasVisible ? 'eye' : 'eye-off'));
      password.focus();
    });
    const messageNode = element('div', {
      id: 'f3-shell-login-message', className: 'f3-shell-form-message', text: message,
      attrs: { role: 'status', 'aria-live': 'polite' },
    });
    const submit = button('ورود', { variant: 'primary', type: 'submit', className: 'f3-shell-button--block' });
    const form = element('form', { id: 'f3-shell-auth-form', className: 'f3-shell-login-form', attrs: { novalidate: true } },
      element('div', { className: 'f3-shell-field' }, element('label', { text: 'شناسه', attrs: { for: 'f3-shell-login-identifier' } }), identifier),
      element('div', { className: 'f3-shell-field' }, element('label', { text: 'رمز عبور', attrs: { for: 'f3-shell-login-password' } }), element('div', { className: 'f3-shell-password-field' }, password, reveal)),
      messageNode,
      submit,
    );
    form.addEventListener('submit', (event) => this.handleLogin(event, { identifier, password, submit, form, messageNode }));

    const auth = element('main', { className: 'f3-shell-auth', attrs: { 'aria-labelledby': 'f3-shell-login-title' } },
      element('a', { className: 'f3-shell-skip', text: 'رفتن به فرم ورود', attrs: { href: '#f3-shell-auth-form' } }),
      element('section', { className: 'f3-shell-auth__story' },
        element('div', { className: 'f3-shell-brand f3-shell-brand--auth' },
          element('span', { className: 'f3-shell-brand__mark' }, icon('lantern')),
          element('div', {}, element('strong', { text: 'فانوس' }), element('small', { text: 'فضای آموزشی دانشجو' })),
        ),
        element('div', { className: 'f3-shell-auth__copy' },
          element('p', { className: 'f3-shell-eyebrow', text: 'یک فضای منظم برای مسیر دانشگاه' }),
          element('h1', { text: 'درس، برنامه و منابع؛ در یک جای مشخص' }),
          element('p', { text: 'با حساب فانوس وارد فضای آموزشی خودت شو و بدون جابه‌جایی میان سامانه‌های پراکنده، مسیر اصلی کارها را پیدا کن.' }),
        ),
        element('div', { className: 'f3-shell-auth__features', attrs: { 'aria-label': 'بخش‌های اصلی فانوس' } },
          this.authFeature('courses', 'درس‌ها', 'دسترسی سریع به فضای هر درس'),
          this.authFeature('calendar', 'برنامه', 'کلاس‌ها و رویدادهای آموزشی'),
          this.authFeature('resources', 'منابع', 'محتوای مجاز و منابع یادگیری'),
        ),
      ),
      element('section', { className: 'f3-shell-login-panel' },
        element('div', { className: 'f3-shell-login-card' },
          element('div', { className: 'f3-shell-login-card__heading' },
            element('span', { className: 'f3-shell-login-card__mark' }, icon('lantern')),
            element('div', {}, element('h2', { id: 'f3-shell-login-title', text: 'ورود به فانوس' }), element('p', { text: 'از شناسه و رمز عبور حساب خود استفاده کنید.' })),
          ),
          form,
          element('p', { className: 'f3-shell-login-note', text: 'پس از ورود، فقط فضاهای آموزشی فعال همین حساب نمایش داده می‌شوند.' }),
        ),
      ),
    );
    this.root.replaceChildren(auth);
    requestAnimationFrame(() => identifier.focus());
  }

  authFeature(iconName, title, description) {
    return element('div', { className: 'f3-shell-auth-feature' },
      element('span', { className: 'f3-shell-auth-feature__icon' }, icon(iconName)),
      element('div', {}, element('strong', { text: title }), element('span', { text: description })),
    );
  }

  async handleLogin(event, refs) {
    event.preventDefault();
    if (refs.form.getAttribute('aria-busy') === 'true') return;
    const identifier = refs.identifier.value.trim();
    const password = refs.password.value;
    if (!identifier || !password) {
      refs.messageNode.textContent = 'شناسه و رمز عبور را وارد کنید.';
      (!identifier ? refs.identifier : refs.password).focus();
      return;
    }
    refs.form.setAttribute('aria-busy', 'true');
    refs.submit.disabled = true;
    refs.identifier.disabled = true;
    refs.password.disabled = true;
    refs.messageNode.textContent = 'در حال ورود…';
    try {
      await this.api.login(identifier, password);
      if (this.destroyed) return;
      refs.messageNode.textContent = 'ورود تأیید شد.';
      this.root.replaceChildren(skeletonShell());
      await this.bootstrap();
    } catch (error) {
      if (this.destroyed) return;
      refs.messageNode.textContent = shellErrorMessage(error, 'login');
      refs.form.setAttribute('aria-busy', 'false');
      refs.submit.disabled = false;
      refs.identifier.disabled = false;
      refs.password.disabled = false;
      refs.password.value = '';
      refs.password.focus();
    }
  }

  renderApp() {
    if (this.destroyed || this.state.auth !== 'signed-in') return;
    this.destroySheets();
    const current = this.currentWorkspace();
    const route = this.state.route || this.router.current || this.registry.get('home');
    const shell = element('div', { className: 'f3-shell', attrs: { 'data-f3-shell-root': '', dir: 'rtl', lang: 'fa' } });
    const outlet = element('div', { id: 'f3-shell-route-outlet', className: 'f3-shell-outlet', attrs: { 'data-f3-shell-outlet': 'route', 'aria-live': 'polite' } });
    const backdrop = element('div', { className: 'f3-shell-backdrop', attrs: { hidden: true, 'aria-hidden': 'true' } });
    const workspaceSheet = this.renderWorkspaceSheet();
    const moreSheet = this.renderMoreSheet();
    const live = element('div', { id: 'f3-shell-live', className: 'f3-shell-sr-only', attrs: { role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true' } });
    shell.append(
      element('a', { className: 'f3-shell-skip', text: 'رفتن به محتوای اصلی', attrs: { href: '#f3-shell-content' } }),
      this.renderSidebar(route, current),
      element('div', { className: 'f3-shell-stage' },
        this.renderTopbar(current),
        element('main', { id: 'f3-shell-content', className: 'f3-shell-content', attrs: { tabindex: '-1' } }, this.renderContextBar(route, current), outlet),
      ),
      this.renderMobileNav(route),
      backdrop,
      workspaceSheet,
      moreSheet,
      live,
    );
    this.root.replaceChildren(shell);
    this.refs = { shell, outlet, backdrop, workspaceSheet, moreSheet, live };

    const workspaceController = createSheet(workspaceSheet, { backdrop });
    const moreController = createSheet(moreSheet, { backdrop });
    this.sheets.push(workspaceController, moreController);
    shell.querySelectorAll('[data-f3-workspace-trigger]').forEach((trigger) => trigger.addEventListener('click', () => {
      const returnTarget = moreController.isOpen ? shell.querySelector('.f3-shell-topbar-workspace') || trigger : trigger;
      if (moreController.isOpen) moreController.close({ restoreFocus: false });
      workspaceController.show(returnTarget);
    }));
    shell.querySelectorAll('[data-f3-more-trigger]').forEach((trigger) => trigger.addEventListener('click', () => moreController.show(trigger)));
    workspaceSheet.querySelectorAll('[data-workspace-id]').forEach((node) => node.addEventListener('click', () => this.switchWorkspace(node.dataset.workspaceId, workspaceController)));

    this.renderRouteContent(route, outlet);
    dispatchShellEvent(this.root, 'fanoos:v3:shell-ready', {
      routeOutletId: 'f3-shell-route-outlet', workspaceId: this.state.selectedWorkspaceId, workspaceEpoch: this.state.workspaceEpoch,
    });
    if (this.state.accountRefreshWarning) this.announce('فضای آموزشی تغییر کرد. بعضی جزئیات در تازه‌سازی بعدی کامل می‌شوند.');
  }

  renderSidebar(route, workspace) {
    const nav = element('nav', { className: 'f3-shell-sidebar__nav', attrs: { 'aria-label': 'ناوبری اصلی' } });
    PRIMARY_IDS.forEach((id) => nav.append(this.navButton(id, route, { desktop: true })));
    const secondary = element('nav', { className: 'f3-shell-sidebar__secondary', attrs: { 'aria-label': 'بخش‌های تکمیلی' } }, element('span', { className: 'f3-shell-nav-label', text: 'بیشتر' }));
    SECONDARY_IDS.forEach((id) => secondary.append(this.navButton(id, route, { desktop: true, secondary: true })));
    if (this.state.managementAvailable) secondary.append(this.navButton('management', route, { desktop: true, secondary: true }));

    return element('aside', { className: 'f3-shell-sidebar', attrs: { 'aria-label': 'فانوس' } },
      element('button', { className: 'f3-shell-brand', attrs: { type: 'button', 'aria-label': 'فانوس؛ خانه' }, on: { click: () => this.router.navigate('home') } },
        element('span', { className: 'f3-shell-brand__mark' }, icon('lantern')),
        element('span', { className: 'f3-shell-brand__copy' }, element('strong', { text: 'فانوس' }), element('small', { text: 'فضای آموزشی دانشجو' })),
      ),
      element('button', { className: 'f3-shell-workspace-card', attrs: { type: 'button', 'data-f3-workspace-trigger': '', 'aria-haspopup': 'dialog', 'aria-controls': 'f3-shell-workspace-sheet' } },
        element('span', { className: 'f3-shell-workspace-card__icon' }, icon('workspace')),
        element('span', { className: 'f3-shell-workspace-card__copy' }, element('small', { text: 'فضای آموزشی' }), element('strong', { text: workspace ? workspaceName(workspace) : 'انتخاب نشده' })),
        icon('chevron'),
      ),
      nav,
      secondary,
      element('div', { className: 'f3-shell-sidebar__spacer' }),
      element('button', {
        className: `f3-shell-account-rail${route?.id === 'account' ? ' is-active' : ''}`,
        attrs: { type: 'button', 'aria-current': route?.id === 'account' ? 'page' : null },
        on: { click: () => this.router.navigate('account') },
      },
        element('span', { className: 'f3-shell-avatar', text: initials(userName(this.state.account)) }),
        element('span', { className: 'f3-shell-account-rail__copy' }, element('strong', { text: userName(this.state.account) }), element('small', { text: 'حساب و عضویت‌ها' })),
        icon('chevron'),
      ),
    );
  }

  renderTopbar(workspace) {
    return element('header', { className: 'f3-shell-topbar', attrs: { 'aria-label': 'نوار بالای فانوس' } },
      element('button', { className: 'f3-shell-brand f3-shell-brand--compact', attrs: { type: 'button', 'aria-label': 'فانوس؛ خانه' }, on: { click: () => this.router.navigate('home') } },
        element('span', { className: 'f3-shell-brand__mark' }, icon('lantern')),
        element('span', { className: 'f3-shell-brand__copy' }, element('strong', { text: 'فانوس' })),
      ),
      element('button', { className: 'f3-shell-topbar-workspace', attrs: { type: 'button', 'data-f3-workspace-trigger': '', 'aria-haspopup': 'dialog', 'aria-controls': 'f3-shell-workspace-sheet' } },
        icon('workspace'), element('span', {}, element('small', { text: 'فضای آموزشی' }), element('strong', { text: workspace ? workspaceName(workspace) : 'انتخاب فضا' })), icon('chevron'),
      ),
      element('div', { className: 'f3-shell-topbar__spacer' }),
      element('button', { className: 'f3-shell-topbar-account', attrs: { type: 'button', 'aria-label': 'باز کردن حساب' }, on: { click: () => this.router.navigate('account') } },
        element('span', { className: 'f3-shell-avatar', text: initials(userName(this.state.account)) }),
        element('span', { className: 'f3-shell-topbar-account__name', text: userName(this.state.account) }),
      ),
    );
  }

  renderContextBar(route, workspace) {
    const notice = this.state.workspaceError || (this.state.accountRefreshWarning ? 'جزئیات فضا در حال همگام‌سازی است.' : '');
    return element('div', { className: 'f3-shell-context' },
      element('nav', { className: 'f3-shell-breadcrumbs', attrs: { 'aria-label': 'مسیر صفحه' } },
        element('span', { text: workspace ? workspaceName(workspace) : 'فانوس' }), icon('chevron'), element('strong', { text: route?.label || 'خانه', attrs: { 'aria-current': 'page' } }),
      ),
      notice ? element('span', { className: 'f3-shell-context__notice', text: notice }) : null,
    );
  }

  navButton(id, route, options = {}) {
    const item = this.registry.get(id);
    if (!item) return element('span');
    const active = route?.id === id;
    const locked = item.workspaceRequired && !this.state.selectedWorkspaceId;
    return element('button', {
      className: `f3-shell-nav-item${options.secondary ? ' f3-shell-nav-item--secondary' : ''}${active ? ' is-active' : ''}`,
      attrs: { type: 'button', disabled: locked || null, 'aria-current': active ? 'page' : null, 'aria-describedby': locked ? 'f3-shell-workspace-required-hint' : null },
      on: { click: () => this.router.navigate(id) },
    }, icon(item.icon || 'more'), element('span', { text: item.longLabel || item.label }));
  }

  renderMobileNav(route) {
    const nav = element('nav', { className: 'f3-shell-mobile-nav', attrs: { 'aria-label': 'ناوبری اصلی موبایل' } });
    MOBILE_IDS.forEach((id) => {
      const item = this.registry.get(id);
      const active = route?.id === id;
      const locked = item.workspaceRequired && !this.state.selectedWorkspaceId;
      nav.append(element('button', {
        className: `f3-shell-mobile-nav__item${active ? ' is-active' : ''}`,
        attrs: { type: 'button', disabled: locked || null, 'aria-current': active ? 'page' : null }, on: { click: () => this.router.navigate(id) },
      }, icon(item.icon), element('span', { text: item.label })));
    });
    nav.append(element('button', {
      className: `f3-shell-mobile-nav__item${route?.id === 'more' ? ' is-active' : ''}`,
      attrs: { type: 'button', 'data-f3-more-trigger': '', 'aria-haspopup': 'dialog', 'aria-controls': 'f3-shell-more-sheet' },
    }, icon('more'), element('span', { text: 'بیشتر' })));
    return nav;
  }

  renderWorkspaceSheet() {
    const workspaces = this.state.account?.workspaces || [];
    const list = element('div', { className: 'f3-shell-sheet-list' });
    if (!workspaces.length) {
      list.append(statePanel('فضای آموزشی فعالی ثبت نشده است', 'حساب شما فعال است، اما هنوز عضویت فعالی در یک فضای آموزشی ندارد.', { kind: 'info', icon: 'workspace' }));
    } else {
      workspaces.forEach((workspace) => {
        const selected = String(workspace.id) === String(this.state.selectedWorkspaceId || '');
        list.append(element('button', {
          className: `f3-shell-workspace-option${selected ? ' is-selected' : ''}`,
          attrs: { type: 'button', 'data-workspace-id': workspace.id, disabled: selected || this.state.workspaceMutation || null, 'aria-pressed': selected ? 'true' : 'false' },
        },
          element('span', { className: 'f3-shell-workspace-option__icon' }, icon('workspace')),
          element('span', { className: 'f3-shell-workspace-option__copy' }, element('strong', { text: workspaceName(workspace) }), element('small', { text: pathLine(workspace) })),
          selected ? element('span', { className: 'f3-shell-workspace-option__selected' }, icon('check'), element('span', { text: 'فعال' })) : icon('chevron'),
        ));
      });
    }
    return element('section', {
      id: 'f3-shell-workspace-sheet', className: 'f3-shell-sheet',
      attrs: { role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'f3-shell-workspace-title', tabindex: '-1', hidden: true, 'aria-hidden': 'true' },
    },
      element('div', { className: 'f3-shell-sheet__handle', attrs: { 'aria-hidden': 'true' } }),
      element('header', { className: 'f3-shell-sheet__header' },
        element('div', {}, element('p', { className: 'f3-shell-eyebrow', text: 'زمینه فعال' }), element('h2', { id: 'f3-shell-workspace-title', text: 'فضای آموزشی' })),
        element('button', { className: 'f3-shell-icon-button', attrs: { type: 'button', 'data-f3-shell-close': '', 'aria-label': 'بستن انتخاب فضا' } }, icon('close')),
      ),
      element('p', { className: 'f3-shell-sheet__lead', text: 'با تغییر فضا، محتوای صفحه قبلی کنار گذاشته می‌شود و اطلاعات فضای تازه دوباره دریافت خواهد شد.' }),
      list,
      element('div', { className: 'f3-shell-sheet-message', text: this.state.workspaceError, attrs: { 'data-f3-workspace-message': '', role: 'status', 'aria-live': 'polite' } }),
      !workspaces.length ? element('div', { className: 'f3-shell-sheet__footer' }, button('مشاهده حساب', { variant: 'secondary', icon: 'account', onClick: () => this.router.navigate('account') })) : null,
    );
  }

  renderMoreSheet() {
    const groups = element('div', { className: 'f3-shell-more-groups' },
      this.moreGroup('آموزش و پیگیری', ['assessments', 'grades', 'announcements']),
      this.moreGroup('کارها و دسترسی', ['forms', 'orders']),
      this.moreGroup('حساب', ['account']),
    );
    if (this.state.managementAvailable) groups.append(this.moreGroup('ابزارهای مجاز', ['management']));
    return element('section', {
      id: 'f3-shell-more-sheet', className: 'f3-shell-sheet f3-shell-sheet--more',
      attrs: { role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'f3-shell-more-title', tabindex: '-1', hidden: true, 'aria-hidden': 'true' },
    },
      element('div', { className: 'f3-shell-sheet__handle', attrs: { 'aria-hidden': 'true' } }),
      element('header', { className: 'f3-shell-sheet__header' },
        element('div', {}, element('p', { className: 'f3-shell-eyebrow', text: 'فانوس' }), element('h2', { id: 'f3-shell-more-title', text: 'بیشتر' })),
        element('button', { className: 'f3-shell-icon-button', attrs: { type: 'button', 'data-f3-shell-close': '', 'aria-label': 'بستن منو' } }, icon('close')),
      ),
      element('button', { className: 'f3-shell-more-workspace', attrs: { type: 'button', 'data-f3-workspace-trigger': '' } },
        element('span', {}, icon('workspace')),
        element('span', {}, element('small', { text: 'فضای آموزشی' }), element('strong', { text: this.currentWorkspace() ? workspaceName(this.currentWorkspace()) : 'انتخاب فضا' })),
        icon('chevron'),
      ),
      groups,
      element('div', { className: 'f3-shell-help-row' }, icon('help'), element('div', {}, element('strong', { text: 'راهنمای شروع' }), element('span', { text: 'از حساب، فضای آموزشی خود را بررسی کن؛ سپس درس‌ها، برنامه و منابع بر اساس همان فضا نمایش داده می‌شوند.' }))),
    );
  }

  moreGroup(title, ids) {
    const group = element('section', { className: 'f3-shell-more-group' }, element('h3', { text: title }));
    ids.forEach((id) => {
      const item = this.registry.get(id);
      if (!item) return;
      const locked = item.workspaceRequired && !this.state.selectedWorkspaceId;
      group.append(element('button', {
        className: 'f3-shell-more-link', attrs: { type: 'button', disabled: locked || null }, on: { click: () => this.router.navigate(id) },
      }, icon(item.icon || 'more'), element('span', { text: item.longLabel || item.label }), icon('chevron')));
    });
    return group;
  }

  renderRouteContent(route, outlet) {
    outlet.append(element('span', { id: 'f3-shell-workspace-required-hint', className: 'f3-shell-sr-only', text: 'برای باز کردن این بخش ابتدا فضای آموزشی را انتخاب کنید.' }));
    if (route?.id === 'account') return outlet.append(this.renderAccountDestination());
    if (route?.id === 'more') return outlet.append(this.renderMoreDestination());
    if (!this.state.selectedWorkspaceId) return outlet.append(this.renderWorkspaceGate());
    this.mountExternalRoute(route, outlet);
  }

  mountExternalRoute(route, outlet) {
    let claimed = false;
    const claim = () => {
      if (claimed) return outlet;
      claimed = true;
      outlet.replaceChildren();
      outlet.setAttribute('aria-busy', 'true');
      return outlet;
    };
    const detail = { route, outlet, workspaceId: this.state.selectedWorkspaceId, workspaceEpoch: this.state.workspaceEpoch, claim };
    dispatchShellEvent(this.root, 'fanoos:v3:route-change', detail);
    if (!claimed && typeof this.ctx.ui?.mountRoute === 'function') {
      const result = this.ctx.ui.mountRoute(detail);
      claimed = claimed || result === true;
    }
    if (!claimed) {
      outlet.replaceChildren(statePanel('این بخش فعلاً در دسترس نیست', 'می‌توانید از ناوبری فانوس به بخش دیگری بروید یا دوباره این صفحه را باز کنید.', {
        kind: 'info', icon: 'info', action: button('باز کردن خانه', { variant: 'secondary', onClick: () => this.router.navigate('home') }),
      }));
      outlet.setAttribute('aria-busy', 'false');
    }
  }

  renderWorkspaceGate() {
    const workspaces = this.state.account?.workspaces || [];
    return workspaces.length ? this.renderWorkspaceSelectionDestination(workspaces) : this.renderZeroWorkspaceDestination();
  }

  renderWorkspaceSelectionDestination(workspaces) {
    const list = element('div', { className: 'f3-shell-workspace-choice-list' });
    workspaces.forEach((workspace) => list.append(element('button', {
      className: 'f3-shell-workspace-choice', attrs: { type: 'button' }, on: { click: () => this.switchWorkspace(workspace.id) },
    },
      element('span', { className: 'f3-shell-workspace-choice__icon' }, icon('workspace')),
      element('span', { className: 'f3-shell-workspace-choice__copy' }, element('strong', { text: workspaceName(workspace) }), element('small', { text: pathLine(workspace) })),
      element('span', { className: 'f3-shell-workspace-choice__action', text: 'انتخاب' }),
    )));
    return element('section', { className: 'f3-shell-workspace-gate', attrs: { 'aria-labelledby': 'f3-shell-select-workspace-title' } },
      element('div', { className: 'f3-shell-page-heading' },
        element('p', { className: 'f3-shell-eyebrow', text: 'شروع کار' }),
        element('h1', { id: 'f3-shell-select-workspace-title', text: 'فضای آموزشی‌ات را انتخاب کن' }),
        element('p', { text: 'این حساب عضویت فعال دارد، اما هنوز فضای فعال این نشست روی سرور انتخاب نشده است.' }),
      ),
      list,
      element('p', { className: 'f3-shell-support-copy', text: 'پس از انتخاب، درس‌ها و اطلاعات همان فضا از منبع اصلی دوباره دریافت می‌شوند.' }),
    );
  }

  renderZeroWorkspaceDestination() {
    return element('section', { className: 'f3-shell-zero-workspace', attrs: { 'aria-labelledby': 'f3-shell-zero-workspace-title' } },
      element('div', { className: 'f3-shell-page-heading f3-shell-page-heading--wide' },
        element('span', { className: 'f3-shell-page-heading__icon' }, icon('workspace')),
        element('p', { className: 'f3-shell-eyebrow', text: 'حساب آماده است' }),
        element('h1', { id: 'f3-shell-zero-workspace-title', text: 'هنوز فضای آموزشی فعالی برای این حساب نیست' }),
        element('p', { text: 'فانوس حساب شما را می‌شناسد، اما برای نمایش درس‌ها و داده‌های آموزشی به یک عضویت فعال نیاز دارد.' }),
        element('div', { className: 'f3-shell-inline-actions' },
          button('مشاهده حساب', { variant: 'secondary', icon: 'account', onClick: () => this.router.navigate('account') }),
          button('تازه‌سازی حساب', { variant: 'quiet', icon: 'retry', onClick: () => this.refreshAccountFromServer() }),
        ),
      ),
      element('div', { className: 'f3-shell-zero-grid' },
        element('section', { className: 'f3-shell-zero-section' }, element('span', { className: 'f3-shell-zero-section__icon' }, icon('account')), element('h2', { text: 'حساب شما' }), element('strong', { text: userName(this.state.account) }), element('p', { text: 'ورود انجام شده و ساختار حساب همچنان در دسترس است.' })),
        element('section', { className: 'f3-shell-zero-section' }, element('span', { className: 'f3-shell-zero-section__icon' }, icon('courses')), element('h2', { text: 'بعد از فعال شدن فضا' }), element('p', { text: 'درس‌ها، برنامه، منابع، آزمون‌ها، نمرات، اطلاعیه‌ها و فرم‌ها از همان فضای آموزشی نمایش داده می‌شوند.' })),
        element('section', { className: 'f3-shell-zero-section' }, element('span', { className: 'f3-shell-zero-section__icon' }, icon('help')), element('h2', { text: 'راهنمای شروع' }), element('p', { text: 'اگر باید عضو یک فضای آموزشی باشید، از مسئول آن فضا بخواهید وضعیت عضویت حساب را بررسی کند؛ سپس حساب را تازه‌سازی کنید.' })),
      ),
    );
  }

  renderAccountDestination() {
    const account = this.state.account;
    const workspaces = account?.workspaces || [];
    const current = this.currentWorkspace();
    const membershipList = element('div', { className: 'f3-shell-membership-list' });
    if (!workspaces.length) {
      membershipList.append(element('div', { className: 'f3-shell-membership-empty' }, icon('workspace'), element('p', { text: 'هنوز عضویت فعال در فضای آموزشی ثبت نشده است.' })));
    } else {
      workspaces.forEach((workspace) => {
        const active = String(workspace.id) === String(this.state.selectedWorkspaceId || '');
        membershipList.append(element('div', { className: `f3-shell-membership-row${active ? ' is-active' : ''}` },
          element('span', { className: 'f3-shell-membership-row__icon' }, icon('workspace')),
          element('div', { className: 'f3-shell-membership-row__copy' }, element('strong', { text: workspaceName(workspace) }), element('span', { text: pathLine(workspace) })),
          active ? statusBadge('فضای فعال', 'success') : button('انتخاب', { variant: 'quiet', onClick: () => this.switchWorkspace(workspace.id) }),
        ));
      });
    }
    return element('section', { className: 'f3-shell-account-page', attrs: { 'aria-labelledby': 'f3-shell-account-title' } },
      element('header', { className: 'f3-shell-page-heading' },
        element('p', { className: 'f3-shell-eyebrow', text: 'حساب' }),
        element('h1', { id: 'f3-shell-account-title', text: 'حساب و فضاهای آموزشی' }),
        element('p', { text: 'اطلاعات اصلی حساب و عضویت‌هایی که اکنون برای شما فعال هستند.' }),
      ),
      element('div', { className: 'f3-shell-account-summary' },
        element('span', { className: 'f3-shell-account-summary__avatar', text: initials(userName(account)) }),
        element('div', { className: 'f3-shell-account-summary__copy' },
          element('strong', { text: userName(account) }),
          account?.user?.status === 'active' ? statusBadge('حساب فعال', 'success') : null,
          element('span', { text: `${formatCount(workspaces.length)} فضای آموزشی فعال` }),
        ),
        button('خروج از حساب', { variant: 'danger-quiet', icon: 'logout', onClick: (event) => this.handleLogout(event.currentTarget) }),
      ),
      element('section', { className: 'f3-shell-account-section' },
        element('div', { className: 'f3-shell-section-heading' },
          element('div', {}, element('h2', { text: 'عضویت‌ها' }), element('p', { text: 'انتخاب فضا فقط زمینه این نشست را تغییر می‌دهد؛ دسترسی هر بخش دوباره روی سرور بررسی می‌شود.' })),
          current ? statusBadge(workspaceName(current), 'primary') : null,
        ),
        membershipList,
      ),
      element('section', { className: 'f3-shell-account-section f3-shell-account-section--quiet' },
        element('span', { className: 'f3-shell-account-section__icon' }, icon('info')),
        element('div', {}, element('h2', { text: 'درباره اطلاعات حساب' }), element('p', { text: 'فانوس فقط اطلاعاتی را اینجا نشان می‌دهد که در نمای عمومی حساب وجود دارد. شناسه‌های فنی و اطلاعات داخلی نمایش داده نمی‌شوند.' })),
      ),
    );
  }

  renderMoreDestination() {
    const rows = element('div', { className: 'f3-shell-more-page__groups' },
      this.moreGroup('آموزش و پیگیری', ['assessments', 'grades', 'announcements']),
      this.moreGroup('کارها و دسترسی', ['forms', 'orders']),
      this.moreGroup('حساب', ['account']),
    );
    if (this.state.managementAvailable) rows.append(this.moreGroup('ابزارهای مجاز', ['management']));
    return element('section', { className: 'f3-shell-more-page', attrs: { 'aria-labelledby': 'f3-shell-more-page-title' } },
      element('header', { className: 'f3-shell-page-heading' },
        element('p', { className: 'f3-shell-eyebrow', text: 'فانوس' }),
        element('h1', { id: 'f3-shell-more-page-title', text: 'بیشتر' }),
        element('p', { text: 'بخش‌های تکمیلی، حساب و دسترسی‌های مجاز از اینجا در دسترس‌اند.' }),
      ),
      element('button', { className: 'f3-shell-more-page__workspace', attrs: { type: 'button', 'data-f3-workspace-trigger': '' } },
        element('span', { className: 'f3-shell-more-page__workspace-icon' }, icon('workspace')),
        element('span', {}, element('small', { text: 'فضای آموزشی' }), element('strong', { text: this.currentWorkspace() ? workspaceName(this.currentWorkspace()) : 'فضایی انتخاب نشده' })),
        icon('chevron'),
      ),
      rows,
      element('section', { className: 'f3-shell-account-section f3-shell-account-section--quiet' },
        element('span', { className: 'f3-shell-account-section__icon' }, icon('help')),
        element('div', {}, element('h2', { text: 'راهنمای شروع' }), element('p', { text: 'فضای آموزشی، زمینه اصلی فانوس است. اگر داده‌ای در یک بخش نمی‌بینید، ابتدا فضای فعال را بررسی کنید.' })),
      ),
    );
  }

  async refreshAccountFromServer() {
    this.announce('در حال تازه‌سازی حساب…');
    try {
      const account = normalizeAccount(await this.api.account());
      this.acceptAccount(account);
      await this.refreshManagement({ quiet: true });
      this.state.accountRefreshWarning = false;
      this.state.workspaceError = '';
      this.renderApp();
      this.announce('اطلاعات حساب تازه شد.');
    } catch (error) {
      if (failureStatus(error) === 401) return this.handleSessionExpired();
      this.announce(shellErrorMessage(error));
    }
  }

  setWorkspaceControlsBusy(busy, message = '') {
    const shell = this.refs.shell;
    if (!shell) return;
    shell.querySelectorAll('[data-workspace-id]').forEach((node) => { node.disabled = busy || node.getAttribute('aria-pressed') === 'true'; });
    const messageNode = shell.querySelector('[data-f3-workspace-message]');
    if (messageNode) messageNode.textContent = message;
  }

  replaceOutletForWorkspaceSwitch() {
    if (!this.refs.outlet) return;
    this.refs.outlet.replaceChildren(statePanel('در حال تغییر فضای آموزشی', 'اطلاعات فضای قبلی کنار گذاشته شد. پس از تأیید سرور، محتوای فضای تازه نمایش داده می‌شود.', { kind: 'info', icon: 'switch', live: true }));
    this.refs.outlet.setAttribute('aria-busy', 'true');
  }

  async switchWorkspace(workspaceId, sheetController = null) {
    const requested = String(workspaceId || '');
    if (!requested || this.state.workspaceMutation || requested === String(this.state.selectedWorkspaceId || '')) return;
    if (!this.state.account?.workspaces?.some((workspace) => String(workspace.id) === requested)) return;
    const previousWorkspaceId = this.state.selectedWorkspaceId;
    const epoch = ++this.state.workspaceEpoch;
    this.state.workspaceMutation = true;
    this.state.workspaceError = '';
    this.state.accountRefreshWarning = false;
    this.setWorkspaceControlsBusy(true, 'در حال تغییر فضای آموزشی…');
    this.replaceOutletForWorkspaceSwitch();
    dispatchShellEvent(this.root, 'fanoos:v3:workspace-switch', { phase: 'start', workspaceId: requested, previousWorkspaceId, epoch });

    try {
      await this.api.selectWorkspace(requested);
      if (this.destroyed || epoch !== this.state.workspaceEpoch) return;
      this.state.selectedWorkspaceId = requested;
      this.state.account = { ...this.state.account, selected_workspace_id: requested };

      try {
        const freshAccount = normalizeAccount(await this.api.account());
        if (this.destroyed || epoch !== this.state.workspaceEpoch) return;
        this.acceptAccount(freshAccount);
      } catch (refreshError) {
        if (failureStatus(refreshError) === 401) return this.handleSessionExpired();
        this.state.accountRefreshWarning = true;
      }

      await this.refreshManagement({ quiet: true });
      if (this.destroyed || epoch !== this.state.workspaceEpoch) return;
      this.state.workspaceMutation = false;
      dispatchShellEvent(this.root, 'fanoos:v3:workspace-switch', {
        phase: 'commit', workspaceId: this.state.selectedWorkspaceId, requestedWorkspaceId: requested, previousWorkspaceId, epoch,
      });
      sheetController?.close({ restoreFocus: false });
      this.renderApp();
      this.announce('فضای آموزشی فعال تغییر کرد.');
    } catch (error) {
      if (this.destroyed || error?.name === 'AbortError') return;
      if (failureStatus(error) === 401) return this.handleSessionExpired();
      this.state.workspaceMutation = false;
      this.state.selectedWorkspaceId = previousWorkspaceId;
      if (this.state.account) this.state.account = { ...this.state.account, selected_workspace_id: previousWorkspaceId };
      this.state.workspaceError = shellErrorMessage(error, 'workspace');
      dispatchShellEvent(this.root, 'fanoos:v3:workspace-switch', {
        phase: 'error', workspaceId: requested, previousWorkspaceId, epoch, error: this.api.failure(error),
      });
      this.renderApp();
      this.announce(this.state.workspaceError);
    }
  }

  async handleLogout(buttonNode) {
    if (!buttonNode || buttonNode.disabled) return;
    buttonNode.disabled = true;
    this.announce('در حال خروج…');
    try {
      const result = await this.api.logout();
      if (!result.confirmed || this.destroyed) return;
      this.state.account = null;
      this.state.selectedWorkspaceId = null;
      this.state.managementAvailable = false;
      this.state.auth = 'signed-out';
      this.router.stop();
      dispatchShellEvent(this.root, 'fanoos:v3:session', { phase: result.alreadyExpired ? 'expired' : 'signed-out' });
      this.renderAuth(result.alreadyExpired ? 'نشست شما پایان یافته بود. دوباره وارد شوید.' : 'از حساب خارج شدید.');
    } catch (error) {
      buttonNode.disabled = false;
      this.announce(shellErrorMessage(error, 'logout'));
    }
  }

  handleSessionExpired() {
    if (this.destroyed) return;
    this.api.clearSessionBridge();
    this.state.account = null;
    this.state.selectedWorkspaceId = null;
    this.state.managementAvailable = false;
    this.state.auth = 'signed-out';
    ++this.state.workspaceEpoch;
    this.router.stop();
    dispatchShellEvent(this.root, 'fanoos:v3:session', { phase: 'expired' });
    this.renderAuth('نشست شما پایان یافته است. دوباره وارد شوید.');
  }

  announce(message) {
    const value = cleanText(message);
    if (!value) return;
    const live = this.refs.live || this.root.querySelector?.('#f3-shell-live');
    if (!live) return;
    live.textContent = '';
    requestAnimationFrame(() => { live.textContent = value; });
  }

  unmount() {
    if (this.destroyed) return;
    this.destroyed = true;
    this.accountAbort?.abort();
    this.managementAbort?.abort();
    this.router.stop();
    this.destroySheets();
    if (this.ctx.signal) this.ctx.signal.removeEventListener('abort', this.boundSignalAbort);
    this.root.classList.remove('f3-shell-host');
    this.root.replaceChildren();
  }
}

let activeController = null;

export const moduleDefinition = Object.freeze({
  id: 'web-02-shell',
  routes: SHELL_ROUTES,
  navItems: NAV_ITEMS,
  mount(ctx) {
    activeController?.unmount();
    activeController = new ShellController(ctx).mount();
    return activeController;
  },
  unmount() {
    activeController?.unmount();
    activeController = null;
  },
});

export { NAV_ITEMS, SHELL_ROUTES, ShellController };
export default moduleDefinition;
