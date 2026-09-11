<?php
declare(strict_types=1);

const CLASSOPS_CRITICAL_ACK_CONTRACT_VERSION = 'classops-exam-ack-v1';
const CLASSOPS_CRITICAL_ACK_STATE_VERSION = 'classops-critical-ack-state-v1';
const CLASSOPS_CRITICAL_ACK_RECORD_VERSION = 'classops-critical-ack-record-v1';
const CLASSOPS_CRITICAL_ACK_ELIGIBILITY_VERSION = 'classops-audience-eligibility-v1';
const CLASSOPS_CRITICAL_ACK_PROJECTION_VERSION = 'classops-critical-ack-projection-v1';

final class DentClassOpsCriticalAckException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly int $statusCode = 422
    ) {
        parent::__construct($message);
    }
}

function classops_ack_fail(string $reasonCode, string $message, int $statusCode = 422): never
{
    throw new DentClassOpsCriticalAckException($reasonCode, $message, $statusCode);
}

function classops_ack_assert_object(array $value, string $field): void
{
    if ($value !== [] && array_is_list($value)) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_OBJECT', "{$field} must be an object.");
    }
}

function classops_ack_assert_known_keys(array $value, array $allowed, string $field): void
{
    classops_ack_assert_object($value, $field);
    $unknown = array_values(array_diff(array_keys($value), $allowed));
    if ($unknown !== []) {
        classops_ack_fail(
            'CLASSOPS_ACK_UNKNOWN_FIELD',
            $field . ' contains unsupported field(s): ' . implode(', ', $unknown)
        );
    }
}

function classops_ack_student_number($value, string $field = 'studentNumber'): string
{
    if (!is_string($value) && !is_int($value)) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_STUDENT_NUMBER', "{$field} must be a canonical student number.");
    }
    $studentNumber = trim((string) $value);
    if (preg_match('/^[0-9]{4,32}$/D', $studentNumber) !== 1) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_STUDENT_NUMBER', "{$field} must be canonical ASCII digits.");
    }
    return $studentNumber;
}

function classops_ack_item_id($value): string
{
    if (!is_string($value) || preg_match('/^cop_[a-f0-9]{16,64}$/D', $value) !== 1) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_ITEM_ID', 'ClassOps item id is invalid.');
    }
    return $value;
}

function classops_ack_revision($value, string $field = 'revision'): int
{
    $revision = filter_var($value, FILTER_VALIDATE_INT);
    if ($revision === false || $revision < 1) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_REVISION', "{$field} must be a positive integer.");
    }
    return (int) $revision;
}

function classops_ack_utc_timestamp($value, string $field = 'ackedAt'): string
{
    if (!is_string($value) || strlen($value) > 64 || preg_match('/(?:Z|[+-]\\d{2}:\\d{2})$/', $value) !== 1) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_TIMESTAMP', "{$field} must be offset-aware ISO-8601.");
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_TIMESTAMP', "{$field} is invalid.");
    }
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
}

function classops_ack_idempotency_key($value): string
{
    if (!is_string($value)) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_IDEMPOTENCY_KEY', 'idempotencyKey must be text.');
    }
    $key = trim($value);
    if (strlen($key) < 8 || strlen($key) > 128 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/D', $key) !== 1) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_IDEMPOTENCY_KEY', 'idempotencyKey format is invalid.');
    }
    return $key;
}

function classops_ack_empty_state(): array
{
    return [
        'contractVersion' => CLASSOPS_CRITICAL_ACK_STATE_VERSION,
        'acks' => [],
        'idempotency' => [],
        'history' => [],
    ];
}

function classops_ack_tuple_key(string $itemId, int $revision, string $studentNumber): string
{
    return hash('sha256', 'classops-critical-ack-tuple-v1|' . $itemId . '|' . $revision . '|' . $studentNumber);
}

function classops_ack_idempotency_index_key(string $studentNumber, string $idempotencyKey): string
{
    return hash('sha256', 'classops-critical-ack-idem-v1|' . $studentNumber . '|' . $idempotencyKey);
}

function classops_ack_payload_fingerprint(string $itemId, int $revision, string $studentNumber): string
{
    return hash('sha256', 'classops-critical-ack-payload-v1|' . $itemId . '|' . $revision . '|' . $studentNumber);
}

function classops_ack_actor_fingerprint(string $itemId, int $revision, string $studentNumber): string
{
    // Item/revision scoping prevents one audit pseudonym from linking the actor across unrelated notices.
    return hash('sha256', 'classops-critical-ack-actor-v1|' . $itemId . '|' . $revision . '|' . $studentNumber);
}

function classops_ack_idempotency_fingerprint(string $idempotencyKey): string
{
    return hash('sha256', 'classops-critical-ack-idem-audit-v1|' . $idempotencyKey);
}

