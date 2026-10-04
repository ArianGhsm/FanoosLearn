/*
 * درسنامه‌ها: the library (data-page="library") and the reader
 * (data-page="lesson"). Written lessons render through renderMarkdown,
 * flashcards flip, and anything else is a file fetched through the
 * protected delivery flow (issue -> consume -> download). Every API string
 * goes in through textContent or renderMarkdown.
 */
import { api, describeError } from '../foundation/api.js';
import { renderMarkdown } from './markdown.js';
import { watermarkImage } from './watermark.js';
import { kindOf, lessonShape, parseCards, typeLabel } from './lessons-rules.js';
import { matches } from './bank-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const root = document.getElementById('lessons');
const STATUS = { draft: 'پیش‌نویس', review: 'در انتظار بازبینی', approved: 'تأییدشده', rejected: 'ردشده', published: 'منتشرشده' };

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    document.getElementById('lessons-error-text').textContent = describeError(error);
    document.getElementById('lessons-error').hidden = false;
}

function done(...children) {
    root.replaceChildren(...children);
    root.setAttribute('aria-busy', 'false');
}

function button(text, tone, onClick) {
    const b = el('button', `f-btn f-btn--${tone} b-start`, text);
    b.type = 'button';
    b.addEventListener('click', async () => {
        b.disabled = true;
        try {
            await onClick();
        } catch (error) {
            fail(error);
        } finally {
            b.disabled = false;
        }
    });
    return b;
}

/* --------------------------------------------------------------- library */

async function library() {
    const roles = (root.dataset.roles || '').split(' ');
    const search = document.getElementById('lesson-search');
    const tabs = [...document.querySelectorAll('[data-kind]')];
    let kind = '';
    let items = [];

    const draw = () => {
        const shown = items.filter((r) => (kind === '' || kindOf(r.type_key) === kind) && matches(search.value, r.title, r.topic, r.description));
        if (shown.length === 0) {
            const box = el('section', 'f-card b-empty');
            box.append(el('h2', '', items.length === 0 ? 'هنوز درسنامه‌ای منتشر نشده' : 'چیزی پیدا نشد'),
                el('p', 'f-muted', items.length === 0 ? 'درسنامه‌ها و خلاصه‌ها که اضافه شوند، این‌جا می‌آیند.' : 'عبارت دیگری را امتحان کن.'));
            done(box);
            return;
        }
        const sheet = el('div', 'b-sheet');
        for (const r of shown) {
            const published = r.lifecycle_status === 'published';
            const row = el(published ? 'a' : 'div', 'b-row');
            if (published) row.href = `/app/lessons/${encodeURIComponent(r.id)}`;
            const body = el('div', 'b-row__body');
            body.append(el('strong', 'b-row__title', r.title));
            const meta = el('div', 'b-row__meta');
            meta.append(el('span', '', typeLabel(r.type_key)));
            if (r.topic) meta.append(el('span', '', r.topic));
            const status = r.latest_version_status ?? r.lifecycle_status;
            if (!published || (status && status !== 'published')) meta.append(el('span', 'b-old', STATUS[status] ?? status));
            body.append(meta);
            row.append(body);
            if (r.latest_version_id && r.latest_version_status !== 'published') {
                const path = `${base}/resources/${encodeURIComponent(r.id)}/versions/${encodeURIComponent(r.latest_version_id)}`;
                const actions = el('div', 'b-actions');
                if (roles.includes('author') && ['draft', 'rejected'].includes(r.latest_version_status)) {
                    actions.append(button('ارسال برای بازبینی', 'ghost', async () => { await api.post(`${path}/review-request`, {}); await reload(); }));
                }
                if (roles.includes('reviewer') && r.latest_version_status === 'review') {
                    actions.append(button('تأیید', 'primary', async () => { await api.post(`${path}/review`, { decision: 'approved' }); await reload(); }));
                    actions.append(button('رد', 'ghost', async () => { await api.post(`${path}/review`, { decision: 'rejected' }); await reload(); }));
                }
                if (roles.includes('publisher') && r.latest_version_status === 'approved') {
                    actions.append(button('انتشار', 'primary', async () => { await api.post(`${path}/publish`, {}); await reload(); }));
                }
                if (actions.firstChild) row.append(actions);
            }
            if (published) row.append(el('span', 'b-row__go', '←'));
            sheet.append(row);
        }
        done(sheet);
    };

    const reload = async () => {
        try {
            items = await api.get(`${base}/resources?sort=title`);
            items = Array.isArray(items) ? items.filter((r) => !['question_bank', 'past_exam'].includes(r.type_key)) : [];
        } catch (error) {
            items = [];
            fail(error);
        }
        draw();
    };

    for (const tab of tabs) {
        tab.addEventListener('click', () => {
            kind = tab.dataset.kind;
            for (const t of tabs) t.classList.toggle('is-active', t === tab);
            draw();
        });
    }
    search.addEventListener('input', draw);

    const form = document.getElementById('compose');
    if (form) {
        const type = document.getElementById('lesson-type');
        const text = document.getElementById('lesson-body');
        type.addEventListener('change', () => {
            text.placeholder = type.value === 'flashcards'
                ? 'هر خط یک کارت: روی کارت :: پشت کارت'
                : 'متن درسنامه. تیترها با ## و فهرست‌ها با - شروع می‌شوند.';
        });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const status = document.getElementById('lesson-status');
            const title = document.getElementById('lesson-title').value.trim();
            const topic = document.getElementById('lesson-topic').value.trim();
            const content = type.value === 'flashcards' ? { cards: parseCards(text.value) } : { format: 'markdown', body: text.value };
            if (title === '' || (content.cards ? content.cards.length === 0 : text.value.trim() === '')) {
                status.textContent = type.value === 'flashcards' ? 'عنوان و دست‌کم یک کارت «رو :: پشت» لازم است.' : 'عنوان و متن لازم است.';
                return;
            }
            const save = document.getElementById('lesson-save');
            save.disabled = true;
            try {
                await api.post(`${base}/resources`, { type: type.value, title, content, metadata: topic === '' ? {} : { topic } });
                form.reset();
                status.textContent = 'پیش‌نویس ذخیره شد؛ از فهرست پایین برای بازبینی بفرست.';
                await reload();
            } catch (error) {
                status.textContent = describeError(error);
            } finally {
                save.disabled = false;
            }
        });
    }
    await reload();
}

