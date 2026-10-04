/*
 * تقویم آزمون‌ها: GET /exam-calendar, grouped open / upcoming / closed; the
 * scheduling form (exam.manage) posts to /exam-calendar/{assessment}.
 * Every API string goes in through textContent.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { localInputToIso, windowLabel } from './plan-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const root = document.getElementById('calendar');
const manage = root?.dataset.manage === '1';
const GROUPS = [['open', 'در حال برگزاری'], ['upcoming', 'به‌زودی'], ['closed', 'تمام‌شده']];

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    document.getElementById('cal-error-text').textContent = describeError(error);
    document.getElementById('cal-error').hidden = false;
}

function row(item) {
    const card = el('div', 'b-row');
    const body = el('div', 'b-row__body');
    body.append(el('strong', 'b-row__title', item.title));
    if (item.note) body.append(el('p', 'f-tiny', item.note));
    const meta = el('div', 'b-row__meta');
    meta.append(el('span', '', windowLabel(item.opens_at, item.closes_at)));
    meta.append(el('span', '', `${faDigits(item.question_count)} سؤال`));
    if (item.time_limit_minutes) meta.append(el('span', '', `${faDigits(item.time_limit_minutes)} دقیقه`));
    meta.append(el('span', '', `${faDigits(item.participants)} شرکت‌کننده`));
    if (item.my_score_percent !== null) meta.append(el('span', 'b-old', `نمره‌ی تو ٪${faDigits(item.my_score_percent)}`));
    body.append(meta);
    card.append(body);
    const actions = el('div', 'b-actions');
    if (item.state === 'open' || item.state === 'closed') {
        const go = el('a', `f-btn f-btn--${item.state === 'open' && item.my_score_percent === null ? 'primary' : 'ghost'} b-start`,
            item.state === 'open' ? (item.my_score_percent === null ? 'شرکت در آزمون' : 'مرور و کارنامه') : 'کارنامه و تمرین');
        go.href = `/app/exams/${encodeURIComponent(item.assessment_id)}`;
        actions.append(go);
    }
    if (manage) {
        const remove = el('button', 'f-btn f-btn--ghost b-start', 'حذف از تقویم');
        remove.type = 'button';
        remove.addEventListener('click', async () => {
            remove.disabled = true;
            try {
                await api.post(`${base}/exam-calendar/${encodeURIComponent(item.assessment_id)}`, { remove: true });
                load();
            } catch (error) {
                remove.disabled = false;
                fail(error);
            }
        });
        actions.append(remove);
    }
    card.append(actions);
    return card;
}

async function load() {
    root.setAttribute('aria-busy', 'true');
    let items;
    try {
        items = await api.get(`${base}/exam-calendar`);
    } catch (error) {
        root.replaceChildren();
        fail(error);
        return;
    } finally {
        root.setAttribute('aria-busy', 'false');
    }
    if (items.length === 0) {
        const box = el('section', 'f-card b-empty');
        box.append(el('h2', '', 'فعلاً آزمون زمان‌داری در تقویم نیست'), el('p', 'f-muted', 'آزمون‌های جامع و آزمونک‌ها که زمان‌بندی شوند، این‌جا با مهلتشان می‌آیند.'));
        root.replaceChildren(box);
        return;
    }
    const parts = [];
    for (const [state, label] of GROUPS) {
        const group = items.filter((i) => i.state === state);
        if (group.length === 0) continue;
        const section = el('section', 'b-panel');
        section.append(el('h2', '', label));
        const sheet = el('div', 'b-sheet');
        for (const item of state === 'closed' ? [...group].reverse() : group) sheet.append(row(item));
        section.append(sheet);
        parts.push(section);
    }
    root.replaceChildren(...parts);
}

async function scheduler() {
    const form = document.getElementById('schedule');
    if (!form) return;
    const select = document.getElementById('schedule-exam');
    try {
        const exams = await api.get(`${base}/assessments`);
        select.replaceChildren(el('option', '', 'یک آزمون انتخاب کن'));
        select.firstChild.value = '';
        for (const exam of Array.isArray(exams) ? exams : []) {
            const option = el('option', '', exam.title);
            option.value = exam.id;
            select.append(option);
        }
    } catch (error) {
        fail(error);
    }
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const status = document.getElementById('schedule-status');
        const opens = localInputToIso(document.getElementById('schedule-opens').value);
        const closes = localInputToIso(document.getElementById('schedule-closes').value);
        if (!select.value || !opens || !closes) {
            status.textContent = 'آزمون، شروع و پایان مهلت لازم است.';
            return;
        }
        const save = document.getElementById('schedule-save');
        save.disabled = true;
        try {
            await api.post(`${base}/exam-calendar/${encodeURIComponent(select.value)}`, { opens_at: opens, closes_at: closes, note: document.getElementById('schedule-note').value });
            status.textContent = 'در تقویم ثبت شد.';
            form.reset();
            load();
        } catch (error) {
            status.textContent = describeError(error);
        } finally {
            save.disabled = false;
        }
    });
}

if (workspaceId && root) {
    load();
    if (manage) scheduler();
}
