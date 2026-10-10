<?php

declare(strict_types=1);

namespace Fanoos\Tests\Content;

use Fanoos\Platform\Content\ExamImageStore;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class ExamImageStoreCleanupTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fanoos-image-cleanup-' . bin2hex(random_bytes(6));
        try {
            $store = new ExamImageStore($root);
            $valid = "\x89PNG\r\n\x1A\n" . str_repeat('a', 32);
            $store->put($valid);
            $this->assertNoStagingFiles($root, 'A completed image write left a staging file.');

            $failing = "\x89PNG\r\n\x1A\n" . str_repeat('b', 32);
            $key = hash('sha256', $failing) . '.png';
            $destination = $root . '/exam-images/' . substr($key, 0, 2) . '/' . substr($key, 2, 2) . '/' . $key;
            if (!mkdir($destination, 0750, true)) {
                throw new RuntimeException('Could not prepare the synthetic rename failure.');
            }
            $this->assertThrows(fn () => $store->put($failing), 'A directory at the image key must make finalization fail.');
            $this->assertNoStagingFiles($root, 'A failed image write left a staging file.');
        } finally {
            self::removeTree($root);
        }
        return $this->assertions;
    }

    private function assertNoStagingFiles(string $root, string $message): void
    {
        $found = [];
        if (is_dir($root)) {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($items as $item) {
                if ($item->isFile() && str_ends_with($item->getFilename(), '.tmp')) {
                    $found[] = $item->getPathname();
                }
            }
        }
        $this->assert($found === [], $message . ' ' . implode(', ', $found));
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

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
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
