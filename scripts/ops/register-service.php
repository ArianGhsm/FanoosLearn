<?php

declare(strict_types=1);

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\Uuid;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    if ($argc !== 6) {
        throw new RuntimeException('Usage: php register-service.php <service-key> <service-type> <key-id> <secret-env-name> <comma-separated-actions>');
    }
    [, $serviceKey, $serviceType, $keyId, $secretEnvName, $actionsRaw] = $argv;
    $types = ['telegram_adapter', 'bale_adapter', 'protected_media_worker', 'notification_worker', 'deployment_updater'];
    if (!preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $serviceKey)
        || !in_array($serviceType, $types, true)
        || !preg_match('/^[A-Za-z0-9._:-]{3,96}$/', $keyId)
        || !preg_match('/^FANOOS_[A-Z0-9_]{3,150}$/', $secretEnvName)) {
        throw new RuntimeException('Service registration metadata is invalid.');
    }
    $actions = array_values(array_unique(array_filter(array_map('trim', explode(',', $actionsRaw)))));
    foreach ($actions as $action) {
        if (!preg_match('/^[a-z0-9_.:-]{3,96}$/', $action)) {
            throw new RuntimeException('Service action name is invalid.');
        }
    }
    if ($actions === []) {
        throw new RuntimeException('At least one service action is required.');
    }

    $db = DatabaseConnection::fromEnvironment();
    $db->beginTransaction();
    try {
        $find = $db->prepare('SELECT id FROM integration_service_identities WHERE service_key = :key FOR UPDATE');
        $find->execute(['key' => $serviceKey]);
        $serviceId = $find->fetchColumn();
        if ($serviceId === false) {
            $serviceId = Uuid::v7();
            $db->prepare("INSERT INTO integration_service_identities (id, service_key, service_type, allowed_actions_json, status, created_at, updated_at) VALUES (:id, :key, :type, :actions, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
                ->execute(['id' => $serviceId, 'key' => $serviceKey, 'type' => $serviceType, 'actions' => json_encode($actions, JSON_THROW_ON_ERROR)]);
        } else {
            $db->prepare("UPDATE integration_service_identities SET service_type = :type, allowed_actions_json = :actions, status = 'active', updated_at = UTC_TIMESTAMP(6) WHERE id = :id")
                ->execute(['type' => $serviceType, 'actions' => json_encode($actions, JSON_THROW_ON_ERROR), 'id' => $serviceId]);
        }
        $existingKey = $db->prepare('SELECT id FROM integration_service_keys WHERE key_id = :key_id FOR UPDATE');
        $existingKey->execute(['key_id' => $keyId]);
        $serviceKeyId = $existingKey->fetchColumn();
        if ($serviceKeyId === false) {
            $db->prepare("INSERT INTO integration_service_keys (id, service_id, key_id, secret_env_name, status, valid_from, created_at) VALUES (:id, :service, :key_id, :env, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
                ->execute(['id' => Uuid::v7(), 'service' => $serviceId, 'key_id' => $keyId, 'env' => $secretEnvName]);
        } else {
            $db->prepare("UPDATE integration_service_keys SET service_id = :service, secret_env_name = :env, status = 'active', revoked_at = NULL WHERE id = :id")
                ->execute(['service' => $serviceId, 'env' => $secretEnvName, 'id' => $serviceKeyId]);
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
    echo 'Service metadata registered; secret value remains outside Git and SQL.' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Service registration failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
