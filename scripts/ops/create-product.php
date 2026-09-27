<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;

/**
 * Creates (or reprices) a product the owner sells, until there is a screen
 * for it.
 *
 * A product grants, once paid, an entitlement on one authorization scope:
 * by default the workspace's own scope, which is what the imported banks'
 * assessments point at (exam_access_policies.target_scope_id), so one
 * product covers a whole discipline library. Selling access does not by
 * itself lock anything: --gate-exams additionally marks every assessment
 * that targets this scope as requiring the entitlement. Without it, the
 * exams stay free and the product is only on offer.
 *
 * A new price takes effect from now; earlier orders keep the price they were
 * placed at (order lines snapshot it).
 *
 * Usage:
 *   php scripts/ops/create-product.php --workspace=<uuid> --key=<ascii key> --name=<title>
 *       --rial=<price in Rials> [--scope=<scope uuid>] [--activate] [--gate-exams]
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $argument, $match) !== 1) {
            throw new RuntimeException("Unknown argument: {$argument}");
        }
        $options[$match[1]] = $match[2] ?? true;
    }
    $workspaceId = (string) ($options['workspace'] ?? '');
    $key = (string) ($options['key'] ?? '');
    $name = trim((string) ($options['name'] ?? ''));
    $rial = (string) ($options['rial'] ?? '');
    if (preg_match('/^[0-9a-f-]{36}$/', $workspaceId) !== 1 || preg_match('/^[a-z0-9][a-z0-9._-]{1,95}$/', $key) !== 1
        || $name === '' || preg_match('/^[1-9][0-9]{3,11}$/', $rial) !== 1) {
        throw new RuntimeException('Usage: --workspace=<uuid> --key=<ascii key> --name=<title> --rial=<at least 1000> [--scope=<uuid>] [--activate] [--gate-exams]');
    }

    $database = DatabaseConnection::fromEnvironment();
    $result = Transaction::run($database, static function () use ($database, $workspaceId, $key, $name, $rial, $options): array {
        $scopeId = (string) ($options['scope'] ?? '');
        if ($scopeId === '') {
            $scope = $database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :workspace AND workspace_id = :workspace_check");
            $scope->execute(['workspace' => $workspaceId, 'workspace_check' => $workspaceId]);
            $scopeId = (string) $scope->fetchColumn();
        } else {
            $scope = $database->prepare('SELECT id FROM rbac_scopes WHERE id = :id AND workspace_id = :workspace AND archived_at IS NULL');
            $scope->execute(['id' => $scopeId, 'workspace' => $workspaceId]);
            $scopeId = (string) $scope->fetchColumn();
        }
        if ($scopeId === '') {
            throw new RuntimeException('That scope does not exist in this workspace.');
        }

        $existing = $database->prepare('SELECT id FROM commerce_products WHERE workspace_id = :workspace AND product_key = :key FOR UPDATE');
        $existing->execute(['workspace' => $workspaceId, 'key' => $key]);
        $productId = $existing->fetchColumn();
        if ($productId === false) {
            $productId = Uuid::v7();
            $database->prepare(<<<'SQL'
INSERT INTO commerce_products (id, workspace_id, target_scope_id, product_key, name, status, created_at, updated_at)
VALUES (:id, :workspace, :scope, :key, :name, 'draft', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $productId, 'workspace' => $workspaceId, 'scope' => $scopeId, 'key' => $key, 'name' => mb_substr($name, 0, 200)]);
        } else {
            $database->prepare('UPDATE commerce_products SET name = :name, target_scope_id = :scope, updated_at = UTC_TIMESTAMP(6) WHERE id = :id')
                ->execute(['name' => mb_substr($name, 0, 200), 'scope' => $scopeId, 'id' => $productId]);
        }

        // The current price ends now and the new one starts now.
        $database->prepare(<<<'SQL'
UPDATE commerce_price_versions SET valid_until = UTC_TIMESTAMP(6)
WHERE product_id = :product AND workspace_id = :workspace AND valid_until IS NULL AND valid_from < UTC_TIMESTAMP(6)
SQL)->execute(['product' => $productId, 'workspace' => $workspaceId]);
        $database->prepare(<<<'SQL'
INSERT INTO commerce_price_versions (id, workspace_id, product_id, amount_minor, currency, valid_from, created_at)
VALUES (:id, :workspace, :product, :amount, 'IRR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'product' => $productId, 'amount' => (int) $rial]);

        if (isset($options['activate'])) {
            $database->prepare("UPDATE commerce_products SET status = 'active', updated_at = UTC_TIMESTAMP(6) WHERE id = :id")->execute(['id' => $productId]);
        }
        $gated = 0;
        if (isset($options['gate-exams'])) {
            $gate = $database->prepare(<<<'SQL'
UPDATE exam_access_policies SET requires_entitlement = TRUE, updated_at = UTC_TIMESTAMP(6)
WHERE workspace_id = :workspace AND target_scope_id = :scope AND requires_entitlement = FALSE
SQL);
            $gate->execute(['workspace' => $workspaceId, 'scope' => $scopeId]);
            $gated = $gate->rowCount();
        }
        (new AuditLogger($database))->record($workspaceId, null, 'commerce.product.upsert', 'commerce_product', (string) $productId, 'success', [
            'key' => $key, 'amount_rial' => (int) $rial, 'gated_exams' => $gated,
        ]);

        return ['product_id' => (string) $productId, 'scope_id' => $scopeId, 'amount_rial' => (int) $rial, 'active' => isset($options['activate']), 'exams_gated' => $gated];
    });

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
