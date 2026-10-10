/*
 * بانک سؤال: the overview (by subject / by year, with search), one subject
 * (its topics most-asked first, its high-yield concepts, its references),
 * and the exam references by year. Which one is decided by data-page on
 * #bank. Every API string goes in through textContent.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { chapterCoverage, droppedReferences, groupSittings, matches, previousYear, referenceChange, scopeText, share, sittingLabel, specialtiesByType, specialtyFirst, yearSpan } from './bank-rules.js';
import { goalPicker, readGoal } from './exam-goal.js';
import { CARD_HUES, paperGroup } from './bank-papers.js';

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

/*
 * The bank's front page: «آزمون من» narrows it to one exam (and, for a
 * specialty exam, puts the specialty first), and three views -- by book and
 * chapter (the default: the official references, chapter by chapter, with
 * how many questions each gave), by subject and topic, and by exam paper,
 * grouped by exam and year with a specialty exam's ten papers together.
 */
async function overview() {
    const search = document.getElementById('bank-search');
    const tabs = [...document.querySelectorAll('[data-view]')];
    const slot = document.getElementById('goal-slot');
    let view = 'books';
    let all;
    let data;
    let goal;
    const books = new Map(); // exam type ('' for every exam) -> GET /bank/books
    try {
        all = await api.get(`${base}/bank`);
        goal = readGoal(all.types.map((t) => t.key));
        data = goal.type ? await api.get(`${base}/bank?type=${encodeURIComponent(goal.type)}`) : all;
    } catch (error) {
        done();
        fail(error);
        return;
    }
    const typeQuery = () => (goal.type ? `?type=${encodeURIComponent(goal.type)}` : '');

    slot.replaceChildren(goalPicker({
        types: all.types,
        specialties: specialtiesByType(all.sittings),
        goal,
        onChange: async (next) => {
            goal = next;
            root.setAttribute('aria-busy', 'true');
            try {
                data = goal.type ? await api.get(`${base}/bank${typeQuery()}`) : all;
                errorBox.hidden = true;
                draw();
            } catch (error) {
                done();
                fail(error);
            }
        },
    }));

    const draw = async () => {
        const q = search.value;
        const typeName = all.types.find((t) => t.key === goal.type)?.name;
        if (view === 'books') {
            if (!books.has(goal.type)) {
                root.setAttribute('aria-busy', 'true');
                try {
                    books.set(goal.type, await api.get(`${base}/bank/books${typeQuery()}`));
                } catch (error) {
                    done();
                    fail(error);
                    return;
                }
            }
            if (view !== 'books') return; // the reader moved on while it loaded
            done(...bookView(books.get(goal.type), q, goal));
            return;
        }
        if (view === 'subjects') {
            if (goal.type && data.total === 0) {
                const box = empty(`سؤال‌های ${typeName} هنوز وارد بانک نشده`, 'منابع اعلام‌شده‌اش آماده است؛ یا «آزمون من» را روی همه‌ی آزمون‌ها بگذار.');
                const link = el('a', 'f-btn f-btn--ghost', `منابع ${typeName}`);
                link.href = `/app/references?type=${encodeURIComponent(goal.type)}`;
                box.append(link);
                done(box);
                return;
            }
            const list = el('div', 'b-cards');
            // A specialty exam's subjects are its specialties: show only those with questions, the goal's first.
            const rows = specialtyFirst(data.subjects.filter((s) => matches(q, s.name, s.name_en) && (!goal.type || s.total > 0)), goal.specialty);
            rows.forEach((subject, index) => {
                const live = subject.total > 0;
                const card = el(live ? 'a' : 'div', `b-card${live ? '' : ' is-empty'}${subject.key === goal.specialty ? ' is-goal' : ''}`);
                if (live) card.href = `/app/bank/${encodeURIComponent(subject.key)}${typeQuery()}`;
                // Each course its own colour, cycling, so the grid is told apart at a glance.
                card.dataset.hue = CARD_HUES[index % CARD_HUES.length];
                card.append(el('span', 'b-card__mark', String(subject.name || '؟').trim().charAt(0)));
                card.append(el('strong', 'b-card__title', subject.name));
                if (live) {
                    card.append(el('span', 'b-card__count', `${faDigits(subject.total)} سؤال`));
                    const meta = el('div', 'b-card__meta');
                    const span = yearSpan(subject.first_year, subject.last_year, faDigits);
                    if (span) meta.append(el('span', '', span));
                    if (subject.topics > 0) meta.append(el('span', '', `${faDigits(subject.topics)} مبحث`));
                    if (subject.per_exam) meta.append(el('span', '', `~${faDigits(subject.per_exam)} در هر آزمون`));
                    if (subject.old_reference > 0) meta.append(el('span', 'b-old', `${faDigits(subject.old_reference)} رفرنس قدیم`));
                    if (meta.firstChild) card.append(meta);
                } else {
                    card.append(el('span', 'b-card__count', 'به‌زودی'));
                }
                list.append(card);
            });
            if (!list.firstChild) list.append(el('p', 'f-muted b-pad', 'درسی با این نام نیست.'));
            done(list);
            return;
        }
        const groups = groupSittings(all.sittings, goal)
            .map((group) => ({ ...group, sittings: group.sittings.filter((s) => matches(q, s.type, s.year, s.subject_name, sittingLabel(s, faDigits))) }))
            .filter((group) => group.sittings.length > 0);
        if (groups.length === 0) {
            done(empty('آزمونی پیدا نشد', goal.type ? 'برای این آزمون هنوز سؤالی منتشر نشده، یا جست‌وجو چیزی پیدا نکرد.' : 'سؤال‌های هر سال که وارد بانک شود، این‌جا فهرست می‌شود.'));
            return;
        }
        done(...groups.map(paperGroup));
    };

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

/*
 * بانک به تفکیک کتاب و فصل: per subject, the books its questions come from
 * (the latest official list's first), each chapter with its count and a
 * study button; chapters no question came from wait behind a button.
 */
function bookView(data, q, goal) {
    const subjects = specialtyFirst(data.subjects, goal.specialty)
        .map((subject) => ({
            ...subject,
            books: matches(q, subject.name, subject.name_en) ? subject.books : subject.books.filter((book) => matches(q, book.title, book.authors)
                || book.chapters.some((chapter) => matches(q, chapter.title, chapter.title_fa))),
        }))
        .filter((subject) => subject.books.length > 0);
    if (subjects.length === 0) {
        return [empty(q ? 'چیزی پیدا نشد' : 'هنوز سؤالی به فصل کتاب وصل نشده', q ? 'درس، کتاب یا فصل دیگری را جست‌وجو کن.' : 'فصل‌بندی سؤال‌ها که پیش برود، این‌جا کتاب به کتاب دیده می‌شود.')];
    }
    const note = el('p', 'f-muted b-note', data.listed_year
        ? `کتاب‌های فهرست رسمی ${faDigits(data.listed_year)} اول آمده‌اند؛ عدد هر فصل، سؤال‌هایی است که جایشان در آن فصل پیدا شده.`
        : 'عدد هر فصل، سؤال‌هایی است که جایشان در آن فصل پیدا شده.');
    return [note, ...subjects.map((subject) => {
        const section = el('section', 'k-subject');
        const head = el('header', 'k-subject__head');
        const link = el('a', 'r-subject__link', 'مبحث‌ها');
        link.href = `/app/bank/${encodeURIComponent(subject.key)}${goal.type ? `?type=${encodeURIComponent(goal.type)}` : ''}`;
        head.append(el('h2', 'k-subject__name', subject.name), link);
        const list = el('div', 'r-books');
        for (const book of subject.books) list.append(bookChapters(book, subject.key, goal.type));
        section.append(head, list);
        return section;
    })];
}

/* How many of a book's chapters show before «همه‌ی فصل‌ها». */
const TOP_CHAPTERS = 5;

function bookChapters(book, subjectKey, type) {
    const card = el('article', `r-book r-book--${spineOf(book.edition_ref)}`);
    const spine = el('span', 'r-book__spine');
    spine.setAttribute('aria-hidden', 'true');
    const body = el('div', 'r-book__body');
    const title = el('h3', 'r-book__title', book.title);
    title.dir = 'auto';
    body.append(title);
    const meta = el('div', 'r-book__meta');
    const edition = el('span', 'r-tag r-tag--edition', book.edition);
    edition.dir = 'auto';
    meta.append(edition);
    if (book.published_year) meta.append(el('span', 'r-tag', `چاپ ${faDigits(book.published_year)}`));
    if (book.listed_year) meta.append(badge('new', `در فهرست ${faDigits(book.listed_year)}`));
    meta.append(el('span', 'r-tag', `${faDigits(book.questions)} سؤال`));
    body.append(meta);
    const scope = (extra) => ({ subject: subjectKey, edition: book.edition_ref, ...(type ? { type } : {}), ...extra });
    body.append(studyButton('تمرین همه‌ی سؤال‌های این کتاب', scope({})));

    // The most-asked chapters first; the whole book, in its own order, one tap away.
    const asked = book.chapters.filter((chapter) => chapter.questions > 0);
    const most = Math.max(1, ...asked.map((chapter) => chapter.questions));
    const top = [...asked].sort((a, b) => b.questions - a.questions).slice(0, TOP_CHAPTERS);
    const line = (chapter) => chapterLine(chapter, most, chapter.questions > 0 ? scope({ chapter: chapter.key }) : null);
    const short = el('ol', 'r-chapters__list k-chapters');
    short.append(...top.map(line));
    const whole = el('ol', 'r-chapters__list k-chapters');
    whole.hidden = true;
    whole.append(...book.chapters.map(line));
    if (top.length > 0) {
        body.append(el('p', 'k-top', top.length < asked.length ? `پرسؤال‌ترین فصل‌ها` : 'فصل‌هایی که سؤال داشته‌اند'), short);
    }
    if (book.chapters.length > top.length) {
        const label = `همه‌ی ${faDigits(book.chapters.length)} فصل کتاب، به ترتیب`;
        const more = el('button', 'r-chapters__more', label);
        more.type = 'button';
        more.setAttribute('aria-expanded', 'false');
        more.addEventListener('click', () => {
            whole.hidden = !whole.hidden;
            short.hidden = !whole.hidden;
            more.setAttribute('aria-expanded', String(!whole.hidden));
            more.textContent = whole.hidden ? label : 'فقط پرسؤال‌ترین فصل‌ها';
        });
        body.append(more, whole);
    }
    card.append(spine, body);
    return card;
}

/* One chapter: its number, Persian and English titles, a bar of its share of the book's questions, and a study button. */
function chapterLine(chapter, most, studyBody) {
    const item = el('li', studyBody ? 'r-ch k-ch' : 'r-ch k-ch is-out');
    item.append(el('span', 'r-ch__number', chapter.number ? faDigits(chapter.number) : '–'));
    const text = el('div', 'r-ch__text');
    text.append(...bilingual(chapter, 'r-ch'));
    if (studyBody) {
        const bar = el('span', 'k-ch__bar');
        bar.setAttribute('aria-hidden', 'true');
        const fill = el('span', 'k-ch__fill');
        fill.style.inlineSize = `${share(chapter.questions, most)}%`;
        bar.append(fill);
        text.append(bar);
    }
    item.append(text);
    if (studyBody || chapter.visuals > 0) {
        const side = el('div', 'k-ch__side');
        if (studyBody) side.append(el('span', 'k-ch__count', `${faDigits(chapter.questions)} سؤال`));
        // A chapter's mind maps and summaries, when there are any.
        if (chapter.visuals > 0 && studyBody) {
            const maps = el('a', 'k-ch__maps', `نقشه · ${faDigits(chapter.visuals)}`);
            maps.href = `/app/visuals?${new URLSearchParams({ edition: studyBody.edition, chapter: chapter.key })}`;
            side.append(maps);
        }
        if (studyBody) side.append(studyButton('تمرین', studyBody));
        item.append(side);
    }
    return item;
}

/* ------------------------------------------------------------- subject */

/* The exam type a subject page is narrowed to (?type=, set by the bank's «آزمون من»), or ''. */
const subjectType = new URLSearchParams(window.location.search).get('type') ?? '';

/** A study request for this subject page, narrowed to its exam type. */
function slice(body) {
    return subjectType ? { ...body, type: subjectType } : body;
}

function topicTable(rows, subject, total, recentFrom, withTopic) {
    const table = el('div', 'b-sheet b-topics');
    for (const row of rows) {
        const line = el('div', 'b-topic');
        line.style.setProperty('--share', `${share(row.total, total)}%`);
        const body = el('div', 'b-row__body');
        body.append(el('strong', 'b-row__title', row.name ?? 'هنوز مبحث‌بندی نشده'));
        if (row.name_en) body.append(el('span', 'b-row__en', row.name_en));
        const meta = el('div', 'b-row__meta');
        meta.append(el('span', '', `${faDigits(row.total)} سؤال`));
        if (recentFrom !== null) meta.append(el('span', '', `${faDigits(row.recent)} از ${faDigits(recentFrom)} به بعد`));
        if (withTopic && row.topic) meta.append(el('span', '', row.topic));
        if (row.old_reference > 0) meta.append(el('span', 'b-old', `${faDigits(row.old_reference)} رفرنس قدیم`));
        body.append(meta);
        line.append(body);
        if (row.key !== null) line.append(studyButton('شروع', slice({ subject, topic: row.key })));
        table.append(line);
    }
    return table;
}

async function subject() {
    const key = root.dataset.subject;
    let data;
    try {
        data = await api.get(`${base}/bank/subjects/${encodeURIComponent(key)}${subjectType ? `?type=${encodeURIComponent(subjectType)}` : ''}`);
    } catch (error) {
        done();
        fail(error);
        return;
    }
    document.title = `${data.subject.name} | بانک سؤال | فانوس`;

    const head = el('header', 'b-head');
    head.append(el('h1', '', `بانک سؤال ${data.subject.name}`));
    if (data.type) {
        // Narrowed by «آزمون من»; one tap shows every exam's questions.
        const scope = el('p', 'b-scope');
        const every = el('a', '', 'همه‌ی آزمون‌ها');
        every.href = `/app/bank/${encodeURIComponent(key)}`;
        scope.append(el('span', '', `فقط سؤال‌های ${data.type_name} · `), every);
        head.append(scope);
    }
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
        actions.append(studyButton('شروع مطالعه‌ی جامع', slice({ subject: key }), 'primary'));
        if (data.recent_from !== null && data.first_year < data.recent_from) {
            actions.append(studyButton(`فقط از ${faDigits(data.recent_from)} به بعد`, slice({ subject: key, recent: true })));
        }
        modes.append(actions);
        if (data.years.length > 1) {
            const years = el('div', 'b-years');
            for (const year of data.years) years.append(studyButton(`${faDigits(year.year)} (${faDigits(year.total)} سؤال)`, slice({ subject: key, year: year.year })));
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
            const list = el('div', 'r-books');
            for (const ref of year.references) list.append(bookCard(ref));
            refs.append(list);
        }
        const all = el('a', 'c-link', 'همه‌ی منابع، سال به سال ←');
        all.href = '/app/references';
        refs.append(all);
        parts.push(refs);
    }
    done(...parts);
}

