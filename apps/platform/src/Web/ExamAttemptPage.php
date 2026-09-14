<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The exam runner: one attempt, from the first question to the report.
 *
 * The server renders the frame and the assessment id; everything else is
 * driven by the page script against the paced API, which serves one question
 * per request. There is deliberately no question content in this document --
 * the whole point of the pacing work is that a paper is never delivered in
 * one response, and rendering it server-side would put it right back.
 *
 * The chrome is suppressed while an attempt is running (focus mode, as the
 * legacy called it): during an exam the navigation is a distraction and an
 * accidental tap out of it loses the student's place.
 */
final class ExamAttemptPage
{
    public function __construct(private readonly PageRenderer $renderer)
    {
    }

    public function render(ViewerContext $viewer, string $assessmentId): string
    {
        $id = $this->renderer->escape($assessmentId);

        $main = <<<HTML
<div class="x-runner" data-assessment="{$id}">
    <div id="runner" aria-live="polite">
        <div class="f-card x-runner__loading">
            <div class="x-skeleton" aria-hidden="true"></div>
            <p class="f-muted">در حال آماده‌سازی آزمون…</p>
        </div>
    </div>
</div>
HTML;

        return $this->renderer->render([
            'title' => 'آزمون | فانوس',
            'description' => 'شرکت در آزمون.',
            'bodyClass' => 'x-runner-page',
            'stylesheets' => ['/assets/web/pages/exams.css', '/assets/web/pages/runner.css'],
            'modules' => ['/assets/web/pages/runner.js'],
            'viewer' => $viewer,
            'activeNav' => 'exams',
        ], $main);
    }
}
