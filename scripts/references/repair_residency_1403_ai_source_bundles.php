<?php

declare(strict_types=1);

/**
 * Residency 1403 only: source-only repair for the independently audited
 * two-page AI citations Q34 and Q119. Preview rolls back all source writes.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\Residency1403AiSourceBundleCorrectionGuard;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionGuard;
use Fanoos\Platform\Bank\VerifiedHumanChapterPageRepairGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Uuid;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const FANOOS_1403_BUNDLE_REPORTS = '/srv/fanoos/shared/research/classification/reports';
const FANOOS_1403_BUNDLE_TMP = '/srv/fanoos/shared/research/tmp';
const FANOOS_1403_BUNDLE_CANONICAL = '/srv/fanoos/shared/research/bank-sittings/1403/canonical-export-20261011/residency-1403-1.json';

/** @return array<int,array<string,mixed>> */
function readCanonical1403Bundle(string $path): array
{
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        $path,
        '/srv/fanoos/shared/research/bank-sittings/1403',
        false,
    ) || hash_file('sha256', $path) !== Residency1403AiSourceBundleCorrectionGuard::CANONICAL_SHA256) {
        throw new RuntimeException('The original canonical 1403 sitting is absent or changed.');
    }
    $canonical = json_decode((string) file_get_contents($path), true, 128, JSON_THROW_ON_ERROR);
    if (($canonical['exam_type'] ?? null) !== 'residency' || ($canonical['year'] ?? null) !== 1403
        || ($canonical['round'] ?? null) !== 1 || count($canonical['questions'] ?? []) !== 250) {
        throw new RuntimeException('The canonical sitting is not residency 1403 round one.');
    }
    $byNumber = [];
    foreach ($canonical['questions'] as $question) {
        $number = $question['number'] ?? null;
        if (!is_int($number) || isset($byNumber[$number])) {
            throw new RuntimeException('The canonical sitting has duplicate question numbers.');
        }
        $byNumber[$number] = $question;
    }
    return $byNumber;
}

