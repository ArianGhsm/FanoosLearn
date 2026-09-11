<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/payments_store.php';
require_once __DIR__ . '/payments_gateway.php';

const FORMS_SCHEMA_VERSION = 1;
const FORMS_ID_PREFIX = 'frm-';
const FORMS_RESPONSE_ID_PREFIX = 'resp-';
const FORMS_RECEIPT_ID_PREFIX = 'rcpt-';
const FORMS_SHARE_PATH = '/forms/fill/';

function forms_clean_cohort(?string $value): string
{
    $value = dent_clean_cohort_key((string) $value);
    return $value !== '' ? $value : dent_primary_cohort_key();
}

function forms_resolve_requested_cohort(?string $value): string
{
    $cohort = forms_clean_cohort($value);
    if ($cohort !== dent_primary_cohort_key()) {
        return $cohort;
    }

    $user = dent_current_user();
    if ($user === null) {
        return $cohort;
    }

    $userCohort = dent_user_cohort_key($user);
    if ($userCohort !== '' && !dent_is_prosthesis_cohort_key($userCohort)) {
        return $userCohort;
    }

    return $cohort;
}

function forms_requested_cohort(): string
{
    return forms_resolve_requested_cohort((string) ($_POST['cohort'] ?? ($_GET['cohort'] ?? '')));
}

function forms_set_active_cohort(string $cohort): void
{
    $GLOBALS['forms_active_cohort'] = forms_clean_cohort($cohort);
}

function forms_active_cohort(): string
{
    return forms_clean_cohort((string) ($GLOBALS['forms_active_cohort'] ?? dent_primary_cohort_key()));
}

function forms_is_prosthesis_context(): bool
{
    return dent_is_prosthesis_cohort_key(forms_active_cohort());
}

function forms_cohort_supports_rotation_groups(?string $cohort = null): bool
{
    return dent_cohort_supports_rotation_groups(forms_clean_cohort($cohort ?? forms_active_cohort()));
}

function forms_store_path(): string
{
    return dent_storage_path('forms/store.json');
}

function forms_lock_path(): string
{
    return dent_storage_path('forms/store.lock');
}

function forms_legacy_store_paths(): array
{
    return [
        dent_prosthesis_legacy_cohort_key() => dent_storage_path('forms/prosthesis_1402_store.json'),
    ];
}

function forms_default_store(): array
{
    return [
        'schemaVersion' => FORMS_SCHEMA_VERSION,
        'forms' => [],
        'responses' => [],
        'receiptUploads' => [],
    ];
}

function forms_receipts_dir(?string $cohort = null): string
{
    $cohort = forms_clean_cohort($cohort ?? forms_active_cohort());
    if ($cohort === dent_prosthesis_legacy_cohort_key()) {
        return dent_storage_path('forms/prosthesis_1402_uploads');
    }
    if ($cohort !== dent_primary_cohort_key()) {
        return dent_storage_path('forms/' . dent_cohort_storage_slug($cohort) . '_uploads');
    }
    return dent_storage_path('forms/uploads');
}

function forms_clean_id(string $value, string $prefix): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    if (!str_starts_with($value, $prefix)) {
        return '';
    }

    return preg_match('/^[a-z0-9_-]{4,80}$/', $value) === 1 ? $value : '';
}

function forms_next_id(string $prefix): string
{
    try {
        return $prefix . bin2hex(random_bytes(5));
    } catch (Throwable $error) {
        return $prefix . strtolower(str_replace('.', '', uniqid('', true)));
    }
}

function forms_clean_text($value, int $maxLength): string
{
    return dent_clean_text((string) $value, $maxLength);
}

function forms_only_digits($value): string
{
    return preg_replace('/\D+/u', '', dent_normalize_digits((string) $value)) ?? '';
}

function forms_parse_bool($value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) !== 0;
    }
    $text = trim(strtolower((string) $value));
    if ($text === '') {
        return $default;
    }
    if (in_array($text, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($text, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return $default;
}

function forms_parse_timestamp($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        $number = (int) $value;
        if ($number <= 0) {
            return null;
        }
        return $number > 20000000000 ? (int) floor($number / 1000) : $number;
    }
    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }
    if (preg_match('/^\d+$/', $text) === 1) {
        $number = (int) $text;
        return $number > 20000000000 ? (int) floor($number / 1000) : $number;
    }
    $parsed = strtotime($text);
    return $parsed === false ? null : $parsed;
}

function forms_clean_kind(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['form', 'survey', 'poll'], true) ? $value : 'form';
}

function forms_kind_label(string $kind): string
{
    $kind = forms_clean_kind($kind);
    if ($kind === 'poll') {
        return 'نظرسنجی';
    }
    if ($kind === 'survey') {
        return 'پرسشنامه';
    }
    return 'فرم';
}

function forms_clean_status(string $value): string
{
    $value = trim(strtolower($value));
    return in_array($value, ['draft', 'open', 'closed', 'archived'], true) ? $value : 'open';
}

function forms_clean_audience(string $value): string
{
    $value = trim(strtolower($value));
    $allowed = ['link', 'all-users', 'rotation-1', 'rotation-2', 'both-rotations', 'custom'];
    return in_array($value, $allowed, true) ? $value : 'link';
}

function forms_normalize_audience_for_cohort(string $audience, ?string $cohort = null): string
{
    $audience = forms_clean_audience($audience);
    if (!forms_cohort_supports_rotation_groups($cohort) && in_array($audience, ['rotation-1', 'rotation-2', 'both-rotations'], true)) {
        return 'all-users';
    }
    return $audience;
}

function forms_audience_label(string $audience): string
{
    $audience = forms_clean_audience($audience);
    if ($audience === 'all-users') {
        return 'همه کاربران سایت';
    }
    if ($audience === 'rotation-1') {
        return 'فقط روتیشن ۱';
    }
    if ($audience === 'rotation-2') {
        return 'فقط روتیشن ۲';
    }
    if ($audience === 'both-rotations') {
        return 'هر دو روتیشن';
    }
    if ($audience === 'custom') {
        return 'فهرست مجاز سفارشی';
    }
    return 'هر کسی که لینک را دارد';
}

function forms_clean_result_visibility(string $value): string
{
    $value = trim(strtolower($value));
    $allowed = ['live', 'after-submit', 'after-close', 'manager-only'];
    return in_array($value, $allowed, true) ? $value : 'after-submit';
}

function forms_clean_field_type(string $value): string
{
    $value = trim(strtolower($value));
    $allowed = [
        'short_text',
        'paragraph',
        'single_choice',
        'multiple_choice',
        'dropdown',
        'linear_scale',
        'multiple_choice_grid',
        'checkbox_grid',
        'date',
        'time',
        'number',
        'email',
        'phone',
        'url',
        'payment',
        'receipt_payment',
    ];
    return in_array($value, $allowed, true) ? $value : 'short_text';
}

function forms_clean_field_id(string $value, int $index): string
{
    $value = trim(strtolower($value));
    if (preg_match('/^[a-z0-9_-]{3,48}$/', $value) === 1) {
        return $value;
    }
    return 'q-' . (string) max(1, $index + 1);
}

function forms_normalize_options($raw): array
{
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } elseif (is_string($raw)) {
        $trimmed = trim($raw);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            $items = is_array($decoded) ? $decoded : (preg_split('/\r\n|\r|\n/u', $trimmed) ?: []);
        }
    }

    $options = [];
    $seen = [];
    $index = 1;
    foreach ($items as $item) {
        $text = '';
        $id = '';
        if (is_array($item)) {
            $text = forms_clean_text($item['text'] ?? ($item['label'] ?? ''), 160);
            $id = trim(strtolower((string) ($item['id'] ?? '')));
        } else {
            $text = forms_clean_text($item, 160);
        }
        if ($text === '') {
            continue;
        }
        if (preg_match('/^[a-z0-9_-]{2,48}$/', $id) !== 1) {
            $id = 'opt-' . (string) $index;
        }
        while (isset($seen[$id])) {
            $index++;
            $id = 'opt-' . (string) $index;
        }
        $seen[$id] = true;
        $option = [
            'id' => $id,
            'text' => $text,
            'capacity' => is_array($item) ? max(0, (int) ($item['capacity'] ?? 0)) : 0,
        ];
        // Soft-deleted options are retained in storage so historical responses and
        // capacity counts stay correct; they are hidden from builders and respondents.
        if (is_array($item) && !empty($item['deleted'])) {
            $option['deleted'] = true;
        }
        $options[] = $option;
        $index++;
        if (count($options) >= 60) {
            break;
        }
    }

    return $options;
}

function forms_normalize_rows($raw): array
{
    $rows = forms_normalize_options($raw);
    if (count($rows) > 30) {
        return array_slice($rows, 0, 30);
    }
    return $rows;
}

function forms_normalize_payment_config($raw): array
{
    $raw = is_array($raw) ? $raw : [];
    $amount = max(0, (int) dent_normalize_digits((string) ($raw['amount'] ?? '0')));
    return [
        'amount' => $amount,
        'gateway' => payments_gateway_key_clean((string) ($raw['gateway'] ?? '')),
    ];
}

function forms_bank_name_from_card(string $cardNumber): string
{
    $digits = forms_only_digits($cardNumber);
    $bin = substr($digits, 0, 6);
    $banks = [
        '603799' => 'بانک ملی ایران',
        '589210' => 'بانک سپه',
        '627648' => 'بانک توسعه صادرات',
        '627961' => 'بانک صنعت و معدن',
        '603770' => 'بانک کشاورزی',
        '628023' => 'بانک مسکن',
        '627760' => 'پست بانک ایران',
        '502908' => 'بانک توسعه تعاون',
        '627412' => 'بانک اقتصاد نوین',
        '622106' => 'بانک پارسیان',
        '502229' => 'بانک پاسارگاد',
        '627488' => 'بانک کارآفرین',
        '621986' => 'بانک سامان',
        '639346' => 'بانک سینا',
        '639607' => 'بانک سرمایه',
        '502806' => 'بانک شهر',
        '502938' => 'بانک دی',
        '603769' => 'بانک صادرات ایران',
        '610433' => 'بانک ملت',
        '627353' => 'بانک تجارت',
        '589463' => 'بانک رفاه کارگران',
        '627381' => 'بانک انصار',
        '636214' => 'بانک آینده',
        '639370' => 'بانک مهر اقتصاد',
        '505416' => 'بانک گردشگری',
        '505785' => 'بانک ایران زمین',
        '636795' => 'بانک مرکزی',
        '636949' => 'بانک حکمت ایرانیان',
        '505801' => 'موسسه اعتباری کوثر',
        '606373' => 'بانک قرض‌الحسنه مهر ایران',
        '628157' => 'موسسه اعتباری توسعه',
        '639599' => 'بانک قوامین',
        '504172' => 'بانک رسالت',
    ];
    return $banks[$bin] ?? '';
}

function forms_normalize_receipt_payment_config($raw): array
{
    $raw = is_array($raw) ? $raw : [];
    $amount = max(0, (int) dent_normalize_digits((string) ($raw['amount'] ?? '0')));
    $cardNumber = substr(forms_only_digits($raw['cardNumber'] ?? ($raw['card_number'] ?? '')), 0, 19);
    $cardholder = forms_clean_text($raw['cardholder'] ?? ($raw['cardholderName'] ?? ($raw['card_holder'] ?? '')), 140);
    $bankName = forms_clean_text($raw['bankName'] ?? ($raw['bank_name'] ?? ''), 120);
    if ($bankName === '' && $cardNumber !== '') {
        $bankName = forms_bank_name_from_card($cardNumber);
    }

    return [
        'amount' => $amount,
        'cardNumber' => $cardNumber,
        'cardholder' => $cardholder,
        'bankName' => $bankName,
    ];
}

function forms_normalize_field($raw, int $index): ?array
{
    if (!is_array($raw)) {
        return null;
    }

    $type = forms_clean_field_type((string) ($raw['type'] ?? 'short_text'));
    $label = forms_clean_text($raw['label'] ?? '', 180);
    if ($label === '') {
        return null;
    }

    $field = [
        'id' => forms_clean_field_id((string) ($raw['id'] ?? ''), $index),
        'type' => $type,
        'label' => $label,
        'help' => forms_clean_text($raw['help'] ?? '', 500),
        'required' => forms_parse_bool($raw['required'] ?? false, false),
        'options' => [],
        'rows' => [],
        'scale' => null,
        'payment' => null,
        'receiptPayment' => null,
    ];

    if (in_array($type, ['single_choice', 'multiple_choice', 'dropdown', 'multiple_choice_grid', 'checkbox_grid'], true)) {
        $options = forms_normalize_options($raw['options'] ?? []);
        if (count($options) < 2) {
            return null;
        }
        $field['options'] = $options;
    }

    if (in_array($type, ['multiple_choice_grid', 'checkbox_grid'], true)) {
        $rows = forms_normalize_rows($raw['rows'] ?? []);
        if (count($rows) < 1) {
            return null;
        }
        $field['rows'] = $rows;
    }

    if ($type === 'linear_scale') {
        $scale = is_array($raw['scale'] ?? null) ? $raw['scale'] : [];
        $min = max(0, min(10, (int) ($scale['min'] ?? 1)));
        $max = max($min + 1, min(10, (int) ($scale['max'] ?? 5)));
        $options = [];
        for ($value = $min; $value <= $max; $value++) {
            $options[] = [
                'id' => (string) $value,
                'text' => (string) $value,
            ];
        }
        $field['scale'] = [
            'min' => $min,
            'max' => $max,
            'minLabel' => forms_clean_text($scale['minLabel'] ?? '', 80),
            'maxLabel' => forms_clean_text($scale['maxLabel'] ?? '', 80),
        ];
        $field['options'] = $options;
    }

    if ($type === 'payment') {
        $payment = forms_normalize_payment_config($raw['payment'] ?? []);
        if ((int) ($payment['amount'] ?? 0) <= 0) {
            return null;
        }
        $field['required'] = forms_parse_bool($raw['required'] ?? true, true);
        $field['payment'] = $payment;
    }

    if ($type === 'receipt_payment') {
        $receiptPayment = forms_normalize_receipt_payment_config($raw['receiptPayment'] ?? ($raw['receipt_payment'] ?? []));
        if ((int) ($receiptPayment['amount'] ?? 0) <= 0) {
            return null;
        }
        if ((string) ($receiptPayment['cardNumber'] ?? '') === '' || strlen((string) ($receiptPayment['cardNumber'] ?? '')) < 16) {
            return null;
        }
        if ((string) ($receiptPayment['cardholder'] ?? '') === '') {
            return null;
        }
        $field['required'] = forms_parse_bool($raw['required'] ?? true, true);
        $field['receiptPayment'] = $receiptPayment;
    }

    return $field;
}

function forms_normalize_fields($raw, string $kind): array
{
    $items = is_array($raw) ? $raw : [];
    $fields = [];
    $seen = [];
    foreach ($items as $index => $item) {
        $field = forms_normalize_field($item, (int) $index);
        if ($field === null) {
            continue;
        }
        $baseId = (string) $field['id'];
        $id = $baseId;
        $suffix = 2;
        while (isset($seen[$id])) {
            $id = $baseId . '-' . (string) $suffix;
            $suffix++;
        }
        $field['id'] = $id;
        $seen[$id] = true;
        $fields[] = $field;
        if (count($fields) >= 80) {
            break;
        }
    }

    if ($kind === 'poll' && $fields !== []) {
        $hasChoiceQuestion = false;
        foreach ($fields as $field) {
            if (in_array((string) ($field['type'] ?? ''), ['single_choice', 'multiple_choice'], true)) {
                $hasChoiceQuestion = true;
                break;
            }
        }
        if (!$hasChoiceQuestion) {
            $fields[0]['type'] = 'single_choice';
            $fields[0]['required'] = true;
            if (count((array) ($fields[0]['options'] ?? [])) < 2) {
                $fields[0]['options'] = [
                    ['id' => 'opt-1', 'text' => 'گزینه اول'],
                    ['id' => 'opt-2', 'text' => 'گزینه دوم'],
                ];
            }
        }
    }

    return $fields;
}

function forms_normalize_student_numbers($raw): array
{
    $items = [];
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text !== '') {
            $decoded = json_decode($text, true);
            $items = is_array($decoded) ? $decoded : (preg_split('/[\s,،;]+/u', $text) ?: []);
        }
    }

    $normalized = [];
    foreach ($items as $item) {
        $studentNumber = dent_normalize_student_number((string) $item);
        if ($studentNumber !== '') {
            $normalized[$studentNumber] = true;
        }
    }

    $result = array_keys($normalized);
    sort($result, SORT_STRING);
    return $result;
}

