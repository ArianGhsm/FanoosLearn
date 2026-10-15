<?php

declare(strict_types=1);

/**
 * Source-only citation for an officially VOIDED and DRAFT residency question.
 * This NEVER publishes, scores, changes or assigns an official answer.
 *
 * Rollback preview:
 *   php scripts/references/repair_verified_voided_source_pages.php
 *     --workspace=UUID --plan=/srv/fanoos/shared/research/classification/reports/PLAN.json
 *
 * Apply (only authorized updater service, after an independently verified
 * recent full backup):
 *   ... --apply --backup=/var/backups/fanoos/BACKUP
 *       --receipt=/srv/fanoos/shared/research/classification/reports/UNUSED.json
 *
 * The independently reviewed audit is SHA-pinned in each protected plan.
 * Printed page labels may be corrected from historical placeholders only
 * when original PDF page/neighbor labels independently corroborate them.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VoidedSourceCitationGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--(workspace|plan|apply|backup|receipt)(?:=(.*))?$/D', $arg, $match) !== 1
            || array_key_exists($match[1], $args)) {
            throw new RuntimeException('Unknown or duplicate protected argument.');
        }
        $args[$match[1]] = $match[2] ?? '1';
    }

    $workspace = (string) ($args['workspace'] ?? '');
    $apply = array_key_exists('apply', $args);
    if (!preg_match('/^[a-f0-9-]{36}$/D', $workspace)
        || $apply !== isset($args['backup'])
        || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace is required; apply requires a backup and a unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Only the authorized updater service may apply.');
    }

    $privateReports = realpath('/srv/fanoos/shared/research/classification/reports');
    $privateClassification = realpath('/srv/fanoos/shared/research/classification');
    $planPath = (string) ($args['plan'] ?? '');
    if ($privateReports === false || realpath(dirname($planPath)) !== $privateReports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Source plan must be a private protected report file.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 32, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.verified-voided-source-page-repair/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || !is_array($plan['entries'] ?? null)
        || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid official exam year, subject or batch shape.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = (string) ($plan['audit_sha256'] ?? '');
    $auditReal = realpath($auditPath);
    if ($privateClassification === false || $auditReal === false || is_link($auditPath)
        || !is_file($auditPath) || !preg_match('/^[a-f0-9]{64}$/D', $auditSha)
        || hash_file('sha256', $auditPath) !== $auditSha
        || (realpath(dirname($auditPath)) !== $privateReports
            && preg_match(
                '~^' . preg_quote($privateClassification, '~')
                . '/recovery-' . (string) $plan['year']
                . '-[a-zA-Z0-9-]+/classification/reports/[^/]+\.json$~D',
                $auditReal,
            ) !== 1)) {
        throw new RuntimeException('Independently accepted private citation audit absent or changed.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 32, JSON_THROW_ON_ERROR);

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $privateReports
            || is_link($receiptPath) || file_exists($receiptPath)
            || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Protected full backup or unique receipt location rejected.');
        }
        $backupManifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($backupManifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($backupManifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh independently verified full database backup required.');
        }
    }

    $map = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 32, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();

    $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,
       s.origin,s.reviewed_at,s.reviewed_by_user_id,
       q.id AS question_id,q.stem,q.status AS question_status,
       n.number AS chapter_no,n.kind,
       e.edition_key,r.reference_key,v.scope_chapters,v.is_official
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
 AND v.exam_type_id=et.id AND v.subject_id=sb.id AND v.exam_year=si.exam_year
WHERE s.workspace_id=:ws AND s.id=:source_id AND q.number_in_sitting=:number
 AND si.exam_year=:year AND si.exam_round=1
 AND et.type_key='residency' AND sb.subject_key=:subject
FOR UPDATE
SQL);
    $keyQuery = $db->prepare(<<<'SQL'
SELECT status,choice_position,also_correct_positions,source,recorded_at
FROM bank_official_answers
WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $choicesQuery = $db->prepare(<<<'SQL'
SELECT position,text,image FROM bank_question_choices
WHERE workspace_id=:ws AND question_id=:qid ORDER BY position
SQL);
    $write = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET printed_page=:printed,pdf_page=:page,pdf_sha256=:sha
WHERE workspace_id=:ws AND id=:id
 AND printed_page <=> :old_printed
 AND pdf_page IS NULL AND pdf_sha256 IS NULL
 AND origin='ai' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
SQL);

    $results = [];
    $seen = [];
    $db->beginTransaction();
    try {
        foreach ($plan['entries'] as $item) {
            $number = $item['number'] ?? null;
            $id = $item['source_id'] ?? null;
            $edition = $item['edition'] ?? null;
            $chapter = $item['chapter'] ?? null;
            $page = $item['pdf_page'] ?? null;
            $sha = $item['pdf_sha256'] ?? null;
            $printed = $item['printed_page'] ?? null;
            $originalPrinted = $item['expected_stored_printed_page'] ?? null;
            $originalPage = $item['expected_legacy_page'] ?? null;
            $anchorHash = $item['expected_anchor_sha256'] ?? null;
            $stemHash = $item['expected_stem_sha256'] ?? null;
            $choicesHash = $item['expected_choices_sha256'] ?? null;
            $answerHash = $item['expected_answer_sha256'] ?? null;
            $evidence = $item['evidence'] ?? null;
            if (!is_array($item) || !is_int($number) || $number < 1 || $number > 300
                || isset($seen[$number])
                || !is_string($id) || preg_match('/^[a-f0-9-]{36}$/D', $id) !== 1
                || !is_string($edition) || preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $edition) !== 1
                || !is_string($chapter) || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $chapter) !== 1
                || !is_int($page) || $page < 1 || $page > 6000
                || !is_string($sha) || preg_match('/^[a-f0-9]{64}$/D', $sha) !== 1
                || !is_string($printed) || preg_match('/^[1-9][0-9]{0,3}$/D', $printed) !== 1
                || !array_key_exists('expected_stored_printed_page', $item)
                || ($originalPrinted !== null && !is_string($originalPrinted))
                || !array_key_exists('expected_legacy_page', $item)
                || ($originalPage !== null && !is_string($originalPage))
                || !is_string($anchorHash) || preg_match('/^[a-f0-9]{64}$/D', $anchorHash) !== 1
                || !is_string($stemHash) || preg_match('/^[a-f0-9]{64}$/D', $stemHash) !== 1
                || !is_string($choicesHash) || preg_match('/^[a-f0-9]{64}$/D', $choicesHash) !== 1
                || !is_string($answerHash) || preg_match('/^[a-f0-9]{64}$/D', $answerHash) !== 1
                || !is_string($evidence) || strlen($evidence) < 16 || strlen($evidence) > 190
                || !VoidedSourceCitationGuard::auditMatches($audit, $plan, $item)) {
                throw new RuntimeException('Q' . (string) $number . ': original-page identity or independent audit invalid.');
            }
            $seen[$number] = true;
            $sourceQuery->execute([
                'ws'=>$workspace,'source_id'=>$id,'number'=>$number,
                'year'=>$plan['year'],'subject'=>$plan['subject'],
            ]);
            $rows = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1) {
                throw new RuntimeException("Q{$number}: original book/source identity not unique.");
            }
            $s = $rows[0];
            if ($s['question_status'] !== 'draft' || $s['origin'] !== 'ai'
                || $s['reviewed_at'] !== null || $s['reviewed_by_user_id'] !== null
                || $s['pdf_page'] !== null || $s['pdf_sha256'] !== null
                || $s['page'] !== $originalPage || $s['printed_page'] !== $originalPrinted
                || hash('sha256', (string) $s['anchor_text']) !== $anchorHash
                || hash('sha256', (string) $s['stem']) !== $stemHash
                || $s['reference_key'] . '@' . $s['edition_key'] !== $edition
                || (string) $s['chapter_no'] !== $chapter || $s['kind'] !== 'chapter'
                || (int) $s['is_official'] !== 1) {
                throw new RuntimeException("Q{$number}: protected original question/source has changed.");
            }

            $scope = json_decode((string) $s['scope_chapters'], true, 32, JSON_THROW_ON_ERROR);
            $allowed = false;
            foreach (is_array($scope) ? $scope : [] as $entry) {
                if (($entry['number'] ?? null) === $chapter && empty($entry['partial'])) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                throw new RuntimeException("Q{$number}: chapter outside the official year scope.");
            }
            $keyQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
            $key = $keyQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($key) || $key['status'] !== 'voided' || $key['choice_position'] !== null
                || hash('sha256', json_encode($key, JSON_THROW_ON_ERROR)) !== $answerHash) {
                throw new RuntimeException("Q{$number}: original voided key/status must remain voided.");
            }
            $choicesQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
            $choices = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($choices) !== 4
                || hash('sha256', json_encode($choices, JSON_THROW_ON_ERROR)) !== $choicesHash) {
                throw new RuntimeException("Q{$number}: original four choices changed.");
            }

            $runs = $map['editions'][$edition]['runs'] ?? [];
            if (($map['editions'][$edition]['source_pdf_sha256'] ?? null) !== $sha
                || !is_array($runs) || $runs === []) {
                throw new RuntimeException("Q{$number}: no SHA-bound approved official book map.");
            }
            $inside = false;
            foreach ($runs as $run) {
                if (is_array($run) && count($run) === 3
                    && (string) $run[0] === $chapter && $page >= $run[1] && $page <= $run[2]) {
                    $inside = true;
                    break;
                }
            }
            if (!$inside || !PrintedBookPageEvidence::corroborates($edition, $page, $printed, $sha)
                || !PrintedBookPageEvidence::pageContainsEvidence($edition, $page, [$evidence], $sha)) {
                throw new RuntimeException("Q{$number}: original book chapter/printed page/exact evidence failed.");
            }

            $write->execute([
                'printed'=>$printed,'page'=>$page,'sha'=>$sha,
                'old_printed'=>$originalPrinted,'ws'=>$workspace,'id'=>$id,
            ]);
            if ($write->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: guarded source-only write ambiguous.");
            }
            $results[] = [
                'number'=>$number,'source_id'=>$id,'edition'=>$edition,
                'chapter'=>$chapter,'previous_printed_page'=>$originalPrinted,
                'printed_page'=>$printed,'pdf_page'=>$page,'pdf_sha256'=>$sha,
                'original_official_answer_status'=>'voided',
                'original_question_status'=>'draft',
            ];
        }
        if ($apply) {
            $db->commit();
        } else {
            $db->rollBack();
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    $receipt = [
        'format'=>'fanoos.verified-voided-source-page-repair-receipt/1',
        'mode'=>$apply ? 'applied' : 'rollback-preview',
        'year'=>$plan['year'],'subject'=>$plan['subject'],
        'count'=>count($results),'plan_sha256'=>hash('sha256', $rawPlan),
        'audit_sha256'=>$auditSha,
        'source_fields_changed'=>VoidedSourceCitationGuard::allowedSourceFields(),
        'questions_changed'=>0,'choices_changed'=>0,'official_answers_changed'=>0,
        'question_status_changed'=>0,'frozen_assessments_changed'=>0,
        'sources'=>$results,
    ];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $receipt['at_utc'] = gmdate('Y-m-d\\TH:i:s\\Z');
        $fp = @fopen($receiptPath, 'x');
        if ($fp === false) {
            throw new RuntimeException('Source committed but receipt missing; independently read back, do not retry.');
        }
        chmod($receiptPath, 0600);
        fwrite($fp, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        fflush($fp);
        fclose($fp);
    }
    echo json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Voided source-only metadata repair refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
