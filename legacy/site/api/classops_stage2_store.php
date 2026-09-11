<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_domain_store_adapter.php';
require_once __DIR__ . '/classops_modules/audience/resolver.php';
require_once __DIR__ . '/classops_modules/tasks/task_domain.php';
require_once __DIR__ . '/classops_modules/ack/critical_ack.php';
require_once __DIR__ . '/classops_modules/delivery/delivery_planner.php';

const CLASSOPS_STAGE2_STATE_SCHEMA_VERSION = 1;
const CLASSOPS_STAGE2_STATE_CONTRACT_VERSION = 'classops-stage2-state-v1';
const CLASSOPS_STAGE2_MAX_AUDIENCE_SNAPSHOTS = 4000;
const CLASSOPS_STAGE2_MAX_TASK_STATES = 20000;
const CLASSOPS_STAGE2_MAX_DELIVERY_INTENTS = 10000;
const CLASSOPS_STAGE2_MAX_SCHEDULER_OCCURRENCES = 10000;
const CLASSOPS_STAGE2_MAX_CALLBACK_REFS = 4000;

function classops_stage2_state_path(): string
{
    $override = dent_env_value('DENT_CLASSOPS_STAGE2_STATE_PATH');
    if ($override !== '') {
        return dent_resolve_path($override, DENT_PROJECT_ROOT);
    }
    // This is a versioned side-state lane inside the canonical ClassOps storage
    // family. It is not a second product database and never duplicates items.
    return dent_storage_path('classops/domain-state.json');
}

function classops_stage2_default_state(): array
{
    return [
        'schemaVersion' => CLASSOPS_STAGE2_STATE_SCHEMA_VERSION,
        'contractVersion' => CLASSOPS_STAGE2_STATE_CONTRACT_VERSION,
        'updatedAt' => dent_iso_now(),
        'audienceSnapshots' => [],
        'taskStates' => [],
        'ackState' => classops_ack_empty_state(),
        'deliveryIntents' => [],
        'schedulerOccurrences' => [],
        'callbackRefs' => [],
        '_storage' => [
            'format' => 'classops-atomic-json-v1',
            'generation' => 0,
            'previousSha256' => '',
            'committedAt' => '',
        ],
    ];
}

function classops_stage2_map(array $state, string $field, int $max, callable $fail): array
{
    $value = $state[$field] ?? null;
    if (!is_array($value) || ($value !== [] && array_is_list($value)) || count($value) > $max) {
        $fail("Invalid or unbounded Stage2 state field: {$field}");
    }
    return $value;
}

