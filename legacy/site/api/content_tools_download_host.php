<?php
declare(strict_types=1);

require_once __DIR__ . '/notes_download_host.php';

function content_download_host_is_enabled(): bool
{
    return notes_download_host_is_enabled();
}

function content_download_host_public_base_url(): string
{
    return notes_download_host_public_base_url();
}

function content_download_host_normalize_relative_path(string $path): string
{
    return notes_download_host_normalize_relative_path($path);
}

function content_download_host_sanitize_leaf_name(string $name, string $fallback): string
{
    return notes_download_host_sanitize_leaf_name($name, $fallback);
}

function content_download_host_absolute_base_dir(): string
{
    return notes_download_host_absolute_base_dir();
}

function content_download_host_abs_path_from_relative(string $relativePath): string
{
    return notes_download_host_abs_path_from_relative($relativePath);
}

function content_download_host_relative_from_abs_path(string $absolutePath): string
{
    return notes_download_host_relative_from_abs_path($absolutePath);
}

function content_download_host_public_url(string $relativePath): string
{
    return notes_download_host_public_url($relativePath);
}

function content_download_host_breadcrumbs(string $relativePath): array
{
    $items = [[
        'label' => 'ریشه آپلودسنتر',
        'path' => '',
    ]];
    if ($relativePath === '') {
        return $items;
    }

    $current = '';
    foreach (explode('/', $relativePath) as $part) {
        $current = $current === '' ? $part : ($current . '/' . $part);
        $items[] = [
            'label' => $part,
            'path' => $current,
        ];
    }

    return $items;
}

function content_download_host_sort_entries(array &$entries): void
{
    notes_download_host_sort_entries($entries);
}

function content_download_host_entry_payload(array $item): array
{
    return notes_download_host_entry_payload($item);
}

function content_download_host_list_dir(string $relativePath): array
{
    $absoluteDir = $relativePath === ''
        ? content_download_host_absolute_base_dir()
        : content_download_host_abs_path_from_relative($relativePath);

    $response = notes_download_host_execute_uapi('Fileman/list_files', [
        'dir' => $absoluteDir,
        'include_mime' => '1',
    ]);
    $items = is_array($response['data'] ?? null) ? $response['data'] : [];
    $entries = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $entries[] = content_download_host_entry_payload($item);
    }
    content_download_host_sort_entries($entries);

    return $entries;
}

function content_download_host_browse(string $relativePath): array
{
    $normalized = content_download_host_normalize_relative_path($relativePath);

    return [
        'currentPath' => $normalized,
        'entries' => content_download_host_list_dir($normalized),
        'breadcrumbs' => content_download_host_breadcrumbs($normalized),
    ];
}

function content_download_host_stats_cache_path(): string
{
    return DENT_TMP_ROOT . DIRECTORY_SEPARATOR . 'content_tools' . DIRECTORY_SEPARATOR . 'download_host_stats.json';
}

function content_download_host_read_stats_cache(bool $allowStale = false, int $ttlSeconds = 120): ?array
{
    $path = content_download_host_stats_cache_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $cachedAt = max(0, (int) ($decoded['cachedAtUnix'] ?? 0));
    if (!$allowStale && $cachedAt > 0 && (time() - $cachedAt) > max(5, $ttlSeconds)) {
        return null;
    }

    return $decoded;
}

function content_download_host_write_stats_cache(array $stats): void
{
    $path = content_download_host_stats_cache_path();
    dent_ensure_directory(dirname($path));
    $json = json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json) || $json === '') {
        return;
    }

    @file_put_contents($path, $json);
}

function content_download_host_parse_numeric_value($value): ?int
{
    if (is_int($value) || is_float($value)) {
        return max(0, (int) round((float) $value));
    }

    $raw = trim((string) $value);
    if ($raw === '' || $raw === '∞' || $raw === '&infin;') {
        return null;
    }

    if (!is_numeric($raw)) {
        return null;
    }

    return max(0, (int) round((float) $raw));
}

