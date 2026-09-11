<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/content_tools_download_host.php';

const CONTENT_TOOLS_SCHEMA_VERSION = 2;
const CONTENT_FILE_ID_PREFIX = 'uf-';
const CONTENT_PASTE_ID_PREFIX = 'ps-';
const CONTENT_FILE_PUBLIC_PATH = '/files/f/';
const CONTENT_PASTE_PUBLIC_PATH = '/paste/p/';
const CONTENT_MAX_UPLOAD_BYTES = 20 * 1024 * 1024 * 1024;
const CONTENT_MAX_UPLOAD_LABEL = '۲۰ گیگابایت';
const CONTENT_MAX_PASTE_CHARS = 500000;
const CONTENT_FILE_STORAGE_LOCAL = 'local';
const CONTENT_FILE_STORAGE_DOWNLOAD_HOST = 'download-host';

function content_store_path(): string
{
    return dent_storage_path('content_tools/store.json');
}

function content_store_lock_path(): string
{
    return dent_storage_path('content_tools/store.lock');
}

function content_uploads_dir(): string
{
    return dent_storage_path('content_tools/uploads');
}

function content_log_path(): string
{
    return dent_storage_path('content_tools/audit.log');
}

function content_default_store(): array
{
    return [
        'schemaVersion' => CONTENT_TOOLS_SCHEMA_VERSION,
        'files' => [],
        'pastes' => [],
    ];
}

function content_ensure_storage(): void
{
    dent_ensure_directory(dirname(content_store_path()));
    dent_ensure_directory(content_uploads_dir());
    if (!is_file(content_store_path())) {
        dent_write_json_file(content_store_path(), content_default_store());
    }
}

function content_load_store_unlocked(): array
{
    content_ensure_storage();
    $raw = dent_read_json_file(content_store_path(), content_default_store());
    if (!isset($raw['files'], $raw['pastes']) || !is_array($raw['files']) || !is_array($raw['pastes'])) {
        throw new DentJsonPersistenceException(
            'CONTENT_STORE_SCHEMA_INVALID',
            'Existing content-tools store has an invalid schema'
        );
    }
    return content_normalize_store($raw);
}

function content_read_store(): array
{
    content_ensure_storage();
    $lock = fopen(content_store_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی ابزار محتوا.', 500);
    }

    try {
        if (!flock($lock, LOCK_SH)) {
            dent_error('قفل خواندن ابزار محتوا آماده نشد.', 500);
        }
        return content_load_store_unlocked();
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
function content_with_store_lock(callable $callback)
{
    content_ensure_storage();
    $lock = fopen(content_store_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی ابزار محتوا.', 500);
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            dent_error('قفل ذخیره‌سازی ابزار محتوا آماده نشد.', 500);
        }
        $store = content_load_store_unlocked();
        $result = $callback($store);
        dent_write_json_file(content_store_path(), content_normalize_store($store));
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function content_normalize_store(array $store): array
{
    $files = [];
    foreach (($store['files'] ?? []) as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        $record = content_normalize_file_record((string) $key, $value);
        if ($record === null) {
            continue;
        }
        $files[(string) $record['id']] = $record;
    }
    uasort($files, static fn(array $left, array $right): int => strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? '')));

    $pastes = [];
    foreach (($store['pastes'] ?? []) as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        $record = content_normalize_paste_record((string) $key, $value);
        if ($record === null) {
            continue;
        }
        $pastes[(string) $record['id']] = $record;
    }
    uasort($pastes, static fn(array $left, array $right): int => strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? '')));

    return [
        'schemaVersion' => CONTENT_TOOLS_SCHEMA_VERSION,
        'files' => $files,
        'pastes' => $pastes,
    ];
}

function content_clean_id(string $value, string $prefix): string
{
    $value = trim(strtolower($value));
    if ($value === '' || !str_starts_with($value, $prefix)) {
        return '';
    }
    return preg_match('/^[a-z0-9_-]{5,80}$/', $value) === 1 ? $value : '';
}

function content_next_id(string $prefix): string
{
    try {
        return $prefix . bin2hex(random_bytes(6));
    } catch (Throwable $error) {
        return $prefix . strtolower(str_replace('.', '', uniqid('', true)));
    }
}

function content_random_token(int $bytes = 10): string
{
    try {
        return strtolower(dent_base64url_encode(random_bytes($bytes)));
    } catch (Throwable $error) {
        return substr(hash('sha256', microtime(true) . '|' . mt_rand()), 0, $bytes * 2);
    }
}

