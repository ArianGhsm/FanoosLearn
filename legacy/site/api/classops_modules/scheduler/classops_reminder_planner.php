<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_reminder_policy.php';

/** @return DateTimeImmutable */
function classops_reminder_default_clock(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

/** @return array<string,string> */
function classops_reminder_daypart_times(): array
{
    return [
        'morning' => '08:00',
        'afternoon' => '15:00',
        'evening' => '19:00',
        'night' => '21:00',
    ];
}

function classops_reminder_forbidden_credential_key(string $key): bool
{
    $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', $key) ?? '');
    return in_array($normalized, [
        'username', 'password', 'credential', 'credentials', 'session', 'sessionid',
        'cookie', 'cookies', 'token', 'accesstoken', 'refreshtoken', 'secret', 'authorization',
    ], true);
}

function classops_reminder_assert_no_credentials(mixed $value, string $path = '$'): void
{
    if (!is_array($value)) {
        return;
    }
    foreach ($value as $key => $child) {
        $childPath = $path . '.' . (string) $key;
        if (is_string($key) && classops_reminder_forbidden_credential_key($key)) {
            classops_reminder_fail('CLASSOPS_REMINDER_CREDENTIAL_MATERIAL_FORBIDDEN', 'Credential/session material is forbidden in reminder planning.', [
                'path' => $childPath,
            ]);
        }
        classops_reminder_assert_no_credentials($child, $childPath);
    }
}

/** @return array<string,mixed> */
function classops_reminder_normalize_item(array $item, int $index): array
{
    $path = 'items[' . $index . ']';
    classops_reminder_assert_known_fields($item, [
        'itemId', 'revision', 'itemType', 'status', 'timing', 'audience', 'deliveryPolicyRef', 'reminderPolicy', 'serviceRef',
    ], $path);
    $itemId = trim((string) ($item['itemId'] ?? ''));
    if (preg_match('/^cop_[a-f0-9]{24}$/', $itemId) !== 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_ITEM_ID', 'Planner itemId must be a canonical ClassOps ID.', ['path' => $path . '.itemId']);
    }
    $revision = $item['revision'] ?? null;
    if (!is_int($revision) || $revision < 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_REVISION', 'Item revision must be a positive integer.', ['path' => $path . '.revision']);
    }
    $itemType = (string) ($item['itemType'] ?? '');
    if (!in_array($itemType, ['announcement', 'event', 'class_change', 'deadline', 'task', 'requirement', 'exam', 'critical_notice', 'service_reminder'], true)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_ITEM_TYPE', 'Unsupported ClassOps item type.', ['path' => $path . '.itemType']);
    }
    $status = (string) ($item['status'] ?? '');
    if (!in_array($status, ['draft', 'scheduled', 'active', 'completed', 'cancelled', 'archived'], true)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_STATUS', 'Unsupported item status.', ['path' => $path . '.status']);
    }
    $timing = $item['timing'] ?? null;
    if (!is_array($timing) || classops_reminder_is_list($timing)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_TIMING', 'Item timing must be an object.', ['path' => $path . '.timing']);
    }
    classops_reminder_assert_known_fields($timing, ['startsAt', 'dueAt'], $path . '.timing');
    $normalizedTiming = ['startsAt' => null, 'dueAt' => null];
    foreach (['startsAt', 'dueAt'] as $anchor) {
        $raw = $timing[$anchor] ?? null;
        if ($raw !== null) {
            if (!is_string($raw)) {
                classops_reminder_fail('CLASSOPS_REMINDER_INVALID_TIMESTAMP', 'Timing anchor must be UTC or null.', ['path' => $path . '.timing.' . $anchor]);
            }
            $normalizedTiming[$anchor] = classops_reminder_utc(classops_reminder_parse_utc($raw, $path . '.timing.' . $anchor));
        }
    }

    $audience = $item['audience'] ?? null;
    if (!is_array($audience) || classops_reminder_is_list($audience)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_AUDIENCE', 'Resolved audience descriptor must be an object.', ['path' => $path . '.audience']);
    }
    classops_reminder_assert_known_fields($audience, ['ref', 'hash'], $path . '.audience');
    $audienceRef = classops_reminder_validate_id((string) ($audience['ref'] ?? ''), $path . '.audience.ref', 128);
    if (preg_match('/^[A-Za-z]/', $audienceRef) !== 1 || preg_match('/(?:telegram|bale|chat[_-]?id|^tg[:_-])/i', $audienceRef) === 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_TRANSPORT_ID_FORBIDDEN', 'Audience ref must be an opaque canonical reference, not a transport/chat identifier.', ['path' => $path . '.audience.ref']);
    }
    $audienceHash = strtolower(trim((string) ($audience['hash'] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $audienceHash) !== 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_AUDIENCE_HASH', 'Audience hash must be SHA-256 hex.', ['path' => $path . '.audience.hash']);
    }
    $deliveryPolicyRef = classops_reminder_validate_id((string) ($item['deliveryPolicyRef'] ?? ''), $path . '.deliveryPolicyRef', 128);
    $rawPolicy = $item['reminderPolicy'] ?? null;
    if (!is_array($rawPolicy)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_POLICY', 'Reminder policy is required.', ['path' => $path . '.reminderPolicy']);
    }
    $policy = classops_reminder_normalize_policy($rawPolicy, $itemType);
    $serviceRef = null;
    if (array_key_exists('serviceRef', $item) && $item['serviceRef'] !== null) {
        if (!is_string($item['serviceRef'])) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_SERVICE', 'serviceRef must be a string or null.', ['path' => $path . '.serviceRef']);
        }
        $serviceRef = strtolower(classops_reminder_validate_id($item['serviceRef'], $path . '.serviceRef', 64));
    }
    if ($serviceRef === 'saba' && $itemType !== 'service_reminder') {
        classops_reminder_fail('CLASSOPS_REMINDER_SABA_TYPE_REQUIRED', 'Saba reminders must use service_reminder item type.', ['path' => $path . '.itemType']);
    }
    if ($itemType === 'service_reminder' && $serviceRef === null) {
        classops_reminder_fail('CLASSOPS_REMINDER_SERVICE_REF_REQUIRED', 'service_reminder requires an opaque serviceRef.', ['path' => $path . '.serviceRef']);
    }

    return [
        'itemId' => $itemId,
        'revision' => $revision,
        'itemType' => $itemType,
        'status' => $status,
        'timing' => $normalizedTiming,
        'audience' => ['ref' => $audienceRef, 'hash' => $audienceHash],
        'deliveryPolicyRef' => $deliveryPolicyRef,
        'reminderPolicy' => $policy,
        'serviceRef' => $serviceRef,
    ];
}

/** @return array<string,mixed> */
function classops_reminder_normalize_known_occurrence(array $known, int $index): array
{
    $path = 'knownOccurrences[' . $index . ']';
    classops_reminder_assert_known_fields($known, [
        'occurrenceKey', 'idempotencyKey', 'itemId', 'revision', 'ruleId', 'dueAt', 'state',
    ], $path);
    $occurrenceKey = trim((string) ($known['occurrenceKey'] ?? ''));
    $idempotencyKey = trim((string) ($known['idempotencyKey'] ?? ''));
    if (preg_match('/^occ_[a-f0-9]{64}$/', $occurrenceKey) !== 1 || preg_match('/^idem_[a-f0-9]{64}$/', $idempotencyKey) !== 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_KNOWN_OCCURRENCE', 'Known occurrence keys are invalid.', ['path' => $path]);
    }
    $revision = $known['revision'] ?? null;
    if (!is_int($revision) || $revision < 1) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_KNOWN_OCCURRENCE', 'Known occurrence revision is invalid.', ['path' => $path . '.revision']);
    }
    $state = (string) ($known['state'] ?? '');
    if (!in_array($state, ['planned', 'leased', 'delivered', 'failed', 'superseded'], true)) {
        classops_reminder_fail('CLASSOPS_REMINDER_INVALID_KNOWN_OCCURRENCE', 'Known occurrence state is invalid.', ['path' => $path . '.state']);
    }
    return [
        'occurrenceKey' => $occurrenceKey,
        'idempotencyKey' => $idempotencyKey,
        'itemId' => (static function () use ($known, $path): string {
            $itemId = trim((string) ($known['itemId'] ?? ''));
            if (preg_match('/^cop_[a-f0-9]{24}$/', $itemId) !== 1) {
                classops_reminder_fail('CLASSOPS_REMINDER_INVALID_ITEM_ID', 'Known occurrence itemId must be a canonical ClassOps ID.', ['path' => $path . '.itemId']);
            }
            return $itemId;
        })(),
        'revision' => $revision,
        'ruleId' => classops_reminder_validate_id((string) ($known['ruleId'] ?? ''), $path . '.ruleId', 64),
        'dueAt' => classops_reminder_utc(classops_reminder_parse_utc((string) ($known['dueAt'] ?? ''), $path . '.dueAt')),
        'state' => $state,
    ];
}

