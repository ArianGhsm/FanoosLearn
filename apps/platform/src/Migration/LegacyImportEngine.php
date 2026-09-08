<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use DateTimeImmutable;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;
use Throwable;

final class LegacyImportEngine
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $schemaCache = [];

    public function __construct(
        private readonly PDO $database,
        private readonly string $hmacKey,
        private readonly LegacyBundleValidator $validator,
    ) {
        if (strlen($hmacKey) < 16) {
            throw new RuntimeException('Legacy import requires an HMAC key of at least 16 bytes.');
        }
    }

    /** @param array<string, mixed> $bundle @return array<string, mixed> */
    public function dryRun(array $bundle): array
    {
        $validation = $this->validator->assertValid($bundle);
        $workspaceMap = $bundle['workspace_map'];
        $this->assertWorkspacesExist($workspaceMap);
        $sourceId = $this->findSourceSystemId((string) $validation['source_key']);

        $counts = ['insert' => 0, 'unchanged' => 0, 'conflict' => 0];
        $conflicts = [];
        foreach ($this->orderedRows($bundle['rows']) as [$index, $row]) {
            $prepared = $this->prepareRow($row, $workspaceMap);
            $mapped = $sourceId === null ? null : $this->mappedTarget($sourceId, (string) $row['entity_type'], (string) $row['source_key']);
            if ($mapped !== null && ($mapped['target_entity_type'] !== $row['entity_type'] || $mapped['target_id'] !== $row['target_id'])) {
                ++$counts['conflict'];
                $conflicts[] = $this->safeConflict($index, $row, 'legacy_mapping_conflict');
                continue;
            }

            $existing = $this->fetchExisting($prepared);
            if ($existing === null) {
                ++$counts['insert'];
                continue;
            }
            if ($this->matches($existing, $prepared)) {
                ++$counts['unchanged'];
                continue;
            }
            ++$counts['conflict'];
            $conflicts[] = $this->safeConflict($index, $row, 'target_row_conflict');
        }

        return [
            'mode' => 'dry-run',
            'valid' => $counts['conflict'] === 0,
            'source_key' => $validation['source_key'],
            'snapshot_sha256' => $validation['snapshot_sha256'],
            'batch_key' => $validation['batch_key'],
            'bundle_sha256' => $validation['bundle_sha256'],
            'row_count' => $validation['row_count'],
            'planned_insert' => $counts['insert'],
            'planned_unchanged' => $counts['unchanged'],
            'conflict_count' => $counts['conflict'],
            'conflicts' => $conflicts,
            'warnings' => $validation['warnings'],
        ];
    }

    /** @param array<string, mixed> $bundle @return array<string, mixed> */
    public function apply(array $bundle, ?string $requestedByUserId = null): array
    {
        $validation = $this->validator->assertValid($bundle);
        $plan = $this->dryRun($bundle);
        if ($plan['valid'] !== true) {
            throw new RuntimeException('Legacy import has preflight conflicts. Apply is blocked; inspect the dry-run report.');
        }

        $sourceSystemId = $this->registerSource((string) $validation['source_key']);
        $existingBatch = $this->findBatch($sourceSystemId, (string) $validation['batch_key']);
        if ($existingBatch !== null) {
            $this->assertSameBatch($existingBatch, $validation);
            if ($existingBatch['status'] === 'completed' || $existingBatch['status'] === 'completed_with_rejects') {
                return $this->batchSummary((string) $existingBatch['id'], true);
            }
            throw new RuntimeException('The batch key already exists in a non-terminal or failed state; preserve that ledger and use a new batch_key for a supervised retry.');
        }

        $batchId = $this->createBatch($sourceSystemId, $requestedByUserId, $validation, $bundle);
        $legacyMap = new LegacyIdMap($this->database, $this->hmacKey);
        $workspaceMap = $bundle['workspace_map'];
        $currentIndex = null;
        $currentRow = null;

        $this->database->beginTransaction();
        try {
            $this->database->prepare("UPDATE migration_batches SET status = 'running', started_at = UTC_TIMESTAMP(6) WHERE id = :id")
                ->execute(['id' => $batchId]);

            foreach ($this->orderedRows($bundle['rows']) as [$index, $row]) {
                $currentIndex = $index;
                $currentRow = $row;
                $prepared = $this->prepareRow($row, $workspaceMap);
                $existing = $this->fetchExisting($prepared);
                $outcome = 'unchanged';
                if ($existing === null) {
                    $this->insertPrepared($prepared);
                    $outcome = 'inserted';
                } elseif (!$this->matches($existing, $prepared)) {
                    throw new RuntimeException('Target row changed after dry-run preflight.');
                }

                $legacyMap->remember(
                    $sourceSystemId,
                    (string) $row['entity_type'],
                    (string) $row['source_key'],
                    (string) $row['entity_type'],
                    (string) $row['target_id'],
                    $batchId,
                );
                $this->recordRowResult($batchId, $row, $outcome, $prepared['workspace_id'] ?? null, (string) $validation['bundle_sha256']);
            }

            $this->database->prepare("UPDATE migration_batches SET status = 'completed', completed_at = UTC_TIMESTAMP(6) WHERE id = :id")
                ->execute(['id' => $batchId]);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->recordFailure($batchId, $currentIndex, $currentRow);
            throw new RuntimeException(
                'Legacy import failed; no domain rows from this batch were committed. Safe row index: ' . ($currentIndex === null ? 'pre-apply' : (string) $currentIndex) . '.',
                0,
                $error,
            );
        }

        return $this->batchSummary($batchId, false);
    }

    /** @param array<string, mixed> $workspaceMap */
    private function assertWorkspacesExist(array $workspaceMap): void
    {
        $query = $this->database->prepare('SELECT status FROM tenant_workspaces WHERE id = :id');
        foreach ($workspaceMap as $legacyKey => $workspaceId) {
            $query->execute(['id' => $workspaceId]);
            $status = $query->fetchColumn();
            if ($status === false) {
                throw new RuntimeException("Destination workspace mapping does not exist for {$legacyKey}.");
            }
            if ($status === 'archived') {
                throw new RuntimeException("Destination workspace mapping is archived for {$legacyKey}.");
            }
        }
    }

    /** @param list<array<string, mixed>> $rows @return list<array{0:int,1:array<string,mixed>}> */
    private function orderedRows(array $rows): array
    {
        $specs = LegacyBundleValidator::specs();
        $indexed = [];
        foreach ($rows as $index => $row) {
            $indexed[] = [$index, $row];
        }
        usort($indexed, static function (array $left, array $right) use ($specs): int {
            $l = $specs[$left[1]['entity_type']]['order'];
            $r = $specs[$right[1]['entity_type']]['order'];
            return $l === $r ? $left[0] <=> $right[0] : $l <=> $r;
        });
        return $indexed;
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $workspaceMap @return array<string, mixed> */
    private function prepareRow(array $row, array $workspaceMap): array
    {
        $spec = LegacyBundleValidator::specs()[$row['entity_type']];
        $table = $spec['table'];
        $schema = $this->tableSchema($table);
        $values = $row['values'];
        $workspaceId = $spec['workspace'] ? (string) $workspaceMap[$row['workspace_key']] : null;

        foreach ($values as $field => $_) {
            if (!isset($schema[$field])) {
                throw new RuntimeException("Canonical destination field {$table}.{$field} does not exist.");
            }
            if (in_array($field, ['id', 'workspace_id'], true)) {
                throw new RuntimeException('Importer-owned identity fields cannot be supplied by the bundle.');
            }
        }

        $insert = ['id' => (string) $row['target_id']];
        if ($workspaceId !== null) {
            $insert['workspace_id'] = $workspaceId;
        }
        foreach ($values as $field => $value) {
            $insert[$field] = $this->convertValue($value, $schema[$field]);
        }
        $now = gmdate('Y-m-d H:i:s') . '.000000';
        foreach (['created_at', 'updated_at'] as $timestamp) {
            if (isset($schema[$timestamp]) && !array_key_exists($timestamp, $insert)) {
                $insert[$timestamp] = $now;
            }
        }

        foreach ($schema as $column => $metadata) {
            if (($metadata['nullable'] ?? true) === false
                && ($metadata['default'] ?? null) === null
                && !str_contains((string) ($metadata['extra'] ?? ''), 'auto_increment')
                && !array_key_exists($column, $insert)) {
                throw new RuntimeException("Required canonical destination field {$table}.{$column} is missing.");
            }
        }

        return [
            'table' => $table,
            'entity_type' => (string) $row['entity_type'],
            'target_id' => (string) $row['target_id'],
            'workspace_id' => $workspaceId,
            'insert' => $insert,
            'compare_fields' => array_values(array_unique(array_merge(
                $workspaceId === null ? [] : ['workspace_id'],
                array_keys($values),
            ))),
            'schema' => $schema,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function tableSchema(string $table): array
    {
        if (isset($this->schemaCache[$table])) {
            return $this->schemaCache[$table];
        }
        $statement = $this->database->prepare(<<<'SQL'
SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, DATA_TYPE, COLUMN_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
ORDER BY ORDINAL_POSITION
SQL);
        $statement->execute(['table' => $table]);
        $schema = [];
        foreach ($statement->fetchAll() as $row) {
            $schema[(string) $row['COLUMN_NAME']] = [
                'nullable' => $row['IS_NULLABLE'] === 'YES',
                'default' => $row['COLUMN_DEFAULT'],
                'extra' => (string) $row['EXTRA'],
                'data_type' => strtolower((string) $row['DATA_TYPE']),
                'column_type' => strtolower((string) $row['COLUMN_TYPE']),
            ];
        }
        if ($schema === []) {
            throw new RuntimeException("Approved migration destination table {$table} is missing.");
        }
        return $this->schemaCache[$table] = $schema;
    }

    /** @param array<string, mixed> $metadata */
    private function convertValue(mixed $value, array $metadata): mixed
    {
        if ($value === null) {
            return null;
        }
        $type = $metadata['data_type'] ?? '';
        if ($type === 'json') {
            return is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (in_array($type, ['binary', 'varbinary'], true) && is_string($value) && preg_match('/^hex:([0-9a-f]+)$/i', $value, $match)) {
            $decoded = hex2bin($match[1]);
            if ($decoded === false) {
                throw new RuntimeException('Invalid hex-encoded binary migration field.');
            }
            return $decoded;
        }
        if (in_array($type, ['datetime', 'timestamp'], true)) {
            if (!is_string($value)) {
                throw new RuntimeException('Datetime migration fields must be strings.');
            }
            return $this->canonicalDateTime($value);
        }
        if ($type === 'date') {
            if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                throw new RuntimeException('Date migration fields must use YYYY-MM-DD.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new RuntimeException('Invalid date migration field.');
            }
            return $date->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_array($value) || is_object($value)) {
            throw new RuntimeException('Only JSON destination columns may receive nested values.');
        }
        return $value;
    }

    /** @param array<string, mixed> $prepared @return array<string, mixed>|null */
    private function fetchExisting(array $prepared): ?array
    {
        $columns = array_unique(array_merge(['id'], $prepared['compare_fields']));
        $quoted = implode(', ', array_map(static fn (string $field): string => '`' . $field . '`', $columns));
        $statement = $this->database->prepare("SELECT {$quoted} FROM `{$prepared['table']}` WHERE id = :id LIMIT 1");
        $statement->execute(['id' => $prepared['target_id']]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $prepared */
    private function matches(array $existing, array $prepared): bool
    {
        foreach ($prepared['compare_fields'] as $field) {
            $expected = $prepared['insert'][$field] ?? null;
            $actual = $existing[$field] ?? null;
            $type = $prepared['schema'][$field]['data_type'] ?? '';
            if ($type === 'json') {
                try {
                    $actualDecoded = is_string($actual) ? json_decode($actual, true, 64, JSON_THROW_ON_ERROR) : $actual;
                    $expectedDecoded = is_string($expected) ? json_decode($expected, true, 64, JSON_THROW_ON_ERROR) : $expected;
                } catch (Throwable) {
                    return false;
                }
                if ($this->canonical($actualDecoded) !== $this->canonical($expectedDecoded)) {
                    return false;
                }
                continue;
            }
            if (in_array($type, ['binary', 'varbinary'], true)) {
                if (!is_string($actual) || !is_string($expected) || !hash_equals($actual, $expected)) {
                    return false;
                }
                continue;
            }
            if (in_array($type, ['datetime', 'timestamp'], true)) {
                if (!is_string($actual) || !is_string($expected)
                    || !hash_equals($this->canonicalDateTime($actual), $this->canonicalDateTime($expected))) {
                    return false;
                }
                continue;
            }
            if ($type === 'date') {
                if ((string) $actual !== (string) $expected) {
                    return false;
                }
                continue;
            }
            if ($actual === null || $expected === null) {
                if ($actual !== $expected) {
                    return false;
                }
                continue;
            }
            if (is_numeric($actual) && is_numeric($expected)) {
                if ($this->numberString((string) $actual) !== $this->numberString((string) $expected)) {
                    return false;
                }
                continue;
            }
            if ((string) $actual !== (string) $expected) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, mixed> $prepared */
    private function insertPrepared(array $prepared): void
    {
        $fields = array_keys($prepared['insert']);
        $columns = implode(', ', array_map(static fn (string $field): string => '`' . $field . '`', $fields));
        $placeholders = implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields));
        $statement = $this->database->prepare("INSERT INTO `{$prepared['table']}` ({$columns}) VALUES ({$placeholders})");
        foreach ($prepared['insert'] as $field => $value) {
            if ($value === null) {
                $statement->bindValue(':' . $field, null, PDO::PARAM_NULL);
            } elseif (in_array($prepared['schema'][$field]['data_type'] ?? '', ['binary', 'varbinary'], true)) {
                $statement->bindValue(':' . $field, $value, PDO::PARAM_LOB);
            } elseif (is_int($value)) {
                $statement->bindValue(':' . $field, $value, PDO::PARAM_INT);
            } else {
                $statement->bindValue(':' . $field, (string) $value);
            }
        }
        $statement->execute();
    }

    private function findSourceSystemId(string $sourceKey): ?string
    {
        $statement = $this->database->prepare("SELECT id FROM migration_source_systems WHERE source_key = :key AND mode = 'read_only' AND retired_at IS NULL");
        $statement->execute(['key' => $sourceKey]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (string) $id;
    }

    private function registerSource(string $sourceKey): string
    {
        $existing = $this->findSourceSystemId($sourceKey);
        if ($existing !== null) {
            return $existing;
        }
        $id = Uuid::v7();
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO migration_source_systems (id, source_key, description, mode, created_at)
VALUES (:id, :key, :description, 'read_only', UTC_TIMESTAMP(6))
SQL);
        $statement->execute([
            'id' => $id,
            'key' => $sourceKey,
            'description' => 'Stage 8 normalized read-only legacy source',
        ]);
        return $id;
    }

    /** @return array<string, mixed>|null */
    private function findBatch(string $sourceSystemId, string $batchKey): ?array
    {
        $statement = $this->database->prepare(<<<'SQL'
SELECT id, status, HEX(source_snapshot_digest) AS source_snapshot_digest, manifest_json
FROM migration_batches
WHERE source_system_id = :source AND batch_key = :batch
LIMIT 1
SQL);
        $statement->execute(['source' => $sourceSystemId, 'batch' => $batchKey]);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $existing @param array<string, mixed> $validation */
    private function assertSameBatch(array $existing, array $validation): void
    {
        if (!hash_equals(strtolower((string) $existing['source_snapshot_digest']), (string) $validation['snapshot_sha256'])) {
            throw new RuntimeException('batch_key is already bound to a different source snapshot.');
        }
        $manifest = json_decode((string) $existing['manifest_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !hash_equals((string) ($manifest['bundle_sha256'] ?? ''), (string) $validation['bundle_sha256'])) {
            throw new RuntimeException('batch_key is already bound to a different normalized migration bundle.');
        }
    }

    /** @param array<string, mixed> $validation @param array<string, mixed> $bundle */
    private function createBatch(string $sourceSystemId, ?string $requestedByUserId, array $validation, array $bundle): string
    {
        $id = Uuid::v7();
        $summary = [
            'schema_version' => 1,
            'bundle_sha256' => $validation['bundle_sha256'],
            'row_count' => $validation['row_count'],
            'transformed_count' => $validation['transformed_count'],
            'entity_counts' => $validation['entity_counts'],
            'workspace_map' => $bundle['workspace_map'],
            'expectations' => $validation['expectations'],
        ];
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO migration_batches (
    id, source_system_id, batch_key, requested_by_user_id, status,
    source_snapshot_digest, manifest_json, created_at
) VALUES (
    :id, :source, :batch, :requester, 'validating',
    :snapshot, :manifest, UTC_TIMESTAMP(6)
)
SQL);
        $statement->bindValue(':id', $id);
        $statement->bindValue(':source', $sourceSystemId);
        $statement->bindValue(':batch', $validation['batch_key']);
        $statement->bindValue(':requester', $requestedByUserId);
        $statement->bindValue(':snapshot', hex2bin((string) $validation['snapshot_sha256']), PDO::PARAM_LOB);
        $statement->bindValue(':manifest', json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $statement->execute();
        return $id;
    }

    /** @return array{target_entity_type:string,target_id:string}|null */
    private function mappedTarget(string $sourceSystemId, string $entityType, string $sourceKey): ?array
    {
        $digest = $this->sourceDigest($sourceKey);
        $statement = $this->database->prepare(<<<'SQL'
SELECT target_entity_type, target_id
FROM migration_legacy_id_mappings
WHERE source_system_id = :source AND source_entity_type = :entity AND source_key_digest = :digest
LIMIT 1
SQL);
        $statement->bindValue(':source', $sourceSystemId);
        $statement->bindValue(':entity', $entityType);
        $statement->bindValue(':digest', $digest, PDO::PARAM_LOB);
        $statement->execute();
        $row = $statement->fetch();
        return $row === false ? null : ['target_entity_type' => (string) $row['target_entity_type'], 'target_id' => (string) $row['target_id']];
    }

    /** @param array<string, mixed> $row */
    private function recordRowResult(string $batchId, array $row, string $outcome, ?string $workspaceId, string $bundleDigest): void
    {
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO migration_row_results (
    id, batch_id, source_entity_type, source_key_digest, outcome,
    target_entity_type, target_id, error_code, detail_json, created_at
) VALUES (
    :id, :batch, :entity, :digest, :outcome,
    :target_entity, :target_id, NULL, :detail, UTC_TIMESTAMP(6)
)
SQL);
        $statement->bindValue(':id', Uuid::v7());
        $statement->bindValue(':batch', $batchId);
        $statement->bindValue(':entity', $row['entity_type']);
        $statement->bindValue(':digest', $this->sourceDigest((string) $row['source_key']), PDO::PARAM_LOB);
        $statement->bindValue(':outcome', $outcome);
        $statement->bindValue(':target_entity', $row['entity_type']);
        $statement->bindValue(':target_id', $row['target_id']);
        $statement->bindValue(':detail', json_encode([
            'workspace_id' => $workspaceId,
            'transformed' => ($row['transformed'] ?? false) === true,
            'bundle_sha256' => $bundleDigest,
        ], JSON_THROW_ON_ERROR));
        $statement->execute();
    }

    /** @param array<string, mixed>|null $row */
    private function recordFailure(string $batchId, ?int $index, ?array $row): void
    {
        $this->database->prepare("UPDATE migration_batches SET status = 'failed', completed_at = UTC_TIMESTAMP(6) WHERE id = :id")
            ->execute(['id' => $batchId]);
        if ($row === null) {
            return;
        }
        $digest = $this->sourceDigest((string) $row['source_key']);
        $statement = $this->database->prepare(<<<'SQL'
INSERT INTO migration_rejects (
    id, batch_id, source_entity_type, source_key_digest, error_code,
    redacted_payload_json, occurred_at
) VALUES (:id, :batch, :entity, :digest, 'apply_failed', :payload, UTC_TIMESTAMP(6))
SQL);
        $statement->bindValue(':id', Uuid::v7());
        $statement->bindValue(':batch', $batchId);
        $statement->bindValue(':entity', $row['entity_type']);
        $statement->bindValue(':digest', $digest, PDO::PARAM_LOB);
        $statement->bindValue(':payload', json_encode([
            'row_index' => $index,
            'source_digest' => bin2hex($digest),
        ], JSON_THROW_ON_ERROR));
        $statement->execute();
    }

    /** @return array<string, mixed> */
    private function batchSummary(string $batchId, bool $replayed): array
    {
        $batch = $this->database->prepare('SELECT status FROM migration_batches WHERE id = :id');
        $batch->execute(['id' => $batchId]);
        $status = $batch->fetchColumn();
        $rows = $this->database->prepare('SELECT outcome, COUNT(*) AS count_rows FROM migration_row_results WHERE batch_id = :id GROUP BY outcome');
        $rows->execute(['id' => $batchId]);
        $counts = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => 0, 'conflict' => 0];
        foreach ($rows->fetchAll() as $row) {
            $counts[(string) $row['outcome']] = (int) $row['count_rows'];
        }
        $rejects = $this->database->prepare('SELECT COUNT(*) FROM migration_rejects WHERE batch_id = :id');
        $rejects->execute(['id' => $batchId]);
        $counts['rejected'] = (int) $rejects->fetchColumn();
        return [
            'mode' => 'apply',
            'batch_id' => $batchId,
            'status' => (string) $status,
            'replayed' => $replayed,
            'outcomes' => $counts,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function safeConflict(int $index, array $row, string $reason): array
    {
        return [
            'row_index' => $index,
            'entity_type' => $row['entity_type'],
            'source_digest' => bin2hex($this->sourceDigest((string) $row['source_key'])),
            'reason' => $reason,
        ];
    }

    private function sourceDigest(string $sourceKey): string
    {
        return hash_hmac('sha256', $sourceKey, $this->hmacKey, true);
    }

    private function canonicalDateTime(string $value): string
    {
        $value = trim($value);
        foreach (['Y-m-d H:i:s.u', 'Y-m-d H:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($parsed === false) {
                continue;
            }
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $parsed->format('Y-m-d H:i:s.u');
            }
        }
        throw new RuntimeException('Datetime migration fields must be explicit UTC wall-clock values using YYYY-MM-DD HH:MM:SS[.uuuuuu].');
    }

    /** @param mixed $value */
    private function canonical(mixed $value): string
    {
        $normalize = static function (mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) {
                return $item;
            }
            if (!array_is_list($item)) {
                ksort($item);
            }
            foreach ($item as $key => $child) {
                $item[$key] = $normalize($child);
            }
            return $item;
        };
        return json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function numberString(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }
        return in_array($value, ['-0', '+0', ''], true) ? '0' : ltrim($value, '+');
    }
}
