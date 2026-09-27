/*
 * The store: lists what the workspace sells and starts a purchase.
 *
 * A purchase is one POST (orders) with an idempotency key kept for this
 * product in this tab, so a double tap or a retry after a network error
 * resumes the same order instead of opening a second one. The server answers
 * with the gateway's address and the browser goes there; the gateway sends
 * the payer back to /pay/return, which the server settles.
 */
import { api, ApiError, describeError } from '../foundation/api.js';

const root = document.getElementById('store');
const errorBox = document.getElementById('store-error');
const errorText = document.getElementById('store-error-text');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const base = `/workspaces/${encodeURIComponent(workspaceId)}`;

function faDigits(value) {
    return String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}

/** Rials to a Toman label, the unit people actually say. */
export function tomanLabel(amountMinor, currency) {
    if (String(currency).toUpperCase() !== 'IRR') return `${faDigits(amountMinor)} ${currency}`;
    const toman = Math.round(Number(amountMinor) / 10);
    return `${faDigits(toman.toLocaleString('en-US')).replace(/,/g, '٬')} تومان`;
}

function text(tag, className, value) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value;
    return node;
}

function idempotencyKey(productId) {
    const slot = `fanoos.order.${productId}`;
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
    try {
        const order = await api.post(`${base}/orders`, { product_id: product.id, idempotency_key: idempotencyKey(product.id) });
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
            : describeError(error);
        errorBox.hidden = false;
    }
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
        text('p', 'f-product__price', tomanLabel(product.amount_minor, product.currency)),
        button,
    );
    return article;
}

async function load() {
    try {
        const products = await api.get(`${base}/catalog`);
        const list = Array.isArray(products) ? products : [];
        root.replaceChildren(...(list.length
            ? list.map(card)
            : [text('p', 'f-muted', 'فعلاً چیزی برای فروش در این فضا نیست.')]));
    } catch (error) {
        root.replaceChildren(text('p', 'f-muted', `فهرست خوانده نشد: ${describeError(error)}`));
    } finally {
        root.setAttribute('aria-busy', 'false');
    }
}

if (root && workspaceId) load();
