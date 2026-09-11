<?php
declare(strict_types=1);

if (!defined('DENT_PROJECT_ROOT')) {
    define('DENT_PROJECT_ROOT', dirname(__DIR__, 2));
}

final class DentJsonPersistenceException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}

function dent_env_value(string $name): string
{
    $value = getenv($name);
    if (!is_string($value)) {
        return '';
    }

    return trim($value);
}

function dent_is_absolute_path(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        return preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\');
    }

    return str_starts_with($path, '/');
}

function dent_resolve_path(string $path, string $basePath): string
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));
    if ($normalized === '') {
        return rtrim($basePath, '\\/');
    }

    if (dent_is_absolute_path($normalized)) {
        return rtrim($normalized, '\\/');
    }

    return rtrim($basePath, '\\/') . DIRECTORY_SEPARATOR . trim($normalized, '\\/');
}

function dent_default_server_only_root(): string
{
    $configured = dent_env_value('DENT_SERVER_ONLY_ROOT');
    if ($configured !== '') {
        return dent_resolve_path($configured, DENT_PROJECT_ROOT);
    }

    return DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'server-only';
}

function dent_default_storage_root(): string
{
    $configured = dent_env_value('DENT_STORAGE_ROOT');
    if ($configured !== '') {
        return dent_resolve_path($configured, DENT_PROJECT_ROOT);
    }

    $serverOnlyStorage = DENT_SERVER_ONLY_ROOT . DIRECTORY_SEPARATOR . 'storage';
    $legacyStorage = DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'storage';

    if (is_dir($serverOnlyStorage) || !is_dir($legacyStorage)) {
        return $serverOnlyStorage;
    }

    return $legacyStorage;
}

function dent_default_session_save_path(): string
{
    $configured = dent_env_value('DENT_SESSION_SAVE_PATH');
    if ($configured !== '') {
        return dent_resolve_path($configured, DENT_PROJECT_ROOT);
    }

    return DENT_SERVER_ONLY_ROOT . DIRECTORY_SEPARATOR . 'sessions';
}

if (!defined('DENT_SERVER_ONLY_ROOT')) {
    define('DENT_SERVER_ONLY_ROOT', dent_default_server_only_root());
}

if (!defined('DENT_STORAGE_ROOT')) {
    define('DENT_STORAGE_ROOT', dent_default_storage_root());
}

if (!defined('DENT_TMP_ROOT')) {
    define('DENT_TMP_ROOT', DENT_SERVER_ONLY_ROOT . DIRECTORY_SEPARATOR . 'tmp');
}

if (!defined('DENT_BACKUP_ROOT')) {
    define('DENT_BACKUP_ROOT', DENT_SERVER_ONLY_ROOT . DIRECTORY_SEPARATOR . 'backups');
}

if (!defined('DENT_SECRETS_ROOT')) {
    define('DENT_SECRETS_ROOT', DENT_SERVER_ONLY_ROOT . DIRECTORY_SEPARATOR . 'secrets');
}

date_default_timezone_set('Asia/Tehran');

