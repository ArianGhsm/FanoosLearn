<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_persistence.php';

const CLASSOPS_SCHEMA_VERSION = 1;
const CLASSOPS_CONTRACT_VERSION = 'classops-v1';
const CLASSOPS_TIMEZONE = 'Asia/Tehran';
const CLASSOPS_AUDIT_LIMIT = 5000;
const CLASSOPS_IDEMPOTENCY_LIMIT = 10000;
const CLASSOPS_IDEMPOTENCY_TTL_SECONDS = 7776000; // 90 days

final class DentClassOpsDomainException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly int $httpStatus,
        string $message
    ) {
        parent::__construct($message);
    }
}

function classops_domain_error(string $code, string $message, int $status = 422): never
{
    throw new DentClassOpsDomainException($code, $status, $message);
}

function classops_store_path(): string
{
    $override = dent_env_value('DENT_CLASSOPS_STORE_PATH');
    if ($override !== '') {
        return dent_resolve_path($override, DENT_PROJECT_ROOT);
    }
    return dent_storage_path('classops/store.json');
}

function classops_default_store(): array
{
    return [
        'schemaVersion' => CLASSOPS_SCHEMA_VERSION,
        'contractVersion' => CLASSOPS_CONTRACT_VERSION,
        'initializedAt' => dent_iso_now(),
        'updatedAt' => dent_iso_now(),
        'items' => [],
        'revisions' => [],
        'idempotency' => [],
        'audit' => [],
        '_storage' => [
            'format' => 'classops-atomic-json-v1',
            'generation' => 0,
            'previousSha256' => '',
            'committedAt' => '',
        ],
    ];
}

function classops_allowed_types(): array
{
    return [
        'announcement', 'event', 'class_change', 'deadline', 'task',
        'requirement', 'exam', 'critical_notice', 'service_reminder',
    ];
}

function classops_allowed_statuses(): array
{
    return ['draft', 'scheduled', 'active', 'completed', 'cancelled', 'archived'];
}

function classops_allowed_importance(): array
{
    return ['normal', 'important', 'critical'];
}

function classops_is_assoc(array $value): bool
{
    return !array_is_list($value);
}

function classops_sort_recursive($value)
{
    if (!is_array($value)) {
        return $value;
    }
    if (classops_is_assoc($value)) {
        ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $child) {
        $value[$key] = classops_sort_recursive($child);
    }
    return $value;
}

function classops_canonical_json($value): string
{
    $json = json_encode(classops_sort_recursive($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        classops_domain_error('CLASSOPS_PAYLOAD_UNENCODABLE', 'ساختار داده قابل پردازش نیست.');
    }
    return $json;
}

function classops_assert_known_keys(array $input, array $allowed, string $code = 'CLASSOPS_UNKNOWN_FIELD'): void
{
    $unknown = array_values(array_diff(array_keys($input), $allowed));
    if ($unknown !== []) {
        classops_domain_error($code, 'فیلد ناشناخته در درخواست ClassOps وجود دارد: ' . implode(', ', $unknown));
    }
}

function classops_text($value, int $maxLength, string $field, bool $required = false): string
{
    if (!is_string($value) && $value !== null) {
        classops_domain_error('CLASSOPS_INVALID_TEXT', "فیلد {$field} باید متن باشد.");
    }
    $text = trim(dent_force_utf8((string) $value));
    if ($required && $text === '') {
        classops_domain_error('CLASSOPS_REQUIRED_FIELD', "فیلد {$field} الزامی است.");
    }
    if (dent_utf8_strlen($text) > $maxLength) {
        classops_domain_error('CLASSOPS_FIELD_TOO_LONG', "فیلد {$field} از حد مجاز طولانی‌تر است.");
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1) {
        classops_domain_error('CLASSOPS_INVALID_TEXT', "فیلد {$field} نویسه کنترلی نامعتبر دارد.");
    }
    return preg_replace('/\r\n|\r/u', "\n", $text) ?? '';
}

function classops_normalize_timestamp($value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value) || strlen($value) > 64 || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
        classops_domain_error('CLASSOPS_INVALID_TIMESTAMP', "زمان {$field} باید ISO-8601 و دارای offset باشد.");
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        classops_domain_error('CLASSOPS_INVALID_TIMESTAMP', "زمان {$field} معتبر نیست.");
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

function classops_normalize_course($value): ?array
{
    if ($value === null || $value === []) {
        return null;
    }
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_domain_error('CLASSOPS_INVALID_COURSE', 'اطلاعات درس باید یک object باشد.');
    }
    classops_assert_known_keys($value, ['ref', 'title']);
    $ref = classops_text($value['ref'] ?? '', 96, 'course.ref');
    $title = classops_text($value['title'] ?? '', 180, 'course.title');
    if ($ref === '' && $title === '') {
        return null;
    }
    if ($ref !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,95}$/', $ref) !== 1) {
        classops_domain_error('CLASSOPS_INVALID_COURSE_REF', 'شناسه درس معتبر نیست.');
    }
    return ['ref' => $ref, 'title' => $title];
}

