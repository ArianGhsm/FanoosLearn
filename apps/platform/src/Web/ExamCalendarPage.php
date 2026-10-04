<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * تقویم آزمون‌ها: scheduled exams -- open now, coming, finished -- with
 * their window, size and how many have sat them. Whoever manages exams
 * (exam.manage) also gets the form that gives an exam a window.
 */
final class ExamCalendarPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $manage = $viewer->can('exam.manage');
        $form = !$manage ? '' : <<<'HTML'
<details class="f-card b-panel l-compose">
    <summary><strong>زمان‌بندی یک آزمون</strong></summary>
    <form id="schedule" class="c-schedule" novalidate>
        <select class="f-input" id="schedule-exam" aria-label="آزمون" required><option value="">در حال خواندن آزمون‌ها…</option></select>
        <label class="c-schedule__field"><span>شروع</span><input class="f-input" type="datetime-local" id="schedule-opens" required></label>
        <label class="c-schedule__field"><span>پایان مهلت</span><input class="f-input" type="datetime-local" id="schedule-closes" required></label>
        <input class="f-input" id="schedule-note" maxlength="300" placeholder="توضیح کوتاه (اختیاری)، مثلاً «آزمون جامع اول — مباحث اندو و پریو»" aria-label="توضیح">
        <div class="b-actions"><button class="f-btn f-btn--primary" type="submit" id="schedule-save">ثبت در تقویم</button></div>
        <p class="f-tiny" id="schedule-status" aria-live="polite"></p>
    </form>
</details>
HTML;
        $flag = $manage ? ' data-manage="1"' : '';

        $main = <<<HTML
<header class="b-head">
    <h1>تقویم آزمون‌ها</h1>
    <p class="f-muted">آزمون‌های زمان‌دار: در مهلتش شرکت کن تا در رتبه‌بندی حساب شوی؛ بعد از مهلت هم می‌توانی تمرینش کنی.</p>
</header>
<div class="f-notice f-notice--error" id="cal-error" hidden>
    <div class="f-notice__body"><p id="cal-error-text"></p></div>
</div>
{$form}
<div id="calendar"{$flag} aria-busy="true" aria-live="polite"><div class="x-skeleton" aria-hidden="true"></div></div>
HTML;

        return $this->renderer->render([
            'title' => 'تقویم آزمون‌ها | فانوس',
            'description' => 'آزمون‌های زمان‌دار فانوس.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/bank.css', '/assets/web/pages/lessons.css', '/assets/web/pages/plan.css'],
            'modules' => ['/assets/web/pages/calendar.js'],
            'viewer' => $viewer,
            'activeNav' => 'exams',
        ], $main);
    }
}
