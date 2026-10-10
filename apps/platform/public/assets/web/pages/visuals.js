/*
 * نقشه‌ها و خلاصه‌ها: the library (search, kind filter, ?subject= /
 * ?edition=&chapter= from a chapter's link) and one visual -- a mind map
 * drawn as a collapsible tree, a capsule or table from the markdown subset,
 * or an image -- with what it explains and the book pages it was made from.
 * Every API string goes in through textContent.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { matches } from './bank-rules.js';
import { KIND_LABELS, inline, parseMarkdown } from './visuals-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const root = document.getElementById('visuals');
const errorBox = document.getElementById('visual-error');

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    document.getElementById('visual-error-text').textContent = describeError(error);
    errorBox.hidden = false;
    root.replaceChildren();
    root.setAttribute('aria-busy', 'false');
}

function done(...children) {
    root.replaceChildren(...children);
    root.setAttribute('aria-busy', 'false');
}

/* ------------------------------------------------------------- library */

async function library() {
    const params = new URLSearchParams(window.location.search);
    const query = new URLSearchParams();
    for (const name of ['subject', 'edition', 'chapter']) if (params.get(name)) query.set(name, params.get(name));
    let rows;
    try {
        rows = await api.get(`${base}/bank/visuals${query.toString() ? `?${query}` : ''}`);
    } catch (error) {
        fail(error);
        return;
    }
    const search = document.getElementById('visual-search');
    const kinds = document.getElementById('visual-kinds');
    let kind = '';
    const present = Object.keys(KIND_LABELS).filter((k) => rows.some((row) => row.kind === k));
    for (const k of ['', ...present]) {
        const chip = el('button', `x-chip${k === '' ? ' is-active' : ''}`, k === '' ? 'همه' : KIND_LABELS[k]);
        chip.type = 'button';
        chip.addEventListener('click', () => {
            kind = k;
            [...kinds.children].forEach((other) => other.classList.toggle('is-active', other === chip));
            draw();
        });
        kinds.append(chip);
    }
    const scope = params.get('chapter') ? 'این فصل' : (params.get('subject') ? 'این درس' : '');

    function draw() {
        const shown = rows.filter((row) => (kind === '' || row.kind === kind) && matches(search.value, row.title, row.subject_name));
        if (shown.length === 0) {
            const box = el('section', 'f-card v-empty');
            box.append(el('h2', '', rows.length === 0 ? `هنوز نقشه‌ای${scope ? ` برای ${scope}` : ''} منتشر نشده` : 'چیزی پیدا نشد'),
                el('p', 'f-muted', rows.length === 0 ? 'نقشه‌های ذهنی و خلاصه‌ها فصل به فصل اضافه می‌شوند.' : 'عنوان یا درس دیگری را جست‌وجو کن.'));
            done(box);
            return;
        }
        const groups = new Map();
        for (const row of shown) {
            const name = row.subject_name ?? 'عمومی';
            if (!groups.has(name)) groups.set(name, []);
            groups.get(name).push(row);
        }
        done(...[...groups].map(([name, items]) => {
            const section = el('section', 'v-group');
            section.append(el('h2', 'v-group__title', name));
            const grid = el('div', 'v-cards');
            for (const item of items) {
                const card = el('a', `v-card v-card--${item.kind}`);
                card.href = `/app/visuals/${encodeURIComponent(item.key)}`;
                card.append(el('span', 'v-card__kind', KIND_LABELS[item.kind] ?? item.kind), el('strong', 'v-card__title', item.title));
                grid.append(card);
            }
            section.append(grid);
            return section;
        }));
    }
    search.addEventListener('input', draw);
    draw();
}

/* -------------------------------------------------------------- visual */

function inlineInto(node, text) {
    for (const part of inline(text)) node.append(part.bold ? el('strong', '', part.text) : document.createTextNode(part.text));
    return node;
}

function markdown(source) {
    const box = el('div', 'v-doc');
    for (const block of parseMarkdown(source)) {
        if (block.type === 'heading') box.append(inlineInto(el(`h${block.level + 1}`, 'v-doc__h'), block.text));
        else if (block.type === 'paragraph') box.append(inlineInto(el('p', 'v-doc__p'), block.text));
        else if (block.type === 'list') {
            const list = el(block.ordered ? 'ol' : 'ul', 'v-doc__list');
            for (const item of block.items) list.append(inlineInto(el('li'), item));
            box.append(list);
        } else if (block.type === 'table') {
            const wrap = el('div', 'v-doc__table');
            const table = el('table');
            block.rows.forEach((row, r) => {
                const tr = el('tr');
                for (const cell of row) tr.append(inlineInto(el(block.header && r === 0 ? 'th' : 'td'), cell));
                table.append(tr);
            });
            wrap.append(table);
            box.append(wrap);
        }
    }
    return box;
}