function classops_normalize_timing($value): array
{
    if ($value === null || $value === []) {
        $value = [];
    }
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_domain_error('CLASSOPS_INVALID_TIMING', 'ساختار زمان‌بندی معتبر نیست.');
    }
    classops_assert_known_keys($value, ['startsAt', 'endsAt', 'dueAt', 'timezone']);
    $timezone = classops_text($value['timezone'] ?? CLASSOPS_TIMEZONE, 64, 'timing.timezone', true);
    if ($timezone !== CLASSOPS_TIMEZONE) {
        classops_domain_error('CLASSOPS_INVALID_TIMEZONE', 'Timezone این نسخه فقط Asia/Tehran است.');
    }
    $startsAt = classops_normalize_timestamp($value['startsAt'] ?? null, 'startsAt');
    $endsAt = classops_normalize_timestamp($value['endsAt'] ?? null, 'endsAt');
    $dueAt = classops_normalize_timestamp($value['dueAt'] ?? null, 'dueAt');
    if ($startsAt !== null && $endsAt !== null && strcmp($endsAt, $startsAt) < 0) {
        classops_domain_error('CLASSOPS_INVALID_TIME_RANGE', 'زمان پایان نمی‌تواند قبل از شروع باشد.');
    }
    return ['startsAt' => $startsAt, 'endsAt' => $endsAt, 'dueAt' => $dueAt, 'timezone' => CLASSOPS_TIMEZONE];
}

function classops_normalize_audience($value): array
{
    if ($value === null || $value === []) {
        $value = ['mode' => 'entire_cohort', 'refs' => []];
    }
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_domain_error('CLASSOPS_INVALID_AUDIENCE', 'تعریف مخاطب معتبر نیست.');
    }
    classops_assert_known_keys($value, ['version', 'mode', 'refs']);
    $version = classops_text($value['version'] ?? 'classops-audience-placeholder-v1', 64, 'audienceSpec.version', true);
    if ($version !== 'classops-audience-placeholder-v1') {
        classops_domain_error('CLASSOPS_AUDIENCE_VERSION_UNSUPPORTED', 'نسخه تعریف مخاطب پشتیبانی نمی‌شود.');
    }
    $mode = classops_text($value['mode'] ?? '', 40, 'audienceSpec.mode', true);
    $modes = ['entire_cohort', 'single_student', 'explicit_students', 'academic_group', 'snapshot', 'dynamic'];
    if (!in_array($mode, $modes, true)) {
        classops_domain_error('CLASSOPS_INVALID_AUDIENCE_MODE', 'نوع مخاطب معتبر نیست.');
    }
    $rawRefs = $value['refs'] ?? [];
    if (!is_array($rawRefs) || !array_is_list($rawRefs) || count($rawRefs) > 500) {
        classops_domain_error('CLASSOPS_INVALID_AUDIENCE_REFS', 'فهرست شناسه‌های مخاطب معتبر نیست.');
    }
    $refs = [];
    foreach ($rawRefs as $rawRef) {
        $ref = classops_text($rawRef, 96, 'audienceSpec.refs');
        if ($ref === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,95}$/', $ref) !== 1) {
            classops_domain_error('CLASSOPS_INVALID_AUDIENCE_REF', 'شناسه مخاطب باید canonical و غیرنامی باشد.');
        }
        $refs[$ref] = true;
    }
    $refs = array_keys($refs);
    $expected = match ($mode) {
        'entire_cohort' => [0, 0],
        'single_student', 'academic_group', 'snapshot', 'dynamic' => [1, 1],
        'explicit_students' => [1, 500],
    };
    if (count($refs) < $expected[0] || count($refs) > $expected[1]) {
        classops_domain_error('CLASSOPS_INVALID_AUDIENCE_REFS', 'تعداد شناسه‌ها با نوع مخاطب سازگار نیست.');
    }
    if (in_array($mode, ['single_student', 'explicit_students'], true)) {
        foreach ($refs as $ref) {
            if (preg_match('/^\d{5,20}$/', dent_normalize_digits($ref)) !== 1) {
                classops_domain_error('CLASSOPS_INVALID_STUDENT_REF', 'مخاطب دانشجویی فقط با شماره دانشجویی canonical پذیرفته می‌شود.');
            }
        }
        $refs = array_map('dent_normalize_digits', $refs);
    }
    return ['version' => $version, 'mode' => $mode, 'refs' => $refs];
}

function classops_normalize_delivery_plan($value): array
{
    if ($value === null || $value === []) {
        $value = [];
    }
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_domain_error('CLASSOPS_INVALID_DELIVERY_PLAN', 'برنامه مقصد معتبر نیست.');
    }
    classops_assert_known_keys($value, ['version', 'initialDestinations', 'reminderDestinations']);
    $version = classops_text($value['version'] ?? 'classops-delivery-placeholder-v1', 64, 'deliveryPlan.version', true);
    if ($version !== 'classops-delivery-placeholder-v1') {
        classops_domain_error('CLASSOPS_DELIVERY_VERSION_UNSUPPORTED', 'نسخه برنامه مقصد پشتیبانی نمی‌شود.');
    }
    $allowed = ['private_users', 'class_group', 'information_channel'];
    $normalize = static function ($raw, string $field) use ($allowed): array {
        if (!is_array($raw) || !array_is_list($raw) || count($raw) > 3) {
            classops_domain_error('CLASSOPS_INVALID_DESTINATIONS', "فیلد {$field} معتبر نیست.");
        }
        $result = [];
        foreach ($raw as $destination) {
            if (!is_string($destination) || !in_array($destination, $allowed, true)) {
                classops_domain_error('CLASSOPS_INVALID_DESTINATION', 'مقصد symbolic معتبر نیست.');
            }
            $result[$destination] = true;
        }
        return array_keys($result);
    };
    return [
        'version' => $version,
        'initialDestinations' => $normalize($value['initialDestinations'] ?? [], 'initialDestinations'),
        'reminderDestinations' => $normalize($value['reminderDestinations'] ?? [], 'reminderDestinations'),
    ];
}

