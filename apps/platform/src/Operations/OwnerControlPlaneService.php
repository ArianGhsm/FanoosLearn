<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use PDO;

final class OwnerControlPlaneService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly DeploymentSnapshotStore $snapshots,
    ) {
    }

    /** @return array<string,mixed> */
    public function overview(string $actorUserId, string $targetKey): array
    {
        $decision = $this->access->platform($actorUserId, 'deployment.manage');
        if (!$decision->allowed) {
            return ['can_manage_deployments' => false];
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,96}$/', $targetKey)) {
            throw new PlatformException('deployment_target_unavailable', 'Deployment target is unavailable.', 404);
        }
        $snapshot = $this->snapshots->read($targetKey);
        if ($snapshot === null) {
            throw new PlatformException('deployment_target_unavailable', 'Deployment target is unavailable.', 404);
        }

        $last = $this->database->prepare(<<<'SQL'
SELECT request.id, request.state, request.current_sha, request.candidate_sha,
       request.safe_failure_code, request.created_at, request.updated_at, request.finished_at
FROM release_update_requests request
JOIN release_update_targets target ON target.id = request.target_id
WHERE target.target_key = :target
ORDER BY request.created_at DESC, request.id DESC
LIMIT 1
SQL);
        $last->execute(['target' => $targetKey]);
        $row = $last->fetch();

        return [
            'can_manage_deployments' => true,
            'target' => $snapshot['target'],
            'current_release_sha' => $snapshot['current_sha'],
            'candidate_sha' => $snapshot['candidate_sha'],
            'update_available' => $snapshot['update_available'],
            'health' => [
                'status' => $snapshot['health_status'],
                'safe_check_code' => $snapshot['safe_check_code'],
                'checked_at' => $snapshot['checked_at'],
            ],
            'last_deployment' => $row === false ? null : [
                'request_id' => (string) $row['id'],
                'state' => (string) $row['state'],
                'current_sha' => $row['current_sha'] === null ? null : (string) $row['current_sha'],
                'candidate_sha' => $row['candidate_sha'] === null ? null : (string) $row['candidate_sha'],
                'failure_code' => $row['safe_failure_code'] === null ? null : (string) $row['safe_failure_code'],
                'created_at' => (string) $row['created_at'],
                'updated_at' => (string) $row['updated_at'],
                'finished_at' => $row['finished_at'] === null ? null : (string) $row['finished_at'],
            ],
        ];
    }
}
