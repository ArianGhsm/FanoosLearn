<?php

declare(strict_types=1);

/**
 * Record a verified same-edition, same-chapter page bundle for a DRAFT
 * residency question with an officially VOIDED answer. The existing primary
 * citation is re-anchored to its audited page; other pages are inserted as
 * secondary citations. No question, choice, key, assessment, review or attempt
 * data is written.
 *
 * Preview:
 *   php scripts/references/repair_verified_voided_source_page_bundle.php \
 *     --workspace=UUID --plan=/srv/fanoos/shared/research/classification/reports/PLAN.json
 * Apply adds --apply --backup=/var/backups/fanoos/BACKUP and a unique
 * --receipt=/srv/fanoos/shared/research/classification/reports/RECEIPT.json.
 */
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VoidedSourcePageBundleGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Uuid;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

/** @return array<string,mixed> */
function voidedBundleImmutableSnapshot(PDO $db, string $workspace, string $questionId): array
{
    $question = $db->prepare(<<<'SQL'
SELECT stem,status FROM bank_questions WHERE workspace_id=:ws AND id=:qid FOR UPDATE
SQL);
    $question->execute(['ws'=>$workspace,'qid'=>$questionId]);
    $row = $question->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('Question identity disappeared during the source transaction.');
    }

    $choicesQuery = $db->prepare(<<<'SQL'
SELECT position,text,image FROM bank_question_choices
WHERE workspace_id=:ws AND question_id=:qid ORDER BY position FOR UPDATE
SQL);
    $choicesQuery->execute(['ws'=>$workspace,'qid'=>$questionId]);
    $choices = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);

    $answerQuery = $db->prepare(<<<'SQL'
SELECT status,choice_position,also_correct_positions,source,recorded_at
FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $answerQuery->execute(['ws'=>$workspace,'qid'=>$questionId]);
    $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
    if (count($choices) !== 4 || !is_array($answer)) {
        throw new RuntimeException('Four choices or official key snapshot is missing.');
    }

    return [
        'question_status'=>(string)$row['status'],
        'stem_sha256'=>hash('sha256',(string)$row['stem']),
        'choices_sha256'=>hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR)),
        'answer_status'=>(string)$answer['status'],
        'answer_choice_position'=>$answer['choice_position']===null?null:(int)$answer['choice_position'],
        'answer_sha256'=>hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR)),
    ];
}

