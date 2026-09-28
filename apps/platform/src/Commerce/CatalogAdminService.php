<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * The owner's product list: what a workspace sells, at what price, and what
 * buying it opens.
 *
 * A price is never edited in place. Changing it closes the current price
 * version and opens a new one from this moment, so the price history is kept
 * and an order already placed keeps the price it was placed at (order lines
 * snapshot it). The owner can change prices whenever they like.
 *
 * Everything here needs commerce.manage_catalog in the workspace; platform
 * owners hold it everywhere.
 */
final class CatalogAdminService
{
    private const STATUSES = ['draft', 'active', 'archived'];
    private const MIN_RIAL = 1000;
    private const MAX_RIAL = 1_000_000_000_000;

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function products(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.manage_catalog');
        $query = $this->database->prepare(<<<'SQL'
SELECT product.id, product.product_key, product.name, product.status, product.target_scope_id,
       scope.scope_type,
       (SELECT price.amount_minor FROM commerce_price_versions price
        WHERE price.product_id = product.id AND price.workspace_id = product.workspace_id
          AND price.valid_from <= UTC_TIMESTAMP(6) AND (price.valid_until IS NULL OR price.valid_until > UTC_TIMESTAMP(6))
        ORDER BY price.valid_from DESC LIMIT 1) AS amount_minor,
       (SELECT COUNT(*) FROM exam_access_policies policy
        WHERE policy.workspace_id = product.workspace_id AND policy.target_scope_id = product.target_scope_id) AS exams_total,
       (SELECT COUNT(*) FROM exam_access_policies policy
        WHERE policy.workspace_id = product.workspace_id AND policy.target_scope_id = product.target_scope_id
          AND policy.requires_entitlement = TRUE) AS exams_locked,
       (SELECT COUNT(*) FROM commerce_order_lines line
        JOIN commerce_orders orders ON orders.id = line.order_id AND orders.workspace_id = line.workspace_id
        WHERE line.product_id = product.id AND line.workspace_id = product.workspace_id AND orders.status = 'paid') AS paid_orders
FROM commerce_products product
LEFT JOIN rbac_scopes scope ON scope.id = product.target_scope_id
WHERE product.workspace_id = :workspace
ORDER BY FIELD(product.status, 'active', 'draft', 'archived'), product.created_at DESC
SQL);
        $query->execute(['workspace' => $workspaceId]);

        $products = [];
        foreach ($query->fetchAll() as $row) {
            $products[] = [
                'id' => (string) $row['id'],
                'key' => (string) $row['product_key'],
                'name' => (string) $row['name'],
                'status' => (string) $row['status'],
                'scope_type' => $row['scope_type'] === null ? null : (string) $row['scope_type'],
                'amount_rial' => $row['amount_minor'] === null ? null : (int) $row['amount_minor'],
                'exams_total' => (int) $row['exams_total'],
                'exams_locked' => (int) $row['exams_locked'],
                'paid_orders' => (int) $row['paid_orders'],
                'price_history' => $this->priceHistory($workspaceId, (string) $row['id']),
            ];
        }

        return $products;
    }

    /**
     * Creates a product, or changes one: its name, its status, and -- when the
     * amount differs from the current one -- its price from now on.
     *
     * @param array<string, mixed> $input name, amount_rial, status, and for a new product key and scope_id (default: the workspace scope)
     * @return array<string, mixed> the product as products() lists it
     */
    public function save(string $actorUserId, string $workspaceId, ?string $productId, array $input): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.manage_catalog');
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')) ?? '');
        if ($name === '' || mb_strlen($name) > 200) {
            throw new PlatformException('product_name_invalid', 'A product needs a name of up to 200 characters.', 422);
        }
        $amount = filter_var($input['amount_rial'] ?? null, FILTER_VALIDATE_INT);
        if ($amount === false || $amount < self::MIN_RIAL || $amount > self::MAX_RIAL) {
            throw new PlatformException('product_price_invalid', 'The price must be at least 1,000 Rials.', 422);
        }
        $status = (string) ($input['status'] ?? 'draft');
        if (!in_array($status, self::STATUSES, true)) {
            throw new PlatformException('product_status_invalid', 'Product status is invalid.', 422);
        }

