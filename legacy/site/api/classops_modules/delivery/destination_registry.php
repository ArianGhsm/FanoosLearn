<?php

declare(strict_types=1);

const CLASSOPS_DESTINATION_REGISTRY_VERSION = 'classops-destination-registry-v1';

final class DentClassOpsDeliveryException extends RuntimeException {}

function classops_delivery_assert_alias(string $alias): void {
    if (!preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/', $alias) || preg_match('/^\d+$/', $alias)) {
        throw new DentClassOpsDeliveryException('invalid_destination_alias');
    }
}

function classops_delivery_assert_symbolic_ref(string $ref, string $field): void {
    if ($ref === '' || preg_match('/^-?\d+$/', $ref)) {
        throw new DentClassOpsDeliveryException('invalid_' . $field);
    }
}

function classops_delivery_reject_raw_identifiers(array $value, string $path = 'root'): void {
    $forbidden = ['chatid','chat_id','userid','user_id','platformuserid','platform_user_id','telegramid','telegram_id','baleid','bale_id','token','bot_token'];
    foreach ($value as $key => $child) {
        $normalized = strtolower((string)$key);
        if (in_array($normalized, $forbidden, true)) {
            throw new DentClassOpsDeliveryException('raw_platform_identifier_forbidden:' . $path . '.' . $key);
        }
        if (is_array($child)) {
            classops_delivery_reject_raw_identifiers($child, $path . '.' . $key);
        }
    }
}

function classops_delivery_default_capabilities(): array {
    return [
        'telegram' => [
            'text_message' => 'supported',
            'private_recipient' => 'supported',
            'group_delivery' => 'supported',
            'channel_delivery' => 'supported',
            'silent_delivery' => 'supported',
            'protected_content' => 'supported',
        ],
        'bale' => [
            'text_message' => 'supported',
            'private_recipient' => 'supported',
            'group_delivery' => 'supported',
            'channel_delivery' => 'unknown',
            'silent_delivery' => 'unknown',
            'protected_content' => 'unknown',
        ],
    ];
}

function classops_delivery_validate_registry(array $registry): array {
    classops_delivery_reject_raw_identifiers($registry);
    if (($registry['version'] ?? null) !== CLASSOPS_DESTINATION_REGISTRY_VERSION) {
        throw new DentClassOpsDeliveryException('unsupported_registry_version');
    }
    classops_delivery_assert_symbolic_ref((string)($registry['id'] ?? ''), 'registry_id');
    $cohortId = (string)($registry['cohortId'] ?? '');
    classops_delivery_assert_symbolic_ref($cohortId, 'cohort_id');
    $destinations = $registry['destinations'] ?? null;
    if (!is_array($destinations) || $destinations === []) {
        throw new DentClassOpsDeliveryException('empty_destination_registry');
    }

    $allowedKinds = ['private_recipient', 'class_group', 'channel', 'logical'];
    $allowedPlatforms = ['telegram', 'bale'];
    foreach ($destinations as $alias => $destination) {
        classops_delivery_assert_alias((string)$alias);
        if (!is_array($destination)) {
            throw new DentClassOpsDeliveryException('invalid_destination');
        }
        $kind = (string)($destination['kind'] ?? '');
        if (!in_array($kind, $allowedKinds, true)) {
            throw new DentClassOpsDeliveryException('invalid_destination_kind');
        }
        if (($destination['cohortId'] ?? null) !== $cohortId) {
            throw new DentClassOpsDeliveryException('cross_cohort_destination');
        }
        if ($kind === 'logical') {
            if (isset($destination['platform']) || isset($destination['bindingRef'])) {
                throw new DentClassOpsDeliveryException('logical_destination_must_not_bind_platform');
            }
            $routes = $destination['routes'] ?? null;
            if (!is_array($routes) || $routes === []) {
                throw new DentClassOpsDeliveryException('logical_destination_requires_routes');
            }
            foreach ($routes as $route) {
                if (!is_array($route)) {
                    throw new DentClassOpsDeliveryException('invalid_logical_route');
                }
                classops_delivery_assert_alias((string)($route['alias'] ?? ''));
                if (isset($route['fallbackFor'])) {
                    classops_delivery_assert_alias((string)$route['fallbackFor']);
                    if (($route['semanticEquivalent'] ?? false) !== true) {
                        throw new DentClassOpsDeliveryException('fallback_requires_semantic_equivalence');
                    }
                }
            }
            continue;
        }

        $platform = (string)($destination['platform'] ?? '');
        if (!in_array($platform, $allowedPlatforms, true)) {
            throw new DentClassOpsDeliveryException('invalid_destination_platform');
        }
        classops_delivery_assert_symbolic_ref((string)($destination['bindingRef'] ?? ''), 'binding_ref');
    }

    foreach ($destinations as $alias => $destination) {
        if (($destination['kind'] ?? null) !== 'logical') {
            continue;
        }
        foreach ($destination['routes'] as $route) {
            $target = $destinations[$route['alias']] ?? null;
            if (!is_array($target) || ($target['kind'] ?? null) === 'logical') {
                throw new DentClassOpsDeliveryException('logical_route_target_invalid');
            }
        }
    }
    return $registry;
}

function classops_delivery_capability_state(array $destination, string $capability, array $overrides = []): string {
    $platform = (string)($destination['platform'] ?? '');
    $matrix = classops_delivery_default_capabilities();
    $state = $matrix[$platform][$capability] ?? 'unsupported';
    if (isset($overrides[$platform][$capability])) {
        $override = $overrides[$platform][$capability];
        if (!is_array($override) || !isset($override['state'], $override['evidence']) || trim((string)$override['evidence']) === '') {
            throw new DentClassOpsDeliveryException('capability_override_requires_evidence');
        }
        if (!in_array($override['state'], ['supported', 'unsupported', 'unknown'], true)) {
            throw new DentClassOpsDeliveryException('invalid_capability_state');
        }
        $state = $override['state'];
    }
    return $state;
}
