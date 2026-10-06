<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * کدهای تخفیف و جعبه‌های سکه: the owner's discount codes and the boxes
 * students buy with coins (docs/product/08_ENGAGEMENT.md). Drawn by
 * discounts-admin.js from the admin discount-code and coin-offer endpoints.
 */
final class DiscountsAdminPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $form = static fn (string $id, string $title, string $extra, string $submit): string => <<<HTML
<form class="f-card p-card p-card--new" id="{$id}" novalidate>
    <h2>{$title}</h2>
    <div class="p-grid">
        {$extra}
        <label class="f-field"><span class="f-field__label">نوع تخفیف</span>
            <select class="f-input" name="kind"><option value="percent">درصدی</option><option value="amount">مبلغ ثابت (تومان)</option></select></label>
        <label class="f-field"><span class="f-field__label">مقدار</span>
            <input class="f-input" name="value" inputmode="numeric" required placeholder="مثلاً ۲۰"></label>
        <label class="f-field"><span class="f-field__label">فقط برای محصول</span>
            <select class="f-input" name="product_id" data-products><option value="">همه‌ی محصولات</option></select></label>
    </div>
    <div class="p-actions"><button class="f-btn f-btn--primary" type="submit">{$submit}</button></div>
</form>
HTML;

        $codeFields = <<<'HTML'
<label class="f-field"><span class="f-field__label">کد (حروف و عدد لاتین)</span>
            <input class="f-input" name="code" dir="ltr" maxlength="32" required placeholder="FANOOS20"></label>
        <label class="f-field"><span class="f-field__label">عنوان (برای خودت)</span>
            <input class="f-input" name="label" maxlength="120" required placeholder="تخفیف شروع ترم"></label>
        <label class="f-field"><span class="f-field__label">سقف کل استفاده (خالی = بی‌سقف)</span>
            <input class="f-input" name="max_uses" inputmode="numeric"></label>
        <label class="f-field"><span class="f-field__label">هر نفر چند بار</span>
            <input class="f-input" name="per_user_limit" inputmode="numeric" value="1"></label>
        <label class="f-field"><span class="f-field__label">اعتبار (روز، خالی = بی‌پایان)</span>
            <input class="f-input" name="valid_days" inputmode="numeric"></label>
HTML;
        $offerFields = <<<'HTML'
<label class="f-field"><span class="f-field__label">عنوان جعبه</span>
            <input class="f-input" name="title" maxlength="120" required placeholder="کد تخفیف اشتراک یک‌ساله"></label>
        <label class="f-field"><span class="f-field__label">چند سکه</span>
            <input class="f-input" name="coins" inputmode="numeric" required placeholder="۱۰"></label>
        <label class="f-field"><span class="f-field__label">کد چند روز معتبر باشد</span>
            <input class="f-input" name="code_valid_days" inputmode="numeric" value="3"></label>
HTML;

        $codeForm = $form('code-form', 'کد تخفیف تازه', $codeFields, 'ساخت کد');
        $offerForm = $form('offer-form', 'جعبه‌ی سکه‌ی تازه', $offerFields, 'ساخت جعبه');

        $main = <<<HTML
<div class="p-head">
    <div>
        <h1>کدهای تخفیف و جعبه‌های سکه</h1>
        <p class="f-muted">کدهایی که خودت می‌دهی، و جعبه‌هایی که دانشجو با سکه‌ی امتیاز روزانه‌اش کد تخفیف شخصی می‌خرد. تخفیف هیچ‌وقت مبلغ را زیر ۱٬۰۰۰ تومان نمی‌برد.</p>
    </div>
    <div class="p-head__actions">
        <a class="f-btn f-btn--ghost" href="/app/admin/affiliate">همکاری در فروش</a>
        <a class="f-btn f-btn--ghost" href="/app/admin/products">محصولات و قیمت</a>
    </div>
</div>

<div class="f-notice f-notice--error" id="discounts-error" hidden>
    <div class="f-notice__body"><p id="discounts-error-text"></p></div>
</div>

<section aria-labelledby="codes-title">
    <h2 id="codes-title">کدهای تخفیف</h2>
    {$codeForm}
    <div class="p-list" id="codes" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
</section>

<section aria-labelledby="offers-title" class="d-section">
    <h2 id="offers-title">جعبه‌های سکه</h2>
    {$offerForm}
    <div class="p-list" id="offers" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'کدهای تخفیف | فانوس',
            'description' => 'کدهای تخفیف و جعبه‌های سکه در فانوس.',
            'stylesheets' => ['/assets/web/pages/products-admin.css'],
            'modules' => ['/assets/web/pages/discounts-admin.js'],
            'viewer' => $viewer,
            'activeNav' => 'products',
        ], $main);
    }
}
