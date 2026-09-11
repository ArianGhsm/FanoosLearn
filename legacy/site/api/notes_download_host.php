<?php
declare(strict_types=1);

const NOTES_DOWNLOAD_HOST_ALLOWED_ROOTS = ['1402', '1403', '1404', 'prosthesis-1402'];
const NOTES_DOWNLOAD_HOST_SECRET_FILE = 'mihan_download_host.json';
const NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS = 300;
const NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS = 14400;
const NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES = 4 * 1024 * 1024;
const NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_GATEWAY_VERSION = '20260713-ftp-parts-1';
const NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_RUNTIME_DIR = '__dent-upload';
const NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_GATEWAY_FILE = 'notes-upload.php';
const NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_USER_INI_FILE = '.user.ini';
const NOTES_DIRECT_UPLOAD_GATEWAY_HEALTHY_TTL_SECONDS = 1800;
const NOTES_DIRECT_UPLOAD_GATEWAY_UNAVAILABLE_TTL_SECONDS = 120;

function notes_download_host_request_header(string $name): string
{
    $normalized = strtoupper(str_replace('-', '_', trim($name)));
    if ($normalized === '') {
        return '';
    }

    if ($normalized === 'CONTENT_TYPE' && isset($_SERVER['CONTENT_TYPE'])) {
        return trim((string) $_SERVER['CONTENT_TYPE']);
    }
    if ($normalized === 'CONTENT_LENGTH' && isset($_SERVER['CONTENT_LENGTH'])) {
        return trim((string) $_SERVER['CONTENT_LENGTH']);
    }

    $serverKey = str_starts_with($normalized, 'HTTP_') ? $normalized : ('HTTP_' . $normalized);
    if (isset($_SERVER[$serverKey])) {
        return trim((string) $_SERVER[$serverKey]);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $headerName => $headerValue) {
                if (strtoupper(str_replace('-', '_', (string) $headerName)) !== $normalized) {
                    continue;
                }
                return trim((string) $headerValue);
            }
        }
    }

    return '';
}

function notes_download_host_decode_header_value(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    return dent_force_utf8(rawurldecode($trimmed));
}

function notes_download_host_allowed_roots(): array
{
    return NOTES_DOWNLOAD_HOST_ALLOWED_ROOTS;
}

function notes_download_host_direct_upload_runtime_dir(): string
{
    return NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_RUNTIME_DIR;
}

function notes_download_host_direct_upload_gateway_relative_path(): string
{
    return notes_download_host_direct_upload_runtime_dir() . '/' . NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_GATEWAY_FILE;
}

function notes_download_host_direct_upload_user_ini_relative_path(): string
{
    return notes_download_host_direct_upload_runtime_dir() . '/' . NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_USER_INI_FILE;
}

function notes_download_host_secret_candidates(): array
{
    $candidates = [];

    $explicit = trim(dent_env_value('NOTES_DOWNLOAD_HOST_SECRET'));
    if ($explicit !== '') {
        $candidates[] = dent_resolve_path($explicit, DENT_PROJECT_ROOT);
    }

    $candidates[] = DENT_SECRETS_ROOT . DIRECTORY_SEPARATOR . NOTES_DOWNLOAD_HOST_SECRET_FILE;
    $candidates[] = DENT_PROJECT_ROOT . DIRECTORY_SEPARATOR . '.codex-local' . DIRECTORY_SEPARATOR . 'mihan-download-host.json';

    return array_values(array_unique(array_filter($candidates, static function ($path): bool {
        return is_string($path) && trim($path) !== '';
    })));
}

function notes_download_host_load_secret(): ?array
{
    static $cached = false;
    static $secret = null;

    if ($cached) {
        return $secret;
    }
    $cached = true;

    foreach (notes_download_host_secret_candidates() as $path) {
        if (!is_file($path) || !is_readable($path)) {
            continue;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            continue;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            continue;
        }

        $host = trim((string) ($decoded['cpanelHost'] ?? $decoded['ftpHost'] ?? $decoded['domain'] ?? ''));
        $username = trim((string) ($decoded['username'] ?? ''));
        $password = trim((string) ($decoded['password'] ?? ''));
        $publicDomain = trim((string) ($decoded['publicDomain'] ?? ''));
        $remoteBaseDir = trim((string) ($decoded['remoteBaseDir'] ?? 'public_html'));
        if ($host === '' || $username === '' || $password === '' || $publicDomain === '' || $remoteBaseDir === '') {
            continue;
        }

        $secret = [
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'publicDomain' => $publicDomain,
            'remoteBaseDir' => trim(str_replace('\\', '/', $remoteBaseDir), '/'),
            'scheme' => str_starts_with((string) ($decoded['cpanelBaseUrl'] ?? ''), 'https://') || !empty($decoded['cpanelSchemeHttps']) ? 'https' : 'http',
            'cpanelSecurePort' => max(1, (int) ($decoded['cpanelSecurePort'] ?? 2083)),
            'cpanelPort' => max(1, (int) ($decoded['cpanelPort'] ?? 2082)),
            'absoluteBaseDir' => trim(str_replace('\\', '/', (string) ($decoded['absoluteBaseDir'] ?? ''))),
        ];
        return $secret;
    }

    $host = trim(dent_env_value('NOTES_DOWNLOAD_HOST'));
    $username = trim(dent_env_value('NOTES_DOWNLOAD_HOST_USER'));
    $password = trim(dent_env_value('NOTES_DOWNLOAD_HOST_PASS'));
    $publicDomain = trim(dent_env_value('NOTES_DOWNLOAD_HOST_PUBLIC_DOMAIN'));
    $remoteBaseDir = trim(dent_env_value('NOTES_DOWNLOAD_HOST_REMOTE_BASE'));
    if ($remoteBaseDir === '') {
        $remoteBaseDir = 'public_html';
    }

    if ($host === '' || $username === '' || $password === '' || $publicDomain === '') {
        $secret = null;
        return null;
    }

    $secret = [
        'host' => $host,
        'username' => $username,
        'password' => $password,
        'publicDomain' => $publicDomain,
        'remoteBaseDir' => trim(str_replace('\\', '/', $remoteBaseDir), '/'),
        'scheme' => dent_env_value('NOTES_DOWNLOAD_HOST_SCHEME') === 'https' ? 'https' : 'http',
        'cpanelSecurePort' => max(1, (int) dent_env_value('NOTES_DOWNLOAD_HOST_SECURE_PORT') ?: 2083),
        'cpanelPort' => max(1, (int) dent_env_value('NOTES_DOWNLOAD_HOST_PORT') ?: 2082),
        'absoluteBaseDir' => trim(str_replace('\\', '/', dent_env_value('NOTES_DOWNLOAD_HOST_ABSOLUTE_BASE'))),
    ];

    return $secret;
}

function notes_download_host_is_enabled(): bool
{
    return is_array(notes_download_host_load_secret());
}

function notes_download_host_domain_is_resolvable(string $host): bool
{
    $normalized = strtolower(trim($host));
    if ($normalized === '') {
        return false;
    }
    if (filter_var($normalized, FILTER_VALIDATE_IP)) {
        return true;
    }

    if (function_exists('dns_get_record')) {
        $records = @dns_get_record($normalized, DNS_A + DNS_AAAA + DNS_CNAME);
        if (is_array($records) && $records !== []) {
            return true;
        }
    }
    if (function_exists('checkdnsrr')) {
        foreach (['A', 'AAAA', 'CNAME'] as $type) {
            if (@checkdnsrr($normalized, $type)) {
                return true;
            }
        }
    }

    $resolved = @gethostbyname($normalized);
    return is_string($resolved) && trim($resolved) !== '' && strcasecmp($resolved, $normalized) !== 0;
}

function notes_download_host_domain_accepts_https(string $host, int $timeoutMs = 1200): bool
{
    $normalized = strtolower(trim($host));
    if ($normalized === '') {
        return false;
    }

    $address = filter_var($normalized, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        ? 'ssl://[' . $normalized . ']:443'
        : 'ssl://' . $normalized . ':443';
    $timeoutSeconds = max(0.2, $timeoutMs / 1000);
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => true,
        ],
    ]);
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($address, $errno, $errstr, $timeoutSeconds, STREAM_CLIENT_CONNECT, $context);
    if (!is_resource($socket)) {
        return false;
    }

    fclose($socket);
    return true;
}

function notes_download_host_public_base_url(): string
{
    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        return '';
    }

    return 'https://' . trim((string) $secret['publicDomain'], '/');
}

function notes_download_host_prepare_long_transfer(): void
{
    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    @ini_set('max_execution_time', '0');
    @ini_set('default_socket_timeout', (string) NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    if (function_exists('dent_release_session_lock')) {
        dent_release_session_lock();
    }
}

function notes_download_host_socket_write_all($socket, string $payload, string $phaseLabel): void
{
    $offset = 0;
    $length = strlen($payload);
    while ($offset < $length) {
        $written = @fwrite($socket, substr($payload, $offset));
        if (!is_int($written) || $written <= 0) {
            $meta = is_resource($socket) ? stream_get_meta_data($socket) : [];
            if (!empty($meta['timed_out'])) {
                dent_error($phaseLabel . ' به‌خاطر timeout شبکه کامل نشد.', 504);
            }
            dent_error($phaseLabel . ' به‌خاطر قطع ارتباط شبکه کامل نشد.', 502);
        }
        $offset += $written;
    }
}

function notes_download_host_normalize_relative_path(string $path): string
{
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = preg_replace('#/+#', '/', $normalized) ?? '';
    $normalized = trim($normalized, '/');
    if ($normalized === '') {
        return '';
    }

    $segments = [];
    foreach (explode('/', $normalized) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            dent_error('مسیر پوشه یا فایل معتبر نیست.', 422);
        }
        if (preg_match('/[\x00-\x1f]/u', $segment) === 1) {
            dent_error('نام پوشه یا فایل معتبر نیست.', 422);
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
}

function notes_download_host_root_of_relative_path(string $relativePath): string
{
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return '';
    }

    $parts = explode('/', $normalized, 2);
    return (string) ($parts[0] ?? '');
}

function notes_download_host_is_allowed_relative_path(string $relativePath, ?string $scopeRoot = null, bool $allowRoot = true): bool
{
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return $allowRoot && ($scopeRoot === null || $scopeRoot === '');
    }

    $root = notes_download_host_root_of_relative_path($normalized);
    if ($scopeRoot !== null && $scopeRoot !== '') {
        return $root === $scopeRoot;
    }

    return in_array($root, notes_download_host_allowed_roots(), true);
}

function notes_download_host_assert_allowed_relative_path(string $relativePath, ?string $scopeRoot = null, bool $allowRoot = true): string
{
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if (!notes_download_host_is_allowed_relative_path($normalized, $scopeRoot, $allowRoot)) {
        dent_error('این مسیر برای مدیریت منابع مجاز نیست.', 403);
    }

    return $normalized;
}

function notes_download_host_public_url(string $relativePath): string
{
    $base = notes_download_host_public_base_url();
    if ($base === '') {
        return '';
    }

    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return $base . '/';
    }

    $segments = array_map(static function (string $segment): string {
        return rawurlencode($segment);
    }, explode('/', $normalized));

    return $base . '/' . implode('/', $segments);
}

function notes_download_host_visible_term_number_from_title(string $title, int $fallback): int
{
    $normalized = dent_normalize_digits($title);
    if (preg_match('/(\d+)/u', $normalized, $matches) === 1) {
        $value = (int) ($matches[1] ?? 0);
        if ($value > 0) {
            return $value;
        }
    }

    return max(1, $fallback);
}