function content_clean_slug_token(string $value): string
{
    $value = trim(strtolower($value));
    return preg_match('/^[a-z0-9_-]{8,80}$/', $value) === 1 ? $value : '';
}

function content_clean_status(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['active', 'hidden', 'deleted'], true) ? $value : 'active';
}

function content_clean_language(string $value): string
{
    $value = trim(strtolower($value));
    $value = preg_replace('/[^a-z0-9_+#.-]+/', '', $value) ?? '';
    if ($value === '') {
        return 'plain';
    }
    return substr($value, 0, 40);
}

function content_clean_tag_list($raw): array
{
    $items = is_array($raw) ? $raw : preg_split('/[\s,،;]+/u', (string) $raw);
    $tags = [];
    foreach ($items ?: [] as $item) {
        $tag = dent_clean_text((string) $item, 40);
        if ($tag === '') {
            continue;
        }
        $key = dent_utf8_strtolower($tag);
        $tags[$key] = $tag;
        if (count($tags) >= 12) {
            break;
        }
    }
    return array_values($tags);
}

function content_parse_expires_at($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false || $timestamp <= time()) {
        return '';
    }
    return date('c', $timestamp);
}

function content_is_expired(array $record): bool
{
    $expiresAt = trim((string) ($record['expiresAt'] ?? ''));
    if ($expiresAt === '') {
        return false;
    }
    $timestamp = strtotime($expiresAt);
    return $timestamp !== false && $timestamp <= time();
}

function content_public_state(array $record): string
{
    $status = content_clean_status((string) ($record['status'] ?? 'active'));
    if ($status === 'deleted') {
        return 'deleted';
    }
    if (content_is_expired($record)) {
        return 'expired';
    }
    if ($status === 'hidden') {
        return 'hidden';
    }
    return 'active';
}

function content_normalize_download_limit($value): int
{
    $number = (int) dent_normalize_digits((string) $value);
    return max(0, min(1000000, $number));
}

function content_hash_password(string $password): string
{
    $password = trim($password);
    if ($password === '') {
        return '';
    }
    return password_hash($password, PASSWORD_DEFAULT) ?: '';
}

function content_verify_record_password(array $record, string $password): bool
{
    $hash = (string) ($record['passwordHash'] ?? '');
    if ($hash === '') {
        return true;
    }
    return password_verify($password, $hash);
}

function content_file_public_url(string $token): string
{
    return CONTENT_FILE_PUBLIC_PATH . '?token=' . rawurlencode($token);
}

function content_file_download_url(string $token): string
{
    return '/api/content_tools_api.php?action=downloadFile&token=' . rawurlencode($token);
}

function content_paste_public_url(string $token): string
{
    return CONTENT_PASTE_PUBLIC_PATH . '?token=' . rawurlencode($token);
}

function content_paste_raw_url(string $token): string
{
    return '/api/content_tools_api.php?action=rawPaste&token=' . rawurlencode($token);
}

function content_absolute_url(string $path): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $path;
    }
    $proto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $secure = $proto === 'https' || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    return ($secure ? 'https://' : 'http://') . $host . $path;
}

function content_clean_storage_driver($value): string
{
    $value = trim(strtolower((string) $value));
    if ($value === CONTENT_FILE_STORAGE_DOWNLOAD_HOST) {
        return CONTENT_FILE_STORAGE_DOWNLOAD_HOST;
    }
    return CONTENT_FILE_STORAGE_LOCAL;
}