/** @param array<string,mixed> $row @param array<string,mixed> $original */
function validateLiveBundleQuestion(PDO $db, array $row, array $original): void
{
    $number = (int) $row['number_in_sitting'];
    if (($row['question_status'] ?? null) !== 'published'
        || ($row['stem'] ?? null) !== ($original['stem'] ?? null)
        || ($row['subject_key'] ?? null) !== ($original['subject'] ?? null)) {
        throw new RuntimeException("Q{$number}: published question differs from the original sitting.");
    }
    $choicesQuery = $db->prepare('SELECT text,image FROM bank_question_choices
        WHERE workspace_id=:ws AND question_id=:qid ORDER BY position FOR UPDATE');
    $choicesQuery->execute(['ws' => $row['workspace_id'], 'qid' => $row['question_id']]);
    $actualChoices = array_map(static fn (array $choice): array => [
        'text' => (string) $choice['text'],
        'image' => $choice['image'] === null ? null : (string) $choice['image'],
    ], $choicesQuery->fetchAll(PDO::FETCH_ASSOC));
    $canonicalChoices = array_map(static fn (array $choice): array => [
        'text' => (string) $choice['text'],
        'image' => $choice['image'] ?? null,
    ], $original['choices'] ?? []);
    if (count($actualChoices) !== 4 || $actualChoices !== $canonicalChoices) {
        throw new RuntimeException("Q{$number}: choices differ from the original sitting.");
    }
    $answerQuery = $db->prepare('SELECT choice_position,also_correct_positions,status
        FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid
        ORDER BY recorded_at DESC,id DESC LIMIT 1 FOR UPDATE');
    $answerQuery->execute(['ws' => $row['workspace_id'], 'qid' => $row['question_id']]);
    $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
    if (!is_array($answer)
        || !Residency1403AiSourceCitationCorrectionGuard::officialAnswerMatches($answer, $original['answer'] ?? [])) {
        throw new RuntimeException("Q{$number}: official final key differs from the original sitting.");
    }
}

/** @return array<string,mixed> */
function validatePinnedBundleResearch(
    array $entry,
    array $evidenceAudit,
    array $supplementary,
    array $originalQuestion,
): array
{
    $number = (int) $entry['number'];
    if (!Residency1403AiSourceBundleCorrectionGuard::entryMatchesEvidence($evidenceAudit, $entry)) {
        throw new RuntimeException("Q{$number}: prepared bundle differs from the pinned private evidence.");
    }
    $record = null;
    foreach ($evidenceAudit['records'] as $candidate) {
        if (($candidate['number'] ?? null) === $number) {
            $record = $candidate;
            break;
        }
    }
    if (!is_array($record)) {
        throw new RuntimeException("Q{$number}: pinned bundle evidence record is missing.");
    }
    $base = $supplementary['candidate_records'][(string) $number] ?? null;
    if (!is_array($base)
        || ($base['official_edition'] ?? null) !== $record['edition']
        || ($base['pdf_sha256'] ?? null) !== $record['pdf_sha256']
        || ($base['primary_chapter'] ?? null) !== $record['pages'][0]['chapter']
        || ($base['primary_pdf_page'] ?? null) !== $record['pages'][0]['pdf_page']
        || ($base['supplemental_pdf_page'] ?? null) !== $record['pages'][1]['pdf_page']
        || ($base['primary_quote'] ?? null) !== $record['primary_research_evidence']
        || ($base['scientific_disambiguation'] ?? null) !== $record['scientific_disambiguation']) {
        throw new RuntimeException("Q{$number}: the original primary+supplementary audit no longer matches.");
    }

    foreach ([
        ['path' => $record['primary_research_path'], 'sha' => $record['primary_research_sha256']],
        ['path' => $record['decisions_path'], 'sha' => $record['decisions_sha256']],
        ['path' => $record['validated_study_path'], 'sha' => $record['validated_study_sha256']],
    ] as $private) {
        if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
            $private['path'], FANOOS_1403_BUNDLE_TMP, false,
        ) || hash_file('sha256', $private['path']) !== $private['sha']) {
            throw new RuntimeException("Q{$number}: a pinned private primary audit file is absent or changed.");
        }
    }
    $primaryReport = json_decode((string) file_get_contents($record['primary_research_path']), true, 64, JSON_THROW_ON_ERROR);
    $decisions = json_decode((string) file_get_contents($record['decisions_path']), true, 64, JSON_THROW_ON_ERROR);
    $first = $record['pages'][0];
    $primaryAuditItem = [
        'number' => $number,
        'subject' => $record['subject'],
        'new_ref' => $record['edition'],
        'new_ch' => $first['chapter'],
        'pdf_page' => $first['pdf_page'],
        'pdf_sha' => $record['pdf_sha256'],
        'evidence' => $record['primary_research_evidence'],
        'decisions_sha256' => $record['decisions_sha256'],
    ];
    if (!is_array($decisions) || !Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches(
        $primaryAuditItem, $primaryReport, $decisions,
    )) {
        throw new RuntimeException("Q{$number}: independent primary-page audit validation failed.");
    }
    $validatedStudy = json_decode((string) file_get_contents($record['validated_study_path']), true, 64, JSON_THROW_ON_ERROR);
    if (($validatedStudy['format'] ?? null) !== 'fanoos.classification.study-only/1'
        || ($validatedStudy['exam_type'] ?? null) !== 'residency' || ($validatedStudy['year'] ?? null) !== 1403
        || ($validatedStudy['round'] ?? null) !== 1 || !is_array($validatedStudy['questions'] ?? null)) {
        throw new RuntimeException("Q{$number}: pinned validated study has an invalid sitting identity.");
    }
    $studyQuestions = array_values(array_filter(
        $validatedStudy['questions'],
        static fn (mixed $question): bool => is_array($question) && ($question['number'] ?? null) === $number,
    ));
    $canonicalChoiceText = array_map(
        static fn (array $choice): string => (string) $choice['text'],
        $originalQuestion['choices'] ?? [],
    );
    $studySources = $studyQuestions[0]['sources'] ?? [];
    if (count($studyQuestions) !== 1 || ($studyQuestions[0]['subject'] ?? null) !== $record['subject']
        || ($studyQuestions[0]['stem'] ?? null) !== ($originalQuestion['stem'] ?? null)
        || ($studyQuestions[0]['choices'] ?? null) !== $canonicalChoiceText
        || ($studyQuestions[0]['answer']['choice'] ?? null) !== ($originalQuestion['answer']['choice'] ?? null)
        || ($studyQuestions[0]['answer']['also_correct'] ?? []) !== ($originalQuestion['answer']['also_correct'] ?? [])
        || ($studyQuestions[0]['answer']['status'] ?? null) !== ($originalQuestion['answer']['status'] ?? null)
        || !is_array($studySources) || count($studySources) !== 1
        || ($studySources[0]['ref'] ?? null) !== $record['edition'] . '#ch' . str_pad($first['chapter'], 2, '0', STR_PAD_LEFT)
        || ($studySources[0]['pdf_page'] ?? null) !== $first['pdf_page']
        || ($studySources[0]['pdf_sha256'] ?? null) !== $record['pdf_sha256']
        || ($studySources[0]['anchor'] ?? null) !== $record['primary_research_evidence']) {
        throw new RuntimeException("Q{$number}: validated study question or primary citation differs from canonical research.");
    }
    return $record;
}

