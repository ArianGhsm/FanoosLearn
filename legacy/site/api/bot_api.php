<?php
declare(strict_types=1);

require_once __DIR__ . '/bot_store.php';
require_once __DIR__ . '/academic_term7_bot_service.php';
require_once __DIR__ . '/classops_bot_service.php';
require_once __DIR__ . '/classops_bot_ux_v2.php';
require_once __DIR__ . '/classops_bot_ux_v3.php';

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$action = dent_request_action();

if ($action === 'service') {
    // HMAC timestamp/nonce verification happens before service dispatch. Runtime
    // requests never provide canonical identity directly; each service resolves
    // the linked website user from platform + platformUserId.
    $payload = dent_bot_service_request();
    $serviceAction = trim((string) ($payload['action'] ?? ''));
    dent_json_response(
        dent_term7_bot_service_action($serviceAction)
            ? dent_term7_bot_service_dispatch($payload)
            : (classops_bot_ux_v3_action($serviceAction)
                ? classops_bot_ux_v3_dispatch($payload)
                : (classops_bot_ux_v2_action($serviceAction)
                    ? classops_bot_ux_v2_dispatch($payload)
                    : (classops_bot_service_action($serviceAction)
                        ? classops_bot_service_dispatch($payload)
                        : dent_bot_service_dispatch($payload))))
    );
}

if ($action === 'linkInfo') {
    dent_require_user();
    dent_json_response(dent_bot_link_challenge_info(trim((string) ($_GET['token'] ?? ''))));
}

if ($action === 'confirmLink') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تأیید اتصال نامعتبر است.', 405);
    }
    dent_auth_session_require_csrf();
    $user = dent_require_user();
    dent_json_response(dent_bot_confirm_link(trim((string) ($_POST['token'] ?? '')), $user));
}

if ($action === 'accountConnections') {
    if (dent_request_method() !== 'GET') {
        dent_error('متد دریافت اتصال‌ها نامعتبر است.', 405);
    }
    $user = dent_require_user();
    $result = dent_bot_account_connections($user);
    $result['csrfToken'] = dent_auth_session_csrf_token();
    dent_json_response($result);
}

if ($action === 'disconnectAccount') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد قطع اتصال نامعتبر است.', 405);
    }
    dent_auth_session_require_csrf();
    $user = dent_require_user();
    dent_json_response(dent_bot_disconnect_account($user, (string) ($_POST['platform'] ?? '')));
}

dent_error('درخواست نامعتبر است.', 404);