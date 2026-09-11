<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/push_store.php';

function push_api_parse_subscription_input(): array
{
    $raw = $_POST['subscription'] ?? '';
    if (is_array($raw)) {
        $decoded = $raw;
    } else {
        $decoded = json_decode((string) $raw, true);
    }
    if (!is_array($decoded)) {
        return [];
    }

    $keys = is_array($decoded['keys'] ?? null) ? $decoded['keys'] : [];

    return [
        'endpoint' => trim((string) ($decoded['endpoint'] ?? '')),
        'p256dh' => trim((string) ($keys['p256dh'] ?? ($decoded['p256dh'] ?? ''))),
        'auth' => trim((string) ($keys['auth'] ?? ($decoded['auth'] ?? ''))),
    ];
}

$action = dent_request_action();

if ($action === 'publicKey') {
    if (dent_request_method() !== 'GET') {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
    $user = dent_require_user();
    dent_release_session_lock();

    if (!push_supported()) {
        dent_json_response([
            'success' => true,
            'supported' => false,
            'publicKey' => '',
            'subscribed' => false,
        ]);
    }

    dent_json_response([
        'success' => true,
        'supported' => true,
        'publicKey' => push_public_key(),
        'subscribed' => push_user_has_subscription((string) ($user['studentNumber'] ?? '')),
    ]);
}

if ($action === 'subscribe') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت اعلان مرورگر نامعتبر است.', 405);
    }
    $user = dent_require_user();
    dent_release_session_lock();

    if (!push_supported()) {
        dent_error('اعلان مرورگر روی این سرور فعال نیست.', 503, ['supported' => false]);
    }

    $subscription = push_api_parse_subscription_input();
    if (($subscription['endpoint'] ?? '') === '') {
        dent_error('اطلاعات اشتراک اعلان ناقص است.', 422);
    }

    $cohortKey = dent_user_cohort_key($user);
    $result = push_save_subscription($user, $cohortKey, $subscription);
    if (empty($result['success'])) {
        dent_error('ثبت اشتراک اعلان مرورگر ناموفق بود.', 422, ['reason' => (string) ($result['reason'] ?? '')]);
    }

    dent_json_response([
        'success' => true,
        'subscribed' => true,
        'message' => 'اعلان‌های مرورگر برای این دستگاه فعال شد.',
    ]);
}

if ($action === 'unsubscribe') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد لغو اعلان مرورگر نامعتبر است.', 405);
    }
    dent_require_user();
    dent_release_session_lock();

    $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
    if ($endpoint === '') {
        $subscription = push_api_parse_subscription_input();
        $endpoint = (string) ($subscription['endpoint'] ?? '');
    }
    if ($endpoint !== '') {
        push_remove_subscription_by_endpoint($endpoint);
    }

    dent_json_response([
        'success' => true,
        'subscribed' => false,
        'message' => 'اعلان‌های مرورگر برای این دستگاه غیرفعال شد.',
    ]);
}

dent_error('درخواست اعلان مرورگر نامعتبر است.', 404);
