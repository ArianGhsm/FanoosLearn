<?php
declare(strict_types=1);

/**
 * Residency 1403 ONLY: guarded source-citation correction for pre-existing,
 * unreviewed, original AI source rows.  Every changed field is in
 * bank_question_sources; NEVER change questions, choices, official keys,
 * student attempts, human reviews or frozen assessment versions.
 *
 * Rollback preview: php scripts/references/repair_residency_1403_ai_sources.php
 *   --plan=/srv/fanoos/shared/research/classification/reports/PLAN.json
 *
 * Apply: same --plan with --apply --backup=<fresh verified full backup>
 *   --receipt=<previously nonexistent protected receipt JSON>.
 *
 * Intentionally excludes unreviewed draft questions and human sources.
 * Exact independently audited original PDF is required for each question.
 * Legacy AI anchor/printed labels, if incorrect, are replaced only with a
 * verbatim short citation from the approved same-year original PDF page.
 * The previous AI evidence survives in the verified full database backup;
 * the SHA-bound original-row identity survives in private plan/receipt.
 */

use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionGuard;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionWriter;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const FANOOS_1403_REPORTS = '/srv/fanoos/shared/research/classification/reports';
const FANOOS_1403_CANONICAL = '/srv/fanoos/shared/research/bank-sittings/1403/canonical-export-20261011/residency-1403-1.json';
const FANOOS_1403_LOCK = 'fanoos:1403:ai-source-citation-correction';

function independentlyAudited1403(array $i, array &$receipts): void
{
    $n = $i['number'];
    $report = $i['audit_path'];
    $decisionPath = $i['decisions_path'];
    foreach ([$report, $decisionPath] as $p) {
        if (!is_string($p) || !str_starts_with($p, '/srv/fanoos/shared/research/tmp/1403-')
            || !Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
                $p,
                '/srv/fanoos/shared/research/tmp',
                false,
            )) {
            throw new RuntimeException("Q{$n}: independently audited private file missing.");
        }
    }
    if (hash_file('sha256', $report) !== $i['audit_sha256']
        || hash_file('sha256', $decisionPath) !== $i['decisions_sha256']) {
        throw new RuntimeException("Q{$n}: protected independent audit hash changed.");
    }
    $audit = json_decode((string) file_get_contents($report), true, 128, JSON_THROW_ON_ERROR);
    $decisions = json_decode((string) file_get_contents($decisionPath), true, 128, JSON_THROW_ON_ERROR);
    if (!is_array($decisions)
        || !Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches($i, $audit, $decisions)) {
        throw new RuntimeException("Q{$n}: independent same-year original-PDF audit or decision mismatch.");
    }
    $receipts[] = ['q' => $n, 'audit_sha256' => $i['audit_sha256'],
        'decisions_sha256' => $i['decisions_sha256']];
}

function readOriginal1403(): array
{
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        FANOOS_1403_CANONICAL,
        '/srv/fanoos/shared/research/bank-sittings/1403',
        false,
    ) || hash_file('sha256', FANOOS_1403_CANONICAL)
        !== Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256) {
        throw new RuntimeException('The original canonical 250-question bank changed.');
    }
    $canonical = json_decode((string) file_get_contents(FANOOS_1403_CANONICAL),
        true, 128, JSON_THROW_ON_ERROR);
    if (($canonical['exam_type'] ?? '') !== 'residency'
        || ($canonical['year'] ?? null) !== 1403
        || ($canonical['round'] ?? null) !== 1
        || count($canonical['questions'] ?? []) !== 250) {
        throw new RuntimeException('Wrong original bank sitting.');
    }
    $byNumber = [];
    foreach ($canonical['questions'] as $q) {
        $n = $q['number'];
        if (!is_int($n) || isset($byNumber[$n])) {
            throw new RuntimeException('Duplicate original question.');
        }
        $byNumber[$n] = $q;
    }
    return $byNumber;
}

