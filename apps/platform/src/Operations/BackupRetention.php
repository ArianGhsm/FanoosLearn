<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class BackupRetention
{
    private const MAX_COMPLETED_BACKUPS = 5;

    /** @return list<string> Removed backup IDs. */
    public static function pruneCompletedFullBackups(string $backupRoot, int $keep = 5): array
    {
        if ($keep < 1 || $keep > self::MAX_COMPLETED_BACKUPS) {
            throw new RuntimeException('Completed backup retention must be between one and five.');
        }
        $resolvedRoot = realpath($backupRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot) || is_link($backupRoot)) {
            throw new RuntimeException('Backup retention root must be a regular directory.');
        }

        $backups = [];
        foreach (new FilesystemIterator($resolvedRoot, FilesystemIterator::SKIP_DOTS) as $entry) {
            $name = $entry->getFilename();
            if (preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{8}$/', $name) !== 1
                || !$entry->isDir()
                || $entry->isLink()) {
                continue;
            }
            $ready = $entry->getPathname() . DIRECTORY_SEPARATOR . 'READY';
            if (is_file($ready) && !is_link($ready)) {
                $backups[$name] = $entry->getPathname();
            }
        }
        uksort($backups, static fn (string $left, string $right): int => strcmp($right, $left));

        $removed = [];
        foreach (array_slice($backups, $keep, null, true) as $name => $path) {
            self::removeTreeWithoutSymlinks($path, $resolvedRoot);
            $removed[] = $name;
        }

        return $removed;
    }

    private static function removeTreeWithoutSymlinks(string $path, string $resolvedRoot): void
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false || dirname($resolvedPath) !== $resolvedRoot || is_link($path)) {
            throw new RuntimeException('Backup retention target is outside the backup root.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Symlinks are not allowed in completed backups.');
            }
            if ($entry->isDir()) {
                if (!rmdir($entry->getPathname())) {
                    throw new RuntimeException('Old backup directory could not be removed.');
                }
            } elseif (!unlink($entry->getPathname())) {
                throw new RuntimeException('Old backup file could not be removed.');
            }
        }
        if (!rmdir($resolvedPath)) {
            throw new RuntimeException('Old backup directory could not be removed.');
        }
    }
}
