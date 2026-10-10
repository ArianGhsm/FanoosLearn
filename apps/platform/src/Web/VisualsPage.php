<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * نقشه‌ها و خلاصه‌ها: the visual study material -- mind maps, flowcharts,
 * diagrams, tables and capsules -- as a library (by subject and kind, or one
 * chapter's) and one visual at a time. Frames are server-rendered; visuals.js
 * fills them from GET /workspaces/{id}/bank/visuals...
 */
final class VisualsPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function library(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="v-head">
    <h1>نقشه‌ها و خلاصه‌ها</h1>
    <p class="f-muted">نقشه‌های ذهنی، فلوچارت‌ها، جدول‌ها و کپسول‌های هر فصل، ساخته‌شده از همان صفحه‌های کتاب مرجع.</p>
    <div class="v-tools">
        <input class="f-input v-search" id="visual-search" type="search" placeholder="جست‌وجوی عنوان یا درس…" autocomplete="off" aria-label="جست‌وجو در نقشه‌ها">
    </div>
    <div class="v-kinds" id="visual-kinds" role="group" aria-label="نوع"></div>
</header>
<div class="f-notice f-notice--error" id="visual-error" hidden>
    <div class="f-notice__body"><p id="visual-error-text"></p></div>
</div>
<div id="visuals" data-page="library" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
</div>
HTML;

        return $this->page($viewer, 'نقشه‌ها و خلاصه‌ها', $main);
    }

    public function visual(ViewerContext $viewer, string $key): string
    {
        $safe = $this->renderer->escape($key);
        $main = <<<HTML
<a class="c-back" href="/app/visuals">→ نقشه‌ها و خلاصه‌ها</a>
<div class="f-notice f-notice--error" id="visual-error" hidden>
    <div class="f-notice__body"><p id="visual-error-text"></p></div>
</div>
<div id="visuals" data-page="visual" data-key="{$safe}" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
</div>
HTML;

        return $this->page($viewer, 'نقشه', $main);
    }

    private function page(ViewerContext $viewer, string $title, string $main): string
    {
        return $this->renderer->render([
            'title' => $title . ' | فانوس',
            'description' => 'نقشه‌های ذهنی و خلاصه‌های فصل به فصل منابع آزمون دندانپزشکی.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/visuals.css'],
            'modules' => ['/assets/web/pages/visuals.js'],
            'viewer' => $viewer,
            'activeNav' => 'bank',
        ], $main);
    }
}
