<?php
declare(strict_types=1);

require_once __DIR__ . '/context.php';

function classops_audience_eval_expression(
    array $node,
    array $context,
    array &$unresolved,
    array &$warnings,
    array &$provenance
): array {
    $op = (string) $node['op'];
    $rosterSet = [];
    foreach ($context['rosterStudentNumbers'] as $studentNumber) {
        $rosterSet[classops_audience_student_key($studentNumber)] = true;
    }

    if ($op === 'whole_cohort') {
        $reasons = [];
        foreach ($context['rosterStudentNumbers'] as $studentNumber) {
            $reasons[classops_audience_student_key($studentNumber)] = ['whole_cohort' => true];
        }
        $provenance['wholeCohort'] = true;
        return ['set' => $rosterSet, 'reasons' => $reasons];
    }

    if ($op === 'students') {
        $set = [];
        $reasons = [];
        foreach ($node['studentNumbers'] as $studentNumber) {
            $resolved = classops_audience_resolve_student_reference(
                $studentNumber,
                $context,
                $unresolved,
                'explicit_student'
            );
            foreach ($resolved['set'] as $memberKey => $_) {
                $set[$memberKey] = true;
                classops_audience_reason_add($reasons, classops_audience_student_number_from_key($memberKey), 'explicit_student');
            }
        }
        return ['set' => $set, 'reasons' => $reasons];
    }

    if ($op === 'selector') {
        $selectorId = $node['kind'] . ':' . $node['key'];
        $selector = $context['selectors'][$selectorId] ?? null;
        if (!is_array($selector)) {
            classops_audience_unresolved_add($unresolved, 'selector', $selectorId, 'AUDIENCE_SELECTOR_NOT_FOUND');
            return ['set' => [], 'reasons' => []];
        }
        $set = [];
        $reasons = [];
        $reason = 'selector:' . $selectorId;
        foreach ($selector['studentNumbers'] as $studentNumber) {
            $studentKey = classops_audience_student_key($studentNumber);
            $knownCohort = $context['identityCohorts'][$studentKey] ?? null;
            if ($knownCohort !== null && $knownCohort !== $context['cohortKey']) {
                classops_audience_error('CLASSOPS_AUDIENCE_CROSS_COHORT_SOURCE', 'Canonical selector contains a cross-cohort identity.');
            }
            if (!isset($rosterSet[$studentKey])) {
                classops_audience_error('CLASSOPS_AUDIENCE_SELECTOR_SOURCE_INVALID', 'Canonical selector contains a non-roster identity.');
            }
            $set[$studentKey] = true;
            $reasons[$studentKey] = [$reason => true];
        }
        $provenance['selectors'][$selectorId] = (string) $selector['sourceRef'];
        return ['set' => $set, 'reasons' => $reasons];
    }

    if ($op === 'not') {
        $unresolvedBefore = count($unresolved);
        $child = classops_audience_eval_expression($node['child'], $context, $unresolved, $warnings, $provenance);
        if (count($unresolved) > $unresolvedBefore) {
            classops_audience_warning_add($warnings, 'AUDIENCE_NEGATION_UNRESOLVED');
            return ['set' => [], 'reasons' => []];
        }
        $set = array_diff_key($rosterSet, $child['set']);
        $reasons = [];
        foreach (array_keys($set) as $studentKey) {
            $reasons[$studentKey] = ['not_expression' => true];
        }
        return ['set' => $set, 'reasons' => $reasons];
    }

    $children = [];
    foreach ($node['children'] as $childNode) {
        $children[] = classops_audience_eval_expression($childNode, $context, $unresolved, $warnings, $provenance);
    }

    if ($op === 'any') {
        $set = [];
        $reasons = [];
        foreach ($children as $child) {
            foreach ($child['set'] as $studentKey => $_) {
                $set[$studentKey] = true;
                foreach (array_keys($child['reasons'][$studentKey] ?? []) as $reason) {
                    classops_audience_reason_add($reasons, classops_audience_student_number_from_key($studentKey), $reason);
                }
            }
        }
        return ['set' => $set, 'reasons' => $reasons];
    }

    if ($op === 'all') {
        $set = $children[0]['set'];
        foreach (array_slice($children, 1) as $child) {
            $set = array_intersect_key($set, $child['set']);
        }
        $allChildrenNonEmpty = true;
        foreach ($children as $child) {
            if ($child['set'] === []) {
                $allChildrenNonEmpty = false;
                break;
            }
        }
        if ($set === [] && $allChildrenNonEmpty) {
            classops_audience_warning_add($warnings, 'AUDIENCE_CONTRADICTORY_INTERSECTION');
        }
        $reasons = [];
        foreach (array_keys($set) as $studentKey) {
            foreach ($children as $child) {
                foreach (array_keys($child['reasons'][$studentKey] ?? []) as $reason) {
                    classops_audience_reason_add($reasons, classops_audience_student_number_from_key($studentKey), $reason);
                }
            }
        }
        return ['set' => $set, 'reasons' => $reasons];
    }

    classops_audience_error('CLASSOPS_AUDIENCE_INVALID_OPERATOR', 'Audience operator is not supported.');
}