function classops_normalize_reminder_policy($value): array
{
    if ($value === null || $value === []) {
        $value = [];
    }
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_domain_error('CLASSOPS_INVALID_REMINDER_POLICY', 'سیاست یادآوری معتبر نیست.');
    }
    classops_assert_known_keys($value, ['version', 'rules']);
    $version = classops_text($value['version'] ?? 'classops-reminder-placeholder-v1', 64, 'reminderPolicy.version', true);
    if ($version !== 'classops-reminder-placeholder-v1') {
        classops_domain_error('CLASSOPS_REMINDER_VERSION_UNSUPPORTED', 'نسخه سیاست یادآوری پشتیبانی نمی‌شود.');
    }
    $rules = $value['rules'] ?? [];
    if (!is_array($rules) || !array_is_list($rules) || count($rules) > 20) {
        classops_domain_error('CLASSOPS_INVALID_REMINDER_RULES', 'قواعد یادآوری معتبر نیستند.');
    }
    $normalized = [];
    foreach ($rules as $rule) {
        if (!is_array($rule) || array_is_list($rule)) {
            classops_domain_error('CLASSOPS_INVALID_REMINDER_RULE', 'قاعده یادآوری باید object باشد.');
        }
        classops_assert_known_keys($rule, ['offsetSeconds', 'destinationScope']);
        $offset = filter_var($rule['offsetSeconds'] ?? null, FILTER_VALIDATE_INT);
        if ($offset === false || $offset < 0 || $offset > 31536000) {
            classops_domain_error('CLASSOPS_INVALID_REMINDER_OFFSET', 'فاصله یادآوری معتبر نیست.');
        }
        $scope = classops_text($rule['destinationScope'] ?? 'inherit', 32, 'destinationScope', true);
        if (!in_array($scope, ['inherit', 'reminder_only'], true)) {
            classops_domain_error('CLASSOPS_INVALID_REMINDER_SCOPE', 'دامنه مقصد یادآوری معتبر نیست.');
        }
        $normalized[] = ['offsetSeconds' => (int) $offset, 'destinationScope' => $scope];
    }
    return ['version' => $version, 'rules' => $normalized];
}

function classops_normalize_extension_map($value, string $field): array
{
    if ($value === null || $value === []) {
        return [];
    }
    if (!is_array($value) || array_is_list($value)) {
        classops_domain_error('CLASSOPS_INVALID_EXTENSION_MAP', "فیلد {$field} باید object باشد.");
    }
    if (count($value) > 50 || strlen(classops_canonical_json($value)) > 16384) {
        classops_domain_error('CLASSOPS_EXTENSION_TOO_LARGE', "فیلد {$field} بیش از حد بزرگ است.");
    }
    foreach ($value as $key => $entry) {
        if (!is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/', $key) !== 1) {
            classops_domain_error('CLASSOPS_INVALID_EXTENSION_KEY', "کلید {$field} معتبر نیست.");
        }
        if (is_resource($entry) || is_object($entry)) {
            classops_domain_error('CLASSOPS_INVALID_EXTENSION_VALUE', "مقدار {$field} معتبر نیست.");
        }
    }
    return $value;
}

function classops_normalize_source($value): array
{
    if ($value === null || $value === []) {
        $value = [];
    }
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_domain_error('CLASSOPS_INVALID_SOURCE', 'منبع باید object باشد.');
    }
    classops_assert_known_keys($value, ['kind', 'ref']);
    $kind = classops_text($value['kind'] ?? 'manual', 24, 'source.kind', true);
    if (!in_array($kind, ['manual', 'import', 'system'], true)) {
        classops_domain_error('CLASSOPS_INVALID_SOURCE_KIND', 'نوع منبع معتبر نیست.');
    }
    $ref = classops_text($value['ref'] ?? '', 120, 'source.ref');
    return ['kind' => $kind, 'ref' => $ref];
}

