import { api } from '../foundation/api.js';
import { renderMindmap } from './mindmap-view.js';
import { renderMarkdown } from './markdown.js';

/*
 * «جمع‌بندی مبحث»: the published mind maps (and capsules) of the topic a
 * question belongs to, drawn under its explanation once the question is
 * answered. One map serves every question of its topic: the API returns
 * visuals linked to the question, to one of its concepts, or to its book
 * chapter or that chapter's parent (BankBrowseService::questionVisuals).
 *
 * A designed map comes as two pictures (scripts/visuals/render_mindmap.py):
 * the wide one, and "<key>.m", the same map laid out top to bottom for a
 * phone. They are paired into one <picture> so each screen gets the one it
 * can read; a tap opens it full screen.
 */

const workspaceId = () => document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = () => `/workspaces/${encodeURIComponent(workspaceId())}`;
const imageUrl = (key) => `/api/v1${base()}/bank/visuals/${encodeURIComponent(key)}/image`;
const cache = new Map();

function load(questionKey) {
    if (!cache.has(questionKey)) {
        cache.set(questionKey, api.get(`${base()}/bank/questions/${encodeURIComponent(questionKey)}/visuals`)
            .then((data) => (Array.isArray(data?.visuals) ? data.visuals : []))
            .catch(() => []));
    }
    return cache.get(questionKey);
}

function text(tag, className, value) {
    const element = document.createElement(tag);
    element.className = className;
    element.textContent = value;
    return element;
}

/** Wide and phone versions of one map side by side: [{wide, phone}]. */
export function pairVisuals(visuals) {
    const byBase = new Map();
    for (const visual of visuals) {
        const phone = typeof visual.key === 'string' && visual.key.endsWith('.m');
        const key = phone ? visual.key.slice(0, -2) : visual.key;
        const pair = byBase.get(key) ?? { wide: null, phone: null };
        pair[phone ? 'phone' : 'wide'] = visual;
        byBase.set(key, pair);
    }
    return [...byBase.values()].map((pair) => ({ wide: pair.wide ?? pair.phone, phone: pair.phone }));
}

function openViewer(src, alt) {
    const dialog = document.createElement('dialog');
    dialog.className = 'x-topic__viewer';
    dialog.setAttribute('aria-label', alt);
    const close = document.createElement('button');
    close.className = 'x-topic__viewer-close';
    close.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    close.type = 'button';
    close.setAttribute('aria-label', 'بستن');
    close.addEventListener('click', () => dialog.close());
    const image = document.createElement('img');
    image.src = src;
    image.alt = alt;
    dialog.append(close, image);
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    document.body.append(dialog);
    dialog.showModal();
}

function picture({ wide, phone }) {
    const pic = document.createElement('picture');
    if (phone && phone.has_image) {
        const source = document.createElement('source');
        source.media = '(max-width: 640px)';
        source.srcset = imageUrl(phone.key);
        pic.append(source);
    }
    const image = document.createElement('img');
    image.className = 'x-topic__img';
    image.src = imageUrl(wide.key);
    image.alt = wide.title;
    // Shown only after the answer, at the end of the page: start it at once.
    image.loading = 'eager';
    image.decoding = 'async';
    pic.append(image);
    const open = document.createElement('button');
    open.type = 'button';
    open.className = 'x-topic__open';
    open.setAttribute('aria-label', `نمایش «${wide.title}» در تمام صفحه`);
    open.addEventListener('click', () => openViewer(image.currentSrc || image.src, wide.title));
    open.append(pic);
    return open;
}

function one(pair) {
    const visual = pair.wide;
    const box = document.createElement('section');
    box.className = 'x-topic__item';
    if (visual.has_image) box.append(picture(pair));
    else if (visual.format === 'tree' && visual.tree) box.append(renderMindmap(visual.tree, { compact: true }));
    else if (visual.format === 'markdown' && visual.markdown) box.append(renderMarkdown(visual.markdown));
    const foot = document.createElement('p');
    foot.className = 'x-topic__foot';
    if (visual.from) {
        const from = text('span', 'x-topic__from', `از ${visual.from.title} (${visual.from.edition})`);
        from.dir = 'auto';
        foot.append(from);
    }
    const link = document.createElement('a');
    link.href = `/app/visuals/${encodeURIComponent(visual.key)}`;
    link.textContent = 'در کتابخانه‌ی نقشه‌ها';
    foot.append(link);
    box.append(foot);
    return box;
}

/** A section that fills itself in; stays hidden if the topic has no map. */
export function renderTopicVisuals(questionKey) {
    const section = document.createElement('section');
    section.className = 'x-topic';
    section.hidden = true;
    if (!questionKey || !workspaceId()) return section;
    load(String(questionKey)).then((visuals) => {
        const pairs = pairVisuals(visuals);
        if (pairs.length === 0) return;
        section.append(text('h3', 'x-topic__head', 'جمع‌بندی مبحث'), ...pairs.map(one));
        section.hidden = false;
    });
    return section;
}
