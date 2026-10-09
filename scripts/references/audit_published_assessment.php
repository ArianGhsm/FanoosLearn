<?php

declare(strict_types=1);

/**
 * Read-only regression audit for immutable published exam snapshots.
 * Preview verifies reviewed study stems/choices/keys against the currently
 * published version. After BankPublisher runs, every field except the
 * source-derived "explanation" must match the previous published version.
 */
use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Bank\BankPublisher;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Support\DatabaseConnection;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(workspace|year|subject|stem|previous|current)(?:=(.*))?$/D', $arg, $m)) {
            throw new RuntimeException('Unexpected argument.');
        }
        $options[$m[1]] = $m[2] ?? '';
    }
    $ws = (string) ($options['workspace'] ?? '');
    $year = filter_var($options['year'] ?? null, FILTER_VALIDATE_INT);
    $subject = (string) ($options['subject'] ?? '');
    $stem = (string) ($options['stem'] ?? '');
    $post = isset($options['previous']) || isset($options['current']);
    if (!preg_match('/^[0-9a-f-]{36}$/D', $ws) || $year === false
        || $year < 1399 || $year > 1500
        || !preg_match('/^[a-z][a-z0-9-]+$/D', $subject)
        || !preg_match('/^[a-z][a-z0-9-]+$/D', $stem)
        || (isset($options['previous']) !== isset($options['current']))) {
        throw new RuntimeException('Valid workspace, year, subject, stem and paired version numbers required.');
    }
    $root = realpath('/srv/fanoos/shared/research');
    if ($root === false) {
        throw new RuntimeException('Private research root absent.');
    }
    $path = "{$root}/bank-sittings/{$year}/{$stem}-study.json";
    if (!is_file($path) || is_link($path)) {
        throw new RuntimeException('Protected study input unavailable.');
    }
    $study = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    if (($study['format'] ?? '') !== 'fanoos.classification.study-only/1') {
        throw new RuntimeException('Only study-only inputs supported.');
    }
    $db = DatabaseConnection::fromEnvironment();
    $meta = $db->prepare(<<<'SQL'
SELECT a.id,a.current_version_no,s.id AS sitting_id
FROM bank_exam_sittings s
JOIN bank_exam_types t ON t.id=s.exam_type_id AND t.workspace_id=s.workspace_id
JOIN exam_assessments a ON a.id=s.assessment_id AND a.workspace_id=s.workspace_id
WHERE s.workspace_id=:ws AND t.type_key='residency' AND s.exam_year=:yr
AND s.exam_round=1 AND a.status='published'
SQL);
    $meta->execute(['ws' => $ws, 'yr' => $year]);
    $exam = $meta->fetch(PDO::FETCH_ASSOC);
    if (!is_array($exam) || $meta->fetch(PDO::FETCH_ASSOC) !== false) {
        throw new RuntimeException('Unique published residency assessment not found.');
    }
    $get = $db->prepare('SELECT definition_json FROM exam_assessment_versions WHERE assessment_id=:aid AND workspace_id=:ws AND version_no=:ver');
    $load = static function (int $v) use ($get, $ws, $exam): array {
        $get->execute(['aid' => $exam['id'], 'ws' => $ws, 'ver' => $v]);
        $found = $get->fetchColumn();
        if (!is_string($found)) {
            throw new RuntimeException('Published frozen definition not found.');
        }
        $data = json_decode($found, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_array($data['questions'] ?? null)) {
            throw new RuntimeException('Frozen assessment questions missing.');
        }
        return $data['questions'];
    };
    $currentNo = (int) $exam['current_version_no'];
    if ($post) {
        $oldNo = filter_var($options['previous'], FILTER_VALIDATE_INT);
        $newNo = filter_var($options['current'], FILTER_VALIDATE_INT);
        if ($oldNo === false || $newNo === false || $newNo !== $oldNo + 1 || $currentNo !== $newNo) {
            throw new RuntimeException('Version history is not a one-step append.');
        }
        $old = $load($oldNo);
        $new = $load($newNo);
        if (count($old) !== count($new)) {
            throw new RuntimeException('Published question count changed.');
        }
        $explained = 0;
        foreach ($old as $i => $question) {
            $prior = $question;
            $later = $new[$i];
            if (($prior['id'] ?? null) !== ($later['id'] ?? null)) {
                throw new RuntimeException('Published question order or identity changed.');
            }
            if (($prior['explanation'] ?? null) !== ($later['explanation'] ?? null)) {
                ++$explained;
            }
            unset($prior['explanation'], $later['explanation']);
            if ($prior !== $later) {
                throw new RuntimeException('Non-citation frozen assessment content changed.');
            }
        }
        echo json_encode(['mode' => 'post', 'year' => $year, 'before' => $oldNo,
            'after' => $newNo, 'question_content_identical' => true,
            'questions' => count($old), 'updated_explanations' => $explained], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    } else {
        // Entire sitting preflight: BankPublisher must be about to freeze the
        // same non-explanation data as the last published assessment version.
        // This catches unrelated historic edits outside the target subject.
        $authorization = new ScopeAuthorizer($db);
        $access = new AccessGate($db, $authorization);
        $auditLogger = new AuditLogger($db);
        $exams = new ExamService(
            $db, $access, $authorization,
            new EntitlementService($db, $access, $auditLogger),
            $auditLogger, new ExamQuestionRateGuard($db),
        );
        $proposed = (new BankPublisher($db, $exams))
            ->previewQuestionDefinitions($ws, (string) $exam['sitting_id']);
        $oldPublished = $load($currentNo);
        if (count($proposed) !== count($oldPublished)) {
            throw new RuntimeException('Publishing would change total exam question count.');
        }
        foreach ($oldPublished as $i => $oldQuestion) {
            $newQuestion = $proposed[$i];
            unset($oldQuestion['explanation'], $newQuestion['explanation']);
            if ($oldQuestion !== $newQuestion) {
                throw new RuntimeException('Publishing would change frozen exam content outside source explanations.');
            }
        }
        $frozen = [];
        foreach ($load($currentNo) as $q) {
            $frozen[(string) ($q['id'] ?? '')] = $q;
        }
        $count = 0;
        foreach ($study['questions'] as $q) {
            if (($q['subject'] ?? '') !== $subject) {
                throw new RuntimeException('Subject mismatch.');
            }
            $key = sprintf('residency-%d-1-%03d', $year, $q['number']);
            $x = $frozen[$key] ?? null;
            $options = array_map(static fn($item) => is_array($item) ? (string) ($item['text'] ?? '') : (string) $item, $q['choices']);
            $answer = $q['answer'];
            $also = array_map(static fn($i) => (int) $i - 1, $answer['also_correct'] ?? []);
            if (!is_array($x) || $x['prompt'] !== $q['stem']
                || $x['choices'] !== $options
                || ($x['answer'] ?? null) !== ($answer['choice'] === null ? null : $answer['choice'] - 1)
                || ($x['also_correct'] ?? []) !== $also) {
                throw new RuntimeException('Live published snapshot differs from reviewed study: abort republish.');
            }
            ++$count;
        }
        echo json_encode(['mode' => 'preflight', 'year' => $year,
            'subject' => $subject, 'version' => $currentNo,
            'matched_study_questions' => $count, 'full_sitting_questions' => count($oldPublished),
            'question_content_identical' => true],
            JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Assessment version audit failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
