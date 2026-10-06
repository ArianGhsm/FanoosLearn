<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * برنامه همکاری در فروش: the student's referral link, the programme's terms,
 * and what the link brought (counts and money, never who). For the owner,
 * adminPage() is the programme's settings and payouts. Both drawn by
 * affiliate.js.
 */
final class AffiliatePage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="p-head">
    <h1>کسب درآمد با معرفی فانوس</h1>
    <p class="f-muted">لینک اختصاصی‌ات را برای دوستانت بفرست. هر کس با آن ثبت‌نام کند و خرید کند، درصدی از خریدش مال تو می‌شود.</p>
</header>

<div class="f-notice f-notice--error" id="aff-error" hidden>
    <div class="f-notice__body"><p id="aff-error-text"></p></div>
</div>

<div class="a-board" id="aff" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
HTML;

        return $this->renderer->render([
            'title' => 'همکاری در فروش | فانوس',
            'description' => 'لینک معرفی و درآمد تو در فانوس.',
            'stylesheets' => ['/assets/web/pages/progress.css', '/assets/web/pages/affiliate.css'],
            'modules' => ['/assets/web/pages/affiliate.js'],
            'viewer' => $viewer,
            'activeNav' => 'account',
        ], $main);
    }

    public function adminPage(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="p-head">
    <h1>برنامه همکاری در فروش</h1>
    <p class="f-muted">روشن یا خاموشش کن، درصد و مدت را تعیین کن و پرداخت به هر همکار را ثبت کن. پرداخت خودش بیرون از سایت انجام می‌شود.</p>
</header>

<div class="f-notice f-notice--error" id="aff-error" hidden>
    <div class="f-notice__body"><p id="aff-error-text"></p></div>
</div>

<form class="f-card a-settings" id="aff-settings" novalidate>
    <label class="a-switch"><input type="checkbox" name="enabled"> <span>برنامه روشن است</span></label>
    <label class="f-field"><span class="f-field__label">پورسانت (درصد از مبلغ پرداخت‌شده)</span>
        <input class="f-input" name="commission_percent" inputmode="numeric" required></label>
    <label class="f-field"><span class="f-field__label">تا چند روز بعد از ثبت‌نام خرید پورسانت دارد</span>
        <input class="f-input" name="attribution_days" inputmode="numeric" required></label>
    <button class="f-btn f-btn--primary" type="submit">ذخیره</button>
    <p class="f-tiny" id="aff-saved" aria-live="polite"></p>
</form>

<section aria-labelledby="aff-list-title">
    <h2 id="aff-list-title">همکاران</h2>
    <div class="a-list" id="aff-list" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'همکاری در فروش | فانوس',
            'description' => 'تنظیمات برنامه‌ی همکاری در فروش.',
            'stylesheets' => ['/assets/web/pages/progress.css', '/assets/web/pages/affiliate.css'],
            'modules' => ['/assets/web/pages/affiliate.js'],
            'viewer' => $viewer,
            'activeNav' => 'products',
        ], $main);
    }
}
