<?php

declare(strict_types=1);

/**
 * Correct an existing human source to a different exact official edition,
 * chapter and hash-bound original PDF page. Only source metadata is changed;
 * the original question, choices, key and human provenance stay intact.
 * Preview always rolls back. Apply requires a fresh verified backup and a
 * unique private receipt.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VerifiedHumanChapterPageRepairGuard;
use Fanoos\Platform\Bank\VerifiedHumanReferenceChapterPageGuard;
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
    if (($plan['format'] ?? null) !== 'fanoos.verified-human-reference-chapter-page-repair/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid year, subject or protected plan size.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = strtolower((string) ($plan['audit_sha256'] ?? ''));
    if (realpath(dirname($auditPath)) !== $reports || is_link($auditPath) || !is_file($auditPath)
        || (fileperms($auditPath) & 0077) !== 0
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha) || hash_file('sha256', $auditPath) !== $auditSha) {
        throw new RuntimeException('Independent private chapter/page audit is absent or changed.');
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
SELECT s.id,s.workspace_id,s.question_id,s.node_id,s.edition_id,s.page,s.printed_page,s.pdf_page,
       LOWER(s.pdf_sha256) AS pdf_sha256,s.anchor_text,s.origin,s.reviewed_at,s.reviewed_by_user_id,
       s.official_scope_status,s.official_scope_note,q.number_in_sitting,q.stem,q.status question_status,
       old_node.number old_chapter,old_node.kind old_kind,old_edition.edition_key old_edition_key,
       old_reference.reference_key old_reference_key,old_validity.is_official old_is_official,
       target_edition.id target_edition_id,target_edition.edition_key target_edition_key,
       target_reference.reference_key target_reference_key,target_validity.scope_chapters target_scope,
       target_validity.is_official target_is_official
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings sitting ON sitting.id=q.sitting_id AND sitting.workspace_id=q.workspace_id
JOIN bank_exam_types exam_type ON exam_type.id=sitting.exam_type_id AND exam_type.workspace_id=sitting.workspace_id
JOIN bank_subjects subject ON subject.id=q.subject_id AND subject.workspace_id=q.workspace_id
JOIN bank_reference_editions old_edition ON old_edition.id=s.edition_id AND old_edition.workspace_id=s.workspace_id
JOIN bank_references old_reference ON old_reference.id=old_edition.reference_id AND old_reference.workspace_id=old_edition.workspace_id
JOIN bank_reference_nodes old_node ON old_node.id=s.node_id AND old_node.workspace_id=s.workspace_id
JOIN bank_reference_validity old_validity ON old_validity.edition_id=old_edition.id
 AND old_validity.workspace_id=s.workspace_id AND old_validity.exam_type_id=exam_type.id
 AND old_validity.subject_id=subject.id AND old_validity.exam_year=sitting.exam_year
JOIN bank_reference_editions target_edition ON target_edition.workspace_id=s.workspace_id
JOIN bank_references target_reference ON target_reference.id=target_edition.reference_id
 AND target_reference.workspace_id=target_edition.workspace_id
JOIN bank_reference_validity target_validity ON target_validity.edition_id=target_edition.id
 AND target_validity.workspace_id=s.workspace_id AND target_validity.exam_type_id=exam_type.id
 AND target_validity.subject_id=subject.id AND target_validity.exam_year=sitting.exam_year
WHERE s.workspace_id=:workspace AND s.id=:source_id AND q.number_in_sitting=:number
 AND sitting.exam_year=:year AND sitting.exam_round=1 AND exam_type.type_key='residency'
 AND subject.subject_key=:subject AND old_validity.is_official=1 AND target_validity.is_official=1
 AND target_reference.reference_key=:target_reference AND target_edition.edition_key=:target_edition
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
SET edition_id=:new_edition,node_id=:new_node,pdf_page=:page,pdf_sha256=:sha
WHERE workspace_id=:workspace AND id=:source_id AND edition_id=:old_edition AND node_id=:old_node
 AND page <=> :old_page AND printed_page <=> :old_printed AND pdf_page IS NULL AND pdf_sha256 IS NULL
 AND origin='human' AND reviewed_at <=> :old_reviewed AND reviewed_by_user_id <=> :old_reviewer
 AND anchor_text <=> :old_anchor AND official_scope_status='in_scope' AND official_scope_note IS NULL
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
                'source_id','expected_edition','edition','expected_chapter','chapter','pdf_page','pdf_sha256',
                'expected_legacy_page','expected_stored_printed_page','expected_old_pdf_page','expected_old_pdf_sha256',
                'expected_official_scope_status','expected_official_scope_note','expected_anchor_sha256',
                'expected_stem_sha256','expected_choices_sha256','expected_answer_sha256',
                'expected_reviewed_at','expected_reviewed_by_user_id','expected_question_status',
                'expected_answer_status','evidence',
            ] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new RuntimeException("Q{$number}: missing {$field}.");
                }
            }

            $id = $item['source_id'];
            $oldEdition = $item['expected_edition'];
            $newEdition = $item['edition'];
            $oldChapter = $item['expected_chapter'];
            $chapter = $item['chapter'];
            $page = $item['pdf_page'];
            $sha = strtolower((string) $item['pdf_sha256']);
            $evidence = $item['evidence'];
            if (!is_string($id) || preg_match('/^[a-f0-9-]{36}$/D', $id) !== 1
                || !is_string($oldEdition) || preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $oldEdition) !== 1
                || !is_string($newEdition) || preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $newEdition) !== 1
                || $oldEdition === $newEdition
                || !is_string($oldChapter) || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $oldChapter) !== 1
                || !is_string($chapter) || preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $chapter) !== 1
                || !is_int($page) || $page < 1 || $page > 6000
                || !preg_match('/^[a-f0-9]{64}$/D', $sha)
                || $item['expected_old_pdf_page'] !== null || $item['expected_old_pdf_sha256'] !== null
                || !is_array($evidence) || !array_is_list($evidence) || count($evidence) < 1 || count($evidence) > 4
                || array_filter($evidence, static fn (mixed $quote): bool =>
                    !is_string($quote) || mb_strlen(trim($quote)) < 6 || strlen($quote) > 190) !== []) {
                throw new RuntimeException("Q{$number}: invalid protected source/page/evidence identity.");
            }
            foreach (['expected_legacy_page','expected_stored_printed_page','expected_official_scope_note','expected_reviewed_at',
                'expected_reviewed_by_user_id'] as $nullable) {
                if ($item[$nullable] !== null && !is_string($item[$nullable]) && !is_int($item[$nullable])) {
                    throw new RuntimeException("Q{$number}: invalid old source snapshot.");
                }
            }
            if (!in_array($item['expected_official_scope_status'], ['in_scope'], true)
                || !VerifiedHumanReferenceChapterPageGuard::auditMatches($audit, $plan, $item)) {
                throw new RuntimeException("Q{$number}: accepted cross-edition audit differs from the plan.");
            }

            [$targetReference, $targetEditionKey] = explode('@', $newEdition, 2);
            $sourceQuery->execute([
                'workspace'=>$workspace,'source_id'=>$id,'number'=>$number,'year'=>$plan['year'],
                'subject'=>$plan['subject'],'target_reference'=>$targetReference,'target_edition'=>$targetEditionKey,
            ]);
            $sources = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($sources) !== 1) {
                throw new RuntimeException("Q{$number}: old and target official-year source identities are not unique.");
            }
            $source = $sources[0];
            if ($source['origin'] !== 'human' || $source['reviewed_at'] !== $item['expected_reviewed_at']
                || $source['reviewed_by_user_id'] !== $item['expected_reviewed_by_user_id']
                || $source['pdf_page'] !== null || $source['pdf_sha256'] !== null
                || $source['page'] !== $item['expected_legacy_page']
                || $source['printed_page'] !== $item['expected_stored_printed_page']
                || $source['official_scope_status'] !== $item['expected_official_scope_status']
                || $source['official_scope_note'] !== $item['expected_official_scope_note']
                || hash('sha256', (string) $source['anchor_text']) !== $item['expected_anchor_sha256']
                || $source['old_reference_key'] . '@' . $source['old_edition_key'] !== $oldEdition
                || (string) $source['old_chapter'] !== $oldChapter || $source['old_kind'] !== 'chapter'
                || (int) $source['old_is_official'] !== 1
                || $source['target_reference_key'] . '@' . $source['target_edition_key'] !== $newEdition
                || (int) $source['target_is_official'] !== 1
                || $source['question_status'] !== $item['expected_question_status']) {
                throw new RuntimeException("Q{$number}: human source, citation, scope, review or question identity changed.");
            }

            $choicesQuery->execute(['workspace'=>$workspace,'question'=>$source['question_id']]);
            $choices = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);
            $answerQuery->execute(['workspace'=>$workspace,'question'=>$source['question_id']]);
            $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
            $publishedScorable = is_array($answer) && $source['question_status'] === 'published'
                && in_array($answer['status'] ?? null, ['final','amended'], true)
                && ($answer['choice_position'] ?? null) !== null;
            $draftVoided = is_array($answer) && $source['question_status'] === 'draft'
                && ($answer['status'] ?? null) === 'voided' && ($answer['choice_position'] ?? null) === null;
            if ((!$publishedScorable && !$draftVoided) || !is_array($answer)
                || $answer['status'] !== $item['expected_answer_status']
                || hash('sha256', (string) $source['stem']) !== $item['expected_stem_sha256']
                || count($choices) !== 4
                || hash('sha256', json_encode($choices, JSON_THROW_ON_ERROR)) !== $item['expected_choices_sha256']
                || hash('sha256', json_encode($answer, JSON_THROW_ON_ERROR)) !== $item['expected_answer_sha256']) {
                throw new RuntimeException("Q{$number}: question, choices, official key or score state changed.");
            }

            if (!VerifiedHumanChapterPageRepairGuard::scopeAllows($source['target_scope'], $chapter)) {
                throw new RuntimeException("Q{$number}: target chapter is not fully within official scope.");
            }
            if (!VerifiedHumanChapterPageRepairGuard::pageInMap($maps, $newEdition, $chapter, $page, $sha)) {
                throw new RuntimeException("Q{$number}: exact target page is outside the SHA-bound chapter map.");
            }
            if (!PrintedBookPageEvidence::pageContainsEvidence($newEdition, $page, $evidence, $sha)) {
                throw new RuntimeException("Q{$number}: direct target original-page evidence failed.");
            }

            $nodeQuery->execute(['workspace'=>$workspace,'edition'=>$source['target_edition_id'],'chapter'=>$chapter]);
            $nodes = $nodeQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($nodes) !== 1) {
                throw new RuntimeException("Q{$number}: target official chapter node is not unique.");
            }
            $update->execute([
                'new_edition'=>$source['target_edition_id'],'new_node'=>$nodes[0]['id'],'page'=>$page,'sha'=>$sha,
                'workspace'=>$workspace,'source_id'=>$id,'old_edition'=>$source['edition_id'],'old_node'=>$source['node_id'],
                'old_page'=>$item['expected_legacy_page'],'old_printed'=>$item['expected_stored_printed_page'],
                'old_reviewed'=>$item['expected_reviewed_at'],'old_reviewer'=>$item['expected_reviewed_by_user_id'],
                'old_anchor'=>$source['anchor_text'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: guarded source-only edition/chapter/page update was ambiguous.");
            }
            $results[] = [
                'number'=>$number,'source_id'=>$id,'old_edition'=>$oldEdition,'edition'=>$newEdition,
                'old_chapter'=>$oldChapter,'chapter'=>$chapter,'old_node_id'=>$source['node_id'],
                'node_id'=>$nodes[0]['id'],'pdf_page'=>$page,'pdf_sha256'=>$sha,
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
        'format'=>'fanoos.verified-human-reference-chapter-page-repair-receipt/1',
        'mode'=>$apply ? 'applied' : 'rollback-preview','year'=>$plan['year'],'subject'=>$plan['subject'],
        'count'=>count($results),'plan_sha256'=>hash('sha256', $rawPlan),'audit_sha256'=>$auditSha,
        'only_fields_changed'=>['edition_id','node_id','pdf_page','pdf_sha256'],
        'questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,
        'human_source_provenance_changed'=>0,'human_review_metadata_changed'=>0,'sources'=>$results,
    ];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $receipt['at_utc'] = gmdate('c');
        $handle = fopen($receiptPath, 'x');
        if ($handle === false) {
            throw new RuntimeException('Committed but unique receipt missing; independently read back before retry.');
        }
        chmod($receiptPath, 0600);
        fwrite($handle, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'Human reference correction refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
