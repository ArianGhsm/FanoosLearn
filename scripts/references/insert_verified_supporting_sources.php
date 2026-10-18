<?php

declare(strict_types=1);

/**
 * Atomically append one or two exact-page citations to a residency question
 * that already has exactly one verified AI source. Existing source rows are
 * immutable; this operator inserts only non-primary supporting citations.
 *
 * Preview rolls the transaction back. Apply requires fanoosupd, a fresh
 * verified full backup, and a unique protected receipt.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\SupportingSourceCitationGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Uuid;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--(workspace|plan|apply|backup|receipt)(?:=(.*))?$/D', $arg, $m) !== 1
            || array_key_exists($m[1], $args)) {
            throw new RuntimeException('Unknown or duplicate protected argument.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $apply = array_key_exists('apply', $args);
    if (!preg_match('/^[a-f0-9-]{36}$/D', $workspace)
        || $apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace required; apply needs a verified backup and unused receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Only fanoosupd may apply supporting citations.');
    }

    $classification = realpath('/srv/fanoos/shared/research/classification');
    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($classification === false || $reports === false
        || realpath(dirname($planPath)) !== $reports || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be an existing protected report file.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 32, JSON_THROW_ON_ERROR);
    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditReal = realpath($auditPath);
    $auditSha = $plan['audit_sha256'] ?? null;
    if ($auditReal === false || is_link($auditPath) || !is_file($auditPath)
        || !preg_match('/^[a-f0-9]{64}$/D', (string) $auditSha)
        || hash_file('sha256', $auditPath) !== $auditSha
        || preg_match('~^' . preg_quote($classification, '~') . '/recovery-'
            . preg_quote((string) ($plan['year'] ?? ''), '~')
            . '-[a-zA-Z0-9-]+/classification/reports/[^/]+\.json$~D', $auditReal) !== 1) {
        throw new RuntimeException('Independent protected source audit is absent or changed.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 32, JSON_THROW_ON_ERROR);
    $map = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 32, JSON_THROW_ON_ERROR);
    if (!SupportingSourceCitationGuard::validate($plan, $audit, $map)) {
        throw new RuntimeException('Supporting-source plan, independent audit or SHA-bound page map rejected.');
    }

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $reports || is_link($receiptPath)
            || file_exists($receiptPath) || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Verified full backup or unique protected receipt path rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('A fresh full database-and-storage backup is required.');
        }
    }

    $mapEd = $map['editions'] ?? [];
    $allEvidence = [];
    $existing = $plan['existing_source'];
    $allEvidence[] = [$existing['edition'], $existing['pdf_page'], $existing['evidence'], $existing['pdf_sha256']];
    foreach ($plan['supporting_sources'] as $source) {
        $allEvidence[] = [$source['edition'], $source['pdf_page'], $source['evidence'], $source['pdf_sha256']];
    }
    foreach ($allEvidence as $i => [$edition, $page, $evidence, $sha]) {
        if (($mapEd[$edition]['source_pdf_sha256'] ?? null) !== $sha
            || !is_array($mapEd[$edition]['runs'] ?? null)) {
            throw new RuntimeException('Page map does not match the currently approved PDF.');
        }
        if (!PrintedBookPageEvidence::pageContainsEvidence($edition, $page, [$evidence], $sha)) {
            throw new RuntimeException('Audited quote is not present on its exact approved original PDF page.');
        }
    }

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $question = $db->prepare(<<<'SQL'
SELECT q.id,q.stem,q.status
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=:yr
  AND si.exam_round=1 AND sb.subject_key=:sub AND q.number_in_sitting=:num
FOR UPDATE
SQL);
    $sources = $db->prepare(<<<'SQL'
SELECT s.id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,s.origin,s.is_primary,
       s.reviewed_at,s.reviewed_by_user_id,s.confidence_source,s.confidence_node,s.confidence_page,
       s.scope_unverified,s.official_scope_status,
       e.edition_key,r.reference_key,n.number AS chapter,n.kind,v.scope_chapters,v.is_official
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
 AND v.exam_type_id=et.id AND v.subject_id=sb.id AND v.exam_year=si.exam_year
WHERE s.workspace_id=:ws AND s.question_id=:qid AND si.exam_year=:yr
  AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:sub
FOR UPDATE
SQL);
    $choices = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $answer = $db->prepare('SELECT choice_position,also_correct_positions,status,source,recorded_at FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $node = $db->prepare(<<<'SQL'
SELECT e.id AS edition_id,n.id AS node_id,n.kind,n.number,v.scope_chapters
FROM bank_references r
JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE r.workspace_id=:ws AND r.reference_key=:ref AND e.edition_key=:edition
  AND n.kind='chapter' AND n.number=:chapter AND v.exam_year=:yr
  AND sb.subject_key=:sub AND et.type_key='residency' AND v.is_official=1
SQL);
    $duplicate = $db->prepare(<<<'SQL'
SELECT s.id FROM bank_question_sources s
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
WHERE s.workspace_id=:ws AND s.question_id=:qid AND r.reference_key=:ref
  AND e.edition_key=:edition AND n.number=:chapter AND s.pdf_page=:page
FOR UPDATE
SQL);
    $insert = $db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,node_id,page,pdf_page,printed_page,pdf_sha256,
 anchor_text,is_primary,confidence_source,confidence_node,confidence_page,origin,created_at)
VALUES (:id,:ws,:qid,:edition,:node,:page,:pdf_page,NULL,:sha,:anchor,0,
 0.950,0.950,0.950,'ai',UTC_TIMESTAMP(6))
SQL);

    $db->beginTransaction();
    try {
        $question->execute(['ws'=>$workspace,'yr'=>$plan['year'],'sub'=>$plan['subject'],'num'=>$plan['number']]);
        $q = $question->fetch(PDO::FETCH_ASSOC);
        if (!is_array($q) || $question->fetch(PDO::FETCH_ASSOC) !== false || $q['status'] !== 'published'
            || hash('sha256', (string) $q['stem']) !== $plan['stem_sha256']) {
            throw new RuntimeException('Original published question identity changed.');
        }
        $qid = $q['id'];
        $params = ['ws'=>$workspace,'qid'=>$qid];
        $sources->execute($params + ['yr'=>$plan['year'],'sub'=>$plan['subject']]);
        $rows = $sources->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new RuntimeException('Exactly one existing source row is required; no row was inserted.');
        }
        $s = $rows[0];
        $old = $plan['existing_source'];
        if ($s['id'] !== $old['source_id'] || $s['reference_key'] . '@' . $s['edition_key'] !== $old['edition']
            || (string) $s['chapter'] !== $old['chapter'] || $s['kind'] !== 'chapter'
            || (int) $s['is_official'] !== 1 || $s['pdf_page'] === null
            || (int) $s['pdf_page'] !== $old['pdf_page'] || $s['pdf_sha256'] !== $old['pdf_sha256']
            || $s['printed_page'] !== $old['printed_page'] || hash('sha256', (string) $s['anchor_text']) !== $old['anchor_sha256']
            || $s['origin'] !== 'ai' || $s['reviewed_at'] !== null || $s['reviewed_by_user_id'] !== null
            || (int) $s['is_primary'] !== 1 || (bool) $s['scope_unverified']
            || $s['official_scope_status'] !== 'in_scope'
            || !SupportingSourceCitationGuard::scopeAllows($s['scope_chapters'], $old['chapter'])) {
            throw new RuntimeException('Existing source is not the exact audited, unreviewed official source.');
        }
        $mapRuns = $mapEd[$old['edition']]['runs'] ?? [];
        $oldPageMapped = false;
        foreach ($mapRuns as $run) {
            if (is_array($run) && count($run) === 3 && (string) $run[0] === $old['chapter']
                && $old['pdf_page'] >= $run[1] && $old['pdf_page'] <= $run[2]) {
                $oldPageMapped = true;
                break;
            }
        }
        if (!$oldPageMapped || !PrintedBookPageEvidence::pageContainsEvidence(
            $old['edition'], $old['pdf_page'], [$old['evidence']], $old['pdf_sha256']
        )) {
            throw new RuntimeException('Audited existing-source quote does not match its mapped original PDF page.');
        }

        $choices->execute($params);
        $choiceRows = $choices->fetchAll(PDO::FETCH_ASSOC);
        $answer->execute($params);
        $answerRow = $answer->fetch(PDO::FETCH_ASSOC);
        if (count($choiceRows) !== 4 || !is_array($answerRow)
            || !in_array($answerRow['status'], ['final','amended'], true)
            || $answerRow['choice_position'] === null
            || hash('sha256', json_encode($choiceRows, JSON_THROW_ON_ERROR)) !== $plan['choices_sha256']
            || hash('sha256', json_encode($answerRow, JSON_THROW_ON_ERROR)) !== $plan['answer_sha256']) {
            throw new RuntimeException('Original four choices or latest final official answer changed.');
        }

        $inserted = [];
        foreach ($plan['supporting_sources'] as $source) {
            [$referenceKey, $editionKey] = explode('@', $source['edition'], 2);
            $node->execute(['ws'=>$workspace,'ref'=>$referenceKey,'edition'=>$editionKey,
                'chapter'=>$source['chapter'],'yr'=>$plan['year'],'sub'=>$plan['subject']]);
            $nodes = $node->fetchAll(PDO::FETCH_ASSOC);
            if (count($nodes) !== 1 || $nodes[0]['kind'] !== 'chapter'
                || (string) $nodes[0]['number'] !== $source['chapter']
                || !SupportingSourceCitationGuard::scopeAllows($nodes[0]['scope_chapters'],$source['chapter'])) {
                throw new RuntimeException('Supporting chapter is not uniquely official and fully in scope.');
            }
            $duplicate->execute(['ws'=>$workspace,'qid'=>$qid,'ref'=>$referenceKey,
                'edition'=>$editionKey,'chapter'=>$source['chapter'],'page'=>$source['pdf_page']]);
            if ($duplicate->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException('A citation for this exact edition/chapter/page already exists.');
            }
            $id = Uuid::v7();
            $insert->execute(['id'=>$id,'ws'=>$workspace,'qid'=>$qid,'edition'=>$nodes[0]['edition_id'],
                'node'=>$nodes[0]['node_id'],'page'=>'pdf ' . $source['pdf_page'],
                'pdf_page'=>$source['pdf_page'],'sha'=>$source['pdf_sha256'],'anchor'=>$source['evidence']]);
            if ($insert->rowCount() !== 1) {
                throw new RuntimeException('Atomic supporting-source insertion failed.');
            }
            $inserted[] = ['source_id'=>$id,'edition'=>$source['edition'],'chapter'=>$source['chapter'],
                'pdf_page'=>$source['pdf_page'],'pdf_sha256'=>$source['pdf_sha256']];
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

    $result = ['format'=>'fanoos.verified-supporting-source-citation-receipt/1',
        'mode'=>$apply ? 'applied' : 'rollback-preview','year'=>$plan['year'],'subject'=>$plan['subject'],
        'number'=>$plan['number'],'existing_source_unchanged'=>true,'supporting_sources_inserted'=>$inserted,
        'question_changes'=>0,'choice_changes'=>0,'answer_changes'=>0,'assessment_changes'=>0,
        'plan_sha256'=>hash('sha256',$rawPlan),'audit_sha256'=>$auditSha];
    if ($apply) {
        $result['backup_id'] = basename($backup);
        $result['at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $fp = @fopen($receiptPath, 'x');
        if ($fp === false) {
            throw new RuntimeException('Committed but receipt missing. Inspect production; never retry blindly.');
        }
        chmod($receiptPath, 0600);
        fwrite($fp,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
        fflush($fp);
        fclose($fp);
    }
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR,'Supporting-source insertion refused: '.$error->getMessage().PHP_EOL);
    exit(1);
}
