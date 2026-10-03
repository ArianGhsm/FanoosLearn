<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Bank\BankImporter;
use Fanoos\Platform\Bank\BankImportException;
use Fanoos\Platform\Bank\BankPublisher;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * The dental bank, end to end with the real example files in
 * contracts/bank/examples: check, import, re-import, an amended answer, a
 * reviewed row an import must not touch, publishing as an exam, taking it,
 * and a voided question left out of the next version.
 */
final class BankImportTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database, private readonly string $root)
    {
    }

    public function run(): int
    {
        $f = $this->fixture();
        $ws = $f['workspace'];
        $importer = new BankImporter($this->database);
        $catalog = $this->example('catalog');
        $sitting = $this->example('sitting');

        // A sitting cannot come before the catalog it refers to.
        $early = $importer->validate($ws, $sitting);
        $this->assert($this->mentions($early, 'exam_type') && $this->mentions($early, 'questions[0].subject'), 'A sitting was accepted before its catalog: ' . json_encode($early));

        $counts = $importer->import($ws, $catalog);
        $this->assert($counts['exam_types'] === 3 && $counts['concepts'] === 4 && $counts['editions'] === 2 && $counts['nodes'] === 3, 'Catalog counts: ' . json_encode($counts));
        $before = $this->rows($ws);
        $importer->import($ws, $catalog);
        $this->assert($this->rows($ws) === $before, 'Importing the same catalog twice created rows.');
        $board = $this->database->prepare("SELECT is_active FROM bank_exam_types WHERE workspace_id = :ws AND type_key = 'board'");
        $board->execute(['ws' => $ws]);
        $this->assert((int) $board->fetchColumn() === 0, 'Board should exist but be inactive.');

        // Every problem is reported with its path, and nothing is written.
        $broken = $sitting;
        $broken['questions'][0]['answer']['choice'] = 9;
        $broken['questions'][1]['number'] = 1;
        $broken['questions'][1]['concepts'][0]['key'] = 'endodontics/no-such-concept';
        $broken['questions'][1]['sources'][0]['confidence']['node'] = 1.4;
        $problems = $importer->validate($ws, $broken);
        foreach (['questions[0].answer.choice', 'questions[1].number', 'questions[1].concepts[0].key', 'questions[1].sources[0].confidence.node'] as $path) {
            $this->assert($this->mentions($problems, $path), "No problem reported at {$path}: " . json_encode($problems));
        }
        try {
            $importer->import($ws, $broken);
            throw new RuntimeException('A broken file was imported.');
        } catch (BankImportException) {
            ++$this->assertions;
        }
        $this->assert($this->count('bank_questions', $ws) === 0, 'A refused import wrote questions.');

        // The real thing.
        $counts = $importer->import($ws, $sitting);
        $this->assert($counts['questions'] === 2 && $counts['answers_recorded'] === 2 && $counts['explanations'] === 2, 'Sitting counts: ' . json_encode($counts));
        $key = BankImporter::questionKey('residency', 1404, 1, 1);
        $this->assert($key === 'residency-1404-1-001', 'Question keys are not type-year-round-number.');
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_question_choices c JOIN bank_questions q ON q.id = c.question_id WHERE q.question_key = :key', ['key' => $key]) === 4, 'Choices were not stored.');
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_explanation_choices e JOIN bank_explanations x ON x.id = e.explanation_id JOIN bank_questions q ON q.id = x.question_id WHERE q.question_key = :key', ['key' => $key]) === 3, 'Why-wrong reasons were not stored.');

        $again = $importer->import($ws, $sitting);
        $this->assert($again['answers_recorded'] === 0 && $again['questions_changed'] === 0 && $again['explanations'] === 0, 'Re-importing an unchanged sitting changed something: ' . json_encode($again));

        // The real catalog built from the reference workbook imports cleanly.
        $real = json_decode((string) file_get_contents($this->root . '/data/bank/catalog.json'), true, 64, JSON_THROW_ON_ERROR);
        $problems = $importer->validate($ws, $real);
        $this->assert($problems === [], 'data/bank/catalog.json does not import: ' . json_encode(array_slice($problems, 0, 5), JSON_UNESCAPED_UNICODE));
        $importer->import($ws, $real);
        $endo = $this->database->prepare(<<<'SQL'
SELECT edition.edition_key, validity.scope FROM bank_reference_validity validity
JOIN bank_subjects subject ON subject.id = validity.subject_id
JOIN bank_reference_editions edition ON edition.id = validity.edition_id
JOIN bank_references reference ON reference.id = edition.reference_id
WHERE validity.workspace_id = :ws AND validity.exam_year = 1405 AND subject.subject_key = 'endodontics' AND reference.reference_key = 'torabinejad-endodontics'
SQL);
        $endo->execute(['ws' => $ws]);
        $row = $endo->fetch();
        $this->assert($row !== false && $row['edition_key'] === '6e' && $row['scope'] !== null, 'The 1405 endodontics reference is not Torabinejad 6e with its scope.');
        $this->assert($this->count('bank_reference_validity', $ws) >= 150, 'The real catalog lost its year-by-year references.');

        // A reviewed source survives a later AI pass.
        $this->database->prepare("UPDATE bank_question_sources s JOIN bank_questions q ON q.id = s.question_id SET s.reviewed_by_user_id = :user, s.reviewed_at = UTC_TIMESTAMP(6), s.page = '257' WHERE q.question_key = :key")
            ->execute(['user' => $f['reviewer'], 'key' => $key]);

        // The official key is amended: history, not overwrite.
        $amended = $sitting;
        $amended['questions'][0]['answer'] = ['choice' => 3, 'status' => 'amended', 'source' => 'اصلاحیه'];
        $amended['questions'][0]['sources'][0]['page'] = '999';
        $result = $importer->import($ws, $amended);
        $this->assert($result['answers_recorded'] === 1, 'An amended answer was not recorded.');
        $this->assert($this->scalar('SELECT COUNT(*) FROM bank_official_answers a JOIN bank_questions q ON q.id = a.question_id WHERE q.question_key = :key', ['key' => $key]) === 2, 'The answer history was overwritten.');
        $page = $this->database->prepare('SELECT s.page FROM bank_question_sources s JOIN bank_questions q ON q.id = s.question_id WHERE q.question_key = :key');
        $page->execute(['key' => $key]);
        $this->assert($page->fetchAll(PDO::FETCH_COLUMN) === ['257'], 'An import overwrote a reviewed source.');

        // Publish, take, review.
        $publisher = new BankPublisher($this->database, $this->exams());
        $published = $publisher->publish($ws, 'residency', 1404, 1, $f['manager'], $f['reviewer'], ['time_limit_minutes' => 30]);
        $this->assert($published['new_exam'] === true && $published['questions'] === 2 && $published['left_out'] === [], 'Publish: ' . json_encode($published));
        $exams = $this->exams();
        $entry = $exams->catalogEntry($f['student'], $ws, $published['assessment_id']);
        $this->assert($entry !== null && str_contains((string) $entry['title'], '۱۴۰۴') && $entry['assessment_kind'] === 'past_exam', 'The published exam is not in the catalogue as a past exam.');
        $attempt = $exams->startAttempt($f['student'], $ws, $published['assessment_id']);
        $answers = [];
        foreach ([1, 2] as $position) {
            $id = (string) $exams->readQuestion($f['student'], $ws, $attempt['attempt_id'], $position)['question']['id'];
            $answers[$id] = $id === $key ? 2 : 1; // the amended answer (choice 3) and choice 2
        }
        $scored = $exams->submitAttempt($f['student'], $ws, $attempt['attempt_id'], 1, $answers);
        $this->assert($scored['correct_count'] === 2, 'The published answers do not match the bank: ' . json_encode($scored));
        $review = null;
        foreach ([1, 2] as $position) {
            $entry = $exams->attemptReviewQuestion($f['student'], $ws, $attempt['attempt_id'], $position);
            if ($entry['question_id'] === $key) {
                $review = $entry;
            }
        }
        $explanation = (string) ($review['explanation'] ?? '');
        $this->assert(str_contains($explanation, 'پاسخ: گزینه‌ی ج') && str_contains($explanation, 'چرا بقیه نه') && str_contains($explanation, 'منبع'), 'The review does not carry the structured explanation: ' . $explanation);

        // A voided question leaves the next version; the exam stays the same exam.
        $voided = $amended;
        $voided['questions'][1]['answer'] = ['choice' => null, 'status' => 'voided'];
        $importer->import($ws, $voided);
        $again = $publisher->publish($ws, 'residency', 1404, 1, $f['manager'], $f['reviewer']);
        $this->assert($again['assessment_id'] === $published['assessment_id'] && $again['new_exam'] === false, 'Republishing created a second exam.');
        $this->assert($again['questions'] === 1 && $again['left_out'] === [BankImporter::questionKey('residency', 1404, 1, 2) . ': official answer voided'], 'A voided question was published: ' . json_encode($again));

        return $this->assertions;
    }

    /** @return array<string, mixed> */
    private function example(string $name): array
    {
        $file = json_decode((string) file_get_contents($this->root . "/contracts/bank/examples/{$name}.example.json"), true, 64, JSON_THROW_ON_ERROR);

        return $file;
    }

    /** @param list<string> $problems */
    private function mentions(array $problems, string $path): bool
    {
        foreach ($problems as $problem) {
            if (str_starts_with($problem, $path . ':')) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, int> */
    private function rows(string $ws): array
    {
        $out = [];
        foreach (['bank_exam_types', 'bank_subjects', 'bank_concepts', 'bank_references', 'bank_reference_editions', 'bank_reference_nodes', 'bank_node_concepts', 'bank_reference_validity', 'bank_edition_mappings'] as $table) {
            $out[$table] = $this->count($table, $ws);
        }

        return $out;
    }

    private function count(string $table, string $ws): int
    {
        return $this->scalar("SELECT COUNT(*) FROM {$table} WHERE workspace_id = :ws", ['ws' => $ws]);
    }

    /** @param array<string, string> $params */
    private function scalar(string $sql, array $params): int
    {
        $query = $this->database->prepare($sql);
        $query->execute($params);

        return (int) $query->fetchColumn();
    }

    private function exams(): ExamService
    {
        $audit = new AuditLogger($this->database);
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));

        return new ExamService($this->database, $access, new ScopeAuthorizer($this->database), new EntitlementService($this->database, $access, $audit), $audit, new ExamQuestionRateGuard($this->database, 1000.0, 1.0));
    }

    /** @return array<string, string> */
    private function fixture(): array
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->user('Bank Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));
        $workspace = (new ClassProvisioningService($this->database, $access, new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Bank Province ' . $suffix],
            'city' => ['name' => 'Bank City ' . $suffix],
            'institution' => ['name' => 'Bank University ' . $suffix],
            'faculty' => ['name' => 'Bank Faculty ' . $suffix],
            'program' => ['name' => 'Bank Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5907, 'label' => 'Bank Cohort ' . $suffix],
            'workspace' => ['name' => 'Dental Library ' . $suffix],
        ])['workspace_id'];

        return [
            'workspace' => $workspace,
            'manager' => $this->member($workspace, 'content-manager'),
            'reviewer' => $this->member($workspace, 'content-reviewer'),
            'student' => $this->member($workspace, 'student'),
        ];
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Bank ' . $roleKey);
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
