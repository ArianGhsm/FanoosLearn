<?php

declare(strict_types=1);

namespace Fanoos\Platform\Notifications;

use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class NotificationDeliveryService
{
    public function __construct(
        private readonly PDO $database,
        private readonly MessagingLinkService $links,
        private readonly int $leaseSeconds = 120,
        private readonly int $maximumAttempts = 5,
    ) {
    }

    public function projectNext(): ?string
    {
        return Transaction::run($this->database, function (): ?string {
            $event = $this->database->query(<<<'SQL'
SELECT id, workspace_id, aggregate_id
FROM outbox_events
WHERE published_at IS NULL AND available_at <= UTC_TIMESTAMP(6)
  AND aggregate_type = 'notification' AND event_type = 'announcement.published'
ORDER BY occurred_at, id
LIMIT 1
FOR UPDATE SKIP LOCKED
SQL)->fetch();
            if ($event === false) {
                return null;
            }

            $recipients = $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO notification_recipients (
    id, workspace_id, notification_id, user_id, channel, status, created_at
)
SELECT UUID(), membership.workspace_id, :notification, membership.user_id, link.platform, 'pending', UTC_TIMESTAMP(6)
FROM tenant_workspace_memberships membership
JOIN messaging_links link ON link.user_id = membership.user_id
 AND link.status = 'active' AND link.revoked_at IS NULL
LEFT JOIN notification_channel_preferences preference
  ON preference.workspace_id = membership.workspace_id
 AND preference.user_id = membership.user_id AND preference.channel = link.platform
WHERE membership.workspace_id = :workspace
  AND membership.status = 'active'
  AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
  AND COALESCE(preference.enabled, TRUE) = TRUE
SQL);
            $recipients->execute(['notification' => $event['aggregate_id'], 'workspace' => $event['workspace_id']]);

            $deliveries = $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO notification_channel_deliveries (
    id, recipient_id, link_id, state, attempt_count, created_at, updated_at
)
SELECT UUID(), recipient.id, link.id, 'pending', 0, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM notification_recipients recipient
JOIN messaging_links link ON link.user_id = recipient.user_id AND link.platform = recipient.channel
 AND link.status = 'active' AND link.revoked_at IS NULL
WHERE recipient.notification_id = :notification AND recipient.workspace_id = :workspace
  AND recipient.channel IN ('telegram', 'bale')
SQL);
            $deliveries->execute(['notification' => $event['aggregate_id'], 'workspace' => $event['workspace_id']]);
            $this->database->prepare('UPDATE outbox_events SET published_at = UTC_TIMESTAMP(6), attempt_count = attempt_count + 1 WHERE id = :id')
                ->execute(['id' => $event['id']]);
            return (string) $event['id'];
        });
    }

    /** @return array<string,mixed>|null */
    public function claim(string $platform): ?array
    {
        $platform = $this->platform($platform);
        $leaseSeconds = max(30, min(600, $this->leaseSeconds));
        return Transaction::run($this->database, function () use ($platform, $leaseSeconds): ?array {
            $query = $this->database->prepare(<<<'SQL'
SELECT delivery.id, delivery.attempt_count, recipient.workspace_id, recipient.notification_id,
       recipient.user_id, recipient.channel, link.id AS link_id,
       message.title, message.body, message.data_json
FROM notification_channel_deliveries delivery
JOIN notification_recipients recipient ON recipient.id = delivery.recipient_id
JOIN notification_messages message ON message.id = recipient.notification_id AND message.workspace_id = recipient.workspace_id
JOIN messaging_links link ON link.id = delivery.link_id AND link.user_id = recipient.user_id
 AND link.platform = recipient.channel AND link.status = 'active' AND link.revoked_at IS NULL
JOIN tenant_workspace_memberships membership ON membership.workspace_id = recipient.workspace_id AND membership.user_id = recipient.user_id
LEFT JOIN notification_channel_preferences preference ON preference.workspace_id = recipient.workspace_id
 AND preference.user_id = recipient.user_id AND preference.channel = recipient.channel
WHERE recipient.channel = :platform AND message.status = 'published'
  AND membership.status = 'active' AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
  AND COALESCE(preference.enabled, TRUE) = TRUE
  AND delivery.state IN ('pending', 'retry')
  AND (delivery.leased_until IS NULL OR delivery.leased_until < UTC_TIMESTAMP(6))
ORDER BY delivery.created_at, delivery.id
LIMIT 1
FOR UPDATE SKIP LOCKED
SQL);
            $query->execute(['platform' => $platform]);
            $row = $query->fetch();
            if ($row === false) {
                return null;
            }

            $leaseToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $sql = "UPDATE notification_channel_deliveries SET state = 'leased', lease_token_digest = :digest, leased_until = DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$leaseSeconds} SECOND), attempt_count = attempt_count + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id";
            $update = $this->database->prepare($sql);
            $update->bindValue(':digest', hash('sha256', $leaseToken, true), PDO::PARAM_LOB);
            $update->bindValue(':id', (string) $row['id']);
            $update->execute();

            $data = json_decode((string) $row['data_json'], true, 32, JSON_THROW_ON_ERROR);
            return [
                'delivery_id' => (string) $row['id'],
                'lease_token' => $leaseToken,
                'lease_seconds' => $leaseSeconds,
                'workspace_id' => (string) $row['workspace_id'],
                'notification_id' => (string) $row['notification_id'],
                'subject' => $this->links->outboundSubject((string) $row['link_id'], $platform),
                'payload' => ['title' => (string) $row['title'], 'body' => (string) $row['body'], 'data' => is_array($data) ? $data : []],
                'attempt' => (int) $row['attempt_count'] + 1,
            ];
        });
    }

    /** @return array{delivery_id:string,state:string,idempotent:bool} */
    public function receipt(
        string $deliveryId,
        string $leaseToken,
        string $idempotencyKey,
        string $outcome,
        ?string $providerMessageRef = null,
        ?string $errorCode = null,
    ): array {
        if (!in_array($outcome, ['delivered', 'retry', 'failed'], true)) {
            throw new PlatformException('notification_receipt_invalid', 'Notification receipt outcome is invalid.', 422);
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 160 || $leaseToken === '') {
            throw new PlatformException('notification_receipt_invalid', 'Notification receipt identifiers are invalid.', 422);
        }

        return Transaction::run($this->database, function () use ($deliveryId, $leaseToken, $idempotencyKey, $outcome, $providerMessageRef, $errorCode): array {
            $existing = $this->database->prepare('SELECT outcome FROM notification_delivery_receipts WHERE delivery_id = :delivery AND idempotency_key = :key LIMIT 1');
            $existing->execute(['delivery' => $deliveryId, 'key' => $idempotencyKey]);
            $prior = $existing->fetchColumn();
            if ($prior !== false) {
                return ['delivery_id' => $deliveryId, 'state' => (string) $prior, 'idempotent' => true];
            }

            $query = $this->database->prepare('SELECT state, lease_token_digest, leased_until, attempt_count FROM notification_channel_deliveries WHERE id = :id FOR UPDATE');
            $query->execute(['id' => $deliveryId]);
            $delivery = $query->fetch();
            if ($delivery === false || $delivery['state'] !== 'leased'
                || !is_string($delivery['lease_token_digest'])
                || !hash_equals($delivery['lease_token_digest'], hash('sha256', $leaseToken, true))
                || $delivery['leased_until'] === null || strtotime((string) $delivery['leased_until'] . ' UTC') < time()) {
                throw new PlatformException('notification_lease_invalid', 'Notification delivery lease is invalid.', 409);
            }

            $effective = $outcome;
            if ($outcome === 'retry' && (int) $delivery['attempt_count'] >= $this->maximumAttempts) {
                $effective = 'failed';
                $errorCode ??= 'attempt_limit_reached';
            }
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO notification_delivery_receipts (
    id, delivery_id, idempotency_key, outcome, provider_message_ref, error_code, created_at
) VALUES (:id, :delivery, :key, :outcome, :provider_ref, :error_code, UTC_TIMESTAMP(6))
SQL);
            $insert->execute([
                'id' => Uuid::v7(), 'delivery' => $deliveryId, 'key' => $idempotencyKey,
                'outcome' => $effective, 'provider_ref' => $providerMessageRef, 'error_code' => $errorCode,
            ]);

            if ($effective === 'delivered') {
                $this->database->prepare("UPDATE notification_channel_deliveries SET state = 'delivered', provider_message_ref = :provider_ref, delivered_at = UTC_TIMESTAMP(6), last_error_code = NULL, lease_token_digest = NULL, leased_until = NULL, updated_at = UTC_TIMESTAMP(6) WHERE id = :id")
                    ->execute(['provider_ref' => $providerMessageRef, 'id' => $deliveryId]);
                $this->database->prepare("UPDATE notification_recipients recipient JOIN notification_channel_deliveries delivery ON delivery.recipient_id = recipient.id SET recipient.status = 'delivered', recipient.delivered_at = UTC_TIMESTAMP(6) WHERE delivery.id = :id")
                    ->execute(['id' => $deliveryId]);
            } else {
                $this->database->prepare("UPDATE notification_channel_deliveries SET state = :state, last_error_code = :error_code, lease_token_digest = NULL, leased_until = NULL, updated_at = UTC_TIMESTAMP(6) WHERE id = :id")
                    ->execute(['state' => $effective, 'error_code' => $errorCode ?? 'delivery_failed', 'id' => $deliveryId]);
            }
            return ['delivery_id' => $deliveryId, 'state' => $effective, 'idempotent' => false];
        });
    }

    private function platform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, ['telegram', 'bale'], true)) {
            throw new PlatformException('notification_platform_invalid', 'Notification platform is not supported.', 422);
        }
        return $platform;
    }
}
