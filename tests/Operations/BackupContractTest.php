<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Operations\BackupRetention;
use Fanoos\Platform\Operations\FileTreeSnapshot;
use RuntimeException;

final class BackupContractTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fanoos-backup-test-' . bin2hex(random_bytes(6));
        $source = $temporaryRoot . DIRECTORY_SEPARATOR . 'source';
        $backup = $temporaryRoot . DIRECTORY_SEPARATOR . 'backup';
        mkdir($source . DIRECTORY_SEPARATOR . 'nested', 0700, true);
        mkdir($backup, 0700, true);

        try {
            file_put_contents($source . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'object.bin', 'object-bytes');
            file_put_contents($backup . DIRECTORY_SEPARATOR . 'database.sql', 'SELECT 1;');
            FileTreeSnapshot::copy($source, $backup . DIRECTORY_SEPARATOR . 'objects');
            $digest = BackupManifest::write($backup, ['release' => 'test']);
            file_put_contents($backup . DIRECTORY_SEPARATOR . 'READY', $digest . "\n");

            $manifest = BackupManifest::verify($backup);
            self::assert(isset($manifest['files']['database.sql']), 'Database dump must be inventoried.');
            self::assert(isset($manifest['files']['objects/nested/object.bin']), 'Object bytes must be inventoried.');
            self::assert(count($manifest['files']) === 2, 'Only payload files belong in the manifest.');

            file_put_contents($backup . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'object.bin', 'tampered');
            self::assertThrows(fn () => BackupManifest::verify($backup), 'Manifest verification must detect tampering.');

            $fullRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'full-backups';
            $researchRoot = $fullRoot . DIRECTORY_SEPARATOR . 'research';
            mkdir($researchRoot, 0700, true);
            $fullNames = [
                '20261010T000000Z-aaaa1111',
                '20261008T000000Z-bbbb2222',
                '20261006T000000Z-cccc3333',
                '20261004T000000Z-dddd4444',
                '20261002T000000Z-eeee5555',
            ];
            foreach ($fullNames as $name) {
                mkdir($fullRoot . DIRECTORY_SEPARATOR . $name, 0700);
                file_put_contents($fullRoot . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'READY', 'verified');
            }

            $recentResearch = '20261010-recent-checkpoint.tar.gz';
            $oldResearch = '20261010-old-checkpoint.tar.gz';
            self::writeResearchArchive($researchRoot, $recentResearch, 'research-new', '2026-10-07T12:00:00Z');
            self::writeResearchArchive($researchRoot, $oldResearch, 'research-old', '2026-10-03T12:00:00Z');
            $receipt = '20261010-publication-receipt.tar.gz';
            self::writeResearchArchive($researchRoot, $receipt, 'audit receipt', '2026-10-12T12:00:00Z');
            $incomplete = $researchRoot . DIRECTORY_SEPARATOR . '20261010-incomplete.tar.gz';
            file_put_contents($incomplete, 'unfinished');
            $corrupt = $researchRoot . DIRECTORY_SEPARATOR . '20261010-corrupt.tar.gz';
            file_put_contents($corrupt, 'corrupt');
            file_put_contents($corrupt . '.sha256', str_repeat('0', 64) . '  ' . basename($corrupt) . PHP_EOL);

            $planned = BackupRetention::pruneCompletedProjectBackups($fullRoot, $researchRoot, 5, true);
            self::assert(
                $planned === [$oldResearch, $fullNames[4]],
                'Dry-run retention must plan the oldest complete sets across full and research destinations.',
            );
            self::assert(
                is_file($researchRoot . DIRECTORY_SEPARATOR . $oldResearch)
                    && is_dir($fullRoot . DIRECTORY_SEPARATOR . $fullNames[4]),
                'Dry-run retention must not remove any backup.',
            );
            $removed = BackupRetention::pruneCompletedProjectBackups($fullRoot, $researchRoot, 5);
            self::assert($removed === $planned, 'Applied retention must remove the exact dry-run plan.');
            self::assert(
                !file_exists($researchRoot . DIRECTORY_SEPARATOR . $oldResearch)
                    && !file_exists($researchRoot . DIRECTORY_SEPARATOR . $oldResearch . '.sha256')
                    && !file_exists($fullRoot . DIRECTORY_SEPARATOR . $fullNames[4]),
                'Applied retention must remove complete backup sets and their receipts together.',
            );
            self::assert(
                is_file($researchRoot . DIRECTORY_SEPARATOR . $receipt)
                    && is_file($incomplete)
                    && is_file($corrupt),
                'Publication receipts and incomplete or unverifiable archives must remain untouched.',
            );

            $projectRoot = dirname(__DIR__, 2);
            $retentionRunner = file_get_contents($projectRoot . '/scripts/ops/prune-retention.sh');
            $retentionService = file_get_contents($projectRoot . '/ops/backup/fanoos-backup-retention.service.example');
            $retentionTimer = file_get_contents($projectRoot . '/ops/backup/fanoos-backup-retention.timer.example');
            $this->assert(
                is_string($retentionRunner) && str_contains($retentionRunner, 'prune-completed-backups.php'),
                'The documented retention runner must use the project-wide backup pruner.',
            );
            $this->assert(
                is_string($retentionService)
                    && str_contains($retentionService, 'ONLY_BACKUPS=1 KEEP_BACKUPS=5')
                    && str_contains($retentionService, 'User=fanoosupd'),
                'The scheduled service must enforce five sets as the FANOOS updater identity.',
            );
            $this->assert(
                is_string($retentionTimer)
                    && str_contains($retentionTimer, 'OnUnitInactiveSec=1h')
                    && str_contains($retentionTimer, 'fanoos-backup-retention.service'),
                'The retention timer must periodically invoke the project service.',
            );
        } finally {
            self::removeTree($temporaryRoot);
        }

        return $this->assertions;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertThrows(callable $operation, string $message): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (\Throwable) {
            return;
        }
        throw new RuntimeException($message);
    }

    private static function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $target = $path . DIRECTORY_SEPARATOR . $entry;
            is_dir($target) && !is_link($target) ? self::removeTree($target) : unlink($target);
        }
        rmdir($path);
    }

    private static function writeResearchArchive(string $root, string $name, string $contents, string $modifiedAt): void
    {
        $archive = $root . DIRECTORY_SEPARATOR . $name;
        file_put_contents($archive, $contents);
        $digest = hash_file('sha256', $archive);
        file_put_contents($archive . '.sha256', $digest . '  ' . $name . PHP_EOL);
        $timestamp = (new \DateTimeImmutable($modifiedAt))->getTimestamp();
        touch($archive, $timestamp);
        touch($archive . '.sha256', $timestamp);
    }
}
