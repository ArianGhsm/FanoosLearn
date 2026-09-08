<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use RuntimeException;

final class LegacyBundleValidator
{
    private const MAX_ROWS = 100000;

    /** @return array<string, array{table:string,workspace:bool,order:int}> */
    public static function specs(): array
    {
        return [
            'user' => ['table' => 'iam_users', 'workspace' => false, 'order' => 10],
            'user_identifier' => ['table' => 'iam_user_identifiers', 'workspace' => false, 'order' => 20],
            'membership' => ['table' => 'tenant_workspace_memberships', 'workspace' => true, 'order' => 30],
            'term' => ['table' => 'academic_terms', 'workspace' => true, 'order' => 40],
            'course' => ['table' => 'academic_courses', 'workspace' => true, 'order' => 50],
            'offering' => ['table' => 'academic_course_offerings', 'workspace' => true, 'order' => 60],
            'session' => ['table' => 'academic_course_sessions', 'workspace' => true, 'order' => 70],
            'enrollment' => ['table' => 'academic_enrollments', 'workspace' => true, 'order' => 80],
            'schedule_event' => ['table' => 'schedule_events', 'workspace' => true, 'order' => 90],
            'gradebook' => ['table' => 'grade_gradebooks', 'workspace' => true, 'order' => 100],
            'grade_item' => ['table' => 'grade_items', 'workspace' => true, 'order' => 110],
            'grade_result' => ['table' => 'grade_results', 'workspace' => true, 'order' => 120],
            'content_object' => ['table' => 'content_objects', 'workspace' => true, 'order' => 130],
            'content_resource' => ['table' => 'content_resources', 'workspace' => true, 'order' => 140],
            'content_resource_version' => ['table' => 'content_resource_versions', 'workspace' => true, 'order' => 150],
            'content_resource_binding' => ['table' => 'content_resource_bindings', 'workspace' => true, 'order' => 160],
            'announcement' => ['table' => 'notification_messages', 'workspace' => true, 'order' => 170],
            'form_definition' => ['table' => 'form_definitions', 'workspace' => true, 'order' => 180],
            'form_version' => ['table' => 'form_versions', 'workspace' => true, 'order' => 190],
            'assessment' => ['table' => 'exam_assessments', 'workspace' => true, 'order' => 200],
            'assessment_version' => ['table' => 'exam_assessment_versions', 'workspace' => true, 'order' => 210],
            'product' => ['table' => 'commerce_products', 'workspace' => true, 'order' => 220],
            'price' => ['table' => 'commerce_price_versions', 'workspace' => true, 'order' => 230],
            'order' => ['table' => 'commerce_orders', 'workspace' => true, 'order' => 240],
            'order_line' => ['table' => 'commerce_order_lines', 'workspace' => true, 'order' => 250],
            'payment' => ['table' => 'commerce_payment_attempts', 'workspace' => true, 'order' => 260],
            'entitlement' => ['table' => 'entitlement_grants', 'workspace' => true, 'order' => 270],
        ];
    }

    public function __construct(private readonly string $hmacKey)
    {
        if (strlen($hmacKey) < 16) {
            throw new RuntimeException('Legacy bundle validation requires an HMAC key of at least 16 bytes.');
        }
    }

    /**
     * @param array<string, mixed> $bundle
     * @return array<string, mixed>
     */
    public function inspect(array $bundle): array
    {
        $errors = [];
        $warnings = [];
        $this->scanSecretKeys($bundle, '$', $errors);

        if (($bundle['schema_version'] ?? null) !== 1) {
            $errors[] = 'schema_version must be exactly 1.';
        }

        $source = is_array($bundle['source'] ?? null) ? $bundle['source'] : [];
        $sourceKey = is_string($source['key'] ?? null) ? trim((string) $source['key']) : '';
        $snapshot = is_string($source['snapshot_sha256'] ?? null) ? strtolower((string) $source['snapshot_sha256']) : '';
        if (!preg_match('/^[a-z0-9][a-z0-9_.:-]{1,95}$/', $sourceKey)) {
            $errors[] = 'source.key is missing or invalid.';
        }
        if (!preg_match('/^[0-9a-f]{64}$/', $snapshot)) {
            $errors[] = 'source.snapshot_sha256 must be a 64-character SHA-256 hex digest.';
        }

        $batchKey = is_string($bundle['batch_key'] ?? null) ? trim((string) $bundle['batch_key']) : '';
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{1,159}$/', $batchKey)) {
            $errors[] = 'batch_key is missing or invalid.';
        }

