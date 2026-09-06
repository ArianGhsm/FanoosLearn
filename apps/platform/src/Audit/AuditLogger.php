<?php

declare(strict_types=1);

namespace Fanoos\Platform\Audit;

use Fanoos\Platform\Support\Uuid;
use PDO;

final class AuditLogger
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function record(
        ?string $workspaceId,
        ?string $actorUserId,
        string $action,
        string $subjectType,
        ?string $subjectId,
        string $outcome = 'success',
        array $metadata = [],
        ?string $correlationId = null,
    ): string {
        $id = Uuid::v7();
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO audit_events (
    id, scope_type, workspace_id, actor_type, actor_id, action, subject_type,
    subject_id, outcome, correlation_id, metadata_json, occurred_at, retention_class
) VALUES (
    :id, :scope_type, :workspace_id, :actor_type, :actor_id, :action, :subject_type,
    :subject_id, :outcome, :correlation_id, :metadata, UTC_TIMESTAMP(6), 'standard'
)
SQL);
        $statement->execute([
            'id' => $id,
            'scope_type' => $workspaceId === null ? 'platform' : 'workspace',
            'workspace_id' => $workspaceId,
            'actor_type' => $actorUserId === null ? 'system' : 'user',
            'actor_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'outcome' => $outcome,
            'correlation_id' => $correlationId,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return $id;
    }
}