/*
 * One book, as منابع آزمون and a subject's page show it: a spine in the
 * book's own colour, the title and authors as the publisher prints them, the
 * edition, what changed since the year before, the year's announced scope,
 * how much of the book that scope covers, and the chapters on demand.
 */
const SPINES = ['accent', 'info', 'subject', 'tag', 'source', 'difficulty'];

function spineOf(editionRef) {
    let hash = 0;
    for (const char of String(editionRef ?? '').split('@')[0]) hash = (hash * 31 + char.codePointAt(0)) >>> 0;
    return SPINES[hash % SPINES.length];
}

const CHANGES = {
    new: ['new', 'تازه در این سال'],
    edition: ['edition', 'ویرایش جدید'],
    scope: ['scope', 'محدوده تغییر کرد'],
};

/* The journals and articles a specialty list names are one entry, not a book. */
function isArticles(ref) {
    return String(ref.edition_ref ?? '').startsWith('announced-articles-');
}

function badge(kind, text) {
    return el('span', `r-badge r-badge--${kind}`, text);
}

function bookCard(ref, change = null) {
    const card = el('article', `r-book r-book--${isArticles(ref) ? 'articles' : spineOf(ref.edition_ref)}`);
    const spine = el('span', 'r-book__spine');
    spine.setAttribute('aria-hidden', 'true');
    const body = el('div', 'r-book__body');
    const title = el('h3', 'r-book__title', ref.title);
    title.dir = 'auto';
    body.append(title);
    if (ref.authors) {
        const authors = el('p', 'r-book__authors', ref.authors);
        authors.dir = 'auto';
        body.append(authors);
    }
    const meta = el('div', 'r-book__meta');
    const edition = el('span', 'r-tag r-tag--edition', ref.edition);
    edition.dir = 'auto';
    meta.append(edition);
    if (ref.published_year) meta.append(el('span', 'r-tag', `چاپ ${faDigits(ref.published_year)}`));
    if (change && CHANGES[change]) meta.append(badge(...CHANGES[change]));
    if (ref.official === false) meta.append(badge('unofficial', 'اعلام غیررسمی'));
    body.append(meta);
    if (ref.scope) {
        const scope = el('div', 'r-scope');
        scope.append(el('span', 'r-scope__label', 'محدوده‌ی اعلام‌شده'), el('p', 'r-scope__text', scopeText(ref.scope, faDigits)));
        body.append(scope);
    }
    const chapters = Array.isArray(ref.chapters) ? ref.chapters : [];
    if (chapters.length > 0) body.append(coverage(chapters), chapterPanel(chapters, ref.edition_ref));
    card.append(spine, body);
    return card;
}

