/*
 * تایمر مطالعه. The running block is kept in localStorage (as its start and
 * end instants), so reloading the page or coming back to the tab resumes it;
 * a finished focus block is sent to POST /study-sessions.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { clock, elapsedMinutes, nextPhase, remaining } from './timer-rules.js';
import { studyTime } from './progress-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const KEY = 'fanoos.studyTimer.v1';
const clockEl = document.getElementById('t-clock');
const phaseEl = document.getElementById('t-phase');
const startBtn = document.getElementById('t-start');
const stopBtn = document.getElementById('t-stop');
const todayEl = document.getElementById('t-today');
const statusEl = document.getElementById('t-status');
const presets = [...document.querySelectorAll('[data-focus]')];

let plan = { focus: 25, rest: 5 };
let run = null; // { phase, startedAt, endsAt, focus, rest }
let ticker = null;

function save() {
    try {
        if (run) localStorage.setItem(KEY, JSON.stringify(run)); else localStorage.removeItem(KEY);
    } catch {
        // A private window: the timer still runs, it just does not survive a reload.
    }
}

function restore() {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (saved && typeof saved.endsAt === 'number') run = saved;
    } catch {
        run = null;
    }
}

function showToday(minutes) {
    todayEl.textContent = `مطالعه‌ی امروز: ${studyTime(minutes, faDigits)}`;
}

async function record(minutes) {
    if (minutes < 1) return;
    try {
        const result = await api.post(`${base}/study-sessions`, { minutes });
        showToday(result.minutes_today);
        statusEl.textContent = `${studyTime(minutes, faDigits)} ثبت شد.`;
    } catch (error) {
        statusEl.textContent = `ثبت نشد: ${describeError(error)}`;
    }
}

function draw() {
    const phase = run ? run.phase : 'focus';
    phaseEl.textContent = phase === 'focus' ? 'تمرکز' : 'استراحت';
    document.body.classList.toggle('t-resting', phase === 'break');
    const seconds = run ? remaining(run.endsAt, Date.now()) : plan.focus * 60;
    clockEl.textContent = clock(seconds, faDigits);
    document.title = run ? `${clock(seconds, faDigits)} · تایمر مطالعه` : 'تایمر مطالعه | فانوس';
    startBtn.hidden = run !== null;
    stopBtn.hidden = run === null;
    stopBtn.textContent = phase === 'focus' ? 'پایان و ثبت' : 'رد کردن استراحت';
    for (const p of presets) p.disabled = run !== null;
}

function begin(phase) {
    const length = (phase === 'focus' ? plan.focus : plan.rest) * 60000;
    const now = Date.now();
    run = { phase, startedAt: now, endsAt: now + length, focus: plan.focus, rest: plan.rest };
    save();
    tick();
}

function tick() {
    clearTimeout(ticker);
    if (run && Date.now() >= run.endsAt) {
        const finished = run;
        if (finished.phase === 'focus') record(finished.focus);
        try { navigator.vibrate?.(200); } catch { /* not every device */ }
        statusEl.textContent = finished.phase === 'focus' ? 'آفرین؛ وقت استراحت است.' : 'استراحت تمام شد؛ بلوک بعدی؟';
        if (finished.phase === 'focus') {
            plan = { focus: finished.focus, rest: finished.rest };
            begin(nextPhase(finished.phase));
            return;
        }
        run = null;
        save();
    }
    draw();
    if (run) ticker = setTimeout(tick, 500);
}

for (const preset of presets) {
    preset.addEventListener('click', () => {
        plan = { focus: Number(preset.dataset.focus), rest: Number(preset.dataset.break) };
        for (const p of presets) p.classList.toggle('is-active', p === preset);
        draw();
    });
}
startBtn.addEventListener('click', () => {
    statusEl.textContent = '';
    begin('focus');
});
stopBtn.addEventListener('click', () => {
    if (run && run.phase === 'focus') record(elapsedMinutes(run.startedAt, Date.now(), run.focus));
    run = null;
    save();
    draw();
});
document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });

restore();
if (run) {
    plan = { focus: run.focus, rest: run.rest };
    for (const p of presets) p.classList.toggle('is-active', Number(p.dataset.focus) === run.focus);
}
tick();
if (workspaceId) {
    api.get(`${base}/progress`).then((data) => showToday(data.totals.today_minutes ?? 0)).catch(() => {});
}
