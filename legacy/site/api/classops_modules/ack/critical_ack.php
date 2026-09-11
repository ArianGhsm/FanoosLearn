<?php
declare(strict_types=1);

require_once __DIR__ . '/ack_state.php';

function classops_ack_assert_notice_identity(array $notice): void
{
    if (($notice['type'] ?? null) !== 'critical_notice') {
        classops_ack_fail('CLASSOPS_ACK_CRITICAL_NOTICE_REQUIRED', 'ACK is only valid for critical_notice items.');
    }
    classops_ack_item_id($notice['id'] ?? null);
    classops_ack_revision($notice['revision'] ?? null);
    if (($notice['requireAck'] ?? null) !== true) {
        classops_ack_fail('CLASSOPS_ACK_NOT_REQUIRED', 'Notice does not require acknowledgement.');
    }
}

function classops_ack_assert_notice_ackable(array $notice): void
{
    classops_ack_assert_notice_identity($notice);
    $status = (string) ($notice['status'] ?? '');
    if (!in_array($status, ['scheduled', 'active'], true)) {
        classops_ack_fail('CLASSOPS_ACK_NOTICE_NOT_ACKABLE', 'Notice is not in an acknowledgement-capable state.', 409);
    }
}

function classops_ack_normalize_actor(array $actor): array
{
    classops_ack_assert_known_keys($actor, ['studentNumber', 'role'], 'actor');
    $studentNumber = classops_ack_student_number($actor['studentNumber'] ?? null);
    $role = (string) ($actor['role'] ?? 'student');
    if ($role !== 'student') {
        classops_ack_fail('CLASSOPS_ACK_STUDENT_ACTOR_REQUIRED', 'ACK actor must be a canonical student actor.', 403);
    }
    return ['studentNumber' => $studentNumber, 'role' => 'student'];
}

