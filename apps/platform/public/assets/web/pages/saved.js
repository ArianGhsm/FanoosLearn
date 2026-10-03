/*
 * ذخیره‌ها و یادداشت‌ها (data-page="saved") and the reviewers' report queue
 * (data-page="reports"). Every API string goes in through textContent.
 */
import { api, describeError } from '../foundation/api.js';
import { groupByTopic } from './saved-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const root = document.getElementById('saved');
const errorBox = document.getElementById('saved-error');
const dateFormat = new Intl.DateTimeFormat('fa-IR', { day: 'numeric', month: 'long' });
const LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و'];
const KIND = { question: 'متن سؤال', answer: 'پاسخ (کلید)', explanation: 'پاسخ تشریحی', other: 'سایر' };

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    document.getElementById('saved-error-text').textContent = describeError(error);
    errorBox.hidden = false;
}

function done(...children) {
    root.replaceChildren(...children);
    root.setAttribute('aria-busy', 'false');
}

function emptyCard(title, text) {
    const box = el('section', 'f-card b-empty');
    box.append(el('h2', '', title), el('p', 'f-muted', text));
    return box;
}

function item(entry, withNote) {
    const row = el('div', 's-item');
    row.append(el('p', 's-item__preview', entry.preview));
    if (withNote && entry.note) row.append(el('p', 's-item__note', entry.note));
    const meta = el('div', 'b-row__meta');
    meta.append(el('span', '', entry.assessment_title), el('span', '', dateFormat.format(new Date(entry.at))));
    row.append(meta);
    return row;
}

async function studyTopic(button, topic) {
    errorBox.hidden = true;
    button.disabled = true;
    try {
        const created = await api.post(`${base}/saved/study`, topic === null ? {} : { topic });
        window.location.assign(`/app/exams/${encodeURIComponent(created.assessment_id)}`);
    } catch (error) {
        button.disabled = false;
        fail(error);
    }
}

async function saved() {
    let data;
    try {
        data = await api.get(`${base}/saved`);
    } catch (error) {
        done();
        fail(error);
        return;
    }
    const tabs = [...document.querySelectorAll('[data-view]')];
    const draw = (view) => {
        const list = view === 'bookmarks' ? data.bookmarks : data.notes;
        if (list.length === 0) {
            done(view === 'bookmarks'
                ? emptyCard('هنوز سؤالی ذخیره نکرده‌ای', 'حین آزمون، دکمه‌ی «ذخیره» بالای هر سؤال آن را این‌جا نگه می‌دارد.')
                : emptyCard('هنوز یادداشتی ننوشته‌ای', 'در «ابزار مطالعه» زیر هر سؤال می‌توانی یادداشت بنویسی.'));
            return;
        }
        const parts = [];
        if (view === 'bookmarks') {
            const all = el('button', 'f-btn f-btn--primary s-all', `مرور همه‌ی ذخیره‌ها (${list.length.toLocaleString('fa-IR')})`);
            all.type = 'button';
            all.addEventListener('click', () => studyTopic(all, null));
            parts.push(all);
        }
        for (const group of groupByTopic(list)) {
            const section = el('section', 'b-panel');
            const head = el('div', 's-group__head');
            head.append(el('h2', '', group.topic ?? 'بدون مبحث'), el('span', 'f-muted', group.items.length.toLocaleString('fa-IR')));
            if (view === 'bookmarks' && group.topic !== null) {
                const go = el('button', 'f-btn f-btn--ghost b-start', 'مرور این مبحث');
                go.type = 'button';
                go.addEventListener('click', () => studyTopic(go, group.topic));
                head.append(go);
            }
            const sheet = el('div', 'b-sheet');
            for (const entry of group.items) sheet.append(item(entry, view === 'notes'));
            section.append(head, sheet);
            parts.push(section);
        }
        done(...parts);
    };
    for (const tab of tabs) {
        tab.addEventListener('click', () => {
            for (const t of tabs) {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', String(t === tab));
            }
            draw(tab.dataset.view);
        });
    }
    draw('bookmarks');
}

async function reports() {
    const tabs = [...document.querySelectorAll('[data-status]')];
    const load = async (status) => {
        root.setAttribute('aria-busy', 'true');
        let list;
        try {
            list = await api.get(`${base}/question-reports?status=${encodeURIComponent(status)}`);
        } catch (error) {
            done();
            fail(error);
            return;
        }
        if (list.length === 0) {
            done(emptyCard('گزارشی نیست', status === 'open' ? 'گزارش تازه‌ای نرسیده.' : 'چیزی در این بخش نیست.'));
            return;
        }
        const cards = list.map((report) => {
            const card = el('article', 'f-card s-report');
            const meta = el('div', 'b-row__meta');
            meta.append(el('span', 'b-old', KIND[report.kind] ?? report.kind), el('span', '', report.assessment_title), el('span', '', report.question_id), el('span', '', report.reporter), el('span', '', dateFormat.format(new Date(report.created_at))));
            card.append(meta, el('p', 's-report__body', report.body));
            if (report.prompt) {
                const q = el('details', 's-report__question');
                q.append(el('summary', '', 'سؤال'), el('p', '', report.prompt));
                const ol = el('ol', 's-report__choices');
                report.choices.forEach((choice, index) => {
                    const li = el('li', index === report.answer ? 'is-answer' : '', `${LETTERS[index] ?? index + 1}) ${choice}`);
                    ol.append(li);
                });
                q.append(ol);
                card.append(q);
            }
            if (report.status === 'open') {
                const note = el('textarea', 'f-input s-report__note');
                note.rows = 2;
                note.placeholder = 'چه کردی؟ (اختیاری)';
                const actions = el('div', 'b-actions');
                for (const [status, label, tone] of [['resolved', 'اصلاح شد', 'primary'], ['rejected', 'اشکالی نبود', 'ghost']]) {
                    const button = el('button', `f-btn f-btn--${tone} b-start`, label);
                    button.type = 'button';
                    button.addEventListener('click', async () => {
                        button.disabled = true;
                        try {
                            await api.post(`${base}/question-reports/${encodeURIComponent(report.id)}`, { status, resolution: note.value });
                            card.remove();
                        } catch (error) {
                            button.disabled = false;
                            fail(error);
                        }
                    });
                    actions.append(button);
                }
                card.append(note, actions);
            } else if (report.resolution) {
                card.append(el('p', 'f-muted', report.resolution));
            }
            return card;
        });
        done(...cards);
    };
    for (const tab of tabs) {
        tab.addEventListener('click', () => {
            for (const t of tabs) {
                t.classList.toggle('is-active', t === tab);
                t.setAttribute('aria-selected', String(t === tab));
            }
            load(tab.dataset.status);
        });
    }
    load('open');
}

if (workspaceId && root) {
    ({ saved, reports })[root.dataset.page]?.();
}
