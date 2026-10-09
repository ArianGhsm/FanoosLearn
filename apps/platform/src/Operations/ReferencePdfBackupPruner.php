<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use FilesystemIterator;
use RuntimeException;

final class ReferencePdfBackupPruner
{
    /**
     * Remove only reference-only PDF objects whose current production metadata,
     * backup manifest, size, and checksum all agree.
     *
     * @param list<array{storage_key:string, byte_size:int, checksum_sha256:string}> $objects
     * @return list<array{backup_id:string, removed_objects:int, removed_bytes:int}>
     */
    public static function pruneCompletedBackups(string $backupRoot, array $objects, bool $apply = true): array
    {
        $resolvedRoot = realpath($backupRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot) || is_link($backupRoot)) {
            throw new RuntimeException('Backup retention root must be a regular directory.');
        }

        $objectByKey = [];
        foreach ($objects as $object) {
            $key = $object['storage_key'] ?? '';
            if (!is_string($key)
                || preg_match('#^(?:private|protected|public)/[0-9a-f]{2}/[0-9a-f-]{36}/[0-9a-f]{2}/[0-9a-f-]{36}/[0-9a-f-]{36}\.bin$#', $key) !== 1) {
                throw new RuntimeException('Reference PDF backup inventory contains an invalid storage key.');
            }
            $objectByKey[$key] = $object;
        }

        $plans = [];
        foreach (new FilesystemIterator($resolvedRoot, FilesystemIterator::SKIP_DOTS) as $entry) {
            $name = $entry->getFilename();
            if (preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{8}$/', $name) !== 1
                || !$entry->isDir()
                || $entry->isLink()) {
                continue;
            }
            $path = $entry->getPathname();
            $readyPath = $path . DIRECTORY_SEPARATOR . 'READY';
            $manifestPath = $path . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($readyPath) || is_link($readyPath) || !is_file($manifestPath) || is_link($manifestPath)) {
                continue;
            }

            $json = file_get_contents($manifestPath);
            $ready = file_get_contents($readyPath);
            if ($json === false || $ready === false || !hash_equals(hash('sha256', $json), trim($ready))) {
                throw new RuntimeException('A completed backup has an invalid manifest or READY marker.');
            }
            $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || !is_array($manifest['files'] ?? null) || !is_array($manifest['metadata'] ?? null)) {
                throw new RuntimeException('A completed backup has an invalid manifest structure.');
            }

            $matching = [];
            foreach ($objectByKey as $key => $object) {
                $relative = 'objects/' . $key;
                if (!array_key_exists($relative, $manifest['files'])) {
                    continue;
                }
                $listed = $manifest['files'][$relative];
                if (!is_array($listed)
                    || ($listed['bytes'] ?? null) !== $object['byte_size']
                    || !is_string($listed['sha256'] ?? null)
                    || !hash_equals($object['checksum_sha256'], strtolower($listed['sha256']))) {
                    throw new RuntimeException('A backup object does not match the current reference PDF metadata.');
                }
                $matching[$relative] = $object;
            }
            if ($matching === []) {
                continue;
            }

            // Verify every file in this backup before altering its inventory.
            $verifiedManifest = BackupManifest::verify($path);
            foreach ($matching as $relative => $object) {
                $target = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_link($target)) {
                    throw new RuntimeException('Reference PDF in a backup must not be a symlink.');
                }
                $resolvedTarget = realpath($target);
                if ($resolvedTarget === false
                    || !is_file($resolvedTarget)
                    || !str_starts_with($resolvedTarget, rtrim($path, '/\\') . DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Reference PDF backup object is unavailable.');
                }
            }
            $plans[] = [
                'backup_id' => $name,
                'path' => $path,
                'manifest' => $verifiedManifest,
                'matching' => $matching,
            ];
        }

        if (!$apply) {
            return array_map(static fn (array $plan): array => [
                'backup_id' => $plan['backup_id'],
                'removed_objects' => count($plan['matching']),
                'removed_bytes' => array_sum(array_column($plan['matching'], 'byte_size')),
            ], $plans);
        }

        $backups = [];
        foreach ($plans as $plan) {
            $filesRemoved = 0;
            $bytesRemoved = 0;
            foreach ($plan['matching'] as $relative => $object) {
                $target = $plan['path'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (!unlink($target)) {
                    throw new RuntimeException('Reference PDF backup object could not be removed.');
                }
                $filesRemoved++;
                $bytesRemoved += $object['byte_size'];
            }

            $metadata = $plan['manifest']['metadata'];
            $metadata['reference_pdf_backup_policy'] = 'exclude_reference_only_objects';
            $metadata['reference_pdf_objects_excluded'] = (int) ($metadata['reference_pdf_objects_excluded'] ?? 0) + $filesRemoved;
            $metadata['reference_pdf_bytes_excluded'] = (int) ($metadata['reference_pdf_bytes_excluded'] ?? 0) + $bytesRemoved;
            BackupManifest::rewriteWithoutFiles($plan['path'], $plan['manifest'], array_keys($plan['matching']), $metadata);
            $backups[] = [
                'backup_id' => $plan['backup_id'],
                'removed_objects' => $filesRemoved,
                'removed_bytes' => $bytesRemoved,
            ];
        }

        return $backups;
    }
}
