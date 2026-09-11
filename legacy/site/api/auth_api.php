<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/analytics_store.php';
require_once __DIR__ . '/grades_store.php';

function dent_owner_dis_request_private_index(): array
{
    $store = dent_auth_load_dis_request_store();
    $responses = $store['responses'];
    $index = [];

    foreach ($responses as $studentNumber => $record) {
        if (!is_array($record)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($record['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
        $nationalCode = dent_normalize_national_code((string) ($fields['nationalCode'] ?? ''));
        $directoryPhoneNumber = dent_normalize_phone_number((string) ($fields['phoneNumber'] ?? ''));
        if ($nationalCode === '' && $directoryPhoneNumber === '') {
            continue;
        }

        $index[$normalizedStudentNumber] = [
            'nationalCode' => $nationalCode,
            'directoryPhoneNumber' => $directoryPhoneNumber,
            'hasNationalCode' => $nationalCode !== '',
            'hasDirectoryPhone' => $directoryPhoneNumber !== '',
            'source' => 'dis-request',
        ];
    }

    return $index;
}

function dent_management_visible_users(array $viewer, bool $includeOwnerPrivate = false): array
{
    $role = dent_normalize_role((string) ($viewer['role'] ?? 'student'), (string) ($viewer['studentNumber'] ?? ''));
    $viewerCohortKey = dent_user_cohort_key($viewer);
    $store = dent_load_user_store();
    $visible = [];

    foreach (($store['users'] ?? []) as $studentNumber => $rawUser) {
        if (!is_array($rawUser)) {
            continue;
        }

        $rawUser['studentNumber'] = (string) ($rawUser['studentNumber'] ?? $studentNumber);
        $targetCohortKey = dent_user_cohort_key($rawUser);
        if ($role !== 'owner' && $targetCohortKey !== $viewerCohortKey) {
            continue;
        }

        $public = dent_public_user($rawUser);
        if ($includeOwnerPrivate) {
            $public['ownerPrivate'] = dent_owner_private_user_fields($rawUser);
        }
        $public['sortableName'] = trim((string) ($rawUser['name'] ?? $studentNumber));
        $public['_cohortKey'] = $targetCohortKey;
        $visible[] = $public;
    }

    usort($visible, static function (array $left, array $right): int {
        if (($left['role'] ?? '') === 'owner' && ($right['role'] ?? '') !== 'owner') {
            return -1;
        }
        if (($right['role'] ?? '') === 'owner' && ($left['role'] ?? '') !== 'owner') {
            return 1;
        }
        if (($left['_cohortKey'] ?? '') !== ($right['_cohortKey'] ?? '')) {
            return strcmp((string) ($left['_cohortKey'] ?? ''), (string) ($right['_cohortKey'] ?? ''));
        }
        return strcasecmp((string) ($left['sortableName'] ?? ''), (string) ($right['sortableName'] ?? ''));
    });

    foreach ($visible as &$user) {
        unset($user['sortableName'], $user['_cohortKey']);
    }
    unset($user);

    return $visible;
}

function dent_management_cohort_cards(array $viewer, array $users): array
{
    $cards = [];
    foreach (dent_visible_cohorts_for_user($viewer) as $cohort) {
        $cohortKey = (string) ($cohort['key'] ?? '');
        $counts = [
            'totalUsers' => 0,
            'representatives' => 0,
            'withNationalCode' => 0,
            'withDirectoryPhone' => 0,
        ];

        foreach ($users as $user) {
            if ((string) ($user['cohortKey'] ?? '') !== $cohortKey) {
                continue;
            }
            $counts['totalUsers']++;
            if (in_array((string) ($user['role'] ?? ''), ['representative', 'prosthesis_representative'], true)) {
                $counts['representatives']++;
            }
            if (!empty($user['hasNationalCode'])) {
                $counts['withNationalCode']++;
            }
            if (!empty($user['hasDirectoryPhone'])) {
                $counts['withDirectoryPhone']++;
            }
        }

        $cards[] = [
            'key' => $cohortKey,
            'title' => (string) ($cohort['title'] ?? ''),
            'shortTitle' => (string) ($cohort['shortTitle'] ?? ''),
            'description' => (string) ($cohort['description'] ?? ''),
            'productType' => (string) ($cohort['productType'] ?? ''),
            'year' => (string) ($cohort['year'] ?? ''),
            'siteVariant' => (string) ($cohort['siteVariant'] ?? ''),
            'notesMode' => (string) ($cohort['notesMode'] ?? ''),
            'allowRepresentativeManagement' => !empty($cohort['allowRepresentativeManagement']),
            'supportsRotationGroups' => !empty($cohort['supportsRotationGroups']),
            'services' => is_array($cohort['services'] ?? null) ? $cohort['services'] : [],
            'routes' => is_array($cohort['routes'] ?? null) ? $cohort['routes'] : [],
            'permissions' => dent_permissions_for_role((string) ($viewer['role'] ?? 'student'), $cohortKey),
            'counts' => $counts,
        ];
    }

    return $cards;
}

function dent_import_role_from_label(string $value, string $cohortKey): string
{
    $normalized = dent_force_utf8(trim($value));
    $normalized = str_replace(['ي', 'ك'], ['ی', 'ک'], $normalized);
    $normalized = preg_replace('/\s+/u', '', $normalized) ?? '';
    if ($normalized === '' || in_array($normalized, ['student', 'دانشجو'], true)) {
        return dent_is_prosthesis_cohort_key($cohortKey) ? 'prosthesis_student' : 'student';
    }

    if (in_array($normalized, ['representative', 'نماینده'], true)) {
        return dent_is_prosthesis_cohort_key($cohortKey) ? 'prosthesis_representative' : 'representative';
    }

    dent_error('نقش واردشده فقط باید دانشجو یا نماینده باشد.', 422);
}

function dent_parse_import_user_rows(string $text, string $cohortKey): array
{
    $rows = preg_split('/\r\n|\r|\n/u', $text) ?: [];
    $entries = [];

    foreach ($rows as $index => $row) {
        $line = trim((string) $row);
        if ($line === '') {
            continue;
        }

        $parts = preg_split('/\s*[,\x{060C}]\s*/u', $line) ?: [];
        if (count($parts) < 4) {
            if ($index === 0 && preg_match('/شماره/u', $line) === 1) {
                continue;
            }
            dent_error('هر خط ورودی باید چهار ستون نام، نام خانوادگی، شماره دانشجویی و نقش داشته باشد.', 422);
        }

        $firstName = trim((string) ($parts[0] ?? ''));
        $lastName = trim((string) ($parts[1] ?? ''));
        $studentNumber = trim((string) ($parts[2] ?? ''));
        $roleLabel = trim((string) ($parts[3] ?? ''));

        if ($index === 0 && preg_match('/نام/u', $firstName) === 1 && preg_match('/شماره/u', $studentNumber) === 1) {
            continue;
        }

        $entries[] = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'studentNumber' => $studentNumber,
            'role' => dent_import_role_from_label($roleLabel, $cohortKey),
        ];
    }

    return $entries;
}

function dent_import_user_split_text_row_v2(string $line): array
{
    if (str_contains($line, "\t")) {
        $parts = preg_split('/\t+/u', $line) ?: [];
    } else {
        $parts = preg_split('/\s*[,;\x{060C}]\s*/u', $line) ?: [];
    }

    $normalized = [];
    foreach ($parts as $part) {
        $normalized[] = trim((string) $part);
    }
    while ($normalized !== [] && end($normalized) === '') {
        array_pop($normalized);
    }

    return $normalized;
}

function dent_import_user_normalize_row_v2(array $row): array
{
    $normalized = [];
    foreach ($row as $cell) {
        $normalized[] = trim((string) $cell);
    }
    while ($normalized !== [] && end($normalized) === '') {
        array_pop($normalized);
    }

    return $normalized;
}

function dent_import_user_header_map_v2(array $parts): ?array
{
    $header = dent_import_user_normalize_row_v2($parts);
    $fullNameIndex = dent_find_header_index($header, ['نام و نام خانوادگی', 'نام کامل', 'fullname', 'full name']);
    $firstNameIndex = dent_find_header_index($header, ['نام', 'first name', 'firstname', 'given name', 'givenname']);
    $lastNameIndex = dent_find_header_index($header, ['نام خانوادگی', 'last name', 'lastname', 'family name', 'familyname', 'surname']);
    $studentNumberIndex = dent_find_header_index($header, ['شماره دانشجویی', 'شماره', 'student number', 'student id', 'studentid']);
    $roleIndex = dent_find_header_index($header, ['نقش', 'role']);

    if ($fullNameIndex === null && $firstNameIndex === null && $lastNameIndex === null && $studentNumberIndex === null && $roleIndex === null) {
        return null;
    }

    return [
        'fullNameIndex' => $fullNameIndex,
        'firstNameIndex' => $firstNameIndex,
        'lastNameIndex' => $lastNameIndex,
        'studentNumberIndex' => $studentNumberIndex,
        'roleIndex' => $roleIndex,
    ];
}

function dent_import_user_name_parts_v2(string $fullName, int $rowNumber): array
{
    $fullName = dent_clean_text($fullName, 120);
    if ($fullName === '') {
        dent_error('نام ردیف ' . $rowNumber . ' خالی است.', 422);
    }

    $tokens = preg_split('/\s+/u', $fullName) ?: [];
    $tokens = array_values(array_filter($tokens, static function ($token): bool {
        return trim((string) $token) !== '';
    }));
    if (count($tokens) < 2) {
        dent_error('نام کامل ردیف ' . $rowNumber . ' باید حداقل دو بخش داشته باشد.', 422);
    }

    return [
        'firstName' => (string) $tokens[0],
        'lastName' => implode(' ', array_slice($tokens, 1)),
    ];
}

function dent_import_user_entry_from_full_name_v2(string $fullName, string $studentNumber, string $roleLabel, string $cohortKey, int $rowNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی ردیف ' . $rowNumber . ' نامعتبر است.', 422);
    }

    $nameParts = dent_import_user_name_parts_v2($fullName, $rowNumber);

    return [
        'firstName' => $nameParts['firstName'],
        'lastName' => $nameParts['lastName'],
        'studentNumber' => $studentNumber,
        'role' => dent_import_role_from_label($roleLabel, $cohortKey),
    ];
}

function dent_import_user_entry_from_name_columns_v2(string $firstName, string $lastName, string $studentNumber, string $roleLabel, string $cohortKey, int $rowNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی ردیف ' . $rowNumber . ' نامعتبر است.', 422);
    }

    $firstName = dent_clean_text($firstName, 60);
    $lastName = dent_clean_text($lastName, 60);
    if ($firstName === '' || $lastName === '') {
        dent_error('نام و نام خانوادگی ردیف ' . $rowNumber . ' کامل نیست.', 422);
    }

    return [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'studentNumber' => $studentNumber,
        'role' => dent_import_role_from_label($roleLabel, $cohortKey),
    ];
}

