<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/notifications_store.php';

function notifications_parse_ids_input($raw): array
{
    if (is_array($raw)) {
        $parts = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            $parts = $decoded;
        } else {
            $parts = preg_split('/[\s,]+/u', $text) ?: [];
        }
    }

    $ids = [];
    foreach ($parts as $value) {
        $id = trim((string) $value);
        if ($id === '') {
            continue;
        }
        $ids[$id] = true;
    }

    return array_keys($ids);
}

$action = dent_request_action();
if ($action === '') {
    $action = 'summary';
}

if ($action === 'summary') {
    $user = dent_require_user();
    dent_release_session_lock();
    notifications_process_due_queue($user);
    $store = notifications_read_store();
    dent_json_response([
        'success' => true,
        'summary' => notifications_summary_for_user($store, $user),
        'preview' => notifications_latest_unread_payload_for_user($store, $user),
    ]);
}

if ($action === 'list') {
    $user = dent_require_user();
    dent_release_session_lock();
    notifications_process_due_queue($user);
    $store = notifications_read_store();
    dent_json_response([
        'success' => true,
        'data' => notifications_list_payload_for_user($store, $user, (int) ($_GET['limit'] ?? 60)),
    ]);
}

if ($action === 'markRead') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت خواندن اعلان نامعتبر است.', 405);
    }

    $user = dent_require_user();
    dent_release_session_lock();
    notifications_process_due_queue($user);
    $ids = notifications_parse_ids_input($_POST['ids'] ?? ($_POST['idsJson'] ?? []));
    $summary = notifications_mark_read($user, $ids);
    $store = notifications_read_store();
    dent_json_response([
        'success' => true,
        'summary' => $summary,
        'preview' => notifications_latest_unread_payload_for_user($store, $user),
        'preferences' => notifications_preferences_payload($user, $store),
    ]);
}

if ($action === 'markAllRead') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت خواندن همه اعلان‌ها نامعتبر است.', 405);
    }

    $user = dent_require_user();
    dent_release_session_lock();
    notifications_process_due_queue($user);
    $summary = notifications_mark_all_read($user);
    $store = notifications_read_store();
    dent_json_response([
        'success' => true,
        'summary' => $summary,
        'preview' => notifications_latest_unread_payload_for_user($store, $user),
        'preferences' => notifications_preferences_payload($user, $store),
    ]);
}

if ($action === 'savePrefs') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ذخیره تنظیمات اعلان نامعتبر است.', 405);
    }

    $user = dent_require_user();
    dent_release_session_lock();
    $preferences = notifications_save_preferences($user, [
        'navidAssignmentAlerts' => $_POST['navidAssignmentAlerts'] ?? null,
        'formReminders' => $_POST['formReminders'] ?? null,
        'paymentReminders' => $_POST['paymentReminders'] ?? null,
        'dailyDigestEnabled' => $_POST['dailyDigestEnabled'] ?? null,
        'dailyDigestHour' => $_POST['dailyDigestHour'] ?? null,
    ]);
    notifications_process_due_queue($user);
    $store = notifications_read_store();
    dent_json_response([
        'success' => true,
        'preferences' => $preferences,
        'summary' => notifications_summary_for_user($store, $user),
        'preview' => notifications_latest_unread_payload_for_user($store, $user),
    ]);
}

if ($action === 'snooze') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تعویق اعلان نامعتبر است.', 405);
    }

    $user = dent_require_user();
    dent_release_session_lock();
    $hours = max(1, min(168, (int) ($_POST['hours'] ?? 24)));
    $summary = notifications_snooze_record($user, (string) ($_POST['id'] ?? ''), $hours);
    $store = notifications_read_store();
    dent_json_response([
        'success' => true,
        'message' => 'اعلان موقتاً کنار گذاشته شد.',
        'summary' => $summary,
        'preview' => notifications_latest_unread_payload_for_user($store, $user),
        'preferences' => notifications_preferences_payload($user, $store),
    ]);
}

