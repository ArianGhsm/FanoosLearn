<?php

declare(strict_types=1);

/**
 * Guarded metadata-only repair for an exact source located outside the
 * official exam scope. Preview rolls back; --apply requires a fresh backup.
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
        if (preg_match('/^--(workspace|plan|apply|backup|receipt)(?:=(.*))?$/D', $arg, $m) !== 1 || isset($args[$m[1]])) {
            throw new RuntimeException('Unknown or duplicate protected argument.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $apply = isset($args['apply']);
    if (preg_match('/^[a-f0-9-]{36}$/D', $workspace) !== 1 || $apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace is required; apply requires backup and receipt.');
    }
    if ($apply && (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd') {
        throw new RuntimeException('Only the authorized updater service may apply.');
    }
    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be a private protected report.');
    }
    $raw = (string) file_get_contents($planPath);
    $plan = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.outside-scope-source-repair/1' || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid protected plan.');
    }
    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receipt = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/') || realpath(dirname($receipt)) !== $reports
            || is_link($receipt) || file_exists($receipt) || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Protected backup or unique receipt rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 14400 || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh verified full backup required.');
        }
    }
    $maps = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'), true, 32, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $read = $db->prepare(<<<'SQL'
SELECT s.id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,s.official_scope_status,s.official_scope_note,
 q.id question_id,q.stem,q.number_in_sitting,n.number chapter_no,n.kind,e.edition_key,r.reference_key,v.scope_chapters
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id AND v.exam_type_id=et.id AND v.subject_id=sb.id AND v.exam_year=si.exam_year AND v.is_official=1
WHERE s.workspace_id=:ws AND s.id=:sid AND q.number_in_sitting=:number AND si.exam_year=:year AND si.exam_round=1 AND et.type_key='residency'
FOR UPDATE
SQL);
    $choices = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $answer = $db->prepare('SELECT status,choice_position,also_correct_positions,source,recorded_at FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $write = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET page=:source_page,printed_page=:printed,pdf_page=:pdf,pdf_sha256=:sha,
 official_scope_status='outside_scope_exam_question',official_scope_note=:note
WHERE workspace_id=:ws AND id=:sid AND page <=> :old_page AND printed_page <=> :old_printed
 AND pdf_page <=> :old_pdf AND pdf_sha256 <=> :old_sha AND official_scope_status='in_scope' AND official_scope_note IS NULL
SQL);
    $out = [];
    $db->beginTransaction();
    foreach ($plan['entries'] as $item) {
        foreach (['source_id','edition','chapter','printed_page','pdf_page','pdf_sha256','note','evidence','expected_stem_sha256','expected_choices_sha256','expected_answer_sha256','expected_anchor_sha256'] as $field) {
            if (!array_key_exists($field, $item)) throw new RuntimeException('Missing protected field: ' . $field);
        }
        $number = $item['number'] ?? null;
        $printedLabelAvailable = is_string($item['printed_page'])
            && preg_match('/^[1-9][0-9]{0,3}$/D', $item['printed_page']) === 1;
        $printedLabelUnavailable = array_key_exists('printed_page', $item) && $item['printed_page'] === null
            && ($item['printed_page_label_unavailable'] ?? null) === true
            && str_contains((string) $item['note'], 'شماره صفحه چاپی در PDF ثبت نشده');
        if (!is_int($number) || !is_int($item['pdf_page']) || preg_match('/^[a-f0-9]{64}$/D', (string) $item['pdf_sha256']) !== 1
            || mb_strlen(trim((string) $item['note'])) < 12 || !str_contains((string) $item['note'], 'خارج از محدوده')) {
            throw new RuntimeException('Invalid outside-scope entry.');
        }
        if (!$printedLabelAvailable && !$printedLabelUnavailable) {
            throw new RuntimeException('Printed page must be exact or explicitly unavailable on this PDF page.');
        }
        $read->execute(['ws'=>$workspace,'sid'=>$item['source_id'],'number'=>$number,'year'=>$plan['year']]);
        $rows = $read->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) throw new RuntimeException("Q{$number}: source identity is not unique.");
        $s = $rows[0];
        $choices->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
        $choiceRows = $choices->fetchAll(PDO::FETCH_ASSOC);
        $answer->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
        $answerRow = $answer->fetch(PDO::FETCH_ASSOC);
        if ($s['reference_key'].'@'.$s['edition_key'] !== $item['edition'] || (string) $s['chapter_no'] !== (string) $item['chapter'] || $s['kind'] !== 'chapter'
            || hash('sha256', (string) $s['stem']) !== $item['expected_stem_sha256'] || hash('sha256', (string) $s['anchor_text']) !== $item['expected_anchor_sha256']
            || hash('sha256', json_encode($choiceRows, JSON_THROW_ON_ERROR)) !== $item['expected_choices_sha256']
            || !is_array($answerRow) || hash('sha256', json_encode($answerRow, JSON_THROW_ON_ERROR)) !== $item['expected_answer_sha256']
            || $s['page'] !== ($item['expected_page'] ?? null) || $s['printed_page'] !== ($item['expected_printed_page'] ?? null)
            || ($s['pdf_page'] === null ? null : (int) $s['pdf_page']) !== ($item['expected_pdf_page'] ?? null)
            || $s['pdf_sha256'] !== ($item['expected_pdf_sha256'] ?? null) || $s['official_scope_status'] !== 'in_scope' || $s['official_scope_note'] !== null) {
            throw new RuntimeException("Q{$number}: protected source or question changed.");
        }
        if (OfficialScopePageGuard::pageDisposition(
            $s['scope_chapters'], (string) $item['chapter'], $item['printed_page'],
        ) !== false) {
            throw new RuntimeException("Q{$number}: book chapter/page is inside or ambiguous against official scope.");
        }
        $map = $maps['editions'][$item['edition']] ?? null;
        $inside = false;
        foreach (($map['runs'] ?? []) as $run) if ((string) $run[0] === (string) $item['chapter'] && $item['pdf_page'] >= $run[1] && $item['pdf_page'] <= $run[2]) $inside = true;
        $paginationVerified = $printedLabelAvailable
            ? PrintedBookPageEvidence::corroborates($item['edition'], $item['pdf_page'], $item['printed_page'], $item['pdf_sha256'])
            : PrintedBookPageEvidence::hasNoPrintedPageLabel($item['edition'], $item['pdf_page'], $item['pdf_sha256']);
        if (($map['source_pdf_sha256'] ?? null) !== $item['pdf_sha256'] || !$inside || !$paginationVerified
            || !PrintedBookPageEvidence::pageContainsEvidence($item['edition'], $item['pdf_page'], [(string) $item['evidence']], $item['pdf_sha256'])) {
            throw new RuntimeException("Q{$number}: exact original-page evidence failed.");
        }
        $sourcePage = $printedLabelAvailable ? $item['printed_page'] : 'pdf ' . $item['pdf_page'];
        $write->execute(['source_page'=>$sourcePage,'printed'=>$item['printed_page'],'pdf'=>$item['pdf_page'],'sha'=>$item['pdf_sha256'],'note'=>$item['note'],
            'ws'=>$workspace,'sid'=>$item['source_id'],'old_page'=>$item['expected_page'] ?? null,'old_printed'=>$item['expected_printed_page'] ?? null,
            'old_pdf'=>$item['expected_pdf_page'] ?? null,'old_sha'=>$item['expected_pdf_sha256'] ?? null]);
        if ($write->rowCount() !== 1) throw new RuntimeException("Q{$number}: guarded write ambiguous.");
        $out[] = ['number'=>$number,'source_id'=>$item['source_id'],'edition'=>$item['edition'],'chapter'=>(string)$item['chapter'],
            'page'=>$sourcePage,'printed_page'=>$item['printed_page'],'printed_page_label_unavailable'=>$printedLabelUnavailable,
            'pdf_page'=>$item['pdf_page'],'pdf_sha256'=>$item['pdf_sha256'],'official_scope_status'=>'outside_scope_exam_question','official_scope_note'=>$item['note']];
    }
    if ($apply) $db->commit(); else $db->rollBack();
    $result = ['format'=>'fanoos.outside-scope-source-repair-receipt/1','mode'=>$apply?'applied':'rollback-preview','year'=>$plan['year'],
        'count'=>count($out),'plan_sha256'=>hash('sha256',$raw),'only_fields_changed'=>['page','printed_page','pdf_page','pdf_sha256','official_scope_status','official_scope_note'],
        'questions_changed'=>0,'choices_changed'=>0,'official_answers_changed'=>0,'sources'=>$out];
    if ($apply) {
        $result['backup_id'] = basename($backup); $result['at_utc'] = gmdate('Y-m-d\\TH:i:s\\Z');
        $fp = fopen($receipt, 'x'); if ($fp === false) throw new RuntimeException('Committed but receipt creation failed; read back and do not retry.');
        chmod($receipt, 0600); fwrite($fp, json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n"); fclose($fp);
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Outside-scope source repair refused: '.$e->getMessage().PHP_EOL); exit(1);
}