function dent_parse_import_user_row_v2(array $parts, string $cohortKey, ?array $headerMap, int $rowNumber): ?array
{
    $parts = dent_import_user_normalize_row_v2($parts);
    if ($parts === []) {
        return null;
    }

    if ($headerMap !== null) {
        $studentNumberIndex = $headerMap['studentNumberIndex'];
        $fullNameIndex = $headerMap['fullNameIndex'];
        $firstNameIndex = $headerMap['firstNameIndex'];
        $lastNameIndex = $headerMap['lastNameIndex'];
        $roleIndex = $headerMap['roleIndex'];

        $studentNumber = $studentNumberIndex === null ? '' : (string) ($parts[$studentNumberIndex] ?? '');
        if ($studentNumber === '') {
            return null;
        }
        $roleLabel = $roleIndex === null ? '' : (string) ($parts[$roleIndex] ?? '');

        if ($fullNameIndex !== null) {
            return dent_import_user_entry_from_full_name_v2(
                (string) ($parts[$fullNameIndex] ?? ''),
                $studentNumber,
                $roleLabel,
                $cohortKey,
                $rowNumber
            );
        }
        if ($firstNameIndex === null || $lastNameIndex === null) {
            dent_error('ستون‌های نام کاربران برای import کامل نیست.', 422);
        }

        return dent_import_user_entry_from_name_columns_v2(
            (string) ($parts[$firstNameIndex] ?? ''),
            (string) ($parts[$lastNameIndex] ?? ''),
            $studentNumber,
            $roleLabel,
            $cohortKey,
            $rowNumber
        );
    }

    if (count($parts) === 1) {
        return null;
    }

    if (count($parts) >= 4) {
        return dent_import_user_entry_from_name_columns_v2(
            (string) ($parts[0] ?? ''),
            (string) ($parts[1] ?? ''),
            (string) ($parts[2] ?? ''),
            (string) ($parts[3] ?? ''),
            $cohortKey,
            $rowNumber
        );
    }

    if (count($parts) === 3) {
        $first = (string) ($parts[0] ?? '');
        $second = (string) ($parts[1] ?? '');
        $third = (string) ($parts[2] ?? '');
        if (dent_normalize_student_number($second) !== '') {
            return dent_import_user_entry_from_full_name_v2($first, $second, $third, $cohortKey, $rowNumber);
        }
        if (dent_normalize_student_number($first) !== '') {
            return dent_import_user_entry_from_full_name_v2($second, $first, $third, $cohortKey, $rowNumber);
        }
        if (dent_normalize_student_number($third) !== '') {
            return dent_import_user_entry_from_name_columns_v2($first, $second, $third, '', $cohortKey, $rowNumber);
        }
    }

    if (count($parts) === 2) {
        $first = (string) ($parts[0] ?? '');
        $second = (string) ($parts[1] ?? '');
        if (dent_normalize_student_number($second) !== '') {
            return dent_import_user_entry_from_full_name_v2($first, $second, '', $cohortKey, $rowNumber);
        }
        if (dent_normalize_student_number($first) !== '') {
            return dent_import_user_entry_from_full_name_v2($second, $first, '', $cohortKey, $rowNumber);
        }
    }

    dent_error('فرمت ردیف ' . $rowNumber . ' برای import کاربران نامعتبر است.', 422);
}

