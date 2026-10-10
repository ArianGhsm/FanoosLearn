<?php

declare(strict_types=1);

/**
 * Narrow, existing-record-only, 5-question Neville 5e printed-page repair.
 * A reviewed citation correction is NOT a new bank import.
 *
 * Preview: php scripts/references/correct_existing_pathology_pages.php
 * Apply: --apply --backup=/var/backups/fanoos/<verified> --receipt=<new protected JSON>
 *
 * Never changes a question, answer, option, provenance, chapter, reference,
 * existing anchor, human-reviewed source, or historical assessment.
 */
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use PDO;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

$base = '/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010';
$sourceSnapshot = $base . '/live-existing-sources.private.json';
$keySnapshot = $base . '/original-official-key-review.json';
$textPath = $base . '/references/neville-oral-pathology@5e.txt';

$expectedHashes = [
    $sourceSnapshot => '9d6b8efd99cb942e0f2b6488dcef0b2fd6e7ae2f01fb05534eca33efa435ac4b',
    $keySnapshot => 'a2b78e2b3d2c0c9434c14adb8e438037e999d73b1b39787e2888fbbb615c7037',
    $textPath => '348dafa50b9f53648dc5f7cad97547459ba70a227680ab921c5a04b1b63b6c40',
];
$targets = [
    '1404:46' => ['chapter' => '10', 'old' => '10', 'new' => '367', 'pdf' => 377,
        'proof' => 'sudden appearance of numerous seborrheic keratoses'],
    '1405:47' => ['chapter' => '10', 'old' => '20', 'new' => '434', 'pdf' => 444,
        'proof' => 'usually shows a “perinuclear dot” pattern'],
    '1405:51' => ['chapter' => '12', 'old' => '12', 'new' => '533', 'pdf' => 543,
        'proof' => 'axons within the tumor (a feature not seen in schwannoma)'],
    '1405:54' => ['chapter' => '14', 'old' => '14', 'new' => '639', 'pdf' => 649,
        'proof' => 'blood-filled spaces lack an endothelial or epithelial lining'],
    '1405:60' => ['chapter' => '16', 'old' => '3', 'new' => '770', 'pdf' => 780,
        'proof' => 'developed autoantibodies directed against desmoglein 3'],
];
$db = null;
$receiptHandle = false;
$receiptName = null;
$lockHeld = false;
$committed = false;
try {
    $opts = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!preg_match('/^--(apply|backup|receipt)(?:=(.*))?$/D', $argument, $part)
            || isset($opts[$part[1]])) {
            throw new RuntimeException('Unknown or repeated option.');
        }
        $opts[$part[1]] = $part[2] ?? '1';
    }
    $apply = isset($opts['apply']);
    if ($apply !== (isset($opts['backup']) && isset($opts['receipt']))) {
        throw new RuntimeException('Apply requires both fresh verified backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Only the updater service account can apply.');
    }
    foreach ($expectedHashes as $path => $sha) {
        if (is_link($path) || !is_file($path) || hash_file('sha256', $path) !== $sha) {
            throw new RuntimeException('Original immutable evidence hash mismatch.');
        }
    }
    $snapshot = json_decode((string) file_get_contents($sourceSnapshot), true, 64, JSON_THROW_ON_ERROR);
    $keyData = json_decode((string) file_get_contents($keySnapshot), true, 64, JSON_THROW_ON_ERROR);
    $storedSources = [];
    foreach ($snapshot['rows'] ?? [] as $source) {
        $id = $source['year'] . ':' . $source['number'];
        if (isset($targets[$id])) {
            if (isset($storedSources[$id])) {
                throw new RuntimeException('Duplicate original-source snapshot.');
            }
            $storedSources[$id] = $source;
        }
    }
    $originals = [];
    foreach ($keyData['questions'] ?? [] as $question) {
        $id = $question['year'] . ':' . $question['number'];
        if (isset($targets[$id])) {
            if (isset($originals[$id])) {
                throw new RuntimeException('Duplicate original answer snapshot.');
            }
            $originals[$id] = $question;
        }
    }
    if (count($storedSources) !== count($targets) || count($originals) !== count($targets)) {
        throw new RuntimeException('Five historical source+question snapshots required.');
    }
    $fullText = (string) file_get_contents($textPath);
    foreach ($targets as $id => $target) {
        $p = $target['pdf'];
        if (!preg_match('/^=== PAGE ' . $p . ' ===\s*$(.*?)(?=^=== PAGE \d+ ===|\z)/ms',
            $fullText, $hit)) {
            throw new RuntimeException('Original PDF page marker missing: ' . $id);
        }
        $normal = static fn(string $s): string => preg_replace('/\s+/u', ' ', mb_strtolower($s));
        if (!str_contains($normal($hit[1]), $normal($target['proof']))) {
            throw new RuntimeException('Clinical answer-defining original passage absent: ' . $id);
        }
    }
    unset($fullText);

    if ($apply) {
        $backup = realpath((string) $opts['backup']);
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Backup path outside verified FANOOS backup root.');
        }
        $manifest = BackupManifest::verify($backup);
        $date = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($date === false || abs(time() - $date) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh complete verified backup required.');
        }
        $receiptName = (string) $opts['receipt'];
        $directory = realpath(dirname($receiptName));
        if ($directory !== realpath($base) || is_link($receiptName)
            || file_exists($receiptName) || !str_ends_with($receiptName, '.json')) {
            throw new RuntimeException('Receipt must be new and in protected pathology research.');
        }
        $receiptHandle = @fopen($receiptName, 'x');
        if ($receiptHandle === false) {
            throw new RuntimeException('Could not reserve unique receipt path.');
        }
        chmod($receiptName, 0600);
    }

    $db = DatabaseConnection::fromEnvironment();
    $lock = $db->query("SELECT GET_LOCK('fanoos:existing-pathology-source-pages',0)")->fetchColumn();
    if ((int) $lock !== 1) {
        throw new RuntimeException('Another pathname publisher has the advisory lock.');
    }
    $lockHeld = true;
    $db->beginTransaction();
    $find = $db->prepare(<<<'SQL'
SELECT q.id question_id,q.stem,q.stem_image,q.status question_status,
       qs.id source_id,qs.page,qs.anchor_text,qs.origin,qs.reviewed_at,
       qs.reviewed_by_user_id,qs.is_primary,qs.edition_id,qs.node_id,
       r.reference_key,e.edition_key,n.node_key,n.kind,v.is_official,v.scope_chapters
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_question_sources qs ON qs.question_id=q.id AND qs.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=qs.edition_id AND e.workspace_id=q.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=q.workspace_id
JOIN bank_reference_nodes n ON n.id=qs.node_id AND n.workspace_id=q.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=q.workspace_id
 AND v.subject_id=sb.id AND v.exam_type_id=et.id AND v.exam_year=si.exam_year
WHERE et.type_key='residency' AND si.exam_year=:year AND si.exam_round=1
AND sb.subject_key='oral-pathology' AND q.number_in_sitting=:number
FOR UPDATE
SQL);
    $choices = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE question_id=:qid ORDER BY position');
    $answer = $db->prepare('SELECT choice_position,status FROM bank_official_answers WHERE question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET page=:new_page
WHERE id=:id AND question_id=:question_id AND page=:old_page
AND origin='ai' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
SQL);
    $result = [];
    foreach ($targets as $id => $target) {
        [$year, $number] = array_map('intval', explode(':', $id));
        $find->execute(['year' => $year, 'number' => $number]);
        $rows = $find->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new RuntimeException('Expected one exact source and one official validity: ' . $id);
        }
        $real = $rows[0];
        $source = $storedSources[$id];
        $question = $originals[$id];
        if ($real['question_id'] !== $source['question_id']
            || $real['source_id'] !== $source['source_id']
            || $real['page'] !== $target['old']
            || $real['page'] !== $source['page']
            || $real['anchor_text'] !== $source['anchor_text']
            || $real['origin'] !== 'ai'
            || $real['reviewed_at'] !== null
            || $real['reviewed_by_user_id'] !== null
            || (int) $real['is_primary'] !== 1
            || $real['reference_key'] !== 'neville-oral-pathology'
            || $real['edition_key'] !== '5e'
            || $real['node_key'] !== 'ch' . str_pad($target['chapter'], 2, '0', STR_PAD_LEFT)
            || $real['kind'] !== 'chapter'
            || (int) $real['is_official'] !== 1
            || $real['question_status'] !== 'published'
            || $real['stem'] !== $question['stem']
            || $real['stem_image'] !== $question['stem_image']) {
            throw new RuntimeException('Protected identity/edition/chapter/review/question mismatch: ' . $id);
        }
        $scope = json_decode((string) $real['scope_chapters'], true, 64, JSON_THROW_ON_ERROR);
        if (!in_array($target['chapter'], array_map(static fn($n) => (string) ($n['number'] ?? ''), $scope), true)) {
            throw new RuntimeException('Chapter is outside official-year scope: ' . $id);
        }
        $choices->execute(['qid' => $real['question_id']]);
        $gotChoices = array_map(static fn(array $c): array => ['position' => (int) $c['position'], 'text' => (string) $c['text'], 'image' => $c['image'] === null ? null : (string) $c['image']], $choices->fetchAll(PDO::FETCH_ASSOC));
        if ($gotChoices !== $question['choices']) {
            throw new RuntimeException('Original choices or images changed: ' . $id);
        }
        $answer->execute(['qid' => $real['question_id']]);
        $gotAnswer = $answer->fetch(PDO::FETCH_ASSOC);
        if (!is_array($gotAnswer) || $gotAnswer['status'] !== ($question['answer']['status'] ?? null)
            || (int) $gotAnswer['choice_position'] !== (int) ($question['answer']['choice_position'] ?? 0)
            || !in_array($gotAnswer['status'], ['final', 'amended'], true)) {
            throw new RuntimeException('Official answer changed: ' . $id);
        }
        $result[] = ['year' => $year, 'number' => $number, 'source_id' => $real['source_id'],
            'chapter' => $target['chapter'], 'old_page' => $target['old'],
            'printed_page' => $target['new'], 'pdf_page' => $target['pdf']];
        if ($apply) {
            $update->execute(['new_page' => $target['new'], 'id' => $real['source_id'],
                'question_id' => $real['question_id'], 'old_page' => $target['old']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Atomic compare-and-swap update failed: ' . $id);
            }
        }
    }
    $receipt = [
        'format' => 'fanoos.existing-neville5-page-corrections/1',
        'applied' => $apply, 'count' => count($result), 'source_only_page_field' => true,
        'question_option_answer_review_mutations' => 0, 'items' => $result,
        'source_snapshot_sha256' => $expectedHashes[$sourceSnapshot],
        'book_text_sha256' => $expectedHashes[$textPath],
        'utc' => gmdate('c'),
    ];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $db->commit();
        $committed = true;
        $write = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($receiptHandle, $write) !== strlen($write) || !fflush($receiptHandle)) {
            throw new RuntimeException('Database committed; private receipt failed: investigate before retry.');
        }
        fclose($receiptHandle);
        $receiptHandle = false;
    } else {
        $db->rollBack();
    }
    echo json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($receiptHandle !== false) {
        fclose($receiptHandle);
        if (!$committed && is_string($receiptName) && is_file($receiptName)) {
            unlink($receiptName);
        }
    }
    fwrite(STDERR, 'Page-only repair refused: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($lockHeld && $db instanceof PDO) {
        $db->query("SELECT RELEASE_LOCK('fanoos:existing-pathology-source-pages')");
    }
}
