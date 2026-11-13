/*
 * Home: today at a glance and the courses to start from. FANOOS is one
 * dental workspace, chosen by the server; there is no chooser here.
 */
import { api, describeError } from '../foundation/api.js';

const courses = document.getElementById('home-courses');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';

if (courses && workspaceId) loadCourses();
if (document.getElementById('today') && workspaceId) loadToday();

/*
 * امروز: today's points against the goal, the reviews due, and coins --
 * the three things worth a glance on arriving. Each read is independent;
 * the strip shows once the points are in.
 */
async function loadToday() {
    const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
    try {
        const stats = await api.get(`${base}/points/today`);
        document.getElementById('today-points').textContent = `${faDigits(stats.points)} از ${faDigits(stats.goal)}`;
        document.getElementById('today-bar').parentElement.style.setProperty('--done', `${Math.min(100, Math.round((stats.points * 100) / stats.goal))}%`);
        document.getElementById('today-coins').textContent = faDigits(stats.coins);
        document.getElementById('today').hidden = false;
    } catch {
        return;
    }
    try {
        const review = await api.get(`${base}/review`);
        document.getElementById('today-review').textContent = faDigits(review.due ?? 0);
        document.getElementById('today-review-note').textContent = review.due > 0 ? 'سؤال برای مرور' : 'چیزی برای مرور نیست';
    } catch {
        document.getElementById('today-review').textContent = '—';
    }
}

const TILE_LIMIT = 10;

function text(tag, className, value) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value;
    return node;
}

function chevron() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('f-course-row__go');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M15 5l-7 7 7 7');
    svg.append(path);
    return svg;
}

function faDigits(value) {
    return String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}


async function loadCourses() {
    try {
        const payload = await api.get(`/workspaces/${encodeURIComponent(workspaceId)}/assessment-courses`);
        const entries = (Array.isArray(payload) ? payload : [])
            .filter((entry) => entry.course_id && Number(entry.exam_count) > 0)
            .sort((a, b) => Number(b.exam_count) - Number(a.exam_count))
            .slice(0, TILE_LIMIT);
        if (entries.length === 0) {
            courses.replaceChildren(text('p', 'f-muted', 'هنوز آزمونی در این فضا منتشر نشده. به محض انتشار، درس‌ها همین‌جا ظاهر می‌شوند.'));
            return;
        }
        courses.replaceChildren(...entries.map((entry) => {
            const row = document.createElement('a');
            row.className = 'f-course-row';
            row.href = `/app/exams#course=${encodeURIComponent(entry.course_id)}`;
            const title = String(entry.course_title || 'بدون عنوان');
            row.append(
                text('span', 'f-course-row__mark', title.trim().charAt(0)),
                text('span', 'f-course-row__title', title),
                text('span', 'f-course-row__count', `${faDigits(entry.exam_count)} آزمون`),
                chevron(),
            );
            return row;
        }));
    } catch (error) {
        courses.replaceChildren(text('p', 'f-muted', `درس‌ها خوانده نشد: ${describeError(error)}`));
    } finally {
        courses.setAttribute('aria-busy', 'false');
    }
}





