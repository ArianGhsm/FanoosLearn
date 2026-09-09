import { normalizeCourses, normalizeResource, normalizeRows } from './learning-contract.js';
import { openResourceDetail } from './detail-view.js';
import { renderLibrary } from './library-view.js';
import { API_ROOT, buildQuery, createState, apiJson, unwrap, workspaceId } from './runtime.js';

async function loadCourses(state) {
  const ws = workspaceId(state.ctx);
  if (!ws || state.embedded) return;
  try {
    const payload = unwrap(await apiJson(state.ctx, `${API_ROOT}/${encodeURIComponent(ws)}/academics`, { signal: state.abort.signal }));
    state.courses = normalizeCourses(payload);
    state.partialCourseError = false;
  } catch (_error) {
    state.courses = [];
    state.partialCourseError = true;
  }
}

async function loadResources(state) {
  const ws = workspaceId(state.ctx);
  if (!ws) throw Object.assign(new Error('Workspace unavailable.'), { code: 'workspace_unavailable' });
  const payload = unwrap(await apiJson(state.ctx, `${API_ROOT}/${encodeURIComponent(ws)}/resources?${buildQuery(state)}`, { signal: state.abort.signal }));
  const courseMap = new Map(state.courses.map((course) => [course.id, course]));
  state.resources = normalizeRows(payload).map((row) => normalizeResource(row, courseMap)).filter((row) => row.id);
}

function mountLearning(ctx, options = {}) {
  const host = ctx?.root;
  if (!(host instanceof Element)) throw new Error('Learning module requires ctx.root.');
  const state = createState(ctx, options);
  let refresh;
  const renderCurrentLibrary = () => renderLibrary(state, host, refresh, (resource, trigger) => openResourceDetail(state, host, resource, trigger, renderCurrentLibrary));
  refresh = async ({ focusSearch = false, reloadCourses = false } = {}) => {
    state.loading = true;
    state.error = null;
    renderCurrentLibrary();
    if (!state.courses.length || reloadCourses) await loadCourses(state);
    try {
      await loadResources(state);
    } catch (error) {
      if (error?.name === 'AbortError') return;
      state.error = error;
      state.resources = [];
    } finally {
      state.loading = false;
      renderCurrentLibrary();
      if (focusSearch) host.querySelector('#f3-learning-search-input')?.focus();
    }
  };
  refresh();
  return {
    unmount() {
      state.abort.abort();
      document.querySelector('.f3-learning-sheet-backdrop')?.remove();
      host.replaceChildren();
    },
    refresh,
    state,
  };
}

let activeInstance = null;

export function createCourseLearningEmbed(ctx, options) {
  return mountLearning(ctx, { courseId: options?.courseId, courseLabel: options?.courseLabel });
}

export const moduleDefinition = Object.freeze({
  id: 'learning',
  routes: Object.freeze([
    { id: 'learning-library', path: '/learning' },
    { id: 'learning-resources', path: '/resources' },
  ]),
  navItems: Object.freeze([
    { id: 'learning-resources', label: 'منابع', route: '/learning', icon: 'book-open', priority: 'primary' },
  ]),
  mount(ctx) {
    activeInstance?.unmount?.();
    activeInstance = mountLearning(ctx);
    return activeInstance;
  },
  unmount() {
    activeInstance?.unmount?.();
    activeInstance = null;
  },
});

export default moduleDefinition;
