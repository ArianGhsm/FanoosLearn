<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use PDO;

final class BotCommerceService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly CommerceService $commerce,
        private readonly EntitlementService $entitlements,
    ) {
    }

    /** @return array<string,mixed> */
    public function createOrder(string $userId, string $workspaceId, string $productId, string $idempotencyKey): array
    {
        $created = $this->commerce->createOrder($userId, $workspaceId, $productId, $idempotencyKey);
        $projection = $this->project($userId, $workspaceId, (string) $created['order_id']);
        $projection['payment_url'] = $created['redirect_url'] ?? null;
        return $projection;
    }

    /** @return array<string,mixed> */
    public function status(string $userId, string $workspaceId, string $orderId): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'commerce.purchase');
        return $this->project($userId, $workspaceId, $orderId);
    }

    /** @return array<string,mixed> */
    private function project(string $userId, string $workspaceId, string $orderId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT orders.id, orders.status, orders.total_minor, orders.currency, orders.created_at, orders.paid_at,
       line.product_name_snapshot, product.target_scope_id
FROM commerce_orders orders
JOIN commerce_order_lines line ON line.order_id = orders.id AND line.workspace_id = orders.workspace_id
JOIN commerce_products product ON product.id = line.product_id AND product.workspace_id = line.workspace_id
WHERE orders.id = :order AND orders.workspace_id = :workspace AND orders.buyer_user_id = :user
ORDER BY line.created_at ASC
LIMIT 1
SQL);
        $query->execute(['order' => $orderId, 'workspace' => $workspaceId, 'user' => $userId]);
        $row = $query->fetch();
        if ($row === false) {
            throw new PlatformException('order_not_found', 'Order was not found.', 404);
        }
        $targetScope = $row['target_scope_id'] === null ? null : (string) $row['target_scope_id'];
        return [
            'order_id' => (string) $row['id'],
            'status' => (string) $row['status'],
            'amount_minor' => (int) $row['total_minor'],
            'currency' => (string) $row['currency'],
            'title' => (string) $row['product_name_snapshot'],
            'created_at' => (string) $row['created_at'],
            'paid_at' => $row['paid_at'] === null ? null : (string) $row['paid_at'],
            'entitlement' => [
                'target_scope_id' => $targetScope,
                'granted' => $targetScope !== null && $this->entitlements->has($userId, $workspaceId, $targetScope),
            ],
        ];
    }
}