/* ---------------------------------------------------------------- reader */

function flashcards(cards) {
    let index = 0;
    let flipped = false;
    const card = el('button', 'l-card');
    card.type = 'button';
    const face = el('p', 'l-card__face');
    const count = el('p', 'f-tiny l-card__count');
    card.append(face);
    const draw = () => {
        face.textContent = flipped ? cards[index].back : cards[index].front;
        card.classList.toggle('is-back', flipped);
        count.textContent = `کارت ${(index + 1).toLocaleString('fa-IR')} از ${cards.length.toLocaleString('fa-IR')} · ${flipped ? 'پشت' : 'رو'} (برای برگرداندن بزن)`;
    };
    card.addEventListener('click', () => { flipped = !flipped; draw(); });
    const nav = el('div', 'b-actions l-card__nav');
    const step = (delta) => () => { index = (index + delta + cards.length) % cards.length; flipped = false; draw(); };
    const prev = el('button', 'f-btn f-btn--ghost', 'قبلی');
    prev.type = 'button';
    prev.addEventListener('click', step(-1));
    const next = el('button', 'f-btn f-btn--primary', 'بعدی');
    next.type = 'button';
    next.addEventListener('click', step(1));
    nav.append(prev, next);
    draw();
    const wrap = el('div', 'l-cards');
    wrap.append(card, count, nav);
    return wrap;
}

async function openFile(button, resourceId) {
    button.disabled = true;
    try {
        const issued = await api.post(`${base}/resources/${encodeURIComponent(resourceId)}/deliveries`, { channel: 'web' });
        const served = await api.post(`${base}/deliveries/consume`, { delivery_token: issued.delivery_token });
        if (!served.download_token) throw new Error('no_file');
        const csrf = document.querySelector('meta[name="fanoos-csrf"]')?.content ?? '';
        const response = await fetch(`/api/v1${base}/downloads/consume`, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ download_token: served.download_token }),
        });
        if (!response.ok) throw new Error('download_failed');
        const url = URL.createObjectURL(await response.blob());
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (error) {
        fail(error);
    } finally {
        button.disabled = false;
    }
}

async function lesson() {
    const id = root.dataset.resource;
    let data;
    try {
        data = await api.get(`${base}/resources/${encodeURIComponent(id)}`);
    } catch (error) {
        done();
        fail(error);
        return;
    }
    document.title = `${data.title} | درسنامه‌ها | فانوس`;
    const head = el('header', 'l-reader__head');
    head.append(el('h1', '', data.title));
    const meta = el('p', 'f-muted');
    meta.textContent = [typeLabel(data.type_key), data.topic].filter(Boolean).join(' · ');
    head.append(meta);
    if (data.description) head.append(el('p', '', data.description));

    const shape = lessonShape(data.content);
    let body;
    if (shape.kind === 'text') {
        body = renderMarkdown(shape.body);
        body.classList.add('l-reader__body');
    } else if (shape.kind === 'cards') {
        body = flashcards(shape.cards);
    } else {
        body = el('div', 'l-reader__file');
        body.append(el('p', 'f-muted', 'این درسنامه یک فایل است و با نام تو نشانه‌گذاری می‌شود.'));
        const open = el('button', 'f-btn f-btn--primary', 'باز کردن فایل');
        open.type = 'button';
        open.addEventListener('click', () => openFile(open, id));
        body.append(open);
    }
    const mark = root.dataset.watermark || '';
    const layer = el('div', 'l-watermark');
    layer.setAttribute('aria-hidden', 'true');
    if (mark) layer.style.backgroundImage = watermarkImage(mark);
    done(head, body, layer);
}

if (workspaceId && root) {
    ({ library, lesson })[root.dataset.page]?.();
}