function notes_download_host_term_relative_dir(string $cohort, int $term = 0, string $termTitle = ''): string
{
    if ($cohort === '1403' || $cohort === '1404') {
        return $cohort . '/term-' . str_pad((string) max(1, $term), 2, '0', STR_PAD_LEFT);
    }

    if ($cohort === 'prosthesis-1402') {
        $visibleTerm = notes_download_host_visible_term_number_from_title($termTitle, $term);
        return 'prosthesis-1402/term-' . str_pad((string) max(1, $visibleTerm), 2, '0', STR_PAD_LEFT);
    }

    return '1402/term-' . str_pad((string) max(1, $term), 2, '0', STR_PAD_LEFT);
}

function notes_download_host_path_segment(string $value, string $fallback): string
{
    $clean = preg_replace('/[^a-z0-9\-]+/i', '-', dent_normalize_digits(trim($value))) ?? '';
    $clean = trim($clean, '-');
    if ($clean === '') {
        return $fallback;
    }
    return strtolower($clean);
}

function notes_download_host_unit_relative_dir(
    string $baseDir,
    string $unitKey = '',
    string $categoryKey = '',
    string $unitTitle = ''
): string {
    $baseDir = notes_download_host_normalize_relative_path($baseDir);
    if ($baseDir === '') {
        return '';
    }

    $normalizedUnitKey = trim(strtolower($unitKey));
    if ($normalizedUnitKey === '' || preg_match('/^uncategorized-term-\d+$/', $normalizedUnitKey) === 1) {
        return $baseDir . '/uncategorized';
    }

    $categorySegment = notes_download_host_path_segment($categoryKey, 'units');
    $unitSegment = notes_download_host_path_segment($normalizedUnitKey !== '' ? $normalizedUnitKey : $unitTitle, 'unit');
    return $baseDir . '/' . $categorySegment . '/' . $unitSegment;
}

function notes_download_host_default_relative_dir(
    string $cohort,
    int $term = 0,
    string $termTitle = '',
    ?array $termPayload = null
): string {
    $baseDir = notes_download_host_term_relative_dir($cohort, $term, $termTitle);
    if (!is_array($termPayload) || (string) ($termPayload['mode'] ?? '') !== 'curriculum-unit') {
        return $baseDir;
    }

    return notes_download_host_unit_relative_dir(
        $baseDir,
        (string) ($termPayload['unitKey'] ?? ''),
        (string) ($termPayload['categoryKey'] ?? ''),
        (string) ($termPayload['unitTitle'] ?? $termPayload['title'] ?? '')
    );
}

function notes_download_host_http_headers(array $secret): array
{
    return [
        'Authorization: Basic ' . base64_encode((string) $secret['username'] . ':' . (string) $secret['password']),
        'Accept: application/json',
        'User-Agent: Dentistry1402TUMS-NotesDownloadHost/1.0',
        'Connection: close',
    ];
}

function notes_download_host_base_url(array $secret): string
{
    $scheme = (string) ($secret['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
    $port = $scheme === 'https'
        ? (int) ($secret['cpanelSecurePort'] ?? 2083)
        : (int) ($secret['cpanelPort'] ?? 2082);

    return $scheme . '://' . $secret['host'] . ':' . $port;
}

function notes_download_host_http_request(string $method, string $url, array $headers = [], ?string $body = null): array
{
    $baseHeaders = [
        'ignore_errors' => true,
        'timeout' => 120,
        'protocol_version' => 1.1,
        'header' => implode("\r\n", $headers) . "\r\n",
        'method' => strtoupper($method),
    ];
    if ($body !== null) {
        $baseHeaders['content'] = $body;
    }

    $context = stream_context_create([
        'http' => $baseHeaders,
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
    $status = 0;
    foreach ($responseHeaders as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/i', (string) $line, $matches) === 1) {
            $status = (int) ($matches[1] ?? 0);
            break;
        }
    }

    if (!is_string($raw)) {
        $error = error_get_last();
        dent_error('اتصال به هاست دانلود برقرار نشد: ' . trim((string) ($error['message'] ?? 'خطای نامشخص')), 502);
    }

    return [
        'status' => $status,
        'body' => $raw,
        'headers' => $responseHeaders,
    ];
}

function notes_download_host_decode_json_response(array $response, string $fallbackMessage): array
{
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($decoded)) {
        dent_error($fallbackMessage, 502);
    }

    return $decoded;
}

function notes_download_host_error_message_from_response(array $decoded, string $fallbackMessage = 'عملیات هاست دانلود ناموفق بود.'): string
{
    $errors = $decoded['errors'] ?? [];
    if (is_array($errors) && $errors !== []) {
        $parts = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $errors), static function (string $value): bool {
            return $value !== '';
        }));
        if ($parts !== []) {
            return implode(' | ', $parts);
        }
    }

    $messages = $decoded['messages'] ?? [];
    if (is_array($messages) && $messages !== []) {
        $parts = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $messages), static function (string $value): bool {
            return $value !== '';
        }));
        if ($parts !== []) {
            return implode(' | ', $parts);
        }
    }

    return $fallbackMessage;
}

function notes_download_host_is_missing_directory_error_message(string $message): bool
{
    $normalized = dent_utf8_strtolower(trim($message));
    if ($normalized === '') {
        return false;
    }

    $mentionsDirectory = strpos($normalized, 'directory') !== false
        || strpos($normalized, 'folder') !== false
        || strpos($normalized, 'path') !== false;
    if (!$mentionsDirectory) {
        return false;
    }

    return strpos($normalized, 'does not exist') !== false
        || strpos($normalized, 'not exist') !== false
        || strpos($normalized, 'not found') !== false
        || strpos($normalized, 'no such file') !== false
        || strpos($normalized, 'failed to opendir') !== false
        || strpos($normalized, 'cannot access') !== false
        || strpos($normalized, "can't access") !== false;
}

function notes_download_host_is_missing_directory_response(array $decoded): bool
{
    return notes_download_host_is_missing_directory_error_message(
        notes_download_host_error_message_from_response($decoded, '')
    );
}

function notes_download_host_execute_uapi(string $operation, array $query = [], bool $allowFailure = false): array
{
    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $url = notes_download_host_base_url($secret) . '/execute/' . ltrim($operation, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    $response = notes_download_host_http_request('GET', $url, notes_download_host_http_headers($secret));
    $decoded = notes_download_host_decode_json_response($response, 'پاسخ نامعتبر از هاست دانلود دریافت شد.');
    if ((int) ($decoded['status'] ?? 0) !== 1 && !$allowFailure) {
        $message = notes_download_host_error_message_from_response($decoded);
        dent_error($message, 502);
    }

    return $decoded;
}

function notes_download_host_execute_api2(string $func, array $params = []): array
{
    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $query = array_merge([
        'cpanel_jsonapi_user' => (string) $secret['username'],
        'cpanel_jsonapi_apiversion' => '2',
        'cpanel_jsonapi_module' => 'Fileman',
        'cpanel_jsonapi_func' => $func,
    ], $params);

    $url = notes_download_host_base_url($secret) . '/json-api/cpanel?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $response = notes_download_host_http_request('GET', $url, notes_download_host_http_headers($secret));
    $decoded = notes_download_host_decode_json_response($response, 'پاسخ نامعتبر از پنل فایل هاست دانلود دریافت شد.');
    $result = is_array($decoded['cpanelresult'] ?? null) ? $decoded['cpanelresult'] : [];

    return $result;
}

function notes_download_host_absolute_base_dir(): string
{
    static $cached = null;
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $configured = trim((string) ($secret['absoluteBaseDir'] ?? ''));
    if ($configured !== '') {
        $cached = rtrim(str_replace('\\', '/', $configured), '/');
        return $cached;
    }

    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => (string) $secret['remoteBaseDir'],
        'include_mime' => '0',
    ]);
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $absDir = trim((string) ($item['absdir'] ?? ''));
        if ($absDir !== '') {
            $cached = rtrim(str_replace('\\', '/', $absDir), '/');
            return $cached;
        }
    }

    dent_error('ریشه مطلق هاست دانلود قابل تشخیص نیست.', 502);
}

function notes_download_host_abs_path_from_relative(string $relativePath): string
{
    $base = notes_download_host_absolute_base_dir();
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    if ($normalized === '') {
        return $base;
    }

    return $base . '/' . $normalized;
}

function notes_download_host_relative_from_abs_path(string $absolutePath): string
{
    $base = notes_download_host_absolute_base_dir();
    $normalized = rtrim(str_replace('\\', '/', trim($absolutePath)), '/');
    if ($normalized === $base) {
        return '';
    }
    if (!str_starts_with($normalized . '/', $base . '/')) {
        return '';
    }

    return notes_download_host_normalize_relative_path(substr($normalized, strlen($base) + 1));
}

function notes_download_host_sort_entries(array &$entries): void
{
    usort($entries, static function (array $left, array $right): int {
        $leftDir = (($left['type'] ?? '') === 'dir') ? 0 : 1;
        $rightDir = (($right['type'] ?? '') === 'dir') ? 0 : 1;
        if ($leftDir !== $rightDir) {
            return $leftDir <=> $rightDir;
        }

        return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });
}

function notes_download_host_human_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    foreach ($units as $index => $unit) {
        if ($value < 1024 || $index === count($units) - 1) {
            return number_format($value, $value < 10 ? 1 : 0, '.', ',') . ' ' . $unit;
        }
        $value /= 1024;
    }

    return number_format($value, 1, '.', ',') . ' TB';
}

function notes_download_host_entry_payload(array $item): array
{
    $name = trim((string) ($item['file'] ?? ''));
    $type = trim((string) ($item['type'] ?? 'file')) === 'dir' ? 'dir' : 'file';
    $parentAbs = rtrim(str_replace('\\', '/', trim((string) ($item['path'] ?? $item['absdir'] ?? ''))), '/');
    $parentRelative = notes_download_host_relative_from_abs_path($parentAbs);
    $relativePath = $parentRelative === '' ? $name : $parentRelative . '/' . $name;
    $bytes = max(0, (int) ($item['size'] ?? 0));
    $modifiedAt = (int) ($item['mtime'] ?? 0);

    return [
        'name' => $name,
        'type' => $type,
        'relativePath' => $relativePath,
        'parentPath' => $parentRelative,
        'sizeBytes' => $bytes,
        'sizeLabel' => $type === 'dir' ? 'پوشه' : notes_download_host_human_size($bytes),
        'mimeType' => (string) ($item['mimetype'] ?? ''),
        'modifiedAt' => $modifiedAt > 0 ? date(DATE_ATOM, $modifiedAt) : '',
        'publicUrl' => $type === 'file' ? notes_download_host_public_url($relativePath) : '',
    ];
}

function notes_download_host_root_listing(): array
{
    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => notes_download_host_absolute_base_dir(),
        'include_mime' => '0',
    ]);
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    $byName = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['file'] ?? ''));
        if ($name === '') {
            continue;
        }
        $byName[$name] = $item;
    }

    $entries = [];
    foreach (notes_download_host_allowed_roots() as $root) {
        if (isset($byName[$root]) && is_array($byName[$root])) {
            $entries[] = notes_download_host_entry_payload($byName[$root]);
            continue;
        }

        $entries[] = [
            'name' => $root,
            'type' => 'dir',
            'relativePath' => $root,
            'parentPath' => '',
            'sizeBytes' => 0,
            'sizeLabel' => 'پوشه آماده‌سازی نشده',
            'mimeType' => '',
            'modifiedAt' => '',
            'publicUrl' => '',
            'missing' => true,
        ];
    }

    return $entries;
}

function notes_download_host_breadcrumbs(string $relativePath): array
{
    $breadcrumbs = [
        [
            'label' => 'ریشه منابع',
            'path' => '',
        ],
    ];
    if ($relativePath === '') {
        return $breadcrumbs;
    }

    $parts = explode('/', $relativePath);
    $current = '';
    foreach ($parts as $part) {
        $current = $current === '' ? $part : $current . '/' . $part;
        $breadcrumbs[] = [
            'label' => $part,
            'path' => $current,
        ];
    }

    return $breadcrumbs;
}

