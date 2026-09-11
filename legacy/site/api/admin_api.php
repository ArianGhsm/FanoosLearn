<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/analytics_store.php';
require_once __DIR__ . '/payments_store.php';
require_once __DIR__ . '/content_tools_store.php';
require_once __DIR__ . '/html_uploader_store.php';
require_once __DIR__ . '/navid_service.php';
require_once __DIR__ . '/notifications_store.php';

function admin_read_json_store(string $relativePath, array $fallback = []): array
{
    $path = dent_storage_path($relativePath);
    if (!is_file($path)) {
        return $fallback;
    }

    $raw = dent_read_json_file($path, $fallback);
    return is_array($raw) ? $raw : $fallback;
}

function admin_file_meta(string $path): array
{
    if ($path === '' || !is_file($path)) {
        return [
            'exists' => false,
            'updatedAt' => '',
            'sizeBytes' => 0,
        ];
    }

    $mtime = @filemtime($path);
    $size = @filesize($path);
    return [
        'exists' => true,
        'updatedAt' => $mtime ? date('c', (int) $mtime) : '',
        'sizeBytes' => $size === false ? 0 : max(0, (int) $size),
    ];
}

function admin_app_version_payload(): array
{
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app-version.json';
    $raw = dent_read_json_file($path, []);
    if (!is_array($raw)) {
        $raw = [];
    }

    return [
        'version' => dent_clean_text((string) ($raw['version'] ?? ''), 80),
        'generatedAt' => dent_clean_text((string) ($raw['generatedAt'] ?? ''), 80),
        'file' => admin_file_meta($path),
    ];
}

function admin_storage_health_payload(): array
{
    $root = defined('DENT_STORAGE_ROOT') ? DENT_STORAGE_ROOT : '';
    $checks = [];
    foreach ([
        'root' => $root,
        'notes' => dent_storage_path('notes'),
        'payments' => dent_storage_path('payments'),
        'forms' => dent_storage_path('forms'),
        'notifications' => dent_storage_path('notifications'),
    ] as $key => $path) {
        $checks[] = [
            'key' => $key,
            'exists' => is_dir($path),
            'writable' => is_dir($path) ? is_writable($path) : false,
            'updatedAt' => is_dir($path) && @filemtime($path) ? date('c', (int) @filemtime($path)) : '',
        ];
    }

    return [
        'rootExists' => $root !== '' && is_dir($root),
        'rootWritable' => $root !== '' && is_dir($root) && is_writable($root),
        'checks' => $checks,
    ];
}

function admin_latest_deploy_notice_payload(): ?array
{
    $store = notifications_read_store();
    $records = is_array($store['notifications'] ?? null) ? $store['notifications'] : [];
    $deploys = [];
    foreach ($records as $record) {
        if (!is_array($record) || (string) ($record['source'] ?? '') !== 'deploy') {
            continue;
        }
        $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];
        $deploys[] = [
            'id' => (string) ($record['id'] ?? ''),
            'title' => (string) ($record['title'] ?? ''),
            'createdAt' => (string) ($record['createdAt'] ?? ''),
            'version' => (string) ($meta['version'] ?? ''),
            'deployedAt' => (string) ($meta['deployedAt'] ?? ($record['releasedAt'] ?? '')),
            'branch' => (string) ($meta['branch'] ?? ''),
            'deployHead' => (string) ($meta['deployHead'] ?? ''),
        ];
    }

    usort($deploys, static function (array $left, array $right): int {
        return strcmp((string) ($right['deployedAt'] ?? ''), (string) ($left['deployedAt'] ?? ''));
    });

    return $deploys[0] ?? null;
}

function admin_payments_pending_payload(): array
{
    $store = payments_read_store();
    $orders = is_array($store['orders'] ?? null) ? $store['orders'] : [];
    $pending = 0;
    $failed = 0;
    $successToday = 0;
    $today = date('Y-m-d');
    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }
        $status = (string) ($order['status'] ?? '');
        if ($status === PAYMENTS_ORDER_STATUS_PENDING) {
            $pending++;
        } elseif ($status === PAYMENTS_ORDER_STATUS_FAILED) {
            $failed++;
        } elseif ($status === PAYMENTS_ORDER_STATUS_SUCCESS && str_starts_with((string) ($order['paid_at'] ?? ''), $today)) {
            $successToday++;
        }
    }

    return [
        'pendingOrders' => $pending,
        'failedOrders' => $failed,
        'successToday' => $successToday,
        'totalOrders' => count($orders),
        'storeFile' => admin_file_meta(payments_store_path()),
    ];
}

