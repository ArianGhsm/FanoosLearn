<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';

const DIS_REQUEST_SCHEMA_VERSION = 1;
const DIS_REQUEST_FORM_TITLE = 'فرم درخواست ایجاد کاربری در DIS';
const DIS_REQUEST_SHARE_PATH = '/dis-request/';
const DIS_REQUEST_MANAGE_PATH = '/dis-request/manage/';

function dis_request_store_path(): string
{
    return dent_storage_path('dis_request/store.json');
}

function dis_request_default_store(): array
{
    return [
        'schemaVersion' => DIS_REQUEST_SCHEMA_VERSION,
        'formOpen' => true,
        'responses' => [],
    ];
}

function dis_request_load_store(): array
{
    $store = dent_read_json_file(dis_request_store_path(), dis_request_default_store());
    if (!isset($store['responses']) || !is_array($store['responses'])) {
        throw new DentJsonPersistenceException(
            'DIS_REQUEST_STORE_SCHEMA_INVALID',
            'Existing DIS request store has an invalid schema'
        );
    }
    $responses = $store['responses'];

    $normalizedResponses = [];
    foreach ($responses as $studentNumber => $response) {
        if (!is_array($response)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($response['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $normalizedResponses[$normalizedStudentNumber] = $response;
    }

    ksort($normalizedResponses, SORT_STRING);

    return [
        'schemaVersion' => DIS_REQUEST_SCHEMA_VERSION,
        'formOpen' => isset($store['formOpen']) ? (bool) $store['formOpen'] : true,
        'responses' => $normalizedResponses,
    ];
}

function dis_request_save_store(array $store): void
{
    $responses = $store['responses'] ?? [];
    if (!is_array($responses)) {
        $responses = [];
    }

    $normalizedResponses = [];
    foreach ($responses as $studentNumber => $response) {
        if (!is_array($response)) {
            continue;
        }

        $normalizedStudentNumber = dent_normalize_student_number((string) ($response['studentNumber'] ?? $studentNumber));
        if ($normalizedStudentNumber === '') {
            continue;
        }

        $normalizedResponses[$normalizedStudentNumber] = $response;
    }

    ksort($normalizedResponses, SORT_STRING);

    dent_write_json_file(dis_request_store_path(), [
        'schemaVersion' => DIS_REQUEST_SCHEMA_VERSION,
        'formOpen' => isset($store['formOpen']) ? (bool) $store['formOpen'] : true,
        'responses' => $normalizedResponses,
    ]);
}

function dis_request_position_options(): array
{
    return [
        'هیئت علمی',
        'دندانپزشک',
        'دانشجو',
        'کارمند',
        'رسمی',
        'قراردادی',
        'شرکتی',
    ];
}

function dis_request_software_options(): array
{
    return [
        'رادیولوژی',
        'DIS',
        'پاتولوژی',
        'داروخانه',
    ];
}

function dis_request_access_options(): array
{
    return [
        'مدیر سیستم',
        'مدیریت درمان',
        'مدیریت کلینیک',
        'دندانپزشکان',
        'مددکاری',
        'انبار داروخانه',
        'صندوق',
        'پذیرش',
        'جهادگر',
        'رزیدنت',
        'دانشجو',
        'حسابداری',
        'آموزش',
        'درآمد',
        'کارشناس DIS',
        'سایر',
    ];
}

function dis_request_approval_labels(): array
{
    return [
        'requester' => 'کاربر درخواست کننده',
        'group_manager' => 'مدیر گروه مربوطه',
        'treatment_manager' => 'مدیر درمان',
        'financial_admin' => 'معاونت مالی اداری',
        'responsible_user_signature' => 'امضا کاربر مسئول',
        'dis_responsible' => 'مسئول DIS',
    ];
}

function dis_request_normalize_text_field($value, int $maxLength): string
{
    return dent_clean_text((string) $value, $maxLength);
}

function dis_request_only_digits($value): string
{
    return preg_replace('/\D+/u', '', dent_normalize_digits((string) $value)) ?? '';
}

function dis_request_to_local_phone($value): string
{
    $digits = dis_request_only_digits($value);
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '9')) {
        $digits = '0' . $digits;
    }

    return $digits;
}

function dis_request_normalize_digits_field($value, int $minLength, int $maxLength, string $label, bool $required = true): string
{
    $digits = dis_request_only_digits($value);
    if ($digits === '') {
        if ($required) {
            dent_error($label . ' الزامی است.', 422);
        }

        return '';
    }
    if (strlen($digits) < $minLength || strlen($digits) > $maxLength) {
        dent_error($label . ' نامعتبر است.', 422);
    }

    return $digits;
}

function dis_request_normalize_phone_field($value, string $label): string
{
    $phone = dis_request_to_local_phone($value);
    if ($phone === '') {
        dent_error($label . ' الزامی است.', 422);
    }
    if (!str_starts_with($phone, '0')) {
        dent_error($label . ' باید با 0 شروع شود.', 422);
    }
    if (strlen($phone) < 10 || strlen($phone) > 14) {
        dent_error($label . ' نامعتبر است.', 422);
    }

    return $phone;
}

function dis_request_phone_for_output($value): string
{
    $phone = dis_request_to_local_phone($value);
    if ($phone === '') {
        return '';
    }

    return str_starts_with($phone, '0') ? $phone : dis_request_only_digits($value);
}

function dis_request_normalize_option_list($raw, array $allowed, string $label, int $maxSelections = 0): array
{
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text !== '') {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\r\n,،;]+/u', $text) ?: [];
            }
        }
    }

    $allowedMap = [];
    foreach ($allowed as $item) {
        $allowedMap[$item] = true;
    }

    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        $clean = dis_request_normalize_text_field($item, 80);
        if ($clean === '' || !isset($allowedMap[$clean]) || isset($seen[$clean])) {
            continue;
        }

        $seen[$clean] = true;
        $normalized[] = $clean;
    }

    if ($normalized === []) {
        dent_error('حداقل یک مورد برای «' . $label . '» الزامی است.', 422);
    }
    if ($maxSelections > 0 && count($normalized) > $maxSelections) {
        dent_error('برای «' . $label . '» فقط انتخاب یک مورد مجاز است.', 422);
    }

    return $normalized;
}

