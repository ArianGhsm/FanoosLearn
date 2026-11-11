/*
 * Pure rules of the paged lesson reader (tests/web/lesson_pages_test.mjs).
 *
 * A written lesson is split into pages at its `## ` headings: page 1 is the
 * table of contents (with any text written before the first heading), and
 * each `## ` section is one page after it. A section titled «خلاصه» or
 * «جمع‌بندی» is marked as the summary page. A lesson with fewer than two
 * sections is not paged at all and reads as one page, as before.
 */

const SECTION = /^##\s+(.+?)\s*#*$/;
const SUMMARY = /خلاصه|جمع[\s‌]?بندی/;

/** {intro, pages: [{title, body, summary}]} or null when the lesson is not paged. */
export function paginate(body) {
    const lines = String(body ?? '').replace(/\r\n/g, '\n').split('\n');
    const intro = [];
    const pages = [];
    let current = null;
    let fence = false;
    for (const line of lines) {
        if (/^\s*```/.test(line)) fence = !fence;
        const heading = fence ? null : SECTION.exec(line.trim());
        if (heading && !line.trim().startsWith('###')) {
            current = { title: heading[1].trim(), lines: [] };
            pages.push(current);
            continue;
        }
        (current ? current.lines : intro).push(line);
    }
    if (pages.length < 2) return null;
    return {
        intro: intro.join('\n').trim(),
        pages: pages.map((p) => ({ title: p.title, body: p.lines.join('\n').trim(), summary: SUMMARY.test(p.title) })),
    };
}

/** Total pages, counting the contents page. */
export function pageCount(book) {
    return book ? book.pages.length + 1 : 1;
}

/** Contents entries: each section's title and the page it is on (page 1 is the contents). */
export function tocEntries(book) {
    return book ? book.pages.map((p, i) => ({ title: p.title, page: i + 2, summary: p.summary })) : [];
}

/** A page number forced into 1..total; anything unreadable is page 1. */
export function clampPage(value, total) {
    const n = Number.parseInt(String(value ?? '').replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))), 10);
    if (!Number.isFinite(n) || n < 1) return 1;
    return Math.min(n, Math.max(1, total));
}

/** The page named by a `#p3` hash, or null. */
export function pageFromHash(hash, total) {
    const match = /^#p(\d{1,4})$/.exec(String(hash ?? ''));
    return match ? clampPage(match[1], total) : null;
}

/** How far through the lesson page n of total is, 0–100. */
export function progressPercent(page, total) {
    if (total <= 1) return 100;
    return Math.round(((clampPage(page, total) - 1) / (total - 1)) * 100);
}

/** Where the reader's last page of one lesson is remembered on this device. */
export function storageKey(resourceId) {
    return `fanoos.lesson.${String(resourceId)}.page`;
}