function classops_stage2_state_validator(array $state): void
{
    $fail = static function (string $message): never {
        throw new DentClassOpsPersistenceException('CLASSOPS_STAGE2_SCHEMA_INVALID', $message);
    };
    if (($state['schemaVersion'] ?? null) !== CLASSOPS_STAGE2_STATE_SCHEMA_VERSION
        || ($state['contractVersion'] ?? null) !== CLASSOPS_STAGE2_STATE_CONTRACT_VERSION) {
        $fail('ClassOps Stage2 state version mismatch');
    }
    foreach (['audienceSnapshots','taskStates','deliveryIntents','schedulerOccurrences','callbackRefs','_storage'] as $field) {
        if (!is_array($state[$field] ?? null)) {
            $fail("ClassOps Stage2 field {$field} is invalid");
        }
    }
    if (($state['_storage']['format'] ?? '') !== 'classops-atomic-json-v1'
        || !is_int($state['_storage']['generation'] ?? null)
        || (int) $state['_storage']['generation'] < 0) {
        $fail('ClassOps Stage2 generation metadata is invalid');
    }
    try {
        $normalizedAck = classops_ack_normalize_state(is_array($state['ackState'] ?? null) ? $state['ackState'] : []);
        if (classops_canonical_json($normalizedAck) !== classops_canonical_json($state['ackState'] ?? null)) {
            $fail('ClassOps ACK state is not canonical');
        }
    } catch (DentClassOpsCriticalAckException $exception) {
        $fail('ClassOps ACK state is invalid');
    }

    $audiences = classops_stage2_map($state, 'audienceSnapshots', CLASSOPS_STAGE2_MAX_AUDIENCE_SNAPSHOTS, $fail);
    foreach ($audiences as $key => $record) {
        if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || !is_array($record)) {
            $fail('Audience snapshot index is invalid');
        }
        foreach (['itemId','revision','cohortKey','spec','snapshot','resolutionHash','confirmedAt'] as $field) {
            if (!array_key_exists($field, $record)) $fail('Audience snapshot record is incomplete');
        }
        if (preg_match('/^cop_[a-f0-9]{16,64}$/D', (string) $record['itemId']) !== 1
            || !is_int($record['revision']) || $record['revision'] < 1
            || preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', (string) $record['cohortKey']) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', (string) $record['resolutionHash']) !== 1
            || !is_array($record['spec']) || !is_array($record['snapshot'])
            || ($record['spec']['version'] ?? '') !== 'classops-audience-v1'
            || ($record['snapshot']['version'] ?? '') !== 'classops-audience-snapshot-v1') {
            $fail('Audience snapshot record is invalid');
        }
        $recipients = $record['snapshot']['recipientStudentNumbers'] ?? null;
        if (!is_array($recipients) || !array_is_list($recipients) || count($recipients) > 5000) {
            $fail('Audience snapshot recipients are invalid');
        }
        foreach ($recipients as $studentNumber) {
            if (preg_match('/^[0-9]{5,20}$/D', (string) $studentNumber) !== 1) $fail('Audience snapshot identity is invalid');
        }
    }

    $tasks = classops_stage2_map($state, 'taskStates', CLASSOPS_STAGE2_MAX_TASK_STATES, $fail);
    foreach ($tasks as $key => $taskState) {
        if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || !is_array($taskState)) {
            $fail('Task state index is invalid');
        }
        try {
            $normalized = classops_task_validate_state($taskState);
            if (classops_canonical_json($normalized) !== classops_canonical_json($taskState)) $fail('Task state is not canonical');
        } catch (DentClassOpsTaskException $exception) {
            $fail('Task state is invalid');
        }
    }

    $deliveries = classops_stage2_map($state, 'deliveryIntents', CLASSOPS_STAGE2_MAX_DELIVERY_INTENTS, $fail);
    foreach ($deliveries as $intentId => $entry) {
        if (!is_string($intentId) || preg_match('/^cdi_[a-f0-9]{32}$/D', $intentId) !== 1 || !is_array($entry)) {
            $fail('Delivery intent index is invalid');
        }
        try { classops_delivery_reject_raw_identifiers($entry); } catch (Throwable $e) { $fail('Raw platform identifier in delivery state'); }
        $status = (string) ($entry['status'] ?? '');
        if (!in_array($status, ['planned','leased','delivered','retry','failed','superseded','cancelled'], true)
            || !is_array($entry['intent'] ?? null)
            || ($entry['intent']['intentId'] ?? '') !== $intentId
            || !in_array((string) ($entry['intent']['platform'] ?? ''), ['telegram','bale'], true)
            || !is_int($entry['attempts'] ?? null) || (int) $entry['attempts'] < 0) {
            $fail('Delivery intent state is invalid');
        }
        if (isset($entry['message']) && (!is_array($entry['message']) || strlen(classops_canonical_json($entry['message'])) > 16000)) {
            $fail('Delivery message is invalid');
        }
    }

    classops_stage2_map($state, 'schedulerOccurrences', CLASSOPS_STAGE2_MAX_SCHEDULER_OCCURRENCES, $fail);
    $callbacks = classops_stage2_map($state, 'callbackRefs', CLASSOPS_STAGE2_MAX_CALLBACK_REFS, $fail);
    foreach ($callbacks as $key => $entry) {
        if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || !is_array($entry)
            || preg_match('/^[a-f0-9]{64}$/D', (string) ($entry['actorHash'] ?? '')) !== 1
            || !in_array((string) ($entry['platform'] ?? ''), ['telegram','bale'], true)
            || !is_string($entry['action'] ?? null) || !is_array($entry['payload'] ?? null)) {
            $fail('Callback reference is invalid');
        }
        try { classops_delivery_reject_raw_identifiers($entry['payload']); } catch (Throwable $e) { $fail('Raw platform identifier in callback payload'); }
    }
}

