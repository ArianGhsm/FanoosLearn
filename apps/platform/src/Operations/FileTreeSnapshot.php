<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use RuntimeException;

final class FileTreeSnapshot
{
    public static function copy(string $source, string $destination): void
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

        self::copyDirectory($resolvedSource, $destination);
    }

    private static function copyDirectory(string $source, string $destination): void
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
            if (is_link($from)) {
                throw new RuntimeException('Symlinks are not allowed in object snapshots.');
            }
            if (is_dir($from)) {
                if (!mkdir($to, 0750) && !is_dir($to)) {
                    throw new RuntimeException('Snapshot directory could not be created.');
                }
                self::copyDirectory($from, $to);
                continue;
            }
            if (!is_file($from) || !copy($from, $to)) {
                throw new RuntimeException('Snapshot file could not be copied.');
            }
            @chmod($to, 0640);
        }
    }
}
