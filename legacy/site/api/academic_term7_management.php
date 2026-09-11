<?php
declare(strict_types=1);

require_once __DIR__ . '/academic_term7.php';

const DENT_TERM7_GROUP_LEADER_SCHEMA = 1;

function dent_term7_group_leader_state_path(): string
{
    return dent_storage_path('academic/term7-1405-1406-leaders.json');
}

function dent_term7_group_leader_state_default(): array
{
    return [
        'schemaVersion' => DENT_TERM7_GROUP_LEADER_SCHEMA,
        'group10' => [],
        'group8' => [],
    ];
}

function dent_term7_group_field_meta(string $field): array
{
    if ($field === 'group10') {
        return ['minimum' => 1, 'maximum' => 10, 'label' => 'صبح'];
    }
    if ($field === 'group8') {
        return ['minimum' => 11, 'maximum' => 18, 'label' => 'عصر'];
    }
    dent_error('نوع گروه معتبر نیست.', 422, ['code' => 'TERM7_GROUP_FIELD_INVALID']);
}

function dent_term7_group_leader_state_normalize(array $raw): array
{
    $state = dent_term7_group_leader_state_default();
    foreach (['group10', 'group8'] as $field) {
        $meta = dent_term7_group_field_meta($field);
        foreach (is_array($raw[$field] ?? null) ? $raw[$field] : [] as $groupKey => $studentNumberRaw) {
            $group = (int) $groupKey;
            $studentNumber = dent_normalize_student_number((string) $studentNumberRaw);
            if ($group < $meta['minimum'] || $group > $meta['maximum'] || $studentNumber === '') {
                continue;
            }
            $state[$field][(string) $group] = $studentNumber;
        }
        ksort($state[$field], SORT_NUMERIC);
    }
    return $state;
}

function dent_term7_group_leader_state_visible(array $state, array $academicState): array
{
    $visible = dent_term7_group_leader_state_default();
    foreach (['group10', 'group8'] as $field) {
        foreach ($state[$field] as $groupKey => $studentNumber) {
            $group = (int) $groupKey;
            $assignment = dent_term7_assignment_for_student((string) $studentNumber, $academicState);
            if ((int) ($assignment[$field] ?? 0) === $group) {
                $visible[$field][(string) $group] = (string) $studentNumber;
            }
        }
    }
    return $visible;
}

/** @template T @param callable(array):T $callback @return T */
function dent_term7_group_leader_state_with_lock(callable $callback)
{
    $path = dent_term7_group_leader_state_path();
    dent_ensure_directory(dirname($path));
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        dent_error('ذخیره‌سازی وضعیت گروه‌ها موقتاً در دسترس نیست.', 503, ['code' => 'TERM7_GROUP_STATUS_STORE_UNAVAILABLE']);
    }
    try {
        $decoded = is_file($path) ? dent_read_json_file($path, []) : [];
        if (!is_array($decoded)) {
            throw new DentJsonPersistenceException('TERM7_GROUP_STATUS_SCHEMA_INVALID', 'Term 7 leader state must be an object');
        }
        $state = dent_term7_group_leader_state_normalize($decoded);
        $result = $callback($state);
        $state = dent_term7_group_leader_state_normalize($state);
        dent_write_json_file($path, $state, true);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_term7_group_leader_state_read(?array $academicState = null): array
{
    $path = dent_term7_group_leader_state_path();
    if (!is_file($path)) return dent_term7_group_leader_state_default();
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_SH)) {
        if (is_resource($handle)) fclose($handle);
        return dent_term7_group_leader_state_default();
    }
    try {
        $decoded = dent_read_json_file($path, []);
        if (!is_array($decoded)) {
            throw new DentJsonPersistenceException('TERM7_GROUP_STATUS_SCHEMA_INVALID', 'Term 7 leader state must be an object');
        }
        $state = dent_term7_group_leader_state_normalize($decoded);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
    return dent_term7_group_leader_state_visible($state, $academicState ?? dent_term7_state_read());
}