function forms_normalize_export_settings($raw): array
{
    $raw = is_array($raw) ? $raw : [];
    $identityDefaults = ['index', 'responseId', 'submittedAt', 'participantKind', 'name', 'studentNumber', 'roleLabel', 'phone'];
    $identityColumns = [];
    foreach (($raw['identityColumns'] ?? $identityDefaults) as $item) {
        $key = trim((string) $item);
        if (in_array($key, $identityDefaults, true)) {
            $identityColumns[$key] = true;
        }
    }
    if ($identityColumns === []) {
        $identityColumns = array_fill_keys($identityDefaults, true);
    }

    $fieldIds = [];
    $rawFieldIds = $raw['fieldIds'] ?? [];
    if (is_string($rawFieldIds)) {
        $decoded = json_decode($rawFieldIds, true);
        $rawFieldIds = is_array($decoded) ? $decoded : (preg_split('/[\s,،;]+/u', $rawFieldIds) ?: []);
    }
    foreach (is_array($rawFieldIds) ? $rawFieldIds : [] as $fieldId) {
        $clean = trim(strtolower((string) $fieldId));
        if (preg_match('/^[a-z0-9_-]{3,48}$/', $clean) === 1) {
            $fieldIds[$clean] = true;
        }
    }

    return [
        'identityColumns' => array_keys($identityColumns),
        'includeAllFields' => forms_parse_bool($raw['includeAllFields'] ?? true, true),
        'fieldIds' => array_keys($fieldIds),
    ];
}