function content_download_host_disk_usage_snapshot(): array
{
    $result = notes_download_host_execute_api2('getdiskinfo', []);
    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];

    $usedBytes = content_download_host_parse_numeric_value($row['spaceused'] ?? null);
    $limitBytes = content_download_host_parse_numeric_value($row['spacelimit'] ?? null);
    $remainingBytes = content_download_host_parse_numeric_value($row['spaceremain'] ?? null);
    $uploadRemainingBytes = content_download_host_parse_numeric_value($row['file_upload_remain'] ?? null);
    $inodeUsed = content_download_host_parse_numeric_value($row['filesused'] ?? null);
    $inodeLimit = content_download_host_parse_numeric_value($row['fileslimit'] ?? null);
    $usagePercent = null;
    if (($usedBytes ?? 0) > 0 && ($limitBytes ?? 0) > 0) {
        $usagePercent = round(min(100, ($usedBytes / $limitBytes) * 100), 1);
    }

    return [
        'usedBytes' => $usedBytes,
        'limitBytes' => $limitBytes,
        'remainingBytes' => $remainingBytes,
        'uploadRemainingBytes' => $uploadRemainingBytes,
        'inodeUsed' => $inodeUsed,
        'inodeLimit' => $inodeLimit,
        'usagePercent' => $usagePercent,
    ];
}

function content_download_host_tree_usage_snapshot(): array
{
    $queue = [''];
    $visited = [];
    $fileCount = 0;
    $directoryCount = 0;
    $totalBytes = 0;

    while ($queue !== []) {
        $relativePath = array_shift($queue);
        if (!is_string($relativePath) || isset($visited[$relativePath])) {
            continue;
        }
        $visited[$relativePath] = true;

        $entries = content_download_host_list_dir($relativePath);
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryType = trim(strtolower((string) ($entry['type'] ?? 'file')));
            if ($entryType === 'dir') {
                $directoryCount++;
                $childPath = content_download_host_normalize_relative_path((string) ($entry['relativePath'] ?? ''));
                if ($childPath !== '' && !isset($visited[$childPath])) {
                    $queue[] = $childPath;
                }
                continue;
            }

            $fileCount++;
            $totalBytes += max(0, (int) ($entry['sizeBytes'] ?? 0));
        }
    }

    return [
        'fileCount' => $fileCount,
        'directoryCount' => $directoryCount,
        'entryCount' => $fileCount + $directoryCount,
        'managedBytes' => $totalBytes,
        'scannedDirectories' => max(1, count($visited)),
    ];
}