function dent_term7_require_owner(array $owner): void
{
    $role = dent_normalize_role((string) ($owner['role'] ?? 'student'), (string) ($owner['studentNumber'] ?? ''));
    if ($role !== 'owner') {
        dent_error('این عملیات فقط برای مالک مجاز است.', 403, ['code' => 'OWNER_REQUIRED']);
    }
}

function dent_term7_require_target_student(string $studentNumber): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
    if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) {
        dent_error('دانشجوی ورودی ۱۴۰۲ پیدا نشد.', 404, ['code' => 'TERM7_STUDENT_NOT_FOUND']);
    }
    return $user;
}

function dent_term7_assignment_status(string $studentNumber, string $field, array $assignment, array $leaderState): array
{
    $group = is_int($assignment[$field] ?? null) ? (int) $assignment[$field] : null;
    if ($group === null) return ['key' => 'unassigned', 'label' => 'بدون گروه'];
    $leader = (string) ($leaderState[$field][(string) $group] ?? '');
    if ($leader !== '' && hash_equals($leader, $studentNumber)) {
        return ['key' => 'leader', 'label' => 'سرگروه'];
    }
    return ['key' => 'member', 'label' => 'عضو'];
}

function dent_term7_public_assignment_for_student(string $studentNumber, ?array $academicState = null, ?array $leaderState = null): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $academicState = $academicState ?? dent_term7_state_read();
    $leaderState = $leaderState ?? dent_term7_group_leader_state_read($academicState);
    $assignment = dent_term7_assignment_for_student($studentNumber, $academicState);
    $morning = dent_term7_assignment_status($studentNumber, 'group10', $assignment, $leaderState);
    $afternoon = dent_term7_assignment_status($studentNumber, 'group8', $assignment, $leaderState);
    return [
        'contractVersion' => DENT_TERM7_CONTRACT,
        'scheduleVersion' => DENT_TERM7_SCHEDULE_VERSION,
        'studentNumber' => $studentNumber,
        'group10' => is_int($assignment['group10'] ?? null) ? (int) $assignment['group10'] : null,
        'group8' => is_int($assignment['group8'] ?? null) ? (int) $assignment['group8'] : null,
        'group10Status' => $morning['key'],
        'group10StatusLabel' => $morning['label'],
        'group8Status' => $afternoon['key'],
        'group8StatusLabel' => $afternoon['label'],
        'updatedAt' => (string) ($assignment['updatedAt'] ?? ''),
    ];
}

function dent_term7_public_group_context_for_student(
    string $studentNumber,
    ?array $academicState = null,
    ?array $leaderState = null
): array {
    $studentNumber = dent_normalize_student_number($studentNumber);
    $academicState = $academicState ?? dent_term7_state_read();
    $leaderState = $leaderState ?? dent_term7_group_leader_state_read($academicState);
    $self = dent_term7_public_assignment_for_student($studentNumber, $academicState, $leaderState);
    $store = dent_load_user_store();
    $users = is_array($store['users'] ?? null) ? $store['users'] : [];

    $nameByStudent = [];
    foreach ($users as $key => $user) {
        if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) continue;
        $number = dent_normalize_student_number((string) ($user['studentNumber'] ?? $key));
        $name = trim((string) ($user['name'] ?? ''));
        if ($number !== '' && $name !== '') $nameByStudent[$number] = $name;
    }

    $out = [];
    foreach (['group10' => 'morning', 'group8' => 'afternoon'] as $field => $key) {
        $group = is_int($self[$field] ?? null) ? (int) $self[$field] : null;
        $members = [];
        $leaderNumber = '';
        if ($group !== null) {
            $leaderNumber = dent_normalize_student_number((string) ($leaderState[$field][(string) $group] ?? ''));
            foreach (($academicState['assignments'] ?? []) as $number => $assignment) {
                if (!is_array($assignment) || !is_int($assignment[$field] ?? null) || (int) $assignment[$field] !== $group) continue;
                $number = dent_normalize_student_number((string) ($assignment['studentNumber'] ?? $number));
                $name = (string) ($nameByStudent[$number] ?? '');
                if ($name !== '') $members[] = $name;
            }
            sort($members, SORT_NATURAL | SORT_FLAG_CASE);
        }
        $out[$key] = [
            'group' => $group,
            'status' => (string) ($self[$field . 'Status'] ?? 'unassigned'),
            'statusLabel' => (string) ($self[$field . 'StatusLabel'] ?? 'بدون گروه'),
            'leaderName' => (string) ($nameByStudent[$leaderNumber] ?? ''),
            'memberCount' => count($members),
            'members' => array_values($members),
        ];
    }
    return $out;
}