function content_clean_remote_relative_path($value): string
{
    $value = trim(str_replace('\\', '/', (string) $value));
    $value = preg_replace('#/+#', '/', $value) ?? '';
    $value = trim($value, '/');
    if ($value === '') {
        return '';
    }

    $segments = [];
    foreach (explode('/', $value) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.' || $segment === '..') {
            continue;
        }
        if (preg_match('/[\x00-\x1f]/u', $segment) === 1) {
            continue;
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
}

function content_file_storage_driver(array $file): string
{
    $driver = content_clean_storage_driver($file['storageDriver'] ?? '');
    if ($driver === CONTENT_FILE_STORAGE_DOWNLOAD_HOST) {
        return $driver;
    }

    $remotePath = content_clean_remote_relative_path($file['remoteRelativePath'] ?? '');
    $remoteUrl = trim((string) ($file['remotePublicUrl'] ?? ''));
    if ($remotePath !== '' || $remoteUrl !== '') {
        return CONTENT_FILE_STORAGE_DOWNLOAD_HOST;
    }

    return CONTENT_FILE_STORAGE_LOCAL;
}

function content_file_is_remote(array $file): bool
{
    return content_file_storage_driver($file) === CONTENT_FILE_STORAGE_DOWNLOAD_HOST;
}

function content_normalize_file_record(string $key, array $record): ?array
{
    $id = content_clean_id((string) ($record['id'] ?? $key), CONTENT_FILE_ID_PREFIX);
    if ($id === '') {
        return null;
    }
    $token = content_clean_slug_token((string) ($record['token'] ?? ''));
    if ($token === '') {
        $token = substr($id . '-' . content_random_token(8), 0, 72);
    }
    $storageDriver = content_file_storage_driver($record);
    $storedName = '';
    if ($storageDriver === CONTENT_FILE_STORAGE_LOCAL) {
        $storedName = basename(str_replace('\\', '/', (string) ($record['storedName'] ?? '')));
        if ($storedName === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{8,220}$/', $storedName) !== 1) {
            return null;
        }
    }
    $remoteRelativePath = '';
    $remotePublicUrl = '';
    if ($storageDriver === CONTENT_FILE_STORAGE_DOWNLOAD_HOST) {
        $remoteRelativePath = content_clean_remote_relative_path($record['remoteRelativePath'] ?? '');
        $remotePublicUrl = trim((string) ($record['remotePublicUrl'] ?? ''));
        if ($remoteRelativePath === '' && $remotePublicUrl === '') {
            return null;
        }
        if ($remotePublicUrl === '' && $remoteRelativePath !== '' && content_download_host_is_enabled()) {
            $remotePublicUrl = content_download_host_public_url($remoteRelativePath);
        }
    }
    $originalName = dent_clean_text((string) ($record['originalName'] ?? $storedName), 240);
    if ($originalName === '') {
        $originalName = $storageDriver === CONTENT_FILE_STORAGE_DOWNLOAD_HOST
            ? basename($remoteRelativePath !== '' ? $remoteRelativePath : 'file')
            : $storedName;
    }
    $createdAt = trim((string) ($record['createdAt'] ?? dent_iso_now()));
    $updatedAt = trim((string) ($record['updatedAt'] ?? $createdAt));
    return [
        'id' => $id,
        'token' => $token,
        'originalName' => $originalName,
        'storedName' => $storedName,
        'storageDriver' => $storageDriver,
        'remoteRelativePath' => $remoteRelativePath,
        'remotePublicUrl' => $remotePublicUrl,
        'size' => max(0, (int) ($record['size'] ?? 0)),
        'mimeType' => dent_clean_text((string) ($record['mimeType'] ?? 'application/octet-stream'), 120),
        'extension' => strtolower(preg_replace('/[^a-z0-9]+/', '', (string) ($record['extension'] ?? pathinfo($originalName, PATHINFO_EXTENSION))) ?? ''),
        'title' => dent_clean_text((string) ($record['title'] ?? ''), 180),
        'description' => dent_clean_text((string) ($record['description'] ?? ''), 1200),
        'tags' => content_clean_tag_list($record['tags'] ?? []),
        'folder' => dent_clean_text((string) ($record['folder'] ?? ''), 80),
        'status' => content_clean_status((string) ($record['status'] ?? 'active')),
        'createdAt' => $createdAt,
        'updatedAt' => $updatedAt,
        'uploadedBy' => dent_normalize_student_number((string) ($record['uploadedBy'] ?? '')),
        'downloadCount' => max(0, (int) ($record['downloadCount'] ?? 0)),
        'lastDownloadedAt' => trim((string) ($record['lastDownloadedAt'] ?? '')),
        'expiresAt' => content_parse_expires_at($record['expiresAt'] ?? ''),
        'passwordHash' => (string) ($record['passwordHash'] ?? ''),
        'downloadLimit' => content_normalize_download_limit($record['downloadLimit'] ?? 0),
        'deletedAt' => trim((string) ($record['deletedAt'] ?? '')),
        'purgedAt' => trim((string) ($record['purgedAt'] ?? '')),
    ];
}

function content_normalize_paste_record(string $key, array $record): ?array
{
    $id = content_clean_id((string) ($record['id'] ?? $key), CONTENT_PASTE_ID_PREFIX);
    if ($id === '') {
        return null;
    }
    $token = content_clean_slug_token((string) ($record['token'] ?? ''));
    if ($token === '') {
        $token = substr($id . '-' . content_random_token(8), 0, 72);
    }
    $body = dent_force_utf8((string) ($record['body'] ?? ''));
    if (dent_utf8_strlen($body) > CONTENT_MAX_PASTE_CHARS) {
        $body = dent_utf8_substr($body, 0, CONTENT_MAX_PASTE_CHARS);
    }
    $createdAt = trim((string) ($record['createdAt'] ?? dent_iso_now()));
    $updatedAt = trim((string) ($record['updatedAt'] ?? $createdAt));
    return [
        'id' => $id,
        'token' => $token,
        'title' => dent_clean_text((string) ($record['title'] ?? 'Paste'), 180) ?: 'Paste',
        'body' => $body,
        'language' => content_clean_language((string) ($record['language'] ?? 'plain')),
        'status' => content_clean_status((string) ($record['status'] ?? 'active')),
        'createdAt' => $createdAt,
        'updatedAt' => $updatedAt,
        'createdBy' => dent_normalize_student_number((string) ($record['createdBy'] ?? '')),
        'viewCount' => max(0, (int) ($record['viewCount'] ?? 0)),
        'rawViewCount' => max(0, (int) ($record['rawViewCount'] ?? 0)),
        'lastViewedAt' => trim((string) ($record['lastViewedAt'] ?? '')),
        'expiresAt' => content_parse_expires_at($record['expiresAt'] ?? ''),
        'passwordHash' => (string) ($record['passwordHash'] ?? ''),
        'deletedAt' => trim((string) ($record['deletedAt'] ?? '')),
    ];
}

function content_upload_file_path(string $storedName): string
{
    $storedName = basename(str_replace('\\', '/', $storedName));
    if ($storedName === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{8,220}$/', $storedName) !== 1) {
        return '';
    }
    $base = realpath(content_uploads_dir());
    if ($base === false) {
        dent_ensure_directory(content_uploads_dir());
        $base = realpath(content_uploads_dir());
    }
    if ($base === false) {
        return '';
    }
    $path = $base . DIRECTORY_SEPARATOR . $storedName;
    $real = realpath($path);
    if ($real !== false && !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
        return '';
    }
    return $path;
}

function content_detect_mime(string $path, string $originalName): string
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
        $map = [
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
        ];
        $mime = $map[$ext] ?? 'application/octet-stream';
    }
    return $mime;
}

