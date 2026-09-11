<?php
declare(strict_types=1);

require_once __DIR__ . '/snapshot.php';

function classops_audience_resolve(
    array $spec,
    string $cohortKey,
    array $context,
    ?array $snapshot = null,
    ?string $expectedResolutionHash = null
): array {
    $targetCohort = classops_audience_normalize_cohort_key($cohortKey);
    $normalizationWarnings = [];
    $normalizedSpec = classops_audience_normalize_spec($spec, $normalizationWarnings);
    $normalizedContext = classops_audience_normalize_context($context, $targetCohort);

    if ($normalizedSpec['resolutionMode'] === 'snapshot' && $snapshot !== null) {
        $result = classops_audience_result_from_snapshot($snapshot, $normalizedSpec, $normalizedContext);
    } else {
        $result = classops_audience_finalize_live_resolution($normalizedSpec, $normalizationWarnings, $normalizedContext);
        if ($normalizedSpec['resolutionMode'] === 'snapshot') {
            $result['resolutionSource'] = 'live_initial_snapshot';
            $result['snapshot'] = classops_audience_snapshot_from_result($result);
        }
    }

    if ($expectedResolutionHash !== null && $expectedResolutionHash !== '') {
        if (preg_match('/^[a-f0-9]{64}$/', $expectedResolutionHash) !== 1
            || !hash_equals($expectedResolutionHash, (string) $result['deterministicHash'])) {
            classops_audience_error(
                'CLASSOPS_AUDIENCE_DRIFT',
                'Audience changed after preview; owner confirmation must be refreshed.',
                409
            );
        }
    }

    return $result;
}

function classops_audience_resolve_from_source(
    DentClassOpsAudienceSourceV1 $source,
    array $spec,
    string $cohortKey,
    array $ownerScope,
    ?array $snapshot = null,
    ?string $expectedResolutionHash = null
): array {
    try {
        $context = $source->loadAudienceContext($cohortKey);
    } catch (DentClassOpsAudienceSourceException $exception) {
        classops_audience_error($exception->reasonCode, 'Canonical audience source is unavailable.', 503);
    } catch (Throwable $exception) {
        classops_audience_error('CLASSOPS_AUDIENCE_SOURCE_UNAVAILABLE', 'Canonical audience source is unavailable.', 503);
    }
    if (!is_array($context)) {
        classops_audience_error('CLASSOPS_AUDIENCE_SOURCE_INVALID', 'Canonical audience source returned invalid data.', 503);
    }
    $context['ownerScope'] = $ownerScope;
    return classops_audience_resolve(
        $spec,
        $cohortKey,
        $context,
        $snapshot,
        $expectedResolutionHash
    );
}

function classops_audience_preview(array $current, ?array $previous = null, bool $includeIdentifiers = false): array
{
    if (($current['version'] ?? '') !== CLASSOPS_AUDIENCE_RESOLUTION_VERSION) {
        classops_audience_error('CLASSOPS_AUDIENCE_PREVIEW_INPUT_INVALID', 'Audience preview input is invalid.');
    }
    $currentRecipients = $current['recipientStudentNumbers'] ?? [];
    if (!is_array($currentRecipients) || !array_is_list($currentRecipients)) {
        classops_audience_error('CLASSOPS_AUDIENCE_PREVIEW_INPUT_INVALID', 'Audience preview recipient set is invalid.');
    }
    $previousRecipients = [];
    if ($previous !== null) {
        if (($previous['version'] ?? '') !== CLASSOPS_AUDIENCE_RESOLUTION_VERSION
            || ($previous['cohortKey'] ?? '') !== ($current['cohortKey'] ?? '')) {
            classops_audience_error('CLASSOPS_AUDIENCE_PREVIEW_INPUT_INVALID', 'Audience diff baseline is invalid.');
        }
        $previousRecipients = $previous['recipientStudentNumbers'] ?? [];
        if (!is_array($previousRecipients) || !array_is_list($previousRecipients)) {
            classops_audience_error('CLASSOPS_AUDIENCE_PREVIEW_INPUT_INVALID', 'Audience diff baseline recipient set is invalid.');
        }
    }

    $added = array_values(array_diff($currentRecipients, $previousRecipients));
    $removed = array_values(array_diff($previousRecipients, $currentRecipients));
    sort($added, SORT_STRING);
    sort($removed, SORT_STRING);

    $warningCounts = [];
    foreach (($current['warnings'] ?? []) as $warning) {
        if (is_array($warning) && isset($warning['code'], $warning['count'])) {
            $warningCounts[] = [
                'code' => (string) $warning['code'],
                'count' => (int) $warning['count'],
            ];
        }
    }
    usort($warningCounts, static fn(array $left, array $right): int => strcmp($left['code'], $right['code']));

    $preview = [
        'version' => CLASSOPS_AUDIENCE_PREVIEW_VERSION,
        'cohortKey' => (string) ($current['cohortKey'] ?? ''),
        'resolutionMode' => (string) ($current['resolutionMode'] ?? ''),
        'resolutionHash' => (string) ($current['deterministicHash'] ?? ''),
        'total' => count($currentRecipients),
        'added' => count($added),
        'removed' => count($removed),
        'unresolved' => count($current['unresolvedReferences'] ?? []),
        'warningCounts' => $warningCounts,
    ];
    if ($includeIdentifiers) {
        $preview['addedStudentNumbers'] = $added;
        $preview['removedStudentNumbers'] = $removed;
        $preview['unresolvedReferences'] = $current['unresolvedReferences'] ?? [];
    }
    return $preview;
}
