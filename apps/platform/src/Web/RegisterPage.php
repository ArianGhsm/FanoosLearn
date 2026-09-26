<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * Website sign-up: the bot's join questions, as three short steps.
 *
 * Account (name, username, password), then field of study, then university
 * and entry details -- the last step entirely optional. No phone number: the
 * owner decided sign-up must not depend on an SMS arriving; one can be added
 * to the account later.
 *
 * Every step is in the document from the start. The script shows one at a
 * time; without it all three show at once and the page still reads top to
 * bottom as one form.
 */
final class RegisterPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(): string
    {
        $lantern = PublicChrome::LANTERN;
        $eye = '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>';
        $main = <<<HTML
<div class="f-auth">
    <aside class="f-auth__panel">
        {$lantern}
        <h1>به فانوس خوش آمدی</h1>
        <p>سه قدم کوتاه، و آزمون‌های رشته‌ات باز می‌شود.</p>
        <ol class="f-auth__track" id="register-track">
            <li class="is-current"><span>۱</span><em>حساب کاربری</em></li>
            <li><span>۲</span><em>رشته</em></li>
            <li><span>۳</span><em>دانشگاه و ورودی</em></li>
        </ol>
    </aside>

    <section class="f-auth__body">
        <div class="f-notice f-notice--error" id="register-error" hidden>
            <div class="f-notice__body">
                <div class="f-notice__title">ثبت‌نام انجام نشد</div>
                <p id="register-error-text"></p>
            </div>
        </div>

        <form id="register-form" novalidate>
            <fieldset class="f-wizard__step" data-step="1">
                <legend class="f-wizard__legend">حساب کاربری</legend>
                <p class="f-auth__sub">با همین نام کاربری و رمز وارد می‌شوی.</p>

                <div class="f-auth__row">
                    <label class="f-field" for="first_name">
                        <span class="f-field__label">نام</span>
                        <input class="f-input" id="first_name" name="first_name" type="text" autocomplete="given-name" maxlength="80" required>
                    </label>
                    <label class="f-field" for="last_name">
                        <span class="f-field__label">نام خانوادگی</span>
                        <input class="f-input" id="last_name" name="last_name" type="text" autocomplete="family-name" maxlength="80" required>
                    </label>
                </div>

                <label class="f-field" for="username">
                    <span class="f-field__label">نام کاربری</span>
                    <input class="f-input f-input--ltr" id="username" name="username" type="text" autocomplete="username"
                           autocapitalize="none" spellcheck="false" maxlength="32" required aria-describedby="username-hint">
                    <span class="f-field__hint" id="username-hint">حروف انگلیسی، عدد، نقطه یا زیرخط — مثلاً sara.ahmadi</span>
                </label>

                <label class="f-field" for="new-password">
                    <span class="f-field__label">رمز عبور</span>
                    <span class="f-secret">
                        <input class="f-input f-input--ltr" id="new-password" name="password" type="password"
                               autocomplete="new-password" minlength="8" maxlength="128" required aria-describedby="password-hint">
                        <button class="f-secret__toggle" type="button" data-reveal="new-password" aria-label="نمایش رمز" aria-pressed="false">{$eye}</button>
                    </span>
                    <span class="f-strength" id="password-strength" data-level="0" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                    <span class="f-field__hint" id="password-hint">دست‌کم ۸ نویسه.</span>
                </label>

                <div class="f-wizard__nav">
                    <button class="f-btn f-btn--primary f-btn--lg" type="button" data-next>ادامه</button>
                </div>
            </fieldset>

            <fieldset class="f-wizard__step" data-step="2">
                <legend class="f-wizard__legend">رشته‌ات چیست؟</legend>
                <p class="f-auth__sub">آزمون‌های همین رشته برایت باز می‌شود؛ از هر دانشگاه و هر ورودی.</p>
                <div class="f-tiles" id="discipline-options" role="radiogroup" aria-label="رشته" aria-busy="true">
                    <p class="f-muted">در حال خواندن فهرست رشته‌ها…</p>
                </div>
                <div class="f-wizard__nav">
                    <button class="f-btn f-btn--ghost f-btn--lg" type="button" data-back>قبلی</button>
                    <button class="f-btn f-btn--primary f-btn--lg" type="button" data-next>ادامه</button>
                </div>
            </fieldset>

            <fieldset class="f-wizard__step" data-step="3">
                <legend class="f-wizard__legend">دانشگاه و ورودی</legend>
                <p class="f-auth__sub">همه‌ی این‌ها اختیاری است؛ بعداً برای آزمون‌های مخصوص دانشگاه و ورودی‌ات به کار می‌آید.</p>

                <div class="f-auth__row">
                    <label class="f-field" for="province">
                        <span class="f-field__label">استان <span class="f-optional">(اختیاری)</span></span>
                        <select class="f-input f-select" id="province" name="province">
                            <option value="">انتخاب کن</option>
                        </select>
                    </label>
                    <label class="f-field" for="institution">
                        <span class="f-field__label">دانشگاه <span class="f-optional">(اختیاری)</span></span>
                        <select class="f-input f-select" id="institution" name="institution_id" disabled>
                            <option value="">اول استان را انتخاب کن</option>
                        </select>
                    </label>
                </div>

                <div class="f-auth__row">
                    <label class="f-field" for="entry_year">
                        <span class="f-field__label">سال ورود <span class="f-optional">(اختیاری)</span></span>
                        <select class="f-input f-select" id="entry_year" name="entry_year">
                            <option value="">انتخاب کن</option>
                        </select>
                    </label>
                    <label class="f-field" for="student_number">
                        <span class="f-field__label">شماره دانشجویی <span class="f-optional">(اختیاری)</span></span>
                        <input class="f-input f-input--ltr" id="student_number" name="student_number" type="text" inputmode="numeric" maxlength="20">
                    </label>
                </div>

                <div class="f-field">
                    <span class="f-field__label">نیمسال ورود <span class="f-optional">(اختیاری)</span></span>
                    <div class="f-segment" role="radiogroup" aria-label="نیمسال ورود">
                        <label><input type="radio" name="entry_term" value="first">نیمسال اول</label>
                        <label><input type="radio" name="entry_term" value="second">نیمسال دوم</label>
                    </div>
                </div>

                <div class="f-field">
                    <span class="f-field__label">نوع دوره <span class="f-optional">(اختیاری)</span></span>
                    <div class="f-segment" role="radiogroup" aria-label="نوع دوره">
                        <label><input type="radio" name="course_type" value="daily">روزانه / تعهدی</label>
                        <label><input type="radio" name="course_type" value="tuition">شهریه‌پرداز</label>
                        <label><input type="radio" name="course_type" value="international">بین‌الملل</label>
                    </div>
                </div>

                <dl class="f-recap" id="register-recap"></dl>

                <div class="f-wizard__nav">
                    <button class="f-btn f-btn--ghost f-btn--lg" type="button" data-back>قبلی</button>
                    <button class="f-btn f-btn--primary f-btn--lg" type="submit" id="register-submit">ساخت حساب</button>
                </div>
            </fieldset>
        </form>

        <p class="f-auth__foot">قبلاً حساب ساخته‌ای؟ <a href="/login">وارد شو</a></p>
    </section>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'ساخت حساب | فانوس',
            'description' => 'ساخت حساب در فانوس با نام کاربری و رمز؛ آزمون‌های رشته‌ات باز می‌شود.',
            'bodyClass' => 'f-register-page f-auth-page f-public',
            'stylesheets' => ['/assets/web/pages/public.css', '/assets/web/pages/auth.css'],
            'modules' => ['/assets/web/pages/register.js'],
            'publicHeader' => PublicChrome::header('register'),
        ], $main);
    }
}
