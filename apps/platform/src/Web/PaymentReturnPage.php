<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * Where the payment gateway sends the payer back.
 *
 * The outcome shown here is FANOOS's own, decided server-side by asking the
 * gateway directly (CommerceService::handleCallback -> gateway verify); the
 * query string the payer's browser arrives with is never believed on its
 * own. A payment that could not be checked yet is "being checked", not
 * "failed": the bank may well have taken the money, and reconciliation
 * settles it.
 */
final class PaymentReturnPage
{
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const PENDING = 'pending';

    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(?ViewerContext $viewer, string $outcome, ?string $orderId = null): string
    {
        [$icon, $title, $text, $tone] = match ($outcome) {
            self::PAID => ['✓', 'پرداخت انجام شد', 'دسترسی‌ات فعال شد. می‌توانی همین حالا از آن استفاده کنی.', 'success'],
            self::PENDING => ['…', 'پرداختت در حال بررسی است', 'هنوز جواب قطعی از درگاه نگرفته‌ایم. اگر مبلغ از حسابت کم شده، نگران نباش؛ حداکثر تا چند دقیقه‌ی دیگر دسترسی‌ات فعال می‌شود یا مبلغ به حسابت برمی‌گردد.', 'warning'],
            default => ['✕', 'پرداخت انجام نشد', 'پرداخت لغو شد یا بانک آن را تأیید نکرد. اگر مبلغی از حسابت کم شده، طبق قانون حداکثر تا ۷۲ ساعت به حسابت برمی‌گردد.', 'error'],
        };
        $reference = $orderId === null ? '' : '<p class="f-tiny">شماره‌ی سفارش: <code dir="ltr">' . $this->renderer->escape(substr(str_replace('-', '', $orderId), -12)) . '</code></p>';
        $back = $viewer === null
            ? '<a class="f-btn f-btn--primary f-btn--lg" href="/login">ورود به فانوس</a>'
            : '<a class="f-btn f-btn--primary f-btn--lg" href="/app">بازگشت به فانوس</a> <a class="f-btn f-btn--ghost f-btn--lg" href="/app/store">فروشگاه</a>';

        $main = <<<HTML
<section class="f-card f-pay f-pay--{$tone}" aria-live="polite">
    <span class="f-pay__icon" aria-hidden="true">{$icon}</span>
    <h1 class="f-pay__title">{$title}</h1>
    <p class="f-pay__text">{$text}</p>
    {$reference}
    <div class="f-pay__actions">{$back}</div>
</section>
HTML;

        return $this->renderer->render([
            'title' => $title . ' | فانوس',
            'description' => 'نتیجه‌ی پرداخت در فانوس.',
            'bodyClass' => 'f-pay-page' . ($viewer === null ? ' f-public' : ''),
            'stylesheets' => ['/assets/web/pages/public.css', '/assets/web/pages/store.css'],
            'viewer' => $viewer,
            'publicHeader' => $viewer === null ? PublicChrome::header('landing') : '',
        ], $main);
    }
}
