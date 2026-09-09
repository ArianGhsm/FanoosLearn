import {
  DELIVERY_PRESENTATION,
  catalogAccessPresentation,
  deliveryStateFromError,
  normalizeResource,
  safeText,
} from './learning-contract.js';
import { API_ROOT, apiBinary, apiJson, button, formatDate, formatNumber, node, unwrap, workspaceId } from './runtime.js';
import { statePanel } from './library-view.js';

function metadataItem(term, value) {
  const wrapper = node('div', 'f3-learning-detail__meta-item');
  wrapper.append(node('dt', 'f3-learning-detail__term', term), node('dd', 'f3-learning-detail__value', value));
  return wrapper;
}

function deliveryPanel(state, resource, onDeliver) {
  const presentation = DELIVERY_PRESENTATION[state.deliveryState] || DELIVERY_PRESENTATION.available;
  const panel = node('aside', `f3-learning-delivery f3-learning-delivery--${presentation.tone}`);
  panel.setAttribute('aria-live', 'polite');
  panel.append(node('p', 'f3-learning-delivery__eyebrow', 'دسترسی و دریافت'), node('h2', 'f3-learning-delivery__title', presentation.title), node('p', 'f3-learning-delivery__body', presentation.body));
  const access = catalogAccessPresentation(resource);
  panel.append(node('span', `f3-learning-access f3-learning-access--${access.tone}`, access.label));
  if (presentation.action) {
    const action = button(presentation.action, 'f3-learning-button f3-learning-button--primary');
    action.disabled = state.deliveryBusy;
    action.setAttribute('aria-busy', state.deliveryBusy ? 'true' : 'false');
    action.addEventListener('click', onDeliver);
    panel.append(action);
  }
  panel.append(node('p', 'f3-learning-delivery__note', 'مجوز دریافت کوتاه‌عمر است و هنگام دریافت دوباره توسط سرور بررسی می‌شود.'));
  return panel;
}

function renderDetail(state, host, callbacks) {
  host.replaceChildren();
  const root = node('section', 'f3-learning-detail');
  const back = button('بازگشت به منابع', 'f3-learning-back');
  back.addEventListener('click', callbacks.back);
  root.append(back);
  if (state.detailStatus === 'loading') {
    root.append(statePanel('در حال دریافت جزئیات…', 'اطلاعات منبع از مسیر امن در حال دریافت است.', null, null, 'info'));
    host.append(root);
    return;
  }
  if (state.detailStatus === 'denied') {
    root.append(statePanel('دسترسی تأیید نشد', 'جزئیات این منبع با دسترسی فعلی حساب قابل مشاهده نیست.', null, null, 'danger'));
    host.append(root);
    return;
  }
  if (state.detailStatus === 'unavailable' || !state.detail) {
    root.append(statePanel('جزئیات منبع در دسترس نیست', 'ممکن است منبع تغییر کرده یا دیگر منتشرشده نباشد.', 'بررسی دوباره', callbacks.retry, 'warning'));
    host.append(root);
    return;
  }
  const resource = state.detail;
  const heading = node('header', 'f3-learning-detail__heading');
  heading.append(node('span', 'f3-learning-resource__type', resource.typeLabel), node('h1', 'f3-learning-detail__title', resource.title));
  if (resource.description) heading.append(node('p', 'f3-learning-detail__description', resource.description));
  root.append(heading);
  const layout = node('div', 'f3-learning-detail__layout');
  const main = node('div', 'f3-learning-detail__main');
  const metadata = node('section', 'f3-learning-detail__metadata');
  metadata.append(node('h2', 'f3-learning-detail__section-title', 'اطلاعات منبع'));
  const dl = node('dl', 'f3-learning-detail__meta-grid');
  if (resource.courseTitle) dl.append(metadataItem('درس', resource.courseCode ? `${resource.courseTitle} · ${resource.courseCode}` : resource.courseTitle));
  dl.append(metadataItem('نوع', resource.typeLabel));
  if (resource.topic) dl.append(metadataItem('موضوع', resource.topic));
  if (resource.professor) dl.append(metadataItem('مدرس / ارائه‌دهنده', resource.professor));
  if (resource.version) dl.append(metadataItem('نسخه منتشرشده', formatNumber(state.ctx, resource.version)));
  const updated = formatDate(state.ctx, resource.updatedAt);
  if (updated) dl.append(metadataItem('آخرین به‌روزرسانی', updated));
  if (resource.formatKey) dl.append(metadataItem('قالب', resource.formatKey));
  metadata.append(dl);
  main.append(metadata);
  layout.append(main, deliveryPanel(state, resource, callbacks.deliver));
  root.append(layout);
  host.append(root);
}