        $id = Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $productId, $input, $name, $amount, $status): string {
            if ($productId === null) {
                $key = strtolower(trim((string) ($input['key'] ?? '')));
                if ($key === '') {
                    $key = 'product-' . substr(str_replace('-', '', Uuid::v7()), -10);
                }
                if (preg_match('/^[a-z0-9][a-z0-9._-]{1,95}$/', $key) !== 1) {
                    throw new PlatformException('product_key_invalid', 'Product key must be lowercase ASCII.', 422);
                }
                $scopeId = $this->scope($workspaceId, (string) ($input['scope_id'] ?? ''));
                $productId = Uuid::v7();
                try {
                    $this->database->prepare(<<<'SQL'
INSERT INTO commerce_products (id, workspace_id, target_scope_id, product_key, name, status, created_at, updated_at)
VALUES (:id, :workspace, :scope, :key, :name, :status, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => $productId, 'workspace' => $workspaceId, 'scope' => $scopeId, 'key' => $key, 'name' => $name, 'status' => $status]);
                } catch (\PDOException $error) {
                    if ((string) $error->getCode() === '23000') {
                        throw new PlatformException('product_key_taken', 'Another product in this workspace uses that key.', 409);
                    }
                    throw $error;
                }
            } else {
                $update = $this->database->prepare(<<<'SQL'
UPDATE commerce_products
SET name = :name, status = :status, updated_at = UTC_TIMESTAMP(6),
    archived_at = CASE WHEN :archived = 1 THEN COALESCE(archived_at, UTC_TIMESTAMP(6)) ELSE NULL END
WHERE id = :id AND workspace_id = :workspace
SQL);
                $update->execute([
                    'name' => $name, 'status' => $status, 'archived' => $status === 'archived' ? 1 : 0,
                    'id' => $productId, 'workspace' => $workspaceId,
                ]);
                $exists = $this->database->prepare('SELECT 1 FROM commerce_products WHERE id = :id AND workspace_id = :workspace FOR UPDATE');
                $exists->execute(['id' => $productId, 'workspace' => $workspaceId]);
                if ($exists->fetchColumn() === false) {
                    throw new PlatformException('product_not_found', 'Product was not found.', 404);
                }
            }

            $current = $this->database->prepare(<<<'SQL'
SELECT amount_minor FROM commerce_price_versions
WHERE product_id = :product AND workspace_id = :workspace
  AND valid_from <= UTC_TIMESTAMP(6) AND (valid_until IS NULL OR valid_until > UTC_TIMESTAMP(6))
ORDER BY valid_from DESC LIMIT 1
FOR UPDATE
SQL);
            $current->execute(['product' => $productId, 'workspace' => $workspaceId]);
            $currentAmount = $current->fetchColumn();
            if ($currentAmount === false || (int) $currentAmount !== $amount) {
                // The old price ends and the new one begins at the same instant.
                $now = gmdate('Y-m-d H:i:s.') . sprintf('%06d', (int) ((microtime(true) - floor(microtime(true))) * 1_000_000));
                $this->database->prepare(<<<'SQL'
UPDATE commerce_price_versions SET valid_until = :now
WHERE product_id = :product AND workspace_id = :workspace AND valid_until IS NULL AND valid_from < :now_check
SQL)->execute(['now' => $now, 'now_check' => $now, 'product' => $productId, 'workspace' => $workspaceId]);
                $this->database->prepare(<<<'SQL'
INSERT INTO commerce_price_versions (id, workspace_id, product_id, amount_minor, currency, valid_from, created_at)
VALUES (:id, :workspace, :product, :amount, 'IRR', :now, UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'product' => $productId, 'amount' => $amount, 'now' => $now]);
            }

            $this->audit->record($workspaceId, $actorUserId, 'commerce.product.save', 'commerce_product', $productId, 'success', [
                'status' => $status, 'amount_rial' => $amount,
                'price_changed' => $currentAmount === false || (int) $currentAmount !== $amount,
            ]);

            return $productId;
        });

        return $this->one($actorUserId, $workspaceId, $id);
    }

    /**
     * Locks or unlocks, behind this product, every assessment that points at
     * its scope. Selling a product does not lock anything by itself.
     *
     * @return array<string, mixed>
     */
    public function setExamLock(string $actorUserId, string $workspaceId, string $productId, bool $locked): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.manage_catalog');
        Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $productId, $locked): void {
            $scope = $this->database->prepare('SELECT target_scope_id FROM commerce_products WHERE id = :id AND workspace_id = :workspace');
            $scope->execute(['id' => $productId, 'workspace' => $workspaceId]);
            $scopeId = $scope->fetchColumn();
            if (!is_string($scopeId) || $scopeId === '') {
                throw new PlatformException('product_not_found', 'Product was not found.', 404);
            }
            $update = $this->database->prepare(<<<'SQL'
UPDATE exam_access_policies SET requires_entitlement = :locked, updated_at = UTC_TIMESTAMP(6)
WHERE workspace_id = :workspace AND target_scope_id = :scope
SQL);
            $update->execute(['locked' => $locked ? 1 : 0, 'workspace' => $workspaceId, 'scope' => $scopeId]);
            $this->audit->record($workspaceId, $actorUserId, 'commerce.product.exam_lock', 'commerce_product', $productId, 'success', [
                'locked' => $locked, 'assessments' => $update->rowCount(),
            ]);
        });

        return $this->one($actorUserId, $workspaceId, $productId);
    }

    /** @return array<string, mixed> */
    private function one(string $actorUserId, string $workspaceId, string $productId): array
    {
        foreach ($this->products($actorUserId, $workspaceId) as $product) {
            if ($product['id'] === $productId) {
                return $product;
            }
        }
        throw new PlatformException('product_not_found', 'Product was not found.', 404);
    }

    /** @return list<array{amount_rial:int,valid_from:string,valid_until:?string}> */
    private function priceHistory(string $workspaceId, string $productId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT amount_minor, valid_from, valid_until FROM commerce_price_versions
WHERE product_id = :product AND workspace_id = :workspace
ORDER BY valid_from DESC LIMIT 20
SQL);
        $query->execute(['product' => $productId, 'workspace' => $workspaceId]);

        return array_map(static fn (array $row): array => [
            'amount_rial' => (int) $row['amount_minor'],
            'valid_from' => gmdate(DATE_ATOM, (int) strtotime($row['valid_from'] . ' UTC')),
            'valid_until' => $row['valid_until'] === null ? null : gmdate(DATE_ATOM, (int) strtotime($row['valid_until'] . ' UTC')),
        ], $query->fetchAll());
    }

    private function scope(string $workspaceId, string $scopeId): string
    {
        if ($scopeId === '') {
            $query = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND entity_id = :workspace AND workspace_id = :workspace_check");
            $query->execute(['workspace' => $workspaceId, 'workspace_check' => $workspaceId]);
        } else {
            $query = $this->database->prepare('SELECT id FROM rbac_scopes WHERE id = :id AND workspace_id = :workspace AND archived_at IS NULL');
            $query->execute(['id' => $scopeId, 'workspace' => $workspaceId]);
        }
        $found = $query->fetchColumn();
        if (!is_string($found) || $found === '') {
            throw new PlatformException('product_scope_invalid', 'That scope does not exist in this workspace.', 422);
        }

        return $found;
    }
}
