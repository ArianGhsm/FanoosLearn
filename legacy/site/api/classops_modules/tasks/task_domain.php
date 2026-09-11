<?php
declare(strict_types=1);

const CLASSOPS_TASKS_CONTRACT_VERSION = 'classops-tasks-v1';
const CLASSOPS_TASKS_STATE_VERSION = 'classops-task-state-v1';
const CLASSOPS_TASKS_EXTENSION_KEY = 'classops_tasks_v1';
const CLASSOPS_TASKS_MAX_HISTORY = 512;
const CLASSOPS_TASKS_MAX_TARGET = 10000;

final class DentClassOpsTaskException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly int $httpStatus,
        string $message
    ) {
        parent::__construct($message);
    }
}

function classops_task_fail(string $code, string $message, int $status = 422): never
{
    throw new DentClassOpsTaskException($code, $status, $message);
}

function classops_task_assert_object($value, string $field): array
{
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_task_fail('CLASSOPS_TASK_INVALID_OBJECT', $field . ' must be an object.');
    }
    return $value;
}

function classops_task_assert_keys(array $value, array $allowed, string $field): void
{
    $unknown = array_values(array_diff(array_keys($value), $allowed));
    if ($unknown !== []) {
        sort($unknown, SORT_STRING);
        classops_task_fail('CLASSOPS_TASK_UNKNOWN_FIELD', $field . ' contains unsupported fields: ' . implode(', ', $unknown));
    }
}

function classops_task_text($value, string $field, int $max, bool $required = false): string
{
    if (!is_string($value) && $value !== null) {
        classops_task_fail('CLASSOPS_TASK_INVALID_TEXT', $field . ' must be text.');
    }
    $text = trim((string) $value);
    if ($required && $text === '') {
        classops_task_fail('CLASSOPS_TASK_REQUIRED_FIELD', $field . ' is required.');
    }
    $length = preg_match_all('/./us', $text, $m);
    if ($length === false || $length > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1) {
        classops_task_fail('CLASSOPS_TASK_INVALID_TEXT', $field . ' is invalid.');
    }
    return preg_replace('/\r\n|\r/u', "\n", $text) ?? '';
}

function classops_task_digits($value): string
{
    if (!is_string($value) && !is_int($value)) {
        classops_task_fail('CLASSOPS_TASK_INVALID_STUDENT', 'Canonical student number is invalid.');
    }
    $value = strtr(trim((string) $value), [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
    ]);
    if (preg_match('/^\d{5,20}$/D', $value) !== 1) {
        classops_task_fail('CLASSOPS_TASK_INVALID_STUDENT', 'Canonical student number is invalid.');
    }
    return $value;
}

function classops_task_cohort($value): string
{
    $value = strtolower(classops_task_text($value, 'cohortKey', 80, true));
    if (preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $value) !== 1) {
        classops_task_fail('CLASSOPS_TASK_INVALID_COHORT', 'Canonical cohort is invalid.');
    }
    return $value;
}

function classops_task_ref($value, string $field, int $max = 128): string
{
    $value = classops_task_text($value, $field, $max, true);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,' . ($max - 1) . '}$/D', $value) !== 1) {
        classops_task_fail('CLASSOPS_TASK_INVALID_REF', $field . ' must be canonical.');
    }
    return $value;
}

function classops_task_utc($value, string $field): string
{
    if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value) !== 1) {
        classops_task_fail('CLASSOPS_TASK_INVALID_TIMESTAMP', $field . ' must be canonical UTC.');
    }
    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
    } catch (Throwable $e) {
        classops_task_fail('CLASSOPS_TASK_INVALID_TIMESTAMP', $field . ' is invalid.');
    }
    if ($date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') !== $value) {
        classops_task_fail('CLASSOPS_TASK_INVALID_TIMESTAMP', $field . ' is not canonical UTC.');
    }
    return $value;
}

