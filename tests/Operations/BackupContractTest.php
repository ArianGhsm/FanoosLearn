<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Operations\BackupManifest;
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
}
