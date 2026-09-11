<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_bot_service.php';
require_once __DIR__ . '/academic_term7_bot_service.php';
require_once __DIR__ . '/classops_partial_theory_syllabus.php';

function classops_bot_ux_v3_action(string $action): bool
{
    return in_array($action, ['classopsTimelineV3', 'classopsNotificationStatusV3'], true);
}

function classops_bot_ux_v3_local_start(array $request): DateTimeImmutable
{
    $timezone = new DateTimeZone(DENT_TERM7_TIMEZONE);
    $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0, 0);
    $rawDate = trim((string) ($request['startDate'] ?? ''));
    if ($rawDate !== '') {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $rawDate) !== 1) {
            classops_domain_error('CLASSOPS_V3_DATE_INVALID', 'Timeline date is invalid.', 422);
        }
        $candidate = new DateTimeImmutable($rawDate . ' 00:00:00', $timezone);
        $delta = (int) floor(($candidate->getTimestamp() - $today->getTimestamp()) / 86400);
        if ($delta < -31 || $delta > 120) {
            classops_domain_error('CLASSOPS_V3_DATE_RANGE', 'Timeline date is outside the supported window.', 422);
        }
        return $candidate;
    }
    $offset = max(-14, min(60, (int) ($request['startOffsetDays'] ?? 0)));
    return $today->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
}

function classops_bot_ux_v3_visible_items(array $user): array
{
    $cohort = dent_user_cohort_key($user);
    $owner = classops_stage2_is_owner($user);
    $student = $owner ? '' : classops_stage2_student_number($user);
    $store = classops_read_store();
    $out = [];
    foreach (($store['items'] ?? []) as $item) {
        if (!is_array($item) || ($item['cohortKey'] ?? '') !== $cohort || ($item['status'] ?? '') === 'archived') continue;
        if ($owner) {
            $out[] = $item;
            continue;
        }
        if (classops_stage2_item_audience_for_student($item, $student) === null) continue;
        $out[] = classops_stage2_student_item_projection($item, $user);
    }
    return $out;
}

function classops_bot_ux_v3_effective_timestamp(array $item): ?int
{
    $timing = is_array($item['timing'] ?? null) ? $item['timing'] : [];
    foreach (['startsAt', 'dueAt', 'endsAt'] as $field) {
        $raw = trim((string) ($timing[$field] ?? ''));
        if ($raw === '') continue;
        $timestamp = strtotime($raw);
        if ($timestamp !== false) return $timestamp;
    }
    return null;
}

