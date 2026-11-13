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
    <h1>آزمون‌ها</h1>
    <p class="f-muted">سالِ آزمون را انتخاب کن؛ هر سؤال را که جواب دادی، پاسخنامه‌اش همان‌جا می‌آید.</p>
</header>

<nav class="x-quick" aria-label="ابزارهای آزمون">
    <a class="x-quick__item is-primary" href="/app/exams/custom">
        <span class="x-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1zM12 8v8M8 12h8"/></svg></span>
        <span class="x-quick__text"><strong>آزمون دلخواه بساز</strong><span>سؤال از درس‌ها و فصل‌هایی که خودت انتخاب می‌کنی</span></span>
        <svg class="x-quick__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </a>
    <a class="x-quick__item" href="/app/exams/mistakes" data-hue="danger">
        <span class="x-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 109-9 9.7 9.7 0 00-6.7 2.8L3 8M3 3v5h5"/></svg></span>
        <span class="x-quick__text"><strong>مرور اشتباه‌ها</strong><span>هر سؤالی که غلط زدی، یک‌جا و با پاسخنامه</span></span>
        <svg class="x-quick__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </a>
    <a class="x-quick__item" href="/app/calendar" data-hue="info">
        <span class="x-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 6h16v15H4zM4 10h16M9 3v4M15 3v4"/></svg></span>
        <span class="x-quick__text"><strong>تقویم آزمون‌ها</strong><span>آزمون‌های زمان‌دار پیش رو</span></span>
        <svg class="x-quick__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </a>
    <a class="x-quick__item" href="/app/plan" data-hue="subject">
        <span class="x-quick__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 6h10M10 12h10M10 18h10M4 6l1.2 1.2L7.5 5M4 12l1.2 1.2L7.5 11M4 18l1.2 1.2L7.5 17"/></svg></span>
        <span class="x-quick__text"><strong>برنامه‌ی مطالعه</strong><span>برنامه‌ی روزانه تا روز آزمون</span></span>
        <svg class="x-quick__chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </a>
</nav>

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
