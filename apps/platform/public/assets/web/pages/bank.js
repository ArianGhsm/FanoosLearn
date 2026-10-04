/*
 * بانک سؤال: the overview (by subject / by year, with search), one subject
 * (its topics most-asked first, its high-yield concepts, its references),
 * and the exam references by year. Which one is decided by data-page on
 * #bank. Every API string goes in through textContent.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { matches, share, sittingLabel, yearSpan } from './bank-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const root = document.getElementById('bank');
const errorBox = document.getElementById('bank-error');

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    document.getElementById('bank-error-text').textContent = describeError(error);
    errorBox.hidden = false;
}

function done(...children) {
    root.replaceChildren(...children);
    root.setAttribute('aria-busy', 'false');
}

function empty(title, text) {
    const box = el('section', 'f-card b-empty');
    box.append(el('h2', '', title), el('p', 'f-muted', text));
    return box;
}

/** Opens a study set and goes straight into it. */
async function study(button, body) {
    errorBox.hidden = true;
    const label = button.textContent;
    button.disabled = true;
    button.textContent = 'در حال آماده‌سازی…';
    try {
        const created = await api.post(`${base}/bank/study`, body);
        window.location.assign(`/app/exams/${encodeURIComponent(created.assessment_id)}`);
    } catch (error) {
        button.disabled = false;
        button.textContent = label;
        fail(error);
    }
}

function studyButton(text, body, tone = 'ghost') {
    const button = el('button', `f-btn f-btn--${tone} b-start`, text);
    button.type = 'button';
    button.addEventListener('click', () => study(button, body));
    return button;
}

/* ------------------------------------------------------------ overview */

async function overview() {
    const search = document.getElementById('bank-search');
    const tabs = [...document.querySelectorAll('[data-view]')];
    let view = 'subjects';
    let data;
    try {
        data = await api.get(`${base}/bank`);
    } catch (error) {
        done();
        fail(error);
        return;
    }

    const draw = () => {
        const q = search.value;
        if (view === 'subjects') {
            const list = el('div', 'b-sheet');
            for (const subject of data.subjects.filter((s) => matches(q, s.name, s.name_en))) {
                const row = el(subject.total > 0 ? 'a' : 'div', `b-row${subject.total > 0 ? '' : ' is-empty'}`);
                if (subject.total > 0) row.href = `/app/bank/${encodeURIComponent(subject.key)}`;
                const body = el('div', 'b-row__body');
                body.append(el('strong', 'b-row__title', subject.name));
                const meta = el('div', 'b-row__meta');
                if (subject.total > 0) {
                    meta.append(el('span', '', `${faDigits(subject.total)} سؤال`));
                    if (subject.per_exam) meta.append(el('span', '', `حدود ${faDigits(subject.per_exam)} سؤال در هر آزمون`));
                    const span = yearSpan(subject.first_year, subject.last_year, faDigits);
                    if (span) meta.append(el('span', '', span));
                    if (subject.topics > 0) meta.append(el('span', '', `${faDigits(subject.topics)} مبحث`));
                    if (subject.old_reference > 0) meta.append(el('span', 'b-old', `${faDigits(subject.old_reference)} با رفرنس قدیم`));
                } else {
                    meta.append(el('span', '', 'سؤال‌هایش به‌زودی'));
                }
                body.append(meta);
                row.append(body);
                if (subject.total > 0) row.append(el('span', 'b-row__go', '←'));
                list.append(row);
            }
            if (!list.firstChild) list.append(el('p', 'f-muted b-pad', 'درسی با این نام نیست.'));
            done(list);
            return;
        }
        const sittings = data.sittings.filter((s) => matches(q, sittingLabel(s, faDigits), s.year));
        if (sittings.length === 0) {
            done(empty('هنوز آزمونی منتشر نشده', 'سؤال‌های هر سال که وارد بانک شود، این‌جا فهرست می‌شود.'));
            return;
        }
        const list = el('div', 'b-sheet');
        for (const sitting of sittings) {
            const row = el('a', 'b-row');
            row.href = `/app/exams/${encodeURIComponent(sitting.assessment_id)}`;
            const body = el('div', 'b-row__body');
            body.append(el('strong', 'b-row__title', sittingLabel(sitting, faDigits)));
            const meta = el('div', 'b-row__meta');
            meta.append(el('span', '', `${faDigits(sitting.questions)} سؤال`));
            body.append(meta);
            row.append(body, el('span', 'b-row__go', '←'));
            list.append(row);
        }
        done(list);
    };

    if (data.total === 0 && data.subjects.length > 0) {
        errorBox.hidden = true;
        root.before(Object.assign(el('p', 'f-muted b-note', 'سؤال‌های آزمون‌ها در حال ورود به بانک است؛ درس‌ها و منابع از همین حالا آماده است.')));
    }
    for (const tab of tabs) {
        tab.addEventListener('click', () => {
            view = tab.dataset.view;
            for (const t of tabs) {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', String(t === tab));
            }
            draw();
        });
    }
    search.addEventListener('input', draw);
    draw();
}

