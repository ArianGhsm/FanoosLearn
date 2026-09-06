<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Storage\ObjectAddress;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class SecureDeliveryService
{
    private const CHANNELS = ['web', 'api', 'telegram', 'bale'];

    public function __construct(
        private readonly PDO $database,
        private readonly ProtectedResourceAuthorizer $authorizer,
        private readonly SignedDownloadToken $downloadTokens,
        private readonly AuditLogger $audit,
        private readonly string $signingKey,
    ) {
        if (strlen($signingKey) < 32) {
            throw new \RuntimeException('Delivery signing key must contain at least 32 bytes.');
        }
    }

    /** @return array<string, mixed> */
    public function issue(string $userId, string $workspaceId, string $resourceId, string $channel, ?int $now = null): array
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            throw new PlatformException('delivery_channel_invalid', 'Delivery channel is not supported.', 422);
        }
        $decision = $this->authorizer->decide($userId, $workspaceId, $resourceId);
        if (!$decision['allowed'] || $decision['resource_version_id'] === null) {
            throw new PlatformException('resource_access_denied', 'Resource delivery was denied.', 403);
        }
        $record = $this->deliveryRecord($userId, $workspaceId, $resourceId, $decision['resource_version_id']);
        $now ??= time();
        $ttl = max(30, min(900, (int) ($decision['download_ttl_seconds'] ?? 300)));
        $issuanceId = Uuid::v7();
        $expiresAt = $now + $ttl;
        $fingerprint = hash_hmac('sha256', implode('|', [$issuanceId, $workspaceId, $resourceId, $decision['resource_version_id'], $userId]), $this->signingKey);
        $payload = [
            'iss' => $issuanceId, 'ws' => strtolower($workspaceId), 'user' => strtolower($userId),
            'resource' => strtolower($resourceId), 'version' => strtolower($decision['resource_version_id']),
            'object' => $record['object_id'], 'channel' => $channel, 'exp' => $expiresAt,
        ];
        $encoded = self::encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $token = $encoded . '.' . self::encode(hash_hmac('sha256', $encoded, $this->signingKey, true));
        $tokenDigest = hash('sha256', $token, true);

        Transaction::run($this->database, function () use ($issuanceId, $workspaceId, $resourceId, $decision, $record, $userId, $channel, $tokenDigest, $fingerprint, $expiresAt): void {
            $this->execute(<<<'SQL'
INSERT INTO content_delivery_issuances (
    id, workspace_id, resource_id, resource_version_id, object_id, user_id, channel,
    token_digest, watermark_fingerprint, expires_at, issued_at
) VALUES (
    :id, :workspace, :resource, :version, :object, :user, :channel,
    :token, :fingerprint, FROM_UNIXTIME(:expires), UTC_TIMESTAMP(6)
)
SQL, [
                'id' => $issuanceId, 'workspace' => $workspaceId, 'resource' => $resourceId,
                'version' => $decision['resource_version_id'], 'object' => $record['object_id'],
                'user' => $userId, 'channel' => $channel, 'token' => $tokenDigest,
                'fingerprint' => hex2bin($fingerprint), 'expires' => $expiresAt,
            ]);
            $this->event($workspaceId, $issuanceId, 'issued', ['channel' => $channel]);
            $this->audit->record($workspaceId, $userId, 'content.delivery.issued', 'content_delivery_issuance', $issuanceId, 'success', ['resource_id' => $resourceId, 'channel' => $channel]);
        });

        return [
            'issuance_id' => $issuanceId,
            'delivery_token' => $token,
            'expires_at' => gmdate(DATE_ATOM, $expiresAt),
            'watermark' => [
                'visible_label' => trim((string) $record['display_name']) . ' · ' . substr($issuanceId, -8),
                'forensic_id' => substr($fingerprint, 0, 20),
                'algorithm' => 'issuance-hmac-sha256-v1',
            ],
            'delivery_contract' => [
                'channel' => $channel,
                'forward_protection_required' => in_array($channel, ['telegram', 'bale'], true),
                'reauthorize_on_serve' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function consume(string $token, string $expectedUserId, string $expectedWorkspaceId, ?int $now = null): array
    {
        $now ??= time();
        $payload = $this->verifyToken($token, $expectedUserId, $expectedWorkspaceId, $now);
        $tokenDigest = hash('sha256', $token, true);
        $query = $this->database->prepare(<<<'SQL'
SELECT issuance.id, issuance.resource_id, issuance.resource_version_id, issuance.object_id,
       issuance.channel, object_record.classification, version.content_json
FROM content_delivery_issuances issuance
JOIN content_resource_versions version ON version.id = issuance.resource_version_id
 AND version.resource_id = issuance.resource_id AND version.workspace_id = issuance.workspace_id
LEFT JOIN content_objects object_record ON object_record.id = issuance.object_id AND object_record.workspace_id = issuance.workspace_id
WHERE issuance.id = :id AND issuance.workspace_id = :workspace AND issuance.user_id = :user
  AND issuance.token_digest = :token AND issuance.revoked_at IS NULL
  AND issuance.expires_at >= UTC_TIMESTAMP(6)
LIMIT 1
SQL);
        $query->bindValue(':id', $payload['iss']);
        $query->bindValue(':workspace', $expectedWorkspaceId);
        $query->bindValue(':user', $expectedUserId);
        $query->bindValue(':token', $tokenDigest, PDO::PARAM_LOB);
        $query->execute();
        $record = $query->fetch();
        if ($record === false) {
            throw new PlatformException('delivery_token_unavailable', 'Delivery token is unavailable.', 403);
        }

        $decision = $this->authorizer->decide($expectedUserId, $expectedWorkspaceId, (string) $record['resource_id']);
        if (!$decision['allowed'] || !hash_equals((string) $record['resource_version_id'], (string) $decision['resource_version_id'])) {
            $this->event($expectedWorkspaceId, (string) $record['id'], 'denied', ['reason' => 'authorization_changed']);
            throw new PlatformException('resource_access_denied', 'Resource authorization changed before delivery.', 403);
        }

        $remaining = max(1, min(900, (int) $payload['exp'] - $now));
        $downloadToken = null;
        if ($record['object_id'] !== null) {
            $address = new ObjectAddress(
                $expectedWorkspaceId,
                (string) $record['object_id'],
                (string) $record['resource_version_id'],
                (string) $record['classification'],
            );
            $downloadToken = $this->downloadTokens->issue($address, $now, $remaining);
        }
        Transaction::run($this->database, function () use ($expectedWorkspaceId, $expectedUserId, $record): void {
            $this->execute('UPDATE content_delivery_issuances SET last_accessed_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace', ['id' => $record['id'], 'workspace' => $expectedWorkspaceId]);
            $this->event($expectedWorkspaceId, (string) $record['id'], 'served', ['channel' => (string) $record['channel']]);
            $this->audit->record($expectedWorkspaceId, $expectedUserId, 'content.delivery.served', 'content_delivery_issuance', (string) $record['id'], 'success', ['resource_id' => (string) $record['resource_id'], 'channel' => (string) $record['channel']]);
        });

        return [
            'issuance_id' => (string) $record['id'],
            'resource_id' => (string) $record['resource_id'],
            'resource_version_id' => (string) $record['resource_version_id'],
            'content' => $record['content_json'] === null ? null : json_decode((string) $record['content_json'], true, 64, JSON_THROW_ON_ERROR),
            'object_id' => $record['object_id'] === null ? null : (string) $record['object_id'],
            'download_token' => $downloadToken,
            'forward_protection_required' => in_array($record['channel'], ['telegram', 'bale'], true),
        ];
    }

    /** @return array<string, mixed> */
    private function deliveryRecord(string $userId, string $workspaceId, string $resourceId, string $versionId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT user.display_name, version.object_id
FROM iam_users user
JOIN content_resource_versions version ON version.id = :version
 AND version.resource_id = :resource AND version.workspace_id = :workspace
WHERE user.id = :user AND user.status = 'active' AND user.deleted_at IS NULL
SQL);
        $query->execute(['version' => $versionId, 'resource' => $resourceId, 'workspace' => $workspaceId, 'user' => $userId]);
        $record = $query->fetch();
        if ($record === false) {
            throw new PlatformException('delivery_subject_unavailable', 'Delivery subject is unavailable.', 409);
        }

        return $record;
    }

    /** @return array{iss:string,ws:string,user:string,resource:string,version:string,object:?string,channel:string,exp:int} */
    private function verifyToken(string $token, string $expectedUserId, string $expectedWorkspaceId, int $now): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || !hash_equals(self::encode(hash_hmac('sha256', $parts[0], $this->signingKey, true)), $parts[1])) {
            throw new PlatformException('delivery_token_invalid', 'Delivery token is invalid.', 403);
        }
        try {
            $payload = json_decode(self::decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new PlatformException('delivery_token_invalid', 'Delivery token payload is invalid.', 403);
        }
        if (!is_array($payload)
            || !isset($payload['iss'], $payload['ws'], $payload['user'], $payload['resource'], $payload['version'], $payload['channel'], $payload['exp'])
            || !is_string($payload['iss']) || !is_string($payload['ws']) || !is_string($payload['user'])
            || !is_string($payload['resource']) || !is_string($payload['version']) || !is_string($payload['channel'])
            || !is_int($payload['exp']) || (!is_null($payload['object'] ?? null) && !is_string($payload['object']))) {
            throw new PlatformException('delivery_token_invalid', 'Delivery token payload is invalid.', 403);
        }
        foreach (['iss', 'ws', 'user', 'resource', 'version'] as $field) {
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $payload[$field])) {
                throw new PlatformException('delivery_token_invalid', 'Delivery token identifiers are invalid.', 403);
            }
        }
        if (!in_array($payload['channel'], self::CHANNELS, true)) {
            throw new PlatformException('delivery_token_invalid', 'Delivery token channel is invalid.', 403);
        }
        if (!hash_equals(strtolower($expectedWorkspaceId), $payload['ws']) || !hash_equals(strtolower($expectedUserId), $payload['user'])) {
            throw new PlatformException('delivery_subject_mismatch', 'Delivery token belongs to another subject.', 403);
        }
        if ($payload['exp'] < $now || $payload['exp'] > $now + 900) {
            throw new PlatformException('delivery_token_expired', 'Delivery token has expired or has an invalid lifetime.', 403);
        }

        /** @var array{iss:string,ws:string,user:string,resource:string,version:string,object:?string,channel:string,exp:int} $payload */
        return $payload;
    }

    /** @param array<string, scalar|bool|null> $metadata */
    private function event(string $workspaceId, string $issuanceId, string $eventType, array $metadata): void
    {
        $this->execute(<<<'SQL'
INSERT INTO content_delivery_events (id, workspace_id, issuance_id, event_type, occurred_at, metadata_json)
VALUES (:id, :workspace, :issuance, :event, UTC_TIMESTAMP(6), :metadata)
SQL, [
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'issuance' => $issuanceId,
            'event' => $eventType,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            throw new PlatformException('delivery_token_invalid', 'Delivery token encoding is invalid.', 403);
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if ($decoded === false) {
            throw new PlatformException('delivery_token_invalid', 'Delivery token encoding is invalid.', 403);
        }

        return $decoded;
    }

    /** @param array<string, scalar|null> $parameters */
    private function execute(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }
}