/* "۲۴ فصل از ۲۸ فصل کتاب" with a bar, or the chapter count when the year names no chapters. */
function coverage(chapters) {
    const { total, inScope } = chapterCoverage(chapters);
    const wrap = el('div', 'r-meter');
    if (inScope === null) {
        wrap.append(el('span', 'r-meter__label', `${faDigits(total)} فصل`));
        return wrap;
    }
    const bar = el('span', 'r-meter__bar');
    bar.setAttribute('aria-hidden', 'true');
    const fill = el('span', 'r-meter__fill');
    fill.style.inlineSize = `${share(inScope, total)}%`;
    bar.append(fill);
    wrap.append(bar, el('span', 'r-meter__label', `${faDigits(inScope)} فصل از ${faDigits(total)} فصل کتاب`));
    return wrap;
}

/*
 * The chapters, Persian title over the publisher's English one. When the
 * year names its chapters, the ones outside it wait behind their own button,
 * and the ones it limits to some pages say so.
 */
function chapterPanel(chapters, editionRef) {
    const { inScope } = chapterCoverage(chapters);
    const wrap = el('div', 'r-chapters');
    const toggle = el('button', 'r-chapters__toggle');
    toggle.type = 'button';
    toggle.setAttribute('aria-expanded', 'false');
    const chevron = el('span', 'r-chevron');
    chevron.setAttribute('aria-hidden', 'true');
    toggle.append(el('span', '', 'فهرست فصل‌ها'), chevron);
    const panel = el('div', 'r-chapters__panel');
    panel.hidden = true;
    const inside = el('ol', 'r-chapters__list');
    const outside = el('ol', 'r-chapters__list r-chapters__list--out');
    outside.hidden = true;
    for (const chapter of chapters) {
        const out = inScope !== null && chapter.in_scope === false;
        (out ? outside : inside).append(chapterRow(chapter, editionRef, out));
    }
    panel.append(inside);
    if (outside.children.length > 0) {
        const label = `${faDigits(outside.children.length)} فصل خارج از محدوده`;
        const more = el('button', 'r-chapters__more', `نمایش ${label}`);
        more.type = 'button';
        more.setAttribute('aria-expanded', 'false');
        more.addEventListener('click', () => {
            outside.hidden = !outside.hidden;
            more.setAttribute('aria-expanded', String(!outside.hidden));
            more.textContent = outside.hidden ? `نمایش ${label}` : `پنهان کردن ${label}`;
        });
        panel.append(more, outside);
    }
    if (chapters.some((chapter) => chapter.title_fa && !chapter.title_fa_reviewed)) {
        panel.append(el('p', 'r-chapters__note', 'عنوان‌های فارسی را هوش مصنوعی ترجمه و یکدست کرده و هنوز متخصص بازبینی‌شان نکرده است؛ عنوان انگلیسی همان متن کتاب است.'));
    }
    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
        wrap.classList.toggle('is-open', !panel.hidden);
    });
    wrap.append(toggle, panel);
    return wrap;
}