function admin_forms_pending_payload(): array
{
    $store = admin_read_json_store('forms/store.json', [
        'forms' => [],
        'responses' => [],
        'receipts' => [],
    ]);
    $forms = is_array($store['forms'] ?? null) ? $store['forms'] : [];
    $responses = is_array($store['responses'] ?? null) ? $store['responses'] : [];
    $receipts = is_array($store['receipts'] ?? null) ? $store['receipts'] : [];
    $open = 0;
    $draft = 0;
    $uploadedReceipts = 0;
    foreach ($forms as $form) {
        if (!is_array($form)) {
            continue;
        }
        $status = (string) ($form['status'] ?? 'open');
        if ($status === 'open') {
            $open++;
        } elseif ($status === 'draft') {
            $draft++;
        }
    }
    foreach ($receipts as $receipt) {
        if (is_array($receipt) && (string) ($receipt['status'] ?? 'uploaded') === 'uploaded') {
            $uploadedReceipts++;
        }
    }

    return [
        'openForms' => $open,
        'draftForms' => $draft,
        'responses' => count($responses),
        'uploadedReceipts' => $uploadedReceipts,
        'storeFile' => admin_file_meta(dent_storage_path('forms/store.json')),
    ];
}

function admin_notes_pending_payload(): array
{
    $files = [
        '1402' => 'notes/1402_terms.json',
        '1403' => 'notes/1403_terms.json',
        '1404' => 'notes/1404_terms.json',
        'prosthesis-1402' => 'notes/prosthesis_1402_terms.json',
    ];
    $requests = 0;
    $reports = 0;
    $latestAt = '';
    foreach ($files as $relativePath) {
        $store = admin_read_json_store($relativePath, []);
        foreach (is_array($store['resourceRequests'] ?? null) ? $store['resourceRequests'] : [] as $request) {
            if (is_array($request) && (string) ($request['status'] ?? 'open') === 'open') {
                $requests++;
            }
        }
        foreach (is_array($store['resourceLinkReports'] ?? null) ? $store['resourceLinkReports'] : [] as $report) {
            if (is_array($report) && (string) ($report['status'] ?? 'open') === 'open') {
                $reports++;
            }
        }
        $mtime = @filemtime(dent_storage_path($relativePath));
        if ($mtime) {
            $latestAt = max($latestAt, date('c', (int) $mtime));
        }
    }

    return [
        'resourceRequests' => $requests,
        'linkReports' => $reports,
        'latestStoreUpdateAt' => $latestAt,
    ];
}

function admin_content_tools_payload(): array
{
    $contentSummary = content_storage_summary(content_read_store());
    $htmlSummary = html_uploader_owner_summary(html_uploader_read_store());

    return [
        'files' => [
            'active' => max(0, (int) ($contentSummary['activeFiles'] ?? 0)),
            'total' => max(0, (int) ($contentSummary['totalFiles'] ?? 0)),
            'downloads' => max(0, (int) ($contentSummary['downloadCount'] ?? 0)),
        ],
        'pastes' => [
            'total' => max(0, (int) ($contentSummary['pasteCount'] ?? 0)),
            'views' => max(0, (int) ($contentSummary['pasteViewCount'] ?? 0)),
        ],
        'html' => [
            'active' => max(0, (int) ($htmlSummary['activePages'] ?? 0)),
            'hidden' => max(0, (int) ($htmlSummary['hiddenPages'] ?? 0)),
            'views' => max(0, (int) ($htmlSummary['viewCount'] ?? 0)),
        ],
    ];
}

function admin_navid_payload(): array
{
    $store = navid_load_store();
    $ownerStatus = navid_build_owner_status($store);
    $ownerState = is_array($ownerStatus['state'] ?? null) ? $ownerStatus['state'] : [];
    $snapshot = is_array($store['snapshot'] ?? null) ? $store['snapshot'] : [];
    return [
        'enabled' => !empty($store['config']['enabled']),
        'sessionStatus' => (string) ($store['session']['status'] ?? 'missing'),
        'lastSyncAt' => (string) ($store['state']['lastSyncAt'] ?? ''),
        'lastSuccessAt' => (string) ($store['state']['lastSuccessAt'] ?? ''),
        'lastResult' => (string) ($store['state']['lastResult'] ?? ''),
        'requiresReconnect' => !empty($store['state']['requiresReconnect']),
        'actionRequired' => (string) ($ownerState['actionRequired'] ?? ''),
        'credentialsMissing' => !empty($ownerState['credentialsMissing']),
        'credentialsInvalid' => !empty($ownerState['credentialsInvalid']),
        'courses' => count(is_array($snapshot['courses'] ?? null) ? $snapshot['courses'] : []),
        'assignments' => count(is_array($snapshot['assignments'] ?? null) ? $snapshot['assignments'] : []),
        'ownerStatus' => $ownerStatus,
        'storeFile' => admin_file_meta(navid_store_path()),
    ];
}

