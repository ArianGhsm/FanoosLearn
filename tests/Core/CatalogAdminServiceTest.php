<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CatalogAdminService;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * The owner's product page: a price can change at any moment and takes
 * effect at once, without rewriting history or orders; only people with
 * commerce.manage_catalog can do it; and locking exams behind a product is
 * an explicit, reversible step.
 */
final class CatalogAdminServiceTest
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
            'province' => ['name' => 'Catalog Province ' . $suffix],
            'city' => ['name' => 'Catalog City ' . $suffix],
            'institution' => ['name' => 'Catalog University ' . $suffix],
            'faculty' => ['name' => 'Catalog Faculty ' . $suffix],
            'program' => ['name' => 'Catalog Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5902, 'label' => 'Catalog Cohort ' . $suffix],
            'workspace' => ['name' => 'Catalog Library ' . $suffix],
        ])['workspace_id'];
        $student = $this->member($workspace, 'student');
        $admin = new CatalogAdminService($this->database, $this->access(), new AuditLogger($this->database));

        // Only the catalog manager may see or change products.
        $this->expectDenied(fn () => $admin->products($student, $workspace));
        $this->expectDenied(fn () => $admin->save($student, $workspace, null, ['name' => 'x', 'amount_rial' => 10000]));

        $product = $admin->save($owner, $workspace, null, ['name' => 'دسترسی کامل', 'amount_rial' => 1500000, 'status' => 'active']);
        $this->assert($product['amount_rial'] === 1500000 && count($product['price_history']) === 1, 'A new product did not get its price.');

        $quote = $this->quote($student, $workspace, $product['id']);
        $this->assert($quote === 1500000, 'The store did not offer the product at its price.');

        // Reprice: effective at once, history kept.
        usleep(2000);
        $repriced = $admin->save($owner, $workspace, $product['id'], ['name' => 'دسترسی کامل', 'amount_rial' => 990000, 'status' => 'active']);
        $this->assert($repriced['amount_rial'] === 990000, 'The new price is not the current one.');
        $this->assert(count($repriced['price_history']) === 2 && $repriced['price_history'][1]['valid_until'] !== null, 'The old price was not closed and kept.');
        $this->assert($this->quote($student, $workspace, $product['id']) === 990000, 'The store still offered the old price.');

        // Saving without a price change adds no history.
        $same = $admin->save($owner, $workspace, $product['id'], ['name' => 'دسترسی کامل ۱۴۰۵', 'amount_rial' => 990000, 'status' => 'active']);
        $this->assert(count($same['price_history']) === 2 && $same['name'] === 'دسترسی کامل ۱۴۰۵', 'A rename wrote a new price version.');

        $this->expectCode('product_price_invalid', fn () => $admin->save($owner, $workspace, $product['id'], ['name' => 'x', 'amount_rial' => 50, 'status' => 'active']));

        // Exams are free until locked, and can be freed again.
        $this->assessment($workspace, $owner);
        $listed = $admin->products($owner, $workspace)[0];
        $this->assert($listed['exams_total'] >= 1 && $listed['exams_locked'] === 0, 'Selling a product locked exams by itself.');
        $locked = $admin->setExamLock($owner, $workspace, $product['id'], true);
        $this->assert($locked['exams_locked'] === $locked['exams_total'], 'Locking did not lock every exam behind the product.');
        $freed = $admin->setExamLock($owner, $workspace, $product['id'], false);
        $this->assert($freed['exams_locked'] === 0, 'Unlocking did not free the exams.');

        // Archived products are not offered.
        $admin->save($owner, $workspace, $product['id'], ['name' => 'دسترسی کامل ۱۴۰۵', 'amount_rial' => 990000, 'status' => 'archived']);
        $this->assert($this->quote($student, $workspace, $product['id']) === null, 'An archived product was still offered.');

        return $this->assertions;
    }

    private function quote(string $student, string $workspace, string $productId): ?int
    {
        $audit = new AuditLogger($this->database);
        $entitlements = new EntitlementService($this->database, $this->access(), $audit);
        $commerce = new CommerceService($this->database, $this->access(), $entitlements, $audit, new FakePaymentGateway(), str_repeat('k', 32));
        foreach ($commerce->catalog($student, $workspace) as $item) {
            if ($item['id'] === $productId) {
                return $item['amount_minor'];
            }
        }

        return null;
    }

    private function assessment(string $workspace, string $owner): void
    {
        $audit = new AuditLogger($this->database);
        $authorizer = new ScopeAuthorizer($this->database);
        $exams = new ExamService($this->database, $this->access(), $authorizer, new EntitlementService($this->database, $this->access(), $audit), $audit, new ExamQuestionRateGuard($this->database));
        $manager = $this->member($workspace, 'content-manager');
        $reviewer = $this->member($workspace, 'content-reviewer');
        $created = $exams->createAssessment($manager, $workspace, 'آزمون فروشی', ['questions' => [
            ['id' => 'q1', 'prompt' => 'یک؟', 'choices' => ['الف', 'ب'], 'answer' => 0],
        ]]);
        $exams->submitForReview($manager, $workspace, $created['assessment_id'], $created['version_id']);
        $exams->reviewVersion($reviewer, $workspace, $created['assessment_id'], $created['version_id'], 'approved');
        $exams->publishVersion($manager, $workspace, $created['assessment_id'], $created['version_id']);
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Catalog ' . $roleKey);
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
        $user = $this->user('Catalog Owner');
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

    private function expectDenied(callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->httpStatus === 403) {
                return;
            }
            throw new RuntimeException("Expected a 403, got {$error->errorCode}.");
        }
        throw new RuntimeException('A student managed products.');
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}.");
        }
        throw new RuntimeException("Expected {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
