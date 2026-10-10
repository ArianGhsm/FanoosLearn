<?php
declare(strict_types=1);

use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

/** One-off reviewed eight-row Proffit6e AI-source correction; source-only DML. */
try {
    $args = [];
    foreach (array_slice($argv, 1) as $part) {
        if (!preg_match('/^--(workspace|apply|backup|receipt)(?:=(.*))?$/D', $part, $m)
            || array_key_exists($m[1], $args)) {
            throw new RuntimeException('Unexpected option');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $workspace = $args['workspace'] ?? '';
    if (!preg_match('/^[0-9a-f-]{36}$/D', (string) $workspace)) {
        throw new RuntimeException('Authorized dentistry workspace UUID required');
    }
    $apply = isset($args['apply']);
    if ($apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Apply requires backup and receipt');
    }
    $root = '/srv/fanoos/shared/research';
    $file = $root . '/classification/reports/orthodontics-eight-source-repair-decisions-20261010.json';
    $sourceHash = '29fedfae46b69b5b3d81439d0e929a12464b9ee1cdf41498ec526b272744976d';
    if (!is_file($file) || is_link($file) || hash_file('sha256', $file) !== $sourceHash) {
        throw new RuntimeException('Immutable scientific decisions changed or missing');
    }
    $referenceText = $root . '/references/proffit-orthodontics@6e.txt';
    if (hash_file('sha256', $referenceText) !== 'c2e9b985eb8bf9fb916dbb2ab763ac27bd30bad89748ef76685c362aeec8aa59') {
        throw new RuntimeException('Wrong original edition text');
    }
    $input = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
    if (($input['format'] ?? '') !== 'fanoos.ortho.existing-ai-source-repair.v1'
        || ($input['book'] ?? '') !== 'proffit-orthodontics@6e' || count($input['decisions'] ?? []) !== 8) {
        throw new RuntimeException('Unexpected scientific decision set');
    }
    // Exact permitted old source and question fingerprints; none may be overridden.
    $expected = [
      '1399:3' => ['3','1','e6867351c094d22bd7744aa87c820238d4bf6de7d9ff18859439fb97b844336a','e89fae9205845322657be7bfd4273befdd10f4daa7be66d2d517f42a7a1ead50',3],
      '1399:7' => ['20','pdf 690','3cdbf709e76ae5a5c5533c57719e6e05e7b3e349b6ca4d1c561b5f7ec25eb270','ea5136ea940d16d16ddacd3d50875e761881f448ccba0048ff94f860617cc3de',2],
      '1401:8' => ['10','pdf 326','5a28be22150777c7241a7f082f055ca554dc9420ce770c576ac0141f035fbaac','da2c7ffbbfb7f460e9e314597fc2db6b91cc4399f3a8fa0738c3da9af24a69e3',1],
      '1401:12' => ['3','1995','87620a70a57c1399dc35b9f76ae8097d6ef3904596a65474d538fbabe9370c55','c213797e379a8f39299ad593046cf8f229551d6b6cdb4fa0ed354deccd041884',1],
      '1402:21' => ['1','1989','8186bf4f92b0fcbeb07975f742f7782b916cfc5ec109fe88ab2723bd9696ed9f','20e2e8998f773df25df5b0c9aabd5a18bc0ca05821606c25db11825ec0b6c982',3],
      '1402:23' => ['2','4','6f9a827ffcb6e19a030587601922c40beca3f10311ee375249668983ccb51041','11f6cd505d002831948ae737bee6421559d93a4ebc20a3feb9b746ca1e879657',1],
      '1403:13' => ['10','pdf 326','b0ebc0dbbd54cd9a5b4169ee76fa33b3dfe52a2cc5aa2f7778ad3cbab307c7c8','748da92ebe686fbe5b2ceffcba397ebc536af0d1e843381b3e6f4e221827210b',2],
      '1404:11' => ['9','pdf 310','dbafc77c1e2ee8ad2042da5c210bd635acc4633902f2e0d1ce20fdadb668c3d0','b8a919f0921831adb8291473b72ebd3f2beb952daa8a72acb1223b974cc6922c',4],
    ];
    $seen = [];
    foreach ($input['decisions'] as $d) {
        $k = (string)$d['year'] . ':' . (string)$d['question'];
        if (!isset($expected[$k]) || isset($seen[$k])
            || ($d['approved_source_only'] ?? false) !== true
            || !isset($d['chapter'], $d['pdf_page'], $d['evidence'])
            || !is_int($d['pdf_page']) || $d['pdf_page'] < 12 || $d['pdf_page'] > 719
            || strlen((string) $d['evidence']) < 25) {
            throw new RuntimeException('Unexpected source decision or chapter');
        }
        $seen[$k] = $d;
    }
    if (count($seen) !== 8) {
        throw new RuntimeException('Incomplete immutable review set');
    }
    if ($apply) {
        if ((posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd') {
            throw new RuntimeException('Apply restricted to updater operator');
        }
        $backup = realpath((string) $args['backup']);
        if ($backup === false || !str_starts_with($backup . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Full backup root invalid');
        }
        $manifest = BackupManifest::verify($backup);
        if (strtotime((string)($manifest['created_at'] ?? '')) < time()-14400
            || (int)($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Verified fresh full database backup required');
        }
        $receipt = (string) $args['receipt'];
        if (realpath(dirname($receipt)) !== realpath($root . '/classification/reports')
            || file_exists($receipt) || is_link($receipt)
            || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Unique private receipt required');
        }
    }
    $db = DatabaseConnection::fromEnvironment();
    $get = $db->prepare("SELECT q.id,q.stem,q.status,q.question_key,s.id AS source_id,
        s.page,s.anchor_text,s.origin,n.number AS chapter,e.edition_key,
        a.choice_position,a.status AS answer_status
        FROM bank_question_sources s
        JOIN bank_questions q ON q.id=s.question_id AND q.workspace_id=s.workspace_id
        JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
        JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
        JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=si.workspace_id
        JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
        JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
        JOIN bank_official_answers a ON a.id=(
            SELECT a2.id FROM bank_official_answers a2
            WHERE a2.question_id=q.id AND a2.workspace_id=q.workspace_id
            ORDER BY a2.recorded_at DESC,a2.id DESC LIMIT 1)
        WHERE q.workspace_id=:ws AND si.exam_year=:yr AND si.exam_round=1
        AND et.type_key='residency' AND sb.subject_key='orthodontics'
        AND q.number_in_sitting=:num FOR UPDATE");
    $chapter = $db->prepare("SELECT n.id AS node_id,n.kind,v.scope_chapters FROM bank_references r
        JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
        JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
        JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
        JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
        JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
        WHERE r.workspace_id=:ws AND r.reference_key='proffit-orthodontics' AND e.edition_key='6e'
        AND n.node_key=:node AND n.kind='chapter'
        AND v.exam_year=:yr AND v.is_official=1 AND et.type_key='residency'
        AND sb.subject_key='orthodontics'");
    $modify = $db->prepare("UPDATE bank_question_sources
        SET node_id=:node,page=:page,anchor_text=:anchor
        WHERE id=:id AND workspace_id=:ws AND question_id=:qid AND origin='ai' AND page=:old_page");
    $db->beginTransaction();
    $count = 0;
    try {
        foreach ($seen as $k => $d) {
            [$yr,$no] = array_map('intval',explode(':',$k));
            $get->execute(['ws'=>$workspace,'yr'=>$yr,'num'=>$no]);
            $q = $get->fetch(PDO::FETCH_ASSOC);
            if (!is_array($q) || $get->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new RuntimeException('Question or unique source does not match pinned review');
            }
            [$oldCh,$oldPage,$oldAnchorHash,$stemHash,$keyChoice] = $expected[$k];
            if ($q['question_key'] !== sprintf('residency-%d-1-%03d',$yr,$no)
                || $q['status'] !== 'published'
                || hash('sha256',$q['stem']) !== $stemHash
                || (int)$q['choice_position'] !== $keyChoice
                || $q['answer_status'] !== ($k === '1402:23' ? 'amended' : 'final')
                || $q['origin'] !== 'ai'
                || $q['edition_key'] !== '6e'
                || (string)$q['chapter'] !== $oldCh || $q['page'] !== $oldPage
                || hash('sha256',(string)$q['anchor_text']) !== $oldAnchorHash) {
                throw new RuntimeException('Live source/question/key changed after original review');
            }
            $chapter->execute(['ws'=>$workspace,'yr'=>$yr,'node'=>'ch'.str_pad((string)$d['chapter'],2,'0',STR_PAD_LEFT)]);
            $n=$chapter->fetch(PDO::FETCH_ASSOC);
            if (!is_array($n) || $chapter->fetch(PDO::FETCH_ASSOC)!==false || $n['kind']!=='chapter') {
                throw new RuntimeException('Exact official chapter not uniquely mapped');
            }
            $scope=json_decode($n['scope_chapters'],true,32,JSON_THROW_ON_ERROR);
            if (!in_array((string)$d['chapter'],array_map(static fn($s)=>(string)($s['number']??''),$scope),true)) {
                throw new RuntimeException('Chapter outside specific-year official syllabus');
            }
            $modify->execute(['node'=>$n['node_id'],'page'=>'pdf '.$d['pdf_page'],
                'anchor'=>$d['evidence'],'id'=>$q['source_id'],'ws'=>$workspace,
                'qid'=>$q['id'],'old_page'=>$oldPage]);
            if ($modify->rowCount()!==1) throw new RuntimeException('One source update required');
            ++$count;
        }
        if ($count!==8) throw new RuntimeException('Incomplete batch');
        if ($apply) $db->commit(); else $db->rollBack();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    $result=['format'=>'fanoos.ortho.6e-source-repair-receipt/1','count'=>$count,
        'applied'=>$apply,'stem_option_key_human_changes'=>0,
        'decision_sha256'=>$sourceHash];
    if ($apply) {
        $result['backup_id']=basename($backup);
        $result['at_utc']=gmdate('Y-m-d\TH:i:s\Z');
        $fp=fopen($receipt,'x');
        if (!$fp) throw new RuntimeException('Applied; receipt write failed: do not retry without DB inspection');
        chmod($receipt,0600);
        fwrite($fp,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
        fclose($fp);
    }
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $e) {
    fwrite(STDERR,'Orthodontics existing-source repair refused: '.$e->getMessage()."\n");
    exit(1);
}
