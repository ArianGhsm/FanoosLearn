<?php

declare(strict_types=1);

/**
 * Fill a missing printed-page label on a pre-existing, PDF-bound AI source.
 * The only database field changed is printed_page. Preview rolls back; apply
 * requires a fresh verified full backup and a unique private receipt.
 */
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VerifiedSourcePrintedLabelGuard;
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
            throw new RuntimeException('Unknown or repeated protected argument.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $apply = isset($args['apply']);
    if (!preg_match('/^[a-f0-9-]{36}$/D', $workspace)
        || $apply !== isset($args['backup'])
        || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace required; apply also needs backup and receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Only the authorized updater service account may apply.');
    }

    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be an existing protected private report.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 48, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.verified-source-printed-label-repair/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid protected printed-label plan scope or size.');
    }
    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = strtolower((string) ($plan['audit_sha256'] ?? ''));
    if (realpath(dirname($auditPath)) !== $reports || is_link($auditPath) || !is_file($auditPath)
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha) || hash_file('sha256', $auditPath) !== $auditSha) {
        throw new RuntimeException('Independent printed-label audit is absent or changed.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 64, JSON_THROW_ON_ERROR);
    if (($audit['year'] ?? null) !== $plan['year'] || ($audit['subject'] ?? null) !== $plan['subject']) {
        throw new RuntimeException('Audit and plan scope differ.');
    }

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $reports
            || is_link($receiptPath) || file_exists($receiptPath) || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Verified backup or unique private receipt rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh fully verified production/database backup required.');
        }
    }

    $maps = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 48, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.workspace_id,s.question_id,s.node_id,s.edition_id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,
       s.anchor_text,s.origin,s.reviewed_at,s.reviewed_by_user_id,s.confidence_page,s.scope_unverified,
       s.official_scope_status,s.official_scope_note,
       q.number_in_sitting,q.stem,q.status question_status,
       n.number chapter_no,n.kind,e.edition_key,r.reference_key,v.scope_chapters,v.is_official
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
WHERE s.workspace_id=:ws AND s.id=:id AND q.number_in_sitting=:number
 AND si.exam_year=:year AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:subject
 AND v.is_official=1
FOR UPDATE
SQL);
    $choicesQuery = $db->prepare(
        'SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position'
    );
    $answerQuery = $db->prepare(
        'SELECT status,choice_position,also_correct_positions,source,recorded_at FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1'
    );
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET printed_page=:printed
WHERE workspace_id=:ws AND id=:id AND printed_page IS NULL
 AND page <=> :old_page AND pdf_page=:pdf_page AND pdf_sha256=:pdf_sha
 AND origin=:origin AND reviewed_at <=> :reviewed_at AND reviewed_by_user_id <=> :reviewed_by_user_id
 AND confidence_page <=> :confidence_page AND official_scope_status='in_scope'
 AND official_scope_note IS NULL AND scope_unverified=0 AND anchor_text <=> :anchor
