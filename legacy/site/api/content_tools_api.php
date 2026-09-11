<?php
declare(strict_types=1);

require_once __DIR__ . '/content_tools_store.php';
require_once __DIR__ . '/content_tools_download_host.php';

function content_api_require_method(array $methods): void
{
    if (!in_array(dent_request_method(), $methods, true)) {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
}

function content_api_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function content_api_bool($value): bool
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
}

function content_api_prepare_long_upload_request(): void
{
    @ignore_user_abort(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    @ini_set('max_execution_time', '0');
    @ini_set('default_socket_timeout', '14400');
    dent_release_session_lock();
}

function content_api_decode_upload_meta_header(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $decoded = dent_base64url_decode($raw);
    if ($decoded === '') {
        dent_error('Upload metadata is invalid.', 422);
    }

    $payload = json_decode($decoded, true);
    if (!is_array($payload)) {
        dent_error('Upload metadata is invalid.', 422);
    }

    return $payload;
}

function content_api_sort_records(array $records, string $sort): array
{
    $sort = trim(strtolower($sort));
    usort($records, static function (array $left, array $right) use ($sort): int {
        if ($sort === 'oldest') {
            return strcmp((string) ($left['createdAt'] ?? ''), (string) ($right['createdAt'] ?? ''));
        }
        if ($sort === 'size') {
            return (int) ($right['size'] ?? 0) <=> (int) ($left['size'] ?? 0);
        }
        if ($sort === 'downloads') {
            return (int) ($right['downloadCount'] ?? 0) <=> (int) ($left['downloadCount'] ?? 0);
        }
        if ($sort === 'views') {
            return (int) ($right['viewCount'] ?? 0) <=> (int) ($left['viewCount'] ?? 0);
        }
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });
    return $records;
}

function content_api_filter_files(array $files, array $params): array
{
    $query = dent_utf8_strtolower(dent_clean_text((string) ($params['query'] ?? ''), 120));
    $status = trim(strtolower((string) ($params['status'] ?? 'all')));
    $type = trim(strtolower((string) ($params['type'] ?? 'all')));
    $folder = dent_utf8_strtolower(dent_clean_text((string) ($params['folder'] ?? ''), 80));

    $filtered = [];
    foreach ($files as $file) {
        if (!is_array($file)) {
            continue;
        }
        $publicState = content_public_state($file);
        if ($status === '' || $status === 'all' || $status === 'available') {
            if ($publicState === 'deleted') {
                continue;
            }
        } elseif ($publicState !== $status && (string) ($file['status'] ?? '') !== $status) {
            continue;
        }
        if ($folder !== '' && dent_utf8_strtolower((string) ($file['folder'] ?? '')) !== $folder) {
            continue;
        }
        $mime = strtolower((string) ($file['mimeType'] ?? ''));
        if ($type !== '' && $type !== 'all') {
            $matchesType = ($type === 'image' && str_starts_with($mime, 'image/'))
                || ($type === 'video' && str_starts_with($mime, 'video/'))
                || ($type === 'audio' && str_starts_with($mime, 'audio/'))
                || ($type === 'pdf' && $mime === 'application/pdf')
                || ($type === 'text' && (str_starts_with($mime, 'text/') || $mime === 'application/json'))
                || ($type === 'other' && !str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/') && !str_starts_with($mime, 'audio/') && $mime !== 'application/pdf' && !str_starts_with($mime, 'text/'));
            if (!$matchesType) {
                continue;
            }
        }
        if ($query !== '') {
            $haystack = dent_utf8_strtolower(implode(' ', [
                (string) ($file['originalName'] ?? ''),
                (string) ($file['title'] ?? ''),
                (string) ($file['description'] ?? ''),
                (string) ($file['folder'] ?? ''),
                implode(' ', is_array($file['tags'] ?? null) ? $file['tags'] : []),
                (string) ($file['extension'] ?? ''),
                (string) ($file['mimeType'] ?? ''),
            ]));
            if (!str_contains($haystack, $query)) {
                continue;
            }
        }
        $filtered[] = $file;
    }
    return content_api_sort_records($filtered, (string) ($params['sort'] ?? 'newest'));
}

function content_api_filter_pastes(array $pastes, array $params): array
{
    $query = dent_utf8_strtolower(dent_clean_text((string) ($params['query'] ?? ''), 120));
    $status = trim(strtolower((string) ($params['status'] ?? 'all')));
    $language = content_clean_language((string) ($params['language'] ?? ''));
    if ($language === 'plain' && trim((string) ($params['language'] ?? '')) === '') {
        $language = '';
    }

    $filtered = [];
    foreach ($pastes as $paste) {
        if (!is_array($paste)) {
            continue;
        }
        $publicState = content_public_state($paste);
        if ($status === '' || $status === 'all' || $status === 'available') {
            if ($publicState === 'deleted') {
                continue;
            }
        } elseif ($publicState !== $status && (string) ($paste['status'] ?? '') !== $status) {
            continue;
        }
        if ($language !== '' && (string) ($paste['language'] ?? '') !== $language) {
            continue;
        }
        if ($query !== '') {
            $haystack = dent_utf8_strtolower(implode(' ', [
                (string) ($paste['title'] ?? ''),
                (string) ($paste['language'] ?? ''),
                (string) ($paste['body'] ?? ''),
            ]));
            if (!str_contains($haystack, $query)) {
                continue;
            }
        }
        $filtered[] = $paste;
    }
    return content_api_sort_records($filtered, (string) ($params['sort'] ?? 'newest'));
}

function content_api_paginate(array $records, array $params): array
{
    $page = max(1, (int) ($params['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($params['perPage'] ?? 20)));
    $total = count($records);
    $pages = max(1, (int) ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'items' => array_slice($records, ($page - 1) * $perPage, $perPage),
    ];
}

function content_api_unavailable_payload(string $kind, string $state = 'missing'): array
{
    $title = $kind === 'paste' ? 'Paste در دسترس نیست' : 'فایل در دسترس نیست';
    $message = 'لینک ممکن است حذف، منقضی یا غیرفعال شده باشد.';
    if ($state === 'password') {
        $title = 'رمز لازم است';
        $message = 'برای باز کردن این لینک، رمز تعریف‌شده را وارد کنید.';
    } elseif ($state === 'limited') {
        $message = 'سقف دانلود این فایل تکمیل شده است.';
    }
    return [
        'available' => false,
        'state' => $state,
        'title' => $title,
        'message' => $message,
    ];
}

function content_api_download_host_meta_payload(): array
{
    return [
        'enabled' => content_download_host_is_enabled(),
        'baseUrl' => content_download_host_public_base_url(),
    ];
}

function content_api_delete_local_file_blob(array $file): void
{
    if (content_file_is_remote($file)) {
        return;
    }
    $path = content_upload_file_path((string) ($file['storedName'] ?? ''));
    if ($path !== '' && is_file($path)) {
        @unlink($path);
    }
}

function content_api_sync_remote_file_path(array &$store, string $fromPath, string $toPath): int
{
    $fromPath = content_clean_remote_relative_path($fromPath);
    $toPath = content_clean_remote_relative_path($toPath);
    if ($fromPath === '' || $toPath === '' || $fromPath === $toPath) {
        return 0;
    }

    $changed = 0;
    foreach (($store['files'] ?? []) as $id => $file) {
        if (!is_array($file) || !content_file_has_remote_path($file, $fromPath)) {
            continue;
        }
        $store['files'][$id] = content_file_with_remote_path($file, $toPath);
        $changed++;
    }

    return $changed;
}

function content_api_sync_remote_directory_path(array &$store, string $fromDirectory, string $toDirectory): int
{
    $fromDirectory = content_clean_remote_relative_path($fromDirectory);
    $toDirectory = content_clean_remote_relative_path($toDirectory);
    if ($fromDirectory === '' || $toDirectory === '' || $fromDirectory === $toDirectory) {
        return 0;
    }

    $prefix = $fromDirectory . '/';
    $changed = 0;
    foreach (($store['files'] ?? []) as $id => $file) {
        if (!is_array($file) || !content_file_is_in_remote_directory($file, $fromDirectory)) {
            continue;
        }
        $currentPath = content_file_remote_path($file);
        $nextPath = $toDirectory . '/' . substr($currentPath, strlen($prefix));
        $store['files'][$id] = content_file_with_remote_path($file, $nextPath);
        $changed++;
    }

    return $changed;
}

function content_api_mark_remote_file_deleted(array &$store, string $relativePath, bool $purged = true): int
{
    $relativePath = content_clean_remote_relative_path($relativePath);
    if ($relativePath === '') {
        return 0;
    }

    $changed = 0;
    foreach (($store['files'] ?? []) as $id => $file) {
        if (!is_array($file) || !content_file_has_remote_path($file, $relativePath)) {
            continue;
        }
        $store['files'][$id] = content_file_mark_deleted($file, $purged);
        $changed++;
    }

    return $changed;
}

function content_api_mark_remote_directory_deleted(array &$store, string $relativeDirectory, bool $purged = true): int
{
    $relativeDirectory = content_clean_remote_relative_path($relativeDirectory);
    if ($relativeDirectory === '') {
        return 0;
    }

    $changed = 0;
    foreach (($store['files'] ?? []) as $id => $file) {
        if (!is_array($file) || !content_file_is_in_remote_directory($file, $relativeDirectory)) {
            continue;
        }
        $store['files'][$id] = content_file_mark_deleted($file, $purged);
        $changed++;
    }

    return $changed;
}

function content_api_emit_file_bytes(array $file, string $mode): void
{
    if (content_file_is_remote($file)) {
        $remoteUrl = trim((string) ($file['remotePublicUrl'] ?? ''));
        if ($remoteUrl === '' && ($path = content_file_remote_path($file)) !== '') {
            $remoteUrl = content_download_host_public_url($path);
        }
        if ($remoteUrl === '') {
            dent_error('لینک فایل روی هاست دانلود در دسترس نیست.', 404);
        }
        if ($mode === 'preview') {
            $file['remotePublicUrl'] = $remoteUrl;
            content_download_host_proxy_preview($file);
        }
        header('Location: ' . $remoteUrl, true, 302);
        exit;
    }
    $path = content_upload_file_path((string) ($file['storedName'] ?? ''));
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        dent_error('فایل در storage پیدا نشد.', 404);
    }
    $mime = (string) ($file['mimeType'] ?? 'application/octet-stream');
    $name = (string) ($file['originalName'] ?? 'download');
    $disposition = $mode === 'preview' && content_is_previewable_file($file) ? 'inline' : 'attachment';
    $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'download';
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header("Content-Disposition: {$disposition}; filename=\"" . addslashes($fallback) . "\"; filename*=UTF-8''" . rawurlencode($name));
    header('Cache-Control: private, max-age=0, no-cache');
    readfile($path);
    exit;
}

$action = dent_request_action();
if ($action === '' && dent_request_method() === 'POST' && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === [] && $_FILES === []) {
    dent_error('حجم درخواست از سقف فعلی PHP/هاست بیشتر است. سقف ابزار ' . CONTENT_MAX_UPLOAD_LABEL . ' تنظیم شده، اما ممکن است هاست هنوز مقدار جدید upload_max_filesize/post_max_size را اعمال نکرده باشد.', 413);
}

if ($action === 'ownerDashboard') {
    content_api_require_method(['GET']);
    dent_require_owner();
    $store = content_read_store();
    dent_json_response([
        'success' => true,
        'summary' => content_storage_summary($store),
        'recentFiles' => array_map(static fn(array $file): array => content_file_public_payload($file, true), array_slice(array_values($store['files']), 0, 8)),
        'recentPastes' => array_map(static fn(array $paste): array => content_paste_public_payload($paste, false, true), array_slice(array_values($store['pastes']), 0, 8)),
    ]);
}

if ($action === 'ownerUploadFiles') {
    content_api_require_method(['POST']);
    $owner = dent_require_owner();
    content_api_prepare_long_upload_request();
    $files = $_FILES['files'] ?? ($_FILES['file'] ?? null);
    if (!is_array($files)) {
        dent_error('فایلی برای آپلود انتخاب نشده است.', 422);
    }

    $normalizedFiles = [];
    if (is_array($files['name'] ?? null)) {
        foreach ($files['name'] as $index => $name) {
            $normalizedFiles[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
    } else {
        $normalizedFiles[] = $files;
    }
    if ($normalizedFiles === []) {
        dent_error('فایلی برای آپلود انتخاب نشده است.', 422);
    }

    $meta = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'tags' => $_POST['tags'] ?? '',
        'folder' => $_POST['folder'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'expiresAt' => $_POST['expiresAt'] ?? '',
        'password' => $_POST['password'] ?? '',
        'downloadLimit' => $_POST['downloadLimit'] ?? 0,
    ];
    $uploaded = [];
    try {
        $uploaded = content_with_store_lock(static function (array &$store) use ($normalizedFiles, $owner, $meta): array {
            $created = [];
            foreach ($normalizedFiles as $file) {
                if (!is_array($file)) {
                    continue;
                }
                $record = content_store_uploaded_file($file, $owner, $meta);
                $store['files'][(string) $record['id']] = $record;
                $created[] = $record;
            }
            return $created;
        });
    } catch (Throwable $error) {
        dent_error($error->getMessage(), 422);
    }
    if ($uploaded === []) {
        dent_error('هیچ فایلی ذخیره نشد.', 422);
    }
    content_audit_log('owner-upload-files', [
        'count' => count($uploaded),
        'by' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
    ]);
    dent_json_response([
        'success' => true,
        'files' => array_map(static fn(array $file): array => content_file_public_payload($file, true), $uploaded),
        'message' => count($uploaded) . ' فایل آپلود شد.',
    ]);
}

if ($action === 'ownerFiles') {
    content_api_require_method(['GET']);
    dent_require_owner();
    $store = content_read_store();
    $filtered = content_api_filter_files(array_values($store['files']), $_GET);
    $page = content_api_paginate($filtered, $_GET);
    $page['items'] = array_map(static fn(array $file): array => content_file_public_payload($file, true), $page['items']);
    dent_json_response([
        'success' => true,
        'summary' => content_storage_summary($store),
        'page' => $page,
    ]);
}

if ($action === 'ownerUpdateFile') {
    content_api_require_method(['POST']);
    dent_require_owner();
    $id = content_clean_id((string) ($_POST['id'] ?? ''), CONTENT_FILE_ID_PREFIX);
    if ($id === '') {
        dent_error('شناسه فایل معتبر نیست.', 422);
    }
    $updated = content_with_store_lock(static function (array &$store) use ($id): array {
        $file = $store['files'][$id] ?? null;
        if (!is_array($file)) {
            dent_error('فایل پیدا نشد.', 404);
        }
        $file['title'] = dent_clean_text((string) ($_POST['title'] ?? ($file['title'] ?? '')), 180);
        $file['description'] = dent_clean_text((string) ($_POST['description'] ?? ($file['description'] ?? '')), 1200);
        $file['tags'] = content_clean_tag_list($_POST['tags'] ?? ($file['tags'] ?? []));
        $file['folder'] = dent_clean_text((string) ($_POST['folder'] ?? ($file['folder'] ?? '')), 80);
        $file['status'] = content_clean_status((string) ($_POST['status'] ?? ($file['status'] ?? 'active')));
        $file['expiresAt'] = content_parse_expires_at($_POST['expiresAt'] ?? ($file['expiresAt'] ?? ''));
        $file['downloadLimit'] = content_normalize_download_limit($_POST['downloadLimit'] ?? ($file['downloadLimit'] ?? 0));
        if (array_key_exists('password', $_POST)) {
            $password = trim((string) $_POST['password']);
            if ($password !== '') {
                $file['passwordHash'] = content_hash_password($password);
            }
        }
        if (content_api_bool($_POST['clearPassword'] ?? false)) {
            $file['passwordHash'] = '';
        }
        $file['updatedAt'] = dent_iso_now();
        $store['files'][$id] = content_normalize_file_record($id, $file) ?? $file;
        return $store['files'][$id];
    });
    dent_json_response([
        'success' => true,
        'file' => content_file_public_payload($updated, true),
        'message' => 'فایل به‌روزرسانی شد.',
    ]);
}

if ($action === 'ownerBulkFiles') {
    content_api_require_method(['POST']);
    dent_require_owner();
    $operation = trim(strtolower((string) ($_POST['operation'] ?? '')));
    $idsRaw = $_POST['ids'] ?? [];
    if (is_string($idsRaw)) {
        $decoded = json_decode($idsRaw, true);
        $idsRaw = is_array($decoded) ? $decoded : preg_split('/[\s,،;]+/u', $idsRaw);
    }
    $ids = [];
    foreach (is_array($idsRaw) ? $idsRaw : [] as $id) {
        $clean = content_clean_id((string) $id, CONTENT_FILE_ID_PREFIX);
        if ($clean !== '') {
            $ids[$clean] = true;
        }
    }
    if ($ids === []) {
        dent_error('حداقل یک فایل را انتخاب کنید.', 422);
    }
    if (!in_array($operation, ['delete', 'hide', 'activate', 'purge'], true)) {
        dent_error('عملیات گروهی معتبر نیست.', 422);
    }
    $result = content_with_store_lock(static function (array &$store) use ($ids, $operation): array {
        $changed = 0;
        $purged = 0;
        foreach (array_keys($ids) as $id) {
            if (!is_array($store['files'][$id] ?? null)) {
                continue;
            }
            $file = $store['files'][$id];
            if ($operation === 'activate') {
                $file['status'] = 'active';
            } elseif ($operation === 'hide') {
                $file['status'] = 'hidden';
            } else {
                $file['status'] = 'deleted';
                $file['deletedAt'] = $file['deletedAt'] ?: dent_iso_now();
            }
            if ($operation === 'purge') {
                if (content_file_is_remote($file)) {
                    $relativePath = content_file_remote_path($file);
                    if ($relativePath !== '') {
                        content_download_host_delete_entry($relativePath, 'file', true);
                    }
                } else {
                    $path = content_upload_file_path((string) ($file['storedName'] ?? ''));
                    if ($path !== '' && is_file($path)) {
                        @unlink($path);
                    }
                }
                $file['purgedAt'] = dent_iso_now();
                $purged++;
            }
            $file['updatedAt'] = dent_iso_now();
            $store['files'][$id] = $file;
            $changed++;
        }
        return ['changed' => $changed, 'purged' => $purged];
    });
    content_audit_log('owner-bulk-files', ['operation' => $operation, 'count' => $result['changed']]);
    dent_json_response([
        'success' => true,
        'result' => $result,
        'message' => 'عملیات گروهی فایل‌ها انجام شد.',
    ]);
}

if ($action === 'ownerDownloadHostBrowse') {
    content_api_require_method(['GET']);
    dent_require_owner();
    if (!content_download_host_is_enabled()) {
        dent_error('هاست دانلود برای آپلودسنتر فعال نیست.', 503);
    }
    $path = content_download_host_normalize_relative_path((string) ($_GET['path'] ?? ''));
    $browse = content_download_host_browse($path);
    $store = content_read_store();
    dent_json_response([
        'success' => true,
        'downloadHost' => content_api_download_host_meta_payload(),
        'summary' => content_storage_summary($store),
        'browser' => $browse,
    ]);
}

if ($action === 'ownerDownloadHostSummary') {
    content_api_require_method(['GET']);
    dent_require_owner();
    if (!content_download_host_is_enabled()) {
        dent_error('هاست دانلود برای آپلودسنتر فعال نیست.', 503);
    }
    $store = content_read_store();
    dent_json_response([
        'success' => true,
        'downloadHost' => content_api_download_host_meta_payload(),
        'summary' => content_storage_summary($store, [
            'includeHostUsage' => true,
            'forceHostUsageRefresh' => content_api_bool($_GET['refresh'] ?? false),
        ]),
    ]);
}

if ($action === 'ownerDownloadHostUpload') {
    content_api_require_method(['POST']);
    $owner = dent_require_owner();
    content_api_prepare_long_upload_request();
    if (!content_download_host_is_enabled()) {
        dent_error('هاست دانلود برای آپلودسنتر فعال نیست.', 503);
    }

    $rawHeaderMeta = content_api_decode_upload_meta_header(notes_download_host_request_header('X-Dent-Upload-Meta'));
    if (!isset($_FILES['files']) && !isset($_FILES['file'])) {
        $contentLength = max(0, (int) notes_download_host_request_header('Content-Length'));
        if ($contentLength <= 0) {
            dent_error('No upload file was provided.', 422);
        }
        if ($contentLength > CONTENT_MAX_UPLOAD_BYTES) {
            dent_error('Each file must be at most ' . CONTENT_MAX_UPLOAD_LABEL . '.', 422);
        }

        $targetPath = content_download_host_normalize_relative_path((string) ($rawHeaderMeta['targetPath'] ?? ''));
        $meta = [
            'title' => $rawHeaderMeta['title'] ?? '',
            'description' => $rawHeaderMeta['description'] ?? '',
            'tags' => $rawHeaderMeta['tags'] ?? '',
            'folder' => $rawHeaderMeta['folder'] ?? '',
            'status' => $rawHeaderMeta['status'] ?? 'active',
            'expiresAt' => $rawHeaderMeta['expiresAt'] ?? '',
            'password' => $rawHeaderMeta['password'] ?? '',
            'downloadLimit' => $rawHeaderMeta['downloadLimit'] ?? 0,
        ];

        $originalName = dent_clean_text(
            (string) ($rawHeaderMeta['fileName'] ?? notes_download_host_decode_header_value(notes_download_host_request_header('X-Dent-Upload-Name'))),
            240
        );
        if ($originalName === '') {
            $originalName = 'file';
        }

        $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($originalName, PATHINFO_EXTENSION)) ?? '');
        if (!content_extension_allowed($extension)) {
            dent_error('This file extension is not allowed for upload.', 422);
        }

        $mime = strtolower(trim((string) strtok(notes_download_host_request_header('Content-Type'), ';')));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        if (!content_mime_allowed($mime)) {
            dent_error('This file type is not allowed for upload.', 422);
        }

        $preparedUploads = [];
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            dent_error('Upload stream could not be opened.', 422);
        }

        try {
            $upload = content_download_host_upload_stream($targetPath, $stream, $contentLength, $originalName, $mime);
            $upload['originalName'] = $originalName;
            $upload['mimeType'] = $mime;
            $upload['extension'] = $extension;
            $preparedUploads[] = $upload;

            $uploaded = content_with_store_lock(static function (array &$store) use ($preparedUploads, $owner, $meta): array {
                $created = [];
                foreach ($preparedUploads as $preparedUpload) {
                    if (!is_array($preparedUpload)) {
                        continue;
                    }
                    $record = content_build_download_host_file_record($preparedUpload, $owner, $meta);
                    $store['files'][(string) $record['id']] = $record;
                    $created[] = $record;
                }
                return $created;
            });
        } catch (Throwable $error) {
            foreach ($preparedUploads as $preparedUpload) {
                if (!is_array($preparedUpload)) {
                    continue;
                }
                $relativePath = content_clean_remote_relative_path($preparedUpload['relativePath'] ?? '');
                if ($relativePath === '') {
                    continue;
                }
                try {
                    content_download_host_delete_entry($relativePath, 'file', true);
                } catch (Throwable $_deleteError) {
                }
            }
            dent_error($error->getMessage(), 422);
        } finally {
            fclose($stream);
        }

        if (($uploaded ?? []) === []) {
            dent_error('No file was saved.', 422);
        }

        content_audit_log('owner-download-host-upload', [
            'count' => count($uploaded),
            'targetPath' => $targetPath,
            'by' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
        ]);
        dent_json_response([
            'success' => true,
            'downloadHost' => content_api_download_host_meta_payload(),
            'targetPath' => $targetPath,
            'files' => array_map(static fn(array $file): array => content_file_public_payload($file, true), $uploaded),
            'message' => count($uploaded) . ' file(s) saved on the download host.',
        ]);
    }

    $files = $_FILES['files'] ?? ($_FILES['file'] ?? null);
    if (!is_array($files)) {
        dent_error('فایلی برای آپلود انتخاب نشده است.', 422);
    }

    $normalizedFiles = [];
    if (is_array($files['name'] ?? null)) {
        foreach ($files['name'] as $index => $name) {
            $normalizedFiles[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }
    } else {
        $normalizedFiles[] = $files;
    }
    if ($normalizedFiles === []) {
        dent_error('فایلی برای آپلود انتخاب نشده است.', 422);
    }

    $targetPath = content_download_host_normalize_relative_path((string) ($_POST['targetPath'] ?? ''));
    $meta = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'tags' => $_POST['tags'] ?? '',
        'folder' => $_POST['folder'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'expiresAt' => $_POST['expiresAt'] ?? '',
        'password' => $_POST['password'] ?? '',
        'downloadLimit' => $_POST['downloadLimit'] ?? 0,
    ];

    $preparedUploads = [];
    try {
        foreach ($normalizedFiles as $file) {
            if (!is_array($file)) {
                continue;
            }
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

            $upload = content_download_host_upload_file($targetPath, [
                'tmp_name' => $tmpName,
                'name' => $originalName,
                'type' => $mime,
            ], $originalName);
            $upload['originalName'] = $originalName;
            $upload['mimeType'] = $mime;
            $upload['extension'] = $extension;
            $preparedUploads[] = $upload;
        }

        $uploaded = content_with_store_lock(static function (array &$store) use ($preparedUploads, $owner, $meta): array {
            $created = [];
            foreach ($preparedUploads as $upload) {
                if (!is_array($upload)) {
                    continue;
                }
                $record = content_build_download_host_file_record($upload, $owner, $meta);
                $store['files'][(string) $record['id']] = $record;
                $created[] = $record;
            }
            return $created;
        });
    } catch (Throwable $error) {
        foreach ($preparedUploads as $upload) {
            if (!is_array($upload)) {
                continue;
            }
            $relativePath = content_clean_remote_relative_path($upload['relativePath'] ?? '');
            if ($relativePath === '') {
                continue;
            }
            try {
                content_download_host_delete_entry($relativePath, 'file', true);
            } catch (Throwable $_deleteError) {
            }
        }
        dent_error($error->getMessage(), 422);
    }

    if (($uploaded ?? []) === []) {
        dent_error('هیچ فایلی ذخیره نشد.', 422);
    }

    content_audit_log('owner-download-host-upload', [
        'count' => count($uploaded),
        'targetPath' => $targetPath,
        'by' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
    ]);
    dent_json_response([
        'success' => true,
        'downloadHost' => content_api_download_host_meta_payload(),
        'targetPath' => $targetPath,
        'files' => array_map(static fn(array $file): array => content_file_public_payload($file, true), $uploaded),
        'message' => count($uploaded) . ' فایل روی هاست دانلود ذخیره شد.',
    ]);
}

// Register a file that the BROWSER already uploaded straight to the download host
// (via the shared direct-upload gateway) as an upload-center record — so large
// files never have to be relayed through this (site) host. The bytes never touch
// this server; only the metadata is stored here.
if ($action === 'ownerRegisterDownloadHostFile') {
    content_api_require_method(['POST']);
    $owner = dent_require_owner();
    dent_release_session_lock();
    if (!content_download_host_is_enabled()) {
        dent_error('هاست دانلود برای آپلودسنتر فعال نیست.', 503);
    }

    $relativePath = content_clean_remote_relative_path((string) ($_POST['relativePath'] ?? ''));
    if ($relativePath === '') {
        dent_error('مسیر فایل آپلودشده روی هاست دانلود معتبر نیست.', 422);
    }

    $originalName = dent_clean_text((string) ($_POST['fileName'] ?? ''), 240);
    if ($originalName === '') {
        $originalName = basename($relativePath);
    }

    $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', pathinfo($originalName, PATHINFO_EXTENSION)) ?? '');
    if (!content_extension_allowed($extension)) {
        dent_error('This file extension is not allowed for upload.', 422);
    }

    $mime = strtolower(trim((string) strtok((string) ($_POST['mimeType'] ?? ''), ';')));
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }
    if (!content_mime_allowed($mime)) {
        dent_error('This file type is not allowed for upload.', 422);
    }

    $size = max(0, (int) ($_POST['bytes'] ?? ($_POST['size'] ?? 0)));

    $meta = [
        'title' => (string) ($_POST['title'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'tags' => (string) ($_POST['tags'] ?? ''),
        'folder' => (string) ($_POST['folder'] ?? ''),
        'status' => (string) ($_POST['status'] ?? 'active'),
        'expiresAt' => (string) ($_POST['expiresAt'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
        'downloadLimit' => $_POST['downloadLimit'] ?? 0,
    ];

    $upload = [
        'relativePath' => $relativePath,
        'originalName' => $originalName,
        'mimeType' => $mime,
        'extension' => $extension,
        'sizeBytes' => $size,
    ];

    $record = content_with_store_lock(static function (array &$store) use ($upload, $owner, $meta): array {
        $built = content_build_download_host_file_record($upload, $owner, $meta);
        $store['files'][(string) $built['id']] = $built;
        return $built;
    });

    content_audit_log('owner-download-host-register', [
        'targetPath' => $relativePath,
        'by' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
    ]);

    dent_json_response([
        'success' => true,
        'downloadHost' => content_api_download_host_meta_payload(),
        'files' => [content_file_public_payload($record, true)],
        'message' => 'فایل روی هاست دانلود ثبت شد.',
    ]);
}

if ($action === 'ownerDownloadHostCreateDir') {
    content_api_require_method(['POST']);
    dent_require_owner();
    if (!content_download_host_is_enabled()) {
        dent_error('هاست دانلود برای آپلودسنتر فعال نیست.', 503);
    }
    $parentPath = content_download_host_normalize_relative_path((string) ($_POST['parentPath'] ?? ''));
    $name = (string) ($_POST['name'] ?? '');
    $entry = content_download_host_create_dir($parentPath, $name);
    content_audit_log('owner-download-host-create-dir', ['path' => $entry['relativePath'] ?? '']);
    dent_json_response([
        'success' => true,
        'downloadHost' => content_api_download_host_meta_payload(),
        'entry' => $entry,
        'message' => 'پوشه جدید ساخته شد.',
    ]);
}

if ($action === 'ownerDownloadHostRenameEntry') {
    content_api_require_method(['POST']);
    dent_require_owner();
    if (!content_download_host_is_enabled()) {
        dent_error('هاست دانلود برای آپلودسنتر فعال نیست.', 503);
    }
    $path = content_download_host_normalize_relative_path((string) ($_POST['path'] ?? ''));
    $type = trim(strtolower((string) ($_POST['type'] ?? 'file'))) === 'dir' ? 'dir' : 'file';
    $newName = (string) ($_POST['newName'] ?? '');
    $renamed = content_download_host_rename_entry($path, $newName);
    $syncedLinks = content_with_store_lock(static function (array &$store) use ($type, $renamed): int {
        if ($type === 'dir') {
            return content_api_sync_remote_directory_path($store, (string) ($renamed['previousPath'] ?? ''), (string) ($renamed['relativePath'] ?? ''));
        }
        return content_api_sync_remote_file_path($store, (string) ($renamed['previousPath'] ?? ''), (string) ($renamed['relativePath'] ?? ''));
    });
    content_audit_log('owner-download-host-rename-entry', [
        'type' => $type,
        'from' => $renamed['previousPath'] ?? '',
        'to' => $renamed['relativePath'] ?? '',
        'syncedLinks' => $syncedLinks,
    ]);
    dent_json_response([
        'success' => true,
        'downloadHost' => content_api_download_host_meta_payload(),
        'entry' => $renamed,
        'syncedLinks' => $syncedLinks,
        'message' => 'نام فایل یا پوشه به‌روزرسانی شد.',
    ]);
}

if ($action === 'ownerDownloadHostDeleteEntry') {
    content_api_require_method(['POST']);
    dent_require_owner();
    if (!content_download_host_is_enabled()) {
        dent_error('هاست دانلود برای آپلودسنتر فعال نیست.', 503);
    }
    $path = content_download_host_normalize_relative_path((string) ($_POST['path'] ?? ''));
    $type = trim(strtolower((string) ($_POST['type'] ?? 'file'))) === 'dir' ? 'dir' : 'file';
    $deleted = content_download_host_delete_entry($path, $type, true);
    $syncedLinks = content_with_store_lock(static function (array &$store) use ($type, $path): int {
        if ($type === 'dir') {
            return content_api_mark_remote_directory_deleted($store, $path, true);
        }
        return content_api_mark_remote_file_deleted($store, $path, true);
    });
    content_audit_log('owner-download-host-delete-entry', [
        'type' => $type,
        'path' => $path,
        'syncedLinks' => $syncedLinks,
    ]);
    dent_json_response([
        'success' => true,
        'downloadHost' => content_api_download_host_meta_payload(),
        'entry' => $deleted,
        'syncedLinks' => $syncedLinks,
        'message' => 'ورودی انتخابی حذف شد.',
    ]);
}

if ($action === 'publicFile') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? $_GET['password'] ?? '');
    $store = content_read_store();
    $file = content_find_file_by_token($store, $token);
    if (!is_array($file)) {
        dent_json_response(['success' => true, 'file' => null, 'unavailable' => content_api_unavailable_payload('file', 'missing')]);
    }
    $state = content_public_state($file);
    if ($state !== 'active' || !content_file_can_download($file)) {
        $unavailableState = $state !== 'active' ? $state : 'limited';
        dent_json_response(['success' => true, 'file' => content_file_public_payload($file), 'unavailable' => content_api_unavailable_payload('file', $unavailableState)]);
    }
    if ((string) ($file['passwordHash'] ?? '') !== '' && !content_verify_record_password($file, $password)) {
        dent_json_response(['success' => true, 'file' => content_file_public_payload($file), 'unavailable' => content_api_unavailable_payload('file', 'password')]);
    }
    dent_json_response([
        'success' => true,
        'file' => content_file_public_payload($file),
        'previewUrl' => content_is_previewable_file($file) ? content_absolute_url('/api/content_tools_api.php?action=previewFile&token=' . rawurlencode($token)) : '',
    ]);
}

if ($action === 'previewFile' || $action === 'downloadFile') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_GET['password'] ?? $_POST['password'] ?? '');
    $file = null;
    $mode = $action === 'previewFile' ? 'preview' : 'download';
    $file = content_with_store_lock(static function (array &$store) use ($token, $password, $mode): array {
        foreach ($store['files'] as $id => $candidate) {
            if (!is_array($candidate) || (string) ($candidate['token'] ?? '') !== $token) {
                continue;
            }
            if (!content_file_can_download($candidate)) {
                dent_error('این لینک فایل در دسترس نیست.', 404);
            }
            if ((string) ($candidate['passwordHash'] ?? '') !== '' && !content_verify_record_password($candidate, $password)) {
                dent_error('رمز لینک فایل معتبر نیست.', 403);
            }
            if ($mode === 'download') {
                $candidate['downloadCount'] = max(0, (int) ($candidate['downloadCount'] ?? 0)) + 1;
                $candidate['lastDownloadedAt'] = dent_iso_now();
                $store['files'][$id] = $candidate;
            }
            return $candidate;
        }
        dent_error('فایل پیدا نشد.', 404);
    });
    content_api_emit_file_bytes($file, $mode);
}

if ($action === 'ownerPastes') {
    content_api_require_method(['GET']);
    dent_require_owner();
    $store = content_read_store();
    $filtered = content_api_filter_pastes(array_values($store['pastes']), $_GET);
    $page = content_api_paginate($filtered, $_GET);
    $includeBody = content_api_bool($_GET['includeBody'] ?? false);
    $page['items'] = array_map(static fn(array $paste): array => content_paste_public_payload($paste, $includeBody, true), $page['items']);
    dent_json_response([
        'success' => true,
        'summary' => content_storage_summary($store),
        'page' => $page,
    ]);
}

if ($action === 'ownerSavePaste') {
    content_api_require_method(['POST']);
    $owner = dent_require_owner();
    $id = content_clean_id((string) ($_POST['id'] ?? ''), CONTENT_PASTE_ID_PREFIX);
    $body = dent_force_utf8((string) ($_POST['body'] ?? ''));
    if (trim($body) === '') {
        dent_error('متن paste خالی است.', 422);
    }
    if (dent_utf8_strlen($body) > CONTENT_MAX_PASTE_CHARS) {
        dent_error('متن paste بیش از حد مجاز است.', 422);
    }
    $saved = content_with_store_lock(static function (array &$store) use ($id, $body, $owner): array {
        $now = dent_iso_now();
        if ($id !== '' && is_array($store['pastes'][$id] ?? null)) {
            $paste = $store['pastes'][$id];
        } else {
            $paste = [
                'id' => content_next_id(CONTENT_PASTE_ID_PREFIX),
                'token' => content_random_token(10),
                'createdAt' => $now,
                'createdBy' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? '')),
                'viewCount' => 0,
                'rawViewCount' => 0,
                'lastViewedAt' => '',
                'deletedAt' => '',
            ];
        }
        $paste['title'] = dent_clean_text((string) ($_POST['title'] ?? ($paste['title'] ?? 'Paste')), 180) ?: 'Paste';
        $paste['body'] = $body;
        $paste['language'] = content_clean_language((string) ($_POST['language'] ?? ($paste['language'] ?? 'plain')));
        $paste['status'] = content_clean_status((string) ($_POST['status'] ?? ($paste['status'] ?? 'active')));
        $paste['expiresAt'] = content_parse_expires_at($_POST['expiresAt'] ?? ($paste['expiresAt'] ?? ''));
        if (array_key_exists('password', $_POST)) {
            $password = trim((string) $_POST['password']);
            if ($password !== '') {
                $paste['passwordHash'] = content_hash_password($password);
            }
        }
        if (content_api_bool($_POST['clearPassword'] ?? false)) {
            $paste['passwordHash'] = '';
        }
        $paste['updatedAt'] = $now;
        $normalized = content_normalize_paste_record((string) ($paste['id'] ?? ''), $paste);
        if ($normalized === null) {
            dent_error('رکورد paste معتبر نیست.', 422);
        }
        $store['pastes'][(string) $normalized['id']] = $normalized;
        return $normalized;
    });
    content_audit_log('owner-save-paste', ['id' => $saved['id'], 'by' => dent_normalize_student_number((string) ($owner['studentNumber'] ?? ''))]);
    dent_json_response([
        'success' => true,
        'paste' => content_paste_public_payload($saved, true, true),
        'message' => $id !== '' ? 'Paste به‌روزرسانی شد.' : 'Paste ساخته شد.',
    ]);
}

if ($action === 'ownerBulkPastes') {
    content_api_require_method(['POST']);
    dent_require_owner();
    $operation = trim(strtolower((string) ($_POST['operation'] ?? '')));
    $idsRaw = $_POST['ids'] ?? [];
    if (is_string($idsRaw)) {
        $decoded = json_decode($idsRaw, true);
        $idsRaw = is_array($decoded) ? $decoded : preg_split('/[\s,،;]+/u', $idsRaw);
    }
    $ids = [];
    foreach (is_array($idsRaw) ? $idsRaw : [] as $id) {
        $clean = content_clean_id((string) $id, CONTENT_PASTE_ID_PREFIX);
        if ($clean !== '') {
            $ids[$clean] = true;
        }
    }
    if ($ids === []) {
        dent_error('حداقل یک paste را انتخاب کنید.', 422);
    }
    if (!in_array($operation, ['delete', 'hide', 'activate'], true)) {
        dent_error('عملیات گروهی معتبر نیست.', 422);
    }
    $changed = content_with_store_lock(static function (array &$store) use ($ids, $operation): int {
        $count = 0;
        foreach (array_keys($ids) as $id) {
            if (!is_array($store['pastes'][$id] ?? null)) {
                continue;
            }
            $paste = $store['pastes'][$id];
            if ($operation === 'activate') {
                $paste['status'] = 'active';
            } elseif ($operation === 'hide') {
                $paste['status'] = 'hidden';
            } else {
                $paste['status'] = 'deleted';
                $paste['deletedAt'] = $paste['deletedAt'] ?: dent_iso_now();
            }
            $paste['updatedAt'] = dent_iso_now();
            $store['pastes'][$id] = $paste;
            $count++;
        }
        return $count;
    });
    content_audit_log('owner-bulk-pastes', ['operation' => $operation, 'count' => $changed]);
    dent_json_response([
        'success' => true,
        'changed' => $changed,
        'message' => 'عملیات گروهی pasteها انجام شد.',
    ]);
}

if ($action === 'publicPaste') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? $_GET['password'] ?? '');
    $paste = content_with_store_lock(static function (array &$store) use ($token, $password): ?array {
        foreach ($store['pastes'] as $id => $candidate) {
            if (!is_array($candidate) || (string) ($candidate['token'] ?? '') !== $token) {
                continue;
            }
            if (content_public_state($candidate) !== 'active') {
                return $candidate;
            }
            if ((string) ($candidate['passwordHash'] ?? '') !== '' && !content_verify_record_password($candidate, $password)) {
                return $candidate;
            }
            $candidate['viewCount'] = max(0, (int) ($candidate['viewCount'] ?? 0)) + 1;
            $candidate['lastViewedAt'] = dent_iso_now();
            $store['pastes'][$id] = $candidate;
            return $candidate;
        }
        return null;
    });
    if (!is_array($paste)) {
        dent_json_response(['success' => true, 'paste' => null, 'unavailable' => content_api_unavailable_payload('paste', 'missing')]);
    }
    $state = content_public_state($paste);
    if ($state !== 'active') {
        dent_json_response(['success' => true, 'paste' => content_paste_public_payload($paste, false), 'unavailable' => content_api_unavailable_payload('paste', $state)]);
    }
    if ((string) ($paste['passwordHash'] ?? '') !== '' && !content_verify_record_password($paste, $password)) {
        dent_json_response(['success' => true, 'paste' => content_paste_public_payload($paste, false), 'unavailable' => content_api_unavailable_payload('paste', 'password')]);
    }
    dent_json_response(['success' => true, 'paste' => content_paste_public_payload($paste, true)]);
}

if ($action === 'rawPaste') {
    content_api_require_method(['GET', 'POST']);
    $token = content_clean_slug_token((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $password = (string) ($_GET['password'] ?? $_POST['password'] ?? '');
    $paste = content_with_store_lock(static function (array &$store) use ($token, $password): array {
        foreach ($store['pastes'] as $id => $candidate) {
            if (!is_array($candidate) || (string) ($candidate['token'] ?? '') !== $token) {
                continue;
            }
            if (content_public_state($candidate) !== 'active') {
                dent_error('این paste در دسترس نیست.', 404);
            }
            if ((string) ($candidate['passwordHash'] ?? '') !== '' && !content_verify_record_password($candidate, $password)) {
                dent_error('رمز paste معتبر نیست.', 403);
            }
            $candidate['rawViewCount'] = max(0, (int) ($candidate['rawViewCount'] ?? 0)) + 1;
            $candidate['lastViewedAt'] = dent_iso_now();
            $store['pastes'][$id] = $candidate;
            return $candidate;
        }
        dent_error('Paste پیدا نشد.', 404);
    });
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-cache');
    echo (string) ($paste['body'] ?? '');
    exit;
}

dent_error('درخواست نامعتبر است.', 404);
