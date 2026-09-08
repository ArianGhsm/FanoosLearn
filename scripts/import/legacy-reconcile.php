<?php

declare(strict_types=1);

use Fanoos\Platform\Migration\LegacyReconciler;
use Fanoos\Platform\Support\DatabaseConnection;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$options = getopt('', ['batch:']);
$batchId = $options['batch'] ?? null;
if (!is_string($batchId) || !preg_match('/^[0-9a-f-]{36}$/i', $batchId)) {
    fwrite(STDERR, "Usage: php scripts/import/legacy-reconcile.php --batch=<migration-batch-uuid>\n");
    exit(2);
}

try {
    $report = (new LegacyReconciler(DatabaseConnection::fromEnvironment()))->reconcile($batchId);
    echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($report['status'] === 'PASS' ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