SQL);

    $seen = [];
    $results = [];
    $db->beginTransaction();
    try {
        foreach ($plan['entries'] as $item) {
            $number = $item['number'] ?? null;
            if (!is_int($number) || $number < 1 || $number > 300 || isset($seen[$number])) {
                throw new RuntimeException('Invalid or duplicate question number.');
            }
            $seen[$number] = true;
            foreach ([
                'source_id','edition','chapter','pdf_page','printed_page','pdf_sha256','expected_page',
                'expected_pdf_page','expected_pdf_sha256','expected_confidence_page','expected_anchor_sha256',
                'expected_stem_sha256','expected_choices_sha256','expected_answer_sha256',
                'expected_question_status','expected_answer_status','expected_origin',
                'expected_reviewed_at','expected_reviewed_by_user_id',
            ] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new RuntimeException("Q{$number}: missing {$field}.");
                }
            }
            $id=$item['source_id'];$edition=$item['edition'];$chapter=$item['chapter'];
            $pdfPage=$item['pdf_page'];$printed=$item['printed_page'];$sha=strtolower((string)$item['pdf_sha256']);
            $oldPdfPage=$item['expected_pdf_page'];$oldPdfSha=$item['expected_pdf_sha256'];
            $oldConfidence=$item['expected_confidence_page'];
            $expectedOrigin=$item['expected_origin'];
            $expectedReviewedAt=$item['expected_reviewed_at'];
            $expectedReviewerId=$item['expected_reviewed_by_user_id'];
            if (!is_string($id)||preg_match('/^[a-f0-9-]{36}$/D',$id)!==1
                || !is_string($edition)||preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D',$edition)!==1
                || !is_string($chapter)||preg_match('/^[0-9]+(?:\.[0-9]+)?$/D',$chapter)!==1
                || !is_int($pdfPage)||$pdfPage<1||$pdfPage>6000
                || !is_string($printed)||preg_match('/^[1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?$/D',$printed)!==1
                || !is_string($sha)||preg_match('/^[a-f0-9]{64}$/D',$sha)!==1
                || !is_int($oldPdfPage)||$oldPdfPage!==$pdfPage||$oldPdfSha!==$sha
                || !in_array($expectedOrigin,['ai','human'],true)
                || ($expectedReviewedAt!==null&&!is_string($expectedReviewedAt))
                || ($expectedReviewerId!==null&&(!is_string($expectedReviewerId)||preg_match('/^[a-f0-9-]{36}$/D',$expectedReviewerId)!==1))
                || ($expectedOrigin==='ai'&&(!is_string($oldConfidence)||!is_numeric($oldConfidence)||(float)$oldConfidence<0.85))
                || ($expectedOrigin==='ai'&&($expectedReviewedAt!==null||$expectedReviewerId!==null))
                || ($expectedOrigin==='human'&&$oldConfidence!==null&&!is_numeric($oldConfidence))
                || !is_string($item['expected_page'])
                || !is_string($item['expected_answer_status'])) {
                throw new RuntimeException("Q{$number}: invalid printed-label-only source identity.");
            }
            if (!VerifiedSourcePrintedLabelGuard::auditMatches($audit,$plan,$item)) {
                throw new RuntimeException("Q{$number}: independent label audit differs from plan.");
            }
            $sourceQuery->execute(['ws'=>$workspace,'id'=>$id,'number'=>$number,'year'=>$plan['year'],'subject'=>$plan['subject']]);
            $rows=$sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)!==1)throw new RuntimeException("Q{$number}: source/official validity identity is not unique.");
            $s=$rows[0];
            $storedConfidence=$s['confidence_page']===null?null:number_format((float)$s['confidence_page'],3,'.','');
            $expectedConfidence=$oldConfidence===null?null:number_format((float)$oldConfidence,3,'.','');
            if($s['question_status']!==$item['expected_question_status']||$s['question_status']!=='published'
                ||$s['origin']!==$expectedOrigin||$s['reviewed_at']!==$expectedReviewedAt
                ||$s['reviewed_by_user_id']!==$expectedReviewerId||$storedConfidence!==$expectedConfidence
                ||$s['printed_page']!==null||$s['page']!==$item['expected_page']
                ||(int)$s['pdf_page']!==$oldPdfPage||$s['pdf_sha256']!==$oldPdfSha
                ||$s['scope_unverified']
                ||$s['official_scope_status']!=='in_scope'||$s['official_scope_note']!==null
                ||$s['reference_key'].'@'.$s['edition_key']!==$edition||(string)$s['chapter_no']!==$chapter
                ||$s['kind']!=='chapter'||(int)$s['is_official']!==1
                ||hash('sha256',(string)$s['anchor_text'])!==$item['expected_anchor_sha256']
                ||hash('sha256',(string)$s['stem'])!==$item['expected_stem_sha256']) {
                throw new RuntimeException("Q{$number}: source or question state changed.");
            }
            if (OfficialScopePageGuard::pageDisposition($s['scope_chapters'],$chapter,$printed)!==true) {
                throw new RuntimeException("Q{$number}: printed label is outside or ambiguous against official page scope.");
            }
            $mapEntry=$maps['editions'][$edition]??null;
            $inside=false;foreach($mapEntry['runs']??[] as $run)if((string)$run[0]===$chapter&&$pdfPage>=$run[1]&&$pdfPage<=$run[2])$inside=true;
            if(($mapEntry['source_pdf_sha256']??null)!==$sha||!$inside
                ||!PrintedBookPageEvidence::corroborates($edition,$pdfPage,$printed,$sha)) {
                throw new RuntimeException("Q{$number}: exact SHA-bound page label or chapter map failed.");
            }
            $choicesQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$choices=$choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$answer=$answerQuery->fetch(PDO::FETCH_ASSOC);
            if(count($choices)!==4||hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR))!==$item['expected_choices_sha256']
                ||!is_array($answer)||$answer['status']!==$item['expected_answer_status']
                ||!in_array($answer['status'],['final','amended'],true)||$answer['choice_position']===null
                ||hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR))!==$item['expected_answer_sha256']) {
                throw new RuntimeException("Q{$number}: choices or official answer changed.");
            }
            $update->execute(['printed'=>$printed,'ws'=>$workspace,'id'=>$id,'old_page'=>$s['page'],
                'pdf_page'=>$pdfPage,'pdf_sha'=>$sha,'origin'=>$expectedOrigin,
                'reviewed_at'=>$expectedReviewedAt,'reviewed_by_user_id'=>$expectedReviewerId,
                'confidence_page'=>$oldConfidence,'anchor'=>$s['anchor_text']]);
            if($update->rowCount()!==1)throw new RuntimeException("Q{$number}: printed-label-only update ambiguous.");
            $results[]=['number'=>$number,'source_id'=>$id,'edition'=>$edition,'chapter'=>$chapter,
                'origin'=>$expectedOrigin,'human_review_metadata_preserved'=>true,
                'pdf_page'=>$pdfPage,'printed_page'=>$printed,'pdf_sha256'=>$sha,'confidence_page'=>$oldConfidence];
        }
        if($apply)$db->commit();else$db->rollBack();
    } catch(Throwable $error) {
        if($db->inTransaction())$db->rollBack();
        throw $error;
    }
    $receipt=['format'=>'fanoos.verified-source-printed-label-repair-receipt/1','mode'=>$apply?'applied':'rollback-preview',
        'year'=>$plan['year'],'subject'=>$plan['subject'],'count'=>count($results),'plan_sha256'=>hash('sha256',$rawPlan),
        'audit_sha256'=>$auditSha,'only_fields_changed'=>['printed_page'],'questions_changed'=>0,'choices_changed'=>0,
        'answers_changed'=>0,'assessment_versions_changed'=>0,'attempts_changed'=>0,'sources'=>$results];
    if($apply){$receipt['backup_id']=basename($backup);$receipt['at_utc']=gmdate('c');$handle=fopen($receiptPath,'x');
        if($handle===false)throw new RuntimeException('Committed but receipt creation failed; read back before retry.');
        chmod($receiptPath,0600);fwrite($handle,json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");fflush($handle);fclose($handle);}
    echo json_encode($receipt,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) {
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    fwrite(STDERR,'Verified printed-page label repair refused: '.$error->getMessage().PHP_EOL);exit(1);
}
