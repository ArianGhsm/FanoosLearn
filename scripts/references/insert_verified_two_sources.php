<?php

declare(strict_types=1);

/**
 * Insert two independently audited, SHA-bound sources for one source-free
 * residency question. Preview rolls back the exact same transaction.
 * Never changes a question, choice, key, score or frozen assessment.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\TwoSourceCitationGuard;
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
        throw new RuntimeException('Workspace required; apply needs backup and unused receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Only the authorized updater service may apply.');
    }
    $private = realpath('/srv/fanoos/shared/research/classification');
    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($private === false || $reports === false
        || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be an existing private report.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 32, JSON_THROW_ON_ERROR);
    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditReal = realpath($auditPath);
    $auditSha = $plan['audit_sha256'] ?? null;
    if (!is_string($auditSha) || !preg_match('/^[a-f0-9]{64}$/D', $auditSha)
        || $auditReal === false || is_link($auditPath) || !is_file($auditPath)
        || hash_file('sha256', $auditPath) !== $auditSha
        || preg_match('~^' . preg_quote($private, '~') . '/recovery-'
            . preg_quote((string) ($plan['year'] ?? ''), '~')
            . '-[a-z0-9-]+/classification/reports/[^/]+\.json$~D', $auditReal) !== 1) {
        throw new RuntimeException('Independent SHA-pinned private source audit missing.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 32, JSON_THROW_ON_ERROR);
    $map = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 32, JSON_THROW_ON_ERROR);
    if (!TwoSourceCitationGuard::validate($plan, $audit, $map)) {
        throw new RuntimeException('Two-source plan, audit or SHA-bound chapter map rejected.');
    }

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $reports
            || is_link($receiptPath) || file_exists($receiptPath)
            || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Verified backup or unique private receipt path rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('A fresh verified full database backup is required.');
        }
    }
    foreach ($plan['sources'] as $source) {
        if (!PrintedBookPageEvidence::pageContainsEvidence(
            $source['edition'], $source['pdf_page'], [$source['evidence']], $source['pdf_sha256']
        )) {
            throw new RuntimeException('Exact evidence missing from the approved original PDF page.');
        }
    }

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $question = $db->prepare(<<<'SQL'
SELECT q.id,q.stem,q.status FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=:yr
AND si.exam_round=1 AND sb.subject_key=:sub AND q.number_in_sitting=:num FOR UPDATE
SQL);
    $choices = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $answer = $db->prepare('SELECT status,choice_position,also_correct_positions,source,recorded_at FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $existing = $db->prepare('SELECT id FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid FOR UPDATE');
    $node = $db->prepare(<<<'SQL'
SELECT e.id edition_id,n.id node_id,n.kind,n.number,v.scope_chapters
FROM bank_references r
JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE r.workspace_id=:ws AND r.reference_key=:ref AND e.edition_key=:edition
AND n.node_key=:node AND v.exam_year=:yr AND sb.subject_key=:sub
AND et.type_key='residency' AND v.is_official=1
SQL);
    $insert = $db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,node_id,page,pdf_page,printed_page,pdf_sha256,
 anchor_text,is_primary,confidence_source,confidence_node,confidence_page,origin,created_at)
VALUES (:id,:ws,:qid,:edition,:node,:page,:pdf_page,NULL,:sha,:anchor,:primary,
 0.950,0.950,0.950,'ai',UTC_TIMESTAMP(6))
SQL);

    $db->beginTransaction();
    try {
        $question->execute(['ws'=>$workspace,'yr'=>$plan['year'],
            'sub'=>$plan['subject'],'num'=>$plan['number']]);
        $q = $question->fetch(PDO::FETCH_ASSOC);
        if (!is_array($q) || $question->fetch(PDO::FETCH_ASSOC) !== false
            || $q['status'] !== 'published'
            || hash('sha256', (string) $q['stem']) !== $plan['stem_sha256']) {
            throw new RuntimeException('Original published question identity changed.');
        }
        $params = ['ws'=>$workspace,'qid'=>$q['id']];
        $choices->execute($params);
        $choiceRows = $choices->fetchAll(PDO::FETCH_ASSOC);
        $answer->execute($params);
        $answerRow = $answer->fetch(PDO::FETCH_ASSOC);
        if (count($choiceRows) !== 4 || !is_array($answerRow)
            || !in_array($answerRow['status'], ['final','amended'], true)
            || $answerRow['choice_position'] === null
            || hash('sha256', json_encode($choiceRows, JSON_THROW_ON_ERROR)) !== $plan['choices_sha256']
            || hash('sha256', json_encode($answerRow, JSON_THROW_ON_ERROR)) !== $plan['answer_sha256']) {
            throw new RuntimeException('Four original choices or latest official answer changed.');
        }
        $existing->execute($params);
        if ($existing->fetch(PDO::FETCH_ASSOC) !== false) {
            throw new RuntimeException('Question already has a source; no partial or duplicate insert.');
        }
        $ids = [];
        foreach ($plan['sources'] as $i => $source) {
            [$ref, $edition] = explode('@', $source['edition'], 2);
            $node->execute(['ws'=>$workspace,'ref'=>$ref,'edition'=>$edition,
                'node'=>'ch' . str_pad($source['chapter'], 2, '0', STR_PAD_LEFT),
                'yr'=>$plan['year'],'sub'=>$plan['subject']]);
            $rows = $node->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1 || $rows[0]['kind'] !== 'chapter'
                || (string) $rows[0]['number'] !== $source['chapter']
                || !TwoSourceCitationGuard::scopeAllows($rows[0]['scope_chapters'], $source['chapter'])) {
                throw new RuntimeException('Chapter not uniquely within exact-year official syllabus.');
            }
            $id = Uuid::v7();
            $insert->execute(['id'=>$id,'ws'=>$workspace,'qid'=>$q['id'],
                'edition'=>$rows[0]['edition_id'],'node'=>$rows[0]['node_id'],
                'page'=>'pdf ' . $source['pdf_page'],'pdf_page'=>$source['pdf_page'],
                'sha'=>$source['pdf_sha256'],'anchor'=>$source['evidence'],
                'primary'=>$i === 0 ? 1 : 0]);
            if ($insert->rowCount() !== 1) {
                throw new RuntimeException('Atomic two-source insert failed.');
            }
            $ids[] = $id;
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
    $result = ['format'=>'fanoos.verified-two-source-citation-receipt/1',
        'mode'=>$apply ? 'applied' : 'rollback-preview',
        'year'=>$plan['year'],'subject'=>$plan['subject'],'number'=>$plan['number'],
        'source_ids'=>$ids,'source_rows'=>2,'question_changes'=>0,
        'choice_changes'=>0,'answer_changes'=>0,'assessment_changes'=>0,
        'plan_sha256'=>hash('sha256', $rawPlan),'audit_sha256'=>$auditSha];
    if ($apply) {
        $result['backup_id'] = basename($backup);
        $result['at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $fp = @fopen($receiptPath, 'x');
        if ($fp === false) {
            throw new RuntimeException('Committed but receipt missing. Inspect live DB; never retry blindly.');
        }
        chmod($receiptPath, 0600);
        fwrite($fp, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        fflush($fp);
        fclose($fp);
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Two-source citation refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