function classops_stage2_read_state(bool $allowMissing = true): array
{
    return classops_persistence_read(
        classops_stage2_state_path(),
        $allowMissing,
        classops_stage2_default_state(),
        'classops_stage2_state_validator'
    )['store'];
}

function classops_stage2_transaction(callable $mutator, string $action, bool $initialize = true, array $testOptions = []): array
{
    return classops_persistence_transaction(
        classops_stage2_state_path(),
        $initialize,
        classops_stage2_default_state(),
        'classops_stage2_state_validator',
        $mutator,
        'stage2-' . $action,
        $testOptions
    );
}

function classops_stage2_audience_key(string $itemId, int $revision): string
{
    return hash('sha256', 'classops-stage2-audience-v1|' . $itemId . '|' . $revision);
}

function classops_stage2_task_key(string $itemId, int $revision, string $studentNumber): string
{
    return hash('sha256', 'classops-stage2-task-v1|' . $itemId . '|' . $revision . '|' . $studentNumber);
}

function classops_stage2_save_audience(array $item, array $spec, array $resolution): array
{
    $itemId = (string) ($item['id'] ?? '');
    $revision = (int) ($item['revision'] ?? 0);
    if ($itemId === '' || $revision < 1 || !is_array($resolution['snapshot'] ?? null)) {
        classops_domain_error('CLASSOPS_STAGE2_AUDIENCE_SNAPSHOT_REQUIRED', 'Trusted audience snapshot is required.', 500);
    }
    $record = [
        'itemId' => $itemId,
        'revision' => $revision,
        'cohortKey' => (string) ($item['cohortKey'] ?? ''),
        'spec' => $spec,
        'snapshot' => $resolution['snapshot'],
        'resolutionHash' => (string) ($resolution['deterministicHash'] ?? ''),
        'confirmedAt' => dent_iso_now(),
    ];
    $key = classops_stage2_audience_key($itemId, $revision);
    $tx = classops_stage2_transaction(static function (array &$state) use ($key, $record): array {
        $existing = $state['audienceSnapshots'][$key] ?? null;
        if (is_array($existing)) {
            if (classops_canonical_json($existing) !== classops_canonical_json($record)) {
                classops_domain_error('CLASSOPS_AUDIENCE_CONFIRMATION_CONFLICT', 'Audience snapshot for this revision already differs.', 409);
            }
            return $existing;
        }
        $state['audienceSnapshots'][$key] = $record;
        if (count($state['audienceSnapshots']) > CLASSOPS_STAGE2_MAX_AUDIENCE_SNAPSHOTS) {
            array_shift($state['audienceSnapshots']);
        }
        $state['updatedAt'] = dent_iso_now();
        return $record;
    }, 'audience-confirm');
    return $tx['result'];
}

function classops_stage2_get_audience(string $itemId, int $revision): ?array
{
    $state = classops_stage2_read_state();
    $record = $state['audienceSnapshots'][classops_stage2_audience_key($itemId, $revision)] ?? null;
    return is_array($record) ? $record : null;
}

function classops_stage2_ensure_task_states(array $item, array $studentNumbers): int
{
    if (!in_array((string) ($item['type'] ?? ''), ['task','requirement'], true)) return 0;
    $itemId = (string) $item['id'];
    $revision = (int) $item['revision'];
    $cohort = (string) $item['cohortKey'];
    $at = gmdate('Y-m-d\TH:i:s\Z');
    $tx = classops_stage2_transaction(static function (array &$state) use ($itemId, $revision, $cohort, $studentNumbers, $at): int {
        $created = 0;
        foreach ($studentNumbers as $rawStudent) {
            $student = classops_task_digits($rawStudent);
            $key = classops_stage2_task_key($itemId, $revision, $student);
            if (isset($state['taskStates'][$key])) continue;
            $state['taskStates'][$key] = classops_task_new_state($itemId, $revision, $cohort, $student, $at);
            $created++;
        }
        $state['updatedAt'] = dent_iso_now();
        return $created;
    }, 'task-initialize');
    return (int) $tx['result'];
}

