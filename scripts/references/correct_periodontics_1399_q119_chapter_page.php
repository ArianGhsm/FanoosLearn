<?php

declare(strict_types=1);

/**
 * Change ONLY existing AI source chapter node and printed page for
 * residency 1399 periodontics Q119, after direct original Carranza 13e proof.
 * Never update anchors, historical assessments/attempts, human decisions,
 * questions, options or official answer keys.
 *
 * Preview: php scripts/references/correct_periodontics_1399_q119_chapter_page.php
 * Apply: --apply --backup=<fresh verified full-backup path>
 *         --receipt=<unique protected private JSON>
 */

use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

$base = '/srv/fanoos/shared/research/classification/reports/periodontics-13e-chapter47-correction-20261010';
$originalPath = $base . '/q119-original-live-snapshot.json';
$textPath = '/srv/fanoos/shared/research/references/carranza-periodontology@13e.txt';
$originalHash = 'e46bb494b6cd52f9e94bab4e88c5c44292146f5bc740dc5c0cb842314cd91e7c';
$textHash = 'baa5b5efd320bd286e101b7243dda7550392897bb6b2155261eeb83071d81814';
$db = null;
$locked = false;
$fh = false;
$receiptName = null;
$committed = false;

try {
    $opts = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!preg_match('/^--(apply|backup|receipt)(?:=(.*))?$/D', $argument, $m)
            || isset($opts[$m[1]])) {
            throw new RuntimeException('Unexpected or repeated option.');
        }
        $opts[$m[1]] = $m[2] ?? '1';
    }
    $apply = isset($opts['apply']);
    if ($apply !== (isset($opts['backup']) && isset($opts['receipt']))) {
        throw new RuntimeException('Apply requires a fresh full backup and new private receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Only updater service account may apply.');
    }
    foreach ([$originalPath => $originalHash, $textPath => $textHash] as $file => $digest) {
        if (!is_file($file) || is_link($file) || hash_file('sha256', $file) !== $digest) {
            throw new RuntimeException('Original private source or exact-edition book digest changed.');
        }
    }
    $snapshot = json_decode((string) file_get_contents($originalPath), true, 64, JSON_THROW_ON_ERROR);
    $saved = $snapshot['question'] ?? null;
    if (!is_array($saved) || ($saved['year'] ?? null) !== 1399 || ($saved['number'] ?? null) !== 119
        || ($saved['old_chapter'] ?? null) !== '48' || ($saved['page'] ?? 'not-null') !== null
        || ($saved['origin'] ?? null) !== 'ai' || ($saved['reviewed_at'] ?? 1) !== null
        || ($saved['reviewed_by_user_id'] ?? 1) !== null
        || ($saved['answer_position'] ?? null) !== 1
        || ($saved['answer_status'] ?? null) !== 'final') {
        throw new RuntimeException('Expected preserved historical Q119 source and official key.');
    }
    $raw = (string) file_get_contents($textPath);
    if (!preg_match('/^=== PAGE 1073 ===\h*$(.*?)(?=^=== PAGE \d+ ===|\z)/ms', $raw, $hit)) {
        throw new RuntimeException('Correct book PDF page 1073 absent.');
    }
    $page = mb_strtolower(preg_replace('/\s+/u', ' ', $hit[1]) ?? '');
    foreach (['targeted oral hygiene', 'is synonymous with the bass technique'] as $proof) {
        if (!str_contains($page, $proof)) {
            throw new RuntimeException('Correct-answer passage is absent on original 13e page.');
        }
    }
    unset($raw);
    if (!PrintedBookPageEvidence::corroborates(
        '/srv/fanoos/shared/research', 'carranza-periodontology@13e', 1073, '507'
    )) {
        throw new RuntimeException('Original printed page 507 failed neighboring page corroboration.');
    }
    if ($apply) {
        $backup = realpath((string) $opts['backup']);
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Backup is outside approved root.');
        }
        $manifest = BackupManifest::verify($backup);
        $when = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($when === false || abs(time() - $when) > 14400
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Verified full database backup less than four hours old required.');
        }
        $receiptName = (string) $opts['receipt'];
        if (realpath(dirname($receiptName)) !== realpath($base)
            || is_link($receiptName) || file_exists($receiptName)
            || !str_ends_with($receiptName, '.json')) {
            throw new RuntimeException('Unique receipt must stay in exact protected Q119 directory.');
        }
        $fh = @fopen($receiptName, 'x');
        if ($fh === false) {
            throw new RuntimeException('Cannot reserve unique protected receipt.');
        }
        chmod($receiptName, 0600);
    }
    $db = DatabaseConnection::fromEnvironment();
    if ((int) $db->query("SELECT GET_LOCK('fanoos:periodontics-existing-source-pages',0)")
        ->fetchColumn() !== 1) {
        throw new RuntimeException('A periodontics source-correction writer is active.');
    }
    $locked = true;
    $db->beginTransaction();
    $find = $db->prepare(<<<'SQL'
SELECT q.id question_id,q.stem,q.stem_image,q.status question_status,
s.id source_id,s.page,s.anchor_text,s.origin,s.reviewed_at,s.reviewed_by_user_id,
s.is_primary,s.edition_id,s.node_id,oldn.number existing_chapter,
r.reference_key,e.edition_key,v.is_official,v.scope_chapters
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_question_sources s ON s.question_id=q.id AND s.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=q.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=q.workspace_id
JOIN bank_reference_nodes oldn ON oldn.id=s.node_id AND oldn.workspace_id=q.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=q.workspace_id
AND v.subject_id=sb.id AND v.exam_type_id=et.id AND v.exam_year=si.exam_year
WHERE et.type_key='residency' AND si.exam_year=1399 AND si.exam_round=1
AND sb.subject_key='periodontics' AND q.number_in_sitting=119 FOR UPDATE
SQL);
    $find->execute();
    $rows = $find->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) {
        throw new RuntimeException('Original official source identity is not unique.');
    }
    $r = $rows[0];
    if ($r['question_id'] !== $saved['question_id']
        || $r['source_id'] !== $saved['source_id']
        || $r['page'] !== null || $r['anchor_text'] !== $saved['anchor_text']
        || $r['origin'] !== 'ai' || $r['reviewed_at'] !== null
        || $r['reviewed_by_user_id'] !== null || (int) $r['is_primary'] !== 1
        || $r['edition_id'] !== $saved['edition_id']
        || $r['node_id'] !== $saved['old_node_id']
        || $r['existing_chapter'] !== '48'
        || $r['reference_key'] !== 'carranza-periodontology' || $r['edition_key'] !== '13e'
        || (int) $r['is_official'] !== 1
        || $r['question_status'] !== 'published'
        || $r['stem'] !== $saved['stem'] || $r['stem_image'] !== $saved['stem_image']) {
        throw new RuntimeException('Source, old chapter, human guard or published question drift.');
    }
    $scope = json_decode((string) $r['scope_chapters'], true, 64, JSON_THROW_ON_ERROR);
    $scopeNumbers = is_array($scope)
        ? array_map(static fn(array $c): string => (string) ($c['number'] ?? ''), $scope)
        : [];
    if (!in_array('47', $scopeNumbers, true)) {
        throw new RuntimeException('Original official-year scope does not include ch47.');
    }
    $newNode = $db->prepare(<<<'SQL'
SELECT n.id,n.kind,n.edition_id FROM bank_reference_nodes n
WHERE n.workspace_id=:ws AND n.edition_id=:edition AND n.node_key='ch47'
SQL);
    $workspace = $db->prepare('SELECT workspace_id FROM bank_questions WHERE id=:qid');
    $workspace->execute(['qid' => $r['question_id']]);
    $ws = $workspace->fetchColumn();
    if (!is_string($ws)) {
        throw new RuntimeException('Workspace not found.');
    }
    $newNode->execute(['ws' => $ws, 'edition' => $r['edition_id']]);
    $nodes = $newNode->fetchAll(PDO::FETCH_ASSOC);
    if (count($nodes) !== 1 || $nodes[0]['kind'] !== 'chapter'
        || $nodes[0]['edition_id'] !== $r['edition_id']) {
        throw new RuntimeException('Original Carranza 13e target chapter node not unique.');
    }
    $choices = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE question_id=:qid ORDER BY position');
    $choices->execute(['qid' => $r['question_id']]);
    $liveChoices = array_map(static fn(array $c): array => [
        'position' => (int) $c['position'],'text' => (string) $c['text'],
        'image' => $c['image'] === null ? null : (string) $c['image'],
    ], $choices->fetchAll(PDO::FETCH_ASSOC));
    if ($liveChoices !== $saved['choices']) {
        throw new RuntimeException('Protected options or images changed.');
    }
    $answer = $db->prepare('SELECT choice_position,status,also_correct_positions FROM bank_official_answers WHERE question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $answer->execute(['qid' => $r['question_id']]);
    $a = $answer->fetch(PDO::FETCH_ASSOC);
    if (!is_array($a) || (int) $a['choice_position'] !== 1
        || $a['status'] !== 'final'
        || (int) $a['choice_position'] !== (int) $saved['answer_position']
        || ($a['also_correct_positions'] === null ? null : json_decode($a['also_correct_positions'],true,64,JSON_THROW_ON_ERROR))
            !== $saved['answer_also_correct']) {
        throw new RuntimeException('Protected official answer was modified.');
    }
    if ($apply) {
        $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET node_id=:target_node,page='507'
WHERE id=:source_id AND question_id=:question_id AND edition_id=:edition_id
AND node_id=:old_node AND page IS NULL AND origin='ai'
AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
SQL);
        $update->execute(['target_node' => $nodes[0]['id'],'source_id' => $r['source_id'],
            'question_id' => $r['question_id'], 'edition_id' => $r['edition_id'],
            'old_node' => $r['node_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Atomic compare-and-swap source update failed.');
        }
    }
    $report = [
        'format' => 'fanoos.periodontics.1399-Q119-exact13e-chapter-page/1',
        'applied' => $apply,'year' => 1399,'number' => 119,
        'old_chapter' => '48','new_chapter' => '47',
        'old_page' => null,'new_printed_page' => '507','original_pdf_page' => 1073,
        'immutable_source_id' => $r['source_id'],
        'updated_only' => ['bank_question_sources.node_id','bank_question_sources.page'],
        'question_option_answer_anchor_review_assessment_attempt_edits' => 0,
        'original_source_snapshot_sha256' => $originalHash,
        'private_exact_book_sha256' => $textHash,'utc' => gmdate('c')
    ];
    if ($apply) {
        $report['backup_id'] = basename($backup);
        $db->commit();
        $committed = true;
        $content = json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($fh,$content) !== strlen($content) || !fflush($fh)) {
            throw new RuntimeException('Database committed, but receipt failed: check live data.');
        }
        fclose($fh);
        $fh = false;
    } else {
        $db->rollBack();
    }
    echo json_encode($report,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($fh !== false) {
        fclose($fh);
        if (!$committed && is_string($receiptName) && is_file($receiptName)) {
            unlink($receiptName);
        }
    }
    fwrite(STDERR,'Exact Carranza13e Q119 correction refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($locked && $db instanceof PDO) {
        $db->query("SELECT RELEASE_LOCK('fanoos:periodontics-existing-source-pages')");
    }
}
