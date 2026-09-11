<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

const HTML_UPLOADER_SCHEMA_VERSION = 1;
const HTML_UPLOADER_ID_PREFIX = 'hp-';
const HTML_UPLOADER_PUBLIC_PATH = '/html/p/';
const HTML_UPLOADER_MAX_UPLOAD_BYTES = 1024 * 1024;
const HTML_UPLOADER_DEFAULT_TTL_HOURS = 24;
const HTML_UPLOADER_MAX_TTL_HOURS = 24 * 7;
const HTML_UPLOADER_DAILY_UPLOAD_LIMIT = 12;

function html_uploader_store_path(): string
{
    return dent_storage_path('html_uploader/store.json');
}

function html_uploader_lock_path(): string
{
    return dent_storage_path('html_uploader/store.lock');
}

function html_uploader_pages_dir(): string
{
    return dent_storage_path('html_uploader/pages');
}

function html_uploader_default_store(): array
{
    return [
        'schemaVersion' => HTML_UPLOADER_SCHEMA_VERSION,
        'pages' => [],
    ];
}

function html_uploader_ensure_storage(): void
{
    dent_ensure_directory(dirname(html_uploader_store_path()));
    dent_ensure_directory(html_uploader_pages_dir());
    if (!is_file(html_uploader_store_path())) {
        dent_write_json_file(html_uploader_store_path(), html_uploader_default_store());
    }
}

function html_uploader_load_store_unlocked(): array
{
    html_uploader_ensure_storage();
    $raw = dent_read_json_file(html_uploader_store_path(), html_uploader_default_store());
    if (!isset($raw['pages']) || !is_array($raw['pages'])) {
        throw new DentJsonPersistenceException(
            'HTML_UPLOADER_STORE_SCHEMA_INVALID',
            'Existing HTML uploader store has an invalid schema'
        );
    }
    return html_uploader_normalize_store($raw);
}

function html_uploader_read_store(): array
{
    html_uploader_ensure_storage();
    $lock = fopen(html_uploader_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('قفل ماژول HTML uploader در دسترس نیست.', 500);
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            dent_error('قفل خواندن HTML uploader آماده نشد.', 500);
        }
        return html_uploader_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function html_uploader_with_store_lock(callable $callback)
{
    html_uploader_ensure_storage();
    $lock = fopen(html_uploader_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('قفل ماژول HTML uploader در دسترس نیست.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            dent_error('قفل ذخیره‌سازی HTML uploader آماده نشد.', 500);
        }
        $store = html_uploader_load_store_unlocked();
        $result = $callback($store);
        dent_write_json_file(html_uploader_store_path(), html_uploader_normalize_store($store));
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function html_uploader_clean_id(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '' || !str_starts_with($value, HTML_UPLOADER_ID_PREFIX)) {
        return '';
    }
    return preg_match('/^[a-z0-9_-]{5,80}$/', $value) === 1 ? $value : '';
}

function html_uploader_next_id(): string
{
    try {
        return HTML_UPLOADER_ID_PREFIX . bin2hex(random_bytes(6));
    } catch (Throwable $error) {
        return HTML_UPLOADER_ID_PREFIX . strtolower(str_replace('.', '', uniqid('', true)));
    }
}

function html_uploader_random_token(int $bytes = 10): string
{
    try {
        return strtolower(dent_base64url_encode(random_bytes($bytes)));
    } catch (Throwable $error) {
        return substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, $bytes * 2);
    }
}

function html_uploader_clean_token(string $value): string
{
    $value = trim(strtolower($value));
    return preg_match('/^[a-z0-9_-]{8,80}$/', $value) === 1 ? $value : '';
}

function html_uploader_clean_status(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['active', 'hidden', 'deleted'], true) ? $value : 'active';
}

function html_uploader_expiry_from_hours($value): string
{
    $hours = (int) dent_normalize_digits((string) $value);
    if ($hours <= 0) {
        $hours = HTML_UPLOADER_DEFAULT_TTL_HOURS;
    }
    $hours = max(1, min(HTML_UPLOADER_MAX_TTL_HOURS, $hours));
    return date('c', time() + ($hours * 3600));
}