function chapterRow(chapter, editionRef, out) {
    const item = el('li', out ? 'r-ch is-out' : 'r-ch');
    item.append(el('span', 'r-ch__number', chapter.number ? faDigits(chapter.number) : '–'));
    const text = el('div', 'r-ch__text');
    text.append(...bilingual(chapter, 'r-ch'));
    if (chapter.partial) text.append(el('span', 'r-ch__partial', `بخشی از فصل: ${faDigits(chapter.partial)}`));
    if (chapter.sections > 0 && editionRef) text.append(outlineToggle(editionRef, chapter));
    // «سؤال‌های این فصل»: every exam's questions found in this chapter.
    if (chapter.questions > 0 && editionRef) text.append(studyButton(`سؤال‌های این فصل · ${faDigits(chapter.questions)}`, { edition: editionRef, chapter: chapter.key }));
    item.append(text);
    return item;
}

/* A title as the page shows it: Persian over the English it translates (a Persian book's title alone). */
function bilingual(node, prefix) {
    const parts = [];
    if (node.title_fa) parts.push(el('span', `${prefix}__fa`, node.title_fa));
    if (node.title !== node.title_fa) {
        const english = el('span', `${prefix}__en`, node.title);
        english.lang = 'en';
        english.dir = 'ltr';
        parts.push(english);
    }
    return parts;
}