function content_extension_allowed(string $extension): bool
{
    $extension = strtolower(trim($extension));
    if ($extension === '') {
        return true;
    }
    $blocked = [
        'php', 'phtml', 'phar', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp',
        'sh', 'bash', 'bat', 'cmd', 'com', 'exe', 'dll', 'msi', 'jar', 'htaccess',
    ];
    return !in_array($extension, $blocked, true);
}

function content_mime_allowed(string $mime): bool
{
    $mime = strtolower(trim($mime));
    $blocked = [
        'application/x-php',
        'application/x-httpd-php',
        'application/x-msdownload',
        'application/x-msdos-program',
    ];
    return !in_array($mime, $blocked, true);
}

function content_store_uploaded_file(array $file, array $owner, array $meta = []): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            dent_error('حجم فایل از سقف فعلی PHP/هاست بیشتر است. سقف ابزار ' . CONTENT_MAX_UPLOAD_LABEL . ' است، اما تنظیمات هاست هم باید این مقدار را بپذیرد.', 413);
        }
        if ($error === UPLOAD_ERR_PARTIAL) {
            dent_error('آپلود فایل کامل نشد. اتصال یا محدودیت هاست را بررسی کنید.', 422);
        }
        dent_error('آپلود فایل انجام نشد.', 422);
    }
    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = max(0, (int) ($file['size'] ?? 0));
    if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0) {
        dent_error('فایل انتخاب‌شده معتبر نیست.', 422);
    }
    if ($size > CONTENT_MAX_UPLOAD_BYTES) {
        dent_error('حجم هر فایل باید حداکثر ' . CONTENT_MAX_UPLOAD_LABEL . ' باشد.', 422);
    }

    $originalName = dent_clean_text((string) ($file['name'] ?? 'file'), 240);
    if ($originalName === '') {
        $originalName = 'file';
    }
    $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($originalName, PATHINFO_EXTENSION)) ?? '');
    if (!content_extension_allowed($extension)) {
        dent_error('این پسوند برای آپلود عمومی مجاز نیست.', 422);
    }
    $mime = content_detect_mime($tmpName, $originalName);
    if (!content_mime_allowed($mime)) {
        dent_error('نوع فایل برای آپلود عمومی مجاز نیست.', 422);
    }

    $id = content_next_id(CONTENT_FILE_ID_PREFIX);
    $token = content_random_token(10);
    $storedName = $id . '-' . content_random_token(6) . ($extension !== '' ? ('.' . $extension) : '.bin');
    $target = content_upload_file_path($storedName);
    if ($target === '') {
        dent_error('مسیر ذخیره‌سازی فایل امن نیست.', 500);
    }
    if (!move_uploaded_file($tmpName, $target)) {
        dent_error('ذخیره فایل در storage پایدار انجام نشد.', 500);
    }
    @chmod($target, 0644);

    $now = dent_iso_now();
    return [
        'id' => $id,
        'token' => $token,
        'originalName' => $originalName,
        'storedName' => $storedName,
        'size' => $size,
        'mimeType' => $mime,
        'extension' => $extension,
        'title' => dent_clean_text((string) ($meta['title'] ?? ''), 180),
        'description' => dent_clean_text((string) ($meta['description'] ?? ''), 1200),
        'tags' => content_clean_tag_list($meta['tags'] ?? ''),
        'folder' => dent_clean_text((string) ($meta['folder'] ?? ''), 80),
        'status' => content_clean_status((string) ($meta['status'] ?? 'active')),
        'createdAt' => $now,
        'updatedAt' => $now,
        'uploadedBy' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
        'downloadCount' => 0,
        'lastDownloadedAt' => '',
        'expiresAt' => content_parse_expires_at($meta['expiresAt'] ?? ''),
        'passwordHash' => content_hash_password((string) ($meta['password'] ?? '')),
        'downloadLimit' => content_normalize_download_limit($meta['downloadLimit'] ?? 0),
        'deletedAt' => '',
        'purgedAt' => '',
    ];
}

