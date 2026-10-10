<?php
declare(strict_types=1);

/**
 * One-time, owner-authorized completion of two already-human-reviewed
 * orthodontics 1404 source page fields, without changing their human decision.
 * No new source, chapter, question, choices, answer or publication mutation.
 */
use Fanoos\Platform\Bank\PrintedBookPageEvidence;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

try {
    $opts = [];
    foreach (array_slice($argv, 1) as $part) {
        if (!preg_match('/^--(workspace|apply|backup|receipt)(?:=(.*))?$/D', $part, $m)
            || array_key_exists($m[1], $opts)) {
            throw new RuntimeException('Unexpected option or duplicate argument.');
        }
        $opts[$m[1]] = $m[2] ?? '1';
    }
    $ws = (string) ($opts['workspace'] ?? '');
    $sourcePdfSha = '5f18cc196553b691635b0c136f9761a4e7c478bf115424d1c7f26f04b5b50015';
    if (!preg_match('/^[0-9a-f-]{36}$/D', $ws)) {
        throw new RuntimeException('Explicit dentistry workspace UUID required.');
    }
    $apply = isset($opts['apply']);
    if ($apply !== isset($opts['backup']) || $apply !== isset($opts['receipt'])) {
        throw new RuntimeException('Apply requires both fresh backup and private receipt.');
    }
    // The exact approved PDF and its hash-bound chapter map are checked at
    // these two page labels; the underlying figure review remains human-led.
    // Q17 uses Fig. 14.35C on PDF491, without adjudicating official-key geometry.
    $review = [
        16 => [
            'chapter' => '13', 'page' => 'pdf 448',
            'stem_sha256' => '691bf0435b4c7c545a67ef7a58f9351defb3d42efa4ec608beee7f7870669ab0',
            'choices_sha256' => 'de1e4da02125509dd8e46da726e15718364ea2eb4406903107eb4c634c7c9ba7',
            'source_id' => '01a11bb7-1cca-7f50-ad27-c758cdc2b2df', 'answer' => 2,
        ],
        17 => [
            'chapter' => '14', 'page' => 'pdf 491',
            'stem_sha256' => 'e6bef7dc4604e309b0699b76ebcd5c48eea060b07bd275f2db0ef063004980a4',
            'choices_sha256' => 'bed3db73d933c2c119a68883a410806f45b6d1f37d5ca63ca7fd045d36a2a367',
            'source_id' => '01a11bb7-1cd1-79b7-aaac-30a45317fc2a', 'answer' => 3,
        ],
    ];
    if (!PrintedBookPageEvidence::corroborates('proffit-orthodontics@6e', 448, '438', $sourcePdfSha)
        || !PrintedBookPageEvidence::corroborates('proffit-orthodontics@6e', 491, '481', $sourcePdfSha)) {
        throw new RuntimeException('Human-reviewed pages do not match the exact approved PDF.');
    }
    $backup = null;
    if ($apply) {
        if ((posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd') {
            throw new RuntimeException('Apply restricted to updater identity.');
        }
        $backup = realpath((string) $opts['backup']);
        if (!$backup || !str_starts_with($backup . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Full backup root mismatch.');
        }
        $manifest = BackupManifest::verify($backup);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || $created < time() - 14400
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh, independently verified database backup required.');
        }
        $receipt = (string) $opts['receipt'];
        if (realpath(dirname($receipt)) !== realpath('/srv/fanoos/shared/research/classification/reports')
            || file_exists($receipt) || is_link($receipt) || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Private unique receipt required.');
        }
    }
    $db = DatabaseConnection::fromEnvironment();
    $query = $db->prepare(<<<'SQL'
SELECT q.id,q.stem,q.question_key,q.status AS question_status,
s.id AS source_id,s.page,s.origin,s.is_primary,s.anchor_text,
n.number AS chapter,e.id AS edition_id,e.edition_key,r.reference_key,
a.choice_position,a.status AS answer_status,a.also_correct_positions
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects b ON b.id=q.subject_id AND b.workspace_id=q.workspace_id
JOIN bank_question_sources s ON s.question_id=q.id AND s.workspace_id=q.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_official_answers a ON a.id=(
 SELECT aa.id FROM bank_official_answers aa
 WHERE aa.workspace_id=q.workspace_id AND aa.question_id=q.id
 ORDER BY aa.recorded_at DESC,aa.id DESC LIMIT 1)
WHERE q.workspace_id=:ws AND si.exam_year=1404 AND si.exam_round=1
 AND et.type_key='residency' AND b.subject_key='orthodontics'
 AND q.number_in_sitting=:num FOR UPDATE
SQL);
    $choices = $db->prepare(<<<'SQL'
SELECT COUNT(*) AS n,
SHA2(GROUP_CONCAT(CONCAT(position,':',text) ORDER BY position SEPARATOR '|'),256) AS digest,
SUM(image IS NOT NULL) AS images FROM bank_question_choices
WHERE workspace_id=:ws AND question_id=:qid
SQL);
    $validity = $db->prepare(<<<'SQL'
SELECT v.scope_chapters FROM bank_reference_validity v
JOIN bank_subjects b ON b.id=v.subject_id AND b.workspace_id=v.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
WHERE v.workspace_id=:ws AND v.edition_id=:edition AND v.exam_year=1404
 AND v.is_official=1 AND b.subject_key='orthodontics' AND et.type_key='residency'
SQL);
    $setPage = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET page=:page
WHERE id=:id AND workspace_id=:ws AND question_id=:qid
 AND origin='human' AND is_primary=1 AND page IS NULL
SQL);
    $db->beginTransaction();
    try {
        $changed = 0;
        foreach ($review as $num => $d) {
            $query->execute(['ws'=>$ws, 'num'=>$num]);
            $q = $query->fetch(PDO::FETCH_ASSOC);
            if (!is_array($q) || $query->fetch(PDO::FETCH_ASSOC) !== false
                || $q['source_id'] !== $d['source_id']
                || $q['question_key'] !== sprintf('residency-1404-1-%03d', $num)
                || $q['question_status'] !== 'published'
                || hash('sha256',(string)$q['stem']) !== $d['stem_sha256']
                || $q['origin'] !== 'human' || (int)$q['is_primary'] !== 1
                || $q['page'] !== null || $q['anchor_text'] !== null
                || $q['reference_key'] !== 'proffit-orthodontics'
                || $q['edition_key'] !== '6e'
                || (string)$q['chapter'] !== $d['chapter']
                || (int)$q['choice_position'] !== $d['answer']
                || $q['answer_status'] !== 'final'
                || !in_array($q['also_correct_positions'], [null,'[]'], true)) {
                throw new RuntimeException("Immutable human-source/question/answer preflight failed: $num");
            }
            $choices->execute(['ws'=>$ws,'qid'=>$q['id']]);
            $c = $choices->fetch(PDO::FETCH_ASSOC);
            if (!is_array($c) || (int)$c['n'] !== 4
                || $c['digest'] !== $d['choices_sha256']
                || (int)$c['images'] !== 0) {
                throw new RuntimeException("Protected question options changed: $num");
            }
            $validity->execute(['ws'=>$ws,'edition'=>$q['edition_id'] ?? '']);
            $v = $validity->fetch(PDO::FETCH_ASSOC);
            if (!is_array($v) || $validity->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException('Exact official reference validity missing.');
            }
            $scope = json_decode((string)$v['scope_chapters'],true,32,JSON_THROW_ON_ERROR);
            if (!in_array($d['chapter'], array_map(
                static fn(array $entry):string => (string)($entry['number']??''),$scope
            ), true)) {
                throw new RuntimeException("Chapter outside official year scope: $num");
            }
            $setPage->execute(['page'=>$d['page'],'id'=>$q['source_id'],'ws'=>$ws,'qid'=>$q['id']]);
            if ($setPage->rowCount() !== 1) {
                throw new RuntimeException("Expected single human page-only update: $num");
            }
            ++$changed;
        }
        if ($changed !== 2) throw new RuntimeException('Expected exactly two source pages.');
        if ($apply) $db->commit(); else $db->rollBack();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
    $result = [
        'format'=>'fanoos.orthodontics.1404-owner-approved-human-page-only/1',
        'applied'=>$apply,'pages_set'=>$changed,
        'question_choice_answer_human_decision_changes'=>0,
        'preserved_origin'=>'human',
        'Q16'=>['chapter'=>13,'pdf_page'=>448,'printed_page'=>438],
        'Q17'=>['chapter'=>14,'pdf_page'=>491,'printed_page'=>481,
            'key_geometry_review'=>'key preserved; detailed facebow configuration not independently established'],
        'source_pdf_sha256'=>$sourcePdfSha,
    ];
    if ($apply) {
        $result['backup_id']=basename($backup);
        $result['applied_at_utc']=gmdate('Y-m-d\TH:i:s\Z');
        $fp=fopen($receipt,'x');
        if ($fp === false) throw new RuntimeException('Applied; receipt write failure, inspect DB before retry.');
        chmod($receipt,0600);
        fwrite($fp,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
        fclose($fp);
    }
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    fwrite(STDERR,'Human source page-only finalization refused: '.$e->getMessage()."\n");
    exit(1);
}
