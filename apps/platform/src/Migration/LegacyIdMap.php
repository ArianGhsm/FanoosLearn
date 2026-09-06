<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use PDO;
use RuntimeException;
use Throwable;
use Fanoos\Platform\Support\Uuid;

final class LegacyIdMap
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $hmacKey,
    ) {
        if ($hmacKey === '') {
            throw new RuntimeException('A non-empty legacy identifier HMAC key is required.');
        }
    }

    public function remember(
        string $sourceSystemId,
        string $sourceEntityType,
        string $sourceKey,
        string $targetEntityType,
        string $targetId,
        ?string $batchId = null,
    ): string {
        $digest = hash_hmac('sha256', $sourceKey, $this->hmacKey, true);
        $ownsTransaction = !$this->database->inTransaction();
        if ($ownsTransaction) {
            $this->database->beginTransaction();
        }

        try {
            $find = $this->database->prepare(<<<'SQL'
SELECT id, target_entity_type, target_id
FROM migration_legacy_id_mappings
WHERE source_system_id = :source_system_id
  AND source_entity_type = :source_entity_type
  AND source_key_digest = :source_key_digest
FOR UPDATE
SQL);
            $find->bindValue('source_system_id', $sourceSystemId);
            $find->bindValue('source_entity_type', $sourceEntityType);
            $find->bindValue('source_key_digest', $digest, PDO::PARAM_LOB);
            $find->execute();
            $existing = $find->fetch();

            if ($existing !== false) {
                if ($existing['target_entity_type'] !== $targetEntityType || $existing['target_id'] !== $targetId) {
                    throw new RuntimeException('Legacy identifier conflict: the source identity already maps to another target.');
                }

                $update = $this->database->prepare(<<<'SQL'
UPDATE migration_legacy_id_mappings
SET last_seen_batch_id = COALESCE(:batch_id, last_seen_batch_id), last_seen_at = UTC_TIMESTAMP(6)
WHERE id = :id
SQL);
                $update->execute(['batch_id' => $batchId, 'id' => $existing['id']]);
                $mappingId = (string) $existing['id'];
            } else {
                $mappingId = Uuid::v7();
                $insert = $this->database->prepare(<<<'SQL'
INSERT INTO migration_legacy_id_mappings (
    id, source_system_id, source_entity_type, source_key_digest,
    target_entity_type, target_id, first_batch_id, last_seen_batch_id,
    created_at, last_seen_at
) VALUES (
    :id, :source_system_id, :source_entity_type, :source_key_digest,
    :target_entity_type, :target_id, :first_batch_id, :last_batch_id,
    UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL);
                $insert->bindValue('id', $mappingId);
                $insert->bindValue('source_system_id', $sourceSystemId);
                $insert->bindValue('source_entity_type', $sourceEntityType);
                $insert->bindValue('source_key_digest', $digest, PDO::PARAM_LOB);
                $insert->bindValue('target_entity_type', $targetEntityType);
                $insert->bindValue('target_id', $targetId);
                $insert->bindValue('first_batch_id', $batchId);
                $insert->bindValue('last_batch_id', $batchId);
                $insert->execute();
            }

            if ($ownsTransaction) {
                $this->database->commit();
            }

            return $mappingId;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }
}
