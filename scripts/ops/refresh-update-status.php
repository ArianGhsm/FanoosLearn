<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\CanonicalMainUpdateExecutor;
use Fanoos\Platform\Operations\DeploymentSnapshotStore;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Uuid;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    if ($argc !== 1) {
        throw new RuntimeException('Update status refresher accepts no command, ref, SHA, path, or positional arguments.');
    }
    $config = RuntimeConfig::load();
    $target = $config->requireString('FANOOS_UPDATER_TARGET_KEY');
    if (!preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $target)) {
        throw new RuntimeException('Updater target key is invalid.');
    }
    $database = DatabaseConnection::fromEnvironment();
    $snapshots = new DeploymentSnapshotStore($database);
    try {
        $preflight = (new CanonicalMainUpdateExecutor($config))->preflight(Uuid::v7(), $target);
        $snapshots->recordHealthy($target, $preflight);
        echo json_encode([
            'status' => 'healthy',
            'update_available' => !$preflight['noop'],
            'current_sha' => $preflight['current_sha'],
            'candidate_sha' => $preflight['candidate_sha'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    } catch (Throwable $error) {
        $safeCode = $error instanceof PlatformException ? $error->errorCode : 'status_check_failed';
        $snapshots->recordFailure($target, $safeCode);
        fwrite(STDERR, 'Update status refresh failed: ' . $safeCode . PHP_EOL);
        exit(2);
    }
} catch (Throwable) {
    fwrite(STDERR, 'Update status refresher failed.' . PHP_EOL);
    exit(1);
}