function content_build_download_host_file_record(array $upload, array $owner, array $meta = []): array
{
    $relativePath = content_clean_remote_relative_path($upload['relativePath'] ?? '');
    if ($relativePath === '') {
        dent_error('مسیر فایل روی هاست دانلود معتبر نیست.', 422);
    }

    $remotePublicUrl = trim((string) ($upload['publicUrl'] ?? ''));
    if ($remotePublicUrl === '') {
        $remotePublicUrl = content_download_host_public_url($relativePath);
    }

    $originalName = dent_clean_text((string) ($upload['originalName'] ?? $upload['name'] ?? basename($relativePath)), 240);
    if ($originalName === '') {
        $originalName = basename($relativePath);
    }
    $mimeType = dent_clean_text((string) ($upload['mimeType'] ?? 'application/octet-stream'), 120);
    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }
    $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', (string) ($upload['extension'] ?? pathinfo($originalName, PATHINFO_EXTENSION))) ?? '');
    $size = max(0, (int) ($upload['sizeBytes'] ?? $upload['size'] ?? 0));

    $now = dent_iso_now();
    return [
        'id' => content_next_id(CONTENT_FILE_ID_PREFIX),
        'token' => content_random_token(10),
        'originalName' => $originalName,
        'storedName' => '',
        'storageDriver' => CONTENT_FILE_STORAGE_DOWNLOAD_HOST,
        'remoteRelativePath' => $relativePath,
        'remotePublicUrl' => $remotePublicUrl,
        'size' => $size,
        'mimeType' => $mimeType,
        'extension' => $extension,
        'title' => dent_clean_text((string) ($meta['title'] ?? ''), 180),
        'description' => dent_clean_text((string) ($meta['description'] ?? ''), 1200),
        'tags' => content_clean_tag_list($meta['tags'] ?? ''),
        'folder' => dent_clean_text((string) ($meta['folder'] ?? ''), 80),
        'status' => content_clean_status((string) ($meta['status'] ?? 'active')),
        'createdAt' => $now,
        'updatedAt' => $now,
        'uploadedBy' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
        'downloadCount' => 0,
        'lastDownloadedAt' => '',
        'expiresAt' => content_parse_expires_at($meta['expiresAt'] ?? ''),
        'passwordHash' => content_hash_password((string) ($meta['password'] ?? '')),
        'downloadLimit' => content_normalize_download_limit($meta['downloadLimit'] ?? 0),
        'deletedAt' => '',
        'purgedAt' => '',
    ];
}