function dis_request_prefill_for_user(array $user): array
{
    $fullName = trim((string) ($user['name'] ?? ''));
    $parts = preg_split('/\s+/u', $fullName) ?: [];
    $parts = array_values(array_filter($parts, static fn($item): bool => trim((string) $item) !== ''));

    $firstName = '';
    $lastName = '';
    if (count($parts) === 1) {
        $firstName = (string) $parts[0];
    } elseif (count($parts) >= 2) {
        $firstName = (string) array_shift($parts);
        $lastName = trim(implode(' ', $parts));
    }

    return [
        'firstName' => dis_request_normalize_text_field($firstName, 60),
        'lastName' => dis_request_normalize_text_field($lastName, 80),
        'phoneNumber' => dis_request_phone_for_output((string) ($user['phoneNumber'] ?? '')),
        'personnelOrStudentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
    ];
}

function dis_request_requester_approval_value(array $fields, array $userSnapshot): string
{
    $fullName = trim(
        dis_request_normalize_text_field((string) ($fields['firstName'] ?? ''), 60)
        . ' '
        . dis_request_normalize_text_field((string) ($fields['lastName'] ?? ''), 80)
    );

    if ($fullName !== '') {
        return $fullName;
    }

    return trim((string) ($userSnapshot['name'] ?? ''));
}

function dis_request_build_approvals(array $existing, array $fields, array $userSnapshot, string $submittedAt): array
{
    $labels = dis_request_approval_labels();
    $approvals = [];

    foreach ($labels as $key => $label) {
        $seed = is_array($existing[$key] ?? null) ? $existing[$key] : [];
        $value = dis_request_normalize_text_field((string) ($seed['value'] ?? ''), 120);
        $status = dis_request_normalize_text_field((string) ($seed['status'] ?? ''), 32);
        $updatedAt = dis_request_normalize_text_field((string) ($seed['updatedAt'] ?? ''), 40);

        if ($key === 'requester') {
            $value = dis_request_requester_approval_value($fields, $userSnapshot);
            $status = 'submitted';
            $updatedAt = $submittedAt;
        }

        $approvals[$key] = [
            'label' => $label,
            'value' => $value,
            'status' => $status !== '' ? $status : 'pending',
            'updatedAt' => $updatedAt,
        ];
    }

    return $approvals;
}

function dis_request_status_text(bool $submitted): string
{
    return $submitted ? 'تکمیل شده' : 'تکمیل نشده';
}

function dis_request_base_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $isSecure = $forwardedProto === 'https'
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    return ($isSecure ? 'https://' : 'http://') . $host;
}

function dis_request_absolute_url(string $path): string
{
    $origin = dis_request_base_origin();
    if ($origin === '') {
        return $path;
    }

    return $origin . $path;
}

function dis_request_public_identity(array $user): array
{
    $public = dent_public_user($user);

    return [
        'studentNumber' => (string) ($public['studentNumber'] ?? ''),
        'name' => (string) ($public['name'] ?? ''),
        'role' => (string) ($public['role'] ?? ''),
        'roleLabel' => (string) ($public['roleLabel'] ?? ''),
    ];
}

