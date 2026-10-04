/*
 * تابلو اعلانات: GET /announcements, unread ones marked read once shown;
 * the composer (shown only to broadcasters) posts to /announcements.
 * Every API string goes in through textContent or renderMarkdown.
 */
import { api, describeError } from '../foundation/api.js';
import { renderMarkdown } from './markdown.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const root = document.getElementById('news');
const dateFormat = new Intl.DateTimeFormat('fa-IR', { day: 'numeric', month: 'long', year: 'numeric' });

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

async function load() {
    root.setAttribute('aria-busy', 'true');
    let items;
    try {
        items = await api.get(`${base}/announcements`);
    } catch (error) {
        root.replaceChildren();
        document.getElementById('news-error-text').textContent = describeError(error);
        document.getElementById('news-error').hidden = false;
        return;
    } finally {
        root.setAttribute('aria-busy', 'false');
    }
    if (!Array.isArray(items) || items.length === 0) {
        const box = el('section', 'f-card b-empty');
        box.append(el('h2', '', 'هنوز اطلاعیه‌ای نیست'), el('p', 'f-muted', 'خبر تازه‌ای که منتشر شود، این‌جا می‌آید.'));
        root.replaceChildren(box);
        return;
    }
    root.replaceChildren(...items.map((item) => {
        const unread = item.read_at === null || item.read_at === undefined;
        const card = el('article', `f-card n-item${unread ? ' is-unread' : ''}`);
        const head = el('header', 'n-item__head');
        head.append(el('h2', '', item.title));
        if (unread) head.append(el('span', 'n-item__new', 'تازه'));
        const when = item.published_at ? dateFormat.format(new Date(`${String(item.published_at).replace(' ', 'T')}Z`)) : '';
        card.append(head, el('p', 'f-tiny n-item__when', [when, item.scope_course_title].filter(Boolean).join(' · ')), renderMarkdown(item.body));
        if (unread) api.post(`${base}/announcements/${encodeURIComponent(item.id)}/read`, {}).catch(() => {});
        return card;
    }));
}

const form = document.getElementById('compose');
if (form) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const title = document.getElementById('compose-title');
        const body = document.getElementById('compose-body');
        const status = document.getElementById('compose-status');
        const send = document.getElementById('compose-send');
        if (title.value.trim() === '' || body.value.trim() === '') {
            status.textContent = 'عنوان و متن هر دو لازم است.';
            return;
        }
        send.disabled = true;
        try {
            await api.post(`${base}/announcements`, { title: title.value, body: body.value });
            title.value = '';
            body.value = '';
            status.textContent = 'منتشر شد.';
            load();
        } catch (error) {
            status.textContent = describeError(error);
        } finally {
            send.disabled = false;
        }
    });
}

if (workspaceId) load();
