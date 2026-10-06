<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * What the selected workspace sells, and a way to buy it. The list and the
 * purchase both come from the commerce API (catalog, orders), so the page
 * shows exactly what the backend would sell and at the price it would charge.
 */
final class StorePage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<h1>فروشگاه</h1>
<p class="f-muted">پرداخت از طریق درگاه امن بانکی انجام می‌شود و دسترسی بلافاصله بعد از پرداخت فعال می‌شود.</p>
<div class="f-notice f-notice--error" id="store-error" hidden>
    <div class="f-notice__body"><p id="store-error-text"></p></div>
</div>
<form class="f-card f-discount" id="discount-form" novalidate>
    <label class="f-discount__label" for="discount-code">کد تخفیف داری؟</label>
    <div class="f-discount__row">
        <input class="f-input" id="discount-code" name="code" autocomplete="off" inputmode="latin" dir="ltr" maxlength="40" placeholder="مثلاً FANOOS20">
        <button class="f-btn f-btn--ghost" type="submit">اعمال</button>
    </div>
    <p class="f-tiny f-discount__note" id="discount-note" aria-live="polite"></p>
</form>
<div class="f-store" id="store" aria-busy="true">
    <p class="f-muted">در حال خواندن…</p>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'فروشگاه | فانوس',
            'description' => 'خرید دسترسی در فانوس.',
            'stylesheets' => ['/assets/web/pages/store.css'],
            'modules' => ['/assets/web/pages/store.js'],
            'viewer' => $viewer,
            'activeNav' => 'store',
        ], $main);
    }
}
