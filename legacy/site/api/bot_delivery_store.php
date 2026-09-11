<?php
declare(strict_types=1);

/** Independent durable queues: identity corruption must never erase delivery state. */

function dent_bot_payment_delivery_store_path(): string
{
    return dent_storage_path('integrations/bot_payment_deliveries.json');
}

function dent_bot_notification_delivery_store_path(): string
{
    return dent_storage_path('integrations/bot_notification_deliveries.json');
}

function dent_bot_payment_delivery_store_default(): array
{
    return [
        'schemaVersion' => 1,
        'deliveries' => [],
        'poll' => ['highWatermark' => 0, 'unresolvedOrderIds' => []],
        'migration' => [],
    ];
}

function dent_bot_notification_delivery_store_default(): array
{
    return [
        'schemaVersion' => 1,
        'deliveries' => [],
        'dispatchSince' => '',
        'migration' => [],
    ];
}

function dent_bot_payment_delivery_store_normalize(array $store): array
{
    foreach (['deliveries', 'poll', 'migration'] as $key) {
        if (array_key_exists($key, $store) && !is_array($store[$key])) {
            throw new DentBotPersistenceException('BOT_PAYMENT_DELIVERY_STORE_SCHEMA_INVALID', 'Payment delivery store schema is invalid');
        }
    }
    $store = array_merge(dent_bot_payment_delivery_store_default(), $store);
    $store['schemaVersion'] = max(1, (int) ($store['schemaVersion'] ?? 0));
    if (isset($store['_storage']) && !is_array($store['_storage'])) {
        throw new DentBotPersistenceException('BOT_PAYMENT_DELIVERY_STORE_SCHEMA_INVALID', 'Payment delivery generation metadata is invalid');
    }
    return $store;
}

function dent_bot_notification_delivery_store_normalize(array $store): array
{
    foreach (['deliveries', 'migration'] as $key) {
        if (array_key_exists($key, $store) && !is_array($store[$key])) {
            throw new DentBotPersistenceException('BOT_NOTIFICATION_DELIVERY_STORE_SCHEMA_INVALID', 'Notification delivery store schema is invalid');
        }
    }
    if (array_key_exists('dispatchSince', $store) && !is_string($store['dispatchSince'])) {
        throw new DentBotPersistenceException('BOT_NOTIFICATION_DELIVERY_STORE_SCHEMA_INVALID', 'Notification dispatch cursor is invalid');
    }
    $store = array_merge(dent_bot_notification_delivery_store_default(), $store);
    $store['schemaVersion'] = max(1, (int) ($store['schemaVersion'] ?? 0));
    if (isset($store['_storage']) && !is_array($store['_storage'])) {
        throw new DentBotPersistenceException('BOT_NOTIFICATION_DELIVERY_STORE_SCHEMA_INVALID', 'Notification delivery generation metadata is invalid');
    }
    return $store;
}

function dent_bot_delivery_store_fail(DentBotPersistenceException $exception): never
{
    dent_error('صف پایدار ربات موقتاً در دسترس نیست.', 503, ['code' => $exception->reasonCode]);
}

function dent_bot_payment_delivery_store_with_lock(callable $callback, string $action): array
{
    try {
        return dent_bot_persistence_update(
            dent_bot_payment_delivery_store_path(),
            dent_bot_payment_delivery_store_default(),
            'dent_bot_payment_delivery_store_normalize',
            $callback,
            false,
            $action
        );
    } catch (DentBotPersistenceException $exception) {
        dent_bot_delivery_store_fail($exception);
    }
}

function dent_bot_notification_delivery_store_with_lock(callable $callback, string $action): array
{
    try {
        return dent_bot_persistence_update(
            dent_bot_notification_delivery_store_path(),
            dent_bot_notification_delivery_store_default(),
            'dent_bot_notification_delivery_store_normalize',
            $callback,
            false,
            $action
        );
    } catch (DentBotPersistenceException $exception) {
        dent_bot_delivery_store_fail($exception);
    }
}

