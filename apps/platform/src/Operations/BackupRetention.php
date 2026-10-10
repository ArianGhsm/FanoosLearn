<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use DateTimeImmutable;
use DateTimeZone;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class BackupRetention
{
    private const MAX_COMPLETED_BACKUPS = 5;

    /**
     * Prune completed full backups and verified private research archives as
     * one FANOOS project retention set.
     *
     * @return list<string> Backup IDs planned for or removed by this run.
     */
    public static function pruneCompletedProjectBackups(
        string $backupRoot,
        string $researchRoot,
        int $keep = 5,
        bool $dryRun = false,
    ): array {
        if ($keep < 1 || $keep > self::MAX_COMPLETED_BACKUPS) {
            throw new RuntimeException('Completed backup retention must be between one and five.');
        }
        $resolvedRoot = realpath($backupRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot) || is_link($backupRoot)) {
            throw new RuntimeException('Backup retention root must be a regular directory.');
        }
        $lockPath = $resolvedRoot . DIRECTORY_SEPARATOR . '.backup-retention.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('Backup retention lock must not be a symlink.');
        }
        $lock = @fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Backup retention lock could not be acquired.');
        }
        @chmod($lockPath, 0600);

        try {
            $resolvedResearchRoot = realpath($researchRoot);
            if ($resolvedResearchRoot !== false
                && (!is_dir($resolvedResearchRoot) || is_link($researchRoot) || dirname($resolvedResearchRoot) !== $resolvedRoot)) {
                throw new RuntimeException('Research backup root must be a direct child of the project backup root.');
            }

            $backups = [];
            foreach (new FilesystemIterator($resolvedRoot, FilesystemIterator::SKIP_DOTS) as $entry) {
                $name = $entry->getFilename();
                if (preg_match('/^(\d{8})T(\d{6})Z-[a-f0-9]{8}$/', $name, $match) !== 1
                    || !$entry->isDir()
                    || $entry->isLink()) {
                    continue;
                }
                $created = DateTimeImmutable::createFromFormat(
                    '!YmdHis',
                    $match[1] . $match[2],
                    new DateTimeZone('UTC'),
                );
                $ready = $entry->getPathname() . DIRECTORY_SEPARATOR . 'READY';
                if ($created !== false && is_file($ready) && !is_link($ready)) {
                    $backups[] = [
                        'path' => $entry->getPathname(),
                        'name' => $name,
                        'kind' => 'full',
                        'createdAt' => $created->getTimestamp(),
                    ];
                }
            }

            if ($resolvedResearchRoot !== false) {
                foreach (new FilesystemIterator($resolvedResearchRoot, FilesystemIterator::SKIP_DOTS) as $entry) {
                    $name = $entry->getFilename();
                    if (preg_match('/^20\d{6}-.+\.tar\.gz$/', $name) !== 1
                        || (str_contains($name, 'publication') && str_contains($name, 'receipt'))
                        || !$entry->isFile()
                        || $entry->isLink()) {
                        continue;
                    }
                    $path = $entry->getPathname();
                    $sidecar = $path . '.sha256';
                    $createdAt = $entry->getMTime();
                    if (!is_int($createdAt) || !self::verifyResearchArchive($path, $sidecar)) {
                        continue;
                    }
                    $backups[] = [
                        'path' => $path,
                        'name' => $name,
                        'kind' => 'research',
                        'createdAt' => $createdAt,
                    ];
                }
            }

            usort($backups, static fn (array $left, array $right): int =>
                ($right['createdAt'] <=> $left['createdAt']) ?: strcmp($right['name'], $left['name'])
            );
            $removed = array_slice($backups, $keep);
            foreach ($removed as $backup) {
                if ($backup['kind'] === 'full') {
                    self::assertTreeWithoutSymlinks($backup['path'], $resolvedRoot);
                } else {
                    $sidecar = $backup['path'] . '.sha256';
                    if (dirname($backup['path']) !== $resolvedResearchRoot
                        || is_link($backup['path'])
                        || is_link($sidecar)
                        || !self::verifyResearchArchive($backup['path'], $sidecar)) {
                        throw new RuntimeException('Research backup changed or became unsafe during retention.');
                    }
                }
            }

            if ($dryRun) {
                return array_values(array_map(static fn (array $backup): string => $backup['name'], $removed));
            }

            $removedNames = [];
            foreach ($removed as $backup) {
                if ($backup['kind'] === 'full') {
                    self::removeTreeWithoutSymlinks($backup['path'], $resolvedRoot);
                } else {
                    if (!unlink($backup['path']) || !unlink($backup['path'] . '.sha256')) {
                        throw new RuntimeException('Research backup could not be removed completely.');
                    }
                }
                $removedNames[] = $backup['name'];
            }

            return $removedNames;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function verifyResearchArchive(string $archive, string $sidecar): bool
    {
        if (is_link($archive) || is_link($sidecar) || !is_file($archive) || !is_file($sidecar)) {
            return false;
        }
        $receipt = file_get_contents($sidecar);
        if (!is_string($receipt)
            || preg_match('/^([a-f0-9]{64})\s+\*?([^\r\n]+)\s*$/iD', trim($receipt), $match) !== 1
            || basename($match[2]) !== basename($archive)) {
            return false;
        }
        $digest = hash_file('sha256', $archive);

        return is_string($digest) && hash_equals(strtolower($match[1]), $digest);
    }

    private static function assertTreeWithoutSymlinks(string $path, string $resolvedRoot): void
    {
        $resolvedPath = realpath($path);
        if ($resolvedPath === false || dirname($resolvedPath) !== $resolvedRoot || is_link($path)) {
            throw new RuntimeException('Backup retention target is outside the backup root.');
        }
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedPath, RecursiveDirectoryIterator::SKIP_DOTS),
        ) as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Symlinks are not allowed in completed backups.');
            }
        }
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
