<?php
declare(strict_types=1);

/**
 * Canonical Term 7 academic timetable and food-reminder state.
 *
 * Group membership is deliberately absent from code. It is imported later,
 * keyed by canonical studentNumber, into deploy-safe private storage.
 */

const DENT_TERM7_CONTRACT = 'academic-term7-v1';
const DENT_TERM7_SCHEDULE_VERSION = '1405-1406.1';
const DENT_TERM7_COHORT = 'dentistry-1402';
const DENT_TERM7_TIMEZONE = 'Asia/Tehran';
const DENT_TERM7_FOOD_URL = 'http://foodstu.tums.ac.ir';

// Academic-term context is deliberately separate from the teaching timetable.
// The schedule may start later or end earlier than the university term itself.
const DENT_TERM7_ACADEMIC_FROM = '1405/06/18';
const DENT_TERM7_ACADEMIC_THROUGH = '1405/11/23';

function dent_term7_academic_context(?DateTimeImmutable $date = null): array
{
    $timezone = new DateTimeZone(DENT_TERM7_TIMEZONE);
    $local = ($date ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    $jalali = dent_term7_jalali_key($local);
    $active = strcmp($jalali, DENT_TERM7_ACADEMIC_FROM) >= 0
        && strcmp($jalali, DENT_TERM7_ACADEMIC_THROUGH) <= 0;
    $state = $active
        ? 'active'
        : (strcmp($jalali, DENT_TERM7_ACADEMIC_FROM) < 0 ? 'before_window' : 'after_window');
    return [
        'currentJalaliDate' => $jalali,
        'term' => $active ? 7 : null,
        'termLabel' => $active ? 'ترم ۷' : '',
        'state' => $state,
        'activeFrom' => DENT_TERM7_ACADEMIC_FROM,
        'activeThrough' => DENT_TERM7_ACADEMIC_THROUGH,
    ];
}

function dent_term7_state_path(): string
{
    return dent_storage_path('academic/term7-1405-1406.json');
}

function dent_term7_state_default(): array
{
    return [
        'schemaVersion' => 1,
        'scheduleVersion' => DENT_TERM7_SCHEDULE_VERSION,
        'assignments' => [],
        'foodConfirmations' => [],
        'schedulerSlots' => [],
    ];
}

function dent_term7_normalize_assignment(string $studentNumber, array $assignment): ?array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    if ($studentNumber === '') {
        return null;
    }
    $group10 = isset($assignment['group10']) && $assignment['group10'] !== ''
        ? (int) $assignment['group10']
        : null;
    $group8 = isset($assignment['group8']) && $assignment['group8'] !== ''
        ? (int) $assignment['group8']
        : null;
    if ($group10 !== null && ($group10 < 1 || $group10 > 10)) {
        $group10 = null;
    }
    if ($group8 !== null && ($group8 < 11 || $group8 > 18)) {
        $group8 = null;
    }
    return [
        'studentNumber' => $studentNumber,
        'cohortKey' => DENT_TERM7_COHORT,
        'term' => 7,
        'group10' => $group10,
        'group8' => $group8,
        'updatedAt' => trim((string) ($assignment['updatedAt'] ?? '')),
    ];
}

function dent_term7_normalize_state(array $raw): array
{
    $state = dent_term7_state_default();
    foreach (is_array($raw['assignments'] ?? null) ? $raw['assignments'] : [] as $key => $assignment) {
        if (!is_array($assignment)) {
            continue;
        }
        $clean = dent_term7_normalize_assignment((string) ($assignment['studentNumber'] ?? $key), $assignment);
        if ($clean !== null) {
            $state['assignments'][$clean['studentNumber']] = $clean;
        }
    }
    foreach (is_array($raw['foodConfirmations'] ?? null) ? $raw['foodConfirmations'] : [] as $key => $confirmation) {
        if (!is_array($confirmation)) {
            continue;
        }
        $studentNumber = dent_normalize_student_number((string) ($confirmation['studentNumber'] ?? ''));
        $weekKey = trim((string) ($confirmation['weekKey'] ?? ''));
        if ($studentNumber === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekKey) !== 1) {
            continue;
        }
        $state['foodConfirmations'][(string) $key] = [
            'studentNumber' => $studentNumber,
            'cohortKey' => DENT_TERM7_COHORT,
            'weekKey' => $weekKey,
            'confirmed' => !empty($confirmation['confirmed']),
            'confirmedAt' => trim((string) ($confirmation['confirmedAt'] ?? '')),
            'confirmedVia' => in_array((string) ($confirmation['confirmedVia'] ?? ''), ['telegram', 'bale'], true)
                ? (string) $confirmation['confirmedVia']
                : '',
            'updatedAt' => trim((string) ($confirmation['updatedAt'] ?? '')),
        ];
    }
    foreach (is_array($raw['schedulerSlots'] ?? null) ? $raw['schedulerSlots'] : [] as $key => $slot) {
        if (!is_array($slot) || preg_match('/^[A-Za-z0-9:._-]{8,120}$/', (string) $key) !== 1) {
            continue;
        }
        $state['schedulerSlots'][(string) $key] = [
            'status' => in_array((string) ($slot['status'] ?? ''), ['leased', 'completed'], true) ? (string) $slot['status'] : 'leased',
            'leaseUntil' => max(0, (int) ($slot['leaseUntil'] ?? 0)),
            'attempts' => max(0, (int) ($slot['attempts'] ?? 0)),
            'startedAt' => trim((string) ($slot['startedAt'] ?? '')),
            'completedAt' => trim((string) ($slot['completedAt'] ?? '')),
        ];
    }
    ksort($state['assignments'], SORT_STRING);
    ksort($state['foodConfirmations'], SORT_STRING);
    ksort($state['schedulerSlots'], SORT_STRING);
    return $state;
}