function classops_bot_ux_v3_classops_record(array $item, DateTimeZone $timezone): ?array
{
    $timestamp = classops_bot_ux_v3_effective_timestamp($item);
    if ($timestamp === null) return null;
    $local = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    $timing = is_array($item['timing'] ?? null) ? $item['timing'] : [];
    $status = (string) ($item['status'] ?? '');
    $type = (string) ($item['type'] ?? '');
    $task = is_array($item['task'] ?? null) ? $item['task'] : [];
    $taskState = (string) ($task['state'] ?? '');
    $overdue = in_array($type, ['task', 'deadline', 'requirement'], true)
        && $timestamp < time()
        && !in_array($status, ['completed', 'cancelled', 'canceled', 'archived'], true)
        && !in_array($taskState, ['completed', 'waived'], true);
    $course = is_array($item['course'] ?? null) ? (string) ($item['course']['title'] ?? '') : '';
    return [
        'source' => 'classops', 'ref' => (string) ($item['id'] ?? ''),
        'type' => (string) ($item['type'] ?? ''), 'status' => $status,
        'title' => (string) ($item['title'] ?? ''), 'description' => (string) ($item['description'] ?? ''),
        'courseTitle' => $course, 'location' => (string) ($item['location'] ?? ''),
        'importance' => (string) ($item['importance'] ?? 'normal'), 'localDate' => $local->format('Y-m-d'),
        'startsAt' => (string) ($timing['startsAt'] ?? ''), 'endsAt' => (string) ($timing['endsAt'] ?? ''),
        'dueAt' => (string) ($timing['dueAt'] ?? ''), 'timeLabel' => '',
        'sortAt' => gmdate('c', $timestamp), 'overdue' => $overdue,
    ];
}
function classops_bot_ux_v3_term7_record(
    array $event,
    DateTimeImmutable $date,
    string $kind,
    string $period,
    string $rotation,
    array $assignment
): array {
    $slug = (string) ($event['slug'] ?? 'schedule');
    $start = trim((string) ($event['start'] ?? ''));
    $end = trim((string) ($event['end'] ?? ''));
    $timezone = new DateTimeZone(DENT_TERM7_TIMEZONE);
    $sessionMode = (string) ($event['sessionMode'] ?? '');
    $startsAt = $start !== '' && $sessionMode !== 'virtual' ? new DateTimeImmutable($date->format('Y-m-d') . ' ' . $start, $timezone) : null;
    $endsAt = $end !== '' && $sessionMode !== 'virtual' ? new DateTimeImmutable($date->format('Y-m-d') . ' ' . $end, $timezone) : null;
    $selector = (string) ($event['selector'] ?? '');
    $group = $selector === 'group10' ? ($assignment['group10'] ?? null) : ($selector === 'group8' ? ($assignment['group8'] ?? null) : null);
    $sessionNumber = isset($event['sessionNumber']) && (int) $event['sessionNumber'] > 0 ? (int) $event['sessionNumber'] : null;
    $refIdentity = $slug . '|' . $period . '|' . ($sessionNumber ?? '');
    $ref = 't7_' . $date->format('Ymd') . '_' . substr(hash('sha256', $refIdentity), 0, 10);
    $sortHour = $startsAt?->format('H:i') ?? ($period === 'afternoon' ? '13:00' : ($period === 'morning' ? '08:00' : '00:00'));
    $sortAt = new DateTimeImmutable($date->format('Y-m-d') . ' ' . $sortHour, $timezone);
    return [
        'source' => 'term7', 'ref' => $ref, 'type' => $kind, 'status' => 'active',
        'title' => (string) ($event['title'] ?? ''), 'description' => '',
        'courseTitle' => (string) ($event['courseTitle'] ?? ''),
        'location' => (string) ($event['location'] ?? ''), 'importance' => 'normal',
        'localDate' => $date->format('Y-m-d'), 'startsAt' => $startsAt?->format('c') ?? '',
        'endsAt' => $endsAt?->format('c') ?? '', 'dueAt' => '',
        'timeLabel' => $sessionMode === 'virtual' ? 'مجازی' : ($period === 'morning' ? 'صبح' : ($period === 'afternoon' ? 'عصر' : '')),
        'sortAt' => $sortAt->setTimezone(new DateTimeZone('UTC'))->format('c'), 'overdue' => false,
        'rotation' => $rotation, 'rotationLabel' => dent_term7_bot_service_rotation_label($rotation),
        'sessionNumber' => $sessionNumber,
        'sessionTitle' => (string) ($event['sessionTitle'] ?? ''),
        'instructor' => (string) ($event['instructor'] ?? ''),
        'sessionMode' => $sessionMode,
        'sessionModeLabel' => (string) ($event['sessionModeLabel'] ?? ''),
        'references' => is_array($event['references'] ?? null) ? $event['references'] : [],
        'sourceDate' => (string) ($event['sourceDate'] ?? ''),
        'applicability' => [
            'selector' => $selector,
            'group' => is_int($group) ? $group : null,
            'groupLabel' => $selector === 'group10' ? 'گروه صبح' : ($selector === 'group8' ? 'گروه عصر' : ''),
        ],
    ];
}