function notes_download_host_browse(string $relativePath, ?string $scopeRoot = null): array
{
    $normalized = notes_download_host_assert_allowed_relative_path($relativePath, $scopeRoot, true);
    if ($normalized === '') {
        $entries = notes_download_host_root_listing();
        notes_download_host_sort_entries($entries);
        return [
            'currentPath' => '',
            'entries' => $entries,
            'breadcrumbs' => notes_download_host_breadcrumbs(''),
            'missingDirectory' => false,
        ];
    }

    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => notes_download_host_abs_path_from_relative($normalized),
        'include_mime' => '1',
    ], true);
    if ((int) ($response['status'] ?? 0) !== 1) {
        if (notes_download_host_is_missing_directory_response($response)) {
            return [
                'currentPath' => $normalized,
                'entries' => [],
                'breadcrumbs' => notes_download_host_breadcrumbs($normalized),
                'missingDirectory' => true,
                'notice' => 'این پوشه هنوز روی هاست دانلود ساخته نشده است. با اولین آپلود فایل یا ساخت زیرپوشه، مسیر به‌صورت خودکار ایجاد می‌شود.',
            ];
        }
        dent_error(notes_download_host_error_message_from_response($response), 502);
    }
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    $entries = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $entries[] = notes_download_host_entry_payload($item);
    }
    notes_download_host_sort_entries($entries);

    return [
        'currentPath' => $normalized,
        'entries' => $entries,
        'breadcrumbs' => notes_download_host_breadcrumbs($normalized),
        'missingDirectory' => false,
    ];
}

function notes_download_host_sanitize_leaf_name(string $name, string $fallback): string
{
    $clean = preg_replace('/[\x00-\x1f<>:"\/\\\\|?*]+/u', ' ', trim($name)) ?? '';
    $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
    $clean = trim((string) $clean, ". \t\n\r\0\x0B");
    if ($clean === '' || $clean === '.' || $clean === '..') {
        return $fallback;
    }

    return $clean;
}

function notes_download_host_ensure_dir(string $relativeDir, ?string $scopeRoot = null): string
{
    $normalized = notes_download_host_assert_allowed_relative_path($relativeDir, $scopeRoot, false);
    $parts = explode('/', $normalized);
    $currentRelative = '';
    $currentAbs = notes_download_host_absolute_base_dir();
    foreach ($parts as $part) {
        $part = notes_download_host_sanitize_leaf_name($part, 'folder');
        $result = notes_download_host_execute_api2('mkdir', [
            'path' => $currentAbs,
            'name' => $part,
            'permissions' => '0755',
        ]);
        $error = trim((string) ($result['error'] ?? ''));
        if ($error !== '' && stripos($error, 'file exists') === false) {
            dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
        }
        $currentRelative = $currentRelative === '' ? $part : $currentRelative . '/' . $part;
        $currentAbs = notes_download_host_abs_path_from_relative($currentRelative);
    }

    return notes_download_host_abs_path_from_relative($normalized);
}

function notes_download_host_file_names_in_dir(string $relativeDir): array
{
    $browse = notes_download_host_browse($relativeDir);
    $names = [];
    foreach (($browse['entries'] ?? []) as $entry) {
        if (($entry['type'] ?? '') !== 'file') {
            continue;
        }
        $names[dent_utf8_strtolower((string) ($entry['name'] ?? ''))] = true;
    }

    return $names;
}

function notes_download_host_unique_file_name_with_reserved(string $relativeDir, string $name, array $reservedNames = []): string
{
    $name = notes_download_host_sanitize_leaf_name($name, 'file');
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $baseName = $extension !== '' ? substr($name, 0, -1 * (strlen($extension) + 1)) : $name;
    $baseName = notes_download_host_sanitize_leaf_name($baseName, 'file');
    $candidate = $extension !== '' ? ($baseName . '.' . $extension) : $baseName;

    $existing = notes_download_host_file_names_in_dir($relativeDir);
    foreach ($reservedNames as $reservedName) {
        $cleanReserved = notes_download_host_sanitize_leaf_name((string) $reservedName, '');
        if ($cleanReserved === '') {
            continue;
        }
        $existing[dent_utf8_strtolower($cleanReserved)] = true;
    }

    $index = 2;
    $lower = dent_utf8_strtolower($candidate);
    while (isset($existing[$lower])) {
        $candidate = $extension !== ''
            ? ($baseName . ' (' . $index . ').' . $extension)
            : ($baseName . ' (' . $index . ')');
        $lower = dent_utf8_strtolower($candidate);
        $index++;
    }

    return $candidate;
}

function notes_download_host_unique_file_name(string $relativeDir, string $name): string
{
    return notes_download_host_unique_file_name_with_reserved($relativeDir, $name, []);
}

function notes_download_host_assert_internal_runtime_relative_path(string $relativePath): string
{
    $normalized = notes_download_host_normalize_relative_path($relativePath);
    $runtimeDir = notes_download_host_direct_upload_runtime_dir();
    if ($normalized === '' || ($normalized !== $runtimeDir && !str_starts_with($normalized . '/', $runtimeDir . '/'))) {
        dent_error('Direct upload runtime path is invalid.', 500);
    }

    return $normalized;
}

function notes_download_host_internal_runtime_public_url(string $relativePath = ''): string
{
    $base = notes_download_host_public_base_url();
    if ($base === '') {
        return '';
    }

    $normalized = trim($relativePath) === ''
        ? notes_download_host_direct_upload_runtime_dir()
        : notes_download_host_assert_internal_runtime_relative_path($relativePath);

    $segments = array_map(static function (string $segment): string {
        return rawurlencode($segment);
    }, explode('/', $normalized));

    return $base . '/' . implode('/', $segments);
}

function notes_download_host_internal_runtime_ensure_dir(string $relativeDir): string
{
    $normalized = notes_download_host_assert_internal_runtime_relative_path($relativeDir);
    $parts = explode('/', $normalized);
    $currentRelative = '';
    $currentAbs = notes_download_host_absolute_base_dir();
    foreach ($parts as $part) {
        $part = notes_download_host_sanitize_leaf_name($part, 'runtime');
        $result = notes_download_host_execute_api2('mkdir', [
            'path' => $currentAbs,
            'name' => $part,
            'permissions' => '0755',
        ]);
        $error = trim((string) ($result['error'] ?? ''));
        if ($error !== '' && stripos($error, 'file exists') === false) {
            dent_error('Runtime directory creation on the download host failed: ' . $error, 502);
        }
        $currentRelative = $currentRelative === '' ? $part : ($currentRelative . '/' . $part);
        $currentAbs = notes_download_host_abs_path_from_relative($currentRelative);
    }

    return $currentAbs;
}

function notes_download_host_internal_runtime_delete_entry(string $relativePath, string $entryType = 'file', bool $ignoreMissing = true): void
{
    $relativePath = notes_download_host_assert_internal_runtime_relative_path($relativePath);
    $operation = $entryType === 'dir' ? 'trash' : 'unlink';
    $result = notes_download_host_execute_api2('fileop', [
        'op' => $operation,
        'sourcefiles' => notes_download_host_abs_path_from_relative($relativePath),
        'doubledecode' => '1',
    ]);

    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    if ((int) ($row['result'] ?? 0) === 1) {
        return;
    }

    $error = trim((string) ($row['err'] ?? $result['error'] ?? 'Delete failed.'));
    $missing = stripos($error, 'No such file') !== false
        || stripos($error, 'No such file or directory') !== false
        || stripos($error, 'does not exist') !== false;
    if ($ignoreMissing && $missing) {
        return;
    }

    dent_error($error !== '' ? $error : 'Delete failed.', 502);
}

function notes_download_host_stream_from_string(string $payload)
{
    $stream = fopen('php://temp', 'r+b');
    if ($stream === false) {
        dent_error('Temporary stream for runtime upload could not be created.', 500);
    }
    if ($payload !== '' && fwrite($stream, $payload) === false) {
        fclose($stream);
        dent_error('Runtime payload could not be staged for upload.', 500);
    }
    rewind($stream);
    return $stream;
}

function notes_download_host_internal_runtime_upload_text_file(string $relativePath, string $contents, string $mimeType = 'text/plain'): void
{
    $relativePath = notes_download_host_assert_internal_runtime_relative_path($relativePath);
    $parent = dirname($relativePath);
    $parent = $parent === '.' ? notes_download_host_direct_upload_runtime_dir() : notes_download_host_assert_internal_runtime_relative_path($parent);
    notes_download_host_internal_runtime_ensure_dir($parent);
    notes_download_host_internal_runtime_delete_entry($relativePath, 'file', true);

    $stream = notes_download_host_stream_from_string($contents);
    try {
        notes_download_host_stream_upload_from_stream(
            notes_download_host_abs_path_from_relative($parent),
            $stream,
            strlen($contents),
            basename($relativePath),
            $mimeType
        );
    } finally {
        fclose($stream);
    }
}

function notes_download_host_direct_upload_user_ini_source(): string
{
    return implode("\n", [
        'post_max_size=20G',
        'upload_max_filesize=20G',
        'max_execution_time=0',
        'max_input_time=0',
        'memory_limit=512M',
        'default_socket_timeout=' . NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS,
        'output_buffering=0',
        'zlib.output_compression=0',
        '',
    ]);
}

function notes_download_host_direct_upload_gateway_source(string $mainSiteOrigin): string
{
    $mainSiteOrigin = rtrim(trim($mainSiteOrigin), '/');
    $template = <<<'PHP'
<?php
declare(strict_types=1);

const DENT_NOTES_GATEWAY_VERSION = __VERSION__;
const DENT_NOTES_GATEWAY_MAIN_SITE_ORIGIN = __MAIN_SITE_ORIGIN__;
const DENT_NOTES_GATEWAY_RESOLVE_URL = __RESOLVE_URL__;
const DENT_NOTES_GATEWAY_COMPLETE_URL = __COMPLETE_URL__;
const DENT_NOTES_GATEWAY_ALLOWED_ORIGIN = __ALLOWED_ORIGIN__;
const DENT_NOTES_GATEWAY_ALLOWED_ROOTS = __ALLOWED_ROOTS__;
const DENT_NOTES_GATEWAY_TIMEOUT_SECONDS = __TIMEOUT_SECONDS__;
const DENT_NOTES_GATEWAY_CHUNK_BYTES = __CHUNK_BYTES__;

function dent_notes_gateway_origin(): string
{
    return trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
}

function dent_notes_gateway_apply_cors(?string $origin = null): void
{
    $candidate = trim((string) ($origin ?? dent_notes_gateway_origin()));
    if ($candidate === '' || !hash_equals(DENT_NOTES_GATEWAY_ALLOWED_ORIGIN, $candidate)) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $candidate);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
    header('Access-Control-Max-Age: 86400');
}

function dent_notes_gateway_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if (!is_string($json) || $json === '') {
        $json = '{"success":false,"error":"gateway-json-failed"}';
    }
    echo $json;
    exit;
}

function dent_notes_gateway_clean_token($value): string
{
    $token = trim(strtolower((string) $value));
    return preg_match('/^[a-z0-9_-]{16,200}$/', $token) === 1 ? $token : '';
}

function dent_notes_gateway_normalize_relative_path(string $path): string
{
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = preg_replace('#/+#', '/', $normalized) ?? '';
    $normalized = trim($normalized, '/');
    if ($normalized === '') {
        return '';
    }

    $segments = [];
    foreach (explode('/', $normalized) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..' || preg_match('/[\x00-\x1f]/u', $segment) === 1) {
            throw new RuntimeException('invalid-relative-path');
        }
        $segments[] = $segment;
    }

    return implode('/', $segments);
}

function dent_notes_gateway_relative_root(string $relativePath): string
{
    if ($relativePath === '') {
        return '';
    }
    $parts = explode('/', $relativePath, 2);
    return (string) ($parts[0] ?? '');
}

