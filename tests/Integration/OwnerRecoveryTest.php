<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\OwnerRecoveryService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * Owner sign-in recovery (docs product decision: a locked-out owner asks the
 * bot for a link, redeems it in the browser, and sets their own password --
 * no operator, script or agent ever sees or sets it).
 */
final class OwnerRecoveryTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertOwnerCanRequestAndStudentCannot();
        $this->assertTokenIsSingleUse();
        $this->assertExpiredTokenIsRefused();
        $this->assertRedeemingEstablishesSessionForTheRightUserOnly();
        $this->assertRateLimitEngagesAndCounterSurvivesRefusal();
        $this->assertStoredValueIsADigestNotTheToken();
        $this->assertSettingPasswordReplacesTheOldAuthenticator();

        return $this->assertions;
    }

    private function assertOwnerCanRequestAndStudentCannot(): void
    {
        $suffix = $this->suffix();
        $owner = $this->owner('Recovery Owner ' . $suffix);
        $student = $this->plainUser('Recovery Student ' . $suffix);
        $links = $this->links();
        // The bot proves identity by messaging link, not by anything the
        // caller asserts -- resolve() is the exact call InternalApiKernel's
        // linked() helper makes before ever reaching the service.
        $links->establishLink($owner, 'telegram', 'tg-owner-' . $suffix);
        $resolved = $links->resolve('telegram', 'tg-owner-' . $suffix);
        $this->assert($resolved !== null && $resolved['user_id'] === $owner, 'Fixture owner messaging link did not resolve.');

        $service = $this->service();
        $issued = $service->requestLink($resolved['user_id']);
        $this->assert($issued['token'] !== '' && $issued['expires_in_seconds'] > 0, 'An owner with a messaging link could not request a recovery link.');

        $this->expectCode('owner_recovery_forbidden', fn () => $service->requestLink($student));
    }

    private function assertTokenIsSingleUse(): void
    {
        $suffix = $this->suffix();
        $owner = $this->owner('SingleUse Owner ' . $suffix);
        $service = $this->service();
        $issued = $service->requestLink($owner);

        $session = $service->redeem($issued['token']);
        $this->assert($session->userId === $owner, 'Redeeming did not establish a session for the requesting owner.');

        $this->expectCode('owner_recovery_token_used', fn () => $service->redeem($issued['token']));
    }

    private function assertExpiredTokenIsRefused(): void
    {
        $suffix = $this->suffix();
        $owner = $this->owner('Expiry Owner ' . $suffix);
        $service = $this->service();
        $now = 1_700_000_000;
        $issued = $service->requestLink($owner, $now);

        // Token TTL is 600s; ask for it back well past that.
        $this->expectCode('owner_recovery_token_expired', fn () => $service->redeem($issued['token'], $now + 700));
    }

    private function assertRedeemingEstablishesSessionForTheRightUserOnly(): void
    {
        $suffix = $this->suffix();
        $ownerA = $this->owner('Session Owner A ' . $suffix);
        $ownerB = $this->owner('Session Owner B ' . $suffix);
        $service = $this->service();

        $issuedA = $service->requestLink($ownerA);
        $session = $service->redeem($issuedA['token']);
        $this->assert($session->userId === $ownerA, 'Redemption established a session for the wrong user.');
        $this->assert($session->userId !== $ownerB, 'Redemption leaked a session to an unrelated owner.');
        $this->assert($session->selectedWorkspaceId === null, 'A workspace-less recovery session unexpectedly selected a workspace.');

        // The session it created is real and usable exactly like a login one.
        $auth = $this->auth();
        $authenticated = $auth->authenticate($session->token);
        $this->assert($authenticated->userId === $ownerA, 'The session token issued by redeem() does not authenticate as the owner.');
    }

    private function assertRateLimitEngagesAndCounterSurvivesRefusal(): void
    {
        $suffix = $this->suffix();
        $owner = $this->owner('RateLimit Owner ' . $suffix);
        $service = $this->service();
        $now = 1_700_100_000;

        // MAX_REQUESTS_PER_WINDOW is 3; the 60s cooldown after each successful
        // issue means a naive "just call it 4 times at the same instant" would
        // hit the cooldown on the 2nd call, not the count cap -- advance the
        // clock past each cooldown so the count cap is what actually engages.
        $service->requestLink($owner, $now);
        $service->requestLink($owner, $now + 61);
        $service->requestLink($owner, $now + 122);

        $this->expectCode('owner_recovery_rate_limited', fn () => $service->requestLink($owner, $now + 183));
        $countAfterFirstRefusal = $this->rateGuardCount($owner);
        $this->assert($countAfterFirstRefusal === 3, 'Burst was not exhausted after the configured number of requests.');

        // Same instant, again: if the refusal's own write had rolled back
        // (thrown from inside Transaction::run instead of after it), the
        // next check would recompute from a stale/unwritten row. It must
        // refuse again, with the counter unchanged.
        $this->expectCode('owner_recovery_rate_limited', fn () => $service->requestLink($owner, $now + 183));
        $countAfterSecondRefusal = $this->rateGuardCount($owner);
        $this->assert($countAfterSecondRefusal === 3, 'The rate-limit counter was reset by a refusal instead of surviving it.');
    }

    private function assertStoredValueIsADigestNotTheToken(): void
    {
        $suffix = $this->suffix();
        $owner = $this->owner('Digest Owner ' . $suffix);
        $service = $this->service();
        $issued = $service->requestLink($owner);

        $row = $this->database->prepare('SELECT token_digest FROM owner_recovery_tokens WHERE user_id = :user ORDER BY created_at DESC LIMIT 1');
        $row->execute(['user' => $owner]);
        $digest = $row->fetchColumn();
        $this->assert($digest !== false, 'No recovery token row was persisted.');
        $this->assert($digest !== $issued['token'], 'The raw token was stored instead of a digest.');
        $this->assert(strlen((string) $digest) === 32, 'The stored value is not a 32-byte sha256 digest.');
        $this->assert(hash_equals((string) $digest, hash('sha256', $issued['token'], true)), 'The stored digest does not match hash(sha256, token).');
    }

    private function assertSettingPasswordReplacesTheOldAuthenticator(): void
    {
        $suffix = $this->suffix();
        $owner = $this->owner('Password Owner ' . $suffix);
        $identifier = 'owner-' . $suffix . '@example.test';
        $this->seedIdentifier($owner, $identifier);
        $this->seedPassword($owner, 'the original password');

        $auth = $this->auth();
        $original = $auth->login($identifier, 'the original password', 'test-source');
        $this->assert($original->userId === $owner, 'Fixture password did not authenticate before the reset.');

        $auth->setPassword($original, 'a brand new password');

        $this->expectCode('invalid_credentials', fn () => $auth->login($identifier, 'the original password', 'test-source'));
        $reAuthenticated = $auth->login($identifier, 'a brand new password', 'test-source');
        $this->assert($reAuthenticated->userId === $owner, 'The newly set password did not authenticate.');

        $count = $this->database->prepare("SELECT COUNT(*) FROM iam_authenticators WHERE user_id = :user AND authenticator_type = 'password' AND revoked_at IS NULL");
        $count->execute(['user' => $owner]);
        $this->assert((int) $count->fetchColumn() === 1, 'Setting a password left more than one active password authenticator.');
    }

    // -- fixtures and small helpers ------------------------------------------

    private function suffix(): string
    {
        return substr(str_replace('-', '', Uuid::v7()), -10);
    }

    private function service(): OwnerRecoveryService
    {
        $audit = new AuditLogger($this->database);
        return new OwnerRecoveryService($this->database, $this->auth(), $audit);
    }

    private function auth(): AuthService
    {
        return new AuthService($this->database, new PasswordHasher(), new AuditLogger($this->database));
    }

    private function links(): MessagingLinkService
    {
        return new MessagingLinkService($this->database, new AuditLogger($this->database), new ChannelSubjectProtector(str_repeat('k', 32)));
    }

    private function owner(string $displayName): string
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

    private function plainUser(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);

        return $id;
    }

    private function seedIdentifier(string $userId, string $email): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at)
VALUES (:id, :user, 'email', :value, TRUE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'value' => strtolower($email)]);
    }

    private function seedPassword(string $userId, string $password): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO iam_authenticators (id, user_id, authenticator_type, secret_digest, metadata_json, created_at)
VALUES (:id, :user, 'password', :digest, JSON_OBJECT(), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'digest' => (new PasswordHasher())->hash($password)]);
    }

    private function rateGuardCount(string $userId): int
    {
        $query = $this->database->prepare('SELECT request_count FROM owner_recovery_rate_guards WHERE user_id = :user');
        $query->execute(['user' => $userId]);
        $value = $query->fetchColumn();
        if ($value === false) {
            throw new RuntimeException('Rate guard row was not persisted.');
        }

        return (int) $value;
    }

    private function expectCode(string $code, callable $operation): void
    {
        try {
            $operation();
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === $code, "Expected {$code}, received {$error->errorCode}.");

            return;
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