/** @template T @param callable(array):T $callback @return T */
function dent_term7_state_with_lock(callable $callback)
{
    $path = dent_term7_state_path();
    dent_ensure_directory(dirname($path));
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        dent_error('ذخیره‌سازی برنامه ترم موقتاً در دسترس نیست.', 503, ['code' => 'ACADEMIC_STORE_UNAVAILABLE']);
    }
    try {
        $decoded = is_file($path) ? dent_read_json_file($path, []) : [];
        if (!is_array($decoded)) {
            throw new DentJsonPersistenceException('ACADEMIC_STORE_SCHEMA_INVALID', 'Academic state must be an object');
        }
        $state = dent_term7_normalize_state($decoded);
        $result = $callback($state);
        $state = dent_term7_normalize_state($state);
        dent_write_json_file($path, $state, true);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_term7_state_read(): array
{
    $path = dent_term7_state_path();
    if (!is_file($path)) {
        return dent_term7_state_default();
    }
    $handle = fopen($path . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_SH)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return dent_term7_state_default();
    }
    try {
        $decoded = dent_read_json_file($path, []);
        if (!is_array($decoded)) {
            throw new DentJsonPersistenceException('ACADEMIC_STORE_SCHEMA_INVALID', 'Academic state must be an object');
        }
        return dent_term7_normalize_state($decoded);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function dent_term7_event(string $slug, string $title, string $period, string $selector, array $groups, string $location = ''): array
{
    return [
        'slug' => $slug,
        'title' => $title,
        'eventType' => 'practical',
        'source' => 'official-practical-schedule',
        'period' => $period,
        'selector' => $selector,
        'groups' => array_values(array_map('intval', $groups)),
        'location' => $location,
    ];
}

function dent_term7_schedule(): array
{
    static $schedule = null;
    if (is_array($schedule)) {
        return $schedule;
    }
    $g1to5 = range(1, 5);
    $g6to10 = range(6, 10);
    $endoLocation = 'پری‌کلینیک منفی ۲';
    $theoryLocation = 'آمفی‌تئاتر ۹۰';
    $schedule = [
        'contractVersion' => DENT_TERM7_CONTRACT,
        'scheduleVersion' => DENT_TERM7_SCHEDULE_VERSION,
        'cohortKey' => DENT_TERM7_COHORT,
        'term' => 7,
        'timezone' => DENT_TERM7_TIMEZONE,
        'activeFrom' => '1405/06/28',
        'activeThrough' => '1405/10/20',
        'foodUrl' => DENT_TERM7_FOOD_URL,
        'theory' => [
            6 => [
                ['slug' => 'periodontology-theory-1', 'title' => 'پریو نظری ۱', 'start' => '07:30', 'end' => '08:30', 'location' => $theoryLocation],
                ['slug' => 'research-methods-2-theory', 'title' => 'روش تحقیق ۲', 'start' => '13:00', 'end' => '15:00', 'location' => $theoryLocation],
            ],
            7 => [
                ['slug' => 'diagnostic-dentistry-3-sun', 'title' => 'دندانپزشکی تشخیصی ۳', 'start' => '07:30', 'end' => '08:30', 'location' => $theoryLocation],
            ],
            1 => [
                ['slug' => 'ent', 'title' => 'گوش و حلق و بینی', 'start' => '07:30', 'end' => '08:30', 'location' => $theoryLocation],
                ['slug' => 'orthodontics-theory-1', 'title' => 'ارتودنسی نظری ۱', 'start' => '12:30', 'end' => '13:30', 'location' => $theoryLocation],
                ['slug' => 'diagnostic-dentistry-3-mon', 'title' => 'دندانپزشکی تشخیصی ۳', 'start' => '13:45', 'end' => '14:45', 'location' => $theoryLocation],
            ],
            2 => [
                ['slug' => 'oral-health-theory-2', 'title' => 'سلامت دهان نظری ۲', 'start' => '07:30', 'end' => '08:30', 'location' => $theoryLocation],
            ],
            3 => [
                ['slug' => 'partial-basics-theory', 'title' => 'مبانی پارسیل نظری', 'start' => '07:30', 'end' => '08:30', 'location' => $theoryLocation],
                ['slug' => 'research-methods-2-theory-wed', 'title' => 'روش تحقیق ۲', 'start' => '13:00', 'end' => '15:00', 'location' => $theoryLocation],
            ],
            4 => [
                // Explicit owner correction takes precedence over the older PDF cell.
                ['slug' => 'endodontics-theory-1', 'title' => 'اندو نظری ۱', 'start' => '08:30', 'end' => '10:30', 'location' => $theoryLocation],
            ],
        ],
        'rotations' => [
            'A' => [
                'from' => '1405/06/28', 'through' => '1405/08/20',
                'days' => [
                    6 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [8, 10]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [6, 7]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [9]),
                        dent_term7_event('oral-health-practical-2', 'سلامت دهان عملی ۲', 'morning', 'group10', $g1to5),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [15]),
                        dent_term7_event('research-methods-2-practical', 'روش تحقیق ۲', 'afternoon', 'group10', $g1to5),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [16]),
                    ],
                    7 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [6, 7]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [8, 9]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [10]),
                        dent_term7_event('pathology-practical-1', 'آسیب‌شناسی عملی ۱', 'morning', 'group10', $g1to5),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [16]),
                        dent_term7_event('endodontics-basics-2', 'مبانی اندو ۲', 'afternoon', 'group10', $g1to5, $endoLocation),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [15]),
                    ],
                    1 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [8, 9]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [6, 10]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [7]),
                        dent_term7_event('oral-health-practical-2', 'سلامت دهان عملی ۲', 'morning', 'group10', $g1to5),
                    ],
                    2 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [6, 10]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [7, 9]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [8]),
                        dent_term7_event('pathology-practical-1', 'آسیب‌شناسی عملی ۱', 'morning', 'group10', $g1to5),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [17]),
                        dent_term7_event('endodontics-basics-2', 'مبانی اندو ۲', 'afternoon', 'group10', $g1to5, $endoLocation),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [18]),
                    ],
                    3 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [7, 9]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [8, 10]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [6]),
                        dent_term7_event('oral-health-practical-2', 'سلامت دهان عملی ۲', 'morning', 'group10', $g1to5),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [18]),
                        dent_term7_event('research-methods-2-practical', 'روش تحقیق ۲', 'afternoon', 'group10', $g1to5),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [17]),
                    ],
                ],
            ],
            'B' => [
                'from' => '1405/08/23', 'through' => '1405/10/16',
                'days' => [
                    6 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [1, 3]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [2, 5]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [4]),
                        dent_term7_event('oral-health-practical-2', 'سلامت دهان عملی ۲', 'morning', 'group10', $g6to10),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [11]),
                        dent_term7_event('research-methods-2-practical', 'روش تحقیق ۲', 'afternoon', 'group10', $g6to10),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [12]),
                    ],
                    7 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [2, 5]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [1, 4]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [3]),
                        dent_term7_event('pathology-practical-1', 'آسیب‌شناسی عملی ۱', 'morning', 'group10', $g6to10),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [12]),
                        dent_term7_event('endodontics-basics-2', 'مبانی اندو ۲', 'afternoon', 'group10', $g6to10, $endoLocation),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [11]),
                    ],
                    1 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [1, 4]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [2, 3]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [5]),
                        dent_term7_event('oral-health-practical-2', 'سلامت دهان عملی ۲', 'morning', 'group10', $g6to10),
                    ],
                    2 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [2, 3]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [4, 5]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [1]),
                        dent_term7_event('pathology-practical-1', 'آسیب‌شناسی عملی ۱', 'morning', 'group10', $g6to10),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [13]),
                        dent_term7_event('endodontics-basics-2', 'مبانی اندو ۲', 'afternoon', 'group10', $g6to10, $endoLocation),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [14]),
                    ],
                    3 => [
                        dent_term7_event('oral-disease-practical-1', 'بیماری‌های دهان عملی ۱', 'morning', 'group10', [4, 5]),
                        dent_term7_event('partial-practical-1', 'پروتز پارسیل عملی ۱', 'morning', 'group10', [1, 3]),
                        dent_term7_event('restorative-practical-2', 'ترمیمی عملی ۲', 'morning', 'group10', [2]),
                        dent_term7_event('oral-health-practical-2', 'سلامت دهان عملی ۲', 'morning', 'group10', $g6to10),
                        dent_term7_event('restorative-practical-2-pm', 'ترمیمی عملی ۲', 'afternoon', 'group8', [14]),
                        dent_term7_event('research-methods-2-practical', 'روش تحقیق ۲', 'afternoon', 'group10', $g6to10),
                        dent_term7_event('surgery-practical-2', 'جراحی عملی ۲', 'afternoon', 'group8', [13]),
                    ],
                ],
            ],
        ],
        'practicalClosures' => ['1405/10/02', '1405/10/16'],
        'makeupDays' => [
            '1405/10/19' => 3,
            '1405/10/20' => 3,
        ],
        // Prepared for later owner-provided requirements; intentionally empty now.
        'prepChecklists' => [],
    ];
    return $schedule;
}