/** @return array<string,mixed> */
function voidedBundleAssessmentSnapshot(PDO $db, string $workspace, string $sittingId): array
{
    $statement = $db->prepare(<<<'SQL'
SELECT a.id assessment_id,a.status,a.current_version_no,v.id version_id,
       LOWER(HEX(v.checksum_sha256)) version_sha256,
       (SELECT COUNT(*) FROM exam_attempts x WHERE x.workspace_id=a.workspace_id AND x.assessment_id=a.id) attempts,
       (SELECT COUNT(*) FROM exam_version_reviews r WHERE r.workspace_id=a.workspace_id AND r.assessment_id=a.id) reviews
FROM exam_assessments a
JOIN bank_exam_sittings s ON s.assessment_id=a.id AND s.workspace_id=a.workspace_id
JOIN exam_assessment_versions v ON v.assessment_id=a.id AND v.workspace_id=a.workspace_id
 AND v.version_no=a.current_version_no
WHERE a.workspace_id=:ws AND s.id=:sitting
FOR UPDATE
SQL);
    $statement->execute(['ws'=>$workspace,'sitting'=>$sittingId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || $statement->fetch(PDO::FETCH_ASSOC) !== false) {
        throw new RuntimeException('Expected one current frozen assessment version for the sitting.');
    }

    return [
        'assessment_id'=>(string)$row['assessment_id'],
        'status'=>(string)$row['status'],
        'current_version_no'=>(int)$row['current_version_no'],
        'version_id'=>(string)$row['version_id'],
        'version_sha256'=>strtolower((string)$row['version_sha256']),
        'attempts'=>(int)$row['attempts'],
        'reviews'=>(int)$row['reviews'],
    ];
}

/** @param array<string,mixed> $payload */
function voidedBundleWriteReceipt(string $path, array $payload): void
{
    $json=json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    $handle=@fopen($path,'x');
    if($handle===false)throw new RuntimeException('Could not create unique protected receipt.');
    chmod($path,0600);
    try{
        if(fwrite($handle,$json)!==strlen($json)||!fflush($handle))throw new RuntimeException('Could not write complete receipt.');
    }catch(Throwable $error){fclose($handle);@unlink($path);throw $error;}
    fclose($handle);
}

try {
    $args=[];
    foreach(array_slice($argv,1) as $argument){
        if(preg_match('/^--(workspace|plan|apply|backup|receipt)(?:=(.*))?$/D',$argument,$match)!==1
            ||array_key_exists($match[1],$args))throw new RuntimeException('Unknown or duplicate protected option.');
        $args[$match[1]]=$match[2]??'1';
    }
    $workspace=(string)($args['workspace']??'');$apply=array_key_exists('apply',$args);
    if(!preg_match('/^[a-f0-9-]{36}$/D',$workspace)
        ||$apply!==isset($args['backup'])||$apply!==isset($args['receipt']))
        throw new RuntimeException('Workspace is required; apply also needs a backup and unique receipt.');
    if($apply&&(!function_exists('posix_getpwuid')||(posix_getpwuid(posix_geteuid())['name']??null)!=='fanoosupd'))
        throw new RuntimeException('Only the authorized updater service account may apply.');

    $reports=realpath('/srv/fanoos/shared/research/classification/reports');
    $classification=realpath('/srv/fanoos/shared/research/classification');
    $planPath=(string)($args['plan']??'');
    if($reports===false||realpath(dirname($planPath))!==$reports||is_link($planPath)||!is_file($planPath)
        ||(fileperms($planPath)&0077)!==0)throw new RuntimeException('Plan must be mode-0600 in protected reports.');
    $rawPlan=(string)file_get_contents($planPath);$plan=json_decode($rawPlan,true,64,JSON_THROW_ON_ERROR);
    if(!VoidedSourcePageBundleGuard::planMatches($plan))throw new RuntimeException('Protected plan or page bundle shape rejected.');

    $auditPath=(string)($plan['audit_path']??'');$auditReal=realpath($auditPath);
    $auditAllowed=$auditReal!==false&&$classification!==false
        &&(realpath(dirname($auditPath))===$reports
            ||preg_match('~^'.preg_quote($classification,'~').'/recovery-'.(string)$plan['year']
                .'-[a-zA-Z0-9-]+/classification/reports/[^/]+\.json$~D',$auditReal)===1);
    if(!$auditAllowed||is_link($auditPath)||!is_file($auditPath)||(fileperms($auditPath)&0077)!==0
        ||!preg_match('/^[a-f0-9]{64}$/D',(string)($plan['audit_sha256']??''))
        ||hash_file('sha256',$auditReal)!==$plan['audit_sha256'])
        throw new RuntimeException('Independent private audit is absent, public or changed.');
    $audit=json_decode((string)file_get_contents($auditReal),true,64,JSON_THROW_ON_ERROR);
    foreach($plan['entries'] as $entry)if(!VoidedSourcePageBundleGuard::auditMatches($audit,$plan,$entry))
        throw new RuntimeException('Independent multi-page audit does not match the exact plan.');

    if($apply){
        $backup=realpath((string)$args['backup']);$receiptPath=(string)$args['receipt'];
        if($backup===false||!str_starts_with($backup.'/','/var/backups/fanoos/')
            ||realpath(dirname($receiptPath))!==$reports||is_link($receiptPath)||file_exists($receiptPath)
            ||!str_ends_with($receiptPath,'.json'))throw new RuntimeException('Verified backup or unique receipt path rejected.');
        $manifest=BackupManifest::verify($backup);$created=strtotime((string)($manifest['created_at']??''));
        if($created===false||abs(time()-$created)>4*3600||(int)($manifest['files']['database.sql']['bytes']??0)<1024)
            throw new RuntimeException('Fresh independently verified full database backup required.');
    }

    $map=json_decode((string)file_get_contents($root.'/data/bank/reference-chapter-pages.json'),true,48,JSON_THROW_ON_ERROR);
    RuntimeConfig::load();$db=DatabaseConnection::fromEnvironment();
    $lock=$db->query("SELECT GET_LOCK('fanoos:verified-voided-source-page-bundle',30)")->fetchColumn();
    if((int)$lock!==1)throw new RuntimeException('Could not acquire the voided-source bundle writer lock.');

    $sourceQuery=$db->prepare(<<<'SQL'
SELECT s.id,s.workspace_id,s.question_id,s.node_id,s.edition_id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,
       s.anchor_text,s.is_primary,s.origin,s.reviewed_at,s.reviewed_by_user_id,
       s.confidence_source,s.confidence_node,s.confidence_page,s.scope_unverified,
       s.official_scope_status,s.official_scope_note,q.stem,q.status question_status,si.id sitting_id,
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
WHERE s.workspace_id=:ws AND s.id=:source_id AND q.number_in_sitting=:number
 AND si.exam_year=:year AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:subject
 AND v.is_official=1
FOR UPDATE
SQL);
    $sourceIds=$db->prepare('SELECT id FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid ORDER BY id FOR UPDATE');
    $nodeQuery=$db->prepare(<<<'SQL'
SELECT n.id node_id,e.id edition_id,n.kind,n.number,v.scope_chapters,v.is_official
FROM bank_reference_nodes n
JOIN bank_reference_editions e ON e.id=n.edition_id AND e.workspace_id=n.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE n.workspace_id=:ws AND e.id=:edition_id AND n.kind='chapter' AND n.number=:chapter
 AND v.exam_year=:year AND sb.subject_key=:subject AND et.type_key='residency' AND v.is_official=1
SQL);
    $choicesQuery=$db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position FOR UPDATE');
    $answerQuery=$db->prepare('SELECT status,choice_position,also_correct_positions,source,recorded_at FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1 FOR UPDATE');
    $updatePrimary=$db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET page=:new_page,printed_page=:new_printed,pdf_page=:new_pdf_page,pdf_sha256=:new_pdf_sha,
 anchor_text=:new_anchor,confidence_source=:confidence_source,confidence_node=:confidence_node,confidence_page=:confidence_page
WHERE workspace_id=:ws AND id=:source_id AND question_id=:question_id
 AND page <=> :old_page AND printed_page <=> :old_printed
 AND pdf_page <=> :old_pdf_page AND pdf_sha256 <=> :old_pdf_sha
 AND anchor_text <=> :old_anchor AND is_primary=1 AND origin='ai'
 AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL AND scope_unverified=0
 AND official_scope_status='in_scope' AND official_scope_note IS NULL
 AND confidence_source=:old_confidence_source AND confidence_node=:old_confidence_node
 AND confidence_page=:old_confidence_page
SQL);
    $insertSecondary=$db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,node_id,page,pdf_page,printed_page,pdf_sha256,anchor_text,is_primary,
 confidence_source,confidence_node,confidence_page,origin,created_at)