function dent_parse_import_user_tabular_rows_v2(array $rows, string $cohortKey): array
{
    $entries = [];
    $headerMap = null;

    foreach ($rows as $index => $row) {
        $parts = is_array($row)
            ? dent_import_user_normalize_row_v2($row)
            : dent_import_user_split_text_row_v2(trim((string) $row));
        if ($parts === []) {
            continue;
        }

        if ($headerMap === null) {
            $headerMap = dent_import_user_header_map_v2($parts);
            if ($headerMap !== null) {
                continue;
            }
        }

        $entry = dent_parse_import_user_row_v2($parts, $cohortKey, $headerMap, $index + 1);
        if ($entry !== null) {
            $entries[] = $entry;
        }
    }

    if ($entries === []) {
        dent_error('هیچ ردیف معتبر کاربری برای import پیدا نشد.', 422);
    }

    return $entries;
}

function dent_parse_import_user_rows_v2(string $text, string $cohortKey): array
{
    $rows = preg_split('/\r\n|\r|\n/u', $text) ?: [];
    return dent_parse_import_user_tabular_rows_v2($rows, $cohortKey);
}

function dent_parse_import_user_rows_from_xlsx_v2(string $path, string $cohortKey): array
{
    return dent_parse_import_user_tabular_rows_v2(dent_xlsx_read_rows($path), $cohortKey);
}

function dent_management_grade_roster_by_cohort(array $users): array
{
    $cohorts = [];
    foreach ($users as $user) {
        $cohortKey = dent_requested_cohort_key((string) ($user['cohortKey'] ?? ''));
        if ($cohortKey !== '') {
            $cohorts[$cohortKey] = true;
        }
    }

    $rosters = [];
    foreach (array_keys($cohorts) as $cohortKey) {
        dent_grades_set_active_cohort($cohortKey);
        $rosters[$cohortKey] = dent_grade_roster_index();
    }

    return $rosters;
}

$action = dent_request_action();

if ($action === 'login') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ورود نامعتبر است.', 405);
    }

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? $_POST['username'] ?? '');
    $password = dent_normalize_digits($_POST['password'] ?? '');

    if ($studentNumber === '' || $password === '') {
        dent_error('شماره دانشجویی و رمز عبور را وارد کن.', 422);
    }

    $user = dent_verify_credentials($studentNumber, $password);
    if ($user === null) {
        dent_error('شماره دانشجویی یا رمز عبور اشتباه است.', 401, ['loggedOut' => true]);
    }

    $publicUser = dent_login_user($user);
    analytics_record_login($user, 'password');

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => dent_auth_status($user),
        'user' => $publicUser,
        'availableCohorts' => dent_visible_cohorts_for_user($publicUser),
        'siteSettings' => [
            'appearance' => dent_site_appearance_public_settings(),
        ],
    ]);
}

if ($action === 'logout') {
    dent_logout_user();

    dent_json_response([
        'success' => true,
        'loggedIn' => false,
        'status' => 'logged-out',
        'siteSettings' => [
            'appearance' => dent_site_appearance_public_settings(),
        ],
    ]);
}

if ($action === 'authSessions') {
    if (dent_request_method() !== 'GET') {
        dent_error('متد دریافت نشست‌ها نامعتبر است.', 405);
    }
    $user = dent_require_user();
    $payload = dent_auth_sessions_for_user($user);
    dent_json_response([
        'success' => true,
        'sessions' => $payload['sessions'] ?? [],
        'currentSessionId' => (string) ($payload['currentSessionId'] ?? ''),
        'csrfToken' => dent_auth_session_csrf_token(),
    ]);
}

if ($action === 'revokeAuthSession') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد پایان نشست نامعتبر است.', 405);
    }
    dent_auth_session_require_csrf();
    $user = dent_require_user();
    $result = dent_auth_session_revoke($user, (string) ($_POST['sessionId'] ?? ''));
    dent_json_response(['success' => true] + $result);
}

if ($action === 'revokeOtherAuthSessions') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد پایان نشست‌ها نامعتبر است.', 405);
    }
    dent_auth_session_require_csrf();
    $user = dent_require_user();
    $result = dent_auth_session_revoke_others($user);
    dent_json_response(['success' => true] + $result);
}

if ($action === 'me') {
    $user = dent_current_user();
    dent_release_session_lock();

    if ($user === null) {
        dent_json_response([
            'success' => true,
            'loggedIn' => false,
            'status' => 'logged-out',
            'siteSettings' => [
                'appearance' => dent_site_appearance_public_settings(),
            ],
        ]);
    }

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => dent_auth_status($user),
        'user' => dent_public_user($user),
        'availableCohorts' => dent_visible_cohorts_for_user($user),
        'siteSettings' => [
            'appearance' => dent_site_appearance_public_settings(),
        ],
    ]);
}

