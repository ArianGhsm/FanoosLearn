<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Operations\FileTreeSnapshot;
use Fanoos\Platform\Operations\MySqlDsn;
use Fanoos\Platform\Operations\ProcessRunner;
use Fanoos\Platform\Operations\BackupRetention;
use Fanoos\Platform\Operations\ReferencePdfBackupPolicy;
use Fanoos\Platform\Operations\ReferencePdfBackupPruner;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\JsonLogger;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$partial = null;
$resolvedBackup = null;
$failed = false;
try {
    $config = RuntimeConfig::load();
    $storageRoot = $config->requireString('FANOOS_STORAGE_ROOT');
    $backupRoot = $config->requireString('FANOOS_BACKUP_ROOT');
    $defaultsFile = $config->requireString('FANOOS_MYSQL_DEFAULTS_FILE');
    if (!is_file($defaultsFile) || !is_readable($defaultsFile)) {
        throw new RuntimeException('MySQL defaults file is unavailable.');
    }
    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0750, true) && !is_dir($backupRoot)) {
        throw new RuntimeException('Backup root could not be created.');
    }
    $resolvedStorage = realpath($storageRoot);
    $resolvedBackup = realpath($backupRoot);
    if ($resolvedStorage === false || $resolvedBackup === false
        || str_starts_with($resolvedBackup . DIRECTORY_SEPARATOR, $resolvedStorage . DIRECTORY_SEPARATOR)
        || str_starts_with($resolvedStorage . DIRECTORY_SEPARATOR, $resolvedBackup . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Backup and object storage roots must be disjoint directory trees.');
    }

    $database = MySqlDsn::parse($config->requireString('FANOOS_DB_DSN'));
    $metadataDatabase = DatabaseConnection::fromEnvironment();
    $referenceObjects = ReferencePdfBackupPolicy::excludedObjects($metadataDatabase);
    $excludedKeys = ReferencePdfBackupPolicy::verifiedStorageKeys($resolvedStorage, $referenceObjects);
    $name = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    $partial = $resolvedBackup . DIRECTORY_SEPARATOR . $name . '.partial';
    $final = $resolvedBackup . DIRECTORY_SEPARATOR . $name;
    if (!mkdir($partial, 0750)) {
        throw new RuntimeException('Backup staging directory could not be created.');
    }

    ProcessRunner::run([
        $config->optionalString('FANOOS_MYSQLDUMP_BIN', 'mysqldump') ?? 'mysqldump',
        '--defaults-extra-file=' . $defaultsFile,
        '--host=' . $database['host'],
        '--port=' . $database['port'],
        '--single-transaction', '--quick', '--hex-blob', '--routines', '--triggers', '--events',
        '--no-tablespaces', '--set-gtid-purged=OFF', $database['database'],
    ], $partial . DIRECTORY_SEPARATOR . 'database.sql');
    $currentReferenceObjects = ReferencePdfBackupPolicy::excludedObjects($metadataDatabase);
    if ($referenceObjects !== $currentReferenceObjects) {
        throw new RuntimeException('Reference PDF inventory changed during backup; retry the backup.');
    }
    FileTreeSnapshot::copy($resolvedStorage, $partial . DIRECTORY_SEPARATOR . 'objects', $excludedKeys);
    $finalReferenceObjects = ReferencePdfBackupPolicy::excludedObjects($metadataDatabase);
    if ($referenceObjects !== $finalReferenceObjects) {
        throw new RuntimeException('Reference PDF inventory changed during backup; retry the backup.');
    }
    $excludedBytes = array_sum(array_column($referenceObjects, 'byte_size'));
    $manifestDigest = BackupManifest::write($partial, [
        'database' => $database['database'],
        'release' => $config->optionalString('FANOOS_RELEASE_SHA', 'unknown'),
        'reference_pdf_backup_policy' => 'exclude_reference_only_objects',
        'reference_pdf_objects_excluded' => count($referenceObjects),
        'reference_pdf_bytes_excluded' => $excludedBytes,
    ]);
    file_put_contents($partial . DIRECTORY_SEPARATOR . 'READY', $manifestDigest . PHP_EOL, LOCK_EX);
    if (!rename($partial, $final)) {
        throw new RuntimeException('Backup could not be atomically finalized.');
    }
    $partial = null;

    BackupManifest::verify($final);
    $sanitizedBackups = ReferencePdfBackupPruner::pruneCompletedBackups($resolvedBackup, $referenceObjects);
    $pruned = BackupRetention::pruneCompletedProjectBackups(
        $resolvedBackup,
        $resolvedBackup . DIRECTORY_SEPARATOR . 'research',
        5,
    );
    JsonLogger::write('info', 'backup.completed', [
        'backup_id' => $name,
        'reference_pdf_objects_excluded' => count($referenceObjects),
        'reference_pdf_bytes_excluded' => $excludedBytes,
        'reference_pdf_backups_sanitized' => count($sanitizedBackups),
        'pruned_backup_set_count' => count($pruned),
    ]);
    echo $final . PHP_EOL;
} catch (Throwable $error) {
    JsonLogger::write('error', 'backup.failed', ['error_type' => $error::class]);
    fwrite(STDERR, 'Backup failed: ' . $error->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    if (is_string($partial) && is_string($resolvedBackup) && is_dir($partial)) {
        try {
            BackupRetention::discardIncompleteStaging($partial, $resolvedBackup);
        } catch (Throwable $cleanupError) {
            JsonLogger::write('error', 'backup.partial_cleanup_failed', [
                'error_type' => $cleanupError::class,
            ]);
        }
    }
}
if ($failed) {
    exit(1);
}
