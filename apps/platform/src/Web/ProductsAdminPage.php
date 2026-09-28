<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The owner's products and prices for the selected workspace. Everything on
 * it comes from, and goes back through, /admin/products, which checks
 * commerce.manage_catalog on every request.
 */
final class ProductsAdminPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<div class="p-head">
    <div>
        <h1>محصولات و قیمت</h1>
        <p class="f-muted">قیمت را هر وقت خواستی عوض کن؛ از همان لحظه اعمال می‌شود و سفارش‌های قبلی با قیمت خودشان می‌مانند.</p>
    </div>
    <button class="f-btn f-btn--primary" type="button" id="product-new">محصول تازه</button>
</div>

<div class="f-notice f-notice--error" id="products-error" hidden>
    <div class="f-notice__body"><p id="products-error-text"></p></div>
</div>

<form class="f-card p-card p-card--new" id="product-new-form" hidden novalidate>
    <h2>محصول تازه</h2>
    <label class="f-field"><span class="f-field__label">نام محصول</span>
        <input class="f-input" name="name" maxlength="200" required placeholder="مثلاً دسترسی کامل بانک سؤالات"></label>
    <label class="f-field"><span class="f-field__label">قیمت (تومان)</span>
        <input class="f-input p-price" name="toman" inputmode="numeric" required placeholder="۱۵۰٬۰۰۰"></label>
    <div class="p-actions">
        <button class="f-btn f-btn--primary" type="submit">ساخت (پیش‌نویس)</button>
        <button class="f-btn f-btn--ghost" type="button" data-cancel>انصراف</button>
    </div>
</form>

<div class="p-list" id="products" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
HTML;

        return $this->renderer->render([
            'title' => 'محصولات و قیمت | فانوس',
            'description' => 'مدیریت محصولات و قیمت‌ها در فانوس.',
            'stylesheets' => ['/assets/web/pages/products-admin.css'],
            'modules' => ['/assets/web/pages/products-admin.js'],
            'viewer' => $viewer,
            'activeNav' => 'products',
        ], $main);
    }
}
