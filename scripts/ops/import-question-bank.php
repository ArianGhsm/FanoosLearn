<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\QuestionBankRow;
use Fanoos\Platform\Support\DatabaseConnection;

/**
 * Imports a question bank export into one workspace as a published assessment.
 *
 * Reads a JSON array in the shape the owner's exam tooling exports, keeps
 * only the rows that can honestly be scored, and writes them through
 * ExamService so every normal rule applies: authorization, the draft ->
 * review -> publish workflow, the field limits and the audit trail. Nothing
 * here writes to exam tables directly.
 *
 * Usage:
 *   php scripts/ops/import-question-bank.php \
 *       --file=<export.json> --workspace=<uuid> --actor=<uuid> --reviewer=<uuid> \
 *       --subject=<subject> --chapter=<chapter> --title=<title> \
 *       [--limit=N] [--kind=practice|mock_exam|past_exam] [--max-attempts=N]
 *       [--course=<uuid>] [--dry-run]
 *
 * `--reviewer` must be a different person from `--actor`: ExamService
 * refuses to let an assessment's creator approve their own work, and that
 * separation is worth honouring for an import too -- it is the only check
 * standing between a bad bank and a published one.
 *
 * A row is skipped, and counted, when it cannot be scored or rendered --
 * see Fanoos\Platform\Content\QuestionBankRow, which holds those rules for
 * this importer and the bulk one alike. Importing an unscorable question
 * would mark a student wrong for a question that has no right answer.
 */

// A real bank export is tens of megabytes of JSON and decodes to several
// times that as PHP arrays. This is an operator-run, one-shot import, not a
// request path, so raising the ceiling here is the honest fix -- failing
// half way through a 20,000-question file is not.
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
    $subject = (string) argument('subject', '');
    $chapter = (string) argument('chapter', '');
    $title = (string) argument('title', '');
    $kind = (string) argument('kind', 'past_exam');
    $limit = (int) argument('limit', '0');
    $maxAttempts = (int) argument('max-attempts', '5');
    $courseId = argument('course');
    $dryRun = argument('dry-run') !== null;

    foreach (['file' => $file, 'workspace' => $workspaceId, 'actor' => $actorId, 'reviewer' => $reviewerId, 'title' => $title] as $name => $value) {
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

    $skipped = ['subject' => 0];
    $questions = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $context = is_array($row['exam_context'] ?? null) ? $row['exam_context'] : [];
        if ($subject !== '' && (string) ($context['subject'] ?? '') !== $subject) {
            $skipped['subject']++;
            continue;
        }
        if ($chapter !== '') {
            $titles = array_map(
                static fn (array $entry): string => (string) ($entry['title'] ?? ''),
                array_filter((array) ($row['chapter_assignments'] ?? []), 'is_array'),
            );
            if (!in_array($chapter, $titles, true)) {
                $skipped['subject']++;
                continue;
            }
        }
        $mapped = QuestionBankRow::map($row);
        if (isset($mapped['skip'])) {
            $skipped[$mapped['skip']] = ($skipped[$mapped['skip']] ?? 0) + 1;
            continue;
        }
        $question = $mapped['question'];
        if ($chapter !== '') {
            // Filtering by chapter means every question here is that chapter,
            // whatever its first assignment happens to be.
            $question['topic'] = $chapter;
        }
        $questions[] = $question;

        if ($limit > 0 && count($questions) >= $limit) {
            break;
        }
    }

    if ($questions === []) {
        throw new RuntimeException('No importable questions matched the given filters.');
    }

    echo 'importable: ' . count($questions) . PHP_EOL;
    foreach ($skipped as $reason => $count) {
        if ($count > 0) {
            echo "  skipped ({$reason}): {$count}" . PHP_EOL;
        }
    }
    if ($dryRun) {
        echo 'dry run -- nothing written' . PHP_EOL;
        exit(0);
    }

    $database = DatabaseConnection::fromEnvironment();
    $audit = new AuditLogger($database);
    $authorizer = new ScopeAuthorizer($database);
    $access = new AccessGate($database, $authorizer);
    $exams = new ExamService(
        $database,
        $access,
        $authorizer,
        new EntitlementService($database, $access, $audit),
        $audit,
        new ExamQuestionRateGuard($database),
    );

    // createAssessment writes version 1 from the definition it is given, so
    // there is no separate addVersion step for a first import.
    $created = $exams->createAssessment(
        $actorId,
        $workspaceId,
        $title,
        ['questions' => $questions],
        ['assessment_kind' => $kind, 'course_id' => $courseId],
    );
    $assessmentId = (string) $created['assessment_id'];
    $versionId = (string) $created['version_id'];
    $exams->submitForReview($actorId, $workspaceId, $assessmentId, $versionId);
    $exams->reviewVersion($reviewerId, $workspaceId, $assessmentId, $versionId, 'approved', 'imported question bank');
    $exams->publishVersion($reviewerId, $workspaceId, $assessmentId, $versionId);

    $policy = $database->prepare(<<<'SQL'
UPDATE exam_access_policies SET max_attempts = :attempts, updated_at = UTC_TIMESTAMP(6)
WHERE workspace_id = :workspace AND assessment_id = :assessment
SQL);
    $policy->execute(['attempts' => max(1, min(100, $maxAttempts)), 'workspace' => $workspaceId, 'assessment' => $assessmentId]);

    echo 'assessment_id: ' . $assessmentId . PHP_EOL;
    echo 'published with ' . count($questions) . ' questions' . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