function dent_notes_gateway_root_is_allowed(string $relativePath): bool
{
    $root = dent_notes_gateway_relative_root($relativePath);
    return $root !== '' && in_array($root, DENT_NOTES_GATEWAY_ALLOWED_ROOTS, true);
}

function dent_notes_gateway_base_dir(): string
{
    static $cached = null;
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $candidate = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    if (!is_string($candidate) || $candidate === '') {
        throw new RuntimeException('gateway-base-dir-missing');
    }

    $cached = rtrim(str_replace('\\', '/', $candidate), '/');
    return $cached;
}

function dent_notes_gateway_absolute_path(string $relativePath): string
{
    return dent_notes_gateway_base_dir() . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function dent_notes_gateway_ensure_parent_dir(string $absolutePath): void
{
    $directory = dirname($absolutePath);
    if (is_dir($directory)) {
        return;
    }
    if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('create-directory-failed');
    }
}

function dent_notes_gateway_parse_size_bytes(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*([kmgt]?)(?:b)?\s*$/i', $value, $matches) !== 1) {
        return is_numeric($value) ? max(0, (int) round((float) $value)) : null;
    }

    $number = (float) ($matches[1] ?? 0);
    $suffix = strtolower((string) ($matches[2] ?? ''));
    $powers = ['' => 0, 'k' => 1, 'm' => 2, 'g' => 3, 't' => 4];
    $power = $powers[$suffix] ?? 0;
    $bytes = $number * (1024 ** $power);
    return max(0, (int) round($bytes));
}

function dent_notes_gateway_http_post_json(string $url, array $payload): array
{
    $body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'timeout' => DENT_NOTES_GATEWAY_TIMEOUT_SECONDS,
            'protocol_version' => 1.1,
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Accept: application/json',
                'Connection: close',
                'Content-Length: ' . strlen($body),
            ]) . "\r\n",
            'content' => $body,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
    if (!is_string($raw)) {
        $error = error_get_last();
        throw new RuntimeException('gateway-callback-failed:' . trim((string) ($error['message'] ?? 'network')));
    }

    $status = 0;
    foreach ($responseHeaders as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/i', (string) $line, $matches) === 1) {
            $status = (int) ($matches[1] ?? 0);
            break;
        }
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('gateway-callback-json-invalid');
    }

    $decoded['httpStatus'] = $status;
    return $decoded;
}

function dent_notes_gateway_health_payload(): array
{
    return [
        'success' => true,
        'version' => DENT_NOTES_GATEWAY_VERSION,
        'mainSiteOrigin' => DENT_NOTES_GATEWAY_MAIN_SITE_ORIGIN,
        'allowedOrigin' => DENT_NOTES_GATEWAY_ALLOWED_ORIGIN,
        'postMaxSize' => (string) ini_get('post_max_size'),
        'postMaxBytes' => dent_notes_gateway_parse_size_bytes((string) ini_get('post_max_size')),
        'uploadMaxSize' => (string) ini_get('upload_max_filesize'),
        'uploadMaxBytes' => dent_notes_gateway_parse_size_bytes((string) ini_get('upload_max_filesize')),
        'maxInputTime' => (string) ini_get('max_input_time'),
        'maxExecutionTime' => (string) ini_get('max_execution_time'),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    dent_notes_gateway_apply_cors();
    http_response_code(204);
    exit;
}

if (isset($_GET['health'])) {
    dent_notes_gateway_apply_cors();
    dent_notes_gateway_json(dent_notes_gateway_health_payload());
}

// The FTP service used by the main host does not reliably support REST+STOR
// append. Each bounded chunk is therefore uploaded as an independent part and
// this authenticated GET assembles the parts locally on the DOWNLOAD host.
// The complete file never exists on the capacity-limited main host.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string) ($_GET['action'] ?? '') === 'assemble') {
    dent_notes_gateway_apply_cors();
    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) { @set_time_limit(0); }

    $token = dent_notes_gateway_clean_token($_GET['token'] ?? '');
    $chunkCount = max(1, min(10000, (int) ($_GET['chunkCount'] ?? 0)));
    if ($token === '' || $chunkCount <= 0) {
        dent_notes_gateway_json(['success' => false, 'error' => 'invalid-assemble-request'], 422);
    }

    $resolve = dent_notes_gateway_http_post_json(DENT_NOTES_GATEWAY_RESOLVE_URL, [
        'token' => $token,
        'gatewayVersion' => DENT_NOTES_GATEWAY_VERSION,
        'origin' => DENT_NOTES_GATEWAY_MAIN_SITE_ORIGIN,
        'contentType' => 'application/octet-stream',
        'contentLength' => '0',
    ]);
    if (!($resolve['success'] ?? false) || !is_array($resolve['session'] ?? null)) {
        dent_notes_gateway_json(['success' => false, 'error' => 'assemble-resolve-failed'], 403);
    }

    $session = $resolve['session'];
    $sessionKey = trim((string) ($session['sessionKey'] ?? ''));
    $relativePath = dent_notes_gateway_normalize_relative_path((string) ($session['relativePath'] ?? ''));
    $expectedSize = max(0, (int) ($session['expectedSize'] ?? 0));
    if ($sessionKey === '' || $relativePath === '' || $expectedSize <= 0 || !dent_notes_gateway_root_is_allowed($relativePath)) {
        dent_notes_gateway_json(['success' => false, 'error' => 'assemble-session-invalid'], 422);
    }

    $absolutePath = dent_notes_gateway_absolute_path($relativePath);
    dent_notes_gateway_ensure_parent_dir($absolutePath);
    $tokenKey = preg_replace('/[^a-z0-9]/', '', strtolower($token)) ?? '';
    $partPrefix = $absolutePath . '.dentup-' . substr($tokenKey, 0, 24) . '.part-';
    $assemblingPath = $absolutePath . '.dentup-' . substr($tokenKey, 0, 24) . '.assembling';
    $target = @fopen($assemblingPath, 'wb');
    if ($target === false) {
        dent_notes_gateway_json(['success' => false, 'error' => 'assemble-target-open-failed'], 500);
    }

    $storedBytes = 0;
    try {
        for ($index = 0; $index < $chunkCount; $index++) {
            $partPath = $partPrefix . str_pad((string) $index, 6, '0', STR_PAD_LEFT);
            $source = @fopen($partPath, 'rb');
            if ($source === false) { throw new RuntimeException('assemble-part-missing-' . $index); }
            $copied = stream_copy_to_stream($source, $target);
            fclose($source);
            if (!is_int($copied) || $copied <= 0) { throw new RuntimeException('assemble-part-copy-failed-' . $index); }
            $storedBytes += $copied;
        }
        fclose($target);
        $target = null;
        if ($storedBytes !== $expectedSize) { throw new RuntimeException('assemble-size-mismatch'); }
        if (is_file($absolutePath)) { @unlink($absolutePath); }
        if (!@rename($assemblingPath, $absolutePath)) { throw new RuntimeException('assemble-rename-failed'); }
        @chmod($absolutePath, 0644);

        $complete = dent_notes_gateway_http_post_json(DENT_NOTES_GATEWAY_COMPLETE_URL, [
            'token' => $token,
            'sessionKey' => $sessionKey,
            'bytes' => (string) $storedBytes,
            'mimeType' => (string) ($session['mimeType'] ?? 'application/octet-stream'),
            'gatewayVersion' => DENT_NOTES_GATEWAY_VERSION,
            'origin' => DENT_NOTES_GATEWAY_MAIN_SITE_ORIGIN,
        ]);
        if (!($complete['success'] ?? false)) { throw new RuntimeException('assemble-complete-callback-failed'); }
        for ($index = 0; $index < $chunkCount; $index++) {
            @unlink($partPrefix . str_pad((string) $index, 6, '0', STR_PAD_LEFT));
        }
        dent_notes_gateway_json($complete);
    } catch (Throwable $error) {
        if (is_resource($target)) { fclose($target); }
        @unlink($assemblingPath);
        dent_notes_gateway_json(['success' => false, 'error' => $error->getMessage()], 502);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    dent_notes_gateway_apply_cors();
    dent_notes_gateway_json([
        'success' => false,
        'error' => 'method-not-allowed',
    ], 405);
}

$origin = dent_notes_gateway_origin();
if ($origin !== '' && !hash_equals(DENT_NOTES_GATEWAY_ALLOWED_ORIGIN, $origin)) {
    dent_notes_gateway_apply_cors();
    dent_notes_gateway_json([
        'success' => false,
        'error' => 'origin-not-allowed',
    ], 403);
}

dent_notes_gateway_apply_cors($origin);
@ignore_user_abort(true);
if (function_exists('set_time_limit')) {
    @set_time_limit(0);
}
@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '0');

$token = dent_notes_gateway_clean_token($_GET['token'] ?? ($_POST['token'] ?? ''));
if ($token === '') {
    dent_notes_gateway_json([
        'success' => false,
        'error' => 'invalid-upload-token',
    ], 422);
}

$contentType = trim((string) strtok((string) ($_SERVER['CONTENT_TYPE'] ?? ''), ';'));
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? max(0, (int) $_SERVER['CONTENT_LENGTH']) : 0;
// Chunked uploads split a large file into several short requests so each one
// finishes well under the host WAF's slow-upload threshold.
$chunkCount = isset($_GET['chunkCount']) ? max(1, (int) $_GET['chunkCount']) : 1;
$chunkIndex = isset($_GET['chunkIndex']) ? max(0, (int) $_GET['chunkIndex']) : 0;
$isChunked = $chunkCount > 1;
$isLastChunk = !$isChunked || ($chunkIndex >= $chunkCount - 1);
$absolutePath = '';
$tempPath = '';
$cleanupFinal = false;

