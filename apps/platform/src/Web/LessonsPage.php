<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * درسنامه‌ها: the workspace's published lessons, summaries, flashcards and
 * slides (content resources), each opened in a reader; files are fetched
 * through the protected delivery flow, carrying the reader's watermark.
 *
 * Whoever may author (resource.create) also gets a composer for a written
 * lesson, and the drafts list with the existing draft -> review (by someone
 * else, resource.review) -> publish (resource.publish) steps. Everything goes
 * through the content API; this page stores nothing of its own.
 */
final class LessonsPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function library(ViewerContext $viewer): string
    {
        $roles = $this->renderer->escape(implode(' ', array_filter([
            $viewer->can('resource.create') ? 'author' : null,
            $viewer->can('resource.review') ? 'reviewer' : null,
            $viewer->can('resource.publish') ? 'publisher' : null,
        ])));
        $composer = !$viewer->can('resource.create') ? '' : <<<'HTML'
<details class="f-card b-panel l-compose" id="compose-box">
    <summary><strong>درسنامه‌ی تازه</strong></summary>
    <form id="compose" novalidate>
        <input class="f-input" id="lesson-title" maxlength="255" placeholder="عنوان" aria-label="عنوان" required>
        <div class="b-actions">
            <select class="f-input" id="lesson-type" aria-label="نوع">
                <option value="lecture_note">درسنامه</option>
                <option value="summary">خلاصه</option>
                <option value="cheat_sheet">جمع‌بندی و نکات طلایی</option>
                <option value="flashcards">فلش‌کارت</option>
            </select>
            <input class="f-input" id="lesson-topic" maxlength="120" placeholder="درس یا مبحث (مثلاً اندودانتیکس)" aria-label="مبحث">
        </div>
        <textarea class="f-input" id="lesson-body" rows="10" placeholder="متن درسنامه. تیترها با ## و فهرست‌ها با - شروع می‌شوند." aria-label="متن" required></textarea>
        <div class="b-actions"><button class="f-btn f-btn--primary" type="submit" id="lesson-save">ذخیره به‌صورت پیش‌نویس</button></div>
        <p class="f-tiny" id="lesson-status" aria-live="polite"></p>
    </form>
</details>
HTML;

        $main = <<<HTML
<header class="b-head">
    <h1>درسنامه‌ها</h1>
    <p class="f-muted">درسنامه، خلاصه، جمع‌بندی و فلش‌کارت، کنار بانک سؤال.</p>
    <div class="b-head__tools">
        <input class="f-input b-search" id="lesson-search" type="search" placeholder="جست‌وجوی درسنامه…" autocomplete="off" aria-label="جست‌وجو">
    </div>
</header>
<div class="f-notice f-notice--error" id="lessons-error" hidden>
    <div class="f-notice__body"><p id="lessons-error-text"></p></div>
</div>
{$composer}
<div class="b-tabs" role="tablist" aria-label="نوع">
    <button class="x-chip is-active" type="button" data-kind="">همه</button>
    <button class="x-chip" type="button" data-kind="lesson">درسنامه</button>
    <button class="x-chip" type="button" data-kind="summary">خلاصه و جمع‌بندی</button>
    <button class="x-chip" type="button" data-kind="flashcards">فلش‌کارت</button>
    <button class="x-chip" type="button" data-kind="other">سایر</button>
</div>
<div id="lessons" data-page="library" data-roles="{$roles}" aria-busy="true" aria-live="polite"><div class="x-skeleton" aria-hidden="true"></div></div>
HTML;

        return $this->page($viewer, 'درسنامه‌ها', $main);
    }

    public function lesson(ViewerContext $viewer, string $resourceId): string
    {
        $id = $this->renderer->escape($resourceId);
        $mark = $this->renderer->escape(mb_substr(trim($viewer->displayName), 0, 24) . ' · ' . substr($viewer->userId, -6));
        $main = <<<HTML
<a class="c-back" href="/app/lessons">→ درسنامه‌ها</a>
<div class="f-notice f-notice--error" id="lessons-error" hidden>
    <div class="f-notice__body"><p id="lessons-error-text"></p></div>
</div>
<article class="f-card l-reader" id="lessons" data-page="lesson" data-resource="{$id}" data-watermark="{$mark}" aria-busy="true">
    <div class="x-skeleton" aria-hidden="true"></div>
</article>
HTML;

        return $this->page($viewer, 'درسنامه', $main);
    }

    private function page(ViewerContext $viewer, string $title, string $main): string
    {
        return $this->renderer->render([
            'title' => $title . ' | فانوس',
            'description' => 'درسنامه‌ها و خلاصه‌های آزمون دستیاری دندانپزشکی.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/custom-practice.css', '/assets/web/pages/bank.css', '/assets/web/pages/lessons.css'],
            'modules' => ['/assets/web/pages/lessons.js'],
            'viewer' => $viewer,
            'activeNav' => 'bank',
        ], $main);
    }
}
