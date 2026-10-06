/*
 * کدهای تخفیف و جعبه‌های سکه: list and create the owner's discount codes,
 * turn them on and off, and set the boxes students buy with coins. Amounts
 * are typed in Tomans and sent in Rials, like every price.
 */
import { api, describeError } from '../foundation/api.js';
import { discountText, faDigits, rialFromTyped } from './products-format.js';

const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}/admin`;
const errorBox = document.getElementById('discounts-error');
const errorText = document.getElementById('discounts-error-text');
const codesBox = document.getElementById('codes');
const offersBox = document.getElementById('offers');
const codeForm = document.getElementById('code-form');
const offerForm = document.getElementById('offer-form');

const ERRORS = {
    discount_code_format: 'کد باید ۴ تا ۳۲ حرف یا عدد لاتین باشد.',
    discount_code_taken: 'این کد قبلاً ساخته شده است.',
    discount_label_invalid: 'یک عنوان کوتاه بنویس.',
    discount_amount_invalid: 'مبلغ تخفیف را درست بنویس.',
    discount_percent_invalid: 'درصد باید بین ۱ تا ۱۰۰ باشد.',
    coin_offer_title_invalid: 'یک عنوان کوتاه برای جعبه بنویس.',
    coin_offer_coins_invalid: 'تعداد سکه باید یک عدد مثبت باشد.',
    coin_offer_days_invalid: 'اعتبار کد بین ۱ تا ۶۰ روز است.',
};

const toAscii = (value) => String(value ?? '').replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d))).replace(/[٬,\s]/g, '');
const whole = (value) => (toAscii(value) === '' ? null : Number(toAscii(value)));

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function fail(error) {
    errorText.textContent = ERRORS[error?.code] || describeError(error);
    errorBox.hidden = false;
    errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/** kind + value from a form, in the API's shape. */
function discountFields(form) {
    const kind = form.elements.kind.value;
    const typed = form.elements.value.value;
    return kind === 'amount'
        ? { kind, amount_minor: rialFromTyped(typed) }
        : { kind, percent: whole(typed) };
}

const until = (iso) => (iso ? new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'medium' }).format(new Date(iso)) : 'بی‌پایان');

function codeRow(code) {
    const card = el('article', `f-card p-card${code.status === 'active' ? '' : ' is-archived'}`);
    const top = el('div', 'p-card__top');
    const name = el('code', 'd-code', code.code);
    name.dir = 'ltr';
    top.append(name, el('span', `p-badge${code.status === 'active' ? ' p-badge--active' : ''}`, code.status === 'active' ? 'فعال' : 'خاموش'));
    const toggle = el('button', 'f-btn f-btn--ghost', code.status === 'active' ? 'خاموش کن' : 'روشن کن');
    toggle.type = 'button';
    toggle.addEventListener('click', async () => {
        toggle.disabled = true;
        try {
            await api.post(`${base}/discount-codes/${encodeURIComponent(code.id)}/status`, { active: code.status !== 'active' });
            await loadCodes();
        } catch (error) {
            toggle.disabled = false;
            fail(error);
        }
    });
    const uses = code.max_uses === null ? `${faDigits(code.uses)} بار استفاده` : `${faDigits(code.uses)} از ${faDigits(code.max_uses)} بار استفاده`;
    card.append(
        top,
        el('p', '', `${code.label} · ${discountText(code)}`),
        el('p', 'p-meta', `${uses} · هر نفر ${faDigits(code.per_user_limit)} بار · تا ${until(code.valid_until)}`),
        toggle,
    );
    return card;
}

function offerRow(offer) {
    const card = el('article', `f-card p-card${offer.active ? '' : ' is-archived'}`);
    const top = el('div', 'p-card__top');
    top.append(el('strong', '', offer.title), el('span', `p-badge${offer.active ? ' p-badge--active' : ''}`, offer.active ? 'فعال' : 'خاموش'));
    const toggle = el('button', 'f-btn f-btn--ghost', offer.active ? 'خاموش کن' : 'روشن کن');
    toggle.type = 'button';
    toggle.addEventListener('click', async () => {
        toggle.disabled = true;
        try {
            await api.patch(`${base}/coin-offers/${encodeURIComponent(offer.id)}`, { ...offer, active: !offer.active });
            await loadOffers();
        } catch (error) {
            toggle.disabled = false;
            fail(error);
        }
    });
    card.append(top, el('p', '', `${faDigits(offer.coins)} سکه → ${discountText(offer)}`), el('p', 'p-meta', `کد تا ${faDigits(offer.code_valid_days)} روز معتبر`), toggle);
    return card;
}

async function loadCodes() {
    try {
        const codes = await api.get(`${base}/discount-codes`);
        codesBox.replaceChildren(...(codes.length ? codes.map(codeRow) : [el('p', 'f-muted', 'هنوز کدی نساخته‌ای.')]));
    } catch (error) {
        fail(error);
    } finally {
        codesBox.setAttribute('aria-busy', 'false');
    }
}

async function loadOffers() {
    try {
        const offers = await api.get(`${base}/coin-offers`);
        offersBox.replaceChildren(...(offers.length ? offers.map(offerRow) : [el('p', 'f-muted', 'هنوز جعبه‌ای نساخته‌ای؛ تا نسازی، دانشجو سکه‌اش را جایی خرج نمی‌کند.')]));
    } catch (error) {
        fail(error);
    } finally {
        offersBox.setAttribute('aria-busy', 'false');
    }
}

async function loadProducts() {
    try {
        const products = await api.get(`${base}/products`);
        for (const select of document.querySelectorAll('[data-products]')) {
            for (const product of products.filter((p) => p.status !== 'archived')) {
                const option = el('option', '', product.name);
                option.value = product.id;
                select.append(option);
            }
        }
    } catch {
        // Without the list a code simply applies to every product.
    }
}

codeForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    const f = codeForm.elements;
    try {
        await api.post(`${base}/discount-codes`, {
            code: f.code.value, label: f.label.value, product_id: f.product_id.value || null,
            max_uses: whole(f.max_uses.value), per_user_limit: whole(f.per_user_limit.value), valid_days: whole(f.valid_days.value),
            ...discountFields(codeForm),
        });
        codeForm.reset();
        await loadCodes();
    } catch (error) {
        fail(error);
    }
});

offerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    const f = offerForm.elements;
    try {
        await api.post(`${base}/coin-offers`, {
            title: f.title.value, coins: whole(f.coins.value), product_id: f.product_id.value || null,
            code_valid_days: whole(f.code_valid_days.value) ?? 3, active: true,
            ...discountFields(offerForm),
        });
        offerForm.reset();
        await loadOffers();
    } catch (error) {
        fail(error);
    }
});

if (workspaceId) {
    loadProducts();
    loadCodes();
    loadOffers();
}