function classops_task_normalize_metadata($value): array
{
    if ($value === null) {
        return [];
    }
    $value = classops_task_assert_object($value, 'metadata');
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || strlen($encoded) > 4096) {
        classops_task_fail('CLASSOPS_TASK_METADATA_TOO_LARGE', 'Task metadata is too large.');
    }
    $forbidden = ['password','token','secret','otp','cookie','authorization','fileContent','submissionContent'];
    foreach ($value as $key => $entry) {
        if (!is_string($key) || preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,63}$/D', $key) !== 1) {
            classops_task_fail('CLASSOPS_TASK_INVALID_METADATA', 'Task metadata key is invalid.');
        }
        if (in_array(strtolower($key), array_map('strtolower', $forbidden), true) || is_object($entry) || is_resource($entry)) {
            classops_task_fail('CLASSOPS_TASK_PRIVATE_CONTENT_FORBIDDEN', 'Task metadata cannot contain secret/submission content.');
        }
    }
    return $value;
}

function classops_task_normalize_extension(array $value, string $itemType): array
{
    if (!in_array($itemType, ['task', 'requirement'], true)) {
        classops_task_fail('CLASSOPS_TASK_TYPE_REQUIRED', 'Task extension requires task or requirement item type.');
    }
    classops_task_assert_keys($value, ['contractVersion','audienceChangePolicy','audienceResolutionHash','requirement'], 'taskExtension');
    if (($value['contractVersion'] ?? CLASSOPS_TASKS_CONTRACT_VERSION) !== CLASSOPS_TASKS_CONTRACT_VERSION) {
        classops_task_fail('CLASSOPS_TASK_VERSION_UNSUPPORTED', 'Unsupported tasks contract version.');
    }
    $policy = classops_task_text($value['audienceChangePolicy'] ?? 'snapshot', 'audienceChangePolicy', 32, true);
    if (!in_array($policy, ['snapshot','explicit_re_evaluation'], true)) {
        classops_task_fail('CLASSOPS_TASK_AUDIENCE_POLICY_INVALID', 'Unsupported audience change policy.');
    }
    $hash = $value['audienceResolutionHash'] ?? null;
    if ($hash !== null && (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1)) {
        classops_task_fail('CLASSOPS_TASK_AUDIENCE_HASH_INVALID', 'Audience resolution hash is invalid.');
    }
    $requirement = null;
    if ($itemType === 'requirement') {
        $requirement = classops_task_assert_object($value['requirement'] ?? [], 'requirement');
        classops_task_assert_keys($requirement, ['kind','targetCount'], 'requirement');
        $kind = classops_task_text($requirement['kind'] ?? 'binary', 'requirement.kind', 16, true);
        if (!in_array($kind, ['binary','count'], true)) {
            classops_task_fail('CLASSOPS_REQUIREMENT_KIND_INVALID', 'Requirement kind is invalid.');
        }
        $target = $requirement['targetCount'] ?? ($kind === 'binary' ? 1 : null);
        if (!is_int($target) || $target < 1 || $target > CLASSOPS_TASKS_MAX_TARGET || ($kind === 'binary' && $target !== 1)) {
            classops_task_fail('CLASSOPS_REQUIREMENT_TARGET_INVALID', 'Requirement target is invalid.');
        }
        $requirement = ['kind'=>$kind,'targetCount'=>$target];
    } elseif (array_key_exists('requirement', $value) && $value['requirement'] !== null) {
        classops_task_fail('CLASSOPS_TASK_REQUIREMENT_FORBIDDEN', 'Task item cannot contain requirement rules.');
    }
    return [
        'contractVersion'=>CLASSOPS_TASKS_CONTRACT_VERSION,
        'audienceChangePolicy'=>$policy,
        'audienceResolutionHash'=>$hash,
        'requirement'=>$requirement,
    ];
}

function classops_task_states(): array
{
    return ['pending','submitted','needs_revision','completed','waived'];
}

function classops_task_allowed_targets(string $state): array
{
    return match ($state) {
        'pending' => ['submitted','completed','waived'],
        'submitted' => ['needs_revision','completed','waived'],
        'needs_revision' => ['submitted','completed','waived'],
        'completed', 'waived' => ['pending'],
        default => [],
    };
}

