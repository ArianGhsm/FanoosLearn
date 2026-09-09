<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\PlatformException;
use PDO;
use RuntimeException;

final class SecureObjectDownloadService
{
    public function __construct(
        private readonly PDO $database,
        private readonly ProtectedResourceAuthorizer $authorizer,
        private readonly SignedDownloadToken $tokens,
        private readonly FilesystemObjectStore $objects,
    ) {
    }

    /** @return array{stream:resource,mime:string,size:int} */
    public function redeem(string $userId, string $workspaceId, string $token, ?int $now = null): array
    {
        $now ??= time();
        try {
            $payload = $this->tokens->verify($token, $workspaceId, $now);
        } catch (RuntimeException|\JsonException) {
            throw new PlatformException('download_token_invalid', 'Download authorization is invalid or expired.', 403);
        }

        $query = $this->database->prepare(<<<'SQL'
SELECT version.resource_id, version.id AS resource_version_id, version.object_id,
       object_record.classification, object_record.detected_mime, object_record.byte_size,
       object_record.status
FROM content_resource_versions version
JOIN content_objects object_record ON object_record.id = version.object_id
 AND object_record.workspace_id = version.workspace_id
WHERE version.workspace_id = :workspace AND version.id = :version
  AND version.object_id = :object
LIMIT 1
SQL);
        $query->execute([
            'workspace' => $workspaceId,
            'version' => $payload['version'],
            'object' => $payload['object'],
        ]);
        $record = $query->fetch();
        if ($record === false
            || (string) $record['status'] !== 'verified'
            || !hash_equals((string) $record['classification'], (string) $payload['class'])
            || $record['byte_size'] === null) {
            throw new PlatformException('download_unavailable', 'Authorized object is unavailable.', 404);
        }

        $decision = $this->authorizer->decide($userId, $workspaceId, (string) $record['resource_id']);
        if (!$decision['allowed']
            || !hash_equals((string) $record['resource_version_id'], (string) ($decision['resource_version_id'] ?? ''))
            || !hash_equals((string) $record['object_id'], (string) ($decision['object_id'] ?? ''))) {
            throw new PlatformException('resource_access_denied', 'Resource authorization changed before download.', 403);
        }

        $address = new ObjectAddress(
            $workspaceId,
            (string) $record['object_id'],
            (string) $record['resource_version_id'],
            (string) $record['classification'],
        );
        try {
            $stream = $this->objects->openRead($address);
        } catch (RuntimeException) {
            throw new PlatformException('download_unavailable', 'Authorized object is unavailable.', 404);
        }
        $size = (int) $record['byte_size'];
        $mime = trim((string) ($record['detected_mime'] ?? ''));
        if ($mime === '' || !preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $mime)) {
            $mime = 'application/octet-stream';
        }
        return ['stream' => $stream, 'mime' => $mime, 'size' => $size];
    }
}
