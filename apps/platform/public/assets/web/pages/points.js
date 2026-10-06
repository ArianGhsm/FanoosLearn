/*
 * امتیاز روزانه: one GET /workspaces/{id}/points, drawn.
 *
 * Every piece of API text goes in through textContent. Nothing here writes
 * markup from data.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits } from './question-stats.js';
import { barHeights, goalLine, goalPercent, rankLine, ruleLines } from './points-rules.js';
import { discountText } from './products-format.js';

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

const CODE_STATE = { ready: 'آماده‌ی استفاده', used: 'استفاده شده', expired: 'منقضی شده' };
const untilFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Tehran' });

/*
 * سکه: the boxes the owner set, each one tap to buy (and a second to
 * confirm, since it spends coins), and the codes already bought.
 */
function drawCoinShop(wallet) {
    document.getElementById('coins').textContent = faDigits(wallet.coins);
    document.getElementById('shop-balance').textContent = `${faDigits(wallet.coins)} سکه داری`;
    const offers = document.getElementById('offers');
    if (wallet.offers.length === 0) {
        offers.replaceChildren(el('li', 'f-muted', 'فعلاً جعبه‌ای برای خرج کردن سکه تعریف نشده است.'));
    } else {
        offers.replaceChildren(...wallet.offers.map((offer) => {
            const item = el('li', `pt-offer${offer.affordable ? '' : ' is-locked'}`);
            const button = el('button', 'f-btn f-btn--primary', 'فعال‌سازی');
            button.type = 'button';
            button.disabled = !offer.affordable;
            let armed = false;
            button.addEventListener('click', async () => {
                if (!armed) {
                    armed = true;
                    button.textContent = `${faDigits(offer.coins)} سکه کم شود؟ دوباره بزن`;
                    return;
                }
                button.disabled = true;
                try {
                    const made = await api.post(`/workspaces/${encodeURIComponent(workspaceId)}/coins/redeem`, { offer_id: offer.id });
                    await loadCoins();
                    const note = document.getElementById('shop-balance');
                    note.textContent = `کد ${made.code} ساخته شد`;
                } catch (error) {
                    button.disabled = false;
                    armed = false;
                    button.textContent = 'فعال‌سازی';
                    document.getElementById('shop-balance').textContent = describeError(error);
                }
            });
            item.append(
                el('strong', 'pt-offer__title', offer.title),
                el('span', 'pt-offer__value', discountText(offer)),
                el('span', 'f-tiny', `${faDigits(offer.coins)} سکه · کد تا ${faDigits(offer.code_valid_days)} روز معتبر است`),
                button,
            );
            return item;
        }));
    }
    const box = document.getElementById('my-codes-box');
    box.hidden = wallet.codes.length === 0;
    document.getElementById('my-codes').replaceChildren(...wallet.codes.map((code) => {
        const item = el('li', `pt-mycode is-${code.state}`);
        const value = el('code', 'pt-mycode__code', code.code);
        value.dir = 'ltr';
        item.append(value, el('span', '', discountText(code)), el('span', 'f-tiny', code.state === 'ready' && code.valid_until
            ? `تا ${untilFormat.format(new Date(code.valid_until))}` : CODE_STATE[code.state]));
        return item;
    }));
}

async function loadCoins() {
    try {
        drawCoinShop(await api.get(`/workspaces/${encodeURIComponent(workspaceId)}/coins`));
    } catch {
        document.getElementById('coin-shop').hidden = true;
    }
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
        await loadCoins();
    } catch (error) {
        document.getElementById('points-error-text').textContent = describeError(error);
        document.getElementById('points-error').hidden = false;
    } finally {
        board.setAttribute('aria-busy', 'false');
    }
}

load();
