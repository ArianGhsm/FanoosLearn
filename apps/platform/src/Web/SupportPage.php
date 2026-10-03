<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * پشتیبانی: how to reach FANOOS, and answers to the questions students ask
 * first. The contact links are the platform's own bots (the usernames come
 * from configuration, as on the account page); a platform without one is
 * simply not listed.
 */
final class SupportPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    /** @param array<string, string> $bots platform => bot username */
    public function render(?ViewerContext $viewer, array $bots = []): string
    {
        $links = '';
        foreach (['bale' => ['https://ble.ir/', 'بله'], 'telegram' => ['https://t.me/', 'تلگرام']] as $platform => [$base, $label]) {
            $username = (string) ($bots[$platform] ?? '');
            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{3,31}$/', $username) === 1) {
                $links .= '<a class="f-btn f-btn--ghost" href="' . $this->renderer->escape($base . $username) . '" rel="noopener" target="_blank">پیام در ' . $label . '</a>';
            }
        }
        if ($links === '') {
            $links = '<p class="f-muted">راه تماس به‌زودی این‌جا قرار می‌گیرد.</p>';
        }

        $main = <<<HTML
<header class="b-head">
    <h1>پشتیبانی</h1>
    <p class="f-muted">سؤال، مشکل یا پیشنهادی داری؟ از راه‌های زیر برایمان بنویس.</p>
</header>

<section class="f-card b-panel">
    <h2>تماس با فانوس</h2>
    <div class="b-actions">{$links}</div>
</section>

<section class="f-card b-panel">
    <h2>پرسش‌های پرتکرار</h2>
    <details class="s-faq"><summary>یک سؤال یا پاسخش اشتباه است؛ چه کنم؟</summary>
        <p>داخل آزمون، «ابزار مطالعه» را زیر همان سؤال باز کن و «گزارش اشکال در این سؤال» را بزن. گزارش مستقیم به بازبین‌های بانک می‌رسد.</p></details>
    <details class="s-faq"><summary>چرا گاهی می‌گوید «کمی آهسته‌تر»؟</summary>
        <p>برای این‌که بانک سؤال یک‌جا کپی نشود، سرعت باز کردن سؤال‌های تازه و تعداد سؤال‌های تازه در روز محدود است. برای مطالعه‌ی عادی هیچ‌وقت به این حد نمی‌رسی.</p></details>
    <details class="s-faq"><summary>«رفرنس قدیم» یعنی چه؟</summary>
        <p>یعنی پاسخ رسمی آن سؤال مربوط به ویرایشی از رفرنس است که در منابع امسال عوض شده. منابع هر سال را در «منابع آزمون» می‌بینی.</p></details>
    <details class="s-faq"><summary>رتبه‌ام در کارنامه چطور حساب می‌شود؟</summary>
        <p>اولین تلاش هر نفر در حالت «آزمون» با بقیه مقایسه می‌شود. تا پنج نفر شرکت نکرده باشند رتبه نشان داده نمی‌شود.</p></details>
    <details class="s-faq"><summary>فانوس را مثل اپ نصب کنم؟</summary>
        <p>در کروم اندروید از منوی مرورگر «نصب برنامه» یا «افزودن به صفحه‌ی اصلی» را بزن؛ در آیفون از دکمه‌ی اشتراک‌گذاری سافاری «Add to Home Screen».</p></details>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'پشتیبانی | فانوس',
            'description' => 'راه‌های تماس با پشتیبانی فانوس و پرسش‌های پرتکرار.',
            'stylesheets' => [...($viewer === null ? ['/assets/web/pages/public.css'] : []), '/assets/web/pages/exams.css', '/assets/web/pages/bank.css', '/assets/web/pages/saved.css'],
            'viewer' => $viewer,
            'publicHeader' => $viewer === null ? PublicChrome::header('landing') : '',
        ], $main);
    }
}
