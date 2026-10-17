<?php

declare(strict_types=1);

/**
 * Build a protected read-only compare-and-swap plan for the independently
 * audited 1403 Q34/Q119 multi-page AI source bundles.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\Residency1403AiSourceBundleCorrectionGuard;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionGuard;
use Fanoos\Platform\Bank\VerifiedHumanChapterPageRepairGuard;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const FANOOS_1403_BUNDLE_REPORTS = '/srv/fanoos/shared/research/classification/reports';
const FANOOS_1403_BUNDLE_TMP = '/srv/fanoos/shared/research/tmp';
const FANOOS_1403_BUNDLE_CANONICAL = '/srv/fanoos/shared/research/bank-sittings/1403/canonical-export-20261011/residency-1403-1.json';

/** @return array<int,array<string,mixed>> */
function bundleCanonicalQuestions(string $path): array
{
    $canonical = json_decode((string) file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    if (($canonical['exam_type'] ?? null) !== 'residency' || ($canonical['year'] ?? null) !== 1403
        || ($canonical['round'] ?? null) !== 1 || count($canonical['questions'] ?? []) !== 250) {
        throw new RuntimeException('The pinned original 1403 sitting has the wrong identity.');
    }
    $byNumber = [];
    foreach ($canonical['questions'] as $question) {
        $number = $question['number'] ?? null;
        if (!is_int($number) || isset($byNumber[$number])) {
            throw new RuntimeException('The original 1403 sitting has a duplicate question number.');
        }
        $byNumber[$number] = $question;
    }
    return $byNumber;
}

/** @param array<string,mixed> $actual @param array<string,mixed> $canonical */
function bundleValidateImmutableQuestion(PDO $db, array $actual, array $canonical): void
{
    $number = (int) $actual['number_in_sitting'];
    if (($actual['question_status'] ?? null) !== 'published'
        || ($actual['subject_key'] ?? null) !== ($canonical['subject'] ?? null)
        || ($actual['stem'] ?? null) !== ($canonical['stem'] ?? null)) {
        throw new RuntimeException("Q{$number}: published question differs from the pinned original.");
    }
    $choicesQuery = $db->prepare('SELECT text,image FROM bank_question_choices
        WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $choicesQuery->execute(['ws' => $actual['workspace_id'], 'qid' => $actual['question_id']]);
    $choices = array_map(static fn (array $choice): array => [
        'text' => (string) $choice['text'],
        'image' => $choice['image'] === null ? null : (string) $choice['image'],
    ], $choicesQuery->fetchAll(PDO::FETCH_ASSOC));
    $expectedChoices = array_map(static fn (array $choice): array => [
        'text' => (string) $choice['text'],
        'image' => $choice['image'] ?? null,
    ], $canonical['choices'] ?? []);
    if (count($choices) !== 4 || $choices !== $expectedChoices) {
        throw new RuntimeException("Q{$number}: choices differ from the pinned original.");
    }
    $answerQuery = $db->prepare('SELECT choice_position,also_correct_positions,status
        FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid
        ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $answerQuery->execute(['ws' => $actual['workspace_id'], 'qid' => $actual['question_id']]);
    $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($answer)
        || !Residency1403AiSourceCitationCorrectionGuard::officialAnswerMatches($answer, $canonical['answer'] ?? [])) {
        throw new RuntimeException("Q{$number}: official final answer differs from the pinned original.");
    }
}

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI-only 1403 bundle plan preparation.');
    }
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--(numbers|output)=(.*)$/D', $argument, $match) !== 1 || isset($args[$match[1]])) {
            throw new RuntimeException('Unknown or duplicate argument.');
        }
        $args[$match[1]] = $match[2];
    }
    if (!isset($args['numbers'], $args['output'])) {
        throw new RuntimeException('Both --numbers and --output are required.');
    }
    $numbers = array_map('intval', explode(',', (string) $args['numbers']));
    $sortedNumbers = $numbers;
    sort($sortedNumbers);
    if ($numbers === [] || count($numbers) > 2 || count(array_unique($numbers)) !== count($numbers)
        || $sortedNumbers !== array_values(array_filter(
            Residency1403AiSourceBundleCorrectionGuard::supportedQuestions(),
            static fn (int $number): bool => in_array($number, $numbers, true),
        ))) {
        throw new RuntimeException('Only unique audited question numbers 34 and 119 are supported.');
    }

    $reports = realpath(FANOOS_1403_BUNDLE_REPORTS);
    $output = (string) $args['output'];
    if ($reports === false || realpath(FANOOS_1403_BUNDLE_REPORTS) !== $reports
        || (fileperms($reports) & 0077) !== 0 || realpath(dirname($output)) !== $reports
        || is_link($output) || file_exists($output) || !str_ends_with($output, '.json')) {
        throw new RuntimeException('Output must be a new JSON file in the private reports directory.');
    }

    $evidencePath = Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_PATH;
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($evidencePath, FANOOS_1403_BUNDLE_REPORTS)
        || hash_file('sha256', $evidencePath) !== Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_SHA256) {
        throw new RuntimeException('The SHA-pinned Q34/Q119 page-bundle evidence is absent or changed.');
    }
    $evidenceAudit = json_decode((string) file_get_contents($evidencePath), true, 128, JSON_THROW_ON_ERROR);
    if (!Residency1403AiSourceBundleCorrectionGuard::evidenceAuditMatches($evidenceAudit)) {
        throw new RuntimeException('The original-PDF page-bundle evidence failed its pinned format checks.');
    }

    $supplementaryPath = Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_PATH;
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        $supplementaryPath,
        FANOOS_1403_BUNDLE_TMP,
        false,
    ) || hash_file('sha256', $supplementaryPath)
        !== Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_SHA256) {
        throw new RuntimeException('The independent supplementary two-page audit is absent or changed.');
    }
    $supplementary = json_decode((string) file_get_contents($supplementaryPath), true, 64, JSON_THROW_ON_ERROR);
    if (($supplementary['format'] ?? null) !== 'fanoos.1403.research-only.twin-page-evidence/1'
        || ($supplementary['no_production_source_mutation'] ?? null) !== true
        || !is_array($supplementary['candidate_records'] ?? null)) {
        throw new RuntimeException('The supplementary research report is not a research-only twin-page audit.');
    }

    $canonicalPath = FANOOS_1403_BUNDLE_CANONICAL;
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        $canonicalPath,
        '/srv/fanoos/shared/research/bank-sittings/1403',
        false,
    ) || hash_file('sha256', $canonicalPath) !== Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256) {
        throw new RuntimeException('The original canonical 1403 sitting is absent or changed.');
    }
    $canonical = bundleCanonicalQuestions($canonicalPath);
    $manifestPath = Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_PATH;
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        $manifestPath,
        FANOOS_1403_BUNDLE_TMP,
        false,
    ) || hash_file('sha256', $manifestPath) !== Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_SHA256) {
        throw new RuntimeException('The pinned 1403 research reconciliation is absent or changed.');
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 128, JSON_THROW_ON_ERROR);
    if (!Residency1403AiSourceCitationCorrectionGuard::researchManifestMatches($manifest)) {
        throw new RuntimeException('The pinned 1403 research reconciliation failed integrity checks.');
    }

    $maps = json_decode((string) file_get_contents(dirname(__DIR__, 2)
        . '/data/bank/reference-chapter-pages.json'), true, 64, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id AS source_id,s.workspace_id,s.question_id,s.edition_id,s.node_id,s.page,s.printed_page,
       s.pdf_page,s.pdf_sha256,s.anchor_text,s.table_ref,s.figure_ref,s.box_ref,s.origin,s.is_primary,
       s.reviewed_at,s.reviewed_by_user_id,q.number_in_sitting,q.status AS question_status,q.stem,
       sb.subject_key,r.reference_key,e.edition_key,n.number AS old_chapter,et.type_key,si.exam_year,si.exam_round
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE q.number_in_sitting=:number AND et.type_key='residency'
 AND si.exam_year=1403 AND si.exam_round=1 AND sb.subject_key=:subject
SQL);
        $sourceCountQuery = $db->prepare('SELECT id FROM bank_question_sources
            WHERE workspace_id=:ws AND question_id=:qid ORDER BY id');
        $validityQuery = $db->prepare(<<<'SQL'
SELECT e.id AS edition_id,n.id AS node_id,n.kind,v.scope_chapters,v.is_official
FROM bank_reference_editions e
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
WHERE e.workspace_id=:ws AND r.reference_key=:reference AND e.edition_key=:edition
 AND n.node_key=:node AND et.type_key='residency' AND v.exam_year=1403
 AND sb.subject_key=:subject AND v.is_official=1
SQL);

        $entries = [];
        foreach ($numbers as $number) {
            $recordMatches = array_values(array_filter(
                $evidenceAudit['records'],
                static fn (mixed $record): bool => is_array($record) && ($record['number'] ?? null) === $number,
            ));
            if (count($recordMatches) !== 1) {
                throw new RuntimeException("Q{$number}: no unique pinned page-bundle evidence record.");
            }
            $record = $recordMatches[0];
            $baseRecord = $supplementary['candidate_records'][(string) $number] ?? null;
            $spec = Residency1403AiSourceBundleCorrectionGuard::spec($number);
            if (!is_array($baseRecord) || !is_array($spec)
                || ($baseRecord['official_edition'] ?? null) !== $record['edition']
                || ($baseRecord['pdf_sha256'] ?? null) !== $record['pdf_sha256']
                || ($baseRecord['primary_chapter'] ?? null) !== $record['pages'][0]['chapter']
                || ($baseRecord['primary_pdf_page'] ?? null) !== $record['pages'][0]['pdf_page']
                || ($baseRecord['supplemental_pdf_page'] ?? null) !== $record['pages'][1]['pdf_page']
                || ($baseRecord['primary_quote'] ?? null) !== $record['primary_research_evidence']
                || ($baseRecord['scientific_disambiguation'] ?? null) !== $record['scientific_disambiguation']) {
                throw new RuntimeException("Q{$number}: pinned primary and supplementary page decisions differ.");
            }

            foreach ([
                ['path' => $record['primary_research_path'], 'sha' => $record['primary_research_sha256']],
                ['path' => $record['decisions_path'], 'sha' => $record['decisions_sha256']],
                ['path' => $record['validated_study_path'], 'sha' => $record['validated_study_sha256']],
            ] as $private) {
                if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
                    $private['path'], FANOOS_1403_BUNDLE_TMP, false,
                ) || hash_file('sha256', $private['path']) !== $private['sha']) {
                    throw new RuntimeException("Q{$number}: pinned primary research artifact is absent or changed.");
                }
            }
            $primaryReport = json_decode((string) file_get_contents($record['primary_research_path']), true, 64, JSON_THROW_ON_ERROR);
            $decisions = json_decode((string) file_get_contents($record['decisions_path']), true, 64, JSON_THROW_ON_ERROR);
            $primaryPage = $record['pages'][0];
            $primaryAuditItem = [
                'number' => $number,
                'subject' => $record['subject'],
                'new_ref' => $record['edition'],
                'new_ch' => $primaryPage['chapter'],
                'pdf_page' => $primaryPage['pdf_page'],
                'pdf_sha' => $record['pdf_sha256'],
                'evidence' => $record['primary_research_evidence'],
                'decisions_sha256' => $record['decisions_sha256'],
            ];
            if (!is_array($decisions) || !Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches(
                $primaryAuditItem, $primaryReport, $decisions,
            )) {
                throw new RuntimeException("Q{$number}: prior independent primary-page audit no longer matches.");
            }
            $validated = json_decode((string) file_get_contents($record['validated_study_path']), true, 64, JSON_THROW_ON_ERROR);
            if (($validated['format'] ?? null) !== 'fanoos.classification.study-only/1'
                || ($validated['exam_type'] ?? null) !== 'residency' || ($validated['year'] ?? null) !== 1403
                || ($validated['round'] ?? null) !== 1) {
                throw new RuntimeException("Q{$number}: validated study has the wrong residency sitting identity.");
            }
            $studyQuestions = array_values(array_filter(
                $validated['questions'] ?? [],
                static fn (mixed $question): bool => is_array($question) && ($question['number'] ?? null) === $number,
            ));
            if (count($studyQuestions) !== 1 || ($studyQuestions[0]['subject'] ?? null) !== $record['subject']) {
                throw new RuntimeException("Q{$number}: validated research study identity changed.");
            }
            $studySources = $studyQuestions[0]['sources'] ?? [];
            if (!is_array($studySources) || count($studySources) !== 1
                || ($studySources[0]['ref'] ?? null) !== $record['edition'] . '#ch' . str_pad($primaryPage['chapter'], 2, '0', STR_PAD_LEFT)
                || ($studySources[0]['pdf_page'] ?? null) !== $primaryPage['pdf_page']
                || ($studySources[0]['pdf_sha256'] ?? null) !== $record['pdf_sha256']
                || ($studySources[0]['anchor'] ?? null) !== $record['primary_research_evidence']) {
                throw new RuntimeException("Q{$number}: validated study primary citation differs from independent audit.");
            }
            if (($canonical[$number]['subject'] ?? null) !== $record['subject']
                || ($studyQuestions[0]['stem'] ?? null) !== ($canonical[$number]['stem'] ?? null)
                || ($studyQuestions[0]['choices'] ?? null) !== array_map(
                    static fn (array $choice): string => (string) $choice['text'],
                    $canonical[$number]['choices'] ?? [],
                )
                || ($studyQuestions[0]['answer']['status'] ?? null) !== ($canonical[$number]['answer']['status'] ?? null)
                || ($studyQuestions[0]['answer']['choice'] ?? null) !== ($canonical[$number]['answer']['choice'] ?? null)
                || ($studyQuestions[0]['answer']['also_correct'] ?? []) !== ($canonical[$number]['answer']['also_correct'] ?? [])) {
                throw new RuntimeException("Q{$number}: validated study question identity differs from canonical sitting.");
            }

            $sourceQuery->execute(['number' => $number, 'subject' => $record['subject']]);
            $sourceRows = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($sourceRows) !== 1) {
                throw new RuntimeException("Q{$number}: existing AI source row is not unique.");
            }
            $source = $sourceRows[0];
            if ($source['question_status'] !== 'published'
                || $source['reference_key'] . '@' . $source['edition_key'] !== $record['edition']
                || $source['origin'] !== 'ai' || (int) $source['is_primary'] !== 1
                || $source['reviewed_at'] !== null || $source['reviewed_by_user_id'] !== null
                || $source['pdf_page'] !== null || $source['pdf_sha256'] !== null
                || $source['table_ref'] !== null || $source['figure_ref'] !== null || $source['box_ref'] !== null) {
                throw new RuntimeException("Q{$number}: existing source is reviewed, bound, or not the audited AI source.");
            }
            $sourceCountQuery->execute(['ws' => $source['workspace_id'], 'qid' => $source['question_id']]);
            if (count($sourceCountQuery->fetchAll(PDO::FETCH_COLUMN)) !== 1) {
                throw new RuntimeException("Q{$number}: existing source is already a bundle or duplicate.");
            }
            bundleValidateImmutableQuestion($db, $source, $canonical[$number]);

            $pages = [];
            foreach ($record['pages'] as $pageRecord) {
                $chapter = (string) $pageRecord['chapter'];
                $pdfPage = (int) $pageRecord['pdf_page'];
                $sha = $record['pdf_sha256'];
                if (($maps['editions'][$record['edition']]['source'] ?? null) !== 'verified-server-pdf'
                    || ($maps['editions'][$record['edition']]['source_pdf_sha256'] ?? null) !== $sha
                    || !VerifiedHumanChapterPageRepairGuard::pageInMap($maps, $record['edition'], $chapter, $pdfPage, $sha)
                    || !PrintedBookPageEvidence::pageContainsEvidence($record['edition'], $pdfPage, $pageRecord['evidence'], $sha)) {
                    throw new RuntimeException("Q{$number}: exact original PDF page evidence or SHA-bound chapter map failed.");
                }
                $validityQuery->execute([
                    'ws' => $source['workspace_id'],
                    'reference' => strstr($record['edition'], '@', true),
                    'edition' => substr(strstr($record['edition'], '@'), 1),
                    'node' => 'ch' . str_pad($chapter, 2, '0', STR_PAD_LEFT),
                    'subject' => $record['subject'],
                ]);
                $valid = $validityQuery->fetchAll(PDO::FETCH_ASSOC);
                if (count($valid) !== 1 || $valid[0]['kind'] !== 'chapter' || (int) $valid[0]['is_official'] !== 1
                    || !VerifiedHumanChapterPageRepairGuard::scopeAllows($valid[0]['scope_chapters'], $chapter)) {
                    throw new RuntimeException("Q{$number}: bundled page chapter is not an official complete 1403 scope chapter.");
                }
                $pages[] = $pageRecord;
            }

            $entries[] = [
                'number' => $number,
                'subject' => $record['subject'],
                'source_id' => $source['source_id'],
                'old_edition_id' => $source['edition_id'],
                'old_node_id' => $source['node_id'],
                'old_ref' => $source['reference_key'] . '@' . $source['edition_key'],
                'old_chapter' => (string) $source['old_chapter'],
                'old_page' => $source['page'],
                'old_printed_page' => $source['printed_page'],
                'old_anchor_sha256' => hash('sha256', (string) $source['anchor_text']),
                'old_origin' => $source['origin'],
                'old_is_primary' => (int) $source['is_primary'],
                'old_reviewed_at' => $source['reviewed_at'],
                'old_reviewed_by_user_id' => $source['reviewed_by_user_id'],
                'immutable_stem_sha256' => hash('sha256', (string) $source['stem']),
                'immutable_question_status' => $source['question_status'],
                'edition' => $record['edition'],
                'pdf_sha256' => $record['pdf_sha256'],
                'primary_research_path' => $record['primary_research_path'],
                'primary_research_sha256' => $record['primary_research_sha256'],
                'decisions_path' => $record['decisions_path'],
                'decisions_sha256' => $record['decisions_sha256'],
                'validated_study_path' => $record['validated_study_path'],
                'validated_study_sha256' => $record['validated_study_sha256'],
                'primary_research_evidence' => $record['primary_research_evidence'],
                'scientific_disambiguation' => $record['scientific_disambiguation'],
                'pages' => $pages,
            ];
        }
        $db->rollBack();
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    $plan = [
        'format' => 'fanoos.residency1403.ai-source-bundle-correction/1',
        'exam_year' => 1403,
        'canonical_sha256' => Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256,
        'research_sha256' => Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_SHA256,
        'evidence_path' => Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_PATH,
        'evidence_sha256' => Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_SHA256,
        'entries' => $entries,
    ];
    $json = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    $temporary = tempnam($reports, '.residency-1403-ai-bundle-plan-');
    if ($temporary === false) {
        throw new RuntimeException('Could not allocate protected plan staging file.');
    }
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('Could not write complete plan.');
        }
        chmod($temporary, 0600);
        if (!link($temporary, $output) || !unlink($temporary)) {
            throw new RuntimeException('Could not atomically install unique plan.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
    echo json_encode([
        'mode' => 'read-only-plan-created', 'path' => $output,
        'sha256' => hash('sha256', $json), 'count' => count($entries),
        'numbers' => $numbers, 'database_writes' => 0, 'assessment_writes' => 0,
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, '1403 AI source-bundle plan refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
