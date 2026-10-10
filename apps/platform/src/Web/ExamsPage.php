<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The exam catalogue: every published assessment in the selected workspace.
 *
 * The frame is server-rendered and the list is filled by the page script, so
 * a slow catalogue shows a real page with a loading state rather than a
 * blank one. Nothing about a particular course, cohort or subject appears
 * here -- every label comes from the API response.
 */
final class ExamsPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="x-catalog__head">
    <div class="x-catalog__head-row">
        <div>
            <h1>آزمون‌ها</h1>
            <p class="f-muted">تمرین کن، و بعد از ثبت برای هر سؤال ببین چرا گزینه‌ی درست، درست است.</p>
        </div>
        <div class="x-catalog__actions">
            <a class="f-btn f-btn--primary" href="/app/exams/custom">آزمون دلخواه بساز</a>
            <a class="f-btn f-btn--ghost" href="/app/exams/mistakes">مرور اشتباه‌ها</a>
            <a class="f-btn f-btn--ghost" href="/app/calendar">تقویم آزمون‌ها</a>
            <a class="f-btn f-btn--ghost" href="/app/plan">برنامه‌ی مطالعه</a>
        </div>
    </div>
</header>

<section class="x-papers" id="papers" aria-labelledby="papers-title" hidden>
    <div class="x-papers__head">
        <h2 id="papers-title">آزمون‌های گذشته، به تفکیک آزمون و سال</h2>
        <div id="goal-slot"></div>
    </div>
    <div id="papers-list"></div>
</section>
<h2 class="x-papers__more" id="catalog-title" hidden>همه‌ی آزمون‌ها و تمرین‌ها، با وضعیت تلاش‌هایت</h2>

<div class="x-catalog__filters" role="group" aria-label="فیلتر آزمون‌ها" id="catalog-filters" hidden>
    <button class="x-chip is-active" type="button" data-kind="">همه</button>
    <button class="x-chip" type="button" data-kind="practice">تمرین</button>
    <button class="x-chip" type="button" data-kind="mock_exam">آزمون آزمایشی</button>
    <button class="x-chip" type="button" data-kind="past_exam">آزمون گذشته</button>
</div>

<div id="catalog" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
    <div class="x-skeleton" aria-hidden="true"></div>
    <p class="f-visually-hidden">در حال خواندن فهرست آزمون‌ها…</p>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'آزمون‌ها | فانوس',
            'description' => 'آزمون‌های فضای آموزشی شما.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/bank.css'],
            'modules' => ['/assets/web/pages/exams.js'],
            'viewer' => $viewer,
            'activeNav' => 'exams',
        ], $main);
    }
}