function dis_request_response_payload(array $record): array
{
    $fields = is_array($record['fields'] ?? null) ? $record['fields'] : [];
    $approvals = is_array($record['approvals'] ?? null) ? $record['approvals'] : [];
    $userSnapshot = is_array($record['user'] ?? null) ? $record['user'] : [];

    return [
        'studentNumber' => dent_normalize_student_number((string) ($record['studentNumber'] ?? '')),
        'submittedAt' => dis_request_normalize_text_field((string) ($record['submittedAt'] ?? ''), 40),
        'updatedAt' => dis_request_normalize_text_field((string) ($record['updatedAt'] ?? ''), 40),
        'status' => 'submitted',
        'statusLabel' => dis_request_status_text(true),
        'user' => [
            'studentNumber' => dent_normalize_student_number((string) ($userSnapshot['studentNumber'] ?? '')),
            'name' => dis_request_normalize_text_field((string) ($userSnapshot['name'] ?? ''), 120),
            'role' => dis_request_normalize_text_field((string) ($userSnapshot['role'] ?? ''), 32),
            'roleLabel' => dis_request_normalize_text_field((string) ($userSnapshot['roleLabel'] ?? ''), 60),
        ],
        'fields' => [
            'firstName' => dis_request_normalize_text_field((string) ($fields['firstName'] ?? ''), 60),
            'lastName' => dis_request_normalize_text_field((string) ($fields['lastName'] ?? ''), 80),
            'nationalCode' => dis_request_only_digits((string) ($fields['nationalCode'] ?? '')),
            'phoneNumber' => dis_request_phone_for_output((string) ($fields['phoneNumber'] ?? '')),
            'medicalNumber' => dis_request_only_digits((string) ($fields['medicalNumber'] ?? '')),
            'personnelOrStudentNumber' => dis_request_only_digits((string) ($fields['personnelOrStudentNumber'] ?? '')),
            'positions' => array_values(array_filter(
                is_array($fields['positions'] ?? null) ? $fields['positions'] : [],
                static fn($item): bool => trim((string) $item) !== ''
            )),
            'software' => array_values(array_filter(
                is_array($fields['software'] ?? null) ? $fields['software'] : [],
                static fn($item): bool => trim((string) $item) !== ''
            )),
            'accessLevels' => array_values(array_filter(
                is_array($fields['accessLevels'] ?? null) ? $fields['accessLevels'] : [],
                static fn($item): bool => trim((string) $item) !== ''
            )),
            'otherAccessDetail' => dis_request_normalize_text_field((string) ($fields['otherAccessDetail'] ?? ''), 120),
            'notes' => dis_request_normalize_text_field((string) ($fields['notes'] ?? ''), 1000),
        ],
        'approvals' => dis_request_build_approvals($approvals, $fields, $userSnapshot, (string) ($record['submittedAt'] ?? '')),
    ];
}

function dis_request_form_response_for_user(array $store, string $studentNumber): ?array
{
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber);
    if ($normalizedStudentNumber === '') {
        return null;
    }

    $record = $store['responses'][$normalizedStudentNumber] ?? null;
    if (!is_array($record)) {
        return null;
    }

    return dis_request_response_payload($record);
}

function dis_request_collect_input(array $source, array $user): array
{
    $firstName = dis_request_normalize_text_field($source['firstName'] ?? '', 60);
    $lastName = dis_request_normalize_text_field($source['lastName'] ?? '', 80);
    if ($firstName === '' || $lastName === '') {
        dent_error('نام و نام خانوادگی الزامی است.', 422);
    }

    $positions = dis_request_normalize_option_list($source['positions'] ?? [], dis_request_position_options(), 'سمت', 1);
    $software = dis_request_normalize_option_list($source['software'] ?? [], dis_request_software_options(), 'نرم افزار', 1);
    $accessLevels = dis_request_normalize_option_list($source['accessLevels'] ?? [], dis_request_access_options(), 'سطح دسترسی');
    $otherAccessDetail = dis_request_normalize_text_field($source['otherAccessDetail'] ?? '', 120);
    if (in_array('سایر', $accessLevels, true) && $otherAccessDetail === '') {
        dent_error('برای گزینه «سایر» در سطح دسترسی، توضیح کوتاه الزامی است.', 422);
    }

    return [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'nationalCode' => dis_request_normalize_digits_field($source['nationalCode'] ?? '', 10, 10, 'کد ملی'),
        'phoneNumber' => dis_request_normalize_phone_field($source['phoneNumber'] ?? '', 'شماره تلفن'),
        'medicalNumber' => dis_request_normalize_digits_field($source['medicalNumber'] ?? '', 3, 20, 'شماره نظام پزشکی', false),
        'personnelOrStudentNumber' => dis_request_normalize_digits_field($source['personnelOrStudentNumber'] ?? '', 3, 20, 'شماره پرسنلی / شماره دانشجویی'),
        'positions' => $positions,
        'software' => $software,
        'accessLevels' => $accessLevels,
        'otherAccessDetail' => $otherAccessDetail,
        'notes' => dis_request_normalize_text_field($source['notes'] ?? '', 1000),
    ];
}