function forms_normalize_form_record(string $formId, array $form): ?array
{
    $formId = forms_clean_id($formId !== '' ? $formId : (string) ($form['id'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        return null;
    }

    $kind = forms_clean_kind((string) ($form['kind'] ?? 'form'));
    $title = forms_clean_text($form['title'] ?? '', 160);
    if ($title === '') {
        return null;
    }

    $fields = forms_normalize_fields($form['fields'] ?? [], $kind);
    if ($fields === []) {
        return null;
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $cohort = forms_clean_cohort((string) ($form['cohort'] ?? 'main'));
    $audience = forms_normalize_audience_for_cohort((string) ($settings['audience'] ?? ($form['audience'] ?? 'link')), $cohort);
    $allowedStudents = forms_normalize_student_numbers($settings['allowedStudents'] ?? ($form['allowedStudents'] ?? []));
    $managerStudentNumbers = forms_normalize_student_numbers($settings['managerStudentNumbers'] ?? []);

    $createdAt = forms_parse_timestamp($form['createdAt'] ?? null) ?? time();
    $updatedAt = forms_parse_timestamp($form['updatedAt'] ?? null) ?? $createdAt;
    $startAt = forms_parse_timestamp($settings['startAt'] ?? ($form['startAt'] ?? null));
    $endAt = forms_parse_timestamp($settings['endAt'] ?? ($form['endAt'] ?? null));
    if ($startAt !== null && $endAt !== null && $endAt <= $startAt) {
        $endAt = null;
    }

    return [
        'id' => $formId,
        'cohort' => $cohort,
        'kind' => $kind,
        'title' => $title,
        'description' => forms_clean_text($form['description'] ?? '', 1400),
        'status' => forms_clean_status((string) ($form['status'] ?? 'open')),
        'createdBy' => dent_normalize_student_number((string) ($form['createdBy'] ?? '')),
        'createdAt' => $createdAt,
        'updatedAt' => max($updatedAt, $createdAt),
        'fields' => $fields,
        'settings' => [
            'audience' => $audience,
            'allowedStudents' => $allowedStudents,
            'allowRepresentativeManage' => forms_parse_bool($settings['allowRepresentativeManage'] ?? false, false),
            'managerStudentNumbers' => $managerStudentNumbers,
            'allowGuest' => forms_parse_bool($settings['allowGuest'] ?? false, false),
            'collectGuestName' => forms_parse_bool($settings['collectGuestName'] ?? true, true),
            'collectGuestPhone' => forms_parse_bool($settings['collectGuestPhone'] ?? false, false),
            'limitOneResponse' => forms_parse_bool($settings['limitOneResponse'] ?? true, true),
            'allowEditResponse' => forms_parse_bool($settings['allowEditResponse'] ?? false, false),
            'allowCreatorSubmit' => forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true),
            'anonymousResponses' => forms_parse_bool($settings['anonymousResponses'] ?? false, false),
            'export' => forms_normalize_export_settings($settings['export'] ?? []),
            'resultVisibility' => forms_clean_result_visibility((string) ($settings['resultVisibility'] ?? 'after-submit')),
            'startAt' => $startAt,
            'endAt' => $endAt,
        ],
    ];
}

function forms_normalize_response_record(string $responseId, array $response, array $knownFormIds): ?array
{
    $responseId = forms_clean_id($responseId !== '' ? $responseId : (string) ($response['id'] ?? ''), FORMS_RESPONSE_ID_PREFIX);
    if ($responseId === '') {
        return null;
    }

    $formId = forms_clean_id((string) ($response['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '' || !isset($knownFormIds[$formId])) {
        return null;
    }

    $identity = is_array($response['identity'] ?? null) ? $response['identity'] : [];
    $kind = (string) ($identity['kind'] ?? 'guest');
    if (!in_array($kind, ['user', 'guest'], true)) {
        $kind = 'guest';
    }
    $key = forms_clean_text($identity['key'] ?? '', 120);
    if ($key === '') {
        return null;
    }

    $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
    $submittedAt = forms_parse_timestamp($response['submittedAt'] ?? null) ?? time();
    $updatedAt = forms_parse_timestamp($response['updatedAt'] ?? null) ?? $submittedAt;

    return [
        'id' => $responseId,
        'formId' => $formId,
        'submittedAt' => $submittedAt,
        'updatedAt' => max($updatedAt, $submittedAt),
        'identity' => [
            'kind' => $kind,
            'key' => $key,
            'studentNumber' => dent_normalize_student_number((string) ($identity['studentNumber'] ?? '')),
            'name' => forms_clean_text($identity['name'] ?? '', 140),
            'roleLabel' => forms_clean_text($identity['roleLabel'] ?? '', 80),
            'phone' => forms_only_digits($identity['phone'] ?? ''),
        ],
        'answers' => $answers,
    ];
}

function forms_clean_receipt_stored_path(string $value): string
{
    $value = str_replace('\\', '/', trim($value));
    if ($value === '' || str_contains($value, '..') || str_starts_with($value, '/')) {
        return '';
    }
    return preg_match('/^[a-zA-Z0-9._\/-]{8,220}$/', $value) === 1 ? $value : '';
}

function forms_normalize_receipt_upload_record(string $receiptId, array $receipt, array $knownForms): ?array
{
    $receiptId = forms_clean_id($receiptId !== '' ? $receiptId : (string) ($receipt['id'] ?? ''), FORMS_RECEIPT_ID_PREFIX);
    if ($receiptId === '') {
        return null;
    }

    $formId = forms_clean_id((string) ($receipt['formId'] ?? ''), FORMS_ID_PREFIX);
    $form = ($formId !== '' && isset($knownForms[$formId]) && is_array($knownForms[$formId])) ? $knownForms[$formId] : null;
    if ($formId === '' || $form === null) {
        return null;
    }
    $fieldId = trim(strtolower((string) ($receipt['fieldId'] ?? '')));
    if (preg_match('/^[a-z0-9_-]{3,48}$/', $fieldId) !== 1) {
        return null;
    }
    $identityKey = forms_clean_text($receipt['identityKey'] ?? '', 120);
    if ($identityKey === '') {
        return null;
    }
    $storedPath = forms_clean_receipt_stored_path((string) ($receipt['storedPath'] ?? ($receipt['stored_path'] ?? '')));
    if ($storedPath === '') {
        return null;
    }
    $uploadedAt = forms_parse_timestamp($receipt['uploadedAt'] ?? ($receipt['uploaded_at'] ?? null)) ?? time();

    return [
        'id' => $receiptId,
        'cohort' => forms_clean_cohort((string) ($receipt['cohort'] ?? forms_form_cohort($form))),
        'formId' => $formId,
        'fieldId' => $fieldId,
        'identityKey' => $identityKey,
        'originalName' => forms_clean_text($receipt['originalName'] ?? ($receipt['original_name'] ?? ''), 220),
        'storedPath' => $storedPath,
        'mimeType' => forms_clean_text($receipt['mimeType'] ?? ($receipt['mime_type'] ?? 'application/octet-stream'), 120),
        'size' => max(0, (int) ($receipt['size'] ?? 0)),
        'uploadedAt' => $uploadedAt,
        'uploadedBy' => dent_normalize_student_number((string) ($receipt['uploadedBy'] ?? ($receipt['uploaded_by'] ?? ''))),
        'status' => forms_clean_text($receipt['status'] ?? 'uploaded', 30) ?: 'uploaded',
    ];
}

function forms_load_store(): array
{
    $sources = [];
    $sources[] = ['raw' => dent_read_json_file(forms_store_path(), forms_default_store()), 'fallbackCohort' => null];
    foreach (forms_legacy_store_paths() as $cohort => $path) {
        if (!is_file($path)) {
            continue;
        }
        $sources[] = ['raw' => dent_read_json_file($path, forms_default_store()), 'fallbackCohort' => forms_clean_cohort($cohort)];
    }

    foreach ($sources as $source) {
        $raw = $source['raw'] ?? null;
        if (!is_array($raw)
            || !isset($raw['forms'], $raw['responses'])
            || !is_array($raw['forms'])
            || !is_array($raw['responses'])) {
            throw new DentJsonPersistenceException(
                'FORMS_STORE_SCHEMA_INVALID',
                'Existing forms store has an invalid schema'
            );
        }
    }

    $forms = [];
    $needsSharedBackfill = false;
    foreach ($sources as $source) {
        $raw = is_array($source['raw'] ?? null) ? $source['raw'] : forms_default_store();
        $fallbackCohort = is_string($source['fallbackCohort'] ?? null) ? $source['fallbackCohort'] : null;
        foreach (($raw['forms'] ?? []) as $formId => $form) {
            if (!is_array($form)) {
                continue;
            }
            if ($fallbackCohort !== null && trim((string) ($form['cohort'] ?? '')) === '') {
                $form['cohort'] = $fallbackCohort;
            }
            $normalized = forms_normalize_form_record((string) $formId, $form);
            if ($normalized === null) {
                continue;
            }
            $existing = $forms[(string) $normalized['id']] ?? null;
            if ($fallbackCohort !== null && (!is_array($existing) || (int) ($normalized['updatedAt'] ?? 0) > (int) ($existing['updatedAt'] ?? 0))) {
                $needsSharedBackfill = true;
            }
            if (!is_array($existing) || (int) ($normalized['updatedAt'] ?? 0) > (int) ($existing['updatedAt'] ?? 0)) {
                $forms[(string) $normalized['id']] = $normalized;
            }
        }
    }

    $knownForms = $forms;

    $responses = [];
    foreach ($sources as $source) {
        $raw = is_array($source['raw'] ?? null) ? $source['raw'] : forms_default_store();
        $fallbackCohort = is_string($source['fallbackCohort'] ?? null) ? $source['fallbackCohort'] : null;
        foreach (($raw['responses'] ?? []) as $responseId => $response) {
            if (!is_array($response)) {
                continue;
            }
            $normalized = forms_normalize_response_record((string) $responseId, $response, $knownForms);
            if ($normalized === null) {
                continue;
            }
            $existing = $responses[(string) $normalized['id']] ?? null;
            if ($fallbackCohort !== null && (!is_array($existing) || (int) ($normalized['updatedAt'] ?? 0) > (int) ($existing['updatedAt'] ?? 0))) {
                $needsSharedBackfill = true;
            }
            if (!is_array($existing) || (int) ($normalized['updatedAt'] ?? 0) > (int) ($existing['updatedAt'] ?? 0)) {
                $responses[(string) $normalized['id']] = $normalized;
            }
        }
    }

    $receiptUploads = [];
    foreach ($sources as $source) {
        $raw = is_array($source['raw'] ?? null) ? $source['raw'] : forms_default_store();
        $fallbackCohort = is_string($source['fallbackCohort'] ?? null) ? $source['fallbackCohort'] : null;
        foreach (($raw['receiptUploads'] ?? ($raw['receipt_uploads'] ?? [])) as $receiptId => $receipt) {
            if (!is_array($receipt)) {
                continue;
            }
            if ($fallbackCohort !== null && trim((string) ($receipt['cohort'] ?? '')) === '') {
                $receipt['cohort'] = $fallbackCohort;
            }
            $normalized = forms_normalize_receipt_upload_record((string) $receiptId, $receipt, $knownForms);
            if ($normalized === null) {
                continue;
            }
            $existing = $receiptUploads[(string) $normalized['id']] ?? null;
            if ($fallbackCohort !== null && (!is_array($existing) || (int) ($normalized['uploadedAt'] ?? 0) > (int) ($existing['uploadedAt'] ?? 0))) {
                $needsSharedBackfill = true;
            }
            if (!is_array($existing) || (int) ($normalized['uploadedAt'] ?? 0) > (int) ($existing['uploadedAt'] ?? 0)) {
                $receiptUploads[(string) $normalized['id']] = $normalized;
            }
        }
    }

    uasort($forms, static fn(array $left, array $right): int => (int) ($right['updatedAt'] ?? 0) <=> (int) ($left['updatedAt'] ?? 0));
    uasort($responses, static fn(array $left, array $right): int => (int) ($right['submittedAt'] ?? 0) <=> (int) ($left['submittedAt'] ?? 0));
    uasort($receiptUploads, static fn(array $left, array $right): int => (int) ($right['uploadedAt'] ?? 0) <=> (int) ($left['uploadedAt'] ?? 0));

    $store = [
        'schemaVersion' => FORMS_SCHEMA_VERSION,
        'forms' => $forms,
        'responses' => $responses,
        'receiptUploads' => $receiptUploads,
    ];

    if ($needsSharedBackfill) {
        // Non-blocking lock: skip auto-save if another writer already holds the lock.
        // That writer will save the merged data as part of its own write cycle.
        $backfillLock = @fopen(forms_lock_path(), 'c+');
        if ($backfillLock !== false) {
            if (@flock($backfillLock, LOCK_EX | LOCK_NB)) {
                forms_save_store($store);
                @flock($backfillLock, LOCK_UN);
            }
            @fclose($backfillLock);
        }
    }

    return $store;
}

function forms_save_store(array $store): void
{
    $forms = is_array($store['forms'] ?? null) ? $store['forms'] : [];
    $responses = is_array($store['responses'] ?? null) ? $store['responses'] : [];
    $receiptUploads = is_array($store['receiptUploads'] ?? null) ? $store['receiptUploads'] : [];

    dent_write_json_file(forms_store_path(), [
        'schemaVersion' => FORMS_SCHEMA_VERSION,
        'forms' => $forms,
        'responses' => $responses,
        'receiptUploads' => $receiptUploads,
    ]);
}

function forms_base_origin(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $secure = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    return ($secure ? 'https://' : 'http://') . $host;
}

function forms_absolute_url(string $path): string
{
    $origin = forms_base_origin();
    return $origin === '' ? $path : $origin . $path;
}

function forms_payment_gateways_payload(): array
{
    $catalog = payments_gateway_checkout_catalog(false);
    $defaultKey = payments_gateway_default_enabled_checkout(false);
    $gateways = [];
    foreach ($catalog as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = payments_gateway_clean((string) ($entry['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $gateways[] = [
            'key' => $key,
            'label' => dent_clean_text((string) ($entry['label'] ?? ''), 80),
            'provider' => dent_clean_text((string) ($entry['provider'] ?? ''), 120),
            'icon' => dent_clean_text((string) ($entry['icon'] ?? ''), 8),
            'isEnabled' => (bool) ($entry['isEnabled'] ?? false),
            'isDefault' => (bool) ($entry['isEnabled'] ?? false) && $key === $defaultKey,
        ];
    }

    return [
        'defaultKey' => $defaultKey,
        'gateways' => $gateways,
    ];
}

function forms_share_path_for_cohort(string $cohort, string $formId): string
{
    $cleanCohort = forms_clean_cohort($cohort);
    $path = FORMS_SHARE_PATH . '?form=' . urlencode($formId);
    if ($cleanCohort !== dent_primary_cohort_key()) {
        $path .= '&cohort=' . urlencode($cleanCohort);
    }
    return $path;
}

function forms_form_cohort(array $form): string
{
    return forms_clean_cohort((string) ($form['cohort'] ?? 'main'));
}

function forms_form_matches_active_cohort(array $form): bool
{
    return forms_form_cohort($form) === forms_active_cohort();
}

function forms_share_path(string $formId, ?array $form = null): string
{
    $cohort = $form !== null ? forms_form_cohort($form) : forms_active_cohort();
    return forms_share_path_for_cohort($cohort, $formId);
}

function forms_user_matches_context(array $user): bool
{
    $role = (string) ($user['role'] ?? 'student');
    if ($role === 'owner' || !empty($user['isOwner'])) {
        return true;
    }

    return dent_user_cohort_key($user) === forms_active_cohort();
}

function forms_user_payload(?array $user): ?array
{
    if ($user === null) {
        return null;
    }
    if (!forms_user_matches_context($user)) {
        return null;
    }
    $public = dent_public_user($user);
    return [
        'studentNumber' => (string) ($public['studentNumber'] ?? ''),
        'name' => (string) ($public['name'] ?? ''),
        'role' => (string) ($public['role'] ?? 'student'),
        'roleLabel' => (string) ($public['roleLabel'] ?? ''),
        'cohortKey' => (string) ($public['cohortKey'] ?? ''),
        'cohort' => is_array($public['cohort'] ?? null) ? $public['cohort'] : null,
        'isOwner' => (bool) ($public['isOwner'] ?? false),
        'isRepresentative' => (bool) ($public['isRepresentative'] ?? false),
    ];
}

function forms_current_site_user(): ?array
{
    $user = dent_current_user();
    if ($user !== null && !forms_user_matches_context($user)) {
        return null;
    }

    return $user;
}

function forms_require_context_user(): array
{
    $user = dent_require_user();
    if (!forms_user_matches_context($user)) {
        dent_error('این بخش برای این حساب فعال نیست.', 403);
    }
    return $user;
}

function forms_can_create(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    $role = (string) ($user['role'] ?? 'student');
    if ($role === 'owner' || !empty($user['isOwner'])) {
        return true;
    }

    if (!forms_user_matches_context($user)) {
        return false;
    }

    return (bool) dent_permissions_for_role($role, forms_active_cohort())['manageForms'];
}

function forms_is_representative(array $user): bool
{
    $role = (string) ($user['role'] ?? 'student');
    if (forms_is_prosthesis_context()) {
        return $role === 'prosthesis_representative' || !empty($user['isProsthesisRepresentative']);
    }
    return $role === 'representative' || !empty($user['isRepresentative']);
}

function forms_can_manage(array $form, ?array $user): bool
{
    if ($user === null) {
        return false;
    }
    if (!forms_form_matches_active_cohort($form)) {
        return false;
    }
    if (forms_can_create($user)) {
        return true;
    }
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '' || $studentNumber === dent_normalize_student_number((string) ($form['createdBy'] ?? ''))) {
        return $studentNumber !== '';
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $managerStudentNumbers = forms_normalize_student_numbers($settings['managerStudentNumbers'] ?? []);
    if (in_array($studentNumber, $managerStudentNumbers, true)) {
        return true;
    }

    return forms_is_representative($user) && forms_parse_bool($settings['allowRepresentativeManage'] ?? false, false);
}

function forms_user_can_view_inactive(array $form, ?array $user): bool
{
    if ($user === null || !forms_form_matches_active_cohort($form)) {
        return false;
    }

    return (string) ($user['role'] ?? '') === 'owner' || !empty($user['isOwner']);
}

function forms_user_can_list_form(array $form, ?array $user): bool
{
    if (!forms_form_matches_active_cohort($form)) {
        return false;
    }

    if (forms_status($form) !== 'open' && !forms_user_can_view_inactive($form, $user)) {
        return false;
    }

    return forms_can_create($user) || forms_can_manage($form, $user) || forms_viewer_can_access($form, $user);
}

function forms_user_can_open_form(array $form, ?array $user): bool
{
    if (!forms_audience_is_cross_cohort($form) && !forms_form_matches_active_cohort($form)) {
        return false;
    }

    if (forms_user_can_view_inactive($form, $user)) {
        return true;
    }

    if (forms_status($form) !== 'open') {
        return false;
    }

    return forms_can_manage($form, $user) || forms_viewer_can_access($form, $user);
}

function forms_can_delete(array $form, ?array $user): bool
{
    if ($user === null) {
        return false;
    }
    if (!forms_form_matches_active_cohort($form)) {
        return false;
    }
    $role = (string) ($user['role'] ?? 'student');
    return $role === 'owner' || !empty($user['isOwner']);
}

function forms_status(array $form, ?int $now = null): string
{
    $now = $now ?? time();
    $status = forms_clean_status((string) ($form['status'] ?? 'open'));
    if ($status === 'archived') {
        return 'archived';
    }
    if ($status === 'draft') {
        return 'draft';
    }
    if ($status === 'closed') {
        return 'closed';
    }
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $startAt = forms_parse_timestamp($settings['startAt'] ?? null);
    $endAt = forms_parse_timestamp($settings['endAt'] ?? null);
    if ($endAt !== null && $endAt <= $now) {
        return 'closed';
    }
    if ($startAt !== null && $startAt > $now) {
        return 'scheduled';
    }
    return 'open';
}

function forms_status_label(string $status): string
{
    if ($status === 'open') {
        return 'فعال';
    }
    if ($status === 'scheduled') {
        return 'زمان‌بندی‌شده';
    }
    if ($status === 'draft') {
        return 'پیش‌نویس';
    }
    if ($status === 'archived') {
        return 'بایگانی‌شده';
    }
    return 'بسته';
}

function forms_user_rotation_id(array $user): ?int
{
    $assignment = dent_user_rotation_assignment($user);
    if (!is_array($assignment)) {
        return null;
    }
    $rotationId = (int) ($assignment['rotationId'] ?? 0);
    return in_array($rotationId, [1, 2], true) ? $rotationId : null;
}

function forms_user_matches_audience(array $form, array $user): bool
{
    if (forms_can_manage($form, $user)) {
        return true;
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $audience = forms_normalize_audience_for_cohort((string) ($settings['audience'] ?? 'link'), forms_form_cohort($form));
    if ($audience === 'link' || $audience === 'all-users') {
        return true;
    }

    if ($audience === 'custom') {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        $allowed = forms_normalize_student_numbers($settings['allowedStudents'] ?? []);
        return $studentNumber !== '' && in_array($studentNumber, $allowed, true);
    }

    $rotationId = forms_user_rotation_id($user);
    if ($audience === 'rotation-1') {
        return $rotationId === 1;
    }
    if ($audience === 'rotation-2') {
        return $rotationId === 2;
    }
    return in_array($rotationId, [1, 2], true);
}

function forms_guest_allowed(array $form): bool
{
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    return forms_parse_bool($settings['allowGuest'] ?? false, false);
}

function forms_audience_is_cross_cohort(array $form): bool
{
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $audience = forms_normalize_audience_for_cohort((string) ($settings['audience'] ?? 'link'), forms_form_cohort($form));
    // "all-users" literally means every site user, regardless of entry-year cohort.
    // Student numbers do NOT reliably encode the cohort, so an all-users form must
    // not be gated by the viewer's cohort.
    return $audience === 'all-users';
}

function forms_viewer_can_access(array $form, ?array $user): bool
{
    if (!forms_audience_is_cross_cohort($form) && !forms_form_matches_active_cohort($form)) {
        return false;
    }
    if ($user !== null && forms_user_matches_audience($form, $user)) {
        return true;
    }
    return $user === null && forms_guest_allowed($form);
}

function forms_identity_key(?array $user, array $source): string
{
    if ($user !== null) {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        return $studentNumber === '' ? '' : 'user:' . $studentNumber;
    }
    $guestKey = forms_clean_text($source['guestKey'] ?? '', 80);
    if (preg_match('/^[a-z0-9_-]{8,80}$/i', $guestKey) !== 1) {
        $guestKey = 'guest-' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . microtime(true)), 0, 24);
    }
    return 'guest:' . strtolower($guestKey);
}

function forms_identity_payload(?array $user, array $source): array
{
    if ($user !== null) {
        $public = dent_public_user($user);
        return [
            'kind' => 'user',
            'key' => forms_identity_key($user, $source),
            'studentNumber' => dent_normalize_student_number((string) ($public['studentNumber'] ?? '')),
            'name' => forms_clean_text($public['name'] ?? '', 140),
            'roleLabel' => forms_clean_text($public['roleLabel'] ?? '', 80),
            'phone' => forms_only_digits($user['phoneNumber'] ?? ''),
        ];
    }

    return [
        'kind' => 'guest',
        'key' => forms_identity_key(null, $source),
        'studentNumber' => '',
        'name' => forms_clean_text($source['guestName'] ?? '', 140),
        'roleLabel' => 'مهمان',
        'phone' => forms_only_digits($source['guestPhone'] ?? ''),
    ];
}

function forms_response_for_identity(array $store, string $formId, string $identityKey): ?array
{
    foreach ($store['responses'] as $response) {
        if (!is_array($response)) {
            continue;
        }
        if ((string) ($response['formId'] ?? '') !== $formId) {
            continue;
        }
        $identity = is_array($response['identity'] ?? null) ? $response['identity'] : [];
        if ((string) ($identity['key'] ?? '') === $identityKey) {
            return $response;
        }
    }
    return null;
}

function forms_responses_for_form(array $store, string $formId): array
{
    $responses = [];
    foreach ($store['responses'] as $response) {
        if (is_array($response) && (string) ($response['formId'] ?? '') === $formId) {
            $responses[] = $response;
        }
    }
    usort($responses, static fn(array $left, array $right): int => (int) ($right['submittedAt'] ?? 0) <=> (int) ($left['submittedAt'] ?? 0));
    return $responses;
}

/**
 * Collect every scalar option id that has been chosen for a given field across responses.
 * Handles flat choice answers (scalar / array) and grid answers (nested per row).
 * Returns: [ optionId => true ]
 */
function forms_answered_option_ids_for_field(array $responses, string $fieldId): array
{
    $ids = [];
    foreach ($responses as $response) {
        if (!is_array($response)) {
            continue;
        }
        $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
        $answer = $answers[$fieldId] ?? null;
        if ($answer === null) {
            continue;
        }
        if (is_array($answer)) {
            foreach ($answer as $value) {
                if (is_array($value)) {
                    foreach ($value as $nested) {
                        if (is_scalar($nested) && (string) $nested !== '') {
                            $ids[(string) $nested] = true;
                        }
                    }
                } elseif (is_scalar($value) && (string) $value !== '') {
                    $ids[(string) $value] = true;
                }
            }
        } elseif (is_scalar($answer) && (string) $answer !== '') {
            $ids[(string) $answer] = true;
        }
    }
    return $ids;
}

/**
 * Preserve options that an editor removed but that still carry meaning: options
 * with a capacity, options already soft-deleted, or options that have at least
 * one stored response. Such options are re-attached to the new form marked
 * `deleted => true` so historical responses render correctly and capacity counts
 * stay authoritative. Visible (non-deleted) options are left untouched.
 * Only flat choice fields participate; grids/scale are left as-is.
 */
function forms_preserve_deleted_options(array $newForm, ?array $oldForm, array $responses): array
{
    if (!is_array($oldForm)) {
        return $newForm;
    }
    $oldFieldsById = [];
    foreach ((array) ($oldForm['fields'] ?? []) as $oldField) {
        if (is_array($oldField) && isset($oldField['id'])) {
            $oldFieldsById[(string) $oldField['id']] = $oldField;
        }
    }
    $newFields = (array) ($newForm['fields'] ?? []);
    foreach ($newFields as $index => $field) {
        if (!is_array($field)) {
            continue;
        }
        $type = (string) ($field['type'] ?? '');
        if (!in_array($type, ['single_choice', 'multiple_choice', 'dropdown'], true)) {
            continue;
        }
        $fieldId = (string) ($field['id'] ?? '');
        $oldField = $oldFieldsById[$fieldId] ?? null;
        if (!is_array($oldField)) {
            continue;
        }
        $currentIds = [];
        foreach ((array) ($field['options'] ?? []) as $option) {
            if (is_array($option) && isset($option['id'])) {
                $currentIds[(string) $option['id']] = true;
            }
        }
        $answeredIds = forms_answered_option_ids_for_field($responses, $fieldId);
        $preserved = [];
        foreach ((array) ($oldField['options'] ?? []) as $oldOption) {
            if (!is_array($oldOption) || !isset($oldOption['id'])) {
                continue;
            }
            $optionId = (string) $oldOption['id'];
            if (isset($currentIds[$optionId])) {
                continue; // still present in the new form
            }
            $hadCapacity = (int) ($oldOption['capacity'] ?? 0) > 0;
            $wasDeleted = !empty($oldOption['deleted']);
            $wasAnswered = isset($answeredIds[$optionId]);
            if ($hadCapacity || $wasDeleted || $wasAnswered) {
                $oldOption['deleted'] = true;
                $preserved[] = $oldOption;
            }
        }
        if ($preserved !== []) {
            $field['options'] = array_merge((array) ($field['options'] ?? []), $preserved);
            $newFields[$index] = $field;
        }
    }
    $newForm['fields'] = $newFields;
    return $newForm;
}

/**
 * Count how many times each option has been selected per field across the given responses.
 * Pass $excludeResponseId to skip one response (used for edit-mode: don't count the user's existing answer).
 * Returns: [ fieldId => [ optionId => count ] ]
 */
function forms_option_capacity_counts_from_responses(array $responses, ?string $excludeResponseId): array
{
    $counts = [];
    foreach ($responses as $response) {
        if (!is_array($response)) {
            continue;
        }
        if ($excludeResponseId !== null && (string) ($response['id'] ?? '') === $excludeResponseId) {
            continue;
        }
        $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
        foreach ($answers as $fieldId => $answer) {
            $fieldId = (string) $fieldId;
            if (!isset($counts[$fieldId])) {
                $counts[$fieldId] = [];
            }
            if (is_array($answer)) {
                foreach ($answer as $optId) {
                    $optId = (string) $optId;
                    if ($optId !== '') {
                        $counts[$fieldId][$optId] = ($counts[$fieldId][$optId] ?? 0) + 1;
                    }
                }
            } else {
                $optId = (string) $answer;
                if ($optId !== '') {
                    $counts[$fieldId][$optId] = ($counts[$fieldId][$optId] ?? 0) + 1;
                }
            }
        }
    }
    return $counts;
}

function forms_payment_fields(array $form): array
{
    return array_values(array_filter((array) ($form['fields'] ?? []), static function ($field): bool {
        return is_array($field) && (string) ($field['type'] ?? '') === 'payment';
    }));
}

function forms_has_payment_fields(array $form): bool
{
    return forms_payment_fields($form) !== [];
}

function forms_receipt_payment_fields(array $form): array
{
    return array_values(array_filter((array) ($form['fields'] ?? []), static function ($field): bool {
        return is_array($field) && (string) ($field['type'] ?? '') === 'receipt_payment';
    }));
}

function forms_has_receipt_payment_fields(array $form): bool
{
    return forms_receipt_payment_fields($form) !== [];
}

function forms_payment_order_matches(array $order, string $formId, string $fieldId, string $identityKey): bool
{
    $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
    return (string) ($extra['_source'] ?? '') === 'form_payment'
        && (string) ($extra['form_id'] ?? '') === $formId
        && (string) ($extra['field_id'] ?? '') === $fieldId
        && (string) ($extra['identity_key'] ?? '') === $identityKey;
}

function forms_payment_order_for_identity(string $formId, string $fieldId, string $identityKey, bool $successOnly = true): ?array
{
    if ($formId === '' || $fieldId === '' || $identityKey === '') {
        return null;
    }

    $store = payments_read_store();
    foreach (($store['orders'] ?? []) as $order) {
        if (!is_array($order) || !forms_payment_order_matches($order, $formId, $fieldId, $identityKey)) {
            continue;
        }
        if ($successOnly && (string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }
        return $order;
    }

    return null;
}

function forms_payment_statuses(array $form, ?string $identityKey): array
{
    $statuses = [];
    if ($identityKey === null || $identityKey === '') {
        return $statuses;
    }
    $formId = (string) ($form['id'] ?? '');
    foreach (forms_payment_fields($form) as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        $order = forms_payment_order_for_identity($formId, $fieldId, $identityKey, true);
        $statuses[$fieldId] = [
            'paid' => $order !== null,
            'orderToken' => $order ? (string) ($order['public_token'] ?? '') : '',
            'refId' => $order ? (string) ($order['ref_id'] ?? '') : '',
            'amount' => $order ? max(0, (int) ($order['amount'] ?? 0)) : max(0, (int) (($field['payment']['amount'] ?? 0))),
        ];
    }
    return $statuses;
}

function forms_payment_answer_from_order(array $order): array
{
    return [
        'status' => 'paid',
        'orderToken' => (string) ($order['public_token'] ?? ''),
        'refId' => (string) ($order['ref_id'] ?? ''),
        'amount' => max(0, (int) ($order['amount'] ?? 0)),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
    ];
}

function forms_collect_payment_answers(array $form, string $identityKey): array
{
    $answers = [];
    $formId = (string) ($form['id'] ?? '');
    foreach (forms_payment_fields($form) as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        if ($fieldId === '') {
            continue;
        }
        $order = forms_payment_order_for_identity($formId, $fieldId, $identityKey, true);
        if ($order === null) {
            if ((bool) ($field['required'] ?? false)) {
                dent_error('پرداخت «' . (string) ($field['label'] ?? 'هزینه') . '» قبل از ثبت پاسخ الزامی است.', 422);
            }
            continue;
        }
        $answers[$fieldId] = forms_payment_answer_from_order($order);
    }
    return $answers;
}

function forms_receipt_upload_matches(array $receipt, string $formId, string $fieldId, string $identityKey): bool
{
    return (string) ($receipt['formId'] ?? '') === $formId
        && (string) ($receipt['fieldId'] ?? '') === $fieldId
        && (string) ($receipt['identityKey'] ?? '') === $identityKey
        && (string) ($receipt['status'] ?? 'uploaded') === 'uploaded';
}

function forms_receipt_upload_for_identity(array $store, string $formId, string $fieldId, string $identityKey): ?array
{
    if ($formId === '' || $fieldId === '' || $identityKey === '') {
        return null;
    }

    $latest = null;
    foreach (($store['receiptUploads'] ?? []) as $receipt) {
        if (!is_array($receipt) || !forms_receipt_upload_matches($receipt, $formId, $fieldId, $identityKey)) {
            continue;
        }
        if ($latest === null || (int) ($receipt['uploadedAt'] ?? 0) >= (int) ($latest['uploadedAt'] ?? 0)) {
            $latest = $receipt;
        }
    }

    return $latest;
}

function forms_receipt_public_payload(?array $receipt): array
{
    if ($receipt === null) {
        return [
            'uploaded' => false,
            'receiptId' => '',
            'originalName' => '',
            'size' => 0,
            'uploadedAt' => null,
        ];
    }

    return [
        'uploaded' => true,
        'receiptId' => (string) ($receipt['id'] ?? ''),
        'originalName' => (string) ($receipt['originalName'] ?? ''),
        'size' => max(0, (int) ($receipt['size'] ?? 0)),
        'uploadedAt' => (int) ($receipt['uploadedAt'] ?? 0),
    ];
}

function forms_receipt_statuses(array $store, array $form, ?string $identityKey): array
{
    $statuses = [];
    if ($identityKey === null || $identityKey === '') {
        return $statuses;
    }
    $formId = (string) ($form['id'] ?? '');
    foreach (forms_receipt_payment_fields($form) as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        $receipt = forms_receipt_upload_for_identity($store, $formId, $fieldId, $identityKey);
        $statuses[$fieldId] = forms_receipt_public_payload($receipt);
    }
    return $statuses;
}

function forms_receipt_answer_from_upload(array $receipt, array $field): array
{
    $config = forms_normalize_receipt_payment_config($field['receiptPayment'] ?? []);
    return [
        'status' => 'uploaded',
        'receiptId' => (string) ($receipt['id'] ?? ''),
        'originalName' => (string) ($receipt['originalName'] ?? ''),
        'mimeType' => (string) ($receipt['mimeType'] ?? ''),
        'size' => max(0, (int) ($receipt['size'] ?? 0)),
        'uploadedAt' => (int) ($receipt['uploadedAt'] ?? 0),
        'amount' => max(0, (int) ($config['amount'] ?? 0)),
        'cardNumber' => (string) ($config['cardNumber'] ?? ''),
        'cardholder' => (string) ($config['cardholder'] ?? ''),
        'bankName' => (string) ($config['bankName'] ?? ''),
    ];
}

function forms_collect_receipt_payment_answers(array $store, array $form, string $identityKey): array
{
    $answers = [];
    $formId = (string) ($form['id'] ?? '');
    foreach (forms_receipt_payment_fields($form) as $field) {
        $fieldId = (string) ($field['id'] ?? '');
        if ($fieldId === '') {
            continue;
        }
        $receipt = forms_receipt_upload_for_identity($store, $formId, $fieldId, $identityKey);
        if ($receipt === null) {
            if ((bool) ($field['required'] ?? false)) {
                dent_error('آپلود رسید «' . (string) ($field['label'] ?? 'پرداخت با رسید') . '» قبل از ثبت پاسخ الزامی است.', 422);
            }
            continue;
        }
        $answers[$fieldId] = forms_receipt_answer_from_upload($receipt, $field);
    }
    return $answers;
}

function forms_option_map(array $field): array
{
    $map = [];
    foreach ((array) ($field['options'] ?? []) as $option) {
        if (is_array($option)) {
            $id = (string) ($option['id'] ?? '');
            if ($id !== '') {
                $text = (string) ($option['text'] ?? $id);
                if (!empty($option['deleted'])) {
                    $text .= ' (حذف‌شده)';
                }
                $map[$id] = $text;
            }
        }
    }
    return $map;
}

function forms_row_map(array $field): array
{
    $map = [];
    foreach ((array) ($field['rows'] ?? []) as $row) {
        if (is_array($row)) {
            $id = (string) ($row['id'] ?? '');
            if ($id !== '') {
                $map[$id] = (string) ($row['text'] ?? $id);
            }
        }
    }
    return $map;
}

function forms_normalize_answer(array $field, $raw)
{
    $type = (string) ($field['type'] ?? 'short_text');
    $required = (bool) ($field['required'] ?? false);
    $label = (string) ($field['label'] ?? 'فیلد');

    if ($type === 'payment' || $type === 'receipt_payment') {
        return '';
    }

    if ($type === 'multiple_choice') {
        $items = [];
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $items = is_array($decoded) ? $decoded : preg_split('/[\s,،;]+/u', trim($raw));
        }
        $allowed = forms_option_map($field);
        $selected = [];
        foreach ($items as $item) {
            $id = trim((string) $item);
            if ($id !== '' && isset($allowed[$id])) {
                $selected[$id] = true;
            }
        }
        $values = array_keys($selected);
        if ($required && $values === []) {
            dent_error('پاسخ «' . $label . '» الزامی است.', 422);
        }
        return $values;
    }

    if (in_array($type, ['multiple_choice_grid', 'checkbox_grid'], true)) {
        $rawMap = is_array($raw) ? $raw : [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $rawMap = is_array($decoded) ? $decoded : [];
        }
        $rows = forms_row_map($field);
        $allowed = forms_option_map($field);
        $answer = [];
        foreach ($rows as $rowId => $_rowText) {
            $rowValue = $rawMap[$rowId] ?? null;
            if ($type === 'checkbox_grid') {
                $rowItems = is_array($rowValue) ? $rowValue : [];
                $selected = [];
                foreach ($rowItems as $item) {
                    $id = trim((string) $item);
                    if ($id !== '' && isset($allowed[$id])) {
                        $selected[$id] = true;
                    }
                }
                $answer[$rowId] = array_keys($selected);
            } else {
                $id = trim((string) $rowValue);
                $answer[$rowId] = $id !== '' && isset($allowed[$id]) ? $id : '';
            }
        }
        if ($required) {
            foreach ($answer as $rowAnswer) {
                if (is_array($rowAnswer) ? $rowAnswer === [] : $rowAnswer === '') {
                    dent_error('پاسخ همه ردیف‌های «' . $label . '» الزامی است.', 422);
                }
            }
        }
        return $answer;
    }

    $value = forms_clean_text($raw ?? '', $type === 'paragraph' ? 4000 : 700);
    if ($required && $value === '') {
        dent_error('پاسخ «' . $label . '» الزامی است.', 422);
    }
    if ($value === '') {
        return '';
    }

    if (in_array($type, ['single_choice', 'dropdown', 'linear_scale'], true)) {
        $allowed = forms_option_map($field);
        if (!isset($allowed[$value])) {
            dent_error('گزینه انتخاب‌شده برای «' . $label . '» معتبر نیست.', 422);
        }
        return $value;
    }

    if ($type === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        dent_error('ایمیل واردشده برای «' . $label . '» معتبر نیست.', 422);
    }
    if ($type === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
        dent_error('لینک واردشده برای «' . $label . '» معتبر نیست.', 422);
    }
    if ($type === 'phone') {
        $digits = forms_only_digits($value);
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 14) {
            dent_error('شماره تماس واردشده برای «' . $label . '» معتبر نیست.', 422);
        }
        return $digits;
    }
    if ($type === 'number') {
        $normalized = dent_normalize_digits($value);
        if (!is_numeric($normalized)) {
            dent_error('عدد واردشده برای «' . $label . '» معتبر نیست.', 422);
        }
        return (string) $normalized;
    }
    if ($type === 'date' && strtotime($value) === false) {
        dent_error('تاریخ واردشده برای «' . $label . '» معتبر نیست.', 422);
    }

    return $value;
}

function forms_collect_answers(array $form, array $source): array
{
    $rawAnswers = $source['answers'] ?? [];
    if (is_string($rawAnswers)) {
        $decoded = json_decode($rawAnswers, true);
        $rawAnswers = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($rawAnswers)) {
        $rawAnswers = [];
    }

    $answers = [];
    foreach ((array) ($form['fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }
        $fieldId = (string) ($field['id'] ?? '');
        if ($fieldId === '') {
            continue;
        }
        if (in_array((string) ($field['type'] ?? ''), ['payment', 'receipt_payment'], true)) {
            continue;
        }
        $answers[$fieldId] = forms_normalize_answer($field, $rawAnswers[$fieldId] ?? null);
    }
    return $answers;
}

function forms_response_payload(array $response, array $form): array
{
    $fieldsById = [];
    foreach ((array) ($form['fields'] ?? []) as $field) {
        if (is_array($field) && isset($field['id'])) {
            $fieldsById[(string) $field['id']] = $field;
        }
    }
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $anonymous = forms_parse_bool($settings['anonymousResponses'] ?? false, false);
    $identity = is_array($response['identity'] ?? null) ? $response['identity'] : [];
    if ($anonymous) {
        $identity = [
            'kind' => (string) ($identity['kind'] ?? 'guest'),
            'key' => '',
            'studentNumber' => '',
            'name' => 'پاسخ ناشناس',
            'roleLabel' => '',
            'phone' => '',
        ];
    }

    $answerPayload = [];
    foreach ((array) ($response['answers'] ?? []) as $fieldId => $answer) {
        $field = $fieldsById[(string) $fieldId] ?? null;
        $answerPayload[] = [
            'fieldId' => (string) $fieldId,
            'label' => is_array($field) ? (string) ($field['label'] ?? $fieldId) : (string) $fieldId,
            'type' => is_array($field) ? (string) ($field['type'] ?? 'short_text') : 'short_text',
            'value' => $answer,
            'displayValue' => forms_answer_display_value(is_array($field) ? $field : [], $answer),
        ];
    }

    return [
        'id' => (string) ($response['id'] ?? ''),
        'formId' => (string) ($response['formId'] ?? ''),
        'submittedAt' => (int) ($response['submittedAt'] ?? 0),
        'updatedAt' => (int) ($response['updatedAt'] ?? 0),
        'identity' => $identity,
        'answers' => $answerPayload,
    ];
}

function forms_answer_display_value(array $field, $answer): string
{
    if ($answer === null || $answer === '') {
        return '';
    }
    if ((string) ($field['type'] ?? '') === 'payment') {
        if (is_array($answer) && (string) ($answer['status'] ?? '') === 'paid') {
            $amount = number_format(max(0, (int) ($answer['amount'] ?? 0)));
            $refId = trim((string) ($answer['refId'] ?? ''));
            return 'پرداخت شده - ' . $amount . ' ریال' . ($refId !== '' ? ' - ref: ' . $refId : '');
        }
        return 'پرداخت نشده';
    }
    if ((string) ($field['type'] ?? '') === 'receipt_payment') {
        if (is_array($answer) && (string) ($answer['status'] ?? '') === 'uploaded') {
            $amount = number_format(max(0, (int) ($answer['amount'] ?? 0)));
            $name = trim((string) ($answer['originalName'] ?? ''));
            return 'رسید آپلود شد - ' . $amount . ' ریال' . ($name !== '' ? ' - ' . $name : '');
        }
        return 'رسید آپلود نشده';
    }
    $optionMap = forms_option_map($field);
    $type = (string) ($field['type'] ?? '');
    $isChoice = in_array($type, ['single_choice', 'multiple_choice', 'dropdown', 'linear_scale', 'multiple_choice_grid', 'checkbox_grid'], true);
    // For choice fields an option id with no matching option means the option was
    // fully removed; show an explicit marker instead of the raw id. For free-text
    // fields the answer is the literal value and must pass through unchanged.
    $resolveOption = static function (string $key) use ($optionMap, $isChoice): string {
        if (isset($optionMap[$key])) {
            return $optionMap[$key];
        }
        return $isChoice ? ('گزینه حذف‌شده (' . $key . ')') : $key;
    };
    if (is_array($answer)) {
        if (in_array($type, ['multiple_choice_grid', 'checkbox_grid'], true)) {
            $rowMap = forms_row_map($field);
            $rows = [];
            foreach ($answer as $rowId => $rowAnswer) {
                $rowLabel = $rowMap[(string) $rowId] ?? (string) $rowId;
                if (is_array($rowAnswer)) {
                    $parts = [];
                    foreach ($rowAnswer as $item) {
                        $parts[] = $resolveOption((string) $item);
                    }
                    $rows[] = $rowLabel . ': ' . implode('، ', $parts);
                } else {
                    $rows[] = $rowLabel . ': ' . $resolveOption((string) $rowAnswer);
                }
            }
            return implode(' | ', array_filter($rows, static fn(string $item): bool => trim($item) !== ''));
        }
        $parts = [];
        foreach ($answer as $item) {
            $parts[] = $resolveOption((string) $item);
        }
        return implode('، ', $parts);
    }
    return $resolveOption((string) $answer);
}

function forms_poll_results(array $store, array $form, ?array $viewer, ?string $identityKey = null): array
{
    if ((string) ($form['kind'] ?? '') !== 'poll') {
        return ['visible' => false, 'options' => [], 'totalResponses' => 0, 'hiddenReason' => ''];
    }

    $fields = (array) ($form['fields'] ?? []);
    $field = null;
    foreach ($fields as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        if (in_array((string) ($candidate['type'] ?? ''), ['single_choice', 'multiple_choice'], true)) {
            $field = $candidate;
            break;
        }
    }
    if ($field === null) {
        return ['visible' => false, 'options' => [], 'totalResponses' => 0, 'hiddenReason' => ''];
    }

    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $visibility = forms_clean_result_visibility((string) ($settings['resultVisibility'] ?? 'after-submit'));
    $status = forms_status($form);
    $canManage = forms_can_manage($form, $viewer);
    $hasSubmitted = false;
    if ($identityKey !== null && $identityKey !== '') {
        $hasSubmitted = forms_response_for_identity($store, (string) ($form['id'] ?? ''), $identityKey) !== null;
    }

    $visible = $canManage || $visibility === 'live' || ($visibility === 'after-close' && $status === 'closed') || ($visibility === 'after-submit' && $hasSubmitted);
    $responses = forms_responses_for_form($store, (string) ($form['id'] ?? ''));
    $counts = [];
    foreach ((array) ($field['options'] ?? []) as $option) {
        if (is_array($option)) {
            $counts[(string) ($option['id'] ?? '')] = 0;
        }
    }

    foreach ($responses as $response) {
        $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
        $answer = $answers[(string) ($field['id'] ?? '')] ?? null;
        if (is_array($answer)) {
            foreach ($answer as $id) {
                $id = (string) $id;
                if (isset($counts[$id])) {
                    $counts[$id]++;
                }
            }
        } else {
            $id = (string) $answer;
            if (isset($counts[$id])) {
                $counts[$id]++;
            }
        }
    }

    $total = count($responses);
    $options = [];
    foreach ((array) ($field['options'] ?? []) as $option) {
        if (!is_array($option)) {
            continue;
        }
        $id = (string) ($option['id'] ?? '');
        $count = $counts[$id] ?? 0;
        $options[] = [
            'id' => $id,
            'text' => (string) ($option['text'] ?? $id),
            'count' => $visible ? $count : null,
            'percent' => $visible && $total > 0 ? round(($count * 1000) / $total) / 10 : null,
        ];
    }

    $hiddenReason = '';
    if (!$visible) {
        $hiddenReason = $visibility === 'after-close'
            ? 'نتایج بعد از بسته‌شدن نظرسنجی نمایش داده می‌شود.'
            : 'نتایج فعلاً فقط برای سازنده/مدیر قابل مشاهده است.';
    }

    return [
        'visible' => $visible,
        'totalResponses' => $visible ? $total : null,
        'hiddenReason' => $hiddenReason,
        'options' => $options,
    ];
}

function forms_form_payload(array $store, array $form, ?array $viewer = null, bool $includeFields = true, ?string $identityKeyOverride = null): array
{
    $formId = (string) ($form['id'] ?? '');
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    $audience = forms_normalize_audience_for_cohort((string) ($settings['audience'] ?? 'link'), forms_form_cohort($form));
    $status = forms_status($form);
    $canManage = forms_can_manage($form, $viewer);
    $responses = forms_responses_for_form($store, $formId);
    $identityKey = $identityKeyOverride !== null ? $identityKeyOverride : ($viewer !== null ? forms_identity_key($viewer, []) : null);
    $alreadySubmitted = $identityKey !== null && $identityKey !== ''
        ? forms_response_for_identity($store, $formId, $identityKey) !== null
        : false;
    $limitOneResponse = forms_parse_bool($settings['limitOneResponse'] ?? true, true);
    $canSubmit = $status === 'open' && forms_viewer_can_access($form, $viewer);
    if ($canSubmit && $viewer !== null && forms_can_manage($form, $viewer) && !forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true)) {
        $canSubmit = false;
    }
    if ($canSubmit && $limitOneResponse && $alreadySubmitted && !forms_parse_bool($settings['allowEditResponse'] ?? false, false)) {
        $canSubmit = false;
    }

    $fieldsPayload = $includeFields ? (array) ($form['fields'] ?? []) : [];
    $paymentStatuses = $includeFields ? forms_payment_statuses($form, $identityKey) : [];
    $receiptStatuses = $includeFields ? forms_receipt_statuses($store, $form, $identityKey) : [];
    if ($fieldsPayload !== [] && $paymentStatuses !== []) {
        foreach ($fieldsPayload as $index => $field) {
            if (!is_array($field) || (string) ($field['type'] ?? '') !== 'payment') {
                continue;
            }
            $fieldId = (string) ($field['id'] ?? '');
            $field['paymentStatus'] = $paymentStatuses[$fieldId] ?? ['paid' => false, 'orderToken' => '', 'refId' => '', 'amount' => max(0, (int) (($field['payment']['amount'] ?? 0)))];
            $fieldsPayload[$index] = $field;
        }
    }
    if ($fieldsPayload !== [] && $receiptStatuses !== []) {
        foreach ($fieldsPayload as $index => $field) {
            if (!is_array($field) || (string) ($field['type'] ?? '') !== 'receipt_payment') {
                continue;
            }
            $fieldId = (string) ($field['id'] ?? '');
            $field['receiptStatus'] = $receiptStatuses[$fieldId] ?? forms_receipt_public_payload(null);
            $fieldsPayload[$index] = $field;
        }
    }

    // Enrich choice-field options with live capacity counts so the fill view
    // can show remaining slots and disable full options.
    if ($fieldsPayload !== []) {
        $capExcludeId = null;
        if ($identityKey !== null && $identityKey !== '' && forms_parse_bool($settings['allowEditResponse'] ?? false, false)) {
            $existingResp = forms_response_for_identity($store, $formId, $identityKey);
            if (is_array($existingResp)) {
                $capExcludeId = (string) ($existingResp['id'] ?? null);
            }
        }
        $capCounts = null; // lazy – only computed if at least one field has capacity
        foreach ($fieldsPayload as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $ftype = (string) ($field['type'] ?? '');
            if ($ftype !== 'single_choice' && $ftype !== 'multiple_choice' && $ftype !== 'dropdown') {
                continue;
            }
            $options = is_array($field['options'] ?? null) ? $field['options'] : [];
            $hasCapacity = false;
            foreach ($options as $opt) {
                if (is_array($opt) && (int) ($opt['capacity'] ?? 0) > 0) {
                    $hasCapacity = true;
                    break;
                }
            }
            if (!$hasCapacity) {
                continue;
            }
            if ($capCounts === null) {
                $capCounts = forms_option_capacity_counts_from_responses($responses, $capExcludeId);
            }
            $fieldId = (string) ($field['id'] ?? '');
            $fieldCounts = $capCounts[$fieldId] ?? [];
            $enrichedOptions = [];
            foreach ($options as $opt) {
                if (!is_array($opt)) {
                    $enrichedOptions[] = $opt;
                    continue;
                }
                $cap = (int) ($opt['capacity'] ?? 0);
                if ($cap > 0) {
                    $used = (int) ($fieldCounts[(string) ($opt['id'] ?? '')] ?? 0);
                    $opt['capacityUsed'] = $used;
                    $opt['capacityRemaining'] = max(0, $cap - $used);
                    $opt['capacityFull'] = $used >= $cap;
                }
                $enrichedOptions[] = $opt;
            }
            $field['options'] = $enrichedOptions;
            $fieldsPayload[$index] = $field;
        }
    }

    // Hide soft-deleted options from builders and respondents alike. Their data is
    // still retained in the store (used for capacity counting and response display),
    // but they must never appear as a selectable option again.
    if ($fieldsPayload !== []) {
        foreach ($fieldsPayload as $index => $field) {
            if (!is_array($field) || !is_array($field['options'] ?? null)) {
                continue;
            }
            $visibleOptions = [];
            foreach ($field['options'] as $opt) {
                if (is_array($opt) && !empty($opt['deleted'])) {
                    continue;
                }
                $visibleOptions[] = $opt;
            }
            $field['options'] = array_values($visibleOptions);
            $fieldsPayload[$index] = $field;
        }
    }

    // Provide the viewer's previous answers when editing is allowed, so the
    // respondent can see and modify their prior submission instead of a blank form.
    $existingResponsePayload = null;
    if ($includeFields && $identityKey !== null && $identityKey !== ''
        && forms_parse_bool($settings['allowEditResponse'] ?? false, false)) {
        $priorResponse = forms_response_for_identity($store, $formId, $identityKey);
        if (is_array($priorResponse)) {
            $existingResponsePayload = [
                'id' => (string) ($priorResponse['id'] ?? ''),
                'answers' => is_array($priorResponse['answers'] ?? null) ? $priorResponse['answers'] : [],
            ];
        }
    }

    $settingsPayload = [
        'allowGuest' => forms_parse_bool($settings['allowGuest'] ?? false, false),
        'collectGuestName' => forms_parse_bool($settings['collectGuestName'] ?? true, true),
        'collectGuestPhone' => forms_parse_bool($settings['collectGuestPhone'] ?? false, false),
        'limitOneResponse' => forms_parse_bool($settings['limitOneResponse'] ?? true, true),
        'allowEditResponse' => forms_parse_bool($settings['allowEditResponse'] ?? false, false),
        'allowCreatorSubmit' => forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true),
        'resultVisibility' => forms_clean_result_visibility((string) ($settings['resultVisibility'] ?? 'after-submit')),
        'startAt' => forms_parse_timestamp($settings['startAt'] ?? null),
        'endAt' => forms_parse_timestamp($settings['endAt'] ?? null),
    ];
    if ($canManage) {
        $settingsPayload['audience'] = $audience;
        $settingsPayload['audienceLabel'] = forms_audience_label($audience);
        $settingsPayload['allowedStudents'] = forms_normalize_student_numbers($settings['allowedStudents'] ?? []);
        $settingsPayload['allowRepresentativeManage'] = forms_parse_bool($settings['allowRepresentativeManage'] ?? false, false);
        $settingsPayload['managerStudentNumbers'] = forms_normalize_student_numbers($settings['managerStudentNumbers'] ?? []);
        $settingsPayload['anonymousResponses'] = forms_parse_bool($settings['anonymousResponses'] ?? false, false);
        $settingsPayload['export'] = forms_normalize_export_settings($settings['export'] ?? []);
    }

    return [
        'id' => $formId,
        'cohort' => forms_form_cohort($form),
        'kind' => (string) ($form['kind'] ?? 'form'),
        'kindLabel' => forms_kind_label((string) ($form['kind'] ?? 'form')),
        'title' => (string) ($form['title'] ?? ''),
        'description' => (string) ($form['description'] ?? ''),
        'status' => $status,
        'statusLabel' => forms_status_label($status),
        'createdBy' => (string) ($form['createdBy'] ?? ''),
        'createdAt' => (int) ($form['createdAt'] ?? 0),
        'updatedAt' => (int) ($form['updatedAt'] ?? 0),
        'sharePath' => forms_share_path($formId, $form),
        'shareUrl' => forms_absolute_url(forms_share_path($formId, $form)),
        'responseCount' => $canManage ? count($responses) : null,
        'hasReceiptPaymentFields' => forms_has_receipt_payment_fields($form),
        'fields' => $fieldsPayload,
        'paymentGateways' => $includeFields && forms_has_payment_fields($form) ? forms_payment_gateways_payload() : null,
        'settings' => $settingsPayload,
        'existingResponse' => $existingResponsePayload,
        'permissions' => [
            'canManage' => $canManage,
            'canDelete' => forms_can_delete($form, $viewer),
            'canCreate' => forms_can_create($viewer),
            'canSubmit' => $canSubmit,
            'guestAllowed' => forms_guest_allowed($form),
            'alreadySubmitted' => $alreadySubmitted,
        ],
        'results' => forms_poll_results($store, $form, $viewer, $identityKey),
    ];
}

function forms_request_payload(): array
{
    $raw = $_POST['payload'] ?? '';
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

function forms_build_form_from_payload(array $payload, array $user, ?array $existing = null): array
{
    $kind = forms_clean_kind((string) ($payload['kind'] ?? ($existing['kind'] ?? 'form')));
    $fields = forms_normalize_fields($payload['fields'] ?? ($existing['fields'] ?? []), $kind);
    if ($fields === []) {
        dent_error('حداقل یک پرسش معتبر لازم است.', 422);
    }

    $title = forms_clean_text($payload['title'] ?? ($existing['title'] ?? ''), 160);
    if ($title === '') {
        dent_error('عنوان فرم الزامی است.', 422);
    }

    $settingsRaw = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
    $existingSettings = is_array($existing['settings'] ?? null) ? $existing['settings'] : [];
    $settingsRaw = array_merge($existingSettings, $settingsRaw);
    if (!forms_can_create($user) && $existing !== null) {
        $settingsRaw['allowRepresentativeManage'] = $existingSettings['allowRepresentativeManage'] ?? false;
        $settingsRaw['managerStudentNumbers'] = $existingSettings['managerStudentNumbers'] ?? [];
    }
    $startAt = forms_parse_timestamp($settingsRaw['startAt'] ?? null);
    $endAt = forms_parse_timestamp($settingsRaw['endAt'] ?? null);
    if ($startAt !== null && $endAt !== null && $endAt <= $startAt) {
        dent_error('زمان پایان باید بعد از زمان شروع باشد.', 422);
    }

    $now = time();
    $formId = $existing !== null ? (string) ($existing['id'] ?? '') : forms_next_id(FORMS_ID_PREFIX);
    $createdBy = $existing !== null
        ? dent_normalize_student_number((string) ($existing['createdBy'] ?? ''))
        : dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));

    return [
        'id' => $formId,
        'cohort' => $existing !== null ? forms_form_cohort($existing) : forms_active_cohort(),
        'kind' => $kind,
        'title' => $title,
        'description' => forms_clean_text($payload['description'] ?? ($existing['description'] ?? ''), 1400),
        'status' => forms_clean_status((string) ($payload['status'] ?? ($existing['status'] ?? 'open'))),
        'createdBy' => $createdBy,
        'createdAt' => $existing !== null ? (int) ($existing['createdAt'] ?? $now) : $now,
        'updatedAt' => $now,
        'fields' => $fields,
        'settings' => [
            'audience' => forms_normalize_audience_for_cohort((string) ($settingsRaw['audience'] ?? 'link'), $existing !== null ? forms_form_cohort($existing) : forms_active_cohort()),
            'allowedStudents' => forms_normalize_student_numbers($settingsRaw['allowedStudents'] ?? []),
            'allowRepresentativeManage' => forms_parse_bool($settingsRaw['allowRepresentativeManage'] ?? false, false),
            'managerStudentNumbers' => forms_normalize_student_numbers($settingsRaw['managerStudentNumbers'] ?? []),
            'allowGuest' => forms_parse_bool($settingsRaw['allowGuest'] ?? false, false),
            'collectGuestName' => forms_parse_bool($settingsRaw['collectGuestName'] ?? true, true),
            'collectGuestPhone' => forms_parse_bool($settingsRaw['collectGuestPhone'] ?? false, false),
            'limitOneResponse' => forms_parse_bool($settingsRaw['limitOneResponse'] ?? true, true),
            'allowEditResponse' => forms_parse_bool($settingsRaw['allowEditResponse'] ?? false, false),
            'allowCreatorSubmit' => forms_parse_bool($settingsRaw['allowCreatorSubmit'] ?? true, true),
            'anonymousResponses' => forms_parse_bool($settingsRaw['anonymousResponses'] ?? false, false),
            'export' => forms_normalize_export_settings($settingsRaw['export'] ?? []),
            'resultVisibility' => forms_clean_result_visibility((string) ($settingsRaw['resultVisibility'] ?? 'after-submit')),
            'startAt' => $startAt,
            'endAt' => $endAt,
        ],
    ];
}

function forms_active_count_for_user(array $store, array $user): int
{
    $count = 0;
    foreach ($store['forms'] as $form) {
        if (!is_array($form) || forms_status($form) !== 'open') {
            continue;
        }
        if (forms_viewer_can_access($form, $user)) {
            $count++;
        }
    }
    return $count;
}

function forms_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function forms_xlsx_column_name(int $columnNumber): string
{
    $name = '';
    while ($columnNumber > 0) {
        $remainder = ($columnNumber - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $columnNumber = intdiv($columnNumber - 1, 26);
    }
    return $name;
}

function forms_xlsx_cell(int $row, int $column, int $style, string $value): string
{
    $ref = forms_xlsx_column_name($column) . (string) $row;
    return '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . forms_xml_escape($value) . '</t></is></c>';
}

function forms_xlsx_row(int $row, array $values, int $style, ?float $height = null): string
{
    $attrs = ' r="' . $row . '"';
    if ($height !== null) {
        $attrs .= ' ht="' . rtrim(rtrim(number_format($height, 2, '.', ''), '0'), '.') . '" customHeight="1"';
    }
    $cells = [];
    $column = 1;
    foreach ($values as $value) {
        $cells[] = forms_xlsx_cell($row, $column, $style, (string) $value);
        $column++;
    }
    return '<row' . $attrs . '>' . implode('', $cells) . '</row>';
}

function forms_xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3"><font><sz val="11"/><name val="B Nazanin"/></font><font><b/><sz val="11"/><name val="B Nazanin"/></font><font><b/><sz val="15"/><name val="B Nazanin"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="5">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" applyAlignment="1"><alignment horizontal="right" vertical="top" wrapText="1" readingOrder="2"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1" readingOrder="2"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}

function forms_xlsx_sheet_xml(string $title, array $headers, array $rows): string
{
    $columnCount = max(1, count($headers));
    $lastColumn = forms_xlsx_column_name($columnCount);
    $sheetRows = [];
    $sheetRows[] = forms_xlsx_row(1, [$title], 1, 32.0);
    $sheetRows[] = '<row r="2"></row>';
    $sheetRows[] = forms_xlsx_row(3, $headers, 2, 24.0);
    $rowNumber = 4;
    foreach ($rows as $row) {
        while (count($row) < $columnCount) {
            $row[] = '';
        }
        $sheetRows[] = forms_xlsx_row($rowNumber, array_slice($row, 0, $columnCount), 3, 24.0);
        $rowNumber++;
    }
    $signatureRow = $rowNumber + 1;
    $sheetRows[] = forms_xlsx_row($signatureRow, ['بخش تایید و امضا'], 4, 25.0);
    $sheetRows[] = forms_xlsx_row($signatureRow + 1, ['تهیه‌کننده خروجی: ماژول فرم‌ها و نظرسنجی‌ها'], 0, 24.0);

    $cols = [];
    for ($i = 1; $i <= $columnCount; $i++) {
        $width = $i <= 3 ? 16 : 24;
        $cols[] = '<col min="' . $i . '" max="' . $i . '" width="' . $width . '" customWidth="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:' . $lastColumn . (string) ($signatureRow + 1) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="22"/><cols>' . implode('', $cols) . '</cols>'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '<mergeCells count="2"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A' . $signatureRow . ':' . $lastColumn . $signatureRow . '"/></mergeCells>'
        . '<autoFilter ref="A3:' . $lastColumn . max(3, $rowNumber - 1) . '"/>'
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
}

function forms_zip_dos_datetime(): array
{
    $parts = getdate();
    $year = max(1980, (int) $parts['year']);
    $dosTime = (((int) $parts['hours'] & 0x1F) << 11) | (((int) $parts['minutes'] & 0x3F) << 5) | ((int) floor((int) $parts['seconds'] / 2) & 0x1F);
    $dosDate = ((($year - 1980) & 0x7F) << 9) | (((int) $parts['mon'] & 0x0F) << 5) | ((int) $parts['mday'] & 0x1F);
    return [$dosTime, $dosDate];
}

function forms_build_zip_archive(array $files): string
{
    [$dosTime, $dosDate] = forms_zip_dos_datetime();
    $localData = '';
    $centralDirectory = '';
    $offset = 0;
    $entryCount = 0;
    foreach ($files as $name => $content) {
        $fileName = str_replace('\\', '/', (string) $name);
        $data = (string) $content;
        $crc = (int) sprintf('%u', crc32($data));
        $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, strlen($data), strlen($data), strlen($fileName), 0);
        $localData .= $localHeader . $fileName . $data;
        $centralHeader = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, strlen($data), strlen($data), strlen($fileName), 0, 0, 0, 0, 0, $offset);
        $centralDirectory .= $centralHeader . $fileName;
        $offset += strlen($localHeader) + strlen($fileName) + strlen($data);
        $entryCount++;
    }
    return $localData . $centralDirectory . pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($centralDirectory), strlen($localData), 0);
}

function forms_export_xlsx(array $form, array $responses, string $mode = 'responses'): void
{
    $mode = in_array($mode, ['responses', 'summary', 'official'], true) ? $mode : 'responses';
    $fields = array_values(array_filter((array) ($form['fields'] ?? []), static fn($field): bool => is_array($field)));

    if ($mode === 'summary') {
        $headers = ['پرسش', 'گزینه/مقدار', 'تعداد'];
        $rows = [];
        foreach ($fields as $field) {
            $fieldId = (string) ($field['id'] ?? '');
            $type = (string) ($field['type'] ?? '');
            if (!in_array($type, ['single_choice', 'multiple_choice', 'dropdown', 'linear_scale', 'multiple_choice_grid', 'checkbox_grid'], true)) {
                continue;
            }
            if (in_array($type, ['multiple_choice_grid', 'checkbox_grid'], true)) {
                $optionMap = forms_option_map($field);
                foreach (forms_row_map($field) as $rowId => $rowLabel) {
                    $counts = [];
                    foreach ($optionMap as $optionId => $_label) {
                        $counts[$optionId] = 0;
                    }
                    foreach ($responses as $response) {
                        $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
                        $gridAnswer = is_array($answers[$fieldId] ?? null) ? $answers[$fieldId] : [];
                        $rowAnswer = $gridAnswer[$rowId] ?? null;
                        foreach (is_array($rowAnswer) ? $rowAnswer : [$rowAnswer] as $item) {
                            $key = (string) $item;
                            if (isset($counts[$key])) {
                                $counts[$key]++;
                            }
                        }
                    }
                    foreach ($counts as $optionId => $count) {
                        $rows[] = [
                            (string) ($field['label'] ?? '') . ' / ' . $rowLabel,
                            (string) ($optionMap[$optionId] ?? $optionId),
                            (string) $count,
                        ];
                    }
                }
                continue;
            }
            $counts = [];
            foreach (forms_option_map($field) as $optionId => $_label) {
                $counts[$optionId] = 0;
            }
            foreach ($responses as $response) {
                $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
                $answer = $answers[$fieldId] ?? null;
                foreach (is_array($answer) ? $answer : [$answer] as $item) {
                    $key = (string) $item;
                    if (isset($counts[$key])) {
                        $counts[$key]++;
                    }
                }
            }
            foreach ($counts as $optionId => $count) {
                $rows[] = [
                    (string) ($field['label'] ?? ''),
                    forms_answer_display_value($field, $optionId),
                    (string) $count,
                ];
            }
        }
    } else {
        $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
        $export = forms_normalize_export_settings($settings['export'] ?? []);
        $identityLabels = [
            'index' => 'ردیف',
            'responseId' => 'شناسه پاسخ',
            'submittedAt' => 'زمان ثبت',
            'participantKind' => 'نوع شرکت‌کننده',
            'name' => 'نام',
            'studentNumber' => 'شماره دانشجویی',
            'roleLabel' => 'نقش',
            'phone' => 'تلفن',
        ];
        $identityColumns = (array) ($export['identityColumns'] ?? array_keys($identityLabels));
        $headers = [];
        foreach ($identityColumns as $column) {
            if (isset($identityLabels[$column])) {
                $headers[] = $identityLabels[$column];
            }
        }

        $fieldIdFilter = [];
        foreach ((array) ($export['fieldIds'] ?? []) as $fieldId) {
            $fieldIdFilter[(string) $fieldId] = true;
        }
        $includeAllFields = $mode === 'official' || forms_parse_bool($export['includeAllFields'] ?? true, true);
        $exportFields = [];
        foreach ($fields as $field) {
            $fieldId = (string) ($field['id'] ?? '');
            if ($fieldId === '') {
                continue;
            }
            if ($includeAllFields || isset($fieldIdFilter[$fieldId])) {
                $exportFields[] = $field;
                $headers[] = (string) ($field['label'] ?? '');
            }
        }

        $rows = [];
        $index = 1;
        $anonymous = forms_parse_bool($settings['anonymousResponses'] ?? false, false);
        foreach ($responses as $response) {
            $identity = is_array($response['identity'] ?? null) ? $response['identity'] : [];
            if ($anonymous) {
                $identity = [
                    'kind' => (string) ($identity['kind'] ?? 'guest'),
                    'name' => 'پاسخ ناشناس',
                    'studentNumber' => '',
                    'roleLabel' => '',
                    'phone' => '',
                ];
            }
            $identityValues = [
                'index' => (string) $index,
                'responseId' => (string) ($response['id'] ?? ''),
                'submittedAt' => date('Y-m-d H:i:s', (int) ($response['submittedAt'] ?? time())),
                'participantKind' => (string) ($identity['kind'] ?? '') === 'user' ? 'کاربر سایت' : 'مهمان',
                'name' => (string) ($identity['name'] ?? ''),
                'studentNumber' => (string) ($identity['studentNumber'] ?? ''),
                'roleLabel' => (string) ($identity['roleLabel'] ?? ''),
                'phone' => (string) ($identity['phone'] ?? ''),
            ];
            $row = [];
            foreach ($identityColumns as $column) {
                if (array_key_exists((string) $column, $identityValues)) {
                    $row[] = $identityValues[(string) $column];
                }
            }
            $answers = is_array($response['answers'] ?? null) ? $response['answers'] : [];
            foreach ($exportFields as $field) {
                $fieldId = (string) ($field['id'] ?? '');
                $row[] = forms_answer_display_value($field, $answers[$fieldId] ?? '');
            }
            $rows[] = $row;
            $index++;
        }
    }

    $modeLabel = $mode === 'summary' ? 'خلاصه' : ($mode === 'official' ? 'خروجی رسمی' : 'پاسخ‌ها');
    $title = $modeLabel . ' - ' . forms_kind_label((string) ($form['kind'] ?? 'form')) . ' - ' . (string) ($form['title'] ?? '');
    $sheetXml = forms_xlsx_sheet_xml($title, $headers, $rows);
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Dentistry1402 Forms</Application></Properties>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . forms_xml_escape($title) . '</dc:title><dc:creator>Dentistry1402 Forms</dc:creator><cp:lastModifiedBy>Dentistry1402 Forms</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Responses" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => forms_xlsx_styles_xml(),
        'xl/worksheets/sheet1.xml' => $sheetXml,
    ];

    if (class_exists('ZipArchive')) {
        $tmpPath = tempnam(sys_get_temp_dir(), 'forms-xlsx-');
        if ($tmpPath === false) {
            dent_error('ساخت فایل خروجی انجام نشد.', 500);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($tmpPath);
            dent_error('ساخت فایل خروجی انجام نشد.', 500);
        }
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $binary = file_get_contents($tmpPath);
        @unlink($tmpPath);
        if ($binary === false) {
            dent_error('خواندن فایل خروجی انجام نشد.', 500);
        }
    } else {
        $binary = forms_build_zip_archive($files);
    }

    $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($form['id'] ?? 'forms-export')) . '-' . $mode . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . (string) strlen($binary));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $binary;
    exit;
}

