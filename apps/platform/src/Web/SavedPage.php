<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * ذخیره‌ها و یادداشت‌ها: the questions a student bookmarked (grouped by
 * topic, each group one tap from a study set) and their notes. Also the
 * reviewers' queue of reported mistakes. Both frames are filled by saved.js.
 */
final class SavedPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function saved(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="b-head">
    <h1>ذخیره‌ها و یادداشت‌ها</h1>
    <p class="f-muted">سؤال‌هایی که حین آزمون ذخیره کرده‌ای و یادداشت‌هایت، مبحث به مبحث.</p>
</header>
<div class="f-notice f-notice--error" id="saved-error" hidden>
    <div class="f-notice__body"><p id="saved-error-text"></p></div>
</div>
<div class="b-tabs" role="tablist" aria-label="ذخیره‌ها">
    <button class="x-chip is-active" type="button" role="tab" aria-selected="true" data-view="bookmarks">سؤال‌های ذخیره‌شده</button>
    <button class="x-chip" type="button" role="tab" aria-selected="false" data-view="notes">یادداشت‌ها</button>
</div>
<div id="saved" data-page="saved" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
</div>
HTML;

        return $this->page($viewer, 'ذخیره‌ها و یادداشت‌ها', $main, 'exams');
    }

    public function reports(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="b-head">
    <h1>گزارش‌های اشکال</h1>
    <p class="f-muted">اشکال‌هایی که دانشجوها در سؤال‌ها و پاسخ‌ها پیدا کرده‌اند.</p>
</header>
<div class="f-notice f-notice--error" id="saved-error" hidden>
    <div class="f-notice__body"><p id="saved-error-text"></p></div>
</div>
<div class="b-tabs" role="tablist" aria-label="وضعیت گزارش">
    <button class="x-chip is-active" type="button" role="tab" aria-selected="true" data-status="open">باز</button>
    <button class="x-chip" type="button" role="tab" aria-selected="false" data-status="resolved">رسیدگی‌شده</button>
    <button class="x-chip" type="button" role="tab" aria-selected="false" data-status="rejected">ردشده</button>
</div>
<div id="saved" data-page="reports" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
</div>
HTML;

        return $this->page($viewer, 'گزارش‌های اشکال', $main, 'reports');
    }

    private function page(ViewerContext $viewer, string $title, string $main, string $nav): string
    {
        return $this->renderer->render([
            'title' => $title . ' | فانوس',
            'description' => $title,
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/bank.css', '/assets/web/pages/saved.css'],
            'modules' => ['/assets/web/pages/saved.js'],
            'viewer' => $viewer,
            'activeNav' => $nav,
        ], $main);
    }
}
