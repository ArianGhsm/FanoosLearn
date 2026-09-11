<?php
declare(strict_types=1);

require_once __DIR__ . '/resolution.php';

function classops_audience_snapshot_from_result(array $result): array
{
    return [
        'version' => CLASSOPS_AUDIENCE_SNAPSHOT_VERSION,
        'specHash' => (string) $result['specHash'],
        'cohortKey' => (string) $result['cohortKey'],
        'provenance' => $result['provenance'],
        'recipientStudentNumbers' => $result['recipientStudentNumbers'],
        'recipientReasons' => $result['recipientReasons'],
        'unresolvedReferences' => $result['unresolvedReferences'],
        'warnings' => $result['warnings'],
        'deterministicHash' => (string) $result['deterministicHash'],
    ];
}

function classops_audience_snapshot_warning_codes(): array
{
    return [
        'AUDIENCE_DUPLICATE_STUDENT_REF',
        'AUDIENCE_DUPLICATE_EXPRESSION',
        'AUDIENCE_INCLUDE_EXCLUDE_CONFLICT',
        'AUDIENCE_NEGATION_UNRESOLVED',
        'AUDIENCE_CONTRADICTORY_INTERSECTION',
        'AUDIENCE_EMPTY_RESULT',
    ];
}

function classops_audience_snapshot_reason(string $reason): string
{
    $reason = trim($reason);
    if (in_array($reason, ['whole_cohort', 'explicit_student', 'include_student', 'not_expression'], true)) {
        return $reason;
    }
    if (preg_match('/^selector:(?:role|group|category):[A-Za-z0-9][A-Za-z0-9._:-]{0,95}$/', $reason) === 1) {
        return $reason;
    }
    classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot reason is invalid.');
}

