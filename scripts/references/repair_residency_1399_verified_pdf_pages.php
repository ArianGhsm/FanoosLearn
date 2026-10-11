<?php
declare(strict_types=1);

/**
 * One-time, exact-original-book PAGE-METADATA repair, dentistry residency 1399.
 *
 * This intentionally cannot change a question, option, official answer,
 * human-reviewed source, attempt or frozen assessment. It will only update
 * page, printed_page, pdf_page, pdf_sha256 on eight existing AI source rows.
 * Original official chapter and edition must ALREADY match the target.
 *
 * 1. php scripts/references/repair_residency_1399_verified_pdf_pages.php
 *      --subject=endodontics --prepare --plan=/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/endo-plan-<unique>.json
 * 2. sha256sum <plan file>   (record in private audit)
 * 3. Fresh full verified backup via official scripts/ops/backup.php.
 * 4. php ... --subject=endodontics --apply --plan=<same> --plan-sha=<64hex>
 *       --backup=<fresh verified full backup dir> --receipt=<new private JSON path>
 * Repeat with --subject=periodontics for independently reviewed second batch.
 *
 * No user texts, book pages, PDF objects or plaintext answer keys are written
 * into the plan. It stores only immutable live-row SHA-256 digests and IDs.
 * A prepare/preview never mutates bank or question tables.
 */

use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const REPORT_DIR_1399 = '/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages';
const PDF_TORABINEJAD_1399 = '71f21c26a4f1e5d038ff6aa408669a5cc040e90c94eea0bf2e00478997710146';
const PDF_CARRANZA_1399 = 'f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf';

function exactTargets1399(): array
{
    return [
        'endodontics' => [
            25 => ['edition' => 'torabinejad-endodontics@5e', 'chapter' => '4',
                'pdf' => 63, 'old_printed' => '7', 'printed' => '51', 'answer' => 3,
                'sha' => PDF_TORABINEJAD_1399,
                'proof' => ['LPS, Lipid A', 'Toll-like receptors (TLRs)']],
            30 => ['edition' => 'torabinejad-endodontics@5e', 'chapter' => '18',
                'pdf' => 347, 'old_printed' => '335', 'printed' => '335', 'answer' => 4,
                'sha' => PDF_TORABINEJAD_1399,
                'proof' => ['GuttaFlow is a cold flowable injection system']],
            32 => ['edition' => 'torabinejad-endodontics@5e', 'chapter' => '1',
                'pdf' => 21, 'old_printed' => '9', 'printed' => '9', 'answer' => 3,
                'sha' => PDF_TORABINEJAD_1399,
                'proof' => ['factors are embedded in dentin matrix']],
        ],
        'periodontics' => [
            115 => ['edition' => 'carranza-periodontology@13e', 'chapter' => '12',
                'pdf' => 506, 'old_printed' => '182', 'printed' => '182', 'answer' => 2,
                'sha' => PDF_CARRANZA_1399,
                'proof' => ['bleeding on probing', 'attachment loss, and bone loss']],
            118 => ['edition' => 'carranza-periodontology@13e', 'chapter' => '33',
                'pdf' => 882, 'old_printed' => '408', 'printed' => '408', 'answer' => 4,
                'sha' => PDF_CARRANZA_1399,
                'proof' => ['fenestrations, and dehiscence']],
            122 => ['edition' => 'carranza-periodontology@13e', 'chapter' => '72',
                'pdf' => 1611, 'old_printed' => '721', 'printed' => '721', 'answer' => 1,
                'sha' => PDF_CARRANZA_1399,
                'proof' => ['Periodontal surgery indicated but not performed for medical',
                    '1–3 months']],
            123 => ['edition' => 'carranza-periodontology@13e', 'chapter' => '52',
                'pdf' => 1196, 'old_printed' => '559', 'printed' => '559', 'answer' => 1,
                'sha' => PDF_CARRANZA_1399,
                'proof' => ['Metronidazole also inhibits warfarin']],
            125 => ['edition' => 'carranza-periodontology@13e', 'chapter' => '3',
                'pdf' => 96, 'old_printed' => '46', 'printed' => '46', 'answer' => 2,
                'sha' => PDF_CARRANZA_1399,
                'proof' => ['The transseptal and alveolar',
                    'crest ibers develop when the tooth merges into the oral cavity']],
        ],
    ];
}

