<?php

declare(strict_types=1);

/**
 * Bind a verified original PDF page and accepted confidence vector to one
 * existing, unreviewed AI source in a fully in-scope chapter. It can fill a
 * missing page or re-score a page already bound by an earlier safe repair.
 * This operator never changes the source edition, chapter, printed/legacy
 * page, anchor, question, choices, answer, review state, assessment, or attempts.
 *
 * A protected one-question provenance audit, exact original-PDF evidence,
 * official scope, page map, and immutable question/choice/key fingerprints
 * are required. Preview rolls back. Apply requires a fresh verified backup.
 */
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\SourceChapterCorrectionGuard;
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
            throw new RuntimeException('Unknown or repeated argument.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $apply = isset($args['apply']);
    if (!preg_match('/^[0-9a-f-]{36}$/D', $workspace)
        || $apply !== isset($args['backup'])
        || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace required; apply also needs backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Only updater service account may apply.');
    }

    $reports = realpath('/srv/fanoos/shared/research/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be an existing protected report JSON file.');
    }
    $raw = (string) file_get_contents($planPath);
    $plan = json_decode($raw, true, 48, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.verified-ai-source-page-repair/2'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || !is_int($plan['year'] ?? null) || $plan['year'] < 1396 || $plan['year'] > 1500
        || !is_string($plan['subject'] ?? null)
        || !preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject'])
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) !== 1) {
        throw new RuntimeException('Invalid exam scope or one-question plan shape.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = (string) ($plan['audit_sha256'] ?? '');
    $classification = realpath('/srv/fanoos/shared/research/classification');
    $auditReal = realpath($auditPath);
    $auditLocationAllowed = $classification !== false && $auditReal !== false
        && preg_match('~^' . preg_quote($classification, '~') . '/recovery-'
            . preg_quote((string) $plan['year'], '~')
            . '-[a-zA-Z0-9-]+/classification/reports/[^/]+\.json$~D', $auditReal) === 1;
    if ((realpath(dirname($auditPath)) !== $reports && !$auditLocationAllowed)
        || is_link($auditPath) || !is_file($auditPath)
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha)
        || hash_file('sha256', $auditPath) !== $auditSha) {
        throw new RuntimeException('Independent protected audit is absent or changed.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 48, JSON_THROW_ON_ERROR);

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receipt = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receipt)) !== $reports
            || is_link($receipt) || file_exists($receipt) || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Protected backup or unique private receipt path invalid.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh fully verified production/database backup required.');
        }
    }

    $map = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 32, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,
       s.origin,s.reviewed_at,s.reviewed_by_user_id,
       s.confidence_source,s.confidence_node,s.confidence_page,
       q.id AS question_id,q.stem,q.status AS question_status,
       e.edition_key,r.reference_key,n.number AS chapter_no,n.kind,
       v.scope_chapters,v.is_official
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
WHERE s.workspace_id=:ws AND s.id=:source_id AND q.number_in_sitting=:number
  AND si.exam_year=:year AND si.exam_round=1
  AND et.type_key='residency' AND sb.subject_key=:subject
