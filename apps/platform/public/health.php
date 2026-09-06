<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\HealthCheck;

require dirname(__DIR__) . '/bootstrap.php';

$readiness = ($_GET['mode'] ?? 'live') === 'ready';
$report = HealthCheck::run($readiness);

http_response_code($report['status'] === 'ok' ? 200 : 503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
