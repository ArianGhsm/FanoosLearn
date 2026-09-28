<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CatalogAdminService;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Commerce\PaymentGateway;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * Automatic settlement of payments nobody came back to settle: a paid one
 * is marked paid and opens what was bought, a refused one fails, a gateway
 * that does not answer decides nothing, and a payer who may still be on the
 * bank's page is left alone.
 */
final class PaymentReconcileTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->user('Reconcile Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);
        $workspace = (new ClassProvisioningService($this->database, $this->access(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Reconcile Province ' . $suffix],
            'city' => ['name' => 'Reconcile City ' . $suffix],
            'institution' => ['name' => 'Reconcile University ' . $suffix],
            'faculty' => ['name' => 'Reconcile Faculty ' . $suffix],
            'program' => ['name' => 'Reconcile Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5903, 'label' => 'Reconcile Cohort ' . $suffix],
            'workspace' => ['name' => 'Reconcile Library ' . $suffix],
        ])['workspace_id'];
        $product = (new CatalogAdminService($this->database, $this->access(), new AuditLogger($this->database)))
            ->save($owner, $workspace, null, ['name' => 'دسترسی', 'amount_rial' => 500000, 'status' => 'active'])['id'];

        $paid = $this->student($workspace);
        $refused = $this->student($workspace);
        $silent = $this->student($workspace);
        $onTheBankPage = $this->student($workspace);
        $fake = $this->commerce(new FakePaymentGateway());
        $orders = [];
        foreach ([$paid, $refused, $silent, $onTheBankPage] as $buyer) {
            $orders[$buyer] = $fake->createOrder($buyer, $workspace, $product, 'rec-' . $buyer);
        }
        // Three payers went quiet half an hour ago; the fourth only just left for the bank.
        foreach ([$paid, $refused, $silent] as $buyer) {
            $this->database->prepare('UPDATE commerce_payment_attempts SET updated_at = UTC_TIMESTAMP(6) - INTERVAL 30 MINUTE WHERE id = :id')
                ->execute(['id' => $orders[$buyer]['attempt_id']]);
        }

        // A gateway that refuses one order and cannot be reached for another.
        $gateway = new class ($orders[$refused]['order_id'], $orders[$silent]['order_id']) implements PaymentGateway {
            public function __construct(private readonly string $refuse, private readonly string $unreachable)
            {
            }
            public function key(): string { return 'fake'; }
            public function start(string $orderId, int $amountMinor, string $currency, string $callbackToken): array { throw new RuntimeException('unused'); }
            public function redirectUrl(string $authority, string $callbackToken): string { return ''; }
            public function verify(string $authority, int $amountMinor, string $currency, array $payload): array { throw new RuntimeException('unused'); }
            public function reconcile(string $orderId, ?string $authority, int $amountMinor, string $currency): array
            {
                if ($orderId === $this->unreachable) {
                    throw new RuntimeException('gateway timed out');
                }
                $verified = $orderId !== $this->refuse;
                return ['verified' => $verified, 'reference' => $verified ? 'ref-' . $orderId : null, 'failure_code' => $verified ? null : 'not_paid', 'payload' => []];
            }
        };

        $summary = $this->commerce($gateway)->reconcileStale();
        $this->assert($summary === ['checked' => 3, 'paid' => 1, 'failed' => 1, 'unreachable' => 1], 'Unexpected settlement: ' . json_encode($summary));
        $this->assert($this->orderStatus($orders[$paid]['order_id']) === 'paid', 'A paid order nobody returned to stayed unpaid.');
        $this->assert($this->entitled($paid, $workspace), 'Settling a paid order did not open what was bought.');
        $this->assert($this->orderStatus($orders[$refused]['order_id']) === 'failed', 'A refused order was not failed.');
        $this->assert($this->orderStatus($orders[$silent]['order_id']) === 'payment_pending', 'An unreachable gateway decided an order.');
        $this->assert($this->orderStatus($orders[$onTheBankPage]['order_id']) === 'payment_pending', 'A payer still on the bank page was settled under them.');

        $again = $this->commerce($gateway)->reconcileStale();
        $this->assert($again['paid'] === 0 && $again['failed'] === 0, 'A settled order was settled twice.');

        return $this->assertions;
    }

    private function commerce(PaymentGateway $gateway): CommerceService
    {
        $audit = new AuditLogger($this->database);

        return new CommerceService($this->database, $this->access(), new EntitlementService($this->database, $this->access(), $audit), $audit, $gateway, str_repeat('k', 32));
    }

    private function orderStatus(string $orderId): string
    {
        $query = $this->database->prepare('SELECT status FROM commerce_orders WHERE id = :id');
        $query->execute(['id' => $orderId]);

        return (string) $query->fetchColumn();
    }

    private function entitled(string $user, string $workspace): bool
    {
        $query = $this->database->prepare('SELECT COUNT(*) FROM entitlement_grants WHERE subject_user_id = :user AND workspace_id = :workspace AND revoked_at IS NULL');
        $query->execute(['user' => $user, 'workspace' => $workspace]);

        return (int) $query->fetchColumn() > 0;
    }

    private function student(string $workspace): string
    {
        $user = $this->user('Reconcile Student');
        $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspace, 'user' => $user]);
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, scope.id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role
JOIN rbac_scopes scope ON scope.scope_type = 'workspace' AND scope.entity_id = :workspace AND scope.workspace_id = :workspace_check
WHERE role.role_key = 'student'
SQL)->execute(['id' => Uuid::v7(), 'user' => $user, 'workspace' => $workspace, 'workspace_check' => $workspace]);

        return $user;
    }

    private function user(string $name): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $name]);

        return $id;
    }

    private function access(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
