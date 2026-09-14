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
        $main = <<<'HTML'
<div class="f-login">
    <div class="f-card f-login__card">
        <h1>ورود به فانوس</h1>
        <p class="f-muted">با همان حسابی وارد شو که در ربات به آن متصل شده‌ای.</p>

        <div class="f-notice f-notice--error" id="login-error" hidden>
            <div class="f-notice__body">
                <div class="f-notice__title">ورود انجام نشد</div>
                <p id="login-error-text"></p>
            </div>
        </div>

        <form id="login-form" method="post" action="/api/v1/auth/login" novalidate>
            <label class="f-field" for="identifier">
                <span class="f-field__label">شناسه</span>
                <input class="f-input" id="identifier" name="identifier" type="text"
                       autocomplete="username" required autocapitalize="none" spellcheck="false">
            </label>

            <label class="f-field" for="password">
                <span class="f-field__label">گذرواژه</span>
                <input class="f-input" id="password" name="password" type="password"
                       autocomplete="current-password" required>
            </label>

            <button class="f-btn f-btn--primary f-btn--block" type="submit" id="login-submit">ورود</button>
        </form>

        <p class="f-tiny f-login__hint">
            اگر حساب نداری، از داخل ربات فانوس شروع کن؛ عضویت از همان‌جا انجام می‌شود.
        </p>
    </div>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'ورود | فانوس',
            'description' => 'ورود به فضای آموزشی فانوس.',
            'bodyClass' => 'f-login-page',
            'stylesheets' => ['/assets/web/pages/login.css'],
            'modules' => ['/assets/web/pages/login.js'],
        ], $main);
    }
}