function admin_dashboard_payload(array $viewer): array
{
    $analytics = analytics_build_owner_dashboard($viewer);
    $errors = is_array($analytics['errorLog'] ?? null) ? $analytics['errorLog'] : analytics_error_log_payload(20);
    $payments = admin_payments_pending_payload();
    $forms = admin_forms_pending_payload();
    $notes = admin_notes_pending_payload();
    $navid = admin_navid_payload();

    $pendingTotal = max(0, (int) ($payments['pendingOrders'] ?? 0))
        + max(0, (int) ($forms['uploadedReceipts'] ?? 0))
        + max(0, (int) ($notes['resourceRequests'] ?? 0))
        + max(0, (int) ($notes['linkReports'] ?? 0))
        + (!empty($navid['requiresReconnect']) || !empty($navid['credentialsMissing']) || !empty($navid['credentialsInvalid']) ? 1 : 0);

    return [
        'generatedAt' => dent_iso_now(),
        'viewer' => [
            'studentNumber' => (string) ($viewer['studentNumber'] ?? ''),
            'name' => (string) ($viewer['name'] ?? ''),
            'role' => (string) ($viewer['role'] ?? ''),
        ],
        'appVersion' => admin_app_version_payload(),
        'deploy' => [
            'latestNotice' => admin_latest_deploy_notice_payload(),
        ],
        'health' => [
            'phpVersion' => PHP_VERSION,
            'storage' => admin_storage_health_payload(),
            'sms' => dent_sms_status_payload(),
            'errors' => [
                'available' => !empty($errors['available']),
                'total' => max(0, (int) ($errors['total'] ?? 0)),
                'last24h' => max(0, (int) ($errors['last24h'] ?? 0)),
                'last7d' => max(0, (int) ($errors['last7d'] ?? 0)),
                'recent' => array_slice(is_array($errors['recent'] ?? null) ? $errors['recent'] : [], 0, 8),
            ],
        ],
        'pending' => [
            'total' => $pendingTotal,
            'payments' => $payments,
            'forms' => $forms,
            'notes' => $notes,
            'navid' => $navid,
        ],
        'activity' => [
            'totals' => is_array($analytics['totals'] ?? null) ? $analytics['totals'] : [],
            'cohorts' => array_slice(is_array($analytics['cohorts'] ?? null) ? $analytics['cohorts'] : [], 0, 8),
            'recentLogins' => array_slice(is_array($analytics['recentLogins'] ?? null) ? $analytics['recentLogins'] : [], 0, 8),
        ],
        'content' => admin_content_tools_payload(),
        'appearance' => [
            'site' => dent_site_appearance_public_settings(),
        ],
        'links' => [
            ['label' => 'کاربران و ورودی‌ها', 'href' => '/account/#owner', 'group' => 'account'],
            ['label' => 'مدیریت خرید', 'href' => '/buy/manage/', 'group' => 'buy'],
            ['label' => 'مدیریت فرم‌ها', 'href' => '/forms/?manage=1', 'group' => 'forms'],
            ['label' => 'فایل‌منیجر منابع', 'href' => '/notes/files/', 'group' => 'notes'],
            ['label' => 'مرکز آپلود', 'href' => '/files/', 'group' => 'files'],
            ['label' => 'Pastebin', 'href' => '/paste/', 'group' => 'paste'],
            ['label' => 'HTML uploader', 'href' => '/html-uploader/', 'group' => 'html'],
            ['label' => 'نوید', 'href' => '/navid/', 'group' => 'navid'],
        ],
    ];
}

$action = dent_request_action();
if ($action === '') {
    $action = 'dashboard';
}

if ($action === 'dashboard') {
    if (dent_request_method() !== 'GET') {
        dent_error('متد درخواست معتبر نیست.', 405);
    }
    $viewer = dent_require_owner();
    dent_json_response([
        'success' => true,
        'dashboard' => admin_dashboard_payload($viewer),
    ]);
}

if ($action === 'saveAppearance') {
    if (dent_request_method() !== 'POST') {
        dent_error('روش ذخیره ظاهر سایت نامعتبر است.', 405);
    }
    dent_require_owner();
    $settings = dent_save_site_appearance_owner_config([
        'bottomNavSwipeEnabled' => $_POST['bottomNavSwipeEnabled'] ?? '0',
        'bottomNavLabelsEnabled' => $_POST['bottomNavLabelsEnabled'] ?? '0',
        'bottomNavGlassEnabled' => $_POST['bottomNavGlassEnabled'] ?? '0',
        'visualEffectsLiteEnabled' => $_POST['visualEffectsLiteEnabled'] ?? '0',
    ]);

    dent_json_response([
        'success' => true,
        'appearance' => [
            'site' => $settings,
        ],
        'message' => 'تنظیمات ظاهر سایت ذخیره شد.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
