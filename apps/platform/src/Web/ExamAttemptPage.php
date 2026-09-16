<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * The exam runner: one attempt, from the first question to the report.
 *
 * The server renders the frame, the assessment id and -- when it resolves --
 * that assessment's catalogue-shaped intro metadata (title, kind, attempt
 * counts and the like); everything else is driven by the page script against
 * the paced API, which serves one question per request. There is
 * deliberately no question/answer content in this document -- the whole
 * point of the pacing work is that a paper is never delivered in one
 * response, and rendering it server-side would put it right back. The intro
 * data embedded here is exactly the same shape ExamService::catalog() already
 * returns per row, not new information a signed-in workspace member could
 * not already read from the catalogue endpoint.
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

    /** @param array<string, mixed>|null $assessment catalogue-shaped row, or null if it could not be resolved server-side -- the page script then falls back to its own catalogue read. */
    public function render(ViewerContext $viewer, string $assessmentId, ?array $assessment = null): string
    {
        $id = $this->renderer->escape($assessmentId);
        $intro = $assessment === null ? '' : $this->renderer->embedJson('assessment-intro', $assessment);

        $main = <<<HTML
<div class="x-runner" data-assessment="{$id}">
    {$intro}
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
