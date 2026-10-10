<?php
declare(strict_types=1);

use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

/**
 * Orthodontics 1398 legacy nearest-6e repair: only UPDATE one existing
 * AI source's exact-edition/chapter/page/evidence per published question.
 * Preview rolls back; production requires audit + fresh full backup.
 * NEVER changes question, choice, key, assessment or human source rows.
 */
require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(workspace|apply|backup|receipt)(?:=(.*))?$/D', $arg, $m) || isset($args[$m[1]])) {
            throw new RuntimeException('Unexpected or duplicated option.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $ws = $args['workspace'] ?? '';
    if (preg_match('/^[0-9a-f-]{36}$/D', (string) $ws) !== 1) {
        throw new RuntimeException('Exact dentistry workspace UUID required.');
    }
    $apply = isset($args['apply']);
    if ($apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Apply requires both full verified backup and new private receipt.');
    }
    $root = realpath('/srv/fanoos/shared/research');
    if ($root === false) {
        throw new RuntimeException('Protected research root unavailable.');
    }
    $studyFile = $root . '/bank-sittings/1398/orthodontics-study.json';
    $decisionsFile = $root . '/classification/decisions/1398-orthodontics.json';
    $validatedFile = $root . '/classification/sittings/1398-orthodontics-validated.json';
    $auditFile = $root . '/classification/reports/1398-orthodontics-19-original5e-independent-audit-20261010.json';
    $read = static function (string $path): array {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Private provenance input unavailable.');
        }
        return json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    };
    $study = $read($studyFile);
    $decisions = $read($decisionsFile);
    $validated = $read($validatedFile);
    $audit = $read($auditFile);
    $batch = $audit['batches'][0] ?? null;
    if (($audit['research_only'] ?? false) !== true
        || ($audit['format'] ?? '') !== 'fanoos.classification.provenance-audit/1'
        || ($audit['batch_count'] ?? null) !== 1
        || ($batch['batch'] ?? '') !== '1398:orthodontics:orthodontics'
        || ($batch['accepted'] ?? null) !== 19 || ($batch['pending'] ?? null) !== 0
        || ($batch['question_content_identical'] ?? false) !== true
        || ($batch['study_sha256'] ?? '') !== hash_file('sha256', $studyFile)
        || ($batch['decisions_sha256'] ?? '') !== hash_file('sha256', $decisionsFile)
        || ($batch['validated_sha256'] ?? '') !== hash_file('sha256', $validatedFile)
        || ($study['format'] ?? '') !== 'fanoos.classification.study-only/1'
        || ($validated['format'] ?? '') !== $study['format']
        || ($study['exam_type'] ?? '') !== 'residency'
        || ($study['year'] ?? 0) !== 1398 || ($validated['year'] ?? 0) !== 1398
        || ($study['round'] ?? 0) !== 1 || count($decisions) !== 19
        || count($study['questions'] ?? []) !== 19) {
        throw new RuntimeException('Independent 19/19 original-book audit or input hashes differ.');
    }
    $original = []; $candidate = []; $selected = [];
    foreach ($study['questions'] as $q) {
        if (($q['subject'] ?? '') !== 'orthodontics' || isset($original[$q['number']]) || isset($q['sources'])) {
            throw new RuntimeException('Invalid source-free study snapshot.');
        }
        $original[$q['number']] = $q;
    }
    foreach ($decisions as $d) {
        $n = $d['number'] ?? null;
        if (!is_int($n) || isset($selected[$n]) || !isset($original[$n])
            || ($d['edition'] ?? '') !== 'proffit-orthodontics@5e'
            || !preg_match('/^[0-9]{1,2}$/D', (string) ($d['chapter'] ?? ''))
            || !is_int($d['page'] ?? null) || $d['page'] < 1 || $d['page'] > 745
            || !is_numeric($d['confidence'] ?? null) || $d['confidence'] < .85
            || mb_strlen((string) ($d['evidence'] ?? '')) < 15
            || isset($d['override_human'], $d['none'])) {
            throw new RuntimeException('Unsupported scientific decision.');
        }
        $selected[$n] = $d;
    }
    foreach ($validated['questions'] ?? [] as $q) {
        $n = $q['number'] ?? null;
        if (!isset($original[$n]) || isset($candidate[$n]) || !isset($selected[$n])) {
            throw new RuntimeException('Validated question set differs.');
        }
        $srcs = $q['sources'] ?? [];
        unset($q['sources']);
        if ($q !== $original[$n] || !is_array($srcs) || count($srcs) !== 1) {
            throw new RuntimeException('Question content changed or source set differs.');
        }
        $s = $srcs[0]; $d = $selected[$n];
        if (($s['ref'] ?? '') !== 'proffit-orthodontics@5e#ch' . str_pad((string) $d['chapter'], 2, '0', STR_PAD_LEFT)
            || ($s['page'] ?? '') !== 'pdf ' . $d['page']
            || ($s['anchor'] ?? '') !== $d['evidence']
            || ($s['origin'] ?? '') !== 'ai' || ($s['primary'] ?? false) !== true
            || abs((float) ($s['confidence']['page'] ?? 0) - (float) $d['confidence']) > .00001) {
            throw new RuntimeException('Exact original-page validated source mismatch.');
        }
        $candidate[$n] = [$d, $s];
    }
    if (count($candidate) !== 19) {
        throw new RuntimeException('Expected exactly 19 source mappings.');
    }
    if ($apply) {
        if ((posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd') {
            throw new RuntimeException('Production update allowed only to updater service user.');
        }
        $backupPath = realpath((string) $args['backup']);
        if ($backupPath === false || !str_starts_with($backupPath . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Unrecognized full backup directory.');
        }
        $manifest = BackupManifest::verify($backupPath);
        $when = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($when === false || abs(time() - $when) > 14400
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Fresh verified full database backup required.');
        }
        $receiptFile = (string) $args['receipt'];
        if (realpath(dirname($receiptFile)) !== realpath($root . '/classification/reports')
            || file_exists($receiptFile) || is_link($receiptFile)
            || !str_ends_with($receiptFile, '.json')) {
            throw new RuntimeException('Receipt path must be unique and protected.');
        }
    }
    $db = DatabaseConnection::fromEnvironment();
    $get = $db->prepare("SELECT q.id,q.stem,q.status FROM bank_questions q
        JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
        JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
        JOIN bank_subjects sb ON sb.id=q.subject_id AND sb.workspace_id=q.workspace_id
        WHERE q.workspace_id=:ws AND et.type_key='residency' AND si.exam_year=1398
        AND si.exam_round=1 AND sb.subject_key='orthodontics' AND q.number_in_sitting=:num FOR UPDATE");
    $choices = $db->prepare('SELECT text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
    $answer = $db->prepare('SELECT choice_position,also_correct_positions,status FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $old = $db->prepare('SELECT id,origin,page,anchor_text FROM bank_question_sources WHERE workspace_id=:ws AND question_id=:qid FOR UPDATE');
    $node = $db->prepare("SELECT e.id AS edition_id,n.id AS node_id,n.kind,v.scope_chapters
        FROM bank_references r JOIN bank_reference_editions e ON e.reference_id=r.id AND e.workspace_id=r.workspace_id
        JOIN bank_reference_nodes n ON n.edition_id=e.id AND n.workspace_id=e.workspace_id
        JOIN bank_reference_validity v ON v.edition_id=e.id AND v.workspace_id=e.workspace_id
        JOIN bank_subjects sb ON sb.id=v.subject_id AND sb.workspace_id=v.workspace_id
        JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
        WHERE r.workspace_id=:ws AND r.reference_key='proffit-orthodontics'
        AND e.edition_key='5e' AND n.node_key=:node AND n.kind='chapter'
        AND v.exam_year=1398 AND sb.subject_key='orthodontics'
        AND et.type_key='residency' AND v.is_official=1");
    $update = $db->prepare("UPDATE bank_question_sources SET edition_id=:edition,node_id=:node,
        page=:page,anchor_text=:anchor,confidence_source=:conf,
        confidence_node=:conf_node,confidence_page=:conf_page
        WHERE id=:id AND workspace_id=:ws AND question_id=:qid
        AND origin='ai' AND page IS NULL");
    $db->beginTransaction(); $updated = 0;
    try {
        foreach ($candidate as $num => [$d, $src]) {
            $get->execute(['ws' => $ws, 'num' => $num]);
            $q = $get->fetch(PDO::FETCH_ASSOC);
            if (!is_array($q) || $get->fetch(PDO::FETCH_ASSOC) !== false
                || $q['status'] !== 'published' || $q['stem'] !== $original[$num]['stem']) {
                throw new RuntimeException('Site question is not the approved immutable snapshot.');
            }
            $params = ['ws' => $ws, 'qid' => $q['id']];
            $choices->execute($params);
            $actualChoices = array_map(static fn(array $x): mixed => $x['image'] === null
                ? (string) $x['text'] : ['text' => (string) $x['text'], 'image' => (string) $x['image']],
                $choices->fetchAll(PDO::FETCH_ASSOC));
            if ($actualChoices !== $original[$num]['choices']) {
                throw new RuntimeException('Question choices differ from original protected snapshot.');
            }
            $answer->execute($params);
            $a = $answer->fetch(PDO::FETCH_ASSOC);
            $actualAnswer = [
                'choice' => $a['choice_position'] === null ? null : (int) $a['choice_position'],
                'also_correct' => $a['also_correct_positions']
                    ? json_decode((string) $a['also_correct_positions'], true, 32, JSON_THROW_ON_ERROR) : [],
                'status' => (string) $a['status'],
            ];
            if ($actualAnswer !== $original[$num]['answer']) {
                throw new RuntimeException('Official answer differs from original snapshot.');
            }
            $old->execute($params);
            $before = $old->fetch(PDO::FETCH_ASSOC);
            if (!is_array($before) || $old->fetch(PDO::FETCH_ASSOC) !== false
                || $before['origin'] !== 'ai' || $before['page'] !== null
                || !str_starts_with((string) $before['anchor_text'], '[proffit-orthodontics@6e#')) {
                throw new RuntimeException('Existing source no longer exactly matches old AI-only defect.');
            }
            $node->execute(['ws' => $ws, 'node' => 'ch' . str_pad((string) $d['chapter'], 2, '0', STR_PAD_LEFT)]);
            $official = $node->fetch(PDO::FETCH_ASSOC);
            if (!is_array($official) || $node->fetch(PDO::FETCH_ASSOC) !== false || $official['kind'] !== 'chapter') {
                throw new RuntimeException('Exact official reference edition/chapter not uniquely present.');
            }
            $scope = json_decode((string) $official['scope_chapters'], true, 32, JSON_THROW_ON_ERROR);
            if (!in_array((string) $d['chapter'], array_map(static fn($x) => (string) ($x['number'] ?? ''), $scope), true)) {
                throw new RuntimeException('Source outside official 1398 chapter scope.');
            }
            $update->execute([
                'edition' => $official['edition_id'], 'node' => $official['node_id'],
                'page' => (string) $src['page'], 'anchor' => (string) $src['anchor'],
                'conf' => $d['confidence'], 'conf_node' => $d['confidence'],
                'conf_page' => $d['confidence'], 'id' => $before['id'],
                'ws' => $ws, 'qid' => $q['id']
            ]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Expected exactly one AI-only source row update.');
            }
            ++$updated;
        }
        if ($updated !== 19) {
            throw new RuntimeException('Count mismatch');
        }
        if ($apply) { $db->commit(); } else { $db->rollBack(); }
    } catch (Throwable $error) {
        if ($db->inTransaction()) { $db->rollBack(); }
        throw $error;
    }
    $receipt = [
        'format' => 'fanoos.existing-ortho-source-correction/1',
        'year' => 1398, 'subject' => 'orthodontics',
        'updated_source_rows' => $updated, 'applied' => $apply,
        'question_changes' => 0, 'choice_changes' => 0,
        'official_answer_changes' => 0, 'human_changes' => 0,
        'study_sha256' => hash_file('sha256', $studyFile),
        'validated_sha256' => hash_file('sha256', $validatedFile),
        'audit_sha256' => hash_file('sha256', $auditFile),
    ];
    if ($apply) {
        $receipt['backup_id'] = basename((string) $backupPath);
        $receipt['completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $handle = fopen($receiptFile, 'x');
        if ($handle === false) {
            throw new RuntimeException('Commit completed; unique receipt write failed. Inspect live DB before retry.');
        }
        chmod($receiptFile, 0600);
        fwrite($handle, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        fclose($handle);
    }
    echo json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Existing orthodontics source correction refused: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
