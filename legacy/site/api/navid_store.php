<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function navid_store_path(): string
{
    return dent_storage_path('navid/integration.json');
}

function navid_log_path(): string
{
    return dent_storage_path('navid/sync.log');
}

function navid_secret_key_path(): string
{
    return dent_storage_path('navid/.secret.key');
}

function navid_default_store(): array
{
    return [
        'schemaVersion' => 1,
        'config' => [
            'enabled' => false,
            'loginUrl' => 'https://navid.tums.ac.ir/account/loginsipadservice',
            'syncIntervalMinutes' => 30,
            'captchaStrategy' => 'python_ocr',
            'credentialUpdatedAt' => '',
            'credentials' => null,
        ],
        'session' => [
            'cookies' => null,
            'browserState' => null,
            'updatedAt' => '',
            'lastValidatedAt' => '',
            'status' => 'missing',
        ],
        'challenge' => null,
        'state' => [
            'lastSyncAt' => '',
            'lastSuccessAt' => '',
            'lastError' => '',
            'consecutiveFailures' => 0,
            'requiresReconnect' => false,
            'lastSyncDurationMs' => 0,
            'lastFailedCourses' => 0,
            'lastResult' => '',
        ],
        'snapshot' => [
            'courses' => [],
            'assignments' => [],
        ],
        'updates' => [],
        'automation' => [
            'dailyDate' => '',
            'challengeIssuedAt' => '',
            'challengeExpiresAt' => '',
            'completedAt' => '',
            'lastResult' => '',
        ],
    ];
}

function navid_load_store(): array
{
    $raw = dent_read_json_file(navid_store_path(), navid_default_store());
    if (!isset($raw['config'], $raw['state'], $raw['snapshot'], $raw['updates'])
        || !is_array($raw['config'])
        || !is_array($raw['state'])
        || !is_array($raw['snapshot'])
        || !is_array($raw['updates'])) {
        throw new DentJsonPersistenceException(
            'NAVID_STORE_SCHEMA_INVALID',
            'Existing Navid store has an invalid schema'
        );
    }

    $defaults = navid_default_store();
    $merged = $defaults;

    foreach (['schemaVersion', 'config', 'session', 'challenge', 'state', 'snapshot', 'updates', 'automation'] as $key) {
        if (array_key_exists($key, $raw)) {
            $merged[$key] = $raw[$key];
        }
    }

    if (!is_array($merged['config'])) {
        $merged['config'] = $defaults['config'];
    } else {
        $merged['config'] = array_merge($defaults['config'], $merged['config']);
    }

    if (!is_array($merged['session'])) {
        $merged['session'] = $defaults['session'];
    } else {
        $merged['session'] = array_merge($defaults['session'], $merged['session']);
    }

    if (!is_array($merged['state'])) {
        $merged['state'] = $defaults['state'];
    } else {
        $merged['state'] = array_merge($defaults['state'], $merged['state']);
    }

    if (!is_array($merged['snapshot'])) {
        $merged['snapshot'] = $defaults['snapshot'];
    } else {
        $merged['snapshot'] = array_merge($defaults['snapshot'], $merged['snapshot']);
    }

    if (!is_array($merged['snapshot']['courses'])) {
        $merged['snapshot']['courses'] = [];
    }
    if (!is_array($merged['snapshot']['assignments'])) {
        $merged['snapshot']['assignments'] = [];
    }
    if (!is_array($merged['updates'])) {
        $merged['updates'] = [];
    }
    if (!is_array($merged['automation'])) {
        $merged['automation'] = $defaults['automation'];
    } else {
        $merged['automation'] = array_merge($defaults['automation'], $merged['automation']);
    }

    return $merged;
}

function navid_save_store(array $store): void
{
    // Keep updates bounded to avoid unbounded JSON growth.
    if (is_array($store['updates'] ?? null) && count($store['updates']) > 300) {
        $store['updates'] = array_slice($store['updates'], 0, 300);
    }

    dent_write_json_file(navid_store_path(), $store);
}

