<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Support\PlatformException;

final class ProtectedMediaArtifactCapability
{
    public function __construct(private readonly string $key, private readonly int $maximumTtlSeconds = 300)
    {
        if (strlen($key) < 32 || $maximumTtlSeconds < 1) {
            throw new \RuntimeException('Protected media capability configuration is invalid.');
        }
    }

    public function issue(string $artifactId, string $workspaceId, string $userId, string $jobId, string $platform, int $now, int $ttlSeconds = 120): string
    {
        if ($ttlSeconds < 1 || $ttlSeconds > $this->maximumTtlSeconds || !in_array($platform, ['telegram', 'bale'], true)) {
            throw new PlatformException('artifact_capability_invalid', 'Artifact capability request is invalid.', 422);
        }
        $payload = json_encode([
            'artifact' => strtolower($artifactId),
            'ws' => strtolower($workspaceId),
            'user' => strtolower($userId),
            'job' => strtolower($jobId),
            'platform' => $platform,
            'exp' => $now + $ttlSeconds,
            'nonce' => bin2hex(random_bytes(12)),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encoded = self::encode($payload);
        return $encoded . '.' . self::encode(hash_hmac('sha256', $encoded, $this->key, true));
    }

    /** @return array{artifact:string,ws:string,user:string,job:string,platform:string,exp:int,nonce:string} */
    public function verify(string $token, string $workspaceId, string $userId, string $platform, int $now): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(self::encode(hash_hmac('sha256', $parts[0], $this->key, true)), $parts[1])) {
            throw new PlatformException('artifact_capability_invalid', 'Artifact capability is invalid.', 401);
        }
        try {
            $payload = json_decode(self::decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new PlatformException('artifact_capability_invalid', 'Artifact capability is invalid.', 401);
        }
        if (!is_array($payload)
            || !isset($payload['artifact'], $payload['ws'], $payload['user'], $payload['job'], $payload['platform'], $payload['exp'], $payload['nonce'])
            || !is_string($payload['artifact']) || !is_string($payload['ws']) || !is_string($payload['user'])
            || !is_string($payload['job']) || !is_string($payload['platform']) || !is_int($payload['exp']) || !is_string($payload['nonce'])
            || !hash_equals(strtolower($workspaceId), $payload['ws'])
            || !hash_equals(strtolower($userId), $payload['user'])
            || !hash_equals($platform, $payload['platform'])
            || $payload['exp'] < $now || $payload['exp'] > $now + $this->maximumTtlSeconds) {
            throw new PlatformException('artifact_capability_invalid', 'Artifact capability is invalid or expired.', 401);
        }
        /** @var array{artifact:string,ws:string,user:string,job:string,platform:string,exp:int,nonce:string} $payload */
        return $payload;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new \RuntimeException('Capability encoding is invalid.');
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if (!is_string($decoded)) {
            throw new \RuntimeException('Capability encoding is invalid.');
        }
        return $decoded;
    }
}