/* ------------------------------------------------------------- subject */

function topicTable(rows, subject, total, recentFrom, withTopic) {
    const table = el('div', 'b-sheet b-topics');
    for (const row of rows) {
        const line = el('div', 'b-topic');
        line.style.setProperty('--share', `${share(row.total, total)}%`);
        const body = el('div', 'b-row__body');
        body.append(el('strong', 'b-row__title', row.name ?? 'هنوز مبحث‌بندی نشده'));
        const meta = el('div', 'b-row__meta');
        meta.append(el('span', '', `${faDigits(row.total)} سؤال`));
        if (recentFrom !== null) meta.append(el('span', '', `${faDigits(row.recent)} از ${faDigits(recentFrom)} به بعد`));
        if (withTopic && row.topic) meta.append(el('span', '', row.topic));
        if (row.old_reference > 0) meta.append(el('span', 'b-old', `${faDigits(row.old_reference)} رفرنس قدیم`));
        body.append(meta);
        line.append(body);
        if (row.key !== null) line.append(studyButton('شروع', { subject, topic: row.key }));
        table.append(line);
    }
    return table;
}

async function subject() {
    const key = root.dataset.subject;
    let data;
    try {
        data = await api.get(`${base}/bank/subjects/${encodeURIComponent(key)}`);
    } catch (error) {
        done();
        fail(error);
        return;
    }
    document.title = `${data.subject.name} | بانک سؤال | فانوس`;

    const head = el('header', 'b-head');
    head.append(el('h1', '', `بانک سؤال ${data.subject.name}`));
    const facts = el('ul', 'b-facts');
    const fact = (label, value) => {
        const li = el('li');
        li.append(el('span', '', label), el('strong', '', value));
        facts.append(li);
    };
    fact('سؤال در بانک', faDigits(data.total));
    fact('در هر آزمون', data.per_exam ? `حدود ${faDigits(data.per_exam)}` : '—');
    fact('سال‌ها', yearSpan(data.first_year, data.last_year, faDigits) ?? '—');
    if (data.old_reference > 0) fact('با رفرنس قدیم', faDigits(data.old_reference));
    head.append(facts);
    const parts = [head];

    if (data.total === 0) {
        parts.push(empty('سؤال‌های این درس هنوز وارد نشده', 'منابع اعلام‌شده‌ی این درس را پایین همین صفحه می‌بینی.'));
    } else {
        const modes = el('section', 'f-card b-panel');
        modes.append(el('h2', '', 'مطالعه‌ی جامع'), el('p', 'f-muted', 'همه‌ی سؤال‌های این درس، از جدیدترین سال به قدیمی‌تر.'));
        const actions = el('div', 'b-actions');
        actions.append(studyButton('شروع مطالعه‌ی جامع', { subject: key }, 'primary'));
        if (data.recent_from !== null && data.first_year < data.recent_from) {
            actions.append(studyButton(`فقط از ${faDigits(data.recent_from)} به بعد`, { subject: key, recent: true }));
        }
        modes.append(actions);
        if (data.years.length > 1) {
            const years = el('div', 'b-years');
            for (const year of data.years) years.append(studyButton(`${faDigits(year.year)} (${faDigits(year.total)} سؤال)`, { subject: key, year: year.year }));
            modes.append(el('p', 'f-tiny', 'یا یک سال:'), years);
        }
        parts.push(modes);

        const topics = el('section', 'b-panel b-panel--flat');
        topics.append(el('h2', '', 'مطالعه‌ی مبحثی'), el('p', 'f-muted', 'مبحث‌ها از پرسؤال‌ترین به کم‌سؤال‌ترین.'));
        topics.append(topicTable(data.topics, key, data.total, data.recent_from, false));
        parts.push(topics);

        if (data.high_yield.length > 0) {
            const hy = el('section', 'b-panel b-panel--flat');
            hy.append(el('h2', '', 'شایع‌ترین ریزمبحث‌ها'), el('p', 'f-muted', 'وقت کم است؟ از این‌ها شروع کن.'));
            hy.append(topicTable(data.high_yield, key, data.total, data.recent_from, true));
            parts.push(hy);
        }
    }

    if (data.references.length > 0) {
        const refs = el('section', 'f-card b-panel');
        refs.append(el('h2', '', 'منابع این درس'));
        for (const year of data.references) {
            refs.append(el('h3', 'b-ref__year', `دستیاری ${faDigits(year.year)}`));
            const ul = el('ul', 'b-refs');
            for (const ref of year.references) ul.append(referenceItem(ref));
            refs.append(ul);
        }
        const all = el('a', 'c-link', 'همه‌ی منابع، سال به سال ←');
        all.href = '/app/references';
        refs.append(all);
        parts.push(refs);
    }
    done(...parts);
}

