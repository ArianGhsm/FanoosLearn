<?php

declare(strict_types=1);

require_once __DIR__ . '/destination_registry.php';

const CLASSOPS_DELIVERY_CONTRACT_VERSION = 'classops-delivery-v1';
const CLASSOPS_DELIVERY_MAX_ATTEMPTS = 5;
const CLASSOPS_DELIVERY_RETRY_BASE_SECONDS = 30;
const CLASSOPS_DELIVERY_RETRY_MAX_SECONDS = 900;

function classops_delivery_canonical_json(array $value): string {
    $normalize = function ($v) use (&$normalize) {
        if (!is_array($v)) return $v;
        if (array_is_list($v)) return array_map($normalize, $v);
        ksort($v, SORT_STRING);
        foreach ($v as $k => $child) $v[$k] = $normalize($child);
        return $v;
    };
    return json_encode($normalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function classops_delivery_assert_utc(string $value): void {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
        throw new DentClassOpsDeliveryException('timestamp_must_be_utc_iso8601');
    }
}

function classops_delivery_validate_refs(array $itemRef, array $audienceRef): void {
    foreach (['id','snapshotHash','cohortId','status'] as $field) {
        if (!isset($itemRef[$field]) || $itemRef[$field] === '') throw new DentClassOpsDeliveryException('invalid_item_ref');
    }
    if (!isset($itemRef['revision']) || !is_int($itemRef['revision']) || $itemRef['revision'] < 1) throw new DentClassOpsDeliveryException('invalid_item_revision');
    foreach (['contractVersion','ref','version','cohortId','hash'] as $field) {
        if (!isset($audienceRef[$field]) || $audienceRef[$field] === '') throw new DentClassOpsDeliveryException('invalid_audience_ref');
    }
    if ((string)$itemRef['cohortId'] !== (string)$audienceRef['cohortId']) throw new DentClassOpsDeliveryException('cross_cohort_audience');
}

function classops_delivery_retry_metadata(): array {
    return [
        'attempt' => 0,
        'maxAttempts' => CLASSOPS_DELIVERY_MAX_ATTEMPTS,
        'baseBackoffSeconds' => CLASSOPS_DELIVERY_RETRY_BASE_SECONDS,
        'maxBackoffSeconds' => CLASSOPS_DELIVERY_RETRY_MAX_SECONDS,
    ];
}

function classops_delivery_find_previous(array $previousIntents, array $lane): ?array {
    $best = null;
    foreach ($previousIntents as $intent) {
        if (!is_array($intent)) continue;
        if (($intent['itemRef']['id'] ?? null) !== $lane['itemId']) continue;
        if (($intent['requestedDestinationAlias'] ?? null) !== $lane['requestedAlias']) continue;
        if (($intent['resolvedDestinationAlias'] ?? null) !== $lane['resolvedAlias']) continue;
        if (($intent['platform'] ?? null) !== $lane['platform']) continue;
        if (($intent['occurrence']['key'] ?? null) !== $lane['occurrenceKey']) continue;
        $rev = (int)($intent['itemRef']['revision'] ?? 0);
        if ($rev >= $lane['revision']) continue;
        if ($best === null || $rev > (int)$best['itemRef']['revision']) $best = $intent;
    }
    return $best;
}

function classops_delivery_make_intent(array $registry, string $requestedAlias, string $resolvedAlias, array $destination, array $itemRef, array $audienceRef, array $occurrence, array $policy, ?string $fallbackFrom, array $previousIntents): array {
    $semantic = [
        'contractVersion' => CLASSOPS_DELIVERY_CONTRACT_VERSION,
        'item' => ['id'=>$itemRef['id'], 'revision'=>$itemRef['revision'], 'snapshotHash'=>$itemRef['snapshotHash']],
        'audience' => ['ref'=>$audienceRef['ref'], 'version'=>$audienceRef['version'], 'hash'=>$audienceRef['hash']],
        'registry' => ['id'=>$registry['id'], 'version'=>$registry['version']],
        'requestedAlias' => $requestedAlias,
        'resolvedAlias' => $resolvedAlias,
        'bindingRef' => $destination['bindingRef'],
        'platform' => $destination['platform'],
        'occurrence' => $occurrence,
        'requiredCapabilities' => array_values($policy['requiredCapabilities'] ?? []),
        'fallbackFrom' => $fallbackFrom,
    ];
    $hash = hash('sha256', classops_delivery_canonical_json($semantic));
    $lane = [
        'itemId'=>$itemRef['id'], 'revision'=>$itemRef['revision'], 'requestedAlias'=>$requestedAlias,
        'resolvedAlias'=>$resolvedAlias, 'platform'=>$destination['platform'], 'occurrenceKey'=>$occurrence['key'],
    ];
    $previous = classops_delivery_find_previous($previousIntents, $lane);
    return [
        'schemaVersion' => 1,
        'contractVersion' => CLASSOPS_DELIVERY_CONTRACT_VERSION,
        'intentId' => 'cdi_' . substr($hash, 0, 32),
        'dedupeKey' => 'classops-delivery:' . $hash,
        'status' => 'planned',
        'itemRef' => $itemRef,
        'audienceRef' => $audienceRef,
        'audienceHash' => $audienceRef['hash'],
        'registryRef' => ['id'=>$registry['id'], 'version'=>$registry['version']],
        'requestedDestinationAlias' => $requestedAlias,
        'resolvedDestinationAlias' => $resolvedAlias,
        'bindingRef' => $destination['bindingRef'],
        'destinationKind' => $destination['kind'],
        'platform' => $destination['platform'],
        'platformPolicy' => $policy['platformMode'] ?? 'independent',
        'occurrence' => $occurrence,
        'retry' => classops_delivery_retry_metadata(),
        'fallbackFrom' => $fallbackFrom,
        'supersedesIntentId' => $previous['intentId'] ?? null,
    ];
}

function classops_delivery_route_check(array $destination, array $policy, array $availability, array $capabilityOverrides): ?string {
    $platform = $destination['platform'];
    $platformState = $availability[$platform] ?? 'unknown';
    if ($platformState !== 'available') return 'platform_' . $platformState;
    foreach (($policy['requiredCapabilities'] ?? []) as $capability) {
        $state = classops_delivery_capability_state($destination, (string)$capability, $capabilityOverrides);
        if ($state !== 'supported') return 'capability_' . $capability . '_' . $state;
    }
    return null;
}

function classops_delivery_plan(array $registry, string $requestedAlias, array $itemRef, array $audienceRef, array $occurrence, array $policy, array $availability, array $previousIntents = [], array $capabilityOverrides = []): array {
    $registry = classops_delivery_validate_registry($registry);
    classops_delivery_assert_alias($requestedAlias);
    classops_delivery_validate_refs($itemRef, $audienceRef);
    if ((string)$itemRef['cohortId'] !== (string)$registry['cohortId']) throw new DentClassOpsDeliveryException('cross_cohort_registry');
    if (in_array($itemRef['status'], ['completed','cancelled','archived'], true)) throw new DentClassOpsDeliveryException('item_status_not_deliverable');
    if (!isset($occurrence['key'], $occurrence['purpose'], $occurrence['scheduledAt'])) throw new DentClassOpsDeliveryException('invalid_occurrence');
    if (!in_array($occurrence['purpose'], ['initial','reminder','manual'], true)) throw new DentClassOpsDeliveryException('invalid_occurrence_purpose');
    classops_delivery_assert_utc((string)$occurrence['scheduledAt']);
    $mode = $policy['platformMode'] ?? 'independent';
    if (!in_array($mode, ['independent','require_all'], true)) throw new DentClassOpsDeliveryException('invalid_platform_mode');
    foreach ($availability as $state) if (!in_array($state, ['available','unavailable','unknown'], true)) throw new DentClassOpsDeliveryException('invalid_platform_availability');

    $requested = $registry['destinations'][$requestedAlias] ?? null;
    if (!is_array($requested)) throw new DentClassOpsDeliveryException('unknown_destination_alias');
    $routes = ($requested['kind'] ?? null) === 'logical' ? $requested['routes'] : [['alias'=>$requestedAlias]];
    $outcomes = [];
    $intents = [];
    $failedPrimaries = [];

    foreach ($routes as $route) {
        if (isset($route['fallbackFor'])) continue;
        $alias = $route['alias'];
        $destination = $registry['destinations'][$alias];
        $reason = classops_delivery_route_check($destination, $policy, $availability, $capabilityOverrides);
        if ($reason !== null) {
            $failedPrimaries[$alias] = $reason;
            $outcomes[] = ['alias'=>$alias, 'platform'=>$destination['platform'], 'state'=>'blocked', 'reason'=>$reason];
            continue;
        }
        $intent = classops_delivery_make_intent($registry, $requestedAlias, $alias, $destination, $itemRef, $audienceRef, $occurrence, $policy, null, $previousIntents);
        $intents[] = $intent;
        $outcomes[] = ['alias'=>$alias, 'platform'=>$destination['platform'], 'state'=>'planned', 'reason'=>null];
    }

    if (($policy['allowFallback'] ?? false) === true && $failedPrimaries !== []) {
        foreach ($routes as $route) {
            if (!isset($route['fallbackFor']) || ($route['semanticEquivalent'] ?? false) !== true) continue;
            if (!isset($failedPrimaries[$route['fallbackFor']])) continue;
            $alias = $route['alias'];
            $destination = $registry['destinations'][$alias];
            $reason = classops_delivery_route_check($destination, $policy, $availability, $capabilityOverrides);
            if ($reason !== null) {
                $outcomes[] = ['alias'=>$alias, 'platform'=>$destination['platform'], 'state'=>'blocked_fallback', 'reason'=>$reason, 'fallbackFor'=>$route['fallbackFor']];
                continue;
            }
            $already = false;
            foreach ($intents as $existing) if ($existing['resolvedDestinationAlias'] === $alias) $already = true;
            if (!$already) $intents[] = classops_delivery_make_intent($registry, $requestedAlias, $alias, $destination, $itemRef, $audienceRef, $occurrence, $policy, $route['fallbackFor'], $previousIntents);
            $outcomes[] = ['alias'=>$alias, 'platform'=>$destination['platform'], 'state'=>'planned_fallback', 'reason'=>null, 'fallbackFor'=>$route['fallbackFor']];
        }
    }

    if ($mode === 'require_all' && $failedPrimaries !== []) {
        $intents = [];
        foreach ($outcomes as &$outcome) {
            if (str_starts_with($outcome['state'], 'planned')) {
                $outcome['state'] = 'withheld_require_all';
                $outcome['reason'] = 'required_primary_unavailable';
            }
        }
        unset($outcome);
    }
    return ['contractVersion'=>CLASSOPS_DELIVERY_CONTRACT_VERSION, 'requestedAlias'=>$requestedAlias, 'intents'=>$intents, 'outcomes'=>$outcomes];
}

function classops_delivery_reconcile_future_intent(array $intent, array $itemRef, string $nowUtc): array {
    classops_delivery_assert_utc($nowUtc);
    classops_delivery_assert_utc((string)($intent['occurrence']['scheduledAt'] ?? ''));
    if ($intent['occurrence']['scheduledAt'] <= $nowUtc) return ['action'=>'runtime_reconcile', 'reason'=>'due_or_past'];
    if (in_array($itemRef['status'] ?? null, ['cancelled','archived'], true)) return ['action'=>'cancel', 'reason'=>'item_' . $itemRef['status']];
    if (($itemRef['id'] ?? null) === ($intent['itemRef']['id'] ?? null) && (int)($itemRef['revision'] ?? 0) > (int)($intent['itemRef']['revision'] ?? 0)) return ['action'=>'supersede', 'reason'=>'newer_revision'];
    return ['action'=>'keep', 'reason'=>'unchanged'];
}

function classops_delivery_adapter_envelope(array $intent): array {
    return [
        'contractVersion'=>'classops-delivery-adapter-v1',
        'intentId'=>$intent['intentId'], 'dedupeKey'=>$intent['dedupeKey'],
        'itemRef'=>['id'=>$intent['itemRef']['id'], 'revision'=>$intent['itemRef']['revision'], 'snapshotHash'=>$intent['itemRef']['snapshotHash']],
        'audienceRef'=>$intent['audienceRef'], 'destination'=>['alias'=>$intent['resolvedDestinationAlias'], 'bindingRef'=>$intent['bindingRef'], 'kind'=>$intent['destinationKind'], 'platform'=>$intent['platform']],
        'occurrence'=>$intent['occurrence'], 'retry'=>$intent['retry'],
    ];
}

function classops_delivery_audit_projection(array $intent, string $resultCode): array {
    return ['intentId'=>$intent['intentId'], 'itemId'=>$intent['itemRef']['id'], 'itemRevision'=>$intent['itemRef']['revision'], 'audienceHash'=>$intent['audienceHash'], 'destinationAlias'=>$intent['resolvedDestinationAlias'], 'platform'=>$intent['platform'], 'resultCode'=>$resultCode];
}