$db = null;
$locked = false;
$committed = false;
$receiptPath = null;
try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI-only 1403 AI source-bundle repair.');
    }
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--(plan|apply|backup|receipt)(?:=(.*))?$/D', $argument, $match) !== 1
            || isset($args[$match[1]])) {
            throw new RuntimeException('Unknown or duplicate protected argument.');
        }
        $args[$match[1]] = $match[2] ?? '1';
    }
    $apply = isset($args['apply']);
    if (!isset($args['plan']) || ($apply !== isset($args['backup'])) || ($apply !== isset($args['receipt']))) {
        throw new RuntimeException('Plan required; apply also requires a verified backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid') || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Production apply requires authorized updater identity.');
    }
    $reports = realpath(FANOOS_1403_BUNDLE_REPORTS);
    $planPath = (string) $args['plan'];
    if ($reports === false || !Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($planPath, FANOOS_1403_BUNDLE_REPORTS)
        || realpath(dirname($planPath)) !== $reports) {
        throw new RuntimeException('Plan must be a private report in the protected classification directory.');
    }
    $planRaw = (string) file_get_contents($planPath);
    $planSha = hash('sha256', $planRaw);
    $plan = json_decode($planRaw, true, 128, JSON_THROW_ON_ERROR);
    $entries = $plan['entries'] ?? null;
    if (($plan['format'] ?? null) !== 'fanoos.residency1403.ai-source-bundle-correction/1'
        || ($plan['exam_year'] ?? null) !== 1403
        || ($plan['canonical_sha256'] ?? null) !== Residency1403AiSourceBundleCorrectionGuard::CANONICAL_SHA256
        || ($plan['research_sha256'] ?? null) !== Residency1403AiSourceBundleCorrectionGuard::RESEARCH_MANIFEST_SHA256
        || ($plan['evidence_path'] ?? null) !== Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_PATH
        || ($plan['evidence_sha256'] ?? null) !== Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_SHA256
        || !is_array($entries) || !array_is_list($entries) || count($entries) < 1 || count($entries) > 2) {
        throw new RuntimeException('Plan is not the pinned 1403 AI source-bundle schema.');
    }
    $numbers = array_map(static fn (array $entry): mixed => $entry['number'] ?? null, $entries);
    $sortedNumbers = $numbers;
    sort($sortedNumbers);
    if (count(array_unique($numbers)) !== count($numbers)
        || $sortedNumbers !== array_values(array_filter(
            Residency1403AiSourceBundleCorrectionGuard::supportedQuestions(),
            static fn (int $number): bool => in_array($number, $numbers, true),
        ))) {
        throw new RuntimeException('Plan has an unsupported or duplicated bundle question.');
    }

    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_PATH, FANOOS_1403_BUNDLE_REPORTS,
    ) || hash_file('sha256', Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_PATH)
        !== Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_SHA256) {
        throw new RuntimeException('Pinned exact-page bundle evidence is absent or changed.');
    }
    $evidenceAudit = json_decode((string) file_get_contents(
        Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_PATH,
    ), true, 128, JSON_THROW_ON_ERROR);
    if (!Residency1403AiSourceBundleCorrectionGuard::evidenceAuditMatches($evidenceAudit)) {
        throw new RuntimeException('Pinned exact-page bundle evidence failed integrity checks.');
    }
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_PATH,
        FANOOS_1403_BUNDLE_TMP,
        false,
    ) || hash_file('sha256', Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_PATH)
        !== Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_SHA256) {
        throw new RuntimeException('Independent supplementary audit is absent or changed.');
    }
    $supplementary = json_decode((string) file_get_contents(
        Residency1403AiSourceBundleCorrectionGuard::SUPPLEMENTARY_RESEARCH_PATH,
    ), true, 64, JSON_THROW_ON_ERROR);
    if (($supplementary['format'] ?? null) !== 'fanoos.1403.research-only.twin-page-evidence/1'
        || ($supplementary['no_production_source_mutation'] ?? null) !== true) {
        throw new RuntimeException('Supplementary report is not the pinned research-only audit.');
    }

    $original = readCanonical1403Bundle(FANOOS_1403_BUNDLE_CANONICAL);
    $manifestPath = Residency1403AiSourceBundleCorrectionGuard::RESEARCH_MANIFEST_PATH;
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile($manifestPath, FANOOS_1403_BUNDLE_TMP, false)
        || hash_file('sha256', $manifestPath) !== Residency1403AiSourceBundleCorrectionGuard::RESEARCH_MANIFEST_SHA256) {
        throw new RuntimeException('Pinned original 1403 research reconciliation is absent or changed.');
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 128, JSON_THROW_ON_ERROR);
    if (!Residency1403AiSourceCitationCorrectionGuard::researchManifestMatches($manifest)) {
        throw new RuntimeException('Pinned original 1403 research reconciliation failed checks.');
    }

    if ($apply) {
        $backup = realpath((string) $args['backup']);
        $receiptPath = (string) $args['receipt'];
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')
            || realpath(dirname($receiptPath)) !== $reports || is_link($receiptPath)
            || file_exists($receiptPath) || !str_ends_with($receiptPath, '.json')) {
            throw new RuntimeException('Fresh official backup and unique private receipt path required.');
        }
        $backupManifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($backupManifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($backupManifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Recent independently verified full database backup required.');
        }
    }

    $maps = json_decode((string) file_get_contents(dirname(__DIR__, 2)
        . '/data/bank/reference-chapter-pages.json'), true, 64, JSON_THROW_ON_ERROR);
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    if ((int) $db->query("SELECT GET_LOCK('" . Residency1403AiSourceBundleCorrectionGuard::LOCK_NAME . "',0)")->fetchColumn() !== 1) {
        throw new RuntimeException('Exclusive 1403 AI source-bundle correction already active.');
    }
    $locked = true;

    $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id AS source_id,s.workspace_id,s.question_id,s.edition_id,s.node_id,s.page,s.printed_page,s.pdf_page,
       s.pdf_sha256,s.anchor_text,s.table_ref,s.figure_ref,s.box_ref,s.origin,s.is_primary,
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
WHERE s.id=:source_id AND q.number_in_sitting=:number AND et.type_key='residency'
 AND si.exam_year=1403 AND si.exam_round=1 AND sb.subject_key=:subject
