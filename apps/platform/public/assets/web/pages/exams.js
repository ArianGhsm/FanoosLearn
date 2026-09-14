/*
 * The exam catalogue.
 *
 * Lists what the workspace actually publishes, with enough on each card to
 * decide whether to open it: how many attempts are left, and whether one is
 * already in progress. Nothing here is hardcoded about a subject or a course
 * -- every label comes from the API row.
 */
import { api, describeError } from '../foundation/api.js';
import { el, faDigits, kindLabel, notice } from './runner-view.js';

const list = document.getElementById('catalog');
const filters = document.getElementById('catalog-filters');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';

let rows = [];
let kind = '';

function card(row) {
    const used = Number(row.attempts_used || 0);
    const max = Number(row.max_attempts || 0);
    const active = Boolean(row.active_attempt_id);
    const exhausted = !active && max > 0 && used >= max;

    const meta = el('div', { className: 'x-card__meta' },
        el('span', { className: 'x-chip x-chip--static', text: kindLabel(row.assessment_kind) }),
        row.course_title ? el('span', { className: 'f-tiny', text: String(row.course_title) }) : null,
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
            el('h2', { className: 'x-card__title', text: String(row.title || 'آزمون') }),
            meta),
        el('div', { className: 'x-card__side' }, status, action));
}

function render() {
    const shown = kind === '' ? rows : rows.filter((row) => row.assessment_kind === kind);
    list.setAttribute('aria-busy', 'false');

    if (rows.length === 0) {
        list.replaceChildren(el('div', { className: 'f-card f-empty' },
            el('div', { className: 'f-empty__title', text: 'هنوز آزمونی منتشر نشده' }),
            el('p', { text: 'وقتی نماینده یا مدیر کلاس آزمونی منتشر کند، همین‌جا ظاهر می‌شود.' })));
        return;
    }
    if (shown.length === 0) {
        list.replaceChildren(el('div', { className: 'f-card f-empty' },
            el('div', { className: 'f-empty__title', text: 'با این فیلتر آزمونی نیست' }),
            el('p', { text: 'فیلتر دیگری را امتحان کن.' })));
        return;
    }
    list.replaceChildren(el('div', { className: 'x-cards' }, ...shown.map(card)));
}

async function load() {
    list.setAttribute('aria-busy', 'true');
    try {
        const payload = await api.get(`/workspaces/${encodeURIComponent(workspaceId)}/assessments`);
        rows = Array.isArray(payload) ? payload : [];
        filters.hidden = rows.length === 0;
        render();
    } catch (error) {
        list.setAttribute('aria-busy', 'false');
        list.replaceChildren(notice('error', 'فهرست آزمون‌ها خوانده نشد', describeError(error), {
            label: 'تلاش دوباره',
            onClick: load,
        }));
    }
}

filters?.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-kind]');
    if (!button) return;
    kind = button.dataset.kind ?? '';
    for (const other of filters.querySelectorAll('button[data-kind]')) {
        other.classList.toggle('is-active', other === button);
    }
    render();
});

if (list && workspaceId) load();
