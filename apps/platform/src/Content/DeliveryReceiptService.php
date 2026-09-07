<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use PDOException;

final class DeliveryReceiptService
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array{id:string,idempotent:bool} */
    public function record(
        string $workspaceId,
        string $issuanceId,
        string $channel,
        string $idempotencyKey,
        string $outcome,
        ?string $providerMessageRef = null,
        ?string $errorCode = null,
    ): array {
        if (!in_array($channel, ['telegram', 'bale', 'web', 'api'], true)
            || !in_array($outcome, ['delivered', 'failed'], true)
            || $idempotencyKey === '' || strlen($idempotencyKey) > 160) {
            throw new PlatformException('delivery_receipt_invalid', 'Delivery receipt is invalid.', 422);
        }
        $issuance = $this->database->prepare('SELECT channel FROM content_delivery_issuances WHERE id = :id AND workspace_id = :workspace LIMIT 1');
        $issuance->execute(['id' => $issuanceId, 'workspace' => $workspaceId]);
        $canonicalChannel = $issuance->fetchColumn();
        if ($canonicalChannel === false || !hash_equals((string) $canonicalChannel, $channel)) {
            throw new PlatformException('delivery_receipt_mismatch', 'Delivery receipt does not match the issuance.', 409);
        }

        $id = Uuid::v7();
        try {
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO content_delivery_receipts (
    id, workspace_id, issuance_id, channel, idempotency_key, outcome,
    provider_message_ref, error_code, created_at
) VALUES (:id, :workspace, :issuance, :channel, :key, :outcome, :provider_ref, :error_code, UTC_TIMESTAMP(6))
SQL);
            $insert->execute([
                'id' => $id, 'workspace' => $workspaceId, 'issuance' => $issuanceId,
                'channel' => $channel, 'key' => $idempotencyKey, 'outcome' => $outcome,
                'provider_ref' => $providerMessageRef, 'error_code' => $errorCode,
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            $existing = $this->database->prepare('SELECT id, outcome FROM content_delivery_receipts WHERE issuance_id = :issuance AND channel = :channel AND idempotency_key = :key');
            $existing->execute(['issuance' => $issuanceId, 'channel' => $channel, 'key' => $idempotencyKey]);
            $row = $existing->fetch();
            if ($row === false || !hash_equals((string) $row['outcome'], $outcome)) {
                throw new PlatformException('delivery_receipt_conflict', 'Delivery receipt idempotency key conflicts with another result.', 409);
            }
            return ['id' => (string) $row['id'], 'idempotent' => true];
        }

        return ['id' => $id, 'idempotent' => false];
    }
}