function classops_audience_resolution_payload_for_hash(array $result): array
{
    return [
        'version' => CLASSOPS_AUDIENCE_RESOLUTION_VERSION,
        'specHash' => $result['specHash'],
        'cohortKey' => $result['cohortKey'],
        'provenance' => $result['provenance'],
        'recipientStudentNumbers' => $result['recipientStudentNumbers'],
        'recipientReasons' => $result['recipientReasons'],
        'unresolvedReferences' => $result['unresolvedReferences'],
        'warnings' => $result['warnings'],
    ];
}

function classops_audience_finalize_live_resolution(
    array $normalizedSpec,
    array $normalizationWarnings,
    array $context
): array {
    $unresolved = [];
    $warnings = $normalizationWarnings;
    $provenance = [
        'source' => $context['source'],
        'wholeCohort' => false,
        'selectors' => [],
    ];

    $evaluated = classops_audience_eval_expression(
        $normalizedSpec['expression'],
        $context,
        $unresolved,
        $warnings,
        $provenance
    );
    $set = $evaluated['set'];
    $reasons = $evaluated['reasons'];

    foreach ($normalizedSpec['includeStudentNumbers'] as $studentNumber) {
        $resolved = classops_audience_resolve_student_reference(
            $studentNumber,
            $context,
            $unresolved,
            'include_student'
        );
        foreach ($resolved['set'] as $memberKey => $_) {
            $set[$memberKey] = true;
            classops_audience_reason_add($reasons, classops_audience_student_number_from_key($memberKey), 'include_student');
        }
    }

    foreach ($normalizedSpec['excludeStudentNumbers'] as $studentNumber) {
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
            continue;
        }
        unset($set[$studentKey], $reasons[$studentKey]);
    }

    if ($set === []) {
        classops_audience_warning_add($warnings, 'AUDIENCE_EMPTY_RESULT');
    }

    $recipientStudentNumbers = array_map('classops_audience_student_number_from_key', array_keys($set));
    sort($recipientStudentNumbers, SORT_STRING);

    $recipientReasons = [];
    foreach ($recipientStudentNumbers as $studentNumber) {
        $reasonList = array_keys($reasons[classops_audience_student_key($studentNumber)] ?? []);
        sort($reasonList, SORT_STRING);
        $recipientReasons[] = ['studentNumber' => $studentNumber, 'reasons' => $reasonList];
    }

    $unresolvedReferences = array_values($unresolved);
    usort($unresolvedReferences, static fn(array $left, array $right): int => strcmp(
        ($left['kind'] ?? '') . '|' . ($left['reference'] ?? '') . '|' . ($left['code'] ?? ''),
        ($right['kind'] ?? '') . '|' . ($right['reference'] ?? '') . '|' . ($right['code'] ?? '')
    ));
    usort($warnings, static fn(array $left, array $right): int => strcmp((string) $left['code'], (string) $right['code']));
    ksort($provenance['selectors'], SORT_STRING);
    $selectorProvenance = [];
    foreach ($provenance['selectors'] as $selectorId => $sourceRef) {
        $selectorProvenance[] = ['selectorId' => $selectorId, 'sourceRef' => $sourceRef];
    }
    $provenance['selectors'] = $selectorProvenance;

    $result = [
        'version' => CLASSOPS_AUDIENCE_RESOLUTION_VERSION,
        'specVersion' => CLASSOPS_AUDIENCE_CONTRACT_VERSION,
        'specHash' => classops_audience_hash($normalizedSpec),
        'cohortKey' => $context['cohortKey'],
        'resolutionMode' => $normalizedSpec['resolutionMode'],
        'resolutionSource' => 'live',
        'normalizedSpec' => $normalizedSpec,
        'recipientStudentNumbers' => $recipientStudentNumbers,
        'recipientCount' => count($recipientStudentNumbers),
        'recipientReasons' => $recipientReasons,
        'unresolvedReferences' => $unresolvedReferences,
        'warnings' => $warnings,
        'provenance' => $provenance,
    ];
    $result['deterministicHash'] = classops_audience_hash(classops_audience_resolution_payload_for_hash($result));
    return $result;
}