function classops_normalize_item_fields(array $input, bool $patch = false): array
{
    $allowed = [
        'cohortKey', 'type', 'title', 'description', 'course', 'timing', 'location',
        'importance', 'requireAck', 'audienceSpec', 'deliveryPlan', 'reminderPolicy',
        'status', 'source', 'metadata', 'extensions',
    ];
    classops_assert_known_keys($input, $allowed);
    if ($patch && $input === []) {
        classops_domain_error('CLASSOPS_EMPTY_PATCH', 'هیچ تغییری ارسال نشده است.');
    }

    $result = [];
    $apply = static fn(string $key): bool => !$patch || array_key_exists($key, $input);
    if ($apply('cohortKey')) {
        $cohort = classops_text($input['cohortKey'] ?? '', 80, 'cohortKey', true);
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $cohort) !== 1) {
            classops_domain_error('CLASSOPS_INVALID_COHORT', 'شناسه ورودی معتبر نیست.');
        }
        $result['cohortKey'] = $cohort;
    }
    if ($apply('type')) {
        $type = classops_text($input['type'] ?? '', 40, 'type', true);
        if (!in_array($type, classops_allowed_types(), true)) {
            classops_domain_error('CLASSOPS_INVALID_TYPE', 'نوع آیتم ClassOps معتبر نیست.');
        }
        $result['type'] = $type;
    }
    if ($apply('title')) {
        $result['title'] = classops_text($input['title'] ?? '', 160, 'title', true);
    }
    if ($apply('description')) {
        $result['description'] = classops_text($input['description'] ?? '', 4000, 'description');
    }
    if ($apply('course')) {
        $result['course'] = classops_normalize_course($input['course'] ?? null);
    }
    if ($apply('timing')) {
        $result['timing'] = classops_normalize_timing($input['timing'] ?? null);
    }
    if ($apply('location')) {
        $result['location'] = classops_text($input['location'] ?? '', 300, 'location');
    }
    if ($apply('importance')) {
        $importance = classops_text($input['importance'] ?? 'normal', 24, 'importance', true);
        if (!in_array($importance, classops_allowed_importance(), true)) {
            classops_domain_error('CLASSOPS_INVALID_IMPORTANCE', 'درجه اهمیت معتبر نیست.');
        }
        $result['importance'] = $importance;
    }
    if ($apply('requireAck')) {
        if (!is_bool($input['requireAck'] ?? false)) {
            classops_domain_error('CLASSOPS_INVALID_REQUIRE_ACK', 'requireAck باید boolean باشد.');
        }
        $result['requireAck'] = (bool) $input['requireAck'];
    }
    if ($apply('audienceSpec')) {
        $result['audienceSpec'] = classops_normalize_audience($input['audienceSpec'] ?? null);
    }
    if ($apply('deliveryPlan')) {
        $result['deliveryPlan'] = classops_normalize_delivery_plan($input['deliveryPlan'] ?? null);
    }
    if ($apply('reminderPolicy')) {
        $result['reminderPolicy'] = classops_normalize_reminder_policy($input['reminderPolicy'] ?? null);
    }
    if ($apply('status')) {
        $status = classops_text($input['status'] ?? 'draft', 24, 'status', true);
        if (!in_array($status, classops_allowed_statuses(), true)) {
            classops_domain_error('CLASSOPS_INVALID_STATUS', 'وضعیت معتبر نیست.');
        }
        $result['status'] = $status;
    }
    if ($apply('source')) {
        $result['source'] = classops_normalize_source($input['source'] ?? null);
    }
    if ($apply('metadata')) {
        $result['metadata'] = classops_normalize_extension_map($input['metadata'] ?? [], 'metadata');
    }
    if ($apply('extensions')) {
        $result['extensions'] = classops_normalize_extension_map($input['extensions'] ?? [], 'extensions');
    }
    return $result;
}

function classops_default_item_fields(array $input): array
{
    return classops_normalize_item_fields(array_merge([
        'description' => '',
        'course' => null,
        'timing' => null,
        'location' => '',
        'importance' => 'normal',
        'requireAck' => false,
        'audienceSpec' => null,
        'deliveryPlan' => null,
        'reminderPolicy' => null,
        'status' => 'draft',
        'source' => null,
        'metadata' => [],
        'extensions' => [],
    ], $input));
}

