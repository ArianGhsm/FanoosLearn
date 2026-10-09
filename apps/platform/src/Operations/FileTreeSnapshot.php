<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use RuntimeException;

final class FileTreeSnapshot
{
    /** @param list<string> $excludedRelativePaths */
    public static function copy(string $source, string $destination, array $excludedRelativePaths = []): void
    {
        $resolvedSource = realpath($source);
        if ($resolvedSource === false || !is_dir($resolvedSource) || is_link($source)) {
            throw new RuntimeException('Snapshot source must be a regular directory.');
        }
        if (file_exists($destination)) {
            throw new RuntimeException('Snapshot destination must not exist.');
        }
        $destinationParent = realpath(dirname($destination));
        if ($destinationParent === false) {
            throw new RuntimeException('Snapshot destination parent must already exist.');
        }
        $normalizedDestination = rtrim($destinationParent, '/\\') . DIRECTORY_SEPARATOR . basename($destination);
        if (str_starts_with($normalizedDestination . DIRECTORY_SEPARATOR, rtrim($resolvedSource, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Snapshot destination cannot be inside its source.');
        }
        if (!mkdir($destination, 0750, true) && !is_dir($destination)) {
            throw new RuntimeException('Snapshot destination could not be created.');
        }

        $excluded = [];
        foreach ($excludedRelativePaths as $relativePath) {
            if (!is_string($relativePath)
                || $relativePath === ''
                || str_starts_with($relativePath, '/')
                || str_contains($relativePath, '\\')
                || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $relativePath) === 1) {
                throw new RuntimeException('Snapshot exclusion path is invalid.');
            }
            $excluded[$relativePath] = true;
        }
        $seenExclusions = [];
        self::copyDirectory($resolvedSource, $destination, '', $excluded, $seenExclusions);
        if (count($seenExclusions) !== count($excluded)) {
            throw new RuntimeException('An excluded snapshot file was not present in the source tree.');
        }
    }

    /** @param array<string, true> $excluded @param array<string, true> $seenExclusions */
    private static function copyDirectory(
        string $source,
        string $destination,
        string $relativeDirectory,
        array $excluded,
        array &$seenExclusions,
    ): void
    {
        $entries = scandir($source);
        if ($entries === false) {
            throw new RuntimeException('Snapshot source could not be listed.');
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $from = $source . DIRECTORY_SEPARATOR . $entry;
            $to = $destination . DIRECTORY_SEPARATOR . $entry;
            $relativePath = $relativeDirectory === '' ? $entry : $relativeDirectory . '/' . $entry;
            if (is_link($from)) {
                throw new RuntimeException('Symlinks are not allowed in object snapshots.');
            }
            if (is_dir($from)) {
                if (!mkdir($to, 0750) && !is_dir($to)) {
                    throw new RuntimeException('Snapshot directory could not be created.');
                }
                self::copyDirectory($from, $to, $relativePath, $excluded, $seenExclusions);
                continue;
            }
            if (isset($excluded[$relativePath])) {
                $seenExclusions[$relativePath] = true;
                continue;
            }
            if (!is_file($from) || !copy($from, $to)) {
                throw new RuntimeException('Snapshot file could not be copied.');
            }
            @chmod($to, 0640);
        }
    }
}