/* سرفصل‌ها: the chapter's headings, fetched the first time the reader opens them. */
function outlineToggle(editionRef, chapter) {
    const wrap = el('div', 'b-outline');
    const button = el('button', 'b-outline__toggle', `سرفصل‌ها · ${faDigits(chapter.sections)}`);
    button.type = 'button';
    button.setAttribute('aria-expanded', 'false');
    const body = el('div', 'b-outline__body');
    body.hidden = true;
    let loaded = false;
    button.addEventListener('click', async () => {
        const open = body.hidden;
        body.hidden = !open;
        button.setAttribute('aria-expanded', String(open));
        if (!open || loaded) return;
        loaded = true;
        body.replaceChildren(el('span', 'f-muted', 'در حال بارگذاری…'));
        try {
            const query = new URLSearchParams({ edition: editionRef, chapter: chapter.key });
            const outline = await api.get(`${base}/bank/chapter-outline?${query}`);
            const list = el('ul', 'b-outline__list');
            for (const section of outline.sections) {
                const item = el('li', 'b-outline__section');
                item.append(...bilingual(section, 'b-outline'));
                if (section.subsections.length > 0) {
                    const sub = el('ul', 'b-outline__sublist');
                    for (const subsection of section.subsections) {
                        const subItem = el('li', 'b-outline__subsection');
                        subItem.append(...bilingual(subsection, 'b-outline'));
                        sub.append(subItem);
                    }
                    item.append(sub);
                }
                list.append(item);
            }
            body.replaceChildren(list);
        } catch (error) {
            loaded = false;
            body.replaceChildren(el('span', 'f-muted', describeError(error)));
        }
    });
    wrap.append(button, body);
    return wrap;
}

