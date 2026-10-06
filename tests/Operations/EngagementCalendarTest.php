<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use DateTimeImmutable;
use Fanoos\Platform\Engagement\PointsService;
use Fanoos\Platform\Engagement\QuestionDifficulty;
use Fanoos\Platform\Support\JalaliCalendar;
use RuntimeException;

/**
 * The calendar arithmetic behind امتیاز روزانه: Jalali months, the Saturday
 * week, and the difficulty bands.
 */
final class EngagementCalendarTest
{
    private int $assertions = 0;

    public function run(): int
    {
        foreach ([
            ['2026-10-06', [1405, 7, 14]],
            ['2026-09-23', [1405, 7, 1]],
            ['2025-03-21', [1404, 1, 1]],
            ['2025-03-20', [1403, 12, 30]],
            ['2024-03-20', [1403, 1, 1]],
            ['2026-03-20', [1404, 12, 29]],
            ['2026-03-21', [1405, 1, 1]],
        ] as [$gregorian, $jalali]) {
            $this->assert(JalaliCalendar::of(new DateTimeImmutable($gregorian)) === $jalali, "{$gregorian} is not " . implode('/', $jalali));
        }
        $this->assert(JalaliCalendar::monthStart(new DateTimeImmutable('2026-10-06'))->format('Y-m-d') === '2026-09-23', 'Mehr 1405 starts on 2026-09-23.');
        $this->assert(JalaliCalendar::monthName(new DateTimeImmutable('2026-10-06')) === 'مهر', 'October 6th 2026 is in Mehr.');

        // The Iranian week runs Saturday to Friday.
        $this->assert(PointsService::weekStart(new DateTimeImmutable('2026-10-06'))->format('Y-m-d') === '2026-10-03', 'A Tuesday belongs to the week of the Saturday before.');
        $this->assert(PointsService::weekStart(new DateTimeImmutable('2026-10-03'))->format('Y-m-d') === '2026-10-03', 'A Saturday starts its own week.');
        $this->assert(PointsService::weekStart(new DateTimeImmutable('2026-10-09'))->format('Y-m-d') === '2026-10-03', 'A Friday ends the week.');

        $this->assert(QuestionDifficulty::measured(9, 9) === null, 'Too few answers must not be called a difficulty.');
        $this->assert(QuestionDifficulty::measured(10, 7) === 'easy', '70% right is easy.');
        $this->assert(QuestionDifficulty::measured(10, 4) === 'medium', '40% right is medium.');
        $this->assert(QuestionDifficulty::measured(10, 3) === 'hard', 'Below 40% right is hard.');
        $this->assert(QuestionDifficulty::authored(' Hard ') === 'hard' && QuestionDifficulty::authored('3') === null, 'Only the three words are an authored difficulty.');

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
