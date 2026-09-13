<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ProtectedMediaForensicService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * Covers the leak-attribution candidate lookup's most important invariant:
 * it returns exactly the recipients actually issued a marked derivative of
 * one resource in one workspace, and it can never be made to answer for
 * another workspace's deliveries. Owner-only authorization is covered too.
 * The original-bytes redemption path itself reuses
 * ProtectedMediaTransferService::redeemSource's already-tested
 * FilesystemObjectStore/ObjectAddress mechanism verbatim, so it is not
 * re-verified here.
 */
final class ProtectedMediaForensicTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database, private readonly string $storageRoot)
    {
    }

    public function run(): int
    {
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $service = new ProtectedMediaForensicService($this->database, $access, new FilesystemObjectStore($this->storageRoot), new AuditLogger($this->database));

        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformSuperAdmin('Forensic Owner ' . $suffix);
        $bystander = $this->plainUser('Forensic Bystander ' . $suffix);

        $workspaceA = $this->workspace($owner, $suffix . '-a');
        $workspaceB = $this->workspace($owner, $suffix . '-b');
        $recipient = $this->plainUser('Forensic Recipient ' . $suffix);

        [$resourceA, $objectA, $versionA] = $this->contentFixture($workspaceA, $recipient, 'Forensic Resource A ' . $suffix);
        [$resourceB, $objectB, $versionB] = $this->contentFixture($workspaceB, $recipient, 'Forensic Resource B ' . $suffix);

        $jobA = $this->completedJob($workspaceA, $resourceA, $versionA, $objectA, $recipient, 'fanoos-raster-v2');
        $this->completedJob($workspaceB, $resourceB, $versionB, $objectB, $recipient, 'fanoos-raster-v2');
        // A job under the pre-secure-raster renderer must never surface as a
        // detectable candidate: it was never marked with the redundant scheme
        // this detector reads.
        $this->completedJob($workspaceA, $resourceA, $versionA, $objectA, $recipient, 'fanoos-raster-v1');
        // A queued (not completed) job was never actually delivered.
        $this->queuedJob($workspaceA, $resourceA, $versionA, $objectA, $recipient);

        $resultA = $service->candidates($owner, $workspaceA, $resourceA);
        self::assert(count($resultA['candidates']) === 1, 'Candidate lookup did not return exactly the one completed fanoos-raster-v2 job for resource A.');
        self::assert($resultA['candidates'][0]['issuance_id'] === $jobA, 'Candidate lookup returned the wrong job as the candidate.');
        self::assert($resultA['candidates'][0]['user_id'] === $recipient, 'Candidate lookup did not report the actual recipient.');
        self::assert($resultA['candidates'][0]['document_id'] === $resourceA, 'Candidate lookup did not bind document_id to the requested resource.');

        // The same resource id can never leak a match across the workspace
        // boundary: workspace B's real recipient job for resourceB must not
        // appear when resourceA is queried under workspace B, and workspace
        // A's own candidate must not appear when queried under workspace B.
        $crossWorkspace = $service->candidates($owner, $workspaceB, $resourceA);
        self::assert($crossWorkspace['candidates'] === [], 'Candidate lookup leaked workspace A deliveries into a workspace B query.');

        $ownWorkspace = $service->candidates($owner, $workspaceB, $resourceB);
        self::assert(count($ownWorkspace['candidates']) === 1, 'Candidate lookup did not find workspace B\'s own completed candidate under its own workspace.');

        $unrelatedResource = $service->candidates($owner, $workspaceA, Uuid::v7());
        self::assert($unrelatedResource['candidates'] === [], 'Candidate lookup returned candidates for a resource with no deliveries.');

        $this->expectCode('forbidden', fn () => $service->candidates($bystander, $workspaceA, $resourceA));

        // resourcesWithCandidates lets the bot offer a resource picker
        // instead of a typed UUID: it must list exactly the resources that
        // actually have a completed fanoos-raster-v2 job in that workspace
        // (never a resource with only a pre-secure-raster or still-queued
        // job, and never another workspace's resource), and it is bound by
        // the same workspace and permission as candidates()/originalSource().
        [$resourceA2, $objectA2, $versionA2] = $this->contentFixture($workspaceA, $recipient, 'Forensic Resource A2 ' . $suffix);
        $this->completedJob($workspaceA, $resourceA2, $versionA2, $objectA2, $recipient, 'fanoos-raster-v2');

        $resourcesInA = $service->resourcesWithCandidates($owner, $workspaceA, 10, null);
        $resourceIdsInA = array_column($resourcesInA['items'], 'resource_id');
        self::assert(count($resourcesInA['items']) === 2, 'Resource picker did not return exactly the two resources with a completed candidate job in workspace A.');
        self::assert(in_array($resourceA, $resourceIdsInA, true) && in_array($resourceA2, $resourceIdsInA, true), 'Resource picker missed a resource that has a completed candidate job.');
        self::assert($resourcesInA['next_cursor'] === null, 'Resource picker returned a next_cursor when every match fit on one page.');

        $resourcesInB = $service->resourcesWithCandidates($owner, $workspaceB, 10, null);
        self::assert(count($resourcesInB['items']) === 1 && $resourcesInB['items'][0]['resource_id'] === $resourceB, 'Resource picker leaked workspace A\'s resource into a workspace B listing, or missed workspace B\'s own resource.');

        $firstPage = $service->resourcesWithCandidates($owner, $workspaceA, 1, null);
        self::assert(count($firstPage['items']) === 1 && $firstPage['next_cursor'] !== null, 'Resource picker did not paginate a two-resource workspace with a page size of one.');
        $secondPage = $service->resourcesWithCandidates($owner, $workspaceA, 1, $firstPage['next_cursor']);
        self::assert(count($secondPage['items']) === 1 && $secondPage['next_cursor'] === null, 'Resource picker\'s second page did not deliver the remaining resource and stop.');
        self::assert($firstPage['items'][0]['resource_id'] !== $secondPage['items'][0]['resource_id'], 'Resource picker returned the same resource on both pages instead of advancing.');

        $this->expectCode('forbidden', fn () => $service->resourcesWithCandidates($bystander, $workspaceA, 10, null));

        return $this->assertions;
    }

    /** @return array{0:string,1:string,2:string} resource_id, object_id, resource_version_id */
    private function contentFixture(string $workspaceId, string $ownerUserId, string $title): array
    {
        $resourceType = (string) $this->database->query("SELECT id FROM content_resource_types WHERE type_key = 'lecture_note'")->fetchColumn();
        $resourceId = Uuid::v7();
        $versionId = Uuid::v7();
        $objectId = Uuid::v7();
        $this->insert(
            "INSERT INTO content_resources (id, workspace_id, resource_type_id, owner_user_id, title, visibility, lifecycle_status, current_version_no, created_at, updated_at) VALUES (:id, :workspace, :type, :owner, :title, 'workspace', 'published', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['id' => $resourceId, 'workspace' => $workspaceId, 'type' => $resourceType, 'owner' => $ownerUserId, 'title' => $title],
        );
        $this->insert(
            "INSERT INTO content_resource_versions (id, workspace_id, resource_id, version_no, created_by_user_id, source_kind, content_json, checksum_sha256, status, created_at, reviewed_at) VALUES (:id, :workspace, :resource, 1, :creator, 'authored', JSON_OBJECT('body', 'fixture'), UNHEX(SHA2(:title, 256)), 'approved', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['id' => $versionId, 'workspace' => $workspaceId, 'resource' => $resourceId, 'creator' => $ownerUserId, 'title' => $title],
        );
        $this->insert(
            "INSERT INTO content_objects (id, workspace_id, storage_adapter, storage_key, original_name, detected_mime, byte_size, checksum_sha256, classification, status, created_at, verified_at) VALUES (:id, :workspace, 'filesystem', :key, 'fixture.pdf', 'application/pdf', 2048, UNHEX(SHA2(:title, 256)), 'private', 'verified', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['id' => $objectId, 'workspace' => $workspaceId, 'key' => 'private/' . substr($objectId, 0, 2) . '/' . $objectId . '/' . substr($objectId, 0, 2) . '/' . $objectId . '/' . Uuid::v7() . '.bin', 'title' => $title],
        );
        return [$resourceId, $objectId, $versionId];
    }

    private function issuance(string $workspaceId, string $resourceId, string $versionId, string $objectId, string $userId): string
    {
        $issuanceId = Uuid::v7();
        $this->insert(
            "INSERT INTO content_delivery_issuances (id, workspace_id, resource_id, resource_version_id, object_id, user_id, channel, token_digest, watermark_fingerprint, expires_at, issued_at) VALUES (:id, :workspace, :resource, :version, :object, :user, 'bale', UNHEX(SHA2(:id2, 256)), UNHEX(SHA2(:id3, 256)), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 DAY), UTC_TIMESTAMP(6))",
            ['id' => $issuanceId, 'workspace' => $workspaceId, 'resource' => $resourceId, 'version' => $versionId, 'object' => $objectId, 'user' => $userId, 'id2' => $issuanceId . '-token', 'id3' => $issuanceId . '-fp'],
        );
        return $issuanceId;
    }

    private function completedJob(string $workspaceId, string $resourceId, string $versionId, string $objectId, string $userId, string $renderer): string
    {
        $issuanceId = $this->issuance($workspaceId, $resourceId, $versionId, $objectId, $userId);
        $jobId = Uuid::v7();
        $this->insert(
            "INSERT INTO protected_media_jobs (id, workspace_id, resource_id, resource_version_id, object_id, issuance_id, completion_key, state, renderer_algorithm_version, watermark_label, forensic_id, limits_json, attempt_count, created_at, updated_at, completed_at) VALUES (:id, :workspace, :resource, :version, :object, :issuance, :completion, 'completed', :renderer, 'FANOOS', :forensic, JSON_OBJECT('max_input_bytes', 1024, 'max_pages', 10, 'max_seconds', 30), 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['id' => $jobId, 'workspace' => $workspaceId, 'resource' => $resourceId, 'version' => $versionId, 'object' => $objectId, 'issuance' => $issuanceId, 'completion' => hash('sha256', $jobId . '-completion'), 'renderer' => $renderer, 'forensic' => substr(hash('sha256', $jobId), 0, 32)],
        );
        return $jobId;
    }

    private function queuedJob(string $workspaceId, string $resourceId, string $versionId, string $objectId, string $userId): string
    {
        $issuanceId = $this->issuance($workspaceId, $resourceId, $versionId, $objectId, $userId);
        $jobId = Uuid::v7();
        $this->insert(
            "INSERT INTO protected_media_jobs (id, workspace_id, resource_id, resource_version_id, object_id, issuance_id, completion_key, state, renderer_algorithm_version, watermark_label, forensic_id, limits_json, attempt_count, created_at, updated_at) VALUES (:id, :workspace, :resource, :version, :object, :issuance, :completion, 'queued', 'fanoos-raster-v2', 'FANOOS', :forensic, JSON_OBJECT('max_input_bytes', 1024, 'max_pages', 10, 'max_seconds', 30), 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['id' => $jobId, 'workspace' => $workspaceId, 'resource' => $resourceId, 'version' => $versionId, 'object' => $objectId, 'issuance' => $issuanceId, 'completion' => hash('sha256', $jobId . '-completion'), 'forensic' => substr(hash('sha256', $jobId), 0, 32)],
        );
        return $jobId;
    }

    private function workspace(string $owner, string $suffix): string
    {
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $provisioning = new ClassProvisioningService($this->database, $access, new AuditLogger($this->database));
        $result = $provisioning->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Forensic Fixture Country'],
            'province' => ['name' => 'Forensic Fixture Province ' . $suffix],
            'city' => ['name' => 'Forensic Fixture City ' . $suffix],
            'institution' => ['name' => 'Forensic Fixture University ' . $suffix],
            'faculty' => ['name' => 'Forensic Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Forensic Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 1402, 'label' => 'Forensic Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Forensic Fixture Class ' . $suffix],
        ]);
        return (string) $result['workspace_id'];
    }

    private function platformSuperAdmin(string $displayName): string
    {
        $id = Uuid::v7();
        $this->insert("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $id, 'name' => $displayName]);
        $this->assignRole($id, 'platform-super-admin', '00000000-0000-7000-8000-000000000001');
        return $id;
    }

    private function plainUser(string $displayName): string
    {
        $id = Uuid::v7();
        $this->insert("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $id, 'name' => $displayName]);
        return $id;
    }

    private function assignRole(string $userId, string $roleKey, string $scopeId): void
    {
        $role = $this->database->prepare('SELECT id FROM rbac_role_templates WHERE role_key = :role LIMIT 1');
        $role->execute(['role' => $roleKey]);
        $roleId = (string) $role->fetchColumn();
        if ($roleId === '') {
            throw new RuntimeException("Role template not found: {$roleKey}");
        }
        $this->insert(
            "INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            ['id' => Uuid::v7(), 'user' => $userId, 'role' => $roleId, 'scope' => $scopeId],
        );
    }

    /** @param array<string, scalar|null> $parameters */
    private function insert(string $sql, array $parameters): void
    {
        $this->database->prepare($sql)->execute($parameters);
    }

    private function expectCode(string $code, callable $callback): void
    {
        try {
            $callback();
        } catch (PlatformException $error) {
            self::assert($error->errorCode === $code, "Expected exception code {$code} but got {$error->errorCode}.");
            return;
        }
        self::assert(false, "Expected exception code {$code} but none was thrown.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException('Assertion failed: ' . $message);
        }
    }
}
