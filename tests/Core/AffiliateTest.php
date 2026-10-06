<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\AffiliateService;
use Fanoos\Platform\Commerce\CatalogAdminService;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * برنامه همکاری در فروش: off until the owner turns it on; a sign-up through a
 * link is attributed; a paid order -- the discounted amount actually paid --
 * earns one commission; the owner marks it paid out; a referrer sees money,
 * never buyers.
 */
final class AffiliateTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->platformOwner();
        $workspace = (new ClassProvisioningService($this->database, $this->access(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Affiliate Province ' . $suffix],
            'city' => ['name' => 'Affiliate City ' . $suffix],
            'institution' => ['name' => 'Affiliate University ' . $suffix],
            'faculty' => ['name' => 'Affiliate Faculty ' . $suffix],
            'program' => ['name' => 'Affiliate Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5901, 'label' => 'Affiliate Cohort ' . $suffix],
            'workspace' => ['name' => 'Affiliate Library ' . $suffix],
        ])['workspace_id'];
        $referrer = $this->member($workspace, 'student');
        $audit = new AuditLogger($this->database);
        $affiliates = new AffiliateService($this->database, $this->access(), $audit);

        // Off until the owner turns it on.
        $this->assert($affiliates->overview($referrer, $workspace)['enabled'] === false, 'The programme started switched on.');
        $this->expectCode('affiliate_program_off', fn () => $affiliates->link($referrer, $workspace));
        $this->expectCode('forbidden', fn () => $affiliates->save($referrer, $workspace, ['enabled' => true, 'commission_percent' => 50, 'attribution_days' => 30]));
        $this->expectCode('affiliate_percent_invalid', fn () => $affiliates->save($owner, $workspace, ['enabled' => true, 'commission_percent' => 95, 'attribution_days' => 30]));
        $affiliates->save($owner, $workspace, ['enabled' => true, 'commission_percent' => 20, 'attribution_days' => 30]);

        $code = $affiliates->link($referrer, $workspace)['code'];
        $this->assert(preg_match('/^[a-z0-9]{10}$/', $code) === 1 && $affiliates->link($referrer, $workspace)['code'] === $code, 'A link is not ten letters, or changed on a second ask.');

        // Sign-ups: through the link is attributed; an unknown code and one's own link are not.
        $buyer = $this->member($workspace, 'student');
        $affiliates->attribute($buyer, strtoupper($code));
        $affiliates->attribute($this->member($workspace, 'student'), 'zzzzzzzzzz');
        $affiliates->attribute($referrer, $code);
        $this->assert($affiliates->overview($referrer, $workspace)['referrals'] === 1, 'Attribution counted the wrong sign-ups.');

        // A paid order earns one commission on what was paid.
        $product = (new CatalogAdminService($this->database, $this->access(), $audit))->save($owner, $workspace, null, ['name' => 'اشتراک', 'amount_rial' => 1500000, 'status' => 'active'])['id'];
        $commerce = new CommerceService($this->database, $this->access(), new EntitlementService($this->database, $this->access(), $audit), $audit, new FakePaymentGateway(), str_repeat('k', 32));
        $order = $commerce->createOrder($buyer, $workspace, $product, 'aff-' . Uuid::v7());
        $paid = $commerce->handleCallback($order['callback_token'], (string) $order['authority'], ['status' => 'success', 'amount_minor' => $order['amount_minor']]);
        $this->assert($paid['status'] === 'paid', 'The fixture order was not paid.');
        $commerce->handleCallback($order['callback_token'], (string) $order['authority'], ['status' => 'success', 'amount_minor' => $order['amount_minor']]);
        $mine = $affiliates->overview($referrer, $workspace);
        $this->assert($mine['buyers'] === 1 && $mine['pending_minor'] === 300000 && count($mine['commissions']) === 1, 'Twenty percent of the paid order was not recorded once: ' . json_encode($mine));
        $this->assert(!str_contains(json_encode($mine, JSON_UNESCAPED_UNICODE), $buyer), 'The referrer was shown who bought.');

        // A buyer not referred earns nobody anything.
        $stranger = $this->member($workspace, 'student');
        $other = $commerce->createOrder($stranger, $workspace, $product, 'aff-' . Uuid::v7());
        $commerce->handleCallback($other['callback_token'], (string) $other['authority'], ['status' => 'success', 'amount_minor' => $other['amount_minor']]);
        $this->assert($affiliates->overview($referrer, $workspace)['pending_minor'] === 300000, 'An unreferred purchase paid a commission.');

        // The owner sees the balance and marks it paid out.
        $admin = $affiliates->admin($owner, $workspace);
        $this->assert(count($admin['affiliates']) === 1 && $admin['affiliates'][0]['pending_minor'] === 300000, 'The owner does not see what is owed.');
        $payout = $affiliates->payOut($owner, $workspace, $referrer);
        $this->assert($payout === ['count' => 1, 'total_minor' => 300000], 'The payout did not cover the pending commission.');
        $after = $affiliates->overview($referrer, $workspace);
        $this->assert($after['pending_minor'] === 0 && $after['paid_out_minor'] === 300000 && $after['commissions'][0]['status'] === 'paid_out', 'The payout is not on the referrer\'s page.');

        // Turned off: no new attributions.
        $affiliates->save($owner, $workspace, ['enabled' => false, 'commission_percent' => 20, 'attribution_days' => 30]);
        $late = $this->member($workspace, 'student');
        $affiliates->attribute($late, $code);
        $affiliates->save($owner, $workspace, ['enabled' => true, 'commission_percent' => 20, 'attribution_days' => 30]);
        $this->assert($affiliates->overview($referrer, $workspace)['referrals'] === 1, 'A sign-up while the programme was off was attributed.');

        return $this->assertions;
    }

    private function expectCode(string $code, callable $call): void
    {
        try {
            $call();
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === $code, "Expected {$code}, got {$error->errorCode}.");
            return;
        }
        throw new RuntimeException("Expected {$code}, but the call succeeded.");
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Affiliate ' . $roleKey);
        $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspace, 'user' => $user]);
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, scope.id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role
JOIN rbac_scopes scope ON scope.scope_type = 'workspace' AND scope.entity_id = :workspace AND scope.workspace_id = :workspace_check
WHERE role.role_key = :role
SQL)->execute(['id' => Uuid::v7(), 'user' => $user, 'workspace' => $workspace, 'workspace_check' => $workspace, 'role' => $roleKey]);

        return $user;
    }

    private function platformOwner(): string
    {
        $user = $this->user('Affiliate Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $user]);

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