function content_file_public_payload(array $file, bool $owner = false): array
{
    $token = (string) ($file['token'] ?? '');
    $storageDriver = content_file_storage_driver($file);
    $remoteRelativePath = content_clean_remote_relative_path($file['remoteRelativePath'] ?? '');
    $remotePublicUrl = trim((string) ($file['remotePublicUrl'] ?? ''));
    if ($remotePublicUrl === '' && $storageDriver === CONTENT_FILE_STORAGE_DOWNLOAD_HOST && $remoteRelativePath !== '' && content_download_host_is_enabled()) {
        $remotePublicUrl = content_download_host_public_url($remoteRelativePath);
    }
    $payload = [
        'id' => (string) ($file['id'] ?? ''),
        'token' => $token,
        'originalName' => (string) ($file['originalName'] ?? ''),
        'size' => max(0, (int) ($file['size'] ?? 0)),
        'mimeType' => (string) ($file['mimeType'] ?? 'application/octet-stream'),
        'extension' => (string) ($file['extension'] ?? ''),
        'title' => (string) ($file['title'] ?? ''),
        'description' => (string) ($file['description'] ?? ''),
        'tags' => is_array($file['tags'] ?? null) ? array_values($file['tags']) : [],
        'folder' => (string) ($file['folder'] ?? ''),
        'status' => content_clean_status((string) ($file['status'] ?? 'active')),
        'publicState' => content_public_state($file),
        'createdAt' => (string) ($file['createdAt'] ?? ''),
        'updatedAt' => (string) ($file['updatedAt'] ?? ''),
        'expiresAt' => (string) ($file['expiresAt'] ?? ''),
        'downloadCount' => max(0, (int) ($file['downloadCount'] ?? 0)),
        'downloadLimit' => max(0, (int) ($file['downloadLimit'] ?? 0)),
        'hasPassword' => (string) ($file['passwordHash'] ?? '') !== '',
        'storageDriver' => $storageDriver,
        'publicUrl' => content_absolute_url(content_file_public_url($token)),
        'downloadUrl' => content_absolute_url(content_file_download_url($token)),
        'directUrl' => $storageDriver === CONTENT_FILE_STORAGE_DOWNLOAD_HOST ? $remotePublicUrl : '',
    ];
    if ($owner) {
        $payload['storedName'] = (string) ($file['storedName'] ?? '');
        $payload['remoteRelativePath'] = $remoteRelativePath;
        $payload['remotePublicUrl'] = $remotePublicUrl;
        $payload['lastDownloadedAt'] = (string) ($file['lastDownloadedAt'] ?? '');
        $payload['deletedAt'] = (string) ($file['deletedAt'] ?? '');
        $payload['purgedAt'] = (string) ($file['purgedAt'] ?? '');
    }
    return $payload;
}

function content_paste_public_payload(array $paste, bool $includeBody = true, bool $owner = false): array
{
    $token = (string) ($paste['token'] ?? '');
    $payload = [
        'id' => (string) ($paste['id'] ?? ''),
        'token' => $token,
        'title' => (string) ($paste['title'] ?? ''),
        'language' => (string) ($paste['language'] ?? 'plain'),
        'status' => content_clean_status((string) ($paste['status'] ?? 'active')),
        'publicState' => content_public_state($paste),
        'createdAt' => (string) ($paste['createdAt'] ?? ''),
        'updatedAt' => (string) ($paste['updatedAt'] ?? ''),
        'expiresAt' => (string) ($paste['expiresAt'] ?? ''),
        'viewCount' => max(0, (int) ($paste['viewCount'] ?? 0)),
        'rawViewCount' => max(0, (int) ($paste['rawViewCount'] ?? 0)),
        'hasPassword' => (string) ($paste['passwordHash'] ?? '') !== '',
        'publicUrl' => content_absolute_url(content_paste_public_url($token)),
        'rawUrl' => content_absolute_url(content_paste_raw_url($token)),
        'bodyLength' => dent_utf8_strlen((string) ($paste['body'] ?? '')),
    ];
    if ($includeBody) {
        $payload['body'] = (string) ($paste['body'] ?? '');
    }
    if ($owner) {
        $payload['lastViewedAt'] = (string) ($paste['lastViewedAt'] ?? '');
        $payload['deletedAt'] = (string) ($paste['deletedAt'] ?? '');
    }
    return $payload;
}

function content_find_file_by_token(array $store, string $token): ?array
{
    $token = content_clean_slug_token($token);
    if ($token === '') {
        return null;
    }
    foreach (($store['files'] ?? []) as $file) {
        if (is_array($file) && (string) ($file['token'] ?? '') === $token) {
            return $file;
        }
    }
    return null;
}

