<?php

declare(strict_types=1);

/**
 * Existing-source PAGE-only repair: five original Carranza 13e residency
 * periodontics citations from 1399. All original stems, options, official
 * answers, sources, human decisions, anchor/provenance and assessment versions
 * remain immutable. The only permitted SQL UPDATE is bank_question_sources.page.
 *
 * Preview (transaction rolls back): php scripts/references/correct_existing_periodontics_pages.php
 * Apply: --apply --backup=/var/backups/fanoos/<verified full backup>
 *        --receipt=<new unique JSON in protected proof directory>
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

$dir = '/srv/fanoos/shared/research/classification/reports/periodontics-13e-page-repair-20261010';
$snapshotFile = $dir . '/1399-five-live-sources-and-keys.json';
$bookFile = '/srv/fanoos/shared/research/references/carranza-periodontology@13e.txt';
$snapshotSha = '2029945671bd3ceef8fcc796020da85fc89e53399c160902b4f18423ae2963f2';
$bookSha = 'baa5b5efd320bd286e101b7243dda7550392897bb6b2155261eeb83071d81814';
$targets = [
    113 => ['chapter' => '45', 'pdf' => 1050, 'printed' => '496',
        'answer' => 3, 'proof' => ['1.0-g loading dose, then 500 mg three times a day for 3 days']],
    114 => ['chapter' => '51', 'pdf' => 1182, 'printed' => '549',
        'answer' => 3, 'proof' => ['Swallowing dificulty (dysphagia)']],
    120 => ['chapter' => '17', 'pdf' => 616, 'printed' => '244',
        'answer' => 2, 'proof' => ['mainly of lymphocytes (75%, with the majority being T cells)']],
    126 => ['chapter' => '20', 'pdf' => 656, 'printed' => '270',
        'answer' => 3, 'proof' => ['It can be used to differentiate NUG from speciic infections (e.g., tuberculosis)']],
    129 => ['chapter' => '23', 'pdf' => 708, 'printed' => '306',
        'answer' => 4, 'proof' => ['The most severe degenerative changes in the periodontal pocket', 'occur along the lateral wall']],
];
$db = null;
$locked = false;
$fh = false;
$receiptName = null;
$committed = false;
try {
    $opts = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!preg_match('/^--(apply|backup|receipt)(?:=(.*))?$/D', $argument, $match)
            || isset($opts[$match[1]])) {
            throw new RuntimeException('Unexpected/repeated option.');
        }
        $opts[$match[1]] = $match[2] ?? '1';
    }
    $apply = isset($opts['apply']);
    if ($apply !== (isset($opts['backup']) && isset($opts['receipt']))) {
        throw new RuntimeException('Apply requires both fresh verified backup and new receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Production mutation requires updater service account.');
    }
    if (is_link($snapshotFile) || !is_file($snapshotFile)
        || hash_file('sha256', $snapshotFile) !== $snapshotSha
        || is_link($bookFile) || !is_file($bookFile)
        || hash_file('sha256', $bookFile) !== $bookSha) {
        throw new RuntimeException('Original private snapshot or exact book digest changed.');
    }
    $original = json_decode((string) file_get_contents($snapshotFile), true, 64, JSON_THROW_ON_ERROR);
    $reviewed = [];
    foreach ($original['questions'] ?? [] as $question) {
        $number = (int) ($question['number'] ?? -1);
        if (!isset($targets[$number]) || isset($reviewed[$number])) {
            throw new RuntimeException('Snapshot contains unexpected or duplicate question.');
        }
        $reviewed[$number] = $question;
    }
    if (($original['year'] ?? null) !== 1399 || count($reviewed) !== count($targets)) {
        throw new RuntimeException('Exact five-question official-year snapshot required.');
    }
    $fullText = (string) file_get_contents($bookFile);
    $flatten = static fn(string $s): string => preg_replace('/\s+/u', ' ', mb_strtolower($s)) ?? '';
    foreach ($targets as $number => $t) {
        if (!preg_match('/^=== PAGE ' . $t['pdf'] . ' ===\h*$(.*?)(?=^=== PAGE \d+ ===|\z)/ms', $fullText, $m)) {
            throw new RuntimeException('Exact PDF page absent from original book: ' . $number);
        }
        $pageText = $flatten($m[1]);
        foreach ($t['proof'] as $proof) {
            if (!str_contains($pageText, $flatten($proof))) {
                throw new RuntimeException('Specific answer evidence is absent on exact page: ' . $number);
            }
        }
        // The common helper rejects original page headers longer than 90 chars
        // (e.g. ch51 PDF1182). Independently corroborate all three consecutive
        // book-PDF page headers before accepting an original printed label.
        $pageHeaderIs = static function (string $book, int $pdf, int $print): bool {
            if (!preg_match('/^=== PAGE ' . $pdf . ' ===\\h*$(.*?)(?=^=== PAGE \\d+ ===|\\z)/ms', $book, $match)) {
                return false;
            }
            $lines = array_values(array_filter(array_map('trim', preg_split('/\\R/u', $match[1]) ?: [])));
            $firstLine = (string) ($lines[0] ?? '');
            return preg_match('/(?:^|\\s)' . $print . '(?:\\s|$)/u', $firstLine) === 1;
        };
        $neighborHeadersVerified = $pageHeaderIs($fullText, $t['pdf'] - 1, (int) $t['printed'] - 1)
            && $pageHeaderIs($fullText, $t['pdf'], (int) $t['printed'])
            && $pageHeaderIs($fullText, $t['pdf'] + 1, (int) $t['printed'] + 1);
        if (!PrintedBookPageEvidence::corroborates(
            '/srv/fanoos/shared/research', 'carranza-periodontology@13e',
            $t['pdf'], $t['printed']
        ) && !$neighborHeadersVerified) {
            throw new RuntimeException('Printed page not independently verified on original consecutive page headers: ' . $number);
        }
    }
    unset($fullText);
    if ($apply) {
        $backup = realpath((string) $opts['backup']);
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Full backup path outside protected root.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 14400
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Full backup must be newly verified within four hours.');
        }
        $receiptName = (string) $opts['receipt'];
        if (realpath(dirname($receiptName)) !== realpath($dir)
            || is_link($receiptName) || file_exists($receiptName)
            || !str_ends_with($receiptName, '.json')) {
            throw new RuntimeException('Receipt must be a new private JSON file in exact proof directory.');
        }
        $fh = @fopen($receiptName, 'x');
        if ($fh === false) {
            throw new RuntimeException('Unique private receipt reservation failed.');
        }
        chmod($receiptName, 0600);
    }
    $db = DatabaseConnection::fromEnvironment();
    if ((int) $db->query("SELECT GET_LOCK('fanoos:periodontics-existing-source-pages',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Another periodontics correction is active.');
    }
    $locked = true;
    $db->beginTransaction();
    $find = $db->prepare(<<<'SQL'
SELECT q.id question_id, q.stem, q.stem_image, q.status question_status,
 s.id source_id, s.page, s.anchor_text, s.origin, s.reviewed_at, s.reviewed_by_user_id,
 s.is_primary, s.edition_id, s.node_id,
 r.reference_key,e.edition_key,n.node_key,n.number chapter,n.kind,v.is_official,v.scope_chapters
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_question_sources s ON s.question_id=q.id AND s.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=q.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=q.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=q.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=q.workspace_id
 AND v.subject_id=sb.id AND v.exam_type_id=et.id AND v.exam_year=si.exam_year
WHERE et.type_key='residency' AND si.exam_year=1399 AND si.exam_round=1
 AND sb.subject_key='periodontics' AND q.number_in_sitting=:number FOR UPDATE
SQL);
    $choices = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE question_id=:qid ORDER BY position');
    $answer = $db->prepare('SELECT choice_position,status,also_correct_positions FROM bank_official_answers WHERE question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET page=:printed
WHERE id=:sid AND question_id=:qid AND page IS NULL AND origin='ai'
AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
SQL);
    $changed = [];
    foreach ($targets as $number => $target) {
        $find->execute(['number' => $number]);
        $live = $find->fetchAll(PDO::FETCH_ASSOC);
        if (count($live) !== 1) {
            throw new RuntimeException('Expected one existing source and one official validity: ' . $number);
        }
        $live = $live[0];
        $saved = $reviewed[$number];
        if ($live['question_id'] !== $saved['question_id']
            || $live['source_id'] !== $saved['source_id']
            || $live['page'] !== null || $saved['source_page'] !== null
            || $live['anchor_text'] !== $saved['source_anchor']
            || $live['origin'] !== 'ai' || $saved['source_origin'] !== 'ai'
            || $live['reviewed_at'] !== null || $live['reviewed_by_user_id'] !== null
            || $saved['source_reviewed_at'] !== null || $saved['source_reviewed_by'] !== null
            || (int) $live['is_primary'] !== 1 || (int) $saved['source_primary'] !== 1
            || $live['edition_id'] !== $saved['source_edition_id']
            || $live['node_id'] !== $saved['source_node_id']
            || $live['reference_key'] !== 'carranza-periodontology'
            || $live['edition_key'] !== '13e'
            || $live['node_key'] !== 'ch' . str_pad($target['chapter'], 2, '0', STR_PAD_LEFT)
            || $live['chapter'] !== $target['chapter'] || $saved['chapter'] !== $target['chapter']
            || $live['kind'] !== 'chapter' || (int) $live['is_official'] !== 1
            || $live['question_status'] !== 'published'
            || $live['stem'] !== $saved['stem'] || $live['stem_image'] !== $saved['stem_image']) {
            throw new RuntimeException('Immutable original/source identity drift: ' . $number);
        }
        $scope = json_decode((string) $live['scope_chapters'], true, 64, JSON_THROW_ON_ERROR);
        $chapters = array_map(static fn(array $ch): string => (string) ($ch['number'] ?? ''), $scope ?? []);
        if (!in_array($target['chapter'], $chapters, true)) {
            throw new RuntimeException('Chapter is outside original-year official syllabus: ' . $number);
        }
        $choices->execute(['qid' => $live['question_id']]);
        $actualChoices = array_map(static fn(array $c): array => [
            'position' => (int) $c['position'], 'text' => (string) $c['text'],
            'image' => $c['image'] === null ? null : (string) $c['image'],
        ], $choices->fetchAll(PDO::FETCH_ASSOC));
        if ($actualChoices !== $saved['choices']) {
            throw new RuntimeException('Options/images changed from protected official-key snapshot: ' . $number);
        }
        $answer->execute(['qid' => $live['question_id']]);
        $a = $answer->fetch(PDO::FETCH_ASSOC);
        if (!is_array($a) || !in_array($a['status'], ['final', 'amended'], true)
            || $a['status'] !== $saved['answer_status']
            || (int) $a['choice_position'] !== $target['answer']
            || (int) $a['choice_position'] !== (int) $saved['answer_position']
            || ($a['also_correct_positions'] === null ? null : json_decode($a['also_correct_positions'], true, 64, JSON_THROW_ON_ERROR))
                !== $saved['answer_also_correct']) {
            throw new RuntimeException('Latest official answer no longer equals original snapshot: ' . $number);
        }
        $changed[] = [
            'year' => 1399, 'number' => $number, 'chapter' => $target['chapter'],
            'source_id' => $live['source_id'], 'old_page' => null,
            'printed_page' => $target['printed'], 'verified_pdf_page' => $target['pdf'],
        ];
        if ($apply) {
            $update->execute(['printed' => $target['printed'],
                'sid' => $live['source_id'], 'qid' => $live['question_id']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Atomic source-page compare-and-swap failed: ' . $number);
            }
        }
    }
    $receipt = [
        'format' => 'fanoos.periodontics.existing13e-original-page-repair/1',
        'applied' => $apply, 'count' => count($changed), 'year' => 1399, 'subject' => 'periodontics',
        'fields_updated' => ['bank_question_sources.page'],
        'question_option_answer_human_assessment_changes' => 0,
        'snapshot_sha256' => $snapshotSha, 'private_original_book_sha256' => $bookSha,
        'items' => $changed, 'utc' => gmdate('c'),
    ];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $db->commit();
        $committed = true;
        $body = json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($fh, $body) !== strlen($body) || !fflush($fh)) {
            throw new RuntimeException('Source rows committed but receipt failed: inspect live DB immediately.');
        }
        fclose($fh);
        $fh = false;
    } else {
        $db->rollBack();
    }
    echo json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
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
    fwrite(STDERR, 'Carranza 13e page-only repair refused: ' . $error->getMessage() . PHP_EOL;
    exit(1);
} finally {
    if ($locked && $db instanceof PDO) {
        $db->query("SELECT RELEASE_LOCK('fanoos:periodontics-existing-source-pages')");
    }
}
