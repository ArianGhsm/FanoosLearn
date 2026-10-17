<?php

declare(strict_types=1);

/**
 * Record an exact original-PDF design location on a human source whose
 * chapter is only partially in the official scope, when the PDF page has no
 * corroborated printed label. The row is marked scope_unverified so the
 * year-level publication gate rejects it until the boundary is resolved.
 * Preview always rolls back; apply requires a fresh verified backup and a
 * unique private receipt.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VerifiedHumanChapterPageRepairGuard;
use Fanoos\Platform\Bank\VerifiedHumanScopeUnverifiedPageGuard;
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
        || $apply !== array_key_exists('backup', $args)
        || $apply !== array_key_exists('receipt', $args)) {
        throw new RuntimeException('Workspace required; apply also requires a verified backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Only the authorized updater service may apply.');
    }

    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || (fileperms($reports) & 0077) !== 0
        || realpath(dirname($planPath)) !== $reports || is_link($planPath) || !is_file($planPath)
        || (fileperms($planPath) & 0077) !== 0) {
        throw new RuntimeException('Plan must be a private protected report.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 48, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.verified-human-scope-unverified-page-repair/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || ($plan['scope_status'] ?? null) !== 'scope_unverified'
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid year, subject or scope-unverified plan shape.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = strtolower((string) ($plan['audit_sha256'] ?? ''));
    if (realpath(dirname($auditPath)) !== $reports || is_link($auditPath) || !is_file($auditPath)
        || (fileperms($auditPath) & 0077) !== 0
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha) || hash_file('sha256', $auditPath) !== $auditSha) {
        throw new RuntimeException('Independent private scope audit is absent or changed.');
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
            throw new RuntimeException('Protected verified backup or unique receipt path rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh fully verified production backup required.');
        }
    }

    $maps = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 48, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.workspace_id,s.question_id,s.node_id,s.edition_id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,
       s.anchor_text,s.origin,s.reviewed_at,s.reviewed_by_user_id,s.official_scope_status,s.official_scope_note,
       s.scope_unverified,
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
 AND v.exam_type_id=et.id AND v.subject_id=sb.id AND v.exam_year=si.exam_year
WHERE s.workspace_id=:workspace AND s.id=:source_id AND q.number_in_sitting=:number
 AND si.exam_year=:year AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:subject
FOR UPDATE
SQL);
    $answerQuery = $db->prepare(<<<'SQL'
SELECT status,choice_position,also_correct_positions,source,recorded_at
FROM bank_official_answers WHERE workspace_id=:workspace AND question_id=:question
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $choicesQuery = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:workspace AND question_id=:question ORDER BY position');
    $nodeQuery = $db->prepare(<<<'SQL'
SELECT id FROM bank_reference_nodes
WHERE workspace_id=:workspace AND edition_id=:edition AND kind='chapter' AND number=:chapter
SQL);
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET pdf_page=:page,pdf_sha256=:sha,scope_unverified=TRUE,official_scope_note=:note
WHERE workspace_id=:workspace AND id=:source_id AND edition_id=:edition AND node_id=:node_id
 AND page <=> :old_page AND printed_page IS NULL AND pdf_page IS NULL AND pdf_sha256 IS NULL
 AND origin='human' AND reviewed_at <=> :old_reviewed AND reviewed_by_user_id <=> :old_reviewer
 AND anchor_text <=> :old_anchor AND official_scope_status='in_scope'
 AND scope_unverified=FALSE AND official_scope_note IS NULL
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
                'source_id','edition','chapter','pdf_page','pdf_sha256','evidence','note',
                'expected_legacy_page','expected_stored_printed_page','expected_anchor_sha256',
                'expected_stem_sha256','expected_choices_sha256','expected_answer_sha256',
                'expected_reviewed_at','expected_reviewed_by_user_id','expected_question_status',
                'expected_answer_status',
            ] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new RuntimeException("Q{$number}: missing {$field}.");
                }
            }
            $id=$item['source_id'];$edition=$item['edition'];$chapter=$item['chapter'];
            $page=$item['pdf_page'];$sha=strtolower((string)$item['pdf_sha256']);
            $evidence=$item['evidence'];$note=$item['note'];
            if (!is_string($id)||preg_match('/^[a-f0-9-]{36}$/D',$id)!==1
                ||!is_string($edition)||preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D',$edition)!==1
                ||!is_string($chapter)||preg_match('/^[0-9]+(?:\.[0-9]+)?$/D',$chapter)!==1
                ||!is_int($page)||$page<1||$page>6000||!preg_match('/^[a-f0-9]{64}$/D',$sha)
                ||$item['expected_legacy_page']!==null||$item['expected_stored_printed_page']!==null
                ||!is_string($note)||mb_strlen(trim($note))<24||mb_strlen($note)>300||!str_contains($note,'تأیید نشده')
                ||!is_array($evidence)||!array_is_list($evidence)||count($evidence)<1||count($evidence)>4
                ||array_filter($evidence,static fn(mixed $q):bool=>!is_string($q)||mb_strlen(trim($q))<6||strlen($q)>190)!==[]) {
                throw new RuntimeException("Q{$number}: invalid exact page or unresolved-scope note.");
            }
            if(!VerifiedHumanScopeUnverifiedPageGuard::auditMatches($audit,$plan,$item))
                throw new RuntimeException("Q{$number}: independent location/scope audit does not match the plan.");
            $sourceQuery->execute(['workspace'=>$workspace,'source_id'=>$id,'number'=>$number,'year'=>$plan['year'],'subject'=>$plan['subject']]);
            $rows=$sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($rows)!==1)throw new RuntimeException("Q{$number}: official source/validity identity is not unique.");
            $s=$rows[0];
            if($s['origin']!=='human'||$s['reviewed_at']!==$item['expected_reviewed_at']
                ||$s['reviewed_by_user_id']!==$item['expected_reviewed_by_user_id']
                ||$s['page']!==$item['expected_legacy_page']||$s['printed_page']!==null
                ||$s['pdf_page']!==null||$s['pdf_sha256']!==null
                ||$s['official_scope_status']!=='in_scope'||(bool)$s['scope_unverified']
                ||$s['official_scope_note']!==null
                ||hash('sha256',(string)$s['anchor_text'])!==$item['expected_anchor_sha256']
                ||$s['reference_key'].'@'.$s['edition_key']!==$edition
                ||(string)$s['chapter_no']!==$chapter||$s['kind']!=='chapter'||(int)$s['is_official']!==1
                ||$s['question_status']!==$item['expected_question_status'])
                throw new RuntimeException("Q{$number}: existing human source identity or scope state changed.");
            if(!VerifiedHumanScopeUnverifiedPageGuard::chapterIsPartial($s['scope_chapters'],$chapter))
                throw new RuntimeException("Q{$number}: target chapter is not an officially listed partial chapter.");
            $choicesQuery->execute(['workspace'=>$workspace,'question'=>$s['question_id']]);$choices=$choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['workspace'=>$workspace,'question'=>$s['question_id']]);$answer=$answerQuery->fetch(PDO::FETCH_ASSOC);
            if(!is_array($answer)||$answer['status']!==$item['expected_answer_status']
                ||!in_array($answer['status'],['final','amended'],true)||$answer['choice_position']===null
                ||hash('sha256',(string)$s['stem'])!==$item['expected_stem_sha256']||count($choices)!==4
                ||hash('sha256',json_encode($choices,JSON_THROW_ON_ERROR))!==$item['expected_choices_sha256']
                ||hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR))!==$item['expected_answer_sha256'])
                throw new RuntimeException("Q{$number}: question, choices or official answer fingerprint changed.");
            if(!VerifiedHumanChapterPageRepairGuard::pageInMap($maps,$edition,$chapter,$page,$sha)
                ||!PrintedBookPageEvidence::hasNoPrintedPageLabel($edition,$page,$sha)
                ||!PrintedBookPageEvidence::pageContainsEvidence($edition,$page,$evidence,$sha))
                throw new RuntimeException("Q{$number}: exact page/map/evidence or missing-label premise failed.");
            $nodeQuery->execute(['workspace'=>$workspace,'edition'=>$s['edition_id'],'chapter'=>$chapter]);$nodes=$nodeQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($nodes)!==1||$nodes[0]['id']!==$s['node_id'])
                throw new RuntimeException("Q{$number}: same-edition chapter node is not unique or changed.");
            $update->execute(['page'=>$page,'sha'=>$sha,'note'=>$note,'workspace'=>$workspace,'source_id'=>$id,
                'edition'=>$s['edition_id'],'node_id'=>$s['node_id'],'old_page'=>$item['expected_legacy_page'],
                'old_reviewed'=>$item['expected_reviewed_at'],'old_reviewer'=>$item['expected_reviewed_by_user_id'],
                'old_anchor'=>$s['anchor_text']]);
            if($update->rowCount()!==1)throw new RuntimeException("Q{$number}: scope-unverified source-only update was ambiguous.");
            $results[]=['number'=>$number,'source_id'=>$id,'edition'=>$edition,'chapter'=>$chapter,
                'pdf_page'=>$page,'pdf_sha256'=>$sha,'official_scope_status'=>'in_scope',
                'scope_unverified'=>true,'official_scope_note'=>$note];
        }
        if($apply)$db->commit();else $db->rollBack();
    } catch(Throwable $error) {
        if($db->inTransaction())$db->rollBack();
        throw $error;
    }
    $receipt=['format'=>'fanoos.verified-human-scope-unverified-page-repair-receipt/1',
        'mode'=>$apply?'applied':'rollback-preview','year'=>$plan['year'],'subject'=>$plan['subject'],
        'count'=>count($results),'plan_sha256'=>hash('sha256',$rawPlan),'audit_sha256'=>$auditSha,
        'only_fields_changed'=>['pdf_page','pdf_sha256','scope_unverified','official_scope_note'],
        'questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,
        'human_source_provenance_changed'=>0,'human_review_metadata_changed'=>0,'sources'=>$results];
    if($apply){$receipt['backup_id']=basename($backup);$receipt['at_utc']=gmdate('c');
        $handle=fopen($receiptPath,'x');if($handle===false)throw new RuntimeException('Committed but receipt missing; read back before retry.');
        chmod($receiptPath,0600);fwrite($handle,json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");fflush($handle);fclose($handle);}
    echo json_encode($receipt,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
} catch(Throwable $error) {
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    fwrite(STDERR,'Human scope-unverified source location refused: '.$error->getMessage().PHP_EOL);exit(1);
}