function dent_bot_identity_snapshot_optional(): ?array
{
    try {
        $result = dent_bot_persistence_read(
            dent_bot_store_path(),
            dent_bot_store_default(),
            'dent_bot_store_normalize',
            static fn(array $store): array => ['store' => $store],
            'delivery-identity-snapshot'
        );
        return is_array($result['store'] ?? null) ? $result['store'] : null;
    } catch (DentBotPersistenceException $exception) {
        error_log('DENT_BOT_DELIVERY ' . json_encode([
            'event' => 'identity-store-unavailable',
            'reasonCode' => $exception->reasonCode,
            'requestId' => dent_bot_persistence_request_id(),
        ], JSON_UNESCAPED_SLASHES));
        return null;
    }
}

function dent_bot_route_encrypted_from_identity(array $identityStore, string $identityHash, string $platform): mixed
{
    $link = $identityStore['links'][$identityHash] ?? null;
    if (is_array($link) && (string) ($link['platform'] ?? '') === $platform && dent_bot_link_auth_complete($link)) {
        return $link['platformUserIdEncrypted'] ?? null;
    }
    $route = $identityStore['onboardingIdentityRoutes'][$identityHash] ?? null;
    if (is_array($route) && (string) ($route['platform'] ?? '') === $platform) {
        return $route['platformUserIdEncrypted'] ?? null;
    }
    return null;
}

function dent_bot_enrich_delivery_routes(array $deliveries, array $identityStore): array
{
    foreach ($deliveries as $key => $delivery) {
        if (!is_array($delivery) || isset($delivery['platformUserIdEncrypted'])) {
            continue;
        }
        $identityHash = (string) ($delivery['identityHash'] ?? '');
        $platform = (string) ($delivery['platform'] ?? '');
        $encrypted = dent_bot_route_encrypted_from_identity($identityStore, $identityHash, $platform);
        if ($encrypted !== null) {
            $delivery['platformUserIdEncrypted'] = $encrypted;
            $deliveries[$key] = $delivery;
        }
    }
    return $deliveries;
}

function dent_bot_delivery_stores_ensure_migrated(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $paymentMissing = !is_file(dent_bot_payment_delivery_store_path());
    $notificationMissing = !is_file(dent_bot_notification_delivery_store_path());
    if ($paymentMissing || $notificationMissing) {
        $identityStore = dent_bot_identity_snapshot_optional();
        if (!is_array($identityStore)) {
            throw new DentBotPersistenceException('BOT_DELIVERY_MIGRATION_IDENTITY_UNAVAILABLE', 'Cannot seed delivery stores without a valid identity generation');
        }
        $sourceHash = hash('sha256', dent_bot_persistence_encode($identityStore));
        $migratedAt = dent_iso_now();
    }
    if ($paymentMissing && isset($identityStore, $sourceHash, $migratedAt)) {
        $seed = dent_bot_payment_delivery_store_default();
        $seed['deliveries'] = dent_bot_enrich_delivery_routes(
            is_array($identityStore['paymentResultDeliveries'] ?? null) ? $identityStore['paymentResultDeliveries'] : [],
            $identityStore
        );
        $seed['poll'] = is_array($identityStore['paymentResultPoll'] ?? null)
            ? $identityStore['paymentResultPoll']
            : $seed['poll'];
        $seed['migration'] = ['source' => 'bot_links.json', 'sourceHash' => $sourceHash, 'migratedAt' => $migratedAt];
        dent_bot_persistence_initialize(
            dent_bot_payment_delivery_store_path(),
            $seed,
            'dent_bot_payment_delivery_store_normalize',
            'migrate-payment-delivery-store'
        );
    }
    if ($notificationMissing && isset($identityStore, $sourceHash, $migratedAt)) {
        $seed = dent_bot_notification_delivery_store_default();
        $seed['deliveries'] = dent_bot_enrich_delivery_routes(
            is_array($identityStore['notificationDeliveries'] ?? null) ? $identityStore['notificationDeliveries'] : [],
            $identityStore
        );
        $seed['dispatchSince'] = (string) ($identityStore['notificationDispatchSince'] ?? '');
        $seed['migration'] = ['source' => 'bot_links.json', 'sourceHash' => $sourceHash, 'migratedAt' => $migratedAt];
        dent_bot_persistence_initialize(
            dent_bot_notification_delivery_store_path(),
            $seed,
            'dent_bot_notification_delivery_store_normalize',
            'migrate-notification-delivery-store'
        );
    }
    dent_bot_finalize_identity_delivery_split();
    $ready = true;
}