/* ---------------------------------------------------------- references */

/*
 * منابع آزمون: one exam type (دستیاری، بورد، ارتقا، آزمون ملی) and one year at
 * a time, chosen on a switch and a strip of years (kept in the address as
 * ?type=&year=), a summary of the year against the one before it, and one
 * card per subject. Search and "only what changed" narrow the cards.
 */
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
    const typeSwitch = document.getElementById('ref-types');
    const strip = document.getElementById('ref-years');
    const summary = document.getElementById('ref-summary');
    const search = document.getElementById('ref-search');
    const changesOnly = document.getElementById('ref-changes');
    const params = new URLSearchParams(window.location.search);
    const types = [...new Map(years.map((year) => [year.type_key, year.type])).entries()];
    const goal = readGoal(types.map(([key]) => key));
    let type = [params.get('type'), goal.type].find((key) => types.some(([known]) => known === key)) ?? types[0][0];
    const asked = Number(params.get('year'));
    let index = years.findIndex((year) => year.type_key === type && year.year === asked);
    if (index < 0) index = years.findIndex((year) => year.type_key === type);
    let tabs = [];

    const remember = () => {
        const url = new URL(window.location.href);
        url.searchParams.set('type', type);
        url.searchParams.set('year', String(years[index].year));
        window.history.replaceState(null, '', url);
    };

    // The exam types, when there is more than one; each keeps its own strip of years.
    const typeButtons = types.map(([key, name]) => {
        const button = el('button', 'r-type', name);
        button.type = 'button';
        button.setAttribute('aria-pressed', String(key === type));
        button.addEventListener('click', () => {
            if (key === type) return;
            type = key;
            index = years.findIndex((year) => year.type_key === type);
            typeButtons.forEach((other, i) => other.setAttribute('aria-pressed', String(types[i][0] === type)));
            remember();
            buildStrip();
            draw();
        });
        return button;
    });
    if (types.length > 1) {
        typeSwitch.append(...typeButtons);
        typeSwitch.hidden = false;
    }

    function buildStrip() {
        strip.replaceChildren();
        tabs = [];
        years.forEach((year, i) => {
            if (year.type_key !== type) return;
            const tab = el('button', 'r-year');
            tab.type = 'button';
            tab.setAttribute('role', 'tab');
            tab.setAttribute('aria-label', `${year.type} ${faDigits(year.year)}`);
            tab.dataset.index = String(i);
            tab.append(el('span', 'r-year__number', faDigits(year.year)));
            tab.addEventListener('click', () => {
                index = i;
                remember();
                draw();
                tab.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
            });
            strip.append(tab);
            tabs.push(tab);
        });
    }
    buildStrip();
    search.addEventListener('input', () => draw());
    changesOnly.addEventListener('click', () => {
        changesOnly.setAttribute('aria-pressed', String(changesOnly.getAttribute('aria-pressed') !== 'true'));
        draw();
    });
    document.getElementById('ref-tools').hidden = false;

    function draw() {
        tabs.forEach((tab) => {
            const on = Number(tab.dataset.index) === index;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', String(on));
        });
        const year = years[index];
        const before = previousYear(years, index);
        const earlier = new Map((before?.subjects ?? []).map((subject) => [subject.key, subject]));
        const subjects = specialtyFirst(year.subjects, goal.type === type ? goal.specialty : '').map((subject) => {
            const previous = before ? (earlier.get(subject.key) ?? { references: [] }) : null;
            // A list's journals and articles come after its books.
            const books = subject.references.map((ref) => ({ ref, change: referenceChange(ref, previous) }))
                .sort((a, b) => isArticles(a.ref) - isArticles(b.ref));
            return { subject, books, dropped: droppedReferences(subject, previous) };
        });

        const books = subjects.reduce((sum, row) => sum + row.books.length, 0);
        const changes = subjects.reduce((sum, row) => sum + row.books.filter((book) => book.change).length + row.dropped.length, 0);
        const scoped = subjects.flatMap((row) => row.books.map((book) => chapterCoverage(book.ref.chapters).inScope)).filter((n) => n !== null);
        summary.replaceChildren();
        const stat = (value, label, tone) => {
            const tile = el('div', `r-stat r-stat--${tone}`);
            tile.append(el('dt', 'r-stat__label', label), el('dd', 'r-stat__value', value));
            summary.append(tile);
        };
        stat(faDigits(subjects.length), 'درس', 'accent');
        stat(faDigits(books), 'کتاب', 'info');
        if (scoped.length > 0) stat(faDigits(scoped.reduce((sum, n) => sum + n, 0)), 'فصل اعلام‌شده', 'subject');
        stat(before ? faDigits(changes) : '—', before ? `تغییر نسبت به ${faDigits(before.year)}` : 'نخستین سال ثبت‌شده', 'difficulty');
        changesOnly.disabled = before === null;

        const query = search.value;
        const onlyChanged = changesOnly.getAttribute('aria-pressed') === 'true' && before !== null;
        const cards = [];
        for (const row of subjects) {
            if (onlyChanged && row.dropped.length === 0 && !row.books.some((book) => book.change)) continue;
            const whole = matches(query, row.subject.name, row.subject.key);
            const shown = whole ? row.books : row.books.filter((book) => matches(query, book.ref.title, book.ref.authors, book.ref.edition));
            if (shown.length === 0) continue;
            cards.push(subjectCard(row, shown, before));
        }
        if (cards.length === 0) {
            done(empty('چیزی پیدا نشد', onlyChanged ? 'در این سال، با این جست‌وجو، تغییری نسبت به سال قبل نیست.' : 'درس یا کتاب دیگری را جست‌وجو کن.'));
            return;
        }
        const grid = el('div', 'r-grid');
        grid.append(...cards);
        done(grid);
    }

    function subjectCard(row, shown, before) {
        const card = el('section', 'r-subject');
        const head = el('header', 'r-subject__head');
        const link = el('a', 'r-subject__link', 'سؤال‌ها');
        link.href = `/app/bank/${encodeURIComponent(row.subject.key)}`;
        link.setAttribute('aria-label', `سؤال‌های ${row.subject.name}`);
        head.append(el('h2', 'r-subject__name', row.subject.name), el('span', 'r-subject__count', `${faDigits(row.books.length)} کتاب`), link);
        const list = el('div', 'r-books');
        for (const book of shown) list.append(bookCard(book.ref, book.change));
        card.append(head, list);
        if (row.dropped.length > 0) {
            const dropped = el('p', 'r-dropped');
            dropped.append(el('span', 'r-dropped__label', `کنار رفته از فهرست ${faDigits(before.year)}`));
            for (const ref of row.dropped) {
                const title = el('span', 'r-dropped__title', `${ref.title} · ${ref.edition}`);
                title.dir = 'auto';
                dropped.append(title);
            }
            card.append(dropped);
        }
        return card;
    }

    draw();
}

if (workspaceId && root) {
    ({ overview, subject, references })[root.dataset.page]?.();
}
