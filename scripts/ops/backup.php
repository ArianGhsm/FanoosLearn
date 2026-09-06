<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Operations\FileTreeSnapshot;
use Fanoos\Platform\Operations\MySqlDsn;
use Fanoos\Platform\Operations\ProcessRunner;
use Fanoos\Platform\Support\JsonLogger;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

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
    FileTreeSnapshot::copy($resolvedStorage, $partial . DIRECTORY_SEPARATOR . 'objects');
    $manifestDigest = BackupManifest::write($partial, [
        'database' => $database['database'],
        'release' => $config->optionalString('FANOOS_RELEASE_SHA', 'unknown'),
    ]);
    file_put_contents($partial . DIRECTORY_SEPARATOR . 'READY', $manifestDigest . PHP_EOL, LOCK_EX);
    if (!rename($partial, $final)) {
        throw new RuntimeException('Backup could not be atomically finalized.');
    }

    JsonLogger::write('info', 'backup.completed', ['backup_id' => $name]);
    echo $final . PHP_EOL;
} catch (Throwable $error) {
    JsonLogger::write('error', 'backup.failed', ['error_type' => $error::class]);
    fwrite(STDERR, 'Backup failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
