<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Support\PlatformException;
use PDO;
use Throwable;

/**
 * Owner-only leak-attribution lookup: given a resource an owner suspects was
 * leaked, returns exactly the recipients who were actually issued a marked
 * derivative of it (never a guess, never every delivery in the system), plus
 * a way to re-fetch each candidate's original source bytes so the detector
 * can recompute the same fingerprint material the worker derived when it
 * marked that derivative. This service never sees or needs the fingerprint
 * key itself -- that stays in the Python detector's own environment, the
 * same way it stays in the worker's.
 */
final class ProtectedMediaForensicService
{
    private const RENDERER_ALGORITHM_VERSION = 'fanoos-raster-v2';

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly FilesystemObjectStore $sourceStore,
        private readonly AuditLogger $audit,
        private readonly int $maximumSourceBytes = 52428800,
    ) {
    }

    /** @return array{candidates: list<array{issuance_id:string,user_id:string,document_id:string,object_id:string,resource_version_id:string,classification:string}>} */
    public function candidates(string $actorUserId, string $workspaceId, string $resourceId): array
    {
        $this->access->requirePlatform($actorUserId, 'protected_media.forensic.investigate');
        $workspaceId = trim($workspaceId);
        $resourceId = trim($resourceId);
        if ($workspaceId === '' || $resourceId === '') {
            throw new PlatformException('forensic_request_invalid', 'Workspace and resource identifiers are required.', 422);
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT job.id AS job_id, job.object_id, job.resource_version_id, object_record.classification,
       issuance.user_id
FROM protected_media_jobs job
JOIN content_delivery_issuances issuance ON issuance.id = job.issuance_id AND issuance.workspace_id = job.workspace_id
JOIN content_objects object_record ON object_record.id = job.object_id AND object_record.workspace_id = job.workspace_id
WHERE job.workspace_id = :workspace AND job.resource_id = :resource
  AND job.state = 'completed' AND job.renderer_algorithm_version = :renderer
ORDER BY job.completed_at
SQL);
        $query->execute([
            'workspace' => $workspaceId,
            'resource' => $resourceId,
            'renderer' => self::RENDERER_ALGORITHM_VERSION,
        ]);
        $rows = $query->fetchAll();
        $this->audit->record($workspaceId, $actorUserId, 'protected_media.forensic.candidates', 'content_resource', $resourceId, 'success', [
            'candidate_count' => count($rows),
        ]);
        return ['candidates' => array_map(static fn (array $row): array => [
            'issuance_id' => (string) $row['job_id'],
            'user_id' => (string) $row['user_id'],
            'document_id' => $resourceId,
            'object_id' => (string) $row['object_id'],
            'resource_version_id' => (string) $row['resource_version_id'],
            'classification' => (string) $row['classification'],
        ], $rows)];
    }

    /** @return array{stream:resource,size:int,mime:string} */
    public function originalSource(
        string $actorUserId,
        string $workspaceId,
        string $objectId,
        string $resourceVersionId,
        string $classification,
    ): array {
        $this->access->requirePlatform($actorUserId, 'protected_media.forensic.investigate');
        $query = $this->database->prepare(
            'SELECT byte_size, detected_mime, status FROM content_objects WHERE id = :object AND workspace_id = :workspace LIMIT 1',
        );
        $query->execute(['object' => $objectId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false || (string) $row['status'] !== 'verified' || (string) $row['detected_mime'] !== 'application/pdf') {
            throw new PlatformException('input_unavailable', 'Forensic source input is unavailable or has an unexpected MIME type.', 409);
        }
        $size = (int) $row['byte_size'];
        if ($size < 5 || $size > $this->maximumSourceBytes) {
            throw new PlatformException('input_too_large', 'Forensic source input exceeds the configured limit.', 422);
        }
        $address = new ObjectAddress($workspaceId, $objectId, $resourceVersionId, $classification);
        try {
            $stream = $this->sourceStore->openRead($address);
        } catch (Throwable) {
            throw new PlatformException('input_unavailable', 'Forensic source input is unavailable.', 409);
        }
        $this->audit->record($workspaceId, $actorUserId, 'protected_media.forensic.source_redeem', 'content_object', $objectId, 'success', [
            'bytes' => $size,
        ]);
        return ['stream' => $stream, 'size' => $size, 'mime' => 'application/pdf'];
    }
}
