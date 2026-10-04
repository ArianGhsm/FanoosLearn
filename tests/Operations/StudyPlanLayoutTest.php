<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use DateTimeImmutable;
use DateTimeZone;
use Fanoos\Platform\Content\StudyPlanService;
use RuntimeException;

/**
 * برنامه‌ی مطالعه's arithmetic, without a database: rest days, the
 * consolidation days at the end, and even packing of the bank's topics.
 */
final class StudyPlanLayoutTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $tz = new DateTimeZone('Asia/Tehran');
        $today = new DateTimeImmutable('2026-10-03', $tz); // a Saturday
        $exam = new DateTimeImmutable('2026-10-31', $tz);   // four weeks later

        $six = StudyPlanService::studyDates($today, $exam, 6);
        $this->assert(count($six) === 24, 'Six days a week for four weeks is 24 days: ' . count($six));
        foreach ($six as $date) {
            $this->assert((new DateTimeImmutable($date))->format('w') !== '5', "A Friday ({$date}) is a study day in a six-day week.");
        }
        $this->assert(count(StudyPlanService::studyDates($today, $exam, 7)) === 28 && count(StudyPlanService::studyDates($today, $exam, 4)) === 16, 'Rest days are not taken off the week end.');
        $this->assert(!in_array('2026-10-31', $six, true), 'The exam day itself is a study day.');

        $units = [];
        foreach (range(1, 20) as $i) {
            $units[] = ['kind' => 'topic', 'topic' => "t{$i}", 'weight' => $i % 3 === 0 ? 30 : 10];
        }
        $days = StudyPlanService::layout($six, $units);
        $this->assert(count($days) === 24, 'Every study date is a day of the plan.');
        $final = array_slice($days, -4); // 15 % of 24, rounded
        $this->assert(array_column(array_merge(...array_column($final, 'items')), 'kind') === ['mock', 'review', 'mock', 'rest'], 'The last days are not mock, review, mock, rest: ' . json_encode($final));
        $placed = array_merge(...array_column(array_slice($days, 0, 20), 'items'));
        $this->assert(array_column($placed, 'topic') === array_column($units, 'topic'), 'Every topic must be placed once, in order.');
        $this->assert(!array_key_exists('weight', $placed[0]), 'The packing weight leaked into the plan.');

        $few = StudyPlanService::layout(array_slice($six, 0, 10), array_slice($units, 0, 3));
        $kinds = array_map(static fn (array $day): string => $day['items'][0]['kind'], $few);
        $this->assert(array_slice($kinds, 0, 3) === ['topic', 'topic', 'topic'] && $kinds[3] === 'review', 'With few topics the spare days must be review days: ' . json_encode($kinds));

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
