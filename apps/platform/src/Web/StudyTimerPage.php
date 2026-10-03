<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * تایمر مطالعه: focus blocks with breaks between them. Each finished (or
 * stopped) focus block is recorded on the account and counts as study time
 * on the progress dashboard. Run by timer.js.
 */
final class StudyTimerPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer): string
    {
        $main = <<<'HTML'
<header class="b-head">
    <h1>تایمر مطالعه</h1>
    <p class="f-muted">بلوک‌های تمرکز با استراحت بینشان. هر بلوکی که تمام کنی، در ساعت مطالعه‌ی «پیشرفت من» حساب می‌شود.</p>
</header>

<section class="f-card t-timer" aria-labelledby="t-phase">
    <p class="t-phase" id="t-phase">تمرکز</p>
    <p class="t-clock" id="t-clock" role="timer" aria-live="off">۲۵:۰۰</p>
    <div class="t-presets" role="radiogroup" aria-label="طول بلوک">
        <button class="x-chip is-active" type="button" data-focus="25" data-break="5">۲۵ / ۵</button>
        <button class="x-chip" type="button" data-focus="50" data-break="10">۵۰ / ۱۰</button>
        <button class="x-chip" type="button" data-focus="90" data-break="15">۹۰ / ۱۵</button>
    </div>
    <div class="t-actions">
        <button class="f-btn f-btn--primary f-btn--lg" type="button" id="t-start">شروع</button>
        <button class="f-btn f-btn--ghost" type="button" id="t-stop" hidden>پایان و ثبت</button>
    </div>
    <p class="f-muted t-today" id="t-today"></p>
    <p class="f-tiny" id="t-status" aria-live="polite"></p>
</section>
HTML;

        return $this->renderer->render([
            'title' => 'تایمر مطالعه | فانوس',
            'description' => 'تایمر مطالعه با ثبت ساعت مطالعه.',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/bank.css', '/assets/web/pages/timer.css'],
            'modules' => ['/assets/web/pages/timer.js'],
            'viewer' => $viewer,
            'activeNav' => 'progress',
        ], $main);
    }
}
