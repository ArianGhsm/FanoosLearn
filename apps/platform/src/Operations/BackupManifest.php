<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class BackupManifest
{
    /** @param array<string, scalar|null> $metadata */
    public static function write(string $backupRoot, array $metadata): string
    {
        $manifest = [
            'format' => 1,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'metadata' => $metadata,
            'files' => self::inventory($backupRoot),
        ];
        $path = rtrim($backupRoot, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json';
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Backup manifest could not be written.');
        }
        @chmod($path, 0640);

        return hash('sha256', $json);
    }

    /** @return array{format: int, created_at: string, metadata: array<string, mixed>, files: array<string, array{bytes: int, sha256: string}>} */
    public static function verify(string $backupRoot): array
    {
        $manifestPath = rtrim($backupRoot, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json';
        $readyPath = rtrim($backupRoot, '/\\') . DIRECTORY_SEPARATOR . 'READY';
        $json = file_get_contents($manifestPath);
        $readyDigest = file_get_contents($readyPath);
        if ($json === false || $readyDigest === false || !hash_equals(hash('sha256', $json), trim($readyDigest))) {
            throw new RuntimeException('Backup completion marker or manifest digest is invalid.');
        }
        $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['format'] ?? null) !== 1 || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('Backup manifest format is invalid.');
        }

        $actual = self::inventory($backupRoot);
        if ($manifest['files'] !== $actual) {
            throw new RuntimeException('Backup contents do not match the manifest.');
        }

        /** @var array{format: int, created_at: string, metadata: array<string, mixed>, files: array<string, array{bytes: int, sha256: string}>} $manifest */
        return $manifest;
    }

    /** @return array<string, array{bytes: int, sha256: string}> */
    private static function inventory(string $backupRoot): array
    {
        $resolved = realpath($backupRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException('Backup directory is unavailable.');
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolved, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('Backup cannot contain symlinks.');
            }
            if (!$item->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($resolved) + 1));
            if ($relative === 'manifest.json' || $relative === 'READY') {
                continue;
            }
            $digest = hash_file('sha256', $item->getPathname());
            if ($digest === false) {
                throw new RuntimeException('Backup file checksum could not be computed.');
            }
            $files[$relative] = ['bytes' => $item->getSize(), 'sha256' => $digest];
        }
        ksort($files, SORT_STRING);

        return $files;
    }
}
