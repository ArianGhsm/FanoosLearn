<?php
declare(strict_types=1);

require_once __DIR__ . '/contract.php';

function classops_audience_normalize_context(array $context, string $targetCohort): array
{
    classops_audience_assert_known_keys($context, [
        'cohortKey', 'ownerScope', 'roster', 'identityCohorts', 'selectors', 'source',
    ], 'CLASSOPS_AUDIENCE_CONTEXT_INVALID');

    $contextCohort = classops_audience_normalize_cohort_key($context['cohortKey'] ?? '');
    if ($contextCohort !== $targetCohort) {
        classops_audience_error('CLASSOPS_AUDIENCE_CONTEXT_COHORT_MISMATCH', 'Audience source was loaded for another cohort.');
    }

    $ownerScope = classops_audience_assert_object($context['ownerScope'] ?? null, 'CLASSOPS_AUDIENCE_OWNER_SCOPE_INVALID');
    classops_audience_assert_known_keys($ownerScope, ['role', 'cohortKeys'], 'CLASSOPS_AUDIENCE_OWNER_SCOPE_INVALID');
    if (($ownerScope['role'] ?? '') !== 'owner') {
        classops_audience_error('CLASSOPS_AUDIENCE_OWNER_REQUIRED', 'Audience resolution requires owner authority.', 403);
    }
    $scopeCohorts = $ownerScope['cohortKeys'] ?? null;
    if (!is_array($scopeCohorts) || !array_is_list($scopeCohorts) || count($scopeCohorts) > 100) {
        classops_audience_error('CLASSOPS_AUDIENCE_OWNER_SCOPE_INVALID', 'Owner audience scope is invalid.', 403);
    }
    $scopeSet = [];
    foreach ($scopeCohorts as $cohortKey) {
        $scopeSet[classops_audience_normalize_cohort_key($cohortKey)] = true;
    }
    if (!isset($scopeSet[$targetCohort])) {
        classops_audience_error('CLASSOPS_AUDIENCE_OWNER_SCOPE_DENIED', 'Owner is not authorized for this cohort.', 403);
    }

    $roster = $context['roster'] ?? null;
    if (!is_array($roster) || !array_is_list($roster) || count($roster) > CLASSOPS_AUDIENCE_MAX_ROSTER) {
        classops_audience_error('CLASSOPS_AUDIENCE_ROSTER_INVALID', 'Canonical audience roster is invalid.');
    }
    $rosterSet = [];
    foreach ($roster as $row) {
        $row = classops_audience_assert_object($row, 'CLASSOPS_AUDIENCE_ROSTER_INVALID');
        classops_audience_assert_known_keys($row, ['studentNumber', 'cohortKey'], 'CLASSOPS_AUDIENCE_ROSTER_INVALID');
        $studentNumber = classops_audience_normalize_student_number($row['studentNumber'] ?? '');
        $cohortKey = classops_audience_normalize_cohort_key($row['cohortKey'] ?? '');
        if ($cohortKey !== $targetCohort) {
            classops_audience_error('CLASSOPS_AUDIENCE_CROSS_COHORT_SOURCE', 'Audience roster contains a cross-cohort record.');
        }
        $rosterSet[classops_audience_student_key($studentNumber)] = $studentNumber;
    }

    $identityCohortsRaw = $context['identityCohorts'] ?? [];
    if (!is_array($identityCohortsRaw) || ($identityCohortsRaw !== [] && !classops_audience_is_assoc($identityCohortsRaw)) || count($identityCohortsRaw) > 10000) {
        classops_audience_error('CLASSOPS_AUDIENCE_IDENTITY_INDEX_INVALID', 'Canonical identity index is invalid.');
    }
    $identityCohorts = [];
    foreach ($identityCohortsRaw as $studentNumber => $cohortKey) {
        $normalizedStudent = classops_audience_normalize_student_number((string) $studentNumber);
        $identityCohorts[classops_audience_student_key($normalizedStudent)] = classops_audience_normalize_cohort_key($cohortKey);
    }
    foreach (array_values($rosterSet) as $studentNumber) {
        $identityKey = classops_audience_student_key($studentNumber);
        if (isset($identityCohorts[$identityKey]) && $identityCohorts[$identityKey] !== $targetCohort) {
            classops_audience_error('CLASSOPS_AUDIENCE_CROSS_COHORT_SOURCE', 'Audience identity index conflicts with the cohort roster.');
        }
        $identityCohorts[$identityKey] = $targetCohort;
    }
    ksort($identityCohorts, SORT_STRING);

    $selectorsRaw = $context['selectors'] ?? [];
    if (!is_array($selectorsRaw) || ($selectorsRaw !== [] && !classops_audience_is_assoc($selectorsRaw)) || count($selectorsRaw) > CLASSOPS_AUDIENCE_MAX_SELECTORS) {
        classops_audience_error('CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID', 'Canonical selector source is invalid.');
    }
    $selectors = [];
    foreach ($selectorsRaw as $selectorId => $selector) {
        $selector = classops_audience_assert_object($selector, 'CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID');
        classops_audience_assert_known_keys(
            $selector,
            ['kind', 'key', 'cohortKey', 'studentNumbers', 'sourceRef'],
            'CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID'
        );
        $kind = strtolower(trim((string) ($selector['kind'] ?? '')));
        if (!in_array($kind, ['role', 'group', 'category'], true)) {
            classops_audience_error('CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID', 'Canonical selector kind is invalid.');
        }
        $key = classops_audience_normalize_selector_key($selector['key'] ?? '');
        $expectedId = $kind . ':' . $key;
        if ((string) $selectorId !== $expectedId) {
            classops_audience_error('CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID', 'Canonical selector key does not match its identity.');
        }
        $selectorCohort = classops_audience_normalize_cohort_key($selector['cohortKey'] ?? '');
        if ($selectorCohort !== $targetCohort) {
            classops_audience_error('CLASSOPS_AUDIENCE_CROSS_COHORT_SOURCE', 'Canonical selector belongs to another cohort.');
        }
        $membersRaw = $selector['studentNumbers'] ?? null;
        if (!is_array($membersRaw) || !array_is_list($membersRaw) || count($membersRaw) > CLASSOPS_AUDIENCE_MAX_SELECTOR_MEMBERS) {
            classops_audience_error('CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID', 'Canonical selector membership is invalid.');
        }
        $memberSet = [];
        foreach ($membersRaw as $member) {
            $studentNumber = classops_audience_normalize_student_number($member);
            $studentKey = classops_audience_student_key($studentNumber);
            if (($identityCohorts[$studentKey] ?? $targetCohort) !== $targetCohort || !isset($rosterSet[$studentKey])) {
                classops_audience_error('CLASSOPS_AUDIENCE_CROSS_COHORT_SOURCE', 'Canonical selector contains an invalid cohort member.');
            }
            $memberSet[$studentKey] = $studentNumber;
        }
        $members = array_values($memberSet);
        sort($members, SORT_STRING);
        $sourceRef = trim((string) ($selector['sourceRef'] ?? ''));
        if ($sourceRef === '' || strlen($sourceRef) > 120 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,119}$/', $sourceRef) !== 1) {
            classops_audience_error('CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID', 'Canonical selector provenance is invalid.');
        }
        $selectors[$expectedId] = [
            'kind' => $kind,
            'key' => $key,
            'cohortKey' => $targetCohort,
            'studentNumbers' => $members,
            'sourceRef' => $sourceRef,
        ];
    }
    ksort($selectors, SORT_STRING);

    $source = classops_audience_normalize_source(classops_audience_assert_object(
        $context['source'] ?? null,
        'CLASSOPS_AUDIENCE_SOURCE_INVALID'
    ));

    $rosterNumbers = array_values($rosterSet);
    sort($rosterNumbers, SORT_STRING);
    $scopeKeys = array_keys($scopeSet);
    sort($scopeKeys, SORT_STRING);

    return [
        'cohortKey' => $targetCohort,
        'ownerScope' => ['role' => 'owner', 'cohortKeys' => $scopeKeys],
        'rosterStudentNumbers' => $rosterNumbers,
        'identityCohorts' => $identityCohorts,
        'selectors' => $selectors,
        'source' => $source,
    ];
}