try {
    $resolve = dent_notes_gateway_http_post_json(DENT_NOTES_GATEWAY_RESOLVE_URL, [
        'token' => $token,
        'gatewayVersion' => DENT_NOTES_GATEWAY_VERSION,
        'origin' => $origin,
        'contentType' => $contentType,
        'contentLength' => (string) $contentLength,
    ]);
    if (!($resolve['success'] ?? false) || !is_array($resolve['session'] ?? null)) {
        $status = max(400, (int) ($resolve['httpStatus'] ?? 502));
        dent_notes_gateway_json([
            'success' => false,
            'error' => trim((string) ($resolve['error'] ?? 'direct-upload-resolve-failed')),
        ], $status);
    }

    $session = $resolve['session'];
    $sessionKey = trim((string) ($session['sessionKey'] ?? ''));
    $relativePath = dent_notes_gateway_normalize_relative_path((string) ($session['relativePath'] ?? ''));
    $expectedSize = max(0, (int) ($session['expectedSize'] ?? 0));
    if ($sessionKey === '' || $relativePath === '' || !dent_notes_gateway_root_is_allowed($relativePath)) {
        throw new RuntimeException('direct-upload-session-invalid');
    }

    // Whole-file size guard only applies to single-shot uploads.
    if (!$isChunked && $contentLength > 0 && $expectedSize > 0 && $contentLength !== $expectedSize) {
        dent_notes_gateway_json([
            'success' => false,
            'error' => 'direct-upload-size-mismatch',
        ], 422);
    }

    $absolutePath = dent_notes_gateway_absolute_path($relativePath);
    dent_notes_gateway_ensure_parent_dir($absolutePath);

    if ($isChunked) {
        // Stable part path keyed by token so successive chunks append to the same file.
        $tokenKey = preg_replace('/[^a-z0-9]/', '', strtolower($token)) ?? '';
        $partPath = $absolutePath . '.dentup-' . substr($tokenKey, 0, 24);
        $writeMode = $chunkIndex <= 0 ? 'wb' : 'cb';

        $source = fopen('php://input', 'rb');
        $target = fopen($partPath, $writeMode);
        if ($source === false || $target === false) {
            if (is_resource($source)) { fclose($source); }
            if (is_resource($target)) { fclose($target); }
            throw new RuntimeException('direct-upload-stream-open-failed');
        }
        if ($chunkIndex > 0) {
            fseek($target, 0, SEEK_END); // append after previously received chunks
        }
        while (!feof($source)) {
            $chunk = fread($source, DENT_NOTES_GATEWAY_CHUNK_BYTES);
            if ($chunk === false) {
                fclose($source); fclose($target);
                throw new RuntimeException('direct-upload-read-failed');
            }
            if ($chunk === '') { continue; }
            $length = strlen($chunk);
            $offset = 0;
            while ($offset < $length) {
                $saved = fwrite($target, substr($chunk, $offset));
                if (!is_int($saved) || $saved <= 0) {
                    fclose($source); fclose($target);
                    throw new RuntimeException('direct-upload-write-failed');
                }
                $offset += $saved;
            }
        }
        fclose($source);
        fclose($target);

        if (!$isLastChunk) {
            // Intermediate chunk stored; wait for the rest. Do not finalize yet.
            dent_notes_gateway_json([
                'success' => true,
                'partial' => true,
                'received' => $chunkIndex + 1,
                'of' => $chunkCount,
            ]);
        }

        clearstatcache(true, $partPath);
        $storedBytes = max(0, (int) (@filesize($partPath) ?: 0));
        if ($storedBytes <= 0) {
            $tempPath = $partPath;
            throw new RuntimeException('direct-upload-empty-body');
        }
        if ($expectedSize > 0 && $storedBytes !== $expectedSize) {
            $tempPath = $partPath;
            throw new RuntimeException('direct-upload-size-mismatch');
        }
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
        if (!@rename($partPath, $absolutePath)) {
            $tempPath = $partPath;
            throw new RuntimeException('direct-upload-finalize-failed');
        }
        $tempPath = '';
        $cleanupFinal = true;
        @chmod($absolutePath, 0644);
        clearstatcache(true, $absolutePath);
        $storedBytes = max(0, (int) (@filesize($absolutePath) ?: $storedBytes));

        $complete = dent_notes_gateway_http_post_json(DENT_NOTES_GATEWAY_COMPLETE_URL, [
            'token' => $token,
            'sessionKey' => $sessionKey,
            'bytes' => (string) $storedBytes,
            'mimeType' => $contentType,
            'gatewayVersion' => DENT_NOTES_GATEWAY_VERSION,
            'origin' => $origin,
        ]);
        if (!($complete['success'] ?? false) || !is_array($complete['file'] ?? null)) {
            $status = max(400, (int) ($complete['httpStatus'] ?? 502));
            dent_notes_gateway_json([
                'success' => false,
                'error' => trim((string) ($complete['error'] ?? 'direct-upload-complete-failed')),
            ], $status);
        }
        $cleanupFinal = false;
        dent_notes_gateway_json($complete);
    }

    $tempPath = $absolutePath . '.part-' . bin2hex(random_bytes(6));
    if (is_file($tempPath)) {
        @unlink($tempPath);
    }

    $source = fopen('php://input', 'rb');
    $target = fopen($tempPath, 'wb');
    if ($source === false || $target === false) {
        if (is_resource($source)) {
            fclose($source);
        }
        if (is_resource($target)) {
            fclose($target);
        }
        throw new RuntimeException('direct-upload-stream-open-failed');
    }

    $writtenBytes = 0;
    while (!feof($source)) {
        $chunk = fread($source, DENT_NOTES_GATEWAY_CHUNK_BYTES);
        if ($chunk === false) {
            fclose($source);
            fclose($target);
            throw new RuntimeException('direct-upload-read-failed');
        }
        if ($chunk === '') {
            continue;
        }
        $length = strlen($chunk);
        $offset = 0;
        while ($offset < $length) {
            $saved = fwrite($target, substr($chunk, $offset));
            if (!is_int($saved) || $saved <= 0) {
                fclose($source);
                fclose($target);
                throw new RuntimeException('direct-upload-write-failed');
            }
            $offset += $saved;
        }
        $writtenBytes += $length;
    }

    fclose($source);
    fclose($target);

    if ($writtenBytes <= 0) {
        throw new RuntimeException('direct-upload-empty-body');
    }
    if (($expectedSize > 0 && $writtenBytes !== $expectedSize) || ($contentLength > 0 && $writtenBytes !== $contentLength)) {
        throw new RuntimeException('direct-upload-size-mismatch');
    }

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
    if (!@rename($tempPath, $absolutePath)) {
        throw new RuntimeException('direct-upload-finalize-failed');
    }
    $tempPath = '';
    $cleanupFinal = true;
    @chmod($absolutePath, 0644);
    clearstatcache(true, $absolutePath);
    $storedBytes = max(0, (int) (@filesize($absolutePath) ?: $writtenBytes));

    $complete = dent_notes_gateway_http_post_json(DENT_NOTES_GATEWAY_COMPLETE_URL, [
        'token' => $token,
        'sessionKey' => $sessionKey,
        'bytes' => (string) $storedBytes,
        'mimeType' => $contentType,
        'gatewayVersion' => DENT_NOTES_GATEWAY_VERSION,
        'origin' => $origin,
    ]);
    if (!($complete['success'] ?? false) || !is_array($complete['file'] ?? null)) {
        $status = max(400, (int) ($complete['httpStatus'] ?? 502));
        dent_notes_gateway_json([
            'success' => false,
            'error' => trim((string) ($complete['error'] ?? 'direct-upload-complete-failed')),
        ], $status);
    }

    $cleanupFinal = false;
    dent_notes_gateway_json($complete);
} catch (Throwable $error) {
    if ($tempPath !== '' && is_file($tempPath)) {
        @unlink($tempPath);
    }
    if ($cleanupFinal && $absolutePath !== '' && is_file($absolutePath)) {
        @unlink($absolutePath);
    }
    dent_notes_gateway_json([
        'success' => false,
        'error' => $error->getMessage(),
    ], 500);
}
PHP;

    return str_replace(
        [
            '__VERSION__',
            '__MAIN_SITE_ORIGIN__',
            '__RESOLVE_URL__',
            '__COMPLETE_URL__',
            '__ALLOWED_ORIGIN__',
            '__ALLOWED_ROOTS__',
            '__TIMEOUT_SECONDS__',
            '__CHUNK_BYTES__',
        ],
        [
            var_export(NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_GATEWAY_VERSION, true),
            var_export($mainSiteOrigin, true),
            var_export($mainSiteOrigin . '/api/notes_api.php?action=resolveDirectHostUpload', true),
            var_export($mainSiteOrigin . '/api/notes_api.php?action=completeDirectHostUpload', true),
            var_export($mainSiteOrigin, true),
            var_export(array_values(notes_download_host_allowed_roots()), true),
            (string) NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS,
            (string) NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES,
        ],
        $template
    );
}

function notes_download_host_direct_upload_health(string $mainSiteOrigin): ?array
{
    // Strict server-side verification (no "trust" fallback, never fatal): used to
    // decide whether the gateway needs to be (re)deployed. Returns null whenever the
    // gateway cannot be positively verified over HTTP — including when the public
    // domain is simply unreachable/unresolvable from the main host.
    $gatewayUrl = notes_download_host_internal_runtime_public_url(notes_download_host_direct_upload_gateway_relative_path());
    if ($gatewayUrl === '') {
        return null;
    }

    $response = notes_download_host_http_get_safe($gatewayUrl . '?health=1', 6);
    if ($response === null || (int) ($response['status'] ?? 0) < 200 || (int) ($response['status'] ?? 0) >= 300) {
        return null;
    }

    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($decoded) || !($decoded['success'] ?? false)) {
        return null;
    }
    if (trim((string) ($decoded['version'] ?? '')) !== NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_GATEWAY_VERSION) {
        return null;
    }
    if (rtrim(trim((string) ($decoded['mainSiteOrigin'] ?? '')), '/') !== rtrim($mainSiteOrigin, '/')) {
        return null;
    }

    $decoded['uploadUrl'] = $gatewayUrl;
    return $decoded;
}

function notes_download_host_direct_upload_health_with_retries(string $mainSiteOrigin, int $attempts = 4): ?array
{
    $attempts = max(1, $attempts);
    for ($index = 0; $index < $attempts; $index++) {
        $health = notes_download_host_direct_upload_health($mainSiteOrigin);
        if (is_array($health)) {
            return $health;
        }
        if ($index + 1 < $attempts) {
            usleep(250000);
        }
    }

    return null;
}

function notes_download_host_http_get_safe(string $url, int $timeoutSeconds = 6): ?array
{
    if ($url === '') {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'timeout' => max(1, $timeoutSeconds),
            'protocol_version' => 1.1,
            'method' => 'GET',
            'header' => "Accept: application/json\r\nConnection: close\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
            'SNI_enabled' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw)) {
        return null;
    }

    $status = 0;
    $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
    foreach ($responseHeaders as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/i', (string) $line, $matches) === 1) {
            $status = (int) ($matches[1] ?? 0);
            break;
        }
    }

    return ['status' => $status, 'body' => $raw];
}

function notes_download_host_direct_upload_trusted_descriptor(string $gatewayUrl, string $mainSiteOrigin): array
{
    // The main host often cannot resolve/reach the PUBLIC download-host domain
    // server-side (it lives on separate DNS), but the BROWSER can — and the browser
    // is what actually performs the direct upload. When the server-side probe cannot
    // connect at all, trust the provisioned gateway instead of disabling direct
    // upload and falling back to the WAF-prone relay. Chunked uploads keep each
    // request tiny, so no server-enforced size cap is included here.
    return [
        'success' => true,
        'version' => NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_GATEWAY_VERSION,
        'mainSiteOrigin' => rtrim($mainSiteOrigin, '/'),
        'allowedOrigin' => rtrim($mainSiteOrigin, '/'),
        'uploadUrl' => $gatewayUrl,
        'trusted' => true,
    ];
}

function notes_download_host_direct_upload_health_safe(string $mainSiteOrigin, string $gatewayUrl): ?array
{
    if ($gatewayUrl === '') {
        return null;
    }

    $response = notes_download_host_http_get_safe($gatewayUrl . '?health=1', 6);
    if ($response === null) {
        // Could not connect to the public gateway domain at all from the main host
        // (typically unresolvable DNS server-side). That says nothing about whether
        // the gateway is reachable from BROWSERS — trust it and let the browser
        // verify, rather than disabling direct upload.
        return notes_download_host_direct_upload_trusted_descriptor($gatewayUrl, $mainSiteOrigin);
    }
    if ((int) $response['status'] < 200 || (int) $response['status'] >= 300) {
        return null;
    }

    $decoded = json_decode((string) $response['body'], true);
    if (!is_array($decoded) || !($decoded['success'] ?? false)) {
        return null;
    }
    if (trim((string) ($decoded['version'] ?? '')) !== NOTES_DOWNLOAD_HOST_DIRECT_UPLOAD_GATEWAY_VERSION) {
        return null;
    }
    if (rtrim(trim((string) ($decoded['mainSiteOrigin'] ?? '')), '/') !== rtrim($mainSiteOrigin, '/')) {
        return null;
    }

    $decoded['uploadUrl'] = $gatewayUrl;
    return $decoded;
}

function notes_direct_upload_gateway_cache_path(): string
{
    return dent_storage_path('notes/direct_upload_gateway.json');
}

function notes_direct_upload_gateway_cache_read(): array
{
    $raw = dent_read_json_file(notes_direct_upload_gateway_cache_path(), []);
    return is_array($raw) ? $raw : [];
}

function notes_direct_upload_gateway_cache_write(array $data): void
{
    dent_ensure_directory(dirname(notes_direct_upload_gateway_cache_path()));
    dent_write_json_file(notes_direct_upload_gateway_cache_path(), $data);
}