function classops_audience_result_from_snapshot(array $snapshot, array $normalizedSpec, array $context): array
{
    classops_audience_assert_known_keys($snapshot, [
        'version', 'specHash', 'cohortKey', 'provenance', 'recipientStudentNumbers',
        'recipientReasons', 'unresolvedReferences', 'warnings', 'deterministicHash',
    ], 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
    if (($snapshot['version'] ?? '') !== CLASSOPS_AUDIENCE_SNAPSHOT_VERSION) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot version is invalid.');
    }
    $cohortKey = classops_audience_normalize_cohort_key($snapshot['cohortKey'] ?? '');
    if ($cohortKey !== $context['cohortKey']) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_COHORT_MISMATCH', 'Audience snapshot belongs to another cohort.');
    }
    $specHash = classops_audience_hash($normalizedSpec);
    if (!hash_equals($specHash, (string) ($snapshot['specHash'] ?? ''))) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_SPEC_MISMATCH', 'Audience snapshot does not match the current audience specification.');
    }

    $snapshotProvenance = classops_audience_assert_object(
        $snapshot['provenance'] ?? null,
        'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID'
    );
    classops_audience_assert_known_keys(
        $snapshotProvenance,
        ['source', 'wholeCohort', 'selectors'],
        'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID'
    );
    $source = classops_audience_normalize_source(classops_audience_assert_object(
        $snapshotProvenance['source'] ?? null,
        'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID'
    ));
    if (!is_bool($snapshotProvenance['wholeCohort'] ?? null)) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot provenance is invalid.');
    }
    $selectorProvenanceRaw = $snapshotProvenance['selectors'] ?? null;
    if (!is_array($selectorProvenanceRaw)
        || !array_is_list($selectorProvenanceRaw)
        || count($selectorProvenanceRaw) > CLASSOPS_AUDIENCE_MAX_SELECTORS) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot selector provenance is invalid.');
    }
    $selectorProvenanceMap = [];
    foreach ($selectorProvenanceRaw as $row) {
        $row = classops_audience_assert_object($row, 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        classops_audience_assert_known_keys($row, ['selectorId', 'sourceRef'], 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        $selectorId = (string) ($row['selectorId'] ?? '');
        $sourceRef = (string) ($row['sourceRef'] ?? '');
        if (preg_match('/^(?:role|group|category):[A-Za-z0-9][A-Za-z0-9._:-]{0,95}$/', $selectorId) !== 1
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,119}$/', $sourceRef) !== 1) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot selector provenance is invalid.');
        }
        if (isset($selectorProvenanceMap[$selectorId])) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot selector provenance contains duplicates.');
        }
        $selectorProvenanceMap[$selectorId] = $sourceRef;
    }
    ksort($selectorProvenanceMap, SORT_STRING);
    $selectorProvenance = [];
    foreach ($selectorProvenanceMap as $selectorId => $sourceRef) {
        $selectorProvenance[] = ['selectorId' => $selectorId, 'sourceRef' => $sourceRef];
    }
    $provenance = [
        'source' => $source,
        'wholeCohort' => $snapshotProvenance['wholeCohort'],
        'selectors' => $selectorProvenance,
    ];

    $recipientRaw = $snapshot['recipientStudentNumbers'] ?? null;
    if (!is_array($recipientRaw) || !array_is_list($recipientRaw) || count($recipientRaw) > CLASSOPS_AUDIENCE_MAX_ROSTER) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot recipient set is invalid.');
    }
    $recipientSet = [];
    foreach ($recipientRaw as $value) {
        $studentNumber = classops_audience_normalize_student_number($value);
        $recipientSet[classops_audience_student_key($studentNumber)] = $studentNumber;
    }
    $recipients = array_values($recipientSet);
    sort($recipients, SORT_STRING);
    foreach ($recipients as $studentNumber) {
        $knownCohort = $context['identityCohorts'][classops_audience_student_key($studentNumber)] ?? null;
        if (is_string($knownCohort) && $knownCohort !== $cohortKey) {
            classops_audience_error(
                'CLASSOPS_AUDIENCE_CROSS_COHORT_REFERENCE',
                'Audience snapshot contains an identity that now belongs to another cohort.'
            );
        }
    }
    if (count($recipients) !== count($recipientRaw)) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot contains duplicate recipients.');
    }

    $reasonsRaw = $snapshot['recipientReasons'] ?? null;
    if (!is_array($reasonsRaw) || !array_is_list($reasonsRaw) || count($reasonsRaw) !== count($recipients)) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot reasons are invalid.');
    }
    $reasonMap = [];
    foreach ($reasonsRaw as $row) {
        $row = classops_audience_assert_object($row, 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        classops_audience_assert_known_keys($row, ['studentNumber', 'reasons'], 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        $studentNumber = classops_audience_normalize_student_number($row['studentNumber'] ?? '');
        $studentKey = classops_audience_student_key($studentNumber);
        if (!isset($recipientSet[$studentKey])) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot reason references a non-recipient.');
        }
        if (isset($reasonMap[$studentKey])) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot contains duplicate recipient reasons.');
        }
        $reasonList = $row['reasons'] ?? null;
        if (!is_array($reasonList) || !array_is_list($reasonList) || count($reasonList) < 1 || count($reasonList) > 32) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot reason list is invalid.');
        }
        $safeReasons = [];
        foreach ($reasonList as $reason) {
            $normalizedReason = classops_audience_snapshot_reason((string) $reason);
            if (isset($safeReasons[$normalizedReason])) {
                classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot reason list contains duplicates.');
            }
            $safeReasons[$normalizedReason] = true;
        }
        $keys = array_keys($safeReasons);
        sort($keys, SORT_STRING);
        $reasonMap[$studentKey] = ['studentNumber' => $studentNumber, 'reasons' => $keys];
    }
    if (count($reasonMap) !== count($recipientSet)) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot is missing recipient reasons.');
    }
    ksort($reasonMap, SORT_STRING);
    $recipientReasons = array_values($reasonMap);

    $unresolved = $snapshot['unresolvedReferences'] ?? null;
    if (!is_array($unresolved) || !array_is_list($unresolved) || count($unresolved) > CLASSOPS_AUDIENCE_MAX_TOTAL_STUDENT_REFS + CLASSOPS_AUDIENCE_MAX_SELECTORS) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot unresolved list is invalid.');
    }
    $normalizedUnresolved = [];
    $unresolvedSeen = [];
    foreach ($unresolved as $row) {
        $row = classops_audience_assert_object($row, 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        classops_audience_assert_known_keys($row, ['kind', 'reference', 'code'], 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        $kind = (string) ($row['kind'] ?? '');
        $reference = (string) ($row['reference'] ?? '');
        $code = (string) ($row['code'] ?? '');
        if ($kind === 'student') {
            $reference = classops_audience_normalize_student_number($reference);
            if ($code !== 'AUDIENCE_STUDENT_NOT_FOUND') {
                classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot unresolved code does not match its kind.');
            }
        } elseif ($kind === 'selector') {
            if (preg_match('/^(?:role|group|category):[A-Za-z0-9][A-Za-z0-9._:-]{0,95}$/', $reference) !== 1) {
                classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot unresolved selector is invalid.');
            }
            if ($code !== 'AUDIENCE_SELECTOR_NOT_FOUND') {
                classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot unresolved code does not match its kind.');
            }
        } else {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot unresolved kind is invalid.');
        }
        $unresolvedKey = $kind . '|' . $reference . '|' . $code;
        if (isset($unresolvedSeen[$unresolvedKey])) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot unresolved list contains duplicates.');
        }
        $unresolvedSeen[$unresolvedKey] = true;
        $normalizedUnresolved[] = ['kind' => $kind, 'reference' => $reference, 'code' => $code];
    }
    usort($normalizedUnresolved, static fn(array $left, array $right): int => strcmp(
        $left['kind'] . '|' . $left['reference'] . '|' . $left['code'],
        $right['kind'] . '|' . $right['reference'] . '|' . $right['code']
    ));

    $warnings = $snapshot['warnings'] ?? null;
    if (!is_array($warnings) || !array_is_list($warnings) || count($warnings) > 32) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot warnings are invalid.');
    }
    $normalizedWarnings = [];
    $warningSeen = [];
    $allowedWarningCodes = classops_audience_snapshot_warning_codes();
    foreach ($warnings as $warning) {
        $warning = classops_audience_assert_object($warning, 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        classops_audience_assert_known_keys($warning, ['code', 'count'], 'CLASSOPS_AUDIENCE_SNAPSHOT_INVALID');
        $code = trim((string) ($warning['code'] ?? ''));
        $count = (int) ($warning['count'] ?? 0);
        if (!in_array($code, $allowedWarningCodes, true) || $count < 1) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot warning is invalid.');
        }
        if (isset($warningSeen[$code])) {
            classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_INVALID', 'Audience snapshot warnings contain duplicate codes.');
        }
        $warningSeen[$code] = true;
        $normalizedWarnings[] = ['code' => $code, 'count' => $count];
    }
    usort($normalizedWarnings, static fn(array $left, array $right): int => strcmp($left['code'], $right['code']));

    $result = [
        'version' => CLASSOPS_AUDIENCE_RESOLUTION_VERSION,
        'specVersion' => CLASSOPS_AUDIENCE_CONTRACT_VERSION,
        'specHash' => $specHash,
        'cohortKey' => $cohortKey,
        'resolutionMode' => 'snapshot',
        'resolutionSource' => 'snapshot',
        'normalizedSpec' => $normalizedSpec,
        'recipientStudentNumbers' => $recipients,
        'recipientCount' => count($recipients),
        'recipientReasons' => $recipientReasons,
        'unresolvedReferences' => $normalizedUnresolved,
        'warnings' => $normalizedWarnings,
        'provenance' => $provenance,
    ];
    $recomputed = classops_audience_hash(classops_audience_resolution_payload_for_hash($result));
    if (!hash_equals($recomputed, (string) ($snapshot['deterministicHash'] ?? ''))) {
        classops_audience_error('CLASSOPS_AUDIENCE_SNAPSHOT_TAMPERED', 'Audience snapshot integrity check failed.');
    }
    $result['deterministicHash'] = $recomputed;
    $result['snapshot'] = $snapshot;
    return $result;
}
