<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * scripts/ops/purge-workspace.php: a dry run deletes nothing; an execution
 * removes the workspace with its exams, scopes, role assignments and
 * memberships, and leaves a neighbouring workspace exactly as it was.
 */
final class PurgeWorkspaceTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database, private readonly string $root)
    {
    }

    public function run(): int
    {
        $doomed = $this->workspaceWithExam('doomed');
        $kept = $this->workspaceWithExam('kept');
        $before = $this->footprint($kept['workspace']);

        $dry = $this->purge($doomed['workspace'], false);
        $this->assert($dry['code'] === 0 && str_contains($dry['output'], 'Dry run'), 'The dry run failed: ' . $dry['output']);
        $this->assert($this->footprint($doomed['workspace'])['assessments'] === 1, 'A dry run deleted something.');

        $mismatch = $this->run_php(['--workspace=' . $doomed['workspace'], '--confirm=' . $kept['workspace'], '--execute']);
        $this->assert($mismatch['code'] !== 0, 'A mismatched confirmation was accepted.');

        $done = $this->purge($doomed['workspace'], true);
        $this->assert($done['code'] === 0, 'The purge failed: ' . $done['output']);
        $gone = $this->footprint($doomed['workspace']);
        $this->assert($gone === ['workspace' => 0, 'assessments' => 0, 'scopes' => 0, 'memberships' => 0, 'assignments' => 0], 'The workspace was not fully removed: ' . json_encode($gone));
        $this->assert($this->footprint($kept['workspace']) === $before, 'The purge touched another workspace.');
        $selected = $this->database->prepare('SELECT selected_workspace_id FROM iam_sessions WHERE id = :id');
        $selected->execute(['id' => $doomed['session']]);
        $this->assert($selected->fetchColumn() === null, 'A session still points at the purged workspace.');
        $selected->execute(['id' => $kept['session']]);
        $this->assert($selected->fetchColumn() === $kept['workspace'], 'A session in another workspace lost its selection.');

        return $this->assertions;
    }

    /** @return array{code:int,output:string} */
    private function purge(string $workspace, bool $execute): array
    {
        return $this->run_php(array_merge(['--workspace=' . $workspace, '--confirm=' . $workspace], $execute ? ['--execute'] : []));
    }

    /** @param list<string> $arguments @return array{code:int,output:string} */
    private function run_php(array $arguments): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/scripts/ops/purge-workspace.php');
        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }
        exec($command . ' 2>&1', $lines, $code);

        return ['code' => $code, 'output' => implode("\n", $lines)];
    }

    /** @return array<string, int> */
    private function footprint(string $workspace): array
    {
        $count = function (string $sql) use ($workspace): int {
            $query = $this->database->prepare($sql);
            $query->execute(['workspace' => $workspace]);

            return (int) $query->fetchColumn();
        };

        return [
            'workspace' => $count('SELECT COUNT(*) FROM tenant_workspaces WHERE id = :workspace'),
            'assessments' => $count('SELECT COUNT(*) FROM exam_assessments WHERE workspace_id = :workspace'),
            'scopes' => $count('SELECT COUNT(*) FROM rbac_scopes WHERE workspace_id = :workspace'),
            'memberships' => $count('SELECT COUNT(*) FROM tenant_workspace_memberships WHERE workspace_id = :workspace'),
            'assignments' => $count('SELECT COUNT(*) FROM rbac_role_assignments WHERE scope_id IN (SELECT id FROM rbac_scopes WHERE workspace_id = :workspace)'),
        ];
    }

    /** @return array{workspace:string,session:string} */
    private function workspaceWithExam(string $label): array
    {
        $suffix = $label . substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->user('Purge Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $workspace = (new ClassProvisioningService($this->database, $access, new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Purge Province ' . $suffix],
            'city' => ['name' => 'Purge City ' . $suffix],
            'institution' => ['name' => 'Purge University ' . $suffix],
            'faculty' => ['name' => 'Purge Faculty ' . $suffix],
            'program' => ['name' => 'Purge Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5906, 'label' => 'Purge Cohort ' . $suffix],
            'workspace' => ['name' => 'Purge Class ' . $suffix],
        ])['workspace_id'];

        $manager = $this->member($workspace, 'content-manager');
        $reviewer = $this->member($workspace, 'content-reviewer');
        $student = $this->member($workspace, 'student');
        $audit = new AuditLogger($this->database);
        $exams = new ExamService($this->database, $access, new ScopeAuthorizer($this->database), new EntitlementService($this->database, $access, $audit), $audit, new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
        $created = $exams->createAssessment($manager, $workspace, 'Purge ' . $label, ['questions' => [
            ['id' => 'q1', 'prompt' => 'یک؟', 'choices' => ['الف', 'ب'], 'answer' => 0],
        ]]);
        $exams->submitForReview($manager, $workspace, $created['assessment_id'], $created['version_id']);
        $exams->reviewVersion($reviewer, $workspace, $created['assessment_id'], $created['version_id'], 'approved');
        $exams->publishVersion($manager, $workspace, $created['assessment_id'], $created['version_id']);
        $attempt = $exams->startAttempt($student, $workspace, $created['assessment_id']);
        $exams->submitAttempt($student, $workspace, $attempt['attempt_id'], 1, ['q1' => 0]);

        // A signed-in student with this workspace selected: the purge must
        // clear the selection, not fail on it and not sign them out.
        $session = Uuid::v7();
        $this->database->prepare('INSERT INTO iam_sessions (id, user_id, selected_workspace_id, token_digest, client_json, created_at, last_seen_at, expires_at) VALUES (:id, :user, :workspace, :token, JSON_OBJECT(), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR))')
            ->execute(['id' => $session, 'user' => $student, 'workspace' => $workspace, 'token' => random_bytes(32)]);

        return ['workspace' => $workspace, 'session' => $session];
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Purge ' . $roleKey);
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

    private function user(string $name): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $name]);

        return $id;
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