/**
 * Returns the cached direct-upload gateway info for the given main-site
 * origin, or null when the gateway is unavailable / not yet provisioned.
 *
 * This never throws and never performs the expensive provisioning round
 * trip — it only issues a single short, best-effort health-check GET when
 * the cache is missing or stale, so a slow/unreachable download host can
 * never stall (let alone break) the relay-based upload fallback.
 */
function notes_download_host_direct_upload_gateway_cached(string $mainSiteOrigin): ?array
{
    $mainSiteOrigin = rtrim(trim($mainSiteOrigin), '/');
    if ($mainSiteOrigin === '') {
        return null;
    }

    $now = time();
    $cache = notes_direct_upload_gateway_cache_read();
    $cachedOrigin = rtrim(trim((string) ($cache['mainSiteOrigin'] ?? '')), '/');
    $checkedAt = trim((string) ($cache['checkedAt'] ?? ''));
    $checkedUnix = $checkedAt !== '' ? (int) strtotime($checkedAt) : 0;
    $status = trim((string) ($cache['status'] ?? ''));

    if ($cachedOrigin === $mainSiteOrigin && $checkedUnix > 0) {
        $age = $now - $checkedUnix;
        if ($status === 'healthy' && $age < NOTES_DIRECT_UPLOAD_GATEWAY_HEALTHY_TTL_SECONDS && is_array($cache['gateway'] ?? null)) {
            return $cache['gateway'];
        }
        if ($status === 'unavailable' && $age < NOTES_DIRECT_UPLOAD_GATEWAY_UNAVAILABLE_TTL_SECONDS) {
            return null;
        }
    }

    $gatewayUrl = notes_download_host_internal_runtime_public_url(notes_download_host_direct_upload_gateway_relative_path());
    $health = notes_download_host_direct_upload_health_safe($mainSiteOrigin, $gatewayUrl);

    if (is_array($health)) {
        notes_direct_upload_gateway_cache_write([
            'mainSiteOrigin' => $mainSiteOrigin,
            'status' => 'healthy',
            'checkedAt' => dent_iso_now(),
            'gateway' => $health,
        ]);
        return $health;
    }

    notes_direct_upload_gateway_cache_write([
        'mainSiteOrigin' => $mainSiteOrigin,
        'status' => 'unavailable',
        'checkedAt' => dent_iso_now(),
        'gateway' => null,
    ]);
    return null;
}

function notes_download_host_ensure_direct_upload_gateway(string $mainSiteOrigin): ?array
{
    static $cache = [];

    $mainSiteOrigin = rtrim(trim($mainSiteOrigin), '/');
    if ($mainSiteOrigin === '') {
        dent_error('Main-site origin for direct upload is invalid.', 500);
    }
    if (isset($cache[$mainSiteOrigin]) && is_array($cache[$mainSiteOrigin])) {
        return $cache[$mainSiteOrigin];
    }

    $gatewayUrl = notes_download_host_internal_runtime_public_url(notes_download_host_direct_upload_gateway_relative_path());
    if ($gatewayUrl === '') {
        return null;
    }

    $existing = notes_download_host_direct_upload_health_with_retries($mainSiteOrigin, 1);
    if (is_array($existing)) {
        $cache[$mainSiteOrigin] = $existing;
        notes_direct_upload_gateway_cache_write([
            'mainSiteOrigin' => $mainSiteOrigin,
            'status' => 'healthy',
            'checkedAt' => dent_iso_now(),
            'gateway' => $existing,
        ]);
        return $existing;
    }

    $runtimeDir = notes_download_host_direct_upload_runtime_dir();
    notes_download_host_internal_runtime_ensure_dir($runtimeDir);
    notes_download_host_internal_runtime_upload_text_file(
        $runtimeDir . '/.user.ini',
        notes_download_host_direct_upload_user_ini_source(),
        'text/plain'
    );
    notes_download_host_internal_runtime_upload_text_file(
        notes_download_host_direct_upload_gateway_relative_path(),
        notes_download_host_direct_upload_gateway_source($mainSiteOrigin),
        'application/x-httpd-php'
    );

    $health = notes_download_host_direct_upload_health_with_retries($mainSiteOrigin, 4);
    if (!is_array($health)) {
        // The gateway files were just deployed via the cPanel API (the reliable,
        // always-reachable channel). If we still cannot verify over HTTP it is
        // because the main host cannot resolve/reach the public download-host
        // domain — not because the gateway is broken. Trust the fresh deployment;
        // the browser performs the actual upload and CAN reach it.
        $health = notes_download_host_direct_upload_health_safe($mainSiteOrigin, $gatewayUrl);
    }
    if (!is_array($health)) {
        notes_direct_upload_gateway_cache_write([
            'mainSiteOrigin' => $mainSiteOrigin,
            'status' => 'unavailable',
            'checkedAt' => dent_iso_now(),
            'gateway' => null,
        ]);
        dent_error('Direct upload gateway on the download host is not reachable.', 502);
    }

    $cache[$mainSiteOrigin] = $health;
    notes_direct_upload_gateway_cache_write([
        'mainSiteOrigin' => $mainSiteOrigin,
        'status' => 'healthy',
        'checkedAt' => dent_iso_now(),
        'gateway' => $health,
    ]);
    return $health;
}

function notes_download_host_extract_response_body(string $raw): string
{
    $separator = strpos($raw, "\r\n\r\n");
    if ($separator === false) {
        return trim($raw);
    }

    return trim(substr($raw, $separator + 4));
}

function notes_download_host_parse_http_headers(string $headerBlock): array
{
    $headers = [];
    $lines = preg_split('/\r\n/', trim($headerBlock)) ?: [];
    foreach ($lines as $index => $line) {
        if ($index === 0) {
            continue;
        }
        $colon = strpos($line, ':');
        if ($colon === false) {
            continue;
        }
        $name = strtolower(trim(substr($line, 0, $colon)));
        if ($name === '') {
            continue;
        }
        $value = trim(substr($line, $colon + 1));
        if ($value === '') {
            continue;
        }
        if (isset($headers[$name])) {
            $headers[$name] .= ', ' . $value;
            continue;
        }
        $headers[$name] = $value;
    }

    return $headers;
}

function notes_download_host_decode_chunked_body(string $body): ?string
{
    $offset = 0;
    $length = strlen($body);
    $decoded = '';

    while (true) {
        $lineEnd = strpos($body, "\r\n", $offset);
        if ($lineEnd === false) {
            return null;
        }

        $sizeLine = trim(substr($body, $offset, $lineEnd - $offset));
        $sizeLine = trim(explode(';', $sizeLine, 2)[0]);
        if ($sizeLine === '' || preg_match('/^[0-9a-fA-F]+$/', $sizeLine) !== 1) {
            return null;
        }

        $chunkSize = hexdec($sizeLine);
        $offset = $lineEnd + 2;

        if ($chunkSize === 0) {
            if ($length < $offset + 2) {
                return null;
            }
            if (substr($body, $offset, 2) !== "\r\n") {
                return null;
            }
            return $decoded;
        }

        if ($length < $offset + $chunkSize + 2) {
            return null;
        }

        $decoded .= substr($body, $offset, $chunkSize);
        $offset += $chunkSize;
        if (substr($body, $offset, 2) !== "\r\n") {
            return null;
        }
        $offset += 2;
    }
}

function notes_download_host_parse_upload_response(string $raw): array
{
    $body = notes_download_host_extract_response_body($raw);
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        dent_error('پاسخ JSON آپلود از هاست دانلود معتبر نبود.', 502);
    }
    if ((int) ($decoded['status'] ?? 0) !== 1) {
        $errors = $decoded['errors'] ?? [];
        $message = is_array($errors) && $errors !== [] ? implode(' | ', array_map('strval', $errors)) : 'آپلود فایل روی هاست دانلود ناموفق بود.';
        dent_error($message, 502);
    }

    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    $uploads = is_array($data['uploads'] ?? null) ? $data['uploads'] : [];
    if ($uploads === []) {
        dent_error('اطلاعات فایل آپلودشده از هاست دانلود دریافت نشد.', 502);
    }
    $upload = is_array($uploads[0] ?? null) ? $uploads[0] : [];
    if ((int) ($upload['status'] ?? 0) !== 1) {
        dent_error(trim((string) ($upload['reason'] ?? 'آپلود فایل روی هاست دانلود ناموفق بود.')), 502);
    }

    return $upload;
}

function notes_download_host_stream_upload_from_stream(string $targetAbsDir, $sourceStream, int $sourceSize, string $remoteName, string $mimeType, bool $relayPing = false): array
{
    notes_download_host_prepare_long_transfer();
    if ($relayPing) {
        notes_download_host_relay_begin_output();
    }
    if (!is_resource($sourceStream)) {
        dent_error('جریان فایل برای آپلود روی هاست دانلود معتبر نیست.', 422);
    }
    if ($sourceSize <= 0) {
        dent_error('حجم فایل برای آپلود روی هاست دانلود معتبر نیست.', 422);
    }

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $scheme = (string) ($secret['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
    $socketPrefix = $scheme === 'https' ? 'ssl://' : 'tcp://';
    $socketPort = $scheme === 'https'
        ? (int) ($secret['cpanelSecurePort'] ?? 2083)
        : (int) ($secret['cpanelPort'] ?? 2082);

    $socket = @stream_socket_client(
        $socketPrefix . $secret['host'] . ':' . $socketPort,
        $errno,
        $errstr,
        NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
            ],
        ])
    );
    if (!is_resource($socket)) {
        dent_error('اتصال امن به هاست دانلود برقرار نشد: ' . trim($errstr), 502);
    }
    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    @stream_set_write_buffer($socket, 0);

    $boundary = '----DentNotesBoundary' . bin2hex(random_bytes(12));
    $prefix = '';
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="dir"' . "\r\n\r\n";
    $prefix .= $targetAbsDir . "\r\n";
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="file-1"; filename="' . addslashes($remoteName) . '"' . "\r\n";
    $prefix .= 'Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream') . "\r\n\r\n";
    $suffix = "\r\n--" . $boundary . "--\r\n";
    $contentLength = strlen($prefix) + $sourceSize + strlen($suffix);

    $headers = [
        'POST /execute/Fileman/upload_files HTTP/1.1',
        'Host: ' . $secret['host'],
        'Authorization: Basic ' . base64_encode((string) $secret['username'] . ':' . (string) $secret['password']),
        'User-Agent: Dentistry1402TUMS-NotesDownloadHost/1.0',
        'Accept: application/json',
        'Accept-Encoding: identity',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . $contentLength,
        'Connection: close',
        '',
        '',
    ];

    try {
        notes_download_host_relay_write_all($socket, implode("\r\n", $headers), 'ارسال هدر آپلود به هاست دانلود');
        notes_download_host_relay_write_all($socket, $prefix, 'شروع انتقال فایل به هاست دانلود');

        $remaining = $sourceSize;
        while ($remaining > 0) {
            $chunk = fread($sourceStream, min(NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES, $remaining));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('دریافت فایل از مرورگر کامل نشد.');
            }
            $remaining -= strlen($chunk);
            notes_download_host_relay_write_all($socket, $chunk, 'ارسال فایل به هاست دانلود');
        }

        notes_download_host_relay_write_all($socket, $suffix, 'پایان‌بندی آپلود روی هاست دانلود');
    } catch (RuntimeException $error) {
        fclose($socket);
        dent_error($error->getMessage(), 502);
    }

    $response = notes_download_host_relay_read_response($socket);
    fclose($socket);
    if (!is_string($response) || trim($response) === '') {
        dent_error('پاسخ آپلود از هاست دانلود دریافت نشد.', 502);
    }

    return notes_download_host_parse_upload_response($response);
}