function validateOriginalQuestion1403(PDO $db, array $row, array $original): void
{
    $n = (int) $row['number_in_sitting'];
    if ($row['question_status'] !== 'published'
        || $row['stem'] !== $original['stem']
        || $row['subject_key'] !== $original['subject']) {
        throw new RuntimeException("Q{$n}: published original question differs.");
    }
    $params = ['ws' => $row['workspace_id'], 'qid' => $row['question_id']];
    $choices = $db->prepare('SELECT text,image FROM bank_question_choices
        WHERE workspace_id=:ws AND question_id=:qid ORDER BY position FOR UPDATE');
    $choices->execute($params);
    $actual = array_map(static fn($c): array => [
        'text' => (string) $c['text'], 'image' => $c['image'] === null ? null : (string) $c['image'],
    ], $choices->fetchAll(PDO::FETCH_ASSOC));
    $wanted = array_map(static fn($c): array => [
        'text' => (string) $c['text'], 'image' => $c['image'] ?? null,
    ], $original['choices']);
    if ($actual !== $wanted) {
        throw new RuntimeException("Q{$n}: immutable choices differ.");
    }
    $answer = $db->prepare('SELECT choice_position,also_correct_positions,status
        FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid
        ORDER BY recorded_at DESC,id DESC LIMIT 1 FOR UPDATE');
    $answer->execute($params);
    $a = $answer->fetch(PDO::FETCH_ASSOC);
    if (!is_array($a) || !is_array($original['answer'] ?? null)
        || !Residency1403AiSourceCitationCorrectionGuard::officialAnswerMatches($a, $original['answer'])) {
        throw new RuntimeException("Q{$n}: final official answer changed or is invalid.");
    }
}

$db = null;
$locked = false;
$committed = false;
$receiptPath = null;
try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI-only source correction.');
    }
    $args = [];
    foreach (array_slice($argv, 1) as $a) {
        if (preg_match('/^--(plan|apply|backup|receipt)(?:=(.*))?$/D', $a, $match) !== 1
            || isset($args[$match[1]])) {
            throw new RuntimeException('Unexpected or duplicate argument.');
        }
        $args[$match[1]] = $match[2] ?? '1';
    }
    $apply = isset($args['apply']);
    if (!isset($args['plan']) || ($apply !== isset($args['backup']))
        || ($apply !== isset($args['receipt']))) {
        throw new RuntimeException('Plan required, apply additionally requires backup AND receipt.');
    }
    $reportDir = realpath(FANOOS_1403_REPORTS);
    $path = (string) $args['plan'];
    if ($reportDir === false || !Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        $path,
        FANOOS_1403_REPORTS,
    )) {
        throw new RuntimeException('Plan must be an existing original private report.');
    }
    $planSha = hash_file('sha256', $path);
    $plan = json_decode((string) file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_PATH,
        '/srv/fanoos/shared/research/tmp',
        false,
    ) || hash_file('sha256', Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_PATH)
        !== Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_SHA256) {
        throw new RuntimeException('The pinned original research-only reconciliation changed or is not private.');
    }
    $researchManifest = json_decode((string) file_get_contents(
        Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_PATH,
    ),
        true, 128, JSON_THROW_ON_ERROR);
    if (!Residency1403AiSourceCitationCorrectionGuard::researchManifestMatches($researchManifest)) {
        throw new RuntimeException('The original 1403 research-only reconciliation failed its pinned integrity checks.');
    }
    $entries = $plan['entries'] ?? null;
    if (($plan['format'] ?? null) !== 'fanoos.residency1403.ai-source-correction/1'
        || ($plan['exam_year'] ?? null) !== 1403
        || ($plan['canonical_sha256'] ?? null) !== Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256
        || ($plan['research_sha256'] ?? null) !== Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_SHA256
        || !is_array($entries) || !array_is_list($entries)
        || count($entries) < 1 || count($entries) > 12) {
        throw new RuntimeException('Wrong exact-1403 source-only protected proposal.');
    }
    $original = readOriginal1403();
    $map = json_decode((string) file_get_contents(dirname(__DIR__, 2)
        . '/data/bank/reference-chapter-pages.json'), true, 128, JSON_THROW_ON_ERROR);
    $seen = [];
    $proofs = [];
    foreach ($entries as $i) {
        $n = $i['number'] ?? null;
        $ref = $i['new_ref'] ?? '';
        $ch = $i['new_ch'] ?? '';
        $p = $i['pdf_page'] ?? null;
        $sha = $i['pdf_sha'] ?? '';
        if (!is_int($n) || $n < 1 || $n > 250 || isset($seen[$n])
            || !isset($original[$n]) || ($original[$n]['subject'] ?? null) !== ($i['subject'] ?? null)
            || !preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', (string) $ref)
            || !preg_match('/^[0-9]{1,3}$/D', (string) $ch)
            || !is_int($p) || $p < 1 || $p > 6000
            || !preg_match('/^[a-f0-9]{64}$/D', (string) $sha)
            || !preg_match('/^[a-f0-9-]{36}$/D', (string) ($i['source_id'] ?? ''))
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($i['old_anchor_sha256'] ?? ''))
            || !is_string($i['evidence'] ?? null)
            || mb_strlen($i['evidence']) < 24 || mb_strlen($i['evidence']) > 190
            || !isset($i['old_ref'], $i['old_ch'])
            || !array_key_exists('old_page', $i)
            || !array_key_exists('old_printed', $i)) {
            throw new RuntimeException('Malformed or duplicated original AI source metadata.');
        }
        $seen[$n] = true;
        if (($map['editions'][$ref]['source_pdf_sha256'] ?? null) !== $sha
            || ($map['editions'][$ref]['source'] ?? null) !== 'verified-server-pdf') {
            throw new RuntimeException("Q{$n}: official PDF map not bound to approved original SHA.");
        }
        $inside = false;
        foreach ($map['editions'][$ref]['runs'] as $run) {
            if ((string) ($run[0] ?? '') === $ch && $p >= $run[1] && $p <= $run[2]) {
                $inside = true;
                break;
            }
        }
        if (!$inside) {
            throw new RuntimeException("Q{$n}: exact original PDF page outside the approved chapter.");
        }
        independentlyAudited1403($i, $proofs);
        if (!PrintedBookPageEvidence::pageContainsEvidence($ref, $p,
            [$i['evidence']], $sha)) {
            throw new RuntimeException("Q{$n}: original PDF does not contain verbatim reviewed citation.");
        }
    }
    if ($apply) {
        if ((posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd') {
            throw new RuntimeException('Production apply requires authorized updater identity.');
        }
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $reportDir
            || is_link($receiptPath) || file_exists($receiptPath)
            || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Fresh official backup and previously unused private receipt required.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Independently verified recent COMPLETE full database backup required.');
        }
    }

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    if ((int) $db->query("SELECT GET_LOCK('" . FANOOS_1403_LOCK . "',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Exclusive residency 1403 AI source correction already active.');
    }
    $locked = true;
    $get = $db->prepare(<<<'SQL'
SELECT s.id AS source_id,s.workspace_id,s.question_id,s.edition_id,s.node_id,
 s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,
 s.table_ref,s.figure_ref,s.box_ref,s.origin,s.is_primary,
 s.reviewed_at,s.reviewed_by_user_id,
 q.number_in_sitting,q.status AS question_status,q.stem,
 sb.subject_key,r.reference_key,e.edition_key,n.number AS old_chapter,
 et.type_key,si.exam_year,si.exam_round
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE s.id=:src FOR UPDATE
SQL);
    $resolve = $db->prepare(<<<'SQL'
SELECT e.id AS edition_id,n.id AS node_id,n.kind,v.scope_chapters
FROM bank_reference_editions e
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
WHERE e.workspace_id=:ws AND r.reference_key=:ref AND e.edition_key=:edition
 AND n.node_key=:node AND et.type_key='residency' AND v.exam_year=1403
 AND sb.subject_key=:subject AND v.is_official=1
SQL);
    $results = [];
    $db->beginTransaction();
    try {
        foreach ($entries as $i) {
            $n = $i['number'];
            $get->execute(['src' => $i['source_id']]);
            $matches = $get->fetchAll(PDO::FETCH_ASSOC);
            if (count($matches) !== 1) {
                throw new RuntimeException("Q{$n}: original source row is no longer unique.");
            }
            $old = $matches[0];
            if ((int) $old['number_in_sitting'] !== $n
                || $old['type_key'] !== 'residency'
                || (int) $old['exam_year'] !== 1403 || (int) $old['exam_round'] !== 1
                || $old['subject_key'] !== $i['subject']
                || ($old['reference_key'] . '@' . $old['edition_key']) !== $i['old_ref']
                || (string) $old['old_chapter'] !== (string) $i['old_ch']
                || $old['page'] !== $i['old_page']
                || $old['printed_page'] !== $i['old_printed']
                || hash('sha256', (string) $old['anchor_text']) !== $i['old_anchor_sha256']
                || $old['origin'] !== 'ai' || (int) $old['is_primary'] !== 1
                || $old['reviewed_at'] !== null || $old['reviewed_by_user_id'] !== null
                || $old['pdf_page'] !== null || $old['pdf_sha256'] !== null
                || $old['table_ref'] !== null || $old['figure_ref'] !== null
                || $old['box_ref'] !== null) {
                throw new RuntimeException("Q{$n}: protected AI source state changed or is human-reviewed.");
            }
            validateOriginalQuestion1403($db, $old, $original[$n]);
            [$refName, $refEdition] = explode('@', $i['new_ref'], 2);
            $resolve->execute(['ws' => $old['workspace_id'],
                'ref' => $refName, 'edition' => $refEdition,
                'node' => 'ch' . str_pad($i['new_ch'], 2, '0', STR_PAD_LEFT),
                'subject' => $i['subject']]);
            $nodeRows = $resolve->fetchAll(PDO::FETCH_ASSOC);
            if (count($nodeRows) !== 1 || $nodeRows[0]['kind'] !== 'chapter') {
                throw new RuntimeException("Q{$n}: exactly one official same-year original edition/chapter required.");
            }
            $scope = json_decode((string) $nodeRows[0]['scope_chapters'], true, 64, JSON_THROW_ON_ERROR);
            $allowed = false;
            foreach ((is_array($scope) ? $scope : []) as $chapter) {
                if ((string) ($chapter['number'] ?? '') === $i['new_ch']
                    && empty($chapter['partial'])) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                throw new RuntimeException("Q{$n}: original chapter excluded by official 1403 syllabus.");
            }
            $target = $nodeRows[0];
            $sourceReadback = Residency1403AiSourceCitationCorrectionWriter::write(
                $db,
                $old,
                ['edition_id'=>$target['edition_id'],'node_id'=>$target['node_id']],
                $i['pdf_page'],
                $i['pdf_sha'],
                $i['evidence'],
            );
            $results[] = [
                'number' => $n, 'source_id' => $i['source_id'],
                'old_ref' => $i['old_ref'], 'old_ch' => $i['old_ch'],
                'ref' => $i['new_ref'], 'chapter' => $i['new_ch'],
                'pdf_page' => $sourceReadback['pdf_page'], 'pdf_sha256' => $sourceReadback['pdf_sha256'],
                'old_source_anchor_sha256' => $i['old_anchor_sha256'],
                'audit_sha256' => $i['audit_sha256'],
            ];
        }
        if (!$apply) {
            $db->rollBack();
        } else {
            $stream = @fopen($receiptPath, 'x');
            if ($stream === false) {
                throw new RuntimeException('Cannot reserve unique protected receipt before commit.');
            }
            chmod($receiptPath, 0600);
            fclose($stream);
            $db->commit();
            $committed = true;
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    $reply = [
        'format' => 'fanoos.residency1403.ai-source-correction-receipt/1',
        'applied' => $apply, 'question_count' => count($results), 'exam_year' => 1403,
        'original_bank_sha256' => Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256,
        'original_research_sha256' => Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_SHA256,
        'plan_sha256' => $planSha,
        'source_fields_only' => ['edition_id','node_id','page','printed_page',
            'pdf_page','pdf_sha256','anchor_text'],
        'questions_choices_official_answers_human_reviews_attempts_assessments_changed' => 0,
        'updated' => $results,
    ];
    if ($apply) {
        $reply['backup_id'] = basename($backup);
        $reply['committed_utc'] = gmdate('c');
        $output = json_encode($reply, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        if (file_put_contents($receiptPath, $output, LOCK_EX) !== strlen($output)) {
            throw new RuntimeException('Committed but receipt write failed: reconcile DB, never retry blindly.');
        }
    }
    echo json_encode($reply, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if (!$committed && is_string($receiptPath) && is_file($receiptPath)
        && filesize($receiptPath) === 0) {
        unlink($receiptPath);
    }
    fwrite(STDERR, 'Strict 1403 original-AI source citation repair rejected: '
        . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($locked && $db instanceof PDO) {
        $db->query("SELECT RELEASE_LOCK('" . FANOOS_1403_LOCK . "')");
    }
}
