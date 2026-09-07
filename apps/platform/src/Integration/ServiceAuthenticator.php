<?php

declare(strict_types=1);

namespace Fanoos\Platform\Integration;

use Fanoos\Platform\Http\Request;
use Fanoos\Platform\Support\PlatformException;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

final class ServiceAuthenticator
{
    /** @var callable(string):?string */
    private $secretResolver;

    /** @param callable(string):?string|null $secretResolver */
    public function __construct(
        private readonly PDO $database,
        ?callable $secretResolver = null,
        private readonly int $maximumSkewSeconds = 300,
        private readonly int $nonceLifetimeSeconds = 600,
    ) {
        $this->secretResolver = $secretResolver ?? static function (string $name): ?string {
            $value = getenv($name);
            return is_string($value) && $value !== '' ? $value : null;
        };
    }

    public function authenticate(Request $request, string $requiredAction, ?int $now = null): ServicePrincipal
    {
        $now ??= time();
        $keyId = $request->header('x-fanoos-key-id');
        $timestamp = $request->header('x-fanoos-timestamp');
        $nonce = $request->header('x-fanoos-nonce');
        $contentDigest = strtolower($request->header('x-fanoos-content-sha256'));
        $signature = strtolower($request->header('x-fanoos-signature'));

        if ($keyId === '' || !preg_match('/^[A-Za-z0-9._:-]{3,96}$/', $keyId)
            || !preg_match('/^[0-9]{10}$/', $timestamp)
            || !preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce)
            || !preg_match('/^[0-9a-f]{64}$/', $contentDigest)
            || !preg_match('/^[0-9a-f]{64}$/', $signature)) {
            throw new PlatformException('service_auth_failed', 'Service authentication failed.', 401);
        }
        if (abs($now - (int) $timestamp) > $this->maximumSkewSeconds) {
            throw new PlatformException('service_auth_failed', 'Service authentication failed.', 401);
        }
        $actualDigest = hash('sha256', $request->rawBody);
        if (!hash_equals($actualDigest, $contentDigest)) {
            throw new PlatformException('service_auth_failed', 'Service authentication failed.', 401);
        }

        $query = $this->database->prepare(<<<'SQL'
SELECT service.id AS service_id, service.service_key, service.service_type, service.allowed_actions_json,
       service_key.id AS service_key_id, service_key.secret_env_name
FROM integration_service_keys service_key
JOIN integration_service_identities service ON service.id = service_key.service_id
WHERE service_key.key_id = :key_id
  AND service.status = 'active'
  AND service_key.status IN ('active', 'retiring')
  AND service_key.revoked_at IS NULL
  AND service_key.valid_from <= UTC_TIMESTAMP(6)
  AND (service_key.valid_until IS NULL OR service_key.valid_until > UTC_TIMESTAMP(6))
LIMIT 1
SQL);
        $query->execute(['key_id' => $keyId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('service_auth_failed', 'Service authentication failed.', 401);
        }

        try {
            $actions = json_decode((string) $row['allowed_actions_json'], true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Service action policy is invalid.');
        }
        if (!is_array($actions) || !in_array($requiredAction, $actions, true)) {
            throw new PlatformException('service_scope_denied', 'Service action is not permitted.', 403);
        }

        $secret = ($this->secretResolver)((string) $row['secret_env_name']);
        if (!is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('Service signing secret is unavailable.');
        }
        $canonical = implode("\n", [
            'fanoos-service-v1',
            strtoupper($request->method),
            $request->path,
            $timestamp,
            $nonce,
            $contentDigest,
        ]);
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new PlatformException('service_auth_failed', 'Service authentication failed.', 401);
        }

        $nonceDigest = hash('sha256', $nonce, true);
        $expiresAt = $now + $this->nonceLifetimeSeconds;
        try {
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO integration_service_nonces (service_key_id, nonce_digest, seen_at, expires_at)
VALUES (:service_key_id, :nonce_digest, UTC_TIMESTAMP(6), FROM_UNIXTIME(:expires_at))
SQL);
            $insert->bindValue(':service_key_id', (string) $row['service_key_id']);
            $insert->bindValue(':nonce_digest', $nonceDigest, PDO::PARAM_LOB);
            $insert->bindValue(':expires_at', $expiresAt, PDO::PARAM_INT);
            $insert->execute();
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new PlatformException('service_replay', 'Service request was already used.', 409);
            }
            throw $error;
        }

        return new ServicePrincipal(
            (string) $row['service_id'],
            (string) $row['service_key'],
            (string) $row['service_type'],
            array_values(array_filter($actions, 'is_string')),
        );
    }

    public static function signature(string $method, string $path, int $timestamp, string $nonce, string $rawBody, string $secret): array
    {
        $digest = hash('sha256', $rawBody);
        $canonical = implode("\n", ['fanoos-service-v1', strtoupper($method), $path, (string) $timestamp, $nonce, $digest]);
        return ['content_sha256' => $digest, 'signature' => hash_hmac('sha256', $canonical, $secret)];
    }
}
