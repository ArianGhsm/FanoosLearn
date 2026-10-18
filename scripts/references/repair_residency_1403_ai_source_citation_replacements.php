<?php

declare(strict_types=1);

/**
 * Replace one specifically audited 1403 AI source citation, without touching
 * the question, choices, key, reviews, attempts or frozen assessment.
 *
 * Default mode is a rollback preview. Apply is updater-only and requires a
 * fresh verified full backup and a unique private receipt.
 */
use Fanoos\Platform\Bank\OfficialScopePageGuard;
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationReplacementGuard as Guard;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionGuard as ResearchGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const REPLACEMENT_REPORTS = '/srv/fanoos/shared/research/classification/reports';
const REPLACEMENT_CANONICAL = '/srv/fanoos/shared/research/bank-sittings/1403/canonical-export-20261011/residency-1403-1.json';
const REPLACEMENT_CANONICAL_SHA256 = '5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7';

/** @return array<int,array<string,mixed>> */
function replacementCanonicalQuestions(string $path): array
{
    $document = json_decode((string) file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    if (($document['exam_type'] ?? null) !== 'residency' || ($document['year'] ?? null) !== 1403
        || ($document['round'] ?? null) !== 1 || count($document['questions'] ?? []) !== 250) {
        throw new RuntimeException('Pinned canonical 1403 sitting has the wrong identity.');
    }
    $questions = [];
    foreach ($document['questions'] as $question) {
        $number = $question['number'] ?? null;
        if (!is_int($number) || isset($questions[$number])) {
            throw new RuntimeException('Pinned canonical sitting has duplicate question numbers.');
        }
        $questions[$number] = $question;
    }
    return $questions;
}

/** @return array<string,mixed> */
function replacementDecision(array $decisions, int $number): array
{
    $matches = array_values(array_filter($decisions, static fn (mixed $row): bool =>
        is_array($row) && ($row['number'] ?? null) === $number));
    if (count($matches) !== 1) {
        throw new RuntimeException('Audited research decision is not unique.');
    }
    return $matches[0];
}

/** @return array<string,mixed> */
function replacementReadImmutableQuestion(PDO $db, string $workspace, array $live, array $canonical): array
{
    $number = (int) $live['number_in_sitting'];
    if (($live['question_status'] ?? null) !== 'published'
        || ($live['subject_key'] ?? null) !== ($canonical['subject'] ?? null)
        || ($live['stem'] ?? null) !== ($canonical['stem'] ?? null)) {
        throw new RuntimeException("Q{$number}: live published question differs from the pinned original.");
    }

    $choicesQuery = $db->prepare(<<<'SQL'
SELECT position,text,image FROM bank_question_choices
WHERE workspace_id=:ws AND question_id=:qid ORDER BY position
SQL);
    $choicesQuery->execute(['ws' => $workspace, 'qid' => $live['question_id']]);
    $actualChoices = array_map(static fn (array $choice): array => [
        'text' => (string) $choice['text'],
        'image' => $choice['image'] === null ? null : (string) $choice['image'],
    ], $choicesQuery->fetchAll(PDO::FETCH_ASSOC));
    $expectedChoices = array_map(static fn (array $choice): array => [
        'text' => (string) ($choice['text'] ?? ''),
        'image' => $choice['image'] ?? null,
    ], $canonical['choices'] ?? []);
    if (count($actualChoices) !== 4 || $actualChoices !== $expectedChoices) {
        throw new RuntimeException("Q{$number}: live choices differ from the pinned original.");
    }

    $answerQuery = $db->prepare(<<<'SQL'
SELECT choice_position,also_correct_positions,status
FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid
ORDER BY recorded_at DESC,id DESC LIMIT 1
SQL);
    $answerQuery->execute(['ws' => $workspace, 'qid' => $live['question_id']]);
    $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($answer) || !ResearchGuard::officialAnswerMatches($answer, $canonical['answer'] ?? [])) {
        throw new RuntimeException("Q{$number}: official answer differs from the pinned original.");
    }

    return [
        'stem_sha256' => hash('sha256', (string) $live['stem']),
        'choices_sha256' => hash('sha256', json_encode($actualChoices, JSON_THROW_ON_ERROR)),
        'answer_status' => (string) $answer['status'],
        'answer_choice' => (int) $answer['choice_position'],
    ];
}