if ($action === 'updateProfile') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد به‌روزرسانی پروفایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $profileInput = [
        'contactHandle' => $_POST['contactHandle'] ?? '',
        'focusArea' => $_POST['focusArea'] ?? '',
    ];

    if (array_key_exists('about', $_POST) || array_key_exists('bio', $_POST)) {
        $profileInput['about'] = $_POST['about'] ?? ($_POST['bio'] ?? '');
        $profileInput['bio'] = $_POST['bio'] ?? ($_POST['about'] ?? '');
    }

    if (array_key_exists('avatarUrl', $_POST)) {
        $profileInput['avatarUrl'] = $_POST['avatarUrl'];
    }

    $updatedUser = dent_update_profile($user, $profileInput);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'اطلاعات حساب کاربری ذخیره شد.',
    ]);
}

if ($action === 'changePassword') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر رمز نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $updatedUser = dent_change_user_password(
        $user,
        (string) ($_POST['currentPassword'] ?? ''),
        (string) ($_POST['newPassword'] ?? '')
    );
    dent_auth_session_mark_current_revoked('password-changed-session-rotation');
    session_regenerate_id(true);
    $_SESSION['student_number'] = $updatedUser['studentNumber'];
    $_SESSION['auth_at'] = time();
    unset($_SESSION['auth_session_public_id'], $_SESSION['auth_session_touch_at'], $_SESSION['auth_session_csrf_token']);
    dent_auth_session_register((string) $updatedUser['studentNumber'], true);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'رمز عبور با موفقیت تغییر کرد.',
    ]);
}

if ($action === 'requestLoginOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست کد ورود نامعتبر است.', 405);
    }

    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $result = dent_request_login_otp($phoneNumber);

    dent_json_response([
        'success' => true,
        'message' => 'کد ورود پیامکی ارسال شد.',
        'phoneMasked' => (string) ($result['phoneMasked'] ?? ''),
        'cooldownSeconds' => (int) ($result['cooldownSeconds'] ?? 0),
        'expiresInSeconds' => (int) ($result['expiresInSeconds'] ?? 0),
    ]);
}

if ($action === 'verifyLoginOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تایید کد ورود نامعتبر است.', 405);
    }

    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $otpCode = (string) ($_POST['otpCode'] ?? ($_POST['code'] ?? ''));
    $loggedInUser = dent_verify_login_otp($phoneNumber, $otpCode);
    analytics_record_login($loggedInUser, 'otp');

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => 'logged-in',
        'user' => $loggedInUser,
        'availableCohorts' => dent_visible_cohorts_for_user($loggedInUser),
        'message' => 'ورود با کد تایید انجام شد.',
    ]);
}

if ($action === 'requestPasswordResetOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست کد بازیابی رمز نامعتبر است.', 405);
    }

    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $result = dent_request_password_reset_otp($phoneNumber);

    dent_json_response([
        'success' => true,
        'message' => 'کد بازیابی رمز پیامکی ارسال شد.',
        'phoneMasked' => (string) ($result['phoneMasked'] ?? ''),
        'cooldownSeconds' => (int) ($result['cooldownSeconds'] ?? 0),
        'expiresInSeconds' => (int) ($result['expiresInSeconds'] ?? 0),
    ]);
}

if ($action === 'resetPasswordWithOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بازیابی رمز نامعتبر است.', 405);
    }

    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $otpCode = (string) ($_POST['otpCode'] ?? ($_POST['code'] ?? ''));
    $loggedInUser = dent_verify_password_reset_otp(
        $phoneNumber,
        $otpCode,
        (string) ($_POST['newPassword'] ?? ''),
        (string) ($_POST['confirmPassword'] ?? $_POST['passwordConfirm'] ?? '')
    );
    analytics_record_login($loggedInUser, 'password-reset');

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => 'logged-in',
        'user' => $loggedInUser,
        'availableCohorts' => dent_visible_cohorts_for_user($loggedInUser),
        'message' => 'رمز عبور تغییر کرد و ورود انجام شد.',
    ]);
}

if ($action === 'requestExternalSignupOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست کد ثبت‌نام نامعتبر است.', 405);
    }

    $result = dent_request_external_signup_otp(
        (string) ($_POST['firstName'] ?? ''),
        (string) ($_POST['lastName'] ?? ''),
        (string) ($_POST['phoneNumber'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['passwordConfirm'] ?? $_POST['confirmPassword'] ?? '')
    );

    dent_json_response([
        'success' => true,
        'message' => 'کد تایید ثبت‌نام پیامکی ارسال شد.',
        'phoneMasked' => (string) ($result['phoneMasked'] ?? ''),
        'username' => (string) ($result['username'] ?? ''),
        'cooldownSeconds' => (int) ($result['cooldownSeconds'] ?? 0),
        'expiresInSeconds' => (int) ($result['expiresInSeconds'] ?? 0),
    ]);
}

if ($action === 'verifyExternalSignupOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تکمیل ثبت‌نام نامعتبر است.', 405);
    }

    $loggedInUser = dent_verify_external_signup_otp(
        (string) ($_POST['firstName'] ?? ''),
        (string) ($_POST['lastName'] ?? ''),
        (string) ($_POST['phoneNumber'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['passwordConfirm'] ?? $_POST['confirmPassword'] ?? ''),
        (string) ($_POST['otpCode'] ?? ($_POST['code'] ?? ''))
    );
    analytics_record_login($loggedInUser, 'external-signup');

    dent_json_response([
        'success' => true,
        'loggedIn' => true,
        'status' => 'logged-in',
        'user' => $loggedInUser,
        'availableCohorts' => dent_visible_cohorts_for_user($loggedInUser),
        'message' => 'ثبت‌نام تکمیل شد.',
    ]);
}

if ($action === 'requestPhoneEnrollOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست تایید شماره نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $result = dent_request_phone_enrollment_otp($user, $phoneNumber);

    dent_json_response([
        'success' => true,
        'message' => 'کد تایید شماره موبایل ارسال شد.',
        'phoneMasked' => (string) ($result['phoneMasked'] ?? ''),
        'cooldownSeconds' => (int) ($result['cooldownSeconds'] ?? 0),
        'expiresInSeconds' => (int) ($result['expiresInSeconds'] ?? 0),
    ]);
}

