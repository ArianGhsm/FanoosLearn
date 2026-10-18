<?php

declare(strict_types=1);

/**
 * Insert exact, audited source locations for source-free residency questions
 * whose entire official-edition chapter is outside the announced syllabus.
 * Preview rolls back; apply is source-only and requires a fresh backup.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\VerifiedAiOutsideScopeSourceInsertGuard;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Uuid;
$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--(workspace|case-root|stem|plan|apply|backup|receipt)(?:=(.*))?$/D', $arg, $match) !== 1
            || isset($args[$match[1]])) {
            throw new RuntimeException('Unknown or repeated protected argument.');
        }
        $args[$match[1]] = $match[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $requestedCase = (string) ($args['case-root'] ?? '');
    $stem = (string) ($args['stem'] ?? '');
    $apply = isset($args['apply']);
    if (!preg_match('/^[a-f0-9-]{36}$/D', $workspace)
        || !preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $stem)
        || $apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Workspace/stem required; apply requires backup and receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? null) !== 'fanoosupd')) {
        throw new RuntimeException('Only the authorized updater service account may apply.');
    }

    $privateRoot = realpath('/srv/fanoos/shared/research/classification');
    $caseRoot = realpath($requestedCase);
    if ($privateRoot === false || $caseRoot === false || $requestedCase !== $caseRoot
        || dirname($caseRoot) !== $privateRoot || is_link($requestedCase)
        || !preg_match('/^recovery-(13[0-9]{2}|14[0-9]{2})-[a-z0-9-]+-20[0-9]{6}$/D', basename($caseRoot))) {
        throw new RuntimeException('Case root must be a direct protected same-year recovery folder.');
    }
    $caseMatch = [];
    preg_match('/^recovery-([0-9]{4})-/', basename($caseRoot), $caseMatch);
    $caseYear = (int) ($caseMatch[1] ?? 0);
    $reports = realpath($caseRoot . '/classification/reports');
    $planPath = (string) ($args['plan'] ?? '');
    if ($reports === false || realpath(dirname($planPath)) !== $reports
        || is_link($planPath) || !is_file($planPath)) {
        throw new RuntimeException('Plan must be a protected report inside the exact recovery case.');
    }
    $rawPlan = (string) file_get_contents($planPath);
    $plan = json_decode($rawPlan, true, 64, JSON_THROW_ON_ERROR);
    if (($plan['format'] ?? null) !== 'fanoos.verified-ai-outside-scope-source-insert/1'
        || ($plan['exam_type'] ?? null) !== 'residency'
        || ($plan['year'] ?? null) !== $caseYear
        || !is_string($plan['subject'] ?? null)
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $plan['subject']) !== 1
        || ($plan['stem'] ?? null) !== $stem
        || !is_array($plan['entries'] ?? null) || !array_is_list($plan['entries'])
        || count($plan['entries']) < 1 || count($plan['entries']) > 12) {
        throw new RuntimeException('Invalid outside-scope source-insert plan scope or size.');
    }

    $studyDirectory = realpath($caseRoot . "/bank-sittings/{$caseYear}");
    $sittingsDirectory = realpath($caseRoot . '/classification/sittings');
    $decisionsDirectory = realpath($caseRoot . '/classification/decisions');
    if ($studyDirectory !== $caseRoot . "/bank-sittings/{$caseYear}"
        || $sittingsDirectory !== $caseRoot . '/classification/sittings'
        || $decisionsDirectory !== $caseRoot . '/classification/decisions'
        || $reports !== $caseRoot . '/classification/reports') {
        throw new RuntimeException('Protected recovery subdirectories are missing or redirected.');
    }
    $readPrivate = static function (string $path, string $parent): array {
        if (realpath(dirname($path)) !== $parent || is_link($path) || !is_file($path)) {
            throw new RuntimeException('Expected private input is absent or outside its protected case.');
        }
        return json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    };
    $studyPath = $caseRoot . "/bank-sittings/{$caseYear}/{$stem}-study.json";
    $validatedPath = $caseRoot . "/classification/sittings/{$caseYear}-{$stem}-validated.json";
    $decisionsPath = $caseRoot . "/classification/decisions/{$caseYear}-{$plan['subject']}.json";
    $study = $readPrivate($studyPath, $studyDirectory);
    $validated = $readPrivate($validatedPath, $sittingsDirectory);
    $decisions = $readPrivate($decisionsPath, $decisionsDirectory);
    if (($study['format'] ?? null) !== 'fanoos.classification.study-only/1'
        || ($validated['format'] ?? null) !== ($study['format'] ?? null)
        || ($study['exam_type'] ?? null) !== 'residency'
        || ($study['year'] ?? null) !== $caseYear || ($validated['year'] ?? null) !== $caseYear
        || ($study['round'] ?? null) !== 1 || ($validated['round'] ?? null) !== 1
        || !is_array($study['questions'] ?? null) || !is_array($validated['questions'] ?? null)
        || !is_array($decisions) || !array_is_list($decisions)) {
        throw new RuntimeException('Study, validated sitting or decision format is not eligible.');
    }

    $auditPath = (string) ($plan['audit_path'] ?? '');
    $auditSha = strtolower((string) ($plan['audit_sha256'] ?? ''));
    if (realpath(dirname($auditPath)) !== $reports || is_link($auditPath) || !is_file($auditPath)
        || !preg_match('/^[a-f0-9]{64}$/D', $auditSha)
        || !hash_equals($auditSha, hash_file('sha256', $auditPath))) {
        throw new RuntimeException('Independent private audit is missing or changed.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 64, JSON_THROW_ON_ERROR);
    $batchSpec = "{$caseYear}:{$plan['subject']}:{$stem}";
    $batches = array_values(array_filter(
        $audit['batches'] ?? [],
        static fn (mixed $batch): bool => is_array($batch) && ($batch['batch'] ?? null) === $batchSpec,
    ));
    if (($audit['format'] ?? null) !== 'fanoos.classification.provenance-audit/1'
        || ($audit['research_only'] ?? null) !== true || count($batches) !== 1
        || ($batches[0]['question_content_identical'] ?? null) !== true
        || ($batches[0]['study_sha256'] ?? null) !== hash_file('sha256', $studyPath)
        || ($batches[0]['validated_sha256'] ?? null) !== hash_file('sha256', $validatedPath)
        || ($batches[0]['decisions_sha256'] ?? null) !== hash_file('sha256', $decisionsPath)) {
        throw new RuntimeException('Research-only original-PDF audit does not match the exact protected batch.');
    }

    $original = [];
    foreach ($study['questions'] as $question) {
        $number = $question['number'] ?? null;
        if (!is_int($number) || isset($original[$number])
            || ($question['subject'] ?? null) !== $plan['subject'] || isset($question['sources'])) {
            throw new RuntimeException('Original study contains an invalid, duplicated or already sourced question.');
        }
        $original[$number] = $question;
    }
    $validatedByNumber = [];
    foreach ($validated['questions'] as $question) {
        $number = $question['number'] ?? null;
        if (!is_int($number) || isset($validatedByNumber[$number]) || !isset($original[$number])) {
            throw new RuntimeException('Validated sitting question identity differs from original study.');
        }
        $validatedByNumber[$number] = $question;
        $withoutSources = $question;
        $sources = $withoutSources['sources'] ?? [];
        unset($withoutSources['sources']);
        if ($withoutSources !== $original[$number]) {
            throw new RuntimeException("Q{$number}: study stem/options/answer changed.");
        }
    }
    if (count($validatedByNumber) !== count($original)) {
        throw new RuntimeException('Validated sitting is not the complete original research batch.');
    }

    $decisionByNumber = [];
    foreach ($decisions as $decision) {
        $number = $decision['number'] ?? null;
        if (!is_int($number) || isset($decisionByNumber[$number]) || !isset($original[$number])) {
            throw new RuntimeException('Decision set has duplicate or foreign questions.');
        }
        $decisionByNumber[$number] = $decision;
        $sourceRows = $validatedByNumber[$number]['sources'] ?? [];
        if (!is_array($sourceRows) || count($sourceRows) !== 1) {
            throw new RuntimeException("Q{$number}: accepted decision is not represented by exactly one validated source.");
        }
        $source = $sourceRows[0];
        $edition = (string) ($decision['edition'] ?? '');
        $chapter = (string) ($decision['chapter'] ?? '');
        $chapterKey = 'ch' . str_pad($chapter, 2, '0', STR_PAD_LEFT);
        if (($source['ref'] ?? null) !== $edition . '#' . $chapterKey
            || ($source['origin'] ?? null) !== 'ai' || ($source['primary'] ?? null) !== true
            || ($source['anchor'] ?? null) !== ($decision['evidence'] ?? null)
            || (int) ($source['pdf_page'] ?? 0) !== (int) ($decision['page'] ?? 0)
            || !is_string($source['pdf_sha256'] ?? null)
            || abs((float) ($source['confidence']['source'] ?? 0) - (float) ($decision['confidence'] ?? 0)) > 0.0005
            || abs((float) ($source['confidence']['node'] ?? 0) - (float) ($decision['confidence'] ?? 0)) > 0.0005
            || abs((float) ($source['confidence']['page'] ?? 0) - (float) ($decision['confidence'] ?? 0)) > 0.0005) {
            throw new RuntimeException("Q{$number}: validated source does not match its exact decision.");
        }
    }
    if (count($decisionByNumber) !== (int) ($batches[0]['accepted'] ?? -1)
        || (int) ($batches[0]['questions'] ?? -1) !== count($original)
        || (int) ($batches[0]['pending'] ?? -1) !== count($original) - count($decisionByNumber)) {
        throw new RuntimeException('Independent audit accepted/pending counts differ from the exact private batch.');
    }

    $maps = json_decode((string) file_get_contents($root . '/data/bank/reference-chapter-pages.json'),
        true, 48, JSON_THROW_ON_ERROR);
    $db = null;
    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $questionQuery = $db->prepare(<<<'SQL'
SELECT q.id,q.stem,q.status question_status,q.number_in_sitting,s.id sitting_id,s.exam_year,s.question_count,
       sb.id subject_id,sb.subject_key,t.id exam_type_id,t.type_key
FROM bank_questions q
JOIN bank_exam_sittings s ON s.id=q.sitting_id AND s.workspace_id=q.workspace_id
JOIN bank_exam_types t ON t.id=s.exam_type_id AND t.workspace_id=s.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
WHERE q.workspace_id=:ws AND q.number_in_sitting=:number AND s.exam_year=:year
  AND s.exam_round=1 AND t.type_key='residency' AND sb.subject_key=:subject
FOR UPDATE
SQL);
    $choiceQuery = $db->prepare('SELECT position,text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position FOR UPDATE');
    $answerQuery = $db->prepare('SELECT choice_position,also_correct_positions,status FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1 FOR UPDATE');
    $existingQuery = $db->prepare('SELECT id FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid FOR UPDATE');
    $nodeQuery = $db->prepare(<<<'SQL'
SELECT e.id edition_id,n.id node_id,n.number chapter_no,n.kind,v.scope_chapters
FROM bank_references r
JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
JOIN bank_exam_types t ON t.id=v.exam_type_id AND t.workspace_id=v.workspace_id
WHERE r.workspace_id=:ws AND r.reference_key=:reference AND e.edition_key=:edition
  AND n.number=:chapter AND n.kind='chapter' AND v.exam_year=:year
  AND sb.subject_key=:subject AND t.type_key='residency' AND v.is_official=1
SQL);
    $insert = $db->prepare(<<<'SQL'
INSERT INTO bank_question_sources
(id,workspace_id,question_id,edition_id,node_id,page,pdf_page,printed_page,pdf_sha256,anchor_text,
 is_primary,confidence_source,confidence_node,confidence_page,origin,official_scope_status,official_scope_note,created_at)
VALUES (:id,:ws,:qid,:edition,:node,:page,:pdf_page,:printed_page,:pdf_sha256,:anchor,
 1,:confidence_source,:confidence_node,:confidence_page,'ai','outside_scope_exam_question',:scope_note,UTC_TIMESTAMP(6))
SQL);

    $backup = null;
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
        if ($created === false || abs(time() - $created) > 14400
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Apply requires a fresh verified full backup with database.sql.');
        }
    }

    $batchAudit = $batches[0];
    $db->beginTransaction();
    $sources = [];
    $seen = [];
    try {
        foreach ($plan['entries'] as $item) {
            foreach ([
                'number','edition','chapter','pdf_page','printed_page','printed_page_label_unavailable',
                'pdf_sha256','evidence','confidence','note','answer_alignment',
                'expected_stem_sha256','expected_choices_sha256','expected_answer_sha256',
            ] as $field) {
                if (!array_key_exists($field, $item)) {
                    throw new RuntimeException('Missing protected outside-scope field: ' . $field);
                }
            }
            $number = $item['number'];
            $edition = $item['edition'];
            $chapter = $item['chapter'];
            $page = $item['pdf_page'];
            $printed = $item['printed_page'];
            $sha = strtolower((string) $item['pdf_sha256']);
            $confidence = $item['confidence'];
            $note = $item['note'];
            $evidence = $item['evidence'];
            $alignment = $item['answer_alignment'];
            if (!is_int($number) || isset($seen[$number]) || !isset($original[$number])
                || isset($decisionByNumber[$number]) === false
                || !is_string($edition) || !preg_match('/^([a-z0-9-]+)@([a-z0-9]+)$/D', $edition, $editionMatch)
                || !is_string($chapter) || !ctype_digit($chapter)
                || !is_int($page) || $page < 1
                || !is_bool($item['printed_page_label_unavailable'])
                || ($printed !== null && (!is_string($printed) || !preg_match('/^[1-9][0-9]{0,3}$/D', $printed)))
                || !preg_match('/^[a-f0-9]{64}$/D', $sha)
                || !is_numeric($confidence) || (float) $confidence < 0.85 || (float) $confidence > 1.0
                || !VerifiedAiOutsideScopeSourceInsertGuard::hasExplicitOutsideScopeNote($note)
                || mb_strlen($note) > 600
                || !is_string($alignment) || mb_strlen(trim($alignment)) < 12
                || !is_string($item['expected_stem_sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $item['expected_stem_sha256'])
                || !is_string($item['expected_choices_sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $item['expected_choices_sha256'])
                || !is_string($item['expected_answer_sha256']) || !preg_match('/^[a-f0-9]{64}$/D', $item['expected_answer_sha256'])
                || !is_array($evidence) || !array_is_list($evidence) || count($evidence) < 1 || count($evidence) > 4
                || array_filter($evidence, static fn (mixed $quote): bool => !is_string($quote)
                    || mb_strlen(trim($quote)) < 6 || strlen($quote) > 190 || count(preg_split('/\s+/u', trim($quote)) ?: []) < 4) !== []) {
                throw new RuntimeException('Invalid outside-scope source entry.');
            }
            $seen[$number] = true;
            $decision = $decisionByNumber[$number];
            $joinedEvidence = implode(' ... ', $evidence);
            if (mb_strlen($joinedEvidence) > 600) {
                throw new RuntimeException("Q{$number}: combined evidence exceeds the stored source-anchor limit.");
            }
            if (($decision['edition'] ?? null) !== $edition
                || (string) ($decision['chapter'] ?? '') !== $chapter
                || ($decision['page'] ?? null) !== $page
                || abs((float) ($decision['confidence'] ?? 0) - (float) $confidence) > 0.0005
                || ($decision['evidence'] ?? null) !== $joinedEvidence
                || ($decision['outside_scope_note'] ?? null) !== $note) {
                throw new RuntimeException("Q{$number}: plan differs from the audited outside-scope decision.");
            }
            $sourceRow = ($validatedByNumber[$number]['sources'] ?? [])[0] ?? null;
            $expectedPageLabel = $printed === null ? 'pdf ' . $page : $printed;
            if (!is_array($sourceRow)
                || ($sourceRow['official_scope_status'] ?? null) !== 'outside_scope_exam_question'
                || ($sourceRow['official_scope_note'] ?? null) !== $note
                || ($sourceRow['page'] ?? null) !== $expectedPageLabel
                || ($sourceRow['printed_page'] ?? null) !== $printed
                || ($sourceRow['pdf_sha256'] ?? null) !== $sha
                || (int) ($sourceRow['pdf_page'] ?? 0) !== $page
                || ($sourceRow['anchor'] ?? null) !== $joinedEvidence) {
                throw new RuntimeException("Q{$number}: validated source does not carry the same explicit scope disposition.");
            }
            if (!VerifiedAiOutsideScopeSourceInsertGuard::auditMatches($audit, $batchSpec, $item)) {
                throw new RuntimeException("Q{$number}: accepted PDF provenance audit differs from plan.");
            }

            $questionQuery->execute(['ws'=>$workspace,'number'=>$number,'year'=>$caseYear,'subject'=>$plan['subject']]);
            $questions = $questionQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($questions) !== 1) {
                throw new RuntimeException("Q{$number}: live question identity is not unique.");
            }
            $question = $questions[0];
            if ($question['question_status'] !== 'published'
                || hash('sha256', (string) $question['stem']) !== $item['expected_stem_sha256']) {
                throw new RuntimeException("Q{$number}: live published question differs from protected plan.");
            }
            $params = ['ws'=>$workspace,'qid'=>$question['id']];
            $choiceQuery->execute($params);
            $choiceRows = $choiceQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($choiceRows) !== 4
                || hash('sha256', json_encode($choiceRows, JSON_THROW_ON_ERROR)) !== $item['expected_choices_sha256']) {
                throw new RuntimeException("Q{$number}: live choices differ from protected plan.");
            }
            $answerQuery->execute($params);
            $answer = $answerQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($answer) || !in_array($answer['status'], ['final','amended'], true)
                || $answer['choice_position'] === null
                || hash('sha256', json_encode($answer, JSON_THROW_ON_ERROR)) !== $item['expected_answer_sha256']) {
                throw new RuntimeException("Q{$number}: live official key differs from protected plan.");
            }
            $originalQuestion = $original[$number];
            $realChoices = array_map(
                static fn (array $choice): mixed => $choice['image'] === null
                    ? (string) $choice['text'] : ['text'=>(string) $choice['text'],'image'=>(string) $choice['image']],
                $choiceRows,
            );
            $actualAnswer = [
                'choice'=>$answer['choice_position'] === null ? null : (int) $answer['choice_position'],
                'also_correct'=>$answer['also_correct_positions']
                    ? json_decode((string) $answer['also_correct_positions'], true, 32, JSON_THROW_ON_ERROR) : [],
                'status'=>(string) $answer['status'],
            ];
            if ((string) $originalQuestion['stem'] !== (string) $question['stem']
                || $realChoices !== $originalQuestion['choices'] || $actualAnswer !== $originalQuestion['answer']) {
                throw new RuntimeException("Q{$number}: live immutable content no longer matches original study.");
            }
            $existingQuery->execute($params);
            if ($existingQuery->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException("Q{$number}: source is no longer source-free; refusing insert.");
            }

            $nodeQuery->execute([
                'ws'=>$workspace,'reference'=>$editionMatch[1],'edition'=>$editionMatch[2],
                'chapter'=>$chapter,'year'=>$caseYear,'subject'=>$plan['subject'],
            ]);
            $nodes = $nodeQuery->fetchAll(PDO::FETCH_ASSOC);
            if (count($nodes) !== 1 || $nodes[0]['kind'] !== 'chapter'
                || (string) $nodes[0]['chapter_no'] !== $chapter) {
                throw new RuntimeException("Q{$number}: exact official edition/chapter validity is not unique.");
            }
            $scope = json_decode((string) $nodes[0]['scope_chapters'], true, 32, JSON_THROW_ON_ERROR);
            if (!VerifiedAiOutsideScopeSourceInsertGuard::chapterIsEntirelyOutsideScope($scope, $chapter)) {
                throw new RuntimeException("Q{$number}: chapter is not explicitly outside the official year/subject scope.");
            }

            $map = $maps['editions'][$edition] ?? null;
            $insideChapter = false;
            foreach (($map['runs'] ?? []) as $run) {
                if (is_array($run) && count($run) === 3 && (string) $run[0] === $chapter
                    && $page >= $run[1] && $page <= $run[2]) {
                    $insideChapter = true;
                    break;
                }
            }
            if (!is_array($map) || ($map['source_pdf_sha256'] ?? null) !== $sha || !$insideChapter) {
                throw new RuntimeException("Q{$number}: page is outside the exact edition chapter map/PDF hash.");
            }

            $labelUnavailable = $item['printed_page_label_unavailable'] === true;
            if ($labelUnavailable) {
                if ($printed !== null || !str_contains($note, 'شماره صفحه چاپی در PDF ثبت نشده')
                    || !PrintedBookPageEvidence::hasNoPrintedPageLabel($edition, $page, $sha)) {
                    throw new RuntimeException("Q{$number}: missing printed-page label is not independently proven.");
                }
                $sourcePage = 'pdf ' . $page;
            } else {
                if (!is_string($printed) || !preg_match('/^[1-9][0-9]{0,3}$/D', $printed)
                    || !PrintedBookPageEvidence::corroborates($edition, $page, $printed, $sha)) {
                    throw new RuntimeException("Q{$number}: printed page label/neighbor sequence is not verified.");
                }
                $sourcePage = $printed;
            }
            if (!PrintedBookPageEvidence::pageContainsEvidence($edition, $page, $evidence, $sha)) {
                throw new RuntimeException("Q{$number}: exact original PDF page evidence failed.");
            }

            $sourceId = Uuid::v7();
            $insert->execute([
                'id'=>$sourceId,'ws'=>$workspace,'qid'=>$question['id'],'edition'=>$nodes[0]['edition_id'],
                'node'=>$nodes[0]['node_id'],'page'=>$sourcePage,'pdf_page'=>$page,'printed_page'=>$printed,
                'pdf_sha256'=>$sha,'anchor'=>$joinedEvidence,
                'confidence_source'=>number_format((float) $confidence,3,'.',''),
                'confidence_node'=>number_format((float) $confidence,3,'.',''),
                'confidence_page'=>number_format((float) $confidence,3,'.',''),
                'scope_note'=>$note,
            ]);
            if ($insert->rowCount() !== 1) {
                throw new RuntimeException("Q{$number}: source-only insert was ambiguous.");
            }
            $sources[] = [
                'number'=>$number,'source_id'=>$sourceId,'edition'=>$edition,'chapter'=>$chapter,
                'page'=>$sourcePage,'printed_page'=>$printed,'printed_page_label_unavailable'=>$labelUnavailable,
                'pdf_page'=>$page,'pdf_sha256'=>$sha,'confidence'=>number_format((float) $confidence,3,'.',''),
                'official_scope_status'=>'outside_scope_exam_question','official_scope_note'=>$note,
                'answer_alignment'=>$alignment,
            ];
        }
        if (count($sources) !== count($plan['entries'])) {
            throw new RuntimeException('Outside-scope insert count mismatch.');
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
        'format'=>'fanoos.verified-ai-outside-scope-source-insert-receipt/1',
        'mode'=>$apply?'applied':'rollback-preview','year'=>$caseYear,'subject'=>$plan['subject'],
        'batch'=>$batchSpec,'count'=>count($sources),'plan_sha256'=>hash('sha256',$rawPlan),
        'audit_sha256'=>$auditSha,'only_rows_changed'=>['bank_question_sources:insert'],
        'questions_changed'=>0,'choices_changed'=>0,'answers_changed'=>0,
        'assessment_versions_changed'=>0,'attempts_changed'=>0,'sources'=>$sources,
    ];
    if ($apply) {
        $receipt['backup_id']=basename((string)$backup);
        $receipt['at_utc']=gmdate('Y-m-d\TH:i:s\Z');
        $handle=@fopen((string)$args['receipt'],'x');
        if ($handle===false) {
            throw new RuntimeException('Committed but receipt creation failed; independently read back before any retry.');
        }
        chmod((string)$args['receipt'],0600);
        fwrite($handle,json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($receipt,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR,'Verified outside-scope source insert refused: '.$error->getMessage().PHP_EOL);
    exit(1);
}