function classops_stage2_get_task_state(array $item, string $studentNumber, bool $createIfEligible = false): ?array
{
    $key = classops_stage2_task_key((string) $item['id'], (int) $item['revision'], classops_task_digits($studentNumber));
    $state = classops_stage2_read_state();
    $current = $state['taskStates'][$key] ?? null;
    if (is_array($current) || !$createIfEligible) return is_array($current) ? $current : null;
    classops_stage2_ensure_task_states($item, [$studentNumber]);
    $state = classops_stage2_read_state(false);
    $current = $state['taskStates'][$key] ?? null;
    return is_array($current) ? $current : null;
}

function classops_stage2_transition_task(array $item, string $studentNumber, int $expectedStateRevision, string $target, string $commandId, string $actorRef, string $reason = ''): array
{
    $studentNumber = classops_task_digits($studentNumber);
    $key = classops_stage2_task_key((string) $item['id'], (int) $item['revision'], $studentNumber);
    $tx = classops_stage2_transaction(static function (array &$state) use ($key, $item, $studentNumber, $expectedStateRevision, $target, $commandId, $actorRef, $reason): array {
        $current = $state['taskStates'][$key] ?? classops_task_new_state(
            (string) $item['id'], (int) $item['revision'], (string) $item['cohortKey'], $studentNumber, gmdate('Y-m-d\TH:i:s\Z')
        );
        $next = classops_task_transition($current, $expectedStateRevision, $target, $commandId, $actorRef, gmdate('Y-m-d\TH:i:s\Z'), $reason);
        $state['taskStates'][$key] = $next;
        $state['updatedAt'] = dent_iso_now();
        return $next;
    }, 'task-transition');
    return $tx['result'];
}

function classops_stage2_ack_state(): array
{
    return classops_stage2_read_state()['ackState'];
}

function classops_stage2_record_ack(array $notice, string $studentNumber, string $audienceHash, string $idempotencyKey): array
{
    $studentNumber = classops_ack_student_number($studentNumber);
    $tx = classops_stage2_transaction(static function (array &$state) use ($notice, $studentNumber, $audienceHash, $idempotencyKey): array {
        $result = classops_ack_record(
            $state['ackState'],
            $notice,
            ['studentNumber' => $studentNumber, 'role' => 'student'],
            [
                'contractVersion' => CLASSOPS_CRITICAL_ACK_ELIGIBILITY_VERSION,
                'itemId' => (string) $notice['id'],
                'revision' => (int) $notice['revision'],
                'studentNumber' => $studentNumber,
                'eligible' => true,
                'audienceFingerprint' => $audienceHash,
            ],
            (int) $notice['revision'],
            $idempotencyKey,
            gmdate('Y-m-d\TH:i:s\Z')
        );
        $state['ackState'] = $result['state'];
        $state['updatedAt'] = dent_iso_now();
        unset($result['state']);
        return $result;
    }, 'critical-ack');
    return $tx['result'];
}

function classops_stage2_store_delivery_intents(array $intents, array $message): int
{
    $tx = classops_stage2_transaction(static function (array &$state) use ($intents, $message): int {
        $created = 0;
        foreach ($intents as $intent) {
            if (!is_array($intent)) continue;
            $id = (string) ($intent['intentId'] ?? '');
            if (preg_match('/^cdi_[a-f0-9]{32}$/D', $id) !== 1) continue;
            if (isset($state['deliveryIntents'][$id])) continue;
            $state['deliveryIntents'][$id] = [
                'status' => 'planned',
                'intent' => $intent,
                'message' => $message,
                'attempts' => 0,
                'nextAttemptAt' => (string) ($intent['occurrence']['scheduledAt'] ?? gmdate('Y-m-d\TH:i:s\Z')),
                'leaseUntil' => null,
                'lastReasonCode' => null,
                'updatedAt' => dent_iso_now(),
            ];
            $created++;
        }
        $state['updatedAt'] = dent_iso_now();
        return $created;
    }, 'delivery-plan');
    return (int) $tx['result'];
}

