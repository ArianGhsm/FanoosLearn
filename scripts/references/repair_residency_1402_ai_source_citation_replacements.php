<?php

declare(strict_types=1);

/**
 * Replace the exact audited AI source citations for residency 1402 Q175, Q190
 * and Q244. The default transaction is rolled back. Apply requires its exact
 * preview receipt and a fresh verified full production backup.
 *
 * Only bank_question_sources citation/scope/confidence metadata is updated.
 */
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\Residency1402AiSourceCitationReplacementGuard as Guard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const RESIDENCY_1402_REPLACEMENT_REPORTS = '/srv/fanoos/shared/research/classification/reports';

/** @return array<string,mixed> */
function residency1402ReplacementAssessment(PDO $db, string $workspace, string $sitting): array
{
    $statement = $db->prepare(<<<'SQL'
SELECT s.question_count,a.id assessment_id,a.status,a.current_version_no,v.id version_id,
       LOWER(HEX(v.checksum_sha256)) version_sha256,
       (SELECT COUNT(*) FROM exam_attempts x WHERE x.workspace_id=a.workspace_id AND x.assessment_id=a.id) attempts,
       (SELECT COUNT(*) FROM exam_version_reviews r WHERE r.workspace_id=a.workspace_id AND r.assessment_id=a.id) reviews
FROM bank_exam_sittings s
JOIN exam_assessments a ON a.id=s.assessment_id AND a.workspace_id=s.workspace_id
JOIN exam_assessment_versions v ON v.assessment_id=a.id AND v.workspace_id=a.workspace_id
 AND v.version_no=a.current_version_no
WHERE s.workspace_id=:ws AND s.id=:sitting
SQL);
    $statement->execute(['ws'=>$workspace,'sitting'=>$sitting]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || $statement->fetch(PDO::FETCH_ASSOC) !== false) {
        throw new RuntimeException('Expected exactly one current frozen assessment version.');
    }
    return [
        'assessment_id'=>(string)$row['assessment_id'],
        'assessment_status'=>(string)$row['status'],
        'version'=>(int)$row['current_version_no'],
        'version_id'=>(string)$row['version_id'],
        'version_sha256'=>strtolower((string)$row['version_sha256']),
        'question_count'=>(int)$row['question_count'],
        'attempts'=>(int)$row['attempts'],
        'reviews'=>(int)$row['reviews'],
    ];
}

/** @return array<string,mixed> */
function residency1402ReplacementQuestion(PDO $db, string $workspace, array $entry): array
{
    $question = $db->prepare(<<<'SQL'
SELECT q.id question_id,q.stem,q.status question_status,si.id sitting_id,
       et.type_key,sb.subject_key,si.exam_year,si.exam_round
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=1402
  AND si.exam_round=1 AND sb.subject_key=:subject AND q.number_in_sitting=:number
FOR UPDATE
SQL);
    $question->execute(['ws'=>$workspace,'subject'=>$entry['subject'],'number'=>$entry['number']]);
    $questions = $question->fetchAll(PDO::FETCH_ASSOC);
    if (count($questions) !== 1 || $questions[0]['question_status'] !== 'published'
        || hash('sha256',(string)$questions[0]['stem']) !== $entry['stem_sha256']) {
        throw new RuntimeException('Q' . $entry['number'] . ': live question identity differs from the protected plan.');
    }
    $q = $questions[0];

    $choicesQuery = $db->prepare(<<<'SQL'
SELECT position,text,image FROM bank_question_choices
WHERE workspace_id=:ws AND question_id=:qid ORDER BY position
SQL);
    $choicesQuery->execute(['ws'=>$workspace,'qid'=>$q['question_id']]);
    $choices = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);
    if (count($choices) !== 4 || hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR)) !== $entry['choices_sha256']) {
        throw new RuntimeException('Q' . $entry['number'] . ': four live choices differ from the protected plan.');
    }

    $answerQuery = $db->prepare(<<<'SQL'
SELECT choice_position,also_correct_positions,status,source,recorded_at
FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $answerQuery->execute(['ws'=>$workspace,'qid'=>$q['question_id']]);
    $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($answer) || $answer['status'] !== 'final'
        || (int)$answer['choice_position'] !== 3
        || hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR)) !== $entry['answer_sha256']) {
        throw new RuntimeException('Q' . $entry['number'] . ': final official answer differs from the protected plan.');
    }

    return [
        'question'=>$q,
        'immutable'=>[
            'stem_sha256'=>hash('sha256',(string)$q['stem']),
            'choices_sha256'=>hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR)),
            'answer_status'=>(string)$answer['status'],
            'answer_choice'=>(int)$answer['choice_position'],
            'answer_sha256'=>hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR)),
        ],
    ];
}