function classops_task_new_state(string $itemId, int $itemRevision, string $cohortKey, $studentNumber, string $atUtc): array
{
    $itemId = classops_task_ref($itemId, 'itemId');
    if ($itemRevision < 1) {
        classops_task_fail('CLASSOPS_TASK_ITEM_REVISION_INVALID', 'Item revision is invalid.');
    }
    return [
        'contractVersion'=>CLASSOPS_TASKS_STATE_VERSION,
        'itemId'=>$itemId,
        'itemRevision'=>$itemRevision,
        'cohortKey'=>classops_task_cohort($cohortKey),
        'studentNumber'=>classops_task_digits($studentNumber),
        'state'=>'pending',
        'stateRevision'=>1,
        'progressCount'=>0,
        'updatedAt'=>classops_task_utc($atUtc, 'atUtc'),
        'history'=>[],
    ];
}

function classops_task_validate_state(array $state): array
{
    classops_task_assert_keys($state, ['contractVersion','itemId','itemRevision','cohortKey','studentNumber','state','stateRevision','progressCount','updatedAt','history'], 'taskState');
    if (($state['contractVersion'] ?? null) !== CLASSOPS_TASKS_STATE_VERSION) {
        classops_task_fail('CLASSOPS_TASK_STATE_VERSION_UNSUPPORTED', 'Unsupported task state version.');
    }
    classops_task_ref($state['itemId'] ?? null, 'itemId');
    if (!is_int($state['itemRevision'] ?? null) || $state['itemRevision'] < 1 || !is_int($state['stateRevision'] ?? null) || $state['stateRevision'] < 1) {
        classops_task_fail('CLASSOPS_TASK_STATE_REVISION_INVALID', 'Task state revision is invalid.');
    }
    classops_task_cohort($state['cohortKey'] ?? null);
    classops_task_digits($state['studentNumber'] ?? null);
    if (!in_array($state['state'] ?? null, classops_task_states(), true)) {
        classops_task_fail('CLASSOPS_TASK_STATE_INVALID', 'Task state is invalid.');
    }
    if (!is_int($state['progressCount'] ?? null) || $state['progressCount'] < 0 || $state['progressCount'] > CLASSOPS_TASKS_MAX_TARGET) {
        classops_task_fail('CLASSOPS_TASK_PROGRESS_INVALID', 'Task progress is invalid.');
    }
    classops_task_utc($state['updatedAt'] ?? null, 'updatedAt');
    if (!is_array($state['history'] ?? null) || !array_is_list($state['history']) || count($state['history']) > CLASSOPS_TASKS_MAX_HISTORY) {
        classops_task_fail('CLASSOPS_TASK_HISTORY_INVALID', 'Task history is invalid.');
    }
    return $state;
}

function classops_task_transition(array $state, int $expectedStateRevision, string $target, string $commandId, string $actorRef, string $atUtc, string $reason = '', array $metadata = []): array
{
    $state = classops_task_validate_state($state);
    $commandId = classops_task_ref($commandId, 'commandId');
    $actorRef = classops_task_ref($actorRef, 'actorRef');
    $atUtc = classops_task_utc($atUtc, 'atUtc');
    $reason = classops_task_text($reason, 'reason', 500);
    $metadata = classops_task_normalize_metadata($metadata);
    if (!in_array($target, classops_task_states(), true)) {
        classops_task_fail('CLASSOPS_TASK_TARGET_INVALID', 'Target task state is invalid.');
    }
    foreach ($state['history'] as $event) {
        if (($event['commandId'] ?? null) !== $commandId) {
            continue;
        }
        if (($event['to'] ?? null) !== $target) {
            classops_task_fail('CLASSOPS_TASK_IDEMPOTENCY_CONFLICT', 'Command id was already used for another transition.', 409);
        }
        return $state;
    }
    if ($state['stateRevision'] !== $expectedStateRevision) {
        classops_task_fail('CLASSOPS_TASK_REVISION_CONFLICT', 'Task state revision is stale.', 409);
    }
    $from = (string) $state['state'];
    if (!in_array($target, classops_task_allowed_targets($from), true)) {
        classops_task_fail('CLASSOPS_TASK_TRANSITION_FORBIDDEN', 'Task state transition is not allowed.', 409);
    }
    $event = [
        'commandId'=>$commandId,
        'from'=>$from,
        'to'=>$target,
        'actorRef'=>$actorRef,
        'at'=>$atUtc,
        'reason'=>$reason,
        'metadata'=>$metadata,
    ];
    $state['history'][] = $event;
    if (count($state['history']) > CLASSOPS_TASKS_MAX_HISTORY) {
        $state['history'] = array_slice($state['history'], -CLASSOPS_TASKS_MAX_HISTORY);
    }
    $state['state'] = $target;
    $state['stateRevision']++;
    $state['updatedAt'] = $atUtc;
    return $state;
}

