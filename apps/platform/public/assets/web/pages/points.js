/*
 * امتیاز روزانه: one GET /workspaces/{id}/points, drawn.
 *
 * Every piece of API text goes in through textContent. Nothing here writes
 * markup from data.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { barHeights, goalLine, goalPercent, rankLine, ruleLines } from './points-rules.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const board = document.getElementById('points-board');
const dayFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'short' });
const dateFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'short' });

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

const asDate = (ymd) => new Date(`${ymd}T12:00:00Z`);

function drawGoal(data) {
    const percent = goalPercent(data.today.points, data.goal);
    const circumference = 2 * Math.PI * 52;
    const fill = document.getElementById('goal-fill');
    fill.style.strokeDasharray = `${circumference}`;
    fill.style.strokeDashoffset = `${circumference * (1 - percent / 100)}`;
    document.getElementById('goal-ring').dataset.done = percent >= 100 ? 'true' : 'false';
    document.getElementById('goal-value').textContent = `${faDigits(data.today.points)}`;
    document.getElementById('goal-line').textContent = goalLine(data.today.points, data.goal);
    document.getElementById('goal-rule').textContent = `هدف هر روز ${faDigits(data.goal)} امتیاز · ${faDigits(data.goal_days)} روز به هدف رسیده‌ای`;
    document.getElementById('coins').textContent = faDigits(data.coins);
}

function rankTile(label, period) {
    const box = el('div', 'f-card pt-rank');
    box.append(
        el('span', 'pt-rank__label', label),
        el('strong', 'pt-rank__value', `${faDigits(period.points)} امتیاز`),
        el('span', 'pt-rank__line', rankLine(period)),
    );
    if (period.rank === 1) box.dataset.first = 'true';
    return box;
}

function drawRanks(data) {
    document.getElementById('ranks').replaceChildren(
        rankTile('امروز', data.today),
        rankTile('این هفته', data.week),
        rankTile(`${data.month.label} ماه`, data.month),
    );
}

function drawBars(id, items, labelOf) {
    const heights = barHeights(items.map((item) => item.points));
    const box = document.getElementById(id);
    box.replaceChildren(...items.map((item, index) => {
        const column = el('div', 'pt-bars__col');
        const bar = el('span', 'pt-bars__bar');
        bar.style.setProperty('--h', `${heights[index]}%`);
        if (index === items.length - 1) bar.dataset.current = 'true';
        column.title = `${labelOf(item)}: ${faDigits(item.points)} امتیاز`;
        column.append(el('span', 'pt-bars__value', item.points > 0 ? faDigits(item.points) : ''), bar, el('span', 'pt-bars__label', labelOf(item)));
        return column;
    }));
}

async function load() {
    try {
        const data = await api.get(`/workspaces/${encodeURIComponent(workspaceId)}/points`);
        drawGoal(data);
        drawRanks(data);
        drawBars('days', data.days, (day) => dayFormat.format(asDate(day.date)));
        drawBars('weeks', data.weeks, (week) => dateFormat.format(asDate(week.start)));
        drawBars('months', data.months, (month) => month.label);
        document.getElementById('rules').replaceChildren(...ruleLines(data).map((line) => el('li', '', line)));
    } catch (error) {
        document.getElementById('points-error-text').textContent = describeError(error);
        document.getElementById('points-error').hidden = false;
    } finally {
        board.setAttribute('aria-busy', 'false');
    }
}

load();