function dent_term7_owner_roster(array $owner): array
{
    dent_term7_require_owner($owner);
    $academicState = dent_term7_state_read();
    $leaderState = dent_term7_group_leader_state_read($academicState);
    $store = dent_load_user_store();
    $rows = [];
    foreach (is_array($store['users'] ?? null) ? $store['users'] : [] as $studentNumber => $user) {
        if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) continue;
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
        if ($studentNumber === '') continue;
        $rows[] = [
            'studentNumber' => $studentNumber,
            'name' => (string) ($user['name'] ?? ''),
            'assignment' => dent_term7_public_assignment_for_student($studentNumber, $academicState, $leaderState),
        ];
    }
    usort($rows, static fn(array $a, array $b): int => strcmp(
        dent_term7_normalize_person_name((string) ($a['name'] ?? '')),
        dent_term7_normalize_person_name((string) ($b['name'] ?? ''))
    ));
    return $rows;
}

function dent_term7_owner_update_assignment(array $owner, string $studentNumber, string $field, ?int $group): array
{
    dent_term7_require_owner($owner);
    $user = dent_term7_require_target_student($studentNumber);
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
    $meta = dent_term7_group_field_meta($field);
    if ($group !== null && ($group < $meta['minimum'] || $group > $meta['maximum'])) {
        dent_error('شماره گروه معتبر نیست.', 422, ['code' => 'TERM7_GROUP_RANGE_INVALID']);
    }

    $before = dent_term7_assignment_for_student($studentNumber);
    $previousGroup = is_int($before[$field] ?? null) ? (int) $before[$field] : null;
    if ($previousGroup !== null && $previousGroup !== $group) {
        dent_term7_group_leader_state_with_lock(static function (array &$state) use ($studentNumber, $field, $previousGroup): array {
            $key = (string) $previousGroup;
            if ((string) ($state[$field][$key] ?? '') === $studentNumber) {
                unset($state[$field][$key]);
            }
            return [];
        });
    }

    dent_term7_state_with_lock(static function (array &$state) use ($studentNumber, $field, $group): array {
        $current = dent_term7_assignment_for_student($studentNumber, $state);
        $current[$field] = $group;
        $current['updatedAt'] = dent_iso_now();
        $state['assignments'][$studentNumber] = $current;
        return [];
    });
    return dent_term7_public_assignment_for_student($studentNumber);
}

function dent_term7_owner_set_leader(array $owner, string $studentNumber, string $field, bool $leader): array
{
    dent_term7_require_owner($owner);
    $user = dent_term7_require_target_student($studentNumber);
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? $studentNumber));
    dent_term7_group_field_meta($field);
    $academicState = dent_term7_state_read();
    $assignment = dent_term7_assignment_for_student($studentNumber, $academicState);
    $group = is_int($assignment[$field] ?? null) ? (int) $assignment[$field] : null;
    if ($group === null) {
        dent_error('برای تعیین سرگروه ابتدا گروه دانشجو را مشخص کن.', 409, ['code' => 'TERM7_GROUP_REQUIRED']);
    }
    dent_term7_group_leader_state_with_lock(static function (array &$state) use ($studentNumber, $field, $group, $leader): array {
        $key = (string) $group;
        if ($leader) {
            $state[$field][$key] = $studentNumber;
        } elseif ((string) ($state[$field][$key] ?? '') === $studentNumber) {
            unset($state[$field][$key]);
        }
        return [];
    });
    return dent_term7_public_assignment_for_student($studentNumber);
}
