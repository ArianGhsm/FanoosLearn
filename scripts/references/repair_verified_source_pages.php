<?php

declare(strict_types=1);

/**
 * Repair ONLY the bound PDF page/hash on a pre-existing, unreviewed AI source.
 * The plan is a protected SHA-pinnable JSON record. Preview executes the
 * exact source-only UPDATE inside a transaction and ROLLS BACK.
 *
 * php scripts/references/repair_verified_source_pages.php \
 *   --workspace=UUID --plan=/srv/fanoos/shared/research/classification/reports/PLAN.json
 *
 * Apply requires --apply --backup=/var/backups/fanoos/<fresh verified full backup>
 * and --receipt=/srv/fanoos/shared/research/classification/reports/<unused>.json.
 *
 * For a source without a stored printed-page label, the independently
 * verified label is written so partial-page syllabus scope can be enforced.
 * Never change chapter, edition, an existing historical printed label, original anchor,
 * question, choices, key, review state, assessment or attempts.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--(workspace|plan|apply|backup|receipt)(?:=(.*))?$/D', $arg, $m) !== 1
            || isset($args[$m[1]])) {
            throw new RuntimeException('Unknown or repeated argument.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $ws = (string) ($args['workspace'] ?? '');
    $apply = isset($args['apply']);
    if (!preg_match('/^[0-9a-f-]{36}$/D', $ws)
        || $apply !== isset($args['backup'])
        || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace required; apply also needs backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Only updater service account may apply.');
    }
    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be an existing protected research/report JSON file.');
    }
    $raw = (string) file_get_contents($planPath);
    $plan = json_decode($raw, true, 48, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? '') !== 'fanoos.verified-source-page-repair/1'
        || ($plan['exam_type'] ?? '') !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || !preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject'])
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid exam scope or plan shape.');
    }

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receipt = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receipt)) !== $reports
            || is_link($receipt) || file_exists($receipt) || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Protected backup or unique private receipt path invalid.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh fully verified production/database backup required.');
        }
    }

    $map = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 32, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,
       s.origin,s.reviewed_at,s.reviewed_by_user_id,
       q.id AS question_id,q.question_key,q.stem,q.status AS question_status,
       e.edition_key,r.reference_key,n.node_key,n.number AS chapter_no,n.kind,
       v.scope_chapters,v.is_official
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
SELECT status,choice_position FROM bank_official_answers
WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $choicesQuery = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET printed_page=:printed,pdf_page=:page,pdf_sha256=:sha
WHERE workspace_id=:ws AND id=:id AND pdf_page IS NULL AND pdf_sha256 IS NULL
  AND printed_page <=> :old_printed
  AND origin='ai' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
SQL);

    $seen = [];
    $results = [];
    $changedSourceFields = ['pdf_page'=>true,'pdf_sha256'=>true];
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
            $storedPrinted = array_key_exists('expected_stored_printed_page', $item)
                ? $item['expected_stored_printed_page'] : $printed;
            $expectedLegacy = $item['expected_legacy_page'] ?? null;
            $anchorHash = $item['expected_anchor_sha256'] ?? null;
            $quote = $item['evidence'] ?? null;
            if (!is_int($number) || $number < 1 || $number > 300 || isset($seen[$number])
                || !is_string($id) || !preg_match('/^[a-f0-9-]{36}$/D', $id)
                || !is_string($edition) || !preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $edition)
                || !is_string($chapter) || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $chapter)
                || !is_int($page) || $page < 1 || $page > 6000
                || !is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D', $sha)
                || !is_string($printed) || !preg_match('/^[1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?$/D', $printed)
                || !array_key_exists('expected_legacy_page', $item)
                || ($expectedLegacy !== null && !is_string($expectedLegacy))
                || ($storedPrinted !== null && (!is_string($storedPrinted)
                    || $storedPrinted !== $printed))
                || !is_string($anchorHash)
                || !preg_match('/^[a-f0-9]{64}$/D', $anchorHash)
                || !is_string($quote) || strlen($quote) < 16 || strlen($quote) > 190) {
                throw new RuntimeException('Invalid source/page/evidence identity in protected plan.');
            }
            $seen[$number] = true;
            $sourceQuery->execute([
                'ws'=>$ws,'source_id'=>$id,'number'=>$number,
                'year'=>$plan['year'],'subject'=>$plan['subject'],
            ]);
            $rows = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1) {
                throw new RuntimeException("Q{$number}: source/official validity identity not unique.");
            }
            $s = $rows[0];
            if ($s['question_status'] !== 'published' || $s['origin'] !== 'ai'
                || $s['reviewed_at'] !== null || $s['reviewed_by_user_id'] !== null
                || $s['pdf_page'] !== null || $s['pdf_sha256'] !== null
                || $s['page'] !== $expectedLegacy || $s['printed_page'] !== $storedPrinted
                || hash('sha256', (string) $s['anchor_text']) !== $anchorHash
                || $s['reference_key'] . '@' . $s['edition_key'] !== $edition
                || (string) $s['chapter_no'] !== $chapter || $s['kind'] !== 'chapter'
                || (int) $s['is_official'] !== 1) {
                throw new RuntimeException("Q{$number}: protected source identity or state changed.");
            }
            if (OfficialScopePageGuard::pageDisposition($s['scope_chapters'], $chapter, $printed) !== true) {
                throw new RuntimeException("Q{$number}: book chapter/printed page is not proven inside official scope.");
            }
            $runs = $map['editions'][$edition]['runs'] ?? [];
            if (($map['editions'][$edition]['source_pdf_sha256'] ?? null) !== $sha
                || !is_array($runs) || $runs === []) {
                throw new RuntimeException("Q{$number}: chapter map not bound to this PDF.");
            }
            $inside = false;
            foreach ($runs as $run) {
                if (is_array($run) && count($run) === 3
                    && (string) $run[0] === $chapter && $page >= $run[1] && $page <= $run[2]) {
                    $inside = true;
                    break;
                }
            }
            if (!$inside
                || !PrintedBookPageEvidence::corroborates($edition, $page, $printed, $sha)
                || !PrintedBookPageEvidence::pageContainsEvidence($edition, $page, [$quote], $sha)) {
                throw new RuntimeException("Q{$number}: verified original page/printed pagination/evidence failed.");
            }
            $keyQuery->execute(['ws'=>$ws,'qid'=>$s['question_id']]);
            $key = $keyQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($key) || !in_array($key['status'], ['final','amended'], true)
                || $key['choice_position'] === null) {
                throw new RuntimeException("Q{$number}: official answer not valid.");
            }
            $choicesQuery->execute(['ws'=>$ws,'qid'=>$s['question_id']]);
            if (count($choicesQuery->fetchAll(PDO::FETCH_ASSOC)) < 2) {
                throw new RuntimeException("Q{$number}: incomplete choices.");
            }
            $update->execute(['printed'=>$printed,'page'=>$page,'sha'=>$sha,'old_printed'=>$storedPrinted,'ws'=>$ws,'id'=>$id]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: zero/ambiguous source-only update.");
            }
            if ($storedPrinted === null) {
                $changedSourceFields['printed_page'] = true;
            }
            $results[] = ['number'=>$number,'source_id'=>$id,'edition'=>$edition,
                'chapter'=>$chapter,'pdf_page'=>$page,'printed_page'=>$printed,'pdf_sha256'=>$sha];
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
    $receipt = ['format'=>'fanoos.verified-source-page-repair-receipt/1',
        'mode'=>$apply ? 'applied' : 'rollback-preview',
        'year'=>$plan['year'],'subject'=>$plan['subject'],'count'=>count($results),
        'plan_sha256'=>hash('sha256', $raw),
        'questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,
        'source_fields_changed'=>array_keys($changedSourceFields),'sources'=>$results];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $receipt['at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $handle = @fopen((string) $args['receipt'], 'x');
        if ($handle === false) {
            throw new RuntimeException('Committed source-only update; receipt missing. Read back database, do not retry blindly.');
        }
        chmod((string) $args['receipt'], 0600);
        fwrite($handle, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Verified source page repair refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