if ($action === 'verifyPhoneEnrollOtp') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تایید شماره موبایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $phoneNumber = (string) ($_POST['phoneNumber'] ?? '');
    $otpCode = (string) ($_POST['otpCode'] ?? ($_POST['code'] ?? ''));
    $updatedUser = dent_verify_phone_enrollment_otp($user, $phoneNumber, $otpCode);
    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'شماره موبایل تایید شد.',
    ]);
}

if ($action === 'setPhoneLoginEnabled') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر وضعیت ورود پیامکی نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    $updatedUser = dent_set_phone_login_enabled($user, $enabled);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => $enabled ? 'ورود با کد تایید فعال شد.' : 'ورود با کد تایید غیرفعال شد.',
    ]);
}

if ($action === 'removePhoneNumber') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف شماره موبایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $updatedUser = dent_remove_phone_number($user);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'شماره موبایل از حساب حذف شد.',
    ]);
}

if ($action === 'dismissPhoneNudge') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد بستن یادآوری شماره موبایل نامعتبر است.', 405);
    }

    $user = dent_require_user();
    $updatedUser = dent_dismiss_phone_nudge($user);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'یادآوری ثبت شماره موبایل بسته شد.',
    ]);
}

if ($action === 'smsStatus') {
    $viewer = dent_require_cohort_manager(dent_requested_cohort_key());

    dent_json_response([
        'success' => true,
        'status' => dent_sms_status_payload(),
    ]);
}

if ($action === 'saveSmsConfig') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ذخیره تنظیمات پیامک نامعتبر است.', 405);
    }

    $viewer = dent_require_owner();
    $status = dent_save_sms_owner_config([
        'enabled' => $_POST['enabled'] ?? '0',
        'apiKey' => $_POST['apiKey'] ?? '',
        'clearApiKey' => $_POST['clearApiKey'] ?? '0',
        'patternCode' => $_POST['patternCode'] ?? '',
        'senderLine' => $_POST['senderLine'] ?? '',
        'domain' => $_POST['domain'] ?? '',
        'codeParam' => $_POST['codeParam'] ?? 'code',
    ]);

    dent_json_response([
        'success' => true,
        'status' => $status,
        'message' => 'تنظیمات سرویس پیامک ذخیره شد.',
    ]);
}

if ($action === 'smsHealthCheck') {
    if (!in_array(dent_request_method(), ['POST', 'GET'], true)) {
        dent_error('متد بررسی سلامت پیامک نامعتبر است.', 405);
    }

    $viewer = dent_require_owner();
    $phoneNumber = (string) ($_POST['phoneNumber'] ?? ($_GET['phoneNumber'] ?? ''));
    $health = dent_sms_health_check($phoneNumber === '' ? null : $phoneNumber);

    dent_json_response([
        'success' => (bool) ($health['success'] ?? false),
        'message' => (string) ($health['message'] ?? ''),
        'status' => $health['status'] ?? dent_sms_status_payload(),
    ]);
}

if ($action === 'users') {
    require_once __DIR__ . '/exams_store.php';
    $viewer = dent_require_user();
    $activeCohortKey = dent_resolve_accessible_cohort($viewer, dent_requested_cohort_key());
    if (!dent_user_has_cohort_management_access($viewer, $activeCohortKey)) {
        dent_error('این بخش فقط برای مالک یا نماینده مجاز همان ورودی فعال است.', 403);
    }

    $users = dent_management_visible_users($viewer, true);
    $gradeRosters = dent_management_grade_roster_by_cohort($users);
    $examsStore = dent_exams_read_store();
    $examLearningSummaryIndex = dent_exams_learning_summary_index($examsStore);
    $disPrivateIndex = dent_owner_dis_request_private_index();
    $representativeCount = 0;
    $withNationalCodeCount = 0;
    $withDirectoryPhoneCount = 0;

    foreach ($users as &$user) {
        $studentNumber = (string) ($user['studentNumber'] ?? '');
        $userCohortKey = dent_requested_cohort_key((string) ($user['cohortKey'] ?? ''));
        $cohortGradeRoster = is_array($gradeRosters[$userCohortKey] ?? null) ? $gradeRosters[$userCohortKey] : [];
        $hasGrades = isset($cohortGradeRoster[$studentNumber]);
        $user['hasGrades'] = $hasGrades;
        $phone = is_array($user['phone'] ?? null) ? $user['phone'] : [];
        $user['hasPhone'] = !empty($phone['hasNumber']);
        $ownerPrivate = is_array($user['ownerPrivate'] ?? null) ? $user['ownerPrivate'] : [];
        $disPrivate = is_array($disPrivateIndex[$studentNumber] ?? null) ? $disPrivateIndex[$studentNumber] : [];
        if (empty($ownerPrivate['nationalCode']) && !empty($disPrivate['nationalCode'])) {
            $ownerPrivate['nationalCode'] = (string) $disPrivate['nationalCode'];
        }
        if (empty($ownerPrivate['directoryPhoneNumber']) && !empty($disPrivate['directoryPhoneNumber'])) {
            $ownerPrivate['directoryPhoneNumber'] = (string) $disPrivate['directoryPhoneNumber'];
        }
        $ownerPrivate['hasNationalCode'] = !empty($ownerPrivate['nationalCode']);
        $ownerPrivate['hasDirectoryPhone'] = !empty($ownerPrivate['directoryPhoneNumber']);
        $ownerPrivate['nationalCodeMasked'] = $ownerPrivate['hasNationalCode']
            ? ('******' . substr((string) $ownerPrivate['nationalCode'], -4))
            : '';
        $ownerPrivate['directoryPhoneMasked'] = $ownerPrivate['hasDirectoryPhone']
            ? dent_mask_phone_number((string) $ownerPrivate['directoryPhoneNumber'])
            : '';
        if (!empty($disPrivate['source'])) {
            $ownerPrivate['source'] = (string) $disPrivate['source'];
        }
        $user['ownerPrivate'] = $ownerPrivate;
        $user['hasNationalCode'] = !empty($ownerPrivate['hasNationalCode']);
        $user['hasDirectoryPhone'] = !empty($ownerPrivate['hasDirectoryPhone']);
        $user['examLearningSummary'] = is_array($examLearningSummaryIndex[$studentNumber] ?? null)
            ? $examLearningSummaryIndex[$studentNumber]
            : [
                'examCount' => 0,
                'attemptCount' => 0,
                'noteCount' => 0,
                'highlightCount' => 0,
                'struckOptionCount' => 0,
                'mistakeQuestionCount' => 0,
            ];
        if ($user['hasNationalCode']) {
            $withNationalCodeCount++;
        }
        if ($user['hasDirectoryPhone']) {
            $withDirectoryPhoneCount++;
        }
        if (in_array((string) ($user['role'] ?? ''), ['representative', 'prosthesis_representative'], true)) {
            $representativeCount++;
        }
    }
    unset($user);

    $cohortCards = dent_management_cohort_cards($viewer, $users);
    dent_grades_set_active_cohort($activeCohortKey);

    dent_json_response([
        'success' => true,
        'viewer' => dent_public_user($viewer),
        'users' => $users,
        'cohorts' => $cohortCards,
        'availableCohorts' => dent_visible_cohorts_for_user($viewer),
        'activeCohortKey' => $activeCohortKey,
        'rotationCatalog' => dent_cohort_supports_rotation_groups($activeCohortKey) ? dent_rotation_group_options() : [],
        'gradeCourses' => dent_owner_grades_course_catalog(),
        'campusLabel' => 'دانشجوی پردیس',
        'summary' => [
            'totalUsers' => count($users),
            'representatives' => $representativeCount,
            'withNationalCode' => $withNationalCodeCount,
            'withDirectoryPhone' => $withDirectoryPhoneCount,
            'ownerStudentNumber' => dent_owner_student_number(),
        ],
    ]);
}