/** @return DateTimeImmutable */
function classops_reminder_anchor(array $item, string $anchor, string $ruleId): DateTimeImmutable
{
    $raw = $item['timing'][$anchor] ?? null;
    if (!is_string($raw) || $raw === '') {
        classops_reminder_fail('CLASSOPS_REMINDER_MISSING_ANCHOR', 'Reminder rule references a missing timing anchor.', [
            'itemId' => $item['itemId'], 'ruleId' => $ruleId, 'anchor' => $anchor,
        ]);
    }
    return classops_reminder_parse_utc($raw, 'item.timing.' . $anchor);
}

/** @return list<DateTimeImmutable> */
function classops_reminder_rule_instants(array $item, array $rule, DateTimeImmutable $horizonEnd): array
{
    $type = (string) $rule['type'];
    if ($type === 'absolute') {
        return [classops_reminder_parse_utc((string) $rule['at'], 'rule.at')];
    }
    if ($type === 'relative') {
        $anchor = classops_reminder_anchor($item, (string) $rule['anchor'], (string) $rule['ruleId']);
        return [$anchor->modify(((int) $rule['offsetSeconds'] >= 0 ? '+' : '') . (int) $rule['offsetSeconds'] . ' seconds')];
    }
    if ($type === 'daypart') {
        $anchor = classops_reminder_anchor($item, (string) $rule['anchor'], (string) $rule['ruleId']);
        $tehran = new DateTimeZone(CLASSOPS_REMINDER_TIMEZONE);
        $local = $anchor->setTimezone($tehran)->setTime(0, 0, 0)->modify(((int) $rule['dayOffset'] >= 0 ? '+' : '') . (int) $rule['dayOffset'] . ' days');
        [$hour, $minute] = array_map('intval', explode(':', classops_reminder_daypart_times()[(string) $rule['daypart']]));
        return [$local->setTime($hour, $minute, 0)->setTimezone(new DateTimeZone('UTC'))];
    }

    $tehran = new DateTimeZone(CLASSOPS_REMINDER_TIMEZONE);
    $utcZone = new DateTimeZone('UTC');
    $start = classops_reminder_parse_utc((string) $rule['startAt'], 'rule.startAt');
    $startLocal = $start->setTimezone($tehran);
    [$hour, $minute] = array_map('intval', explode(':', (string) $rule['localTime']));
    $firstLocal = $startLocal->setTime($hour, $minute, 0);
    if ($firstLocal < $startLocal) {
        $firstLocal = $firstLocal->modify('+1 day');
    }
    $until = $rule['until'] === null ? null : classops_reminder_parse_utc((string) $rule['until'], 'rule.until');
    $instants = [];
    $max = min((int) $rule['maxOccurrences'], CLASSOPS_REMINDER_MAX_OCCURRENCES);

    // Jump close to the planning window instead of walking recurrence history from a very old startAt.
    $windowFloor = $horizonEnd->modify('-' . CLASSOPS_REMINDER_MAX_CATCH_UP_SECONDS . ' seconds')->setTimezone($tehran);
    $candidate = $firstLocal;
    if ($candidate < $windowFloor) {
        $days = (int) $candidate->setTime(0, 0)->diff($windowFloor->setTime(0, 0))->format('%a');
        if ($rule['frequency'] === 'daily') {
            $step = (int) $rule['interval'];
            $jumps = max(0, intdiv($days, $step) - 1);
            $candidate = $candidate->modify('+' . ($jumps * $step) . ' days');
        } else {
            $candidate = $candidate->modify('+' . max(0, $days - 14) . ' days');
        }
    }

    $guard = 0;
    while (count($instants) < $max && $guard < 80) {
        $guard++;
        $utc = $candidate->setTimezone($utcZone);
        if ($utc > $horizonEnd) {
            break;
        }
        if ($until !== null && $utc > $until) {
            break;
        }
        $eligible = true;
        if ($rule['frequency'] === 'weekly') {
            $eligible = in_array((int) $candidate->format('N'), $rule['weekdays'], true);
            $weekDistance = intdiv((int) $firstLocal->setTime(0, 0)->diff($candidate->setTime(0, 0))->format('%a'), 7);
            $eligible = $eligible && ($weekDistance % (int) $rule['interval'] === 0);
        }
        if ($eligible && $utc >= $start) {
            $instants[] = $utc;
        }
        $candidate = $candidate->modify($rule['frequency'] === 'daily' ? '+' . (int) $rule['interval'] . ' days' : '+1 day');
    }
    return $instants;
}

