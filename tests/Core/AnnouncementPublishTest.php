<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Core\BotReadProjectionService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Core\WorkspacePlatformService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Onboarding\ClassMembershipService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

/**
 * The representative's compose/preview/send announcement capability
 * (WorkspacePlatformService::publishAnnouncement, exposed to the bot for the
 * first time this round -- see contracts/REGISTRY.md "Representative
 * announcement publishing"). No new permission or table: notification.broadcast
 * already covers it and publishing fans out through the existing
 * notification_messages/notification_recipients/outbox_events pipeline.
 */
final class AnnouncementPublishTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertRepresentativePublishesIntoOwnClassAndItIsReadableThere();
        $this->assertRepresentativeOfClassACannotPublishOrListIntoClassB();
        $this->assertLimitedMemberAndPlainStudentCannotPublish();
        $this->assertEmptyTitleOrBodyIsRefusedBeforeAnythingIsWritten();
        $this->assertPublishingEnqueuesDeliveryThroughExistingNotificationPath();

        return $this->assertions;
    }

    private function assertRepresentativePublishesIntoOwnClassAndItIsReadableThere(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Announce Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5101);
        $protector = $this->protector();

        $repUserId = $this->joinAsMember($protector, 'tg-ann-rep-' . $suffix, $fixture, $this->fixturePhone());
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $repUserId);
        $studentUserId = $this->promoteToStudent($owner, $fixture, 'tg-ann-student-' . $suffix, $protector);

        $reads = $this->reads();
        $before = $reads->announcements($studentUserId, $fixture['workspace_id'], 20);
        $this->assert($before['can_publish'] === false, 'A plain student was reported as able to publish.');
        $this->assert(count($before['items']) === 0, 'Fixture workspace unexpectedly already had an announcement.');

        $repView = $reads->announcements($repUserId, $fixture['workspace_id'], 20);
        $this->assert($repView['can_publish'] === true, 'The appointed representative was not reported as able to publish.');

        $id = $this->workspacePlatform()->publishAnnouncement($repUserId, $fixture['workspace_id'], 'اطلاعیه آزمایشی', 'متن اطلاعیه برای کلاس.');
        $this->assert($id !== '', 'publishAnnouncement did not return an id.');

        $after = $reads->announcements($studentUserId, $fixture['workspace_id'], 20);
        $this->assert(count($after['items']) === 1, 'The published announcement was not readable by an active member of the same class.');
        $this->assert($after['items'][0]['id'] === $id, 'The readable announcement id did not match the published one.');
        $this->assert($after['items'][0]['title'] === 'اطلاعیه آزمایشی', 'The readable announcement title did not match what was published.');
    }

    private function assertRepresentativeOfClassACannotPublishOrListIntoClassB(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Announce Isolation Owner ' . $suffix);
        $fixtureA = $this->provisionFixtureClass($owner, $suffix . '-a', 5102);
        $fixtureB = $this->provisionFixtureClass($owner, $suffix . '-b', 5103);
        $protector = $this->protector();

        $repAUserId = $this->joinAsMember($protector, 'tg-ann-repa-' . $suffix, $fixtureA, $this->fixturePhone());
        $this->workspacePlatform()->assignRepresentative($owner, $fixtureA['workspace_id'], $repAUserId);

        $this->expectCode('forbidden', fn () => $this->workspacePlatform()->publishAnnouncement($repAUserId, $fixtureB['workspace_id'], 'نفوذ به کلاس دیگر', 'نباید ثبت شود.'));
        $this->expectCode('forbidden', fn () => $this->reads()->announcements($repAUserId, $fixtureB['workspace_id'], 20));

        $countB = (int) $this->database->query("SELECT COUNT(*) FROM notification_messages WHERE workspace_id = '" . $fixtureB['workspace_id'] . "'")->fetchColumn();
        $this->assert($countB === 0, 'A cross-workspace publish attempt left a row behind despite being refused.');
    }

    private function assertLimitedMemberAndPlainStudentCannotPublish(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Announce Role Gate Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5104);
        $protector = $this->protector();

        $limitedUserId = $this->joinAsMember($protector, 'tg-ann-limited-' . $suffix, $fixture, $this->fixturePhone());
        $this->expectCode('forbidden', fn () => $this->workspacePlatform()->publishAnnouncement($limitedUserId, $fixture['workspace_id'], 'باید رد شود', 'عضو محدود اجازه ندارد.'));

        $studentUserId = $this->promoteToStudent($owner, $fixture, 'tg-ann-plainstudent-' . $suffix, $protector);
        $this->expectCode('forbidden', fn () => $this->workspacePlatform()->publishAnnouncement($studentUserId, $fixture['workspace_id'], 'باید رد شود', 'دانشجوی عادی اجازه ندارد.'));
    }

    private function assertEmptyTitleOrBodyIsRefusedBeforeAnythingIsWritten(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Announce Validation Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5105);
        $protector = $this->protector();
        $repUserId = $this->joinAsMember($protector, 'tg-ann-validator-' . $suffix, $fixture, $this->fixturePhone());
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $repUserId);

        $before = $this->countMessages($fixture['workspace_id']);
        $this->expectCode('invalid_announcement', fn () => $this->workspacePlatform()->publishAnnouncement($repUserId, $fixture['workspace_id'], '', 'متن معتبر'));
        $this->expectCode('invalid_announcement', fn () => $this->workspacePlatform()->publishAnnouncement($repUserId, $fixture['workspace_id'], '   ', 'متن معتبر'));
        $this->expectCode('invalid_announcement', fn () => $this->workspacePlatform()->publishAnnouncement($repUserId, $fixture['workspace_id'], 'عنوان معتبر', ''));
        $this->expectCode('invalid_announcement', fn () => $this->workspacePlatform()->publishAnnouncement($repUserId, $fixture['workspace_id'], 'عنوان معتبر', '   '));
        $after = $this->countMessages($fixture['workspace_id']);
        $this->assert($before === $after, 'An empty title or body was refused but still left a row behind.');
    }

    private function assertPublishingEnqueuesDeliveryThroughExistingNotificationPath(): void
    {
        $suffix = $this->suffix();
        $owner = $this->platformSuperAdmin('Announce Pipeline Owner ' . $suffix);
        $fixture = $this->provisionFixtureClass($owner, $suffix, 5106);
        $protector = $this->protector();
        $repUserId = $this->joinAsMember($protector, 'tg-ann-pipeline-rep-' . $suffix, $fixture, $this->fixturePhone());
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $repUserId);
        $studentUserId = $this->promoteToStudent($owner, $fixture, 'tg-ann-pipeline-student-' . $suffix, $protector);

        $id = $this->workspacePlatform()->publishAnnouncement($repUserId, $fixture['workspace_id'], 'اطلاعیه مسیر ارسال', 'برای اطمینان از استفاده از مسیر موجود.');

        $message = $this->database->prepare("SELECT status, message_type FROM notification_messages WHERE id = :id AND workspace_id = :workspace");
        $message->execute(['id' => $id, 'workspace' => $fixture['workspace_id']]);
        $row = $message->fetch();
        $this->assert($row !== false && $row['status'] === 'published' && $row['message_type'] === 'announcement', 'Publishing did not write the existing notification_messages row as published.');

        $recipient = $this->database->prepare("SELECT status FROM notification_recipients WHERE notification_id = :id AND user_id = :user AND channel = 'web'");
        $recipient->execute(['id' => $id, 'user' => $studentUserId]);
        $recipientRow = $recipient->fetch();
        $this->assert($recipientRow !== false && $recipientRow['status'] === 'delivered', 'Publishing did not fan out into the existing notification_recipients row for an active member.');

        $outbox = $this->database->prepare("SELECT COUNT(*) FROM outbox_events WHERE aggregate_type = 'notification' AND aggregate_id = :id AND event_type = 'announcement.published'");
        $outbox->execute(['id' => $id]);
        $this->assert((int) $outbox->fetchColumn() === 1, 'Publishing did not enqueue exactly one outbox_events row for channel fan-out.');
    }

    // -- fixtures and small helpers ------------------------------------------

    private function suffix(): string
    {
        return substr(str_replace('-', '', Uuid::v7()), -10);
    }

    /**
     * Uuid::v7()'s leading hex is a millisecond timestamp, not randomness --
     * two fixtures created in the same millisecond would collide on this
     * unique phone digest. Derive the phone from random_int instead.
     */
    private function fixturePhone(): string
    {
        return '+9892' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function countMessages(string $workspaceId): int
    {
        $query = $this->database->prepare('SELECT COUNT(*) FROM notification_messages WHERE workspace_id = :workspace');
        $query->execute(['workspace' => $workspaceId]);
        return (int) $query->fetchColumn();
    }

    private function accessGate(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function provisioning(): ClassProvisioningService
    {
        return new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database));
    }

    private function workspacePlatform(): WorkspacePlatformService
    {
        return new WorkspacePlatformService($this->database, $this->accessGate(), new AuditLogger($this->database));
    }

    private function reads(): BotReadProjectionService
    {
        $access = $this->accessGate();
        $scopeAuthorizer = new ScopeAuthorizer($this->database);
        $audit = new AuditLogger($this->database);
        $entitlements = new EntitlementService($this->database, $access, $audit);
        $resources = new ProtectedResourceAuthorizer($this->database, $scopeAuthorizer, $entitlements);
        return new BotReadProjectionService($this->database, $access, $resources);
    }

    private function protector(): ChannelSubjectProtector
    {
        return new ChannelSubjectProtector(str_repeat('a', 32));
    }

    private function classMembership(ChannelSubjectProtector $protector): ClassMembershipService
    {
        $links = new MessagingLinkService($this->database, new AuditLogger($this->database), $protector);
        return new ClassMembershipService($this->database, new AuditLogger($this->database), $protector, $links, $this->accessGate());
    }

    /** @return array<string,mixed> */
    private function provisionFixtureClass(string $owner, string $suffix, int $entryYear): array
    {
        return $this->provisioning()->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => $entryYear, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Class ' . $suffix],
        ]);
    }

    /** @param array<string,mixed> $fixture */
    private function joinAsMember(ChannelSubjectProtector $protector, string $subject, array $fixture, string $phone): string
    {
        $this->markPhoneVerified($protector, 'telegram', $subject, $phone);
        $membership = $this->classMembership($protector);
        $joined = $membership->join('telegram', $subject, $fixture['program_id'], $this->entryYearFor($fixture));
        if ($joined['status'] !== 'joined') {
            throw new RuntimeException('Fixture join did not resolve to a class.');
        }
        return $this->userIdForPhone($phone);
    }

    /** @param array<string,mixed> $fixture */
    private function promoteToStudent(string $owner, array $fixture, string $subject, ChannelSubjectProtector $protector): string
    {
        $userId = $this->joinAsMember($protector, $subject, $fixture, $this->fixturePhone());
        $membership = $this->classMembership($protector);
        $membership->requestUpgrade('telegram', $subject, $fixture['workspace_id']);
        $requestId = $this->requestIdFor($fixture['workspace_id'], $userId);
        // A representative resolves the upgrade; reuse the owner as a fresh
        // one-off representative so this helper does not depend on the
        // caller already having appointed one.
        $resolverSubject = 'tg-ann-resolver-' . substr(str_replace('-', '', Uuid::v7()), -10);
        $resolverUserId = $this->joinAsMember($protector, $resolverSubject, $fixture, $this->fixturePhone());
        $this->workspacePlatform()->assignRepresentative($owner, $fixture['workspace_id'], $resolverUserId);
        $membership->approveUpgradeRequest('telegram', $resolverSubject, $fixture['workspace_id'], $requestId);
        return $userId;
    }

    /** @param array<string,mixed> $fixture */
    private function entryYearFor(array $fixture): int
    {
        $year = $this->database->prepare('SELECT entry_year FROM directory_cohorts WHERE id = :id');
        $year->execute(['id' => $fixture['cohort_id']]);
        return (int) $year->fetchColumn();
    }

    private function markPhoneVerified(ChannelSubjectProtector $protector, string $platform, string $subject, string $phone): void
    {
        $subjectDigest = $protector->digest('onboarding:' . $platform, $subject);
        $phoneDigest = $protector->digest('phone', $phone);
        $phoneCiphertext = $protector->encrypt('phone', $phone);
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO onboarding_verified_phones (platform, subject_digest, phone_digest, phone_ciphertext, verified_at, updated_at)
VALUES (:platform, :subject_digest, :phone_digest, :phone_ciphertext, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
        $statement->bindValue(':platform', $platform);
        $statement->bindValue(':subject_digest', $subjectDigest, PDO::PARAM_LOB);
        $statement->bindValue(':phone_digest', $phoneDigest, PDO::PARAM_LOB);
        $statement->bindValue(':phone_ciphertext', $phoneCiphertext, PDO::PARAM_LOB);
        $statement->execute();
    }

    private function userIdForPhone(string $phone): string
    {
        $query = $this->database->prepare("SELECT user_id FROM iam_user_identifiers WHERE identifier_type = 'phone' AND normalized_value = :phone");
        $query->execute(['phone' => $phone]);
        $userId = $query->fetchColumn();
        if ($userId === false) {
            throw new RuntimeException('Expected a canonical account to exist for the verified phone.');
        }
        return (string) $userId;
    }

    private function requestIdFor(string $workspaceId, string $userId): string
    {
        $query = $this->database->prepare('SELECT id FROM tenant_workspace_role_upgrade_requests WHERE workspace_id = :workspace AND user_id = :user');
        $query->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $id = $query->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Expected an upgrade request row to exist.');
        }
        return (string) $id;
    }

    private function platformSuperAdmin(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);
        $role = $this->database->prepare("SELECT id FROM rbac_role_templates WHERE role_key = 'platform-super-admin' LIMIT 1");
        $role->execute();
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('Role template is missing: platform-super-admin');
        }
        $this->database->prepare('INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))')
            ->execute(['id' => Uuid::v7(), 'user' => $id, 'role' => $roleId, 'scope' => '00000000-0000-7000-8000-000000000001']);
        return $id;
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}: {$error->getMessage()}");
        } catch (Throwable $error) {
            throw new RuntimeException("Expected PlatformException {$code}, got " . get_class($error) . ': ' . $error->getMessage());
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