function forms_receipt_file_path(array $receipt): string
{
    $clean = forms_clean_receipt_stored_path((string) ($receipt['storedPath'] ?? ''));
    if ($clean === '') {
        return '';
    }
    $cohort = forms_clean_cohort((string) ($receipt['cohort'] ?? 'main'));
    return forms_receipts_dir($cohort) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
}

function forms_delete_receipt_file(array $receipt): void
{
    $path = forms_receipt_file_path($receipt);
    if ($path === '' || !is_file($path)) {
        return;
    }
    @unlink($path);
}

function forms_uploaded_receipt_mime(string $tmpName, string $originalName): string
{
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = @finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if (is_string($detected)) {
                $mime = strtolower(trim($detected));
            }
        }
    }
    if ($mime === '') {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $extension === 'pdf' ? 'application/pdf' : 'application/octet-stream';
    }
    return $mime;
}

function forms_receipt_extension(string $mime, string $originalName): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($mime === 'application/pdf' || $extension === 'pdf') {
        return 'pdf';
    }
    if (in_array($mime, ['image/jpeg', 'image/pjpeg'], true) || in_array($extension, ['jpg', 'jpeg'], true)) {
        return 'jpg';
    }
    if ($mime === 'image/png' || $extension === 'png') {
        return 'png';
    }
    if ($mime === 'image/webp' || $extension === 'webp') {
        return 'webp';
    }
    if ($mime === 'image/gif' || $extension === 'gif') {
        return 'gif';
    }
    return '';
}

