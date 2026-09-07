<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use PDOException;
use Throwable;

final class ProtectedMediaTransferService
{
    public function __construct(
        private readonly PDO $database,
        private readonly ProtectedResourceAuthorizer $authorizer,
        private readonly SignedDownloadToken $sourceCapabilities,
        private readonly FilesystemObjectStore $sourceStore,
        private readonly ProtectedMediaArtifactStore $artifactStore,
        private readonly ProtectedMediaArtifactCapability $artifactCapabilities,
        private readonly ProtectedMediaUploadCapability $uploadCapabilities,
        private readonly ProtectedMediaJobService $jobs,
        private readonly AuditLogger $audit,
        private readonly int $artifactTtlSeconds = 86400,
        private readonly int $maximumPublishedBytes = 104857600,
    ) {
    }

    /** @return array{stream:resource,size:int,mime:string} */
    public function redeemSource(string $jobId, string $leaseToken, string $objectCapability, ?int $now = null): array
    {
        $now ??= time();
        $job = $this->leasedJob($jobId, $leaseToken, $now);
        $this->requireIssuance($job, $now);
        try {
            $payload = $this->sourceCapabilities->verify($objectCapability, (string) $job['workspace_id'], $now);
        } catch (Throwable) {
            throw new PlatformException('object_capability_invalid', 'Protected media source capability is invalid or expired.', 401);
        }
        if (!hash_equals((string) $job['object_id'], $payload['object'])
            || !hash_equals((string) $job['resource_version_id'], $payload['version'])
            || !hash_equals((string) $job['classification'], $payload['class'])) {
            throw new PlatformException('object_capability_invalid', 'Protected media source capability does not match the leased job.', 401);
        }
        $this->requireCurrentAuthorization($job);
        $limits = $this->limits($job);
        $size = (int) $job['byte_size'];
        if ($size < 5 || $size > (int) $limits['max_input_bytes']) {
            throw new PlatformException('input_too_large', 'Protected media input exceeds the configured limit.', 422);
        }
        if ((string) $job['object_status'] !== 'verified' || (string) $job['detected_mime'] !== 'application/pdf') {
            throw new PlatformException('input_unavailable', 'Protected media input is unavailable or has an unexpected MIME type.', 409);
        }
        $address = new ObjectAddress(
            (string) $job['workspace_id'],
            (string) $job['object_id'],
            (string) $job['resource_version_id'],
            (string) $job['classification'],
        );
        try {
            $stream = $this->sourceStore->openRead($address);
        } catch (Throwable) {
            throw new PlatformException('input_unavailable', 'Protected media input is unavailable.', 409);
        }
        $this->audit->record((string) $job['workspace_id'], null, 'protected_media.source_redeem', 'protected_media_job', $jobId, 'success', [
            'object_id' => (string) $job['object_id'], 'bytes' => $size,
        ]);
        return ['stream' => $stream, 'size' => $size, 'mime' => 'application/pdf'];
    }

    /** @return array{upload_capability:string,checksum_sha256:string,size:int,mime:string,expires_at:string} */
    public function authorizeArtifactPublish(
        string $jobId,
        string $leaseToken,
        string $completionKey,
        string $checksum,
        int $size,
        string $mime,
        ?int $now = null,
    ): array {
        $now ??= time();
        $job = $this->leasedJob($jobId, $leaseToken, $now);
        $this->requireIssuance($job, $now);
        if (!hash_equals((string) $job['completion_key'], $completionKey)) {
            throw new PlatformException('protected_media_job_not_found', 'Protected media job was not found.', 404);
        }
        $this->requireCurrentAuthorization($job);
        $checksum = strtolower($checksum);
        $limit = $this->outputLimit($job);
        if (!preg_match('/^[0-9a-f]{64}$/', $checksum) || $size < 5 || $size > $limit || $mime !== 'application/pdf') {
            throw new PlatformException('protected_media_output_invalid', 'Protected media output metadata is invalid.', 422);
        }
        $leaseExpiry = $this->timestamp((string) $job['leased_until']);
        $issuanceExpiry = $this->timestamp((string) $job['issuance_expires_at']);
        $ttl = min(120, $leaseExpiry - $now, $issuanceExpiry - $now);
        if ($ttl < 1) {
            throw new PlatformException('protected_media_lease_invalid', 'Protected media lease is invalid.', 409);
        }
        return [
            'upload_capability' => $this->uploadCapabilities->issue($jobId, $checksum, $size, $mime, $now, $ttl),
            'checksum_sha256' => $checksum,
            'size' => $size,
            'mime' => $mime,
            'expires_at' => gmdate(DATE_ATOM, $now + $ttl),
        ];
    }

