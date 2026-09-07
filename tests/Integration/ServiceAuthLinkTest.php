<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Http\Request;
use Fanoos\Platform\Integration\ServiceAuthenticator;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class ServiceAuthLinkTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $fixture = $this->fixture();
        $secret = 'stage7-service-auth-test-secret-000000000000';
        $envName = 'FANOOS_TEST_STAGE7_SERVICE_SECRET';
        $serviceId = Uuid::v7();
        $keyRowId = Uuid::v7();
        $keyId = 'test-stage7-' . substr(str_replace('-', '', Uuid::v7()), -10);
        $this->database->prepare("INSERT INTO integration_service_identities (id, service_key, service_type, allowed_actions_json, status, created_at, updated_at) VALUES (:id, :key, 'telegram_adapter', :actions, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $serviceId, 'key' => 'telegram-test-' . $keyId, 'actions' => json_encode(['messaging.workspace.read'], JSON_THROW_ON_ERROR)]);
        $this->database->prepare("INSERT INTO integration_service_keys (id, service_id, key_id, secret_env_name, status, valid_from, created_at) VALUES (:id, :service, :key_id, :env, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $keyRowId, 'service' => $serviceId, 'key_id' => $keyId, 'env' => $envName]);
        $auth = new ServiceAuthenticator($this->database, static fn (string $name): ?string => $name === $envName ? $secret : null);
        $now = time();

        $request = $this->signedRequest('/api/internal/v1/messaging/workspaces/list', ['platform' => 'telegram', 'subject' => '123'], $keyId, $secret, $now, 'nonce-valid-' . bin2hex(random_bytes(8)));
        $principal = $auth->authenticate($request, 'messaging.workspace.read', $now);
        self::assert($principal->serviceType === 'telegram_adapter', 'Valid service signature did not authenticate the expected service.');
        $this->expectCode('service_replay', fn () => $auth->authenticate($request, 'messaging.workspace.read', $now));

        $stale = $this->signedRequest('/api/internal/v1/messaging/workspaces/list', ['platform' => 'telegram', 'subject' => '123'], $keyId, $secret, $now - 301, 'nonce-stale-' . bin2hex(random_bytes(8)));
        $this->expectCode('service_auth_failed', fn () => $auth->authenticate($stale, 'messaging.workspace.read', $now));

        $bad = $this->signedRequest('/api/internal/v1/messaging/workspaces/list', ['platform' => 'telegram', 'subject' => '123'], $keyId, $secret, $now, 'nonce-bad-' . bin2hex(random_bytes(8)));
        $badHeaders = $bad->headers;
        $badHeaders['x-fanoos-signature'] = str_repeat('0', 64);
        $bad = new Request($bad->method, $bad->path, $badHeaders, [], $bad->body, 'test', $bad->rawBody);
        $this->expectCode('service_auth_failed', fn () => $auth->authenticate($bad, 'messaging.workspace.read', $now));

        $scope = $this->signedRequest('/api/internal/v1/messaging/workspaces/list', ['platform' => 'telegram', 'subject' => '123'], $keyId, $secret, $now, 'nonce-scope-' . bin2hex(random_bytes(8)));
        $this->expectCode('service_scope_denied', fn () => $auth->authenticate($scope, 'deployment.request', $now));

        $links = new MessagingLinkService($this->database, new AuditLogger($this->database), new ChannelSubjectProtector(str_repeat('m', 32)), 300, 30, 5);
        $challenge = $links->createChallenge($fixture['student'], 'telegram', $now);
        $linked = $links->consumeChallenge('telegram', $challenge['challenge_token'], 'tg-subject-100', $now + 1);
        self::assert($linked['user_id'] === $fixture['student'], 'Link challenge did not bind the authenticated canonical user.');
        $this->expectCode('link_challenge_used', fn () => $links->consumeChallenge('telegram', $challenge['challenge_token'], 'tg-subject-100', $now + 2));
        self::assert(($links->resolve('telegram', 'tg-subject-100')['user_id'] ?? null) === $fixture['student'], 'Linked platform subject did not resolve to canonical identity.');
        $this->expectCode('link_challenge_cooldown', fn () => $links->createChallenge($fixture['student'], 'telegram', $now + 2));

        $workspaces = $links->workspaces($linked['link_id']);
        self::assert(count($workspaces) >= 2, 'Linked multi-workspace user did not receive canonical active memberships.');
        $links->selectWorkspace($linked['link_id'], $fixture['workspace_a']);
        self::assert($links->selectedWorkspace($linked['link_id']) === $fixture['workspace_a'], 'Messaging workspace selection was not persisted.');
        $this->database->prepare("UPDATE tenant_workspace_memberships SET status = 'suspended' WHERE workspace_id = :workspace AND user_id = :user")
            ->execute(['workspace' => $fixture['workspace_a'], 'user' => $fixture['student']]);
        self::assert($links->selectedWorkspace($linked['link_id']) === null, 'Suspended membership remained valid as messaging workspace authority.');
        $this->database->prepare("UPDATE tenant_workspace_memberships SET status = 'active' WHERE workspace_id = :workspace AND user_id = :user")
            ->execute(['workspace' => $fixture['workspace_a'], 'user' => $fixture['student']]);

        $expired = $links->createChallenge($fixture['representative'], 'bale', $now);
        $this->database->prepare('UPDATE messaging_link_challenges SET expires_at = FROM_UNIXTIME(:expired) WHERE id = :id')->execute(['expired' => $now - 1, 'id' => $expired['challenge_id']]);
        $this->expectCode('link_challenge_expired', fn () => $links->consumeChallenge('bale', $expired['challenge_token'], 'bale-subject-expired', $now));

        $wrongPlatform = $links->createChallenge($fixture['global_admin'], 'telegram', $now);
        $this->expectCode('link_challenge_invalid', fn () => $links->consumeChallenge('bale', $wrongPlatform['challenge_token'], 'bale-wrong', $now));

        $conflict = $links->createChallenge($fixture['representative'], 'telegram', $now + 31);
        $this->expectCode('platform_subject_conflict', fn () => $links->consumeChallenge('telegram', $conflict['challenge_token'], 'tg-subject-100', $now + 32));

        $links->revoke($fixture['student'], 'telegram');
        self::assert($links->resolve('telegram', 'tg-subject-100') === null, 'Revoked messaging link remained an identity authority.');
        return $this->assertions;
    }

    /** @param array<string,mixed> $body */
    private function signedRequest(string $path, array $body, string $keyId, string $secret, int $timestamp, string $nonce): Request
    {
        $raw = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signed = ServiceAuthenticator::signature('POST', $path, $timestamp, $nonce, $raw, $secret);
        return new Request('POST', $path, [
            'x-fanoos-key-id' => $keyId,
            'x-fanoos-timestamp' => (string) $timestamp,
            'x-fanoos-nonce' => $nonce,
            'x-fanoos-content-sha256' => $signed['content_sha256'],
            'x-fanoos-signature' => $signed['signature'],
        ], [], $body, 'test', $raw);
    }

    /** @return array<string,string> */
    private function fixture(): array
    {
        $result = [];
        foreach (['Multi member' => 'student', 'Representative' => 'representative', 'Global admin' => 'global_admin'] as $name => $key) {
            $query = $this->database->prepare('SELECT id FROM iam_users WHERE display_name = :name ORDER BY created_at DESC LIMIT 1');
            $query->execute(['name' => $name]);
            $id = $query->fetchColumn();
            if ($id === false) {
                throw new RuntimeException("Missing Stage 3 fixture user: {$name}");
            }
            $result[$key] = (string) $id;
        }
        foreach (['Fixture Workspace A' => 'workspace_a', 'Fixture Workspace B' => 'workspace_b'] as $name => $key) {
            $query = $this->database->prepare('SELECT id FROM tenant_workspaces WHERE name = :name ORDER BY created_at DESC LIMIT 1');
            $query->execute(['name' => $name]);
            $id = $query->fetchColumn();
            if ($id === false) {
                throw new RuntimeException("Missing Stage 3 fixture workspace: {$name}");
            }
            $result[$key] = (string) $id;
        }
        return $result;
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
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}.");
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
