import { api } from '../foundation/api.js';
import { renderMindmap } from './mindmap-view.js';
import { renderMarkdown } from './markdown.js';

/*
 * «جمع‌بندی مبحث»: the published mind maps (and capsules) of the topic a
 * question belongs to, drawn under its explanation. One map serves every
 * question of its topic: the API returns visuals linked to the question,
 * to one of its concepts, or to its book chapter or that chapter's parent
 * (BankBrowseService::questionVisuals). Loaded after the explanation is on
 * screen; nothing is shown when the topic has none yet.
 */

const workspaceId = () => document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const cache = new Map();

function load(questionKey) {
    if (!cache.has(questionKey)) {
        const base = `/workspaces/${encodeURIComponent(workspaceId())}`;
        cache.set(questionKey, api.get(`${base}/bank/questions/${encodeURIComponent(questionKey)}/visuals`)
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

function one(visual) {
    const box = document.createElement('details');
    box.className = 'x-topic__item';
    box.open = true;
    const summary = document.createElement('summary');
    summary.className = 'x-topic__summary';
    summary.append(text('span', 'x-topic__badge', visual.kind === 'mindmap' ? '🧠 نقشه‌ی ذهنی' : '💊 کپسول'), text('span', 'x-topic__title', visual.title));
    box.append(summary);
    if (visual.format === 'tree' && visual.tree) box.append(renderMindmap(visual.tree, { compact: true }));
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
    link.textContent = 'نمایش در صفحه‌ی کامل';
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
        if (visuals.length === 0) return;
        section.append(text('h3', 'x-topic__head', 'جمع‌بندی مبحث'), ...visuals.map(one));
        section.hidden = false;
    });
    return section;
}
