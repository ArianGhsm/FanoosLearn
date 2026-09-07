<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;
use Throwable;

final class DeploymentRunner
{
    private const TERMINAL = ['SUCCEEDED', 'FAILED', 'ROLLED_BACK'];
    private const POST_ACTIVATION = ['ACTIVATING', 'RESTARTING', 'HEALTHCHECK'];

    public function __construct(
        private readonly PDO $database,
        private readonly DeploymentExecutor $executor,
        private readonly int $leaseSeconds = 900,
    ) {
    }

    /** @return array<string,mixed>|null */
    public function runNext(string $targetKey): ?array
    {
        $lockName = self::targetLockName($targetKey);
        $lock = $this->database->prepare('SELECT GET_LOCK(:name, 0)');
        $lock->execute(['name' => $lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            return null;
        }

        try {
            return $this->runLocked($targetKey);
        } finally {
            try {
                $release = $this->database->prepare('SELECT RELEASE_LOCK(:name)');
                $release->execute(['name' => $lockName]);
                $release->fetchColumn();
            } catch (Throwable) {
                // Connection-scoped advisory locks are released by MySQL on disconnect.
            }
        }
    }

    /** @return array<string,mixed>|null */
    private function runLocked(string $targetKey): ?array
    {
        $request = $this->claim($targetKey);
        if ($request === null) {
            return null;
        }
        $requestId = (string) $request['id'];
        $state = (string) $request['state'];
        $currentSha = $request['current_sha'] === null ? null : (string) $request['current_sha'];
        $candidateSha = $request['candidate_sha'] === null ? null : (string) $request['candidate_sha'];
        $rollbackSha = $request['rollback_sha'] === null ? null : (string) $request['rollback_sha'];

        try {
            while (!in_array($state, self::TERMINAL, true)) {
                if ($state === 'REQUESTED') {
                    $state = $this->transition($requestId, 'PREFLIGHT', 'preflight.started');
                    continue;
                }
                if ($state === 'PREFLIGHT') {
                    $preflight = $this->executor->preflight($requestId, $targetKey);
                    $this->requireSha($preflight['current_sha']);
                    $this->requireSha($preflight['candidate_sha']);
                    $currentSha = $preflight['current_sha'];
                    $candidateSha = $preflight['candidate_sha'];
                    $rollbackSha = $currentSha;
                    $this->database->prepare('UPDATE release_update_requests SET current_sha = :current, candidate_sha = :candidate, rollback_sha = :rollback, updated_at = UTC_TIMESTAMP(6) WHERE id = :id')
                        ->execute(['current' => $currentSha, 'candidate' => $candidateSha, 'rollback' => $rollbackSha, 'id' => $requestId]);
                    if ($preflight['noop']) {
                        $state = $this->terminal($requestId, 'SUCCEEDED', null, 'preflight.noop');
                        break;
                    }
                    $state = $this->transition($requestId, 'BACKUP', 'backup.started');
                    continue;
                }
                if ($state === 'BACKUP') {
                    if ($currentSha === null || $candidateSha === null) {
                        throw new PlatformException('deployment_state_invalid', 'Deployment SHA state is incomplete.', 500);
                    }
                    $backup = $this->executor->backup($requestId, $targetKey, $currentSha, $candidateSha);
                    if (($backup['backup_id'] ?? '') === '') {
                        throw new PlatformException('backup_verification_failed', 'Verified backup was not produced.', 500);
                    }
                    $this->event($requestId, 'BACKUP', 'backup.verified', ['verified' => true]);
                    $state = $this->transition($requestId, 'TESTING', 'tests.started');
                    continue;
                }
                if ($state === 'TESTING') {
                    $this->executor->test($requestId, $targetKey, $this->sha($candidateSha));
                    $state = $this->transition($requestId, 'MIGRATING', 'migration.started');
                    continue;
                }
                if ($state === 'MIGRATING') {
                    $this->executor->migrate($requestId, $targetKey, $this->sha($candidateSha));
                    $state = $this->transition($requestId, 'ACTIVATING', 'activation.started');
                    continue;
                }
                if ($state === 'ACTIVATING') {
                    $this->executor->activate($requestId, $targetKey, $this->sha($candidateSha));
                    $state = $this->transition($requestId, 'RESTARTING', 'restart.started');
                    continue;
                }
                if ($state === 'RESTARTING') {
                    $this->executor->restart($requestId, $targetKey, $this->sha($candidateSha));
                    $state = $this->transition($requestId, 'HEALTHCHECK', 'health.started');
                    continue;
                }
                if ($state === 'HEALTHCHECK') {
                    $this->executor->health($requestId, $targetKey, $this->sha($candidateSha));
                    $state = $this->terminal($requestId, 'SUCCEEDED', null, 'deployment.succeeded');
                    break;
                }
                throw new PlatformException('deployment_state_invalid', 'Deployment state is not recognized.', 500);
            }
        } catch (Throwable $error) {
            $failureCode = $error instanceof PlatformException ? $error->errorCode : 'deployment_step_failed';
            if (in_array($state, self::POST_ACTIVATION, true) && $rollbackSha !== null) {
                try {
                    $this->executor->rollback($requestId, $targetKey, $this->sha($rollbackSha));
                    $state = $this->terminal($requestId, 'ROLLED_BACK', $failureCode, 'deployment.rolled_back');
                } catch (Throwable) {
                    $state = $this->terminal($requestId, 'FAILED', 'rollback_failed', 'deployment.rollback_failed');
                }
            } else {
                $state = $this->terminal($requestId, 'FAILED', $failureCode, 'deployment.failed');
            }
        }

        return $this->status($requestId);
    }

    /** @return array<string,mixed>|null */
    private function claim(string $targetKey): ?array
    {
        $leaseSeconds = max(60, min(3600, $this->leaseSeconds));
        return Transaction::run($this->database, function () use ($targetKey, $leaseSeconds): ?array {
            $target = $this->database->prepare("SELECT id FROM release_update_targets WHERE target_key = :target AND status = 'active' FOR UPDATE");
            $target->execute(['target' => $targetKey]);
            $targetId = $target->fetchColumn();
            if ($targetId === false) {
                throw new PlatformException('deployment_target_unavailable', 'Deployment target is unavailable.', 404);
            }
            $query = $this->database->prepare(<<<'SQL'
SELECT id, state, current_sha, candidate_sha, rollback_sha
FROM release_update_requests
WHERE target_id = :target AND state NOT IN ('SUCCEEDED','FAILED','ROLLED_BACK')
  AND (leased_until IS NULL OR leased_until < UTC_TIMESTAMP(6))
ORDER BY created_at, id
LIMIT 1
FOR UPDATE SKIP LOCKED
SQL);
            $query->execute(['target' => $targetId]);
            $row = $query->fetch();
            if ($row === false) {
                return null;
            }
            $leaseToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $sql = "UPDATE release_update_requests SET lease_token_digest = :digest, leased_until = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$leaseSeconds} SECOND), updated_at = UTC_TIMESTAMP(6) WHERE id = :id";
            $update = $this->database->prepare($sql);
            $update->bindValue(':digest', hash('sha256', $leaseToken, true), PDO::PARAM_LOB);
            $update->bindValue(':id', (string) $row['id']);
            $update->execute();
            return $row;
        });
    }

    private function transition(string $requestId, string $state, string $eventCode): string
    {
        $leaseSeconds = max(60, min(3600, $this->leaseSeconds));
        $sql = "UPDATE release_update_requests SET state = :state, leased_until = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$leaseSeconds} SECOND), updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND state NOT IN ('SUCCEEDED','FAILED','ROLLED_BACK')";
        $this->database->prepare($sql)->execute(['state' => $state, 'id' => $requestId]);
        $this->event($requestId, $state, $eventCode);
        return $state;
    }

    private function terminal(string $requestId, string $state, ?string $failureCode, string $eventCode): string
    {
        $this->database->prepare(<<<'SQL'
UPDATE release_update_requests
SET state = :state, safe_failure_code = :failure_code, lease_token_digest = NULL, leased_until = NULL,
    finished_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL)->execute(['state' => $state, 'failure_code' => $failureCode, 'id' => $requestId]);
        $this->event($requestId, $state, $eventCode, $failureCode === null ? [] : ['failure_code' => $failureCode]);
        return $state;
    }

    /** @param array<string,scalar|bool|null> $detail */
    private function event(string $requestId, string $state, string $eventCode, array $detail = []): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO release_update_events (id, request_id, state, event_code, detail_json, created_at)
VALUES (:id, :request, :state, :event_code, :detail, UTC_TIMESTAMP(6))
SQL)->execute([
            'id' => Uuid::v7(), 'request' => $requestId, 'state' => $state, 'event_code' => $eventCode,
            'detail' => json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<string,mixed> */
    private function status(string $requestId): array
    {
        $query = $this->database->prepare('SELECT id, state, current_sha, candidate_sha, rollback_sha, safe_failure_code, updated_at, finished_at FROM release_update_requests WHERE id = :id');
        $query->execute(['id' => $requestId]);
        $row = $query->fetch();
        return $row === false ? [] : [
            'request_id' => (string) $row['id'], 'state' => (string) $row['state'],
            'current_sha' => $row['current_sha'], 'candidate_sha' => $row['candidate_sha'], 'rollback_sha' => $row['rollback_sha'],
            'failure_code' => $row['safe_failure_code'], 'updated_at' => $row['updated_at'], 'finished_at' => $row['finished_at'],
        ];
    }

    private static function targetLockName(string $targetKey): string
    {
        return 'fanoos-deploy-' . substr(hash('sha256', $targetKey), 0, 40);
    }

    private function requireSha(string $sha): void
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
            throw new PlatformException('deployment_sha_invalid', 'Deployment candidate SHA is invalid.', 500);
        }
    }

    private function sha(?string $sha): string
    {
        if ($sha === null) {
            throw new PlatformException('deployment_state_invalid', 'Deployment SHA state is incomplete.', 500);
        }
        $this->requireSha($sha);
        return $sha;
    }
}
