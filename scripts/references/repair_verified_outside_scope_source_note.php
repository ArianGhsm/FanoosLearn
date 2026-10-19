<?php

declare(strict_types=1);

/**
 * Correct only a mistaken official_scope_note on an already verified,
 * outside-scope residency source. Preview rolls back; apply requires a fresh
 * full backup and a unique private receipt.
 */
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VerifiedOutsideScopeSourceNoteRepairGuard;
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
        || $apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace required; apply also needs backup and receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Only the authorized updater service may apply.');
    }

    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be a protected private report.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 32, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.outside-scope-source-note-repair/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid protected note-correction plan.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = strtolower((string) ($plan['audit_sha256'] ?? ''));
    if (realpath(dirname($auditPath)) !== $reports || is_link($auditPath) || !is_file($auditPath)
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha) || hash_file('sha256', $auditPath) !== $auditSha) {
        throw new RuntimeException('Independent note-correction audit is absent or changed.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 32, JSON_THROW_ON_ERROR);

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $reports || is_link($receiptPath)
            || file_exists($receiptPath) || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Verified backup or unique private receipt rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh fully verified production backup required.');
        }
    }

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $source = $db->prepare(<<<'SQL'
SELECT s.id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,s.origin,
       s.reviewed_at,s.reviewed_by_user_id,s.official_scope_status,s.official_scope_note,
       s.scope_unverified,s.is_primary,s.node_id,s.confidence_source,s.confidence_node,s.confidence_page,
       q.id AS question_id,q.question_key,q.stem,q.status AS question_status,
       q.number_in_sitting,n.number AS chapter_no,n.kind,e.edition_key,r.reference_key,
       v.scope_chapters,v.is_official
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
 AND v.exam_type_id=et.id AND v.subject_id=sb.id AND v.exam_year=si.exam_year
WHERE s.workspace_id=:ws AND s.id=:source_id AND q.number_in_sitting=:number
 AND si.exam_year=:year AND si.exam_round=1 AND et.type_key='residency' AND sb.subject_key=:subject
 AND v.is_official=1
FOR UPDATE
SQL);
    $choicesQuery = $db->prepare(
        'SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position'
    );
    $answerQuery = $db->prepare(<<<'SQL'
SELECT status,choice_position,also_correct_positions,source,recorded_at
FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET official_scope_note=:new_note
WHERE workspace_id=:ws AND id=:source_id
 AND official_scope_status='outside_scope_exam_question'
 AND SHA2(official_scope_note,256)=:old_note_sha256
 AND pdf_page=:pdf_page AND pdf_sha256=:pdf_sha256
 AND anchor_text <=> :anchor_text AND node_id=:node_id
SQL);

    $map = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'), true, 32, JSON_THROW_ON_ERROR);
    $db->beginTransaction();
    $results = [];
    try {
        $seen = [];
        foreach ($plan['entries'] as $item) {
            foreach ([
                'number','source_id','question_key','edition','chapter','printed_page','pdf_page','pdf_sha256',
                'anchor_sha256','current_note_sha256','stem_sha256','choices_sha256','answer_sha256',
                'question_status','answer_status','answer_choice_position','answer_alignment_status',
                'page_evidence','corrected_note',
            ] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new RuntimeException('Note-correction plan is missing ' . $field . '.');
                }
            }
            $number = $item['number'];
            if (!is_int($number) || $number < 1 || $number > 300 || isset($seen[$number])) {
                throw new RuntimeException('Invalid or duplicate question number.');
            }
            $seen[$number] = true;
            if (!VerifiedOutsideScopeSourceNoteRepairGuard::auditMatches($audit, $plan, $item)) {
                throw new RuntimeException("Q{$number}: independent note-correction audit differs from plan.");
            }

            $source->execute([
                'ws'=>$workspace,'source_id'=>$item['source_id'],'number'=>$number,
                'year'=>$plan['year'],'subject'=>$plan['subject'],
            ]);
            $rows = $source->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1) {
                throw new RuntimeException("Q{$number}: source and official validity identity is not unique.");
            }
            $row = $rows[0];
            $edition = $row['reference_key'] . '@' . $row['edition_key'];
            if ($row['question_key'] !== $item['question_key']
                || $row['question_status'] !== $item['question_status'] || $row['question_status'] !== 'published'
                || $row['origin'] !== 'ai' || $row['reviewed_at'] !== null || $row['reviewed_by_user_id'] !== null
                || $row['official_scope_status'] !== 'outside_scope_exam_question'
                || !is_string($row['official_scope_note'])
                || hash('sha256', $row['official_scope_note']) !== $item['current_note_sha256']
                || $edition !== $item['edition'] || (string) $row['chapter_no'] !== $item['chapter']
                || $row['kind'] !== 'chapter' || (int) $row['is_official'] !== 1
                || (bool) $row['scope_unverified'] || !(bool) $row['is_primary']
                || (string) $row['printed_page'] !== $item['printed_page']
                || (int) $row['pdf_page'] !== $item['pdf_page'] || $row['pdf_sha256'] !== $item['pdf_sha256']
                || hash('sha256', (string) $row['anchor_text']) !== $item['anchor_sha256']
                || hash('sha256', (string) $row['stem']) !== $item['stem_sha256']) {
                throw new RuntimeException("Q{$number}: source, note or question identity changed.");
            }

            $scope = json_decode((string) $row['scope_chapters'], true, 32, JSON_THROW_ON_ERROR);
            if (OfficialScopePageGuard::pageDisposition($scope, $item['chapter'], $item['printed_page']) !== false) {
                throw new RuntimeException("Q{$number}: cited source is no longer outside the official scope.");
            }
            $mapEntry = $map['editions'][$edition] ?? null;
            $inside = false;
            foreach (($mapEntry['runs'] ?? []) as $run) {
                if (is_array($run) && count($run) === 3 && (string) $run[0] === $item['chapter']
                    && $item['pdf_page'] >= $run[1] && $item['pdf_page'] <= $run[2]) {
                    $inside = true;
                    break;
                }
            }
            if (!is_array($mapEntry) || ($mapEntry['source_pdf_sha256'] ?? null) !== $item['pdf_sha256']
                || !$inside
                || !PrintedBookPageEvidence::corroborates($edition, $item['pdf_page'], $item['printed_page'], $item['pdf_sha256'])
                || !PrintedBookPageEvidence::pageContainsEvidence($edition, $item['pdf_page'], [$item['page_evidence']], $item['pdf_sha256'])) {
                throw new RuntimeException("Q{$number}: source page, printed label, or original evidence failed.");
            }

            $choicesQuery->execute(['ws'=>$workspace,'qid'=>$row['question_id']]);
            $choices = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['ws'=>$workspace,'qid'=>$row['question_id']]);
            $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
            if (count($choices) !== 4 || hash('sha256', json_encode($choices, JSON_THROW_ON_ERROR)) !== $item['choices_sha256']
                || !is_array($answer) || $answer['status'] !== $item['answer_status']
                || !in_array($answer['status'], ['final','amended'], true)
                || (int) $answer['choice_position'] !== $item['answer_choice_position']
                || hash('sha256', json_encode($answer, JSON_THROW_ON_ERROR)) !== $item['answer_sha256']) {
                throw new RuntimeException("Q{$number}: choices or official key changed.");
            }

            $update->execute([
                'new_note'=>$item['corrected_note'],'ws'=>$workspace,'source_id'=>$item['source_id'],
                'old_note_sha256'=>$item['current_note_sha256'],'pdf_page'=>$item['pdf_page'],
                'pdf_sha256'=>$item['pdf_sha256'],'anchor_text'=>$row['anchor_text'],'node_id'=>$row['node_id'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: source-note update was ambiguous.");
            }

            $readback = $db->prepare(<<<'SQL'
SELECT id,node_id,page,printed_page,pdf_page,pdf_sha256,anchor_text,origin,
       reviewed_at,reviewed_by_user_id,official_scope_status,official_scope_note,
       scope_unverified,is_primary,confidence_source,confidence_node,confidence_page
FROM bank_question_sources WHERE workspace_id=:ws AND id=:id AND question_id=:qid
SQL);
            $readback->execute(['ws'=>$workspace,'id'=>$item['source_id'],'qid'=>$row['question_id']]);
            $after = $readback->fetch(PDO::FETCH_ASSOC);
            $newNote = $after['official_scope_note'] ?? null;
            $unchanged = is_array($after);
            foreach ([
                'id','node_id','page','printed_page','pdf_page','pdf_sha256','anchor_text','origin',
                'reviewed_at','reviewed_by_user_id','official_scope_status','scope_unverified','is_primary',
                'confidence_source','confidence_node','confidence_page',
            ] as $field) {
                $unchanged = $unchanged && ($after[$field] ?? null) === ($row[$field] ?? null);
            }
            if (!$unchanged || !is_string($newNote) || $newNote !== $item['corrected_note']) {
                throw new RuntimeException("Q{$number}: corrected source note failed immediate readback.");
            }
            $results[] = [
                'number'=>$number,'source_id'=>$item['source_id'],'old_note_sha256'=>$item['current_note_sha256'],
                'new_note_sha256'=>hash('sha256', $newNote),'official_answer_status'=>$answer['status'],
                'official_answer_choice_position'=>(int)$answer['choice_position'],
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
        'format'=>'fanoos.outside-scope-source-note-repair-receipt/1',
        'mode'=>$apply ? 'applied' : 'rollback-preview','year'=>$plan['year'],'subject'=>$plan['subject'],
        'count'=>count($results),'plan_sha256'=>hash('sha256',$rawPlan),'audit_sha256'=>$auditSha,
        'only_fields_changed'=>['official_scope_note'],'questions_changed'=>0,'choices_changed'=>0,
        'answers_changed'=>0,'assessment_versions_changed'=>0,'attempts_changed'=>0,'sources'=>$results,
    ];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $receipt['at_utc'] = gmdate('c');
        $handle = fopen((string)$args['receipt'], 'x');
        if ($handle === false) {
            throw new RuntimeException('Committed but unique receipt is missing; read back before retrying.');
        }
        chmod((string)$args['receipt'], 0600);
        fwrite($handle, json_encode($receipt, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) . "\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($receipt, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Outside-scope note repair refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
