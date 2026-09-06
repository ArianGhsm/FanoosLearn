<?php

declare(strict_types=1);

namespace Fanoos\Tests\Storage;

use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Storage\UploadInspector;
use RuntimeException;

final class StorageSecurityTest
{
    private int $assertions = 0;

    public function run(): int
    {
        $temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fanoos-storage-test-' . bin2hex(random_bytes(6));
        if (!mkdir($temporaryRoot, 0700)) {
            throw new RuntimeException('Test directory could not be created.');
        }

        try {
            $source = $temporaryRoot . DIRECTORY_SEPARATOR . 'safe.txt';
            file_put_contents($source, "tenant-safe-content\n");
            $inspector = new UploadInspector(1024);
            $upload = $inspector->inspect($source, '../lesson.txt');
            self::assert($upload->displayName === 'lesson.txt', 'Client path must be reduced to display metadata.');
            self::assert($upload->detectedMime === 'text/plain', 'Safe text type should be detected.');

            $workspace = '018f4e2a-5d51-7abc-8def-0123456789ab';
            $otherWorkspace = '018f4e2a-5d51-7abc-8def-1123456789ab';
            $address = new ObjectAddress(
                $workspace,
                '018f4e2a-5d51-7abc-8def-2123456789ab',
                '018f4e2a-5d51-7abc-8def-3123456789ab',
            );
            $store = new FilesystemObjectStore($temporaryRoot . DIRECTORY_SEPARATOR . 'objects');
            $key = $store->put($address, $upload);
            self::assert(str_starts_with($key, 'private/01/' . $workspace . '/'), 'Storage key must be private and tenant namespaced.');
            self::assert($store->put($address, $upload) === $key, 'Identical retry must be idempotent.');
            $stream = $store->openRead($address);
            self::assert(stream_get_contents($stream) === "tenant-safe-content\n", 'Stored bytes must round-trip.');
            fclose($stream);

            $different = $temporaryRoot . DIRECTORY_SEPARATOR . 'different.txt';
            file_put_contents($different, "different\n");
            self::assertThrows(
                fn () => $store->put($address, $inspector->inspect($different, 'different.txt')),
                'The same key must reject different bytes.',
            );

            $active = $temporaryRoot . DIRECTORY_SEPARATOR . 'active.txt';
            file_put_contents($active, "<?php echo 'unsafe';");
            self::assertThrows(fn () => $inspector->inspect($active, 'active.txt'), 'Active content must be rejected.');
            self::assertThrows(fn () => (new UploadInspector(2))->inspect($source, 'large.txt'), 'Oversized content must be rejected.');

            $signer = new SignedDownloadToken(str_repeat('test-signing-key-', 3), 120);
            $token = $signer->issue($address, 1_000, 60);
            $payload = $signer->verify($token, $workspace, 1_001);
            self::assert($payload['object'] === $address->objectId, 'Signed token must bind the object.');
            self::assertThrows(fn () => $signer->verify($token, $otherWorkspace, 1_001), 'Cross-tenant token use must fail.');
            self::assertThrows(fn () => $signer->verify($token . 'x', $workspace, 1_001), 'Tampered token must fail.');
            self::assertThrows(fn () => $signer->verify($token, $workspace, 1_061), 'Expired token must fail.');
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
