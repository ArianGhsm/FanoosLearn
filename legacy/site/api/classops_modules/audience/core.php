<?php
declare(strict_types=1);

require_once __DIR__ . '/source.php';

const CLASSOPS_AUDIENCE_CONTRACT_VERSION = 'classops-audience-v1';
const CLASSOPS_AUDIENCE_RESOLUTION_VERSION = 'classops-audience-resolution-v1';
const CLASSOPS_AUDIENCE_SNAPSHOT_VERSION = 'classops-audience-snapshot-v1';
const CLASSOPS_AUDIENCE_PREVIEW_VERSION = 'classops-audience-preview-v1';
const CLASSOPS_AUDIENCE_MAX_DEPTH = 6;
const CLASSOPS_AUDIENCE_MAX_NODES = 64;
const CLASSOPS_AUDIENCE_MAX_CHILDREN = 16;
const CLASSOPS_AUDIENCE_MAX_STUDENT_REFS = 500;
const CLASSOPS_AUDIENCE_MAX_TOTAL_STUDENT_REFS = 1000;
const CLASSOPS_AUDIENCE_MAX_ROSTER = 5000;
const CLASSOPS_AUDIENCE_MAX_SELECTORS = 200;
const CLASSOPS_AUDIENCE_MAX_SELECTOR_MEMBERS = 5000;

final class DentClassOpsAudienceException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly int $httpStatus,
        string $message
    ) {
        parent::__construct($message);
    }
}

function classops_audience_error(string $code, string $message, int $status = 422): never
{
    throw new DentClassOpsAudienceException($code, $status, $message);
}

function classops_audience_is_assoc(array $value): bool
{
    return !array_is_list($value);
}

function classops_audience_assert_object($value, string $code): array
{
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_audience_error($code, 'Audience object is invalid.');
    }
    return $value;
}

function classops_audience_assert_known_keys(array $value, array $allowed, string $code = 'CLASSOPS_AUDIENCE_UNKNOWN_FIELD'): void
{
    $unknown = array_values(array_diff(array_keys($value), $allowed));
    if ($unknown !== []) {
        classops_audience_error($code, 'Audience input contains an unsupported field.');
    }
}

function classops_audience_sort_recursive($value)
{
    if (!is_array($value)) {
        return $value;
    }
    if (classops_audience_is_assoc($value)) {
        ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $child) {
        $value[$key] = classops_audience_sort_recursive($child);
    }
    return $value;
}

function classops_audience_canonical_json($value): string
{
    $encoded = json_encode(
        classops_audience_sort_recursive($value),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($encoded)) {
        classops_audience_error('CLASSOPS_AUDIENCE_UNENCODABLE', 'Audience data cannot be encoded.');
    }
    return $encoded;
}

function classops_audience_hash($value): string
{
    return hash('sha256', classops_audience_canonical_json($value));
}

function classops_audience_normalize_digits(string $value): string
{
    return strtr($value, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function classops_audience_normalize_student_number($value): string
{
    if (!is_string($value) && !is_int($value)) {
        classops_audience_error('CLASSOPS_AUDIENCE_INVALID_STUDENT_REF', 'Student reference is invalid.');
    }
    $studentNumber = trim(classops_audience_normalize_digits((string) $value));
    if (preg_match('/^\d{5,20}$/', $studentNumber) !== 1) {
        classops_audience_error('CLASSOPS_AUDIENCE_INVALID_STUDENT_REF', 'Student reference is invalid.');
    }
    return $studentNumber;
}

function classops_audience_normalize_cohort_key($value): string
{
    if (!is_string($value)) {
        classops_audience_error('CLASSOPS_AUDIENCE_INVALID_COHORT', 'Canonical cohort is invalid.');
    }
    $key = strtolower(trim($value));
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $key) !== 1) {
        classops_audience_error('CLASSOPS_AUDIENCE_INVALID_COHORT', 'Canonical cohort is invalid.');
    }
    return $key;
}

function classops_audience_normalize_selector_key($value): string
{
    if (!is_string($value)) {
        classops_audience_error('CLASSOPS_AUDIENCE_INVALID_SELECTOR', 'Canonical selector is invalid.');
    }
    $key = trim($value);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,95}$/', $key) !== 1) {
        classops_audience_error('CLASSOPS_AUDIENCE_INVALID_SELECTOR', 'Canonical selector is invalid.');
    }
    return $key;
}

function classops_audience_warning_add(array &$warnings, string $code, int $count = 1): void
{
    $count = max(1, $count);
    foreach ($warnings as &$warning) {
        if (($warning['code'] ?? '') === $code) {
            $warning['count'] = (int) ($warning['count'] ?? 0) + $count;
            return;
        }
    }
    unset($warning);
    $warnings[] = ['code' => $code, 'count' => $count];
}

function classops_audience_normalize_student_list($raw, string $field, array &$warnings, int &$totalRefs): array
{
    if (!is_array($raw) || !array_is_list($raw) || count($raw) > CLASSOPS_AUDIENCE_MAX_STUDENT_REFS) {
        classops_audience_error('CLASSOPS_AUDIENCE_REF_LIMIT', 'Audience student reference list exceeds its limit.');
    }
    $seen = [];
    $duplicates = 0;
    foreach ($raw as $value) {
        $totalRefs++;
        if ($totalRefs > CLASSOPS_AUDIENCE_MAX_TOTAL_STUDENT_REFS) {
            classops_audience_error('CLASSOPS_AUDIENCE_REF_LIMIT', 'Audience student reference budget was exceeded.');
        }
        $studentNumber = classops_audience_normalize_student_number($value);
        $studentKey = 'student:' . $studentNumber;
        if (isset($seen[$studentKey])) {
            $duplicates++;
            continue;
        }
        $seen[$studentKey] = $studentNumber;
    }
    if ($duplicates > 0) {
        classops_audience_warning_add($warnings, 'AUDIENCE_DUPLICATE_STUDENT_REF', $duplicates);
    }
    $result = array_values($seen);
    sort($result, SORT_STRING);
    return $result;
}