function classops_actor_ref(array $viewer): string
{
    $studentNumber = dent_normalize_student_number((string) ($viewer['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        classops_domain_error('CLASSOPS_ACTOR_INVALID', 'هویت canonical مالک معتبر نیست.', 403);
    }
    return 'usr_' . substr(hash('sha256', 'classops-actor-v1|' . $studentNumber), 0, 24);
}

function classops_actor(array $viewer): array
{
    return ['kind' => 'canonical_user', 'ref' => classops_actor_ref($viewer), 'role' => 'owner'];
}

function classops_assert_lifecycle_transition(string $from, string $to): void
{
    $transitions = [
        'draft' => ['draft', 'scheduled', 'cancelled', 'archived'],
        'scheduled' => ['scheduled', 'draft', 'active', 'cancelled', 'archived'],
        'active' => ['active', 'completed', 'cancelled', 'archived'],
        'completed' => ['completed', 'archived'],
        'cancelled' => ['cancelled', 'archived'],
        'archived' => ['archived'],
    ];
    if (!in_array($to, $transitions[$from] ?? [], true)) {
        classops_domain_error('CLASSOPS_INVALID_TRANSITION', "گذار وضعیت {$from} به {$to} مجاز نیست.", 409);
    }
}

function classops_changed_fields(?array $previous, array $current): array
{
    if ($previous === null) {
        return array_keys($current);
    }
    $changed = [];
    foreach (array_unique(array_merge(array_keys($previous), array_keys($current))) as $key) {
        if (classops_canonical_json($previous[$key] ?? null) !== classops_canonical_json($current[$key] ?? null)) {
            $changed[] = $key;
        }
    }
    sort($changed, SORT_STRING);
    return $changed;
}

function classops_append_revision(array &$store, array $item, ?array $previous, array $actor, string $reason, string $action): void
{
    $id = (string) $item['id'];
    $revision = (int) $item['revision'];
    $store['revisions'][$id] ??= [];
    $store['revisions'][$id][] = [
        'itemId' => $id,
        'revision' => $revision,
        'changedAt' => (string) $item['updatedAt'],
        'changedBy' => $actor,
        'action' => $action,
        'reason' => $reason,
        'previousHash' => $previous === null ? '' : hash('sha256', classops_canonical_json($previous)),
        'newHash' => hash('sha256', classops_canonical_json($item)),
        'changedFields' => classops_changed_fields($previous, $item),
        'snapshot' => $item,
    ];
}

function classops_append_audit(array &$store, string $action, array $item, array $actor, string $resultCode): void
{
    $store['audit'][] = [
        'event' => $action,
        'itemId' => (string) $item['id'],
        'revision' => (int) $item['revision'],
        'actor' => $actor,
        'timestamp' => (string) $item['updatedAt'],
        'resultCode' => $resultCode,
    ];
    if (count($store['audit']) > CLASSOPS_AUDIT_LIMIT) {
        $store['audit'] = array_slice($store['audit'], -CLASSOPS_AUDIT_LIMIT);
    }
}

function classops_prune_idempotency(array &$store): void
{
    $cutoff = time() - CLASSOPS_IDEMPOTENCY_TTL_SECONDS;
    foreach ($store['idempotency'] as $key => $record) {
        $created = strtotime((string) ($record['createdAt'] ?? ''));
        if ($created !== false && $created < $cutoff) {
            unset($store['idempotency'][$key]);
        }
    }
    if (count($store['idempotency']) > CLASSOPS_IDEMPOTENCY_LIMIT) {
        uasort($store['idempotency'], static fn(array $left, array $right): int => strcmp((string) ($left['createdAt'] ?? ''), (string) ($right['createdAt'] ?? '')));
        foreach (array_slice(array_keys($store['idempotency']), 0, count($store['idempotency']) - CLASSOPS_IDEMPOTENCY_LIMIT) as $key) {
            unset($store['idempotency'][$key]);
        }
    }
}

function classops_with_idempotency(array &$store, array $actor, string $action, string $key, array $payload, callable $mutation): array
{
    $key = classops_text($key, 128, 'idempotencyKey', true);
    if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key) !== 1) {
        classops_domain_error('CLASSOPS_INVALID_IDEMPOTENCY_KEY', 'کلید idempotency معتبر نیست.');
    }
    $requestHash = hash('sha256', classops_canonical_json($payload));
    $recordKey = hash('sha256', $actor['ref'] . '|' . $action . '|' . $key);
    $existing = $store['idempotency'][$recordKey] ?? null;
    if (is_array($existing)) {
        if (!hash_equals((string) ($existing['requestHash'] ?? ''), $requestHash)) {
            classops_domain_error('CLASSOPS_IDEMPOTENCY_CONFLICT', 'این idempotency key قبلاً با payload دیگری استفاده شده است.', 409);
        }
        $result = is_array($existing['result'] ?? null) ? $existing['result'] : [];
        $result['idempotentReplay'] = true;
        return $result;
    }

    $result = $mutation();
    $result['idempotentReplay'] = false;
    $store['idempotency'][$recordKey] = [
        'action' => $action,
        'requestHash' => $requestHash,
        'createdAt' => dent_iso_now(),
        'result' => $result,
    ];
    classops_prune_idempotency($store);
    return $result;
}

