<?php

declare(strict_types=1);

/**
 * Repair ONLY the hash-bound PDF location of an existing AI source row.
 *
 * Requires a private, independently page-validated one-question research
 * package, exact audit SHA, the original published question/key/choices,
 * official year scope, an unchanged existing edition/chapter/printed page,
 * and a current approved reference-PDF SHA. Never inserts, deletes, or
 * changes chapter, legacy page, text, answer, review or attempt data.
 *
 * Default = rollback-only preview. Apply requires an independently verified
 * fresh full backup, authorized fanoosupd identity, and unique private receipt.
 */
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--(workspace|case-root|year|subject|number|stem|audit-sha|apply|backup|receipt)(?:=(.*))?$/D', $arg, $m) !== 1
            || isset($args[$m[1]])) {
            throw new RuntimeException('Unknown or duplicate option.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $year = filter_var($args['year'] ?? null, FILTER_VALIDATE_INT);
    $number = filter_var($args['number'] ?? null, FILTER_VALIDATE_INT);
    $subject = (string) ($args['subject'] ?? '');
    $stem = (string) ($args['stem'] ?? '');
    $expectedAuditSha = strtolower((string) ($args['audit-sha'] ?? ''));
    $apply = isset($args['apply']);
    if (!preg_match('/^[0-9a-f-]{36}$/D', $workspace)
        || $year === false || $year < 1397 || $year > 1500
        || $number === false || $number < 1 || $number > 300
        || !preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $subject)
        || !preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $stem)
        || !preg_match('/^[a-f0-9]{64}$/D', $expectedAuditSha)) {
        throw new RuntimeException('Explicit sitting, subject, number, package and audited SHA required.');
    }
    if ($apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Apply requires both a full verified backup and unique receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Only the authorized updater identity may apply a repair.');
    }
    $privateRoot = realpath('/srv/fanoos/shared/research/classification');
    $case = realpath((string) ($args['case-root'] ?? ''));
    if ($privateRoot === false || $case === false
        || dirname($case) !== $privateRoot
        || !preg_match('/^recovery-14[0-9]{2}-[a-z0-9-]+-20[0-9]{6}$/D', basename($case))
        || is_link((string) $args['case-root'])) {
        throw new RuntimeException('Case must be an existing protected per-year research recovery folder.');
    }
    $originalPath = "$case/bank-sittings/$year/$stem-study.json";
    $decisionsPath = "$case/classification/decisions/$year-$subject.json";
    $validatedPath = "$case/classification/sittings/$year-$stem-validated.json";
    $auditPath = "$case/classification/reports/$year-$stem-audit.json";
    $load = static function (string $path): array {
        if (is_link($path) || !is_file($path)) {
            throw new RuntimeException('Missing or linked immutable private evidence file.');
        }
        $decoded = json_decode((string) file_get_contents($path), true, 256, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid private evidence file.');
        }
        return $decoded;
    };
    if (!hash_equals($expectedAuditSha, hash_file('sha256', $auditPath))) {
        throw new RuntimeException('Private audit does not match independently reviewed digest.');
    }
    $study = $load($originalPath);
    $decisions = $load($decisionsPath);
    $validated = $load($validatedPath);
    $audit = $load($auditPath);
    $spec = "$year:$subject:$stem";
    if (($study['format'] ?? '') !== 'fanoos.classification.study-only/1'
        || ($validated['format'] ?? '') !== $study['format']
        || ($study['exam_type'] ?? '') !== 'residency'
        || ($validated['exam_type'] ?? '') !== 'residency'
        || ($study['year'] ?? null) !== $year || ($validated['year'] ?? null) !== $year
        || ($study['round'] ?? null) !== 1 || ($validated['round'] ?? null) !== 1
        || count($study['questions'] ?? []) !== 1 || count($validated['questions'] ?? []) !== 1
        || count($decisions) !== 1
        || ($study['questions'][0]['number'] ?? null) !== $number
        || ($study['questions'][0]['subject'] ?? null) !== $subject
        || isset($study['questions'][0]['sources'])
        || ($validated['questions'][0]['number'] ?? null) !== $number
        || ($decisions[0]['number'] ?? null) !== $number
        || ($audit['research_only'] ?? false) !== true
        || ($audit['batch_count'] ?? null) !== 1
        || count($audit['batches'] ?? []) !== 1) {
        throw new RuntimeException('Package is not one original unchanged residency question.');
    }
    $original = $study['questions'][0];
    $checked = $validated['questions'][0];
    $newSources = $checked['sources'] ?? [];
    unset($checked['sources']);
    if ($checked !== $original || count($newSources) !== 1) {
        throw new RuntimeException('Validated question content differs from published study.');
    }
    $d = $decisions[0];
    $s = $newSources[0];
    $edition = (string) ($d['edition'] ?? '');
    $chapter = (string) ($d['chapter'] ?? '');
    $pdfPage = $d['page'] ?? null;
    $node = 'ch' . str_pad($chapter, 2, '0', STR_PAD_LEFT);
    $reference = "$edition#$node";
    $prov = $audit['batches'][0]['source_pdf_provenance'][$edition] ?? [];
    $pdfSha = strtolower((string) ($prov['sha256'] ?? ''));
    $batch = $audit['batches'][0];
    if (!preg_match('/^([a-z0-9-]+)@([a-z0-9-]+)$/D', $edition, $parts)
        || !preg_match('/^[0-9]{1,3}$/D', $chapter)
        || !is_int($pdfPage) || $pdfPage < 1
        || !preg_match('/^[a-f0-9]{64}$/D', $pdfSha)
        || ($prov['source'] ?? '') !== 'verified-server-pdf'
        || ($s['ref'] ?? '') !== $reference
        || ($s['pdf_page'] ?? null) !== $pdfPage
        || strtolower((string) ($s['pdf_sha256'] ?? '')) !== $pdfSha
        || ($s['page'] ?? '') !== 'pdf ' . $pdfPage
        || ($s['origin'] ?? '') !== 'ai'
        || ($batch['batch'] ?? '') !== $spec || ($batch['accepted'] ?? null) !== 1
        || ($batch['questions'] ?? null) !== 1 || ($batch['pending'] ?? null) !== 0
        || ($batch['question_content_identical'] ?? false) !== true
        || ($batch['study_sha256'] ?? '') !== hash_file('sha256', $originalPath)
        || ($batch['decisions_sha256'] ?? '') !== hash_file('sha256', $decisionsPath)
        || ($batch['validated_sha256'] ?? '') !== hash_file('sha256', $validatedPath)
        || ($batch['mapped'][(string) $number]['pdf_page'] ?? null) !== $pdfPage
        || ($batch['mapped'][(string) $number]['ref'] ?? '') !== $reference) {
        throw new RuntimeException('Exact-PDF provenance or source decision does not match the independent audit.');
    }
    $backupId = null;
    $receipt = '';
    if ($apply) {
        $backupPath = realpath((string) $args['backup']);
        if ($backupPath === false || !str_starts_with($backupPath . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Backup is not under the protected official backup root.');
        }
        $manifest = BackupManifest::verify($backupPath);
        $created = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($created === false || abs(time() - $created) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('A recent complete independently verified backup is required.');
        }
        $backupId = basename($backupPath);
        $receipt = (string) $args['receipt'];
        if (realpath(dirname($receipt)) !== "$case/classification/reports"
            || !str_ends_with($receipt, '.json') || is_link($receipt) || file_exists($receipt)) {
            throw new RuntimeException('Receipt must be a new protected file under the case reports directory.');
        }
    }
    $db = DatabaseConnection::fromEnvironment();
    $db->beginTransaction();
    try {
        $get = $db->prepare(<<<'SQL'
SELECT q.id question_id, q.stem, q.status,
       s.id source_id, s.page, s.printed_page, s.pdf_page, s.pdf_sha256, s.origin, s.reviewed_at,
       r.reference_key, e.edition_key, n.node_key, n.kind
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects bs ON bs.id=q.subject_id AND bs.workspace_id=q.workspace_id
JOIN bank_question_sources s ON s.question_id=q.id AND s.workspace_id=q.workspace_id
JOIN bank_reference_editions e ON e.id=s.edition_id AND e.workspace_id=s.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_reference_nodes n ON n.id=s.node_id AND n.workspace_id=s.workspace_id
WHERE q.workspace_id=:ws AND et.type_key='residency'
  AND si.exam_year=:yr AND si.exam_round=1 AND bs.subject_key=:subject
  AND q.number_in_sitting=:number
FOR UPDATE
SQL);
        $get->execute(['ws' => $workspace, 'yr' => $year, 'subject' => $subject, 'number' => $number]);
        $live = $get->fetch(PDO::FETCH_ASSOC);
        if (!is_array($live) || $get->fetch(PDO::FETCH_ASSOC) !== false
            || $live['status'] !== 'published' || $live['stem'] !== $original['stem']
            || $live['reference_key'] !== $parts[1] || $live['edition_key'] !== $parts[2]
            || $live['node_key'] !== $node || $live['kind'] !== 'chapter'
            || $live['origin'] !== 'ai' || $live['reviewed_at'] !== null
            || $live['pdf_page'] !== null || $live['pdf_sha256'] !== null
            || (string) $live['page'] !== (string) $pdfPage
            || (string) $live['printed_page'] !== (string) $pdfPage) {
            throw new RuntimeException('Existing source is changed, reviewed, mismatched or not an exact page-label candidate.');
        }
        $q = ['ws' => $workspace, 'qid' => $live['question_id']];
        $choices = $db->prepare('SELECT text,image FROM bank_question_choices WHERE workspace_id=:ws AND question_id=:qid ORDER BY position');
        $choices->execute($q);
        $actualChoices = array_map(
            static fn(array $v): mixed => $v['image'] === null
                ? (string) $v['text'] : ['text' => (string) $v['text'], 'image' => (string) $v['image']],
            $choices->fetchAll(PDO::FETCH_ASSOC),
        );
        if ($actualChoices !== $original['choices']) {
            throw new RuntimeException('Question choices differ from original evidence.');
        }
        $answer = $db->prepare('SELECT choice_position,also_correct_positions,status FROM bank_official_answers WHERE workspace_id=:ws AND question_id=:qid ORDER BY recorded_at DESC,id DESC LIMIT 1');
        $answer->execute($q);
        $a = $answer->fetch(PDO::FETCH_ASSOC);
        $latest = is_array($a) ? [
            'choice' => $a['choice_position'] === null ? null : (int) $a['choice_position'],
            'also_correct' => $a['also_correct_positions']
                ? json_decode((string) $a['also_correct_positions'], true, 32, JSON_THROW_ON_ERROR) : [],
            'status' => (string) $a['status'],
        ] : null;
        if ($latest !== $original['answer']) {
            throw new RuntimeException('Official key changed since the protected audit.');
        }
        $scope = $db->prepare(<<<'SQL'
SELECT v.scope_chapters
FROM bank_reference_validity v
JOIN bank_reference_editions e ON e.id=v.edition_id AND e.workspace_id=v.workspace_id
JOIN bank_references r ON r.id=e.reference_id AND r.workspace_id=e.workspace_id
JOIN bank_exam_types et ON et.id=v.exam_type_id AND et.workspace_id=v.workspace_id
JOIN bank_subjects bs ON bs.id=v.subject_id AND bs.workspace_id=v.workspace_id
WHERE v.workspace_id=:ws AND et.type_key='residency' AND v.exam_year=:yr
AND bs.subject_key=:subject AND r.reference_key=:reference AND e.edition_key=:edition
AND v.is_official=1
SQL);
        $scope->execute(['ws'=>$workspace,'yr'=>$year,'subject'=>$subject,'reference'=>$parts[1],'edition'=>$parts[2]]);
        $validity = $scope->fetch(PDO::FETCH_ASSOC);
        if (!is_array($validity) || $scope->fetch(PDO::FETCH_ASSOC) !== false) {
            throw new RuntimeException('Official same-year reference scope missing or ambiguous.');
        }
        $nodes = json_decode((string) $validity['scope_chapters'], true, 64, JSON_THROW_ON_ERROR);
        $allowed = array_map(static fn(array $x): string => (string) ($x['number'] ?? ''), $nodes);
        if (!in_array($chapter, $allowed, true)) {
            throw new RuntimeException('Existing chapter is outside the official announced year scope.');
        }
        $verified = $db->prepare(<<<'SQL'
SELECT LOWER(HEX(o.checksum_sha256)) approved_sha
FROM content_resources cr
JOIN content_resource_metadata m ON m.resource_id=cr.id AND m.workspace_id=cr.workspace_id
JOIN content_resource_versions v ON v.resource_id=cr.id AND v.workspace_id=cr.workspace_id AND v.version_no=cr.current_version_no
JOIN content_objects o ON o.id=v.object_id AND o.workspace_id=v.workspace_id
WHERE cr.workspace_id=:ws AND m.topic=:topic AND m.format_key='reference_pdf'
AND m.access_level='private' AND cr.lifecycle_status='published'
AND cr.deleted_at IS NULL AND cr.archived_at IS NULL
AND v.status='approved' AND o.status='verified'
AND o.classification='private' AND o.detected_mime='application/pdf' AND o.deleted_at IS NULL
SQL);
        $verified->execute(['ws'=>$workspace,'topic'=>$edition]);
        $object = $verified->fetch(PDO::FETCH_ASSOC);
        if (!is_array($object) || $verified->fetch(PDO::FETCH_ASSOC) !== false
            || !hash_equals($pdfSha, (string) $object['approved_sha'])) {
            throw new RuntimeException('Approved PDF identity differs from the independently checked source file.');
        }
        $update = $db->prepare(<<<'SQL'
UPDATE bank_question_sources SET pdf_page=:page, pdf_sha256=:sha
WHERE id=:id AND workspace_id=:ws AND pdf_page IS NULL AND pdf_sha256 IS NULL
SQL);
        $update->execute(['page'=>$pdfPage,'sha'=>$pdfSha,'id'=>$live['source_id'],'ws'=>$workspace]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Exactly one pre-existing source must be repaired.');
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
    $result = [
        'format'=>'fanoos.source-page-repair/1', 'applied'=>$apply,
        'year'=>$year, 'subject'=>$subject, 'number'=>$number,
        'reference'=>$edition, 'chapter'=>$chapter, 'pdf_page'=>$pdfPage,
        'pdf_sha256'=>$pdfSha, 'source_rows_updated'=>1,
        'question_rows_updated'=>0, 'choices_updated'=>0, 'answers_updated'=>0,
        'source_chapter_updated'=>0, 'human_reviews_updated'=>0,
        'audit_sha256'=>$expectedAuditSha, 'backup_id'=>$backupId,
    ];
    if ($apply) {
        $result['completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $handle = @fopen($receipt, 'x');
        if ($handle === false) {
            throw new RuntimeException('Committed source location but could not create receipt; inspect DB before retry.');
        }
        chmod($receipt, 0600);
        fwrite($handle, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'PDF page repair rejected: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