function classops_stage2_supersede_item_deliveries(string $itemId, int $newRevision, string $reason): int
{
    $tx = classops_stage2_transaction(static function (array &$state) use ($itemId, $newRevision, $reason): int {
        $count = 0;
        foreach ($state['deliveryIntents'] as &$entry) {
            if (!is_array($entry) || !is_array($entry['intent'] ?? null)) continue;
            if (($entry['intent']['itemRef']['id'] ?? '') !== $itemId) continue;
            if ((int) ($entry['intent']['itemRef']['revision'] ?? 0) >= $newRevision) continue;
            if (in_array((string) ($entry['status'] ?? ''), ['delivered','failed','cancelled','superseded'], true)) continue;
            $entry['status'] = 'superseded';
            $entry['lastReasonCode'] = $reason;
            $entry['updatedAt'] = dent_iso_now();
            $count++;
        }
        unset($entry);
        $state['updatedAt'] = dent_iso_now();
        return $count;
    }, 'delivery-supersede');
    return (int) $tx['result'];
}

function classops_stage2_claim_deliveries(string $platform, int $limit = 20): array
{
    if (!in_array($platform, ['telegram','bale'], true)) {
        classops_domain_error('CLASSOPS_DELIVERY_PLATFORM_INVALID', 'Delivery platform is invalid.');
    }
    $limit = max(1, min(50, $limit));
    $now = time();
    $leaseUntil = gmdate('Y-m-d\TH:i:s\Z', $now + 120);
    $tx = classops_stage2_transaction(static function (array &$state) use ($platform, $limit, $now, $leaseUntil): array {
        $claimed = [];
        foreach ($state['deliveryIntents'] as $id => &$entry) {
            if (count($claimed) >= $limit) break;
            if (!is_array($entry) || !is_array($entry['intent'] ?? null) || ($entry['intent']['platform'] ?? '') !== $platform) continue;
            $status = (string) ($entry['status'] ?? '');
            $leaseTs = strtotime((string) ($entry['leaseUntil'] ?? '')) ?: 0;
            if ($status === 'leased' && $leaseTs > $now) continue;
            if (!in_array($status, ['planned','retry','leased'], true)) continue;
            $next = strtotime((string) ($entry['nextAttemptAt'] ?? '')) ?: 0;
            if ($next > $now) continue;
            $entry['status'] = 'leased';
            $entry['leaseUntil'] = $leaseUntil;
            $entry['updatedAt'] = dent_iso_now();
            $claimed[] = [
                'intentId' => $id,
                'dedupeKey' => (string) ($entry['intent']['dedupeKey'] ?? ''),
                'destination' => [
                    'alias' => (string) ($entry['intent']['resolvedDestinationAlias'] ?? ''),
                    'bindingRef' => (string) ($entry['intent']['bindingRef'] ?? ''),
                    'kind' => (string) ($entry['intent']['destinationKind'] ?? ''),
                    'platform' => $platform,
                ],
                'message' => $entry['message'],
                'occurrence' => $entry['intent']['occurrence'] ?? [],
                'attempt' => (int) $entry['attempts'] + 1,
            ];
        }
        unset($entry);
        $state['updatedAt'] = dent_iso_now();
        return $claimed;
    }, 'delivery-claim');
    return $tx['result'];
}

function classops_stage2_ack_delivery(string $platform, string $intentId, bool $success, string $reasonCode = ''): array
{
    $tx = classops_stage2_transaction(static function (array &$state) use ($platform, $intentId, $success, $reasonCode): array {
        $entry = $state['deliveryIntents'][$intentId] ?? null;
        if (!is_array($entry) || ($entry['intent']['platform'] ?? '') !== $platform) {
            classops_domain_error('CLASSOPS_DELIVERY_INTENT_NOT_FOUND', 'Delivery intent was not found.', 404);
        }
        if (($entry['status'] ?? '') === 'delivered') return $entry;
        if (!in_array((string) ($entry['status'] ?? ''), ['leased','planned','retry'], true)) return $entry;
        $entry['attempts'] = (int) ($entry['attempts'] ?? 0) + 1;
        $entry['leaseUntil'] = null;
        $entry['lastReasonCode'] = $reasonCode === '' ? null : substr($reasonCode, 0, 80);
        if ($success) {
            $entry['status'] = 'delivered';
            $entry['nextAttemptAt'] = null;
        } else {
            $max = max(1, (int) ($entry['intent']['retry']['maxAttempts'] ?? 5));
            if ($entry['attempts'] >= $max) {
                $entry['status'] = 'failed';
                $entry['nextAttemptAt'] = null;
            } else {
                $base = max(1, (int) ($entry['intent']['retry']['baseBackoffSeconds'] ?? 30));
                $cap = max($base, (int) ($entry['intent']['retry']['maxBackoffSeconds'] ?? 900));
                $delay = min($cap, $base * (2 ** max(0, $entry['attempts'] - 1)));
                $entry['status'] = 'retry';
                $entry['nextAttemptAt'] = gmdate('Y-m-d\TH:i:s\Z', time() + $delay);
            }
        }
        $entry['updatedAt'] = dent_iso_now();
        $state['deliveryIntents'][$intentId] = $entry;
        $state['updatedAt'] = dent_iso_now();
        return $entry;
    }, 'delivery-ack');
    return $tx['result'];
}

