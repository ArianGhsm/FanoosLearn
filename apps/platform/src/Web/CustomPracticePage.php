<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * آزمون دلخواه: the builder. Courses, then topics, then which questions (all,
 * not yet seen, got wrong), then how many and whether timed -- and the exam
 * is built and opened in the ordinary runner. Below, the student's earlier
 * custom exams.
 */
final class CustomPracticePage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="c-head">
    <a class="c-back" href="/app/exams">→ آزمون‌ها</a>
    <h1>آزمون دلخواه</h1>
    <p class="f-muted">خودت بچین: از چه درس‌ها و مبحث‌هایی، چند سؤال، و فقط سؤال‌هایی که ندیده‌ای یا غلط زده‌ای.</p>
</header>

<div class="f-notice f-notice--error" id="custom-error" hidden>
    <div class="f-notice__body"><p id="custom-error-text"></p></div>
</div>

<form class="c-builder" id="custom-form" novalidate>
    <section class="f-card c-step" aria-labelledby="step-courses">
        <div class="c-step__head">
            <span class="c-step__num" aria-hidden="true">۱</span>
            <h2 id="step-courses">درس‌ها</h2>
            <span class="c-step__hint" id="courses-picked"></span>
        </div>
        <input class="f-input c-search" id="course-search" type="search" placeholder="جست‌وجوی درس…" autocomplete="off">
        <div class="c-chips" id="courses" role="group" aria-label="درس‌ها" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
    </section>

    <section class="f-card c-step" aria-labelledby="step-topics" id="topics-step" hidden>
        <div class="c-step__head">
            <span class="c-step__num" aria-hidden="true">۲</span>
            <h2 id="step-topics">مبحث‌ها <span class="f-optional">(اختیاری)</span></h2>
            <button class="c-link" type="button" id="topics-clear" hidden>همه‌ی مبحث‌ها</button>
        </div>
        <p class="f-tiny">چیزی انتخاب نکنی، از همه‌ی مبحث‌های این درس‌ها می‌آید.</p>
        <div class="c-chips c-chips--topics" id="topics" role="group" aria-label="مبحث‌ها"></div>
    </section>

    <section class="f-card c-step" aria-labelledby="step-source" id="source-step" hidden>
        <div class="c-step__head">
            <span class="c-step__num" aria-hidden="true">۳</span>
            <h2 id="step-source">کدام سؤال‌ها؟</h2>
        </div>
        <div class="c-sources" role="radiogroup" aria-label="کدام سؤال‌ها">
            <label class="c-source"><input type="radio" name="source" value="all" checked><strong>همه</strong><span id="count-all"></span></label>
            <label class="c-source"><input type="radio" name="source" value="unseen"><strong>ندیده‌ها</strong><span id="count-unseen"></span></label>
            <label class="c-source"><input type="radio" name="source" value="wrong"><strong>غلط‌ها</strong><span id="count-wrong"></span></label>
        </div>
    </section>

    <section class="f-card c-step" aria-labelledby="step-size" id="size-step" hidden>
        <div class="c-step__head">
            <span class="c-step__num" aria-hidden="true">۴</span>
            <h2 id="step-size">چند سؤال؟</h2>
            <output class="c-count" id="count-output" for="count">۲۰</output>
        </div>
        <input class="c-range" id="count" name="count" type="range" min="5" max="100" step="1" value="20" aria-describedby="count-output">
        <label class="c-timer">
            <input type="checkbox" id="timed" name="timed">
            <span>زمان‌دار، مثل جلسه‌ی امتحان</span>
            <input class="f-input c-minutes" id="minutes" name="minutes" type="number" min="1" max="600" inputmode="numeric" disabled aria-label="دقیقه">
            <span class="f-muted">دقیقه</span>
        </label>
    </section>

    <div class="c-bar" id="custom-bar" hidden>
        <p class="c-bar__summary" id="summary"></p>
        <button class="f-btn f-btn--primary f-btn--lg" type="submit" id="custom-submit">ساخت و شروع</button>
    </div>
</form>

<section class="c-mine" aria-labelledby="mine-title">
    <h2 id="mine-title">آزمون‌های دلخواه قبلی</h2>
    <div id="mine" aria-busy="true"><p class="f-muted">در حال خواندن…</p></div>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'آزمون دلخواه | فانوس',
            'description' => 'آزمون دلخواه از بانک سؤالات بساز.',
            'stylesheets' => ['/assets/web/pages/custom-practice.css'],
            'modules' => ['/assets/web/pages/custom-practice.js'],
            'viewer' => $viewer,
            'activeNav' => 'exams',
        ], $main);
    }
}
