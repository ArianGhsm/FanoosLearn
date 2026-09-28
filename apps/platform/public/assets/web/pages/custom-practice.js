/*
 * آزمون دلخواه: the builder.
 *
 * Choosing courses asks the server what they hold for this student (per
 * topic: total, never answered, last answered wrong); everything after that
 * -- which topics, which source, how many -- is counted here from that one
 * answer, so the numbers on screen always say how many questions a choice can
 * actually produce. Building sends the choices once and opens the new exam in
 * the ordinary runner.
 */
import { api, ApiError, describeError } from '../foundation/api.js';
import { availableCount, suggestedMinutes } from './custom-practice-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const form = document.getElementById('custom-form');
const coursesBox = document.getElementById('courses');
const search = document.getElementById('course-search');
const picked = document.getElementById('courses-picked');
const topicsStep = document.getElementById('topics-step');
const topicsBox = document.getElementById('topics');
const topicsClear = document.getElementById('topics-clear');
const sourceStep = document.getElementById('source-step');
const sizeStep = document.getElementById('size-step');
const range = document.getElementById('count');
const countOutput = document.getElementById('count-output');
const timed = document.getElementById('timed');
const minutes = document.getElementById('minutes');
const bar = document.getElementById('custom-bar');
const summary = document.getElementById('summary');
const submit = document.getElementById('custom-submit');
const errorBox = document.getElementById('custom-error');
const errorText = document.getElementById('custom-error-text');
const mineBox = document.getElementById('mine');

const ERRORS = {
    custom_practice_too_few: 'با این انتخاب‌ها کمتر از ۵ سؤال پیدا شد؛ درس یا مبحث بیشتری انتخاب کن.',
    custom_practice_courses_invalid: 'دست‌کم یک درس و حداکثر ۲۰ درس انتخاب کن.',
};

const faDigits = (value) => String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
const collator = new Intl.Collator('fa', { numeric: true });

const state = { courses: new Map(), selected: new Set(), topics: new Set(), options: null, request: 0 };

