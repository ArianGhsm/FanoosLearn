import { safeText } from './learning-contract.js';

export const MODULE_ID = 'learning';
export const API_ROOT = '/api/v1/workspaces';

export function node(tag, className, text) {
  const el = document.createElement(tag);
  if (className) el.className = className;
  if (text != null) el.textContent = text;
  return el;
}

export function button(label, className = 'f3-learning-button', type = 'button') {
  const el = node('button', className, label);
  el.type = type;
  return el;
}

export function formatNumber(ctx, value) {
  if (ctx?.format?.number) return ctx.format.number(value);
  return new Intl.NumberFormat('fa-IR').format(value);
}

export function formatDate(ctx, value) {
  if (!value) return '';
  if (ctx?.format?.date) return ctx.format.date(value);
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? '' : new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium' }).format(parsed);
}

export function workspaceId(ctx) {
  return safeText(ctx?.state?.workspace?.id ?? ctx?.state?.workspaceId ?? ctx?.state?.activeWorkspaceId, 80);
}

export async function apiJson(ctx, path, options = {}) {
  if (typeof ctx?.api === 'function') return ctx.api(path, options);
  if (typeof ctx?.api?.request === 'function') return ctx.api.request(path, options);
  if ((options.method ?? 'GET') === 'GET' && typeof ctx?.api?.get === 'function') return ctx.api.get(path, options);
  if (typeof ctx?.api?.post === 'function' && (options.method ?? 'GET') === 'POST') return ctx.api.post(path, options.body ?? {}, options);
  const error = new Error('Canonical API adapter is unavailable.');
  error.code = 'integration_api_adapter_missing';
  throw error;
}

export async function apiBinary(ctx, path, body) {
  if (typeof ctx?.api?.binary === 'function') return ctx.api.binary(path, { method: 'POST', body });
  if (typeof ctx?.api?.requestBinary === 'function') return ctx.api.requestBinary(path, { method: 'POST', body });
  const error = new Error('Binary API adapter is unavailable.');
  error.code = 'integration_binary_adapter_missing';
  throw error;
}

export function unwrap(payload) {
  if (payload && typeof payload === 'object' && 'data' in payload && Object.keys(payload).length <= 5) return payload.data;
  return payload;
}

export function createState(ctx, options = {}) {
  const embeddedCourseId = safeText(options.courseId, 80);
  return {
    ctx,
    embedded: Boolean(embeddedCourseId),
    embeddedCourseId,
    embeddedCourseLabel: safeText(options.courseLabel, 180),
    resources: [],
    courses: [],
    filters: { q: '', type: '', courseId: embeddedCourseId },
    loading: true,
    error: null,
    partialCourseError: false,
    selected: null,
    detail: null,
    detailStatus: 'idle',
    detailError: null,
    deliveryState: 'available',
    deliveryBusy: false,
    abort: new AbortController(),
    sheetOpen: false,
    lastFocus: null,
  };
}

export function buildQuery(state) {
  const params = new URLSearchParams();
  const q = safeText(state.filters.q, 120);
  if (q.length >= 2) params.set('q', q);
  if (state.filters.type) params.set('type', state.filters.type);
  if (state.filters.courseId) params.set('course_id', state.filters.courseId);
  params.set('sort', 'newest');
  return params.toString();
}
