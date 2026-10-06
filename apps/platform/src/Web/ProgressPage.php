<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * داشبورد پیشرفت: how a student is doing, from their own finished exams.
 *
 * The frame is server-rendered; the numbers come from one call to
 * GET /workspaces/{id}/progress (ProgressService) and are drawn by the page
 * script -- the summary, the last twelve weeks, the score trend, each course,
 * the weakest topics (each with a one-tap practice of exactly that topic),
 * and the latest exams.
 */
final class ProgressPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="p-head">
    <h1>پیشرفت من</h1>
    <p class="p-head__links"><a class="f-btn f-btn--ghost" href="/app/points">امتیاز روزانه و رتبه</a></p>
    <p class="f-muted">از روی آزمون‌هایی که تمام کرده‌ای: چقدر، چه روزهایی، کجا خوبی و کجا نه.</p>
</header>

<div class="f-notice f-notice--error" id="progress-error" hidden>
    <div class="f-notice__body"><p id="progress-error-text"></p></div>
</div>

<section class="p-empty f-card" id="progress-empty" hidden>
    <h2>هنوز آزمونی تمام نکرده‌ای</h2>
    <p class="f-muted">اولین آزمونت را که ثبت کنی، این صفحه پر می‌شود.</p>
    <div class="p-empty__actions">
        <a class="f-btn f-btn--primary" href="/app/exams">رفتن به آزمون‌ها</a>
        <a class="f-btn f-btn--ghost" href="/app/exams/custom">آزمون دلخواه بساز</a>
    </div>
</section>

<section class="f-card p-review" id="review" aria-labelledby="review-title" hidden>
    <div class="p-panel__head">
        <h2 id="review-title">مرور هوشمند</h2>
        <a class="p-panel__link" href="/app/timer">تایمر مطالعه</a>
    </div>
    <p class="p-review__line" id="review-line"></p>
    <div class="p-review__topics" id="review-topics"></div>
    <p class="f-tiny" id="review-next"></p>
</section>

<div class="p-board" id="progress-board" aria-busy="true">
    <section class="p-tiles" id="tiles" aria-label="خلاصه">
        <span class="p-skel"></span><span class="p-skel"></span><span class="p-skel"></span><span class="p-skel"></span>
    </section>

    <section class="f-card p-panel" aria-labelledby="activity-title">
        <div class="p-panel__head">
            <h2 id="activity-title">دوازده هفته‌ی اخیر</h2>
            <span class="p-panel__hint" id="streak"></span>
        </div>
        <div class="p-heat" id="heat" role="img" aria-label="تعداد سؤال‌های پاسخ‌داده در هر روز"></div>
        <div class="p-heat__legend" aria-hidden="true">
            <span>کمتر</span><i data-level="0"></i><i data-level="1"></i><i data-level="2"></i><i data-level="3"></i><i data-level="4"></i><span>بیشتر</span>
        </div>
    </section>

    <section class="f-card p-panel" aria-labelledby="trend-title">
        <div class="p-panel__head">
            <h2 id="trend-title">روند نمره</h2>
            <span class="p-panel__hint" id="trend-hint"></span>
        </div>
        <div class="p-trend" id="trend"></div>
    </section>

    <section class="f-card p-panel" aria-labelledby="courses-title">
        <div class="p-panel__head"><h2 id="courses-title">درس‌ها</h2></div>
        <ul class="p-courses" id="courses"></ul>
    </section>

    <section class="f-card p-panel" aria-labelledby="weak-title">
        <div class="p-panel__head"><h2 id="weak-title">مبحث‌هایی که بیشتر غلط می‌زنی</h2></div>
        <ul class="p-weak" id="weak"></ul>
    </section>

    <section class="f-card p-panel p-panel--wide" aria-labelledby="recent-title">
        <div class="p-panel__head">
            <h2 id="recent-title">آخرین آزمون‌ها</h2>
            <a class="p-panel__link" href="/app/exams/mistakes">مرور اشتباه‌ها</a>
        </div>
        <ul class="p-recent" id="recent"></ul>
    </section>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'پیشرفت من | فانوس',
            'description' => 'خلاصه‌ی پیشرفت تو در آزمون‌ها.',
            'stylesheets' => ['/assets/web/pages/progress.css'],
            'modules' => ['/assets/web/pages/progress.js'],
            'viewer' => $viewer,
            'activeNav' => 'progress',
        ], $main);
    }
}
