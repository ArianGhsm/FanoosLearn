<?php

declare(strict_types=1);

namespace Fanoos\Platform\Authorization;

use Fanoos\Platform\Support\PlatformException;
use PDO;

final class AccessGate
{
    public function __construct(
        private readonly PDO $database,
        private readonly ScopeAuthorizer $authorizer,
    ) {
    }

    public function workspace(string $actorUserId, string $workspaceId, string $permission): AuthorizationDecision
    {
        $query = $this->database->prepare(
            "SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :entity_workspace AND workspace_id = :workspace AND archived_at IS NULL",
        );
        $query->execute(['entity_workspace' => $workspaceId, 'workspace' => $workspaceId]);
        $scopeId = $query->fetchColumn();
        if ($scopeId === false) {
            throw new PlatformException('workspace_not_found', 'Workspace was not found.', 404);
        }

        return $this->authorizer->decide($actorUserId, $permission, 'workspace', (string) $scopeId, $workspaceId);
    }

    public function requireWorkspace(string $actorUserId, string $workspaceId, string $permission): AuthorizationDecision
    {
        $decision = $this->workspace($actorUserId, $workspaceId, $permission);
        if (!$decision->allowed) {
            throw new PlatformException('forbidden', 'The scoped permission was not granted.', 403);
        }

        return $decision;
    }

    public function requirePlatform(string $actorUserId, string $permission): AuthorizationDecision
    {
        $decision = $this->authorizer->decide(
            $actorUserId,
            $permission,
            'platform',
            '00000000-0000-7000-8000-000000000001',
            null,
        );
        if (!$decision->allowed) {
            throw new PlatformException('forbidden', 'The platform permission was not granted.', 403);
        }
        return $decision;
    }
}