    /** @return array{artifact_ref:string,checksum_sha256:string,size:int,mime:string,idempotent:bool} */
    public function publishAuthorizedArtifact(string $uploadCapability, string $bytes, ?int $now = null): array
    {
        $now ??= time();
        $authorization = $this->uploadCapabilities->verify($uploadCapability, $now);
        $job = $this->leasedJobById($authorization['job'], $now);
        $this->requireIssuance($job, $now);
        $this->requireCurrentAuthorization($job);
        $size = strlen($bytes);
        $checksum = hash('sha256', $bytes);
        if ($size !== $authorization['size'] || $authorization['mime'] !== 'application/pdf'
            || !hash_equals($authorization['sha256'], $checksum)
            || $size < 5 || $size > $this->outputLimit($job) || !str_starts_with($bytes, '%PDF-')) {
            throw new PlatformException('protected_media_output_invalid', 'Protected media output bytes do not match the upload authorization.', 422);
        }
        return $this->storeArtifact($job, $bytes, $checksum, $size, $now);
    }

    /** @param array<string,mixed> $result @return array{job_id:string,state:string,idempotent:bool} */
    public function complete(string $jobId, string $leaseToken, string $completionKey, array $result): array
    {
        $artifactRef = (string) ($result['artifact_ref'] ?? '');
        $artifact = $this->artifactForJob($jobId);
        if ($artifact === null || !hash_equals((string) $artifact['artifact_ref'], $artifactRef)
            || $artifact['state'] !== 'active' || $this->timestamp((string) $artifact['expires_at']) < time()) {
            throw new PlatformException('protected_media_artifact_unavailable', 'Published protected media artifact is unavailable.', 409);
        }
        $checksum = strtolower((string) ($result['checksum_sha256'] ?? ''));
        if (!hash_equals(bin2hex((string) $artifact['checksum_sha256']), $checksum)
            || (int) $artifact['byte_size'] !== (int) ($result['size'] ?? 0)
            || (string) $artifact['mime'] !== (string) ($result['mime'] ?? '')) {
            throw new PlatformException('protected_media_artifact_mismatch', 'Protected media completion does not match the published artifact.', 409);
        }
        return $this->jobs->complete($jobId, $leaseToken, $completionKey, $result);
    }