function dis_request_submit(array $user, array $source): array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $store = dis_request_load_store();

    if (!($store['formOpen'] ?? true)) {
        dent_error('فرم در حال حاضر بسته است و امکان ثبت درخواست وجود ندارد.', 403);
    }

    if (isset($store['responses'][$studentNumber])) {
        dent_error(
            'برای این کاربر قبلا پاسخ ثبت شده است و ارسال تکراری مجاز نیست.',
            409,
            ['response' => dis_request_form_response_for_user($store, $studentNumber)]
        );
    }

    $fields = dis_request_collect_input($source, $user);
    $submittedAt = dent_iso_now();
    $userSnapshot = dis_request_public_identity($user);
    $record = [
        'studentNumber' => $studentNumber,
        'submittedAt' => $submittedAt,
        'updatedAt' => $submittedAt,
        'user' => $userSnapshot,
        'fields' => $fields,
        'approvals' => dis_request_build_approvals([], $fields, $userSnapshot, $submittedAt),
    ];

    $store['responses'][$studentNumber] = $record;
    dis_request_save_store($store);

    // Persist nationalCode and directoryPhoneNumber into the user's auth profile.
    $profileNationalCode = dent_normalize_national_code((string) ($fields['nationalCode'] ?? ''));
    $profilePhone = dent_normalize_phone_number((string) ($fields['phoneNumber'] ?? ''));
    if ($profileNationalCode !== '' || $profilePhone !== '') {
        $profilePatch = ['studentNumber' => $studentNumber];
        if ($profileNationalCode !== '') {
            $profilePatch['nationalCode'] = $profileNationalCode;
        }
        if ($profilePhone !== '') {
            $profilePatch['directoryPhoneNumber'] = $profilePhone;
        }
        dent_persist_user($profilePatch);
    }

    return dis_request_response_payload($record);
}

function dis_request_roster_indexes(): array
{
    $users = dent_list_public_users();
    $byStudentNumber = [];
    foreach ($users as $user) {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if ($studentNumber === '') {
            continue;
        }

        $byStudentNumber[$studentNumber] = $user;
    }

    return [
        'users' => $users,
        'byStudentNumber' => $byStudentNumber,
    ];
}

function dis_request_submitted_summary(array $response): array
{
    $payload = dis_request_response_payload($response);
    $fields = $payload['fields'];
    $fullName = trim($fields['firstName'] . ' ' . $fields['lastName']);

    return [
        'studentNumber' => $payload['studentNumber'],
        'submittedAt' => $payload['submittedAt'],
        'status' => 'submitted',
        'statusLabel' => $payload['statusLabel'],
        'user' => $payload['user'],
        'fullName' => $fullName,
        'positionsText' => implode('، ', $fields['positions']),
        'softwareText' => implode('، ', $fields['software']),
        'accessLevelsText' => implode('، ', $fields['accessLevels']),
    ];
}

function dis_request_pending_summary(array $user): array
{
    return [
        'studentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
        'submittedAt' => '',
        'status' => 'not-submitted',
        'statusLabel' => dis_request_status_text(false),
        'user' => [
            'studentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
            'name' => dis_request_normalize_text_field((string) ($user['name'] ?? ''), 120),
            'role' => dis_request_normalize_text_field((string) ($user['role'] ?? ''), 32),
            'roleLabel' => dis_request_normalize_text_field((string) ($user['roleLabel'] ?? ''), 60),
        ],
        'fullName' => '',
        'positionsText' => '',
        'softwareText' => '',
        'accessLevelsText' => '',
    ];
}

function dis_request_owner_overview(): array
{
    $store = dis_request_load_store();
    $responses = is_array($store['responses'] ?? null) ? $store['responses'] : [];
    $roster = dis_request_roster_indexes();
    $users = $roster['users'];
    $usersByStudentNumber = $roster['byStudentNumber'];

    $submittedUsers = [];
    foreach ($responses as $studentNumber => $record) {
        if (!is_array($record)) {
            continue;
        }
        $submittedUsers[] = dis_request_submitted_summary($record);
    }

    usort($submittedUsers, static function (array $left, array $right): int {
        return strcmp((string) ($right['submittedAt'] ?? ''), (string) ($left['submittedAt'] ?? ''));
    });

    $pendingUsers = [];
    foreach ($users as $user) {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if ($studentNumber === '' || isset($responses[$studentNumber])) {
            continue;
        }
        $pendingUsers[] = dis_request_pending_summary($user);
    }

    usort($pendingUsers, static function (array $left, array $right): int {
        return strcasecmp(
            (string) (($left['user']['name'] ?? '') ?: ($left['studentNumber'] ?? '')),
            (string) (($right['user']['name'] ?? '') ?: ($right['studentNumber'] ?? ''))
        );
    });

    $submittedCount = count($submittedUsers);
    $pendingCount = count($pendingUsers);
    $totalUsers = count($users);
    $completionRate = $totalUsers > 0 ? round(($submittedCount / $totalUsers) * 100, 1) : 0.0;
    $latestSubmissionAt = $submittedUsers !== [] ? (string) ($submittedUsers[0]['submittedAt'] ?? '') : '';

    $orphanedResponses = [];
    foreach ($responses as $studentNumber => $record) {
        if (isset($usersByStudentNumber[$studentNumber])) {
            continue;
        }
        $payload = dis_request_response_payload($record);
        $orphanedResponses[] = [
            'studentNumber' => $payload['studentNumber'],
            'name' => (string) ($payload['user']['name'] ?? ''),
            'submittedAt' => (string) ($payload['submittedAt'] ?? ''),
        ];
    }

    return [
        'shareUrl' => dis_request_absolute_url(DIS_REQUEST_SHARE_PATH),
        'sharePath' => DIS_REQUEST_SHARE_PATH,
        'manageUrl' => dis_request_absolute_url(DIS_REQUEST_MANAGE_PATH),
        'managePath' => DIS_REQUEST_MANAGE_PATH,
        'summary' => [
            'totalUsers' => $totalUsers,
            'submittedCount' => $submittedCount,
            'pendingCount' => $pendingCount,
            'completionRate' => $completionRate,
            'latestSubmissionAt' => $latestSubmissionAt,
            'orphanedResponses' => $orphanedResponses,
        ],
        'submittedUsers' => $submittedUsers,
        'pendingUsers' => $pendingUsers,
    ];
}