FOR UPDATE
SQL);
    $keyQuery = $db->prepare(<<<'SQL'
SELECT status,choice_position FROM bank_official_answers
WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $choicesQuery = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET pdf_page=:page,pdf_sha256=:sha,
    confidence_source=:confidence_source,confidence_node=:confidence_node,confidence_page=:confidence_page
WHERE workspace_id=:ws AND id=:id AND pdf_page <=> :old_pdf_page AND pdf_sha256 <=> :old_pdf_sha
  AND origin='ai' AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
  AND confidence_source=:old_confidence_source AND confidence_node=:old_confidence_node
  AND confidence_page=:old_confidence_page
SQL);

    $item = $plan['entries'][0];
    $number = $item['number'] ?? null;
    $id = $item['source_id'] ?? null;
    $edition = $item['edition'] ?? null;
    $chapter = $item['chapter'] ?? null;
    $page = $item['pdf_page'] ?? null;
    $sha = $item['pdf_sha256'] ?? null;
    $printed = $item['printed_page'] ?? null;
    $evidence = $item['evidence'] ?? null;
    $confidence = $item['accepted_confidence_page'] ?? null;
    $expectedSourceConfidence = $item['expected_confidence_source'] ?? null;
    $expectedNodeConfidence = $item['expected_confidence_node'] ?? null;
    $expectedPageConfidence = $item['expected_confidence_page'] ?? null;
    $expectedPdfPage = $item['expected_pdf_page'] ?? null;
    $expectedPdfSha = $item['expected_pdf_sha256'] ?? null;
    if (!is_int($number) || $number < 1 || $number > 300
        || !is_string($id) || !preg_match('/^[a-f0-9-]{36}$/D', $id)
        || !is_string($edition) || !preg_match('/^[a-z0-9-]+@[a-z0-9]+$/D', $edition)
        || !is_string($chapter) || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $chapter)
        || !is_int($page) || $page < 1 || $page > 6000
        || !is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D', $sha)
        || !array_key_exists('expected_pdf_page', $item)
        || ($expectedPdfPage !== null && (!is_int($expectedPdfPage) || $expectedPdfPage < 1))
        || !array_key_exists('expected_pdf_sha256', $item)
        || ($expectedPdfSha !== null && (!is_string($expectedPdfSha)
            || !preg_match('/^[a-f0-9]{64}$/D', $expectedPdfSha)))
        || (($expectedPdfPage === null) !== ($expectedPdfSha === null))
        || ($expectedPdfPage !== null && ($expectedPdfPage !== $page || $expectedPdfSha !== $sha))
        || !is_string($printed) || !preg_match('/^[1-9][0-9]{0,3}(?:\.e[1-9][0-9]?)?$/D', $printed)
        || (!is_string($evidence) && !is_array($evidence))
        || (is_string($evidence) && (strlen($evidence) < 16 || strlen($evidence) > 190))
        || (is_array($evidence) && ($evidence === [] || count($evidence) > 4
            || array_filter($evidence, static fn (mixed $quote): bool =>
                !is_string($quote) || strlen($quote) < 6 || strlen($quote) > 190) !== []))
        || (!is_float($confidence) && !is_int($confidence))
        || $confidence < 0.85 || $confidence > 1.0
        || !is_string($expectedSourceConfidence)
        || !preg_match('/^(?:0\.[0-9]{3}|1\.000)$/D', $expectedSourceConfidence)
        || !is_string($expectedNodeConfidence)
        || !preg_match('/^(?:0\.[0-9]{3}|1\.000)$/D', $expectedNodeConfidence)
        || !is_string($expectedPageConfidence)
        || !preg_match('/^(?:0\.[0-9]{3}|1\.000)$/D', $expectedPageConfidence)
        || !array_key_exists('expected_legacy_page', $item)
        || ($item['expected_legacy_page'] !== null && !is_string($item['expected_legacy_page']))
        || !array_key_exists('expected_stored_printed_page', $item)
        || ($item['expected_stored_printed_page'] !== null && !is_string($item['expected_stored_printed_page']))
        || !is_string($item['expected_anchor_sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $item['expected_anchor_sha256'])
        || !is_string($item['expected_stem_sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $item['expected_stem_sha256'])
        || !is_string($item['expected_choices_sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $item['expected_choices_sha256'])
        || !is_string($item['expected_answer_sha256'] ?? null)
        || !preg_match('/^[a-f0-9]{64}$/D', $item['expected_answer_sha256'])) {
        throw new RuntimeException('Invalid source, page, confidence, or fingerprint in protected plan.');
    }
    if (!SourceChapterCorrectionGuard::auditMatches($audit, $plan, $item)) {
        throw new RuntimeException("Q{$number}: accepted independent audit is for another question/page/PDF.");
    }
    $mapped = $audit['batches'][0]['mapped'][(string) $number] ?? [];
    $auditedSourceConfidence = $mapped['confidence_source'] ?? null;
    $auditedNodeConfidence = $mapped['confidence_node'] ?? null;
    $auditedPageConfidence = $mapped['confidence_page'] ?? null;
    if (!is_numeric($auditedSourceConfidence) || !is_numeric($auditedNodeConfidence)
        || !is_numeric($auditedPageConfidence)
        || abs((float) $auditedSourceConfidence - (float) $confidence) > 0.0005
        || abs((float) $auditedNodeConfidence - (float) $confidence) > 0.0005
        || abs((float) $auditedPageConfidence - (float) $confidence) > 0.0005) {
        throw new RuntimeException("Q{$number}: accepted plan confidence vector differs from the provenance audit.");
    }

    $result = [];
    $db->beginTransaction();
    try {
        $sourceQuery->execute(['ws'=>$workspace,'source_id'=>$id,'number'=>$number,
            'year'=>$plan['year'],'subject'=>$plan['subject']]);
        $rows = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new RuntimeException("Q{$number}: source/official validity identity not unique.");
        }
        $s = $rows[0];
        $storedPdfPage = $s['pdf_page'] === null ? null : (int) $s['pdf_page'];
        $storedPdfSha = $s['pdf_sha256'] === null ? null : (string) $s['pdf_sha256'];
        if ($s['question_status'] !== 'published' || $s['origin'] !== 'ai'
            || $s['reviewed_at'] !== null || $s['reviewed_by_user_id'] !== null
            || $storedPdfPage !== $expectedPdfPage || $storedPdfSha !== $expectedPdfSha
            || $s['page'] !== $item['expected_legacy_page']
            || $s['printed_page'] !== $item['expected_stored_printed_page']
            || hash('sha256', (string) $s['anchor_text']) !== $item['expected_anchor_sha256']
            || $s['reference_key'] . '@' . $s['edition_key'] !== $edition
            || (string) $s['chapter_no'] !== $chapter || $s['kind'] !== 'chapter'
            || (int) $s['is_official'] !== 1
            || !is_numeric($s['confidence_source']) || !is_numeric($s['confidence_node'])
            || !is_numeric($s['confidence_page'])
            || number_format((float) $s['confidence_source'], 3, '.', '') !== $expectedSourceConfidence
            || number_format((float) $s['confidence_node'], 3, '.', '') !== $expectedNodeConfidence
            || number_format((float) $s['confidence_page'], 3, '.', '') !== $expectedPageConfidence
            || (float) $confidence < (float) $s['confidence_source']
            || (float) $confidence < (float) $s['confidence_node']
            || (float) $confidence < (float) $s['confidence_page']) {
            throw new RuntimeException("Q{$number}: protected source identity, state, or confidence changed.");
        }
        if (hash('sha256', (string) $s['stem']) !== $item['expected_stem_sha256']) {
            throw new RuntimeException("Q{$number}: published question wording changed.");
        }

        $scopes = json_decode((string) $s['scope_chapters'], true, 32, JSON_THROW_ON_ERROR);
        $fullChapter = false;
        foreach (is_array($scopes) ? $scopes : [] as $scopeEntry) {
            if (is_array($scopeEntry) && ($scopeEntry['number'] ?? null) === $chapter
                && !isset($scopeEntry['partial'])) {
                $fullChapter = true;
                break;
            }
        }
        if (!$fullChapter || OfficialScopePageGuard::pageDisposition($scopes, $chapter, $printed) !== true) {
            throw new RuntimeException("Q{$number}: chapter/page is not proven fully inside official scope.");
        }

        $runs = $map['editions'][$edition]['runs'] ?? [];
        if (($map['editions'][$edition]['source_pdf_sha256'] ?? null) !== $sha
            || !is_array($runs) || $runs === []) {
            throw new RuntimeException("Q{$number}: chapter map is not bound to this exact PDF.");
        }
        $inside = false;
        foreach ($runs as $run) {
            if (is_array($run) && count($run) === 3 && (string) $run[0] === $chapter
                && $page >= $run[1] && $page <= $run[2]) {
                $inside = true;
                break;
            }
        }
        if (!$inside
            || !PrintedBookPageEvidence::corroborates($edition, $page, $printed, $sha)
            || !PrintedBookPageEvidence::pageContainsEvidence(
                $edition, $page, is_array($evidence) ? $evidence : [$evidence], $sha
            )) {
            throw new RuntimeException("Q{$number}: exact original page, printed label, or evidence failed.");
        }

        $keyQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
        $key = $keyQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($key) || !in_array($key['status'], ['final','amended'], true)
            || $key['choice_position'] === null
            || hash('sha256', json_encode($key, JSON_THROW_ON_ERROR)) !== $item['expected_answer_sha256']) {
            throw new RuntimeException("Q{$number}: official answer changed or is not valid.");
        }
        $choicesQuery->execute(['ws'=>$workspace,'qid'=>$s['question_id']]);
        $choices = $choicesQuery->fetchAll(PDO::FETCH_ASSOC);
        if (count($choices) !== 4
            || hash('sha256', json_encode($choices, JSON_THROW_ON_ERROR)) !== $item['expected_choices_sha256']) {
            throw new RuntimeException("Q{$number}: choices changed or are incomplete.");
        }

        $sourceFieldsChanged = [];
        if ($storedPdfPage !== $page) {
            $sourceFieldsChanged[] = 'pdf_page';
        }
        if ($storedPdfSha !== $sha) {
            $sourceFieldsChanged[] = 'pdf_sha256';
        }
        foreach ([
            'confidence_source' => $expectedSourceConfidence,
            'confidence_node' => $expectedNodeConfidence,
            'confidence_page' => $expectedPageConfidence,
        ] as $field => $oldConfidence) {
            if ((float) $confidence > (float) $oldConfidence) {
                $sourceFieldsChanged[] = $field;
            }
        }
        if ($sourceFieldsChanged === []) {
            throw new RuntimeException("Q{$number}: plan would not change any source field.");
        }

        $update->execute(['page'=>$page,'sha'=>$sha,
            'old_pdf_page'=>$expectedPdfPage,'old_pdf_sha'=>$expectedPdfSha,
            'confidence_source'=>number_format((float) $confidence, 3, '.', ''),
            'confidence_node'=>number_format((float) $confidence, 3, '.', ''),
            'confidence_page'=>number_format((float) $confidence, 3, '.', ''),
            'old_confidence_source'=>$expectedSourceConfidence,
            'old_confidence_node'=>$expectedNodeConfidence,
            'old_confidence_page'=>$expectedPageConfidence,'ws'=>$workspace,'id'=>$id]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException("Q{$number}: zero/ambiguous source-only update.");
        }
        $result = ['number'=>$number,'source_id'=>$id,'edition'=>$edition,'chapter'=>$chapter,
            'pdf_page'=>$page,'printed_page_verified'=>$printed,'pdf_sha256'=>$sha,
            'old_pdf_page'=>$expectedPdfPage,'old_pdf_sha256'=>$expectedPdfSha,
            'old_confidence_source'=>$expectedSourceConfidence,
            'old_confidence_node'=>$expectedNodeConfidence,
            'old_confidence_page'=>$expectedPageConfidence,
            'confidence_source'=>number_format((float) $confidence, 3, '.', ''),
            'confidence_node'=>number_format((float) $confidence, 3, '.', ''),
            'confidence_page'=>number_format((float) $confidence, 3, '.', ''),
            'source_fields_changed'=>$sourceFieldsChanged];
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

    $receipt = ['format'=>'fanoos.verified-ai-source-page-repair-receipt/2',
        'mode'=>$apply ? 'applied' : 'rollback-preview','year'=>$plan['year'],'subject'=>$plan['subject'],
        'plan_sha256'=>hash('sha256', $raw),'audit_sha256'=>$auditSha,
        'questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,
        'source_fields_changed'=>$result['source_fields_changed'],'source'=>$result];
    if ($apply) {
        $receipt['backup_id'] = basename($backup);
        $receipt['at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $handle = @fopen((string) $args['receipt'], 'x');
        if ($handle === false) {
            throw new RuntimeException('Committed source-only update; receipt missing. Read back DB; do not retry blindly.');
        }
        chmod((string) $args['receipt'], 0600);
        fwrite($handle, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Verified AI source page repair refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