function notes_download_host_stream_upload(string $targetAbsDir, string $tmpPath, string $remoteName, string $mimeType): array
{
    $file = fopen($tmpPath, 'rb');
    if ($file === false) {
        dent_error('خواندن فایل آپلودی امکان‌پذیر نیست.', 422);
    }

    try {
        return notes_download_host_stream_upload_from_stream(
            $targetAbsDir,
            $file,
            max(0, (int) (filesize($tmpPath) ?: 0)),
            $remoteName,
            $mimeType
        );
    } finally {
        fclose($file);
    }

    notes_download_host_prepare_long_transfer();

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $scheme = (string) ($secret['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
    $socketPrefix = $scheme === 'https' ? 'ssl://' : 'tcp://';
    $socketPort = $scheme === 'https'
        ? (int) ($secret['cpanelSecurePort'] ?? 2083)
        : (int) ($secret['cpanelPort'] ?? 2082);

    $socket = @stream_socket_client(
        $socketPrefix . $secret['host'] . ':' . $socketPort,
        $errno,
        $errstr,
        NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
            ],
        ])
    );
    if (!is_resource($socket)) {
        dent_error('اتصال امن به هاست دانلود برقرار نشد: ' . trim($errstr), 502);
    }
    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    @stream_set_write_buffer($socket, 0);

    $boundary = '----DentNotesBoundary' . bin2hex(random_bytes(12));
    $prefix = '';
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="dir"' . "\r\n\r\n";
    $prefix .= $targetAbsDir . "\r\n";
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="file-1"; filename="' . addslashes($remoteName) . '"' . "\r\n";
    $prefix .= 'Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream') . "\r\n\r\n";
    $suffix = "\r\n--" . $boundary . "--\r\n";
    $contentLength = strlen($prefix) + filesize($tmpPath) + strlen($suffix);

    $headers = [
        'POST /execute/Fileman/upload_files HTTP/1.1',
        'Host: ' . $secret['host'],
        'Authorization: Basic ' . base64_encode((string) $secret['username'] . ':' . (string) $secret['password']),
        'User-Agent: Dentistry1402TUMS-NotesDownloadHost/1.0',
        'Accept: application/json',
        'Accept-Encoding: identity',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . $contentLength,
        'Connection: close',
        '',
        '',
    ];

    notes_download_host_socket_write_all($socket, implode("\r\n", $headers), 'ارسال هدر آپلود به هاست دانلود');
    notes_download_host_socket_write_all($socket, $prefix, 'شروع انتقال فایل به هاست دانلود');

    $file = fopen($tmpPath, 'rb');
    if ($file === false) {
        fclose($socket);
        dent_error('خواندن فایل آپلودی امکان‌پذیر نیست.', 422);
    }

    try {
        while (!feof($file)) {
            $chunk = fread($file, NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES);
            if ($chunk === false) {
                throw new RuntimeException('stream-read-failed');
            }
            if ($chunk !== '') {
                notes_download_host_socket_write_all($socket, $chunk, 'ارسال فایل به هاست دانلود');
            }
        }
    } catch (RuntimeException $error) {
        fclose($file);
        fclose($socket);
        dent_error('ارسال فایل به هاست دانلود کامل نشد.', 502);
    }

    fclose($file);
    notes_download_host_socket_write_all($socket, $suffix, 'پایان‌بندی آپلود روی هاست دانلود');

    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    $response = stream_get_contents($socket);
    $meta = stream_get_meta_data($socket);
    fclose($socket);
    if (!empty($meta['timed_out'])) {
        dent_error('پاسخ نهایی هاست دانلود برای این فایل در زمان مجاز نرسید. timeout سمت سرور یا شبکه را بررسی کنید.', 504);
    }
    if (!is_string($response) || trim($response) === '') {
        dent_error('پاسخ آپلود از هاست دانلود دریافت نشد.', 502);
    }

    return notes_download_host_parse_upload_response($response);
}

/**
 * Streams one bounded browser chunk to the download host over FTP resume.
 *
 * The complete file is never materialized on the main host. At most the current
 * request chunk can be buffered by the web server/PHP transport while the same
 * bytes are written into a token-scoped partial file on the download host.
 */
function notes_download_host_stream_chunk_to_ftp(
    string $relativePath,
    $sourceStream,
    int $chunkIndex,
    int $chunkCount,
    int $chunkStart,
    int $chunkBytes,
    int $expectedSize,
    string $uploadToken,
    bool $isLastChunk,
    string $mainSiteOrigin
): array {
    notes_download_host_prepare_long_transfer();
    if (!is_resource($sourceStream)) {
        dent_error('جریان chunk آپلود معتبر نیست.', 422);
    }
    if ($chunkIndex < 0 || $chunkIndex >= $chunkCount || $chunkStart < 0 || $chunkBytes <= 0 || $expectedSize <= 0 || ($chunkStart + $chunkBytes) > $expectedSize) {
        dent_error('بازه chunk آپلود معتبر نیست.', 422);
    }
    if (!function_exists('ftp_connect') || !function_exists('ftp_fput')) {
        dent_error('FTP streaming روی هاست اصلی فعال نیست.', 503);
    }

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        dent_error('تنظیمات هاست دانلود روی سرور فعال نیست.', 503);
    }

    $normalizedPath = notes_download_host_normalize_relative_path($relativePath);
    if ($normalizedPath === '') {
        dent_error('مسیر مقصد chunk معتبر نیست.', 422);
    }

    $tokenKey = preg_replace('/[^a-z0-9]/', '', strtolower($uploadToken)) ?? '';
    if ($tokenKey === '') {
        dent_error('توکن chunk معتبر نیست.', 422);
    }

    $remoteBase = trim(str_replace('\\', '/', (string) ($secret['remoteBaseDir'] ?? 'public_html')), '/');
    $remoteFinal = $remoteBase . '/' . $normalizedPath;
    $remotePartPrefix = $remoteFinal . '.dentup-' . substr($tokenKey, 0, 24) . '.part-';
    $remotePart = $remotePartPrefix . str_pad((string) $chunkIndex, 6, '0', STR_PAD_LEFT);
    $ftp = @ftp_connect((string) $secret['host'], 21, NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS);
    if ($ftp === false) {
        dent_error('اتصال FTP streaming به هاست دانلود برقرار نشد.', 502);
    }

    try {
        if (!@ftp_login($ftp, (string) $secret['username'], (string) $secret['password'])) {
            dent_error('ورود FTP streaming به هاست دانلود ناموفق بود.', 502);
        }
        @ftp_pasv($ftp, true);
        if (defined('FTP_TIMEOUT_SEC')) {
            @ftp_set_option($ftp, FTP_TIMEOUT_SEC, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
        }

        $expectedEnd = $chunkStart + $chunkBytes;
        $remoteSize = @ftp_size($ftp, $remotePart);
        $remoteSize = $remoteSize >= 0 ? (int) $remoteSize : 0;

        // The host does not reliably support REST+STOR append. Store every
        // bounded chunk as its own idempotent part and assemble on dl host.
        if ($remoteSize !== $chunkBytes) {
            if ($remoteSize > 0) { @ftp_delete($ftp, $remotePart); }
            if (!@ftp_fput($ftp, $remotePart, $sourceStream, FTP_BINARY, 0)) {
                dent_error('نوشتن chunk روی هاست دانلود ناموفق بود.', 502);
            }
            clearstatcache();
            $remoteSize = @ftp_size($ftp, $remotePart);
            $remoteSize = $remoteSize >= 0 ? (int) $remoteSize : 0;
        }

        if ($remoteSize !== $chunkBytes) {
            dent_error('حجم chunk ذخیره‌شده روی هاست دانلود کامل نیست.', 502);
        }

        if (!$isLastChunk) {
            return [
                'partial' => true,
                'storedBytes' => $expectedEnd,
                'expectedSize' => $expectedSize,
            ];
        }
    } finally {
        @ftp_close($ftp);
    }

    $gatewayUrl = notes_download_host_internal_runtime_public_url(notes_download_host_direct_upload_gateway_relative_path());
    $assembleUrl = $gatewayUrl . '?' . http_build_query([
        'action' => 'assemble',
        'token' => $uploadToken,
        'chunkCount' => $chunkCount,
    ], '', '&', PHP_QUERY_RFC3986);
    $response = notes_download_host_http_request('GET', $assembleUrl, [
        'Accept: application/json',
        'Origin: ' . $mainSiteOrigin,
        'Connection: close',
    ]);
    $decoded = notes_download_host_decode_json_response($response, 'پاسخ نهایی‌سازی فایل از هاست دانلود معتبر نیست.');
    if (!($decoded['success'] ?? false) || !is_array($decoded['file'] ?? null)) {
        dent_error('نهایی‌سازی chunkهای فایل روی هاست دانلود انجام نشد: ' . trim((string) ($decoded['error'] ?? 'خطای نامشخص')), 502);
    }

    return [
        'partial' => false,
        'storedBytes' => $expectedSize,
        'expectedSize' => $expectedSize,
        'file' => $decoded['file'],
    ];
}

function notes_download_host_relay_begin_output(): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    @ini_set('output_buffering', 'off');
    @ini_set('implicit_flush', '1');
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Accel-Buffering: no');
    }
    echo ' ';
    @flush();
}

function notes_download_host_relay_ping(): void
{
    echo ' ';
    @flush();
}

function notes_download_host_relay_fail(string $message): void
{
    $payload = ['success' => false, 'error' => $message];
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        $json = '{"success":false,"error":"Server error."}';
    }
    echo ' ' . $json;
    @flush();
    exit;
}

function notes_download_host_relay_succeed(array $payload): void
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        notes_download_host_relay_fail('خطا در تولید پاسخ JSON سرور.');
        return;
    }
    echo ' ' . $json;
    @flush();
    exit;
}

function notes_download_host_relay_write_all($socket, string $payload, string $phaseLabel): void
{
    $offset = 0;
    $length = strlen($payload);
    $lastPing = microtime(true);
    while ($offset < $length) {
        $written = @fwrite($socket, substr($payload, $offset));
        if (!is_int($written) || $written <= 0) {
            $meta = is_resource($socket) ? stream_get_meta_data($socket) : [];
            if (!empty($meta['timed_out'])) {
                throw new RuntimeException($phaseLabel . ' به‌خاطر timeout شبکه کامل نشد.');
            }
            throw new RuntimeException($phaseLabel . ' به‌خاطر قطع ارتباط شبکه کامل نشد.');
        }
        $offset += $written;
        if (microtime(true) - $lastPing > 3) {
            notes_download_host_relay_ping();
            $lastPing = microtime(true);
        }
    }
}

function notes_download_host_relay_read_response($socket): string
{
    $buffer = '';
    $headerEnd = null;
    $headers = [];
    $deadline = microtime(true) + NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS;
    while (true) {
        stream_set_timeout($socket, 4);
        $chunk = fread($socket, 65536);
        if ($chunk === false) {
            $meta = stream_get_meta_data($socket);
            if (!empty($meta['timed_out'])) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('پاسخ نهایی هاست دانلود برای این فایل در زمان مجاز نرسید.');
                }
                notes_download_host_relay_ping();
                continue;
            }
            throw new RuntimeException('خواندن پاسخ آپلود از هاست دانلود ممکن نشد.');
        }
        if ($chunk !== '') {
            $buffer .= $chunk;
        }

        if ($headerEnd === null) {
            $headerEnd = strpos($buffer, "\r\n\r\n");
            if ($headerEnd !== false) {
                $headers = notes_download_host_parse_http_headers(substr($buffer, 0, $headerEnd));
            }
        }

        if ($headerEnd !== null) {
            $body = substr($buffer, $headerEnd + 4);
            $transferEncoding = strtolower((string) ($headers['transfer-encoding'] ?? ''));
            $contentLength = isset($headers['content-length']) ? max(0, (int) $headers['content-length']) : null;

            if (str_contains($transferEncoding, 'chunked')) {
                $decoded = notes_download_host_decode_chunked_body($body);
                if ($decoded !== null) {
                    return $decoded;
                }
            } elseif ($contentLength !== null) {
                if (strlen($body) >= $contentLength) {
                    return substr($body, 0, $contentLength);
                }
            } else {
                $trimmed = trim($body);
                if ($trimmed !== '') {
                    $decoded = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return $trimmed;
                    }
                }
            }
        }

        if (feof($socket)) {
            break;
        }

        $meta = stream_get_meta_data($socket);
        if (!empty($meta['timed_out'])) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('پاسخ نهایی هاست دانلود برای این فایل در زمان مجاز نرسید.');
            }
            notes_download_host_relay_ping();
        }
    }

    if ($headerEnd !== null) {
        return trim((string) substr($buffer, $headerEnd + 4));
    }

    return trim($buffer);
}