if ($action === 'ownerUserGrades') {
    if (!in_array(dent_request_method(), ['POST', 'GET'], true)) {
        dent_error('متد دریافت کارنامه کاربر نامعتبر است.', 405);
    }

    $viewer = dent_require_user();

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? ($_GET['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    dent_require_manage_target_user($viewer, $studentNumber);
    dent_grades_set_active_cohort(dent_user_cohort_key($user));
    $grades = dent_owner_grades_payload($studentNumber, (string) ($user['name'] ?? ''));

    dent_json_response([
        'success' => true,
        'studentNumber' => $studentNumber,
        'grades' => $grades,
    ]);
}

if ($action === 'ownerClearUserExamStudy') {
    require_once __DIR__ . '/exams_store.php';
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف داده‌های مطالعه آزمون نامعتبر است.', 405);
    }
    $viewer = dent_require_owner();
    dent_release_session_lock();
    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }
    dent_require_manage_target_user($viewer, $studentNumber);

    dent_exams_with_store_lock(static function (array &$store) use ($studentNumber): void {
        $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
        foreach ($records as $examKey => $record) {
            if (!is_array($record)) {
                continue;
            }
            $normalized = dent_exams_normalize_exam_record($record);
            unset($normalized['studyStateByUser'][$studentNumber]);
            $store['examRecords'][$examKey] = $normalized;
        }
    });

    $freshStore = dent_exams_read_store();
    dent_json_response([
        'success' => true,
        'studentNumber' => $studentNumber,
        'examLearningSummary' => dent_exams_user_learning_summary($freshStore, $studentNumber),
        'message' => 'یادداشت‌ها، هایلایت‌ها، گزینه‌های خط‌خورده و دفترچه اشتباهات این کاربر حذف شد. تاریخچه تلاش‌ها حفظ شد.',
    ]);
}

if ($action === 'setRepresentative') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر نماینده نامعتبر است.', 405);
    }

    $viewer = dent_require_user();
    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    $representative = (string) ($_POST['representative'] ?? '0') === '1';
    dent_require_manage_target_user($viewer, $studentNumber);
    $updatedUser = dent_set_representative_status($studentNumber, $representative);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => $representative ? 'نماینده ثبت شد.' : 'نماینده از این حساب برداشته شد.',
    ]);
}

if ($action === 'ownerSetUserPassword') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر رمز کاربر نامعتبر است.', 405);
    }

    $viewer = dent_require_user();
    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    $newPassword = (string) ($_POST['newPassword'] ?? '');
    dent_require_manage_target_user($viewer, $studentNumber);
    $updatedUser = dent_owner_set_user_password($studentNumber, $newPassword);

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($updatedUser),
        'message' => 'رمز عبور کاربر ذخیره شد.',
    ]);
}

if ($action === 'ownerSetUserRotation') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تنظیم روتیشن/گروه کاربر نامعتبر است.', 405);
    }

    $viewer = dent_require_user();
    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    dent_require_manage_target_user($viewer, $studentNumber);
    $rotationMode = (string) ($_POST['rotationMode'] ?? 'none');
    $rotationIdRaw = $_POST['rotationId'] ?? null;
    $groupNumberRaw = $_POST['groupNumber'] ?? null;
    $rotationId = ($rotationIdRaw === null || $rotationIdRaw === '') ? null : (int) $rotationIdRaw;
    $groupNumber = ($groupNumberRaw === null || $groupNumberRaw === '') ? null : (int) $groupNumberRaw;

    $updatedUser = dent_owner_set_user_rotation($studentNumber, $rotationMode, $rotationId, $groupNumber);
    $publicUser = dent_public_user($updatedUser);
    $summary = trim((string) (($publicUser['rotation']['summary'] ?? '') ?: 'بدون روتیشن/گروه'));

    dent_json_response([
        'success' => true,
        'user' => $publicUser,
        'message' => 'روتیشن/گروه کاربر به‌روزرسانی شد: ' . $summary,
    ]);
}

if ($action === 'ownerMarkCampusStudents') {
    if (!in_array(dent_request_method(), ['POST', 'GET'], true)) {
        dent_error('متد تخصیص دانشجوی پردیس نامعتبر است.', 405);
    }

    dent_require_owner();

    $result = dent_mark_unassigned_students_as_campus();

    dent_json_response([
        'success' => true,
        'updatedCount' => (int) ($result['count'] ?? 0),
        'updatedStudentNumbers' => $result['studentNumbers'] ?? [],
        'updatedUsers' => $result['users'] ?? [],
        'message' => 'تخصیص دانشجوی پردیس انجام شد.',
    ]);
}