// "تمرین همین مبحث" on the progress page arrives as ?course=…&topic=…
const params = new URLSearchParams(window.location.search);
const preset = { course: params.get('course'), topic: params.get('topic') };

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function fail(error) {
    errorText.textContent = (error instanceof ApiError && ERRORS[error.code]) || describeError(error);
    errorBox.hidden = false;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function source() {
    return form.elements.source.value || 'all';
}

function chip(label, count, pressed, onToggle) {
    const button = el('button', `c-chip${pressed ? ' is-on' : ''}`);
    button.type = 'button';
    button.setAttribute('aria-pressed', String(pressed));
    button.append(el('span', '', label));
    if (count !== null) button.append(el('span', 'c-chip__count', faDigits(count)));
    button.addEventListener('click', onToggle);
    return button;
}

function drawCourses() {
    const query = search.value.trim();
    const items = [...state.courses.values()].filter((course) => !query || course.title.includes(query));
    coursesBox.replaceChildren(...(items.length
        ? items.map((course) => chip(course.title, course.count, state.selected.has(course.id), () => {
            if (state.selected.has(course.id)) state.selected.delete(course.id);
            else if (state.selected.size < 20) state.selected.add(course.id);
            drawCourses();
            loadOptions();
        }))
        : [el('p', 'f-muted', 'درسی با این نام نیست.')]));
    picked.textContent = state.selected.size ? `${faDigits(state.selected.size)} درس` : '';
}

function drawTopics() {
    const topics = state.options?.topics ?? [];
    const key = source() === 'all' ? 'total' : source();
    topicsBox.replaceChildren(...topics.map((topic) => chip(
        topic.topic || 'بدون مبحث',
        topic[key],
        state.topics.has(topic.topic),
        () => {
            if (state.topics.has(topic.topic)) state.topics.delete(topic.topic);
            else state.topics.add(topic.topic);
            drawTopics();
            drawSize();
        },
    )));
    topicsClear.hidden = state.topics.size === 0;
}

function drawSize() {
    const options = state.options;
    for (const key of ['all', 'unseen', 'wrong']) {
        document.getElementById(`count-${key}`).textContent = options
            ? `${faDigits(availableCount(options, [...state.topics], key))} سؤال`
            : '';
    }
    const available = options ? availableCount(options, [...state.topics], source()) : 0;
    const max = Math.max(5, Math.min(100, available));
    range.max = String(max);
    if (Number(range.value) > max) range.value = String(max);
    countOutput.textContent = faDigits(range.value);
    if (!timed.checked) minutes.value = String(suggestedMinutes(Number(range.value)));
    const enough = available >= 5;
    summary.textContent = options
        ? (enough
            ? `${faDigits(Math.min(Number(range.value), available))} سؤال از ${faDigits(available)} سؤال موجود`
            : 'با این انتخاب‌ها کمتر از ۵ سؤال هست.')
        : '';
    submit.disabled = !enough;
}

async function loadOptions() {
    const ticket = ++state.request;
    state.topics.clear();
    if (state.selected.size === 0) {
        state.options = null;
        for (const step of [topicsStep, sourceStep, sizeStep, bar]) step.hidden = true;
        return;
    }
    summary.textContent = 'در حال شمردن…';
    try {
        const ids = [...state.selected].join(',');
        const options = await api.get(`${base}/custom-practice/options?course_ids=${encodeURIComponent(ids)}`);
        if (ticket !== state.request) return; // a later choice already asked again
        state.options = options;
        if (preset.topic !== null && options.topics.some((topic) => topic.topic === preset.topic)) {
            state.topics.add(preset.topic);
        }
        preset.topic = null;
        for (const step of [topicsStep, sourceStep, sizeStep, bar]) step.hidden = false;
        drawTopics();
        drawSize();
    } catch (error) {
        if (ticket === state.request) fail(error);
    }
}

async function loadCourses() {
    try {
        const courses = await api.get(`${base}/assessment-courses`);
        for (const course of (Array.isArray(courses) ? courses : [])) {
            if (!course.course_id) continue;
            state.courses.set(course.course_id, { id: course.course_id, title: String(course.course_title || ''), count: null });
        }
        state.courses = new Map([...state.courses.entries()].sort((a, b) => collator.compare(a[1].title, b[1].title)));
        drawCourses();
        if (preset.course !== null && state.courses.has(preset.course)) {
            state.selected.add(preset.course);
            drawCourses();
            loadOptions();
        }
    } catch (error) {
        coursesBox.replaceChildren(el('p', 'f-muted', `درس‌ها خوانده نشد: ${describeError(error)}`));
    } finally {
        coursesBox.setAttribute('aria-busy', 'false');
    }
}

async function loadMine() {
    try {
        const mine = await api.get(`${base}/custom-practice`);
        if (!Array.isArray(mine) || mine.length === 0) {
            mineBox.replaceChildren(el('p', 'f-muted', 'هنوز آزمون دلخواهی نساخته‌ای.'));
            return;
        }
        mineBox.replaceChildren(el('ul', 'c-mine__list'));
        for (const exam of mine) {
            const link = el('a', 'c-mine__item');
            link.href = `/app/exams/${encodeURIComponent(exam.id)}`;
            const score = exam.in_progress
                ? el('span', 'c-mine__badge is-open', 'نیمه‌کاره')
                : (exam.last_score_percent === null
                    ? el('span', 'c-mine__badge', 'شروع نشده')
                    : el('span', 'c-mine__badge is-done', `٪${faDigits(exam.last_score_percent)}`));
            link.append(el('strong', '', exam.title), score);
            const li = el('li');
            li.append(link);
            mineBox.firstChild.append(li);
        }
    } catch {
        mineBox.replaceChildren(el('p', 'f-muted', 'فهرست خوانده نشد.'));
    } finally {
        mineBox.setAttribute('aria-busy', 'false');
    }
}

search.addEventListener('input', drawCourses);
topicsClear.addEventListener('click', () => { state.topics.clear(); drawTopics(); drawSize(); });
form.addEventListener('change', (event) => {
    if (event.target.name === 'source') { drawTopics(); drawSize(); }
    if (event.target === timed) {
        minutes.disabled = !timed.checked;
        if (timed.checked) minutes.focus();
    }
});
range.addEventListener('input', drawSize);

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    submit.disabled = true;
    submit.textContent = 'در حال ساخت…';
    try {
        const created = await api.post(`${base}/custom-practice`, {
            course_ids: [...state.selected],
            topics: [...state.topics],
            count: Number(range.value),
            source: source(),
            time_limit_minutes: timed.checked ? Number(minutes.value) : null,
        });
        window.location.assign(`/app/exams/${encodeURIComponent(created.assessment_id)}`);
    } catch (error) {
        submit.disabled = false;
        submit.textContent = 'ساخت و شروع';
        fail(error);
    }
});

if (workspaceId) {
    loadCourses();
    loadMine();
}