function content_download_host_usage_summary(bool $forceRefresh = false, int $ttlSeconds = 120): array
{
    if (!content_download_host_is_enabled()) {
        return [
            'enabled' => false,
            'available' => false,
            'baseUrl' => '',
            'rootPath' => '',
        ];
    }

    if (!$forceRefresh) {
        $cached = content_download_host_read_stats_cache(false, $ttlSeconds);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $stale = content_download_host_read_stats_cache(true, PHP_INT_MAX);
    try {
        $snapshot = array_merge(
            [
                'enabled' => true,
                'available' => true,
                'baseUrl' => content_download_host_public_base_url(),
                'rootPath' => '',
                'generatedAt' => dent_iso_now(),
                'cachedAtUnix' => time(),
            ],
            content_download_host_disk_usage_snapshot(),
            content_download_host_tree_usage_snapshot()
        );
        content_download_host_write_stats_cache($snapshot);
        return $snapshot;
    } catch (Throwable $error) {
        if (is_array($stale)) {
            $stale['stale'] = true;
            $stale['refreshError'] = 'refresh_failed';
            return $stale;
        }
        throw $error;
    }
}

function content_download_host_ensure_dir(string $relativeDir): string
{
    $normalized = content_download_host_normalize_relative_path($relativeDir);
    if ($normalized === '') {
        return content_download_host_absolute_base_dir();
    }

    $currentRelative = '';
    $currentAbs = content_download_host_absolute_base_dir();
    foreach (explode('/', $normalized) as $part) {
        $part = content_download_host_sanitize_leaf_name($part, 'folder');
        $result = notes_download_host_execute_api2('mkdir', [
            'path' => $currentAbs,
            'name' => $part,
            'permissions' => '0755',
        ]);
        $error = trim((string) ($result['error'] ?? ''));
        if ($error !== '' && stripos($error, 'file exists') === false) {
            dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
        }
        $currentRelative = $currentRelative === '' ? $part : ($currentRelative . '/' . $part);
        $currentAbs = content_download_host_abs_path_from_relative($currentRelative);
    }

    return $currentAbs;
}

function content_download_host_file_names_in_dir(string $relativeDir): array
{
    $entries = content_download_host_list_dir(content_download_host_normalize_relative_path($relativeDir));
    $names = [];
    foreach ($entries as $entry) {
        if (($entry['type'] ?? '') !== 'file') {
            continue;
        }
        $names[dent_utf8_strtolower((string) ($entry['name'] ?? ''))] = true;
    }

    return $names;
}

function content_download_host_unique_file_name(string $relativeDir, string $name): string
{
    $name = content_download_host_sanitize_leaf_name($name, 'file');
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $baseName = $extension !== '' ? substr($name, 0, -1 * (strlen($extension) + 1)) : $name;
    $baseName = content_download_host_sanitize_leaf_name($baseName, 'file');
    $candidate = $extension !== '' ? ($baseName . '.' . $extension) : $baseName;

    $existing = content_download_host_file_names_in_dir($relativeDir);
    $index = 2;
    while (isset($existing[dent_utf8_strtolower($candidate)])) {
        $candidate = $extension !== ''
            ? ($baseName . ' (' . $index . ').' . $extension)
            : ($baseName . ' (' . $index . ')');
        $index++;
    }

    return $candidate;
}

function content_download_host_upload_file(string $relativeDir, array $file, string $desiredName = ''): array
{
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        dent_error('فایل ارسالی معتبر نیست.', 422);
    }

    $normalizedDir = content_download_host_normalize_relative_path($relativeDir);
    $desiredName = trim($desiredName) !== '' ? $desiredName : (string) ($file['name'] ?? 'file');
    $targetAbsDir = content_download_host_ensure_dir($normalizedDir);
    $finalName = content_download_host_unique_file_name($normalizedDir, $desiredName);
    $mimeType = trim((string) ($file['type'] ?? ''));
    $upload = notes_download_host_stream_upload($targetAbsDir, $tmpPath, $finalName, $mimeType);

    $finalRelativePath = trim(($normalizedDir === '' ? '' : ($normalizedDir . '/')) . $finalName, '/');
    $bytes = max(0, (int) ($upload['size'] ?? filesize($tmpPath) ?: 0));

    return [
        'name' => $finalName,
        'relativeDir' => $normalizedDir,
        'relativePath' => $finalRelativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $mimeType,
        'publicUrl' => content_download_host_public_url($finalRelativePath),
        'message' => trim((string) ($upload['reason'] ?? 'فایل روی هاست دانلود ذخیره شد.')),
    ];
}

function content_download_host_upload_stream(string $relativeDir, $sourceStream, int $sourceSize, string $desiredName = '', string $mimeType = ''): array
{
    if (!is_resource($sourceStream)) {
        dent_error('Upload stream is invalid.', 422);
    }
    if ($sourceSize <= 0) {
        dent_error('Upload size is invalid.', 422);
    }

    $normalizedDir = content_download_host_normalize_relative_path($relativeDir);
    $targetAbsDir = content_download_host_ensure_dir($normalizedDir);
    $finalName = content_download_host_unique_file_name($normalizedDir, $desiredName);
    $upload = notes_download_host_stream_upload_from_stream($targetAbsDir, $sourceStream, $sourceSize, $finalName, $mimeType, true);
    $finalRelativePath = trim(($normalizedDir === '' ? '' : ($normalizedDir . '/')) . $finalName, '/');
    $bytes = max(0, (int) ($upload['size'] ?? $sourceSize));

    return [
        'name' => $finalName,
        'relativeDir' => $normalizedDir,
        'relativePath' => $finalRelativePath,
        'sizeBytes' => $bytes,
        'sizeLabel' => notes_download_host_human_size($bytes),
        'mimeType' => $mimeType,
        'publicUrl' => content_download_host_public_url($finalRelativePath),
        'message' => trim((string) ($upload['reason'] ?? 'File was saved on the download host.')),
    ];
}

