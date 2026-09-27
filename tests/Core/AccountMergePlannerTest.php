<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Identity\AccountMergePlanner;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * The merge planner counts, per table, what a merge would move, and which of
 * those rows the target already has an equivalent of -- and writes nothing.
 */
final class AccountMergePlannerTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $source = $this->user('Bot Account');
        $target = $this->user('Web Account');
        $sharedWorkspace = (string) $this->database->query('SELECT id FROM tenant_workspaces ORDER BY created_at LIMIT 1')->fetchColumn();
        if ($sharedWorkspace === '') {
            throw new RuntimeException('Fixture needs at least one workspace from earlier scenarios.');
        }
        foreach ([$source, $target] as $user) {
            $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
                ->execute(['id' => Uuid::v7(), 'workspace' => $sharedWorkspace, 'user' => $user]);
        }
        $this->database->prepare("INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at) VALUES (:id, :user, 'phone', :phone, TRUE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'user' => $source, 'phone' => '+989' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT)]);

        $before = (int) $this->database->query('SELECT COUNT(*) FROM tenant_workspace_memberships')->fetchColumn();
        $plan = (new AccountMergePlanner($this->database))->inventory($source, $target);
        $rows = [];
        foreach ($plan['rows'] as $row) {
            $rows[$row['table'] . '.' . $row['column']] = $row;
        }

        $membership = $rows['tenant_workspace_memberships.user_id'];
        $this->assert($membership['count'] === 1 && $membership['duplicates'] === 1, 'A membership the target already has was not reported as a duplicate.');
        $phone = $rows['iam_user_identifiers.user_id'];
        $this->assert($phone['count'] === 1 && $phone['duplicates'] === 0, 'The source phone was not counted as moving.');
        $this->assert(!isset($rows['exam_assessment_versions.created_by_user_id']), 'History columns must not appear as work to do.');
        $this->assert((int) $this->database->query('SELECT COUNT(*) FROM tenant_workspace_memberships')->fetchColumn() === $before, 'The planner wrote something.');

        $this->expectCode('account_merge_same_account', fn () => (new AccountMergePlanner($this->database))->inventory($target, $target));

        return $this->assertions;
    }

    private function user(string $name): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $name]);

        return $id;
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
