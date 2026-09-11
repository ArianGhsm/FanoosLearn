<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/analytics_store.php';

$action = dent_request_action();

/**
 * Classify the current request as 'owner', 'bot' (AI/crawler) or 'human'.
 * Must run BEFORE the session lock is released so the logged-in user is known.
 */
function analytics_resolve_request_segment(): string
{
    $user = function_exists('dent_current_user') ? dent_current_user() : null;
    if (is_array($user)) {
        $role = (string) ($user['role'] ?? '');
        if ($role === 'owner' || !empty($user['isOwner'])) {
            return 'owner';
        }
    }
    return analytics_request_is_bot() ? 'bot' : 'human';
}

if ($action === 'trackPageView') {
    if (!in_array(dent_request_method(), ['POST', 'GET'], true)) {
        dent_error('متد ثبت بازدید صفحه نامعتبر است.', 405);
    }

    $segment = analytics_resolve_request_segment();
    dent_release_session_lock();
    analytics_record_page_view([
        'path' => $_POST['path'] ?? ($_GET['path'] ?? ($_SERVER['REQUEST_URI'] ?? '/')),
        'title' => $_POST['title'] ?? ($_GET['title'] ?? ''),
        'cohort' => $_POST['cohort'] ?? ($_GET['cohort'] ?? ''),
        'visitorId' => $_POST['visitorId'] ?? ($_GET['visitorId'] ?? ''),
        'visitId' => $_POST['visitId'] ?? ($_GET['visitId'] ?? ''),
        'segment' => $segment,
    ]);

    dent_json_response([
        'success' => true,
        'tracked' => 'page-view',
    ]);
}

if ($action === 'trackDownload') {
    if (!in_array(dent_request_method(), ['POST', 'GET'], true)) {
        dent_error('متد ثبت دانلود نامعتبر است.', 405);
    }

    $segment = analytics_resolve_request_segment();
    dent_release_session_lock();
    analytics_record_download([
        'href' => $_POST['href'] ?? ($_GET['href'] ?? ''),
        'label' => $_POST['label'] ?? ($_GET['label'] ?? ''),
        'sourcePath' => $_POST['sourcePath'] ?? ($_GET['sourcePath'] ?? ''),
        'sourceFamily' => $_POST['sourceFamily'] ?? ($_GET['sourceFamily'] ?? ''),
        'cohort' => $_POST['cohort'] ?? ($_GET['cohort'] ?? ''),
        'segment' => $segment,
    ]);

    dent_json_response([
        'success' => true,
        'tracked' => 'download',
    ]);
}

if ($action === 'ownerDashboard') {
    $viewer = dent_require_owner();
    dent_release_session_lock();

    dent_json_response([
        'success' => true,
        'dashboard' => analytics_build_owner_dashboard($viewer),
    ]);
}

dent_error('درخواست آمار نامعتبر است.', 404);
