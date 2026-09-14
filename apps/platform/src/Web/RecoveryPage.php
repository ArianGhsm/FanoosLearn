<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * Where an owner's recovery link lands: it redeems the token from the URL
 * automatically, then asks for a new password -- never the reverse. The
 * token is never put in a form field or logged from here; it is read once
 * from the address and sent straight to the redeem call.
 */
final class RecoveryPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(): string
    {
        $main = <<<'HTML'
<div class="f-login">
    <div class="f-card f-login__card">
        <div id="recovery-checking">
            <h1>در حال بررسی پیوند…</h1>
            <p class="f-muted">چند لحظه صبر کن.</p>
        </div>

        <div id="recovery-failed" hidden>
            <h1>پیوند نامعتبر است</h1>
            <div class="f-notice f-notice--error">
                <div class="f-notice__body">
                    <p id="recovery-failed-text"></p>
                </div>
            </div>
            <p class="f-tiny f-login__hint">از داخل ربات فانوس یک پیوند تازه بخواه.</p>
        </div>

        <div id="recovery-form-wrap" hidden>
            <h1>تعیین گذرواژه</h1>
            <p class="f-muted">وارد فانوس شدی. یک گذرواژه‌ی تازه برای حساب خودت تعیین کن.</p>

            <div class="f-notice f-notice--error" id="recovery-form-error" hidden>
                <div class="f-notice__body">
                    <p id="recovery-form-error-text"></p>
                </div>
            </div>

            <form id="recovery-form" novalidate>
                <label class="f-field" for="new-password">
                    <span class="f-field__label">گذرواژه‌ی تازه</span>
                    <input class="f-input" id="new-password" name="new_password" type="password"
                           autocomplete="new-password" required minlength="8">
                </label>

                <label class="f-field" for="new-password-confirm">
                    <span class="f-field__label">تکرار گذرواژه</span>
                    <input class="f-input" id="new-password-confirm" name="new_password_confirm" type="password"
                           autocomplete="new-password" required minlength="8">
                </label>

                <button class="f-btn f-btn--primary f-btn--block" type="submit" id="recovery-submit">ثبت گذرواژه و ورود</button>
            </form>
        </div>
    </div>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'بازیابی دسترسی | فانوس',
            'description' => 'بازیابی دسترسی مالک به فانوس.',
            'bodyClass' => 'f-login-page',
            'stylesheets' => ['/assets/web/pages/login.css'],
            'modules' => ['/assets/web/pages/recovery.js'],
        ], $main);
    }
}
