<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use PDO;
use RuntimeException;

final class LegacyReconciler
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array<string, mixed> */
    public function reconcile(string $batchId): array
    {
        $batchQuery = $this->database->prepare(<<<'SQL'
SELECT batch.id, batch.status, batch.manifest_json, HEX(batch.source_snapshot_digest) AS snapshot_sha256,
       source.source_key
FROM migration_batches batch
JOIN migration_source_systems source ON source.id = batch.source_system_id
WHERE batch.id = :id
LIMIT 1
SQL);
        $batchQuery->execute(['id' => $batchId]);
        $batch = $batchQuery->fetch();
        if ($batch === false) {
            throw new RuntimeException('Migration batch was not found.');
        }
        $manifest = json_decode((string) $batch['manifest_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) {
            throw new RuntimeException('Migration batch manifest is invalid.');
        }

        $resultsQuery = $this->database->prepare(<<<'SQL'
SELECT source_entity_type, outcome, target_entity_type, target_id, detail_json
FROM migration_row_results
WHERE batch_id = :id
ORDER BY created_at, id
SQL);
        $resultsQuery->execute(['id' => $batchId]);
        $results = $resultsQuery->fetchAll();

        $outcomes = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => 0, 'conflict' => 0];
        $transformed = 0;
        $destinationCount = 0;
        $orphan = 0;
        $crossTenantMismatch = 0;
        $moneyCount = 0;
        $moneyTotalMinor = 0;
        $verifiedMoneyCount = 0;
        $verifiedMoneyTotalMinor = 0;
        $paymentEntitlementInconsistency = 0;
        $objectTotal = 0;
        $objectChecksumCovered = 0;
        $specs = LegacyBundleValidator::specs();

        foreach ($results as $result) {
            $outcome = (string) $result['outcome'];
            if (array_key_exists($outcome, $outcomes)) {
                ++$outcomes[$outcome];
            }
            $detail = json_decode((string) $result['detail_json'], true, 32, JSON_THROW_ON_ERROR);
            if (is_array($detail) && ($detail['transformed'] ?? false) === true) {
                ++$transformed;
            }
            $type = (string) ($result['target_entity_type'] ?? '');
            $targetId = (string) ($result['target_id'] ?? '');
            if ($targetId === '' || !isset($specs[$type])) {
                continue;
            }
            $table = $specs[$type]['table'];
            $select = $this->database->prepare("SELECT * FROM `{$table}` WHERE id = :id LIMIT 1");
            $select->execute(['id' => $targetId]);
            $target = $select->fetch();
            if ($target === false) {
                ++$orphan;
                continue;
            }
            ++$destinationCount;

            if ($specs[$type]['workspace']) {
                $expectedWorkspace = is_array($detail) ? ($detail['workspace_id'] ?? null) : null;
                if (!is_string($expectedWorkspace) || !isset($target['workspace_id']) || !hash_equals($expectedWorkspace, (string) $target['workspace_id'])) {
                    ++$crossTenantMismatch;
                }
            }

            if ($type === 'payment') {
                ++$moneyCount;
                $amount = (int) ($target['requested_amount_minor'] ?? 0);
                $moneyTotalMinor += $amount;
                if (($target['status'] ?? null) === 'verified') {
                    ++$verifiedMoneyCount;
                    $verifiedMoneyTotalMinor += $amount;
                }
            }

            if ($type === 'entitlement' && ($target['source_type'] ?? null) === 'order') {
                if (!is_string($target['source_id'] ?? null) || $target['source_id'] === '') {
                    ++$paymentEntitlementInconsistency;
                } else {
                    $proof = $this->database->prepare(<<<'SQL'
SELECT 1
FROM commerce_payment_attempts
WHERE workspace_id = :workspace AND order_id = :order_id
  AND status = 'verified' AND verified_at IS NOT NULL AND provider_reference IS NOT NULL
LIMIT 1
SQL);
                    $proof->execute(['workspace' => $target['workspace_id'], 'order_id' => $target['source_id']]);
                    if ($proof->fetchColumn() === false) {
                        ++$paymentEntitlementInconsistency;
                    }
                }
            }

            if ($type === 'content_object') {
                ++$objectTotal;
                if (($target['status'] ?? null) === 'verified'
                    && is_string($target['checksum_sha256'] ?? null)
                    && strlen((string) $target['checksum_sha256']) === 32
                    && (int) ($target['byte_size'] ?? 0) > 0) {
                    ++$objectChecksumCovered;
                }
            }
        }

        $rejectQuery = $this->database->prepare('SELECT COUNT(*) FROM migration_rejects WHERE batch_id = :id');
        $rejectQuery->execute(['id' => $batchId]);
        $ledgerRejects = (int) $rejectQuery->fetchColumn();
        $rejected = $outcomes['rejected'] + $ledgerRejects;
        $accepted = $outcomes['inserted'] + $outcomes['updated'] + $outcomes['unchanged'];
        $sourceCount = (int) ($manifest['row_count'] ?? 0);
        $accountingMatches = $sourceCount === ($accepted + $rejected + $outcomes['conflict']);
        $objectCoverage = $objectTotal === 0 ? null : round(($objectChecksumCovered / $objectTotal) * 100, 2);

        $actuals = [
            'row_count' => $sourceCount,
            'accepted_count' => $accepted,
            'transformed_count' => $transformed,
            'duplicate_count' => $outcomes['unchanged'],
            'rejected_count' => $rejected,
            'destination_count' => $destinationCount,
            'orphan_count' => $orphan,
            'cross_tenant_mismatch_count' => $crossTenantMismatch,
            'money_record_count' => $moneyCount,
            'money_total_minor' => $moneyTotalMinor,
            'verified_money_record_count' => $verifiedMoneyCount,
            'verified_money_total_minor' => $verifiedMoneyTotalMinor,
            'payment_entitlement_inconsistency_count' => $paymentEntitlementInconsistency,
            'object_count' => $objectTotal,
            'object_checksum_covered_count' => $objectChecksumCovered,
        ];
        $expectations = is_array($manifest['expectations'] ?? null) ? $manifest['expectations'] : [];
        $deltas = [];
        foreach ($expectations as $key => $expected) {
            $deltas[$key] = [
                'expected' => $expected,
                'actual' => $actuals[$key] ?? null,
                'matches' => array_key_exists($key, $actuals) ? $actuals[$key] === $expected : null,
            ];
        }

        $pass = in_array($batch['status'], ['completed', 'completed_with_rejects'], true)
            && $accountingMatches
            && $orphan === 0
            && $crossTenantMismatch === 0
            && $paymentEntitlementInconsistency === 0
            && ($objectTotal === 0 || $objectChecksumCovered === $objectTotal);
        foreach ($deltas as $delta) {
            if ($delta['matches'] === false) {
                $pass = false;
            }
        }

        return [
            'status' => $pass ? 'PASS' : 'FAIL',
            'batch_id' => $batchId,
            'batch_status' => (string) $batch['status'],
            'source_key' => (string) $batch['source_key'],
            'snapshot_sha256' => strtolower((string) $batch['snapshot_sha256']),
            'bundle_sha256' => (string) ($manifest['bundle_sha256'] ?? ''),
            'source_count' => $sourceCount,
            'accepted' => $accepted,
            'transformed' => $transformed,
            'duplicate' => $outcomes['unchanged'],
            'rejected' => $rejected,
            'conflict' => $outcomes['conflict'],
            'destination_count' => $destinationCount,
            'orphan' => $orphan,
            'cross_tenant_mismatch' => $crossTenantMismatch,
            'money' => [
                'record_count' => $moneyCount,
                'total_minor' => $moneyTotalMinor,
                'verified_record_count' => $verifiedMoneyCount,
                'verified_total_minor' => $verifiedMoneyTotalMinor,
            ],
            'payment_entitlement_inconsistency' => $paymentEntitlementInconsistency,
            'object_checksum_coverage' => [
                'total' => $objectTotal,
                'covered' => $objectChecksumCovered,
                'percent' => $objectCoverage,
            ],
            'accounting_matches' => $accountingMatches,
            'expectation_deltas' => $deltas,
        ];
    }
}