if ($action === 'createStudent') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ایجاد حساب دانشجو نامعتبر است.', 405);
    }

    $firstName = (string) ($_POST['firstName'] ?? '');
    $lastName = (string) ($_POST['lastName'] ?? '');
    $studentNumber = (string) ($_POST['studentNumber'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $cohortKey = dent_requested_cohort_key((string) ($_POST['cohortKey'] ?? dent_requested_cohort_key()));
    dent_require_cohort_manager($cohortKey);
    $role = (string) ($_POST['role'] ?? 'student');
    $nationalCode = (string) ($_POST['nationalCode'] ?? '');
    $directoryPhoneNumber = (string) ($_POST['directoryPhoneNumber'] ?? '');
    $rotationMode = (string) ($_POST['rotationMode'] ?? 'none');
    $rotationIdRaw = $_POST['rotationId'] ?? null;
    $groupNumberRaw = $_POST['groupNumber'] ?? null;
    $rotationId = ($rotationIdRaw === null || $rotationIdRaw === '') ? null : (int) $rotationIdRaw;
    $groupNumber = ($groupNumberRaw === null || $groupNumberRaw === '') ? null : (int) $groupNumberRaw;

    $created = dent_create_student_account(
        $firstName,
        $lastName,
        $studentNumber,
        $password,
        $cohortKey,
        $role,
        $rotationMode,
        $rotationId,
        $groupNumber,
        $nationalCode,
        $directoryPhoneNumber
    );

    dent_json_response([
        'success' => true,
        'user' => dent_public_user($created),
        'message' => 'حساب دانشجو ایجاد شد.',
    ]);
}

if ($action === 'createCohort') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ساخت ورودی جدید نامعتبر است.', 405);
    }

    dent_require_owner();
    $created = dent_create_cohort([
        'key' => $_POST['key'] ?? '',
        'title' => $_POST['title'] ?? '',
        'shortTitle' => $_POST['shortTitle'] ?? '',
        'description' => $_POST['description'] ?? '',
        'productType' => $_POST['productType'] ?? 'dentistry',
        'year' => $_POST['year'] ?? '',
        'notesMode' => $_POST['notesMode'] ?? '',
        'allowRepresentativeManagement' => $_POST['allowRepresentativeManagement'] ?? '1',
        'services' => [
            'notes' => $_POST['serviceNotes'] ?? '',
            'forms' => $_POST['serviceForms'] ?? '',
            'grades' => $_POST['serviceGrades'] ?? '',
            'navid' => $_POST['serviceNavid'] ?? '',
            'buy' => $_POST['serviceBuy'] ?? '',
        ],
        'sortOrder' => $_POST['sortOrder'] ?? '',
    ]);

    dent_json_response([
        'success' => true,
        'cohort' => $created,
        'message' => 'ورودی جدید ساخته شد.',
    ]);
}

if ($action === 'importCohortUsers') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ورود گروهی کاربران نامعتبر است.', 405);
    }

    $cohortKey = dent_requested_cohort_key((string) ($_POST['cohortKey'] ?? ''));
    dent_require_cohort_manager($cohortKey);

    $entries = [];
    $file = $_FILES['usersFile'] ?? null;
    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            dent_error('آپلود فایل کاربران انجام نشد.', 422);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            dent_error('فایل کاربران نامعتبر است.', 422);
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $entries = dent_parse_import_user_rows_from_xlsx_v2($tmpName, $cohortKey);
        } else {
            $content = file_get_contents($tmpName);
            if ($content === false) {
                dent_error('خواندن فایل کاربران انجام نشد.', 422);
            }
            $entries = dent_parse_import_user_rows_v2($content, $cohortKey);
        }
    }

    $importText = trim((string) ($_POST['importText'] ?? ''));
    if ($entries === [] && $importText === '') {
        dent_error('متن یا فایل ورود گروهی کاربران خالی است.', 422);
    }
    if ($entries === []) {
        $entries = dent_parse_import_user_rows_v2($importText, $cohortKey);
    }

    $defaultPassword = trim((string) ($_POST['defaultPassword'] ?? ''));
    if ($defaultPassword === '') {
        $defaultPassword = '12345678';
    }

    $replaceExisting = dent_parse_bool($_POST['replaceExisting'] ?? false, false);
    $result = dent_owner_sync_cohort_users($cohortKey, $entries, $defaultPassword, $replaceExisting);
    dent_grades_set_active_cohort($cohortKey);
    foreach (($result['removedStudentNumbers'] ?? []) as $removedStudentNumber) {
        dent_owner_remove_grades_row((string) $removedStudentNumber);
    }

    dent_json_response([
        'success' => true,
        'count' => (int) ($result['count'] ?? 0),
        'createdCount' => (int) ($result['createdCount'] ?? 0),
        'updatedCount' => (int) ($result['updatedCount'] ?? 0),
        'removedCount' => (int) ($result['removedCount'] ?? 0),
        'message' => $replaceExisting ? 'همگام‌سازی کامل کاربران ورودی انجام شد.' : 'ورود گروهی کاربران انجام شد.',
    ]);
}

if ($action === 'importCohortUsersV1Legacy') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ورود گروهی کاربران نامعتبر است.', 405);
    }

    $cohortKey = dent_requested_cohort_key((string) ($_POST['cohortKey'] ?? ''));
    dent_require_cohort_manager($cohortKey);

    $importText = trim((string) ($_POST['importText'] ?? ''));
    if ($importText === '') {
        dent_error('متن ورود گروهی کاربران خالی است.', 422);
    }

    $defaultPassword = trim((string) ($_POST['defaultPassword'] ?? ''));
    if ($defaultPassword === '') {
        $defaultPassword = '12345678';
    }

    $createdUsers = [];
    foreach (dent_parse_import_user_rows($importText, $cohortKey) as $entry) {
        $createdUsers[] = dent_public_user(dent_create_student_account(
            (string) ($entry['firstName'] ?? ''),
            (string) ($entry['lastName'] ?? ''),
            (string) ($entry['studentNumber'] ?? ''),
            $defaultPassword,
            $cohortKey,
            (string) ($entry['role'] ?? 'student')
        ));
    }

    dent_json_response([
        'success' => true,
        'createdUsers' => $createdUsers,
        'count' => count($createdUsers),
        'message' => 'ورود گروهی کاربران انجام شد.',
    ]);
}