VALUES (:id,:ws,:qid,:edition_id,:node_id,:page,:pdf_page,:printed_page,:pdf_sha,:anchor,FALSE,
 :confidence_source,:confidence_node,:confidence_page,'ai',UTC_TIMESTAMP(6))
SQL);
    $duplicate=$db->prepare(<<<'SQL'
SELECT id FROM bank_question_sources
WHERE workspace_id=:ws AND question_id=:qid AND edition_id=:edition_id AND node_id=:node_id AND pdf_page=:pdf_page
FOR UPDATE
SQL);

    $db->beginTransaction();$receiptWritten='';
    try{
        $sittingQuery=$db->prepare(<<<'SQL'
SELECT si.id FROM bank_exam_sittings si JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
WHERE si.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=:year AND si.exam_round=1
SQL);
        $sittingQuery->execute(['ws'=>$workspace,'year'=>$plan['year']]);$sittings=$sittingQuery->fetchAll(PDO::FETCH_COLUMN);
        if(count($sittings)!==1)throw new RuntimeException('Exact residency sitting identity is not unique.');
        $assessmentBefore=voidedBundleAssessmentSnapshot($db,$workspace,(string)$sittings[0]);
        $changes=[];$immutableBefore=[];$immutableAfter=[];
        foreach($plan['entries'] as $item){
            $sourceQuery->execute(['ws'=>$workspace,'source_id'=>$item['source_id'],'number'=>$item['number'],
                'year'=>$plan['year'],'subject'=>$plan['subject']]);
            $rows=$sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)!==1)throw new RuntimeException('Question/source/official-reference identity is not unique.');
            $s=$rows[0];$edition=$s['reference_key'].'@'.$s['edition_key'];$oldPdfPage=$s['pdf_page']===null?null:(int)$s['pdf_page'];
            if($s['question_status']!=='draft'||$s['origin']!=='ai'||$s['reviewed_at']!==null||$s['reviewed_by_user_id']!==null
                ||(int)$s['is_primary']!==1||(bool)$s['scope_unverified']||$s['official_scope_status']!=='in_scope'
                ||$s['official_scope_note']!==null||$edition!==$item['edition']||(string)$s['chapter_no']!==$item['chapter']
                ||$s['kind']!=='chapter'||(int)$s['is_official']!==1||$s['page']!==$item['expected_source_page']
                ||$s['printed_page']!==$item['expected_source_printed_page']||$oldPdfPage!==$item['expected_source_pdf_page']
                ||strtolower((string)$s['pdf_sha256'])!==$item['expected_source_pdf_sha256']
                ||hash('sha256',(string)$s['anchor_text'])!==$item['expected_source_anchor_sha256']
                ||$s['reviewed_at']!==$item['expected_source_reviewed_at']
                ||$s['reviewed_by_user_id']!==$item['expected_source_reviewed_by_user_id']
                ||number_format((float)$s['confidence_source'],3,'.','')!==$item['expected_confidence_source']
                ||number_format((float)$s['confidence_node'],3,'.','')!==$item['expected_confidence_node']
                ||number_format((float)$s['confidence_page'],3,'.','')!==$item['expected_confidence_page'])
                throw new RuntimeException('Original primary source or draft/voided question state differs from plan.');
            $sourceIds->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$existingSources=$sourceIds->fetchAll(PDO::FETCH_COLUMN);
            if(count($existingSources)!==1||$existingSources[0]!==$item['source_id'])
                throw new RuntimeException('Citation count changed; exactly one pinned existing source is required.');
            $choicesQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$choices=$choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$answer=$answerQuery->fetch(PDO::FETCH_ASSOC);
            $snapshot=['question_status'=>$s['question_status'],'stem_sha256'=>hash('sha256',(string)$s['stem']),
                'choices_sha256'=>hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR)),
                'answer_status'=>is_array($answer)?$answer['status']:null,
                'answer_choice_position'=>is_array($answer)?$answer['choice_position']:null,
                'answer_sha256'=>is_array($answer)?hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR)):null];
            if($snapshot['question_status']!=='draft'||$snapshot['stem_sha256']!==$item['expected_stem_sha256']
                ||count($choices)!==4||$snapshot['choices_sha256']!==$item['expected_choices_sha256']
                ||!is_array($answer)||$answer['status']!=='voided'||$answer['choice_position']!==null
                ||$snapshot['answer_sha256']!==$item['expected_answer_sha256'])
                throw new RuntimeException('Original question, choices, draft state or voided key changed.');
            $immutableBefore[(string)$item['number']]=$snapshot;

            $mapEntry=$map['editions'][$edition]??null;
            if(!is_array($mapEntry)||($mapEntry['source_pdf_sha256']??null)!==$item['pdf_sha256']||!is_array($mapEntry['runs']??null))
                throw new RuntimeException('Current page map does not match the approved PDF.');
            $pageRecords=[];
            foreach($item['pages'] as $pageItem){
                $mapped=false;foreach($mapEntry['runs'] as $run)if(is_array($run)&&count($run)===3
                    &&(string)$run[0]===$item['chapter']&&$pageItem['pdf_page']>=$run[1]&&$pageItem['pdf_page']<=$run[2]){$mapped=true;break;}
                if(!$mapped||!PrintedBookPageEvidence::corroborates($edition,$pageItem['pdf_page'],$pageItem['printed_page'],$item['pdf_sha256'])
                    ||!PrintedBookPageEvidence::pageContainsEvidence($edition,$pageItem['pdf_page'],$pageItem['evidence'],$item['pdf_sha256'])
                    ||OfficialScopePageGuard::pageDisposition($s['scope_chapters'],$item['chapter'],$pageItem['printed_page'])!==true)
                    throw new RuntimeException('Bundle page failed map, SHA, printed-label, scope or original-page evidence.');
                $pageRecords[]=['pdf_page'=>$pageItem['pdf_page'],'printed_page'=>$pageItem['printed_page'],
                    'evidence'=>$pageItem['evidence'],'anchor_text'=>implode('...',$pageItem['evidence'])];
            }
            $nodeQuery->execute(['ws'=>$workspace,'edition_id'=>$s['edition_id'],'chapter'=>$item['chapter'],
                'year'=>$plan['year'],'subject'=>$plan['subject']]);$nodes=$nodeQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($nodes)!==1||$nodes[0]['kind']!=='chapter'||(string)$nodes[0]['number']!==$item['chapter']||(int)$nodes[0]['is_official']!==1)
                throw new RuntimeException('Official chapter node is not unique.');
            $primary=array_values(array_filter($pageRecords,static fn(array $page):bool=>$page['pdf_page']===$item['primary_pdf_page']));
            if(count($primary)!==1||$primary[0]['printed_page']!==$item['primary_printed_page'])
                throw new RuntimeException('Primary citation does not match one exact audited page.');

            $updatePrimary->execute(['new_page'=>$primary[0]['printed_page'],'new_printed'=>$primary[0]['printed_page'],
                'new_pdf_page'=>$primary[0]['pdf_page'],'new_pdf_sha'=>$item['pdf_sha256'],'new_anchor'=>$primary[0]['anchor_text'],
                'confidence_source'=>$item['accepted_confidence'],'confidence_node'=>$item['accepted_confidence'],
                'confidence_page'=>$item['accepted_confidence'],'ws'=>$workspace,'source_id'=>$s['id'],'question_id'=>$s['question_id'],
                'old_page'=>$s['page'],'old_printed'=>$s['printed_page'],'old_pdf_page'=>$s['pdf_page'],'old_pdf_sha'=>$s['pdf_sha256'],
                'old_anchor'=>$s['anchor_text'],'old_confidence_source'=>$item['expected_confidence_source'],
                'old_confidence_node'=>$item['expected_confidence_node'],'old_confidence_page'=>$item['expected_confidence_page']]);
            if($updatePrimary->rowCount()!==1)throw new RuntimeException('Primary source compare-and-swap was ambiguous.');
            $secondarySources=[];
            foreach($pageRecords as $pageRecord){
                if($pageRecord['pdf_page']===$item['primary_pdf_page'])continue;
                $duplicate->execute(['ws'=>$workspace,'qid'=>$s['question_id'],'edition_id'=>$s['edition_id'],
                    'node_id'=>$s['node_id'],'pdf_page'=>$pageRecord['pdf_page']]);
                if($duplicate->fetch(PDO::FETCH_ASSOC)!==false)throw new RuntimeException('Duplicate edition/chapter/PDF-page source exists.');
                $newId=Uuid::v7();
                $insertSecondary->execute(['id'=>$newId,'ws'=>$workspace,'qid'=>$s['question_id'],'edition_id'=>$s['edition_id'],
                    'node_id'=>$s['node_id'],'page'=>$pageRecord['printed_page'],'pdf_page'=>$pageRecord['pdf_page'],
                    'printed_page'=>$pageRecord['printed_page'],'pdf_sha'=>$item['pdf_sha256'],'anchor'=>$pageRecord['anchor_text'],
                    'confidence_source'=>$item['accepted_confidence'],'confidence_node'=>$item['accepted_confidence'],
                    'confidence_page'=>$item['accepted_confidence']]);
                if($insertSecondary->rowCount()!==1)throw new RuntimeException('Secondary page citation insert failed.');
                $secondarySources[]=['source_id'=>$newId,'chapter'=>$item['chapter'],'pdf_page'=>$pageRecord['pdf_page'],
                    'printed_page'=>$pageRecord['printed_page'],'pdf_sha256'=>$item['pdf_sha256'],
                    'anchor_sha256'=>hash('sha256',$pageRecord['anchor_text']),'is_primary'=>false];
            }

            $readSources=$db->prepare(<<<'SQL'
SELECT s.id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,s.is_primary,s.origin,
 s.confidence_source,s.confidence_node,s.confidence_page,s.official_scope_status,s.official_scope_note,s.scope_unverified,n.number chapter
FROM bank_question_sources s JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE s.workspace_id=:ws AND s.question_id=:qid ORDER BY s.pdf_page,s.id FOR UPDATE
SQL);
            $readSources->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$afterSources=$readSources->fetchAll(PDO::FETCH_ASSOC);
            if(count($afterSources)!==count($pageRecords))throw new RuntimeException('Post-write source count differs from the page bundle.');
            $afterByPage=[];foreach($afterSources as $source)$afterByPage[(int)$source['pdf_page']]=$source;
            foreach($pageRecords as $pageRecord){
                $after=$afterByPage[$pageRecord['pdf_page']]??null;
                if(!is_array($after)||(string)$after['chapter']!==$item['chapter']
                    ||$after['page']!==$pageRecord['printed_page']||$after['printed_page']!==$pageRecord['printed_page']
                    ||(int)$after['pdf_page']!==$pageRecord['pdf_page']||strtolower((string)$after['pdf_sha256'])!==$item['pdf_sha256']
                    ||$after['anchor_text']!==$pageRecord['anchor_text']||$after['origin']!=='ai'
                    ||$after['official_scope_status']!=='in_scope'||$after['official_scope_note']!==null||(bool)$after['scope_unverified']
                    ||number_format((float)$after['confidence_source'],3,'.','')!==$item['accepted_confidence']
                    ||number_format((float)$after['confidence_node'],3,'.','')!==$item['accepted_confidence']
                    ||number_format((float)$after['confidence_page'],3,'.','')!==$item['accepted_confidence'])
                    throw new RuntimeException('A source row failed exact bundle readback.');
                if($pageRecord['pdf_page']===$item['primary_pdf_page']
                    &&((int)$after['is_primary']!==1||$after['id']!==$item['source_id']))
                    throw new RuntimeException('Original primary citation identity was not preserved.');
                if($pageRecord['pdf_page']!==$item['primary_pdf_page']&&(int)$after['is_primary']!==0)
                    throw new RuntimeException('A secondary page was marked primary.');
            }
            $afterImmutable=voidedBundleImmutableSnapshot($db,$workspace,$s['question_id']);
            if($afterImmutable!==$snapshot)throw new RuntimeException('Question, choices or voided key changed.');
            $immutableAfter[(string)$item['number']]=$afterImmutable;
            $changes[]=['number'=>$item['number'],'source_id'=>$s['id'],'edition'=>$edition,'chapter'=>$item['chapter'],
                'primary'=>['pdf_page'=>$primary[0]['pdf_page'],'printed_page'=>$primary[0]['printed_page'],
                    'pdf_sha256'=>$item['pdf_sha256'],'anchor_sha256'=>hash('sha256',$primary[0]['anchor_text'])],
                'secondary_sources'=>$secondarySources,'draft_status_preserved'=>true,'official_answer_voided_preserved'=>true];
        }
        $assessmentAfter=voidedBundleAssessmentSnapshot($db,$workspace,(string)$sittings[0]);
        if($assessmentAfter!==$assessmentBefore)throw new RuntimeException('Assessment, review or attempt snapshot changed.');
        $receipt=['format'=>'fanoos.verified-voided-source-page-bundle-receipt/1','mode'=>$apply?'applied':'rollback-preview',
            'year'=>$plan['year'],'subject'=>$plan['subject'],'count'=>count($changes),'changes'=>$changes,
            'primary_source_fields_changed'=>VoidedSourcePageBundleGuard::allowedSourceFields(),
            'secondary_source_rows_inserted'=>array_sum(array_map(static fn(array $change):int=>count($change['secondary_sources']),$changes)),
            'question_changes'=>0,'choice_changes'=>0,'official_answer_changes'=>0,'question_status_changes'=>0,
            'assessment_version_and_review_changes'=>0,'attempt_changes'=>0,
            'immutable_question_before_after'=>['before'=>$immutableBefore,'after'=>$immutableAfter],
            'assessment_before_after'=>['before'=>$assessmentBefore,'after'=>$assessmentAfter],
            'plan_sha256'=>hash('sha256',$rawPlan),'audit_sha256'=>$plan['audit_sha256']];
        if($apply){
            $receipt['backup_id']=basename($backup);$receipt['at_utc']=gmdate('c');
            voidedBundleWriteReceipt((string)$args['receipt'],$receipt);$receiptWritten=(string)$args['receipt'];$db->commit();
        }else{$db->rollBack();}
        echo json_encode($receipt,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(Throwable $error){
        if($db->inTransaction())$db->rollBack();
        if($receiptWritten!==''&&is_file($receiptWritten))@unlink($receiptWritten);
        throw $error;
    }finally{$db->query("SELECT RELEASE_LOCK('fanoos:verified-voided-source-page-bundle')");}
}catch(Throwable $error){
    fwrite(STDERR,'Voided multi-page source bundle refused: '.$error->getMessage().PHP_EOL);
    exit(1);
}
