<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class ProtectedMediaJobService
{
    private const FAILURE_CODES = [
        'input_unavailable', 'authorization_changed', 'input_too_large', 'page_limit',
        'time_limit', 'render_failed', 'output_invalid', 'internal_error',
    ];

    public function __construct(
        private readonly PDO $database,
        private readonly ProtectedResourceAuthorizer $authorizer,
        private readonly SignedDownloadToken $capabilities,
        private readonly int $leaseSeconds = 180,
        private readonly int $maximumAttempts = 3,
    ) {
    }

    /** @param array<string,int> $limits @return array{job_id:string,completion_key:string,idempotent:bool} */
    public function enqueue(
        string $workspaceId,
        string $issuanceId,
        string $rendererAlgorithmVersion,
        string $watermarkLabel,
        string $forensicId,
        array $limits = [],
    ): array {
        $rendererAlgorithmVersion = trim($rendererAlgorithmVersion);
        $watermarkLabel = trim($watermarkLabel);
        $forensicId = trim($forensicId);
        if (!preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $rendererAlgorithmVersion)
            || $watermarkLabel === '' || mb_strlen($watermarkLabel) > 255
            || !preg_match('/^[A-Za-z0-9._:-]{4,96}$/', $forensicId)) {
            throw new PlatformException('protected_media_job_invalid', 'Protected media job metadata is invalid.', 422);
        }
        $limits = [
            'max_input_bytes' => max(1024, min(200 * 1024 * 1024, (int) ($limits['max_input_bytes'] ?? 50 * 1024 * 1024))),
            'max_pages' => max(1, min(2000, (int) ($limits['max_pages'] ?? 500))),
            'max_seconds' => max(5, min(1800, (int) ($limits['max_seconds'] ?? 300))),
        ];
        $completionKey = hash('sha256', implode('|', [$issuanceId, $rendererAlgorithmVersion, $forensicId]));

        return Transaction::run($this->database, function () use ($workspaceId, $issuanceId, $rendererAlgorithmVersion, $watermarkLabel, $forensicId, $limits, $completionKey): array {
            $existing = $this->database->prepare('SELECT id FROM protected_media_jobs WHERE completion_key = :key LIMIT 1');
            $existing->execute(['key' => $completionKey]);
            $existingId = $existing->fetchColumn();
            if ($existingId !== false) {
                return ['job_id' => (string) $existingId, 'completion_key' => $completionKey, 'idempotent' => true];
            }

            $issuance = $this->database->prepare(<<<'SQL'
SELECT issuance.resource_id, issuance.resource_version_id, issuance.object_id, issuance.user_id,
       object_record.byte_size
FROM content_delivery_issuances issuance
JOIN content_objects object_record ON object_record.id = issuance.object_id AND object_record.workspace_id = issuance.workspace_id
WHERE issuance.id = :issuance AND issuance.workspace_id = :workspace
  AND issuance.revoked_at IS NULL AND issuance.expires_at > UTC_TIMESTAMP(6)
LIMIT 1
FOR UPDATE
SQL);
            $issuance->execute(['issuance' => $issuanceId, 'workspace' => $workspaceId]);
            $row = $issuance->fetch();
            if ($row === false) {
                throw new PlatformException('delivery_token_unavailable', 'Protected media issuance is unavailable.', 409);
            }
            $decision = $this->authorizer->decide((string) $row['user_id'], $workspaceId, (string) $row['resource_id']);
            if (!$decision['allowed'] || !hash_equals((string) $row['resource_version_id'], (string) $decision['resource_version_id'])) {
                throw new PlatformException('resource_access_denied', 'Protected media authorization changed.', 403);
            }
            if ((int) $row['byte_size'] > $limits['max_input_bytes']) {
                throw new PlatformException('input_too_large', 'Protected media input exceeds the configured limit.', 422);
            }

            $id = Uuid::v7();
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO protected_media_jobs (
    id, workspace_id, resource_id, resource_version_id, object_id, issuance_id,
    completion_key, state, renderer_algorithm_version, watermark_label, forensic_id,
    limits_json, attempt_count, created_at, updated_at
) VALUES (
    :id, :workspace, :resource, :version, :object, :issuance,
    :completion_key, 'queued', :renderer, :watermark, :forensic,
    :limits, 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL);
            $insert->execute([
                'id' => $id, 'workspace' => $workspaceId, 'resource' => $row['resource_id'],
                'version' => $row['resource_version_id'], 'object' => $row['object_id'], 'issuance' => $issuanceId,
                'completion_key' => $completionKey, 'renderer' => $rendererAlgorithmVersion,
                'watermark' => $watermarkLabel, 'forensic' => $forensicId,
                'limits' => json_encode($limits, JSON_THROW_ON_ERROR),
            ]);
            return ['job_id' => $id, 'completion_key' => $completionKey, 'idempotent' => false];
        });
    }

    /** @return array<string,mixed>|null */
    public function claim(?int $now = null): ?array
    {
        $now ??= time();
        $leaseSeconds = max(30, min(900, $this->leaseSeconds));
        return Transaction::run($this->database, function () use ($now, $leaseSeconds): ?array {
            $query = $this->database->prepare(<<<'SQL'
SELECT job.*, issuance.user_id, object_record.classification, object_record.byte_size
FROM protected_media_jobs job
JOIN content_delivery_issuances issuance ON issuance.id = job.issuance_id AND issuance.workspace_id = job.workspace_id
JOIN content_objects object_record ON object_record.id = job.object_id AND object_record.workspace_id = job.workspace_id
WHERE job.state IN ('queued', 'leased')
  AND (job.state = 'queued' OR job.leased_until < UTC_TIMESTAMP(6))
  AND job.attempt_count < :max_attempts
  AND issuance.revoked_at IS NULL AND issuance.expires_at > UTC_TIMESTAMP(6)
ORDER BY job.created_at, job.id
LIMIT 1
FOR UPDATE SKIP LOCKED
SQL);
            $query->bindValue(':max_attempts', $this->maximumAttempts, PDO::PARAM_INT);
            $query->execute();
            $job = $query->fetch();
            if ($job === false) {
                return null;
            }
            $decision = $this->authorizer->decide((string) $job['user_id'], (string) $job['workspace_id'], (string) $job['resource_id']);
            if (!$decision['allowed'] || !hash_equals((string) $job['resource_version_id'], (string) $decision['resource_version_id'])) {
                $this->database->prepare("UPDATE protected_media_jobs SET state = 'failed', failure_code = 'authorization_changed', updated_at = UTC_TIMESTAMP(6), completed_at = UTC_TIMESTAMP(6) WHERE id = :id")
                    ->execute(['id' => $job['id']]);
                throw new PlatformException('resource_access_denied', 'Protected media authorization changed.', 403);
            }

            $leaseToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $sql = "UPDATE protected_media_jobs SET state = 'leased', lease_token_digest = :digest, leased_until = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$leaseSeconds} SECOND), attempt_count = attempt_count + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id";
            $update = $this->database->prepare($sql);
            $update->bindValue(':digest', hash('sha256', $leaseToken, true), PDO::PARAM_LOB);
            $update->bindValue(':id', (string) $job['id']);
            $update->execute();

            $address = new ObjectAddress(
                (string) $job['workspace_id'],
                (string) $job['object_id'],
                (string) $job['resource_version_id'],
                (string) $job['classification'],
            );
            $limits = json_decode((string) $job['limits_json'], true, 16, JSON_THROW_ON_ERROR);
            return [
                'job_id' => (string) $job['id'],
                'lease_token' => $leaseToken,
                'lease_seconds' => $leaseSeconds,
                'workspace_id' => (string) $job['workspace_id'],
                'resource_id' => (string) $job['resource_id'],
                'resource_version_id' => (string) $job['resource_version_id'],
                'object_capability' => $this->capabilities->issue($address, $now, min(300, $leaseSeconds)),
                'watermark_label' => (string) $job['watermark_label'],
                'forensic_id' => (string) $job['forensic_id'],
                'user_id' => (int) $job['user_id'],
                'renderer_algorithm_version' => (string) $job['renderer_algorithm_version'],
                'limits' => is_array($limits) ? $limits : [],
                'completion_key' => (string) $job['completion_key'],
            ];
        });
    }

    /** @param array<string,mixed> $result @return array{job_id:string,state:string,idempotent:bool} */
    public function complete(string $jobId, string $leaseToken, string $completionKey, array $result): array
    {
        return Transaction::run($this->database, function () use ($jobId, $leaseToken, $completionKey, $result): array {
            $query = $this->database->prepare('SELECT state, completion_key, lease_token_digest, leased_until, limits_json, output_checksum_sha256 FROM protected_media_jobs WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $jobId]);
            $job = $query->fetch();
            if ($job === false || !hash_equals((string) $job['completion_key'], $completionKey)) {
                throw new PlatformException('protected_media_job_not_found', 'Protected media job was not found.', 404);
            }
            if ($job['state'] === 'completed') {
                return ['job_id' => $jobId, 'state' => 'completed', 'idempotent' => true];
            }
            $this->requireLease($job, $leaseToken);

            $checksum = strtolower((string) ($result['checksum_sha256'] ?? ''));
            $size = (int) ($result['size'] ?? 0);
            $mime = (string) ($result['mime'] ?? '');
            $artifactRef = (string) ($result['artifact_ref'] ?? '');
            $limits = json_decode((string) $job['limits_json'], true, 16, JSON_THROW_ON_ERROR);
            $maxBytes = is_array($limits) ? (int) ($limits['max_input_bytes'] ?? 0) : 0;
            if (!preg_match('/^[0-9a-f]{64}$/', $checksum) || $size < 1 || ($maxBytes > 0 && $size > $maxBytes * 4)
                || $mime !== 'application/pdf' || !preg_match('/^[A-Za-z0-9._:-]{1,200}$/', $artifactRef)) {
                throw new PlatformException('protected_media_output_invalid', 'Protected media output metadata is invalid.', 422);
            }

            $update = $this->database->prepare(<<<'SQL'
UPDATE protected_media_jobs
SET state = 'completed', output_checksum_sha256 = :checksum, output_size = :size,
    output_mime = :mime, artifact_ref = :artifact_ref, failure_code = NULL,
    lease_token_digest = NULL, leased_until = NULL, completed_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL);
            $update->bindValue(':checksum', hex2bin($checksum), PDO::PARAM_LOB);
            $update->bindValue(':size', $size, PDO::PARAM_INT);
            $update->bindValue(':mime', $mime);
            $update->bindValue(':artifact_ref', $artifactRef);
            $update->bindValue(':id', $jobId);
            $update->execute();
            return ['job_id' => $jobId, 'state' => 'completed', 'idempotent' => false];
        });
    }

    /** @return array{job_id:string,state:string} */
    public function fail(string $jobId, string $leaseToken, string $failureCode): array
    {
        if (!in_array($failureCode, self::FAILURE_CODES, true)) {
            throw new PlatformException('protected_media_failure_invalid', 'Protected media failure code is invalid.', 422);
        }
        return Transaction::run($this->database, function () use ($jobId, $leaseToken, $failureCode): array {
            $query = $this->database->prepare('SELECT state, lease_token_digest, leased_until FROM protected_media_jobs WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $jobId]);
            $job = $query->fetch();
            if ($job === false) {
                throw new PlatformException('protected_media_job_not_found', 'Protected media job was not found.', 404);
            }
            $this->requireLease($job, $leaseToken);
            $this->database->prepare("UPDATE protected_media_jobs SET state = 'failed', failure_code = :code, lease_token_digest = NULL, leased_until = NULL, completed_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) WHERE id = :id")
                ->execute(['code' => $failureCode, 'id' => $jobId]);
            return ['job_id' => $jobId, 'state' => 'failed'];
        });
    }

    /** @param array<string,mixed> $job */
    private function requireLease(array $job, string $leaseToken): void
    {
        if ($job['state'] !== 'leased' || $leaseToken === '' || !is_string($job['lease_token_digest'])
            || !hash_equals($job['lease_token_digest'], hash('sha256', $leaseToken, true))
            || $job['leased_until'] === null || strtotime((string) $job['leased_until'] . ' UTC') < time()) {
            throw new PlatformException('protected_media_lease_invalid', 'Protected media lease is invalid.', 409);
        }
    }
}