async function deliverResource(state, host, callbacks) {
  if (!state.detail?.id || state.deliveryBusy) return;
  state.deliveryBusy = true;
  state.deliveryState = 'preparing';
  renderDetail(state, host, callbacks);
  const ws = workspaceId(state.ctx);
  try {
    if (typeof state.ctx?.capabilities?.learning?.deliverResource === 'function') {
      await state.ctx.capabilities.learning.deliverResource({ workspaceId: ws, resourceId: state.detail.id, channel: 'web' });
    } else {
      const issued = unwrap(await apiJson(state.ctx, `${API_ROOT}/${encodeURIComponent(ws)}/resources/${encodeURIComponent(state.detail.id)}/deliveries`, { method: 'POST', body: { channel: 'web' }, signal: state.abort.signal }));
      const deliveryToken = safeText(issued?.delivery_token, 4096);
      if (!deliveryToken) throw Object.assign(new Error('Delivery capability missing.'), { code: 'delivery_token_unavailable' });
      const served = unwrap(await apiJson(state.ctx, `${API_ROOT}/${encodeURIComponent(ws)}/deliveries/consume`, { method: 'POST', body: { delivery_token: deliveryToken }, signal: state.abort.signal }));
      if (served?.content && typeof state.ctx?.capabilities?.learning?.openStructuredContent === 'function') {
        await state.ctx.capabilities.learning.openStructuredContent(served.content, { title: state.detail.title });
      } else {
        const downloadToken = safeText(served?.download_token, 4096);
        if (!downloadToken) throw Object.assign(new Error('Download capability unavailable.'), { code: 'download_unavailable', status: 404 });
        const blob = await apiBinary(state.ctx, `${API_ROOT}/${encodeURIComponent(ws)}/downloads/consume`, { download_token: downloadToken });
        if (typeof state.ctx?.capabilities?.learning?.saveBlob !== 'function') throw Object.assign(new Error('Binary save adapter unavailable.'), { code: 'integration_binary_adapter_missing' });
        await state.ctx.capabilities.learning.saveBlob(blob, { title: state.detail.title });
      }
    }
    state.deliveryState = 'ready';
  } catch (error) {
    state.deliveryState = deliveryStateFromError(error);
  } finally {
    state.deliveryBusy = false;
    renderDetail(state, host, callbacks);
  }
}

export async function openResourceDetail(state, host, resource, trigger, backToLibrary) {
  if (!resource?.id) return;
  state.selected = resource;
  state.detailStatus = 'loading';
  state.detailError = null;
  const callbacks = {
    back: () => {
      state.selected = null;
      state.detail = null;
      state.detailStatus = 'idle';
      state.detailError = null;
      state.deliveryState = 'available';
      backToLibrary();
      trigger?.focus?.();
    },
    retry: () => openResourceDetail(state, host, resource, trigger, backToLibrary),
    deliver: () => deliverResource(state, host, callbacks),
  };
  renderDetail(state, host, callbacks);
  const ws = workspaceId(state.ctx);
  if (!ws) {
    state.detailStatus = 'unavailable';
    renderDetail(state, host, callbacks);
    return;
  }
  try {
    const payload = unwrap(await apiJson(state.ctx, `${API_ROOT}/${encodeURIComponent(ws)}/resources/${encodeURIComponent(resource.id)}`, { signal: state.abort.signal }));
    const courses = new Map(state.courses.map((course) => [course.id, course]));
    state.detail = normalizeResource({ ...resource, ...(payload || {}) }, courses);
    state.detailStatus = 'ready';
    state.deliveryState = 'available';
  } catch (error) {
    const status = Number(error?.status ?? error?.statusCode ?? error?.response?.status ?? 0);
    state.detailStatus = status === 403 ? 'denied' : 'unavailable';
    state.detailError = error;
  }
  renderDetail(state, host, callbacks);
}
