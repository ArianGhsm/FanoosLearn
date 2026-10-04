/*
 * برنامه‌ی مطالعه: GET /study-plan; without one, the form that POSTs a new
 * plan. Today's card opens each item where it is studied (a topic or subject
 * as a bank study set, the review box, the past papers), and every day has a
 * "done" tick. Every API string goes in through textContent.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { fullDay, itemLabel, planDay, weeks } from './plan-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const root = document.getElementById('plan');
const form = document.getElementById('plan-form');

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    document.getElementById('plan-error-text').textContent = describeError(error);
    document.getElementById('plan-error').hidden = false;
}

async function open(button, path, body) {
    button.disabled = true;
    try {
        const created = await api.post(path, body);
        window.location.assign(`/app/exams/${encodeURIComponent(created.assessment_id)}`);
    } catch (error) {
        button.disabled = false;
        fail(error);
    }
}

function action(item) {
    const make = (text, onClick) => {
        const b = el('button', 'f-btn f-btn--ghost b-start', text);
        b.type = 'button';
        b.addEventListener('click', () => onClick(b));
        return b;
    };
    const link = (text, href) => {
        const a = el('a', 'f-btn f-btn--ghost b-start', text);
        a.href = href;
        return a;
    };
    switch (item.kind) {
        case 'topic': return make('شروع تست‌ها', (b) => open(b, `${base}/bank/study`, { subject: item.subject_key, topic: item.topic_key }));
        case 'subject': return make('شروع تست‌ها', (b) => open(b, `${base}/bank/study`, { subject: item.subject_key }));
        case 'reading': return link('منابع این درس', `/app/bank/${encodeURIComponent(item.subject_key)}`);
        case 'review': return make('شروع مرور', (b) => open(b, `${base}/review/start`, {}));
        case 'mock': return link('انتخاب آزمون', '/app/bank');
        default: return null;
    }
}

function dayCard(day, plan, { big = false } = {}) {
    const card = el('article', `f-card p-day${day.done ? ' is-done' : ''}${big ? ' p-day--today' : ''}`);
    const head = el('header', 'p-day__head');
    head.append(el('h3', '', `${big ? 'امروز · ' : ''}روز ${faDigits(day.day_no)} · ${planDay(day.date)}`));
    const tick = el('label', 'p-day__tick');
    const box = el('input');
    box.type = 'checkbox';
    box.checked = day.done;
    box.addEventListener('change', async () => {
        box.disabled = true;
        try {
            await api.post(`${base}/study-plan/days/${day.day_no}`, { done: box.checked });
            day.done = box.checked;
            card.classList.toggle('is-done', day.done);
            plan.done += day.done ? 1 : -1;
            drawSummary(plan);
        } catch (error) {
            box.checked = !box.checked;
            fail(error);
        } finally {
            box.disabled = false;
        }
    });
    tick.append(box, el('span', '', 'مطالعه کردم'));
    head.append(tick);
    card.append(head);
    const list = el('ul', 'p-day__items');
    for (const item of day.items) {
        const li = el('li', 'p-item');
        const text = el('span', 'p-item__text', itemLabel(item));
        li.append(text);
        if (item.questions) li.append(el('span', 'f-tiny', `${faDigits(item.questions)} تست`));
        const act = action(item);
        if (act) li.append(act);
        list.append(li);
    }
    card.append(list);
    return card;
}

function drawSummary(plan) {
    const summary = document.getElementById('plan-summary');
    if (!summary) return;
    const percent = plan.total === 0 ? 0 : Math.round((plan.done * 100) / plan.total);
    summary.replaceChildren(
        el('span', '', `آزمون: ${fullDay(plan.exam_date)}`),
        el('span', '', `${faDigits(plan.done)} از ${faDigits(plan.total)} روز (٪${faDigits(percent)})`),
        plan.missed > 0 ? el('span', 'b-old', `${faDigits(plan.missed)} روز عقب`) : el('span', '', 'طبق برنامه'),
    );
    const bar = document.getElementById('plan-bar');
    if (bar) bar.style.setProperty('--p', `${percent}%`);
}

function drawPlan(plan) {
    form.hidden = true;
    const head = el('section', 'f-card b-panel');
    const summary = el('p', 'b-row__meta p-summary');
    summary.id = 'plan-summary';
    const bar = el('div', 'p-bar');
    bar.id = 'plan-bar';
    head.append(summary, bar);
    const parts = [head];

    const today = plan.days.find((d) => d.day_no === plan.today_day_no);
    if (today) {
        parts.push(dayCard(today, plan, { big: true }));
    } else {
        const box = el('section', 'f-card b-empty');
        box.append(el('h2', '', 'برنامه تمام شد'), el('p', 'f-muted', 'موفق باشی! اگر آزمون جابه‌جا شد، برنامه‌ی تازه بساز.'));
        parts.push(box);
    }

    const all = el('section', 'b-panel');
    all.append(el('h2', '', 'همه‌ی روزها'));
    weeks(plan.days).forEach((week, index) => {
        const details = el('details', 'p-week');
        if (week.some((d) => d.day_no === plan.today_day_no)) details.open = true;
        const doneCount = week.filter((d) => d.done).length;
        details.append(el('summary', '', `هفته‌ی ${faDigits(index + 1)} · ${planDay(week[0].date)} · ${faDigits(doneCount)} از ${faDigits(week.length)}`));
        for (const day of week) details.append(dayCard(day, plan));
        all.append(details);
    });
    parts.push(all);

    const reset = el('button', 'f-btn f-btn--ghost', 'برنامه‌ی تازه (این یکی کنار می‌رود)');
    reset.type = 'button';
    reset.addEventListener('click', async () => {
        reset.disabled = true;
        try {
            await api.post(`${base}/study-plan/archive`, {});
            showForm();
        } catch (error) {
            reset.disabled = false;
            fail(error);
        }
    });
    parts.push(reset);
    root.replaceChildren(...parts);
    drawSummary(plan);
}

function showForm() {
    root.replaceChildren();
    form.hidden = false;
}

async function load() {
    try {
        const plan = await api.get(`${base}/study-plan`);
        if (plan) drawPlan(plan); else showForm();
    } catch (error) {
        root.replaceChildren();
        fail(error);
    } finally {
        root.setAttribute('aria-busy', 'false');
    }
}

const dateInput = document.getElementById('plan-date');
dateInput.addEventListener('input', () => {
    document.getElementById('plan-date-fa').textContent = dateInput.value ? `یعنی ${fullDay(dateInput.value)}` : '';
});
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!dateInput.value) {
        document.getElementById('plan-date-fa').textContent = 'تاریخ آزمون را انتخاب کن.';
        return;
    }
    const make = document.getElementById('plan-make');
    make.disabled = true;
    make.textContent = 'در حال ساخت…';
    try {
        const plan = await api.post(`${base}/study-plan`, { exam_date: dateInput.value, days_per_week: Number(document.getElementById('plan-days').value) });
        drawPlan(plan);
    } catch (error) {
        fail(error);
    } finally {
        make.disabled = false;
        make.textContent = 'ساخت برنامه';
    }
});

if (workspaceId && root) load();