function classops_store_validator(array $store): void
{
    $fail = static function (string $message): never {
        throw new DentClassOpsPersistenceException('CLASSOPS_SCHEMA_INVALID', $message);
    };
    if (($store['schemaVersion'] ?? null) !== CLASSOPS_SCHEMA_VERSION
        || ($store['contractVersion'] ?? null) !== CLASSOPS_CONTRACT_VERSION) {
        $fail('ClassOps schema or contract version mismatch');
    }
    foreach (['items', 'revisions', 'idempotency', 'audit', '_storage'] as $field) {
        if (!isset($store[$field]) || !is_array($store[$field])) {
            $fail("ClassOps store field {$field} is invalid");
        }
    }
    foreach (['items', 'revisions', 'idempotency'] as $keyedField) {
        if ($store[$keyedField] !== [] && array_is_list($store[$keyedField])) {
            $fail('ClassOps keyed collections must be objects');
        }
    }
    if (($store['_storage']['format'] ?? '') !== 'classops-atomic-json-v1'
        || !is_int($store['_storage']['generation'] ?? null)
        || (int) $store['_storage']['generation'] < 0) {
        $fail('ClassOps generation metadata is invalid');
    }
    foreach ($store['items'] as $id => $item) {
        if (!is_string($id) || !is_array($item) || (string) ($item['id'] ?? '') !== $id) {
            $fail('ClassOps item identity is invalid');
        }
        if (($item['contractVersion'] ?? '') !== CLASSOPS_CONTRACT_VERSION
            || !in_array((string) ($item['type'] ?? ''), classops_allowed_types(), true)
            || !in_array((string) ($item['status'] ?? ''), classops_allowed_statuses(), true)
            || !is_int($item['revision'] ?? null) || (int) $item['revision'] < 1) {
            $fail('ClassOps item contract is invalid');
        }
        $requiredItemFields = [
            'cohortKey', 'type', 'title', 'description', 'course', 'timing', 'location',
            'importance', 'requireAck', 'audienceSpec', 'deliveryPlan', 'reminderPolicy',
            'status', 'source', 'metadata', 'extensions', 'createdBy', 'createdAt', 'updatedAt',
        ];
        foreach ($requiredItemFields as $field) {
            if (!array_key_exists($field, $item)) {
                $fail("ClassOps item field {$field} is missing");
            }
        }
        try {
            $canonicalFields = classops_normalize_item_fields(array_intersect_key($item, array_flip([
                'cohortKey', 'type', 'title', 'description', 'course', 'timing', 'location',
                'importance', 'requireAck', 'audienceSpec', 'deliveryPlan', 'reminderPolicy',
                'status', 'source', 'metadata', 'extensions',
            ])));
        } catch (Throwable $exception) {
            $fail('ClassOps item fields are not canonical');
        }
        foreach ($canonicalFields as $field => $value) {
            if (classops_canonical_json($item[$field] ?? null) !== classops_canonical_json($value)) {
                $fail("ClassOps item field {$field} is not canonical");
            }
        }
        if (!is_array($item['createdBy'])
            || ($item['createdBy']['kind'] ?? '') !== 'canonical_user'
            || ($item['createdBy']['role'] ?? '') !== 'owner'
            || preg_match('/^usr_[a-f0-9]{24}$/', (string) ($item['createdBy']['ref'] ?? '')) !== 1
            || classops_normalize_timestamp((string) $item['createdAt'], 'createdAt') === null
            || classops_normalize_timestamp((string) $item['updatedAt'], 'updatedAt') === null) {
            $fail('ClassOps item actor or timestamp is invalid');
        }
        $history = $store['revisions'][$id] ?? null;
        if (!is_array($history) || $history === []) {
            $fail('ClassOps revision history is missing');
        }
        $expected = 1;
        foreach ($history as $revision) {
            if (!is_array($revision) || (int) ($revision['revision'] ?? 0) !== $expected
                || (string) ($revision['itemId'] ?? '') !== $id || !is_array($revision['snapshot'] ?? null)) {
                $fail('ClassOps revision sequence is invalid');
            }
            $expected++;
        }
        if ((int) $item['revision'] !== $expected - 1) {
            $fail('ClassOps current revision does not match history');
        }
        $latest = $history[count($history) - 1]['snapshot'] ?? null;
        if (!is_array($latest) || classops_canonical_json($latest) !== classops_canonical_json($item)) {
            $fail('ClassOps current item does not match latest revision snapshot');
        }
    }
    foreach ($store['revisions'] as $id => $history) {
        if (!isset($store['items'][$id]) || !is_array($history)) {
            $fail('ClassOps orphan revision history exists');
        }
    }
    if (!array_is_list($store['audit']) || count($store['audit']) > CLASSOPS_AUDIT_LIMIT) {
        $fail('ClassOps audit collection is invalid');
    }
    foreach ($store['audit'] as $record) {
        if (!is_array($record)
            || !isset($store['items'][(string) ($record['itemId'] ?? '')])
            || !is_int($record['revision'] ?? null)
            || !is_array($record['actor'] ?? null)
            || preg_match('/^usr_[a-f0-9]{24}$/', (string) ($record['actor']['ref'] ?? '')) !== 1) {
            $fail('ClassOps audit record is invalid');
        }
    }
    if (count($store['idempotency']) > CLASSOPS_IDEMPOTENCY_LIMIT) {
        $fail('ClassOps idempotency collection is unbounded');
    }
    foreach ($store['idempotency'] as $key => $record) {
        if (preg_match('/^[a-f0-9]{64}$/', (string) $key) !== 1
            || !is_array($record)
            || !in_array((string) ($record['action'] ?? ''), ['create', 'update', 'cancel', 'archive'], true)
            || preg_match('/^[a-f0-9]{64}$/', (string) ($record['requestHash'] ?? '')) !== 1
            || !is_array($record['result'] ?? null)) {
            $fail('ClassOps idempotency record is invalid');
        }
    }
}

function classops_read_snapshot(bool $allowMissing = true): array
{
    return classops_persistence_read(classops_store_path(), $allowMissing, classops_default_store(), 'classops_store_validator');
}

function classops_read_store(bool $allowMissing = true): array
{
    return classops_read_snapshot($allowMissing)['store'];
}

function classops_transaction(callable $mutator, string $action, bool $initializeIfMissing = false, array $testOptions = []): array
{
    return classops_persistence_transaction(
        classops_store_path(),
        $initializeIfMissing,
        classops_default_store(),
        'classops_store_validator',
        $mutator,
        $action,
        $testOptions
    );
}

function classops_new_id(): string
{
    return 'cop_' . bin2hex(random_bytes(12));
}

