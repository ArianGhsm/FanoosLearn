<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The account page: who you are signed in as, which class you are in, how to
 * change either, and how to sign out.
 *
 * The chrome has linked here since the site was built, but the route did not
 * exist -- so the one button in the header returned 404 and there was
 * nowhere to sign out from at all. Signing out is not a nicety: on a shared
 * or borrowed device it is the only way to end a session, and its absence is
 * a security problem, not a missing convenience.
 */
final class AccountPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $name = $this->renderer->escape($viewer->displayName);
        $workspace = $viewer->workspaceName === null
            ? '<p class="f-muted">هنوز فضای آموزشی‌ای انتخاب نکرده‌ای.</p>'
            : '<p class="a-account__value">' . $this->renderer->escape($viewer->workspaceName) . '</p>';

        $main = <<<HTML
<h1>حساب کاربری</h1>

<section class="f-card a-account">
    <h2>شما</h2>
    <p class="a-account__value">{$name}</p>

    <h2>فضای آموزشی</h2>
    {$workspace}
    <p><a class="f-btn f-btn--ghost" href="/app?switch=1">تغییر فضای آموزشی</a></p>
</section>

<section class="f-card a-account">
    <h2>گذرواژه</h2>
    <p class="f-muted">گذرواژه‌ات را همین‌جا عوض کن. گذرواژه‌ی فعلی لازم نیست جایی ثبت شود جز همین فرم.</p>

    <div class="f-notice f-notice--error" id="password-error" hidden>
        <div class="f-notice__body"><p id="password-error-text"></p></div>
    </div>
    <div class="f-notice f-notice--success" id="password-done" hidden>
        <div class="f-notice__body"><p>گذرواژه عوض شد.</p></div>
    </div>

    <form id="password-form" novalidate>
        <label class="f-field" for="new-password">
            <span class="f-field__label">گذرواژه تازه</span>
            <input class="f-input" id="new-password" name="new_password" type="password"
                   autocomplete="new-password" required>
            <span class="f-field__hint">دست‌کم ۱۲ نویسه.</span>
        </label>
        <label class="f-field" for="repeat-password">
            <span class="f-field__label">تکرار گذرواژه تازه</span>
            <input class="f-input" id="repeat-password" name="repeat_password" type="password"
                   autocomplete="new-password" required>
        </label>
        <button class="f-btn f-btn--primary" type="submit" id="password-submit">ثبت گذرواژه</button>
    </form>
</section>

<section class="f-card a-account">
    <h2>خروج</h2>
    <p class="f-muted">این نشست بسته می‌شود. روی دستگاه مشترک حتماً خارج شو.</p>
    <div class="f-notice f-notice--error" id="signout-error" hidden>
        <div class="f-notice__body"><p id="signout-error-text"></p></div>
    </div>
    <button class="f-btn f-btn--ghost a-account__signout" type="button" id="signout">خروج از حساب</button>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'حساب کاربری | فانوس',
            'description' => 'حساب کاربری شما در فانوس.',
            'stylesheets' => ['/assets/web/pages/account.css'],
            'modules' => ['/assets/web/pages/account.js'],
            'viewer' => $viewer,
            'activeNav' => 'account',
        ], $main);
    }
}