function html_uploader_is_expired(array $page): bool
{
    $expiresAt = trim((string) ($page['expiresAt'] ?? ''));
    if ($expiresAt === '') {
        return false;
    }
    $timestamp = strtotime($expiresAt);
    return $timestamp !== false && $timestamp <= time();
}

function html_uploader_public_state(array $page): string
{
    $status = html_uploader_clean_status((string) ($page['status'] ?? 'active'));
    if ($status === 'deleted') {
        return 'deleted';
    }
    if (html_uploader_is_expired($page)) {
        return 'expired';
    }
    if ($status === 'hidden') {
        return 'hidden';
    }
    return 'active';
}

function html_uploader_absolute_url(string $path): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $path;
    }
    $proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $secure = $proto === 'https' || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    return ($secure ? 'https://' : 'http://') . $host . $path;
}

function html_uploader_public_url(string $token): string
{
    return HTML_UPLOADER_PUBLIC_PATH . '?token=' . rawurlencode($token);
}

function html_uploader_page_path(string $storedName): string
{
    $storedName = basename(str_replace('\\', '/', $storedName));
    if ($storedName === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{8,220}$/', $storedName) !== 1) {
        return '';
    }
    $base = realpath(html_uploader_pages_dir());
    if ($base === false) {
        dent_ensure_directory(html_uploader_pages_dir());
        $base = realpath(html_uploader_pages_dir());
    }
    if ($base === false) {
        return '';
    }
    $path = $base . DIRECTORY_SEPARATOR . $storedName;
    $resolvedDir = realpath(dirname($path));
    if ($resolvedDir !== false && strpos($resolvedDir, $base) !== 0) {
        return '';
    }
    return $path;
}

function html_uploader_detect_mime(string $path, string $originalName): string
{
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($detected)) {
                $mime = strtolower(trim($detected));
            }
        }
    }
    if ($mime === '' || $mime === 'application/octet-stream') {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'html' || $ext === 'htm') {
            return 'text/html';
        }
    }
    return $mime;
}

function html_uploader_mime_allowed(string $mime, string $extension): bool
{
    $mime = strtolower(trim($mime));
    $extension = strtolower(trim($extension));
    if (!in_array($extension, ['html', 'htm'], true)) {
        return false;
    }
    return in_array($mime, ['text/html', 'application/xhtml+xml', 'text/plain', ''], true);
}

function html_uploader_client_key(): string
{
    $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    $ip = $forwarded !== '' ? trim(explode(',', $forwarded)[0]) : trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $agent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return hash('sha256', $ip . '|' . $agent);
}

function html_uploader_normalize_page_record(string $key, array $page): ?array
{
    $id = html_uploader_clean_id((string) ($page['id'] ?? $key));
    if ($id === '') {
        return null;
    }
    $token = html_uploader_clean_token((string) ($page['token'] ?? ''));
    if ($token === '') {
        $token = substr($id . '-' . html_uploader_random_token(8), 0, 72);
    }
    $storedName = basename(str_replace('\\', '/', (string) ($page['storedName'] ?? '')));
    if ($storedName === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{8,220}$/', $storedName) !== 1) {
        return null;
    }
    $originalName = dent_clean_text((string) ($page['originalName'] ?? $storedName), 240);
    if ($originalName === '') {
        $originalName = $storedName;
    }
    $createdAt = trim((string) ($page['createdAt'] ?? dent_iso_now()));
    $updatedAt = trim((string) ($page['updatedAt'] ?? $createdAt));
    return [
        'id' => $id,
        'token' => $token,
        'storedName' => $storedName,
        'originalName' => $originalName,
        'title' => dent_clean_text((string) ($page['title'] ?? ''), 180),
        'size' => max(0, (int) ($page['size'] ?? 0)),
        'mimeType' => dent_clean_text((string) ($page['mimeType'] ?? 'text/html'), 120) ?: 'text/html',
        'status' => html_uploader_clean_status((string) ($page['status'] ?? 'active')),
        'createdAt' => $createdAt,
        'updatedAt' => $updatedAt,
        'expiresAt' => trim((string) ($page['expiresAt'] ?? '')),
        'viewCount' => max(0, (int) ($page['viewCount'] ?? 0)),
        'lastViewedAt' => trim((string) ($page['lastViewedAt'] ?? '')),
        'uploaderKey' => preg_replace('/[^a-f0-9]+/', '', strtolower((string) ($page['uploaderKey'] ?? ''))) ?? '',
        'deletedAt' => trim((string) ($page['deletedAt'] ?? '')),
        'purgedAt' => trim((string) ($page['purgedAt'] ?? '')),
    ];
}

