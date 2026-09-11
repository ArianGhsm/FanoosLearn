<?php
declare(strict_types=1);

require_once __DIR__ . '/core.php';

function classops_audience_normalize_expression(
    $raw,
    int $depth,
    int &$nodeCount,
    int &$totalRefs,
    array &$warnings
): array {
    if ($depth > CLASSOPS_AUDIENCE_MAX_DEPTH) {
        classops_audience_error('CLASSOPS_AUDIENCE_MAX_DEPTH', 'Audience expression nesting is too deep.');
    }
    $nodeCount++;
    if ($nodeCount > CLASSOPS_AUDIENCE_MAX_NODES) {
        classops_audience_error('CLASSOPS_AUDIENCE_MAX_NODES', 'Audience expression contains too many nodes.');
    }

    $node = classops_audience_assert_object($raw, 'CLASSOPS_AUDIENCE_INVALID_EXPRESSION');
    $op = strtolower(trim((string) ($node['op'] ?? '')));

    if ($op === 'whole_cohort') {
        classops_audience_assert_known_keys($node, ['op']);
        return ['op' => 'whole_cohort'];
    }

    if ($op === 'students') {
        classops_audience_assert_known_keys($node, ['op', 'studentNumbers']);
        $studentNumbers = classops_audience_normalize_student_list(
            $node['studentNumbers'] ?? [],
            'expression.studentNumbers',
            $warnings,
            $totalRefs
        );
        if ($studentNumbers === []) {
            classops_audience_error('CLASSOPS_AUDIENCE_EMPTY_STUDENT_NODE', 'Student selector must contain at least one reference.');
        }
        return ['op' => 'students', 'studentNumbers' => $studentNumbers];
    }

    if ($op === 'selector') {
        classops_audience_assert_known_keys($node, ['op', 'kind', 'key']);
        $kind = strtolower(trim((string) ($node['kind'] ?? '')));
        if (!in_array($kind, ['role', 'group', 'category'], true)) {
            classops_audience_error('CLASSOPS_AUDIENCE_INVALID_SELECTOR', 'Selector kind is not supported.');
        }
        return [
            'op' => 'selector',
            'kind' => $kind,
            'key' => classops_audience_normalize_selector_key($node['key'] ?? ''),
        ];
    }

    if ($op === 'not') {
        classops_audience_assert_known_keys($node, ['op', 'child']);
        return [
            'op' => 'not',
            'child' => classops_audience_normalize_expression(
                $node['child'] ?? null,
                $depth + 1,
                $nodeCount,
                $totalRefs,
                $warnings
            ),
        ];
    }

    if ($op === 'any' || $op === 'all') {
        classops_audience_assert_known_keys($node, ['op', 'children']);
        $children = $node['children'] ?? null;
        if (!is_array($children) || !array_is_list($children) || count($children) < 2 || count($children) > CLASSOPS_AUDIENCE_MAX_CHILDREN) {
            classops_audience_error('CLASSOPS_AUDIENCE_INVALID_CHILDREN', 'Audience combination has an invalid child count.');
        }
        $normalized = [];
        $canonicalSeen = [];
        $duplicateChildren = 0;
        foreach ($children as $child) {
            $item = classops_audience_normalize_expression(
                $child,
                $depth + 1,
                $nodeCount,
                $totalRefs,
                $warnings
            );
            $canonical = classops_audience_canonical_json($item);
            if (isset($canonicalSeen[$canonical])) {
                $duplicateChildren++;
                continue;
            }
            $canonicalSeen[$canonical] = true;
            $normalized[] = $item;
        }
        if ($duplicateChildren > 0) {
            classops_audience_warning_add($warnings, 'AUDIENCE_DUPLICATE_EXPRESSION', $duplicateChildren);
        }
        if (count($normalized) < 2) {
            classops_audience_error('CLASSOPS_AUDIENCE_REDUNDANT_COMBINATION', 'Audience combination became redundant after normalization.');
        }
        usort($normalized, static fn(array $left, array $right): int => strcmp(
            classops_audience_canonical_json($left),
            classops_audience_canonical_json($right)
        ));
        return ['op' => $op, 'children' => $normalized];
    }

    classops_audience_error('CLASSOPS_AUDIENCE_INVALID_OPERATOR', 'Audience operator is not supported.');
}

