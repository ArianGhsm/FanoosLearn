<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use PDO;
use RuntimeException;

final class ReferencePdfBackupPolicy
{
    /**
     * @return list<array{storage_key:string, byte_size:int, checksum_sha256:string}>
     *         Reference PDF objects with no non-reference resource versions.
     */
    public static function excludedObjects(PDO $database): array
    {
        $query = $database->query(<<<'SQL'
SELECT DISTINCT object_record.storage_key,
       object_record.byte_size,
       LOWER(HEX(object_record.checksum_sha256)) AS checksum_sha256
FROM content_objects object_record
JOIN content_resource_versions reference_version
  ON reference_version.object_id = object_record.id
 AND reference_version.workspace_id = object_record.workspace_id
JOIN content_resource_metadata reference_metadata
  ON reference_metadata.resource_id = reference_version.resource_id
 AND reference_metadata.workspace_id = reference_version.workspace_id
WHERE object_record.storage_adapter = 'filesystem'
  AND object_record.status = 'verified'
  AND object_record.deleted_at IS NULL
  AND object_record.detected_mime = 'application/pdf'
  AND reference_metadata.format_key = 'reference_pdf'
  AND NOT EXISTS (
      SELECT 1
      FROM content_resource_versions other_version
      LEFT JOIN content_resource_metadata other_metadata
        ON other_metadata.resource_id = other_version.resource_id
       AND other_metadata.workspace_id = other_version.workspace_id
      WHERE other_version.object_id = object_record.id
        AND other_version.workspace_id = object_record.workspace_id
        AND (other_metadata.format_key IS NULL OR other_metadata.format_key <> 'reference_pdf')
  )
ORDER BY object_record.storage_key
SQL);

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $objects = [];
        foreach ($rows as $row) {
            $key = is_string($row['storage_key'] ?? null) ? $row['storage_key'] : '';
            $size = filter_var($row['byte_size'] ?? null, FILTER_VALIDATE_INT);
            $checksum = is_string($row['checksum_sha256'] ?? null) ? strtolower($row['checksum_sha256']) : '';
            if (preg_match('#^(?:private|protected|public)/[0-9a-f]{2}/[0-9a-f-]{36}/[0-9a-f]{2}/[0-9a-f-]{36}/[0-9a-f-]{36}\.bin$#', $key) !== 1
                || !is_int($size)
                || $size < 1
                || preg_match('/^[0-9a-f]{64}$/', $checksum) !== 1) {
                throw new RuntimeException('Reference PDF object metadata is invalid.');
            }
            $objects[] = ['storage_key' => $key, 'byte_size' => $size, 'checksum_sha256' => $checksum];
        }

        return $objects;
    }

    /**
     * @param list<array{storage_key:string, byte_size:int, checksum_sha256:string}> $objects
     * @return list<string> Relative object keys suitable for FileTreeSnapshot.
     */
    public static function verifiedStorageKeys(string $storageRoot, array $objects): array
    {
        $resolvedRoot = realpath($storageRoot);
        if ($resolvedRoot === false || !is_dir($resolvedRoot) || is_link($storageRoot)) {
            throw new RuntimeException('Object storage root is not a regular directory.');
        }

        $keys = [];
        foreach ($objects as $object) {
            $key = $object['storage_key'];
            $path = $resolvedRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
            if (is_link($path)) {
                throw new RuntimeException('Reference PDF storage object must not be a symlink.');
            }
            $resolvedPath = realpath($path);
            if ($resolvedPath === false
                || !is_file($resolvedPath)
                || !str_starts_with($resolvedPath, rtrim($resolvedRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Reference PDF storage object is unavailable.');
            }
            $actualSize = filesize($resolvedPath);
            $actualChecksum = hash_file('sha256', $resolvedPath);
            if ($actualSize !== $object['byte_size']
                || !is_string($actualChecksum)
                || !hash_equals($object['checksum_sha256'], $actualChecksum)) {
                throw new RuntimeException('Reference PDF storage object does not match its database metadata.');
            }
            $keys[] = $key;
        }

        return $keys;
    }
}
