<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Migration\LegacyBundleValidator;
use Fanoos\Platform\Migration\LegacyImportEngine;
use Fanoos\Platform\Migration\LegacyReconciler;
use Fanoos\Platform\Migration\LegacyTargetId;
use PDO;
use RuntimeException;

final class Stage8FinalClosureTest
{
    private int $assertions = 0;

    public function __construct(
        private readonly PDO $database,
        private readonly string $legacyHmacKey,
    ) {
    }

    public function run(): int
    {
        $validator = new LegacyBundleValidator($this->legacyHmacKey);
        $workspace = $this->activeWorkspace();
        $source = 'stage8-' . bin2hex(random_bytes(5));
        $suffix = bin2hex(random_bytes(4));
        $bundle = $this->bundle($source, $workspace, $suffix);

        $validation = $validator->inspect($bundle);
        self::assert($validation['valid'] === true, 'Sanitized Stage 8 bundle must validate.');
        self::assert($validation['row_count'] === 3 && $validation['transformed_count'] === 3, 'Bundle accounting is incorrect.');

        $secretBundle = $bundle;
        $secretBundle['rows'][0]['values']['bot_token'] = 'forbidden-fixture-value';
        self::assert($validator->inspect($secretBundle)['valid'] === false, 'Secret-shaped migration field was accepted.');

        $duplicateBundle = $bundle;
        $duplicateBundle['rows'][] = $duplicateBundle['rows'][0];
        self::assert($validator->inspect($duplicateBundle)['valid'] === false, 'Duplicate legacy source identity was accepted.');

        $forgedTarget = $bundle;
        $forgedTarget['rows'][0]['target_id'] = '018f4e2a-5d51-7abc-8def-1123456789ab';
        self::assert($validator->inspect($forgedTarget)['valid'] === false, 'Forged deterministic target ID was accepted.');

        $foreignWorkspace = $bundle;
        $foreignWorkspace['rows'][1]['workspace_key'] = 'missing-workspace';
        self::assert($validator->inspect($foreignWorkspace)['valid'] === false, 'Unmapped workspace alias was accepted.');

        $payment = $bundle;
        $payment['rows'] = [[
            'entity_type' => 'payment',
            'source_key' => 'payment-1',
            'target_id' => LegacyTargetId::derive($this->legacyHmacKey, $source, 'payment', 'payment-1'),
            'workspace_key' => 'legacy-workspace',
            'values' => [
                'order_id' => '018f4e2a-5d51-7abc-8def-2123456789ab',
                'provider_key' => 'fixture',
                'status' => 'verified',
                'requested_amount_minor' => 1000,
                'provider_reference' => 'fixture-reference',
                'verified_at' => '2026-09-08 00:00:00',
            ],
        ]];
        self::assert($validator->inspect($payment)['valid'] === false, 'Verified payment without canonical evidence was accepted.');

        $link = $bundle;
        $link['rows'] = [[
            'entity_type' => 'messaging_link',
            'source_key' => 'telegram-1',
            'target_id' => LegacyTargetId::derive($this->legacyHmacKey, $source, 'messaging_link', 'telegram-1'),
            'values' => ['platform' => 'telegram'],
        ]];
        self::assert($validator->inspect($link)['valid'] === false, 'Direct legacy messaging-link import was accepted.');

        $unsafeObject = $bundle;
        $unsafeObject['rows'] = [[
            'entity_type' => 'content_object',
            'source_key' => 'object-1',
            'target_id' => LegacyTargetId::derive($this->legacyHmacKey, $source, 'content_object', 'object-1'),
            'workspace_key' => 'legacy-workspace',
            'values' => [
                'storage_adapter' => 'filesystem',
                'storage_key' => '../escape.pdf',
                'original_name' => 'safe.pdf',
                'detected_mime' => 'application/pdf',
                'byte_size' => 100,
                'checksum_sha256' => 'hex:' . str_repeat('a', 64),
                'classification' => 'protected',
                'status' => 'verified',
                'verified_at' => '2026-09-08 00:00:00',
            ],
            'evidence' => ['object_verified' => true, 'evidence_sha256' => str_repeat('a', 64)],
        ]];
        self::assert($validator->inspect($unsafeObject)['valid'] === false, 'Unsafe legacy object key was accepted.');

        $engine = new LegacyImportEngine($this->database, $this->legacyHmacKey, $validator);
        $beforeBatchCount = (int) $this->database->query('SELECT COUNT(*) FROM migration_batches')->fetchColumn();
        $plan = $engine->dryRun($bundle);
        $afterDryRunCount = (int) $this->database->query('SELECT COUNT(*) FROM migration_batches')->fetchColumn();
        self::assert($plan['valid'] === true && $plan['planned_insert'] === 3, 'Dry-run did not plan the sanitized import.');
        self::assert($beforeBatchCount === $afterDryRunCount, 'Dry-run mutated migration ledger state.');

        $applied = $engine->apply($bundle);
        self::assert($applied['status'] === 'completed' && $applied['outcomes']['inserted'] === 3, 'Apply did not commit all sanitized rows atomically.');
        $replay = $engine->apply($bundle);
        self::assert($replay['replayed'] === true && $replay['batch_id'] === $applied['batch_id'], 'Completed batch rerun was not idempotent.');

        $countUser = $this->database->prepare('SELECT COUNT(*) FROM iam_users WHERE id = :id');
        $countUser->execute(['id' => $bundle['rows'][0]['target_id']]);
        self::assert((int) $countUser->fetchColumn() === 1, 'Imported user target is missing.');
        $countMembership = $this->database->prepare('SELECT COUNT(*) FROM tenant_workspace_memberships WHERE id = :id AND workspace_id = :workspace');
        $countMembership->execute(['id' => $bundle['rows'][1]['target_id'], 'workspace' => $workspace]);
        self::assert((int) $countMembership->fetchColumn() === 1, 'Imported membership escaped/missed its workspace.');

        $report = (new LegacyReconciler($this->database))->reconcile((string) $applied['batch_id']);
        self::assert($report['status'] === 'PASS', 'Machine reconciliation did not pass for a clean import.');
        self::assert($report['source_count'] === 3 && $report['accepted'] === 3 && $report['destination_count'] === 3, 'Reconciliation counts do not balance.');
        self::assert($report['orphan'] === 0 && $report['cross_tenant_mismatch'] === 0, 'Reconciliation found an orphan or tenant mismatch.');

        return $this->assertions;
    }