function dis_request_export_headers_official(): array
{
    return [
        'ردیف',
        'نام',
        'نام خانوادگی',
        'کد ملی',
        'شماره تلفن',
        'شماره نظام پزشکی',
        'شماره پرسنلی / دانشجویی',
        'سمت',
        'نرم افزار',
        'سطح دسترسی',
        'سایر / توضیح سطح دسترسی',
        'توضیحات',
        'تاریخ ثبت',
        'امضای دانشجو',
    ];
}

function dis_request_export_signature_lines(): array
{
    return [
        'کاربر درخواست کننده: ....................................................',
        'مدیر گروه مربوطه: ....................................................',
        'مدیر درمان: ....................................................',
        'معاونت مالی اداری: ....................................................',
        'امضا کاربر مسئول: ....................................................',
        'مسئول DIS: ....................................................',
    ];
}

function dis_request_export_student_row(array $payload, int $index): array
{
    $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
    $positions = array_values(array_filter(
        is_array($fields['positions'] ?? null) ? $fields['positions'] : [],
        static fn($item): bool => trim((string) $item) !== ''
    ));
    $software = array_values(array_filter(
        is_array($fields['software'] ?? null) ? $fields['software'] : [],
        static fn($item): bool => trim((string) $item) !== ''
    ));
    $accessLevels = array_values(array_filter(
        is_array($fields['accessLevels'] ?? null) ? $fields['accessLevels'] : [],
        static fn($item): bool => trim((string) $item) !== ''
    ));

    return [
        (string) $index,
        dis_request_normalize_text_field((string) ($fields['firstName'] ?? ''), 60),
        dis_request_normalize_text_field((string) ($fields['lastName'] ?? ''), 80),
        dis_request_only_digits((string) ($fields['nationalCode'] ?? '')),
        dis_request_phone_for_output((string) ($fields['phoneNumber'] ?? '')),
        dis_request_only_digits((string) ($fields['medicalNumber'] ?? '')),
        dis_request_only_digits((string) ($fields['personnelOrStudentNumber'] ?? '')),
        implode('، ', $positions),
        implode('، ', $software),
        implode('، ', $accessLevels),
        dis_request_normalize_text_field((string) ($fields['otherAccessDetail'] ?? ''), 120),
        dis_request_normalize_text_field((string) ($fields['notes'] ?? ''), 1000),
        dis_request_normalize_text_field((string) ($payload['submittedAt'] ?? ''), 40),
        '',
    ];
}

function dis_request_export_dataset(string $kind): array
{
    $normalizedKind = trim(strtolower($kind));
    $aliases = [
        '' => true,
        'official' => true,
        'owner-official' => true,
        'full-responses' => true,
        'submitted-users' => true,
        'submitted-structured' => true,
        'submitted-members' => true,
        'not-submitted-members' => true,
    ];

    if (!isset($aliases[$normalizedKind])) {
        dent_error('نوع خروجی نامعتبر است.', 422);
    }

    $store = dis_request_load_store();
    $responses = is_array($store['responses'] ?? null) ? $store['responses'] : [];
    $payloads = [];
    foreach ($responses as $response) {
        if (!is_array($response)) {
            continue;
        }
        $payloads[] = dis_request_response_payload($response);
    }

    usort($payloads, static function (array $left, array $right): int {
        return strcmp((string) ($right['submittedAt'] ?? ''), (string) ($left['submittedAt'] ?? ''));
    });

    $rows = [];
    $index = 1;
    foreach ($payloads as $payload) {
        $rows[] = dis_request_export_student_row($payload, $index);
        $index++;
    }

    return [
        'filename' => 'dis-request-owner-export.xlsx',
        'sheetName' => 'فرم DIS',
        'title' => DIS_REQUEST_FORM_TITLE,
        'headers' => dis_request_export_headers_official(),
        'rows' => $rows,
        'signatureLines' => dis_request_export_signature_lines(),
    ];
}

function dis_request_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function dis_request_safe_sheet_name(string $value): string
{
    $clean = preg_replace('/[\[\]\:\*\?\/\\\\]+/u', ' ', trim($value)) ?? '';
    if ($clean === '') {
        $clean = 'Sheet1';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($clean, 'UTF-8') > 31) {
            $clean = mb_substr($clean, 0, 31, 'UTF-8');
        }
    } elseif (strlen($clean) > 31) {
        $clean = substr($clean, 0, 31);
    }

    return $clean;
}

