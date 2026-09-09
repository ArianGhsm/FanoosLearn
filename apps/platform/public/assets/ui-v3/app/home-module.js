import '../home/home.js';
import { addDays, formatDateKey, workspaceTodayKey } from '../schedule/schedule-model.js';

function rows(value) {
  if (Array.isArray(value)) return value;
  if (Array.isArray(value?.items)) return value.items;
  return [];
}

function workspace(ctx) {
  return ctx?.state?.workspace || ctx?.state?.activeWorkspace || null;
}

async function safeRead(ctx, path) {
  try {
    const data = await ctx.api(path, { method: 'GET', signal: ctx.signal });
    return { ok: true, data: rows(data) };
  } catch (error) {
    if (error?.name === 'AbortError') throw error;
    return { ok: false, error: { status: Number(error?.status || 0), code: String(error?.code || 'request_failed').slice(0, 80) } };
  }
}

function modelContext(ctx) {
  const active = workspace(ctx) || {};
  const timezoneName = String(active.timezone_name || active.timezoneName || '').trim();
  const todayKey = timezoneName ? workspaceTodayKey(timezoneName) : '';
  return {
    workspaceId: active.id || ctx.state?.workspaceId || '',
    workspaceName: active.name || active.label || 'فضای آموزشی',
    workspace: active,
    timezoneName,
    todayKey,
    dateLabel: todayKey ? formatDateKey(todayKey, { year: true }) : '',
    nowEpochMs: Date.now(),
  };
}

function render(ctx, payload) {
  const renderer = window.FanoosV3?.home?.render;
  if (typeof renderer !== 'function') throw new Error('home_renderer_unavailable');
  return renderer(ctx.root, payload);
}

async function mountHome(ctx) {
  const context = modelContext(ctx);
  render(ctx, {
    phase: 'loading',
    context,
    parts: {},
    handlers: { navigate: ctx.navigate },
    formatters: ctx.format,
  });
  const workspaceId = String(context.workspaceId || '');
  if (!workspaceId) {
    render(ctx, { phase: 'ready', context, parts: {}, handlers: { navigate: ctx.navigate }, formatters: ctx.format });
    return;
  }
  const base = `/api/v1/workspaces/${encodeURIComponent(workspaceId)}`;
  const today = context.todayKey;
  const schedulePath = today
    ? `${base}/schedule?${new URLSearchParams({ from: today, to: addDays(today, 30) })}`
    : `${base}/schedule`;
  const [schedule, assessments, announcements, resources, grades] = await Promise.all([
    safeRead(ctx, schedulePath),
    safeRead(ctx, `${base}/assessments`),
    safeRead(ctx, `${base}/announcements`),
    safeRead(ctx, `${base}/resources?sort=newest`),
    safeRead(ctx, `${base}/grades/me`),
  ]);
  if (ctx.signal?.aborted) return;
  const rerun = () => {
    if (!ctx.signal?.aborted) mountHome(ctx);
  };
  render(ctx, {
    phase: 'ready',
    context,
    parts: { schedule, assessments, announcements, resources, grades },
    handlers: {
      navigate: ctx.navigate,
      retryHome: rerun,
      openWorkspacePicker: () => ctx.navigate('/account'),
      reauthenticate: () => ctx.navigate('/account'),
    },
    formatters: ctx.format,
  });
}

export const moduleDefinition = Object.freeze({
  id: 'home',
  routes: Object.freeze([{ id: 'home', path: '/home' }]),
  navItems: Object.freeze([{ id: 'home', label: 'خانه', route: '/home' }]),
  mount: mountHome,
  unmount(ctx) {
    if (ctx?.root instanceof Element) ctx.root.replaceChildren();
  },
});

export default moduleDefinition;