function referenceItem(ref) {
    const li = el('li', 'b-ref');
    li.append(el('strong', '', ref.title));
    const meta = el('span', 'f-muted', [ref.edition, ref.authors].filter(Boolean).join(' · '));
    li.append(meta);
    if (ref.scope) li.append(el('span', 'b-ref__scope', ref.scope));
    if (ref.official === false) li.append(el('span', 'b-old', 'اعلام غیررسمی'));
    if (Array.isArray(ref.chapters) && ref.chapters.length > 0) {
        const details = el('details', 'b-chapters');
        details.append(el('summary', '', `فهرست فصل‌ها · ${faDigits(ref.chapters.length)} فصل`));
        const ol = el('ol', 'b-chapters__list');
        for (const chapter of ref.chapters) {
            const item = el('li', 'b-chapter');
            item.append(el('span', 'b-chapter__number', chapter.number ? faDigits(chapter.number) : '–'));
            const title = el('span', 'b-chapter__title', chapter.title);
            title.lang = 'en';
            title.dir = 'ltr';
            item.append(title);
            ol.append(item);
        }
        details.append(ol);
        li.append(details);
    }
    return li;
}

/* ---------------------------------------------------------- references */

async function references() {
    let years;
    try {
        years = await api.get(`${base}/bank/references`);
    } catch (error) {
        done();
        fail(error);
        return;
    }
    if (!Array.isArray(years) || years.length === 0) {
        done(empty('منبعی ثبت نشده', 'منابع هر سال که وارد شود، این‌جا می‌آید.'));
        return;
    }
    const tabs = document.getElementById('ref-years');
    const draw = (index) => {
        [...tabs.children].forEach((tab, i) => {
            tab.classList.toggle('is-active', i === index);
            tab.setAttribute('aria-selected', String(i === index));
        });
        const year = years[index];
        const list = el('div', 'b-sheet');
        for (const subjectRow of year.subjects) {
            const row = el('div', 'b-refrow');
            const title = el('a', 'b-row__title', subjectRow.name);
            title.href = `/app/bank/${encodeURIComponent(subjectRow.key)}`;
            const ul = el('ul', 'b-refs');
            for (const ref of subjectRow.references) ul.append(referenceItem(ref));
            row.append(title, ul);
            list.append(row);
        }
        done(list);
    };
    years.forEach((year, index) => {
        const tab = el('button', 'x-chip', `${year.type} ${faDigits(year.year)}`);
        tab.type = 'button';
        tab.setAttribute('role', 'tab');
        tab.addEventListener('click', () => draw(index));
        tabs.append(tab);
    });
    draw(0);
}

if (workspaceId && root) {
    ({ overview, subject, references })[root.dataset.page]?.();
}