/** @return array<string,mixed> */
function replacementAssessmentSnapshot(PDO $db, string $workspace, string $sittingId): array
{
    $meta = $db->prepare(<<<'SQL'
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
    $meta->execute(['ws' => $workspace, 'sitting' => $sittingId]);
    $row = $meta->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || $meta->fetch(PDO::FETCH_ASSOC) !== false) {
        throw new RuntimeException('Expected exactly one current frozen assessment version.');
    }
    return [
        'assessment_id' => (string) $row['assessment_id'],
        'assessment_status' => (string) $row['status'],
        'version' => (int) $row['current_version_no'],
        'version_id' => (string) $row['version_id'],
        'version_sha256' => strtolower((string) $row['version_sha256']),
        'question_count' => (int) $row['question_count'],
        'attempts' => (int) $row['attempts'],
        'reviews' => (int) $row['reviews'],
    ];
}

function replacementWritePrivateJson(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('Could not create the unique protected receipt.');
    }
    chmod($path, 0600);
    try {
        if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Could not write the complete protected receipt.');
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
        throw new RuntimeException('CLI-only protected source citation replacement.');
    }
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--(workspace|plan|preview-receipt|apply|backup|receipt)(?:=(.*))?$/D', $argument, $match) !== 1
            || isset($args[$match[1]])) {
            throw new RuntimeException('Unknown or duplicate option.');
        }
        $args[$match[1]] = $match[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $apply = isset($args['apply']);
    if (!preg_match('/^[0-9a-f-]{36}$/D', $workspace)
        || $apply !== isset($args['backup']) || $apply !== isset($args['receipt'])
        || $apply !== isset($args['preview-receipt'])) {
        throw new RuntimeException('Workspace required; apply needs its rollback-preview receipt, backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Only updater service identity may apply.');
    }

    $reports = realpath(REPLACEMENT_REPORTS);
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath) || (fileperms($planPath) & 0077) !== 0) {
        throw new RuntimeException('Plan must be a private regular JSON file in classification reports.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 48, JSON_THROW_ON_ERROR);
    $number = $plan['number'] ?? null;
    if (!is_int($number) || !is_array($plan['evidence_fragments'] ?? null)
        || !Guard::planMatches($number, $plan, $plan['evidence_fragments'])) {
        throw new RuntimeException('Plan is not one of the pinned 1403 Q152/Q162 citation corrections.');
    }
    $previewPath = (string) ($args['preview-receipt'] ?? '');
    if ($apply) {
        if (realpath(dirname($previewPath)) !== $reports || is_link($previewPath)
            || !is_file($previewPath) || (fileperms($previewPath) & 0077) !== 0
            || !str_ends_with($previewPath, '.json')) {
            throw new RuntimeException('A protected rollback-preview receipt is required before apply.');
        }
        $preview = json_decode((string) file_get_contents($previewPath), true, 32, JSON_THROW_ON_ERROR);
        if (($preview['format'] ?? null) !== 'fanoos.residency1403.ai-source-citation-replacement-receipt/1'
            || ($preview['mode'] ?? null) !== 'rollback-preview'
            || ($preview['number'] ?? null) !== $number
            || ($preview['private_plan_sha256'] ?? null) !== hash('sha256', $rawPlan)
            || ($preview['question_stem_choices_official_key_unchanged'] ?? null) !== true
            || ($preview['assessment_version_and_reviews_unchanged'] ?? null) !== true
            || ($preview['attempts_unchanged'] ?? null) !== true) {
            throw new RuntimeException('Rollback preview does not match this exact plan.');
        }
    } elseif (isset($args['preview-receipt'])
        && (realpath(dirname($previewPath)) !== $reports || is_link($previewPath)
            || file_exists($previewPath) || !str_ends_with($previewPath, '.json'))) {
        throw new RuntimeException('Preview receipt must be a new file in the protected reports directory.');
    }
    $spec = Guard::spec($number);
    if ($spec === null) {
        throw new RuntimeException('Unsupported question.');
    }

    $classificationRoot = '/srv/fanoos/shared/research/classification';
    $evidencePath = $spec['evidence_input_path'];
    if (!ResearchGuard::isPrivateFile($evidencePath, $classificationRoot, false)
        || hash_file('sha256', $evidencePath) !== $spec['evidence_input_sha256']) {
        throw new RuntimeException('Pinned short original-page evidence file is absent or changed.');
    }
    $evidenceInput = json_decode((string) file_get_contents($evidencePath), true, 32, JSON_THROW_ON_ERROR);
    if (($evidenceInput['format'] ?? null) !== 'fanoos.residency1403.ai-source-citation-page-evidence/1'
        || ($evidenceInput['number'] ?? null) !== $number
        || ($evidenceInput['evidence_fragments'] ?? null) !== $plan['evidence_fragments']
        || ($evidenceInput['anchor_text'] ?? null) !== $plan['anchor_text']) {
        throw new RuntimeException('Private plan excerpts differ from the pinned evidence input.');
    }
    foreach ([
        [$spec['audit_path'], $spec['audit_sha256']],
        [$spec['study_path'], $spec['study_sha256']],
        [$spec['decisions_path'], $spec['decisions_sha256']],
        [$spec['validated_path'], $spec['validated_sha256']],
    ] as [$path, $sha]) {
        if (!ResearchGuard::isPrivateFile($path, $classificationRoot, false)
            || hash_file('sha256', $path) !== $sha) {
            throw new RuntimeException('A pinned private research file is absent or changed.');
        }
    }
    if (hash_file('sha256', (string) $plan['audit_path']) !== $spec['audit_sha256']) {
        throw new RuntimeException('Independent audit SHA no longer matches the pinned receipt.');
    }
    $study = json_decode((string) file_get_contents($spec['study_path']), true, 64, JSON_THROW_ON_ERROR);
    $decisions = json_decode((string) file_get_contents($spec['decisions_path']), true, 64, JSON_THROW_ON_ERROR);
    $validated = json_decode((string) file_get_contents($spec['validated_path']), true, 64, JSON_THROW_ON_ERROR);
    $audit = json_decode((string) file_get_contents($spec['audit_path']), true, 64, JSON_THROW_ON_ERROR);
    if (!Guard::researchMatches(
        $number, $study, $decisions, $validated, $audit,
        $spec['study_sha256'], $spec['decisions_sha256'], $spec['validated_sha256'],
    )) {
        throw new RuntimeException('Independent exact-page research audit does not match its immutable case.');
    }
    $decision = replacementDecision($decisions, $number);
    $mapPath = dirname(__DIR__, 2) . '/data/bank/reference-chapter-pages.json';
    $maps = json_decode((string) file_get_contents($mapPath), true, 64, JSON_THROW_ON_ERROR);
    $mapEntry = $maps['editions'][$spec['reference']] ?? null;
    if (!is_array($mapEntry) || ($mapEntry['source_pdf_sha256'] ?? null) !== $spec['pdf_sha256']) {
        throw new RuntimeException('Target edition chapter map is not bound to the audited PDF.');
    }
    $mapped = false;
    foreach ($mapEntry['runs'] ?? [] as $run) {
        if (is_array($run) && count($run) === 3 && (string) $run[0] === $spec['chapter']
            && is_int($run[1]) && is_int($run[2])
            && $run[1] <= $spec['pdf_page'] && $spec['pdf_page'] <= $run[2]) {
            $mapped = true;
            break;
        }
    }
    if (!$mapped
        || !PrintedBookPageEvidence::corroborates(
            $spec['reference'], $spec['pdf_page'], $spec['printed_page'], $spec['pdf_sha256'],
        )
        || !PrintedBookPageEvidence::pageContainsEvidence(
            $spec['reference'], $spec['pdf_page'], $plan['evidence_fragments'], $spec['pdf_sha256'],
        )) {
        throw new RuntimeException('Original PDF page, printed label, map or short evidence failed validation.');
    }

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receipt = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receipt)) !== $reports || is_link($receipt)
            || file_exists($receipt) || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Fresh backup or unique private receipt path rejected.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('A fresh, complete verified production backup is required.');
        }
    }

    $previewReceipt = $previewPath;

    if (!ResearchGuard::isPrivateFile(REPLACEMENT_CANONICAL, '/srv/fanoos/shared/research/bank-sittings/1403', false)
        || hash_file('sha256', REPLACEMENT_CANONICAL) !== REPLACEMENT_CANONICAL_SHA256) {
        throw new RuntimeException('Pinned original 1403 canonical sitting is absent or changed.');
    }
    $canonicalQuestions = replacementCanonicalQuestions(REPLACEMENT_CANONICAL);
    $canonical = $canonicalQuestions[$number] ?? null;
    if (!is_array($canonical) || ($canonical['subject'] ?? null) !== $spec['subject']) {
        throw new RuntimeException('Pinned original question is missing or has another subject.');
    }

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $lockName = 'fanoos:1403:ai-source-citation-replacement';
    $lock = $db->prepare('SELECT GET_LOCK(:name, 10)');
    $lock->execute(['name' => $lockName]);
    if ((int) $lock->fetchColumn() !== 1) {
        throw new RuntimeException('Another 1403 AI source correction is active.');
    }

    $db->beginTransaction();
    $receiptPath = '';
    try {
        $sittingQuery = $db->prepare(<<<'SQL'
SELECT s.id,s.question_count,t.id type_id
FROM bank_exam_sittings s
JOIN bank_exam_types t ON t.id=s.exam_type_id AND t.workspace_id=s.workspace_id
WHERE s.workspace_id=:ws AND t.type_key='residency' AND s.exam_year=1403 AND s.exam_round=1
SQL);
        $sittingQuery->execute(['ws' => $workspace]);
        $sitting = $sittingQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($sitting) || (int) $sitting['question_count'] !== 250
            || $sittingQuery->fetch(PDO::FETCH_ASSOC) !== false) {
            throw new RuntimeException('Expected one live 250-question 1403 residency sitting.');
        }

        $questionQuery = $db->prepare(<<<'SQL'
SELECT q.id question_id,q.stem,q.status question_status,q.number_in_sitting,
       sb.id subject_id,sb.subject_key
FROM bank_questions q
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND q.sitting_id=:sitting AND q.number_in_sitting=:number
FOR UPDATE
SQL);
        $questionQuery->execute(['ws' => $workspace, 'sitting' => $sitting['id'], 'number' => $number]);
        $question = $questionQuery->fetch(PDO::FETCH_ASSOC);
        if (!is_array($question) || $questionQuery->fetch(PDO::FETCH_ASSOC) !== false) {
            throw new RuntimeException('Expected exactly one current live question.');
        }

        $sourceCount = $db->prepare('SELECT COUNT(*) FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid');
        $sourceCount->execute(['ws' => $workspace, 'qid' => $question['question_id']]);
        if ((int) $sourceCount->fetchColumn() !== 1) {
            throw new RuntimeException("Q{$number}: expected exactly one existing citation row.");
        }
        $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id source_id,s.workspace_id,s.question_id,s.edition_id,s.node_id,s.page,s.printed_page,
       s.pdf_page,s.pdf_sha256,s.anchor_text,s.table_ref,s.figure_ref,s.box_ref,s.is_primary,
       s.confidence_source,s.confidence_node,s.confidence_page,s.origin,s.reviewed_at,
       s.reviewed_by_user_id,s.official_scope_status,s.official_scope_note,s.scope_unverified,
       r.reference_key,e.edition_key,n.number chapter,n.kind