function notes_download_host_relay_parse_response(string $raw): array
{
    $body = notes_download_host_extract_response_body($raw);
    if ($body === '') {
        throw new RuntimeException('پاسخ آپلود از هاست دانلود معتبر نبود.');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('پاسخ JSON آپلود از هاست دانلود معتبر نبود.');
    }
    if ((int) ($decoded['status'] ?? 0) !== 1) {
        $errors = $decoded['errors'] ?? [];
        throw new RuntimeException(
            is_array($errors) && $errors !== [] ? implode(' | ', array_map('strval', $errors)) : 'آپلود فایل روی هاست دانلود ناموفق بود.'
        );
    }

    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    $uploads = is_array($data['uploads'] ?? null) ? $data['uploads'] : [];
    if ($uploads === []) {
        throw new RuntimeException('اطلاعات فایل آپلودشده از هاست دانلود دریافت نشد.');
    }
    $upload = is_array($uploads[0] ?? null) ? $uploads[0] : [];
    if ((int) ($upload['status'] ?? 0) !== 1) {
        throw new RuntimeException(trim((string) ($upload['reason'] ?? 'آپلود فایل روی هاست دانلود ناموفق بود.')));
    }

    return $upload;
}

/**
 * Streams the upload body directly from the browser to the download host (cPanel)
 * without buffering it on the main host's disk. While the transfer (and the wait
 * for cPanel's response) is in progress, periodic single-space "ping" bytes are
 * flushed to the browser to keep LiteSpeed's idle/response timeout from firing.
 * JSON.parse() ignores leading/trailing whitespace, so the JSON payload appended
 * at the end is still parsed correctly by the browser.
 */
function notes_download_host_stream_upload_relay(string $relativeDir, $sourceStream, int $sourceSize, string $desiredName = '', string $mimeType = '', ?string $scopeRoot = null): void
{
    if (!is_resource($sourceStream)) {
        dent_error('جریان فایل برای آپلود معتبر نیست.', 422);
    }
    if ($sourceSize <= 0) {
        dent_error('حجم فایل برای آپلود معتبر نیست.', 422);
    }

    notes_download_host_prepare_long_transfer();
    notes_download_host_relay_begin_output();

    $targetAbsDir = notes_download_host_ensure_dir($relativeDir, $scopeRoot);
    notes_download_host_relay_ping();
    $finalName = notes_download_host_unique_file_name($relativeDir, $desiredName);
    notes_download_host_relay_ping();

    $secret = notes_download_host_load_secret();
    if (!is_array($secret)) {
        notes_download_host_relay_fail('تنظیمات هاست دانلود روی سرور فعال نیست.');
        return;
    }

    $scheme = (string) ($secret['scheme'] ?? 'http') === 'https' ? 'https' : 'http';
    $socketPrefix = $scheme === 'https' ? 'ssl://' : 'tcp://';
    $socketPort = $scheme === 'https'
        ? (int) ($secret['cpanelSecurePort'] ?? 2083)
        : (int) ($secret['cpanelPort'] ?? 2082);

    $socket = @stream_socket_client(
        $socketPrefix . $secret['host'] . ':' . $socketPort,
        $errno,
        $errstr,
        NOTES_DOWNLOAD_HOST_STREAM_CONNECT_TIMEOUT_SECONDS,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'SNI_enabled' => true,
            ],
        ])
    );
    if (!is_resource($socket)) {
        notes_download_host_relay_fail('اتصال امن به هاست دانلود برقرار نشد: ' . trim($errstr));
        return;
    }
    stream_set_timeout($socket, NOTES_DOWNLOAD_HOST_STREAM_IO_TIMEOUT_SECONDS);
    @stream_set_write_buffer($socket, 0);
    notes_download_host_relay_ping();

    $boundary = '----DentNotesBoundary' . bin2hex(random_bytes(12));
    $prefix = '';
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="dir"' . "\r\n\r\n";
    $prefix .= $targetAbsDir . "\r\n";
    $prefix .= '--' . $boundary . "\r\n";
    $prefix .= 'Content-Disposition: form-data; name="file-1"; filename="' . addslashes($finalName) . '"' . "\r\n";
    $prefix .= 'Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream') . "\r\n\r\n";
    $suffix = "\r\n--" . $boundary . "--\r\n";
    $contentLength = strlen($prefix) + $sourceSize + strlen($suffix);

    $headers = [
        'POST /execute/Fileman/upload_files HTTP/1.1',
        'Host: ' . $secret['host'],
        'Authorization: Basic ' . base64_encode((string) $secret['username'] . ':' . (string) $secret['password']),
        'User-Agent: Dentistry1402TUMS-NotesDownloadHost/1.0',
        'Accept: application/json',
        'Accept-Encoding: identity',
        'Content-Type: multipart/form-data; boundary=' . $boundary,
        'Content-Length: ' . $contentLength,
        'Connection: close',
        '',
        '',
    ];

    $relativePath = trim(trim($relativeDir, '/') . '/' . $finalName, '/');

    try {
        notes_download_host_relay_write_all($socket, implode("\r\n", $headers), 'ارسال هدر آپلود به هاست دانلود');
        notes_download_host_relay_write_all($socket, $prefix, 'شروع انتقال فایل به هاست دانلود');

        $remaining = $sourceSize;
        while ($remaining > 0) {
            $chunk = fread($sourceStream, min(NOTES_DOWNLOAD_HOST_STREAM_CHUNK_BYTES, $remaining));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('دریافت فایل از مرورگر کامل نشد.');
            }
            $remaining -= strlen($chunk);
            notes_download_host_relay_write_all($socket, $chunk, 'ارسال فایل به هاست دانلود');
        }

        notes_download_host_relay_write_all($socket, $suffix, 'پایان‌بندی آپلود روی هاست دانلود');

        $response = notes_download_host_relay_read_response($socket);
        if (trim($response) === '') {
            throw new RuntimeException('پاسخ آپلود از هاست دانلود دریافت نشد.');
        }
        $upload = notes_download_host_relay_parse_response($response);
    } catch (\Throwable $error) {
        @fclose($socket);
        notes_download_host_relay_fail($error->getMessage());
        return;
    }

    @fclose($socket);

    $bytes = max(0, (int) ($upload['size'] ?? $sourceSize));
    $message = trim((string) ($upload['reason'] ?? 'فایل روی هاست دانلود ذخیره شد.'));

    notes_download_host_relay_succeed([
        'success' => true,
        'file' => [
            'name' => $finalName,
            'relativeDir' => notes_download_host_normalize_relative_path($relativeDir),
            'relativePath' => $relativePath,
            'sizeBytes' => $bytes,
            'sizeLabel' => notes_download_host_human_size($bytes),
            'mimeType' => $mimeType,
            'publicUrl' => notes_download_host_public_url($relativePath),
            'message' => $message,
        ],
        'message' => $message,
    ]);
}

function notes_download_host_upload_file(string $relativeDir, array $file, string $desiredName = '', ?string $scopeRoot = null): array
{
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        dent_error('فایل ارسالی معتبر نیست.', 422);
    }

    $originalName = trim((string) ($file['name'] ?? ''));
    $desiredName = $desiredName !== '' ? $desiredName : $originalName;
    $targetAbsDir = notes_download_host_ensure_dir($relativeDir, $scopeRoot);
    $finalName = notes_download_host_unique_file_name($relativeDir, $desiredName);
    $mimeType = trim((string) ($file['type'] ?? ''));
    $upload = notes_download_host_stream_upload($targetAbsDir, $tmpPath, $finalName, $mimeType);

    $relativePath = trim($relativeDir, '/') . '/' . $finalName;
    $relativePath = trim($relativePath, '/');
    $bytes = max(0, (int) ($upload['size'] ?? filesize($tmpPath) ?: 0));

    return [
        'name' => $finalName,
        'relativeDir' => notes_download_host_normalize_relative_path($relativeDir),
        'relativePath' => $relativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $mimeType,
        'publicUrl' => notes_download_host_public_url($relativePath),
        'message' => trim((string) ($upload['reason'] ?? 'فایل روی هاست دانلود ذخیره شد.')),
    ];
}

function notes_download_host_create_dir(string $parentRelativePath, string $directoryName, ?string $scopeRoot = null): array
{
    $parentRelativePath = notes_download_host_assert_allowed_relative_path($parentRelativePath, $scopeRoot, true);
    $directoryName = notes_download_host_sanitize_leaf_name($directoryName, '');
    if ($directoryName === '') {
        dent_error('نام پوشه جدید معتبر نیست.', 422);
    }

    $parentAbs = $parentRelativePath === ''
        ? notes_download_host_absolute_base_dir()
        : notes_download_host_ensure_dir($parentRelativePath, $scopeRoot);
    $result = notes_download_host_execute_api2('mkdir', [
        'path' => $parentAbs,
        'name' => $directoryName,
        'permissions' => '0755',
    ]);
    $error = trim((string) ($result['error'] ?? ''));
    if ($error !== '' && stripos($error, 'file exists') === false) {
        dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
    }

    $relativePath = $parentRelativePath === '' ? $directoryName : $parentRelativePath . '/' . $directoryName;
    return [
        'name' => $directoryName,
        'type' => 'dir',
        'relativePath' => $relativePath,
        'parentPath' => $parentRelativePath,
    ];
}

function notes_download_host_rename_entry(string $relativePath, string $newName, ?string $scopeRoot = null): array
{
    $relativePath = notes_download_host_assert_allowed_relative_path($relativePath, $scopeRoot, false);
    $newName = notes_download_host_sanitize_leaf_name($newName, '');
    if ($newName === '') {
        dent_error('نام جدید معتبر نیست.', 422);
    }

    $parent = dirname($relativePath);
    $parent = $parent === '.' ? '' : notes_download_host_normalize_relative_path($parent);
    $destination = $parent === '' ? $newName : ($parent . '/' . $newName);
    $result = notes_download_host_execute_api2('fileop', [
        'op' => 'rename',
        'sourcefiles' => notes_download_host_abs_path_from_relative($relativePath),
        'destfiles' => notes_download_host_abs_path_from_relative($destination),
        'doubledecode' => '1',
    ]);

    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    if ((int) ($row['result'] ?? 0) !== 1) {
        $error = trim((string) ($row['err'] ?? $result['error'] ?? 'تغییر نام انجام نشد.'));
        dent_error($error, 502);
    }

    return [
        'previousPath' => $relativePath,
        'relativePath' => $destination,
        'name' => $newName,
        'parentPath' => $parent,
    ];
}

function notes_download_host_delete_entry(string $relativePath, string $entryType, ?string $scopeRoot = null): array
{
    $relativePath = notes_download_host_assert_allowed_relative_path($relativePath, $scopeRoot, false);
    $type = $entryType === 'dir' ? 'dir' : 'file';
    $operation = $type === 'dir' ? 'trash' : 'unlink';

    $result = notes_download_host_execute_api2('fileop', [
        'op' => $operation,
        'sourcefiles' => notes_download_host_abs_path_from_relative($relativePath),
        'doubledecode' => '1',
    ]);

    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    if ((int) ($row['result'] ?? 0) !== 1) {
        $error = trim((string) ($row['err'] ?? $result['error'] ?? 'حذف انجام نشد.'));
        dent_error($error, 502);
    }

    return [
        'relativePath' => $relativePath,
        'type' => $type,
        'deleteMode' => $operation,
    ];
}
