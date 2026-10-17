<?php

declare(strict_types=1);

/**
 * Build a private, read-only compare-and-swap plan from an existing independent
 * 1403 PDF audit and its decision file. This command never writes to MySQL.
 * The guarded repair CLI rechecks every condition during preview and apply.
 *
 * php scripts/references/prepare_residency_1403_ai_source_correction_plan.php \
 *   --subject=pediatric-dentistry --audit=<private audit.json> \
 *   --decisions=<private decisions.json> --numbers=186,188 \
 *   --output=/srv/fanoos/shared/research/classification/reports/PLAN.json
 */

use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Bank\Residency1403AiSourceCitationCorrectionGuard;
use Fanoos\Platform\Bank\VerifiedHumanChapterPageRepairGuard;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

const FANOOS_1403_PLAN_REPORTS = '/srv/fanoos/shared/research/classification/reports';
const FANOOS_1403_PLAN_TMP = '/srv/fanoos/shared/research/tmp';
const FANOOS_1403_PLAN_MAP = __DIR__ . '/../../data/bank/reference-chapter-pages.json';

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI-only plan preparation.');
    }
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--(subject|audit|decisions|numbers|output)=(.*)$/D', $argument, $match) !== 1
            || isset($args[$match[1]])) {
            throw new RuntimeException('Unknown or duplicate plan-preparation argument.');
        }
        $args[$match[1]] = $match[2];
    }
    foreach (['subject', 'audit', 'decisions', 'numbers', 'output'] as $required) {
        if (!isset($args[$required]) || $args[$required] === '') {
            throw new RuntimeException("Missing --{$required}.");
        }
    }
    $subject = (string) $args['subject'];
    if (!preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $subject)) {
        throw new RuntimeException('Invalid official subject key.');
    }
    $numbers = array_map('intval', explode(',', (string) $args['numbers']));
    if (count($numbers) < 1 || count($numbers) > 12
        || count(array_unique($numbers)) !== count($numbers)
        || array_filter($numbers, static fn (int $number): bool => $number < 1 || $number > 250) !== []) {
        throw new RuntimeException('Supply one to twelve unique 1403 question numbers.');
    }

    $reports = realpath(FANOOS_1403_PLAN_REPORTS);
    $output = (string) $args['output'];
    if ($reports === false || realpath(FANOOS_1403_PLAN_REPORTS) !== $reports
        || (fileperms($reports) & 0077) !== 0 || realpath(dirname($output)) !== $reports
        || is_link($output) || file_exists($output) || !str_ends_with($output, '.json')) {
        throw new RuntimeException('Output must be a new JSON file in the private reports directory.');
    }
    $auditPath = (string) $args['audit'];
    $decisionsPath = (string) $args['decisions'];
    foreach ([$auditPath, $decisionsPath] as $privatePath) {
        if (!str_starts_with($privatePath, FANOOS_1403_PLAN_TMP . '/1403-')
            || !Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
                $privatePath,
                FANOOS_1403_PLAN_TMP,
                false,
            )) {
            throw new RuntimeException('Audit and decisions must be existing private 1403 research files.');
        }
    }
    $auditSha = hash_file('sha256', $auditPath);
    $decisionsSha = hash_file('sha256', $decisionsPath);
    if (!is_string($auditSha) || !is_string($decisionsSha)) {
        throw new RuntimeException('Could not hash the protected independent evidence files.');
    }
    $audit = json_decode((string) file_get_contents($auditPath), true, 128, JSON_THROW_ON_ERROR);
    $decisions = json_decode((string) file_get_contents($decisionsPath), true, 128, JSON_THROW_ON_ERROR);
    if (!is_array($decisions) || !array_is_list($decisions)) {
        throw new RuntimeException('Protected decisions must be a JSON list.');
    }
    $batch = $audit['batches'][0] ?? null;
    if (!is_array($batch) || ($batch['subject'] ?? null) !== $subject) {
        throw new RuntimeException('Independent audit subject does not match the requested subject.');
    }

    $researchPath = Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_PATH;
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        $researchPath,
        FANOOS_1403_PLAN_TMP,
        false,
    ) || hash_file('sha256', $researchPath)
        !== Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_SHA256) {
        throw new RuntimeException('Pinned 1403 research-only manifest is missing or changed.');
    }
    $researchManifest = json_decode((string) file_get_contents($researchPath), true, 128, JSON_THROW_ON_ERROR);
    if (!Residency1403AiSourceCitationCorrectionGuard::researchManifestMatches($researchManifest)) {
        throw new RuntimeException('Pinned 1403 research-only manifest failed its consistency checks.');
    }
    $canonicalPath = '/srv/fanoos/shared/research/bank-sittings/1403/canonical-export-20261011/residency-1403-1.json';
    if (!Residency1403AiSourceCitationCorrectionGuard::isPrivateFile(
        $canonicalPath,
        '/srv/fanoos/shared/research/bank-sittings/1403',
        false,
    ) || hash_file('sha256', $canonicalPath)
        !== Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256) {
        throw new RuntimeException('Original 1403 canonical sitting is missing or changed.');
    }
    $mapPath = realpath(FANOOS_1403_PLAN_MAP);
    if ($mapPath === false || !is_file($mapPath) || is_link(FANOOS_1403_PLAN_MAP)) {
        throw new RuntimeException('Versioned original-PDF chapter map is unavailable.');
    }
    $maps = json_decode((string) file_get_contents($mapPath), true, 128, JSON_THROW_ON_ERROR);

    RuntimeConfig::load();
    $db = DatabaseConnection::fromEnvironment();
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
        $sourceQuery = $db->prepare(<<<'SQL'
SELECT s.id source_id,s.workspace_id,s.question_id,s.edition_id,s.node_id,s.page,s.printed_page,
       s.pdf_page,s.pdf_sha256,s.anchor_text,s.table_ref,s.figure_ref,s.box_ref,s.origin,s.is_primary,
       s.reviewed_at,s.reviewed_by_user_id,q.number_in_sitting,q.status question_status,
       sb.subject_key,r.reference_key,e.edition_key,n.number old_chapter,et.type_key,si.exam_year,si.exam_round
FROM bank_question_sources s
JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE q.number_in_sitting=:number AND sb.subject_key=:subject AND et.type_key='residency'
 AND si.exam_year=1403 AND si.exam_round=1
SQL);
        $sourceCount = $db->prepare('SELECT COUNT(*) FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid');
        $validity = $db->prepare(<<<'SQL'
SELECT e.id edition_id,n.id node_id,n.kind,v.scope_chapters,v.is_official
FROM bank_reference_editions e
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
WHERE e.workspace_id=:ws AND r.reference_key=:ref AND e.edition_key=:edition
 AND n.node_key=:node AND et.type_key='residency' AND v.exam_year=1403
 AND sb.subject_key=:subject AND v.is_official=1
SQL);
        $entries = [];
        foreach ($numbers as $number) {
            $matches = array_values(array_filter($decisions, static fn (mixed $decision): bool =>
                is_array($decision) && ($decision['number'] ?? null) === $number));
            if (count($matches) !== 1) {
                throw new RuntimeException("Q{$number}: expected exactly one independent decision.");
            }
            $decision = $matches[0];
            $reference = (string) ($decision['edition'] ?? '');
            $chapter = (string) ($decision['chapter'] ?? '');
            $page = $decision['page'] ?? null;
            $evidence = $decision['evidence'] ?? null;
            $pdfSha = $batch['source_pdf_provenance'][$reference]['sha256'] ?? null;
            $item = [
                'number'=>$number,'subject'=>$subject,'new_ref'=>$reference,'new_ch'=>$chapter,
                'pdf_page'=>$page,'pdf_sha'=>$pdfSha,'evidence'=>$evidence,'decisions_sha256'=>$decisionsSha,
            ];
            if (!Residency1403AiSourceCitationCorrectionGuard::independentAuditMatches($item,$audit,$decisions)
                || !is_string($pdfSha) || !preg_match('/^[a-f0-9]{64}$/D',$pdfSha)
                || !is_string($evidence)
                || !VerifiedHumanChapterPageRepairGuard::pageInMap($maps,$reference,$chapter,$page,$pdfSha)
                || !PrintedBookPageEvidence::pageContainsEvidence($reference,$page,[$evidence],$pdfSha)) {
                throw new RuntimeException("Q{$number}: exact original-PDF audit, map or evidence validation failed.");
            }
            $nodeKey='ch'.str_pad($chapter,2,'0',STR_PAD_LEFT);
            $sourceQuery->execute(['number'=>$number,'subject'=>$subject]);
            $sources=$sourceQuery->fetchAll(PDO::FETCH_ASSOC);
            if(count($sources)!==1)throw new RuntimeException("Q{$number}: live published source row is not unique.");
            $source=$sources[0];
            if($source['question_status']!=='published'||$source['origin']!=='ai'||(int)$source['is_primary']!==1
                ||$source['reviewed_at']!==null||$source['reviewed_by_user_id']!==null
                ||$source['pdf_page']!==null||$source['pdf_sha256']!==null
                ||$source['table_ref']!==null||$source['figure_ref']!==null||$source['box_ref']!==null)
                throw new RuntimeException("Q{$number}: source is reviewed, already bound, or has a non-page annotation.");
            $sourceCount->execute(['ws'=>$source['workspace_id'],'qid'=>$source['question_id']]);
            if((int)$sourceCount->fetchColumn()!==1)throw new RuntimeException("Q{$number}: source bundle requires a separate reviewed workflow.");
            $validity->execute(['ws'=>$source['workspace_id'],'ref'=>strtok($reference,'@'),
                'edition'=>substr(strstr($reference,'@'),1),'node'=>$nodeKey,'subject'=>$subject]);
            $validRows=$validity->fetchAll(PDO::FETCH_ASSOC);
            if(count($validRows)!==1||$validRows[0]['kind']!=='chapter'||(int)$validRows[0]['is_official']!==1)
                throw new RuntimeException("Q{$number}: target is not one official same-year chapter.");
            $scope=json_decode((string)$validRows[0]['scope_chapters'],true,64,JSON_THROW_ON_ERROR);
            $inScope=false;
            foreach(is_array($scope)?$scope:[] as $allowed){
                if((string)($allowed['number']??'')===$chapter&&empty($allowed['partial'])){$inScope=true;break;}
            }
            if(!$inScope)throw new RuntimeException("Q{$number}: target chapter is excluded or partial in the 1403 syllabus.");
            $entries[]=$item+[
                'source_id'=>$source['source_id'],'old_ref'=>$source['reference_key'].'@'.$source['edition_key'],
                'old_ch'=>(string)$source['old_chapter'],'old_page'=>$source['page'],
                'old_printed'=>$source['printed_page'],'old_anchor_sha256'=>hash('sha256',(string)$source['anchor_text']),
                'audit_path'=>$auditPath,'audit_sha256'=>$auditSha,'decisions_path'=>$decisionsPath,
            ];
        }
        $db->rollBack();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }

    $plan=[
        'format'=>'fanoos.residency1403.ai-source-correction/1','exam_year'=>1403,
        'subject'=>$subject,'canonical_sha256'=>Residency1403AiSourceCitationCorrectionGuard::CANONICAL_SHA256,
        'research_sha256'=>Residency1403AiSourceCitationCorrectionGuard::RESEARCH_MANIFEST_SHA256,
        'audit_path'=>$auditPath,'audit_sha256'=>$auditSha,'decisions_path'=>$decisionsPath,
        'decisions_sha256'=>$decisionsSha,'entries'=>$entries,
    ];
    $json=json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
    $temp=tempnam($reports,'.residency-1403-plan-');
    if($temp===false)throw new RuntimeException('Cannot allocate protected plan staging file.');
    try{
        if(file_put_contents($temp,$json,LOCK_EX)!==strlen($json))throw new RuntimeException('Could not write complete plan.');
        chmod($temp,0600);
        if(!link($temp,$output))throw new RuntimeException('Could not atomically install unique plan.');
        if(!unlink($temp))throw new RuntimeException('Could not remove protected plan staging link.');
    }finally{if(is_file($temp))unlink($temp);}
    echo json_encode(['mode'=>'read-only-plan-created','path'=>$output,'sha256'=>hash('sha256',$json),
        'count'=>count($entries),'numbers'=>$numbers,'database_writes'=>0,'assessment_writes'=>0],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error){
    fwrite(STDERR,'1403 source-correction plan refused: '.$error->getMessage().PHP_EOL);
    exit(1);
}