function dent_load_env_files(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $paths = [];
    $explicit = getenv('DENT_ENV_FILE');
    if (is_string($explicit) && trim($explicit) !== '') {
        $paths[] = trim($explicit);
    }
    $paths[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_SERVER_ONLY_ROOT . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . '.env';
    $paths[] = DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'sms.env';

    foreach (array_unique($paths) as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '' || preg_match('/^[A-Z_][A-Z0-9_]*$/', $name) !== 1) {
                continue;
            }
            if (getenv($name) !== false) {
                continue;
            }

            $value = trim($value);
            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function dent_bootstrap(): void
{
    static $booted = false;

    if ($booted) {
        return;
    }

    $booted = true;
    dent_load_env_files();

    $withoutSession = defined('DENT_BOOTSTRAP_WITHOUT_SESSION') && DENT_BOOTSTRAP_WITHOUT_SESSION === true;
    if (!$withoutSession) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        $sessionLifetime = 60 * 60 * 24 * 30;
        ini_set('session.cookie_lifetime', (string) $sessionLifetime);
        ini_set('session.gc_maxlifetime', (string) $sessionLifetime);

        $sessionSavePath = dent_default_session_save_path();
        if ($sessionSavePath !== '' && (is_dir($sessionSavePath) || @mkdir($sessionSavePath, 0755, true))) {
            ini_set('session.save_path', $sessionSavePath);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
            $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
            $isSecure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || $forwardedProto === 'https'
                || $forwardedSsl === 'on';

            session_name('dent1402_session');
            session_set_cookie_params([
                'lifetime' => $sessionLifetime,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }

    set_exception_handler(static function (Throwable $exception): void {
        $status = $exception instanceof DentJsonPersistenceException ? 503 : 500;
        dent_error_log_record([
            'type' => 'exception',
            'message' => get_class($exception) . ': ' . $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'status' => $status,
        ]);

        if (headers_sent()) {
            return;
        }

        if ($exception instanceof DentJsonPersistenceException) {
            dent_json_response([
                'success' => false,
                'error' => 'ذخیره‌سازی موقتاً در دسترس نیست؛ داده موجود دست‌نخورده باقی ماند.',
                'code' => $exception->reasonCode,
            ], 503);
        }
        dent_emit_fallback_json_error('خطای داخلی سرور رخ داد.', 500);
    });

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }

        dent_error_log_record([
            'type' => 'fatal',
            'message' => (string) ($error['message'] ?? ''),
            'file' => (string) ($error['file'] ?? ''),
            'line' => (int) ($error['line'] ?? 0),
            'status' => 500,
        ]);

        if (headers_sent()) {
            return;
        }

        dent_emit_fallback_json_error('خطای داخلی سرور رخ داد.', 500);
    });
}

function dent_storage_path(string $relativePath): string
{
    $trimmed = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);

    return DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . $trimmed;
}

function dent_parse_bool($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) !== 0;
    }

    $text = trim(strtolower((string) $value));
    if ($text === '') {
        return $default;
    }
    if (in_array($text, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($text, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function dent_ensure_directory(string $directory): void
{
    if (is_dir($directory)) {
        return;
    }

    if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
        dent_error('امکان آماده‌سازی فضای ذخیره‌سازی وجود ندارد.', 500);
    }
}

function dent_json_response(array $payload, int $statusCode = 200): void
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        $statusCode = 500;
        $json = json_encode([
            'success' => false,
            'error' => 'خطا در تولید پاسخ JSON سرور.',
        ], $flags);

        if ($json === false) {
            $json = '{"success":false,"error":"Server JSON encoding failed."}';
        }
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $contentEncoding = '';
    $acceptEncoding = (string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
    if (strlen($json) >= 1024 && stripos($acceptEncoding, 'gzip') !== false && function_exists('gzencode')) {
        $compressed = gzencode($json, 5);
        if (is_string($compressed) && $compressed !== '') {
            $json = $compressed;
            $contentEncoding = 'gzip';
        }
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        if ($contentEncoding !== '') {
            header('Content-Encoding: ' . $contentEncoding);
            header('Vary: Accept-Encoding');
        }
    }
    echo $json;
    exit;
}

function dent_emit_fallback_json_error(string $message, int $statusCode = 500): void
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode([
        'success' => false,
        'error' => $message,
    ], $flags);

    if ($json === false) {
        $json = '{"success":false,"error":"Server error."}';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }
    echo $json;
    exit;
}

const DENT_ERROR_LOG_MAX_LINES = 1500;

function dent_error_log_path(): string
{
    return DENT_STORAGE_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'errors.jsonl';
}

function dent_sanitize_log_text(string $text, int $maxLength): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $text = preg_replace('/([?&](?:token|csrfToken|csrf|password|secret|credential|cookie|session)=)[^&\s]+/i', '$1[redacted]', $text) ?? $text;
    $text = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1[redacted]', $text) ?? $text;
    foreach ([DENT_STORAGE_ROOT, DENT_SERVER_ONLY_ROOT, DENT_TMP_ROOT, DENT_PROJECT_ROOT] as $root) {
        $root = trim((string) $root);
        if ($root !== '') {
            $text = str_replace([$root, str_replace('\\', '/', $root), str_replace('/', '\\', $root)], '[private-path]', $text);
        }
    }
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $maxLength, 'UTF-8');
    }
    return substr($text, 0, $maxLength);
}