function classops_requirement_progress(array $taskExtension, int $progressCount): array
{
    $extension = classops_task_normalize_extension($taskExtension, 'requirement');
    if ($progressCount < 0 || $progressCount > CLASSOPS_TASKS_MAX_TARGET) {
        classops_task_fail('CLASSOPS_TASK_PROGRESS_INVALID', 'Requirement progress is invalid.');
    }
    $target = (int) $extension['requirement']['targetCount'];
    return [
        'current'=>$progressCount,
        'target'=>$target,
        'remaining'=>max(0, $target - $progressCount),
        'satisfied'=>$progressCount >= $target,
    ];
}

function classops_task_set_progress(array $state, int $expectedStateRevision, int $progressCount, string $commandId, string $actorRef, string $atUtc): array
{
    $state = classops_task_validate_state($state);
    if ($progressCount < 0 || $progressCount > CLASSOPS_TASKS_MAX_TARGET) {
        classops_task_fail('CLASSOPS_TASK_PROGRESS_INVALID', 'Progress is invalid.');
    }
    $commandId = classops_task_ref($commandId, 'commandId');
    foreach ($state['history'] as $event) {
        if (($event['commandId'] ?? null) === $commandId) {
            if (($event['kind'] ?? null) !== 'progress' || ($event['progressCount'] ?? null) !== $progressCount) {
                classops_task_fail('CLASSOPS_TASK_IDEMPOTENCY_CONFLICT', 'Command id was already used for another mutation.', 409);
            }
            return $state;
        }
    }
    if ($state['stateRevision'] !== $expectedStateRevision) {
        classops_task_fail('CLASSOPS_TASK_REVISION_CONFLICT', 'Task state revision is stale.', 409);
    }
    $event = ['kind'=>'progress','commandId'=>$commandId,'progressCount'=>$progressCount,'actorRef'=>classops_task_ref($actorRef, 'actorRef'),'at'=>classops_task_utc($atUtc, 'atUtc')];
    $state['history'][] = $event;
    $state['progressCount'] = $progressCount;
    $state['stateRevision']++;
    $state['updatedAt'] = $event['at'];
    return $state;
}

function classops_task_is_overdue(array $state, ?string $dueAtUtc, string $nowUtc): bool
{
    $state = classops_task_validate_state($state);
    if ($dueAtUtc === null || in_array($state['state'], ['completed','waived'], true)) {
        return false;
    }
    return strcmp(classops_task_utc($nowUtc, 'nowUtc'), classops_task_utc($dueAtUtc, 'dueAtUtc')) > 0;
}

function classops_task_student_projection(array $state, $viewerStudentNumber, ?string $dueAtUtc, string $nowUtc): array
{
    $state = classops_task_validate_state($state);
    $viewer = classops_task_digits($viewerStudentNumber);
    if (!hash_equals((string) $state['studentNumber'], $viewer)) {
        classops_task_fail('CLASSOPS_TASK_FORBIDDEN', 'Task state is private to its canonical student.', 403);
    }
    return [
        'contractVersion'=>'classops-task-projection-v1',
        'itemId'=>$state['itemId'],
        'itemRevision'=>$state['itemRevision'],
        'state'=>$state['state'],
        'stateRevision'=>$state['stateRevision'],
        'progressCount'=>$state['progressCount'],
        'overdue'=>classops_task_is_overdue($state, $dueAtUtc, $nowUtc),
        'updatedAt'=>$state['updatedAt'],
    ];
}
