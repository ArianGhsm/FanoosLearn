<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The sign-in page.
 *
 * The form is a real <form> with real inputs, so a password manager can fill
 * it and the browser can offer to save it. JavaScript upgrades the submit to
 * a fetch so errors appear in place; without it the page still explains what
 * to do rather than silently failing.
 */
final class LoginPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(): string
    {
        $lantern = PublicChrome::LANTERN;
        $main = <<<HTML
<div class="f-auth f-auth--narrow">
    <aside class="f-auth__panel">
        {$lantern}
        <h1>دوباره خوش آمدی</h1>
        <p>از همان‌جا که ماندی ادامه بده.</p>
        <ul class="f-auth__perks">
            <li>آزمون‌های نیمه‌کاره منتظرت هستند</li>
            <li>اشتباه‌هایت برای مرور کنار گذاشته شده‌اند</li>
            <li>روی گوشی و لپ‌تاپ، یک حساب</li>
        </ul>
    </aside>

    <section class="f-auth__body">
        <h2 class="f-auth__title">ورود به فانوس</h2>
        <p class="f-auth__sub">با نام کاربری و رمزت وارد شو.</p>

        <div class="f-notice f-notice--error" id="login-error" hidden>
            <div class="f-notice__body">
                <div class="f-notice__title">ورود انجام نشد</div>
                <p id="login-error-text"></p>
            </div>
        </div>

        <form id="login-form" method="post" action="/api/v1/auth/login" novalidate>
            <label class="f-field" for="identifier">
                <span class="f-field__label">نام کاربری</span>
                <input class="f-input f-input--ltr" id="identifier" name="identifier" type="text"
                       autocomplete="username" required autocapitalize="none" spellcheck="false">
            </label>

            <label class="f-field" for="password">
                <span class="f-field__label">رمز عبور</span>
                <span class="f-secret">
                    <input class="f-input f-input--ltr" id="password" name="password" type="password"
                           autocomplete="current-password" required>
                    <button class="f-secret__toggle" type="button" data-reveal="password" aria-label="نمایش رمز" aria-pressed="false">
                        <svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
            </label>

            <button class="f-btn f-btn--primary f-btn--block f-btn--lg" type="submit" id="login-submit">ورود</button>
        </form>

        <p class="f-auth__foot">حساب نداری؟ <a href="/register">همین حالا بساز</a></p>
    </section>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'ورود | فانوس',
            'description' => 'ورود به فضای آموزشی فانوس.',
            'bodyClass' => 'f-login-page f-auth-page f-public',
            'stylesheets' => ['/assets/web/pages/public.css', '/assets/web/pages/auth.css'],
            'modules' => ['/assets/web/pages/login.js'],
            'publicHeader' => PublicChrome::header('login'),
        ], $main);
    }
}
