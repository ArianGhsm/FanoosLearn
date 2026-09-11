<?php
declare(strict_types=1);

const CLASSOPS_EXAM_ACK_CANDIDATE_VERSION = 'classops-exam-ack-v1';
const CLASSOPS_EXAM_EXTENSION_KEY = 'exam_ops_v1';
const CLASSOPS_EXAM_REMINDER_VERSION = 'classops-exam-reminder-v1';
const CLASSOPS_EXAM_PROJECTION_VERSION = 'classops-exam-projection-v1';

final class DentClassOpsExamOpsException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly int $statusCode = 422
    ) {
        parent::__construct($message);
    }
}

function classops_exam_fail(string $reasonCode, string $message, int $statusCode = 422): never
{
    throw new DentClassOpsExamOpsException($reasonCode, $message, $statusCode);
}

function classops_exam_assert_object(array $value, string $field): void
{
    if ($value !== [] && array_is_list($value)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_OBJECT', "{$field} must be an object.");
    }
}

function classops_exam_assert_known_keys(array $value, array $allowed, string $field): void
{
    classops_exam_assert_object($value, $field);
    $unknown = array_values(array_diff(array_keys($value), $allowed));
    if ($unknown !== []) {
        classops_exam_fail(
            'CLASSOPS_EXAM_UNKNOWN_FIELD',
            $field . ' contains unsupported field(s): ' . implode(', ', $unknown)
        );
    }
}

function classops_exam_text($value, int $maxLength, string $field, bool $required = false): string
{
    if (!is_string($value) && !is_numeric($value)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_TEXT', "{$field} must be text.");
    }
    $text = trim((string) $value);
    if ($required && $text === '') {
        classops_exam_fail('CLASSOPS_EXAM_REQUIRED_FIELD', "{$field} is required.");
    }
    if (strlen($text) > $maxLength) {
        classops_exam_fail('CLASSOPS_EXAM_TEXT_TOO_LONG', "{$field} exceeds {$maxLength} bytes.");
    }
    return $text;
}


function classops_exam_cohort_key($value): string
{
    $cohort = classops_exam_text($value, 80, 'cohortKey', true);
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $cohort) !== 1) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_COHORT', 'cohortKey must match frozen classops-v1 canonical format.');
    }
    return $cohort;
}

function classops_exam_foundation_ref($value, string $field): string
{
    $ref = classops_exam_text($value, 96, $field, true);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,95}$/D', $ref) !== 1) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_FOUNDATION_REF', "{$field} must match frozen classops-v1 canonical ref format.");
    }
    return $ref;
}

function classops_exam_utc_timestamp($value, string $field, bool $required = false): ?string
{
    if ($value === null || $value === '') {
        if ($required) {
            classops_exam_fail('CLASSOPS_EXAM_REQUIRED_FIELD', "{$field} is required.");
        }
        return null;
    }
    if (!is_string($value) || strlen($value) > 64 || preg_match('/(?:Z|[+-]\\d{2}:\\d{2})$/', $value) !== 1) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_TIMESTAMP', "{$field} must be offset-aware ISO-8601.");
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_TIMESTAMP', "{$field} is not a valid timestamp.");
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
}

function classops_exam_ref($value, string $field, bool $required = false, int $maxLength = 160): ?string
{
    if ($value === null || $value === '') {
        if ($required) {
            classops_exam_fail('CLASSOPS_EXAM_REQUIRED_FIELD', "{$field} is required.");
        }
        return null;
    }
    $ref = classops_exam_text($value, $maxLength, $field, true);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,159}$/D', $ref) !== 1) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REF', "{$field} is not a canonical reference.");
    }
    return $ref;
}

function classops_exam_default_reminder_policy(): array
{
    return [
        'version' => CLASSOPS_EXAM_REMINDER_VERSION,
        'enabled' => true,
        'rules' => [
            [
                'key' => 't_minus_3d',
                'kind' => 'relative_before_start',
                'offsetSeconds' => 259200,
            ],
            [
                'key' => 't_minus_1d',
                'kind' => 'relative_before_start',
                'offsetSeconds' => 86400,
            ],
            [
                'key' => 'night_before',
                'kind' => 'calendar_marker',
                'marker' => 'night_before',
            ],
            [
                'key' => 'morning_of',
                'kind' => 'calendar_marker',
                'marker' => 'morning_of',
            ],
        ],
    ];
}

