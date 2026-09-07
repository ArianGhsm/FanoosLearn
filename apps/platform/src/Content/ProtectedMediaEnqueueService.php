<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Support\PlatformException;
use PDO;

final class ProtectedMediaEnqueueService
{
    public function __construct(
        private readonly PDO $database,
        private readonly ProtectedMediaJobService $jobs,
    ) {
    }

    /** @param array<string,int> $limits @return array{job_id:string,completion_key:string,idempotent:bool} */
    public function enqueue(string $workspaceId, string $issuanceId, string $rendererAlgorithmVersion, array $limits = []): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT issuance.watermark_fingerprint, user.display_name
FROM content_delivery_issuances issuance
JOIN iam_users user ON user.id = issuance.user_id
WHERE issuance.id = :issuance AND issuance.workspace_id = :workspace
  AND issuance.revoked_at IS NULL AND issuance.expires_at > UTC_TIMESTAMP(6)
LIMIT 1
SQL);
        $query->execute(['issuance' => $issuanceId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false || !is_string($row['watermark_fingerprint'])) {
            throw new PlatformException('delivery_token_unavailable', 'Protected media issuance is unavailable.', 409);
        }
        $forensic = substr(bin2hex($row['watermark_fingerprint']), 0, 32);
        $label = trim((string) $row['display_name']) . ' · ' . substr($issuanceId, -8);
        return $this->jobs->enqueue($workspaceId, $issuanceId, $rendererAlgorithmVersion, $label, $forensic, $limits);
    }
}