/**
 * Append a structured error record to the shared server error log.
 *
 * Called from the global exception/shutdown handlers and from 5xx responses,
 * so it must be fully defensive: it never throws and never blocks the response.
 */
function dent_error_log_record(array $entry): void
{
    try {
        if (!defined('DENT_STORAGE_ROOT')) {
            return;
        }

        $path = dent_error_log_path();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }

        $clip = static fn($value, int $max): string => dent_sanitize_log_text((string) $value, $max);

        $student = '';
        if (isset($_SESSION) && is_array($_SESSION)) {
            $student = trim((string) ($_SESSION['student_number'] ?? ''));
        }

        $record = [
            'at' => date('c'),
            'type' => $clip($entry['type'] ?? 'error', 24),
            'message' => $clip($entry['message'] ?? '', 600),
            'file' => $clip($entry['file'] ?? '', 220),
            'line' => max(0, (int) ($entry['line'] ?? 0)),
            'status' => max(0, (int) ($entry['status'] ?? 0)),
            'method' => $clip($_SERVER['REQUEST_METHOD'] ?? '', 10),
            'uri' => $clip($_SERVER['REQUEST_URI'] ?? '', 300),
            'action' => $clip($_POST['action'] ?? ($_GET['action'] ?? ''), 80),
            'user' => $student,
            'ua' => $clip($_SERVER['HTTP_USER_AGENT'] ?? '', 220),
        ];

        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return;
        }
        try {
            if (!@flock($handle, LOCK_EX)) {
                return;
            }
            @fseek($handle, 0, SEEK_END);
            @fwrite($handle, $line . "\n");

            // Occasionally trim the log so it cannot grow without bound.
            $size = @ftell($handle);
            if ($size !== false && $size > 1500000) {
                @rewind($handle);
                $contents = @stream_get_contents($handle);
                if (is_string($contents) && $contents !== '') {
                    $lines = preg_split('/\n/', rtrim($contents, "\n")) ?: [];
                    if (count($lines) > DENT_ERROR_LOG_MAX_LINES) {
                        $lines = array_slice($lines, -DENT_ERROR_LOG_MAX_LINES);
                        $rewritten = implode("\n", $lines) . "\n";
                        @ftruncate($handle, 0);
                        @rewind($handle);
                        @fwrite($handle, $rewritten);
                    }
                }
            }
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    } catch (\Throwable $loggingError) {
        // Logging must never interfere with the actual response.
    }
}

function dent_error(string $message, int $statusCode = 400, array $extra = []): void
{
    if ($statusCode >= 500) {
        dent_error_log_record([
            'type' => 'handled',
            'message' => $message,
            'status' => $statusCode,
        ]);
    }

    dent_json_response(array_merge([
        'success' => false,
        'error' => $message,
    ], $extra), $statusCode);
}

function dent_read_json_file(string $path, $default)
{
    if (!file_exists($path)) {
        return $default;
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        throw new DentJsonPersistenceException('JSON_STORE_UNREADABLE', 'Existing JSON store is unreadable or empty');
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new DentJsonPersistenceException('JSON_STORE_CORRUPT', 'Existing JSON store is malformed');
    }
    if (is_array($default) && !is_array($decoded)) {
        throw new DentJsonPersistenceException('JSON_STORE_SCHEMA_INVALID', 'Existing JSON store has an invalid root type');
    }

    return $decoded;
}

