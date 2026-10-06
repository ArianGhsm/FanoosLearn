<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Engagement\PointsService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * امتیاز روزانه، رتبه و سکه: points earned at scoring, once per question a
 * day, by difficulty; the daily goal's coin; and the student's own place.
 */
final class EngagementTest
{
    private int $assertions = 0;
    /** @var array<string, string> */
    private array $f = [];

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->f = $this->fixture(substr(str_replace('-', '', Uuid::v7()), -10));
        $f = $this->f;
        $exams = $this->exams();
        $points = new PointsService($this->database, $this->access());

        $plain = $this->publish('ساده', [
            ['id' => 'e-q1', 'prompt' => 'یک؟', 'choices' => ['درست', 'غلط'], 'answer' => 0],
            ['id' => 'e-q2', 'prompt' => 'دو؟', 'choices' => ['غلط', 'درست'], 'answer' => 1],
            ['id' => 'e-q3', 'prompt' => 'سه؟', 'choices' => ['درست', 'غلط'], 'answer' => 0, 'difficulty' => 'hard'],
        ]);

        // Two right (unknown difficulty, 10 each) and one hard right (20); a wrong answer earns nothing.
        $first = $this->sit($f['s1'], $plain, ['e-q1' => 0, 'e-q2' => 1, 'e-q3' => 0]);
        $this->assert($first['points_earned'] === 40, 'Points for 10 + 10 + 20 were not awarded: ' . json_encode($first));
        $again = $this->sit($f['s1'], $plain, ['e-q1' => 0, 'e-q2' => 1, 'e-q3' => 0]);
        $this->assert($again['points_earned'] === 0, 'The same questions earned points twice in a day.');
        $second = $this->sit($f['s2'], $plain, ['e-q1' => 0, 'e-q2' => 0]);
        $this->assert($second['points_earned'] === 10, 'A wrong answer earned points: ' . json_encode($second));

        // Places: s1 first of two, s2 second, s3 none.
        $one = $points->summary($f['s1'], $f['workspace']);
        $this->assert($one['today']['points'] === 40 && $one['today']['rank'] === 1 && $one['today']['active'] === 2, 'Today\'s first place is wrong: ' . json_encode($one['today']));
        $this->assert($one['week']['rank'] === 1 && $one['month']['rank'] === 1 && $one['week']['points'] === 40, 'The week and month do not include today.');
        $two = $points->summary($f['s2'], $f['workspace']);
        $this->assert($two['today']['rank'] === 2 && $two['today']['active'] === 2, 'Second place is wrong: ' . json_encode($two['today']));
        $none = $points->summary($f['s3'], $f['workspace']);
        $this->assert($none['today']['rank'] === null && $none['today']['active'] === 2 && $none['today']['points'] === 0, 'Someone without points was given a place.');
        $this->assert(count($none['days']) === 7 && count($none['weeks']) === 7 && count($none['months']) === 7, 'The recent series are not seven long.');
        $this->assert(end($one['days'])['points'] === 40, 'Today is not the last day of the series.');

        // The daily goal: 25 hard questions right is 500 points and one coin, once.
        $hard = [];
        for ($i = 1; $i <= 25; $i++) {
            $hard[] = ['id' => "e-h{$i}", 'prompt' => "سخت {$i}؟", 'choices' => ['درست', 'غلط'], 'answer' => 0, 'difficulty' => 'hard'];
        }
        $goalExam = $this->publish('هدف', $hard);
        $answers = array_fill_keys(array_map(static fn (array $q): string => $q['id'], $hard), 0);
        $goal = $this->sit($f['s3'], $goalExam, $answers);
        $this->assert($goal['points_earned'] === 500 && $goal['daily_goal_reached'] === true, 'Reaching the goal was not reported: ' . json_encode($goal));
        $this->assert($points->coins($f['workspace'], $f['s3']) === 1, 'Reaching the goal did not earn one coin.');
        $more = $this->sit($f['s3'], $plain, ['e-q1' => 0]);
        $this->assert($more['daily_goal_reached'] === false && $points->coins($f['workspace'], $f['s3']) === 1, 'The goal paid a second coin the same day.');
        $three = $points->summary($f['s3'], $f['workspace']);
        $this->assert($three['today']['rank'] === 1 && $three['goal_days'] === 1 && $three['coins'] === 1, 'The goal day is not on the summary: ' . json_encode($three['today']));

        // Another workspace's students are not in this ranking.
        $other = $this->fixture(substr(str_replace('-', '', Uuid::v7()), -10));
        $this->assert($points->summary($other['s1'], $other['workspace'])['today']['active'] === 0, 'A ranking counted another workspace.');

        return $this->assertions;
    }

    /** @param array<string, int> $answers @return array<string, mixed> */
    private function sit(string $user, string $assessment, array $answers): array
    {
        $exams = $this->exams();
        $attempt = $exams->startAttempt($user, $this->f['workspace'], $assessment);

        return $exams->submitAttempt($user, $this->f['workspace'], $attempt['attempt_id'], (int) $attempt['revision'], $answers);
    }

    /** @param list<array<string, mixed>> $questions */
    private function publish(string $title, array $questions): string
    {
        $f = $this->f;
        $exams = $this->exams();
        $created = $exams->createAssessment($f['manager'], $f['workspace'], 'امتیاز ' . $title, ['questions' => $questions], ['max_attempts' => 10]);
        $exams->submitForReview($f['manager'], $f['workspace'], $created['assessment_id'], $created['version_id']);
        $exams->reviewVersion($f['reviewer'], $f['workspace'], $created['assessment_id'], $created['version_id'], 'approved');
        $exams->publishVersion($f['manager'], $f['workspace'], $created['assessment_id'], $created['version_id']);

        return $created['assessment_id'];
    }

    /** @return array<string, string> */
    private function fixture(string $suffix): array
    {
        $owner = $this->user('Points Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);
        $workspace = (new ClassProvisioningService($this->database, $this->access(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Points Province ' . $suffix],
            'city' => ['name' => 'Points City ' . $suffix],
            'institution' => ['name' => 'Points University ' . $suffix],
            'faculty' => ['name' => 'Points Faculty ' . $suffix],
            'program' => ['name' => 'Points Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5906, 'label' => 'Points Cohort ' . $suffix],
            'workspace' => ['name' => 'Points Class ' . $suffix],
        ])['workspace_id'];

        $fixture = ['workspace' => $workspace];
        $fixture['manager'] = $this->member($workspace, 'content-manager');
        $fixture['reviewer'] = $this->member($workspace, 'content-reviewer');
        foreach (['s1', 's2', 's3'] as $student) {
            $fixture[$student] = $this->member($workspace, 'student');
        }

        return $fixture;
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Points ' . $roleKey);
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

    private function exams(): ExamService
    {
        return new ExamService($this->database, $this->access(), new ScopeAuthorizer($this->database), new EntitlementService($this->database, $this->access(), new AuditLogger($this->database)), new AuditLogger($this->database), new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
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
