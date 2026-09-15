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
        <a class="f-btn f-btn--ghost" href="/app/exams/mistakes">مرور اشتباه‌ها</a>
    </div>
</header>

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
            'stylesheets' => ['/assets/web/pages/exams.css'],
            'modules' => ['/assets/web/pages/exams.js'],
            'viewer' => $viewer,
            'activeNav' => 'exams',
        ], $main);
    }
}
