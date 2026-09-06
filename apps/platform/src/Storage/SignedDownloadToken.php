<?php

declare(strict_types=1);

namespace Fanoos\Platform\Storage;

use RuntimeException;

final class SignedDownloadToken
{
    public function __construct(private readonly string $key, private readonly int $maximumTtlSeconds = 900)
    {
        if (strlen($key) < 32) {
            throw new RuntimeException('Download signing key must contain at least 32 bytes.');
        }
        if ($maximumTtlSeconds < 1) {
            throw new RuntimeException('Maximum token lifetime must be positive.');
        }
    }

    public function issue(ObjectAddress $address, int $now, int $ttlSeconds = 300): string
    {
        if ($ttlSeconds < 1 || $ttlSeconds > $this->maximumTtlSeconds) {
            throw new RuntimeException('Requested token lifetime is not permitted.');
        }

        $payload = json_encode([
            'ws' => strtolower($address->workspaceId),
            'object' => strtolower($address->objectId),
            'version' => strtolower($address->versionId),
            'class' => $address->classification,
            'exp' => $now + $ttlSeconds,
            'nonce' => bin2hex(random_bytes(12)),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encoded = self::encode($payload);

        return $encoded . '.' . self::encode(hash_hmac('sha256', $encoded, $this->key, true));
    }

    /** @return array{ws: string, object: string, version: string, class: string, exp: int, nonce: string} */
    public function verify(string $token, string $expectedWorkspaceId, int $now): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(self::encode(hash_hmac('sha256', $parts[0], $this->key, true)), $parts[1])) {
            throw new RuntimeException('Download token is invalid.');
        }

        $decoded = self::decode($parts[0]);
        $payload = json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($payload)
            || !isset($payload['ws'], $payload['object'], $payload['version'], $payload['class'], $payload['exp'], $payload['nonce'])
            || !is_string($payload['ws'])
            || !is_string($payload['object'])
            || !is_string($payload['version'])
            || !is_string($payload['class'])
            || !is_int($payload['exp'])
            || !is_string($payload['nonce'])) {
            throw new RuntimeException('Download token payload is invalid.');
        }
        if (!hash_equals(strtolower($expectedWorkspaceId), $payload['ws'])) {
            throw new RuntimeException('Download token belongs to another workspace.');
        }
        if ($payload['exp'] < $now || $payload['exp'] > $now + $this->maximumTtlSeconds) {
            throw new RuntimeException('Download token has expired or has an invalid lifetime.');
        }

        new ObjectAddress($payload['ws'], $payload['object'], $payload['version'], $payload['class']);
        /** @var array{ws: string, object: string, version: string, class: string, exp: int, nonce: string} $payload */
        return $payload;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new RuntimeException('Download token encoding is invalid.');
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Download token encoding is invalid.');
        }

        return $decoded;
    }
}
