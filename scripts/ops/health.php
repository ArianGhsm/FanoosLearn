<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\HealthCheck;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$report = HealthCheck::run(true);
echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
exit($report['status'] === 'ok' ? 0 : 1);
