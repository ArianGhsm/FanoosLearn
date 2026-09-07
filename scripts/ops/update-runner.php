<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\CanonicalMainUpdateExecutor;
use Fanoos\Platform\Operations\DeploymentRunner;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    if ($argc !== 1) {
        throw new RuntimeException('The updater runner accepts no command, ref, SHA, path, or positional arguments.');
    }
    $config = RuntimeConfig::load();
    $target = $config->requireString('FANOOS_UPDATER_TARGET_KEY');
    if (!preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $target)) {
        throw new RuntimeException('Updater target key is invalid.');
    }
    $runner = new DeploymentRunner(
        DatabaseConnection::fromEnvironment(),
        new CanonicalMainUpdateExecutor($config),
    );
    $result = $runner->runNext($target);
    echo json_encode($result ?? ['state' => 'IDLE'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Updater runner failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
