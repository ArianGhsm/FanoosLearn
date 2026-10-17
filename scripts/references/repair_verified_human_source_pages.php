<?php

declare(strict_types=1);

/**
 * Add an exact PDF page and current file hash to an existing human-origin
 * residency source without changing its human citation or review metadata.
 * The protected research plan and audit pin the complete current question,
 * answer, choice, and source fingerprints. Preview always rolls back.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
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
            throw new RuntimeException('Unknown or duplicate protected argument.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $apply = isset($args['apply']);
    if (!preg_match('/^[a-f0-9-]{36}$/D', $workspace)
        || $apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace required; apply also needs a verified backup and unique receipt.');
    }
    if ($apply && (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd') {
        throw new RuntimeException('Only the authorized updater service may apply.');
    }

    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be a private protected report.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 48, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.verified-human-source-page-repair/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid year, subject, or plan size.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = strtolower((string) ($plan['audit_sha256'] ?? ''));
    if (realpath(dirname($auditPath)) !== $reports || is_link($auditPath) || !is_file($auditPath)
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha) || hash_file('sha256', $auditPath) !== $auditSha) {
        throw new RuntimeException('Independent private page audit is absent or changed.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 64, JSON_THROW_ON_ERROR);
    if (($audit['format'] ?? null) !== 'fanoos.human-source-pdf-page-audit/1'
        || ($audit['research_only'] ?? null) !== true
        || ($audit['year'] ?? null) !== $plan['year']
        || ($audit['subject'] ?? null) !== $plan['subject']
        || !is_array($audit['records'] ?? null)) {
        throw new RuntimeException('Human-source audit scope or format is invalid.');
    }

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $reports
            || is_link($receiptPath) || file_exists($receiptPath) || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Protected backup or unique receipt path rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh verified full backup required.');
        }
    }

    $maps = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 48, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $read = $db->prepare(<<<'SQL'
SELECT s.id,s.workspace_id,s.question_id,s.node_id,s.edition_id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,
       s.anchor_text,s.origin,s.reviewed_at,s.reviewed_by_user_id,
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
WHERE s.workspace_id=:ws AND s.id=:sid AND q.number_in_sitting=:number
 AND si.exam_year=:year AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:subject
FOR UPDATE
SQL);
    $choicesQuery = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $answerQuery = $db->prepare('SELECT status,choice_position,also_correct_positions,source,recorded_at FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET pdf_page=:page,pdf_sha256=:sha
WHERE workspace_id=:ws AND id=:sid AND pdf_page IS NULL AND pdf_sha256 IS NULL
 AND page <=> :old_page AND printed_page <=> :old_printed AND anchor_text <=> :old_anchor
 AND origin='human' AND reviewed_at <=> :old_reviewed AND reviewed_by_user_id <=> :old_reviewer
SQL);

    $auditRows = [];
    foreach ($audit['records'] as $record) {
        if (is_array($record) && is_int($record['number'] ?? null)) {
            $auditRows[$record['number']][] = $record;
        }
    }
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
            foreach (['source_id','edition','chapter','pdf_page','pdf_sha256','expected_legacy_page',
                'expected_stored_printed_page','expected_anchor_sha256','expected_stem_sha256',
                'expected_choices_sha256','expected_answer_sha256','expected_reviewed_at',
                'expected_reviewed_by_user_id','expected_question_status','expected_answer_status','evidence'] as $field) {
                if (!array_key_exists($field, $item)) throw new RuntimeException("Q{$number}: missing $field.");
            }
            $sourceId = $item['source_id']; $edition = $item['edition']; $chapter = $item['chapter'];
            $page = $item['pdf_page']; $sha = strtolower((string) $item['pdf_sha256']);
            $evidenceValue = $item['evidence'] ?? null;
            $evidenceQuotes = is_string($evidenceValue) ? [$evidenceValue] : $evidenceValue;
            if (!is_array($evidenceQuotes) || !array_is_list($evidenceQuotes)
                || count($evidenceQuotes) < 1 || count($evidenceQuotes) > 4
                || array_filter($evidenceQuotes, static fn(mixed $quote): bool =>
                    !is_string($quote) || mb_strlen(trim($quote)) < 6 || strlen($quote) > 190) !== []) {
                throw new RuntimeException("Q{$number}: provide one to four short verbatim page excerpts.");
            }
            if (!is_string($sourceId) || preg_match('/^[a-f0-9-]{36}$/D', $sourceId) !== 1
                || !is_string($edition) || preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $edition) !== 1
                || !is_string($chapter) || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $chapter) !== 1
                || !is_int($page) || $page < 1 || $page > 6000
                || !preg_match('/^[a-f0-9]{64}$/D', $sha)
                || !is_array($evidenceQuotes)) {
                throw new RuntimeException("Q{$number}: invalid source/page/evidence identity.");
            }
            $matches = $auditRows[$number] ?? [];
            if (count($matches) !== 1 || ($matches[0]['scientifically_accepted'] ?? null) !== true) {
                throw new RuntimeException("Q{$number}: exact independently accepted audit record required.");
            }
            foreach (['source_id','edition','chapter','pdf_page','pdf_sha256','evidence',
                'expected_stem_sha256','expected_choices_sha256','expected_answer_sha256','expected_anchor_sha256'] as $field) {
                if (($matches[0][$field] ?? null) !== ($item[$field] ?? null)) {
                    throw new RuntimeException("Q{$number}: audit and protected plan differ at $field.");
                }
            }
            if (($matches[0]['expected_question_status'] ?? null) !== $item['expected_question_status']
                || ($matches[0]['expected_answer_status'] ?? null) !== $item['expected_answer_status']) {
                throw new RuntimeException("Q{$number}: question/key status differs from its independent audit.");
            }

            $read->execute(['ws'=>$workspace,'sid'=>$sourceId,'number'=>$number,
                'year'=>$plan['year'],'subject'=>$plan['subject']]);
            $sources = $read->fetchAll(PDO::FETCH_ASSOC);
            if (count($sources) !== 1) throw new RuntimeException("Q{$number}: source identity is not unique.");
            $s = $sources[0];
            $choicesQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
            $choiceRows = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
            $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
            $answerIsArray = is_array($answer);
            $publishedScorable = $answerIsArray && $s['question_status'] === 'published'
                && in_array($answer['status'] ?? null, ['final','amended'], true)
                && ($answer['choice_position'] ?? null) !== null;
            $draftVoided = $answerIsArray && $s['question_status'] === 'draft'
                && ($answer['status'] ?? null) === 'voided'
                && ($answer['choice_position'] ?? null) === null;
            if ((!$publishedScorable && !$draftVoided)
                || $s['question_status'] !== $item['expected_question_status']
                || ($answer['status'] ?? null) !== $item['expected_answer_status']
                || $s['origin'] !== 'human'
                || $s['reference_key'].'@'.$s['edition_key'] !== $edition
                || (string)$s['chapter_no'] !== $chapter || $s['kind'] !== 'chapter' || (int)$s['is_official'] !== 1
                || $s['page'] !== $item['expected_legacy_page']
                || $s['printed_page'] !== $item['expected_stored_printed_page']
                || $s['reviewed_at'] !== $item['expected_reviewed_at']
                || $s['reviewed_by_user_id'] !== $item['expected_reviewed_by_user_id']
                || $s['pdf_page'] !== null || $s['pdf_sha256'] !== null
                || hash('sha256',(string)$s['stem']) !== $item['expected_stem_sha256']
                || hash('sha256',(string)$s['anchor_text']) !== $item['expected_anchor_sha256']
                || hash('sha256',json_encode($choiceRows,JSON_THROW_ON_ERROR)) !== $item['expected_choices_sha256']
                || !is_array($answer)
                || hash('sha256',json_encode($answer,JSON_THROW_ON_ERROR)) !== $item['expected_answer_sha256']) {
                throw new RuntimeException("Q{$number}: human source, review, question, choices, key or state changed.");
            }
            if (count($choiceRows) !== 4) throw new RuntimeException("Q{$number}: expected four official choices.");
            $scope = json_decode((string)$s['scope_chapters'],true,64,JSON_THROW_ON_ERROR);
            $allowed = false;
            foreach (is_array($scope)?$scope:[] as $entry) if ((string)($entry['number']??'')===$chapter && empty($entry['partial'])) $allowed=true;
            if (!$allowed) throw new RuntimeException("Q{$number}: chapter is outside complete official scope.");
            $map=$maps['editions'][$edition]??[];
            if (($map['source_pdf_sha256']??null)!==$sha) throw new RuntimeException("Q{$number}: chapter map PDF SHA differs.");
            $inside=false; foreach($map['runs']??[] as $run) if(is_array($run)&&count($run)===3&&(string)$run[0]===$chapter&&$page>=$run[1]&&$page<=$run[2])$inside=true;
            if(!$inside)throw new RuntimeException("Q{$number}: PDF page is outside the cited chapter.");
            if (is_string($s['printed_page']) && $s['printed_page'] !== '') {
                if (!preg_match('/^[1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?$/D',$s['printed_page'])
                    || !PrintedBookPageEvidence::corroborates($edition,$page,$s['printed_page'],$sha)) {
                    throw new RuntimeException("Q{$number}: stored human printed label does not match exact PDF page.");
                }
            }
            if (!PrintedBookPageEvidence::pageContainsEvidence($edition,$page,$evidenceQuotes,$sha)) {
                throw new RuntimeException("Q{$number}: exact original-page evidence failed.");
            }
            $update->execute(['page'=>$page,'sha'=>$sha,'ws'=>$workspace,'sid'=>$sourceId,
                'old_page'=>$item['expected_legacy_page'],'old_printed'=>$item['expected_stored_printed_page'],
                'old_anchor'=>$s['anchor_text'],'old_reviewed'=>$item['expected_reviewed_at'],
                'old_reviewer'=>$item['expected_reviewed_by_user_id']]);
            if($update->rowCount()!==1)throw new RuntimeException("Q{$number}: guarded human source page update ambiguous.");
            $results[]=['number'=>$number,'source_id'=>$sourceId,'edition'=>$edition,'chapter'=>$chapter,
                'pdf_page'=>$page,'printed_page'=>$s['printed_page'],'pdf_sha256'=>$sha];
        }
        if($apply)$db->commit();else $db->rollBack();
    } catch(Throwable $error) {
        if($db->inTransaction())$db->rollBack(); throw $error;
    }
    $receipt=['format'=>'fanoos.verified-human-source-page-repair-receipt/1','mode'=>$apply?'applied':'rollback-preview',
        'year'=>$plan['year'],'subject'=>$plan['subject'],'count'=>count($results),'plan_sha256'=>hash('sha256',$rawPlan),
        'audit_sha256'=>$auditSha,'only_fields_changed'=>['pdf_page','pdf_sha256'],'questions_changed'=>0,
        'choices_changed'=>0,'answers_changed'=>0,'human_source_provenance_changed'=>0,'human_review_metadata_changed'=>0,'sources'=>$results];
    if($apply){$receipt['backup_id']=basename($backup);$receipt['at_utc']=gmdate('c');$fp=fopen($receiptPath,'x');if($fp===false)throw new RuntimeException('Source committed but unique receipt unavailable; read back before retry.');chmod($receiptPath,0600);fwrite($fp,json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");fclose($fp);}
    echo json_encode($receipt,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
} catch(Throwable $e) {
    if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
    fwrite(STDERR,'Human source PDF page repair refused: '.$e->getMessage().PHP_EOL);exit(1);
}
