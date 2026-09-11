<?php
declare(strict_types=1);

final class DentClassOpsReminderException extends RuntimeException
{
    public string $reasonCode;
    /** @var array<string,mixed> */
    public array $details;

    /** @param array<string,mixed> $details */
    public function __construct(string $reasonCode, string $message, array $details = [])
    {
        parent::__construct($message);
        $this->reasonCode = $reasonCode;
        $this->details = $details;
    }
}

const CLASSOPS_REMINDER_CONTRACT_VERSION = 'classops-reminder-v1';
const CLASSOPS_REMINDER_TIMEZONE = 'Asia/Tehran';
const CLASSOPS_REMINDER_MAX_HORIZON_SECONDS = 2678400; // 31 days.
const CLASSOPS_REMINDER_MAX_OCCURRENCES = 128;
const CLASSOPS_REMINDER_MAX_CATCH_UP_SECONDS = 604800; // 7 days.

/** @return never */
function classops_reminder_fail(string $code, string $message, array $details = []): void
{
    throw new DentClassOpsReminderException($code, $message, $details);
}

function classops_reminder_is_list(array $value): bool
{
    if ($value === []) {
        return true;
    }
    return array_keys($value) === range(0, count($value) - 1);
}

/** @param list<string> $allowed */
function classops_reminder_assert_known_fields(array $value, array $allowed, string $path): void
{
    foreach (array_keys($value) as $key) {
        if (!is_string($key) || !in_array($key, $allowed, true)) {
            classops_reminder_fail('CLASSOPS_REMINDER_UNKNOWN_FIELD', 'Unknown reminder field.', [
                'path' => $path,
                'field' => is_scalar($key) ? (string) $key : '',
            ]);
        }
    }
}

function classops_reminder_parse_utc(string $value, string $path): DateTimeImmutable
{
    $value = trim($value);
    if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) !== 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_TIMESTAMP', 'Timestamp must be canonical UTC ISO-8601.', [
            'path' => $path,
        ]);
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $value, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if ($parsed === false || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_TIMESTAMP', 'Timestamp is not a valid UTC instant.', [
            'path' => $path,
        ]);
    }
    return $parsed;
}

function classops_reminder_utc(DateTimeInterface $value): string
{
    return DateTimeImmutable::createFromInterface($value)
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d\\TH:i:s\\Z');
}

function classops_reminder_utf8_length(string $value, string $path): int
{
    $matched = preg_match_all('/./us', $value, $matches);
    if ($matched === false) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_TEXT', 'Reminder text must be valid UTF-8.', ['path' => $path]);
    }
    return $matched;
}

function classops_reminder_validate_id(string $value, string $path, int $maxLength = 96): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > $maxLength || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $value) !== 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_IDENTIFIER', 'Invalid reminder identifier.', ['path' => $path]);
    }
    return $value;
}

function classops_reminder_local_time(string $value, string $path): string
{
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_LOCAL_TIME', 'Local time must be HH:MM.', ['path' => $path]);
    }
    return $value;
}

/** @return array{mode:string,maxAgeSeconds:int} */
function classops_reminder_normalize_catch_up(?array $value): array
{
    if ($value === null) {
        return ['mode' => 'skip', 'maxAgeSeconds' => 0];
    }
    classops_reminder_assert_known_fields($value, ['mode', 'maxAgeSeconds'], 'catchUp');
    $mode = (string) ($value['mode'] ?? '');
    if (!in_array($mode, ['skip', 'latest_once'], true)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_CATCH_UP', 'Unsupported catch-up mode.', ['path' => 'catchUp.mode']);
    }
    $maxAge = $value['maxAgeSeconds'] ?? ($mode === 'skip' ? 0 : null);
    if (!is_int($maxAge) || $maxAge < 0 || $maxAge > CLASSOPS_REMINDER_MAX_CATCH_UP_SECONDS) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_CATCH_UP', 'Catch-up max age is outside the bounded range.', [
            'path' => 'catchUp.maxAgeSeconds',
            'maxAllowed' => CLASSOPS_REMINDER_MAX_CATCH_UP_SECONDS,
        ]);
    }
    if ($mode === 'latest_once' && $maxAge === 0) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_CATCH_UP', 'latest_once requires a positive max age.', ['path' => 'catchUp.maxAgeSeconds']);
    }
    return ['mode' => $mode, 'maxAgeSeconds' => $maxAge];
}

