<?php

declare(strict_types=1);

/**
 * Bind an existing human-origin residency citation to a bundle of exact
 * original PDF pages from one official chapter. The existing human row keeps
 * its provenance; additional page rows are inserted as secondary AI citations.
 * Preview rolls back the update and inserts.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VerifiedHumanChapterPageRepairGuard;
use Fanoos\Platform\Bank\VerifiedHumanSourcePageBundleGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Uuid;

$root=dirname(__DIR__,2);
require $root.'/apps/platform/bootstrap.php';

try{
    $args=[];
    foreach(array_slice($argv,1) as $arg){
        if(preg_match('/^--(workspace|plan|apply|backup|receipt)(?:=(.*))?$/D',$arg,$m)!==1||array_key_exists($m[1],$args))
            throw new RuntimeException('Unknown or duplicate protected argument.');
        $args[$m[1]]=$m[2]??'1';
    }
    $workspace=(string)($args['workspace']??'');$apply=array_key_exists('apply',$args);
    if(!preg_match('/^[a-f0-9-]{36}$/D',$workspace)||$apply!==array_key_exists('backup',$args)||$apply!==array_key_exists('receipt',$args))
        throw new RuntimeException('Workspace required; apply also needs a verified backup and unique receipt.');
    if($apply&&(!function_exists('posix_getpwuid')||(posix_getpwuid(posix_geteuid())['name']??null)!=='fanoosupd'))
        throw new RuntimeException('Only the authorized updater service may apply.');
    $reports=realpath('/srv/fanoos/shared/research/classification/reports');$planPath=(string)($args['plan']??'');
    if($reports===false||realpath(dirname($planPath))!==$reports||is_link($planPath)||!is_file($planPath))
        throw new RuntimeException('Plan must be a private protected report.');
    $rawPlan=(string)file_get_contents($planPath);$plan=json_decode($rawPlan,true,48,JSON_THROW_ON_ERROR);
    if(($plan['format']??null)!=='fanoos.verified-human-source-page-bundle-repair/1'
        ||($plan['exam_type']??null)!=='residency'||!is_int($plan['year']??null)||$plan['year']<1396||$plan['year']>1500
        ||!is_string($plan['subject']??null)||preg_match('/^[a-z][a-z0-9-]{1,59}$/D',$plan['subject'])!==1
        ||!is_array($plan['entries']??null)||!array_is_list($plan['entries'])||count($plan['entries'])<1||count($plan['entries'])>12)
        throw new RuntimeException('Invalid year, subject or bundle plan size.');
    $auditPath=(string)($plan['audit_path']??'');$auditSha=strtolower((string)($plan['audit_sha256']??''));
    if(realpath(dirname($auditPath))!==$reports||is_link($auditPath)||!is_file($auditPath)
        ||!preg_match('/^[a-f0-9]{64}$/D',$auditSha)||hash_file('sha256',$auditPath)!==$auditSha)
        throw new RuntimeException('Independent private page-bundle audit is absent or changed.');
    $audit=json_decode((string)file_get_contents($auditPath),true,64,JSON_THROW_ON_ERROR);
    if(($audit['year']??null)!==$plan['year']||($audit['subject']??null)!==$plan['subject'])
        throw new RuntimeException('Audit and plan scopes differ.');
    if($apply){
        $backup=realpath((string)$args['backup']);$receiptPath=(string)$args['receipt'];
        if($backup===false||!str_starts_with($backup.'/', '/var/backups/fanoos/')||realpath(dirname($receiptPath))!==$reports
            ||is_link($receiptPath)||file_exists($receiptPath)||!str_ends_with($receiptPath,'.json'))
            throw new RuntimeException('Protected verified backup or unique receipt path rejected.');
        $manifest=BackupManifest::verify($backup);$created=strtotime((string)($manifest['created_at']??''));
        if($created===false||abs(time()-$created)>4*3600||(int)($manifest['files']['database.sql']['bytes']??0)<1024)
            throw new RuntimeException('Fresh fully verified production backup required.');
    }

    $maps=json_decode((string)file_get_contents($root.'/data/bank/reference-chapter-pages.json'),true,48,JSON_THROW_ON_ERROR);
    RuntimeConfig::load();$db=DatabaseConnection::fromEnvironment();
    $sourceQuery=$db->prepare(<<<'SQL'
SELECT s.id,s.workspace_id,s.question_id,s.node_id,s.edition_id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,
       s.anchor_text,s.origin,s.reviewed_at,s.reviewed_by_user_id,s.is_primary,
       s.official_scope_status,s.official_scope_note,
       q.number_in_sitting,q.stem,q.status question_status,
       n.number chapter_no,n.kind,e.edition_key,r.reference_key,v.scope_chapters,v.is_official
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
 AND v.exam_type_id=et.id AND v.subject_id=sb.id AND v.exam_year=si.exam_year AND v.is_official=1
WHERE s.workspace_id=:ws AND s.id=:source_id AND q.number_in_sitting=:number
 AND si.exam_year=:year AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:subject
FOR UPDATE
SQL);
    $sourceCountQuery=$db->prepare('SELECT id FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid ORDER BY id FOR UPDATE');
    $choicesQuery=$db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $answerQuery=$db->prepare('SELECT status,choice_position,also_correct_positions,source,recorded_at FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $nodeQuery=$db->prepare("SELECT id FROM bank_reference_nodes WHERE workspace_id=:ws AND edition_id=:edition_id AND kind='chapter' AND number=:chapter");
    $update=$db->prepare(<<<'SQL'
UPDATE bank_question_sources SET node_id=:new_node,pdf_page=:primary_page,pdf_sha256=:sha
WHERE workspace_id=:ws AND id=:source_id AND node_id=:old_node
 AND pdf_page IS NULL AND pdf_sha256 IS NULL AND page <=> :old_page AND printed_page <=> :old_printed
 AND anchor_text <=> :old_anchor AND is_primary <=> :old_primary
 AND origin='human' AND reviewed_at <=> :old_reviewed AND reviewed_by_user_id <=> :old_reviewer
 AND official_scope_status='in_scope' AND official_scope_note IS NULL
SQL);
    $insert=$db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,node_id,page,pdf_page,printed_page,pdf_sha256,anchor_text,is_primary,
 confidence_source,confidence_node,confidence_page,origin,created_at)
VALUES (:id,:ws,:qid,:edition_id,:node_id,:page,:pdf_page,NULL,:pdf_sha,:anchor,0,0.950,0.950,0.950,'ai',UTC_TIMESTAMP(6))
SQL);

    $seen=[];$results=[];$db->beginTransaction();
    try{
        foreach($plan['entries'] as $item){
            $number=$item['number']??null;if(!is_int($number)||$number<1||$number>300||isset($seen[$number]))throw new RuntimeException('Invalid or duplicate question number.');$seen[$number]=true;
            foreach(['source_id','edition','expected_chapter','chapter','pdf_sha256','pages','expected_legacy_page','expected_stored_printed_page','expected_is_primary','expected_anchor_sha256','expected_stem_sha256','expected_choices_sha256','expected_answer_sha256','expected_reviewed_at','expected_reviewed_by_user_id','expected_question_status','expected_answer_status'] as $field)
                if(!array_key_exists($field,$item))throw new RuntimeException("Q{$number}: missing {$field}.");
            $id=$item['source_id'];$edition=$item['edition'];$oldChapter=$item['expected_chapter'];$chapter=$item['chapter'];$sha=strtolower((string)$item['pdf_sha256']);$pages=$item['pages'];
            if(!is_string($id)||preg_match('/^[a-f0-9-]{36}$/D',$id)!==1||!is_string($edition)||preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D',$edition)!==1
                ||!is_string($oldChapter)||preg_match('/^[0-9]+(?:\.[0-9]+)?$/D',$oldChapter)!==1||!is_string($chapter)||preg_match('/^[0-9]+(?:\.[0-9]+)?$/D',$chapter)!==1
                ||!preg_match('/^[a-f0-9]{64}$/D',$sha)||!is_array($pages)||!array_is_list($pages)||count($pages)<2||count($pages)>4)
                throw new RuntimeException("Q{$number}: invalid protected page bundle.");
            if(!VerifiedHumanSourcePageBundleGuard::auditMatches($audit,$plan,$item))throw new RuntimeException("Q{$number}: independent bundle audit differs from plan.");
            $sourceQuery->execute(['ws'=>$workspace,'source_id'=>$id,'number'=>$number,'year'=>$plan['year'],'subject'=>$plan['subject']]);$sources=$sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($sources)!==1)throw new RuntimeException("Q{$number}: existing human source is not unique.");$s=$sources[0];
            $sourceCountQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$sourceIds=$sourceCountQuery->fetchAll(PDO::FETCH_COLUMN);
            if(count($sourceIds)!==1||$sourceIds[0]!==$id)throw new RuntimeException("Q{$number}: existing citation set changed; refusing duplicate bundle.");
            if($s['origin']!=='human'||$s['reviewed_at']!==$item['expected_reviewed_at']||$s['reviewed_by_user_id']!==$item['expected_reviewed_by_user_id']
                ||$s['pdf_page']!==null||$s['pdf_sha256']!==null||$s['page']!==$item['expected_legacy_page']||$s['printed_page']!==$item['expected_stored_printed_page']
                ||(int)$s['is_primary']!==(int)$item['expected_is_primary']||$s['official_scope_status']!=='in_scope'||$s['official_scope_note']!==null
                ||hash('sha256',(string)$s['anchor_text'])!==$item['expected_anchor_sha256']||$s['reference_key'].'@'.$s['edition_key']!==$edition
                ||(string)$s['chapter_no']!==$oldChapter||$s['kind']!=='chapter'||(int)$s['is_official']!==1||$s['question_status']!==$item['expected_question_status'])
                throw new RuntimeException("Q{$number}: human source, chapter, review or question identity changed.");
            if(!VerifiedHumanChapterPageRepairGuard::scopeAllows($s['scope_chapters'],$chapter))throw new RuntimeException("Q{$number}: target chapter is not complete official scope.");
            $choicesQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$choices=$choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);$answer=$answerQuery->fetch(PDO::FETCH_ASSOC);
            $publishedScorable=is_array($answer)&&$s['question_status']==='published'&&in_array($answer['status']??null,['final','amended'],true)&&($answer['choice_position']??null)!==null;
            $draftVoided=is_array($answer)&&$s['question_status']==='draft'&&($answer['status']??null)==='voided'&&($answer['choice_position']??null)===null;
            if((!$publishedScorable&&!$draftVoided)||!is_array($answer)||$answer['status']!==$item['expected_answer_status']||count($choices)!==4
                ||hash('sha256',(string)$s['stem'])!==$item['expected_stem_sha256']||hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR))!==$item['expected_choices_sha256']
                ||hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR))!==$item['expected_answer_sha256'])throw new RuntimeException("Q{$number}: question, choices or official key changed.");
            $seenPages=[];$verifiedPages=[];
            foreach($pages as $index=>$pageItem){
                if(!is_array($pageItem)||!is_int($pageItem['pdf_page']??null)||$pageItem['pdf_page']<1||$pageItem['pdf_page']>6000
                    ||isset($seenPages[$pageItem['pdf_page']])||!is_array($pageItem['evidence']??null)||!array_is_list($pageItem['evidence'])
                    ||count($pageItem['evidence'])<1||count($pageItem['evidence'])>4
                    ||array_filter($pageItem['evidence'],static fn(mixed $quote):bool=>!is_string($quote)||mb_strlen(trim($quote))<6||strlen($quote)>190)!==[])
                    throw new RuntimeException("Q{$number}: invalid page-local citation.");
                $seenPages[$pageItem['pdf_page']]=true;
                if(!VerifiedHumanChapterPageRepairGuard::pageInMap($maps,$edition,$chapter,$pageItem['pdf_page'],$sha)
                    ||!PrintedBookPageEvidence::pageContainsEvidence($edition,$pageItem['pdf_page'],$pageItem['evidence'],$sha))
                    throw new RuntimeException("Q{$number}: page bundle evidence or map failed.");
                $verifiedPages[]=['pdf_page'=>$pageItem['pdf_page'],'evidence'=>$pageItem['evidence']];
            }
            $nodeQuery->execute(['ws'=>$workspace,'edition_id'=>$s['edition_id'],'chapter'=>$chapter]);$nodes=$nodeQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($nodes)!==1)throw new RuntimeException("Q{$number}: target chapter node is not unique.");
            $primaryPage=$verifiedPages[0]['pdf_page'];
            $update->execute(['new_node'=>$nodes[0]['id'],'primary_page'=>$primaryPage,'sha'=>$sha,'ws'=>$workspace,'source_id'=>$id,
                'old_node'=>$s['node_id'],'old_page'=>$item['expected_legacy_page'],'old_printed'=>$item['expected_stored_printed_page'],
                'old_anchor'=>$s['anchor_text'],'old_primary'=>$item['expected_is_primary'],'old_reviewed'=>$item['expected_reviewed_at'],
                'old_reviewer'=>$item['expected_reviewed_by_user_id']]);
            if($update->rowCount()!==1)throw new RuntimeException("Q{$number}: guarded human source update ambiguous.");
            $secondary=[];
            foreach(array_slice($verifiedPages,1) as $pageItem){$newId=Uuid::v7();$quote=$pageItem['evidence'][0];
                $insert->execute(['id'=>$newId,'ws'=>$workspace,'qid'=>$s['question_id'],'edition_id'=>$s['edition_id'],'node_id'=>$nodes[0]['id'],
                    'page'=>'pdf '.$pageItem['pdf_page'],'pdf_page'=>$pageItem['pdf_page'],'pdf_sha'=>$sha,'anchor'=>$quote]);
                if($insert->rowCount()!==1)throw new RuntimeException("Q{$number}: secondary source insert ambiguous.");
                $secondary[]=['source_id'=>$newId,'pdf_page'=>$pageItem['pdf_page'],'pdf_sha256'=>$sha,'origin'=>'ai','is_primary'=>false,'anchor_sha256'=>hash('sha256',$quote)];
            }
            $results[]=['number'=>$number,'human_source_id'=>$id,'edition'=>$edition,'old_chapter'=>$oldChapter,'chapter'=>$chapter,
                'primary_pdf_page'=>$primaryPage,'pdf_sha256'=>$sha,'secondary_sources'=>$secondary];
        }
        if($apply)$db->commit();else$db->rollBack();
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}

    $receipt=['format'=>'fanoos.verified-human-source-page-bundle-repair-receipt/1','mode'=>$apply?'applied':'rollback-preview',
        'year'=>$plan['year'],'subject'=>$plan['subject'],'count'=>count($results),'plan_sha256'=>hash('sha256',$rawPlan),'audit_sha256'=>$auditSha,
        'existing_human_source_fields_changed'=>['node_id','pdf_page','pdf_sha256'],
        'secondary_source_rows_planned'=>array_sum(array_map(
            static fn (array $source): int => count($source['secondary_sources']),
            $results,
        )),
        'questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,'human_source_provenance_changed'=>0,
        'human_review_metadata_changed'=>0,'sources'=>$results];
    if($apply){$receipt['backup_id']=basename($backup);$receipt['at_utc']=gmdate('c');$fp=fopen($receiptPath,'x');
        if($fp===false)throw new RuntimeException('Committed but unique receipt missing; read back before retry.');chmod($receiptPath,0600);
        fwrite($fp,json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");fflush($fp);fclose($fp);}
    echo json_encode($receipt,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $error){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'Human page-bundle repair refused: '.$error->getMessage().PHP_EOL);exit(1);}