function navid_log(string $level, string $event, array $context = []): void
{
    $payload = [
        'time' => dent_iso_now(),
        'level' => $level,
        'event' => $event,
        'context' => $context,
    ];

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $line = json_encode($payload, $flags);
    if ($line === false) {
        return;
    }

    $path = navid_log_path();
    dent_ensure_directory(dirname($path));
    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function navid_secret_key(): string
{
    static $cached = null;
    if (is_string($cached) && strlen($cached) === 32) {
        return $cached;
    }

    $env = trim((string) (getenv('DENT_NAVID_SECRET_KEY') ?: ''));
    if ($env !== '') {
        $decoded = base64_decode($env, true);
        if (is_string($decoded) && strlen($decoded) >= 32) {
            $cached = substr($decoded, 0, 32);
            return $cached;
        }

        $cached = hash('sha256', $env, true);
        return $cached;
    }

    $cached = dent_load_or_create_base64_secret_file(navid_secret_key_path());
    return $cached;
}

function navid_encrypt_text(string $plainText): array
{
    if (!function_exists('openssl_encrypt')) {
        dent_error('رمزنگاری سمت سرور برای نوید در دسترس نیست.', 500);
    }

    $key = navid_secret_key();
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

    if (!is_string($cipher) || $cipher === '' || $tag === '') {
        dent_error('خطا در رمزگذاری اطلاعات نوید.', 500);
    }

    return [
        'v' => 1,
        'alg' => 'aes-256-gcm',
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'ct' => base64_encode($cipher),
    ];
}

function navid_decrypt_text($payload): string
{
    if (!is_array($payload) || !function_exists('openssl_decrypt')) {
        return '';
    }

    $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
    $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
    $ct = base64_decode((string) ($payload['ct'] ?? ''), true);
    if (!is_string($iv) || !is_string($tag) || !is_string($ct)) {
        return '';
    }

    $plain = openssl_decrypt($ct, 'aes-256-gcm', navid_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($plain)) {
        return '';
    }

    return $plain;
}

function navid_encrypt_json($value): array
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($value, $flags);
    if ($json === false) {
        dent_error('خطا در آماده‌سازی داده محرمانه نوید.', 500);
    }

    return navid_encrypt_text($json);
}

function navid_decrypt_json($payload)
{
    $plain = navid_decrypt_text($payload);
    if ($plain === '') {
        return null;
    }

    $decoded = json_decode($plain, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return $decoded;
}

function navid_mask_value(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (strlen($value) <= 3) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 2) . str_repeat('*', max(2, strlen($value) - 4)) . substr($value, -2);
}

function navid_set_credentials(array &$store, string $username, string $password): void
{
    $store['config']['credentials'] = navid_encrypt_json([
        'username' => trim($username),
        'password' => trim($password),
    ]);
    $store['config']['credentialUpdatedAt'] = dent_iso_now();
}

function navid_get_credentials(array $store): ?array
{
    $decrypted = navid_decrypt_json($store['config']['credentials'] ?? null);
    if (!is_array($decrypted)) {
        return null;
    }

    $username = trim((string) ($decrypted['username'] ?? ''));
    $password = trim((string) ($decrypted['password'] ?? ''));
    if ($username === '' || $password === '') {
        return null;
    }

    return [
        'username' => $username,
        'password' => $password,
    ];
}

function navid_set_session_cookies(array &$store, array $cookies): void
{
    $store['session']['cookies'] = navid_encrypt_json($cookies);
    $store['session']['updatedAt'] = dent_iso_now();
    $store['session']['status'] = 'stored';
}

function navid_set_session_browser_state(array &$store, array $browserState): void
{
    $store['session']['browserState'] = navid_encrypt_json($browserState);
    $cookies = is_array($browserState['cookies'] ?? null) ? $browserState['cookies'] : [];
    $store['session']['cookies'] = navid_encrypt_json($cookies);
    $store['session']['updatedAt'] = dent_iso_now();
    $store['session']['status'] = 'stored';
}

function navid_get_session_cookies(array $store): array
{
    $decrypted = navid_decrypt_json($store['session']['cookies'] ?? null);
    return is_array($decrypted) ? $decrypted : [];
}

function navid_get_session_browser_state(array $store): ?array
{
    $decrypted = navid_decrypt_json($store['session']['browserState'] ?? null);
    return is_array($decrypted) ? $decrypted : null;
}

function navid_clear_session_cookies(array &$store): void
{
    $store['session']['cookies'] = null;
    $store['session']['browserState'] = null;
    $store['session']['status'] = 'missing';
    $store['session']['updatedAt'] = dent_iso_now();
}

function navid_set_challenge(array &$store, array $challenge): void
{
    $store['challenge'] = navid_encrypt_json($challenge);
}

function navid_get_challenge(array $store): ?array
{
    $decoded = navid_decrypt_json($store['challenge'] ?? null);
    return is_array($decoded) ? $decoded : null;
}

function navid_clear_challenge(array &$store): void
{
    $store['challenge'] = null;
}

