<?php

declare(strict_types=1);

/**
 * Atomic, existing-source ONLY repair for independently verified original-PDF pages.
 *
 * Dry-run: php scripts/references/repair_bound_pdf_pages.php --file=<protected-json>
 * Apply:   php scripts/references/repair_bound_pdf_pages.php --file=<protected-json>
 *            --apply --backup=<fresh-verified-full-backup> --receipt=<new-protected-json>
 *
 * Changes only bank_question_sources.pdf_page and pdf_sha256. It never changes
 * node_id, edition_id, legacy page, printed page, evidence, confidence, human
 * decisions, questions, options, official keys, attempts or frozen assessments.
 */
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use PDO;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

function repairNormalize(string $value): string
{
    return trim((string) preg_replace('/\\s+/u', ' ',
        mb_strtolower(str_replace("\u{00AD}", '', $value))));
}

function repairVerifyPdfPage(string $edition, int $page, string $sha, string $excerpt): void
{
    $command = [
        'python3', '-B', __DIR__ . '/verified_reference_pdf.py',
        '--edition', $edition, '--page', (string) $page,
        '--expected-sha256', $sha, '--require-map',
    ];
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        throw new RuntimeException('Exact verified PDF reader could not start.');
    }
    fclose($pipes[0]);
    $pageText = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0 || !is_string($pageText)) {
        throw new RuntimeException('Original-PDF reader failed for ' . $edition
            . ' page ' . $page . ': ' . substr((string) $error, 0, 160));
    }
    if (mb_strlen(repairNormalize($excerpt)) < 24
        || !str_contains(repairNormalize($pageText), repairNormalize($excerpt))) {
        throw new RuntimeException('Short evidence is not verbatim on the verified original PDF page.');
    }
    unset($pageText);
}

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--(file|backup|receipt|apply)(?:=(.*))?$/D', $arg, $m) !== 1
            || isset($args[$m[1]])) {
            throw new RuntimeException('Unexpected or repeated option.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $apply = isset($args['apply']);
    $research = realpath('/srv/fanoos/shared/research');
    $reportDir = realpath('/srv/fanoos/shared/research/classification/reports');
    $file = (string) ($args['file'] ?? '');
    if ($research === false || $reportDir === false || is_link($file)
        || realpath($file) !== $file || !is_file($file)
        || !str_starts_with($file, $reportDir . '/')) {
        throw new RuntimeException('Input must be a regular approved private classification report.');
    }
    if ($apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Apply needs BOTH a verified full backup and a new receipt.');
    }
    if ($apply) {
        if (!function_exists('posix_getpwuid')
            || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd') {
            throw new RuntimeException('Only the updater service account may apply.');
        }
        $backup = realpath((string) $args['backup']);
        $receipt = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || dirname($receipt) !== $reportDir || !str_ends_with($receipt, '.json')
            || is_link($receipt) || file_exists($receipt)) {
            throw new RuntimeException('Unsafe backup or receipt destination.');
        }
        $manifest = BackupManifest::verify($backup);
        $timestamp = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($timestamp === false || abs(time() - $timestamp) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('A fresh verified full database backup is required.');
        }
    }
    $payload = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
    $rows = $payload['rows'] ?? null;
    if (($payload['format'] ?? '') !== 'fanoos.verified-bound-pdf-page-proposals/1'
        || ($payload['exam_type'] ?? '') !== 'residency' || ($payload['year'] ?? null) !== 1398
        || ($payload['status'] ?? '') !== 'VERIFIED_PDF_PAGE_PROPOSALS_NOT_APPLIED'
        || !is_array($rows) || $rows === [] || count($rows) !== (int) ($payload['total'] ?? -1)
        || count($rows) > 250) {
        throw new RuntimeException('Incorrect scope or unexpected proposal manifest.');
    }
    $maps = json_decode((string) file_get_contents(
        dirname(__DIR__, 2) . '/data/bank/reference-chapter-pages.json'), true, 64, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $get = $db->prepare(<<<'SQL'
SELECT s.id,s.workspace_id,s.question_id,s.edition_id,s.node_id,
s.page,s.pdf_page,s.pdf_sha256,s.anchor_text,s.origin,s.reviewed_at,
q.number_in_sitting,q.status,si.exam_year,si.exam_round,et.type_key,
sb.subject_key,r.reference_key,e.edition_key,n.node_key,n.number AS chapter_number,n.kind
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE s.id=:source_id FOR UPDATE
SQL);
    $validity = $db->prepare(<<<'SQL'
SELECT v.scope_chapters FROM bank_reference_validity v
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE v.edition_id=:edition_id AND v.workspace_id=:workspace
AND v.exam_year=1398 AND v.is_official=1 AND et.type_key='residency'
AND sb.subject_key=:subject
SQL);
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET pdf_page=:page,pdf_sha256=:sha
WHERE id=:source_id AND workspace_id=:workspace
AND pdf_page IS NULL AND pdf_sha256 IS NULL AND reviewed_at IS NULL
SQL);
    $seen = [];
    $db->beginTransaction();
    $checked = [];
    try {
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['source_id'] ?? null)
                || isset($seen[$row['source_id']])) {
                throw new RuntimeException('Malformed or duplicated source ID.');
            }
            $seen[$row['source_id']] = true;
            $get->execute(['source_id' => $row['source_id']]);
            $source = $get->fetch(PDO::FETCH_ASSOC);
            if (!is_array($source) || $get->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException('Expected one existing source row.');
            }
            $edition = (string) $source['reference_key'] . '@' . $source['edition_key'];
            $page = $row['pdf_page'] ?? null;
            $sha = $row['pdf_sha256'] ?? null;
            $excerpt = $row['exact_quote'] ?? null;
            if ($source['type_key'] !== 'residency' || (int) $source['exam_year'] !== 1398
                || (int) $source['exam_round'] !== 1
                || $source['subject_key'] !== ($row['subject'] ?? null)
                || (int) $source['number_in_sitting'] !== ($row['question_number'] ?? null)
                || $source['status'] !== 'published'
                || $edition !== ($row['edition'] ?? null)
                || $source['node_key'] !== ($row['node_key'] ?? null)
                || $source['chapter_number'] !== ($row['chapter'] ?? null)
                || $source['kind'] !== 'chapter'
                || $source['page'] !== ($row['prior_printed_page'] ?? null)
                || $source['origin'] !== 'ai' || $source['reviewed_at'] !== null
                || $source['pdf_page'] !== null || $source['pdf_sha256'] !== null
                || !is_int($page) || $page <= 0
                || !is_string($sha) || preg_match('/^[0-9a-f]{64}$/D', $sha) !== 1
                || !is_string($excerpt) || mb_strlen($excerpt) < 24
                || ($row['status'] ?? '') !== 'proposal_not_published') {
                throw new RuntimeException('Live source differs from a protected pending proposal.');
            }
            $old = repairNormalize((string) $source['anchor_text']);
            $needle = repairNormalize($excerpt);
            if ($old === '' || (!str_contains($old, $needle) && !str_contains($needle, $old))) {
                throw new RuntimeException('Original source anchor differs from the verified book excerpt.');
            }
            $validity->execute(['edition_id' => $source['edition_id'],
                'workspace' => $source['workspace_id'], 'subject' => $source['subject_key']]);
            $scopes = $validity->fetchAll(PDO::FETCH_ASSOC);
            if (count($scopes) !== 1) {
                throw new RuntimeException('Year-exact official edition validity is not unique.');
            }
            $allowed = json_decode((string) $scopes[0]['scope_chapters'], true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($allowed) || !in_array($source['chapter_number'],
                array_column($allowed, 'number'), true)) {
                throw new RuntimeException('Proposed chapter is outside the official 1398 scope.');
            }
            $map = $maps['editions'][$edition] ?? [];
            if (($map['source_pdf_sha256'] ?? '') !== $sha) {
                throw new RuntimeException('Chapter map is not tied to the current exact PDF.');
            }
            $inChapter = false;
            foreach ($map['runs'] ?? [] as $run) {
                if (is_array($run) && count($run) === 3
                    && $run[0] === $source['chapter_number']
                    && is_int($run[1]) && is_int($run[2])
                    && $run[1] <= $page && $page <= $run[2]) {
                    $inChapter = true;
                    break;
                }
            }
            if (!$inChapter) {
                throw new RuntimeException('PDF page does not belong to the exact chapter.');
            }
            repairVerifyPdfPage($edition, $page, $sha, $excerpt);
            $checked[] = ['source_id' => $source['id'],
                'question_number' => (int) $source['number_in_sitting'],
                'chapter' => $source['chapter_number'], 'pdf_page' => $page,
                'pdf_sha256' => $sha];
            if ($apply) {
                $update->execute(['page' => $page,'sha' => $sha,
                    'source_id' => $source['id'],'workspace' => $source['workspace_id']]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Expected exactly one unchanged, unreviewed source row.');
                }
            }
        }
        $receiptPayload = [
            'format' => 'fanoos.bound-pdf-page-source-repair-receipt/1',
            'at' => gmdate('c'), 'mode' => $apply ? 'applied' : 'dry-run',
            'year' => 1398, 'exam_type' => 'residency',
            'proposal_sha256' => hash_file('sha256', $file),
            'only_columns_changed' => ['bank_question_sources.pdf_page', 'bank_question_sources.pdf_sha256'],
            'rows' => $checked, 'count' => count($checked),
            'verified_backup' => $apply ? $backup : null,
        ];
        if ($apply) {
            $json = json_encode($receiptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            $handle = fopen($receipt, 'x');
            if ($handle === false) {
                throw new RuntimeException('Could not create a unique private receipt.');
            }
            chmod($receipt, 0600);
            if (fwrite($handle, $json) !== strlen($json)) {
                fclose($handle);
                unlink($receipt);
                throw new RuntimeException('Could not save complete receipt.');
            }
            fclose($handle);
            $db->commit();
        } else {
            $db->rollBack();
        }
        unset($receiptPayload['rows']);
        echo json_encode($receiptPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($apply && isset($receipt) && is_file($receipt)) {
            unlink($receipt);
        }
        throw $error;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Bound PDF page repair failed: ' . $error->getMessage() . "\n");
    exit(1);
}
