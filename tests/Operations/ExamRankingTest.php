<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Content\ExamRankingService;
use RuntimeException;

/**
 * کارنامه's arithmetic, without a database: rank, "better than", the
 * withheld rank in small groups, and the score histogram.
 */
final class ExamRankingTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $scores = ['a' => 9000, 'b' => 7000, 'me' => 7000, 'c' => 5000, 'd' => 3000, 'e' => 10000];
        $standing = ExamRankingService::standing($scores, 'me');
        $this->assert($standing['ranked'] === true && $standing['rank'] === 3, 'Two higher scores make rank 3: ' . json_encode($standing));
        $this->assert($standing['better_than_percent'] === 40, 'Better than 2 of the 5 others is 40%.');
        $this->assert($standing['top_percent'] === 100 && $standing['mine_percent'] === 70 && $standing['average_percent'] === 68, 'Summary numbers: ' . json_encode($standing));
        $this->assert(array_sum($standing['distribution']) === 6 && $standing['distribution'][9] === 2 && $standing['my_bucket'] === 7, 'Histogram: ' . json_encode($standing['distribution']));

        $few = ExamRankingService::standing(['me' => 5000, 'x' => 6000], 'me');
        $this->assert($few['ranked'] === false && $few['reason'] === 'too_few' && !isset($few['rank']), 'A rank among two was shown.');
        $none = ExamRankingService::standing($scores, 'stranger');
        $this->assert($none['ranked'] === false && $none['reason'] === 'no_exam_attempt' && $none['participants'] === 6, 'Someone who did not sit it was ranked.');

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
