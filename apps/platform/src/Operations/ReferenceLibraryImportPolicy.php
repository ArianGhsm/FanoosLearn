<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

/** Coverage rules for importing the official reference catalog. */
final class ReferenceLibraryImportPolicy
{
    /** @param list<string> $officialKeys @param list<string> $providedKeys @return list<string> */
    public static function missingKeys(array $officialKeys, array $providedKeys): array
    {
        return array_values(array_diff($officialKeys, $providedKeys));
    }

    /** @param list<string> $pendingKeys */
    public static function blocksApply(array $pendingKeys, bool $allowPartial): bool
    {
        return $pendingKeys !== [] && !$allowPartial;
    }

    /** @param list<string> $officialKeys @param list<string> $pendingKeys @return list<string> */
    public static function expectedReadyKeys(array $officialKeys, array $pendingKeys): array
    {
        return array_values(array_diff($officialKeys, $pendingKeys));
    }
}
