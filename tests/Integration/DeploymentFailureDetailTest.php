<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Operations\DeploymentControlService;
use Fanoos\Platform\Operations\DeploymentExecutor;
use Fanoos\Platform\Operations\DeploymentRunner;
use Fanoos\Platform\Operations\DeploymentSnapshotStore;
use Fanoos\Platform\Operations\OwnerControlPlaneService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Covers the loss of operator-facing failure detail described in the deployment postmortem:
 * a step's real failure message must survive into release_update_events.detail_json and the
 * journal, while safe_failure_code and both control-plane services stay exactly as narrow as
 * before.
 */
final class DeploymentFailureDetailTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $operator = $this->operator();
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $control = new DeploymentControlService($this->database, $access, new AuditLogger($this->database));
        $overview = new OwnerControlPlaneService($this->database, $access, new DeploymentSnapshotStore($this->database));

        // 1. A raw, unwrapped RuntimeException's message must reach the failure event.
        $directMessage = 'Direct process failure: unwrapped stderr detail for scenario T1.';
        $target1 = $this->target('stage7-detail-t1');
        $request1 = $control->request($operator, 'telegram', $target1, 'detail-t1')['request_id'];
        $result1 = (new DeploymentRunner($this->database, new DetailFakeExecutor('test', new RuntimeException($directMessage)), 120))->runNext($target1);
        $this->assert(($result1['state'] ?? null) === 'FAILED', 'Unwrapped RuntimeException did not fail the deployment.');
        $detail1 = $this->latestDetail((string) $request1, 'deployment.failed');
        $this->assert(str_contains($detail1, $directMessage), 'Failure event detail_json did not contain the real RuntimeException message.');

        // 2. The exception chain is walked: PlatformException's own (safe) message must not
        //    replace the real cause carried by its previous exception.
        $realCause = 'Pending migration 0099_example.sql is declared contract-mode and must be applied by the supervised operator path, not the unattended updater.';
        $safeMessage = 'Forward-compatible migration failed.';
        $wrapped = new PlatformException('deployment_migration_failed', $safeMessage, 500, new RuntimeException($realCause));
        $target2 = $this->target('stage7-detail-t2');
        $request2 = $control->request($operator, 'telegram', $target2, 'detail-t2')['request_id'];
        $result2 = (new DeploymentRunner($this->database, new DetailFakeExecutor('migrate', $wrapped), 120))->runNext($target2);
        $this->assert(($result2['state'] ?? null) === 'FAILED' && ($result2['failure_code'] ?? null) === 'deployment_migration_failed', 'Wrapped migration failure did not surface deployment_migration_failed.');
        $detail2 = $this->latestDetail((string) $request2, 'deployment.failed');
        $this->assert(str_contains($detail2, '0099_example.sql'), 'Failure event detail_json did not surface the inner exception message.');
        $this->assert($detail2 !== $safeMessage, 'Failure event detail_json stored the vague outer PlatformException message instead of the real cause.');

        // 3. safe_failure_code and both control-plane services stay exactly as narrow as before.
        $status = $control->status($operator, (string) $request2);
        $this->assert($status['failure_code'] === 'deployment_migration_failed', 'safe_failure_code changed shape or value.');
        $statusKeys = array_keys($status);
        sort($statusKeys);
        $this->assert($statusKeys === ['candidate_sha', 'correlation_id', 'created_at', 'failure_code', 'finished_at', 'request_id', 'rollback_sha', 'state', 'target', 'updated_at'], 'DeploymentControlService::status() response shape changed.');
        $statusJson = json_encode($status, JSON_THROW_ON_ERROR);
        $this->assert(!str_contains($statusJson, '0099_example.sql'), 'DeploymentControlService leaked raw failure detail to a caller.');
        $ownerView = $overview->overview($operator, $target2);
        $ownerJson = json_encode($ownerView, JSON_THROW_ON_ERROR);
        $this->assert(!str_contains($ownerJson, '0099_example.sql'), 'OwnerControlPlaneService leaked raw failure detail to a caller.');
        $this->assert(($ownerView['last_deployment']['failure_code'] ?? null) === 'deployment_migration_failed', 'OwnerControlPlaneService lost the safe failure code.');

        // 4. Redact by content: DSN, password=/secret=-shaped fragments, and /etc/fanoos/ paths.
        $secretMessage = 'Operational process failed: mysql:host=127.0.0.1;dbname=fanoos;user=root;password=hunter2 '
            . 'also password=hunter2 and secret=topsecret, config at /etc/fanoos/env.production';
        $secretFailure = new PlatformException('deployment_tests_failed', 'Candidate tests or migration preflight failed.', 500, new RuntimeException($secretMessage));
        $target4 = $this->target('stage7-detail-t4');
        $request4 = $control->request($operator, 'telegram', $target4, 'detail-t4')['request_id'];
        (new DeploymentRunner($this->database, new DetailFakeExecutor('test', $secretFailure), 120))->runNext($target4);
        $detail4 = $this->latestDetail((string) $request4, 'deployment.failed');
        $this->assert(!str_contains($detail4, 'hunter2'), 'Redaction left a password value in stored detail.');
        $this->assert(!str_contains($detail4, 'topsecret'), 'Redaction left a secret value in stored detail.');
        $this->assert(!str_contains($detail4, '/etc/fanoos/env.production'), 'Redaction left an /etc/fanoos/ path in stored detail.');
        $this->assert(str_contains($detail4, '[redacted]'), 'Redaction did not mark the secret-shaped content as redacted.');

        // 5. An over-long message is capped rather than stored whole.
        $longMessage = str_repeat('Q', 6000);
        $longFailure = new PlatformException('deployment_tests_failed', 'Candidate tests or migration preflight failed.', 500, new RuntimeException($longMessage));
        $target5 = $this->target('stage7-detail-t5');
        $request5 = $control->request($operator, 'telegram', $target5, 'detail-t5')['request_id'];
        (new DeploymentRunner($this->database, new DetailFakeExecutor('test', $longFailure), 120))->runNext($target5);
        $detail5 = $this->latestDetail((string) $request5, 'deployment.failed');
        $this->assert(strlen($detail5) < 6000, 'Over-long failure detail was not capped.');
        $this->assert(str_contains($detail5, 'truncated'), 'Capped failure detail did not mark itself as truncated.');

        // 6. A post-activation failure still rolls back, and the rollback path records detail too.
        $activationCause = 'Activation health probe found a stale symlink and refused to promote the release.';
        $activationFailure = new PlatformException('activation_failed', 'Candidate could not be activated.', 500, new RuntimeException($activationCause));
        $target6 = $this->target('stage7-detail-t6');
        $request6 = $control->request($operator, 'telegram', $target6, 'detail-t6')['request_id'];
        $rollbackExecutor = new DetailFakeExecutor('activate', $activationFailure);
        $result6 = (new DeploymentRunner($this->database, $rollbackExecutor, 120))->runNext($target6);
        $this->assert(($result6['state'] ?? null) === 'ROLLED_BACK', 'Post-activation failure did not roll back.');
        $this->assert(in_array('rollback', $rollbackExecutor->calls, true), 'Rollback was not invoked for a post-activation failure.');
        $detail6 = $this->latestDetail((string) $request6, 'deployment.rolled_back');
        $this->assert(str_contains($detail6, 'stale symlink'), 'Rollback path did not record the originating failure detail.');

        return $this->assertions;
    }

    private function latestDetail(string $requestId, string $eventCode): string
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT detail_json FROM release_update_events
WHERE request_id = :request AND event_code = :event
ORDER BY created_at DESC LIMIT 1
SQL);
        $query->execute(['request' => $requestId, 'event' => $eventCode]);
        $raw = $query->fetchColumn();
        if ($raw === false) {
            throw new RuntimeException("No {$eventCode} event was recorded for request {$requestId}.");
        }
        $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_key_exists('detail', $decoded) || !is_string($decoded['detail'])) {
            throw new RuntimeException("Event {$eventCode} for request {$requestId} carries no detail text.");
        }
        return $decoded['detail'];
    }

    private function operator(): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, 'Failure detail operator', 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id]);
        $role = $this->database->query("SELECT id FROM rbac_role_templates WHERE role_key = 'platform-deployment-operator' LIMIT 1")->fetchColumn();
        if ($role === false) {
            throw new RuntimeException('Stage 7 deployment role seed is unavailable.');
        }
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'user' => $id, 'role' => $role]);
        return $id;
    }

    private function target(string $targetKey): string
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO release_update_targets (id, target_key, node_key, service_key, status, created_at, updated_at)
VALUES (:id, :target, 'test-node', 'platform', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE status = 'active', updated_at = UTC_TIMESTAMP(6)
SQL)->execute(['id' => Uuid::v7(), 'target' => $targetKey]);
        return $targetKey;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

final class DetailFakeExecutor implements DeploymentExecutor
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private readonly string $failAt, private readonly Throwable $failure)
    {
    }

    public function preflight(string $requestId, string $targetKey): array
    {
        $this->maybeFail('preflight');
        return ['current_sha' => str_repeat('a', 40), 'candidate_sha' => str_repeat('b', 40), 'noop' => false];
    }

    public function backup(string $requestId, string $targetKey, string $currentSha, string $candidateSha): array
    {
        $this->maybeFail('backup');
        return ['backup_id' => 'detail-test-backup'];
    }

    public function test(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->maybeFail('test');
    }

    public function migrate(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->maybeFail('migrate');
    }

    public function activate(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->maybeFail('activate');
    }

    public function restart(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->maybeFail('restart');
    }

    public function health(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->maybeFail('health');
    }

    public function rollback(string $requestId, string $targetKey, string $rollbackSha): void
    {
        $this->calls[] = 'rollback';
        $this->maybeFail('rollback');
    }

    private function maybeFail(string $step): void
    {
        $this->calls[] = $step;
        if ($step === $this->failAt) {
            throw $this->failure;
        }
    }
}
