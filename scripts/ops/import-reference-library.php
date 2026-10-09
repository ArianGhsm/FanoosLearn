<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ContentService;
use Fanoos\Platform\Content\ContentUploadService;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Operations\ReferenceLibraryImportPolicy;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\UploadInspector;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

function option(string $name): ?string
{
    foreach ($GLOBALS['argv'] as $argument) {
        if ($argument === "--{$name}") {
            return '1';
        }
        if (str_starts_with($argument, "--{$name}=")) {
            return substr($argument, strlen($name) + 3);
        }
    }
    return null;
}

function fail(string $message, int $status = 1): never
{
    fwrite(STDERR, 'REFERENCE IMPORT FAILED: ' . $message . PHP_EOL);
    exit($status);
}

try {
    $dryRun = option('dry-run') === '1';
    $apply = option('apply') === '1';
    $allowPartial = option('allow-partial') === '1';
    $stagingOption = option('staging');
    $backupOption = option('verified-backup');
    if ($dryRun === $apply || !is_string($stagingOption) || $stagingOption === ''
        || ($apply && (!is_string($backupOption) || $backupOption === ''))) {
        fail('Usage: php scripts/ops/import-reference-library.php (--dry-run | --apply --verified-backup=<verified-backup-directory>) --staging=<staging-directory> [--allow-partial]', 2);
    }

    $stagingRoot = realpath($stagingOption);
    if ($stagingRoot === false || !is_dir($stagingRoot) || is_link($stagingOption)) {
        fail('Staging directory must be an existing regular directory.');
    }
    $manifestPath = $stagingRoot . DIRECTORY_SEPARATOR . 'manifest.json';
    if (!is_file($manifestPath) || is_link($manifestPath) || !is_readable($manifestPath)) {
        fail('Staging manifest is unavailable.');
    }

    $manifestRaw = file_get_contents($manifestPath);
    $manifest = json_decode(is_string($manifestRaw) ? $manifestRaw : '', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || ($manifest['format'] ?? null) !== 'fanoos.reference-library.v1'
        || !is_array($manifest['editions'] ?? null) || !array_is_list($manifest['editions'])) {
        fail('Staging manifest format is invalid.');
    }

    $officialCatalogPath = $root . '/data/bank/catalog.json';
    $officialCatalogRaw = file_get_contents($officialCatalogPath);
    $officialCatalog = json_decode(is_string($officialCatalogRaw) ? $officialCatalogRaw : '', true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($officialCatalog['references'] ?? null)) {
        fail('Official reference catalog is unavailable.');
    }
    $officialEditions = [];
    foreach ($officialCatalog['references'] as $reference) {
        if (!is_array($reference) || !is_string($reference['key'] ?? null) || !is_array($reference['editions'] ?? null)) {
            fail('Official reference catalog has an invalid row.');
        }
        foreach ($reference['editions'] as $edition) {
            if (!is_array($edition) || !is_string($edition['key'] ?? null)) {
                fail('Official reference catalog has an invalid edition.');
            }
            $key = $reference['key'] . '@' . $edition['key'];
            $officialEditions[$key] = [
                'reference_title' => (string) ($reference['title'] ?? ''),
                'edition_label' => (string) ($edition['label'] ?? ''),
                'published_year' => isset($edition['year']) ? (int) $edition['year'] : null,
            ];
        }
    }

    $manifestByEdition = [];
    foreach ($manifest['editions'] as $entry) {
        if (!is_array($entry) || !is_string($entry['edition_key'] ?? null) || !is_string($entry['file'] ?? null)
            || basename($entry['file']) !== $entry['file'] || $entry['file'] === '' || str_contains($entry['file'], "\0")) {
            fail('Manifest edition entry is invalid.');
        }
        $key = $entry['edition_key'];
        if (!isset($officialEditions[$key]) || isset($manifestByEdition[$key])) {
            fail('Manifest contains an unknown or duplicate edition key.');
        }
        if (!is_string($entry['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/i', $entry['sha256'])
            || !is_int($entry['bytes'] ?? null) || $entry['bytes'] < 1) {
            fail('Manifest size or checksum is invalid.');
        }
        $sourcePath = $stagingRoot . DIRECTORY_SEPARATOR . $entry['file'];
        $realSource = realpath($sourcePath);
        $sourceKind = 'staging';
        if (file_exists($sourcePath) || is_link($sourcePath)) {
            if ($realSource === false || !is_file($realSource) || is_link($sourcePath)
                || !str_starts_with($realSource, $stagingRoot . DIRECTORY_SEPARATOR)) {
                fail('A staged PDF is not a regular file inside the staging directory.');
            }
        } elseif (is_string($entry['drive_path'] ?? null) && $entry['drive_path'] !== '') {
            $realSource = realpath($entry['drive_path']);
            $onDriveMount = $realSource !== false && is_file($realSource) && !is_link($entry['drive_path'])
                && pathIsInsideDriveMount($realSource);
            if (!$onDriveMount) {
                fail('A Drive source must be a regular file inside a mounted Google Drive filesystem.');
            }
            $sourceKind = 'drive_mount';
        } else {
            $realSource = null;
            $sourceKind = 'missing';
        }
        if (is_string($realSource)) {
            $actualSize = filesize($realSource);
            $actualDigest = hash_file('sha256', $realSource);
            $header = file_get_contents($realSource, false, null, 0, 8);
            if ($actualSize !== $entry['bytes'] || !is_string($actualDigest)
                || !hash_equals(strtolower($entry['sha256']), $actualDigest)
                || !is_string($header) || !str_starts_with($header, '%PDF-')) {
                fail('A source PDF does not match the manifest.');
            }
        }
        $manifestByEdition[$key] = [
            'edition_key' => $key,
            'file' => $entry['file'],
            'path' => $realSource,
            'source_kind' => $sourceKind,
            'bytes' => $entry['bytes'],
            'sha256' => strtolower($entry['sha256']),
            ...$officialEditions[$key],
        ];
    }

    $missingEditions = ReferenceLibraryImportPolicy::missingKeys(array_keys($officialEditions), array_keys($manifestByEdition));
    if (!$dryRun && ReferenceLibraryImportPolicy::blocksApply($missingEditions, $allowPartial)) {
        fail('Manifest is incomplete for the official catalog: ' . implode(', ', $missingEditions));
    }

    $config = RuntimeConfig::load();
    $database = DatabaseConnection::fromEnvironment();
    $libraryQuery = $database->prepare("SELECT workspace.id, workspace.name FROM academic_disciplines discipline JOIN tenant_workspaces workspace ON workspace.id = discipline.library_workspace_id WHERE discipline.code = 'dentistry' AND discipline.status = 'active' AND workspace.status = 'active' AND workspace.archived_at IS NULL");
    $libraryQuery->execute();
    $library = $libraryQuery->fetch();
    if ($library === false) {
        fail('The active dentistry library workspace has not been provisioned.');
    }
    $workspaceId = (string) $library['id'];

    $actorQuery = $database->prepare(<<<'SQL'
SELECT user.id
FROM iam_users user
JOIN rbac_role_assignments assignment ON assignment.user_id = user.id
JOIN rbac_role_templates role ON role.id = assignment.role_template_id AND role.role_key = 'platform-super-admin'
JOIN rbac_scopes scope ON scope.id = assignment.scope_id
WHERE user.status = 'active' AND user.deleted_at IS NULL
  AND assignment.revoked_at IS NULL
  AND assignment.valid_from <= UTC_TIMESTAMP(6)
  AND (assignment.valid_until IS NULL OR assignment.valid_until > UTC_TIMESTAMP(6))
  AND scope.scope_type = 'platform' AND scope.archived_at IS NULL
ORDER BY user.created_at, user.id
LIMIT 1
SQL);
    $actorQuery->execute();
    $actorUserId = $actorQuery->fetchColumn();
    if (!is_string($actorUserId) || $actorUserId === '') {
        fail('An active FANOOS platform administrator is required for the audited import.');
    }

    $inventoryQuery = $database->prepare(<<<'SQL'
SELECT resource.id AS resource_id, resource.lifecycle_status, resource.current_version_no,
       metadata.topic AS edition_key, metadata.format_key, metadata.access_level,
       version.id AS version_id, version.version_no, version.status AS version_status,
       version.source_kind, version.object_id,
       LOWER(HEX(object_record.checksum_sha256)) AS sha256,
       object_record.detected_mime, object_record.byte_size, object_record.classification,
       object_record.status AS object_status,
       (SELECT MAX(latest.version_no)
        FROM content_resource_versions latest
        WHERE latest.resource_id = resource.id AND latest.workspace_id = resource.workspace_id) AS latest_version_no
FROM content_resources resource
JOIN content_resource_metadata metadata
  ON metadata.resource_id = resource.id AND metadata.workspace_id = resource.workspace_id
LEFT JOIN content_resource_versions version
  ON version.resource_id = resource.id AND version.workspace_id = resource.workspace_id
 AND version.version_no = (
     SELECT MAX(latest.version_no) FROM content_resource_versions latest
     WHERE latest.resource_id = resource.id AND latest.workspace_id = resource.workspace_id
 )
LEFT JOIN content_objects object_record
  ON object_record.id = version.object_id AND object_record.workspace_id = version.workspace_id
WHERE resource.workspace_id = :workspace
  AND resource.deleted_at IS NULL AND resource.archived_at IS NULL
  AND metadata.format_key = 'reference_pdf'
SQL);
    $inventoryQuery->execute(['workspace' => $workspaceId]);
    $resourceByEdition = [];
    foreach ($inventoryQuery->fetchAll() as $row) {
        $editionKey = (string) $row['edition_key'];
        if (isset($resourceByEdition[$editionKey])) {
            fail('More than one active library resource is registered for edition ' . $editionKey . '.');
        }
        $resourceByEdition[$editionKey] = $row;
    }

    $reuseQuery = $database->prepare(<<<'SQL'
SELECT id
FROM content_objects
WHERE workspace_id = :workspace AND checksum_sha256 = :checksum
  AND detected_mime = 'application/pdf' AND classification = 'private' AND status = 'verified'
ORDER BY created_at, id
LIMIT 1
SQL);
    $items = [];
    $missingSourceEditions = [];
    $bytesToUpload = 0;
    foreach ($missingEditions as $key) {
        $current = $resourceByEdition[$key] ?? null;
        if (is_array($current) && isVerifiedPrivatePdf($current)) {
            if (isPublishedReferencePdf($current)) {
                $items[] = [
                    'edition_key' => $key,
                    'action' => 'already_present',
                    'source' => 'fanoos',
                    'bytes' => (int) $current['byte_size'],
                    'sha256' => strtolower((string) $current['sha256']),
                ];
            } elseif (isPublishableReferencePdfDraft($current)) {
                $items[] = [
                    'edition_key' => $key,
                    'action' => 'publish_existing_reference_pdf',
                    'resource_id' => (string) $current['resource_id'],
                    'version_id' => (string) $current['version_id'],
                    'sha256' => strtolower((string) $current['sha256']),
                ];
            } else {
                $missingSourceEditions[] = $key;
                $items[] = ['edition_key' => $key, 'action' => 'source_missing'];
            }
            continue;
        }
        $missingSourceEditions[] = $key;
        $items[] = ['edition_key' => $key, 'action' => 'source_missing'];
    }
    foreach ($manifestByEdition as $key => $entry) {
        $current = $resourceByEdition[$key] ?? null;
        if (is_array($current) && $current['access_level'] !== 'private') {
            fail('Existing reference resource is not private: ' . $key);
        }
        if (is_array($current) && isVerifiedPrivatePdf($current)
            && is_string($current['sha256'] ?? null)
            && hash_equals(strtolower((string) $current['sha256']), $entry['sha256'])) {
            if (isPublishedReferencePdf($current)) {
                $items[] = ['edition_key' => $key, 'action' => 'already_present', 'bytes' => $entry['bytes'], 'sha256' => $entry['sha256']];
            } elseif (isPublishableReferencePdfDraft($current)) {
                $items[] = [
                    'edition_key' => $key,
                    'action' => 'publish_existing_reference_pdf',
                    'resource_id' => (string) $current['resource_id'],
                    'version_id' => (string) $current['version_id'],
                    'sha256' => $entry['sha256'],
                ];
            } else {
                fail('Existing reference PDF cannot be published safely: ' . $key);
            }
            continue;
        }
        $reuseQuery->bindValue(':workspace', $workspaceId);
        $reuseQuery->bindValue(':checksum', hex2bin($entry['sha256']), PDO::PARAM_LOB);
        $reuseQuery->execute();
        $existingObjectId = $reuseQuery->fetchColumn();
        if (is_string($existingObjectId) && $existingObjectId !== '') {
            $items[] = [
                'edition_key' => $key,
                'action' => 'reuse_existing_private_pdf',
                'bytes' => $entry['bytes'], 'sha256' => $entry['sha256'],
                'resource_exists' => is_array($current),
            ];
            continue;
        }
        $bytesToUpload += $entry['bytes'];
        $items[] = [
            'edition_key' => $key,
            'action' => is_array($current) ? 'upload_new_version' : 'upload_new_resource',
            'file' => $entry['file'],
            'bytes' => $entry['bytes'], 'sha256' => $entry['sha256'],
            'source_kind' => $entry['source_kind'],
            'source_available' => is_string($entry['path']),
        ];
    }

    if ($dryRun) {
        echo json_encode([
            'mode' => 'dry_run',
            'allow_partial' => $allowPartial,
            'workspace_name' => (string) $library['name'],
            'official_editions' => count($officialEditions),
            'manifest_editions' => count($manifestByEdition),
            'missing_source_editions' => $missingSourceEditions,
            'expected_ready_editions' => ReferenceLibraryImportPolicy::expectedReadyKeys(array_keys($officialEditions), $missingSourceEditions),
            'bytes_to_upload' => $bytesToUpload,
            'items' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }

    if (ReferenceLibraryImportPolicy::blocksApply($missingSourceEditions, $allowPartial)) {
        fail('PDF source or a verified matching private PDF is required for: ' . implode(', ', $missingSourceEditions));
    }

    $verifiedBackup = realpath((string) $backupOption);
    if ($verifiedBackup === false || !is_dir($verifiedBackup)) {
        fail('Verified backup directory is unavailable.');
    }
    BackupManifest::verify($verifiedBackup);

    foreach ($items as $item) {
        if (in_array($item['action'], ['upload_new_version', 'upload_new_resource'], true)
            && !is_string($manifestByEdition[$item['edition_key']]['path'])) {
            fail('Required PDF is not staged for edition ' . $item['edition_key'] . '.');
        }
    }

    $storageRoot = $config->requireString('FANOOS_STORAGE_ROOT');
    $storagePath = realpath($storageRoot);
    $freeBytes = $storagePath === false ? false : disk_free_space($storagePath);
    if ($storagePath === false || $freeBytes === false || $bytesToUpload > $freeBytes) {
        fail('Insufficient free disk space for the missing reference PDFs.');
    }

    $audit = new AuditLogger($database);
    $scopeAuthorizer = new ScopeAuthorizer($database);
    $access = new AccessGate($database, $scopeAuthorizer);
    $entitlements = new EntitlementService($database, $access, $audit);
    $protected = new ProtectedResourceAuthorizer($database, $scopeAuthorizer, $entitlements);
    $content = new ContentService($database, $access, $protected, $audit);
    $uploader = new ContentUploadService(
        $database, $access, new UploadInspector(1_000_000_000),
        new FilesystemObjectStore($storagePath), $audit,
    );

    $applied = [];
    foreach ($items as $item) {
        if ($item['action'] === 'source_missing') {
            $applied[] = $item + ['result' => 'left_pending'];
            continue;
        }
        if ($item['action'] === 'already_present') {
            $applied[] = $item + ['result' => 'skipped'];
            continue;
        }
        if ($item['action'] === 'publish_existing_reference_pdf') {
            $content->publishVerifiedPrivateReferencePdf(
                $actorUserId, $workspaceId, (string) $item['resource_id'],
                (string) $item['version_id'], (string) $item['edition_key'],
            );
            $applied[] = $item + ['result' => 'published_existing'];
            continue;
        }
        $entry = $manifestByEdition[$item['edition_key']];
        $resourceId = null;
        if (is_array($resourceByEdition[$entry['edition_key']] ?? null)) {
            $resourceId = (string) $resourceByEdition[$entry['edition_key']]['resource_id'];
        } else {
            $title = $entry['reference_title'] . ' — ' . $entry['edition_label'];
            $created = $content->createResource(
                $actorUserId, $workspaceId, 'other', $title,
                ['reference' => [
                    'edition_key' => $entry['edition_key'],
                    'published_year' => $entry['published_year'],
                    'bytes' => $entry['bytes'],
                    'sha256' => $entry['sha256'],
                ]],
                [
                    'topic' => $entry['edition_key'],
                    'format_key' => 'reference_pdf',
                    'access_level' => 'private',
                    'description' => 'Official full-book reference PDF for the dental residency catalog.',
                    'source_reference' => 'official-residency-reference-catalog',
                ],
                'imported',
            );
            $resourceId = $created['resource_id'];
        }

        if ($item['action'] === 'reuse_existing_private_pdf') {
            $result = $uploader->addExistingPrivatePdfVersion($actorUserId, $workspaceId, (string) $resourceId, $entry['edition_key'], $entry['sha256']);
        } else {
            $result = $uploader->addUploadedVersion(
                $actorUserId, $workspaceId, (string) $resourceId,
                $entry['path'], $entry['file'], 'private',
            );
        }
        $content->publishVerifiedPrivateReferencePdf(
            $actorUserId, $workspaceId, (string) $resourceId,
            $result['version_id'], $entry['edition_key'],
        );
        $applied[] = $item + ['result' => 'imported', 'resource_id' => $result['resource_id'], 'version_id' => $result['version_id']];
    }

    echo json_encode([
        'mode' => 'applied',
        'allow_partial' => $allowPartial,
        'workspace_name' => (string) $library['name'],
        'official_editions' => count($officialEditions),
        'missing_source_editions' => $missingSourceEditions,
        'expected_ready_editions' => ReferenceLibraryImportPolicy::expectedReadyKeys(array_keys($officialEditions), $missingSourceEditions),
        'items' => $applied,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fail('Unexpected failure (' . $error::class . '). Inspect server logs using the approved FANOOS operations procedure.');
}

function pathIsInsideDriveMount(string $candidate): bool
{
    $mountInfo = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($mountInfo)) {
        return false;
    }

    foreach ($mountInfo as $line) {
        $separator = strpos($line, ' - ');
        if ($separator === false) {
            continue;
        }
        $left = preg_split('/\\s+/', substr($line, 0, $separator));
        $right = preg_split('/\\s+/', substr($line, $separator + 3));
        if (!is_array($left) || !isset($left[4]) || !is_array($right) || !isset($right[0], $right[1])) {
            continue;
        }
        $mountPoint = strtr($left[4], ['\\040' => ' ', '\\011' => "\t", '\\134' => '\\']);
        if (preg_match('/(?:drive|google|rclone)/i', $mountPoint . ' ' . $right[0] . ' ' . $right[1]) !== 1) {
            continue;
        }
        $realMount = realpath($mountPoint);
        if ($realMount === false) {
            continue;
        }
        if (str_starts_with($candidate, rtrim($realMount, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }

    return false;
}

/** @param array<string, mixed> $row */
function isVerifiedPrivatePdf(array $row): bool
{
    return ($row['format_key'] ?? null) === 'reference_pdf'
        && ($row['access_level'] ?? null) === 'private'
        && ($row['detected_mime'] ?? null) === 'application/pdf'
        && ($row['classification'] ?? null) === 'private'
        && ($row['object_status'] ?? null) === 'verified'
        && is_string($row['sha256'] ?? null)
        && is_string($row['version_id'] ?? null);
}

/** @param array<string, mixed> $row */
function isPublishedReferencePdf(array $row): bool
{
    return isVerifiedPrivatePdf($row)
        && ($row['lifecycle_status'] ?? null) === 'published'
        && ($row['version_status'] ?? null) === 'approved'
        && (int) ($row['current_version_no'] ?? 0) === (int) ($row['version_no'] ?? -1);
}

/** @param array<string, mixed> $row */
function isPublishableReferencePdfDraft(array $row): bool
{
    return isVerifiedPrivatePdf($row)
        && ($row['version_status'] ?? null) === 'draft'
        && (int) ($row['version_no'] ?? 0) === (int) ($row['latest_version_no'] ?? -1);
}
