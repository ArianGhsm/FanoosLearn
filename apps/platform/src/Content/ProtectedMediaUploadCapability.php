<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Support\PlatformException;

final class ProtectedMediaUploadCapability
{
    public function __construct(private readonly string $key, private readonly int $maximumTtlSeconds = 180)
    {
        if (strlen($key) < 32 || $maximumTtlSeconds < 1) {
            throw new \RuntimeException('Protected media upload capability configuration is invalid.');
        }
    }

    public function issue(string $jobId, string $checksum, int $size, string $mime, int $now, int $ttlSeconds): string
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $jobId) || !preg_match('/^[0-9a-f]{64}$/', $checksum)
            || $size < 1 || $mime !== 'application/pdf' || $ttlSeconds < 1 || $ttlSeconds > $this->maximumTtlSeconds) {
            throw new PlatformException('artifact_upload_authorization_invalid', 'Artifact upload authorization is invalid.', 422);
        }
        $payload = json_encode([
            'v' => 1,
            'job' => strtolower($jobId),
            'sha256' => $checksum,
            'size' => $size,
            'mime' => $mime,
            'exp' => $now + $ttlSeconds,
            'nonce' => bin2hex(random_bytes(12)),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encoded = self::encode($payload);
        $mac = hash_hmac('sha256', 'fanoos-media-upload-v1.' . $encoded, $this->key, true);
        return $encoded . '.' . self::encode($mac);
    }

    /** @return array{v:int,job:string,sha256:string,size:int,mime:string,exp:int,nonce:string} */
    public function verify(string $token, int $now): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new PlatformException('artifact_upload_authorization_invalid', 'Artifact upload authorization is invalid.', 401);
        }
        $expected = self::encode(hash_hmac('sha256', 'fanoos-media-upload-v1.' . $parts[0], $this->key, true));
        if (!hash_equals($expected, $parts[1])) {
            throw new PlatformException('artifact_upload_authorization_invalid', 'Artifact upload authorization is invalid.', 401);
        }
        try {
            $payload = json_decode(self::decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new PlatformException('artifact_upload_authorization_invalid', 'Artifact upload authorization is invalid.', 401);
        }
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1
            || !isset($payload['job'], $payload['sha256'], $payload['size'], $payload['mime'], $payload['exp'], $payload['nonce'])
            || !is_string($payload['job']) || !preg_match('/^[0-9a-f-]{36}$/', $payload['job'])
            || !is_string($payload['sha256']) || !preg_match('/^[0-9a-f]{64}$/', $payload['sha256'])
            || !is_int($payload['size']) || $payload['size'] < 1
            || $payload['mime'] !== 'application/pdf' || !is_int($payload['exp']) || !is_string($payload['nonce'])
            || $payload['exp'] < $now || $payload['exp'] > $now + $this->maximumTtlSeconds) {
            throw new PlatformException('artifact_upload_authorization_invalid', 'Artifact upload authorization is invalid or expired.', 401);
        }
        /** @var array{v:int,job:string,sha256:string,size:int,mime:string,exp:int,nonce:string} $payload */
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