/** @return array<string,mixed> */
function classops_reminder_normalize_rule(array $rule, int $index, string $itemType): array
{
    $path = 'rules[' . $index . ']';
    if (classops_reminder_is_list($rule)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RULE', 'Reminder rule must be an object.', ['path' => $path]);
    }
    $type = (string) ($rule['type'] ?? '');
    $baseFields = ['ruleId', 'type', 'reason'];
    $allowed = match ($type) {
        'absolute' => array_merge($baseFields, ['at']),
        'relative' => array_merge($baseFields, ['anchor', 'offsetSeconds']),
        'daypart' => array_merge($baseFields, ['anchor', 'dayOffset', 'daypart']),
        'recurring' => array_merge($baseFields, ['frequency', 'interval', 'localTime', 'startAt', 'until', 'maxOccurrences', 'weekdays']),
        default => [],
    };
    if ($allowed === []) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RULE_TYPE', 'Unsupported reminder rule type.', ['path' => $path . '.type']);
    }
    classops_reminder_assert_known_fields($rule, $allowed, $path);
    $ruleId = classops_reminder_validate_id((string) ($rule['ruleId'] ?? ''), $path . '.ruleId', 64);
    $reason = trim((string) ($rule['reason'] ?? $ruleId));
    $reasonLength = classops_reminder_utf8_length($reason, $path . '.reason');
    if ($reason === '' || $reasonLength > 160) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_REASON', 'Reminder reason is invalid.', ['path' => $path . '.reason']);
    }

    if ($type === 'absolute') {
        $at = classops_reminder_parse_utc((string) ($rule['at'] ?? ''), $path . '.at');
        return ['ruleId' => $ruleId, 'type' => $type, 'reason' => $reason, 'at' => classops_reminder_utc($at)];
    }

    if ($type === 'relative') {
        $anchor = (string) ($rule['anchor'] ?? '');
        if (!in_array($anchor, ['startsAt', 'dueAt'], true)) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_ANCHOR', 'Relative reminder anchor is invalid.', ['path' => $path . '.anchor']);
        }
        $offset = $rule['offsetSeconds'] ?? null;
        if (!is_int($offset) || $offset < -31536000 || $offset > 31536000) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_OFFSET', 'Relative reminder offset is invalid.', ['path' => $path . '.offsetSeconds']);
        }
        return ['ruleId' => $ruleId, 'type' => $type, 'reason' => $reason, 'anchor' => $anchor, 'offsetSeconds' => $offset];
    }

    if ($type === 'daypart') {
        $anchor = (string) ($rule['anchor'] ?? '');
        if (!in_array($anchor, ['startsAt', 'dueAt'], true)) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_ANCHOR', 'Daypart reminder anchor is invalid.', ['path' => $path . '.anchor']);
        }
        $dayOffset = $rule['dayOffset'] ?? null;
        if (!is_int($dayOffset) || $dayOffset < -31 || $dayOffset > 31) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_DAY_OFFSET', 'Daypart offset is invalid.', ['path' => $path . '.dayOffset']);
        }
        $daypart = (string) ($rule['daypart'] ?? '');
        if (!in_array($daypart, ['morning', 'afternoon', 'evening', 'night'], true)) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_DAYPART', 'Unsupported daypart.', ['path' => $path . '.daypart']);
        }
        return ['ruleId' => $ruleId, 'type' => $type, 'reason' => $reason, 'anchor' => $anchor, 'dayOffset' => $dayOffset, 'daypart' => $daypart];
    }

    if ($itemType !== 'service_reminder') {
        classops_reminder_fail('CLASSOPS_REMINDER_RECURRENCE_NOT_ALLOWED', 'Recurrence is allowed only for service_reminder items.', ['path' => $path]);
    }
    $frequency = (string) ($rule['frequency'] ?? '');
    if (!in_array($frequency, ['daily', 'weekly'], true)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'Only daily and weekly recurrence are approved.', ['path' => $path . '.frequency']);
    }
    $interval = $rule['interval'] ?? 1;
    if (!is_int($interval) || $interval < 1 || $interval > 4) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'Recurrence interval is outside the approved bound.', ['path' => $path . '.interval']);
    }
    $startAt = classops_reminder_parse_utc((string) ($rule['startAt'] ?? ''), $path . '.startAt');
    $until = null;
    if (array_key_exists('until', $rule) && $rule['until'] !== null) {
        if (!is_string($rule['until'])) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'Recurrence until must be UTC or null.', ['path' => $path . '.until']);
        }
        $until = classops_reminder_parse_utc($rule['until'], $path . '.until');
        if ($until < $startAt) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'Recurrence until precedes startAt.', ['path' => $path . '.until']);
        }
    }
    $max = $rule['maxOccurrences'] ?? null;
    if (!is_int($max) || $max < 1 || $max > CLASSOPS_REMINDER_MAX_OCCURRENCES) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'Recurrence maxOccurrences is outside the bound.', ['path' => $path . '.maxOccurrences']);
    }
    $localTime = classops_reminder_local_time((string) ($rule['localTime'] ?? ''), $path . '.localTime');
    $weekdays = [];
    if ($frequency === 'weekly') {
        $rawWeekdays = $rule['weekdays'] ?? [];
        if (!is_array($rawWeekdays) || !classops_reminder_is_list($rawWeekdays) || $rawWeekdays === [] || count($rawWeekdays) > 7) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'Weekly recurrence requires bounded weekdays.', ['path' => $path . '.weekdays']);
        }
        foreach ($rawWeekdays as $weekday) {
            if (!is_int($weekday) || $weekday < 1 || $weekday > 7) {
                classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'Weekday must use ISO 1..7.', ['path' => $path . '.weekdays']);
            }
            $weekdays[] = $weekday;
        }
        $weekdays = array_values(array_unique($weekdays));
        sort($weekdays);
    } elseif (array_key_exists('weekdays', $rule)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RECURRENCE', 'weekdays is valid only for weekly recurrence.', ['path' => $path . '.weekdays']);
    }

    return [
        'ruleId' => $ruleId,
        'type' => $type,
        'reason' => $reason,
        'frequency' => $frequency,
        'interval' => $interval,
        'localTime' => $localTime,
        'startAt' => classops_reminder_utc($startAt),
        'until' => $until === null ? null : classops_reminder_utc($until),
        'maxOccurrences' => $max,
        'weekdays' => $weekdays,
    ];
}