/** @return array<string,string|int|null> */
function classops_reminder_occurrence(array $item, array $rule, DateTimeImmutable $dueAt): array
{
    $canonicalDue = classops_reminder_utc($dueAt);
    $identity = implode('|', [
        CLASSOPS_REMINDER_CONTRACT_VERSION,
        (string) $item['itemId'],
        (string) $item['revision'],
        (string) $rule['ruleId'],
        $canonicalDue,
    ]);
    $occurrenceHash = hash('sha256', $identity);
    $idempotencyHash = hash('sha256', $identity . '|' . (string) $item['audience']['hash'] . '|' . (string) $item['deliveryPolicyRef']);
    return [
        'itemId' => (string) $item['itemId'],
        'revision' => (int) $item['revision'],
        'ruleId' => (string) $rule['ruleId'],
        'occurrenceKey' => 'occ_' . $occurrenceHash,
        'idempotencyKey' => 'idem_' . $idempotencyHash,
        'dueAt' => $canonicalDue,
        'audienceRef' => (string) $item['audience']['ref'],
        'audienceHash' => (string) $item['audience']['hash'],
        'deliveryPolicyRef' => (string) $item['deliveryPolicyRef'],
        'reason' => (string) $rule['reason'],
        'serviceRef' => $item['serviceRef'],
    ];
}

