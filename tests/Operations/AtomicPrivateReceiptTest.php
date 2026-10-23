<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Operations\AtomicPrivateReceipt;
use RuntimeException;

final class AtomicPrivateReceiptTest
{
    public function run(): int
    {
        $directory = sys_get_temp_dir() . '/fanoos-private-receipt-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700) || !chmod($directory, 0700)) {
            throw new RuntimeException('Could not create isolated private receipt test directory.');
        }
        $checks = 0;
        $targets = [];
        try {
            $payload = "{\"status\":\"applied_verified\"}\n";
            $success = $directory . '/success.json';
            $targets[] = $success;
            AtomicPrivateReceipt::publish($success, $payload);
            $this->check(file_get_contents($success) === $payload, 'Atomic receipt content was not published exactly.');
            $this->check((fileperms($success) & 0777) === 0600, 'Atomic receipt was not private mode 0600.');
            $this->check(glob($directory . '/.source-only-receipt-*') === [], 'Successful receipt left a staging file.');
            $checks += 3;

            $existing = $directory . '/existing.json';
            $targets[] = $existing;
            file_put_contents($existing, 'keep-existing');
            $this->expectFailure($existing, 'replacement');
            $this->check(file_get_contents($existing) === 'keep-existing', 'Exclusive publish overwrote an existing receipt.');
            $this->check(glob($directory . '/.source-only-receipt-*') === [], 'Existing-path refusal left a staging file.');
            $checks += 2;

            $shortWritePath = $directory . '/short-write.json';
            $targets[] = $shortWritePath;
            $writeCalls = 0;
            $this->expectFailure($shortWritePath, 'write', static function ($stream, string $chunk) use (&$writeCalls): int|false {
                ++$writeCalls;
                return $writeCalls === 1 ? fwrite($stream, substr($chunk, 0, 4)) : false;
            });
            $this->check(!file_exists($shortWritePath), 'Short write published a partial receipt.');
            $checks += 1;

            $flushPath = $directory . '/flush-failure.json';
            $targets[] = $flushPath;
            $this->expectFailure($flushPath, 'flush', null, static fn($stream): bool => false);
            $this->check(!file_exists($flushPath), 'Flush failure published a receipt.');
            $checks += 1;

            $syncPath = $directory . '/sync-failure.json';
            $targets[] = $syncPath;
            $this->expectFailure($syncPath, 'sync', null, null, static fn($stream): bool => false);
            $this->check(!file_exists($syncPath), 'Sync failure published a receipt.');
            $checks += 1;

            $linkPath = $directory . '/link-failure.json';
            $targets[] = $linkPath;
            $this->expectFailure($linkPath, 'publish', null, null, null, static fn(string $from, string $to): bool => false);
            $this->check(!file_exists($linkPath), 'Failed hard-link publish created a final receipt.');
            $checks += 1;

            $shortSuccess = $directory . '/short-success.json';
            $targets[] = $shortSuccess;
            AtomicPrivateReceipt::publish($shortSuccess, $payload, static fn($stream, string $chunk): int|false => fwrite($stream, substr($chunk, 0, 3)));
            $this->check(file_get_contents($shortSuccess) === $payload, 'Positive short writes were not retried to completion.');
            $checks += 1;

            $this->check(glob($directory . '/.source-only-receipt-*') === [], 'A receipt failure path left a staging file.');
            ++$checks;
        } finally {
            foreach ($targets as $target) {
                if (is_file($target) && !unlink($target)) {
                    throw new RuntimeException('Could not remove isolated receipt test output.');
                }
            }
            foreach (glob($directory . '/.source-only-receipt-*') ?: [] as $temporary) {
                if (is_file($temporary) && !unlink($temporary)) {
                    throw new RuntimeException('Could not remove isolated receipt staging output.');
                }
            }
            if (is_dir($directory) && !rmdir($directory)) {
                throw new RuntimeException('Could not remove isolated receipt test directory.');
            }
        }

        return $checks;
    }

    private function expectFailure(
        string $target, string $label,
        ?callable $write = null, ?callable $flush = null,
        ?callable $sync = null, ?callable $publish = null,
    ): void {
        try {
            AtomicPrivateReceipt::publish($target, 'partial contents', $write, $flush, $sync, $publish);
            throw new RuntimeException("{$label} failure was accepted.");
        } catch (RuntimeException $error) {
            $this->check(str_contains($error->getMessage(), 'Committed source batch; manual reconciliation required')
                && str_contains($error->getMessage(), 'do not blindly retry'),
                "{$label} failure did not return the required reconciliation guidance.");
        }
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