function dis_request_xlsx_column_name(int $columnNumber): string
{
    $name = '';
    $number = $columnNumber;
    while ($number > 0) {
        $remainder = ($number - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $number = intdiv($number - 1, 26);
    }

    return $name;
}

function dis_request_xlsx_cell_ref(int $row, int $columnNumber): string
{
    return dis_request_xlsx_column_name($columnNumber) . (string) $row;
}

function dis_request_xlsx_inline_cell(int $row, int $columnNumber, int $styleId, string $value): string
{
    $cellRef = dis_request_xlsx_cell_ref($row, $columnNumber);

    return '<c r="' . $cellRef . '" s="' . $styleId . '" t="inlineStr"><is><t xml:space="preserve">'
        . dis_request_xml_escape($value)
        . '</t></is></c>';
}

function dis_request_xlsx_row_xml(int $row, array $cells, ?float $height = null): string
{
    $attributes = ' r="' . $row . '"';
    if ($height !== null) {
        $attributes .= ' ht="' . rtrim(rtrim(number_format($height, 2, '.', ''), '0'), '.') . '" customHeight="1"';
    }

    return '<row' . $attributes . '>' . implode('', $cells) . '</row>';
}

function dis_request_xlsx_styles_xml(): string
{
    return implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">',
        '<fonts count="3">',
        '<font><sz val="11"/><name val="B Nazanin"/><family val="2"/></font>',
        '<font><b/><sz val="11"/><name val="B Nazanin"/><family val="2"/></font>',
        '<font><b/><sz val="16"/><name val="B Nazanin"/><family val="2"/></font>',
        '</fonts>',
        '<fills count="2">',
        '<fill><patternFill patternType="none"/></fill>',
        '<fill><patternFill patternType="gray125"/></fill>',
        '</fills>',
        '<borders count="2">',
        '<border><left/><right/><top/><bottom/><diagonal/></border>',
        '<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border>',
        '</borders>',
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>',
        '<cellXfs count="7">',
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>',
        '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>',
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>',
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1" readingOrder="2"/></xf>',
        '<xf numFmtId="49" fontId="0" fillId="0" borderId="1" applyAlignment="1" applyNumberFormat="1"><alignment horizontal="right" vertical="top" wrapText="1" readingOrder="2"/></xf>',
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>',
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>',
        '</cellXfs>',
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>',
        '</styleSheet>',
    ]);
}

function dis_request_xlsx_sheet_xml(string $title, array $headers, array $rows, array $signatureLines): string
{
    $columnCount = max(1, count($headers));
    $lastColumn = dis_request_xlsx_column_name($columnCount);
    $sheetRows = [];
    $mergeRefs = [];

    $sheetRows[] = dis_request_xlsx_row_xml(1, [
        dis_request_xlsx_inline_cell(1, 1, 1, $title),
    ], 34.0);
    $mergeRefs[] = 'A1:' . $lastColumn . '1';

    $sheetRows[] = dis_request_xlsx_row_xml(2, [], 8.0);

    $headerCells = [];
    $headerColumn = 1;
    foreach ($headers as $headerText) {
        $headerCells[] = dis_request_xlsx_inline_cell(3, $headerColumn, 2, (string) $headerText);
        $headerColumn++;
    }
    $sheetRows[] = dis_request_xlsx_row_xml(3, $headerCells, 24.0);

    $currentRow = 4;
    foreach ($rows as $rowValues) {
        $cells = [];
        $column = 1;
        $cellValues = is_array($rowValues) ? array_values($rowValues) : [];
        while (count($cellValues) < $columnCount) {
            $cellValues[] = '';
        }
        foreach ($cellValues as $value) {
            $styleId = in_array($column, [4, 5, 6, 7], true) ? 4 : 3;
            $cells[] = dis_request_xlsx_inline_cell($currentRow, $column, $styleId, (string) $value);
            $column++;
            if ($column > $columnCount) {
                break;
            }
        }

        $sheetRows[] = dis_request_xlsx_row_xml($currentRow, $cells, 24.0);
        $currentRow++;
    }

    $lastTableRow = $currentRow - 1;
    if ($lastTableRow < 3) {
        $lastTableRow = 3;
    }

    $currentRow += 1;
    $sheetRows[] = dis_request_xlsx_row_xml($currentRow, [
        dis_request_xlsx_inline_cell($currentRow, 1, 5, 'بخش تایید و امضا'),
    ], 25.0);
    $mergeRefs[] = 'A' . $currentRow . ':' . $lastColumn . $currentRow;

    foreach ($signatureLines as $line) {
        $currentRow++;
        $sheetRows[] = dis_request_xlsx_row_xml($currentRow, [
            dis_request_xlsx_inline_cell($currentRow, 1, 6, (string) $line),
        ], 26.0);
        $mergeRefs[] = 'A' . $currentRow . ':' . $lastColumn . $currentRow;
    }

    $dimensionRef = 'A1:' . $lastColumn . $currentRow;
    $autoFilterRef = 'A3:' . $lastColumn . $lastTableRow;

    $colsXml = [
        '<col min="1" max="1" width="6" customWidth="1"/>',
        '<col min="2" max="2" width="12" customWidth="1"/>',
        '<col min="3" max="3" width="14" customWidth="1"/>',
        '<col min="4" max="4" width="14" customWidth="1"/>',
        '<col min="5" max="5" width="15" customWidth="1"/>',
        '<col min="6" max="6" width="16" customWidth="1"/>',
        '<col min="7" max="7" width="18" customWidth="1"/>',
        '<col min="8" max="8" width="13" customWidth="1"/>',
        '<col min="9" max="9" width="12" customWidth="1"/>',
        '<col min="10" max="10" width="18" customWidth="1"/>',
        '<col min="11" max="11" width="22" customWidth="1"/>',
        '<col min="12" max="12" width="26" customWidth="1"/>',
        '<col min="13" max="13" width="16" customWidth="1"/>',
        '<col min="14" max="14" width="16" customWidth="1"/>',
    ];

    $mergeXml = '';
    if ($mergeRefs !== []) {
        $parts = [];
        foreach ($mergeRefs as $ref) {
            $parts[] = '<mergeCell ref="' . $ref . '"/>';
        }
        $mergeXml = '<mergeCells count="' . count($parts) . '">' . implode('', $parts) . '</mergeCells>';
    }

    return implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">',
        '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>',
        '<dimension ref="' . $dimensionRef . '"/>',
        '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A4" sqref="A4"/></sheetView></sheetViews>',
        '<sheetFormatPr defaultRowHeight="22"/>',
        '<cols>' . implode('', $colsXml) . '</cols>',
        '<sheetData>' . implode('', $sheetRows) . '</sheetData>',
        '<autoFilter ref="' . $autoFilterRef . '"/>',
        $mergeXml,
        '<printOptions horizontalCentered="1" verticalCentered="0"/>',
        '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>',
        '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>',
        '</worksheet>',
    ]);
}

