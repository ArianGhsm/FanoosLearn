/*
 * The paged reader of a written lesson (12_LESSON_FORMAT.md).
 *
 * Page 1 is the contents; each `## ` section is a page after it. A progress
 * bar, previous / next, «go to page» and «contents» move between pages; the
 * page shown is kept in the address (#p3) and remembered on this device, so
 * the lesson opens where the reader left it. All text goes in through
 * textContent or renderMarkdown.
 */
import { renderMarkdown } from './markdown.js';
import { clampPage, pageCount, pageFromHash, paginate, progressPercent, storageKey, tocEntries } from './lesson-pages.js';

const faNum = (n) => Number(n).toLocaleString('fa-IR');

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function remembered(key) {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
}

function remember(key, page) {
    try {
        window.localStorage.setItem(key, String(page));
    } catch {
        // Private windows and blocked storage: the lesson still reads, it just
        // opens on page 1 next time.
    }
}

function navButton(text, tone, onClick) {
    const b = el('button', `f-btn f-btn--${tone}`, text);
    b.type = 'button';
    b.addEventListener('click', onClick);
    return b;
}

/** The reader for a written lesson body; an unpaged lesson is one page, as before. */
export function lessonBody(resourceId, body) {
    const book = paginate(body);
    if (!book) {
        const flat = renderMarkdown(body);
        flat.classList.add('l-reader__body');
        return flat;
    }

    const total = pageCount(book);
    const key = storageKey(resourceId);
    const wrap = el('div', 'l-book');

    const progress = el('div', 'l-progress');
    progress.setAttribute('role', 'progressbar');
    progress.setAttribute('aria-valuemin', '0');
    progress.setAttribute('aria-valuemax', '100');
    progress.setAttribute('aria-label', 'پیشرفت در این درسنامه');
    const fill = el('div', 'l-progress__fill');
    const percent = el('span', 'l-progress__label');
    progress.append(fill, percent);

    const view = el('section', 'l-page');
    view.tabIndex = -1;
    view.setAttribute('aria-live', 'polite');

    const prev = navButton('→ صفحه‌ی قبل', 'ghost', () => show(page - 1, true));
    const next = navButton('صفحه‌ی بعد ←', 'primary', () => show(page + 1, true));
    const where = el('span', 'l-nav__where');
    const nav = el('nav', 'l-nav');
    nav.setAttribute('aria-label', 'صفحه‌های درسنامه');
    nav.append(prev, where, next);

    const go = el('form', 'l-goto');
    const label = el('label', 'f-tiny', 'برو به صفحه‌ی');
    const input = el('input', 'f-input l-goto__input');
    input.type = 'number';
    input.inputMode = 'numeric';
    input.min = '1';
    input.max = String(total);
    input.id = `l-goto-${resourceId}`;
    label.htmlFor = input.id;
    const submit = el('button', 'f-btn f-btn--ghost', 'برو');
    submit.type = 'submit';
    const contents = navButton('فهرست مطالب', 'ghost', () => show(1, true));
    go.append(label, input, submit, contents);
    go.addEventListener('submit', (event) => {
        event.preventDefault();
        show(clampPage(input.value, total), true);
    });

    let page = 1;

    function contentsPage() {
        const nodes = [el('h2', 'l-page__title', 'فهرست مطالب')];
        if (book.intro) nodes.push(renderMarkdown(book.intro));
        nodes.push(el('p', 'f-muted', 'روی هر بخش بزن تا مستقیم به صفحه‌اش بروی.'));
        const list = el('ol', 'l-toc');
        for (const entry of tocEntries(book)) {
            const item = el('li', entry.summary ? 'is-summary' : '');
            const link = el('button', 'l-toc__link');
            link.type = 'button';
            link.append(el('span', 'l-toc__title', entry.title), el('span', 'l-toc__page', `صفحه‌ی ${faNum(entry.page)}`));
            link.addEventListener('click', () => show(entry.page, true));
            item.append(link);
            list.append(item);
        }
        nodes.push(list);
        return nodes;
    }

    function sectionPage(section) {
        const title = el('h2', `l-page__title${section.summary ? ' is-summary' : ''}`, section.title);
        const content = renderMarkdown(section.body);
        content.classList.add('l-page__body');
        return [title, content];
    }

    function show(target, moved) {
        page = clampPage(target, total);
        view.replaceChildren(...(page === 1 ? contentsPage() : sectionPage(book.pages[page - 2])));
        view.classList.toggle('is-summary', page > 1 && book.pages[page - 2].summary);
        const pct = progressPercent(page, total);
        fill.style.inlineSize = `${pct}%`;
        percent.textContent = `${faNum(pct)}٪`;
        progress.setAttribute('aria-valuenow', String(pct));
        where.textContent = `صفحه‌ی ${faNum(page)} از ${faNum(total)}`;
        prev.disabled = page === 1;
        next.disabled = page === total;
        input.value = '';
        input.placeholder = faNum(page);
        remember(key, page);
        try {
            window.history.replaceState(null, '', `#p${page}`);
        } catch {
            // Without history the page simply is not in the address.
        }
        if (moved) {
            view.focus({ preventScroll: true });
            wrap.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }
    }

    document.addEventListener('keydown', (event) => {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) return;
        if (event.target instanceof HTMLElement && event.target.closest('input, textarea, select, [contenteditable="true"]')) return;
        // Right-to-left: the arrow pointing left goes forward.
        if (event.key === 'ArrowLeft' && page < total) show(page + 1, true);
        if (event.key === 'ArrowRight' && page > 1) show(page - 1, true);
    });

    wrap.append(progress, view, nav, go);
    show(pageFromHash(window.location.hash, total) ?? clampPage(remembered(key), total), false);
    return wrap;
}