function forms_store_uploaded_receipt_file(array $file, string $receiptId, ?string $cohort = null): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            dent_error('حجم فایل از محدودیت تنظیمات سرور بیشتر است.', 422);
        }
        dent_error('آپلود فایل رسید انجام نشد. دوباره فایل را انتخاب کنید.', 422);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = max(0, (int) ($file['size'] ?? 0));
    $originalName = forms_clean_text($file['name'] ?? 'receipt', 220);
    if ($tmpName === '' || !is_uploaded_file($tmpName) || $size <= 0) {
        dent_error('فایل رسید معتبر نیست.', 422);
    }

    $mime = forms_uploaded_receipt_mime($tmpName, $originalName);
    $extension = forms_receipt_extension($mime, $originalName);
    if ($extension === '') {
        dent_error('فایل رسید باید تصویر یا PDF باشد.', 422);
    }
    if ($extension === 'pdf') {
        $mime = 'application/pdf';
    } elseif ($mime === 'application/octet-stream') {
        $mime = 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);
    }

    $period = date('Ym');
    $dir = forms_receipts_dir($cohort) . DIRECTORY_SEPARATOR . $period;
    dent_ensure_directory($dir);
    $fileName = $receiptId . '.' . $extension;
    $target = $dir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpName, $target)) {
        dent_error('ذخیره رسید در فضای پایدار فرم انجام نشد.', 500);
    }
    @chmod($target, 0640);

    return [
        'originalName' => $originalName !== '' ? $originalName : $fileName,
        'storedPath' => $period . '/' . $fileName,
        'mimeType' => $mime,
        'size' => $size,
    ];
}