function dis_request_zip_dos_datetime(?int $timestamp = null): array
{
    $time = $timestamp ?? time();
    $parts = getdate($time);

    $year = max(1980, (int) ($parts['year'] ?? 1980));
    $month = (int) ($parts['mon'] ?? 1);
    $day = (int) ($parts['mday'] ?? 1);
    $hour = (int) ($parts['hours'] ?? 0);
    $minute = (int) ($parts['minutes'] ?? 0);
    $second = (int) ($parts['seconds'] ?? 0);

    $dosTime = (($hour & 0x1F) << 11) | (($minute & 0x3F) << 5) | ((int) floor($second / 2) & 0x1F);
    $dosDate = ((($year - 1980) & 0x7F) << 9) | (($month & 0x0F) << 5) | ($day & 0x1F);

    return [$dosTime, $dosDate];
}

function dis_request_build_zip_archive(array $files): string
{
    [$dosTime, $dosDate] = dis_request_zip_dos_datetime();

    $localData = '';
    $centralDirectory = '';
    $offset = 0;
    $entryCount = 0;

    foreach ($files as $name => $content) {
        $fileName = str_replace('\\', '/', (string) $name);
        $data = (string) $content;
        $dataLength = strlen($data);
        $crc = (int) sprintf('%u', crc32($data));

        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $dataLength,
            $dataLength,
            strlen($fileName),
            0
        );

        $localData .= $localHeader . $fileName . $data;

        $centralHeader = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $dataLength,
            $dataLength,
            strlen($fileName),
            0,
            0,
            0,
            0,
            0,
            $offset
        );

        $centralDirectory .= $centralHeader . $fileName;
        $offset += strlen($localHeader) + strlen($fileName) + $dataLength;
        $entryCount++;
    }

    $endOfCentralDirectory = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $entryCount,
        $entryCount,
        strlen($centralDirectory),
        strlen($localData),
        0
    );

    return $localData . $centralDirectory . $endOfCentralDirectory;
}

