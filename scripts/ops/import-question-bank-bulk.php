<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\QuestionBankRow;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\Uuid;

/**
 * Imports a whole question-bank export as one published assessment per past
 * exam, grouped into one course per subject.
 *
 * import-question-bank.php builds a single assessment from a subject/chapter
 * slice, which is right for a hand-picked practice set and wrong for a bank of
 * several hundred past exams: the bank's natural unit is the exam a cohort
 * actually sat (ارتوپدی · ورودی ۱۳۹۵ · گروه A), and a student browses by
 * course first. This keeps that shape.
 *
 * Row rules come from QuestionBankRow, shared with the single importer --
 * including skipping any question that needs an image, which FANOOS cannot
 * show yet. Assessments are written through ExamService (authorization,
 * review -> publish, field limits, audit); only the course rows are written
 * directly, because nothing in the application creates courses yet and this
 * is an operator provisioning step.
 *
 * Re-running is safe: a course is matched by its stable code, and an exam
 * whose title already exists in that course is left alone.
 *
 * Usage:
 *   php scripts/ops/import-question-bank-bulk.php \
 *       --file=<export.json> --workspace=<uuid> --actor=<uuid> --reviewer=<uuid> \
 *       --stage=<label, e.g. استاجری> [--kind=past_exam] [--max-attempts=5] [--dry-run]
 */

ini_set('memory_limit', '1G');

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

function argument(string $name, ?string $default = null): ?string
{
    foreach ($GLOBALS['argv'] as $argument) {
        if (str_starts_with($argument, "--{$name}=")) {
            return substr($argument, strlen($name) + 3);
        }
        if ($argument === "--{$name}") {
            return '1';
        }
    }

    return $default;
}

