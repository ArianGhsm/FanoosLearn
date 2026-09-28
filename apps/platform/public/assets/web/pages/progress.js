/*
 * داشبورد پیشرفت: one GET /workspaces/{id}/progress, drawn.
 *
 * Every piece of API text goes in through textContent; the chart is SVG built
 * with createElementNS. Nothing here writes markup from data.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { direction, heatLevel, recentAverage, trendPoints, weekColumns } from './progress-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const board = document.getElementById('progress-board');
const SVG = 'http://www.w3.org/2000/svg';
const dayFormat = new Intl.DateTimeFormat('fa-IR', { day: 'numeric', month: 'long' });
const dateFormat = new Intl.DateTimeFormat('fa-IR', { day: 'numeric', month: 'short', year: 'numeric' });
const KIND = { practice: 'تمرین', quiz: 'کوییز', mock_exam: 'آزمون آزمایشی', past_exam: 'آزمون سال‌های قبل', custom: 'آزمون دلخواه' };

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function svg(tag, attrs = {}) {
    const node = document.createElementNS(SVG, tag);
    for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, String(value));
    return node;
}

const percent = (value) => (value === null || value === undefined ? '—' : `٪${faDigits(value)}`);

function tile(label, value, note, tone = '') {
    const box = el('div', `p-tile${tone ? ` p-tile--${tone}` : ''}`);
    box.append(el('span', 'p-tile__label', label), el('strong', 'p-tile__value', value));
    if (note) box.append(el('span', 'p-tile__note', note));
    return box;
}

function drawTiles(data) {
    const t = data.totals;
    const s = data.streak;
    document.getElementById('tiles').replaceChildren(
        tile('آزمون تمام‌شده', faDigits(t.attempts), `${faDigits(t.study_days)} روز مطالعه`),
        tile('سؤال پاسخ‌داده', faDigits(t.answers), `${faDigits(t.questions_seen)} سؤال متفاوت`),
        tile('درصد پاسخ درست', percent(t.correct_percent), `${faDigits(t.questions_mastered)} سؤال را آخرین بار درست زده‌ای`, 'accent'),
        tile('روزهای پشت‌سرهم', faDigits(s.current), s.best > 0 ? `بیشترین: ${faDigits(s.best)} روز` : null, s.current > 0 ? 'warm' : ''),
    );
    const streak = document.getElementById('streak');
    streak.textContent = s.current === 0
        ? 'امروز یک آزمون بزن تا زنجیره شروع شود'
        : (s.today ? `${faDigits(s.current)} روز پشت‌سرهم، امروز هم خواندی` : `${faDigits(s.current)} روز پشت‌سرهم — امروز را از دست نده`);
}

function drawHeat(days) {
    const max = Math.max(0, ...days.map((day) => day.answered));
    const grid = document.getElementById('heat');
    const cells = [];
    for (const column of weekColumns(days)) {
        const col = el('div', 'p-heat__col');
        for (const day of column) {
            const cell = el('i', 'p-heat__cell');
            if (day === null) {
                cell.classList.add('is-pad');
            } else {
                cell.dataset.level = String(heatLevel(day.answered, max));
                const when = dayFormat.format(new Date(`${day.date}T12:00:00Z`));
                cell.title = day.answered > 0
                    ? `${when}: ${faDigits(day.answered)} سؤال، ${faDigits(day.correct)} درست`
                    : `${when}: —`;
            }
            col.append(cell);
        }
        cells.push(col);
    }
    grid.replaceChildren(...cells);
}

function drawTrend(trend) {
    const box = document.getElementById('trend');
    const hint = document.getElementById('trend-hint');
    const scores = trend.map((point) => point.score_percent);
    if (scores.length < 2) {
        box.replaceChildren(el('p', 'f-muted', 'با دو آزمون، روندت اینجا دیده می‌شود.'));
        hint.textContent = '';
        return;
    }
    const width = 640;
    const height = 180;
    // Oldest on the right: the page reads right to left.
    const points = trendPoints(scores, width, height, 14).map((p) => ({ x: width - p.x, y: p.y }));
    const chart = svg('svg', { viewBox: `0 0 ${width} ${height}`, class: 'p-trend__svg', role: 'img', 'aria-label': 'نمره‌ی آزمون‌های اخیر' });
    for (const level of [0, 50, 100]) {
        const y = trendPoints([level], width, height, 14)[0].y;
        chart.append(svg('line', { x1: 0, x2: width, y1: y, y2: y, class: 'p-trend__grid' }));
        const label = svg('text', { x: width - 2, y: y - 3, class: 'p-trend__axis', 'text-anchor': 'end' });
        label.textContent = `٪${faDigits(level)}`;
        chart.append(label);
    }
    const line = points.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
    const bottom = height - 14;
    chart.append(svg('path', { d: `${line} L${points.at(-1).x.toFixed(1)} ${bottom} L${points[0].x.toFixed(1)} ${bottom} Z`, class: 'p-trend__area' }));
    chart.append(svg('path', { d: line, class: 'p-trend__line' }));
    points.forEach((p, i) => {
        const dot = svg('circle', { cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: 4, class: 'p-trend__dot' });
        const title = svg('title');
        title.textContent = `${trend[i].title} — ٪${faDigits(scores[i])} — ${dateFormat.format(new Date(trend[i].date))}`;
        dot.append(title);
        chart.append(dot);
    });
    box.replaceChildren(chart);

    const average = recentAverage(scores);
    const way = direction(scores);
    const arrow = way === 'up' ? ' ↑ رو به بالا' : (way === 'down' ? ' ↓ رو به پایین' : '');
    hint.textContent = `میانگین ۵ آزمون آخر: ٪${faDigits(average)}${arrow}`;
    hint.dataset.direction = way ?? '';
}

function bar(value) {
    const track = el('span', 'p-bar');
    const fill = el('span', 'p-bar__fill');
    fill.style.setProperty('--p-fill', `${Math.max(0, Math.min(100, value ?? 0))}%`);
    track.append(fill);
    return track;
}

function drawCourses(courses) {
    const list = document.getElementById('courses');
    if (courses.length === 0) {
        list.replaceChildren(el('li', 'f-muted', 'هنوز در هیچ درسی سؤالی جواب نداده‌ای.'));
        return;
    }
    list.replaceChildren(...courses.map((course) => {
        const item = el('li', 'p-course');
        const head = el('div', 'p-course__head');
        head.append(el('strong', 'p-course__title', course.title), el('span', 'p-course__pct', percent(course.correct_percent)));
        const seen = course.questions_total
            ? `${faDigits(course.questions_seen)} از حدود ${faDigits(course.questions_total)} سؤال دیده‌ای`
            : `${faDigits(course.questions_seen)} سؤال دیده‌ای`;
        const practice = el('a', 'p-course__go', 'تمرین');
        practice.href = `/app/exams/custom?course=${encodeURIComponent(course.course_id)}`;
        const foot = el('div', 'p-course__foot');
        foot.append(el('span', 'f-tiny', seen), practice);
        item.append(head, bar(course.correct_percent), foot);
        return item;
    }));
}

function drawWeak(topics) {
    const list = document.getElementById('weak');
    if (topics.length === 0) {
        list.replaceChildren(el('li', 'f-muted', 'هنوز مبحثی نیست که دست‌کم ۵ بار جواب داده باشی و در آن اشتباه داشته باشی.'));
        return;
    }
    list.replaceChildren(...topics.map((topic) => {
        const item = el('li', 'p-weak__item');
        const text = el('div', 'p-weak__text');
        text.append(el('strong', '', topic.topic), el('span', 'f-tiny', `${topic.course_title} · ${faDigits(topic.answers)} پاسخ`));
        const go = el('a', 'f-btn f-btn--ghost p-weak__go', 'تمرین همین مبحث');
        go.href = `/app/exams/custom?course=${encodeURIComponent(topic.course_id)}&topic=${encodeURIComponent(topic.topic)}`;
        item.append(el('span', 'p-weak__pct', percent(topic.correct_percent)), text, go);
        return item;
    }));
}

function drawRecent(recent) {
    const list = document.getElementById('recent');
    list.replaceChildren(...recent.map((attempt) => {
        const link = el('a', 'p-recent__item');
        link.href = `/app/exams/${encodeURIComponent(attempt.assessment_id)}`;
        const text = el('span', 'p-recent__text');
        text.append(
            el('strong', '', attempt.title),
            el('span', 'f-tiny', `${KIND[attempt.kind] ?? 'آزمون'} · ${dateFormat.format(new Date(attempt.date))} · ${faDigits(attempt.correct_count)} از ${faDigits(attempt.question_count)}`),
        );
        const score = el('span', 'p-recent__score', `٪${faDigits(attempt.score_percent)}`);
        score.dataset.band = attempt.score_percent >= 70 ? 'good' : (attempt.score_percent >= 40 ? 'mid' : 'low');
        link.append(text, score);
        const li = el('li');
        li.append(link);
        return li;
    }));
}

async function load() {
    try {
        const data = await api.get(`/workspaces/${encodeURIComponent(workspaceId)}/progress`);
        if (data.totals.attempts === 0) {
            board.hidden = true;
            document.getElementById('progress-empty').hidden = false;
            return;
        }
        drawTiles(data);
        drawHeat(data.activity);
        drawTrend(data.trend);
        drawCourses(data.courses);
        drawWeak(data.weak_topics);
        drawRecent(data.recent);
    } catch (error) {
        board.hidden = true;
        document.getElementById('progress-error-text').textContent = `پیشرفت خوانده نشد: ${describeError(error)}`;
        document.getElementById('progress-error').hidden = false;
    } finally {
        board.setAttribute('aria-busy', 'false');
    }
}

if (workspaceId) load();