if ($action === 'broadcast') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ارسال اعلان نامعتبر است.', 405);
    }

    $viewer = dent_require_user();
    dent_release_session_lock();
    notifications_process_due_queue($viewer);
    $record = notifications_create_broadcast($viewer, [
        'targetKey' => (string) ($_POST['targetKey'] ?? ''),
        'title' => (string) ($_POST['title'] ?? ''),
        'body' => (string) ($_POST['body'] ?? ''),
        'ctaLabel' => (string) ($_POST['ctaLabel'] ?? ''),
        'ctaHref' => (string) ($_POST['ctaHref'] ?? ''),
        'scheduleAt' => (string) ($_POST['scheduleAt'] ?? ''),
        'sendSms' => $_POST['sendSms'] ?? null,
    ]);
    $store = notifications_read_store();
    $recordId = (string) ($record['id'] ?? '');
    $latestRecord = is_array($store['notifications'][$recordId] ?? null)
        ? $store['notifications'][$recordId]
        : $record;

    dent_json_response([
        'success' => true,
        'message' => notifications_record_is_scheduled($latestRecord)
            ? 'اعلان زمان‌بندی شد.'
            : 'اعلان برای کاربران مقصد ثبت شد.',
        'summary' => notifications_summary_for_user($store, $viewer),
        'preview' => notifications_latest_unread_payload_for_user($store, $viewer),
        'notification' => notifications_public_payload($latestRecord, $viewer, $store),
        'manager' => notifications_manager_payload($viewer),
    ]);
}

if ($action === 'deployNotice') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت اعلان استقرار نامعتبر است.', 405);
    }

    $viewer = dent_require_owner();
    dent_release_session_lock();
    notifications_process_due_queue($viewer);
    $record = notifications_create_owner_deploy_notice($viewer, [
        'version' => (string) ($_POST['version'] ?? ''),
        'deployedAt' => (string) ($_POST['deployedAt'] ?? ''),
        'branch' => (string) ($_POST['branch'] ?? ''),
        'deployHead' => (string) ($_POST['deployHead'] ?? ''),
        'title' => (string) ($_POST['title'] ?? ''),
        'body' => (string) ($_POST['body'] ?? ''),
    ]);
    $store = notifications_read_store();
    $recordId = (string) ($record['id'] ?? '');
    $latestRecord = is_array($store['notifications'][$recordId] ?? null)
        ? $store['notifications'][$recordId]
        : $record;

    dent_json_response([
        'success' => true,
        'message' => 'اعلان استقرار برای مالک ثبت شد.',
        'summary' => notifications_summary_for_user($store, $viewer),
        'preview' => notifications_latest_unread_payload_for_user($store, $viewer),
        'notification' => notifications_public_payload($latestRecord, $viewer, $store),
        'manager' => notifications_manager_payload($viewer),
    ]);
}

if ($action === 'audience') {
    $viewer = dent_require_user();
    dent_release_session_lock();
    notifications_process_due_queue($viewer);
    dent_json_response([
        'success' => true,
        'data' => notifications_audience_payload(
            $viewer,
            (string) ($_GET['id'] ?? ($_POST['id'] ?? ''))
        ),
    ]);
}

if ($action === 'delete') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف اعلان نامعتبر است.', 405);
    }

    $viewer = dent_require_user();
    dent_release_session_lock();
    notifications_process_due_queue($viewer);
    $deleted = notifications_delete_record($viewer, (string) ($_POST['id'] ?? ''));
    $store = notifications_read_store();
    dent_json_response([
        'success' => true,
        'message' => notifications_record_is_scheduled($deleted)
            ? 'اعلان زمان‌بندی‌شده حذف شد.'
            : 'اعلان حذف شد.',
        'summary' => notifications_summary_for_user($store, $viewer),
        'preview' => notifications_latest_unread_payload_for_user($store, $viewer),
        'preferences' => notifications_preferences_payload($viewer, $store),
        'manager' => notifications_manager_payload($viewer),
        'deletedId' => (string) ($deleted['id'] ?? ''),
    ]);
}

dent_error('درخواست اعلان نامعتبر است.', 404);