function dent_write_json_file(string $path, $payload, bool $lockAlreadyHeld = false): void
{
    dent_ensure_directory(dirname($path));

    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode($payload, $flags);
    if ($json === false) {
        dent_error('خطا در تولید داده JSON.', 500);
    }

    $encoded = $json . PHP_EOL;
    $lock = null;
    if (!$lockAlreadyHeld) {
        $lockPath = $path . '.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false || !@flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                @fclose($lock);
            }
            throw new DentJsonPersistenceException('JSON_STORE_LOCK_FAILED', 'Unable to lock JSON store');
        }
    }
    $temp = '';
    try {
        if (is_file($path)) {
            $current = @file_get_contents($path);
            if ($current === false || trim($current) === '') {
                throw new DentJsonPersistenceException('JSON_STORE_UNREADABLE', 'Existing JSON store is unreadable or empty');
            }
            $currentDecoded = json_decode($current, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new DentJsonPersistenceException('JSON_STORE_CORRUPT', 'Existing JSON store is malformed');
            }
            if (is_array($payload) && !is_array($currentDecoded)) {
                throw new DentJsonPersistenceException('JSON_STORE_SCHEMA_INVALID', 'Existing JSON store has an invalid root type');
            }
            if (hash_equals(hash('sha256', $current), hash('sha256', $encoded))) {
                return;
            }
        }
        $temp = $path . '.tmp.' . bin2hex(random_bytes(6));
        $handle = @fopen($temp, 'xb');
        if ($handle === false) {
            throw new DentJsonPersistenceException('JSON_STORE_TEMP_OPEN_FAILED', 'Unable to open JSON temporary generation');
        }
        $writtenTotal = 0;
        try {
            $expected = strlen($encoded);
            while ($writtenTotal < $expected) {
                // Shared hosting can reject a multi-megabyte fwrite even when
                // the temporary file and filesystem are healthy.  Keep the
                // atomic generation protocol, but bound each syscall so a
                // large notification/auth store is written incrementally.
                $remaining = min(65536, $expected - $writtenTotal);
                $written = @fwrite($handle, substr($encoded, $writtenTotal, $remaining));
                if ($written === false || $written === 0) {
                    throw new DentJsonPersistenceException('JSON_STORE_SHORT_WRITE', 'Short JSON generation write');
                }
                $writtenTotal += $written;
            }
            if (!@fflush($handle)) {
                throw new DentJsonPersistenceException('JSON_STORE_FLUSH_FAILED', 'Unable to flush JSON generation');
            }
            if (function_exists('fsync') && !@fsync($handle)) {
                throw new DentJsonPersistenceException('JSON_STORE_FSYNC_FAILED', 'Unable to sync JSON generation');
            }
        } finally {
            @fclose($handle);
        }
        $verified = @file_get_contents($temp);
        if ($verified === false || strlen($verified) !== strlen($encoded)) {
            throw new DentJsonPersistenceException('JSON_STORE_TEMP_INVALID', 'JSON generation length validation failed');
        }
        json_decode($verified, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DentJsonPersistenceException('JSON_STORE_TEMP_INVALID', 'JSON generation decode validation failed');
        }
        if (!@rename($temp, $path)) {
            throw new DentJsonPersistenceException('JSON_STORE_COMMIT_FAILED', 'Atomic JSON generation commit failed');
        }
        $temp = '';
    } finally {
        if ($temp !== '' && is_file($temp)) {
            @unlink($temp);
        }
        if (is_resource($lock)) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }
}