function forms_receipt_payment_order_matches(array $order, string $formId, string $fieldId, string $identityKey): bool
{
    $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
    return (string) ($extra['_source'] ?? '') === 'form_receipt_payment'
        && (string) ($extra['form_id'] ?? '') === $formId
        && (string) ($extra['field_id'] ?? '') === $fieldId
        && (string) ($extra['identity_key'] ?? '') === $identityKey;
}

function forms_upsert_receipt_payment_order(array $form, array $field, array $receipt, array $identity): void
{
    $formId = (string) ($form['id'] ?? '');
    $fieldId = (string) ($field['id'] ?? '');
    $identityKey = (string) ($receipt['identityKey'] ?? '');
    $config = forms_normalize_receipt_payment_config($field['receiptPayment'] ?? []);
    $amount = max(0, (int) ($config['amount'] ?? 0));
    if ($formId === '' || $fieldId === '' || $identityKey === '' || $amount <= 0) {
        return;
    }

    payments_with_store_lock(static function (array &$store) use ($form, $field, $receipt, $identity, $formId, $fieldId, $identityKey, $config, $amount): void {
        $targetIndex = -1;
        foreach (($store['orders'] ?? []) as $index => $order) {
            if (is_array($order) && forms_receipt_payment_order_matches($order, $formId, $fieldId, $identityKey)) {
                $targetIndex = (int) $index;
                break;
            }
        }

        $nowIso = dent_iso_now();
        $uploadedAt = (int) ($receipt['uploadedAt'] ?? time());
        $paidAt = date('c', $uploadedAt > 0 ? $uploadedAt : time());
        $payerName = forms_clean_text($identity['name'] ?? '', 120);
        $studentNumber = dent_normalize_student_number((string) ($identity['studentNumber'] ?? ''));
        $payerPhone = payments_normalize_phone((string) ($identity['phone'] ?? ''));
        $title = (string) ($field['label'] ?? 'پرداخت با رسید');
        $extra = [
            '_source' => 'form_receipt_payment',
            'form_id' => $formId,
            'form_title' => (string) ($form['title'] ?? ''),
            'field_id' => $fieldId,
            'field_label' => $title,
            'identity_key' => $identityKey,
            'receipt_id' => (string) ($receipt['id'] ?? ''),
            'receipt_name' => (string) ($receipt['originalName'] ?? ''),
            'card_number' => (string) ($config['cardNumber'] ?? ''),
            'cardholder' => (string) ($config['cardholder'] ?? ''),
            'bank_name' => (string) ($config['bankName'] ?? ''),
            '_return_path' => forms_share_path($formId, $form),
        ];
        $line = [
            'item_id' => 0,
            'slug' => '',
            'title' => $title,
            'quantity' => 1,
            'unit_price' => $amount,
            'subtotal' => $amount,
            'discount_code' => '',
            'discount_amount' => 0,
            'amount' => $amount,
            'extra_form_data' => [],
        ];

        if ($targetIndex >= 0) {
            $current = is_array($store['orders'][$targetIndex] ?? null) ? $store['orders'][$targetIndex] : [];
            $current = array_merge($current, [
                'user_id' => $studentNumber,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'payer_student_number' => $studentNumber,
                'extra_form_data' => $extra,
                'cart_items' => [$line],
                'quantity' => 1,
                'unit_price' => $amount,
                'subtotal' => $amount,
                'discount_code' => '',
                'discount_amount' => 0,
                'amount' => $amount,
                'gateway' => 'receipt',
                'ref_id' => (string) ($receipt['id'] ?? ''),
                'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
                'paid_at' => $paidAt,
                'verified_at' => $nowIso,
            ]);
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['receipt_upload'] = [
                'receiptId' => (string) ($receipt['id'] ?? ''),
                'at' => $nowIso,
            ];
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$targetIndex] = $current;
            return;
        }

        $orderId = payments_next_order_id($store);
        $store['orders'][] = [
            'id' => $orderId,
            'item_id' => 0,
            'user_id' => $studentNumber,
            'payer_name' => $payerName,
            'payer_phone' => $payerPhone,
            'payer_student_number' => $studentNumber,
            'extra_form_data' => $extra,
            'cart_items' => [$line],
            'quantity' => 1,
            'unit_price' => $amount,
            'subtotal' => $amount,
            'discount_code' => '',
            'discount_amount' => 0,
            'amount' => $amount,
            'gateway' => 'receipt',
            'authority' => '',
            'ref_id' => (string) ($receipt['id'] ?? ''),
            'status' => PAYMENTS_ORDER_STATUS_SUCCESS,
            'gateway_response_snapshot' => [
                'created' => ['at' => $nowIso, 'type' => 'form_receipt_payment'],
                'receipt_upload' => ['receiptId' => (string) ($receipt['id'] ?? ''), 'at' => $nowIso],
            ],
            'created_at' => $nowIso,
            'paid_at' => $paidAt,
            'verified_at' => $nowIso,
            'public_token' => payments_random_token(),
        ];
        payments_append_notification(
            $store,
            PAYMENTS_NOTIFICATION_TYPE_ORDER_SUCCESS,
            'پرداخت با رسید فرم',
            (string) ($form['title'] ?? 'فرم') . ' | ' . $payerName . ' | ' . number_format($amount) . ' ریال',
            $orderId
        );
    });
}

function forms_receipt_download_filename(array $receipt): string
{
    $extension = strtolower(pathinfo((string) ($receipt['storedPath'] ?? ''), PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = 'bin';
    }
    return preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($receipt['id'] ?? 'receipt')) . '.' . $extension;
}

function forms_receipts_for_form(array $store, string $formId): array
{
    $receipts = [];
    foreach (($store['receiptUploads'] ?? []) as $receipt) {
        if (is_array($receipt) && (string) ($receipt['formId'] ?? '') === $formId && (string) ($receipt['status'] ?? 'uploaded') === 'uploaded') {
            $receipts[] = $receipt;
        }
    }
    usort($receipts, static fn(array $left, array $right): int => (int) ($right['uploadedAt'] ?? 0) <=> (int) ($left['uploadedAt'] ?? 0));
    return $receipts;
}

$action = dent_request_action();
forms_set_active_cohort(forms_requested_cohort());

if ($action === 'session') {
    $user = forms_current_site_user();
    dent_release_session_lock();
    $store = forms_load_store();
    $activeCohort = dent_cohort_record(forms_active_cohort());
    dent_json_response([
        'success' => true,
        'viewer' => forms_user_payload($user),
        'canCreate' => forms_can_create($user),
        'activeCount' => $user !== null ? forms_active_count_for_user($store, $user) : 0,
        'activeCohort' => $activeCohort === null ? null : [
            'key' => (string) ($activeCohort['key'] ?? ''),
            'title' => (string) ($activeCohort['title'] ?? ''),
            'shortTitle' => (string) ($activeCohort['shortTitle'] ?? ''),
            'productType' => (string) ($activeCohort['productType'] ?? ''),
            'year' => (string) ($activeCohort['year'] ?? ''),
            'supportsRotationGroups' => !empty($activeCohort['supportsRotationGroups']),
        ],
        'legacyDis' => [
            'publicPath' => '/dis-request/',
            'managePath' => '/dis-request/manage/',
        ],
    ]);
}