function residency1402WritePrivateJson(string $path, array $payload): void
{
    $json = json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
    $handle = @fopen($path,'x');
    if ($handle === false) {
        throw new RuntimeException('Could not create unique protected citation receipt.');
    }
    chmod($path,0600);
    try {
        if (fwrite($handle,$json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Could not write complete protected citation receipt.');
        }
    } catch (Throwable $error) {
        fclose($handle);
        @unlink($path);
        throw $error;
    }
    fclose($handle);
}

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI-only protected source-citation replacement.');
    }
    $args=[];
    foreach (array_slice($argv,1) as $argument) {
        if (preg_match('/^--(workspace|plan|preview-receipt|apply|backup|receipt)(?:=(.*))?$/D',$argument,$m)!==1
            || isset($args[$m[1]])) {
            throw new RuntimeException('Unknown or duplicate option.');
        }
        $args[$m[1]]=$m[2]??'1';
    }
    $workspace=(string)($args['workspace']??'');
    $apply=isset($args['apply']);
    if (!preg_match('/^[0-9a-f-]{36}$/D',$workspace)
        || !Guard::modeArgumentsValid($apply,isset($args['backup']),isset($args['receipt']),isset($args['preview-receipt']))) {
        throw new RuntimeException('Workspace required; apply also needs the preview receipt, fresh backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name']??'')!=='fanoosupd')) {
        throw new RuntimeException('Only updater service identity may apply.');
    }

    $reports=realpath(RESIDENCY_1402_REPLACEMENT_REPORTS);
    $planPath=(string)($args['plan']??'');
    if ($reports===false || realpath(dirname($planPath))!==$reports || is_link($planPath) || !is_file($planPath)
        || (fileperms($planPath)&0077)!==0) {
        throw new RuntimeException('Plan must be a mode-0600 private report file.');
    }
    $rawPlan=(string)file_get_contents($planPath);
    $plan=json_decode($rawPlan,true,64,JSON_THROW_ON_ERROR);
    if (!Guard::planMatches($plan)) {
        throw new RuntimeException('Plan is not the exact audited 1402 Q175/Q190/Q244 source replacement set.');
    }
    $classification=realpath('/srv/fanoos/shared/research/classification');
    $auditPath=(string)$plan['audit_path'];
    $auditReal=realpath($auditPath);
    if ($classification===false || $auditReal===false || is_link($auditPath)
        || !is_file($auditPath) || (fileperms($auditPath)&0077)!==0
        || !str_starts_with($auditReal,$classification.'/recovery-1402-')
        || hash_file('sha256',$auditReal)!==$plan['audit_sha256']) {
        throw new RuntimeException('Protected research audit is absent, public or has changed.');
    }
    $audit=json_decode((string)file_get_contents($auditReal),true,64,JSON_THROW_ON_ERROR);
    if (!Guard::researchMatches($audit,$plan)) {
        throw new RuntimeException('Independent research audit does not prove the exact question/page/PDF set.');
    }

    $previewPath=(string)$args['preview-receipt'];
    if ($apply) {
        if (realpath(dirname($previewPath))!==$reports || is_link($previewPath) || !is_file($previewPath)
            || (fileperms($previewPath)&0077)!==0 || !str_ends_with($previewPath,'.json')) {
            throw new RuntimeException('A private rollback-preview receipt is required before apply.');
        }
        $preview=json_decode((string)file_get_contents($previewPath),true,64,JSON_THROW_ON_ERROR);
        if (($preview['format']??null)!=='fanoos.residency1402.ai-source-citation-replacement-receipt/1'
            || ($preview['mode']??null)!=='rollback-preview'
            || ($preview['private_plan_sha256']??null)!==hash('sha256',$rawPlan)
            || ($preview['question_stem_choices_official_key_unchanged']??null)!==true
            || ($preview['assessment_version_and_reviews_unchanged']??null)!==true
            || ($preview['attempts_unchanged']??null)!==true) {
            throw new RuntimeException('Preview receipt does not match this exact plan.');
        }
        $backup=realpath((string)$args['backup']);
        $receipt=(string)$args['receipt'];
        if ($backup===false || !str_starts_with($backup.'/','/var/backups/fanoos/')
            || realpath(dirname($receipt))!==$reports || is_link($receipt) || file_exists($receipt)
            || !str_ends_with($receipt,'.json')) {
            throw new RuntimeException('Verified backup or unique private receipt path rejected.');
        }
        $manifest=BackupManifest::verify($backup);
        $created=strtotime((string)($manifest['created_at']??''));
        if ($created===false || abs(time()-$created)>4*3600
            || (int)($manifest['files']['database.sql']['bytes']??0)<1024) {
            throw new RuntimeException('Fresh complete production backup with database.sql is required.');
        }
    } elseif (realpath(dirname($previewPath))!==$reports || is_link($previewPath)
        || file_exists($previewPath) || !str_ends_with($previewPath,'.json')) {
        throw new RuntimeException('Preview receipt must be a new protected report path.');
    }

    $root=dirname(__DIR__,2);
    $map=json_decode((string)file_get_contents($root.'/data/bank/reference-chapter-pages.json'),true,64,JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db=DatabaseConnection::fromEnvironment();
    $lock=$db->query("SELECT GET_LOCK('fanoos:residency-1402-ai-source-citation-replacements',30)")->fetchColumn();
    if ((int)$lock!==1) {
        throw new RuntimeException('Could not acquire the 1402 source-correction writer lock.');
    }

    $releaseLock=true;
    $db->beginTransaction();
    $changedReceipt='';
    try {
        $sittingQuery=$db->prepare(<<<'SQL'
SELECT si.id,si.workspace_id,si.question_count FROM bank_exam_sittings si
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
WHERE si.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=1402 AND si.exam_round=1
SQL);
        $sittingQuery->execute(['ws'=>$workspace]);$sittings=$sittingQuery->fetchAll(PDO::FETCH_ASSOC);
        if(count($sittings)!==1)throw new RuntimeException('1402 sitting identity is not unique.');
        $sitting=$sittings[0];
        $assessmentBefore=residency1402ReplacementAssessment($db,$workspace,(string)$sitting['id']);
        $sourceQuery=$db->prepare(<<<'SQL'
SELECT s.id source_id,s.edition_id,s.node_id,s.page,s.printed_page,s.pdf_page,LOWER(s.pdf_sha256) pdf_sha256,s.anchor_text,
       s.origin,s.is_primary,s.reviewed_at,s.reviewed_by_user_id,s.confidence_source,s.confidence_node,s.confidence_page,
       s.official_scope_status,s.official_scope_note,s.scope_unverified,
       q.id question_id,q.stem,q.status question_status,sb.subject_key,
       r.reference_key,e.edition_key,n.number chapter,n.kind,
       v.scope_chapters,v.is_official
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
WHERE s.workspace_id=:ws AND LOWER(s.id)=:source_id AND q.number_in_sitting=:number
  AND si.exam_year=1402 AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:subject
FOR UPDATE
SQL);
        $newNodeQuery=$db->prepare(<<<'SQL'
SELECT e.id edition_id,n.id node_id,n.kind,n.number,v.scope_chapters,v.is_official
FROM bank_references r JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE r.workspace_id=:ws AND r.reference_key=:reference AND e.edition_key=:edition
 AND n.kind='chapter' AND n.number=:chapter AND v.exam_year=1402 AND sb.subject_key=:subject
 AND et.type_key='residency' AND v.is_official=1
SQL);
        $update=$db->prepare(<<<'SQL'
UPDATE bank_question_sources SET edition_id=:new_edition,node_id=:new_node,page=:new_page,
 printed_page=:new_printed,pdf_page=:new_pdf_page,pdf_sha256=:new_pdf_sha,anchor_text=:new_anchor,
 confidence_source=:confidence_source,confidence_node=:confidence_node,confidence_page=:confidence_page,
 official_scope_status='in_scope',official_scope_note=NULL,scope_unverified=FALSE
WHERE workspace_id=:ws AND id=:source_id AND edition_id=:old_edition AND node_id=:old_node
 AND page=:old_page AND printed_page <=> :old_printed AND pdf_page <=> :old_pdf_page
 AND pdf_sha256 <=> :old_pdf_sha AND anchor_text=:old_anchor AND origin='ai' AND is_primary=1
 AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
 AND confidence_source=:old_confidence_source AND confidence_node=:old_confidence_node AND confidence_page=:old_confidence_page
 AND official_scope_status=:old_scope_status AND official_scope_note <=> :old_scope_note
 AND scope_unverified=:old_scope_unverified
SQL);

        $beforeImmutable=[];$afterImmutableByNumber=[];$changes=[];
        foreach($plan['entries'] as $entry){
            $spec=Guard::spec((int)$entry['number']);
            $sourceQuery->execute(['ws'=>$workspace,'source_id'=>$entry['source_id'],'number'=>$entry['number'],'subject'=>$entry['subject']]);
            $rows=$sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)!==1)throw new RuntimeException('Q'.$entry['number'].': source identity is not unique.');
            $old=$rows[0];
            $oldEdition=$old['reference_key'].'@'.$old['edition_key'];
            $oldPdfPage=$old['pdf_page']===null?null:(int)$old['pdf_page'];
            if($old['question_status']!=='published'||$old['subject_key']!==$entry['subject']
                ||$oldEdition!==$spec['old_reference']||(string)$old['chapter']!==$spec['old_chapter']
                ||$old['page']!==$spec['old_legacy_page']||$old['printed_page']!==$spec['old_printed_page']
                ||$oldPdfPage!==$spec['old_pdf_page']||$old['pdf_sha256']!==$spec['old_pdf_sha256']
                ||hash('sha256',(string)$old['anchor_text'])!==$spec['old_anchor_sha256']
                ||$old['origin']!=='ai'||(int)$old['is_primary']!==1||$old['reviewed_at']!==null
                ||$old['reviewed_by_user_id']!==null||number_format((float)$old['confidence_source'],3,'.','')!==$spec['old_confidence']
                ||number_format((float)$old['confidence_node'],3,'.','')!==$spec['old_confidence']
                ||number_format((float)$old['confidence_page'],3,'.','')!==$spec['old_confidence']
                ||$old['official_scope_status']!=='in_scope'||$old['official_scope_note']!==null||(bool)$old['scope_unverified']){
                throw new RuntimeException('Q'.$entry['number'].': live source differs from exact audited old citation.');
            }

            $immutableBefore=residency1402ReplacementQuestion($db,$workspace,$entry);
            $beforeImmutable[(string)$entry['number']]=$immutableBefore['immutable'];

            $newEdition=$entry['reference'];$newChapter=$entry['chapter'];$newPage=$entry['pdf_page'];
            $newSha=$entry['pdf_sha256'];$newPrinted=$entry['printed_page'];
            $mapEntry=$map['editions'][$newEdition]??null;
            if(!is_array($mapEntry)||($mapEntry['source_pdf_sha256']??null)!==$newSha
                ||!is_array($mapEntry['runs']??null))throw new RuntimeException('Q'.$entry['number'].': current verified page map differs.');
            $mapped=false;foreach($mapEntry['runs'] as $run){if(is_array($run)&&count($run)===3&&(string)$run[0]===$newChapter&&$newPage>=$run[1]&&$newPage<=$run[2]){$mapped=true;break;}}
            if(!$mapped||!PrintedBookPageEvidence::corroborates($newEdition,$newPage,$newPrinted,$newSha)
                ||!PrintedBookPageEvidence::pageContainsEvidence($newEdition,$newPage,[$entry['evidence']],$newSha)){
                throw new RuntimeException('Q'.$entry['number'].': exact original PDF page, printed label or evidence rejected.');
            }

            [$referenceKey,$editionKey]=explode('@',$newEdition,2);
            $newNodeQuery->execute(['ws'=>$workspace,'reference'=>$referenceKey,'edition'=>$editionKey,'chapter'=>$newChapter,'subject'=>$entry['subject']]);
            $nodes=$newNodeQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($nodes)!==1||$nodes[0]['kind']!=='chapter'||(string)$nodes[0]['number']!==$newChapter
                ||(int)$nodes[0]['is_official']!==1
                ||OfficialScopePageGuard::pageDisposition($nodes[0]['scope_chapters'],$newChapter,$newPrinted)!==true){
                throw new RuntimeException('Q'.$entry['number'].': replacement chapter/page is not in exact 1402 official scope.');
            }

            $update->execute([
                'new_edition'=>$nodes[0]['edition_id'],'new_node'=>$nodes[0]['node_id'],'new_page'=>$newPrinted,
                'new_printed'=>$newPrinted,'new_pdf_page'=>$newPage,'new_pdf_sha'=>$newSha,'new_anchor'=>$entry['evidence'],
                'confidence_source'=>number_format((float)$entry['confidence'],3,'.',''),
                'confidence_node'=>number_format((float)$entry['confidence'],3,'.',''),
                'confidence_page'=>number_format((float)$entry['confidence'],3,'.',''),
                'ws'=>$workspace,'source_id'=>$old['source_id'],'old_edition'=>$old['edition_id'],'old_node'=>$old['node_id'],
                'old_page'=>$old['page'],'old_printed'=>$old['printed_page'],'old_pdf_page'=>$old['pdf_page'],'old_pdf_sha'=>$old['pdf_sha256'],
                'old_anchor'=>$old['anchor_text'],'old_confidence_source'=>$spec['old_confidence'],
                'old_confidence_node'=>$spec['old_confidence'],'old_confidence_page'=>$spec['old_confidence'],
                'old_scope_status'=>$old['official_scope_status'],
                'old_scope_note'=>$old['official_scope_note'],'old_scope_unverified'=>(int)$old['scope_unverified'],
            ]);
            if($update->rowCount()!==1)throw new RuntimeException('Q'.$entry['number'].': source-only compare-and-swap did not update exactly one row.');

            $immutableAfter=residency1402ReplacementQuestion($db,$workspace,$entry);
            if($immutableAfter['immutable']!==$immutableBefore['immutable'])throw new RuntimeException('Q'.$entry['number'].': question/key changed during source update.');
            $afterImmutableRows=$db->prepare(<<<'SQL'
SELECT r.reference_key,e.edition_key,n.number chapter,s.page,s.printed_page,s.pdf_page,LOWER(s.pdf_sha256) pdf_sha256,
       s.anchor_text,s.confidence_source,s.confidence_node,s.confidence_page,s.official_scope_status,s.official_scope_note,s.scope_unverified
FROM bank_question_sources s JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE s.workspace_id=:ws AND s.id=:id
SQL);$afterImmutableRows->execute(['ws'=>$workspace,'id'=>$old['source_id']]);$new=$afterImmutableRows->fetch(PDO::FETCH_ASSOC);
            if(!is_array($new)||$new['reference_key'].'@'.$new['edition_key']!==$newEdition||(string)$new['chapter']!==$newChapter
                ||$new['printed_page']!==$newPrinted||(int)$new['pdf_page']!==$newPage||$new['pdf_sha256']!==$newSha
                ||$new['anchor_text']!==$entry['evidence']||$new['official_scope_status']!=='in_scope'
                ||$new['official_scope_note']!==null||(bool)$new['scope_unverified'])throw new RuntimeException('Q'.$entry['number'].': replacement source did not read back exactly.');
            $afterImmutableByNumber[(string)$entry['number']]=$immutableAfter['immutable'];
            $changes[]=['number'=>$entry['number'],'source_id'=>$old['source_id'],'subject'=>$entry['subject'],
                'old'=>['reference'=>$oldEdition,'chapter'=>$old['chapter'],'legacy_page'=>$old['page'],'printed_page'=>$old['printed_page'],
                    'pdf_page'=>$oldPdfPage,'pdf_sha256'=>$old['pdf_sha256'],'anchor_sha256'=>$spec['old_anchor_sha256']],
                'new'=>['reference'=>$newEdition,'chapter'=>$newChapter,'printed_page'=>$newPrinted,'pdf_page'=>$newPage,
                    'pdf_sha256'=>$newSha,'anchor_sha256'=>hash('sha256',$entry['evidence']),'confidence'=>$entry['confidence']],
                'science_note'=>$entry['note']];
        }
        if(count($changes)!==3||!Guard::immutableSnapshotsMatch($beforeImmutable,$afterImmutableByNumber)){
            throw new RuntimeException('Exactly three source citations and unchanged question/key snapshots are required.');
        }
        $assessmentAfter=residency1402ReplacementAssessment($db,$workspace,(string)$sitting['id']);
        $assessmentUnchanged=$assessmentAfter===$assessmentBefore;
        $attemptsUnchanged=$assessmentAfter['attempts']===$assessmentBefore['attempts'];
        if(!$assessmentUnchanged)throw new RuntimeException('Frozen assessment, review or attempt snapshot changed.');

        $result=['format'=>'fanoos.residency1402.ai-source-citation-replacement-receipt/1',
            'mode'=>$apply?'applied':'rollback-preview','year'=>1402,'round'=>1,'question_count'=>250,
            'changed_source_count'=>3,'changes'=>$changes,'only_changed_table'=>'bank_question_sources',
            'only_changed_columns'=>['edition_id','node_id','page','printed_page','pdf_page','pdf_sha256','anchor_text',
                'confidence_source','confidence_node','confidence_page','official_scope_status','official_scope_note','scope_unverified'],
            'question_stem_choices_official_key_unchanged'=>Guard::immutableSnapshotsMatch($beforeImmutable,$afterImmutableByNumber),
            'assessment_version_and_reviews_unchanged'=>$assessmentUnchanged,'attempts_unchanged'=>$attemptsUnchanged,
            'immutable_question_before_after'=>['before'=>$beforeImmutable,'after'=>$afterImmutableByNumber],
            'assessment_before_after'=>['before'=>$assessmentBefore,'after'=>$assessmentAfter],
            'research_audit_sha256'=>$plan['audit_sha256'],'private_plan_sha256'=>hash('sha256',$rawPlan),
            'preview_receipt_sha256'=>$apply?hash_file('sha256',$previewPath):null,
            'verified_backup'=>$apply?basename($backup):null,'at_utc'=>gmdate('Y-m-d\TH:i:s\Z')];
        if($apply){
            $receiptPath=(string)$args['receipt'];
            residency1402WritePrivateJson($receiptPath,$result);
            $changedReceipt=$receiptPath;
            $db->commit();
        }else{
            $db->rollBack();
            residency1402WritePrivateJson($previewPath,$result);
        }
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(Throwable $error){
        if($db->inTransaction())$db->rollBack();
        if($changedReceipt!==''&&is_file($changedReceipt))@unlink($changedReceipt);
        throw $error;
    }finally{
        $db->query("SELECT RELEASE_LOCK('fanoos:residency-1402-ai-source-citation-replacements')");
    }
} catch(Throwable $error) {
    fwrite(STDERR,'Residency 1402 source citation replacement refused: '.$error->getMessage().PHP_EOL);
    exit(1);
}
