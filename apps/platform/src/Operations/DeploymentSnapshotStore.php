<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use Fanoos\Platform\Support\PlatformException;
use PDO;

final class DeploymentSnapshotStore
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @param array{current_sha:string,candidate_sha:string,noop:bool} $preflight */
    public function recordHealthy(string $targetKey, array $preflight): void
    {
        $this->write($targetKey, $preflight['current_sha'], $preflight['candidate_sha'], !$preflight['noop'], 'healthy', null);
    }

    public function recordFailure(string $targetKey, string $safeCode): void
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $safeCode)) {
            $safeCode = 'status_check_failed';
        }
        $target = $this->targetId($targetKey);
        $this->database->prepare(<<<'SQL'
INSERT INTO release_update_snapshots (target_id, current_sha, candidate_sha, update_available, health_status, safe_check_code, checked_at, updated_at)
VALUES (:target, NULL, NULL, NULL, 'degraded', :code, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE health_status = 'degraded', safe_check_code = VALUES(safe_check_code), checked_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
SQL)->execute(['target' => $target, 'code' => $safeCode]);
    }

    /** @return array<string,mixed>|null */
    public function read(string $targetKey): ?array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT target.target_key, target.node_key, target.service_key, snapshot.current_sha,
       snapshot.candidate_sha, snapshot.update_available, snapshot.health_status,
       snapshot.safe_check_code, snapshot.checked_at
FROM release_update_targets target
LEFT JOIN release_update_snapshots snapshot ON snapshot.target_id = target.id
WHERE target.target_key = :target AND target.status = 'active'
LIMIT 1
SQL);
        $query->execute(['target' => $targetKey]);
        $row = $query->fetch();
        if ($row === false) {
            return null;
        }
        return [
            'target' => ['key' => (string) $row['target_key'], 'node' => (string) $row['node_key'], 'service' => (string) $row['service_key']],
            'current_sha' => $row['current_sha'] === null ? null : (string) $row['current_sha'],
            'candidate_sha' => $row['candidate_sha'] === null ? null : (string) $row['candidate_sha'],
            'update_available' => $row['update_available'] === null ? null : (bool) $row['update_available'],
            'health_status' => $row['health_status'] === null ? 'unknown' : (string) $row['health_status'],
            'safe_check_code' => $row['safe_check_code'] === null ? null : (string) $row['safe_check_code'],
            'checked_at' => $row['checked_at'] === null ? null : (string) $row['checked_at'],
        ];
    }

    private function write(string $targetKey, string $currentSha, string $candidateSha, bool $available, string $health, ?string $code): void
    {
        foreach ([$currentSha, $candidateSha] as $sha) {
            if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
                throw new PlatformException('deployment_sha_invalid', 'Deployment snapshot SHA is invalid.', 500);
            }
        }
        $target = $this->targetId($targetKey);
        $this->database->prepare(<<<'SQL'
INSERT INTO release_update_snapshots (target_id, current_sha, candidate_sha, update_available, health_status, safe_check_code, checked_at, updated_at)
VALUES (:target, :current, :candidate, :available, :health, :code, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE current_sha = VALUES(current_sha), candidate_sha = VALUES(candidate_sha),
    update_available = VALUES(update_available), health_status = VALUES(health_status),
    safe_check_code = VALUES(safe_check_code), checked_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
SQL)->execute([
            'target' => $target, 'current' => $currentSha, 'candidate' => $candidateSha,
            'available' => $available ? 1 : 0, 'health' => $health, 'code' => $code,
        ]);
    }

    private function targetId(string $targetKey): string
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $targetKey)) {
            throw new PlatformException('deployment_target_unavailable', 'Deployment target is unavailable.', 404);
        }
        $query = $this->database->prepare("SELECT id FROM release_update_targets WHERE target_key = :target AND status = 'active'");
        $query->execute(['target' => $targetKey]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new PlatformException('deployment_target_unavailable', 'Deployment target is unavailable.', 404);
        }
        return (string) $id;
    }
}