try {
    $file = (string) argument('file', '');
    $workspaceId = (string) argument('workspace', '');
    $actorId = (string) argument('actor', '');
    $reviewerId = (string) argument('reviewer', '');
    $stage = trim((string) argument('stage', ''));
    $kind = (string) argument('kind', 'past_exam');
    $maxAttempts = max(1, min(100, (int) argument('max-attempts', '5')));
    $dryRun = argument('dry-run') !== null;

    foreach (['file' => $file, 'workspace' => $workspaceId, 'actor' => $actorId, 'reviewer' => $reviewerId, 'stage' => $stage] as $name => $value) {
        if ($value === '') {
            throw new RuntimeException("--{$name} is required.");
        }
    }
    if ($reviewerId === $actorId) {
        throw new RuntimeException('--reviewer must be a different account from --actor: a creator cannot approve their own assessment.');
    }
    if (!is_file($file)) {
        throw new RuntimeException("Export file not found: {$file}");
    }

    $rows = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($rows)) {
        throw new RuntimeException('Export must be a JSON array of questions.');
    }

    // Group by the exam each row came from. Keyed by exam_id, never by
    // title: two exams can share a subject, year and group, and a title
    // collision must not merge their questions.
    $exams = [];
    $skipped = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $mapped = QuestionBankRow::map($row);
        if (isset($mapped['skip'])) {
            $skipped[$mapped['skip']] = ($skipped[$mapped['skip']] ?? 0) + 1;
            continue;
        }
        $examId = (string) ($row['exam_id'] ?? '');
        $context = is_array($row['exam_context'] ?? null) ? $row['exam_context'] : [];
        $exams[$examId] ??= ['context' => $context, 'questions' => []];
        $exams[$examId]['questions'][] = ['n' => (int) ($row['question_number'] ?? 0), 'q' => $mapped['question']];
    }
    unset($rows);

    // A stable order, so a re-run walks the same sequence and the log reads
    // subject by subject.
    uasort($exams, static function (array $a, array $b): int {
        return [QuestionBankRow::examTitle($a['context'])] <=> [QuestionBankRow::examTitle($b['context'])];
    });

    $questionTotal = array_sum(array_map(static fn (array $exam): int => count($exam['questions']), $exams));
    echo "stage: {$stage}\n";
    echo 'exams: ' . count($exams) . "  questions: {$questionTotal}\n";
    foreach ($skipped as $reason => $count) {
        echo "  skipped ({$reason}): {$count}\n";
    }
    if ($dryRun) {
        echo "dry run -- nothing written\n";
        exit(0);
    }

    $database = DatabaseConnection::fromEnvironment();
    $audit = new AuditLogger($database);
    $authorizer = new ScopeAuthorizer($database);
    $access = new AccessGate($database, $authorizer);
    $service = new ExamService(
        $database,
        $access,
        $authorizer,
        new EntitlementService($database, $access, $audit),
        $audit,
        new ExamQuestionRateGuard($database),
    );

    $courseIds = [];
    $ensureCourse = static function (string $subject) use ($database, $workspaceId, $stage, &$courseIds): string {
        $code = QuestionBankRow::courseCode($stage, $subject);
        if (isset($courseIds[$code])) {
            return $courseIds[$code];
        }
        $title = mb_substr($subject . ' — ' . $stage, 0, 200);
        $database->prepare(<<<'SQL'
INSERT INTO academic_courses (id, workspace_id, course_code, title, status, version, created_at, updated_at)
VALUES (:id, :workspace, :code, :title, 'active', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE title = VALUES(title), updated_at = UTC_TIMESTAMP(6)
SQL)->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'code' => $code, 'title' => $title]);
        $lookup = $database->prepare('SELECT id FROM academic_courses WHERE workspace_id = :workspace AND course_code = :code');
        $lookup->execute(['workspace' => $workspaceId, 'code' => $code]);

        return $courseIds[$code] = (string) $lookup->fetchColumn();
    };

    $existing = $database->prepare(<<<'SQL'
SELECT 1 FROM exam_assessments assessment
JOIN exam_assessment_metadata metadata
  ON metadata.assessment_id = assessment.id AND metadata.workspace_id = assessment.workspace_id
WHERE assessment.workspace_id = :workspace AND metadata.course_id = :course AND assessment.title = :title
LIMIT 1
SQL);
    $policy = $database->prepare(<<<'SQL'
UPDATE exam_access_policies SET max_attempts = :attempts, updated_at = UTC_TIMESTAMP(6)
WHERE workspace_id = :workspace AND assessment_id = :assessment
SQL);

    $created = 0;
    $alreadyThere = 0;
    $failed = 0;
    $titlesThisRun = [];
    foreach ($exams as $examId => $exam) {
        $subject = trim((string) ($exam['context']['subject'] ?? '')) ?: 'بدون درس';
        $courseId = $ensureCourse($subject);

        // Two exams with the same subject, year and group get a suffix rather
        // than one silently standing in for the other.
        $title = QuestionBankRow::examTitle($exam['context']);
        $key = $courseId . '|' . $title;
        $titlesThisRun[$key] = ($titlesThisRun[$key] ?? 0) + 1;
        if ($titlesThisRun[$key] > 1) {
            $title = mb_substr($title . ' (' . $titlesThisRun[$key] . ')', 0, 200);
        }

        $existing->execute(['workspace' => $workspaceId, 'course' => $courseId, 'title' => $title]);
        if ($existing->fetchColumn() !== false) {
            $alreadyThere++;
            continue;
        }

        usort($exam['questions'], static fn (array $a, array $b): int => $a['n'] <=> $b['n']);
        $questions = array_map(static fn (array $entry): array => $entry['q'], $exam['questions']);

        try {
            $result = $service->createAssessment($actorId, $workspaceId, $title, ['questions' => $questions], [
                'assessment_kind' => $kind,
                'course_id' => $courseId,
            ]);
            $assessmentId = (string) $result['assessment_id'];
            $versionId = (string) $result['version_id'];
            $service->submitForReview($actorId, $workspaceId, $assessmentId, $versionId);
            $service->reviewVersion($reviewerId, $workspaceId, $assessmentId, $versionId, 'approved', 'imported question bank');
            $service->publishVersion($reviewerId, $workspaceId, $assessmentId, $versionId);
            $policy->execute(['attempts' => $maxAttempts, 'workspace' => $workspaceId, 'assessment' => $assessmentId]);
            $created++;
        } catch (Throwable $error) {
            // One malformed exam must not abandon the other several hundred;
            // it is named, counted and reported, and a re-run retries it.
            $failed++;
            fwrite(STDERR, "FAILED {$title} (exam {$examId}): " . $error->getMessage() . PHP_EOL);
        }
    }

    echo "courses: " . count($courseIds) . "\n";
    echo "created: {$created}  already present: {$alreadyThere}  failed: {$failed}\n";
    exit($failed > 0 ? 2 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
