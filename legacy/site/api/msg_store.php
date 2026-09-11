<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const MSG_SCHEMA_VERSION = 1;
const MSG_ID_PREFIX = 'msg-';
const MSG_PUBLIC_PATH = '/msg/v/';

function msg_store_path(): string
{
    return dent_storage_path('msg/store.json');
}

function msg_store_lock_path(): string
{
    return dent_storage_path('msg/store.lock');
}

function msg_default_store(): array
{
    return ['schemaVersion' => MSG_SCHEMA_VERSION, 'cards' => []];
}

function msg_ensure_storage(): void
{
    dent_ensure_directory(dirname(msg_store_path()));
    if (!is_file(msg_store_path())) {
        dent_write_json_file(msg_store_path(), msg_default_store());
    }
}

function msg_acquire_lock(int $mode)
{
    msg_ensure_storage();
    $lock = fopen(msg_store_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در قفل فضای ذخیره‌سازی.', 500);
    }
    if (!flock($lock, $mode)) {
        fclose($lock);
        dent_error('قفل فضای ذخیره‌سازی آماده نشد.', 500);
    }
    return $lock;
}

function msg_load_unlocked(): array
{
    msg_ensure_storage();
    $raw = dent_read_json_file(msg_store_path(), msg_default_store());
    if (!isset($raw['cards']) || !is_array($raw['cards'])) {
        throw new DentJsonPersistenceException(
            'MSG_STORE_SCHEMA_INVALID',
            'Existing message-card store has an invalid schema'
        );
    }
    return $raw;
}

function msg_read_store(): array
{
    $lock = msg_acquire_lock(LOCK_SH);
    try {
        return msg_load_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function msg_with_write_lock(callable $callback): void
{
    $lock = msg_acquire_lock(LOCK_EX);
    try {
        $store = msg_load_unlocked();
        $store = $callback($store);
        dent_write_json_file(msg_store_path(), $store);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function msg_next_id(): string
{
    try {
        return MSG_ID_PREFIX . bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        return MSG_ID_PREFIX . strtolower(str_replace('.', '', uniqid('', true)));
    }
}

function msg_next_token(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, 32);
    }
}

function msg_clean_token(string $value): string
{
    $value = trim(strtolower($value));
    return preg_match('/^[a-f0-9]{32}$/', $value) === 1 ? $value : '';
}

function msg_clean_id(string $value): string
{
    $value = trim(strtolower($value));
    return preg_match('/^msg-[a-f0-9]{12}$/', $value) === 1 ? $value : '';
}

function msg_find_by_token(array $store, string $token): ?array
{
    $token = msg_clean_token($token);
    if ($token === '') {
        return null;
    }
    foreach (($store['cards'] ?? []) as $record) {
        if (is_array($record) && ($record['token'] ?? '') === $token && ($record['deleted'] ?? false) === false) {
            return $record;
        }
    }
    return null;
}

function msg_public_url(string $token): string
{
    return MSG_PUBLIC_PATH . '?token=' . rawurlencode($token);
}