        $workspaceMap = is_array($bundle['workspace_map'] ?? null) ? $bundle['workspace_map'] : [];
        foreach ($workspaceMap as $legacyKey => $workspaceId) {
            if (!is_string($legacyKey) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,127}$/', $legacyKey)) {
                $errors[] = 'workspace_map contains an invalid source workspace key.';
                continue;
            }
            if (!is_string($workspaceId) || !$this->isUuid($workspaceId)) {
                $errors[] = "workspace_map.{$legacyKey} must resolve to a UUID.";
            }
        }

        $rows = $bundle['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            $errors[] = 'rows must be a JSON list.';
            $rows = [];
        }
        if (count($rows) > self::MAX_ROWS) {
            $errors[] = 'rows exceeds the maximum bundle size of ' . self::MAX_ROWS . '.';
        }

        $specs = self::specs();
        $identities = [];
        $entityCounts = [];
        $transformedCount = 0;
        $verifiedPayments = [];
        $entitlements = [];
        $objectRows = 0;
        $objectEvidenceRows = 0;

        foreach ($rows as $index => $row) {
            $path = "rows[{$index}]";
            if (!is_array($row)) {
                $errors[] = "{$path} must be an object.";
                continue;
            }
            $entityType = is_string($row['entity_type'] ?? null) ? (string) $row['entity_type'] : '';
            if (!isset($specs[$entityType])) {
                $errors[] = "{$path}.entity_type is unsupported; raw/ad-hoc table migration is prohibited.";
                continue;
            }
            $entityCounts[$entityType] = ($entityCounts[$entityType] ?? 0) + 1;

            $sourceRowKey = is_string($row['source_key'] ?? null) ? (string) $row['source_key'] : '';
            if ($sourceRowKey === '' || strlen($sourceRowKey) > 500) {
                $errors[] = "{$path}.source_key must contain 1..500 bytes.";
            } else {
                $identity = $entityType . "\0" . $sourceRowKey;
                if (isset($identities[$identity])) {
                    $errors[] = "{$path} duplicates a source identity already present in this bundle.";
                }
                $identities[$identity] = true;
            }

            $targetId = is_string($row['target_id'] ?? null) ? strtolower((string) $row['target_id']) : '';
            if (!$this->isUuid($targetId)) {
                $errors[] = "{$path}.target_id must be a UUID.";
            } elseif ($sourceKey !== '' && $sourceRowKey !== '') {
                $expected = LegacyTargetId::derive($this->hmacKey, $sourceKey, $entityType, $sourceRowKey);
                if (!hash_equals($expected, $targetId)) {
                    $errors[] = "{$path}.target_id does not match deterministic source identity derivation.";
                }
            }

            $workspaceKey = $row['workspace_key'] ?? null;
            if ($specs[$entityType]['workspace']) {
                if (!is_string($workspaceKey) || $workspaceKey === '' || !isset($workspaceMap[$workspaceKey])) {
                    $errors[] = "{$path}.workspace_key must resolve through workspace_map.";
                }
            } elseif ($workspaceKey !== null) {
                $errors[] = "{$path}.workspace_key must be null/omitted for a platform-global entity.";
            }

            $values = $row['values'] ?? null;
            if (!is_array($values) || array_is_list($values) || $values === []) {
                $errors[] = "{$path}.values must be a non-empty object.";
                $values = [];
            }
            if (array_key_exists('id', $values) || array_key_exists('workspace_id', $values)) {
                $errors[] = "{$path}.values must not supply id/workspace_id; the importer owns those fields.";
            }
            foreach ($values as $field => $value) {
                if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field)) {
                    $errors[] = "{$path}.values contains an invalid canonical field name.";
                    continue;
                }
                if ((str_ends_with($field, '_id') || $field === 'target_scope_id') && $value !== null
                    && (!is_string($value) || !$this->isUuid($value))) {
                    $errors[] = "{$path}.values.{$field} must be a target UUID or null.";
                }
            }

            if (($row['transformed'] ?? false) === true) {
                ++$transformedCount;
            }
            $evidence = is_array($row['evidence'] ?? null) ? $row['evidence'] : [];

            if ($entityType === 'user_identifier' && in_array($values['identifier_type'] ?? null, ['telegram', 'bale'], true)) {
                $errors[] = "{$path}: Telegram/Bale account links must be rebuilt through the canonical messaging-link flow, not imported as IAM identifiers.";
            }

            if ($entityType === 'payment') {
                $status = $values['status'] ?? null;
                if ($status === 'verified') {
                    $proof = $evidence['provider_verified'] ?? false;
                    $digest = is_string($evidence['evidence_sha256'] ?? null) ? strtolower((string) $evidence['evidence_sha256']) : '';
                    if ($proof !== true || !preg_match('/^[0-9a-f]{64}$/', $digest)
                        || !is_string($values['provider_reference'] ?? null) || trim((string) $values['provider_reference']) === ''
                        || !is_string($values['verified_at'] ?? null) || trim((string) $values['verified_at']) === '') {
                        $errors[] = "{$path}: verified payment requires canonical provider evidence digest, provider reference, and verified_at.";
                    } elseif (is_string($workspaceKey)) {
                        $verifiedPayments[$workspaceKey . "\0" . $sourceRowKey] = true;
                    }
                } elseif (($evidence['provider_verified'] ?? false) === true) {
                    $errors[] = "{$path}: non-verified payment cannot claim provider_verified evidence.";
                }
            }

            if ($entityType === 'entitlement') {
                $entitlements[] = [$path, $workspaceKey, $values, $evidence];
            }

            if ($entityType === 'content_object') {
                ++$objectRows;
                $checksum = is_string($values['checksum_sha256'] ?? null) ? strtolower((string) $values['checksum_sha256']) : '';
                $evidenceDigest = is_string($evidence['evidence_sha256'] ?? null) ? strtolower((string) $evidence['evidence_sha256']) : '';
                $storageKey = is_string($values['storage_key'] ?? null) ? (string) $values['storage_key'] : '';
                if (($evidence['object_verified'] ?? false) !== true || !preg_match('/^hex:[0-9a-f]{64}$/', $checksum)
                    || !preg_match('/^[0-9a-f]{64}$/', $evidenceDigest)
                    || !hash_equals(substr($checksum, 4), $evidenceDigest)
                    || $storageKey === '' || str_contains($storageKey, '..') || str_starts_with($storageKey, '/') || str_contains($storageKey, "\0")) {
                    $errors[] = "{$path}: content object metadata requires a staged, checksum-verified private object and safe storage key.";
                } else {
                    ++$objectEvidenceRows;
                }
            }
        }

        foreach ($entitlements as [$path, $workspaceKey, $values, $evidence]) {
            if (($values['source_type'] ?? null) === 'order') {
                $paymentSource = is_string($evidence['payment_source_key'] ?? null) ? (string) $evidence['payment_source_key'] : '';
                if (!is_string($workspaceKey) || $paymentSource === '' || !isset($verifiedPayments[$workspaceKey . "\0" . $paymentSource])) {
                    $errors[] = "{$path}: order-derived entitlement requires a verified payment row in the same workspace bundle.";
                }
            } elseif (($values['source_type'] ?? null) === 'migration') {
                $approval = is_string($evidence['approval_sha256'] ?? null) ? strtolower((string) $evidence['approval_sha256']) : '';
                if (!preg_match('/^[0-9a-f]{64}$/', $approval)) {
                    $errors[] = "{$path}: migration-derived entitlement requires an approval evidence digest.";
                }
            }
        }

        $expectations = is_array($bundle['expectations'] ?? null) ? $bundle['expectations'] : [];
        foreach ($expectations as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) || !is_int($value) || $value < 0) {
                $errors[] = 'expectations may contain only non-negative integer counters/totals.';
                break;
            }
        }

        if ($objectRows > 0 && $objectRows !== $objectEvidenceRows) {
            $warnings[] = 'Not every content object row has verified checksum evidence.';
        }
        if (count($rows) === 0) {
            $warnings[] = 'Bundle contains no rows; this is valid only for a deliberate empty snapshot rehearsal.';
        }

        ksort($entityCounts);
        $bundleDigest = hash('sha256', self::canonicalJson($bundle));

        return [
            'valid' => $errors === [],
            'schema_version' => 1,
            'source_key' => $sourceKey,
            'snapshot_sha256' => $snapshot,
            'batch_key' => $batchKey,
            'bundle_sha256' => $bundleDigest,
            'row_count' => count($rows),
            'transformed_count' => $transformedCount,
            'entity_counts' => $entityCounts,
            'workspace_count' => count($workspaceMap),
            'object_evidence_count' => $objectEvidenceRows,
            'expectations' => $expectations,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @param array<string, mixed> $bundle @return array<string, mixed> */
    public function assertValid(array $bundle): array
    {
        $report = $this->inspect($bundle);
        if ($report['valid'] !== true) {
            throw new RuntimeException('Legacy migration bundle validation failed: ' . implode(' | ', $report['errors']));
        }
        return $report;
    }

    /** @param mixed $value @param list<string> $errors */
    private function scanSecretKeys(mixed $value, string $path, array &$errors): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            $childPath = $path . '.' . (string) $key;
            if (is_string($key) && preg_match('/^(?:password|password_hash|token|access_token|refresh_token|api_token|api_key|secret|hmac_key|private_key|cookie|session|bot_token|otp|update_offset|ssh_key|ftp_password|credential)$/i', $key)) {
                $errors[] = "{$childPath} is a secret/runtime field and is forbidden in migration bundles.";
            }
            $this->scanSecretKeys($child, $childPath, $errors);
        }
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /** @param mixed $value */
    private static function canonicalJson(mixed $value): string
    {
        $normalize = static function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) {
                return $item;
            }
            if (array_is_list($item)) {
                return array_map($normalize, $item);
            }
            ksort($item);
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };

        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