if ($action === 'ownerSetUserGrade') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت نمره کاربر نامعتبر است.', 405);
    }

    $viewer = dent_require_user();

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $columnIndex = (int) ($_POST['columnIndex'] ?? -1);
    $gradeValue = (string) ($_POST['gradeValue'] ?? '');
    $user = dent_get_user_record($studentNumber);
    if ($user === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }

    dent_require_manage_target_user($viewer, $studentNumber);
    dent_grades_set_active_cohort(dent_user_cohort_key($user));
    $grades = dent_owner_set_grade($studentNumber, $columnIndex, $gradeValue, (string) ($user['name'] ?? ''));
    $updatedUser = dent_get_user_record($studentNumber);
    $publicUser = $updatedUser ? dent_public_user($updatedUser) : dent_public_user($user);
    $publicUser['hasGrades'] = dent_grade_payload_has_values($grades);
    $publicUser['hasPhone'] = !empty(($publicUser['phone'] ?? [])['hasNumber']);

    dent_json_response([
        'success' => true,
        'grades' => $grades,
        'user' => $publicUser,
        'message' => trim($gradeValue) === '' ? 'نمره پاک شد.' : 'نمره ذخیره شد.',
    ]);
}

if ($action === 'ownerImportGrades') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد import نمرات نامعتبر است.', 405);
    }

    $cohortKey = dent_requested_cohort_key((string) ($_POST['cohortKey'] ?? dent_requested_cohort_key()));
    dent_require_cohort_manager($cohortKey);
    dent_grades_set_active_cohort($cohortKey);

    $result = null;
    $file = $_FILES['gradesFile'] ?? null;
    if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            dent_error('آپلود فایل نمرات انجام نشد.', 422);
        }
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            dent_error('فایل نمرات نامعتبر است.', 422);
        }
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $result = dent_owner_import_grades_from_xlsx($tmpName);
        } else {
            $content = file_get_contents($tmpName);
            if ($content === false) {
                dent_error('خواندن فایل نمرات انجام نشد.', 422);
            }
            $result = dent_owner_import_grades_from_text($content);
        }
    } else {
        $importText = (string) ($_POST['importText'] ?? '');
        if (trim($importText) === '') {
            dent_error('متن import یا فایل نمرات را وارد کن.', 422);
        }
        $result = dent_owner_import_grades_from_text($importText);
    }

    dent_json_response(array_merge($result, [
        'message' => 'Import نمرات انجام شد.',
        'users' => dent_list_public_users(true),
    ]));
}

if ($action === 'ownerDeleteGradeCourse') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف درس نامعتبر است.', 405);
    }

    $cohortKey = dent_requested_cohort_key((string) ($_POST['cohortKey'] ?? dent_requested_cohort_key()));
    dent_require_cohort_manager($cohortKey);
    dent_grades_set_active_cohort($cohortKey);
    $courseKey = (string) ($_POST['courseKey'] ?? '');
    $result = dent_owner_delete_grade_course($courseKey);

    dent_json_response(array_merge($result, [
        'message' => 'درس از همه کارنامه‌ها حذف شد.',
        'users' => dent_list_public_users(true),
    ]));
}

if ($action === 'ownerResetGradebook') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ریست کارنامه نامعتبر است.', 405);
    }

    $cohortKey = dent_requested_cohort_key((string) ($_POST['cohortKey'] ?? dent_requested_cohort_key()));
    dent_require_cohort_manager($cohortKey);
    dent_grades_set_active_cohort($cohortKey);
    $confirm = trim((string) ($_POST['confirm'] ?? ''));
    if ($confirm !== 'RESET') {
        dent_error('برای ریست کامل کارنامه تایید معتبر ارسال نشده است.', 422);
    }

    $result = dent_owner_reset_gradebook();
    dent_json_response(array_merge($result, [
        'message' => 'همه نمرات و درس‌ها از کارنامه حذف شدند.',
        'users' => dent_list_public_users(true),
    ]));
}

if ($action === 'ownerRemoveUserPhone') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف شماره کاربر نامعتبر است.', 405);
    }

    $viewer = dent_require_user();
    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    dent_require_manage_target_user($viewer, $studentNumber);
    $updatedUser = dent_owner_remove_user_phone($studentNumber);
    dent_grades_set_active_cohort(dent_user_cohort_key($updatedUser));
    $publicUser = dent_public_user($updatedUser);
    $publicUser['hasPhone'] = !empty(($publicUser['phone'] ?? [])['hasNumber']);
    $gradeRoster = dent_grade_roster_index();
    $publicUser['hasGrades'] = isset($gradeRoster[(string) ($publicUser['studentNumber'] ?? '')]);

    dent_json_response([
        'success' => true,
        'user' => $publicUser,
        'message' => 'شماره موبایل کاربر حذف شد.',
    ]);
}

if ($action === 'ownerDeleteStudent') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف دانشجو نامعتبر است.', 405);
    }

    $viewer = dent_require_user();

    $studentNumber = dent_normalize_student_number($_POST['studentNumber'] ?? '');
    if ($studentNumber === '') {
        dent_error('شماره دانشجویی نامعتبر است.', 422);
    }

    $targetUser = dent_get_user_record($studentNumber);
    if ($targetUser === null) {
        dent_error('کاربر موردنظر پیدا نشد.', 404);
    }
    dent_require_manage_target_user($viewer, $studentNumber);
    dent_grades_set_active_cohort(dent_user_cohort_key($targetUser));
    dent_owner_delete_student_account($studentNumber);
    dent_owner_remove_grades_row($studentNumber);
    require_once __DIR__ . '/exams_store.php';
    dent_exams_with_store_lock(static function (array &$store) use ($studentNumber): void {
        $records = is_array($store['examRecords'] ?? null) ? $store['examRecords'] : [];
        foreach ($records as $examKey => $record) {
            if (!is_array($record)) {
                continue;
            }
            $normalized = dent_exams_normalize_exam_record($record);
            foreach (['flagsByUser', 'reportsByUser', 'attemptsByUser', 'studyStateByUser', 'activityByUser'] as $mapKey) {
                unset($normalized[$mapKey][$studentNumber]);
            }
            $store['examRecords'][$examKey] = $normalized;
        }
    });

    dent_json_response([
        'success' => true,
        'studentNumber' => $studentNumber,
        'message' => 'حساب دانشجو حذف شد.',
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
