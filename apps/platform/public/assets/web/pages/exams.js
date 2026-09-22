/*
 * The exam catalogue.
 *
 * Lists what the workspace actually publishes, with enough on each card to
 * decide whether to open it: how many attempts are left, and whether one is
 * already in progress. Nothing here is hardcoded about a subject or a course
 * -- every label comes from the API row.
 *
 * Course first, when there is more than one course. The catalogue endpoint
 * returns at most 100 assessments, newest first -- right for a class that
 * published a handful by hand, and silently wrong for an imported bank of
 * several hundred past exams, where a flat list would have shown 100 and hidden
 * the rest with nothing to say they existed. So the page asks for the course
 * index first (GET /assessment-courses, the same visibility rule as the
 * catalogue) and loads one course's exams at a time.
 *
 * The chosen course lives in the URL fragment, so the back button returns to
 * the index and a link to a course can be shared.
 */
import { api, describeError } from '../foundation/api.js';
import { el, faDigits, faText, kindLabel, notice } from './runner-view.js';

const list = document.getElementById('catalog');
const filters = document.getElementById('catalog-filters');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;

/** Sentinel for assessments with no course -- the index lists them as a group of their own. */
const NO_COURSE = 'none';

let courses = [];
let rows = [];
let kind = '';
let query = '';
let course = null;

const collator = new Intl.Collator('fa', { numeric: true, sensitivity: 'base' });