function dent_term7_weekday_label(int $weekday): string
{
    return [1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه', 6 => 'شنبه', 7 => 'یکشنبه'][$weekday] ?? '';
}

function dent_term7_jalali_key(DateTimeImmutable $date): string
{
    $local = $date->setTimezone(new DateTimeZone(DENT_TERM7_TIMEZONE));
    [$year, $month, $day] = notifications_gregorian_to_jalali((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));
    return sprintf('%04d/%02d/%02d', $year, $month, $day);
}

function dent_term7_assignment_for_student(string $studentNumber, ?array $state = null): array
{
    $studentNumber = dent_normalize_student_number($studentNumber);
    $state = $state ?? dent_term7_state_read();
    return is_array($state['assignments'][$studentNumber] ?? null)
        ? $state['assignments'][$studentNumber]
        : ['studentNumber' => $studentNumber, 'cohortKey' => DENT_TERM7_COHORT, 'term' => 7, 'group10' => null, 'group8' => null];
}

function dent_term7_event_matches(array $event, array $assignment): bool
{
    $selector = (string) ($event['selector'] ?? '');
    $group = $selector === 'group10' ? ($assignment['group10'] ?? null) : ($selector === 'group8' ? ($assignment['group8'] ?? null) : null);
    return is_int($group) && in_array($group, is_array($event['groups'] ?? null) ? $event['groups'] : [], true);
}

function dent_term7_theory_events(array $events): array
{
    return array_map(static function (array $event): array {
        $event['eventType'] = 'theory';
        $event['source'] = 'official-theory-schedule';
        return $event;
    }, $events);
}

function dent_term7_resolve_jalali(string $jalaliDate, int $weekday, array $assignment = []): array
{
    $schedule = dent_term7_schedule();
    $inTerm = strcmp($jalaliDate, (string) $schedule['activeFrom']) >= 0
        && strcmp($jalaliDate, (string) $schedule['activeThrough']) <= 0;
    $theory = $inTerm ? dent_term7_theory_events(is_array($schedule['theory'][$weekday] ?? null) ? $schedule['theory'][$weekday] : []) : [];
    $rotation = '';
    $practicalSource = [];
    $closed = in_array($jalaliDate, $schedule['practicalClosures'], true);
    if (!$closed && isset($schedule['makeupDays'][$jalaliDate])) {
        $rotation = 'makeup';
        $sourceWeekday = (int) $schedule['makeupDays'][$jalaliDate];
        $practicalSource = $schedule['rotations']['B']['days'][$sourceWeekday] ?? [];
    } elseif (!$closed) {
        foreach (['A', 'B'] as $candidate) {
            $config = $schedule['rotations'][$candidate];
            if (strcmp($jalaliDate, (string) $config['from']) >= 0 && strcmp($jalaliDate, (string) $config['through']) <= 0) {
                $rotation = $candidate;
                $practicalSource = is_array($config['days'][$weekday] ?? null) ? $config['days'][$weekday] : [];
                break;
            }
        }
    }
    $morning = [];
    $afternoon = [];
    foreach ($practicalSource as $event) {
        if (!is_array($event) || !dent_term7_event_matches($event, $assignment)) {
            continue;
        }
        if ((string) ($event['period'] ?? '') === 'afternoon') {
            $afternoon[] = $event;
        } else {
            $morning[] = $event;
        }
    }
    return [
        'date' => $jalaliDate,
        'weekday' => $weekday,
        'weekdayLabel' => dent_term7_weekday_label($weekday),
        'inTerm' => $inTerm,
        'rotation' => $rotation,
        'practicalClosed' => $closed,
        'theory' => $theory,
        'practicalMorning' => $morning,
        'practicalAfternoon' => $afternoon,
        'missingGroup10' => !isset($assignment['group10']) || !is_int($assignment['group10']),
        'missingGroup8' => !isset($assignment['group8']) || !is_int($assignment['group8']),
    ];
}

function dent_term7_resolve_date(DateTimeImmutable $date, array $assignment = []): array
{
    $local = $date->setTimezone(new DateTimeZone(DENT_TERM7_TIMEZONE));
    return dent_term7_resolve_jalali(dent_term7_jalali_key($local), (int) $local->format('N'), $assignment);
}

function dent_term7_summary_body(array $resolved): string
{
    $lines = [];
    $appendEvents = static function (array &$target, array $events, bool $showTime): void {
        foreach ($events as $event) {
            $target[] = '• ' . (string) ($event['title'] ?? '');
            if ($showTime && (string) ($event['start'] ?? '') !== '') {
                $target[] = '  ⏰ ' . (string) $event['start'] . ' تا ' . (string) ($event['end'] ?? '');
            }
            if ((string) ($event['location'] ?? '') !== '') {
                $target[] = '  📍 ' . (string) $event['location'];
            }
        }
    };
    $lines[] = '📚 کلاس‌های نظری';
    if (($resolved['theory'] ?? []) === []) {
        $lines[] = '• کلاس نظری ثبت‌شده‌ای ندارد.';
    } else {
        $appendEvents($lines, $resolved['theory'], true);
    }
    $lines[] = '';
    $lines[] = '🦷 کارآموزی صبح';
    if (($resolved['practicalMorning'] ?? []) === []) {
        $lines[] = '• برنامه‌ای برای گروه شما ثبت نشده است.';
    } else {
        $appendEvents($lines, $resolved['practicalMorning'], false);
    }
    $lines[] = '';
    $lines[] = '🌆 کارآموزی عصر';
    if (($resolved['practicalAfternoon'] ?? []) === []) {
        $lines[] = '• برنامه‌ای برای گروه شما ثبت نشده است.';
    } else {
        $appendEvents($lines, $resolved['practicalAfternoon'], false);
    }
    if (!empty($resolved['missingGroup10']) || !empty($resolved['missingGroup8'])) {
        $lines[] = '';
        $lines[] = 'ℹ️ گروه کارآموزی شما هنوز به‌طور کامل در سامانه ثبت نشده است؛ برنامه عملی حدس زده نمی‌شود.';
    }
    if (($resolved['theory'] ?? []) === [] && ($resolved['practicalMorning'] ?? []) === [] && ($resolved['practicalAfternoon'] ?? []) === []) {
        $lines[] = '';
        $lines[] = 'برای فردا برنامه ثبت‌شده‌ای ندارید.';
    }
    $lines[] = '';
    $lines[] = '✅ برای فردا';
    $lines[] = '• مورد تکمیلی ثبت نشده است.';
    return implode("\n", $lines);
}

function dent_term7_user_is_eligible(array $user): bool
{
    return dent_user_cohort_key($user) === DENT_TERM7_COHORT
        && dent_normalize_student_number((string) ($user['studentNumber'] ?? '')) !== '';
}

function dent_term7_jalali_in_active_window(string $jalaliDate): bool
{
    $schedule = dent_term7_schedule();
    return strcmp($jalaliDate, (string) $schedule['activeFrom']) >= 0
        && strcmp($jalaliDate, (string) $schedule['activeThrough']) <= 0;
}

function dent_term7_linked_eligible_users(): array
{
    if (!function_exists('dent_bot_store_read') || !function_exists('dent_bot_link_auth_complete')) {
        return [];
    }
    $result = dent_bot_store_read(static function (array $store): array {
        $users = [];
        foreach (is_array($store['links'] ?? null) ? $store['links'] : [] as $link) {
            if (!is_array($link) || !dent_bot_link_auth_complete($link)) {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            if (is_array($user) && dent_term7_user_is_eligible($user)) {
                $users[$studentNumber] = $user;
            }
        }
        return ['users' => $users];
    });
    return is_array($result['users'] ?? null) ? $result['users'] : [];
}

function dent_term7_food_week_key(DateTimeImmutable $local): string
{
    $weekday = (int) $local->format('N');
    $tuesday = $weekday === 3 ? $local->modify('-1 day') : $local;
    return $tuesday->format('Y-m-d');
}

function dent_term7_food_confirmation_key(string $studentNumber, string $weekKey): string
{
    return hash('sha256', dent_normalize_student_number($studentNumber) . ':' . $weekKey);
}

function dent_term7_food_is_confirmed(string $studentNumber, string $weekKey, ?array $state = null): bool
{
    $state = $state ?? dent_term7_state_read();
    $key = dent_term7_food_confirmation_key($studentNumber, $weekKey);
    return !empty($state['foodConfirmations'][$key]['confirmed']);
}

function dent_term7_confirm_food(array $user, string $weekKey, string $platform): array
{
    if (!dent_term7_user_is_eligible($user)) {
        dent_error('این یادآوری برای حساب شما فعال نیست.', 403, ['code' => 'ACADEMIC_COHORT_REQUIRED']);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekKey) !== 1 || !in_array($platform, ['telegram', 'bale'], true)) {
        dent_error('چرخه رزرو غذا معتبر نیست.', 422, ['code' => 'INVALID_FOOD_CYCLE']);
    }
    $studentNumber = dent_normalize_student_number((string) $user['studentNumber']);
    return dent_term7_state_with_lock(static function (array &$state) use ($studentNumber, $weekKey, $platform): array {
        $key = dent_term7_food_confirmation_key($studentNumber, $weekKey);
        $already = !empty($state['foodConfirmations'][$key]['confirmed']);
        if (!$already) {
            $now = dent_iso_now();
            $state['foodConfirmations'][$key] = [
                'studentNumber' => $studentNumber,
                'cohortKey' => DENT_TERM7_COHORT,
                'weekKey' => $weekKey,
                'confirmed' => true,
                'confirmedAt' => $now,
                'confirmedVia' => $platform,
                'updatedAt' => $now,
            ];
        }
        return ['confirmed' => true, 'alreadyConfirmed' => $already, 'weekKey' => $weekKey];
    });
}

function dent_term7_food_action_ref(string $studentNumber, string $weekKey): string
{
    return substr(hash_hmac('sha256', 'food:' . $studentNumber . ':' . $weekKey, dent_auth_secret_key()), 0, 18);
}

function dent_term7_scheduler_claim_slot(string $slotKey, int $now): bool
{
    return dent_term7_state_with_lock(static function (array &$state) use ($slotKey, $now): bool {
        $existing = is_array($state['schedulerSlots'][$slotKey] ?? null) ? $state['schedulerSlots'][$slotKey] : [];
        if ((string) ($existing['status'] ?? '') === 'completed') {
            return false;
        }
        if ((string) ($existing['status'] ?? '') === 'leased' && (int) ($existing['leaseUntil'] ?? 0) > $now) {
            return false;
        }
        $state['schedulerSlots'][$slotKey] = [
            'status' => 'leased',
            'leaseUntil' => $now + 180,
            'attempts' => max(0, (int) ($existing['attempts'] ?? 0)) + 1,
            'startedAt' => dent_iso_now(),
            'completedAt' => '',
        ];
        if (count($state['schedulerSlots']) > 120) {
            $state['schedulerSlots'] = array_slice($state['schedulerSlots'], -120, null, true);
        }
        return true;
    });
}

function dent_term7_scheduler_finish_slot(string $slotKey, bool $success): void
{
    dent_term7_state_with_lock(static function (array &$state) use ($slotKey, $success): array {
        $slot = is_array($state['schedulerSlots'][$slotKey] ?? null) ? $state['schedulerSlots'][$slotKey] : [];
        $slot['status'] = $success ? 'completed' : 'leased';
        $slot['leaseUntil'] = $success ? 0 : time() + 60;
        $slot['attempts'] = max(1, (int) ($slot['attempts'] ?? 1));
        $slot['startedAt'] = (string) (($slot['startedAt'] ?? '') ?: dent_iso_now());
        $slot['completedAt'] = $success ? dent_iso_now() : '';
        $state['schedulerSlots'][$slotKey] = $slot;
        return [];
    });
}

function dent_term7_food_slot(DateTimeImmutable $local): ?array
{
    $weekday = (int) $local->format('N');
    $hour = (int) $local->format('G');
    $slots = $weekday === 2 ? [15, 17, 19, 21, 23] : ($weekday === 3 ? [1, 3, 5] : []);
    foreach ($slots as $index => $slotHour) {
        $nextHour = $slots[$index + 1] ?? ($weekday === 2 ? 24 : 6);
        if ($hour >= $slotHour && $hour < $nextHour) {
            return ['hour' => $slotHour, 'weekKey' => dent_term7_food_week_key($local)];
        }
    }
    return null;
}

function dent_term7_notification_food_context(array $record): ?array
{
    if ((string) ($record['source'] ?? '') !== 'food-reservation') {
        return null;
    }
    $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];
    $weekKey = trim((string) ($meta['foodWeekKey'] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekKey) === 1 ? ['weekKey' => $weekKey] : null;
}

function dent_term7_notification_is_suppressed_for_user(array $record, array $user): bool
{
    $context = dent_term7_notification_food_context($record);
    return $context !== null && dent_term7_food_is_confirmed((string) ($user['studentNumber'] ?? ''), $context['weekKey']);
}

function dent_term7_public_actions_for_notification(array $record, array $user): array
{
    if (dent_term7_notification_is_suppressed_for_user($record, $user)) {
        return [];
    }
    return is_array($record['actions'] ?? null) ? $record['actions'] : [];
}

function dent_term7_perform_notification_action(array $user, string $platform, array $record, string $actionRef): array
{
    $context = dent_term7_notification_food_context($record);
    if ($context === null) {
        dent_error('این عملیات اعلان پشتیبانی نمی‌شود.', 422, ['code' => 'NOTIFICATION_ACTION_UNSUPPORTED']);
    }
    $expected = '';
    foreach (is_array($record['actions'] ?? null) ? $record['actions'] : [] as $action) {
        if (is_array($action) && hash_equals((string) ($action['ref'] ?? ''), $actionRef)) {
            $expected = $actionRef;
            break;
        }
    }
    if ($expected === '') {
        dent_error('این عملیات دیگر معتبر نیست.', 409, ['code' => 'NOTIFICATION_ACTION_EXPIRED']);
    }
    $result = dent_term7_confirm_food($user, $context['weekKey'], $platform);
    return [
        'success' => true,
        'message' => !empty($result['alreadyConfirmed']) ? 'رزرو غذا قبلاً ثبت شده بود.' : 'رزرو غذا ثبت شد؛ یادآوری‌های باقی‌مانده این هفته متوقف شدند.',
        'state' => $result,
    ];
}

function dent_term7_scheduler_tick(?DateTimeImmutable $now = null): array
{
    $timezone = new DateTimeZone(DENT_TERM7_TIMEZONE);
    $local = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    $tomorrow = $local->modify('+1 day');
    $academicDue = (int) $local->format('G') === 21
        && dent_term7_jalali_in_active_window(dent_term7_jalali_key($tomorrow));
    $foodSlot = dent_term7_food_slot($local);
    if ($foodSlot !== null && !dent_term7_jalali_in_active_window(dent_term7_jalali_key($local))) {
        $foodSlot = null;
    }
    if (!$academicDue && $foodSlot === null) {
        return [
            'success' => true,
            'contractVersion' => DENT_TERM7_CONTRACT,
            'scheduleVersion' => DENT_TERM7_SCHEDULE_VERSION,
            'checkedAt' => $local->format(DATE_ATOM),
            'eligibleUsers' => 0,
            'created' => 0,
            'existing' => 0,
            'errors' => [],
        ];
    }
    $users = dent_term7_linked_eligible_users();
    $created = 0;
    $existing = 0;
    $errors = [];

    $academicSlotKey = 'academic:' . $local->format('Y-m-d') . ':21';
    if ($academicDue && dent_term7_scheduler_claim_slot($academicSlotKey, $local->getTimestamp())) {
        $slotErrors = [];
        $state = dent_term7_state_read();
        foreach ($users as $studentNumber => $user) {
            try {
                $assignment = dent_term7_assignment_for_student((string) $studentNumber, $state);
                $resolved = dent_term7_resolve_date($tomorrow, $assignment);
                $candidate = [
                    'source' => 'academic-term7',
                    'sourceKey' => 'term7:' . DENT_TERM7_SCHEDULE_VERSION . ':tomorrow:' . $tomorrow->format('Y-m-d') . ':' . $studentNumber,
                    'title' => '📅 برنامه فردا | ' . $resolved['weekdayLabel'] . ' ' . $resolved['date'],
                    'body' => dent_term7_summary_body($resolved),
                    'tone' => 'accent',
                    'meta' => ['important' => true, 'scheduleVersion' => DENT_TERM7_SCHEDULE_VERSION],
                ];
                $result = notifications_ensure_user_candidate($user, $candidate);
                !empty($result['created']) ? $created++ : $existing++;
            } catch (Throwable $error) {
                $slotErrors[] = 'academic:' . substr(hash('sha256', (string) $studentNumber), 0, 10);
            }
        }
        dent_term7_scheduler_finish_slot($academicSlotKey, $slotErrors === []);
        $errors = array_merge($errors, $slotErrors);
    }

    $foodSlotKey = $foodSlot === null ? '' : 'food:' . $foodSlot['weekKey'] . ':' . sprintf('%02d', $foodSlot['hour']);
    if ($foodSlot !== null && dent_term7_scheduler_claim_slot($foodSlotKey, $local->getTimestamp())) {
        $slotErrors = [];
        $state = dent_term7_state_read();
        foreach ($users as $studentNumber => $user) {
            try {
                if (dent_term7_food_is_confirmed((string) $studentNumber, $foodSlot['weekKey'], $state)) {
                    continue;
                }
                $actionRef = dent_term7_food_action_ref((string) $studentNumber, $foodSlot['weekKey']);
                $candidate = [
                    'source' => 'food-reservation',
                    'sourceKey' => 'food:' . $foodSlot['weekKey'] . ':' . sprintf('%02d', $foodSlot['hour']) . ':' . $studentNumber,
                    'title' => '🍽 یادآوری رزرو غذا',
                    'body' => "مهلت رزرو غذای هفته بعد را از دست نده.\n\nاگر رزرو را انجام دادی، حتماً روی «✅ رزرو کردم» بزن. تا زمانی که تأیید نکنی، این یادآوری هر دو ساعت تا صبح چهارشنبه ادامه پیدا می‌کند.",
                    'tone' => 'warn',
                    'actions' => [['ref' => $actionRef, 'label' => '✅ رزرو کردم', 'style' => 'success']],
                    'meta' => [
                        'important' => true,
                        'foodWeekKey' => $foodSlot['weekKey'],
                        'externalUrl' => DENT_TERM7_FOOD_URL,
                    ],
                ];
                $result = notifications_ensure_user_candidate($user, $candidate);
                !empty($result['created']) ? $created++ : $existing++;
            } catch (Throwable $error) {
                $slotErrors[] = 'food:' . substr(hash('sha256', (string) $studentNumber), 0, 10);
            }
        }
        dent_term7_scheduler_finish_slot($foodSlotKey, $slotErrors === []);
        $errors = array_merge($errors, $slotErrors);
    }

    return [
        'success' => $errors === [],
        'contractVersion' => DENT_TERM7_CONTRACT,
        'scheduleVersion' => DENT_TERM7_SCHEDULE_VERSION,
        'checkedAt' => $local->format(DATE_ATOM),
        'eligibleUsers' => count($users),
        'created' => $created,
        'existing' => $existing,
        'errors' => $errors,
    ];
}

function dent_term7_normalize_person_name(string $name): string
{
    $name = strtr($name, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', "\xE2\x80\x8C" => ' ']);
    $name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name) ?? $name;
    return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
}

function dent_term7_import_assignments(array $rows, string $field, bool $commit): array
{
    if (!in_array($field, ['group10', 'group8'], true)) {
        dent_error('نوع گروه نامعتبر است.', 422);
    }
    $minimum = $field === 'group10' ? 1 : 11;
    $maximum = $field === 'group10' ? 10 : 18;
    $userStore = dent_load_user_store();
    $nameIndex = [];
    foreach (is_array($userStore['users'] ?? null) ? $userStore['users'] : [] as $studentNumber => $user) {
        if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) {
            continue;
        }
        $normalizedName = dent_term7_normalize_person_name((string) ($user['name'] ?? ''));
        if ($normalizedName !== '') {
            $nameIndex[$normalizedName][] = (string) $studentNumber;
        }
    }
    $report = [
        'matched' => [],
        'unmatched' => [],
        'ambiguous' => [],
        'duplicates' => [],
        'invalid' => [],
        'missingGroup10' => [],
        'missingGroup8' => [],
    ];
    $pending = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            $report['invalid'][] = ['row' => $index + 1, 'reason' => 'ROW_NOT_OBJECT'];
            continue;
        }
        $studentNumber = dent_normalize_student_number((string) ($row['studentNumber'] ?? ''));
        $name = dent_term7_normalize_person_name((string) ($row['name'] ?? ''));
        $group = (int) ($row['group'] ?? ($row[$field] ?? 0));
        if ($group < $minimum || $group > $maximum) {
            $report['invalid'][] = ['row' => $index + 1, 'name' => $name, 'reason' => 'GROUP_RANGE'];
            continue;
        }
        $matchedBy = 'studentNumber';
        if ($studentNumber === '' || !isset($userStore['users'][$studentNumber])) {
            $candidates = $name !== '' ? ($nameIndex[$name] ?? []) : [];
            if (count($candidates) > 1) {
                $report['ambiguous'][] = ['row' => $index + 1, 'name' => $name, 'candidates' => $candidates];
                continue;
            }
            if (count($candidates) === 0) {
                $report['unmatched'][] = ['row' => $index + 1, 'name' => $name, 'studentNumber' => $studentNumber];
                continue;
            }
            $studentNumber = $candidates[0];
            $matchedBy = 'normalizedName';
        }
        $user = $userStore['users'][$studentNumber] ?? null;
        if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) {
            $report['unmatched'][] = ['row' => $index + 1, 'name' => $name, 'studentNumber' => $studentNumber, 'reason' => 'WRONG_COHORT'];
            continue;
        }
        if (isset($pending[$studentNumber])) {
            $report['duplicates'][] = ['row' => $index + 1, 'studentNumber' => $studentNumber];
            continue;
        }
        $pending[$studentNumber] = $group;
        $report['matched'][] = ['studentNumber' => $studentNumber, 'name' => (string) ($user['name'] ?? ''), 'group' => $group, 'matchedBy' => $matchedBy];
    }
    $currentState = dent_term7_state_read();
    foreach ($userStore['users'] ?? [] as $studentNumber => $user) {
        if (!is_array($user) || dent_user_cohort_key($user) !== DENT_TERM7_COHORT) {
            continue;
        }
        $prospective = dent_term7_assignment_for_student((string) $studentNumber, $currentState);
        if (isset($pending[$studentNumber])) {
            $prospective[$field] = $pending[$studentNumber];
        }
        $missing = ['studentNumber' => (string) $studentNumber, 'name' => (string) ($user['name'] ?? '')];
        if (!is_int($prospective['group10'] ?? null)) {
            $report['missingGroup10'][] = $missing;
        }
        if (!is_int($prospective['group8'] ?? null)) {
            $report['missingGroup8'][] = $missing;
        }
    }
    $report['committed'] = false;
    $targetMissingKey = $field === 'group10' ? 'missingGroup10' : 'missingGroup8';
    if ($commit
        && $report['unmatched'] === []
        && $report['ambiguous'] === []
        && $report['duplicates'] === []
        && $report['invalid'] === []
        && $report[$targetMissingKey] === []) {
        dent_term7_state_with_lock(static function (array &$state) use ($pending, $field): array {
            foreach ($pending as $studentNumber => $group) {
                $current = dent_term7_assignment_for_student((string) $studentNumber, $state);
                $current[$field] = (int) $group;
                $current['updatedAt'] = dent_iso_now();
                $state['assignments'][$studentNumber] = $current;
            }
            return [];
        });
        $report['committed'] = true;
    }
    return $report;
}