function classops_audience_student_key(string $studentNumber): string
{
    return 'student:' . $studentNumber;
}

function classops_audience_student_number_from_key(string $key): string
{
    if (!str_starts_with($key, 'student:')) {
        classops_audience_error('CLASSOPS_AUDIENCE_INTERNAL_SET_INVALID', 'Audience recipient set is invalid.', 500);
    }
    return classops_audience_normalize_student_number(substr($key, 8));
}

function classops_audience_unresolved_add(array &$unresolved, string $kind, string $reference, string $code): void
{
    $key = $kind . '|' . $reference . '|' . $code;
    $unresolved[$key] = ['kind' => $kind, 'reference' => $reference, 'code' => $code];
}

function classops_audience_reason_add(array &$reasons, string $studentNumber, string $reason): void
{
    $studentKey = classops_audience_student_key($studentNumber);
    if (!isset($reasons[$studentKey])) {
        $reasons[$studentKey] = [];
    }
    $reasons[$studentKey][$reason] = true;
}

function classops_audience_resolve_student_reference(
    string $studentNumber,
    array $context,
    array &$unresolved,
    string $reason
): array {
    $studentKey = classops_audience_student_key($studentNumber);
    $knownCohort = $context['identityCohorts'][$studentKey] ?? null;
    if (is_string($knownCohort) && $knownCohort !== $context['cohortKey']) {
        classops_audience_error(
            'CLASSOPS_AUDIENCE_CROSS_COHORT_REFERENCE',
            'Audience contains a student reference from another cohort.'
        );
    }
    if (!in_array($studentNumber, $context['rosterStudentNumbers'], true)) {
        classops_audience_unresolved_add($unresolved, 'student', $studentNumber, 'AUDIENCE_STUDENT_NOT_FOUND');
        return ['set' => [], 'reasons' => []];
    }
    return ['set' => [$studentKey => true], 'reasons' => [$studentKey => [$reason => true]]];
}