function content_find_paste_by_token(array $store, string $token): ?array
{
    $token = content_clean_slug_token($token);
    if ($token === '') {
        return null;
    }
    foreach (($store['pastes'] ?? []) as $paste) {
        if (is_array($paste) && (string) ($paste['token'] ?? '') === $token) {
            return $paste;
        }
    }
    return null;
}

function content_file_remote_path(array $file): string
{
    return content_clean_remote_relative_path($file['remoteRelativePath'] ?? '');
}

function content_file_has_remote_path(array $file, string $relativePath): bool
{
    if (!content_file_is_remote($file)) {
        return false;
    }
    return content_file_remote_path($file) === content_clean_remote_relative_path($relativePath);
}

function content_file_is_in_remote_directory(array $file, string $directoryPath): bool
{
    if (!content_file_is_remote($file)) {
        return false;
    }
    $directoryPath = content_clean_remote_relative_path($directoryPath);
    if ($directoryPath === '') {
        return false;
    }
    $remotePath = content_file_remote_path($file);
    return $remotePath !== '' && str_starts_with($remotePath, $directoryPath . '/');
}

function content_file_mark_deleted(array $file, bool $purged = false): array
{
    $file['status'] = 'deleted';
    $file['deletedAt'] = trim((string) ($file['deletedAt'] ?? '')) !== '' ? (string) $file['deletedAt'] : dent_iso_now();
    if ($purged) {
        $file['purgedAt'] = dent_iso_now();
    }
    $file['updatedAt'] = dent_iso_now();
    return $file;
}

function content_file_with_remote_path(array $file, string $relativePath): array
{
    $relativePath = content_clean_remote_relative_path($relativePath);
    $file['storageDriver'] = CONTENT_FILE_STORAGE_DOWNLOAD_HOST;
    $file['remoteRelativePath'] = $relativePath;
    $file['remotePublicUrl'] = $relativePath !== '' ? content_download_host_public_url($relativePath) : '';
    if ($relativePath !== '') {
        $file['originalName'] = dent_clean_text((string) ($file['originalName'] ?? basename($relativePath)), 240) ?: basename($relativePath);
    }
    $file['updatedAt'] = dent_iso_now();
    return $file;
}

function content_file_can_download(array $file): bool
{
    if (content_public_state($file) !== 'active') {
        return false;
    }
    $limit = max(0, (int) ($file['downloadLimit'] ?? 0));
    if ($limit > 0 && max(0, (int) ($file['downloadCount'] ?? 0)) >= $limit) {
        return false;
    }
    return true;
}

function content_is_previewable_file(array $file): bool
{
    $mime = strtolower((string) ($file['mimeType'] ?? ''));
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'audio/mpeg', 'audio/wav', 'audio/ogg', 'video/mp4', 'video/webm'], true)) {
        return true;
    }
    return in_array($mime, ['application/pdf', 'text/plain', 'text/markdown', 'text/csv', 'application/json'], true);
}

