<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Content\ProgressService;
use Fanoos\Platform\Content\StudyService;
use RuntimeException;

/**
 * مرور هوشمند's schedule and the study-time arithmetic, without a database.
 */
final class StudyScheduleTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $now = (int) strtotime('2026-10-10 12:00:00 UTC');
        $row = static fn (int $answered, int $correct, bool $last, string $at): array => [
            'answered_count' => $answered, 'correct_count' => $correct, 'last_correct' => $last, 'last_answered_at' => $at,
        ];

        // Last answer wrong: box 1, due the next day.
        $this->assert(StudyService::box($row(1, 0, false, '2026-10-09 12:00:00')) === 1, 'A wrong last answer is box 1.');
        $this->assert(StudyService::daysUntilDue($row(1, 0, false, '2026-10-09 12:00:00'), $now) <= 0, 'A wrong answer from yesterday is due today.');
        $this->assert(StudyService::daysUntilDue($row(1, 0, false, '2026-10-10 11:00:00'), $now) === 1, 'A wrong answer from this morning is due tomorrow.');

        // Got it right after getting it wrong: three days; right again: further out.
        $this->assert(StudyService::box($row(2, 1, true, '2026-10-09 12:00:00')) === 2, 'Right once after a wrong is box 2.');
        $this->assert(StudyService::daysUntilDue($row(2, 1, true, '2026-10-09 12:00:00'), $now) === 2, 'Box 2 comes back three days after the answer.');
        $this->assert(StudyService::box($row(3, 2, true, '2026-10-01 12:00:00')) === 3, 'Right twice after one wrong is box 3.');
        $this->assert(StudyService::box($row(20, 19, true, '2026-10-01 12:00:00')) === 5, 'The box is capped at the longest interval.');
        $this->assert(StudyService::box($row(5, 1, true, '2026-10-01 12:00:00')) === 2, 'Many wrongs keep a question in the short box.');

        // Attempt time is study time, capped.
        $this->assert(ProgressService::attemptMinutes('2026-10-10 10:00:00', '2026-10-10 10:20:30', 40) === 21, 'Twenty minutes and a bit count as 21.');
        $this->assert(ProgressService::attemptMinutes('2026-10-09 10:00:00', '2026-10-10 10:00:00', 10) === 30, 'An attempt left open overnight counts three minutes a question.');
        $this->assert(ProgressService::attemptMinutes('2026-10-09 10:00:00', '2026-10-10 10:00:00', 150) === 180, 'No attempt counts more than three hours.');
        $this->assert(ProgressService::attemptMinutes('2026-10-10 10:00:00', '2026-10-10 10:00:00', 5) === 0, 'No time, no study time.');

        return $this->assertions;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
