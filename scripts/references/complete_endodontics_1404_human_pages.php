<?php

declare(strict_types=1);

/**
 * Existing HUMAN-source page-only completion for the exact 1404 endodontics
 * Q27/Q34. Does not change chapter, edition, provenance, anchor, questions,
 * choices, official keys, historical versions, or attempts.
 *
 * Preview: php scripts/references/complete_endodontics_1404_human_pages.php
 * Apply: --apply --backup=/var/backups/fanoos/<verified> --receipt=<new-private-json>
 */
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

$root = '/srv/fanoos/shared/research/classification/coordinator/W03-location-final-20261010';
$originalBook = '/srv/fanoos/shared/storage/private/01/01a1037b-0322-7c8f-8904-7c9334fb9cbe/01/01a1218c-f646-7c72-8283-5e6e9b3e0fa0/01a1218c-f646-75bc-8142-17ee6088c90a.bin';
$bookSha = '09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0';
$proofPath = $root . '/classification/reports/1404-endo-human-exact-pdf-page-proof-20261010.json';
$proofSha = '3c1bff9ec82b3e661b7bae87df86b280c982ddd977dba7037285086cb24eb76a';
$targets = [
    27 => ['chapter' => '12', 'pdf' => 246, 'page' => 'pdf 246',
        'proof' => 'the main canal or canals of the distal root'],
    34 => ['chapter' => '13', 'pdf' => 281, 'page' => 'pdf 281',
        'proof' => 'Access through crowns with extensive foundations may make'],
];
$db = null;
$receiptHandle = false;
$receiptPath = null;
$committed = false;
$locked = false;
try {
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (!preg_match('/^--(apply|backup|receipt)(?:=(.*))?$/D', $argument, $part)
            || isset($args[$part[1]])) {
            throw new RuntimeException('Unexpected or repeated option.');
        }
        $args[$part[1]] = $part[2] ?? '1';
    }
    $apply = isset($args['apply']);
    if ($apply !== (isset($args['backup']) && isset($args['receipt']))) {
        throw new RuntimeException('Apply requires both backup and receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Apply requires the official updater service account.');
    }
    if (is_link($originalBook) || !is_file($originalBook)
        || hash_file('sha256', $originalBook) !== $bookSha) {
        throw new RuntimeException('Original authorized Torabinejad 6e PDF hash mismatch.');
    }
    if (is_link($proofPath) || !is_file($proofPath)
        || hash_file('sha256', $proofPath) !== $proofSha) {
        throw new RuntimeException('Immutable original PDF page proof is unavailable or changed.');
    }
    $proofData = json_decode((string) file_get_contents($proofPath), true, 64, JSON_THROW_ON_ERROR);
    if (($proofData['format'] ?? '') !== 'fanoos.endodontics.original-pdf-page-proof/1'
        || ($proofData['edition'] ?? '') !== 'torabinejad-endodontics@6e'
        || ($proofData['source_pdf_sha256'] ?? '') !== $bookSha
        || (int) ($proofData['total_pdf_pages'] ?? 0) !== 501) {
        throw new RuntimeException('Untrusted original PDF evidence provenance.');
    }
    $pages = [];
    foreach ($proofData['pages'] ?? [] as $item) {
        $n = (int) ($item['pdf_page'] ?? 0);
        $body = (string) ($item['page_text'] ?? '');
        if (isset($pages[$n]) || !in_array($n, [246, 281], true)
            || hash('sha256', $body) !== ($item['page_text_sha256'] ?? '')) {
            throw new RuntimeException('Original PDF page proof integrity mismatch.');
        }
        $pages[$n] = $body;
    }
    $normalize = static fn(string $v): string => trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($v)));
    foreach ($targets as $n => $target) {
        if (!isset($pages[$target['pdf']])
            || !str_contains($normalize($pages[$target['pdf']]), $normalize($target['proof']))) {
            throw new RuntimeException('Exact Torabinejad 6e citation not supported: Q' . $n);
        }
    }
    if ($apply) {
        $backup = realpath((string) $args['backup']);
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Verified backup must be in Fanoos protected backup root.');
        }
        $manifest = BackupManifest::verify($backup);
        $when = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($when === false || abs(time() - $when) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('A recently verified full backup is required.');
        }
        $receiptPath = (string) $args['receipt'];
        if (realpath(dirname($receiptPath)) !== realpath($root . '/classification/reports')
            || is_link($receiptPath) || file_exists($receiptPath)
            || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Unique private receipt inside protected coordinator stage required.');
        }
        $receiptHandle = @fopen($receiptPath, 'x');
        if ($receiptHandle === false) {
            throw new RuntimeException('Cannot reserve private receipt.');
        }
        chmod($receiptPath, 0600);
    }

    $db = DatabaseConnection::fromEnvironment();
    if ((int) $db->query("SELECT GET_LOCK('fanoos:endo-1404-human-pages',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Another human-page publisher holds the lock.');
    }
    $locked = true;
    $db->beginTransaction();
    $find = $db->prepare(<<<'SQL'
SELECT q.id question_id, q.status question_status,
       s.id source_id,s.page,s.origin,s.anchor_text,s.reviewed_at,
       s.reviewed_by_user_id,s.is_primary,
       r.reference_key,e.edition_key,n.node_key,n.kind,v.is_official,v.scope_chapters
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
WHERE et.type_key='residency' AND si.exam_year=1404 AND si.exam_round=1
 AND sb.subject_key='endodontics' AND q.number_in_sitting=:number
FOR UPDATE
SQL);
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET page=:new_page
WHERE id=:id AND question_id=:question_id AND page IS NULL AND origin='human'
SQL);
    $rows = [];
    foreach ($targets as $number => $target) {
        $find->execute(['number' => $number]);
        $matches = $find->fetchAll(PDO::FETCH_ASSOC);
        if (count($matches) !== 1) {
            throw new RuntimeException('Exactly one existing human source is required: Q' . $number);
        }
        $s = $matches[0];
        if ($s['page'] !== null || $s['origin'] !== 'human'
            || $s['reference_key'] !== 'torabinejad-endodontics'
            || $s['edition_key'] !== '6e'
            || $s['node_key'] !== 'ch' . $target['chapter']
            || $s['kind'] !== 'chapter'
            || (int) $s['is_official'] !== 1
            || (int) $s['is_primary'] !== 1
            || $s['question_status'] !== 'published') {
            throw new RuntimeException('Existing human source, chapter, or year no longer matches Q' . $number);
        }
        $scope = json_decode((string) $s['scope_chapters'], true, 64, JSON_THROW_ON_ERROR);
        if (!in_array($target['chapter'], array_map(
            static fn(array $item): string => (string) ($item['number'] ?? ''), $scope
        ), true)) {
            throw new RuntimeException('Human chapter not in official year scope Q' . $number);
        }
        $rows[] = ['number' => $number, 'source_id' => $s['source_id'],
            'chapter' => $target['chapter'], 'old_page' => null,
            'new_page' => $target['page'], 'original_human_origin_preserved' => true];
        if ($apply) {
            $update->execute(['new_page' => $target['page'],
                'id' => $s['source_id'], 'question_id' => $s['question_id']]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Human page compare-and-swap failed Q' . $number);
            }
        }
    }
    $receipt = ['format' => 'fanoos.endodontics.human-page-only/1',
        'applied' => $apply, 'count' => 2, 'rows' => $rows,
        'source_pdf_sha256' => $bookSha, 'private_page_proof_sha256' => $proofSha,
        'changed_columns' => ['bank_question_sources.page'],
        'question_option_answer_human_provenance_mutations' => 0,
        'utc' => gmdate('c')];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $db->commit();
        $committed = true;
        $text = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($receiptHandle, $text) !== strlen($text) || !fflush($receiptHandle)) {
            throw new RuntimeException('Database committed, receipt write failed: manual audit needed.');
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
        if (!$committed && is_string($receiptPath) && is_file($receiptPath)) {
            unlink($receiptPath);
        }
    }
    fwrite(STDERR, 'Endodontics human page-only completion refused: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($locked && $db instanceof PDO) {
        $db->query("SELECT RELEASE_LOCK('fanoos:endo-1404-human-pages')");
    }
}