function classops_ack_normalize_eligibility(array $eligibility): array
{
    classops_ack_assert_known_keys(
        $eligibility,
        ['contractVersion', 'itemId', 'revision', 'studentNumber', 'eligible', 'audienceFingerprint'],
        'eligibility'
    );
    if (($eligibility['contractVersion'] ?? null) !== CLASSOPS_CRITICAL_ACK_ELIGIBILITY_VERSION) {
        classops_ack_fail('CLASSOPS_ACK_ELIGIBILITY_VERSION_UNSUPPORTED', 'Audience eligibility proof version is unsupported.', 403);
    }
    if (!is_bool($eligibility['eligible'] ?? null)) {
        classops_ack_fail('CLASSOPS_ACK_ELIGIBILITY_INVALID', 'Audience eligibility proof is invalid.', 403);
    }
    $audienceFingerprint = (string) ($eligibility['audienceFingerprint'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/D', $audienceFingerprint) !== 1) {
        classops_ack_fail('CLASSOPS_ACK_ELIGIBILITY_INVALID', 'Audience fingerprint is invalid.', 403);
    }
    return [
        'contractVersion' => CLASSOPS_CRITICAL_ACK_ELIGIBILITY_VERSION,
        'itemId' => classops_ack_item_id($eligibility['itemId'] ?? null),
        'revision' => classops_ack_revision($eligibility['revision'] ?? null, 'eligibility.revision'),
        'studentNumber' => classops_ack_student_number($eligibility['studentNumber'] ?? null, 'eligibility.studentNumber'),
        'eligible' => $eligibility['eligible'],
        'audienceFingerprint' => $audienceFingerprint,
    ];
}

function classops_ack_assert_eligible(array $notice, array $actor, array $eligibility): void
{
    $noticeId = classops_ack_item_id($notice['id'] ?? null);
    $noticeRevision = classops_ack_revision($notice['revision'] ?? null);
    if ($eligibility['itemId'] !== $noticeId
        || $eligibility['revision'] !== $noticeRevision
        || $eligibility['studentNumber'] !== $actor['studentNumber']
        || $eligibility['eligible'] !== true) {
        classops_ack_fail('CLASSOPS_ACK_NOT_ELIGIBLE', 'Actor is not eligible for this exact notice revision.', 403);
    }
}

function classops_ack_record(
    array $state,
    array $notice,
    array $actor,
    array $eligibility,
    int $expectedRevision,
    string $idempotencyKey,
    string $ackedAt,
    string $intent = 'explicit_user_ack'
): array {
    $state = classops_ack_normalize_state($state);
    classops_ack_assert_notice_ackable($notice);
    $actor = classops_ack_normalize_actor($actor);
    $eligibility = classops_ack_normalize_eligibility($eligibility);
    $noticeRevision = classops_ack_revision($notice['revision'] ?? null);
    if ($expectedRevision !== $noticeRevision) {
        classops_ack_fail('CLASSOPS_ACK_STALE_REVISION', 'ACK targets a stale notice revision.', 409);
    }
    if ($intent !== 'explicit_user_ack') {
        classops_ack_fail('CLASSOPS_ACK_EXPLICIT_INTENT_REQUIRED', 'Delivery/read receipts are not acknowledgements.', 422);
    }
    classops_ack_assert_eligible($notice, $actor, $eligibility);

    $itemId = classops_ack_item_id($notice['id'] ?? null);
    $studentNumber = $actor['studentNumber'];
    $idempotencyKey = classops_ack_idempotency_key($idempotencyKey);
    $ackedAt = classops_ack_utc_timestamp($ackedAt);
    $ackKey = classops_ack_tuple_key($itemId, $noticeRevision, $studentNumber);
    $payloadFingerprint = classops_ack_payload_fingerprint($itemId, $noticeRevision, $studentNumber);
    $idempotencyIndexKey = classops_ack_idempotency_index_key($studentNumber, $idempotencyKey);

    if (isset($state['idempotency'][$idempotencyIndexKey])) {
        $entry = $state['idempotency'][$idempotencyIndexKey];
        if (!hash_equals((string) $entry['payloadFingerprint'], $payloadFingerprint)) {
            classops_ack_fail('CLASSOPS_ACK_IDEMPOTENCY_CONFLICT', 'Idempotency key was already used for another ACK payload.', 409);
        }
        $storedAckKey = (string) $entry['ackKey'];
        if (!isset($state['acks'][$storedAckKey])) {
            classops_ack_fail('CLASSOPS_ACK_STATE_INVALID', 'Idempotency entry points to a missing ACK.', 500);
        }
        return [
            'state' => $state,
            'ack' => $state['acks'][$storedAckKey],
            'idempotentReplay' => true,
            'alreadyAcknowledged' => true,
            'stateChanged' => false,
        ];
    }

    if (isset($state['acks'][$ackKey])) {
        $state['idempotency'][$idempotencyIndexKey] = [
            'payloadFingerprint' => $payloadFingerprint,
            'ackKey' => $ackKey,
        ];
        return [
            'state' => $state,
            'ack' => $state['acks'][$ackKey],
            'idempotentReplay' => false,
            'alreadyAcknowledged' => true,
            'stateChanged' => true,
        ];
    }

    $ack = [
        'contractVersion' => CLASSOPS_CRITICAL_ACK_RECORD_VERSION,
        'ackId' => 'ack_' . substr($ackKey, 0, 24),
        'itemId' => $itemId,
        'revision' => $noticeRevision,
        'studentNumber' => $studentNumber,
        'ackedAt' => $ackedAt,
        'idempotencyKey' => $idempotencyKey,
        'intent' => 'explicit_user_ack',
    ];
    $ack = classops_ack_normalize_record($ack);
    $state['acks'][$ackKey] = $ack;
    $state['idempotency'][$idempotencyIndexKey] = [
        'payloadFingerprint' => $payloadFingerprint,
        'ackKey' => $ackKey,
    ];
    $state['history'][] = [
        'event' => 'critical_ack_recorded',
        'ackId' => $ack['ackId'],
        'itemId' => $itemId,
        'revision' => $noticeRevision,
        'actorFingerprint' => classops_ack_actor_fingerprint($itemId, $noticeRevision, $studentNumber),
        'idempotencyFingerprint' => classops_ack_idempotency_fingerprint($idempotencyKey),
        'at' => $ackedAt,
    ];

    return [
        'state' => classops_ack_normalize_state($state),
        'ack' => $ack,
        'idempotentReplay' => false,
        'alreadyAcknowledged' => false,
        'stateChanged' => true,
    ];
}

function classops_ack_from_transport_receipt(array $receipt): never
{
    // Transport delivery/read state may be useful for delivery telemetry, but it can never create canonical ACK state.
    classops_ack_fail('CLASSOPS_ACK_EXPLICIT_INTENT_REQUIRED', 'Transport delivery/read receipt is not an acknowledgement.');
}

function classops_ack_is_satisfied(array $state, array $notice, string $studentNumber): bool
{
    $state = classops_ack_normalize_state($state);
    classops_ack_assert_notice_identity($notice);
    $itemId = classops_ack_item_id($notice['id'] ?? null);
    $revision = classops_ack_revision($notice['revision'] ?? null);
    $studentNumber = classops_ack_student_number($studentNumber);
    return isset($state['acks'][classops_ack_tuple_key($itemId, $revision, $studentNumber)]);
}

function classops_ack_owner_stats(array $state, array $notice, array $eligibleStudentNumbers): array
{
    $state = classops_ack_normalize_state($state);
    classops_ack_assert_notice_identity($notice);
    if (!array_is_list($eligibleStudentNumbers)) {
        classops_ack_fail('CLASSOPS_ACK_INVALID_ELIGIBLE_SET', 'Eligible student set must be a list.');
    }
    $eligible = [];
    foreach ($eligibleStudentNumbers as $studentNumber) {
        $normalized = classops_ack_student_number($studentNumber, 'eligibleStudentNumbers[]');
        $eligible[$normalized] = true;
    }
    $itemId = classops_ack_item_id($notice['id'] ?? null);
    $revision = classops_ack_revision($notice['revision'] ?? null);
    $acked = 0;
    foreach (array_keys($eligible) as $studentNumber) {
        $studentNumber = (string) $studentNumber;
        if (isset($state['acks'][classops_ack_tuple_key($itemId, $revision, $studentNumber)])) {
            $acked++;
        }
    }
    $eligibleCount = count($eligible);
    return [
        'itemId' => $itemId,
        'revision' => $revision,
        'eligible' => $eligibleCount,
        'acked' => $acked,
        'pending' => $eligibleCount - $acked,
    ];
}

function classops_ack_actor_projection(
    array $state,
    array $notice,
    array $actor,
    array $eligibility
): array {
    $state = classops_ack_normalize_state($state);
    classops_ack_assert_notice_identity($notice);
    $actor = classops_ack_normalize_actor($actor);
    $eligibility = classops_ack_normalize_eligibility($eligibility);
    classops_ack_assert_eligible($notice, $actor, $eligibility);
    $itemId = classops_ack_item_id($notice['id'] ?? null);
    $revision = classops_ack_revision($notice['revision'] ?? null);
    $ackKey = classops_ack_tuple_key($itemId, $revision, $actor['studentNumber']);
    $record = $state['acks'][$ackKey] ?? null;
    return [
        'contractVersion' => CLASSOPS_CRITICAL_ACK_PROJECTION_VERSION,
        'itemId' => $itemId,
        'revision' => $revision,
        'eligible' => true,
        'acked' => is_array($record),
        'ackedAt' => is_array($record) ? $record['ackedAt'] : null,
    ];
}