function html_uploader_normalize_store(array $store): array
{
    $pages = [];
    foreach (($store['pages'] ?? []) as $key => $page) {
        if (!is_array($page)) {
            continue;
        }
        $record = html_uploader_normalize_page_record((string) $key, $page);
        if ($record === null) {
            continue;
        }
        $pages[(string) $record['id']] = $record;
    }
    uasort($pages, static fn(array $left, array $right): int => strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? '')));
    return [
        'schemaVersion' => HTML_UPLOADER_SCHEMA_VERSION,
        'pages' => $pages,
    ];
}

function html_uploader_recent_upload_count(array $store, string $uploaderKey, int $windowSeconds = 86400): int
{
    if ($uploaderKey === '') {
        return 0;
    }
    $threshold = time() - $windowSeconds;
    $count = 0;
    foreach (($store['pages'] ?? []) as $page) {
        if (!is_array($page) || (string) ($page['uploaderKey'] ?? '') !== $uploaderKey) {
            continue;
        }
        $createdAt = strtotime((string) ($page['createdAt'] ?? ''));
        if ($createdAt === false || $createdAt < $threshold) {
            continue;
        }
        if ((string) ($page['status'] ?? '') === 'deleted' && trim((string) ($page['purgedAt'] ?? '')) !== '') {
            continue;
        }
        $count++;
    }
    return $count;
}

function html_uploader_store_uploaded_page(array $file, array $meta = []): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            dent_error('حجم فایل HTML از سقف فعلی هاست بیشتر است.', 413);
        }
        dent_error('آپلود فایل HTML کامل نشد.', 422);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = max(0, (int) ($file['size'] ?? 0));
    if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0) {
        dent_error('فایل HTML انتخاب‌شده معتبر نیست.', 422);
    }
    if ($size > HTML_UPLOADER_MAX_UPLOAD_BYTES) {
        dent_error('حجم هر فایل HTML باید حداکثر ۱ مگابایت باشد.', 422);
    }

    $originalName = dent_clean_text((string) ($file['name'] ?? 'page.html'), 240);
    if ($originalName === '') {
        $originalName = 'page.html';
    }
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mime = html_uploader_detect_mime($tmpName, $originalName);
    if (!html_uploader_mime_allowed($mime, $extension)) {
        dent_error('فقط فایل‌های HTML با پسوند html یا htm مجاز هستند.', 422);
    }

    $raw = file_get_contents($tmpName);
    if (!is_string($raw) || trim($raw) === '') {
        dent_error('محتوای فایل HTML خالی یا نامعتبر است.', 422);
    }
    $body = dent_force_utf8($raw);
    if (trim($body) === '') {
        dent_error('محتوای فایل HTML بعد از خواندن معتبر نبود.', 422);
    }

    $id = html_uploader_next_id();
    $token = html_uploader_random_token(10);
    $storedName = $id . '-' . html_uploader_random_token(6) . '.html';
    $target = html_uploader_page_path($storedName);
    if ($target === '') {
        dent_error('مسیر ذخیره‌سازی فایل HTML امن نیست.', 500);
    }
    if (file_put_contents($target, $body, LOCK_EX) === false) {
        dent_error('ذخیره فایل HTML در storage پایدار انجام نشد.', 500);
    }
    @chmod($target, 0644);

    $now = dent_iso_now();
    return [
        'id' => $id,
        'token' => $token,
        'storedName' => $storedName,
        'originalName' => $originalName,
        'title' => dent_clean_text((string) ($meta['title'] ?? ''), 180),
        'size' => strlen($body),
        'mimeType' => 'text/html',
        'status' => html_uploader_clean_status((string) ($meta['status'] ?? 'active')),
        'createdAt' => $now,
        'updatedAt' => $now,
        'expiresAt' => html_uploader_expiry_from_hours($meta['ttlHours'] ?? HTML_UPLOADER_DEFAULT_TTL_HOURS),
        'viewCount' => 0,
        'lastViewedAt' => '',
        'uploaderKey' => html_uploader_client_key(),
        'deletedAt' => '',
        'purgedAt' => '',
    ];
}