function classops_exam_normalize_reminder_policy($value): array
{
    if ($value === null || $value === []) {
        return classops_exam_default_reminder_policy();
    }
    if (!is_array($value)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_POLICY', 'reminderPolicy must be an object.');
    }
    classops_exam_assert_known_keys($value, ['version', 'enabled', 'rules'], 'reminderPolicy');
    $version = classops_exam_text($value['version'] ?? CLASSOPS_EXAM_REMINDER_VERSION, 64, 'reminderPolicy.version', true);
    if ($version !== CLASSOPS_EXAM_REMINDER_VERSION) {
        classops_exam_fail('CLASSOPS_EXAM_REMINDER_VERSION_UNSUPPORTED', 'Unsupported exam reminder policy version.');
    }
    if (array_key_exists('enabled', $value) && !is_bool($value['enabled'])) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_POLICY', 'reminderPolicy.enabled must be boolean.');
    }
    $enabled = (bool) ($value['enabled'] ?? true);
    $rules = $value['rules'] ?? classops_exam_default_reminder_policy()['rules'];
    if (!is_array($rules) || !array_is_list($rules) || count($rules) > 12) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_RULES', 'reminderPolicy.rules must be a list with at most 12 rules.');
    }

    $normalized = [];
    $seen = [];
    foreach ($rules as $index => $rule) {
        if (!is_array($rule)) {
            classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_RULE', "reminderPolicy.rules[{$index}] must be an object.");
        }
        classops_exam_assert_known_keys($rule, ['key', 'kind', 'offsetSeconds', 'marker'], "reminderPolicy.rules[{$index}]");
        $key = classops_exam_text($rule['key'] ?? '', 48, "reminderPolicy.rules[{$index}].key", true);
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/D', $key) !== 1 || isset($seen[$key])) {
            classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_KEY', 'Reminder rule keys must be unique canonical identifiers.');
        }
        $seen[$key] = true;
        $kind = classops_exam_text($rule['kind'] ?? '', 40, "reminderPolicy.rules[{$index}].kind", true);
        if ($kind === 'relative_before_start') {
            if (array_key_exists('marker', $rule)) {
                classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_RULE', 'Relative reminder rules cannot define marker.');
            }
            $offset = filter_var($rule['offsetSeconds'] ?? null, FILTER_VALIDATE_INT);
            if ($offset === false || $offset < 1 || $offset > 31536000) {
                classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_OFFSET', 'Relative reminder offset must be 1..31536000 seconds.');
            }
            $normalized[] = [
                'key' => $key,
                'kind' => $kind,
                'offsetSeconds' => (int) $offset,
            ];
            continue;
        }
        if ($kind === 'calendar_marker') {
            if (array_key_exists('offsetSeconds', $rule)) {
                classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_RULE', 'Calendar marker reminder rules cannot define offsetSeconds.');
            }
            $marker = classops_exam_text($rule['marker'] ?? '', 32, "reminderPolicy.rules[{$index}].marker", true);
            if (!in_array($marker, ['night_before', 'morning_of'], true)) {
                classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_MARKER', 'Unsupported calendar reminder marker.');
            }
            $normalized[] = [
                'key' => $key,
                'kind' => $kind,
                'marker' => $marker,
            ];
            continue;
        }
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REMINDER_KIND', 'Unsupported exam reminder rule kind.');
    }

    return [
        'version' => CLASSOPS_EXAM_REMINDER_VERSION,
        'enabled' => $enabled,
        'rules' => $normalized,
    ];
}

function classops_exam_normalize_external_reference($value, string $field): ?array
{
    if ($value === null || $value === []) {
        return null;
    }
    if (!is_array($value)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REFERENCE', "{$field} must be an object.");
    }
    classops_exam_assert_known_keys($value, ['system', 'ref'], $field);
    $system = classops_exam_text($value['system'] ?? '', 64, "{$field}.system", true);
    if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/D', $system) !== 1) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REFERENCE', "{$field}.system is invalid.");
    }
    $ref = classops_exam_ref($value['ref'] ?? null, "{$field}.ref", true, 160);
    return ['system' => $system, 'ref' => $ref];
}

function classops_exam_normalize_assessment_ref($value): ?array
{
    $normalized = classops_exam_normalize_external_reference($value, 'assessmentRef');
    if ($normalized !== null && $normalized['system'] !== 'website_assessment') {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_ASSESSMENT_REF', 'assessmentRef.system must be website_assessment.');
    }
    return $normalized;
}

function classops_exam_normalize_payment_access_ref($value): ?array
{
    if ($value === null || $value === []) {
        return null;
    }
    if (!is_array($value)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_PAYMENT_REF', 'paymentAccessRef must be an object.');
    }
    classops_exam_assert_known_keys($value, ['system', 'ref', 'mode'], 'paymentAccessRef');
    $normalized = classops_exam_normalize_external_reference([
        'system' => $value['system'] ?? null,
        'ref' => $value['ref'] ?? null,
    ], 'paymentAccessRef');
    if ($normalized === null || !in_array($normalized['system'], ['website_assessment_access', 'payments_store'], true)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_PAYMENT_REF', 'paymentAccessRef must point to an existing access/payment boundary.');
    }
    $mode = $value['mode'] ?? 'reference_only';
    if ($mode !== 'reference_only') {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_PAYMENT_REF', 'paymentAccessRef is reference-only and cannot assert payment state.');
    }
    return $normalized + ['mode' => 'reference_only'];
}

function classops_exam_normalize_resource_refs($value): array
{
    if ($value === null) {
        return [];
    }
    if (!is_array($value) || !array_is_list($value) || count($value) > 30) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_RESOURCE_REFS', 'resourceRefs must be a list with at most 30 entries.');
    }
    $normalized = [];
    $seen = [];
    foreach ($value as $index => $entry) {
        if (!is_array($entry)) {
            classops_exam_fail('CLASSOPS_EXAM_INVALID_RESOURCE_REF', "resourceRefs[{$index}] must be an object.");
        }
        classops_exam_assert_known_keys($entry, ['kind', 'ref'], "resourceRefs[{$index}]");
        $kind = classops_exam_text($entry['kind'] ?? '', 48, "resourceRefs[{$index}].kind", true);
        if (preg_match('/^[a-z][a-z0-9_-]{1,47}$/D', $kind) !== 1) {
            classops_exam_fail('CLASSOPS_EXAM_INVALID_RESOURCE_REF', 'Resource kind is invalid.');
        }
        $ref = classops_exam_ref($entry['ref'] ?? null, "resourceRefs[{$index}].ref", true, 160);
        $key = $kind . '|' . $ref;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $normalized[] = ['kind' => $kind, 'ref' => $ref];
    }
    return $normalized;
}