function dent_load_or_create_base64_secret_file(string $path): string
{
    dent_ensure_directory(dirname($path));
    $lock = @fopen($path . '.lock', 'c');
    if ($lock === false || !@flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            @fclose($lock);
        }
        throw new DentJsonPersistenceException('SECRET_KEY_LOCK_FAILED', 'Unable to lock secret key storage');
    }

    $temp = '';
    try {
        if (file_exists($path)) {
            $raw = @file_get_contents($path);
            if ($raw === false || trim($raw) === '') {
                throw new DentJsonPersistenceException('SECRET_KEY_UNREADABLE', 'Existing secret key is unreadable or empty');
            }
            $decoded = base64_decode(trim($raw), true);
            if (!is_string($decoded) || strlen($decoded) !== 32) {
                throw new DentJsonPersistenceException('SECRET_KEY_CORRUPT', 'Existing secret key is invalid');
            }
            return $decoded;
        }

        $key = random_bytes(32);
        $encoded = base64_encode($key) . PHP_EOL;
        $temp = $path . '.tmp.' . bin2hex(random_bytes(6));
        $handle = @fopen($temp, 'xb');
        if ($handle === false) {
            throw new DentJsonPersistenceException('SECRET_KEY_TEMP_OPEN_FAILED', 'Unable to open secret key temporary generation');
        }
        $writtenTotal = 0;
        try {
            $expected = strlen($encoded);
            while ($writtenTotal < $expected) {
                $written = @fwrite($handle, substr($encoded, $writtenTotal));
                if ($written === false || $written === 0) {
                    throw new DentJsonPersistenceException('SECRET_KEY_SHORT_WRITE', 'Short secret key generation write');
                }
                $writtenTotal += $written;
            }
            if (!@fflush($handle)) {
                throw new DentJsonPersistenceException('SECRET_KEY_FLUSH_FAILED', 'Unable to flush secret key generation');
            }
            if (function_exists('fsync') && !@fsync($handle)) {
                throw new DentJsonPersistenceException('SECRET_KEY_FSYNC_FAILED', 'Unable to sync secret key generation');
            }
        } finally {
            @fclose($handle);
        }
        @chmod($temp, 0600);
        $verified = @file_get_contents($temp);
        $verifiedKey = is_string($verified) ? base64_decode(trim($verified), true) : false;
        if (
            !is_string($verified)
            || strlen($verified) !== strlen($encoded)
            || !is_string($verifiedKey)
            || strlen($verifiedKey) !== 32
            || !hash_equals($key, $verifiedKey)
        ) {
            throw new DentJsonPersistenceException('SECRET_KEY_TEMP_INVALID', 'Secret key generation validation failed');
        }
        if (!@rename($temp, $path)) {
            throw new DentJsonPersistenceException('SECRET_KEY_COMMIT_FAILED', 'Atomic secret key generation commit failed');
        }
        $temp = '';
        return $key;
    } finally {
        if ($temp !== '' && is_file($temp)) {
            @unlink($temp);
        }
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function dent_request_action(): string
{
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    return trim((string) $action);
}

function dent_request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function dent_release_session_lock(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function dent_normalize_digits(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return str_replace(
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        $value
    );
}

function dent_normalize_student_number(?string $value): string
{
    return preg_replace('/\D+/u', '', dent_normalize_digits($value)) ?? '';
}

function dent_iso_now(): string
{
    return date('c');
}

function dent_utf8_strlen(string $value): int
{
    if ($value === '') {
        return 0;
    }

    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }

    if (preg_match_all('/./us', $value, $matches) === false) {
        return strlen($value);
    }

    return count($matches[0]);
}

function dent_utf8_substr(string $value, int $start, int $length): string
{
    if ($value === '' || $length <= 0) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return (string) mb_substr($value, $start, $length, 'UTF-8');
    }

    if (preg_match_all('/./us', $value, $matches) === false) {
        return substr($value, $start, $length);
    }

    return implode('', array_slice($matches[0], $start, $length));
}

function dent_utf8_strtolower(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        return (string) mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function dent_clean_text(?string $value, int $maxLength): string
{
    $value = trim(dent_force_utf8((string) $value));
    $value = preg_replace('/\r\n|\r/u', "\n", $value) ?? '';
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

    if ($value === '') {
        return '';
    }

    if (dent_utf8_strlen($value) > $maxLength) {
        $value = dent_utf8_substr($value, 0, $maxLength);
    }

    return trim($value);
}

function dent_force_utf8(?string $value): string
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }

    if (preg_match('//u', $value) === 1) {
        return $value;
    }

    $encodings = ['Windows-1256', 'CP1256', 'Windows-1252', 'ISO-8859-1'];

    if (function_exists('iconv')) {
        foreach ($encodings as $encoding) {
            $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8,Windows-1256,CP1256,Windows-1252,ISO-8859-1');
        if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
            return $converted;
        }
    }

    return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';
}

dent_bootstrap();
