<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * مرور اشتباه‌ها: every question this student has answered incorrectly
 * across their own scored attempts in this workspace, in one place.
 *
 * The frame is server-rendered and the list is filled by the page script
 * against ExamService::mistakesReview(), same pattern as ExamsPage --
 * nothing about a particular course or subject is hardcoded here.
 */
final class MistakesReviewPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="x-catalog__head">
    <h1>مرور اشتباه‌ها</h1>
    <p class="f-muted">سؤال‌هایی که در آزمون‌های ثبت‌شده‌ی خودت غلط پاسخ داده‌ای، همه‌جا یک‌جا.</p>
</header>

<div id="mistakes" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
    <div class="x-skeleton" aria-hidden="true"></div>
    <p class="f-visually-hidden">در حال خواندن اشتباه‌های قبلی…</p>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'مرور اشتباه‌ها | فانوس',
            'description' => 'سؤال‌هایی که پیش‌تر غلط پاسخ داده‌ای.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/runner.css'],
            'modules' => ['/assets/web/pages/mistakes-review.js'],
            'viewer' => $viewer,
            'activeNav' => 'exams',
        ], $main);
    }
}