if ($action === 'list') {
    $user = forms_require_context_user();
    dent_release_session_lock();
    $store = forms_load_store();
    $forms = [];
    foreach ($store['forms'] as $form) {
        if (!is_array($form)) {
            continue;
        }
        if (!forms_form_matches_active_cohort($form)) {
            continue;
        }
        if (forms_user_can_list_form($form, $user)) {
            // Pass false for $includeFields: the list view only needs metadata (title,
            // status, responseCount, audienceLabel, hasReceiptPaymentFields).
            // Full fields + payment-status reads are deferred to the 'get' action.
            $forms[] = forms_form_payload($store, $form, $user, false);
        }
    }
    dent_json_response([
        'success' => true,
        'forms' => $forms,
        'canCreate' => forms_can_create($user),
    ]);
}

if ($action === 'create') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ساخت فرم نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    if (!forms_can_create($user)) {
        dent_error('ساخت فرم و نظرسنجی فقط برای مالک یا مدیر مجاز همان ورودی فعال است.', 403);
    }
    // Release session lock before file I/O; validate payload before acquiring store lock.
    dent_release_session_lock();
    $form = forms_build_form_from_payload(forms_request_payload(), $user, null);
    $createLock = fopen(forms_lock_path(), 'c+');
    if ($createLock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی فرم.', 500);
    }
    try {
        flock($createLock, LOCK_EX);
        $store = forms_load_store();
        $store['forms'][(string) $form['id']] = $form;
        forms_save_store($store);
    } finally {
        @flock($createLock, LOCK_UN);
        @fclose($createLock);
    }
    dent_json_response([
        'success' => true,
        'message' => forms_kind_label((string) $form['kind']) . ' ساخته شد.',
        'form' => forms_form_payload($store, $form, $user, true),
        'forms' => array_values(array_map(static fn(array $item): array => forms_form_payload($store, $item, $user, false), $store['forms'])),
    ]);
}

if ($action === 'update') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ویرایش فرم نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    dent_release_session_lock();
    $payload = forms_request_payload();
    $updateLock = fopen(forms_lock_path(), 'c+');
    if ($updateLock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی فرم.', 500);
    }
    $updated = null;
    try {
        flock($updateLock, LOCK_EX);
        $store = forms_load_store();
        $existing = $store['forms'][$formId] ?? null;
        if (!is_array($existing)) {
            dent_error('فرم پیدا نشد.', 404);
        }
        if (!forms_can_manage($existing, $user)) {
            dent_error('اجازه ویرایش این فرم را ندارید.', 403);
        }
        $updated = forms_build_form_from_payload($payload, $user, $existing);
        $updated['id'] = $formId;
        // Keep removed options that still carry capacity/responses as soft-deleted,
        // so historical responses and capacity counts remain correct.
        $updated = forms_preserve_deleted_options($updated, $existing, forms_responses_for_form($store, $formId));
        $store['forms'][$formId] = $updated;
        forms_save_store($store);
    } finally {
        @flock($updateLock, LOCK_UN);
        @fclose($updateLock);
    }
    dent_json_response([
        'success' => true,
        'message' => 'تنظیمات فرم ذخیره شد.',
        'form' => forms_form_payload($store, $updated, $user, true),
    ]);
}

if ($action === 'setStatus') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد تغییر وضعیت نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    $status = forms_clean_status((string) ($_POST['status'] ?? 'open'));
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    dent_release_session_lock();
    $statusLock = fopen(forms_lock_path(), 'c+');
    if ($statusLock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی فرم.', 500);
    }
    try {
        flock($statusLock, LOCK_EX);
        $store = forms_load_store();
        $form = $store['forms'][$formId] ?? null;
        if (!is_array($form)) {
            dent_error('فرم پیدا نشد.', 404);
        }
        if (!forms_can_manage($form, $user)) {
            dent_error('اجازه مدیریت این فرم را ندارید.', 403);
        }
        $form['status'] = $status;
        $form['updatedAt'] = time();
        $store['forms'][$formId] = $form;
        forms_save_store($store);
    } finally {
        @flock($statusLock, LOCK_UN);
        @fclose($statusLock);
    }
    dent_json_response([
        'success' => true,
        'form' => forms_form_payload($store, $form, $user, false),
    ]);
}

if ($action === 'delete') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد حذف فرم نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    dent_release_session_lock();
    $deleteLock = fopen(forms_lock_path(), 'c+');
    if ($deleteLock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی فرم.', 500);
    }
    try {
        flock($deleteLock, LOCK_EX);
        $store = forms_load_store();
        $form = $store['forms'][$formId] ?? null;
        if (!is_array($form)) {
            dent_error('فرم پیدا نشد.', 404);
        }
        if (!forms_can_delete($form, $user)) {
            dent_error('حذف کامل فرم فقط برای مالک مجاز است.', 403);
        }
        unset($store['forms'][$formId]);
        foreach ($store['responses'] as $responseId => $response) {
            if (is_array($response) && (string) ($response['formId'] ?? '') === $formId) {
                unset($store['responses'][$responseId]);
            }
        }
        foreach ($store['receiptUploads'] as $receiptId => $receipt) {
            if (is_array($receipt) && (string) ($receipt['formId'] ?? '') === $formId) {
                forms_delete_receipt_file($receipt);
                unset($store['receiptUploads'][$receiptId]);
            }
        }
        forms_save_store($store);
    } finally {
        @flock($deleteLock, LOCK_UN);
        @fclose($deleteLock);
    }
    dent_json_response([
        'success' => true,
        'formId' => $formId,
    ]);
}

if ($action === 'get') {
    $user = forms_current_site_user();
    dent_release_session_lock();
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_GET['form'] ?? $_GET['formId'] ?? $_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_user_can_open_form($form, $user)) {
        if ($user === null && !forms_guest_allowed($form)) {
            dent_error('برای شرکت در این فرم باید وارد حساب شوید.', 401, ['loggedOut' => true, 'requiresLogin' => true]);
        }
        dent_error('این فرم برای شما فعال نیست.', 403);
    }
    if ($user === null && forms_has_payment_fields($form)) {
        dent_error('برای پاسخ به فرم دارای سوال پرداخت باید وارد حساب شوید.', 401, ['loggedOut' => true, 'requiresLogin' => true]);
    }
    $identityKeyOverride = null;
    if ($user === null && trim((string) ($_GET['guestKey'] ?? '')) !== '') {
        $identityKeyOverride = forms_identity_key(null, $_GET);
    }
    dent_json_response([
        'success' => true,
        'form' => forms_form_payload($store, $form, $user, true, $identityKeyOverride),
        'viewer' => forms_user_payload($user),
    ]);
}

if ($action === 'uploadReceipt') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد آپلود رسید نامعتبر است.', 405);
    }
    // Read session (user) first, then release lock before any file I/O.
    $user = forms_current_site_user();
    dent_release_session_lock();

    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    $fieldId = trim(strtolower((string) ($_POST['fieldId'] ?? '')));
    if (preg_match('/^[a-z0-9_-]{3,48}$/', $fieldId) !== 1) {
        $fieldId = '';
    }
    if ($formId === '' || $fieldId === '') {
        dent_error('شناسه فرم یا سوال رسید معتبر نیست.', 422);
    }
    // Pre-lock read for validation (non-authoritative but fast).
    $store = forms_load_store();
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_viewer_can_access($form, $user)) {
        if ($user === null && !forms_guest_allowed($form)) {
            dent_error('برای آپلود رسید باید وارد حساب شوید.', 401, ['loggedOut' => true, 'requiresLogin' => true]);
        }
        dent_error('این فرم برای شما فعال نیست.', 403);
    }
    if (forms_status($form) !== 'open') {
        dent_error('آپلود رسید برای این فرم فعال نیست.', 422);
    }
    $targetField = null;
    foreach (forms_receipt_payment_fields($form) as $field) {
        if ((string) ($field['id'] ?? '') === $fieldId) {
            $targetField = $field;
            break;
        }
    }
    if (!is_array($targetField)) {
        dent_error('سوال پرداخت با رسید پیدا نشد.', 404);
    }
    $file = $_FILES['receipt'] ?? null;
    if (!is_array($file)) {
        dent_error('فایل رسید انتخاب نشده است.', 422);
    }
    $identityKey = forms_identity_key($user, $_POST);
    if ($identityKey === '') {
        dent_error('شناسه شرکت‌کننده برای آپلود رسید معتبر نیست.', 422);
    }
    // Upload file to disk outside the store lock (slow I/O should not block other requests).
    $receiptId = forms_next_id(FORMS_RECEIPT_ID_PREFIX);
    $stored = forms_store_uploaded_receipt_file($file, $receiptId, forms_form_cohort($form));
    $receipt = [
        'id' => $receiptId,
        'cohort' => forms_form_cohort($form),
        'formId' => $formId,
        'fieldId' => $fieldId,
        'identityKey' => $identityKey,
        'originalName' => (string) ($stored['originalName'] ?? ''),
        'storedPath' => (string) ($stored['storedPath'] ?? ''),
        'mimeType' => (string) ($stored['mimeType'] ?? 'application/octet-stream'),
        'size' => max(0, (int) ($stored['size'] ?? 0)),
        'uploadedAt' => time(),
        'uploadedBy' => $user !== null ? dent_normalize_student_number((string) ($user['studentNumber'] ?? '')) : '',
        'status' => 'uploaded',
    ];
    // Atomic store update: re-read inside lock to avoid concurrent-write data loss.
    $receiptLock = fopen(forms_lock_path(), 'c+');
    if ($receiptLock === false) {
        forms_delete_receipt_file($receipt);
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی فرم.', 500);
    }
    try {
        flock($receiptLock, LOCK_EX);
        $store = forms_load_store();
        // Re-validate form status inside the lock.
        $lockedForm = $store['forms'][$formId] ?? null;
        if (!is_array($lockedForm) || forms_status($lockedForm) !== 'open') {
            forms_delete_receipt_file($receipt);
            dent_error('فرم دیگر پذیرش رسید ندارد.', 409);
        }
        $store['receiptUploads'][$receiptId] = $receipt;
        forms_save_store($store);
    } finally {
        @flock($receiptLock, LOCK_UN);
        @fclose($receiptLock);
    }
    forms_upsert_receipt_payment_order($form, $targetField, $receipt, forms_identity_payload($user, $_POST));
    dent_json_response([
        'success' => true,
        'receipt' => forms_receipt_public_payload($receipt),
        'message' => 'رسید با موفقیت آپلود و پرداخت ثبت شد.',
    ]);
}

