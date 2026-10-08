<?php

declare(strict_types=1);

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $catalogPath = $root . '/data/bank/catalog.json';
    $catalogRaw = file_get_contents($catalogPath);
    $catalog = json_decode(is_string($catalogRaw) ? $catalogRaw : '', true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($catalog['references'] ?? null)) {
        throw new RuntimeException('Official reference catalog is unavailable.');
    }
    $editionKeys = [];
    foreach ($catalog['references'] as $reference) {
        foreach (($reference['editions'] ?? []) as $edition) {
            if (is_string($reference['key'] ?? null) && is_string($edition['key'] ?? null)) {
                $editionKeys[$reference['key'] . '@' . $edition['key']] = true;
            }
        }
    }

    $config = RuntimeConfig::load();
    $database = DatabaseConnection::fromEnvironment();
    $workspaceQuery = $database->prepare("SELECT workspace.id, workspace.name FROM academic_disciplines discipline LEFT JOIN tenant_workspaces workspace ON workspace.id = discipline.library_workspace_id AND workspace.status = 'active' AND workspace.archived_at IS NULL WHERE discipline.code = 'dentistry' AND discipline.status = 'active'");
    $workspaceQuery->execute();
    $workspace = $workspaceQuery->fetch();
    $workspaceId = is_array($workspace) && is_string($workspace['id'] ?? null) ? $workspace['id'] : null;

    $pdfRows = [];
    if ($workspaceId !== null) {
        $pdfQuery = $database->prepare(<<<'SQL'
SELECT metadata.topic AS edition_key,
       metadata.format_key, metadata.access_level,
       resource.title,
       resource.lifecycle_status,
       resource.current_version_no,
       object_record.original_name,
       object_record.byte_size,
       LOWER(HEX(object_record.checksum_sha256)) AS checksum_sha256,
       object_record.classification,
       object_record.status AS object_status,
       version.status AS version_status,
       version.version_no
FROM content_resources resource
JOIN content_resource_metadata metadata
  ON metadata.resource_id = resource.id AND metadata.workspace_id = resource.workspace_id
JOIN content_resource_versions version
  ON version.resource_id = resource.id AND version.workspace_id = resource.workspace_id
JOIN content_objects object_record
  ON object_record.id = version.object_id AND object_record.workspace_id = version.workspace_id
JOIN (
    SELECT resource_id, workspace_id, MAX(version_no) AS version_no
    FROM content_resource_versions
    GROUP BY resource_id, workspace_id
) latest
  ON latest.resource_id = version.resource_id
 AND latest.workspace_id = version.workspace_id
 AND latest.version_no = version.version_no
WHERE resource.workspace_id = :workspace
  AND resource.deleted_at IS NULL
  AND resource.archived_at IS NULL
  AND metadata.format_key = 'reference_pdf'
  AND object_record.detected_mime = 'application/pdf'
ORDER BY metadata.topic, resource.title
SQL);
        $pdfQuery->execute(['workspace' => $workspaceId]);
        $pdfRows = $pdfQuery->fetchAll();
    }

    $storage = ['configured' => false, 'free_bytes' => null, 'total_bytes' => null];
    $storageRoot = $config->optionalString('FANOOS_STORAGE_ROOT');
    if (is_string($storageRoot) && $storageRoot !== '') {
        $resolvedStorage = realpath($storageRoot);
        if ($resolvedStorage !== false) {
            $free = disk_free_space($resolvedStorage);
            $total = disk_total_space($resolvedStorage);
            $storage = [
                'configured' => true,
                'free_bytes' => $free === false ? null : (int) $free,
                'total_bytes' => $total === false ? null : (int) $total,
            ];
        }
    }

    $driveMounts = driveFilesystemMounts();
    if ($workspaceId !== null) {
        $objectBytesQuery = $database->prepare("SELECT COALESCE(SUM(byte_size), 0) FROM content_objects WHERE workspace_id = :workspace AND status = 'verified'");
        $objectBytesQuery->execute(['workspace' => $workspaceId]);
        $objectBytes = (int) $objectBytesQuery->fetchColumn();
    } else {
        $objectBytes = 0;
    }

    echo json_encode([
        'reference_edition_count' => count($editionKeys),
        'dentistry_library_configured' => $workspaceId !== null,
        'dentistry_library_name' => is_array($workspace) ? ($workspace['name'] ?? null) : null,
        'pdf_resource_count' => count($pdfRows),
        'pdf_resource_bytes' => array_sum(array_map(static fn (array $row): int => (int) $row['byte_size'], $pdfRows)),
        'all_verified_object_bytes' => $objectBytes,
        'pdf_resources' => array_map(static fn (array $row): array => [
            'edition_key' => $row['edition_key'],
            'format_key' => $row['format_key'],
            'access_level' => $row['access_level'],
            'title' => $row['title'],
            'lifecycle_status' => $row['lifecycle_status'],
            'current_version_no' => (int) $row['current_version_no'],
            'file_name' => $row['original_name'],
            'byte_size' => (int) $row['byte_size'],
            'sha256' => $row['checksum_sha256'],
            'classification' => $row['classification'],
            'object_status' => $row['object_status'],
            'version_status' => $row['version_status'],
            'version_no' => (int) $row['version_no'],
            'ready' => $row['access_level'] === 'private'
                && $row['lifecycle_status'] === 'published'
                && $row['classification'] === 'private'
                && $row['object_status'] === 'verified'
                && $row['version_status'] === 'approved'
                && (int) $row['current_version_no'] === (int) $row['version_no'],
        ], $pdfRows),
        'storage' => $storage,
        'drive_mounts' => $driveMounts,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'REFERENCE LIBRARY INVENTORY FAILED: ' . $error::class . PHP_EOL);
    exit(1);
}

/**
 * Checks only the filesystem containing FANOOS storage; no other mount is
 * enumerated or reported.
 */
function driveFilesystemMounts(): array
{
    $mountInfo = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($mountInfo)) {
        return [];
    }

    $mounts = [];
    foreach ($mountInfo as $line) {
        $separator = strpos($line, ' - ');
        if ($separator === false) {
            continue;
        }
        $left = preg_split('/\s+/', substr($line, 0, $separator));
        $right = preg_split('/\s+/', substr($line, $separator + 3));
        if (!is_array($left) || !isset($left[4]) || !is_array($right) || !isset($right[0], $right[1])) {
            continue;
        }
        $mountPoint = strtr($left[4], ['\\040' => ' ', '\\011' => "\t", '\\134' => '\\']);
        if (preg_match('/(?:drive|google|rclone)/i', $right[0] . ' ' . $right[1]) === 1) {
            $mounts[] = ['mount_point' => $mountPoint, 'filesystem' => $right[0]];
        }
    }

    return $mounts;
}
