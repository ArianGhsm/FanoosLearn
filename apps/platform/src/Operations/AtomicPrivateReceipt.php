<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use RuntimeException;

/** Atomically publish a mode-0600 private receipt without replacing an existing path. */
final class AtomicPrivateReceipt
{
    /**
     * The optional callbacks make short-write/flush/publish failures testable.
     * A hard link is used for the final exclusive publish: it is atomic and
     * fails if another process has already created the receipt path.
     *
     * @param null|callable(resource, string): (int|false) $writeChunk
     * @param null|callable(resource): bool $flush
     * @param null|callable(resource): bool $sync
     * @param null|callable(string, string): bool $publishLink
     * @param null|callable(string): bool $syncDirectory
     */
    public static function publish(
        string $target, string $contents,
        ?callable $writeChunk = null,
        ?callable $flush = null,
        ?callable $sync = null,
        ?callable $publishLink = null,
        ?callable $syncDirectory = null,
    ): void {
        $directory = realpath(dirname($target));
        if ($directory === false || !is_dir($directory) || dirname($target) !== $directory) {
            throw new RuntimeException('Committed source batch; manual reconciliation required; private receipt directory is invalid; do not blindly retry.');
        }
        $temporary = tempnam($directory, '.source-only-receipt-');
        if ($temporary === false || dirname($temporary) !== $directory) {
            if (is_string($temporary) && is_file($temporary)) {
                @unlink($temporary);
            }
            throw new RuntimeException('Committed source batch; manual reconciliation required; private receipt staging failed; do not blindly retry.');
        }

        $handle = null;
        $syncDirectory ??= static function (string $path): bool {
            if (!function_exists('fsync')) {
                return false;
            }
            $directoryHandle = @fopen($path, 'r');
            if ($directoryHandle === false) {
                return false;
            }
            try {
                return @fsync($directoryHandle);
            } finally {
                fclose($directoryHandle);
            }
        };
        try {
            if (!chmod($temporary, 0600)) {
                throw new RuntimeException('private receipt mode could not be restricted');
            }
            $handle = @fopen($temporary, 'wb');
            if ($handle === false) {
                $handle = null;
                throw new RuntimeException('private receipt staging file could not be opened');
            }
            $writeChunk ??= static fn($stream, string $chunk): int|false => fwrite($stream, $chunk);
            $flush ??= static fn($stream): bool => fflush($stream);
            $sync ??= static fn($stream): bool => function_exists('fsync') && @fsync($stream);
            $publishLink ??= static fn(string $from, string $to): bool => @link($from, $to);

            $offset = 0;
            $length = strlen($contents);
            while ($offset < $length) {
                $written = $writeChunk($handle, substr($contents, $offset));
                if (!is_int($written) || $written <= 0) {
                    throw new RuntimeException('private receipt short write');
                }
                $offset += $written;
            }
            if (!$flush($handle) || !$sync($handle)) {
                throw new RuntimeException('private receipt flush or sync failed');
            }
            if (!fclose($handle)) {
                $handle = null;
                throw new RuntimeException('private receipt close failed');
            }
            $handle = null;
            if (!$publishLink($temporary, $target)) {
                throw new RuntimeException('exclusive private receipt publish failed');
            }
            if (!$syncDirectory($directory)) {
                throw new RuntimeException('receipt directory sync after publish failed');
            }
        } catch (\Throwable $error) {
            throw new RuntimeException(
                'Committed source batch; manual reconciliation required; private receipt write failed; do not blindly retry.',
                0,
                $error,
            );
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            $temporaryCleanupFailed = is_file($temporary) && !@unlink($temporary);
            $directorySynced = $syncDirectory($directory);
            if ($temporaryCleanupFailed) {
                // The final link, when present, still names a complete durable
                // receipt. Preserve the operation's non-success signal if its
                // private staging name could not be removed.
                throw new RuntimeException('Committed source batch; manual reconciliation required; receipt staging cleanup failed; do not blindly retry.');
            }
            if (!$directorySynced) {
                throw new RuntimeException('Committed source batch; manual reconciliation required; receipt directory sync after cleanup failed; do not blindly retry.');
            }
        }
    }
}
