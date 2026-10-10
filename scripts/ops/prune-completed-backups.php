<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\BackupRetention;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $arguments = array_slice($argv, 1);
    if (array_diff($arguments, ['--dry-run', '--apply']) !== []
        || count(array_intersect($arguments, ['--dry-run', '--apply'])) > 1) {
        throw new RuntimeException('Usage: php prune-completed-backups.php [--dry-run|--apply]');
    }
    $keepText = getenv('KEEP_BACKUPS');
    $keep = $keepText === false ? 5 : filter_var($keepText, FILTER_VALIDATE_INT);
    if (!is_int($keep) || $keep < 1 || $keep > 5) {
        throw new RuntimeException('KEEP_BACKUPS must be an integer from one through five.');
    }

    $config = RuntimeConfig::load();
    $backupRoot = $config->requireString('FANOOS_BACKUP_ROOT');
    $researchRoot = rtrim($backupRoot, '/\\') . DIRECTORY_SEPARATOR . 'research';
    $dryRun = in_array('--apply', $arguments, true) === false;
    $removed = BackupRetention::pruneCompletedProjectBackups(
        $backupRoot,
        $researchRoot,
        $keep,
        $dryRun,
    );
    foreach ($removed as $name) {
        echo ($dryRun ? 'would remove ' : 'removed ') . $name . PHP_EOL;
    }
    echo json_encode([
        'status' => $dryRun ? 'dry-run' : 'applied',
        'keep' => $keep,
        'planned_or_removed' => count($removed),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Backup retention failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
