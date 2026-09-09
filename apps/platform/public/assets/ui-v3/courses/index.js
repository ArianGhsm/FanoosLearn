import { buildCourseCatalog, findCourseByCode } from './course-model.js';
import {
  renderCourseDetail,
  renderCourseMissing,
  renderCoursesFailure,
  renderCoursesIndex,
  renderIntegrationFailure,
  renderLoading,
} from './course-view.js';

const activeMount = new WeakMap();

function workspaceId(ctx) {
  return ctx.state?.workspace?.id
    || ctx.state?.activeWorkspace?.id
    || ctx.state?.workspaceId
    || '';
}

function routeState(ctx) {
  const route = ctx.state?.route || {};
  const params = route.params || {};
  const query = route.query || {};
  const rawCode = params.courseCode || params.course || query.course || '';
  let courseCode = String(rawCode || '');
  try { courseCode = decodeURIComponent(courseCode); } catch (_error) {}
  return {
    courseCode,
    tab: String(query.tab || params.tab || route.subview || 'overview'),
    term: String(query.term || ''),
    q: String(query.q || ''),
  };
}

function asError(error) {
  return {
    status: Number(error?.status || error?.response?.status || 0),
    code: String(error?.code || error?.response?.code || 'request_failed').slice(0, 80),
  };
}

function unwrap(result) {
  if (result && typeof result === 'object' && typeof result.ok === 'boolean') {
    if (!result.ok) {
      const failure = new Error('api');
      failure.status = Number(result.status || result.error?.status || 0);
      failure.code = String(result.error?.code || 'request_failed').slice(0, 80);
      throw failure;
    }
    return result.data;
  }
  return result;
}

async function apiGet(ctx, path) {
  if (typeof ctx.api?.get === 'function') return unwrap(await ctx.api.get(path, { signal: ctx.signal }));
  if (typeof ctx.api === 'function') return unwrap(await ctx.api(path, { method: 'GET', signal: ctx.signal }));
  throw Object.assign(new Error('api_adapter_missing'), { code: 'api_adapter_missing' });
}

function query(values) {
  const params = new URLSearchParams();
  Object.entries(values).forEach(([key, value]) => {
    if (value != null && String(value).trim() !== '') params.set(key, String(value));
  });
  return params.toString();
}

function rows(value) {
  return Array.isArray(value) ? value : [];
}

async function safePart(ctx, path) {
  try {
    const data = await apiGet(ctx, path);
    return { state: 'ready', data: rows(data) };
  } catch (error) {
    const normalized = asError(error);
    if (normalized.status === 403) return { state: 'denied', data: [], error: normalized };
    if (normalized.status === 401) return { state: 'error', data: [], error: normalized, sessionExpired: true };
    return { state: 'error', data: [], error: normalized };
  }
}

async function loadOverview(ctx, workspace, course) {
  const courseId = course.id || '';
  const [resources, assessments, grades] = await Promise.all([
    courseId
      ? safePart(ctx, `/api/v1/workspaces/${encodeURIComponent(workspace)}/resources?${query({ course_id: courseId, sort: 'newest' })}`)
      : Promise.resolve({ state: 'error', data: [], error: { code: 'course_identity_missing', status: 0 } }),
    courseId
      ? safePart(ctx, `/api/v1/workspaces/${encodeURIComponent(workspace)}/assessments?${query({ course_id: courseId })}`)
      : Promise.resolve({ state: 'error', data: [], error: { code: 'course_identity_missing', status: 0 } }),
    safePart(ctx, `/api/v1/workspaces/${encodeURIComponent(workspace)}/grades/me`),
  ]);
  return { resources, assessments, grades };
}

function mountIsCurrent(root, marker, ctx) {
  return activeMount.get(root) === marker && !ctx.signal?.aborted;
}

export async function mountCourses(ctx) {
  const root = ctx.root;
  if (!(root instanceof Element)) return;
  const marker = Symbol('f3-courses-mount');
  activeMount.set(root, marker);

  const workspace = workspaceId(ctx);
  if (!workspace) {
    renderIntegrationFailure(root, 'فضای آموزشی فعال از shell V3 به ماژول درس‌ها نرسیده است.');
    return;
  }
  if (!(typeof ctx.api?.get === 'function' || typeof ctx.api === 'function')) {
    renderIntegrationFailure(root, 'API adapter مشترک V3 برای این ماژول در دسترس نیست.');
    return;
  }

  const route = routeState(ctx);
  renderLoading(root, Boolean(route.courseCode));

  let academics;
  try {
    academics = await apiGet(ctx, `/api/v1/workspaces/${encodeURIComponent(workspace)}/academics`);
  } catch (error) {
    if (!mountIsCurrent(root, marker, ctx)) return;
    renderCoursesFailure(root, ctx, asError(error), () => mountCourses(ctx));
    return;
  }
  if (!mountIsCurrent(root, marker, ctx)) return;

  const catalog = buildCourseCatalog(academics || {});
  if (!route.courseCode) {
    renderCoursesIndex(root, ctx, catalog, route);
    return;
  }

  const course = findCourseByCode(catalog.courses, route.courseCode);
  if (!course) {
    renderCourseMissing(root, ctx);
    return;
  }

  if (route.tab && route.tab !== 'overview') {
    renderCourseDetail(root, ctx, course, null, route);
    return;
  }

  renderCourseDetail(root, ctx, course, null, route);
  const summary = await loadOverview(ctx, workspace, course);
  if (!mountIsCurrent(root, marker, ctx)) return;
  renderCourseDetail(root, ctx, course, summary, route);
}

export function unmountCourses(ctx) {
  const root = ctx.root;
  if (!(root instanceof Element)) return;
  activeMount.delete(root);
  root.replaceChildren();
}

export const courseRouteContract = Object.freeze({
  index: '#/courses',
  detail: '#/courses/:courseCode',
  tabs: ['overview', 'sessions', 'schedule', 'resources', 'assessments', 'grades', 'announcements'],
  listQuery: ['term', 'q'],
  detailQuery: ['tab'],
});

export const moduleDefinition = Object.freeze({
  id: 'courses',
  routes: [
    { id: 'courses.index', pattern: '/courses' },
    { id: 'courses.detail', pattern: '/courses/:courseCode' },
  ],
  navItems: [
    { id: 'courses', label: 'درس‌ها', href: '#/courses', icon: 'courses', order: 20 },
  ],
  stylesheets: ['/assets/ui-v3/courses/courses.css'],
  mount: mountCourses,
  unmount: unmountCourses,
});

export default moduleDefinition;
