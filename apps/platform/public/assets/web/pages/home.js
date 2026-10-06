/*
 * Home.
 *
 * Two jobs, depending on which variant the server rendered: choosing a
 * workspace (the chooser), or showing the selected workspace's courses as a
 * place to start. Selecting a workspace is a server-side change to the
 * session, so the page reloads afterwards and every subsequent page is
 * rendered for the new workspace.
 */
import { api, describeError } from '../foundation/api.js';

const list = document.getElementById('workspace-list');
const courses = document.getElementById('home-courses');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';

if (list) loadWorkspaces();
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
            row.append(
                text('span', 'f-course-row__title', String(entry.course_title || 'بدون عنوان')),
                text('span', 'f-course-row__count', `${faDigits(entry.exam_count)} آزمون`),
                text('span', 'f-course-row__go', '←'),
            );
            return row;
        }));
    } catch (error) {
        courses.replaceChildren(text('p', 'f-muted', `درس‌ها خوانده نشد: ${describeError(error)}`));
    } finally {
        courses.setAttribute('aria-busy', 'false');
    }
}

function renderEmpty() {
    const wrap = document.createElement('div');
    wrap.className = 'f-empty';
    wrap.append(
        text('div', 'f-empty__title', 'هنوز فضایی برایت باز نشده'),
        text('p', '', 'اگر رشته‌ات را موقع ثبت‌نام انتخاب کرده‌ای، آزمون‌هایش به محض آماده شدن همین‌جا ظاهر می‌شوند.'),
    );
    list.replaceChildren(wrap);
}

function renderError(message, retry) {
    const wrap = document.createElement('div');
    wrap.className = 'f-notice f-notice--error';
    const body = document.createElement('div');
    body.className = 'f-notice__body';
    body.append(text('div', 'f-notice__title', 'فهرست خوانده نشد'), text('p', '', message));
    if (retry) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'f-btn f-btn--ghost';
        button.textContent = 'تلاش دوباره';
        button.addEventListener('click', loadWorkspaces);
        body.append(button);
    }
    wrap.append(body);
    list.replaceChildren(wrap);
}

function renderChoices(workspaces) {
    const items = workspaces.map((workspace) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'f-home__workspace';
        button.append(
            text('strong', '', String(workspace.name || 'فضای بی‌نام')),
            text('span', 'f-muted', [workspace.institution_name, workspace.program_name, workspace.cohort_label]
                .filter(Boolean).join(' · ')),
        );
        button.addEventListener('click', () => select(workspace.id, button));
        return button;
    });

    const wrap = document.createElement('div');
    wrap.className = 'f-home__workspaces';
    wrap.append(...items);
    list.replaceChildren(wrap);
}

async function loadWorkspaces() {
    list.setAttribute('aria-busy', 'true');
    list.replaceChildren(text('p', 'f-muted', 'در حال خواندن فهرست…'));
    try {
        const account = await api.get('/account');
        const workspaces = Array.isArray(account?.workspaces) ? account.workspaces : [];
        if (workspaces.length === 0) {
            renderEmpty();
            return;
        }
        renderChoices(workspaces);
    } catch (error) {
        renderError(describeError(error), true);
    } finally {
        list.setAttribute('aria-busy', 'false');
    }
}

async function select(id, button) {
    const previous = button.textContent;
    button.disabled = true;
    try {
        await api.post('/workspaces/select', { workspace_id: String(id) });
        window.location.assign('/app');
    } catch (error) {
        button.disabled = false;
        button.textContent = previous;
        renderError(describeError(error), false);
    }
}