FROM bank_question_sources s
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE s.workspace_id=:ws AND s.question_id=:qid FOR UPDATE
SQL);
        $sourceQuery->execute(['ws' => $workspace, 'qid' => $question['question_id']]);
        $old = $sourceQuery->fetch(PDO::FETCH_ASSOC);
        $expected = $plan['expected_source'];
        if (!is_array($old) || $sourceQuery->fetch(PDO::FETCH_ASSOC) !== false
            || $old['id'] !== $expected['source_id']
            || $old['reference_key'] . '@' . $old['edition_key'] !== $spec['old_reference']
            || (string) $old['chapter'] !== $spec['old_chapter'] || $old['kind'] !== 'chapter'
            || $old['page'] !== $spec['old_legacy_page'] || $old['printed_page'] !== null
            || (int) $old['pdf_page'] !== $spec['old_pdf_page']
            || strtolower((string) $old['pdf_sha256']) !== $spec['old_pdf_sha256']
            || !hash_equals($expected['anchor_sha256'], hash('sha256', (string) $old['anchor_text']))
            || $old['origin'] !== 'ai' || (int) $old['is_primary'] !== 1
            || $old['reviewed_at'] !== null || $old['reviewed_by_user_id'] !== null
            || $old['table_ref'] !== null || $old['figure_ref'] !== null || $old['box_ref'] !== null
            || $old['official_scope_status'] !== 'in_scope' || (bool) $old['scope_unverified']
            || number_format((float) $old['confidence_source'], 3, '.', '') !== $spec['old_confidence']
            || number_format((float) $old['confidence_node'], 3, '.', '') !== $spec['old_confidence']
            || number_format((float) $old['confidence_page'], 3, '.', '') !== $spec['old_confidence']) {
            throw new RuntimeException("Q{$number}: source row changed, reviewed, or outside the pinned correction case.");
        }

        $immutableBefore = replacementReadImmutableQuestion($db, $workspace, $question, $canonical);
        $validity = $db->prepare(<<<'SQL'
SELECT e.id edition_id,n.id node_id,n.kind,n.number,v.scope_chapters
FROM bank_references r
JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE r.workspace_id=:ws AND r.reference_key=:reference AND e.edition_key=:edition
 AND n.kind='chapter' AND n.number=:chapter AND v.exam_year=1403 AND v.is_official=1
 AND sb.subject_key=:subject AND et.type_key='residency'
SQL);
        [$referenceKey, $editionKey] = explode('@', $spec['reference'], 2);
        $validity->execute(['ws' => $workspace, 'reference' => $referenceKey,
            'edition' => $editionKey, 'chapter' => $spec['chapter'], 'subject' => $spec['subject']]);
        $target = $validity->fetch(PDO::FETCH_ASSOC);
        if (!is_array($target) || $validity->fetch(PDO::FETCH_ASSOC) !== false
            || $target['kind'] !== 'chapter'
            || OfficialScopePageGuard::pageDisposition(
                $target['scope_chapters'], $spec['chapter'], $spec['printed_page'],
            ) !== true) {
            throw new RuntimeException('Target is not one unique, fully in-scope official 1403 chapter/page.');
        }

        $assessmentBefore = replacementAssessmentSnapshot($db, $workspace, (string) $sitting['id']);
        if ($assessmentBefore['assessment_status'] !== 'published' || $assessmentBefore['question_count'] !== 250) {
            throw new RuntimeException('Current frozen 1403 assessment is not the expected published full sitting.');
        }

        $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET edition_id=:edition,node_id=:node,page=:legacy_page,printed_page=:printed_page,
    pdf_page=:pdf_page,pdf_sha256=:pdf_sha256,anchor_text=:anchor_text,
    confidence_source=:new_confidence_source,confidence_node=:new_confidence_node,
    confidence_page=:new_confidence_page
