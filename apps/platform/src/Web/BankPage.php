<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * بانک سؤال: the bank by subject and by year (overview), one subject with
 * its topics most-asked first (subject), and the exam references by year
 * (references). Frames are server-rendered; bank.js fills them from
 * GET /workspaces/{id}/bank..., and every label it draws comes from the API.
 */
final class BankPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function overview(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="b-head">
    <h1>بانک سؤال</h1>
    <p class="f-muted">سؤال‌های آزمون‌های گذشته، درس به درس و سال به سال، هر کدام با پاسخ تشریحی و جای پاسخ در رفرنس.</p>
    <div id="goal-slot"></div>
    <div class="b-head__tools">
        <input class="f-input b-search" id="bank-search" type="search" placeholder="جست‌وجوی درس، مبحث یا سال…" autocomplete="off" aria-label="جست‌وجو در بانک">
        <a class="f-btn f-btn--ghost" href="/app/references">منابع آزمون</a>
    </div>
</header>

<div class="f-notice f-notice--error" id="bank-error" hidden>
    <div class="f-notice__body"><p id="bank-error-text"></p></div>
</div>

<div class="b-tabs" role="tablist" aria-label="نمای بانک">
    <button class="x-chip is-active" type="button" role="tab" aria-selected="true" data-view="books">به تفکیک کتاب و فصل</button>
    <button class="x-chip" type="button" role="tab" aria-selected="false" data-view="subjects">به تفکیک درس و مبحث</button>
    <button class="x-chip" type="button" role="tab" aria-selected="false" data-view="years">به تفکیک آزمون و سال</button>
</div>

<div id="bank" data-page="overview" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
    <div class="x-skeleton" aria-hidden="true"></div>
</div>
HTML;

        return $this->page($viewer, 'بانک سؤال', $main);
    }

    public function subject(ViewerContext $viewer, string $subjectKey): string
    {
        $key = $this->renderer->escape($subjectKey);
        $main = <<<HTML
<a class="c-back" href="/app/bank">→ بانک سؤال</a>
<div class="f-notice f-notice--error" id="bank-error" hidden>
    <div class="f-notice__body"><p id="bank-error-text"></p></div>
</div>
<div id="bank" data-page="subject" data-subject="{$key}" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
    <div class="x-skeleton" aria-hidden="true"></div>
</div>
HTML;

        return $this->page($viewer, 'درس | بانک سؤال', $main);
    }

    public function references(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<a class="c-back" href="/app/bank">→ بانک سؤال</a>
<header class="r-hero">
    <div>
        <span class="r-hero__eyebrow">فهرست اعلام‌شده‌ی هر آزمون و هر سال</span>
        <h1>منابع آزمون</h1>
        <p class="r-hero__lead">کتاب‌ها و فصل‌هایی که برای دستیاری، بورد، ارتقا و آزمون ملی هر سال اعلام شده، درس به درس: کدام کتاب، کدام ویرایش، کدام فصل‌ها، و چه چیزی نسبت به سال قبل عوض شده.</p>
    </div>
    <dl class="r-summary" id="ref-summary" aria-live="polite"></dl>
</header>
<div class="f-notice f-notice--error" id="bank-error" hidden>
    <div class="f-notice__body"><p id="bank-error-text"></p></div>
</div>
<div class="r-types" id="ref-types" role="group" aria-label="نوع آزمون" hidden></div>
<nav class="r-years" id="ref-years" role="tablist" aria-label="سال آزمون"></nav>
<div class="r-tools" id="ref-tools" hidden>
    <input class="f-input r-search" id="ref-search" type="search" placeholder="جست‌وجوی درس، کتاب یا نویسنده…" autocomplete="off" aria-label="جست‌وجو در منابع">
    <button class="r-filter" id="ref-changes" type="button" aria-pressed="false">فقط تغییرات</button>
</div>
<div id="bank" data-page="references" aria-busy="true" aria-live="polite">
    <div class="x-skeleton" aria-hidden="true"></div>
    <div class="x-skeleton" aria-hidden="true"></div>
</div>
HTML;

        return $this->page($viewer, 'منابع آزمون', $main);
    }

    private function page(ViewerContext $viewer, string $title, string $main): string
    {
        return $this->renderer->render([
            'title' => $title . ' | فانوس',
            'description' => 'بانک سؤال آزمون دستیاری دندانپزشکی، درس به درس و سال به سال.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/custom-practice.css', '/assets/web/pages/bank.css'],
            'modules' => ['/assets/web/pages/bank.js'],
            'viewer' => $viewer,
            'activeNav' => 'bank',
        ], $main);
    }
}