function classops_create_item(array $input, array $viewer, string $idempotencyKey, string $reason = ''): array
{
    $actor = classops_actor($viewer);
    $fields = classops_default_item_fields($input);
    if ($fields['status'] !== 'draft') {
        classops_domain_error('CLASSOPS_CREATE_MUST_BE_DRAFT', 'آیتم جدید فقط در وضعیت draft ساخته می‌شود.');
    }
    $reason = classops_text($reason !== '' ? $reason : 'create draft', 300, 'reason', true);
    $transaction = classops_transaction(static function (array &$store) use ($actor, $fields, $idempotencyKey, $reason): array {
        return classops_with_idempotency($store, $actor, 'create', $idempotencyKey, ['item' => $fields, 'reason' => $reason], static function () use (&$store, $actor, $fields, $reason): array {
            $now = dent_iso_now();
            do {
                $id = classops_new_id();
            } while (isset($store['items'][$id]));
            $item = [
                'id' => $id,
                'contractVersion' => CLASSOPS_CONTRACT_VERSION,
            ] + $fields + [
                'createdBy' => $actor,
                'createdAt' => $now,
                'updatedAt' => $now,
                'revision' => 1,
            ];
            $store['items'][$id] = $item;
            classops_append_revision($store, $item, null, $actor, $reason, 'created');
            classops_append_audit($store, 'created', $item, $actor, 'CLASSOPS_CREATED');
            $store['updatedAt'] = $now;
            return ['item' => $item];
        });
    }, 'create', true);
    $result = $transaction['result'];
    $result['writePerformed'] = $transaction['written'];
    return $result;
}

function classops_update_item(
    string $id,
    int $expectedRevision,
    array $patch,
    array $viewer,
    string $idempotencyKey,
    string $reason,
    string $operation = 'update'
): array
{
    $id = classops_text($id, 64, 'id', true);
    $reason = classops_text($reason, 300, 'reason', true);
    if ($expectedRevision < 1) {
        classops_domain_error('CLASSOPS_EXPECTED_REVISION_REQUIRED', 'expectedRevision معتبر الزامی است.');
    }
    $actor = classops_actor($viewer);
    $normalizedPatch = classops_normalize_item_fields($patch, true);
    if (!in_array($operation, ['update', 'cancel', 'archive'], true)) {
        classops_domain_error('CLASSOPS_UNKNOWN_ACTION', 'عملیات lifecycle معتبر نیست.', 404);
    }
    $transaction = classops_transaction(static function (array &$store) use ($id, $expectedRevision, $normalizedPatch, $actor, $idempotencyKey, $reason, $operation): array {
        return classops_with_idempotency($store, $actor, $operation, $idempotencyKey, [
            'id' => $id, 'expectedRevision' => $expectedRevision, 'patch' => $normalizedPatch, 'reason' => $reason,
        ], static function () use (&$store, $id, $expectedRevision, $normalizedPatch, $actor, $reason, $operation): array {
            $previous = $store['items'][$id] ?? null;
            if (!is_array($previous)) {
                classops_domain_error('CLASSOPS_ITEM_NOT_FOUND', 'آیتم ClassOps پیدا نشد.', 404);
            }
            if ((int) $previous['revision'] !== $expectedRevision) {
                classops_domain_error('CLASSOPS_REVISION_CONFLICT', 'نسخه آیتم تغییر کرده است؛ داده تازه را دریافت کن.', 409);
            }
            if ((string) $previous['status'] === 'archived') {
                classops_domain_error('CLASSOPS_ITEM_ARCHIVED', 'آیتم archive‌شده قابل ویرایش نیست.', 409);
            }
            $next = array_replace($previous, $normalizedPatch);
            classops_assert_lifecycle_transition((string) $previous['status'], (string) $next['status']);
            $semanticPrevious = $previous;
            unset($semanticPrevious['updatedAt'], $semanticPrevious['revision']);
            $semanticNext = $next;
            unset($semanticNext['updatedAt'], $semanticNext['revision']);
            if (classops_canonical_json($semanticPrevious) === classops_canonical_json($semanticNext)) {
                classops_domain_error('CLASSOPS_NO_CHANGES', 'تغییر معنی‌داری در آیتم وجود ندارد.', 409);
            }
            $next['updatedAt'] = dent_iso_now();
            $next['revision'] = $expectedRevision + 1;
            $store['items'][$id] = $next;
            $revisionAction = $operation === 'update' ? 'updated' : ($operation === 'cancel' ? 'cancelled' : 'archived');
            $resultCode = $operation === 'update' ? 'CLASSOPS_UPDATED' : ($operation === 'cancel' ? 'CLASSOPS_CANCELLED' : 'CLASSOPS_ARCHIVED');
            classops_append_revision($store, $next, $previous, $actor, $reason, $revisionAction);
            classops_append_audit($store, $revisionAction, $next, $actor, $resultCode);
            $store['updatedAt'] = $next['updatedAt'];
            return ['item' => $next];
        });
    }, $operation);
    $result = $transaction['result'];
    $result['writePerformed'] = $transaction['written'];
    return $result;
}

function classops_transition_item(string $action, string $id, int $expectedRevision, array $viewer, string $idempotencyKey, string $reason): array
{
    $target = $action === 'cancel' ? 'cancelled' : ($action === 'archive' ? 'archived' : '');
    if ($target === '') {
        classops_domain_error('CLASSOPS_UNKNOWN_ACTION', 'عملیات lifecycle معتبر نیست.', 404);
    }
    return classops_update_item($id, $expectedRevision, ['status' => $target], $viewer, $idempotencyKey, $reason, $action);
}