function verifyPrivateFile1399(string $file, bool $mustBeNew): void
{
    if (realpath(dirname($file)) !== realpath(REPORT_DIR_1399)
        || !str_ends_with($file, '.json') || is_link($file)
        || ($mustBeNew && file_exists($file))
        || (!$mustBeNew && !is_file($file))) {
        throw new RuntimeException('Plan/receipt must be an exact private JSON path with expected existence.');
    }
}

function rowsFingerprint1399(PDO $db, string $ws, string $qid): string
{
    $result = [];
    foreach ([
        'question' => ['bank_questions', 'id', 'id'],
        'choices' => ['bank_question_choices', 'question_id', 'position'],
        'answers' => ['bank_official_answers', 'question_id', 'recorded_at,id'],
        'sources' => ['bank_question_sources', 'question_id', 'id'],
    ] as $kind => [$table, $idColumn, $order]) {
        // All table/column names are fixed constants, never caller-controlled.
        $statement = $db->prepare(
            "SELECT * FROM {$table} WHERE workspace_id=:ws AND {$idColumn}=:qid ORDER BY {$order} FOR UPDATE"
        );
        $statement->execute(['ws' => $ws, 'qid' => $qid]);
        $result[$kind] = $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    if (count($result['question']) !== 1
        || count($result['sources']) !== 1 || count($result['choices']) < 2
        || $result['answers'] === []) {
        throw new RuntimeException('Immutable question/source/answer cardinality mismatch.');
    }
    return hash('sha256', json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
}

function inspectTargets1399(PDO $db, string $subject, array $targets): array
{
    $find = $db->prepare(<<<'SQL'
SELECT q.id qid, q.workspace_id ws, q.status qstatus,
s.id sid,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.origin,
s.reviewed_at,s.reviewed_by_user_id,s.is_primary,
r.reference_key,e.edition_key,n.node_key,n.number chapter,n.kind,
v.is_official,v.scope_chapters
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
  AND sb.subject_key=:subject AND q.number_in_sitting=:number
FOR UPDATE
SQL);
    $latestAnswer = $db->prepare(<<<'SQL'
SELECT choice_position,status FROM bank_official_answers
WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1 FOR UPDATE
SQL);
    $rows = [];
    foreach ($targets as $number => $target) {
        $find->execute(['subject' => $subject, 'number' => $number]);
        $found = $find->fetchAll(PDO::FETCH_ASSOC);
        if (count($found) !== 1) {
            throw new RuntimeException("Exactly one original official source required for Q{$number}.");
        }
        $v = $found[0];
        [$ref, $edition] = explode('@', $target['edition'], 2);
        if ($v['qstatus'] !== 'published'
            || $v['reference_key'] !== $ref || $v['edition_key'] !== $edition
            || $v['node_key'] !== 'ch' . str_pad($target['chapter'], 2, '0', STR_PAD_LEFT)
            || (string) $v['chapter'] !== $target['chapter'] || $v['kind'] !== 'chapter'
            || (int) $v['is_official'] !== 1
            || $v['origin'] !== 'ai' || (int) $v['is_primary'] !== 1
            || $v['reviewed_at'] !== null || $v['reviewed_by_user_id'] !== null
            || $v['pdf_page'] !== null || $v['pdf_sha256'] !== null
            || $v['page'] !== $target['old_printed']
            || $v['printed_page'] !== $target['old_printed']) {
            throw new RuntimeException("Source/edition/chapter/human/old-page guard failed on Q{$number}.");
        }
        $scope = json_decode((string) $v['scope_chapters'], true, 64, JSON_THROW_ON_ERROR);
        $allowed = array_map(static fn(array $x): string => (string) ($x['number'] ?? ''), $scope ?? []);
        if (!in_array($target['chapter'], $allowed, true)) {
            throw new RuntimeException("Original-year official chapter not in scope, Q{$number}.");
        }
        $latestAnswer->execute(['ws' => $v['ws'], 'qid' => $v['qid']]);
        $a = $latestAnswer->fetch(PDO::FETCH_ASSOC);
        if (!is_array($a) || $a['status'] !== 'final'
            || (int) $a['choice_position'] !== $target['answer']) {
            throw new RuntimeException("Final-answer integrity guard failed on Q{$number}.");
        }
        $rows[] = [
            'number' => (int) $number,
            'question_id' => $v['qid'],
            'source_id' => $v['sid'],
            'fingerprint' => rowsFingerprint1399($db, $v['ws'], $v['qid']),
            'old_page' => $target['old_printed'],
            'new_printed_page' => $target['printed'],
            'new_pdf_page' => $target['pdf'],
            'source_pdf_sha256' => $target['sha'],
        ];
    }
    return $rows;
}

function inspectOriginalPages1399(array $targets): void
{
    foreach ($targets as $number => $target) {
        if (!PrintedBookPageEvidence::pageContainsEvidence(
            $target['edition'], $target['pdf'], $target['proof'], $target['sha']
        ) || !PrintedBookPageEvidence::corroborates(
            $target['edition'], $target['pdf'], $target['printed'], $target['sha'], true
        )) {
            throw new RuntimeException("Exact original PDF quote/printed-label evidence rejected Q{$number}.");
        }
    }
}

function writeUniquePrivate1399(string $path, array $document): void
{
    verifyPrivateFile1399($path, true);
    $stream = @fopen($path, 'x');
    if (!is_resource($stream)) {
        throw new RuntimeException('Could not reserve unique private audit file.');
    }
    chmod($path, 0600);
    try {
        $data = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        if (fwrite($stream, $data) !== strlen($data) || !fflush($stream)) {
            throw new RuntimeException('Private audit write failed.');
        }
    } finally {
        fclose($stream);
    }
}

if (PHP_SAPI !== 'cli') {
    throw new RuntimeException('CLI only.');
}

$db = null;
$locked = false;
$committed = false;
$receiptPath = null;
try {
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--(subject|prepare|apply|plan|plan-sha|backup|receipt)(?:=(.*))?$/D', $argument, $m) !== 1
            || isset($args[$m[1]])) {
            throw new RuntimeException('Unexpected or repeated flag.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $subject = (string) ($args['subject'] ?? '');
    $all = exactTargets1399();
    if (!isset($all[$subject]) || isset($args['prepare']) === isset($args['apply'])
        || !isset($args['plan']) || !is_string($args['plan'])) {
        throw new RuntimeException('Choose exactly one --prepare/--apply, one permitted --subject and --plan.');
    }
    $apply = isset($args['apply']);
    if ($apply !== (isset($args['plan-sha'], $args['backup'], $args['receipt']))) {
        throw new RuntimeException('Apply requires --plan-sha, --backup and --receipt together.');
    }
    if ((posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd') {
        throw new RuntimeException('Only protected fanoosupd may prepare/apply.');
    }
    $planPath = (string) $args['plan'];
    verifyPrivateFile1399($planPath, !$apply);
    if ($apply) {
        if (!preg_match('/^[a-f0-9]{64}$/D', (string) $args['plan-sha'])
            || hash_file('sha256', $planPath) !== $args['plan-sha']) {
            throw new RuntimeException('Plan SHA mismatch.');
        }
        $plan = json_decode((string) file_get_contents($planPath), true, 64, JSON_THROW_ON_ERROR);
        if (($plan['format'] ?? '') !== 'fanoos.residency1399.existing-source-exact-pdfpage-plan/1'
            || ($plan['subject'] ?? '') !== $subject || ($plan['exam_year'] ?? null) !== 1399
            || !isset($plan['created_utc']) || strtotime($plan['created_utc']) < time()-14400) {
            throw new RuntimeException('Plan wrong, missing or older than four hours.');
        }
        $backup = realpath((string) $args['backup']);
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Backup must reside in protected backup root.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 14400
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Verified complete backup must be less than four hours old.');
        }
        $receiptPath = (string) $args['receipt'];
        verifyPrivateFile1399($receiptPath, true);
    }
    inspectOriginalPages1399($all[$subject]);
    $db = DatabaseConnection::fromEnvironment();
    if ((int) $db->query("SELECT GET_LOCK('fanoos:1399:source-metadata',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Exclusive 1399 source metadata lock busy.');
    }
    $locked = true;
    $db->beginTransaction();
    $rows = inspectTargets1399($db, $subject, $all[$subject]);
    if (!$apply) {
        $db->rollBack();
        $plan = [
            'format' => 'fanoos.residency1399.existing-source-exact-pdfpage-plan/1',
            'subject' => $subject, 'exam_year' => 1399, 'created_utc' => gmdate('c'),
            'rows' => $rows,
            'mutation_whitelist' => ['bank_question_sources.page', 'bank_question_sources.printed_page',
                'bank_question_sources.pdf_page', 'bank_question_sources.pdf_sha256'],
        ];
        writeUniquePrivate1399($planPath, $plan);
        echo json_encode(['applied' => false, 'subject' => $subject, 'count' => count($rows),
            'plan' => $planPath, 'plan_sha256' => hash_file('sha256', $planPath)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        exit(0);
    }
    if ($rows !== ($plan['rows'] ?? null)) {
        throw new RuntimeException('Live immutable bank/source rows changed since sealed plan.');
    }
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET pdf_page=:new_pdf_page, pdf_sha256=:new_pdf_sha,
    page=:new_printed, printed_page=:new_printed_2
WHERE id=:sid AND question_id=:qid AND node_id IS NOT NULL
AND page=:old_printed AND printed_page=:old_printed_2
AND pdf_page IS NULL AND pdf_sha256 IS NULL
AND origin='ai' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
SQL);
    $readback = $db->prepare(<<<'SQL'
SELECT page,printed_page,pdf_page,pdf_sha256
FROM bank_question_sources WHERE id=:sid AND question_id=:qid FOR UPDATE
SQL);
    foreach ($rows as $row) {
        $update->execute([
            'new_pdf_page' => $row['new_pdf_page'],
            'new_pdf_sha' => $row['source_pdf_sha256'],
            'new_printed' => $row['new_printed_page'],
            'new_printed_2' => $row['new_printed_page'],
            'sid' => $row['source_id'], 'qid' => $row['question_id'],
            'old_printed' => $row['old_page'], 'old_printed_2' => $row['old_page'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException("Source metadata compare-and-swap failed Q{$row['number']}.");
        }
        $readback->execute(['sid' => $row['source_id'], 'qid' => $row['question_id']]);
        $after = $readback->fetch(PDO::FETCH_ASSOC);
        if (!is_array($after) || $after['page'] !== $row['new_printed_page']
            || $after['printed_page'] !== $row['new_printed_page']
            || (int) $after['pdf_page'] !== $row['new_pdf_page']
            || $after['pdf_sha256'] !== $row['source_pdf_sha256']) {
            throw new RuntimeException("Source metadata readback mismatch Q{$row['number']}.");
        }
    }
    // Receipt is reserved before committing; any failure to write after
    // commit is explicitly reported for operator reconciliation.
    $receipt = @fopen($receiptPath, 'x');
    if ($receipt === false) {
        throw new RuntimeException('Could not reserve unique receipt before commit.');
    }
    chmod($receiptPath, 0600);
    fclose($receipt);
    $db->commit();
    $committed = true;
    $body = ['format' => 'fanoos.residency1399.existing-source-exact-pdfpage-receipt/1',
        'applied' => true, 'subject' => $subject, 'exam_year' => 1399, 'rows' => $rows,
        'fields_updated' => ['bank_question_sources.page','bank_question_sources.printed_page',
            'bank_question_sources.pdf_page','bank_question_sources.pdf_sha256'],
        'question_choice_answer_human_attempt_assessment_updates' => 0,
        'backup_id' => basename($backup), 'plan_sha256' => $args['plan-sha'], 'utc' => gmdate('c')];
    $data = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    if (file_put_contents($receiptPath, $data, LOCK_EX) !== strlen($data)) {
        throw new RuntimeException('Committed but receipt write failed: reconcile live bank before any retry.');
    }
    echo json_encode(['applied' => true, 'subject' => $subject, 'count' => count($rows),
        'receipt' => $receiptPath, 'backup_id' => basename($backup),
        'question_choice_answer_human_attempt_assessment_updates' => 0], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if (!$committed && $receiptPath !== null && is_file($receiptPath) && filesize($receiptPath) === 0) {
        unlink($receiptPath);
    }
    fwrite(STDERR, 'Verified 1399 existing-source page repair rejected: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($locked && $db instanceof PDO) {
        $db->query("SELECT RELEASE_LOCK('fanoos:1399:source-metadata')");
    }
}
