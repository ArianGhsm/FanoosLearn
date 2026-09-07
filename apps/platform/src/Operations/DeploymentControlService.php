<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class DeploymentControlService
{
    private const TERMINAL = ['SUCCEEDED', 'FAILED', 'ROLLED_BACK'];

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function request(string $actorUserId, string $channel, string $targetKey, string $idempotencyKey): array
    {
        $this->access->requirePlatform($actorUserId, 'deployment.manage');
        if (!in_array($channel, ['telegram', 'web', 'api'], true)
            || $idempotencyKey === '' || strlen($idempotencyKey) > 160
            || !preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $targetKey)) {
            throw new PlatformException('deployment_request_invalid', 'Deployment request is invalid.', 422);
        }
        $digest = hash('sha256', implode('|', [$actorUserId, $channel, $targetKey]), true);

        return Transaction::run($this->database, function () use ($actorUserId, $channel, $targetKey, $idempotencyKey, $digest): array {
            $targetQuery = $this->database->prepare("SELECT id FROM release_update_targets WHERE target_key = :target AND status = 'active' FOR UPDATE");
            $targetQuery->execute(['target' => $targetKey]);
            $targetId = $targetQuery->fetchColumn();
            if ($targetId === false) {
                throw new PlatformException('deployment_target_unavailable', 'Deployment target is unavailable.', 404);
            }

            $existing = $this->database->prepare('SELECT id, request_digest FROM release_update_requests WHERE target_id = :target AND idempotency_key = :key LIMIT 1 FOR UPDATE');
            $existing->execute(['target' => $targetId, 'key' => $idempotencyKey]);
            $prior = $existing->fetch();
            if ($prior !== false) {
                if (!hash_equals((string) $prior['request_digest'], $digest)) {
                    throw new PlatformException('idempotency_conflict', 'Deployment idempotency key conflicts with another request.', 409);
                }
                $result = $this->safeStatus((string) $prior['id']);
                $result['idempotent'] = true;
                return $result;
            }

            $active = $this->database->prepare("SELECT id FROM release_update_requests WHERE target_id = :target AND state NOT IN ('SUCCEEDED','FAILED','ROLLED_BACK') LIMIT 1 FOR UPDATE");
            $active->execute(['target' => $targetId]);
            if ($active->fetchColumn() !== false) {
                throw new PlatformException('deployment_in_progress', 'A deployment is already in progress for this target.', 409);
            }

            $id = Uuid::v7();
            $correlation = Uuid::v7();
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO release_update_requests (
    id, target_id, requested_by_user_id, requested_via_channel, idempotency_key,
    request_digest, state, correlation_id, created_at, updated_at
) VALUES (:id, :target, :user, :channel, :key, :digest, 'REQUESTED', :correlation, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $insert->bindValue(':id', $id);
            $insert->bindValue(':target', (string) $targetId);
            $insert->bindValue(':user', $actorUserId);
            $insert->bindValue(':channel', $channel);
            $insert->bindValue(':key', $idempotencyKey);
            $insert->bindValue(':digest', $digest, PDO::PARAM_LOB);
            $insert->bindValue(':correlation', $correlation);
            $insert->execute();
            $this->event($id, 'REQUESTED', 'request.created', ['channel' => $channel]);
            $this->audit->record(null, $actorUserId, 'deployment.request', 'release_update_request', $id, 'success', ['target_key' => $targetKey], $correlation);
            $result = $this->safeStatus($id);
            $result['idempotent'] = false;
            return $result;
        });
    }

    /** @return array<string,mixed> */
    public function status(string $actorUserId, string $requestId): array
    {
        $this->access->requirePlatform($actorUserId, 'deployment.manage');
        return $this->safeStatus($requestId);
    }

    /** @return array<string,mixed> */
    public function safeStatus(string $requestId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT request.id, target.target_key, target.node_key, target.service_key,
       request.state, request.current_sha, request.candidate_sha, request.rollback_sha,
       request.safe_failure_code, request.correlation_id, request.created_at,
       request.updated_at, request.finished_at
FROM release_update_requests request
JOIN release_update_targets target ON target.id = request.target_id
WHERE request.id = :id
LIMIT 1
SQL);
        $query->execute(['id' => $requestId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('deployment_request_not_found', 'Deployment request was not found.', 404);
        }
        return [
            'request_id' => (string) $row['id'],
            'target' => ['key' => (string) $row['target_key'], 'node' => (string) $row['node_key'], 'service' => (string) $row['service_key']],
            'state' => (string) $row['state'],
            'current_sha' => $row['current_sha'] === null ? null : (string) $row['current_sha'],
            'candidate_sha' => $row['candidate_sha'] === null ? null : (string) $row['candidate_sha'],
            'rollback_sha' => $row['rollback_sha'] === null ? null : (string) $row['rollback_sha'],
            'failure_code' => $row['safe_failure_code'] === null ? null : (string) $row['safe_failure_code'],
            'correlation_id' => (string) $row['correlation_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'finished_at' => $row['finished_at'] === null ? null : (string) $row['finished_at'],
        ];
    }

    /** @param array<string,scalar|bool|null> $detail */
    public function event(string $requestId, string $state, string $eventCode, array $detail = []): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO release_update_events (id, request_id, state, event_code, detail_json, created_at)
VALUES (:id, :request, :state, :event_code, :detail, UTC_TIMESTAMP(6))
SQL)->execute([
            'id' => Uuid::v7(), 'request' => $requestId, 'state' => $state, 'event_code' => $eventCode,
            'detail' => json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
