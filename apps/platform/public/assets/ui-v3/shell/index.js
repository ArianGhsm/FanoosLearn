import { NAV_ITEMS, SHELL_ROUTES, ShellController } from './module.js';
import { dispatchShellEvent } from './ui.js';

function statusOf(error) { return Number(error?.status) || 0; }

class FanoosShellController extends ShellController {
  async bootstrap() {
    this.accountAbort?.abort();
    this.accountAbort = new AbortController();
    const hadSessionBridge = this.api.hasSessionBridge;
    try {
      const account = await this.api.account({ signal: this.accountAbort.signal });
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
      if (statusOf(error) === 401) {
        this.api.clearSessionBridge();
        this.state.auth = 'signed-out';
        this.renderAuth(hadSessionBridge ? 'نشست قبلی شما پایان یافته است. دوباره وارد شوید.' : '');
        if (hadSessionBridge) dispatchShellEvent(this.root, 'fanoos:v3:session', { phase: 'expired' });
        return;
      }
      this.state.auth = 'unknown';
      this.renderSessionCheckError(error);
    }
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
      if (statusOf(error) === 401) throw error;
      if (!quiet) this.announce('بررسی دسترسی مدیریتی کامل نشد. سایر بخش‌های فانوس همچنان قابل استفاده‌اند.');
    }
  }

  handleSessionExpired() {
    this.state.workspaceMutation = false;
    this.state.workspaceError = '';
    this.state.accountRefreshWarning = false;
    return super.handleSessionExpired();
  }
}

let activeController = null;

export const moduleDefinition = Object.freeze({
  id: 'web-02-shell',
  routes: SHELL_ROUTES,
  navItems: NAV_ITEMS,
  mount(ctx) {
    activeController?.unmount();
    activeController = new FanoosShellController(ctx).mount();
    return activeController;
  },
  unmount() {
    activeController?.unmount();
    activeController = null;
  },
});

export { FanoosShellController };
export default moduleDefinition;
