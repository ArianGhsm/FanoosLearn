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
    private const MAXIMUM_INPUT_BYTES_CEILING = 209715200;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly FilesystemObjectStore $sourceStore,
        private readonly AuditLogger $audit,
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

    /**
     * Lists resources in one workspace that have at least one completed
     * `fanoos-raster-v2` candidate job -- i.e. resources an owner could
     * actually investigate. Deliberately uses `linked()` at the HTTP layer,
     * never `linkedWorkspace()`, for the same reason as `candidates()`: an
     * owner is platform-scoped and need not be a member of the workspace
     * under suspicion. This lets the bot offer a resource picker instead of
     * asking the owner to type a resource UUID, without reusing the
     * membership-gated resource catalog (which would wrongly refuse a
     * non-member owner).
     *
     * @return array{items: list<array{resource_id:string,title:string,candidate_count:int}>, next_cursor:?string}
     */
    public function resourcesWithCandidates(string $actorUserId, string $workspaceId, int $limit, ?string $cursor): array
    {
        $this->access->requirePlatform($actorUserId, 'protected_media.forensic.investigate');
        $workspaceId = trim($workspaceId);
        if ($workspaceId === '') {
            throw new PlatformException('forensic_request_invalid', 'A workspace identifier is required.', 422);
        }
        $limit = $this->boundedPageLimit($limit);
        $offset = $this->decodePageCursor($cursor);
        $query = $this->database->prepare(<<<'SQL'
SELECT resource.id AS resource_id, resource.title AS title, COUNT(*) AS candidate_count
FROM protected_media_jobs job
JOIN content_resources resource ON resource.id = job.resource_id AND resource.workspace_id = job.workspace_id
WHERE job.workspace_id = :workspace AND job.state = 'completed' AND job.renderer_algorithm_version = :renderer
GROUP BY resource.id, resource.title
ORDER BY resource.title ASC, resource.id ASC
LIMIT :limit OFFSET :offset
SQL);
        $query->bindValue(':workspace', $workspaceId);
        $query->bindValue(':renderer', self::RENDERER_ALGORITHM_VERSION);
        $query->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $rows = $query->fetchAll();
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        return [
            'items' => array_map(static fn (array $row): array => [
                'resource_id' => (string) $row['resource_id'],
                'title' => (string) $row['title'],
                'candidate_count' => (int) $row['candidate_count'],
            ], $rows),
            'next_cursor' => $hasMore ? $this->encodePageCursor($offset + count($rows)) : null,
        ];
    }

    private function boundedPageLimit(int $limit): int
    {
        return max(1, min(50, $limit));
    }

    private function decodePageCursor(?string $cursor): int
    {
        if ($cursor === null || $cursor === '') {
            return 0;
        }
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $cursor)) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        $padding = (4 - strlen($cursor) % 4) % 4;
        $decoded = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
        if (!is_string($decoded) || !preg_match('/^o:[0-9]{1,7}$/', $decoded)) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        $offset = (int) substr($decoded, 2);
        if ($offset > 1_000_000) {
            throw new PlatformException('cursor_invalid', 'Pagination cursor is invalid.', 422);
        }
        return $offset;
    }

    private function encodePageCursor(int $offset): string
    {
        return rtrim(strtr(base64_encode('o:' . $offset), '+/', '-_'), '=');
    }

    /**
     * `jobId` is exactly the `issuance_id` a `candidates()` row returned --
     * the object/version/classification tuple is read from that job's own
     * row, never accepted from the caller, so this can only ever redeem the
     * original behind a real, completed, secure-raster candidate job in the
     * caller's own workspace. It cannot be turned into a general "fetch any
     * verified object" capability by supplying an unrelated version/
     * classification for a real object id.
     *
     * @return array{stream:resource,size:int,mime:string}
     */
    public function originalSource(string $actorUserId, string $workspaceId, string $jobId): array
    {
        $this->access->requirePlatform($actorUserId, 'protected_media.forensic.investigate');
        $query = $this->database->prepare(<<<'SQL'
SELECT job.object_id, job.resource_version_id, job.limits_json,
       object_record.classification, object_record.byte_size, object_record.detected_mime, object_record.status
FROM protected_media_jobs job
JOIN content_objects object_record ON object_record.id = job.object_id AND object_record.workspace_id = job.workspace_id
WHERE job.id = :job AND job.workspace_id = :workspace
  AND job.state = 'completed' AND job.renderer_algorithm_version = :renderer
LIMIT 1
SQL);
        $query->execute(['job' => $jobId, 'workspace' => $workspaceId, 'renderer' => self::RENDERER_ALGORITHM_VERSION]);
        $row = $query->fetch();
        if ($row === false || (string) $row['status'] !== 'verified' || (string) $row['detected_mime'] !== 'application/pdf') {
            throw new PlatformException('input_unavailable', 'Forensic source input is unavailable or has an unexpected MIME type.', 409);
        }
        $limits = json_decode((string) $row['limits_json'], true, 16, JSON_THROW_ON_ERROR);
        $maximumBytes = is_array($limits) && isset($limits['max_input_bytes'])
            ? min(self::MAXIMUM_INPUT_BYTES_CEILING, max(1, (int) $limits['max_input_bytes']))
            : self::MAXIMUM_INPUT_BYTES_CEILING;
        $size = (int) $row['byte_size'];
        if ($size < 5 || $size > $maximumBytes) {
            throw new PlatformException('input_too_large', 'Forensic source input exceeds the configured limit.', 422);
        }
        $address = new ObjectAddress($workspaceId, (string) $row['object_id'], (string) $row['resource_version_id'], (string) $row['classification']);
        try {
            $stream = $this->sourceStore->openRead($address);
        } catch (Throwable) {
            throw new PlatformException('input_unavailable', 'Forensic source input is unavailable.', 409);
        }
        $this->audit->record($workspaceId, $actorUserId, 'protected_media.forensic.source_redeem', 'protected_media_job', $jobId, 'success', [
            'bytes' => $size,
        ]);
        return ['stream' => $stream, 'size' => $size, 'mime' => 'application/pdf'];
    }
}