    /** @return array<string,mixed> */
    public function issueDerivative(string $userId, string $workspaceId, string $jobId, string $platform, ?int $now = null): array
    {
        $now ??= time();
        if (!in_array($platform, ['telegram', 'bale'], true)) {
            throw new PlatformException('messaging_platform_invalid', 'Messaging platform is not supported.', 422);
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT job.id AS job_id, job.state AS job_state, job.resource_id, job.resource_version_id,
       artifact.id AS artifact_id, artifact.user_id, artifact.checksum_sha256, artifact.byte_size,
       artifact.mime, artifact.state AS artifact_state, artifact.expires_at
FROM protected_media_jobs job
JOIN protected_media_artifacts artifact ON artifact.job_id = job.id AND artifact.workspace_id = job.workspace_id
WHERE job.id = :job AND job.workspace_id = :workspace
LIMIT 1
SQL);
        $query->execute(['job' => $jobId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false || $row['job_state'] !== 'completed' || $row['artifact_state'] !== 'active'
            || !hash_equals((string) $row['user_id'], $userId) || $this->timestamp((string) $row['expires_at']) < $now) {
            throw new PlatformException('protected_media_artifact_unavailable', 'Personalized artifact is unavailable.', 404);
        }
        $decision = $this->authorizer->decide($userId, $workspaceId, (string) $row['resource_id']);
        if (!$decision['allowed'] || !hash_equals((string) $row['resource_version_id'], (string) $decision['resource_version_id'])) {
            throw new PlatformException('resource_access_denied', 'Protected media authorization changed.', 403);
        }
        $ttl = min(120, max(1, $this->timestamp((string) $row['expires_at']) - $now));
        return [
            'job_id' => $jobId,
            'resource_id' => (string) $row['resource_id'],
            'resource_version_id' => (string) $row['resource_version_id'],
            'artifact_capability' => $this->artifactCapabilities->issue((string) $row['artifact_id'], $workspaceId, $userId, $jobId, $platform, $now, $ttl),
            'checksum_sha256' => bin2hex((string) $row['checksum_sha256']),
            'size' => (int) $row['byte_size'],
            'mime' => (string) $row['mime'],
            'expires_at' => gmdate(DATE_ATOM, $now + $ttl),
        ];
    }

    /** @return array{stream:resource,size:int,mime:string} */
    public function redeemDerivative(string $userId, string $workspaceId, string $platform, string $capability, ?int $now = null): array
    {
        $now ??= time();
        $payload = $this->artifactCapabilities->verify($capability, $workspaceId, $userId, $platform, $now);
        $query = $this->database->prepare(<<<'SQL'
SELECT artifact.id, artifact.job_id, artifact.user_id, artifact.resource_id, artifact.resource_version_id,
       artifact.checksum_sha256, artifact.byte_size, artifact.mime, artifact.storage_key,
       artifact.state, artifact.expires_at, job.state AS job_state
FROM protected_media_artifacts artifact
JOIN protected_media_jobs job ON job.id = artifact.job_id
WHERE artifact.id = :artifact AND artifact.workspace_id = :workspace
LIMIT 1
SQL);
        $query->execute(['artifact' => $payload['artifact'], 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false || !hash_equals((string) $row['job_id'], $payload['job'])
            || !hash_equals((string) $row['user_id'], $userId) || $row['state'] !== 'active'
            || $row['job_state'] !== 'completed' || $this->timestamp((string) $row['expires_at']) < $now) {
            throw new PlatformException('protected_media_artifact_unavailable', 'Personalized artifact is unavailable.', 404);
        }
        $decision = $this->authorizer->decide($userId, $workspaceId, (string) $row['resource_id']);
        if (!$decision['allowed'] || !hash_equals((string) $row['resource_version_id'], (string) $decision['resource_version_id'])) {
            throw new PlatformException('resource_access_denied', 'Protected media authorization changed.', 403);
        }
        try {
            $stream = $this->artifactStore->openRead((string) $row['storage_key']);
        } catch (Throwable) {
            throw new PlatformException('protected_media_artifact_unavailable', 'Personalized artifact bytes are unavailable.', 409);
        }
        $this->database->prepare('UPDATE protected_media_artifacts SET last_accessed_at = UTC_TIMESTAMP(6) WHERE id = :id')->execute(['id' => $row['id']]);
        $this->audit->record($workspaceId, $userId, 'protected_media.artifact_redeem', 'protected_media_artifact', (string) $row['id'], 'success', [
            'job_id' => (string) $row['job_id'], 'bytes' => (int) $row['byte_size'],
        ]);
        return ['stream' => $stream, 'size' => (int) $row['byte_size'], 'mime' => (string) $row['mime']];
    }

    public function cleanupExpired(?int $now = null, int $limit = 100): int
    {
        $now ??= time();
        $limit = max(1, min(1000, $limit));
        $query = $this->database->prepare("SELECT id, storage_key FROM protected_media_artifacts WHERE state = 'active' AND expires_at <= FROM_UNIXTIME(:now) ORDER BY expires_at LIMIT {$limit}");
        $query->bindValue(':now', $now, PDO::PARAM_INT);
        $query->execute();
        $count = 0;
        foreach ($query->fetchAll() as $row) {
            try {
                $this->artifactStore->delete((string) $row['storage_key']);
            } catch (Throwable) {
                continue;
            }
            $this->database->prepare("UPDATE protected_media_artifacts SET state = 'deleted', deleted_at = UTC_TIMESTAMP(6) WHERE id = :id AND state = 'active'")
                ->execute(['id' => $row['id']]);
            ++$count;
        }
        return $count;
    }

    /** @param array<string,mixed> $job @return array{artifact_ref:string,checksum_sha256:string,size:int,mime:string,idempotent:bool} */
    private function storeArtifact(array $job, string $bytes, string $checksum, int $size, int $now): array
    {
        $existing = $this->artifactForJob((string) $job['id']);
        if ($existing !== null) {
            return $this->existingArtifactResult($existing, $checksum, $size);
        }
        $artifactId = Uuid::v7();
        $artifactRef = 'pma:' . $artifactId;
        $storageKey = $this->artifactStore->put($artifactId, $bytes, $checksum);
        try {
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO protected_media_artifacts (
    id, job_id, workspace_id, issuance_id, user_id, resource_id, resource_version_id,
    artifact_ref, checksum_sha256, byte_size, mime, storage_key, state, expires_at, created_at
) VALUES (
    :id, :job, :workspace, :issuance, :user, :resource, :version,
    :artifact_ref, :checksum, :size, 'application/pdf', :storage_key, 'active', FROM_UNIXTIME(:expires), UTC_TIMESTAMP(6)
)
SQL);
            $insert->bindValue(':id', $artifactId);
            $insert->bindValue(':job', (string) $job['id']);
            $insert->bindValue(':workspace', (string) $job['workspace_id']);
            $insert->bindValue(':issuance', (string) $job['issuance_id']);
            $insert->bindValue(':user', (string) $job['user_id']);
            $insert->bindValue(':resource', (string) $job['resource_id']);
            $insert->bindValue(':version', (string) $job['resource_version_id']);
            $insert->bindValue(':artifact_ref', $artifactRef);
            $insert->bindValue(':checksum', hex2bin($checksum), PDO::PARAM_LOB);
            $insert->bindValue(':size', $size, PDO::PARAM_INT);
            $insert->bindValue(':storage_key', $storageKey);
            $insert->bindValue(':expires', $now + max(300, min(604800, $this->artifactTtlSeconds)), PDO::PARAM_INT);
            $insert->execute();
        } catch (PDOException $error) {
            try {
                $this->artifactStore->delete($storageKey);
            } catch (Throwable) {
            }
            if ($error->getCode() === '23000') {
                $raced = $this->artifactForJob((string) $job['id']);
                if ($raced !== null) {
                    return $this->existingArtifactResult($raced, $checksum, $size);
                }
            }
            throw $error;
        } catch (Throwable $error) {
            try {
                $this->artifactStore->delete($storageKey);
            } catch (Throwable) {
            }
            throw $error;
        }
        $this->audit->record((string) $job['workspace_id'], null, 'protected_media.artifact_publish', 'protected_media_artifact', $artifactId, 'success', [
            'job_id' => (string) $job['id'], 'bytes' => $size, 'checksum_prefix' => substr($checksum, 0, 16),
        ]);
        return ['artifact_ref' => $artifactRef, 'checksum_sha256' => $checksum, 'size' => $size, 'mime' => 'application/pdf', 'idempotent' => false];
    }

    /** @param array<string,mixed> $existing @return array{artifact_ref:string,checksum_sha256:string,size:int,mime:string,idempotent:bool} */
    private function existingArtifactResult(array $existing, string $checksum, int $size): array
    {
        if (!hash_equals(bin2hex((string) $existing['checksum_sha256']), $checksum)
            || (int) $existing['byte_size'] !== $size || (string) $existing['mime'] !== 'application/pdf') {
            throw new PlatformException('protected_media_artifact_conflict', 'A different artifact is already published for this job.', 409);
        }
        return ['artifact_ref' => (string) $existing['artifact_ref'], 'checksum_sha256' => $checksum, 'size' => $size, 'mime' => 'application/pdf', 'idempotent' => true];
    }

    /** @return array<string,mixed> */
    private function leasedJob(string $jobId, string $leaseToken, int $now): array
    {
        $row = $this->job($jobId);
        if ($row['state'] !== 'leased' || $leaseToken === '' || !is_string($row['lease_token_digest'])
            || !hash_equals((string) $row['lease_token_digest'], hash('sha256', $leaseToken, true))
            || $row['leased_until'] === null || $this->timestamp((string) $row['leased_until']) < $now) {
            throw new PlatformException('protected_media_lease_invalid', 'Protected media lease is invalid.', 409);
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function leasedJobById(string $jobId, int $now): array
    {
        $row = $this->job($jobId);
        if ($row['state'] !== 'leased' || $row['leased_until'] === null || $this->timestamp((string) $row['leased_until']) < $now) {
            throw new PlatformException('protected_media_lease_invalid', 'Protected media lease is invalid.', 409);
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function job(string $jobId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT job.id, job.workspace_id, job.resource_id, job.resource_version_id, job.object_id,
       job.issuance_id, job.completion_key, job.state, job.lease_token_digest, job.leased_until,
       job.limits_json, issuance.user_id, issuance.revoked_at AS issuance_revoked_at,
       issuance.expires_at AS issuance_expires_at, object_record.classification,
       object_record.byte_size, object_record.detected_mime, object_record.status AS object_status
FROM protected_media_jobs job
JOIN content_delivery_issuances issuance ON issuance.id = job.issuance_id AND issuance.workspace_id = job.workspace_id
JOIN content_objects object_record ON object_record.id = job.object_id AND object_record.workspace_id = job.workspace_id
WHERE job.id = :job
LIMIT 1
SQL);
        $query->execute(['job' => $jobId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('protected_media_job_not_found', 'Protected media job was not found.', 404);
        }
        return $row;
    }

    /** @param array<string,mixed> $job */
    private function requireIssuance(array $job, int $now): void
    {
        if ($job['issuance_revoked_at'] !== null || $this->timestamp((string) $job['issuance_expires_at']) < $now) {
            throw new PlatformException('authorization_changed', 'Protected media authorization changed.', 403);
        }
    }

    /** @param array<string,mixed> $job */
    private function requireCurrentAuthorization(array $job): void
    {
        $decision = $this->authorizer->decide((string) $job['user_id'], (string) $job['workspace_id'], (string) $job['resource_id']);
        if (!$decision['allowed'] || !hash_equals((string) $job['resource_version_id'], (string) $decision['resource_version_id'])) {
            throw new PlatformException('authorization_changed', 'Protected media authorization changed.', 403);
        }
    }

    /** @param array<string,mixed> $job @return array<string,int> */
    private function limits(array $job): array
    {
        $limits = json_decode((string) $job['limits_json'], true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($limits)) {
            throw new PlatformException('protected_media_job_invalid', 'Protected media job limits are invalid.', 500);
        }
        return [
            'max_input_bytes' => (int) ($limits['max_input_bytes'] ?? 0),
            'max_pages' => (int) ($limits['max_pages'] ?? 0),
            'max_seconds' => (int) ($limits['max_seconds'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $job */
    private function outputLimit(array $job): int
    {
        $limits = $this->limits($job);
        return min($this->maximumPublishedBytes, max(1, (int) $limits['max_input_bytes'] * 4));
    }

    /** @return array<string,mixed>|null */
    private function artifactForJob(string $jobId): ?array
    {
        $query = $this->database->prepare('SELECT * FROM protected_media_artifacts WHERE job_id = :job LIMIT 1');
        $query->execute(['job' => $jobId]);
        $row = $query->fetch();
        return $row === false ? null : $row;
    }

    private function timestamp(string $value): int
    {
        $timestamp = strtotime($value . ' UTC');
        return $timestamp === false ? 0 : $timestamp;
    }
}