function classops_stage2_callback_actor_hash(string $platform, string $platformUserId): string
{
    return hash('sha256', 'classops-callback-actor-v1|' . $platform . '|' . $platformUserId);
}

function classops_stage2_issue_callback(string $platform, string $platformUserId, string $action, array $payload, int $ttlSeconds = 900): string
{
    if (!in_array($platform, ['telegram','bale'], true) || $platformUserId === '') {
        classops_domain_error('CLASSOPS_CALLBACK_ACTOR_INVALID', 'Callback actor is invalid.', 422);
    }
    classops_delivery_reject_raw_identifiers($payload);
    $ttlSeconds = max(60, min(1800, $ttlSeconds));
    $token = 'cxo_' . bin2hex(random_bytes(12));
    $key = hash('sha256', $token);
    $entry = [
        'actorHash' => classops_stage2_callback_actor_hash($platform, $platformUserId),
        'platform' => $platform,
        'action' => substr($action, 0, 80),
        'payload' => $payload,
        'expiresAt' => gmdate('Y-m-d\TH:i:s\Z', time() + $ttlSeconds),
        'usedAt' => null,
    ];
    classops_stage2_transaction(static function (array &$state) use ($key, $entry): void {
        $now = time();
        foreach ($state['callbackRefs'] as $oldKey => $old) {
            if ((strtotime((string) ($old['expiresAt'] ?? '')) ?: 0) < $now - 3600) unset($state['callbackRefs'][$oldKey]);
        }
        $state['callbackRefs'][$key] = $entry;
        if (count($state['callbackRefs']) > CLASSOPS_STAGE2_MAX_CALLBACK_REFS) array_shift($state['callbackRefs']);
        $state['updatedAt'] = dent_iso_now();
    }, 'callback-issue');
    return $token;
}

function classops_stage2_resolve_callback(string $platform, string $platformUserId, string $token): array
{
    if (preg_match('/^cxo_[a-f0-9]{24}$/D', $token) !== 1) {
        classops_domain_error('CLASSOPS_CALLBACK_INVALID', 'Callback reference is invalid.', 422);
    }
    $key = hash('sha256', $token);
    $actorHash = classops_stage2_callback_actor_hash($platform, $platformUserId);
    $tx = classops_stage2_transaction(static function (array &$state) use ($key, $actorHash, $platform): array {
        $entry = $state['callbackRefs'][$key] ?? null;
        if (!is_array($entry) || ($entry['platform'] ?? '') !== $platform || !hash_equals((string) ($entry['actorHash'] ?? ''), $actorHash)) {
            classops_domain_error('CLASSOPS_CALLBACK_FORBIDDEN', 'Callback does not belong to this actor.', 403);
        }
        if ((strtotime((string) ($entry['expiresAt'] ?? '')) ?: 0) < time()) {
            classops_domain_error('CLASSOPS_CALLBACK_EXPIRED', 'Callback has expired.', 409);
        }
        if (($entry['usedAt'] ?? null) === null) {
            $entry['usedAt'] = dent_iso_now();
            $state['callbackRefs'][$key] = $entry;
            $state['updatedAt'] = dent_iso_now();
        }
        return ['action' => $entry['action'], 'payload' => $entry['payload']];
    }, 'callback-resolve');
    return $tx['result'];
}
