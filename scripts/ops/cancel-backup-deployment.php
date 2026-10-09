<?php

declare(strict_types=1);

use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use RuntimeException;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $requestId = $argv[1] ?? '';
    if ($argc !== 2 || preg_match('/^[0-9a-f-]{36}$/i', $requestId) !== 1) {
        throw new RuntimeException('Usage: php scripts/ops/cancel-backup-deployment.php <request-uuid>');
    }

    $targetKey = RuntimeConfig::load()->requireString('FANOOS_UPDATER_TARGET_KEY');
    $database = DatabaseConnection::fromEnvironment();
    $lockName = 'fanoos-deploy-' . substr(hash('sha256', $targetKey), 0, 40);
    $lock = $database->prepare('SELECT GET_LOCK(:name, 0)');
    $lock->execute(['name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Updater is still running; request was not changed.');
    }

    try {
        $result = Transaction::run($database, static function () use ($database, $requestId, $targetKey): array {
            $query = $database->prepare(<<<'SQL'
SELECT request.state
FROM release_update_requests request
JOIN release_update_targets target ON target.id = request.target_id
WHERE request.id = :id AND target.target_key = :target
LIMIT 1
FOR UPDATE
SQL);
            $query->execute(['id' => $requestId, 'target' => $targetKey]);
            $state = $query->fetchColumn();
            if ($state !== 'BACKUP') {
                throw new RuntimeException('Only a stopped deployment in BACKUP state can be cancelled.');
            }

            $update = $database->prepare(<<<'SQL'
UPDATE release_update_requests
SET state = 'FAILED', safe_failure_code = 'operator_cancelled',
    lease_token_digest = NULL, leased_until = NULL,
    finished_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
WHERE id = :id AND state = 'BACKUP'
SQL);
            $update->execute(['id' => $requestId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Deployment state changed before cancellation.');
            }

            $event = $database->prepare(<<<'SQL'
INSERT INTO release_update_events (id, request_id, state, event_code, detail_json, created_at)
VALUES (:id, :request, 'FAILED', 'deployment.cancelled', :detail, UTC_TIMESTAMP(6))
SQL);
            $event->execute([
                'id' => Uuid::v7(),
                'request' => $requestId,
                'detail' => json_encode(['reason' => 'operator_requested', 'last_state' => 'BACKUP'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ]);

            return ['request_id' => $requestId, 'previous_state' => 'BACKUP', 'state' => 'FAILED'];
        });
    } finally {
        try {
            $release = $database->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => $lockName]);
            $release->fetchColumn();
        } catch (Throwable) {
            // MySQL releases connection-scoped advisory locks when the connection closes.
        }
    }

    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Deployment cancellation failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
