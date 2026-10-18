<?php

declare(strict_types=1);

/** Prepare one private, read-only compare-and-swap plan for 1403 Q152 or Q162. */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationReplacementGuard as Guard;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionGuard as ResearchGuard;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const REPLACEMENT_PLAN_REPORTS = '/srv/fanoos/shared/research/classification/reports';
const REPLACEMENT_PLAN_CANONICAL = '/srv/fanoos/shared/research/bank-sittings/1403/canonical-export-20261011/residency-1403-1.json';
const REPLACEMENT_PLAN_CANONICAL_SHA256 = '5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7';

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI-only protected source correction plan preparation.');
    }
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--(workspace|number|evidence-file|output)=(.*)$/D', $argument, $match) !== 1
            || isset($args[$match[1]])) {
            throw new RuntimeException('Unknown or duplicate option.');
        }
        $args[$match[1]] = $match[2];
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $number = filter_var($args['number'] ?? null, FILTER_VALIDATE_INT);
    if (!preg_match('/^[0-9a-f-]{36}$/D', $workspace) || $number === false
        || Guard::spec($number) === null) {
        throw new RuntimeException('Only an explicit workspace and pinned Q152/Q162 are accepted.');
    }
    $reports = realpath(REPLACEMENT_PLAN_REPORTS);
    $inputPath = (string) ($args['evidence-file'] ?? '');
    $outputPath = (string) ($args['output'] ?? '');
    $spec = Guard::spec($number);
    if ($reports === false || realpath(dirname($inputPath)) !== $reports
        || is_link($inputPath) || !is_file($inputPath) || (fileperms($inputPath) & 0077) !== 0
        || $inputPath !== $spec['evidence_input_path']
        || hash_file('sha256', $inputPath) !== $spec['evidence_input_sha256']
        || realpath(dirname($outputPath)) !== $reports || is_link($outputPath)
        || file_exists($outputPath) || !str_ends_with($outputPath, '.json')) {
        throw new RuntimeException('Evidence input and new plan must be private regular files in reports.');
    }
    $evidence = json_decode((string) file_get_contents($inputPath), true, 32, JSON_THROW_ON_ERROR);
    $fragments = $evidence['evidence_fragments'] ?? null;
    $anchor = $evidence['anchor_text'] ?? null;
    if (($evidence['format'] ?? null) !== 'fanoos.residency1403.ai-source-citation-page-evidence/1'
        || ($evidence['number'] ?? null) !== $number || !is_string($anchor)
        || !is_array($fragments) || !array_is_list($fragments) || $fragments === [] || count($fragments) > 4
        || !in_array($anchor, $fragments, true)) {
        throw new RuntimeException('Evidence input must pin one target and exact page-local excerpts.');
    }
    $classificationRoot = '/srv/fanoos/shared/research/classification';
    foreach ([
        [$spec['audit_path'], $spec['audit_sha256']],
        [$spec['study_path'], $spec['study_sha256']],
        [$spec['decisions_path'], $spec['decisions_sha256']],
        [$spec['validated_path'], $spec['validated_sha256']],
    ] as [$path, $sha]) {
        if (!ResearchGuard::isPrivateFile($path, $classificationRoot, false)
            || hash_file('sha256', $path) !== $sha) {
            throw new RuntimeException('Pinned private research artifacts are absent or changed.');
        }
    }
    $study = json_decode((string) file_get_contents($spec['study_path']), true, 64, JSON_THROW_ON_ERROR);
    $decisions = json_decode((string) file_get_contents($spec['decisions_path']), true, 64, JSON_THROW_ON_ERROR);
    $validated = json_decode((string) file_get_contents($spec['validated_path']), true, 64, JSON_THROW_ON_ERROR);
    $audit = json_decode((string) file_get_contents($spec['audit_path']), true, 64, JSON_THROW_ON_ERROR);
    if (!Guard::researchMatches($number, $study, $decisions, $validated, $audit,
        $spec['study_sha256'], $spec['decisions_sha256'], $spec['validated_sha256'])) {
        throw new RuntimeException('Accepted exact-page research audit no longer matches its pinned input.');
    }

    $mapPath = dirname(__DIR__, 2) . '/data/bank/reference-chapter-pages.json';
    $maps = json_decode((string) file_get_contents($mapPath), true, 64, JSON_THROW_ON_ERROR);
    $map = $maps['editions'][$spec['reference']] ?? null;
    $mapped = false;
    foreach (is_array($map) ? ($map['runs'] ?? []) : [] as $run) {
        if (is_array($run) && count($run) === 3 && (string) $run[0] === $spec['chapter']
            && is_int($run[1]) && is_int($run[2])
            && $run[1] <= $spec['pdf_page'] && $spec['pdf_page'] <= $run[2]) {
            $mapped = true;
            break;
        }
    }
    if (!is_array($map) || ($map['source_pdf_sha256'] ?? null) !== $spec['pdf_sha256'] || !$mapped
        || !PrintedBookPageEvidence::corroborates(
            $spec['reference'], $spec['pdf_page'], $spec['printed_page'], $spec['pdf_sha256'],
        )
        || !PrintedBookPageEvidence::pageContainsEvidence(
            $spec['reference'], $spec['pdf_page'], $fragments, $spec['pdf_sha256'],
        )) {
        throw new RuntimeException('Original PDF page, label, chapter map or exact excerpts failed validation.');
    }

    if (!ResearchGuard::isPrivateFile(
        REPLACEMENT_PLAN_CANONICAL,
        '/srv/fanoos/shared/research/bank-sittings/1403',
        false,
    ) || hash_file('sha256', REPLACEMENT_PLAN_CANONICAL) !== REPLACEMENT_PLAN_CANONICAL_SHA256) {
        throw new RuntimeException('Pinned original 1403 canonical export is absent or changed.');
    }
    $canonical = json_decode((string) file_get_contents(REPLACEMENT_PLAN_CANONICAL), true, 128, JSON_THROW_ON_ERROR);
    if (($canonical['exam_type'] ?? null) !== 'residency' || ($canonical['year'] ?? null) !== 1403
        || ($canonical['round'] ?? null) !== 1 || count($canonical['questions'] ?? []) !== 250) {
        throw new RuntimeException('Pinned canonical 1403 sitting has the wrong identity.');
    }
    $canonicalMatches = array_values(array_filter($canonical['questions'], static fn (mixed $question): bool =>
        is_array($question) && ($question['number'] ?? null) === $number));
    if (count($canonicalMatches) !== 1 || ($canonicalMatches[0]['subject'] ?? null) !== $spec['subject']) {
        throw new RuntimeException('Pinned canonical target question is missing or has a different subject.');
    }

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $query = $db->prepare(<<<'SQL'
SELECT q.id question_id,q.stem,q.status question_status,q.number_in_sitting,sb.subject_key,
       s.id source_id,s.page,s.printed_page,s.pdf_page,s.pdf_sha256,s.anchor_text,
       s.confidence_source,s.confidence_node,s.confidence_page,s.origin,s.is_primary,
       s.reviewed_at,s.reviewed_by_user_id,s.table_ref,s.figure_ref,s.box_ref,
       s.official_scope_status,s.scope_unverified,
       r.reference_key,e.edition_key,n.number chapter,n.kind
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_question_sources s ON s.question_id=q.id AND s.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE q.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=1403 AND si.exam_round=1
 AND q.number_in_sitting=:number AND sb.subject_key=:subject
SQL);
        $query->execute(['ws' => $workspace, 'number' => $number, 'subject' => $spec['subject']]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 1) {
            throw new RuntimeException('Expected exactly one current source row for the target.');
        }
        $row = $rows[0];
        $sourceCount = $db->prepare('SELECT COUNT(*) FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid');
        $sourceCount->execute(['ws' => $workspace, 'qid' => $row['question_id']]);
        if ((int) $sourceCount->fetchColumn() !== 1
            || $row['question_status'] !== 'published'
            || $row['stem'] !== ($canonicalMatches[0]['stem'] ?? null)
            || $row['reference_key'] . '@' . $row['edition_key'] !== $spec['old_reference']
            || (string) $row['chapter'] !== $spec['old_chapter'] || $row['kind'] !== 'chapter'
            || $row['page'] !== $spec['old_legacy_page'] || $row['printed_page'] !== null
            || (int) $row['pdf_page'] !== $spec['old_pdf_page']
            || strtolower((string) $row['pdf_sha256']) !== $spec['old_pdf_sha256']
            || $row['origin'] !== 'ai' || (int) $row['is_primary'] !== 1
            || $row['reviewed_at'] !== null || $row['reviewed_by_user_id'] !== null
            || $row['table_ref'] !== null || $row['figure_ref'] !== null || $row['box_ref'] !== null
            || $row['official_scope_status'] !== 'in_scope' || (bool) $row['scope_unverified']
            || number_format((float) $row['confidence_source'], 3, '.', '') !== $spec['old_confidence']
            || number_format((float) $row['confidence_node'], 3, '.', '') !== $spec['old_confidence']
            || number_format((float) $row['confidence_page'], 3, '.', '') !== $spec['old_confidence']) {
            throw new RuntimeException('Live source differs from the expected old citation state.');
        }

        $expectedChoices = array_map(static fn (array $choice): array => [
            'text' => (string) ($choice['text'] ?? ''), 'image' => $choice['image'] ?? null,
        ], $canonicalMatches[0]['choices'] ?? []);
        $choicesQuery = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
        $choicesQuery->execute(['ws' => $workspace, 'qid' => $row['question_id']]);
        $actualChoices = array_map(static fn (array $choice): array => [
            'text' => (string) $choice['text'], 'image' => $choice['image'] === null ? null : (string) $choice['image'],
        ], $choicesQuery->fetchAll(PDO::FETCH_ASSOC));
        $answerQuery = $db->prepare('SELECT status,choice_position,also_correct_positions FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
        $answerQuery->execute(['ws' => $workspace, 'qid' => $row['question_id']]);
        $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
        if (count($actualChoices) !== 4 || $actualChoices !== $expectedChoices || !is_array($answer)
            || !ResearchGuard::officialAnswerMatches($answer, $canonicalMatches[0]['answer'] ?? [])) {
            throw new RuntimeException('Live choices or official key differ from the pinned original.');
        }

        $plan = [
            'format' => 'fanoos.residency1403.ai-source-citation-replacement-plan/1',
            'year' => 1403,
            'round' => 1,
            'number' => $number,
            'subject' => $spec['subject'],
            'reference' => $spec['reference'],
            'chapter' => $spec['chapter'],
            'pdf_page' => $spec['pdf_page'],
            'printed_page' => $spec['printed_page'],
            'pdf_sha256' => $spec['pdf_sha256'],
            'confidence' => $spec['confidence'],
            'audit_path' => $spec['audit_path'],
            'audit_sha256' => $spec['audit_sha256'],
            'evidence_input_path' => $spec['evidence_input_path'],
            'evidence_input_sha256' => $spec['evidence_input_sha256'],
            'evidence_fragments' => $fragments,
            'anchor_text' => $anchor,
            'expected_source' => [
                'source_id' => (string) $row['source_id'],
                'edition' => $row['reference_key'] . '@' . $row['edition_key'],
                'chapter' => (string) $row['chapter'],
                'legacy_page' => (string) $row['page'],
                'printed_page' => null,
                'pdf_page' => (int) $row['pdf_page'],
                'pdf_sha256' => strtolower((string) $row['pdf_sha256']),
                'confidence' => number_format((float) $row['confidence_page'], 3, '.', ''),
                'anchor_sha256' => hash('sha256', (string) $row['anchor_text']),
            ],
            'immutable_question_verified' => true,
            'assessment_publish' => false,
            'database_writes' => 0,
        ];
        if (!Guard::planMatches($number, $plan, $fragments)) {
            throw new RuntimeException('Prepared compare-and-swap plan failed its static target guard.');
        }
        $db->rollBack();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    $json = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $handle = @fopen($outputPath, 'x');
    if ($handle === false) {
        throw new RuntimeException('Could not create a new private plan.');
    }
    chmod($outputPath, 0600);
    if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
        fclose($handle);
        @unlink($outputPath);
        throw new RuntimeException('Could not write the complete private plan.');
    }
    fclose($handle);
    echo json_encode(['mode' => 'read-only-plan', 'year' => 1403, 'number' => $number,
        'reference' => $spec['reference'], 'chapter' => $spec['chapter'],
        'pdf_page' => $spec['pdf_page'], 'printed_page' => $spec['printed_page'],
        'plan_sha256' => hash_file('sha256', $outputPath), 'database_writes' => 0],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '1403 source replacement plan refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