function html_uploader_page_public_payload(array $page, bool $owner = false): array
{
    $token = (string) ($page['token'] ?? '');
    $payload = [
        'id' => (string) ($page['id'] ?? ''),
        'token' => $token,
        'originalName' => (string) ($page['originalName'] ?? ''),
        'title' => (string) ($page['title'] ?? ''),
        'size' => max(0, (int) ($page['size'] ?? 0)),
        'mimeType' => (string) ($page['mimeType'] ?? 'text/html'),
        'status' => html_uploader_clean_status((string) ($page['status'] ?? 'active')),
        'publicState' => html_uploader_public_state($page),
        'createdAt' => (string) ($page['createdAt'] ?? ''),
        'updatedAt' => (string) ($page['updatedAt'] ?? ''),
        'expiresAt' => (string) ($page['expiresAt'] ?? ''),
        'viewCount' => max(0, (int) ($page['viewCount'] ?? 0)),
        'lastViewedAt' => (string) ($page['lastViewedAt'] ?? ''),
        'publicUrl' => html_uploader_absolute_url(html_uploader_public_url($token)),
    ];
    if ($owner) {
        $payload['storedName'] = (string) ($page['storedName'] ?? '');
        $payload['deletedAt'] = (string) ($page['deletedAt'] ?? '');
        $payload['purgedAt'] = (string) ($page['purgedAt'] ?? '');
        $payload['uploaderKey'] = (string) ($page['uploaderKey'] ?? '');
    }
    return $payload;
}

function html_uploader_find_page_by_token(array $store, string $token): ?array
{
    foreach (($store['pages'] ?? []) as $page) {
        if (!is_array($page) || (string) ($page['token'] ?? '') !== $token) {
            continue;
        }
        return $page;
    }
    return null;
}

function html_uploader_content_security_policy(): string
{
    return implode('; ', [
        "default-src * data: blob:",
        "img-src * data: blob:",
        "media-src * data: blob:",
        "font-src * data: blob:",
        "connect-src * data: blob:",
        "style-src * 'unsafe-inline' data: blob:",
        "script-src * 'unsafe-inline' 'unsafe-eval' blob:",
        "frame-src * data: blob:",
        "child-src * data: blob:",
        "worker-src * blob: data:",
        "object-src 'none'",
        "base-uri 'none'",
        "form-action *",
        "frame-ancestors 'none'",
        "sandbox allow-scripts allow-forms allow-modals allow-popups allow-downloads allow-top-navigation-by-user-activation",
    ]);
}

function html_uploader_emit_page_bytes(array $page): void
{
    $path = html_uploader_page_path((string) ($page['storedName'] ?? ''));
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        dent_error('فایل HTML در storage پیدا نشد.', 404);
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Security-Policy: ' . html_uploader_content_security_policy());
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: private, max-age=0, no-store');
    readfile($path);
    exit;
}

function html_uploader_owner_summary(array $store): array
{
    $pages = array_values($store['pages'] ?? []);
    $active = 0;
    $hidden = 0;
    $expired = 0;
    $deleted = 0;
    $views = 0;
    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        $state = html_uploader_public_state($page);
        if ($state === 'active') {
            $active++;
        } elseif ($state === 'hidden') {
            $hidden++;
        } elseif ($state === 'expired') {
            $expired++;
        } else {
            $deleted++;
        }
        $views += max(0, (int) ($page['viewCount'] ?? 0));
    }
    return [
        'totalPages' => count($pages),
        'activePages' => $active,
        'hiddenPages' => $hidden,
        'expiredPages' => $expired,
        'deletedPages' => $deleted,
        'viewCount' => $views,
    ];
}