/**
 * Pure deterministic planner. It does not persist, send, claim, ACK, call bots or mutate the input snapshot.
 *
 * @param array<string,mixed> $snapshot
 * @param null|callable():DateTimeInterface $clock
 * @return array<string,mixed>
 */
function classops_reminder_plan(array $snapshot, ?callable $clock = null): array
{
    try {
        classops_reminder_assert_no_credentials($snapshot);
        classops_reminder_assert_known_fields($snapshot, [
            'contractVersion', 'planningHorizonSeconds', 'maxOccurrences', 'items', 'knownOccurrences',
        ], 'snapshot');
        if (($snapshot['contractVersion'] ?? null) !== CLASSOPS_REMINDER_CONTRACT_VERSION) {
            classops_reminder_fail('CLASSOPS_REMINDER_UNSUPPORTED_VERSION', 'Unsupported planner snapshot version.', ['path' => 'contractVersion']);
        }
        $horizonSeconds = $snapshot['planningHorizonSeconds'] ?? null;
        if (!is_int($horizonSeconds) || $horizonSeconds < 1 || $horizonSeconds > CLASSOPS_REMINDER_MAX_HORIZON_SECONDS) {
            classops_reminder_fail('CLASSOPS_REMINDER_HORIZON_OUT_OF_RANGE', 'Planning horizon is outside the bounded range.', [
                'maxAllowed' => CLASSOPS_REMINDER_MAX_HORIZON_SECONDS,
            ]);
        }
        $maxOccurrences = $snapshot['maxOccurrences'] ?? null;
        if (!is_int($maxOccurrences) || $maxOccurrences < 1 || $maxOccurrences > CLASSOPS_REMINDER_MAX_OCCURRENCES) {
            classops_reminder_fail('CLASSOPS_REMINDER_OCCURRENCE_LIMIT_OUT_OF_RANGE', 'Planner occurrence limit is outside the bounded range.', [
                'maxAllowed' => CLASSOPS_REMINDER_MAX_OCCURRENCES,
            ]);
        }
        $itemsRaw = $snapshot['items'] ?? null;
        $knownRaw = $snapshot['knownOccurrences'] ?? null;
        if (!is_array($itemsRaw) || !classops_reminder_is_list($itemsRaw) || count($itemsRaw) > 500) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_ITEMS', 'Planner items must be a bounded list.', ['path' => 'items']);
        }
        if (!is_array($knownRaw) || !classops_reminder_is_list($knownRaw) || count($knownRaw) > 5000) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_KNOWN_OCCURRENCES', 'Known occurrences must be a bounded list.', ['path' => 'knownOccurrences']);
        }

        $nowRaw = $clock === null ? classops_reminder_default_clock() : $clock();
        if (!$nowRaw instanceof DateTimeInterface) {
            classops_reminder_fail('CLASSOPS_REMINDER_INVALID_CLOCK', 'Injected clock must return DateTimeInterface.');
        }
        $now = DateTimeImmutable::createFromInterface($nowRaw)->setTimezone(new DateTimeZone('UTC'));
        $horizonEnd = $now->modify('+' . $horizonSeconds . ' seconds');

        $items = [];
        foreach ($itemsRaw as $index => $item) {
            if (!is_array($item)) {
                classops_reminder_fail('CLASSOPS_REMINDER_INVALID_ITEM', 'Planner item must be an object.', ['path' => 'items[' . $index . ']']);
            }
            $normalized = classops_reminder_normalize_item($item, (int) $index);
            if (isset($items[$normalized['itemId']])) {
                classops_reminder_fail('CLASSOPS_REMINDER_DUPLICATE_ITEM', 'Planner snapshot contains duplicate item IDs.', ['itemId' => $normalized['itemId']]);
            }
            $items[$normalized['itemId']] = $normalized;
        }
        $known = [];
        foreach ($knownRaw as $index => $row) {
            if (!is_array($row)) {
                classops_reminder_fail('CLASSOPS_REMINDER_INVALID_KNOWN_OCCURRENCE', 'Known occurrence must be an object.', ['path' => 'knownOccurrences[' . $index . ']']);
            }
            $normalized = classops_reminder_normalize_known_occurrence($row, (int) $index);
            if (isset($known[$normalized['occurrenceKey']])) {
                classops_reminder_fail('CLASSOPS_REMINDER_DUPLICATE_KNOWN_OCCURRENCE', 'Known occurrence keys must be unique.', ['occurrenceKey' => $normalized['occurrenceKey']]);
            }
            $known[$normalized['occurrenceKey']] = $normalized;
        }

        $desired = [];
        $candidates = [];
        foreach ($items as $item) {
            if (!in_array($item['status'], ['scheduled', 'active'], true)) {
                continue;
            }
            $itemMissed = [];
            foreach ($item['reminderPolicy']['rules'] as $rule) {
                foreach (classops_reminder_rule_instants($item, $rule, $horizonEnd) as $instant) {
                    if ($instant > $horizonEnd) {
                        continue;
                    }
                    $occurrence = classops_reminder_occurrence($item, $rule, $instant);
                    $desired[$occurrence['occurrenceKey']] = $occurrence;
                    if ($instant >= $now) {
                        $candidates[] = $occurrence;
                        continue;
                    }
                    $age = $now->getTimestamp() - $instant->getTimestamp();
                    if ($age <= (int) $item['reminderPolicy']['catchUp']['maxAgeSeconds']) {
                        $itemMissed[] = ['instant' => $instant, 'occurrence' => $occurrence];
                    }
                }
            }
            if ($item['reminderPolicy']['catchUp']['mode'] === 'latest_once' && $itemMissed !== []) {
                usort($itemMissed, static fn(array $a, array $b): int => $a['instant'] <=> $b['instant']);
                $latest = $itemMissed[count($itemMissed) - 1]['occurrence'];
                $latest['plannedDueAt'] = classops_reminder_utc($now);
                $latest['catchUp'] = true;
                $candidates[] = $latest;
            }
        }

        $intents = [];
        usort($candidates, static fn(array $a, array $b): int => strcmp((string) ($a['plannedDueAt'] ?? $a['dueAt']), (string) ($b['plannedDueAt'] ?? $b['dueAt'])) ?: strcmp((string) $a['occurrenceKey'], (string) $b['occurrenceKey']));
        foreach ($candidates as $candidate) {
            if (count($intents) >= $maxOccurrences) {
                break;
            }
            if (isset($known[$candidate['occurrenceKey']])) {
                continue;
            }
            $intents[] = $candidate + [
                'plannedDueAt' => $candidate['dueAt'],
                'catchUp' => false,
                'coordination' => [
                    'requirement' => 'single_active_leader',
                    'scope' => 'classops-reminder-delivery',
                    'sideEffectOwner' => 'integration-runtime',
                    'plannerSideEffects' => 'none',
                ],
            ];
        }

        $supersessions = [];
        foreach ($known as $row) {
            if ($row['state'] !== 'planned') {
                continue;
            }
            $due = classops_reminder_parse_utc((string) $row['dueAt'], 'known.dueAt');
            if ($due < $now) {
                continue;
            }
            $item = $items[$row['itemId']] ?? null;
            $reason = null;
            if ($item === null) {
                $reason = 'item_missing_from_snapshot';
            } elseif (in_array($item['status'], ['cancelled', 'archived', 'completed'], true)) {
                $reason = 'item_' . $item['status'];
            } elseif ((int) $row['revision'] !== (int) $item['revision']) {
                $reason = 'item_revision_changed';
            } elseif (!isset($desired[$row['occurrenceKey']])) {
                $reason = 'schedule_changed';
            }
            if ($reason !== null) {
                $supersessions[] = [
                    'occurrenceKey' => $row['occurrenceKey'],
                    'itemId' => $row['itemId'],
                    'revision' => $row['revision'],
                    'dueAt' => $row['dueAt'],
                    'reason' => $reason,
                ];
            }
        }
        usort($supersessions, static fn(array $a, array $b): int => strcmp((string) $a['occurrenceKey'], (string) $b['occurrenceKey']));

        return [
            'ok' => true,
            'contractVersion' => CLASSOPS_REMINDER_CONTRACT_VERSION,
            'plannedAt' => classops_reminder_utc($now),
            'horizonEnd' => classops_reminder_utc($horizonEnd),
            'intents' => $intents,
            'supersessions' => $supersessions,
            'errors' => [],
            'truncated' => count($candidates) > count($intents) + count(array_intersect(array_column($candidates, 'occurrenceKey'), array_keys($known))),
        ];
    } catch (DentClassOpsReminderException $exception) {
        return [
            'ok' => false,
            'contractVersion' => CLASSOPS_REMINDER_CONTRACT_VERSION,
            'plannedAt' => null,
            'horizonEnd' => null,
            'intents' => [],
            'supersessions' => [],
            'errors' => [[
                'code' => $exception->reasonCode,
                'message' => $exception->getMessage(),
                'details' => $exception->details,
            ]],
            'truncated' => false,
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'contractVersion' => CLASSOPS_REMINDER_CONTRACT_VERSION,
            'plannedAt' => null,
            'horizonEnd' => null,
            'intents' => [],
            'supersessions' => [],
            'errors' => [[
                'code' => 'CLASSOPS_REMINDER_INTERNAL_ERROR',
                'message' => 'Reminder planner failed closed.',
                'details' => [],
            ]],
            'truncated' => false,
        ];
    }
}