function classops_ack_normalize_record(array $record): array
{
    classops_ack_assert_known_keys(
        $record,
        ['contractVersion', 'ackId', 'itemId', 'revision', 'studentNumber', 'ackedAt', 'idempotencyKey', 'intent'],
        'ackRecord'
    );
    if (($record['contractVersion'] ?? null) !== CLASSOPS_CRITICAL_ACK_RECORD_VERSION) {
        classops_ack_fail('CLASSOPS_ACK_RECORD_VERSION_UNSUPPORTED', 'ACK record version is unsupported.', 500);
    }
    $itemId = classops_ack_item_id($record['itemId'] ?? null);
    $revision = classops_ack_revision($record['revision'] ?? null);
    $studentNumber = classops_ack_student_number($record['studentNumber'] ?? null);
    $ackedAt = classops_ack_utc_timestamp($record['ackedAt'] ?? null);
    $idempotencyKey = classops_ack_idempotency_key($record['idempotencyKey'] ?? null);
    if (($record['intent'] ?? null) !== 'explicit_user_ack') {
        classops_ack_fail('CLASSOPS_ACK_EXPLICIT_INTENT_REQUIRED', 'ACK record intent must be explicit_user_ack.', 500);
    }
    $ackId = $record['ackId'] ?? null;
    $expectedAckId = 'ack_' . substr(classops_ack_tuple_key($itemId, $revision, $studentNumber), 0, 24);
    if (!is_string($ackId) || !hash_equals($expectedAckId, $ackId)) {
        classops_ack_fail('CLASSOPS_ACK_RECORD_ID_INVALID', 'ACK record id is invalid.', 500);
    }
    return [
        'contractVersion' => CLASSOPS_CRITICAL_ACK_RECORD_VERSION,
        'ackId' => $ackId,
        'itemId' => $itemId,
        'revision' => $revision,
        'studentNumber' => $studentNumber,
        'ackedAt' => $ackedAt,
        'idempotencyKey' => $idempotencyKey,
        'intent' => 'explicit_user_ack',
    ];
}

function classops_ack_normalize_state(array $state): array
{
    classops_ack_assert_known_keys($state, ['contractVersion', 'acks', 'idempotency', 'history'], 'ackState');
    if (($state['contractVersion'] ?? null) !== CLASSOPS_CRITICAL_ACK_STATE_VERSION) {
        classops_ack_fail('CLASSOPS_ACK_STATE_VERSION_UNSUPPORTED', 'ACK state version is unsupported.', 500);
    }
    foreach (['acks', 'idempotency', 'history'] as $field) {
        if (!is_array($state[$field] ?? null)) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', "ACK state {$field} is invalid.", 500);
        }
    }
    if (array_is_list($state['acks']) && $state['acks'] !== []) {
        classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK state acks must be an object map.', 500);
    }
    if (array_is_list($state['idempotency']) && $state['idempotency'] !== []) {
        classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK state idempotency must be an object map.', 500);
    }
    if (!array_is_list($state['history'])) {
        classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK state history must be a list.', 500);
    }

    $acks = [];
    foreach ($state['acks'] as $key => $record) {
        if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || !is_array($record)) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK state contains an invalid ACK key.', 500);
        }
        $normalized = classops_ack_normalize_record($record);
        $expectedKey = classops_ack_tuple_key($normalized['itemId'], $normalized['revision'], $normalized['studentNumber']);
        if (!hash_equals($expectedKey, $key)) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK state tuple index is corrupt.', 500);
        }
        $acks[$key] = $normalized;
    }

    $idempotency = [];
    foreach ($state['idempotency'] as $key => $entry) {
        if (!is_string($key) || preg_match('/^[a-f0-9]{64}$/D', $key) !== 1 || !is_array($entry)) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK idempotency index is invalid.', 500);
        }
        classops_ack_assert_known_keys($entry, ['payloadFingerprint', 'ackKey'], 'ackState.idempotency');
        $payloadFingerprint = (string) ($entry['payloadFingerprint'] ?? '');
        $ackKey = (string) ($entry['ackKey'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $payloadFingerprint) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $ackKey) !== 1
            || !isset($acks[$ackKey])) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK idempotency index references invalid state.', 500);
        }
        $idempotency[$key] = ['payloadFingerprint' => $payloadFingerprint, 'ackKey' => $ackKey];
    }

    $history = [];
    foreach ($state['history'] as $event) {
        if (!is_array($event)) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK history event is invalid.', 500);
        }
        classops_ack_assert_known_keys(
            $event,
            ['event', 'ackId', 'itemId', 'revision', 'actorFingerprint', 'idempotencyFingerprint', 'at'],
            'ackState.history'
        );
        if (($event['event'] ?? null) !== 'critical_ack_recorded') {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK history event type is invalid.', 500);
        }
        foreach (['actorFingerprint', 'idempotencyFingerprint'] as $hashField) {
            if (preg_match('/^[a-f0-9]{64}$/D', (string) ($event[$hashField] ?? '')) !== 1) {
                classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK history fingerprint is invalid.', 500);
            }
        }
        if (preg_match('/^ack_[a-f0-9]{24}$/D', (string) ($event['ackId'] ?? '')) !== 1) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'ACK history id is invalid.', 500);
        }
        $history[] = [
            'event' => 'critical_ack_recorded',
            'ackId' => (string) ($event['ackId'] ?? ''),
            'itemId' => classops_ack_item_id($event['itemId'] ?? null),
            'revision' => classops_ack_revision($event['revision'] ?? null),
            'actorFingerprint' => (string) $event['actorFingerprint'],
            'idempotencyFingerprint' => (string) $event['idempotencyFingerprint'],
            'at' => classops_ack_utc_timestamp($event['at'] ?? null, 'history.at'),
        ];
    }

    return [
        'contractVersion' => CLASSOPS_CRITICAL_ACK_STATE_VERSION,
        'acks' => $acks,
        'idempotency' => $idempotency,
        'history' => $history,
    ];
}