FOR UPDATE
SQL);
    $sourceCount = $db->prepare('SELECT id FROM bank_question_sources
        WHERE workspace_id=:ws AND question_id=:qid ORDER BY id FOR UPDATE');
    $validity = $db->prepare(<<<'SQL'
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
    $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources
SET edition_id=:new_edition,node_id=:new_node,page=:new_page,printed_page=NULL,
 pdf_page=:new_pdf_page,pdf_sha256=:new_pdf_sha256,anchor_text=:new_anchor
WHERE id=:source_id AND workspace_id=:workspace_id AND question_id=:question_id
 AND edition_id=:old_edition AND node_id=:old_node AND page <=> :old_page AND printed_page <=> :old_printed_page
 AND anchor_text <=> :old_anchor AND pdf_page IS NULL AND pdf_sha256 IS NULL
 AND origin='ai' AND is_primary=1 AND reviewed_at IS NULL AND reviewed_by_user_id IS NULL
 AND table_ref IS NULL AND figure_ref IS NULL AND box_ref IS NULL
SQL);
    $insert = $db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,node_id,page,pdf_page,printed_page,pdf_sha256,anchor_text,is_primary,
 confidence_source,confidence_node,confidence_page,origin,created_at)
VALUES (:id,:ws,:qid,:edition_id,:node_id,:page,:pdf_page,NULL,:pdf_sha,:anchor,0,0.950,0.950,0.950,'ai',UTC_TIMESTAMP(6))
SQL);
$liveReadback = $db->prepare(<<<'SQL'
SELECT id,edition_id,node_id,page,printed_page,pdf_page,pdf_sha256,anchor_text,is_primary,origin,
       reviewed_at,reviewed_by_user_id
FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid ORDER BY is_primary DESC,id FOR UPDATE
SQL);

    $results = [];
    $db->beginTransaction();
    try {
        foreach ($entries as $entry) {
            $number = $entry['number'] ?? null;
            $spec = is_int($number) ? Residency1403AiSourceBundleCorrectionGuard::spec($number) : null;
            if ($spec === null || !is_array($original[$number] ?? null)
                || !Residency1403AiSourceBundleCorrectionGuard::entryMatchesEvidence($evidenceAudit, $entry)
                || !isset($entry['source_id'], $entry['old_anchor_sha256'])
                || !array_key_exists('old_page', $entry) || !array_key_exists('old_printed_page', $entry)
                || !is_array($entry['pages']) || count($entry['pages']) !== 2) {
                throw new RuntimeException('Malformed or unsupported audited bundle plan entry.');
            }
            $record = null;
            foreach ($evidenceAudit['records'] as $candidate) {
                if (($candidate['number'] ?? null) === $number) {
                    $record = $candidate;
                    break;
                }
            }
            if (!is_array($record)) {
                throw new RuntimeException("Q{$number}: pinned page bundle missing.");
            }
            validatePinnedBundleResearch($entry, $evidenceAudit, $supplementary, $original[$number]);

            $sourceQuery->execute([
                'source_id' => $entry['source_id'],
                'number' => $number,
                'subject' => $record['subject'],
            ]);
            $rows = $sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== 1) {
                throw new RuntimeException("Q{$number}: original source row is no longer unique.");
            }
            $old = $rows[0];
            if ($old['source_id'] !== $entry['source_id']
                || $old['question_status'] !== $entry['immutable_question_status']
                || hash('sha256', (string) $old['stem']) !== $entry['immutable_stem_sha256']
                || $old['subject_key'] !== $record['subject']
                || $old['type_key'] !== 'residency' || (int) $old['exam_year'] !== 1403 || (int) $old['exam_round'] !== 1
                || $old['edition_id'] !== $entry['old_edition_id'] || $old['node_id'] !== $entry['old_node_id']
                || $old['reference_key'] . '@' . $old['edition_key'] !== $entry['old_ref']
                || (string) $old['old_chapter'] !== (string) $entry['old_chapter']
                || $old['page'] !== $entry['old_page'] || $old['printed_page'] !== $entry['old_printed_page']
                || hash('sha256', (string) $old['anchor_text']) !== $entry['old_anchor_sha256']
                || $old['origin'] !== 'ai' || (int) $old['is_primary'] !== 1
                || $old['reviewed_at'] !== null || $old['reviewed_by_user_id'] !== null
                || $old['pdf_page'] !== null || $old['pdf_sha256'] !== null
                || $old['table_ref'] !== null || $old['figure_ref'] !== null || $old['box_ref'] !== null
                || $entry['old_origin'] !== 'ai' || (int) $entry['old_is_primary'] !== 1
                || $entry['old_reviewed_at'] !== null || $entry['old_reviewed_by_user_id'] !== null) {
                throw new RuntimeException("Q{$number}: original AI source row changed, reviewed or already bundled.");
            }
            $sourceCount->execute(['ws' => $old['workspace_id'], 'qid' => $old['question_id']]);
            if (count($sourceCount->fetchAll(PDO::FETCH_COLUMN)) !== 1) {
                throw new RuntimeException("Q{$number}: existing source bundle is not a single AI source row.");
            }
            validateLiveBundleQuestion($db, $old, $original[$number]);

            $verifiedPages = [];
            foreach ($entry['pages'] as $page) {
                $chapter = (string) $page['chapter'];
                $pdfPage = (int) $page['pdf_page'];
                if (($maps['editions'][$record['edition']]['source'] ?? null) !== 'verified-server-pdf'
                    || ($maps['editions'][$record['edition']]['source_pdf_sha256'] ?? null) !== $record['pdf_sha256']
                    || !VerifiedHumanChapterPageRepairGuard::pageInMap($maps, $record['edition'], $chapter, $pdfPage, $record['pdf_sha256'])
                    || !PrintedBookPageEvidence::pageContainsEvidence($record['edition'], $pdfPage, $page['evidence'], $record['pdf_sha256'])) {
                    throw new RuntimeException("Q{$number}: page-local evidence or exact-PDF chapter map failed.");
                }
                $reference = strstr($record['edition'], '@', true);
                $editionKey = substr(strstr($record['edition'], '@'), 1);
                $validity->execute([
                    'ws' => $old['workspace_id'],
                    'reference' => $reference,
                    'edition' => $editionKey,
                    'node' => 'ch' . str_pad($chapter, 2, '0', STR_PAD_LEFT),
                    'subject' => $record['subject'],
                ]);
                $targets = $validity->fetchAll(PDO::FETCH_ASSOC);
                if (count($targets) !== 1 || $targets[0]['kind'] !== 'chapter' || (int) $targets[0]['is_official'] !== 1
                    || !VerifiedHumanChapterPageRepairGuard::scopeAllows($targets[0]['scope_chapters'], $chapter)) {
                    throw new RuntimeException("Q{$number}: each bundled chapter must be official and fully in scope for 1403.");
                }
                $verifiedPages[] = [
                    'chapter' => $chapter,
                    'edition_id' => $targets[0]['edition_id'],
                    'node_id' => $targets[0]['node_id'],
                    'pdf_page' => $pdfPage,
                    'pdf_sha256' => $record['pdf_sha256'],
                    'evidence' => $page['evidence'],
                    'primary' => (bool) $page['primary'],
                ];
            }
            if ($verifiedPages[0]['edition_id'] !== $verifiedPages[1]['edition_id']
                || $verifiedPages[0]['pdf_sha256'] !== $verifiedPages[1]['pdf_sha256']) {
                throw new RuntimeException("Q{$number}: pages must use the same official original edition and PDF.");
            }

            $first = $verifiedPages[0];
            $primaryAnchor = $first['evidence'][0];
            $update->execute([
                'new_edition' => $first['edition_id'],
                'new_node' => $first['node_id'],
                'new_page' => 'pdf ' . $first['pdf_page'],
                'new_pdf_page' => $first['pdf_page'],
                'new_pdf_sha256' => $first['pdf_sha256'],
                'new_anchor' => $primaryAnchor,
                'source_id' => $old['source_id'],
                'workspace_id' => $old['workspace_id'],
                'question_id' => $old['question_id'],
                'old_edition' => $old['edition_id'],
                'old_node' => $old['node_id'],
                'old_page' => $old['page'],
                'old_printed_page' => $old['printed_page'],
                'old_anchor' => $old['anchor_text'],
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: guarded primary source update failed.");
            }

            $secondary = $verifiedPages[1];
            $secondaryId = Uuid::v7();
            $secondaryAnchor = $secondary['evidence'][0];
            $insert->execute([
                'id' => $secondaryId,
                'ws' => $old['workspace_id'],
                'qid' => $old['question_id'],
                'edition_id' => $secondary['edition_id'],
                'node_id' => $secondary['node_id'],
                'page' => 'pdf ' . $secondary['pdf_page'],
                'pdf_page' => $secondary['pdf_page'],
                'pdf_sha' => $secondary['pdf_sha256'],
                'anchor' => $secondaryAnchor,
            ]);
            if ($insert->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: guarded secondary source insert failed.");
            }

            $liveReadback->execute(['ws' => $old['workspace_id'], 'qid' => $old['question_id']]);
            $after = $liveReadback->fetchAll(PDO::FETCH_ASSOC);
            $expectedPrimary = [
                'id' => $old['source_id'], 'edition_id' => $first['edition_id'], 'node_id' => $first['node_id'],
                'page' => 'pdf ' . $first['pdf_page'], 'printed_page' => null,
                'pdf_page' => $first['pdf_page'], 'pdf_sha256' => $first['pdf_sha256'],
                'anchor_text' => $primaryAnchor, 'is_primary' => 1, 'origin' => 'ai',
            ];
            $expectedSecondary = [
                'id' => $secondaryId, 'edition_id' => $secondary['edition_id'], 'node_id' => $secondary['node_id'],
                'page' => 'pdf ' . $secondary['pdf_page'], 'printed_page' => null,
                'pdf_page' => $secondary['pdf_page'], 'pdf_sha256' => $secondary['pdf_sha256'],
                'anchor_text' => $secondaryAnchor, 'is_primary' => 0, 'origin' => 'ai',
            ];
            if (count($after) !== 2
                || !is_array($after[0] ?? null) || !is_array($after[1] ?? null)
                || $after[0]['id'] !== $expectedPrimary['id']
                || $after[0]['edition_id'] !== $expectedPrimary['edition_id']
                || $after[0]['node_id'] !== $expectedPrimary['node_id']
                || $after[0]['page'] !== $expectedPrimary['page'] || $after[0]['printed_page'] !== null
                || (int) $after[0]['pdf_page'] !== $expectedPrimary['pdf_page']
                || strtolower((string) $after[0]['pdf_sha256']) !== $expectedPrimary['pdf_sha256']
                || $after[0]['anchor_text'] !== $expectedPrimary['anchor_text']
                || (int) $after[0]['is_primary'] !== 1 || $after[0]['origin'] !== 'ai'
                || $after[0]['reviewed_at'] !== null || $after[0]['reviewed_by_user_id'] !== null
                || $after[1]['id'] !== $expectedSecondary['id']
                || $after[1]['edition_id'] !== $expectedSecondary['edition_id']
                || $after[1]['node_id'] !== $expectedSecondary['node_id']
                || $after[1]['page'] !== $expectedSecondary['page'] || $after[1]['printed_page'] !== null
                || (int) $after[1]['pdf_page'] !== $expectedSecondary['pdf_page']
                || strtolower((string) $after[1]['pdf_sha256']) !== $expectedSecondary['pdf_sha256']
                || $after[1]['anchor_text'] !== $expectedSecondary['anchor_text']
                || (int) $after[1]['is_primary'] !== 0 || $after[1]['origin'] !== 'ai'
                || $after[1]['reviewed_at'] !== null || $after[1]['reviewed_by_user_id'] !== null) {
                throw new RuntimeException("Q{$number}: source bundle row count readback mismatch.");
            }
            $results[] = [
                'number' => $number,
                'primary' => [
                    'source_id' => $old['source_id'], 'edition' => $record['edition'],
                    'chapter' => $first['chapter'], 'pdf_page' => $first['pdf_page'],
                    'pdf_sha256' => $first['pdf_sha256'], 'anchor_sha256' => hash('sha256', $primaryAnchor),
                    'is_primary' => true,
                ],
                'secondary' => [
                    'source_id' => $secondaryId, 'edition' => $record['edition'],
                    'chapter' => $secondary['chapter'], 'pdf_page' => $secondary['pdf_page'],
                    'pdf_sha256' => $secondary['pdf_sha256'], 'anchor_sha256' => hash('sha256', $secondaryAnchor),
                    'is_primary' => false,
                ],
            ];
        }

        if (!$apply) {
            $db->rollBack();
        } else {
            $receiptStream = @fopen($receiptPath, 'x');
            if ($receiptStream === false) {
                throw new RuntimeException('Cannot reserve unique protected receipt before commit.');
            }
            chmod($receiptPath, 0600);
            fclose($receiptStream);
            $db->commit();
            $committed = true;
        }
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    $reply = [
        'format' => 'fanoos.residency1403.ai-source-bundle-correction-receipt/1',
        'applied' => $apply,
        'question_count' => count($results),
        'exam_year' => 1403,
        'canonical_sha256' => Residency1403AiSourceBundleCorrectionGuard::CANONICAL_SHA256,
        'research_sha256' => Residency1403AiSourceBundleCorrectionGuard::RESEARCH_MANIFEST_SHA256,
        'evidence_sha256' => Residency1403AiSourceBundleCorrectionGuard::EVIDENCE_SHA256,
        'plan_sha256' => $planSha,
        'source_fields_only' => ['edition_id','node_id','page','printed_page','pdf_page','pdf_sha256','anchor_text'],
        'secondary_source_fields_inserted' => [
            'id','workspace_id','question_id','edition_id','node_id','page','pdf_page','printed_page',
            'pdf_sha256','anchor_text','is_primary','confidence_source','confidence_node','confidence_page','origin','created_at',
        ],
        'questions_choices_official_answers_human_reviews_attempts_assessments_changed' => 0,
        'updated' => $results,
    ];
    if ($apply) {
        $reply['backup_id'] = basename($backup);
        $reply['committed_utc'] = gmdate('c');
        $output = json_encode($reply, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        if (file_put_contents($receiptPath, $output, LOCK_EX) !== strlen($output)) {
            throw new RuntimeException('Committed but receipt write failed: reconcile DB before any retry.');
        }
    }
    echo json_encode($reply, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if (!$committed && is_string($receiptPath) && is_file($receiptPath) && filesize($receiptPath) === 0) {
        unlink($receiptPath);
    }
    fwrite(STDERR, 'Strict 1403 AI source-bundle repair rejected: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($locked && $db instanceof PDO) {
        $db->query("SELECT RELEASE_LOCK('" . Residency1403AiSourceBundleCorrectionGuard::LOCK_NAME . "')");
    }
}