function content_download_host_create_dir(string $parentRelativePath, string $directoryName): array
{
    $parent = content_download_host_normalize_relative_path($parentRelativePath);
    $directoryName = content_download_host_sanitize_leaf_name($directoryName, '');
    if ($directoryName === '') {
        dent_error('نام پوشه جدید معتبر نیست.', 422);
    }

    $parentAbs = $parent === ''
        ? content_download_host_absolute_base_dir()
        : content_download_host_ensure_dir($parent);
    $result = notes_download_host_execute_api2('mkdir', [
        'path' => $parentAbs,
        'name' => $directoryName,
        'permissions' => '0755',
    ]);
    $error = trim((string) ($result['error'] ?? ''));
    if ($error !== '' && stripos($error, 'file exists') === false) {
        dent_error('ساخت پوشه روی هاست دانلود انجام نشد: ' . $error, 502);
    }

    $relativePath = $parent === '' ? $directoryName : ($parent . '/' . $directoryName);
    return [
        'name' => $directoryName,
        'type' => 'dir',
        'relativePath' => $relativePath,
        'parentPath' => $parent,
    ];
}

function content_download_host_rename_entry(string $relativePath, string $newName): array
{
    $relativePath = content_download_host_normalize_relative_path($relativePath);
    if ($relativePath === '') {
        dent_error('تغییر نام ریشه آپلودسنتر مجاز نیست.', 422);
    }
    $newName = content_download_host_sanitize_leaf_name($newName, '');
    if ($newName === '') {
        dent_error('نام جدید معتبر نیست.', 422);
    }

    $parent = dirname($relativePath);
    $parent = $parent === '.' ? '' : content_download_host_normalize_relative_path($parent);
    $destination = $parent === '' ? $newName : ($parent . '/' . $newName);
    $result = notes_download_host_execute_api2('fileop', [
        'op' => 'rename',
        'sourcefiles' => content_download_host_abs_path_from_relative($relativePath),
        'destfiles' => content_download_host_abs_path_from_relative($destination),
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

function content_download_host_delete_entry(string $relativePath, string $entryType, bool $ignoreMissing = false): array
{
    $relativePath = content_download_host_normalize_relative_path($relativePath);
    if ($relativePath === '') {
        dent_error('حذف ریشه آپلودسنتر مجاز نیست.', 422);
    }

    $type = $entryType === 'dir' ? 'dir' : 'file';
    $operation = $type === 'dir' ? 'trash' : 'unlink';
    $result = notes_download_host_execute_api2('fileop', [
        'op' => $operation,
        'sourcefiles' => content_download_host_abs_path_from_relative($relativePath),
        'doubledecode' => '1',
    ]);

    $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
    $row = is_array($rows[0] ?? null) ? $rows[0] : [];
    if ((int) ($row['result'] ?? 0) !== 1) {
        $error = trim((string) ($row['err'] ?? $result['error'] ?? 'حذف انجام نشد.'));
        $missing = stripos($error, 'No such file') !== false
            || stripos($error, 'No such file or directory') !== false
            || stripos($error, 'does not exist') !== false;
        if (!$ignoreMissing || !$missing) {
            dent_error($error, 502);
        }
    }

    return [
        'relativePath' => $relativePath,
        'type' => $type,
        'deleteMode' => $operation,
    ];
}

function content_download_host_proxy_preview(array $file): void
{
    $remoteUrl = trim((string) ($file['remotePublicUrl'] ?? ''));
    if ($remoteUrl === '') {
        dent_error('لینک مستقیم فایل روی هاست دانلود موجود نیست.', 404);
    }

    $mime = strtolower((string) ($file['mimeType'] ?? 'application/octet-stream'));
    $shouldProxy = str_starts_with($mime, 'text/') || $mime === 'application/json' || $mime === 'application/pdf';
    if (!$shouldProxy) {
        header('Location: ' . $remoteUrl, true, 302);
        exit;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 120,
            'ignore_errors' => true,
            'follow_location' => 1,
            'user_agent' => 'Dentistry1402TUMS-ContentTools/1.0',
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);
    $raw = @file_get_contents($remoteUrl, false, $context);
    if (!is_string($raw)) {
        dent_error('پیش‌نمایش فایل از هاست دانلود دریافت نشد.', 502);
    }

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: private, max-age=0, no-cache');
    header('Content-Type: ' . $mime . '; charset=UTF-8');
    echo $raw;
    exit;
}