function classops_audience_normalize_spec(array $raw, ?array &$normalizationWarnings = null): array
{
    classops_audience_assert_known_keys($raw, [
        'version', 'resolutionMode', 'expression', 'includeStudentNumbers', 'excludeStudentNumbers',
    ]);
    if (($raw['version'] ?? '') !== CLASSOPS_AUDIENCE_CONTRACT_VERSION) {
        classops_audience_error('CLASSOPS_AUDIENCE_VERSION_UNSUPPORTED', 'Audience contract version is not supported.');
    }
    $resolutionMode = strtolower(trim((string) ($raw['resolutionMode'] ?? '')));
    if (!in_array($resolutionMode, ['snapshot', 'live'], true)) {
        classops_audience_error('CLASSOPS_AUDIENCE_INVALID_RESOLUTION_MODE', 'Audience resolution mode is invalid.');
    }

    $warnings = [];
    $nodeCount = 0;
    $totalRefs = 0;
    $expression = classops_audience_normalize_expression(
        $raw['expression'] ?? null,
        1,
        $nodeCount,
        $totalRefs,
        $warnings
    );
    $include = classops_audience_normalize_student_list(
        $raw['includeStudentNumbers'] ?? [],
        'includeStudentNumbers',
        $warnings,
        $totalRefs
    );
    $exclude = classops_audience_normalize_student_list(
        $raw['excludeStudentNumbers'] ?? [],
        'excludeStudentNumbers',
        $warnings,
        $totalRefs
    );

    $conflictCount = count(array_intersect($include, $exclude));
    if ($conflictCount > 0) {
        classops_audience_warning_add($warnings, 'AUDIENCE_INCLUDE_EXCLUDE_CONFLICT', $conflictCount);
    }

    usort($warnings, static fn(array $left, array $right): int => strcmp((string) $left['code'], (string) $right['code']));
    $normalizationWarnings = $warnings;

    return [
        'version' => CLASSOPS_AUDIENCE_CONTRACT_VERSION,
        'resolutionMode' => $resolutionMode,
        'expression' => $expression,
        'includeStudentNumbers' => $include,
        'excludeStudentNumbers' => $exclude,
    ];
}

function classops_audience_upgrade_placeholder(array $placeholder, string $resolutionMode = 'snapshot'): array
{
    classops_audience_assert_known_keys($placeholder, ['version', 'mode', 'refs']);
    if (($placeholder['version'] ?? 'classops-audience-placeholder-v1') !== 'classops-audience-placeholder-v1') {
        classops_audience_error('CLASSOPS_AUDIENCE_LEGACY_VERSION_UNSUPPORTED', 'Legacy audience version is not supported.');
    }
    $mode = trim((string) ($placeholder['mode'] ?? ''));
    $refs = $placeholder['refs'] ?? [];
    if (!is_array($refs) || !array_is_list($refs)) {
        classops_audience_error('CLASSOPS_AUDIENCE_LEGACY_INVALID', 'Legacy audience reference list is invalid.');
    }

    if ($mode === 'entire_cohort') {
        $expression = ['op' => 'whole_cohort'];
    } elseif ($mode === 'single_student' || $mode === 'explicit_students') {
        $expression = ['op' => 'students', 'studentNumbers' => $refs];
    } else {
        classops_audience_error(
            'CLASSOPS_AUDIENCE_LEGACY_MODE_REQUIRES_MIGRATION',
            'Legacy audience mode needs an explicit contract migration.'
        );
    }

    return classops_audience_normalize_spec([
        'version' => CLASSOPS_AUDIENCE_CONTRACT_VERSION,
        'resolutionMode' => $resolutionMode,
        'expression' => $expression,
        'includeStudentNumbers' => [],
        'excludeStudentNumbers' => [],
    ]);
}

function classops_audience_normalize_source(array $source): array
{
    classops_audience_assert_known_keys($source, ['type', 'version'], 'CLASSOPS_AUDIENCE_SOURCE_INVALID');
    $type = trim((string) ($source['type'] ?? ''));
    $version = trim((string) ($source['version'] ?? ''));
    if ($type === '' || strlen($type) > 80 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,79}$/', $type) !== 1) {
        classops_audience_error('CLASSOPS_AUDIENCE_SOURCE_INVALID', 'Audience source identity is invalid.');
    }
    if ($version === '' || strlen($version) > 128 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $version) !== 1) {
        classops_audience_error('CLASSOPS_AUDIENCE_SOURCE_INVALID', 'Audience source version is invalid.');
    }
    return ['type' => $type, 'version' => $version];
}
