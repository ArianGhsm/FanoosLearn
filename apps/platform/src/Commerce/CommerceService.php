<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class CommerceService
{
    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly EntitlementService $entitlements,
        private readonly AuditLogger $audit,
        private readonly PaymentGateway $gateway,
        private readonly string $callbackTokenKey,
    ) {
        if (strlen($callbackTokenKey) < 32) {
            throw new PlatformException('weak_callback_key', 'Payment callback key must contain at least 32 characters.', 500);
        }
    }

    /** @return array<string, mixed> */
    public function createOrder(string $actorUserId, string $workspaceId, string $productId, string $idempotencyKey): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.purchase');
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 160) {
            throw new PlatformException('invalid_idempotency_key', 'A valid idempotency key is required.', 422);
        }

        $order = Transaction::run($this->database, function () use ($actorUserId, $workspaceId, $productId, $idempotencyKey): array {
            $existing = $this->database->prepare('SELECT id, buyer_user_id, total_minor, currency, status FROM commerce_orders WHERE workspace_id = :workspace AND idempotency_key = :key FOR UPDATE');
            $existing->execute(['workspace' => $workspaceId, 'key' => $idempotencyKey]);
            $found = $existing->fetch();
            if ($found !== false) {
                if (!hash_equals((string) $found['buyer_user_id'], $actorUserId)) {
                    throw new PlatformException('idempotency_conflict', 'Idempotency key belongs to another account.', 409);
                }
                return $found;
            }

            $quote = $this->database->prepare(<<<'SQL'
SELECT product.id, product.name, product.target_scope_id, price.id AS price_id, price.amount_minor, price.currency
FROM commerce_products product
JOIN commerce_price_versions price ON price.product_id = product.id AND price.workspace_id = product.workspace_id
WHERE product.id = :product AND product.workspace_id = :workspace AND product.status = 'active'
  AND product.archived_at IS NULL AND product.target_scope_id IS NOT NULL
  AND price.valid_from <= UTC_TIMESTAMP(6) AND (price.valid_until IS NULL OR price.valid_until > UTC_TIMESTAMP(6))
ORDER BY price.valid_from DESC LIMIT 1
FOR UPDATE
SQL);
            $quote->execute(['product' => $productId, 'workspace' => $workspaceId]);
            $product = $quote->fetch();
            if ($product === false) {
                throw new PlatformException('product_unavailable', 'Product or active price is unavailable.', 404);
            }

            $orderId = Uuid::v7();
            $callbackToken = $this->callbackToken($orderId);
            $insertOrder = $this->database->prepare(<<<'SQL'
INSERT INTO commerce_orders (
    id, workspace_id, buyer_user_id, public_token_digest, status, total_minor,
    currency, idempotency_key, version, created_at, updated_at
) VALUES (:id, :workspace, :buyer, :token_digest, 'pending', :amount, :currency, :key, 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $insertOrder->bindValue(':id', $orderId);
            $insertOrder->bindValue(':workspace', $workspaceId);
            $insertOrder->bindValue(':buyer', $actorUserId);
            $insertOrder->bindValue(':token_digest', hash('sha256', $callbackToken, true), PDO::PARAM_LOB);
            $insertOrder->bindValue(':amount', (int) $product['amount_minor'], PDO::PARAM_INT);
            $insertOrder->bindValue(':currency', (string) $product['currency']);
            $insertOrder->bindValue(':key', $idempotencyKey);
            $insertOrder->execute();
            $line = $this->database->prepare(<<<'SQL'
INSERT INTO commerce_order_lines (
    id, workspace_id, order_id, product_id, price_version_id, quantity,
    unit_amount_minor, line_total_minor, product_name_snapshot, created_at
) VALUES (:id, :workspace, :order_id, :product, :price, 1, :unit_amount, :line_amount, :name, UTC_TIMESTAMP(6))
SQL);
            $line->execute([
                'id' => Uuid::v7(), 'workspace' => $workspaceId, 'order_id' => $orderId,
                'product' => $product['id'], 'price' => $product['price_id'],
                'unit_amount' => $product['amount_minor'], 'line_amount' => $product['amount_minor'], 'name' => $product['name'],
            ]);
            $attempt = $this->database->prepare(<<<'SQL'
INSERT INTO commerce_payment_attempts (
    id, workspace_id, order_id, idempotency_key, provider_key, status,
    requested_amount_minor, created_at, updated_at
) VALUES (:id, :workspace, :order_id, :key, :provider, 'created', :amount, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $attemptId = Uuid::v7();
            $attempt->execute([
                'id' => $attemptId, 'workspace' => $workspaceId, 'order_id' => $orderId,
                'key' => 'start', 'provider' => $this->gateway->key(), 'amount' => $product['amount_minor'],
            ]);
            return ['id' => $orderId, 'total_minor' => $product['amount_minor'], 'currency' => $product['currency'], 'status' => 'pending', 'attempt_id' => $attemptId];
        });

        $callbackToken = $this->callbackToken((string) $order['id']);
        $attempt = $this->database->prepare('SELECT id, status, provider_reference FROM commerce_payment_attempts WHERE workspace_id = :workspace AND order_id = :order AND provider_key = :provider ORDER BY created_at DESC LIMIT 1');
        $attempt->execute(['workspace' => $workspaceId, 'order' => $order['id'], 'provider' => $this->gateway->key()]);
        $attemptRow = $attempt->fetch();
        if ($attemptRow === false) {
            throw new PlatformException('payment_attempt_missing', 'Payment attempt could not be found.', 500);
        }
        $start = null;
        if (in_array($attemptRow['status'], ['created'], true)) {
            $claim = $this->database->prepare("UPDATE commerce_payment_attempts SET status = 'verifying', updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace AND status = 'created'");
            $claim->execute(['id' => $attemptRow['id'], 'workspace' => $workspaceId]);
            if ($claim->rowCount() !== 1) {
                throw new PlatformException('payment_start_in_progress', 'Payment initiation is already in progress.', 409);
            }
            try {
                $start = $this->gateway->start((string) $order['id'], (int) $order['total_minor'], (string) $order['currency'], $callbackToken);
                $update = $this->database->prepare(<<<'SQL'
UPDATE commerce_payment_attempts
SET provider_authority_digest = :authority, provider_reference = :reference,
    status = 'redirected', updated_at = UTC_TIMESTAMP(6)
WHERE id = :id AND workspace_id = :workspace AND status = 'verifying'
SQL);
                $update->bindValue(':authority', hash('sha256', $start['authority'], true), PDO::PARAM_LOB);
                $update->bindValue(':reference', $start['authority']);
                $update->bindValue(':id', $attemptRow['id']);
                $update->bindValue(':workspace', $workspaceId);
                $update->execute();
                $this->database->prepare("UPDATE commerce_orders SET status = 'payment_pending', version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace AND status = 'pending'")
                    ->execute(['id' => $order['id'], 'workspace' => $workspaceId]);
            } catch (\Throwable $error) {
                $this->database->prepare("UPDATE commerce_payment_attempts SET status = 'failed', failure_code = 'gateway_start_failed', updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace AND status = 'verifying'")
                    ->execute(['id' => $attemptRow['id'], 'workspace' => $workspaceId]);
                throw new PlatformException('gateway_unavailable', 'Payment provider could not start the payment.', 503);
            }
        } elseif ($attemptRow['status'] === 'redirected' && is_string($attemptRow['provider_reference'])) {
            $start = [
                'authority' => $attemptRow['provider_reference'],
                'redirect_url' => $this->gateway->redirectUrl($attemptRow['provider_reference'], $callbackToken),
            ];
        }
        $this->audit->record($workspaceId, $actorUserId, 'commerce.order_create', 'commerce_order', (string) $order['id']);
        return [
            'order_id' => (string) $order['id'], 'attempt_id' => (string) $attemptRow['id'],
            'status' => $start === null ? (string) $order['status'] : 'payment_pending',
            'amount_minor' => (int) $order['total_minor'], 'currency' => (string) $order['currency'],
            'callback_token' => $callbackToken, 'redirect_url' => $start['redirect_url'] ?? null,
            'authority' => $start['authority'] ?? null,
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function handleCallback(string $callbackToken, string $authority, array $payload): array
    {
        $lookup = $this->loadAttemptByCallback($callbackToken, $authority);
        if ($lookup['status'] === 'verified' || $lookup['order_status'] === 'paid') {
            return ['order_id' => $lookup['order_id'], 'status' => 'paid', 'duplicate' => true];
        }
        if (in_array($lookup['status'], ['failed', 'cancelled', 'refunded'], true)) {
            return ['order_id' => $lookup['order_id'], 'status' => 'failed', 'duplicate' => true];
        }
        $claim = $this->database->prepare("UPDATE commerce_payment_attempts SET status = 'verifying', updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace AND status IN ('created', 'redirected')");
        $claim->execute(['id' => $lookup['attempt_id'], 'workspace' => $lookup['workspace_id']]);
        if ($claim->rowCount() !== 1) {
            $again = $this->loadAttemptByCallback($callbackToken, $authority);
            return ['order_id' => $again['order_id'], 'status' => $again['order_status'], 'duplicate' => true];
        }
        $verification = $this->gateway->verify($authority, (int) $lookup['total_minor'], (string) $lookup['currency'], $payload);
        return $this->finalize($lookup, $verification, null);
    }

    /** @return array<string, mixed> */
    public function reconcile(string $actorUserId, string $workspaceId, string $attemptId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'payment.reconcile');
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.id AS attempt_id, attempt.status, attempt.provider_key,
       orders.id AS order_id, orders.workspace_id, orders.buyer_user_id,
       orders.status AS order_status, orders.total_minor, orders.currency
FROM commerce_payment_attempts attempt
JOIN commerce_orders orders ON orders.id = attempt.order_id AND orders.workspace_id = attempt.workspace_id
WHERE attempt.id = :attempt AND attempt.workspace_id = :workspace
SQL);
        $query->execute(['attempt' => $attemptId, 'workspace' => $workspaceId]);
        $row = $query->fetch();
        if ($row === false || $row['provider_key'] !== $this->gateway->key()) {
            throw new PlatformException('payment_attempt_not_found', 'Payment attempt was not found.', 404);
        }
        if ($row['status'] === 'verified') {
            $result = ['order_id' => $row['order_id'], 'status' => 'paid', 'duplicate' => true];
            $resultCode = 'unchanged';
        } else {
            $verification = $this->gateway->reconcile((string) $row['order_id'], (int) $row['total_minor'], (string) $row['currency']);
            $result = $this->finalize($row, $verification, $actorUserId);
            $resultCode = $verification['verified'] ? 'verified' : 'failed';
        }
        $insert = $this->database->prepare(<<<'SQL'
INSERT INTO commerce_reconciliation_runs (
    id, workspace_id, payment_attempt_id, requested_by_user_id, result, detail_json, created_at
) VALUES (:id, :workspace, :attempt, :actor, :result, :detail, UTC_TIMESTAMP(6))
SQL);
        $insert->execute([
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'attempt' => $attemptId,
            'actor' => $actorUserId, 'result' => $resultCode,
            'detail' => json_encode(['status' => $result['status']], JSON_THROW_ON_ERROR),
        ]);
        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function history(string $actorUserId, string $workspaceId): array
    {
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.purchase');
        $query = $this->database->prepare(<<<'SQL'
SELECT orders.id, orders.status, orders.total_minor, orders.currency, orders.created_at, orders.paid_at,
       line.product_name_snapshot, attempt.provider_key, attempt.provider_reference
FROM commerce_orders orders
JOIN commerce_order_lines line ON line.order_id = orders.id AND line.workspace_id = orders.workspace_id
LEFT JOIN commerce_payment_attempts attempt ON attempt.order_id = orders.id AND attempt.workspace_id = orders.workspace_id
WHERE orders.workspace_id = :workspace AND orders.buyer_user_id = :user
ORDER BY orders.created_at DESC
SQL);
        $query->execute(['workspace' => $workspaceId, 'user' => $actorUserId]);
        return $query->fetchAll();
    }

    /** @return array<string, mixed> */
    private function loadAttemptByCallback(string $callbackToken, string $authority): array
    {
        if ($callbackToken === '' || $authority === '') {
            throw new PlatformException('invalid_payment_callback', 'Payment callback token and authority are required.', 400);
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT attempt.id AS attempt_id, attempt.status, attempt.provider_key,
       orders.id AS order_id, orders.workspace_id, orders.buyer_user_id,
       orders.status AS order_status, orders.total_minor, orders.currency
FROM commerce_orders orders
JOIN commerce_payment_attempts attempt ON attempt.order_id = orders.id AND attempt.workspace_id = orders.workspace_id
WHERE orders.public_token_digest = :token_digest
  AND attempt.provider_key = :provider AND attempt.provider_authority_digest = :authority_digest
LIMIT 1
SQL);
        $query->bindValue(':token_digest', hash('sha256', $callbackToken, true), PDO::PARAM_LOB);
        $query->bindValue(':provider', $this->gateway->key());
        $query->bindValue(':authority_digest', hash('sha256', $authority, true), PDO::PARAM_LOB);
        $query->execute();
        $row = $query->fetch();
        if ($row === false || !hash_equals($this->callbackToken((string) $row['order_id']), $callbackToken)) {
            throw new PlatformException('invalid_payment_callback', 'Payment callback could not be matched.', 404);
        }
        return $row;
    }

    /** @param array<string, mixed> $order @param array{verified:bool,reference:?string,failure_code:?string,payload:array<string,mixed>} $verification @return array<string, mixed> */
    private function finalize(array $order, array $verification, ?string $actorUserId): array
    {
        return Transaction::run($this->database, function () use ($order, $verification, $actorUserId): array {
            $lock = $this->database->prepare('SELECT status FROM commerce_orders WHERE id = :id AND workspace_id = :workspace FOR UPDATE');
            $lock->execute(['id' => $order['order_id'], 'workspace' => $order['workspace_id']]);
            $status = $lock->fetchColumn();
            if ($status === 'paid') {
                return ['order_id' => $order['order_id'], 'status' => 'paid', 'duplicate' => true];
            }
            if ($verification['verified']) {
                $attempt = $this->database->prepare(<<<'SQL'
UPDATE commerce_payment_attempts
SET status = 'verified', provider_reference = :reference, failure_code = NULL,
    provider_payload_json = :payload, verified_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6)
WHERE id = :id AND workspace_id = :workspace AND status <> 'verified'
SQL);
                $attempt->execute([
                    'reference' => $verification['reference'],
                    'payload' => json_encode($verification['payload'], JSON_THROW_ON_ERROR),
                    'id' => $order['attempt_id'], 'workspace' => $order['workspace_id'],
                ]);
                $this->database->prepare("UPDATE commerce_orders SET status = 'paid', paid_at = UTC_TIMESTAMP(6), failed_at = NULL, version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace")
                    ->execute(['id' => $order['order_id'], 'workspace' => $order['workspace_id']]);
                $scopes = $this->database->prepare(<<<'SQL'
SELECT DISTINCT product.target_scope_id
FROM commerce_order_lines line
JOIN commerce_products product ON product.id = line.product_id AND product.workspace_id = line.workspace_id
WHERE line.order_id = :order_id AND line.workspace_id = :workspace AND product.target_scope_id IS NOT NULL
SQL);
                $scopes->execute(['order_id' => $order['order_id'], 'workspace' => $order['workspace_id']]);
                foreach ($scopes->fetchAll(PDO::FETCH_COLUMN) as $scopeId) {
                    $this->entitlements->grantFromOrder((string) $order['workspace_id'], (string) $order['buyer_user_id'], (string) $scopeId, (string) $order['order_id']);
                }
                $this->audit->record((string) $order['workspace_id'], $actorUserId, 'payment.verify', 'commerce_order', (string) $order['order_id']);
                return ['order_id' => $order['order_id'], 'status' => 'paid', 'duplicate' => false];
            }

            $attempt = $this->database->prepare(<<<'SQL'
UPDATE commerce_payment_attempts
SET status = 'failed', failure_code = :failure_code, provider_payload_json = :payload, updated_at = UTC_TIMESTAMP(6)
WHERE id = :id AND workspace_id = :workspace AND status <> 'verified'
SQL);
            $attempt->execute([
                'failure_code' => $verification['failure_code'],
                'payload' => json_encode($verification['payload'], JSON_THROW_ON_ERROR),
                'id' => $order['attempt_id'], 'workspace' => $order['workspace_id'],
            ]);
            $this->database->prepare("UPDATE commerce_orders SET status = 'failed', failed_at = UTC_TIMESTAMP(6), version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace AND status <> 'paid'")
                ->execute(['id' => $order['order_id'], 'workspace' => $order['workspace_id']]);
            $this->audit->record((string) $order['workspace_id'], $actorUserId, 'payment.verify', 'commerce_order', (string) $order['order_id'], 'failure', ['failure_code' => $verification['failure_code']]);
            return ['order_id' => $order['order_id'], 'status' => 'failed', 'duplicate' => false];
        });
    }

    private function callbackToken(string $orderId): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $orderId, $this->callbackTokenKey, true)), '+/', '-_'), '=');
    }
}