function courseFromHash() {
    const match = window.location.hash.match(/^#course=([A-Za-z0-9-]+)$/);
    return match ? match[1] : null;
}

function matches(text) {
    return query === '' || String(text ?? '').toLocaleLowerCase('fa').includes(query);
}

function card(row) {
    const used = Number(row.attempts_used || 0);
    const max = Number(row.max_attempts || 0);
    const active = Boolean(row.active_attempt_id);
    const exhausted = !active && max > 0 && used >= max;

    const meta = el('div', { className: 'x-card__meta' },
        el('span', { className: 'x-chip x-chip--static', text: kindLabel(row.assessment_kind) }),
        // Inside a course the course name is the page heading; repeating it on
        // every card is noise. It stays on the flat list, where it is not.
        row.course_title && course === null ? el('span', { className: 'f-tiny', text: String(row.course_title) }) : null,
        row.term_name ? el('span', { className: 'f-tiny', text: String(row.term_name) }) : null);

    const status = active
        ? el('span', { className: 'x-card__status is-active', text: 'در جریان' })
        : (max > 0
            ? el('span', { className: 'x-card__status', text: `${faDigits(Math.max(0, max - used))} تلاش باقی‌مانده` })
            : null);

    const action = exhausted
        ? el('span', { className: 'f-tiny', text: 'تلاشی باقی نمانده' })
        : el('a', {
            className: 'f-btn f-btn--primary',
            attrs: { href: `/app/exams/${encodeURIComponent(row.id)}` },
            text: active ? 'ادامه' : 'شروع',
        });

    return el('article', { className: `f-card x-card${exhausted ? ' is-exhausted' : ''}` },
        el('div', { className: 'x-card__body' },
            el('h2', { className: 'x-card__title', text: faText(String(row.title || 'آزمون')) }),
            meta),
        el('div', { className: 'x-card__side' }, status, action));
}

function courseTile(entry) {
    const id = entry.course_id ?? NO_COURSE;
    return el('a', {
        className: 'f-card x-course',
        attrs: { href: `#course=${encodeURIComponent(id)}` },
    },
        el('span', { className: 'x-course__title', text: faText(entry.course_title ?? 'بدون درس') }),
        el('span', { className: 'x-course__count', text: `${faDigits(entry.exam_count)} آزمون` }));
}

function empty(title, message) {
    return el('div', { className: 'f-card f-empty' },
        el('div', { className: 'f-empty__title', text: title }),
        el('p', { text: message }));
}

function searchBox(placeholder) {
    const input = el('input', {
        className: 'x-catalog__search',
        type: 'search',
        attrs: { placeholder, 'aria-label': placeholder, autocomplete: 'off' },
        on: {
            input: (event) => {
                query = event.target.value.trim().toLocaleLowerCase('fa');
                renderBody();
            },
        },
    });
    input.value = query;
    return input;
}

let body = null;

function renderBody() {
    if (!body) return;

    if (course === null) {
        const shown = courses.filter((entry) => matches(entry.course_title ?? 'بدون درس'));
        body.replaceChildren(shown.length === 0
            ? empty('درسی با این نام نیست', 'نام دیگری را جست‌وجو کن.')
            : el('div', { className: 'x-courses' }, ...shown.map(courseTile)));
        return;
    }

    const shown = rows
        .filter((row) => kind === '' || row.assessment_kind === kind)
        .filter((row) => matches(row.title))
        .sort((a, b) => collator.compare(String(a.title ?? ''), String(b.title ?? '')));
    body.replaceChildren(shown.length === 0
        ? empty('با این فیلتر آزمونی نیست', 'فیلتر یا جست‌وجوی دیگری را امتحان کن.')
        : el('div', { className: 'x-cards' }, ...shown.map(card)));
}

function render() {
    list.setAttribute('aria-busy', 'false');

    if (courses.length === 0 && rows.length === 0) {
        filters.hidden = true;
        list.replaceChildren(empty('هنوز آزمونی منتشر نشده', 'وقتی نماینده یا مدیر کلاس آزمونی منتشر کند، همین‌جا ظاهر می‌شود.'));
        return;
    }

    const courseMode = courses.length > 1;
    // Kind filters only mean something inside a list of exams.
    filters.hidden = courseMode && course === null;

    const head = [];
    if (courseMode && course !== null) {
        const entry = courses.find((item) => (item.course_id ?? NO_COURSE) === course);
        head.push(el('nav', { className: 'x-catalog__crumbs', attrs: { 'aria-label': 'مسیر' } },
            el('a', { className: 'x-catalog__back', attrs: { href: '#' }, text: 'همه‌ی درس‌ها' }),
            el('span', { className: 'x-catalog__course', text: faText(entry?.course_title ?? 'بدون درس') })));
    }
    head.push(searchBox(course === null && courseMode ? 'جست‌وجوی درس…' : 'جست‌وجوی آزمون…'));

    body = el('div', { className: 'x-catalog__body' });
    list.replaceChildren(el('div', { className: 'x-catalog__tools' }, ...head), body);
    renderBody();
}

async function loadCourse(id) {
    list.setAttribute('aria-busy', 'true');
    const path = id === NO_COURSE
        ? `${base}/assessments`
        : `${base}/assessments?course_id=${encodeURIComponent(id)}`;
    const payload = await api.get(path);
    rows = Array.isArray(payload) ? payload : [];
    if (id === NO_COURSE) {
        rows = rows.filter((row) => !row.course_id);
    }
}

async function route() {
    course = courses.length > 1 ? courseFromHash() : null;
    query = '';
    try {
        if (courses.length <= 1) {
            // One course or none: nothing to choose between, so the flat list,
            // exactly as the page behaved before course browsing existed.
            list.setAttribute('aria-busy', 'true');
            const payload = await api.get(`${base}/assessments`);
            rows = Array.isArray(payload) ? payload : [];
        } else if (course !== null) {
            await loadCourse(course);
        } else {
            rows = [];
        }
        render();
    } catch (error) {
        showError(error);
    }
}

function showError(error) {
    list.setAttribute('aria-busy', 'false');
    list.replaceChildren(notice('error', 'فهرست آزمون‌ها خوانده نشد', describeError(error), {
        label: 'تلاش دوباره',
        onClick: load,
    }));
}

async function load() {
    list.setAttribute('aria-busy', 'true');
    try {
        const payload = await api.get(`${base}/assessment-courses`);
        courses = (Array.isArray(payload) ? payload : [])
            .filter((entry) => Number(entry.exam_count) > 0)
            .sort((a, b) => {
                // "No course" last; the rest alphabetically as a reader expects.
                if (!a.course_id) return 1;
                if (!b.course_id) return -1;
                return collator.compare(String(a.course_title ?? ''), String(b.course_title ?? ''));
            });
        await route();
    } catch (error) {
        showError(error);
    }
}

window.addEventListener('hashchange', () => {
    kind = '';
    for (const other of filters?.querySelectorAll('button[data-kind]') ?? []) {
        other.classList.toggle('is-active', (other.dataset.kind ?? '') === '');
    }
    route();
});

filters?.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-kind]');
    if (!button) return;
    kind = button.dataset.kind ?? '';
    for (const other of filters.querySelectorAll('button[data-kind]')) {
        other.classList.toggle('is-active', other === button);
    }
    renderBody();
});

if (list && workspaceId) load();
