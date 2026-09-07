<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ProtectedMediaArtifactCapability;
use Fanoos\Platform\Content\ProtectedMediaArtifactStore;
use Fanoos\Platform\Content\ProtectedMediaEnqueueService;
use Fanoos\Platform\Content\ProtectedMediaJobService;
use Fanoos\Platform\Content\ProtectedMediaTransferService;
use Fanoos\Platform\Content\ProtectedMediaUploadCapability;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Core\BotReadProjectionService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Messaging\MessagingUnlinkService;
use Fanoos\Platform\Operations\DeploymentSnapshotStore;
use Fanoos\Platform\Operations\OwnerControlPlaneService;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\InspectedUpload;
use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class BotPlatformHandoffTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertions = 0;
        $fixture = $this->fixture();
        $audit = new AuditLogger($this->database);
        $scopeAuthorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $scopeAuthorizer);
        $entitlements = new EntitlementService($this->database, $access, $audit);
        $protected = new ProtectedResourceAuthorizer($this->database, $scopeAuthorizer, $entitlements);
        $this->assertReadProjections($fixture, $access, $protected);
        $this->assertSubjectUnlink($fixture, $audit);
        $this->assertOwnerOverview($fixture, $access);
        $this->assertProtectedMediaTransfer($fixture, $audit, $protected);
        return $this->assertions;
    }

    /** @param array<string,string> $fixture */
    private function assertReadProjections(array $fixture, AccessGate $access, ProtectedResourceAuthorizer $protected): void
    {
        $this->database->prepare("UPDATE tenant_workspaces SET timezone_name = 'Asia/Tehran' WHERE id = :workspace")
            ->execute(['workspace' => $fixture['workspace_a']]);
        $reads = new BotReadProjectionService($this->database, $access, $protected);
        $schedule = $reads->schedule($fixture['student'], $fixture['workspace_a'], '2026-05-15', '2026-05-15', 20);
        $this->assert($schedule['timezone'] === 'Asia/Tehran', 'Bot schedule did not use the canonical workspace timezone.');
        $this->assert(count($schedule['items']) >= 1, 'Bot schedule returned no canonical workspace event.');
        $this->assert(str_contains((string) $schedule['items'][0]['starts_at'], '+03:30'), 'Bot schedule did not localize UTC event time.');
        $this->expectCode('invalid_date_range', fn () => $reads->schedule($fixture['student'], $fixture['workspace_a'], '2026-01-01', '2026-03-15'));

        $grades = $reads->grades($fixture['student'], $fixture['workspace_a'], 10);
        $this->assert(count($grades['items']) >= 1 && isset($grades['items'][0]['result_id'], $grades['items'][0]['score']), 'Bot self-grade projection lacks stable canonical identity/value.');
        $announcements = $reads->announcements($fixture['student'], $fixture['workspace_a'], 10);
        $this->assert(count($announcements['items']) >= 1 && isset($announcements['items'][0]['id']), 'Bot announcement projection lacks stable canonical IDs.');
        $catalog = $reads->resourceCatalog($fixture['student'], $fixture['workspace_a'], 20);
        $this->assert(count($catalog['items']) >= 1, 'Bot resource catalog returned no authorized resource.');
        foreach ($catalog['items'] as $item) {
            $this->assert(isset($item['resource_id'], $item['resource_version_id']), 'Bot resource catalog lacks stable resource/version identity.');
            $this->assert(!isset($item['storage_key'], $item['object_id'], $item['path']), 'Bot resource catalog leaked storage addressing.');
        }
    }

    /** @param array<string,string> $fixture */
    private function assertSubjectUnlink(array $fixture, AuditLogger $audit): void
    {
        $protector = new ChannelSubjectProtector(str_repeat('u', 32));
        $links = new MessagingLinkService($this->database, $audit, $protector, 300, 1, 20);
        $unlink = new MessagingUnlinkService($this->database, $audit, $protector);
        $this->database->prepare("UPDATE messaging_links SET status = 'revoked', revoked_at = COALESCE(revoked_at, UTC_TIMESTAMP(6)), revoke_reason = COALESCE(revoke_reason, 'handoff_test_setup'), updated_at = UTC_TIMESTAMP(6) WHERE (user_id = :student OR user_id = :representative) AND status = 'active'")
            ->execute(['student' => $fixture['student'], 'representative' => $fixture['representative']]);
        // Earlier integration suites intentionally reuse these canonical fixture users. Move this test's
        // synthetic clock beyond their link-challenge cooldown without deleting prior rate-limit evidence.
        $now = time() + 120;
        $telegramSubject = 'handoff-tg-' . substr(str_replace('-', '', Uuid::v7()), -12);
        $baleSubject = 'handoff-bale-' . substr(str_replace('-', '', Uuid::v7()), -12);
        $otherSubject = 'handoff-other-' . substr(str_replace('-', '', Uuid::v7()), -12);
        $telegramChallenge = $links->createChallenge($fixture['student'], 'telegram', $now);
        $telegram = $links->consumeChallenge('telegram', $telegramChallenge['challenge_token'], $telegramSubject, $now + 1);
        $links->selectWorkspace($telegram['link_id'], $fixture['workspace_a']);
        $baleChallenge = $links->createChallenge($fixture['student'], 'bale', $now + 2);
        $links->consumeChallenge('bale', $baleChallenge['challenge_token'], $baleSubject, $now + 3);
        $otherChallenge = $links->createChallenge($fixture['representative'], 'telegram', $now + 4);
        $links->consumeChallenge('telegram', $otherChallenge['challenge_token'], $otherSubject, $now + 5);

        $result = $unlink->revokeSubject('telegram', $telegramSubject);
        $this->assert($result === ['revoked' => true, 'idempotent' => false], 'Subject unlink did not revoke only the requested Telegram link.');
        $this->assert($links->selectedWorkspace($telegram['link_id']) === null, 'Subject unlink did not invalidate selected workspace context.');
        $this->assert($unlink->revokeSubject('telegram', $telegramSubject) === ['revoked' => false, 'idempotent' => true], 'Repeated subject unlink was not idempotent.');
        $this->assert($links->resolve('bale', $baleSubject) !== null, 'Telegram unlink revoked unrelated Bale state.');
        $unlink->revokeSubject('telegram', $baleSubject);
        $this->assert($links->resolve('bale', $baleSubject) !== null, 'Cross-channel unlink escaped the platform+subject boundary.');
        $this->assert($links->resolve('telegram', $otherSubject) !== null, 'Subject unlink revoked another user link.');
    }

    /** @param array<string,string> $fixture */
    private function assertOwnerOverview(array $fixture, AccessGate $access): void
    {
        $targetKey = 'platform-primary';
        $query = $this->database->prepare('SELECT id FROM release_update_targets WHERE target_key = :target LIMIT 1');
        $query->execute(['target' => $targetKey]);
        if ($query->fetchColumn() === false) {
            $this->database->prepare("INSERT INTO release_update_targets (id, target_key, node_key, service_key, status, created_at, updated_at) VALUES (:id, :target, 'primary', 'platform', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
                ->execute(['id' => Uuid::v7(), 'target' => $targetKey]);
        }
        $snapshots = new DeploymentSnapshotStore($this->database);
        $snapshots->recordHealthy($targetKey, ['current_sha' => str_repeat('a', 40), 'candidate_sha' => str_repeat('b', 40), 'noop' => false]);
        $service = new OwnerControlPlaneService($this->database, $access, $snapshots);
        $allowed = $service->overview($this->deploymentOperator(), $targetKey);
        $this->assert(($allowed['can_manage_deployments'] ?? false) === true, 'Deployment operator could not read owner overview.');
        $this->assert($allowed['current_release_sha'] === str_repeat('a', 40) && $allowed['candidate_sha'] === str_repeat('b', 40) && $allowed['update_available'] === true, 'Owner overview did not return safe canonical release status.');
        $this->assert(!isset($allowed['token'], $allowed['environment'], $allowed['logs']), 'Owner overview leaked secret/runtime fields.');
        $this->assert($service->overview($fixture['representative'], $targetKey) === ['can_manage_deployments' => false], 'Non-operator owner overview leaked deployment metadata.');
    }

    /** @param array<string,string> $fixture */
    private function assertProtectedMediaTransfer(array $fixture, AuditLogger $audit, ProtectedResourceAuthorizer $protected): void
    {
        $content = $this->protectedFixture($fixture['student'], $fixture['workspace_a']);
        $sourceBytes = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n";
        $renderedBytes = "%PDF-1.4\n% personalized FANOOS derivative\n%%EOF\n";
        $sourceRoot = sys_get_temp_dir() . '/fanoos-source-' . bin2hex(random_bytes(6));
        $artifactRoot = sys_get_temp_dir() . '/fanoos-artifact-' . bin2hex(random_bytes(6));
        $uploadFile = tempnam(sys_get_temp_dir(), 'fanoos-pdf-');
        if ($uploadFile === false) {
            throw new RuntimeException('Temporary source file could not be created.');
        }
        file_put_contents($uploadFile, $sourceBytes);
        try {
            $statement = $this->database->prepare("UPDATE content_objects SET detected_mime = 'application/pdf', byte_size = :size, checksum_sha256 = :checksum, status = 'verified', verified_at = UTC_TIMESTAMP(6) WHERE id = :object AND workspace_id = :workspace");
            $statement->bindValue(':size', strlen($sourceBytes), PDO::PARAM_INT);
            $statement->bindValue(':checksum', hex2bin(hash('sha256', $sourceBytes)), PDO::PARAM_LOB);
            $statement->bindValue(':object', $content['object_id']);
            $statement->bindValue(':workspace', $fixture['workspace_a']);
            $statement->execute();

            $sourceStore = new FilesystemObjectStore($sourceRoot);
            $sourceStore->put(
                new ObjectAddress($fixture['workspace_a'], $content['object_id'], $content['resource_version_id'], $content['classification']),
                new InspectedUpload($uploadFile, 'source.pdf', 'application/pdf', strlen($sourceBytes), hash('sha256', $sourceBytes)),
            );
            $tokens = new SignedDownloadToken(str_repeat('x', 32));
            $delivery = new SecureDeliveryService($this->database, $protected, $tokens, $audit, str_repeat('y', 32));
            $issuance = $delivery->issue($fixture['student'], $fixture['workspace_a'], $content['resource_id'], 'telegram');
            $jobs = new ProtectedMediaJobService($this->database, $protected, $tokens, 120, 3);
            $job = (new ProtectedMediaEnqueueService($this->database, $jobs))->enqueue($fixture['workspace_a'], $issuance['issuance_id'], 'pdf-watermark-v2', ['max_input_bytes' => 1048576, 'max_pages' => 20, 'max_seconds' => 60]);
            $claim = $jobs->claim();
            $this->assert(is_array($claim) && $claim['job_id'] === $job['job_id'], 'Protected-media handoff job was not claimable.');

            $transfer = new ProtectedMediaTransferService(
                $this->database, $protected, $tokens, $sourceStore, new ProtectedMediaArtifactStore($artifactRoot),
                new ProtectedMediaArtifactCapability(str_repeat('z', 32)), new ProtectedMediaUploadCapability(str_repeat('z', 32)),
                $jobs, $audit, 3600, 4 * 1048576,
            );
            $source = $transfer->redeemSource($claim['job_id'], $claim['lease_token'], $claim['object_capability']);
            $this->assert(stream_get_contents($source['stream']) === $sourceBytes, 'Source capability did not redeem exact PDF bytes.');
            fclose($source['stream']);
            $this->expectCode('protected_media_lease_invalid', fn () => $transfer->redeemSource($claim['job_id'], $claim['lease_token'], $claim['object_capability'], time() + 1000));

            $checksum = hash('sha256', $renderedBytes);
            $authorization = $transfer->authorizeArtifactPublish($claim['job_id'], $claim['lease_token'], $claim['completion_key'], $checksum, strlen($renderedBytes), 'application/pdf');
            $this->expectCode('protected_media_output_invalid', fn () => $transfer->publishAuthorizedArtifact($authorization['upload_capability'], $renderedBytes . 'tampered'));
            $published = $transfer->publishAuthorizedArtifact($authorization['upload_capability'], $renderedBytes);
            $this->assert(str_starts_with($published['artifact_ref'], 'pma:') && $published['idempotent'] === false, 'Personalized derivative was not canonically published.');
            $replay = $transfer->publishAuthorizedArtifact($authorization['upload_capability'], $renderedBytes);
            $this->assert($replay['artifact_ref'] === $published['artifact_ref'] && $replay['idempotent'] === true, 'Artifact publication retry was not idempotent.');
            $completed = $transfer->complete($claim['job_id'], $claim['lease_token'], $claim['completion_key'], ['checksum_sha256' => $checksum, 'size' => strlen($renderedBytes), 'mime' => 'application/pdf', 'artifact_ref' => $published['artifact_ref']]);
            $this->assert($completed['state'] === 'completed', 'Protected-media completion did not bind the canonical artifact.');

            $derivative = $transfer->issueDerivative($fixture['student'], $fixture['workspace_a'], $claim['job_id'], 'telegram');
            $this->assert(isset($derivative['artifact_capability']) && !isset($derivative['storage_key'], $derivative['path']), 'Derivative issue leaked storage addressing.');
            $download = $transfer->redeemDerivative($fixture['student'], $fixture['workspace_a'], 'telegram', $derivative['artifact_capability']);
            $this->assert(stream_get_contents($download['stream']) === $renderedBytes, 'Derivative capability did not return personalized PDF bytes.');
            fclose($download['stream']);
            $this->expectCode('protected_media_artifact_unavailable', fn () => $transfer->issueDerivative($fixture['representative'], $fixture['workspace_a'], $claim['job_id'], 'telegram'));

            $this->database->prepare("UPDATE entitlement_grants SET revoked_at = UTC_TIMESTAMP(6), revoke_reason = 'handoff_media_test' WHERE workspace_id = :workspace AND subject_user_id = :user AND target_scope_id = :scope AND revoked_at IS NULL")
                ->execute(['workspace' => $fixture['workspace_a'], 'user' => $fixture['student'], 'scope' => $content['target_scope_id']]);
            $this->expectCode('resource_access_denied', fn () => $transfer->redeemDerivative($fixture['student'], $fixture['workspace_a'], 'telegram', $derivative['artifact_capability']));
            $this->database->prepare("UPDATE entitlement_grants SET revoked_at = NULL, revoke_reason = NULL WHERE workspace_id = :workspace AND subject_user_id = :user AND target_scope_id = :scope AND revoke_reason = 'handoff_media_test'")
                ->execute(['workspace' => $fixture['workspace_a'], 'user' => $fixture['student'], 'scope' => $content['target_scope_id']]);
            $this->database->prepare("UPDATE protected_media_artifacts SET expires_at = DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND) WHERE job_id = :job")->execute(['job' => $claim['job_id']]);
            $this->assert($transfer->cleanupExpired(time(), 10) === 1, 'Expired personalized artifact was not cleaned up.');
            $this->expectCode('protected_media_artifact_unavailable', fn () => $transfer->redeemDerivative($fixture['student'], $fixture['workspace_a'], 'telegram', $derivative['artifact_capability']));
        } finally {
            @unlink($uploadFile);
            $this->removeTree($sourceRoot);
            $this->removeTree($artifactRoot);
        }
    }

    /** @return array<string,string> */
    private function fixture(): array
    {
        $result = [];
        foreach (['Multi member' => 'student', 'Representative' => 'representative'] as $name => $key) {
            $query = $this->database->prepare('SELECT id FROM iam_users WHERE display_name = :name ORDER BY created_at DESC LIMIT 1');
            $query->execute(['name' => $name]);
            $id = $query->fetchColumn();
            if ($id === false) {
                throw new RuntimeException("Fixture user is missing: {$name}");
            }
            $result[$key] = (string) $id;
        }
        foreach (['Fixture Workspace A' => 'workspace_a', 'Fixture Workspace B' => 'workspace_b'] as $name => $key) {
            $query = $this->database->prepare('SELECT id FROM tenant_workspaces WHERE name = :name ORDER BY created_at DESC LIMIT 1');
            $query->execute(['name' => $name]);
            $id = $query->fetchColumn();
            if ($id === false) {
                throw new RuntimeException("Fixture workspace is missing: {$name}");
            }
            $result[$key] = (string) $id;
        }
        return $result;
    }

    /** @return array{resource_id:string,target_scope_id:string,resource_version_id:string,object_id:string,classification:string} */
    private function protectedFixture(string $userId, string $workspaceId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT policy.resource_id, policy.target_scope_id, version.id AS resource_version_id, version.object_id, object_record.classification
FROM content_access_policies policy
JOIN content_resources resource ON resource.id = policy.resource_id AND resource.workspace_id = policy.workspace_id
JOIN content_resource_versions version ON version.resource_id = resource.id AND version.workspace_id = resource.workspace_id
 AND version.version_no = resource.current_version_no AND version.status = 'approved'
JOIN content_objects object_record ON object_record.id = version.object_id AND object_record.workspace_id = version.workspace_id
JOIN entitlement_grants grant_record ON grant_record.workspace_id = policy.workspace_id
 AND grant_record.subject_user_id = :user AND grant_record.target_scope_id = policy.target_scope_id AND grant_record.revoked_at IS NULL
WHERE policy.workspace_id = :workspace AND policy.requires_entitlement = TRUE AND resource.lifecycle_status = 'published'
ORDER BY resource.updated_at DESC LIMIT 1
SQL);
        $query->execute(['user' => $userId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new RuntimeException('Protected content fixture is unavailable for bot handoff tests.');
        }
        return ['resource_id' => (string) $row['resource_id'], 'target_scope_id' => (string) $row['target_scope_id'], 'resource_version_id' => (string) $row['resource_version_id'], 'object_id' => (string) $row['object_id'], 'classification' => (string) $row['classification']];
    }

    private function deploymentOperator(): string
    {
        $query = $this->database->query(<<<'SQL'
SELECT assignment.user_id
FROM rbac_role_assignments assignment
JOIN rbac_role_template_permissions role_permission ON role_permission.role_template_id = assignment.role_template_id
JOIN rbac_permissions permission ON permission.id = role_permission.permission_id
WHERE permission.permission_key = 'deployment.manage' AND assignment.user_id IS NOT NULL
  AND assignment.revoked_at IS NULL AND (assignment.expires_at IS NULL OR assignment.expires_at > UTC_TIMESTAMP(6))
ORDER BY assignment.created_at LIMIT 1
SQL);
        $userId = $query->fetchColumn();
        if ($userId === false) {
            throw new RuntimeException('Deployment operator fixture is unavailable.');
        }
        return (string) $userId;
    }

    private function expectCode(string $code, callable $callback): void
    {
        try {
            $callback();
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === $code, "Expected {$code}, got {$error->errorCode}.");
            return;
        }
        throw new RuntimeException("Expected PlatformException {$code} was not thrown.");
    }

    private function removeTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
