<?php

declare(strict_types=1);

use Fanoos\Platform\Migration\MigrationPreflight;
use Fanoos\Platform\Support\DatabaseConnection;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $allowBootstrap = in_array('--allow-bootstrap', $argv, true);
    $report = (new MigrationPreflight(DatabaseConnection::fromEnvironment(), $root . '/database/migrations'))->inspect($allowBootstrap);
    echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration preflight failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
