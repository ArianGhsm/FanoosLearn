<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/exams_home_highlights.php';

if (dent_request_method() !== 'GET') {
    dent_error('Method not allowed.', 405);
}

$requestedCohort = dent_requested_cohort_key();
$viewer = dent_current_user();
if ($viewer !== null) {
    $cohortKey = dent_resolve_accessible_cohort($viewer, $requestedCohort);
} else {
    // Signed-out visitors may see only the public primary-cohort highlights.
    $cohortKey = $requestedCohort;
    if ($cohortKey !== dent_primary_cohort_key()) {
        dent_error('برای مشاهده این ورودی ابتدا وارد حساب کاربری شوید.', 401, ['loggedOut' => true]);
    }
}

$cohort = dent_cohort_record($cohortKey);
if ($cohort === null) {
    dent_error('ورودی درخواستی پیدا نشد.', 404);
}

dent_release_session_lock();
$services = is_array($cohort['services'] ?? null) ? $cohort['services'] : [];
$enabled = !empty($services['activeExamHighlights'])
    || $cohortKey === dent_external_site_users_cohort_key();
$index = dent_exams_home_highlights_index();

dent_json_response([
    'success' => true,
    'cohortKey' => $cohortKey,
    'sourceSignature' => (string) ($index['sourceSignature'] ?? ''),
    'courses' => $enabled ? dent_exams_home_highlights_courses_for_cohort($cohortKey) : [],
]);
