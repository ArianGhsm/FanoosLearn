/*
 * The store: lists what the workspace sells and starts a purchase.
 *
 * A purchase is one POST (orders) with an idempotency key kept for this
 * product (and discount code) in this tab, so a double tap or a retry after
 * a network error resumes the same order instead of opening a second one.
 * The server answers with the gateway's address and the browser goes there;
 * the gateway sends the payer back to /pay/return, which the server settles.
 *
 * کد تخفیف: a code entered once is checked against each product
 * (POST discount-codes/check); a card shows the new price, or why the code
 * does not apply to it. The server checks the code again when the order is
 * made, so what is shown here is never what decides the charge.
 */
import { api, ApiError, describeError } from '../foundation/api.js';

const root = document.getElementById('store');
const errorBox = document.getElementById('store-error');
const errorText = document.getElementById('store-error-text');
const discountForm = document.getElementById('discount-form');
const discountInput = document.getElementById('discount-code');
const discountNote = document.getElementById('discount-note');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;

let products = [];
let code = '';
/** product id => {total_minor, discount_minor} or {error} for the current code */
let quotes = new Map();

const DISCOUNT_REASONS = {
    discount_code_invalid: 'این کد معتبر نیست.',
    discount_code_expired: 'این کد دیگر معتبر نیست.',
    discount_code_wrong_product: 'این کد برای این محصول نیست.',
    discount_code_used: 'این کد قبلاً استفاده شده است.',
    discount_code_not_applicable: 'این کد قیمت این محصول را کم نمی‌کند.',
};

function faDigits(value) {
    return String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}

/** Rials to a Toman label, the unit people actually say. */
export function tomanLabel(amountMinor, currency) {
    if (String(currency).toUpperCase() !== 'IRR') return `${faDigits(amountMinor)} ${currency}`;
    const toman = Math.round(Number(amountMinor) / 10);
    return `${faDigits(toman.toLocaleString('en-US')).replace(/,/g, '٬')} تومان`;
}

/** What a refused code means, in words; anything unexpected falls back to the generic message. */
export function discountReason(error) {
    return (error instanceof ApiError && DISCOUNT_REASONS[error.code]) || describeError(error);
}

function text(tag, className, value) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value;
    return node;
}

function idempotencyKey(productId, discountCode) {
    const slot = `fanoos.order.${productId}.${discountCode}`;
    try {
        const existing = sessionStorage.getItem(slot);
        if (existing) return existing;
        const created = `web-${crypto.randomUUID()}`;
        sessionStorage.setItem(slot, created);
        return created;
    } catch {
        return `web-${crypto.randomUUID()}`;
    }
}

async function buy(product, button) {
    errorBox.hidden = true;
    button.disabled = true;
    button.textContent = 'در حال انتقال به درگاه…';
    const quote = quotes.get(product.id);
    const applied = quote && !quote.error ? code : '';
    try {
        const body = { product_id: product.id, idempotency_key: idempotencyKey(product.id, applied) };
        if (applied) body.discount_code = applied;
        const order = await api.post(`${base}/orders`, body);
        if (order?.redirect_url) {
            window.location.assign(order.redirect_url);
            return;
        }
        throw new ApiError('no_redirect', '', 500);
    } catch (error) {
        button.disabled = false;
        button.textContent = 'خرید';
        errorText.textContent = error instanceof ApiError && error.code === 'payment_provider_not_configured'
            ? 'پرداخت آنلاین فعلاً فعال نیست.'
            : (DISCOUNT_REASONS[error?.code] ?? describeError(error));
        errorBox.hidden = false;
    }
}

function priceBlock(product) {
    const quote = quotes.get(product.id);
    const box = document.createElement('div');
    box.className = 'f-product__prices';
    if (quote && !quote.error) {
        box.append(
            text('p', 'f-product__price f-product__price--old', tomanLabel(product.amount_minor, product.currency)),
            text('p', 'f-product__price', tomanLabel(quote.total_minor, product.currency)),
            text('p', 'f-tiny f-product__saving', `${tomanLabel(quote.discount_minor, product.currency)} تخفیف`),
        );
    } else {
        box.append(text('p', 'f-product__price', tomanLabel(product.amount_minor, product.currency)));
        if (quote?.error) box.append(text('p', 'f-tiny f-product__refused', quote.error));
    }
    return box;
}

function card(product) {
    const owned = product.entitlement_status === 'active';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `f-btn ${owned ? 'f-btn--ghost' : 'f-btn--primary'}`;
    button.textContent = owned ? 'خریده‌ای' : 'خرید';
    button.disabled = owned;
    if (!owned) button.addEventListener('click', () => buy(product, button));

    const article = document.createElement('article');
    article.className = `f-card f-product${owned ? ' is-owned' : ''}`;
    article.append(
        text('h2', 'f-product__name', product.name),
        text('p', 'f-muted f-product__scope', product.resource_title || product.scope_label || ''),
        priceBlock(product),
        button,
    );
    return article;
}

function draw() {
    root.replaceChildren(...(products.length
        ? products.map(card)
        : [text('p', 'f-muted', 'فعلاً چیزی برای فروش در این فضا نیست.')]));
}

async function applyCode(event) {
    event.preventDefault();
    code = discountInput.value.replace(/[\s-]+/g, '').toUpperCase();
    quotes = new Map();
    discountNote.textContent = '';
    if (code === '') {
        draw();
        return;
    }
    discountNote.textContent = 'در حال بررسی کد…';
    const buyable = products.filter((product) => product.entitlement_status !== 'active');
    await Promise.all(buyable.map(async (product) => {
        try {
            quotes.set(product.id, await api.post(`${base}/discount-codes/check`, { product_id: product.id, code }));
        } catch (error) {
            quotes.set(product.id, { error: discountReason(error) });
        }
    }));
    const applied = [...quotes.values()].filter((quote) => !quote.error).length;
    discountNote.textContent = applied > 0
        ? `کد روی ${faDigits(applied)} محصول اعمال شد.`
        : ([...quotes.values()][0]?.error ?? 'این کد روی هیچ محصولی اعمال نشد.');
    draw();
}

async function load() {
    try {
        const list = await api.get(`${base}/catalog`);
        products = Array.isArray(list) ? list : [];
        draw();
        discountForm.hidden = products.length === 0;
    } catch (error) {
        root.replaceChildren(text('p', 'f-muted', `فهرست خوانده نشد: ${describeError(error)}`));
    } finally {
        root.setAttribute('aria-busy', 'false');
    }
}

if (root && workspaceId) {
    discountForm?.addEventListener('submit', applyCode);
    load();
}
