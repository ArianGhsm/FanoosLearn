<?php

declare(strict_types=1);

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\Uuid;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    if ($argc !== 4) {
        throw new RuntimeException('Usage: php register-deployment-target.php <target-key> <node-key> <service-key>');
    }
    [, $targetKey, $nodeKey, $serviceKey] = $argv;
    foreach ([$targetKey, $nodeKey, $serviceKey] as $value) {
        if (!preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $value)) {
            throw new RuntimeException('Deployment target metadata is invalid.');
        }
    }
    $db = DatabaseConnection::fromEnvironment();
    $statement = $db->prepare(<<<'SQL'
INSERT INTO release_update_targets (id, target_key, node_key, service_key, status, created_at, updated_at)
VALUES (:id, :target, :node, :service, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE node_key = VALUES(node_key), service_key = VALUES(service_key), status = 'active', updated_at = UTC_TIMESTAMP(6)
SQL);
    $statement->execute(['id' => Uuid::v7(), 'target' => $targetKey, 'node' => $nodeKey, 'service' => $serviceKey]);
    echo 'Deployment target registered.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Deployment target registration failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
