<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * برنامه‌ی مطالعه: the student's day-by-day plan to their exam date, made
 * from the bank (StudyPlanService), with today's work up front and a tick
 * per day. Without a plan, the form that makes one. Run by plan.js.
 */
final class StudyPlanPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="b-head">
    <h1>برنامه‌ی مطالعه</h1>
    <p class="f-muted">برنامه‌ی روزبه‌روز تا روز آزمون، از روی بانک سؤال: مبحث‌های پرسؤال‌تر زودتر، و روزهای آخر برای آزمون جامع و مرور.</p>
</header>
<div class="f-notice f-notice--error" id="plan-error" hidden>
    <div class="f-notice__body"><p id="plan-error-text"></p></div>
</div>

<form class="f-card b-panel p-make" id="plan-form" hidden novalidate>
    <h2>ساخت برنامه</h2>
    <label class="c-schedule__field"><span>تاریخ آزمون</span><input class="f-input" type="date" id="plan-date" required></label>
    <p class="f-tiny" id="plan-date-fa"></p>
    <label class="c-schedule__field"><span>چند روز در هفته می‌خوانی؟</span>
        <select class="f-input" id="plan-days">
            <option value="7">هر روز</option>
            <option value="6" selected>۶ روز (جمعه‌ها استراحت)</option>
            <option value="5">۵ روز</option>
            <option value="4">۴ روز</option>
        </select>
    </label>
    <div class="b-actions"><button class="f-btn f-btn--primary f-btn--lg" type="submit" id="plan-make">ساخت برنامه</button></div>
</form>

<div id="plan" aria-busy="true" aria-live="polite"><div class="x-skeleton" aria-hidden="true"></div></div>
HTML;

        return $this->renderer->render([
            'title' => 'برنامه‌ی مطالعه | فانوس',
            'description' => 'برنامه‌ی روزانه‌ی مطالعه تا روز آزمون.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/bank.css', '/assets/web/pages/plan.css'],
            'modules' => ['/assets/web/pages/plan.js'],
            'viewer' => $viewer,
            'activeNav' => 'progress',
        ], $main);
    }
}
