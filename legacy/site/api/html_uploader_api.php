<?php
declare(strict_types=1);

require_once __DIR__ . '/html_uploader_store.php';

function html_uploader_api_require_method(array $methods): void
{
    if (!in_array(dent_request_method(), $methods, true)) {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
}

function html_uploader_api_sort_pages(array $pages, string $sort): array
{
    $sort = trim(strtolower($sort));
    usort($pages, static function (array $left, array $right) use ($sort): int {
        if ($sort === 'views') {
            return (int) ($right['viewCount'] ?? 0) <=> (int) ($left['viewCount'] ?? 0);
        }
        if ($sort === 'oldest') {
            return strcmp((string) ($left['createdAt'] ?? ''), (string) ($right['createdAt'] ?? ''));
        }
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });
    return $pages;
}

function html_uploader_api_filter_pages(array $pages, array $params): array
{
    $query = dent_utf8_strtolower(dent_clean_text((string) ($params['query'] ?? ''), 120));
    $status = trim(strtolower((string) ($params['status'] ?? 'available')));

    $filtered = [];
    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        $state = html_uploader_public_state($page);
        if ($status === '' || $status === 'available') {
            if ($state === 'deleted') {
                continue;
            }
        } elseif ($status !== 'all' && $state !== $status && (string) ($page['status'] ?? '') !== $status) {
            continue;
        }
        if ($query !== '') {
            $haystack = dent_utf8_strtolower(implode(' ', [
                (string) ($page['title'] ?? ''),
                (string) ($page['originalName'] ?? ''),
                (string) ($page['token'] ?? ''),
            ]));
            if (!str_contains($haystack, $query)) {
                continue;
            }
        }
        $filtered[] = $page;
    }
    return html_uploader_api_sort_pages($filtered, (string) ($params['sort'] ?? 'newest'));
}

function html_uploader_api_paginate(array $pages, array $params): array
{
    $page = max(1, (int) ($params['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($params['perPage'] ?? 20)));
    $total = count($pages);
    $pagesCount = max(1, (int) ceil($total / $perPage));
    if ($page > $pagesCount) {
        $page = $pagesCount;
    }
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'pages' => $pagesCount,
        'items' => array_slice($pages, ($page - 1) * $perPage, $perPage),
    ];
}

$action = dent_request_action();

// Only auth_store writes the PHP session; every action below is a pure session
// reader. Release the session lock now so concurrent same-session requests are
// not serialized behind this request. $_SESSION stays readable.
dent_release_session_lock();

if ($action === 'uploadPage') {
    html_uploader_api_require_method(['POST']);
    $file = $_FILES['file'] ?? null;
    if (!is_array($file)) {
        dent_error('فایل HTML برای آپلود انتخاب نشده است.', 422);
    }
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 180);
    $ttlHours = $_POST['ttlHours'] ?? HTML_UPLOADER_DEFAULT_TTL_HOURS;

    $uploaded = html_uploader_with_store_lock(static function (array &$store) use ($file, $title, $ttlHours): array {
        $uploaderKey = html_uploader_client_key();
        if (html_uploader_recent_upload_count($store, $uploaderKey) >= HTML_UPLOADER_DAILY_UPLOAD_LIMIT) {
            dent_error('سقف روزانه آپلود این لینک تکمیل شده است. بعداً دوباره تلاش کنید.', 429);
        }
        $page = html_uploader_store_uploaded_page($file, [
            'title' => $title,
            'ttlHours' => $ttlHours,
            'status' => 'active',
        ]);
        $store['pages'][(string) $page['id']] = $page;
        return $page;
    });

    dent_json_response([
        'success' => true,
        'page' => html_uploader_page_public_payload($uploaded),
        'message' => 'صفحه HTML موقت ساخته شد.',
    ]);
}

if ($action === 'ownerPages') {
    html_uploader_api_require_method(['GET']);
    dent_require_owner();
    $store = html_uploader_read_store();
    $filtered = html_uploader_api_filter_pages(array_values($store['pages'] ?? []), $_GET);
    $page = html_uploader_api_paginate($filtered, $_GET);
    $page['items'] = array_map(static fn(array $item): array => html_uploader_page_public_payload($item, true), $page['items']);
    dent_json_response([
        'success' => true,
        'summary' => html_uploader_owner_summary($store),
        'page' => $page,
    ]);
}

if ($action === 'ownerPageAction') {
    html_uploader_api_require_method(['POST']);
    dent_require_owner();
    $id = html_uploader_clean_id((string) ($_POST['id'] ?? ''));
    $operation = trim(strtolower((string) ($_POST['operation'] ?? '')));
    if ($id === '') {
        dent_error('شناسه صفحه HTML معتبر نیست.', 422);
    }
    if (!in_array($operation, ['activate', 'hide', 'delete', 'purge'], true)) {
        dent_error('عملیات صفحه HTML معتبر نیست.', 422);
    }

    $updated = html_uploader_with_store_lock(static function (array &$store) use ($id, $operation): array {
        $page = $store['pages'][$id] ?? null;
        if (!is_array($page)) {
            dent_error('صفحه HTML پیدا نشد.', 404);
        }
        if ($operation === 'activate') {
            $page['status'] = 'active';
        } elseif ($operation === 'hide') {
            $page['status'] = 'hidden';
        } else {
            $page['status'] = 'deleted';
            $page['deletedAt'] = $page['deletedAt'] ?: dent_iso_now();
        }
        if ($operation === 'purge') {
            $path = html_uploader_page_path((string) ($page['storedName'] ?? ''));
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
            $page['purgedAt'] = dent_iso_now();
        }
        $page['updatedAt'] = dent_iso_now();
        $store['pages'][$id] = $page;
        return $page;
    });

    dent_json_response([
        'success' => true,
        'page' => html_uploader_page_public_payload($updated, true),
        'message' => 'وضعیت صفحه HTML به‌روزرسانی شد.',
    ]);
}

dent_error('درخواست HTML uploader نامعتبر است.', 404);
