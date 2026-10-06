/*
 * برنامه همکاری در فروش. On /app/affiliate: the student's link, the terms
 * and what it brought (GET .../affiliate; POST .../affiliate/link makes the
 * link). On /app/admin/affiliate: the owner's settings and each affiliate's
 * balance, with "paid out" (GET/POST .../admin/affiliate). Text goes in
 * through textContent only.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits, tomanText } from './products-format.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;
const errorBox = document.getElementById('aff-error');

const ERRORS = {
    affiliate_program_off: 'برنامه‌ی همکاری فعلاً روشن نیست.',
    affiliate_percent_invalid: 'پورسانت باید عددی از ۱ تا ۹۰ باشد.',
    affiliate_days_invalid: 'مدت باید از ۱ تا ۳۶۵۰ روز باشد.',
};
const STATUS = { pending: 'در انتظار پرداخت', paid_out: 'پرداخت شد' };
const dateFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long', year: 'numeric' });

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    document.getElementById('aff-error-text').textContent = ERRORS[error?.code] ?? describeError(error);
    errorBox.hidden = false;
}

function tile(label, value) {
    const box = el('div', 'f-card a-tile');
    box.append(el('span', 'a-tile__label', label), el('strong', 'a-tile__value', value));
    return box;
}

/* ------------------------------------------------------------ student */

function drawStudent(data) {
    const board = document.getElementById('aff');
    if (!data.enabled) {
        board.replaceChildren(el('p', 'f-card f-muted', 'برنامه‌ی همکاری در فروش فعلاً روشن نیست. وقتی روشن شود، لینک اختصاصی‌ات همین‌جاست.'));
        return;
    }
    const terms = el('p', 'f-card a-terms', `از هر خرید کسی که با لینک تو ثبت‌نام کند، تا ${faDigits(data.attribution_days)} روز بعد از ثبت‌نامش، ٪${faDigits(data.commission_percent)} مبلغ پرداخت‌شده مال توست.`);

    const linkBox = el('section', 'f-card a-link');
    if (data.code) {
        const url = `${window.location.origin}/r/${data.code}`;
        const field = el('input', 'f-input a-link__url');
        field.value = url;
        field.readOnly = true;
        field.dir = 'ltr';
        const copy = el('button', 'f-btn f-btn--primary', 'کپی لینک');
        copy.type = 'button';
        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(url);
                copy.textContent = 'کپی شد';
            } catch {
                field.select();
            }
        });
        linkBox.append(el('h2', '', 'لینک تو'), field, copy);
    } else {
        const make = el('button', 'f-btn f-btn--primary', 'ساختن لینک اختصاصی');
        make.type = 'button';
        make.addEventListener('click', async () => {
            make.disabled = true;
            try {
                await api.post(`${base}/affiliate/link`, {});
                await loadStudent();
            } catch (error) {
                make.disabled = false;
                fail(error);
            }
        });
        linkBox.append(el('h2', '', 'لینک تو'), el('p', 'f-muted', 'هنوز لینکی نساخته‌ای.'), make);
    }

    const tiles = el('section', 'a-tiles');
    tiles.append(
        tile('ثبت‌نام با لینک تو', faDigits(data.referrals)),
        tile('خریدار', faDigits(data.buyers)),
        tile('در انتظار پرداخت', tomanText(data.pending_minor)),
        tile('پرداخت‌شده به تو', tomanText(data.paid_out_minor)),
    );

    const history = el('section', 'f-card a-history');
    history.append(el('h2', '', 'پورسانت‌ها'));
    history.append(data.commissions.length
        ? el('ul', 'a-history__list')
        : el('p', 'f-muted', 'هنوز خریدی با لینک تو انجام نشده است.'));
    const list = history.querySelector('ul');
    for (const item of data.commissions) {
        const row = el('li', `a-history__row is-${item.status}`);
        row.append(el('strong', '', tomanText(item.commission_minor)), el('span', '', STATUS[item.status] ?? item.status), el('span', 'f-tiny', dateFormat.format(new Date(item.created_at))));
        list?.append(row);
    }

    board.replaceChildren(terms, linkBox, tiles, history);
}

async function loadStudent() {
    const board = document.getElementById('aff');
    try {
        drawStudent(await api.get(`${base}/affiliate`));
    } catch (error) {
        fail(error);
    } finally {
        board.setAttribute('aria-busy', 'false');
    }
}

/* -------------------------------------------------------------- owner */

function drawAdmin(data) {
    const form = document.getElementById('aff-settings');
    form.elements.enabled.checked = data.enabled;
    form.elements.commission_percent.value = faDigits(data.commission_percent);
    form.elements.attribution_days.value = faDigits(data.attribution_days);

    const list = document.getElementById('aff-list');
    list.replaceChildren(...(data.affiliates.length ? data.affiliates.map((affiliate) => {
        const card = el('article', 'f-card a-affiliate');
        const pay = el('button', 'f-btn f-btn--ghost', 'پرداخت شد');
        pay.type = 'button';
        pay.disabled = affiliate.pending_minor === 0;
        pay.addEventListener('click', async () => {
            if (!pay.dataset.armed) {
                pay.dataset.armed = '1';
                pay.textContent = `${tomanText(affiliate.pending_minor)} پرداخت شد؟ دوباره بزن`;
                return;
            }
            pay.disabled = true;
            try {
                await api.post(`${base}/admin/affiliate/${encodeURIComponent(affiliate.user_id)}/pay-out`, {});
                await loadAdmin();
            } catch (error) {
                pay.disabled = false;
                fail(error);
            }
        });
        card.append(
            el('strong', '', affiliate.name),
            el('span', 'f-tiny', `${faDigits(affiliate.referrals)} ثبت‌نام · ${faDigits(affiliate.buyers)} خریدار`),
            el('span', '', `در انتظار: ${tomanText(affiliate.pending_minor)} · پرداخت‌شده: ${tomanText(affiliate.paid_out_minor)}`),
            pay,
        );
        return card;
    }) : [el('p', 'f-muted', 'هنوز کسی لینک همکاری نساخته است.')]));
}

async function loadAdmin() {
    const list = document.getElementById('aff-list');
    try {
        drawAdmin(await api.get(`${base}/admin/affiliate`));
    } catch (error) {
        fail(error);
    } finally {
        list.setAttribute('aria-busy', 'false');
    }
}

const toAscii = (value) => String(value ?? '').replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).trim();

const settings = document.getElementById('aff-settings');
if (settings) {
    settings.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorBox.hidden = true;
        try {
            await api.post(`${base}/admin/affiliate`, {
                enabled: settings.elements.enabled.checked,
                commission_percent: Number(toAscii(settings.elements.commission_percent.value)),
                attribution_days: Number(toAscii(settings.elements.attribution_days.value)),
            });
            document.getElementById('aff-saved').textContent = 'ذخیره شد.';
            await loadAdmin();
        } catch (error) {
            fail(error);
        }
    });
    if (workspaceId) loadAdmin();
} else if (workspaceId) {
    loadStudent();
}