if ($action === 'downloadReceipt') {
    if (dent_request_method() !== 'GET') {
        dent_error('متد دانلود رسید نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    dent_release_session_lock();
    $store = forms_load_store();
    $receiptId = forms_clean_id((string) ($_GET['id'] ?? ''), FORMS_RECEIPT_ID_PREFIX);
    if ($receiptId === '') {
        dent_error('شناسه رسید معتبر نیست.', 422);
    }
    $receipt = $store['receiptUploads'][$receiptId] ?? null;
    if (!is_array($receipt)) {
        dent_error('رسید پیدا نشد.', 404);
    }
    $form = $store['forms'][(string) ($receipt['formId'] ?? '')] ?? null;
    if (!is_array($form) || !forms_can_manage($form, $user)) {
        dent_error('دسترسی به دانلود رسید مجاز نیست.', 403);
    }
    $path = forms_receipt_file_path($receipt);
    if ($path === '' || !is_file($path) || !is_readable($path)) {
        dent_error('فایل رسید در storage پیدا نشد.', 404);
    }
    header('Content-Type: ' . (string) ($receipt['mimeType'] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . forms_receipt_download_filename($receipt) . '"');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

if ($action === 'exportReceipts') {
    if (dent_request_method() !== 'GET') {
        dent_error('متد خروجی رسیدها نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    dent_release_session_lock();
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_GET['formId'] ?? $_GET['form'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم معتبر نیست.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_manage($form, $user)) {
        dent_error('دسترسی به خروجی رسیدها مجاز نیست.', 403);
    }

    $files = [];
    $manifest = [["receipt_id", "field_id", "original_name", "size", "uploaded_at"]];
    foreach (forms_receipts_for_form($store, $formId) as $receipt) {
        $path = forms_receipt_file_path($receipt);
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            continue;
        }
        $downloadName = forms_receipt_download_filename($receipt);
        $files['receipts/' . $downloadName] = (string) file_get_contents($path);
        $manifest[] = [
            (string) ($receipt['id'] ?? ''),
            (string) ($receipt['fieldId'] ?? ''),
            (string) ($receipt['originalName'] ?? ''),
            (string) max(0, (int) ($receipt['size'] ?? 0)),
            (string) date('c', (int) ($receipt['uploadedAt'] ?? time())),
        ];
    }
    if ($files === []) {
        dent_error('رسیدی برای این فرم ثبت نشده است.', 404);
    }
    $csv = implode("\r\n", array_map(static function (array $row): string {
        return implode(',', array_map(static function (string $cell): string {
            return '"' . str_replace('"', '""', $cell) . '"';
        }, $row));
    }, $manifest));
    $files['manifest.csv'] = "\xEF\xBB\xBF" . $csv . "\r\n";
    $binary = forms_build_zip_archive($files);
    $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $formId) . '-receipts.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . (string) strlen($binary));
    echo $binary;
    exit;
}

if ($action === 'createPayment') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد پرداخت فرم نامعتبر است.', 405);
    }

    $user = forms_require_context_user();
    dent_release_session_lock();
    $formStore = forms_load_store();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    $fieldId = trim(strtolower((string) ($_POST['fieldId'] ?? '')));
    if (preg_match('/^[a-z0-9_-]{3,48}$/', $fieldId) !== 1) {
        $fieldId = '';
    }
    if ($formId === '' || $fieldId === '') {
        dent_error('شناسه فرم یا سوال پرداخت معتبر نیست.', 422);
    }
    $form = $formStore['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_viewer_can_access($form, $user) || forms_status($form) !== 'open') {
        dent_error('پرداخت برای این فرم فعال نیست.', 403);
    }

    $targetField = null;
    foreach (forms_payment_fields($form) as $field) {
        if ((string) ($field['id'] ?? '') === $fieldId) {
            $targetField = $field;
            break;
        }
    }
    if (!is_array($targetField)) {
        dent_error('سوال پرداخت پیدا نشد.', 404);
    }

    $payment = forms_normalize_payment_config($targetField['payment'] ?? []);
    $amount = max(0, (int) ($payment['amount'] ?? 0));
    if ($amount <= 0) {
        dent_error('مبلغ سوال پرداخت معتبر نیست.', 422);
    }

    $enabledGateways = payments_gateway_enabled_checkout_keys(false);
    if ($enabledGateways === []) {
        dent_error('هیچ درگاه پرداخت فعالی برای این فرم وجود ندارد.', 503);
    }
    $requestedGateway = payments_gateway_clean((string) ($_POST['gateway'] ?? ''));
    $defaultGateway = payments_gateway_default_enabled_checkout(false);
    $payerPhone = payments_normalize_phone((string) ($_POST['payerPhone'] ?? ($user['phoneNumber'] ?? '')));
    if ($payerPhone === '' || strlen($payerPhone) < 10 || strlen($payerPhone) > 14) {
        dent_error('شماره موبایل پرداخت‌کننده معتبر نیست.', 422);
    }
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    $identityKey = forms_identity_key($user, $_POST);
    $publicUser = dent_public_user($user);
    $payerName = dent_clean_text((string) ($_POST['payerName'] ?? ($publicUser['name'] ?? '')), 120);
    if ($payerName === '') {
        $payerName = $studentNumber;
    }

    try {
        $created = payments_with_store_lock(static function (array &$store) use (
            $form,
            $formId,
            $targetField,
            $fieldId,
            $payment,
            $amount,
            $enabledGateways,
            $requestedGateway,
            $defaultGateway,
            $payerPhone,
            $payerName,
            $studentNumber,
            $identityKey
        ): array {
            foreach (($store['orders'] ?? []) as $order) {
                if (!is_array($order) || !forms_payment_order_matches($order, $formId, $fieldId, $identityKey)) {
                    continue;
                }
                if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
                    return [
                        'alreadyPaid' => true,
                        'order' => $order,
                    ];
                }
            }

            $gateway = $requestedGateway !== '' ? $requestedGateway : payments_gateway_clean((string) ($payment['gateway'] ?? ''));
            if ($gateway === '') {
                $gateway = $defaultGateway;
            }
            if ($gateway === '' || !in_array($gateway, $enabledGateways, true)) {
                throw new RuntimeException('درگاه پرداخت انتخاب‌شده فعال نیست. لطفا گزینه دیگری را انتخاب کنید.');
            }

            $now = dent_iso_now();
            $title = (string) ($targetField['label'] ?? 'پرداخت فرم');
            $order = [
                'id' => payments_next_order_id($store),
                'item_id' => 0,
                'user_id' => $studentNumber,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'payer_student_number' => $studentNumber,
                'extra_form_data' => [
                    '_source' => 'form_payment',
                    'form_id' => $formId,
                    'form_title' => (string) ($form['title'] ?? ''),
                    'field_id' => $fieldId,
                    'field_label' => $title,
                    'identity_key' => $identityKey,
                    '_return_path' => forms_share_path($formId, $form),
                ],
                'cart_items' => [[
                    'item_id' => 0,
                    'slug' => '',
                    'title' => $title,
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'subtotal' => $amount,
                    'discount_code' => '',
                    'discount_amount' => 0,
                    'amount' => $amount,
                    'extra_form_data' => [],
                ]],
                'quantity' => 1,
                'unit_price' => $amount,
                'subtotal' => $amount,
                'discount_code' => '',
                'discount_amount' => 0,
                'amount' => $amount,
                'gateway' => $gateway,
                'authority' => '',
                'ref_id' => '',
                'status' => PAYMENTS_ORDER_STATUS_PENDING,
                'gateway_response_snapshot' => [
                    'created' => ['at' => $now, 'type' => 'form_payment'],
                ],
                'created_at' => $now,
                'paid_at' => '',
                'verified_at' => '',
                'public_token' => payments_random_token(),
            ];
            $store['orders'][] = $order;
            return [
                'alreadyPaid' => false,
                'order' => $order,
            ];
        });
    } catch (Throwable $error) {
        dent_error($error->getMessage(), 422);
    }

    $order = is_array($created['order'] ?? null) ? $created['order'] : [];
    if ((bool) ($created['alreadyPaid'] ?? false)) {
        dent_json_response([
            'success' => true,
            'alreadyPaid' => true,
            'orderToken' => (string) ($order['public_token'] ?? ''),
        ]);
    }

    $orderToken = (string) ($order['public_token'] ?? '');
    $callbackUrl = forms_absolute_url('/api/payments_api.php?action=callback&orderToken=' . rawurlencode($orderToken));
    $syntheticItem = [
        'id' => 0,
        'title' => (string) ($targetField['label'] ?? 'پرداخت فرم'),
    ];
    $startResult = payments_gateway_start_payment((string) ($order['gateway'] ?? ''), $syntheticItem, $order, [
        'callbackUrl' => $callbackUrl,
        'description' => 'پرداخت ' . (string) ($targetField['label'] ?? 'فرم'),
        'mobile' => $payerPhone,
        'orderId' => $orderToken,
    ]);

    payments_log_gateway_event('start-request', [
        'orderId' => (int) ($order['id'] ?? 0),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $startResult,
    ]);

    if (!(bool) ($startResult['success'] ?? false)) {
        payments_with_store_lock(static function (array &$store) use ($order, $startResult): void {
            $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
            if ($orderIndex < 0) {
                return;
            }
            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                $current['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['start'] = $startResult;
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;
        });
        dent_error((string) ($startResult['error'] ?? 'ایجاد درخواست درگاه پرداخت فرم انجام نشد.'), 503, [
            'orderToken' => $orderToken,
        ]);
    }

    $authority = dent_clean_text((string) ($startResult['authority'] ?? ''), 120);
    $redirectUrl = dent_clean_text((string) ($startResult['redirectUrl'] ?? ''), 900);
    if ($redirectUrl === '') {
        dent_error('لینک انتقال به درگاه پرداخت دریافت نشد.', 500);
    }

    payments_with_store_lock(static function (array &$store) use ($order, $authority, $startResult): void {
        $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
        if ($orderIndex < 0) {
            return;
        }
        $current = $store['orders'][$orderIndex];
        $current['authority'] = $authority;
        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['start'] = $startResult;
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;
    });

    dent_json_response([
        'success' => true,
        'orderToken' => $orderToken,
        'redirectUrl' => $redirectUrl,
        'resultUrl' => forms_share_path($formId, $form) . '&paymentOrderToken=' . rawurlencode($orderToken),
    ]);
}

if ($action === 'submit') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد ثبت پاسخ نامعتبر است.', 405);
    }
    // --- Pre-lock validation (reads session + initial store snapshot) ---
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    $user = forms_current_site_user();
    // Release PHP session lock as early as possible — file I/O continues below.
    dent_release_session_lock();
    if (!forms_viewer_can_access($form, $user)) {
        if ($user === null && !forms_guest_allowed($form)) {
            dent_error('برای ثبت پاسخ باید وارد حساب شوید.', 401, ['loggedOut' => true, 'requiresLogin' => true]);
        }
        dent_error('این فرم برای شما فعال نیست.', 403);
    }
    if ($user === null && forms_has_payment_fields($form)) {
        dent_error('برای ثبت پاسخ فرم دارای سوال پرداخت باید وارد حساب شوید.', 401, ['loggedOut' => true, 'requiresLogin' => true]);
    }
    if (forms_status($form) !== 'open') {
        dent_error('ثبت پاسخ برای این فرم فعال نیست.', 422);
    }
    $settings = is_array($form['settings'] ?? null) ? $form['settings'] : [];
    if ($user !== null && forms_can_manage($form, $user) && !forms_parse_bool($settings['allowCreatorSubmit'] ?? true, true)) {
        dent_error('ثبت پاسخ توسط سازنده/مدیر برای این فرم فعال نیست.', 403);
    }
    if ($user === null && forms_parse_bool($settings['collectGuestName'] ?? true, true) && forms_clean_text($_POST['guestName'] ?? '', 140) === '') {
        dent_error('نام شرکت‌کننده مهمان الزامی است.', 422);
    }
    if ($user === null && forms_parse_bool($settings['collectGuestPhone'] ?? false, false) && forms_only_digits($_POST['guestPhone'] ?? '') === '') {
        dent_error('شماره تماس شرکت‌کننده مهمان الزامی است.', 422);
    }
    $identityKey = forms_identity_key($user, $_POST);
    // Pre-compute answers from POST data (outside the file lock).
    $preAnswers = forms_collect_answers($form, $_POST);

    // --- Atomic check-and-save (exclusive file lock) ---
    $submitLock = fopen(forms_lock_path(), 'c+');
    if ($submitLock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی فرم.', 500);
    }
    $response = null;
    $existingResponse = null;
    try {
        if (!flock($submitLock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire store lock.');
        }
        // Re-read the store inside the lock so we see any concurrent writes.
        $store = forms_load_store();
        $form = $store['forms'][$formId] ?? null;
        if (!is_array($form)) {
            dent_error('فرم پیدا نشد.', 404);
        }
        $existingResponse = forms_response_for_identity($store, $formId, $identityKey);
        if (forms_parse_bool($settings['limitOneResponse'] ?? true, true) && $existingResponse !== null && !forms_parse_bool($settings['allowEditResponse'] ?? false, false)) {
            dent_error('برای این شرکت‌کننده قبلاً پاسخ ثبت شده است.', 409);
        }
        // Validate per-option capacity limits (inside the lock so counts are authoritative).
        $capExcludeId = is_array($existingResponse) ? (string) ($existingResponse['id'] ?? null) : null;
        $capCounts = null; // lazy – only computed if a field with capacity is found
        foreach ((array) ($form['fields'] ?? []) as $capField) {
            if (!is_array($capField)) {
                continue;
            }
            $capFtype = (string) ($capField['type'] ?? '');
            if ($capFtype !== 'single_choice' && $capFtype !== 'multiple_choice' && $capFtype !== 'dropdown') {
                continue;
            }
            $capFieldId = (string) ($capField['id'] ?? '');
            $fieldAnswer = $preAnswers[$capFieldId] ?? null;
            if ($fieldAnswer === null || $fieldAnswer === '' || $fieldAnswer === []) {
                continue;
            }
            $selectedIds = is_array($fieldAnswer) ? array_map('strval', $fieldAnswer) : [(string) $fieldAnswer];
            $capOptions = is_array($capField['options'] ?? null) ? $capField['options'] : [];
            $fieldHasCap = false;
            foreach ($capOptions as $opt) {
                if (is_array($opt) && (int) ($opt['capacity'] ?? 0) > 0) {
                    $fieldHasCap = true;
                    break;
                }
            }
            if (!$fieldHasCap) {
                continue;
            }
            if ($capCounts === null) {
                $capCounts = forms_option_capacity_counts_from_responses(
                    forms_responses_for_form($store, $formId),
                    $capExcludeId
                );
            }
            $capFieldCounts = $capCounts[$capFieldId] ?? [];
            foreach ($capOptions as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $cap = (int) ($opt['capacity'] ?? 0);
                if ($cap <= 0) {
                    continue;
                }
                $optId = (string) ($opt['id'] ?? '');
                if (!in_array($optId, $selectedIds, true)) {
                    continue;
                }
                $used = (int) ($capFieldCounts[$optId] ?? 0);
                if ($used >= $cap) {
                    dent_error('ظرفیت گزینه «' . (string) ($opt['text'] ?? $optId) . '» تکمیل شده است.', 409);
                }
            }
        }
        $answers = $preAnswers;
        foreach (forms_collect_payment_answers($form, $identityKey) as $fieldId => $answer) {
            $answers[$fieldId] = $answer;
        }
        foreach (forms_collect_receipt_payment_answers($store, $form, $identityKey) as $fieldId => $answer) {
            $answers[$fieldId] = $answer;
        }
        $now = time();
        $response = [
            'id' => is_array($existingResponse) ? (string) ($existingResponse['id'] ?? forms_next_id(FORMS_RESPONSE_ID_PREFIX)) : forms_next_id(FORMS_RESPONSE_ID_PREFIX),
            'formId' => $formId,
            'submittedAt' => is_array($existingResponse) ? (int) ($existingResponse['submittedAt'] ?? $now) : $now,
            'updatedAt' => $now,
            'identity' => forms_identity_payload($user, $_POST),
            'answers' => $answers,
        ];
        $store['responses'][(string) $response['id']] = $response;
        $form['updatedAt'] = $now;
        $store['forms'][$formId] = $form;
        forms_save_store($store);
    } finally {
        @flock($submitLock, LOCK_UN);
        @fclose($submitLock);
    }
    $formPayload = forms_form_payload($store, $form, $user, true, $identityKey);
    dent_json_response([
        'success' => true,
        'message' => is_array($existingResponse) ? 'پاسخ با موفقیت به‌روزرسانی شد.' : 'پاسخ با موفقیت ثبت شد.',
        'response' => forms_response_payload($response, $form),
        'form' => $formPayload,
    ]);
}

if ($action === 'responses') {
    $user = forms_require_context_user();
    dent_release_session_lock();
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_GET['formId'] ?? $_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_manage($form, $user)) {
        dent_error('اجازه مشاهده پاسخ‌ها را ندارید.', 403);
    }
    $responses = forms_responses_for_form($store, $formId);
    dent_json_response([
        'success' => true,
        'form' => forms_form_payload($store, $form, $user, true),
        'responses' => array_map(static fn(array $response): array => forms_response_payload($response, $form), $responses),
    ]);
}

if ($action === 'export') {
    $user = forms_require_context_user();
    dent_release_session_lock();
    $store = forms_load_store();
    $formId = forms_clean_id((string) ($_GET['formId'] ?? $_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    if ($formId === '') {
        dent_error('شناسه فرم نامعتبر است.', 422);
    }
    $form = $store['forms'][$formId] ?? null;
    if (!is_array($form)) {
        dent_error('فرم پیدا نشد.', 404);
    }
    if (!forms_can_manage($form, $user)) {
        dent_error('اجازه دریافت خروجی را ندارید.', 403);
    }
    $mode = trim(strtolower((string) ($_GET['mode'] ?? $_POST['mode'] ?? 'responses')));
    forms_export_xlsx($form, forms_responses_for_form($store, $formId), $mode);
}

if ($action === 'ownerPatchAnswer') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد نامعتبر است.', 405);
    }
    $user = dent_require_owner();
    dent_release_session_lock();

    $formId     = forms_clean_id((string) ($_POST['formId']     ?? ''), FORMS_ID_PREFIX);
    $responseId = forms_clean_id((string) ($_POST['responseId'] ?? ''), FORMS_RESPONSE_ID_PREFIX);
    $fieldId    = trim((string) ($_POST['fieldId'] ?? ''));
    $newAnswer  = $_POST['newAnswer'] ?? '';

    if ($formId === '' || $responseId === '' || $fieldId === '') {
        dent_error('پارامترهای ناقص.', 422);
    }

    $handle = @fopen(forms_lock_path(), 'c');
    if ($handle === false) {
        dent_error('قفل store باز نشد.', 500);
    }
    try {
        if (!@flock($handle, LOCK_EX)) {
            dent_error('قفل store گرفته نشد.', 500);
        }
        $store = forms_load_store();
        if (!isset($store['forms'][$formId])) {
            dent_error('فرم پیدا نشد.', 404);
        }
        if (!isset($store['responses'][$responseId])) {
            dent_error('پاسخ پیدا نشد.', 404);
        }
        if ((string) ($store['responses'][$responseId]['formId'] ?? '') !== $formId) {
            dent_error('پاسخ به این فرم تعلق ندارد.', 422);
        }
        $store['responses'][$responseId]['answers'][$fieldId] = $newAnswer;
        $store['responses'][$responseId]['updatedAt'] = time();
        forms_save_store($store);
        dent_json_response(['success' => true, 'responseId' => $responseId, 'fieldId' => $fieldId, 'newAnswer' => $newAnswer]);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

if ($action === 'deleteResponse') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    dent_release_session_lock();

    $formId     = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    $responseId = forms_clean_id((string) ($_POST['responseId'] ?? ''), FORMS_RESPONSE_ID_PREFIX);
    if ($formId === '' || $responseId === '') {
        dent_error('پارامترهای ناقص.', 422);
    }

    $handle = @fopen(forms_lock_path(), 'c');
    if ($handle === false) {
        dent_error('قفل store باز نشد.', 500);
    }
    try {
        if (!@flock($handle, LOCK_EX)) {
            dent_error('قفل store گرفته نشد.', 500);
        }
        $store = forms_load_store();
        $form = $store['forms'][$formId] ?? null;
        if (!is_array($form)) {
            dent_error('فرم پیدا نشد.', 404);
        }
        if (!forms_can_manage($form, $user)) {
            dent_error('اجازه مدیریت این فرم را ندارید.', 403);
        }
        if (!isset($store['responses'][$responseId]) || (string) ($store['responses'][$responseId]['formId'] ?? '') !== $formId) {
            dent_error('پاسخ پیدا نشد.', 404);
        }
        unset($store['responses'][$responseId]);
        forms_save_store($store);
        dent_json_response(['success' => true, 'responseId' => $responseId]);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

if ($action === 'ownerEditResponse') {
    if (dent_request_method() !== 'POST') {
        dent_error('متد نامعتبر است.', 405);
    }
    $user = forms_require_context_user();
    dent_release_session_lock();

    $formId     = forms_clean_id((string) ($_POST['formId'] ?? ''), FORMS_ID_PREFIX);
    $responseId = forms_clean_id((string) ($_POST['responseId'] ?? ''), FORMS_RESPONSE_ID_PREFIX);
    $answersRaw = (string) ($_POST['answers'] ?? '');
    if ($formId === '' || $responseId === '') {
        dent_error('پارامترهای ناقص.', 422);
    }
    $patch = json_decode($answersRaw, true);
    if (!is_array($patch)) {
        dent_error('داده پاسخ نامعتبر است.', 422);
    }

    $handle = @fopen(forms_lock_path(), 'c');
    if ($handle === false) {
        dent_error('قفل store باز نشد.', 500);
    }
    try {
        if (!@flock($handle, LOCK_EX)) {
            dent_error('قفل store گرفته نشد.', 500);
        }
        $store = forms_load_store();
        $form = $store['forms'][$formId] ?? null;
        if (!is_array($form)) {
            dent_error('فرم پیدا نشد.', 404);
        }
        if (!forms_can_manage($form, $user)) {
            dent_error('اجازه مدیریت این فرم را ندارید.', 403);
        }
        if (!isset($store['responses'][$responseId]) || (string) ($store['responses'][$responseId]['formId'] ?? '') !== $formId) {
            dent_error('پاسخ پیدا نشد.', 404);
        }
        // Only allow editing simple/choice fields; payment & receipt answers stay intact.
        $editableTypes = ['short_text', 'paragraph', 'single_choice', 'multiple_choice', 'dropdown', 'linear_scale'];
        $fieldsById = [];
        foreach ((array) ($form['fields'] ?? []) as $f) {
            if (is_array($f) && isset($f['id'])) {
                $fieldsById[(string) $f['id']] = $f;
            }
        }
        $answers = is_array($store['responses'][$responseId]['answers'] ?? null) ? $store['responses'][$responseId]['answers'] : [];
        foreach ($patch as $fieldId => $value) {
            $fieldId = (string) $fieldId;
            $field = $fieldsById[$fieldId] ?? null;
            if (!is_array($field) || !in_array((string) ($field['type'] ?? ''), $editableTypes, true)) {
                continue; // ignore unknown or non-editable fields
            }
            if (is_array($value)) {
                $clean = [];
                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $clean[] = forms_clean_text((string) $item, 400);
                    }
                }
                $answers[$fieldId] = $clean;
            } else {
                $answers[$fieldId] = forms_clean_text((string) $value, 4000);
            }
        }
        $store['responses'][$responseId]['answers'] = $answers;
        $store['responses'][$responseId]['updatedAt'] = time();
        forms_save_store($store);
        dent_json_response([
            'success'  => true,
            'response' => forms_response_payload($store['responses'][$responseId], $form),
        ]);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

dent_error('درخواست نامعتبر است.', 404);