WHERE id=:source_id AND workspace_id=:workspace AND question_id=:question_id
  AND edition_id=:old_edition AND node_id=:old_node AND page <=> :old_page
  AND printed_page IS NULL AND pdf_page=:old_pdf_page AND pdf_sha256=:old_pdf_sha256
  AND anchor_text <=> :old_anchor AND origin='ai' AND is_primary=1
  AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
  AND confidence_source=:old_confidence_source AND confidence_node=:old_confidence_node
  AND confidence_page=:old_confidence_page AND official_scope_status='in_scope'
  AND scope_unverified=0 AND table_ref IS NULL AND figure_ref IS NULL AND box_ref IS NULL
SQL);
        $update->execute([
            'edition' => $target['edition_id'], 'node' => $target['node_id'],
            'legacy_page' => $spec['printed_page'], 'printed_page' => $spec['printed_page'],
            'pdf_page' => $spec['pdf_page'], 'pdf_sha256' => $spec['pdf_sha256'],
            'anchor_text' => $plan['anchor_text'],
            'new_confidence_source' => $spec['confidence'],
            'new_confidence_node' => $spec['confidence'],
            'new_confidence_page' => $spec['confidence'],
            'source_id' => $old['source_id'], 'workspace' => $workspace,
            'question_id' => $question['question_id'], 'old_edition' => $old['edition_id'],
            'old_node' => $old['node_id'], 'old_page' => $old['page'],
            'old_pdf_page' => $spec['old_pdf_page'], 'old_pdf_sha256' => $spec['old_pdf_sha256'],
            'old_anchor' => $old['anchor_text'],
            'old_confidence_source' => (float) $spec['old_confidence'],
            'old_confidence_node' => (float) $spec['old_confidence'],
            'old_confidence_page' => (float) $spec['old_confidence'],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException("Q{$number}: source-only compare-and-swap did not update exactly one row.");
        }

        $readback = $db->prepare(<<<'SQL'
SELECT edition_id,node_id,page,printed_page,pdf_page,pdf_sha256,anchor_text,
       confidence_source,confidence_node,confidence_page,origin,is_primary,reviewed_at,reviewed_by_user_id
FROM bank_question_sources WHERE workspace_id=:ws AND id=:id AND question_id=:qid FOR UPDATE
SQL);
        $readback->execute(['ws' => $workspace, 'id' => $old['source_id'], 'qid' => $question['question_id']]);
        $after = $readback->fetch(PDO::FETCH_ASSOC);
        if (!is_array($after) || $after['edition_id'] !== $target['edition_id']
            || $after['node_id'] !== $target['node_id'] || $after['page'] !== $spec['printed_page']
            || $after['printed_page'] !== $spec['printed_page'] || (int) $after['pdf_page'] !== $spec['pdf_page']
            || strtolower((string) $after['pdf_sha256']) !== $spec['pdf_sha256']
            || $after['anchor_text'] !== $plan['anchor_text']
            || number_format((float) $after['confidence_page'], 3, '.', '') !== '0.950'
            || $after['origin'] !== 'ai' || (int) $after['is_primary'] !== 1
            || $after['reviewed_at'] !== null || $after['reviewed_by_user_id'] !== null) {
            throw new RuntimeException("Q{$number}: source-only readback did not match the pinned target.");
        }

        $immutableAfter = replacementReadImmutableQuestion($db, $workspace, $question, $canonical);
        $assessmentAfter = replacementAssessmentSnapshot($db, $workspace, (string) $sitting['id']);
        if ($immutableBefore !== $immutableAfter || $assessmentBefore !== $assessmentAfter) {
            throw new RuntimeException('Question/key or frozen assessment metadata changed during source-only transaction.');
        }

        $result = [
            'format' => 'fanoos.residency1403.ai-source-citation-replacement-receipt/1',
            'mode' => $apply ? 'applied' : 'rollback-preview',
            'year' => 1403,
            'number' => $number,
            'subject' => $spec['subject'],
            'source_id' => $old['source_id'],
            'old' => ['reference' => $spec['old_reference'], 'chapter' => $spec['old_chapter'],
                'pdf_page' => $spec['old_pdf_page'], 'pdf_sha256' => $spec['old_pdf_sha256'],
                'anchor_sha256' => hash('sha256', (string) $old['anchor_text'])],
            'new' => ['reference' => $spec['reference'], 'chapter' => $spec['chapter'],
                'printed_page' => $spec['printed_page'], 'pdf_page' => $spec['pdf_page'],
                'pdf_sha256' => $spec['pdf_sha256'], 'anchor_sha256' => hash('sha256', $plan['anchor_text']),
                'confidence' => $spec['confidence']],
            'only_changed_table' => 'bank_question_sources',
            'only_changed_columns' => ['edition_id','node_id','page','printed_page','pdf_page','pdf_sha256',
                'anchor_text','confidence_source','confidence_node','confidence_page'],
            'question_stem_choices_official_key_unchanged' => true,
            'assessment_version_and_reviews_unchanged' => true,
            'attempts_unchanged' => true,
            'immutable_question_before_after' => ['before' => $immutableBefore, 'after' => $immutableAfter],
            'assessment_before_after' => ['before' => $assessmentBefore, 'after' => $assessmentAfter],
            'research_audit_sha256' => $spec['audit_sha256'],
            'private_plan_sha256' => hash('sha256', $rawPlan),
            'preview_receipt_sha256' => $apply ? hash_file('sha256', $previewPath) : null,
            'verified_backup' => $apply ? basename($backup) : null,
            'at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        if ($apply) {
            $receiptPath = (string) $args['receipt'];
            replacementWritePrivateJson($receiptPath, $result);
            $db->commit();
        } else {
            $db->rollBack();
            if (isset($args['preview-receipt'])) {
                replacementWritePrivateJson($previewReceipt, $result);
            }
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($receiptPath !== '' && is_file($receiptPath)) {
            @unlink($receiptPath);
        }
        throw $error;
    } finally {
        $db->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $lockName]);
    }
} catch (Throwable $error) {
    fwrite(STDERR, '1403 AI source citation replacement refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
