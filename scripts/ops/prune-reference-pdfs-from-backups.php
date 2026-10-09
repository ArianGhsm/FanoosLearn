<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\ReferencePdfBackupPolicy;
use Fanoos\Platform\Operations\ReferencePdfBackupPruner;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use RuntimeException;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $mode = $argv[1] ?? '--dry-run';
    if ($argc !== 2 || !in_array($mode, ['--dry-run', '--apply'], true)) {
        throw new RuntimeException('Usage: php scripts/ops/prune-reference-pdfs-from-backups.php [--dry-run|--apply]');
    }

    $config = RuntimeConfig::load();
    $backupRoot = $config->requireString('FANOOS_BACKUP_ROOT');
    $storageRoot = $config->requireString('FANOOS_STORAGE_ROOT');
    $targetKey = $config->requireString('FANOOS_UPDATER_TARGET_KEY');
    $database = DatabaseConnection::fromEnvironment();
    $lockName = 'fanoos-deploy-' . substr(hash('sha256', $targetKey), 0, 40);
    $lock = $database->prepare('SELECT GET_LOCK(:name, 0)');
    $lock->execute(['name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Updater is running; backup snapshots were not changed.');
    }

    try {
        $objects = ReferencePdfBackupPolicy::excludedObjects($database);
        ReferencePdfBackupPolicy::verifiedStorageKeys($storageRoot, $objects);
        $backups = ReferencePdfBackupPruner::pruneCompletedBackups(
            $backupRoot,
            $objects,
            $mode === '--apply',
        );
    } finally {
        try {
            $release = $database->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => $lockName]);
            $release->fetchColumn();
        } catch (Throwable) {
            // MySQL releases connection-scoped advisory locks when the connection closes.
        }
    }

    $result = [
        'mode' => $mode === '--apply' ? 'applied' : 'dry_run',
        'backup_count' => count($backups),
        'planned_or_removed_objects' => array_sum(array_column($backups, 'removed_objects')),
        'planned_or_removed_bytes' => array_sum(array_column($backups, 'removed_bytes')),
        'backups' => $backups,
    ];
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Reference PDF backup pruning failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
