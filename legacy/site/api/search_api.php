<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/search_store.php';

$action = dent_request_action();

if ($action !== 'query') {
    dent_error('درخواست نامعتبر است.', 404);
}

if (dent_request_method() !== 'GET') {
    dent_error('متد درخواست نامعتبر است.', 405);
}

$user = dent_require_user();
dent_release_session_lock();

$cohort = dent_resolve_accessible_cohort($user, dent_requested_cohort_key());
$rawQuery = (string) ($_GET['q'] ?? '');

dent_json_response(search_run_query($rawQuery, $cohort));
