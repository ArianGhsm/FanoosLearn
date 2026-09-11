<?php
declare(strict_types=1);

require_once __DIR__ . '/academic_term7_management.php';

function dent_term7_bot_service_action(string $action): bool
{
    return in_array($action, [
        'academicTerm7Self',
        'academicTerm7Roster',
        'academicTerm7AssignmentUpdate',
        'academicTerm7LeaderUpdate',
    ], true);
}

function dent_term7_bot_service_linked_user(array $payload): array
{
    [$platform, $platformUserId] = dent_bot_identity(
        (string) ($payload['platform'] ?? ''),
        (string) ($payload['platformUserId'] ?? '')
    );
    $user = dent_bot_linked_user($platform, $platformUserId);
    if (!is_array($user)) {
        dent_error('اتصال حساب سایت برای این عملیات لازم است.', 403, ['code' => 'TERM7_BOT_LINK_REQUIRED']);
    }
    return $user;
}

function dent_term7_bot_service_has_assignment(string $studentNumber, ?array $state = null): bool
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $state = $state ?? dent_term7_state_read();
    return $studentNumber !== '' && is_array($state['assignments'][$studentNumber] ?? null);
}

function dent_term7_bot_service_roster(array $owner): array
{
    $state = dent_term7_state_read();
    return array_values(array_filter(
        dent_term7_owner_roster($owner),
        static fn(array $row): bool => dent_term7_bot_service_has_assignment(
            (string) ($row['studentNumber'] ?? ''),
            $state
        )
    ));
}

function dent_term7_bot_service_event_projection(array $event, string $period = ''): array
{
    return [
        'slug' => (string) ($event['slug'] ?? ''),
        'title' => (string) ($event['title'] ?? ''),
        'eventType' => (string) ($event['eventType'] ?? ''),
        'period' => $period !== '' ? $period : (string) ($event['period'] ?? ''),
        'start' => (string) ($event['start'] ?? ''),
        'end' => (string) ($event['end'] ?? ''),
        'location' => (string) ($event['location'] ?? ''),
    ];
}

function dent_term7_bot_service_rotation_label(string $rotation): string
{
    return match ($rotation) {
        'A' => 'روتیشن اول',
        'B' => 'روتیشن دوم',
        'makeup' => 'جبرانی',
        default => '',
    };
}

function dent_term7_bot_service_schedule_context(array $assignment, ?DateTimeImmutable $now = null): array
{
    $timezone = new DateTimeZone(DENT_TERM7_TIMEZONE);
    $local = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    $resolved = dent_term7_resolve_date($local, $assignment);
    $schedule = dent_term7_schedule();
    $rotation = (string) ($resolved['rotation'] ?? '');
    $period = null;
    if (in_array($rotation, ['A', 'B'], true) && is_array($schedule['rotations'][$rotation] ?? null)) {
        $period = [
            'from' => (string) ($schedule['rotations'][$rotation]['from'] ?? ''),
            'through' => (string) ($schedule['rotations'][$rotation]['through'] ?? ''),
        ];
    }
    $current = [];
    foreach (($resolved['practicalMorning'] ?? []) as $event) {
        if (is_array($event)) $current[] = dent_term7_bot_service_event_projection($event, 'morning');
    }
    foreach (($resolved['practicalAfternoon'] ?? []) as $event) {
        if (is_array($event)) $current[] = dent_term7_bot_service_event_projection($event, 'afternoon');
    }
    $theory = [];
    foreach (($resolved['theory'] ?? []) as $event) {
        if (is_array($event)) $theory[] = dent_term7_bot_service_event_projection($event, 'theory');
    }
    $next = null;
    for ($offset = 1; $offset <= 90; $offset++) {
        $candidateDate = $local->setTime(0, 0)->modify('+' . $offset . ' days');
        $candidate = dent_term7_resolve_date($candidateDate, $assignment);
        $events = [];
        foreach (($candidate['practicalMorning'] ?? []) as $event) {
            if (is_array($event)) $events[] = dent_term7_bot_service_event_projection($event, 'morning');
        }
        foreach (($candidate['practicalAfternoon'] ?? []) as $event) {
            if (is_array($event)) $events[] = dent_term7_bot_service_event_projection($event, 'afternoon');
        }
        if ($events !== []) {
            $next = [
                'date' => (string) ($candidate['date'] ?? ''),
                'weekdayLabel' => (string) ($candidate['weekdayLabel'] ?? ''),
                'rotation' => (string) ($candidate['rotation'] ?? ''),
                'rotationLabel' => dent_term7_bot_service_rotation_label((string) ($candidate['rotation'] ?? '')),
                'events' => $events,
            ];
            break;
        }
        if (strcmp((string) ($candidate['date'] ?? ''), (string) ($schedule['activeThrough'] ?? '')) > 0) break;
    }
    return [
        'date' => (string) ($resolved['date'] ?? ''),
        'weekdayLabel' => (string) ($resolved['weekdayLabel'] ?? ''),
        'inSchedule' => !empty($resolved['inTerm']),
        'rotation' => $rotation,
        'rotationLabel' => dent_term7_bot_service_rotation_label($rotation),
        'rotationPeriod' => $period,
        'practicalClosed' => !empty($resolved['practicalClosed']),
        'currentPractical' => $current,
        'currentTheory' => $theory,
        'nextPractical' => $next,
    ];
}

function dent_term7_bot_service_dispatch(array $request): array
{
    $action = trim((string) ($request['action'] ?? ''));
    $user = dent_term7_bot_service_linked_user($request);

    if ($action === 'academicTerm7Self') {
        $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
        if (dent_user_cohort_key($user) !== DENT_TERM7_COHORT) {
            return ['success' => true, 'eligible' => false, 'assignment' => null];
        }
        $academicState = dent_term7_state_read();
        $leaderState = dent_term7_group_leader_state_read($academicState);
        $assignment = dent_term7_public_assignment_for_student($studentNumber, $academicState, $leaderState);
        return [
            'success' => true,
            'eligible' => true,
            'assignment' => $assignment,
            'academicTerm' => dent_term7_academic_context(),
            'groups' => dent_term7_public_group_context_for_student($studentNumber, $academicState, $leaderState),
            'scheduleContext' => dent_term7_bot_service_schedule_context($assignment),
        ];
    }

    dent_term7_require_owner($user);

    if ($action === 'academicTerm7Roster') {
        return ['success' => true, 'roster' => dent_term7_bot_service_roster($user)];
    }

    $studentNumber = (string) ($request['studentNumber'] ?? '');
    $field = trim((string) ($request['field'] ?? ''));

    if ($action === 'academicTerm7AssignmentUpdate') {
        $rawGroup = $request['group'] ?? null;
        $group = ($rawGroup === null || $rawGroup === '') ? null : (int) $rawGroup;
        return [
            'success' => true,
            'assignment' => dent_term7_owner_update_assignment($user, $studentNumber, $field, $group),
        ];
    }

    if ($action === 'academicTerm7LeaderUpdate') {
        $leader = filter_var($request['leader'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($leader === null) {
            dent_error('وضعیت سرگروهی معتبر نیست.', 422, ['code' => 'TERM7_LEADER_VALUE_INVALID']);
        }
        return [
            'success' => true,
            'assignment' => dent_term7_owner_set_leader($user, $studentNumber, $field, $leader),
        ];
    }

    dent_error('عملیات گروه‌بندی ترم ۷ معتبر نیست.', 404, ['code' => 'TERM7_BOT_ACTION_UNKNOWN']);
}
