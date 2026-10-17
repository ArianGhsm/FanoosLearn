<?php

declare(strict_types=1);

/**
 * Correct an AI source's chapter and bind an exact original PDF page when
 * the verified chapter is outside the official residency scope. Preview
 * runs the source-only transaction and rolls it back; apply requires a
 * fresh full backup and a unique private receipt.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Bank\VerifiedAiOutsideScopeChapterPageGuard;
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
        throw new RuntimeException('Workspace required; apply requires backup and receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
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
    if (($plan['format'] ?? null) !== 'fanoos.verified-ai-outside-scope-chapter-page-repair/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid protected residency plan scope or size.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = strtolower((string) ($plan['audit_sha256'] ?? ''));
    if (realpath(dirname($auditPath)) !== $reports || is_link($auditPath) || !is_file($auditPath)
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha) || hash_file('sha256', $auditPath) !== $auditSha) {
        throw new RuntimeException('Independent private audit is absent or changed.');
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
            throw new RuntimeException('Fresh fully verified production backup required.');
        }
    }

    $maps = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 48, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.workspace_id,s.question_id,s.node_id,s.edition_id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,
       s.anchor_text,s.origin,s.reviewed_at,s.reviewed_by_user_id,s.confidence_page,
       s.official_scope_status,s.official_scope_note,
       q.number_in_sitting,q.stem,q.status question_status,
       n.number old_chapter,n.kind,e.edition_key,r.reference_key,v.scope_chapters,v.is_official
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
WHERE s.workspace_id=:ws AND s.id=:sid AND q.number_in_sitting=:number
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
    $nodeQuery = $db->prepare(<<<'SQL'
SELECT id FROM bank_reference_nodes
WHERE workspace_id=:ws AND edition_id=:edition AND kind='chapter' AND number=:chapter
SQL);
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET node_id=:new_node,page=:new_page,printed_page=:printed,pdf_page=:pdf_page,pdf_sha256=:pdf_sha,
    official_scope_status='outside_scope_exam_question',official_scope_note=:scope_note,
    confidence_page=:new_confidence
WHERE workspace_id=:ws AND id=:id AND node_id=:old_node
 AND page <=> :old_page AND printed_page <=> :old_printed
 AND pdf_page <=> :old_pdf_page AND pdf_sha256 <=> :old_pdf_sha
 AND confidence_page=:old_confidence
 AND official_scope_status='in_scope' AND official_scope_note IS NULL
 AND origin='ai' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
 AND anchor_text <=> :old_anchor
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
                'source_id','edition','expected_chapter','chapter','printed_page','pdf_page','pdf_sha256',
                'evidence','note','expected_legacy_page','expected_stored_printed_page','expected_pdf_page',
                'expected_pdf_sha256','expected_old_confidence_page','new_confidence_page',
                'expected_scope_status','expected_scope_note','expected_anchor_sha256','expected_stem_sha256',
                'expected_choices_sha256','expected_answer_sha256','expected_question_status','expected_answer_status',
            ] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new RuntimeException("Q{$number}: missing {$field}.");
                }
            }
            $id = $item['source_id'];
            $edition = $item['edition'];
            $oldChapter = $item['expected_chapter'];
            $chapter = $item['chapter'];
            $printed = $item['printed_page'];
            $page = $item['pdf_page'];
            $sha = strtolower((string) $item['pdf_sha256']);
            $note = $item['note'];
            $evidence = $item['evidence'];
            $oldConfidence = $item['expected_old_confidence_page'];
            $newConfidence = $item['new_confidence_page'];
            if (!is_string($id) || preg_match('/^[a-f0-9-]{36}$/D', $id) !== 1
                || !is_string($edition) || preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $edition) !== 1
                || !is_string($oldChapter) || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $oldChapter) !== 1
                || !is_string($chapter) || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $chapter) !== 1
                || $oldChapter === $chapter
                || !is_string($printed) || preg_match('/^[1-9][0-9]{0,3}$/D', $printed) !== 1
                || !is_int($page) || $page < 1 || $page > 6000
                || preg_match('/^[a-f0-9]{64}$/D', $sha) !== 1
                || !is_string($note) || mb_strlen(trim($note)) < 12 || !str_contains($note, 'خارج از محدوده')
                || !is_array($evidence) || !array_is_list($evidence) || count($evidence) < 1 || count($evidence) > 4
                || array_filter($evidence, static fn (mixed $quote): bool => !is_string($quote)
                    || mb_strlen(trim($quote)) < 6 || strlen($quote) > 190) !== []
                || !is_string($oldConfidence) || !is_numeric($oldConfidence)
                || !is_string($newConfidence) || !is_numeric($newConfidence)
                || (float) $newConfidence < 0.85 || (float) $newConfidence > 1.0
                || (float) $newConfidence < (float) $oldConfidence
                || $item['expected_scope_status'] !== 'in_scope'
                || $item['expected_scope_note'] !== null) {
                throw new RuntimeException("Q{$number}: invalid protected outside-scope source plan.");
            }
            if (!VerifiedAiOutsideScopeChapterPageGuard::auditMatches($audit, $plan, $item)) {
                throw new RuntimeException("Q{$number}: independent accepted audit differs from protected plan.");
            }

            $sourceQuery->execute([
                'ws'=>$workspace,'sid'=>$id,'number'=>$number,'year'=>$plan['year'],'subject'=>$plan['subject'],
            ]);
            $sources = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($sources) !== 1) {
                throw new RuntimeException("Q{$number}: source/official-year edition is not unique.");
            }
            $s = $sources[0];
            if ($s['origin'] !== 'ai' || $s['reviewed_at'] !== null || $s['reviewed_by_user_id'] !== null
                || $s['page'] !== $item['expected_legacy_page']
                || $s['printed_page'] !== $item['expected_stored_printed_page']
                || ($s['pdf_page'] === null ? null : (int) $s['pdf_page']) !== $item['expected_pdf_page']
                || $s['pdf_sha256'] !== $item['expected_pdf_sha256']
                || $s['official_scope_status'] !== $item['expected_scope_status']
                || $s['official_scope_note'] !== $item['expected_scope_note']
                || $s['official_scope_status'] !== 'in_scope' || $s['official_scope_note'] !== null
                || (string) $s['confidence_page'] !== $oldConfidence
                || hash('sha256', (string) $s['anchor_text']) !== $item['expected_anchor_sha256']
                || $s['reference_key'] . '@' . $s['edition_key'] !== $edition
                || (string) $s['old_chapter'] !== $oldChapter || $s['kind'] !== 'chapter'
                || (int) $s['is_official'] !== 1
                || $s['question_status'] !== $item['expected_question_status']) {
                throw new RuntimeException("Q{$number}: source, confidence, scope or question state changed.");
            }

            $scope = json_decode((string) $s['scope_chapters'], true, 32, JSON_THROW_ON_ERROR);
            if (OfficialScopePageGuard::pageDisposition($scope, $chapter, $printed) !== false) {
                throw new RuntimeException("Q{$number}: target chapter/page is inside or ambiguous against official scope.");
            }
            $choicesQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
            $choices = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
            $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
            if ($s['question_status'] !== 'published' || !is_array($answer)
                || !in_array($answer['status'] ?? null, ['final','amended'], true)
                || ($answer['choice_position'] ?? null) === null
                || $answer['status'] !== $item['expected_answer_status']
                || count($choices) !== 4
                || hash('sha256', (string) $s['stem']) !== $item['expected_stem_sha256']
                || hash('sha256', json_encode($choices, JSON_THROW_ON_ERROR)) !== $item['expected_choices_sha256']
                || hash('sha256', json_encode($answer, JSON_THROW_ON_ERROR)) !== $item['expected_answer_sha256']) {
                throw new RuntimeException("Q{$number}: question, choices, official answer or status changed.");
            }

            $entry = $maps['editions'][$edition] ?? null;
            if (!is_array($entry) || ($entry['source_pdf_sha256'] ?? null) !== $sha
                || !is_array($entry['runs'] ?? null)) {
                throw new RuntimeException("Q{$number}: chapter map is not bound to the approved PDF.");
            }
            $insideChapter = false;
            foreach ($entry['runs'] as $run) {
                if (is_array($run) && count($run) === 3 && (string) $run[0] === $chapter
                    && $page >= $run[1] && $page <= $run[2]) {
                    $insideChapter = true;
                    break;
                }
            }
            if (!$insideChapter
                || !PrintedBookPageEvidence::corroborates($edition, $page, $printed, $sha)
                || !PrintedBookPageEvidence::pageContainsEvidence($edition, $page, $evidence, $sha)) {
                throw new RuntimeException("Q{$number}: exact outside-scope chapter/page, label or evidence failed.");
            }
            $nodeQuery->execute(['ws'=>$workspace,'edition'=>$s['edition_id'],'chapter'=>$chapter]);
            $nodes = $nodeQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($nodes) !== 1) {
                throw new RuntimeException("Q{$number}: target chapter node is not unique.");
            }

            $update->execute([
                'new_node'=>$nodes[0]['id'],'new_page'=>$printed,'printed'=>$printed,'pdf_page'=>$page,'pdf_sha'=>$sha,
                'scope_note'=>$note,'new_confidence'=>$newConfidence,'ws'=>$workspace,'id'=>$id,'old_node'=>$s['node_id'],
                'old_page'=>$item['expected_legacy_page'],'old_printed'=>$item['expected_stored_printed_page'],
                'old_pdf_page'=>$item['expected_pdf_page'],'old_pdf_sha'=>$item['expected_pdf_sha256'],
                'old_confidence'=>$oldConfidence,'old_anchor'=>$s['anchor_text'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: guarded outside-scope source update is ambiguous.");
            }
            $results[] = [
                'number'=>$number,'source_id'=>$id,'edition'=>$edition,'old_chapter'=>$oldChapter,'chapter'=>$chapter,
                'page'=>$printed,'printed_page'=>$printed,'pdf_page'=>$page,'pdf_sha256'=>$sha,
                'old_confidence_page'=>$oldConfidence,'confidence_page'=>$newConfidence,
                'official_scope_status'=>'outside_scope_exam_question','official_scope_note'=>$note,
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
        'format'=>'fanoos.verified-ai-outside-scope-chapter-page-repair-receipt/1',
        'mode'=>$apply ? 'applied' : 'rollback-preview','year'=>$plan['year'],'subject'=>$plan['subject'],
        'count'=>count($results),'plan_sha256'=>hash('sha256',$rawPlan),'audit_sha256'=>$auditSha,
        'only_fields_changed'=>['node_id','page','printed_page','pdf_page','pdf_sha256','official_scope_status','official_scope_note','confidence_page'],
        'questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,
        'assessment_versions_changed'=>0,'attempts_changed'=>0,'sources'=>$results,
    ];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $receipt['at_utc'] = gmdate('c');
        $handle = fopen($receiptPath, 'x');
        if ($handle === false) {
            throw new RuntimeException('Committed but unique receipt missing; independently read back before retry.');
        }
        chmod($receiptPath, 0600);
        fwrite($handle, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'AI outside-scope chapter/page repair refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