/**
 * Once both independent queues are durable, remove their legacy copies from
 * bot_links.json. Failure here must not stop either queue worker: the split
 * files are already authoritative and the legacy fields are only rollback
 * baggage at that point.
 */
function dent_bot_finalize_identity_delivery_split(): void
{
    if (!is_file(dent_bot_payment_delivery_store_path()) || !is_file(dent_bot_notification_delivery_store_path())) {
        return;
    }
    $identityStore = dent_bot_identity_snapshot_optional();
    if (!is_array($identityStore)) {
        return;
    }
    $migration = is_array($identityStore['deliveryStoreMigration'] ?? null)
        ? $identityStore['deliveryStoreMigration']
        : [];
    if ((string) ($migration['status'] ?? '') === 'complete'
        && empty($identityStore['paymentResultDeliveries'])
        && empty($identityStore['notificationDeliveries'])) {
        return;
    }
    try {
        dent_bot_persistence_update(
            dent_bot_store_path(),
            dent_bot_store_default(),
            'dent_bot_store_normalize',
            static function (array &$store): array {
                $store['paymentResultDeliveries'] = [];
                $store['paymentResultPoll'] = ['highWatermark' => 0, 'unresolvedOrderIds' => []];
                $store['notificationDeliveries'] = [];
                $store['notificationDispatchSince'] = '';
                $store['deliveryStoreMigration'] = [
                    'status' => 'complete',
                    'schemaVersion' => 1,
                    'completedAt' => dent_iso_now(),
                    'paymentStore' => basename(dent_bot_payment_delivery_store_path()),
                    'notificationStore' => basename(dent_bot_notification_delivery_store_path()),
                ];
                return ['compacted' => true];
            },
            false,
            'finalize-delivery-store-split'
        );
    } catch (DentBotPersistenceException $exception) {
        error_log('DENT_BOT_DELIVERY ' . json_encode([
            'event' => 'identity-store-compaction-deferred',
            'reasonCode' => $exception->reasonCode,
            'requestId' => dent_bot_persistence_request_id(),
        ], JSON_UNESCAPED_SLASHES));
    }
}

function dent_bot_cleanup_payment_delivery_store(array &$store, int $now): void
{
    $retentionDays = max(7, min(365, (int) (getenv('DENT_BOT_NOTIFICATION_DELIVERY_RETENTION_DAYS') ?: 90)));
    $terminalBefore = $now - ($retentionDays * 86400);
    foreach ($store['deliveries'] as $key => $delivery) {
        if (!is_array($delivery)) {
            unset($store['deliveries'][$key]);
            continue;
        }
        $status = (string) ($delivery['status'] ?? '');
        $referenceAt = strtotime((string) (($delivery['deliveredAt'] ?? '') ?: ($delivery['lastAttemptAt'] ?? '') ?: ($delivery['createdAt'] ?? '')));
        if (in_array($status, ['delivered', 'failed'], true) && $referenceAt !== false && $referenceAt < $terminalBefore) {
            unset($store['deliveries'][$key]);
        }
    }
}

function dent_bot_cleanup_notification_delivery_store(array &$store, int $now): void
{
    $retentionDays = max(7, min(365, (int) (getenv('DENT_BOT_NOTIFICATION_DELIVERY_RETENTION_DAYS') ?: 90)));
    $terminalBefore = $now - ($retentionDays * 86400);
    foreach ($store['deliveries'] as $key => $delivery) {
        if (!is_array($delivery)) {
            unset($store['deliveries'][$key]);
            continue;
        }
        $status = (string) ($delivery['status'] ?? '');
        $referenceAt = strtotime((string) (($delivery['deliveredAt'] ?? '') ?: ($delivery['lastAttemptAt'] ?? '')));
        if (in_array($status, ['delivered', 'failed'], true) && $referenceAt !== false && $referenceAt < $terminalBefore) {
            unset($store['deliveries'][$key]);
        }
    }
}