/** @return array{version:string,timezone:string,catchUp:array{mode:string,maxAgeSeconds:int},rules:list<array<string,mixed>>} */
function classops_reminder_normalize_policy(array $policy, string $itemType): array
{
    if (classops_reminder_is_list($policy)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_POLICY', 'Reminder policy must be an object.');
    }
    classops_reminder_assert_known_fields($policy, ['version', 'timezone', 'catchUp', 'rules'], 'reminderPolicy');
    if (($policy['version'] ?? null) !== CLASSOPS_REMINDER_CONTRACT_VERSION) {
        classops_reminder_fail('CLASSOPS_REMINDER_UNSUPPORTED_VERSION', 'Unsupported reminder policy version.', ['path' => 'reminderPolicy.version']);
    }
    if (($policy['timezone'] ?? null) !== CLASSOPS_REMINDER_TIMEZONE) {
        classops_reminder_fail('CLASSOPS_REMINDER_UNSUPPORTED_TIMEZONE', 'Reminder timezone must be Asia/Tehran.', ['path' => 'reminderPolicy.timezone']);
    }
    $rawCatchUp = $policy['catchUp'] ?? null;
    if ($rawCatchUp !== null && !is_array($rawCatchUp)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_CATCH_UP', 'catchUp must be an object.', ['path' => 'reminderPolicy.catchUp']);
    }
    $rawRules = $policy['rules'] ?? null;
    if (!is_array($rawRules) || !classops_reminder_is_list($rawRules) || count($rawRules) > 32) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RULES', 'Reminder rules must be a bounded list.', ['path' => 'reminderPolicy.rules']);
    }
    $rules = [];
    $ids = [];
    foreach ($rawRules as $index => $rule) {
        if (!is_array($rule)) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_RULE', 'Reminder rule must be an object.', ['path' => 'rules[' . $index . ']']);
        }
        $normalized = classops_reminder_normalize_rule($rule, (int) $index, $itemType);
        $id = (string) $normalized['ruleId'];
        if (isset($ids[$id])) {
            classops_reminder_fail('CLASSOPS_REMINDER_DUPLICATE_RULE_ID', 'Reminder rule IDs must be unique.', ['ruleId' => $id]);
        }
        $ids[$id] = true;
        $rules[] = $normalized;
    }
    return [
        'version' => CLASSOPS_REMINDER_CONTRACT_VERSION,
        'timezone' => CLASSOPS_REMINDER_TIMEZONE,
        'catchUp' => classops_reminder_normalize_catch_up($rawCatchUp),
        'rules' => $rules,
    ];
}