function classops_bot_ux_v3_term7_records(array $user, DateTimeImmutable $date, ?array $term7State = null): array
{
    if (dent_user_cohort_key($user) !== DENT_TERM7_COHORT) return [];
    $student = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($student === '') return [];
    $state = $term7State ?? dent_term7_state_read();
    if (classops_stage2_is_owner($user) && !isset($state['assignments'][$student])) return [];
    $assignment = dent_term7_assignment_for_student($student, $state);
    $resolved = dent_term7_resolve_date($date, $assignment);
    $rotation = (string) ($resolved['rotation'] ?? '');
    $out = [];
    $theory = classops_partial_theory_enrich_events(
        is_array($resolved['theory'] ?? null) ? $resolved['theory'] : [],
        (string) ($resolved['date'] ?? '')
    );
    foreach ($theory as $event) {
        if (is_array($event)) $out[] = classops_bot_ux_v3_term7_record($event, $date, 'theory', 'theory', $rotation, $assignment);
    }
    foreach (($resolved['practicalMorning'] ?? []) as $event) {
        if (is_array($event)) $out[] = classops_bot_ux_v3_term7_record($event, $date, 'practical', 'morning', $rotation, $assignment);
    }
    foreach (($resolved['practicalAfternoon'] ?? []) as $event) {
        if (is_array($event)) $out[] = classops_bot_ux_v3_term7_record($event, $date, 'practical', 'afternoon', $rotation, $assignment);
    }
    return $out;
}
function classops_bot_ux_v3_timeline(array $request, array $user): array
{
    $timezone = new DateTimeZone(DENT_TERM7_TIMEZONE);
    $start = classops_bot_ux_v3_local_start($request);
    $days = max(1, min(31, (int) ($request['days'] ?? 1)));
    $buckets = [];
    for ($index = 0; $index < $days; $index++) {
        $date = $start->modify('+' . $index . ' days');
        $buckets[$date->format('Y-m-d')] = [
            'localDate' => $date->format('Y-m-d'),
            'jalaliDate' => dent_term7_jalali_key($date),
            'weekdayLabel' => dent_term7_weekday_label((int) $date->format('N')),
            'items' => [],
        ];
    }
    foreach (classops_bot_ux_v3_visible_items($user) as $item) {
        $record = classops_bot_ux_v3_classops_record($item, $timezone);
        if ($record === null || !isset($buckets[$record['localDate']])) continue;
        $buckets[$record['localDate']]['items'][] = $record;
    }
    foreach ($buckets as $dateKey => &$bucket) {
        $date = new DateTimeImmutable($dateKey . ' 00:00:00', $timezone);
        $bucket['items'] = array_merge($bucket['items'], classops_bot_ux_v3_term7_records($user, $date));
    }
    unset($bucket);
    foreach ($buckets as &$bucket) {
        usort($bucket['items'], static function (array $left, array $right): int {
            $a = strtotime((string) ($left['sortAt'] ?? '')) ?: PHP_INT_MAX;
            $b = strtotime((string) ($right['sortAt'] ?? '')) ?: PHP_INT_MAX;
            if ($a !== $b) return $a <=> $b;
            $leftSession = (int) ($left['sessionNumber'] ?? 0);
            $rightSession = (int) ($right['sessionNumber'] ?? 0);
            if ($leftSession > 0 && $rightSession > 0 && $leftSession !== $rightSession) return $leftSession <=> $rightSession;
            return strcmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });
    }
    unset($bucket);
    return [
        'success' => true,
        'role' => classops_stage2_is_owner($user) ? 'owner' : 'student',
        'timezone' => DENT_TERM7_TIMEZONE,
        'startDate' => $start->format('Y-m-d'),
        'days' => array_values($buckets),
    ];
}

function classops_bot_ux_v3_notification_status(array $request, array $owner): array
{
    $base = classops_bot_service_notification_status($request, $owner);
    $state = classops_stage2_read_state();
    $deliveries = [];
    foreach (($state['deliveryIntents'] ?? []) as $entry) {
        if (!is_array($entry) || !is_array($entry['intent'] ?? null)) continue;
        $intent = $entry['intent'];
        $message = is_array($entry['message'] ?? null) ? $entry['message'] : [];
        $deliveries[] = [
            'itemTitle' => (string) ($message['title'] ?? 'آیتم امور کلاس'),
            'scheduledAt' => (string) ($intent['occurrence']['scheduledAt'] ?? ''),
            'status' => (string) ($entry['status'] ?? 'unknown'),
            'destination' => (string) ($intent['resolvedDestinationAlias'] ?? $intent['requestedDestinationAlias'] ?? ''),
            'platform' => (string) ($intent['platform'] ?? ''),
            'attempts' => max(0, (int) ($entry['attempts'] ?? 0)),
            'updatedAt' => (string) ($entry['updatedAt'] ?? ''),
        ];
    }
    usort($deliveries, static fn(array $a, array $b): int => strcmp(
        (string) ($b['updatedAt'] ?? ''),
        (string) ($a['updatedAt'] ?? '')
    ));
    $base['deliveries'] = array_slice($deliveries, 0, 20);
    return $base;
}

function classops_bot_ux_v3_dispatch(array $request): array
{
    $action = trim((string) ($request['action'] ?? ''));
    $user = classops_bot_service_linked_user($request);
    if ($action === 'classopsTimelineV3') {
        return classops_bot_ux_v3_timeline($request, $user);
    }
    if ($action === 'classopsNotificationStatusV3') {
        if (!classops_stage2_is_owner($user)) {
            classops_domain_error('CLASSOPS_OWNER_REQUIRED', 'Owner status required.', 403);
        }
        return classops_bot_ux_v3_notification_status($request, $user);
    }
    classops_domain_error('CLASSOPS_BOT_UX_V3_ACTION_UNKNOWN', 'ClassOps bot UX v3 action is not recognized.', 404);
}