function dis_request_emit_excel(array $dataset): void
{
    $safeFileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($dataset['filename'] ?? '')) ?: 'dis-request-owner-export.xlsx';
    if (!str_ends_with(strtolower($safeFileName), '.xlsx')) {
        $safeFileName .= '.xlsx';
    }

    $sheetName = dis_request_safe_sheet_name((string) ($dataset['sheetName'] ?? 'Sheet1'));
    $title = dis_request_normalize_text_field((string) ($dataset['title'] ?? DIS_REQUEST_FORM_TITLE), 150);
    $headers = is_array($dataset['headers'] ?? null) ? $dataset['headers'] : [];
    $rows = is_array($dataset['rows'] ?? null) ? $dataset['rows'] : [];
    $signatureLines = is_array($dataset['signatureLines'] ?? null) ? $dataset['signatureLines'] : [];

    $sheetXml = dis_request_xlsx_sheet_xml($title, $headers, $rows, $signatureLines);
    $stylesXml = dis_request_xlsx_styles_xml();

    $now = gmdate('Y-m-d\TH:i:s\Z');
    $workbookXml = implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">',
        '<bookViews><workbookView/></bookViews>',
        '<sheets><sheet name="' . dis_request_xml_escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>',
        '</workbook>',
    ]);
    $workbookRelsXml = implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>',
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>',
        '</Relationships>',
    ]);
    $rootRelsXml = implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">',
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>',
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>',
        '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>',
        '</Relationships>',
    ]);
    $contentTypesXml = implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">',
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>',
        '<Default Extension="xml" ContentType="application/xml"/>',
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>',
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>',
        '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
        '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>',
        '</Types>',
    ]);
    $coreXml = implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">',
        '<dc:title>' . dis_request_xml_escape($title) . '</dc:title>',
        '<dc:creator>DIS Export</dc:creator>',
        '<cp:lastModifiedBy>DIS Export</cp:lastModifiedBy>',
        '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>',
        '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>',
        '</cp:coreProperties>',
    ]);
    $appXml = implode('', [
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
        '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">',
        '<Application>DIS Export</Application>',
        '</Properties>',
    ]);

    $archiveFiles = [
        '[Content_Types].xml' => $contentTypesXml,
        '_rels/.rels' => $rootRelsXml,
        'docProps/app.xml' => $appXml,
        'docProps/core.xml' => $coreXml,
        'xl/workbook.xml' => $workbookXml,
        'xl/_rels/workbook.xml.rels' => $workbookRelsXml,
        'xl/styles.xml' => $stylesXml,
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    $outputBinary = '';
    if (class_exists('ZipArchive')) {
        $tmpPath = tempnam(sys_get_temp_dir(), 'dis-xlsx-');
        if ($tmpPath === false) {
            dent_error('ایجاد فایل موقت برای خروجی اکسل انجام نشد.', 500);
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($tmpPath, ZipArchive::OVERWRITE | ZipArchive::CREATE);
        if ($openResult !== true) {
            @unlink($tmpPath);
            dent_error('ساخت فایل اکسل انجام نشد.', 500);
        }

        foreach ($archiveFiles as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);
        if ($binary === false) {
            dent_error('خواندن فایل خروجی اکسل انجام نشد.', 500);
        }
        $outputBinary = $binary;
    } else {
        $outputBinary = dis_request_build_zip_archive($archiveFiles);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
    header('Content-Length: ' . (string) strlen($outputBinary));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $outputBinary;
    exit;
}

$action = dent_request_action();

// Only auth_store writes the PHP session; every action below is a pure session
// reader. Release the session lock now so concurrent same-session requests are
// not serialized behind this request. $_SESSION stays readable.
dent_release_session_lock();

if ($action === 'status') {
    $user = dent_require_main_site_user();
    $store = dis_request_load_store();
    $formOpen = $store['formOpen'] ?? true;
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $response = $studentNumber !== '' ? dis_request_form_response_for_user($store, $studentNumber) : null;

    dent_json_response([
        'success' => true,
        'title' => DIS_REQUEST_FORM_TITLE,
        'formOpen' => $formOpen,
        'shareUrl' => dis_request_absolute_url(DIS_REQUEST_SHARE_PATH),
        'sharePath' => DIS_REQUEST_SHARE_PATH,
        'manageUrl' => dis_request_absolute_url(DIS_REQUEST_MANAGE_PATH),
        'managePath' => DIS_REQUEST_MANAGE_PATH,
        'ownerAccess' => ((string) ($user['role'] ?? '')) === 'owner',
        'alreadySubmitted' => $response !== null,
        'canSubmit' => $response === null && $formOpen,
        'response' => $response,
        'prefill' => dis_request_prefill_for_user($user),
    ]);
}

if ($action === 'submit') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت فرم نامعتبر است.', 405);
    }

    $user = dent_require_main_site_user();
    $response = dis_request_submit($user, $_POST);

    dent_json_response([
        'success' => true,
        'message' => 'درخواست ایجاد کاربری در DIS ثبت شد.',
        'response' => $response,
    ]);
}

if ($action === 'ownerOverview') {
    dent_require_owner();
    $store = dis_request_load_store();

    dent_json_response([
        'success' => true,
        'title' => DIS_REQUEST_FORM_TITLE,
        'formOpen' => $store['formOpen'] ?? true,
        'overview' => dis_request_owner_overview(),
    ]);
}

if ($action === 'setFormStatus') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد نامعتبر است.', 405);
    }

    dent_require_owner();

    $openRaw = trim((string) ($_POST['open'] ?? ''));
    $open = filter_var($openRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($open === null) {
        dent_error('مقدار وضعیت فرم نامعتبر است.', 422);
    }

    $store = dis_request_load_store();
    $store['formOpen'] = $open;
    dis_request_save_store($store);

    dent_json_response([
        'success' => true,
        'formOpen' => $open,
        'message' => $open ? 'فرم DIS باز شد.' : 'فرم DIS بسته شد.',
    ]);
}

if ($action === 'ownerResponse') {
    dent_require_owner();

    $studentNumber = dent_normalize_student_number((string) ($_GET['studentNumber'] ?? $_POST['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        dent_error('شناسه کاربر نامعتبر است.', 422);
    }

    $store = dis_request_load_store();
    $response = dis_request_form_response_for_user($store, $studentNumber);
    if ($response === null) {
        dent_error('پاسخ موردنظر پیدا نشد.', 404);
    }

    dent_json_response([
        'success' => true,
        'response' => $response,
    ]);
}

if ($action === 'export') {
    dent_require_owner();

    $kind = trim((string) ($_GET['kind'] ?? $_POST['kind'] ?? ''));
    $dataset = dis_request_export_dataset($kind);
    dis_request_emit_excel($dataset);
}

dent_error('درخواست نامعتبر است.', 404);