function classops_cursor_offset(string $cursor): int
{
    if ($cursor === '') {
        return 0;
    }
    $padded = strtr($cursor, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    $decoded = base64_decode($padded, true);
    if (!is_string($decoded) || preg_match('/^classops-v1:(\d+)$/', $decoded, $matches) !== 1) {
        classops_domain_error('CLASSOPS_INVALID_CURSOR', 'cursor معتبر نیست.');
    }
    return min(1000000, (int) $matches[1]);
}

function classops_make_cursor(int $offset): string
{
    return rtrim(strtr(base64_encode('classops-v1:' . max(0, $offset)), '+/', '-_'), '=');
}

function classops_list_items(array $filters = []): array
{
    classops_assert_known_keys($filters, ['cohortKey', 'type', 'status', 'limit', 'cursor']);
    $limit = filter_var($filters['limit'] ?? 25, FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 100) {
        classops_domain_error('CLASSOPS_INVALID_LIMIT', 'limit باید بین ۱ تا ۱۰۰ باشد.');
    }
    $cohort = classops_text($filters['cohortKey'] ?? '', 80, 'cohortKey');
    $type = classops_text($filters['type'] ?? '', 40, 'type');
    $status = classops_text($filters['status'] ?? '', 24, 'status');
    if ($type !== '' && !in_array($type, classops_allowed_types(), true)) {
        classops_domain_error('CLASSOPS_INVALID_TYPE', 'نوع آیتم معتبر نیست.');
    }
    if ($status !== '' && !in_array($status, classops_allowed_statuses(), true)) {
        classops_domain_error('CLASSOPS_INVALID_STATUS', 'وضعیت معتبر نیست.');
    }
    $offset = classops_cursor_offset(classops_text($filters['cursor'] ?? '', 128, 'cursor'));
    $snapshot = classops_read_snapshot(true);
    $items = array_values(array_filter($snapshot['store']['items'], static function (array $item) use ($cohort, $type, $status): bool {
        return ($cohort === '' || (string) $item['cohortKey'] === $cohort)
            && ($type === '' || (string) $item['type'] === $type)
            && ($status === '' || (string) $item['status'] === $status);
    }));
    usort($items, static fn(array $left, array $right): int => strcmp((string) $right['updatedAt'] . '|' . (string) $right['id'], (string) $left['updatedAt'] . '|' . (string) $left['id']));
    $page = array_slice($items, $offset, (int) $limit);
    $nextOffset = $offset + count($page);
    return [
        'items' => $page,
        'count' => count($page),
        'total' => count($items),
        'nextCursor' => $nextOffset < count($items) ? classops_make_cursor($nextOffset) : null,
        'generation' => (int) ($snapshot['store']['_storage']['generation'] ?? 0),
        'initialized' => (bool) $snapshot['exists'],
    ];
}

function classops_get_item(string $id): array
{
    $id = classops_text($id, 64, 'id', true);
    $store = classops_read_store(true);
    $item = $store['items'][$id] ?? null;
    if (!is_array($item)) {
        classops_domain_error('CLASSOPS_ITEM_NOT_FOUND', 'آیتم ClassOps پیدا نشد.', 404);
    }
    return $item;
}

function classops_revision_history(string $id, int $limit = 50, string $cursor = ''): array
{
    $id = classops_text($id, 64, 'id', true);
    if ($limit < 1 || $limit > 100) {
        classops_domain_error('CLASSOPS_INVALID_LIMIT', 'limit باید بین ۱ تا ۱۰۰ باشد.');
    }
    $offset = classops_cursor_offset($cursor);
    $store = classops_read_store(true);
    if (!isset($store['items'][$id])) {
        classops_domain_error('CLASSOPS_ITEM_NOT_FOUND', 'آیتم ClassOps پیدا نشد.', 404);
    }
    $history = array_reverse($store['revisions'][$id] ?? []);
    $page = array_slice($history, $offset, $limit);
    $nextOffset = $offset + count($page);
    return [
        'revisions' => $page,
        'count' => count($page),
        'total' => count($history),
        'nextCursor' => $nextOffset < count($history) ? classops_make_cursor($nextOffset) : null,
    ];
}

function classops_status(): array
{
    $snapshot = classops_read_snapshot(true);
    $store = $snapshot['store'];
    $counts = array_fill_keys(classops_allowed_statuses(), 0);
    foreach ($store['items'] as $item) {
        $status = (string) ($item['status'] ?? '');
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }
    return [
        'initialized' => (bool) $snapshot['exists'],
        'contractVersion' => CLASSOPS_CONTRACT_VERSION,
        'storage' => 'atomic-json',
        'generation' => (int) ($store['_storage']['generation'] ?? 0),
        'itemCount' => count($store['items']),
        'revisionCount' => array_sum(array_map('count', $store['revisions'])),
        'statusCounts' => $counts,
        'updatedAt' => (string) ($store['updatedAt'] ?? ''),
        'deliveryEnabled' => false,
        'aiEnabled' => false,
    ];
}

function classops_capabilities(): array
{
    return [
        'success' => true,
        'contractVersion' => CLASSOPS_CONTRACT_VERSION,
        'timezone' => CLASSOPS_TIMEZONE,
        'types' => classops_allowed_types(),
        'statuses' => classops_allowed_statuses(),
        'mutations' => ['create', 'update', 'cancel', 'archive'],
        'features' => [
            'revisionHistory' => true,
            'optimisticConcurrency' => true,
            'idempotency' => true,
            'ownerOnly' => true,
            'delivery' => false,
            'audienceResolution' => false,
            'ai' => false,
        ],
    ];
}
