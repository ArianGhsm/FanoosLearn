<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Operations\ReferenceLibraryImportPolicy;
use RuntimeException;

final class ReferenceLibraryImportPolicyTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $official = ['book@1e', 'book@2e', 'book@3e'];
        $pending = ReferenceLibraryImportPolicy::missingKeys($official, ['book@1e']);

        $this->assert($pending === ['book@2e', 'book@3e'], 'Every absent official edition must remain visible as pending.');
        $this->assert(ReferenceLibraryImportPolicy::blocksApply($pending, false), 'Default apply must require complete catalog coverage.');
        $this->assert(!ReferenceLibraryImportPolicy::blocksApply($pending, true), 'Explicit partial mode must allow available references to be imported.');
        $this->assert(ReferenceLibraryImportPolicy::expectedReadyKeys($official, $pending) === ['book@1e'], 'Post-import verification must require only available editions in partial mode.');

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