    /** @return array<string, mixed> */
    private function bundle(string $source, string $workspace, string $suffix): array
    {
        $userSource = 'user-' . $suffix;
        $membershipSource = 'membership-' . $suffix;
        $courseSource = 'course-' . $suffix;
        $userId = LegacyTargetId::derive($this->legacyHmacKey, $source, 'user', $userSource);
        $membershipId = LegacyTargetId::derive($this->legacyHmacKey, $source, 'membership', $membershipSource);
        $courseId = LegacyTargetId::derive($this->legacyHmacKey, $source, 'course', $courseSource);

        return [
            'schema_version' => 1,
            'source' => [
                'key' => $source,
                'snapshot_sha256' => hash('sha256', 'stage8-sanitized-' . $suffix),
                'exported_at' => '2026-09-08T00:00:00Z',
            ],
            'batch_key' => 'stage8-sanitized-' . $suffix,
            'workspace_map' => ['legacy-workspace' => $workspace],
            'rows' => [
                [
                    'entity_type' => 'user',
                    'source_key' => $userSource,
                    'target_id' => $userId,
                    'values' => ['display_name' => 'Stage 8 Migrated User ' . $suffix, 'status' => 'active', 'locale' => 'fa-IR'],
                    'transformed' => true,
                ],
                [
                    'entity_type' => 'membership',
                    'source_key' => $membershipSource,
                    'target_id' => $membershipId,
                    'workspace_key' => 'legacy-workspace',
                    'values' => ['user_id' => $userId, 'status' => 'active', 'joined_at' => '2026-09-01 00:00:00'],
                    'transformed' => true,
                ],
                [
                    'entity_type' => 'course',
                    'source_key' => $courseSource,
                    'target_id' => $courseId,
                    'workspace_key' => 'legacy-workspace',
                    'values' => ['course_code' => 'S8-' . strtoupper($suffix), 'title' => 'Stage 8 Migrated Course', 'status' => 'active'],
                    'transformed' => true,
                ],
            ],
            'expectations' => [
                'row_count' => 3,
                'accepted_count' => 3,
                'destination_count' => 3,
                'orphan_count' => 0,
                'cross_tenant_mismatch_count' => 0,
            ],
        ];
    }

    private function activeWorkspace(): string
    {
        $id = $this->database->query("SELECT id FROM tenant_workspaces WHERE status = 'active' ORDER BY created_at LIMIT 1")->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Stage 8 test requires an active workspace fixture.');
        }
        return (string) $id;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