function content_storage_summary(array $store, array $options = []): array
{
    $includeHostUsage = !empty($options['includeHostUsage']);
    $forceHostUsageRefresh = !empty($options['forceHostUsageRefresh']);
    $totalFiles = 0;
    $activeFiles = 0;
    $totalBytes = 0;
    $downloads = 0;
    $remoteFiles = 0;
    $localFiles = 0;
    $largest = [];
    foreach (($store['files'] ?? []) as $file) {
        if (!is_array($file)) {
            continue;
        }
        $totalFiles++;
        if (content_file_is_remote($file)) {
            $remoteFiles++;
        } else {
            $localFiles++;
        }
        if (content_public_state($file) === 'active') {
            $activeFiles++;
        }
        $size = max(0, (int) ($file['size'] ?? 0));
        $totalBytes += $size;
        $downloads += max(0, (int) ($file['downloadCount'] ?? 0));
        $largest[] = $file;
    }
    usort($largest, static fn(array $left, array $right): int => (int) ($right['size'] ?? 0) <=> (int) ($left['size'] ?? 0));
    $pasteViews = 0;
    $pasteRawViews = 0;
    foreach (($store['pastes'] ?? []) as $paste) {
        if (!is_array($paste)) {
            continue;
        }
        $pasteViews += max(0, (int) ($paste['viewCount'] ?? 0));
        $pasteRawViews += max(0, (int) ($paste['rawViewCount'] ?? 0));
    }

    $downloadHostEnabled = content_download_host_is_enabled();
    $freeBytes = null;
    $totalDiskBytes = null;
    $hostUsage = [
        'enabled' => $downloadHostEnabled,
        'available' => false,
        'baseUrl' => $downloadHostEnabled ? content_download_host_public_base_url() : '',
        'rootPath' => '',
    ];
    if ($downloadHostEnabled && !$includeHostUsage && function_exists('content_download_host_read_stats_cache')) {
        $cachedHostUsage = content_download_host_read_stats_cache(true, PHP_INT_MAX);
        if (is_array($cachedHostUsage)) {
            $hostUsage = $cachedHostUsage;
            if (($hostUsage['available'] ?? false) === true) {
                $freeBytes = isset($hostUsage['remainingBytes']) ? max(0, (int) $hostUsage['remainingBytes']) : $freeBytes;
                $totalDiskBytes = isset($hostUsage['limitBytes']) ? max(0, (int) $hostUsage['limitBytes']) : $totalDiskBytes;
            }
        }
    }
    if ($localFiles > 0) {
        $root = content_uploads_dir();
        if (function_exists('disk_free_space')) {
            try {
                $free = @disk_free_space($root);
                if (is_float($free) || is_int($free)) {
                    $freeBytes = max(0, (int) $free);
                }
            } catch (Throwable $error) {
                $freeBytes = null;
            }
        }
        if (function_exists('disk_total_space')) {
            try {
                $total = @disk_total_space($root);
                if (is_float($total) || is_int($total)) {
                    $totalDiskBytes = max(0, (int) $total);
                }
            } catch (Throwable $error) {
                $totalDiskBytes = null;
            }
        }
    }

    if ($downloadHostEnabled && $includeHostUsage) {
        try {
            $hostUsage = content_download_host_usage_summary($forceHostUsageRefresh);
            if (($hostUsage['available'] ?? false) === true) {
                $freeBytes = isset($hostUsage['remainingBytes']) ? max(0, (int) $hostUsage['remainingBytes']) : $freeBytes;
                $totalDiskBytes = isset($hostUsage['limitBytes']) ? max(0, (int) $hostUsage['limitBytes']) : $totalDiskBytes;
            }
        } catch (Throwable $error) {
            $hostUsage = [
                'enabled' => true,
                'available' => false,
                'baseUrl' => content_download_host_public_base_url(),
                'rootPath' => '',
                'error' => 'host_usage_unavailable',
            ];
        }
    }

    $notice = $downloadHostEnabled
        ? 'فایل‌های جدید آپلودسنتر روی هاست دانلود سایت نگه‌داری می‌شوند و لینک مستقیم آن‌ها از همان هاست سرو می‌شود.'
        : 'هاست دانلود هنوز برای آپلودسنتر فعال نشده است و فقط فایل‌های قدیمی local قابل مشاهده هستند.';
    if ($localFiles > 0 && $downloadHostEnabled) {
        $notice .= ' بخشی از لینک‌های قدیمی همچنان روی storage محلی سایت باقی مانده‌اند.';
    }
    if (($hostUsage['available'] ?? false) === true) {
        $notice .= ' اسکن ریشه دانلودهاست ' . max(0, (int) ($hostUsage['fileCount'] ?? 0)) . ' فایل و '
            . max(0, (int) ($hostUsage['directoryCount'] ?? 0)) . ' پوشه را نشان می‌دهد.';
    }

    return [
        'totalFiles' => $totalFiles,
        'activeFiles' => $activeFiles,
        'totalBytes' => $totalBytes,
        'downloadCount' => $downloads,
        'remoteFiles' => $remoteFiles,
        'localFiles' => $localFiles,
        'downloadHostEnabled' => $downloadHostEnabled,
        'pasteCount' => count($store['pastes'] ?? []),
        'pasteViewCount' => $pasteViews,
        'pasteRawViewCount' => $pasteRawViews,
        'storageRoot' => $downloadHostEnabled ? content_download_host_public_base_url() : 'content_tools/uploads',
        'remainingBytes' => $freeBytes,
        'diskTotalBytes' => $totalDiskBytes,
        'remainingKnown' => $freeBytes !== null,
        'hostUsage' => $hostUsage,
        'notice' => $notice,
        'largestFiles' => array_map(static fn(array $file): array => content_file_public_payload($file, true), array_slice($largest, 0, 5)),
    ];
}

function content_audit_log(string $event, array $payload = []): void
{
    $entry = [
        'event' => dent_clean_text($event, 80),
        'at' => dent_iso_now(),
        'payload' => $payload,
    ];
    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        return;
    }
    dent_ensure_directory(dirname(content_log_path()));
    @file_put_contents(content_log_path(), $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}
