<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Operations\DeploymentControlService;
use Fanoos\Platform\Operations\DeploymentExecutor;
use Fanoos\Platform\Operations\DeploymentRunner;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class DeploymentControlTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $operator = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, 'Stage 7 deployment operator', 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $operator]);
        $role = $this->database->query("SELECT id FROM rbac_role_templates WHERE role_key = 'platform-deployment-operator' LIMIT 1")->fetchColumn();
        if ($role === false) {
            throw new RuntimeException('Stage 7 deployment role seed is unavailable.');
        }
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'user' => $operator, 'role' => $role]);

        $student = $this->user('Multi member');
        $representative = $this->user('Representative');
        $audit = new AuditLogger($this->database);
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $control = new DeploymentControlService($this->database, $access, $audit);
        $this->expectCode('forbidden', fn () => $control->request($student, 'telegram', 'stage7-test-target', 'member-denied'));
        $this->expectCode('forbidden', fn () => $control->request($representative, 'telegram', 'stage7-test-target', 'rep-denied'));

        $target = $this->target('stage7-test-target');
        $requested = $control->request($operator, 'telegram', $target, 'op-1');
        self::assert($requested['state'] === 'REQUESTED' && $requested['idempotent'] === false, 'Deployment operator could not create a durable request.');
        self::assert(!array_key_exists('request_digest', $requested) && !array_key_exists('lease_token', $requested), 'Deployment response leaked internal lock or request digest material.');
        $duplicate = $control->request($operator, 'telegram', $target, 'op-1');
        self::assert($duplicate['request_id'] === $requested['request_id'] && $duplicate['idempotent'] === true, 'Deployment request replay was not idempotent.');
        $this->expectCode('deployment_in_progress', fn () => $control->request($operator, 'telegram', $target, 'op-concurrent'));

        $otherDatabase = DatabaseConnection::fromEnvironment();
        $lockName = 'fanoos-deploy-' . substr(hash('sha256', $target), 0, 40);
        $acquire = $otherDatabase->prepare('SELECT GET_LOCK(:name, 0)');
        $acquire->execute(['name' => $lockName]);
        self::assert((int) $acquire->fetchColumn() === 1, 'Deployment contention fixture could not acquire the target lock.');
        try {
            $blockedExecutor = new FakeDeploymentExecutor();
            $blocked = (new DeploymentRunner($this->database, $blockedExecutor, 120))->runNext($target);
            self::assert($blocked === null && $blockedExecutor->calls === [], 'Concurrent runner bypassed the per-target advisory lock.');
        } finally {
            $release = $otherDatabase->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => $lockName]);
            $release->fetchColumn();
        }

        $successExecutor = new FakeDeploymentExecutor();
        $success = (new DeploymentRunner($this->database, $successExecutor, 120))->runNext($target);
        self::assert(($success['state'] ?? null) === 'SUCCEEDED', 'Successful deployment did not reach SUCCEEDED.');
        self::assert($successExecutor->calls === ['preflight', 'backup', 'test', 'migrate', 'activate', 'restart', 'health'], 'Deployment phases ran out of order.');

        $backupRequest = $control->request($operator, 'telegram', $target, 'backup-fail');
        $backupExecutor = new FakeDeploymentExecutor('backup');
        $backupResult = (new DeploymentRunner($this->database, $backupExecutor, 120))->runNext($target);
        self::assert(($backupResult['state'] ?? null) === 'FAILED' && ($backupResult['failure_code'] ?? null) === 'backup_verification_failed', 'Failed backup did not stop before activation.');
        self::assert(!in_array('activate', $backupExecutor->calls, true), 'Activation occurred after failed backup.');

        $testRequest = $control->request($operator, 'telegram', $target, 'test-fail');
        $testExecutor = new FakeDeploymentExecutor('test');
        $testResult = (new DeploymentRunner($this->database, $testExecutor, 120))->runNext($target);
        self::assert(($testResult['state'] ?? null) === 'FAILED' && !in_array('activate', $testExecutor->calls, true), 'Failed candidate tests did not block activation.');

        $healthRequest = $control->request($operator, 'telegram', $target, 'health-fail');
        $healthExecutor = new FakeDeploymentExecutor('health');
        $healthResult = (new DeploymentRunner($this->database, $healthExecutor, 120))->runNext($target);
        self::assert(($healthResult['state'] ?? null) === 'ROLLED_BACK', 'Post-activation health failure did not produce durable ROLLED_BACK state.');
        self::assert(in_array('rollback', $healthExecutor->calls, true), 'Post-health failure did not invoke application pointer rollback.');
        self::assert(!in_array('database_rollback', $healthExecutor->calls, true), 'Deployment runner attempted to reverse a forward database migration.');

        foreach ([$requested, $backupRequest, $testRequest, $healthRequest] as $safe) {
            $encoded = json_encode($safe, JSON_THROW_ON_ERROR);
            self::assert(!preg_match('/token|secret|password|private[_-]?key/i', $encoded), 'Deployment API response contains a secret-like field.');
        }

        return $this->assertions;
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

    private function user(string $displayName): string
    {
        $query = $this->database->prepare('SELECT id FROM iam_users WHERE display_name = :name ORDER BY created_at DESC LIMIT 1');
        $query->execute(['name' => $displayName]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new RuntimeException("Fixture user missing: {$displayName}");
        }
        return (string) $id;
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}.");
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

final class FakeDeploymentExecutor implements DeploymentExecutor
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private readonly ?string $failAt = null)
    {
    }

    public function preflight(string $requestId, string $targetKey): array
    {
        $this->step('preflight');
        return ['current_sha' => str_repeat('a', 40), 'candidate_sha' => str_repeat('b', 40), 'noop' => false];
    }

    public function backup(string $requestId, string $targetKey, string $currentSha, string $candidateSha): array
    {
        $this->step('backup', 'backup_verification_failed');
        return ['backup_id' => 'verified-backup-fixture'];
    }

    public function test(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->step('test', 'deployment_tests_failed');
    }

    public function migrate(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->step('migrate', 'deployment_migration_failed');
    }

    public function activate(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->step('activate', 'activation_failed');
    }

    public function restart(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->step('restart', 'restart_failed');
    }

    public function health(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->step('health', 'post_health_failed');
    }

    public function rollback(string $requestId, string $targetKey, string $rollbackSha): void
    {
        $this->calls[] = 'rollback';
        if ($this->failAt === 'rollback') {
            throw new PlatformException('rollback_failed', 'Fixture rollback failed.', 500);
        }
    }

    private function step(string $name, string $failureCode = 'deployment_step_failed'): void
    {
        $this->calls[] = $name;
        if ($this->failAt === $name) {
            throw new PlatformException($failureCode, 'Fixture deployment step failed.', 500);
        }
    }
}
