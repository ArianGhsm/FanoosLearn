<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Bank\OfficialScopePageGuard;
use RuntimeException;

final class OfficialScopePageGuardTest
{
    public function run(): int
    {
        $scope = json_decode(<<<'JSON'
[
  {"number":"4"},
  {"number":"7","partial":"بافت نرم فصل 7 تا ص 161"},
  {"number":"10","partial":"فصل 10 به جز صص 224–232"},
  {"number":"2","partial":"فصل 2 صص 18–47"}
]
JSON, true, 16, JSON_THROW_ON_ERROR);

        $cases = [
            [[['number'=>'4']], '4', null, true],
            [$scope, '4', '999', true],
            [$scope, '7', '149', true],
            [$scope, '7', '161', true],
            [$scope, '7', '162', false],
            [$scope, '7', '161.e1', null],
            [$scope, '10', '223', true],
            [$scope, '10', '224', false],
            [$scope, '10', '232', false],
            [$scope, '10', '233', true],
            [$scope, '2', '17', false],
            [$scope, '2', '18', true],
            [$scope, '2', '47', true],
            [$scope, '2', '48', false],
            [$scope, '20', '515', false],
            [$scope, '7', null, null],
            [[['number'=>'9','partial'=>'selected pages']], '9', '120', null],
            ['{broken', '7', '150', null],
        ];
        foreach ($cases as [$chapters, $chapter, $page, $expected]) {
            $actual = OfficialScopePageGuard::pageDisposition($chapters, $chapter, $page);
            $this->check($actual === $expected, "Unexpected page-scope disposition for chapter {$chapter}, page " . ($page ?? 'null'));
        }

        return count($cases);
    }

    private function check(bool $okay, string $message): void
    {
        if (!$okay) {
            throw new RuntimeException($message);
        }
    }
}
