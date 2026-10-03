<?php

declare(strict_types=1);

namespace Fanoos\Platform\Bank;

use RuntimeException;

/** An import file that cannot be applied, with every problem found in it. */
final class BankImportException extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(count($problems) . ' problem(s) in the import file: ' . implode('; ', array_slice($problems, 0, 5)));
    }
}