/* A mind map: the root, and each branch a nested list that folds open and shut. */
function tree(node, depth = 0) {
    const item = el('li', `v-node v-node--d${Math.min(depth, 4)}`);
    const children = Array.isArray(node.children) ? node.children : [];
    const label = el(children.length > 0 ? 'button' : 'span', 'v-node__label');
    if (children.length > 0) {
        label.type = 'button';
        label.setAttribute('aria-expanded', String(depth < 2));
    }
    label.append(el('span', 'v-node__text', node.text));
    if (node.note) label.append(el('span', 'v-node__note', node.note));
    item.append(label);
    if (children.length > 0) {
        const list = el('ul', 'v-tree__branch');
        list.hidden = depth >= 2;
        for (const child of children) list.append(tree(child, depth + 1));
        item.append(list);
        label.addEventListener('click', () => {
            list.hidden = !list.hidden;
            label.setAttribute('aria-expanded', String(!list.hidden));
        });
    }
    return item;
}

function mindmap(outline) {
    const wrap = el('div', 'v-tree');
    const tools = el('div', 'v-tree__tools');
    const open = el('button', 'f-btn f-btn--ghost', 'باز کردن همه');
    const shut = el('button', 'f-btn f-btn--ghost', 'بستن همه');
    for (const button of [open, shut]) button.type = 'button';
    const list = el('ul', 'v-tree__root');
    list.append(tree(outline));
    const toggleAll = (show) => {
        list.querySelectorAll('.v-tree__branch').forEach((branch) => { branch.hidden = !show; });
        list.querySelectorAll('button.v-node__label').forEach((label) => label.setAttribute('aria-expanded', String(show)));
        list.querySelector(':scope > li > .v-tree__branch')?.removeAttribute('hidden');
    };
    open.addEventListener('click', () => toggleAll(true));
    shut.addEventListener('click', () => toggleAll(false));
    tools.append(open, shut);
    wrap.append(tools, list);
    return wrap;
}

async function visual() {
    const key = root.dataset.key;
    let data;
    try {
        data = await api.get(`${base}/bank/visuals/${encodeURIComponent(key)}`);
    } catch (error) {
        fail(error);
        return;
    }
    document.title = `${data.title} | فانوس`;
    const head = el('header', 'v-head');
    head.append(el('span', `v-card__kind v-card--${data.kind}`, KIND_LABELS[data.kind] ?? data.kind), el('h1', '', data.title));
    if (data.subject_name) head.append(el('p', 'f-muted', data.subject_name));
    const parts = [head];

    const body = el('section', 'f-card v-body');
    if (data.format === 'tree' && data.tree) body.append(mindmap(data.tree));
    else if (data.format === 'markdown') body.append(markdown(data.markdown));
    else if (data.has_image) {
        const image = el('img', 'v-image');
        image.src = `/api/v1${base}/bank/visuals/${encodeURIComponent(data.key)}/image`;
        image.alt = data.title;
        image.loading = 'lazy';
        body.append(image);
    }
    parts.push(body);

    const about = el('section', 'v-about');
    if (data.from) {
        const pages = data.from.pdf_page_from
            ? ` · صفحهٔ ${faDigits(data.from.pdf_page_from)}${data.from.pdf_page_to && data.from.pdf_page_to !== data.from.pdf_page_from ? ` تا ${faDigits(data.from.pdf_page_to)}` : ''} فایل کتاب`
            : '';
        const from = el('p', 'v-about__from', `ساخته‌شده از ${data.from.title} (${data.from.edition})${pages}`);
        from.dir = 'auto';
        about.append(from);
    }
    if (data.chapters.length > 0) {
        about.append(el('h2', 'v-about__title', 'فصل‌ها'));
        const list = el('ul', 'v-about__list');
        for (const chapter of data.chapters) list.append(el('li', '', `${chapter.number ? `فصل ${faDigits(chapter.number)} · ` : ''}${chapter.title_fa ?? chapter.title}`));
        about.append(list);
    }
    if (data.concepts.length > 0) {
        about.append(el('h2', 'v-about__title', 'مبحث‌ها'));
        const list = el('ul', 'v-about__list');
        for (const concept of data.concepts) list.append(el('li', '', concept.name));
        about.append(list);
    }
    if (data.origin === 'ai' && !data.reviewed) about.append(el('p', 'v-about__note', 'این نقشه را هوش مصنوعی از روی کتاب ساخته و هنوز متخصص بازبینی‌اش نکرده است.'));
    if (about.childNodes.length > 0) parts.push(about);
    done(...parts);
}

if (workspaceId && root) {
    ({ library, visual })[root.dataset.page]?.();
}
