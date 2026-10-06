<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CatalogAdminService;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\DiscountService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Engagement\CoinService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * کد تخفیف و سکه: a code lowers what an order charges and nothing else;
 * its uses count only once paid; a personal code bought with coins is its
 * owner's alone; and coins cannot be spent twice.
 */
final class DiscountCoinTest
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
            'province' => ['name' => 'Discount Province ' . $suffix],
            'city' => ['name' => 'Discount City ' . $suffix],
            'institution' => ['name' => 'Discount University ' . $suffix],
            'faculty' => ['name' => 'Discount Faculty ' . $suffix],
            'program' => ['name' => 'Discount Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5903, 'label' => 'Discount Cohort ' . $suffix],
            'workspace' => ['name' => 'Discount Library ' . $suffix],
        ])['workspace_id'];
        $student = $this->member($workspace, 'student');
        $other = $this->member($workspace, 'student');
        $audit = new AuditLogger($this->database);
        $catalog = new CatalogAdminService($this->database, $this->access(), $audit);
        $year = $catalog->save($owner, $workspace, null, ['name' => 'اشتراک یک‌ساله', 'amount_rial' => 1500000, 'status' => 'active'])['id'];
        $month = $catalog->save($owner, $workspace, null, ['name' => 'اشتراک یک‌ماهه', 'amount_rial' => 150000, 'status' => 'active'])['id'];
        $discounts = new DiscountService($this->database, $this->access(), $audit);
        $commerce = new CommerceService($this->database, $this->access(), new EntitlementService($this->database, $this->access(), $audit), $audit, new FakePaymentGateway(), str_repeat('k', 32));

        // Only the catalog manager makes codes.
        $this->expectCode('forbidden', fn () => $discounts->create($student, $workspace, ['code' => 'NOPE1', 'label' => 'x', 'kind' => 'percent', 'percent' => 10]));
        $discounts->create($owner, $workspace, ['code' => 'term-20', 'label' => 'شروع ترم', 'kind' => 'percent', 'percent' => 20, 'per_user_limit' => 1]);
        $this->expectCode('discount_code_taken', fn () => $discounts->create($owner, $workspace, ['code' => 'TERM20', 'label' => 'دوباره', 'kind' => 'percent', 'percent' => 5]));
        $discounts->create($owner, $workspace, ['code' => 'YEARONLY', 'label' => 'فقط سالانه', 'kind' => 'amount', 'amount_minor' => 300000, 'product_id' => $year]);
        $discounts->create($owner, $workspace, ['code' => 'HUGE', 'label' => 'خیلی زیاد', 'kind' => 'amount', 'amount_minor' => 99000000]);

        // A check shows the new price; spelling and case do not matter.
        $check = $discounts->check($student, $workspace, $year, ' term 20 ');
        $this->assert($check['discount_minor'] === 300000 && $check['total_minor'] === 1200000, 'Twenty percent off was not quoted: ' . json_encode($check));
        $this->expectCode('discount_code_wrong_product', fn () => $discounts->check($student, $workspace, $month, 'YEARONLY'));
        $this->expectCode('discount_code_invalid', fn () => $discounts->check($student, $workspace, $year, 'NOSUCHCODE'));
        $huge = $discounts->check($student, $workspace, $month, 'HUGE');
        $this->assert($huge['total_minor'] === DiscountService::MINIMUM_CHARGE_MINOR, 'A discount took the charge below the minimum.');

        // The order charges the discounted total and records the code.
        $order = $commerce->createOrder($student, $workspace, $year, 'test-' . Uuid::v7(), 'TERM20');
        $this->assert($order['amount_minor'] === 1200000, 'The order was not charged the discounted total: ' . json_encode($order));
        $row = $this->database->prepare('SELECT total_minor, discount_minor, discount_code_id IS NOT NULL AS coded FROM commerce_orders WHERE id = :id');
        $row->execute(['id' => $order['order_id']]);
        $stored = $row->fetch();
        $this->assert((int) $stored['total_minor'] === 1200000 && (int) $stored['discount_minor'] === 300000 && (bool) $stored['coded'], 'The order does not record its discount.');

        // An unpaid order does not use the code up; a paid one does (once per person here).
        $this->assert($discounts->check($student, $workspace, $year, 'TERM20')['total_minor'] === 1200000, 'An unpaid order used the code up.');
        $this->database->prepare("UPDATE commerce_orders SET status = 'paid' WHERE id = :id")->execute(['id' => $order['order_id']]);
        $this->expectCode('discount_code_used', fn () => $discounts->check($student, $workspace, $year, 'TERM20'));
        $this->assert($discounts->check($other, $workspace, $year, 'TERM20')['total_minor'] === 1200000, 'One person\'s use blocked another.');

        // A disabled code is no longer valid.
        $termId = $discounts->codes($owner, $workspace);
        $termId = array_values(array_filter($termId, static fn (array $c): bool => $c['code'] === 'TERM20'))[0];
        $this->assert($termId['uses'] === 1, 'The code\'s paid use was not counted.');
        $discounts->setStatus($owner, $workspace, $termId['id'], false);
        $this->expectCode('discount_code_expired', fn () => $discounts->check($other, $workspace, $year, 'TERM20'));

        // Coins: a box costs coins and mints a personal, single-use code.
        $coins = new CoinService($this->database, $this->access(), $audit);
        $this->expectCode('forbidden', fn () => $coins->saveOffer($student, $workspace, null, ['title' => 'x', 'coins' => 1, 'kind' => 'percent', 'percent' => 5]));
        $box = $coins->saveOffer($owner, $workspace, null, ['title' => 'ده درصد سالانه', 'coins' => 3, 'kind' => 'percent', 'percent' => 10, 'product_id' => $year, 'code_valid_days' => 3])['id'];
        $this->expectCode('coins_insufficient', fn () => $coins->redeem($student, $workspace, $box));
        foreach (['2026-01-01', '2026-01-02', '2026-01-03', '2026-01-04'] as $day) {
            $this->database->prepare("INSERT INTO engagement_coin_ledger (id, workspace_id, user_id, delta, reason, reference, created_at) VALUES (:id, :workspace, :user, 1, 'daily_goal', :day, UTC_TIMESTAMP(6))")
                ->execute(['id' => Uuid::v7(), 'workspace' => $workspace, 'user' => $student, 'day' => $day]);
        }
        $wallet = $coins->wallet($student, $workspace);
        $this->assert($wallet['coins'] === 4 && $wallet['offers'][0]['affordable'] === true, 'The box is not offered as affordable.');
        $made = $coins->redeem($student, $workspace, $box);
        $this->assert($made['coins'] === 1 && preg_match('/^[A-Z0-9]{8}$/', $made['code']) === 1, 'Redeeming did not mint a code and take the coins: ' . json_encode($made));
        $this->expectCode('coins_insufficient', fn () => $coins->redeem($student, $workspace, $box));
        $after = $coins->wallet($student, $workspace);
        $this->assert($after['coins'] === 1 && count($after['codes']) === 1 && $after['codes'][0]['state'] === 'ready', 'The bought code is not in the wallet.');

        $mine = $discounts->check($student, $workspace, $year, $made['code']);
        $this->assert($mine['discount_minor'] === 150000, 'The personal code does not take ten percent off.');
        $this->expectCode('discount_code_invalid', fn () => $discounts->check($other, $workspace, $year, $made['code']));
        $this->assert($discounts->codes($owner, $workspace) !== [] && count(array_filter($discounts->codes($owner, $workspace), static fn (array $c): bool => $c['code'] === $made['code'])) === 0, 'Coin codes appear among the owner\'s codes.');

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
        $user = $this->user('Discount ' . $roleKey);
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
        $user = $this->user('Discount Owner');
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
