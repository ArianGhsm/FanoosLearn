<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The public front door, for someone who is not signed in.
 *
 * It says what FANOOS does and offers the two ways in. It still makes no
 * claim about how many people use it and names no university or field: it
 * serves every field, and the fields on offer are data, not copy. The picture
 * beside the headline is drawn in CSS from the runner's own parts, so what a
 * visitor is shown is what they will actually use, not a stock screenshot.
 */
final class LandingPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(): string
    {
        $main = <<<'HTML'
<section class="f-hero">
    <div class="f-hero__copy">
        <span class="f-hero__eyebrow">بانک آزمون · پاسخ تشریحی · مرور اشتباه</span>
        <h1 class="f-hero__title">آزمون‌های رشته‌ات،<br><span class="f-hero__accent">یک‌جا و مرتب</span></h1>
        <p class="f-hero__lede">
            فانوس آزمون‌های دوره‌های گذشته را درس‌به‌درس کنار هم می‌گذارد. تمرین کن،
            پاسخ تشریحی هر سؤال را ببین و اشتباه‌هایت را بعداً دوباره مرور کن.
        </p>
        <div class="f-hero__actions">
            <a class="f-btn f-btn--primary f-btn--lg" href="/register">ساخت حساب</a>
            <a class="f-btn f-btn--ghost f-btn--lg" href="/login">ورود به فانوس</a>
        </div>
        <p class="f-hero__note">برای ساخت حساب فقط نام کاربری و رمز لازم است؛ شماره موبایل را هر وقت خواستی اضافه کن.</p>
    </div>

    <div class="f-hero__stage" aria-hidden="true">
        <div class="f-demo">
            <div class="f-demo__bar">
                <span class="f-demo__step">سؤال <b>۱۲</b> از ۴۰</span>
                <span class="f-demo__timer">۲۴:۱۸</span>
            </div>
            <div class="f-demo__meta">
                <span class="f-demo__chip f-demo__chip--subject">مبحث</span>
                <span class="f-demo__chip f-demo__chip--difficulty">دشواری</span>
                <span class="f-demo__chip f-demo__chip--source">ورودی</span>
            </div>
            <div class="f-demo__stem">
                <span></span><span></span><span class="is-short"></span>
            </div>
            <ol class="f-demo__choices">
                <li><i>الف</i><span></span></li>
                <li class="is-right"><i>ب</i><span></span><svg class="f-demo__tick" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></li>
                <li><i>ج</i><span></span></li>
                <li><i>د</i><span></span></li>
            </ol>
            <div class="f-demo__explain">
                <strong>پاسخ تشریحی</strong>
                <span></span><span class="is-short"></span>
            </div>
        </div>
        <div class="f-demo-rail">
            <span class="is-done"></span><span class="is-done"></span><span class="is-wrong"></span>
            <span class="is-done"></span><span class="is-current"></span><span></span><span></span>
        </div>
    </div>
</section>

<section class="f-features" aria-labelledby="features-title">
    <h2 id="features-title" class="f-section-title">چیزی که فانوس برایت مرتب می‌کند</h2>
    <div class="f-features__grid">
        <article class="f-feature f-feature--subject">
            <span class="f-feature__icon"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5zM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/></svg></span>
            <h3>درس‌به‌درس</h3>
            <p>آزمون‌ها بر اساس درس دسته شده‌اند؛ مستقیم برو سراغ همانی که فردا امتحانش را داری.</p>
        </article>
        <article class="f-feature f-feature--tag">
            <span class="f-feature__icon"><svg viewBox="0 0 24 24"><path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.4 1 1.1 1 1.8V16h5v-.3c0-.7.4-1.4 1-1.8A6 6 0 0 0 12 3z"/></svg></span>
            <h3>پاسخ تشریحی</h3>
            <p>بعد از هر سؤال یا آخر آزمون ببین چرا گزینه‌ی درست، درست است و بقیه چرا نه.</p>
        </article>
        <article class="f-feature f-feature--difficulty">
            <span class="f-feature__icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg></span>
            <h3>سه حالت تمرین</h3>
            <p>آموزشی با جواب فوری، تمرینی آزاد، یا زمان‌دار درست مثل جلسه‌ی امتحان.</p>
        </article>
        <article class="f-feature f-feature--source">
            <span class="f-feature__icon"><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5"/><path d="M12 8v4l3 2"/></svg></span>
            <h3>مرور اشتباه‌ها</h3>
            <p>سؤال‌هایی که غلط زدی کنار گذاشته می‌شوند تا دوباره و فقط سراغ همان‌ها بروی.</p>
        </article>
    </div>
</section>

<section class="f-steps" aria-labelledby="steps-title">
    <h2 id="steps-title" class="f-section-title">سه قدم تا اولین آزمون</h2>
    <ol class="f-steps__list">
        <li class="f-step">
            <span class="f-step__num">۱</span>
            <div><h3>حساب بساز</h3><p>نام کاربری و رمز؛ همین.</p></div>
        </li>
        <li class="f-step">
            <span class="f-step__num">۲</span>
            <div><h3>رشته‌ات را بگو</h3><p>آزمون‌های رشته‌ات، از هر دانشگاه و هر ورودی، برایت باز می‌شود.</p></div>
        </li>
        <li class="f-step">
            <span class="f-step__num">۳</span>
            <div><h3>شروع کن</h3><p>روی گوشی یا لپ‌تاپ؛ پیشرفتت همراهت می‌ماند.</p></div>
        </li>
    </ol>
</section>

<section class="f-cta">
    <div>
        <h2>آماده‌ای؟</h2>
        <p>ساخت حساب کمتر از یک دقیقه طول می‌کشد.</p>
    </div>
    <a class="f-btn f-btn--light f-btn--lg" href="/register">ساخت حساب</a>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'فانوس | آزمون‌های رشته‌ات، یک‌جا',
            'description' => 'فانوس؛ آزمون‌های دوره‌های گذشته درس‌به‌درس، با پاسخ تشریحی و مرور اشتباه‌ها.',
            'bodyClass' => 'f-landing-page f-public',
            'stylesheets' => ['/assets/web/pages/public.css', '/assets/web/pages/landing.css'],
            'publicHeader' => PublicChrome::header('landing'),
        ], $main);
    }
}
