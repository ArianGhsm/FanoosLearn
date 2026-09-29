/*
 * Products and prices: list, create, reprice, publish/archive, and lock or
 * unlock the exams behind a product. Prices are typed and shown in Tomans and
 * stored in Rials (x10), the only unit the gateway takes.
 */
import { api, describeError } from '../foundation/api.js';
import { faDigits, groupDigits, rialFromTyped, tomanText } from './products-format.js';

const list = document.getElementById('products');
const newButton = document.getElementById('product-new');
const newForm = document.getElementById('product-new-form');
const errorBox = document.getElementById('products-error');
const errorText = document.getElementById('products-error-text');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}/admin/products`;

const STATUS = { draft: 'پیش‌نویس', active: 'در فروش', archived: 'بایگانی' };
const ERRORS = {
    product_price_invalid: 'قیمت باید دست‌کم ۱۰۰ تومان باشد.',
    product_name_invalid: 'نام محصول را بنویس.',
    product_key_taken: 'محصولی با همین شناسه هست.',
};

/** Keeps a price field grouped as it is typed. */
function formatAsTyped(input) {
    input.addEventListener('input', () => {
        const rial = rialFromTyped(input.value);
        if (rial !== null) input.value = groupDigits(rial / 10);
    });
}

function shamsi(iso) {
    try {
        return new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function el(tag, props = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(props)) {
        if (key === 'text') node.textContent = value;
        else if (key === 'className') node.className = value;
        else if (key.startsWith('on')) node.addEventListener(key.slice(2).toLowerCase(), value);
        else if (value !== null && value !== undefined && value !== false) node.setAttribute(key, value === true ? '' : value);
    }
    node.append(...children.filter(Boolean));
    return node;
}

function fail(error) {
    errorText.textContent = ERRORS[error?.code] || describeError(error);
    errorBox.hidden = false;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/*
 * One product is one row of the list: what it is, whether it sells, what it
 * costs, how often it sold and what it locks. The row opens in place into
 * its editor -- the list stays a list, and only the product being changed
 * shows its fields.
 */
function card(product, open = false) {
    const name = el('input', { className: 'f-input', value: product.name, maxlength: '200', 'aria-label': 'نام محصول' });
    const price = el('input', {
        className: 'f-input p-price', inputmode: 'numeric', 'aria-label': 'قیمت به تومان',
        value: product.amount_rial === null ? '' : groupDigits(Math.round(product.amount_rial / 10)),
    });
    formatAsTyped(price);
    const status = el('div', { className: 'f-segment p-status', role: 'radiogroup', 'aria-label': 'وضعیت' },
        ...Object.entries(STATUS).map(([value, label]) => el('label', {},
            el('input', { type: 'radio', name: `status-${product.id}`, value, checked: product.status === value }), label)));
    const save = el('button', { className: 'f-btn f-btn--primary', type: 'button', text: 'ذخیره' });

    save.addEventListener('click', async () => {
        errorBox.hidden = true;
        const rial = rialFromTyped(price.value);
        if (rial === null) {
            fail({ code: 'product_price_invalid' });
            return;
        }
        const chosen = status.querySelector('input:checked')?.value ?? product.status;
        save.disabled = true;
        try {
            const updated = await api.patch(`${base}/${product.id}`, { name: name.value.trim(), amount_rial: rial, status: chosen });
            article.replaceWith(card(updated, true));
        } catch (error) {
            fail(error);
            save.disabled = false;
        }
    });

    const locked = product.exams_locked > 0;
    const lock = el('button', {
        className: `f-btn ${locked ? 'f-btn--ghost' : 'f-btn--ghost p-lock'}`, type: 'button',
        text: locked ? 'باز کردن آزمون‌ها برای همه' : 'قفل کردن آزمون‌ها پشت این محصول',
        disabled: product.exams_total === 0,
    });
    lock.addEventListener('click', async () => {
        const question = locked
            ? `${faDigits(product.exams_locked)} آزمون برای همه رایگان شود؟`
            : `${faDigits(product.exams_total)} آزمون فقط برای خریداران این محصول باز باشد؟`;
        if (!window.confirm(question)) return;
        lock.disabled = true;
        try {
            const updated = await api.post(`${base}/${product.id}/exam-lock`, { locked: !locked });
            article.replaceWith(card(updated, true));
        } catch (error) {
            fail(error);
            lock.disabled = false;
        }
    });

    const history = el('details', { className: 'p-history' },
        el('summary', { text: `سابقه‌ی قیمت (${faDigits(product.price_history.length)})` }),
        el('ol', {}, ...product.price_history.map((entry) => el('li', {},
            el('strong', { text: tomanText(entry.amount_rial) }),
            el('span', { className: 'f-muted', text: ` از ${shamsi(entry.valid_from)}${entry.valid_until ? ` تا ${shamsi(entry.valid_until)}` : ' تا حالا'}` })))));

    const lockLabel = product.exams_total === 0
        ? 'بدون آزمون'
        : (locked ? `${faDigits(product.exams_locked)} آزمون قفل` : 'آزمون‌ها آزاد');
    const article = el('details', { className: `p-row is-${product.status}`, open: open === true },
        el('summary', { className: 'p-row__summary' },
            el('span', { className: 'p-row__name', text: product.name }),
            el('span', { className: `p-badge p-badge--${product.status}`, text: STATUS[product.status] ?? product.status }),
            el('span', { className: 'p-row__price', text: product.amount_rial === null ? '—' : tomanText(product.amount_rial) }),
            el('span', { className: 'p-row__meta', text: `${faDigits(product.paid_orders)} فروش` }),
            el('span', { className: `p-row__meta${locked ? ' is-locked' : ''}`, text: lockLabel }),
            el('span', { className: 'p-row__edit', 'aria-hidden': 'true', text: 'ویرایش' })),
        el('div', { className: 'p-row__body' },
            el('div', { className: 'p-grid' },
                el('label', { className: 'f-field' }, el('span', { className: 'f-field__label', text: 'نام' }), name),
                el('label', { className: 'f-field' }, el('span', { className: 'f-field__label', text: 'قیمت (تومان)' }), price)),
            el('div', { className: 'p-actions' }, status, save),
            el('div', { className: 'p-lockbox' },
                el('p', { className: 'f-muted', text: product.exams_total === 0
                    ? 'هیچ آزمونی به این محصول وصل نیست.'
                    : `${faDigits(product.exams_locked)} از ${faDigits(product.exams_total)} آزمونِ این فضا فقط با خرید این محصول باز می‌شود.` }),
                lock),
            history));
    return article;
}

async function load() {
    list.setAttribute('aria-busy', 'true');
    try {
        const products = await api.get(base);
        list.replaceChildren(...(products.length
            ? products.map((product) => card(product))
            : [el('p', { className: 'f-muted', text: 'هنوز محصولی نساخته‌ای.' })]));
    } catch (error) {
        list.replaceChildren(el('p', { className: 'f-muted', text: `خوانده نشد: ${describeError(error)}` }));
    } finally {
        list.setAttribute('aria-busy', 'false');
    }
}

newButton?.addEventListener('click', () => {
    newForm.hidden = false;
    newForm.elements.name.focus();
});
newForm?.querySelector('[data-cancel]')?.addEventListener('click', () => { newForm.hidden = true; });
newForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    const rial = rialFromTyped(newForm.elements.toman.value);
    if (rial === null) {
        fail({ code: 'product_price_invalid' });
        return;
    }
    try {
        await api.post(base, { name: newForm.elements.name.value.trim(), amount_rial: rial, status: 'draft' });
        newForm.reset();
        newForm.hidden = true;
        await load();
    } catch (error) {
        fail(error);
    }
});

if (newForm) formatAsTyped(newForm.elements.toman);
if (list && workspaceId) load();
