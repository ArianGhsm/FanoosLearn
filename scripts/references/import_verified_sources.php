<?php

declare(strict_types=1);

use Fanoos\Platform\Bank\SourceOnlyPublisher;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Support\DatabaseConnection;

/**
 * Secure CLI: apply EXACT reviewed, scope-approved source links ONLY.
 * Never feed study-only JSON to the ordinary full-sitting bank importer.
 *
 * Preview: --workspace=UUID --year=1403 --subject=community-dentistry
 *          --stem=community --expected=9 --audit=<protected-audit-json>
 * Apply:   same options plus --apply --backup=<verified-backup-directory>
 *          --receipt=<new-private-report-json>
 */
require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(workspace|year|subject|stem|expected|audit|apply|backup|receipt|package-root)(?:=(.*))?$/D', $arg, $m)
            || isset($args[$m[1]])) {
            throw new RuntimeException('Unexpected/repeated option.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $ws = (string) ($args['workspace'] ?? '');
    $year = filter_var($args['year'] ?? null, FILTER_VALIDATE_INT);
    $subject = (string) ($args['subject'] ?? '');
    $stem = (string) ($args['stem'] ?? '');
    $expected = filter_var($args['expected'] ?? null, FILTER_VALIDATE_INT);
    $apply = isset($args['apply']);
    if (preg_match('/^[0-9a-f-]{36}$/D', $ws) !== 1 || $year === false
        || ($year < 1399 && !(($year === 1398 && $subject === 'endodontics' && in_array($stem, ['endodontics', 'endodontics-q9', 'endodontics-q1'], true)) || ($year === 1398 && $subject === 'periodontics' && $stem === 'periodontics') || ($year === 1398 && $subject === 'community-dentistry' && $stem === 'community') || ($year === 1398 && $subject === 'oral-surgery' && $stem === 'surgery' && $expected === 2 && isset($args['package-root']))))
        || $year > 1500 || $expected === false || $expected < 1 || $expected > 250
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $subject) !== 1
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $stem) !== 1) {
        throw new RuntimeException('Workspace, exact year/subject/stem and expected count required; 1398 is held outside independently verified endodontics, periodontics and community dentistry.');
    }
    if ($apply !== isset($args['backup']) || $apply !== isset($args['receipt'])) {
        throw new RuntimeException('Apply requires both verified full backup and unique private receipt.');
    }
    if ($apply && (!function_exists('posix_getpwuid')
        || (posix_getpwuid(posix_geteuid())['name'] ?? '') !== 'fanoosupd')) {
        throw new RuntimeException('Production apply restricted to updater service account.');
    }
    $root = realpath('/srv/fanoos/shared/research');
    $reportDir = $root === false ? false : realpath($root . '/classification/reports');
    $inputRoot = $root;
    $auditDir = $reportDir;
    if (isset($args['package-root'])) {
        // A coordinator-created, immutable snapshot, never a research worker's
        // mutable source folder. All original inputs remain SHA-pinned by audit.
        $candidate = (string) $args['package-root'];
        $label = basename($candidate);
        if ($root === false || preg_match('/^(?:W0[1-8]-audit-stage|W02-community-final-audit-stage|W03-endo-q1-audit-stage)$/D', $label) !== 1
            || $candidate !== "{$root}/classification/coordinator/{$label}"
            || is_link($candidate) || realpath($candidate) !== $candidate) {
            throw new RuntimeException('Package root must be an exact coordinator WNN audit stage.');
        }
        if ($year === 1398 && $subject === 'oral-surgery' && $label !== 'W08-audit-stage') {
            throw new RuntimeException('1398 oral surgery requires the exact W08 coordinator audit-stage.');
        }
        if ($label === 'W03-endo-q1-audit-stage'
            && !($year === 1398 && $subject === 'endodontics' && $stem === 'endodontics-q1')) {
            throw new RuntimeException('Endodontics Q1 coordinator stage may only map the exact 1398 Q1 batch.');
        }
        $inputRoot = $candidate;
        $auditDir = realpath($candidate . '/classification/reports');
    }
    $auditPath = (string) ($args['audit'] ?? '');
    if ($root === false || $reportDir === false || $auditDir === false
        || realpath(dirname($auditPath)) !== $auditDir
        || is_link($auditPath) || !is_file($auditPath)) {
        throw new RuntimeException('Audit must be a protected private report under the approved input root.');
    }
    $studyPath = "{$inputRoot}/bank-sittings/{$year}/{$stem}-study.json";
    $validatedPath = "{$inputRoot}/classification/sittings/{$year}-{$stem}-validated.json";
    $decisionsPath = "{$inputRoot}/classification/decisions/{$year}-{$subject}.json";
    $read = static function (string $path): array {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Expected verified private file is unavailable.');
        }
        return json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    };
    $audit = $read($auditPath);
    // 1398 surgery exception is only Q123/Q124, in the approved Malamed Emergencies 7e PDF.
    // No Hupp7 substitution, original answer correction, or bulk 1398 surgery bypass.
    if ($year === 1398 && $subject === 'oral-surgery'
        && ($expected !== 2 || $stem !== 'surgery' || !isset($args['package-root']))) {
        throw new RuntimeException('1398 surgery accepts only the two exact audited Malamed7 questions.');
    }
    if (($audit['format'] ?? '') !== 'fanoos.classification.provenance-audit/1'
        || ($audit['research_only'] ?? false) !== true) {
        throw new RuntimeException('Required no-question-change provenance audit absent.');
    }
    $key = "{$year}:{$subject}:{$stem}";
    $matches = array_values(array_filter($audit['batches'] ?? [], static fn($b) => ($b['batch'] ?? '') === $key));
    if (count($matches) !== 1) {
        throw new RuntimeException('Exactly one batch audit required.');
    }
    $b = $matches[0];
    if (($b['accepted'] ?? 0) !== $expected || ($b['question_content_identical'] ?? false) !== true
        || ($b['study_sha256'] ?? '') !== hash_file('sha256', $studyPath)
        || ($b['validated_sha256'] ?? '') !== hash_file('sha256', $validatedPath)
        || ($b['decisions_sha256'] ?? '') !== hash_file('sha256', $decisionsPath)) {
        throw new RuntimeException('Protected batch digest mismatch or accepted count changed.');
    }
    if ($apply) {
        $backupPath = realpath((string) $args['backup']);
        if ($backupPath === false || !str_starts_with($backupPath . '/', '/var/backups/fanoos/')) {
            throw new RuntimeException('Full backup must be in the protected FANOOS backup root.');
        }
        $manifest = BackupManifest::verify($backupPath);
        $then = strtotime((string) ($manifest['created_at'] ?? ''));
        if ($then === false || abs(time() - $then) > 4 * 3600
            || (int) ($manifest['files']['database.sql']['bytes'] ?? 0) < 1024) {
            throw new RuntimeException('Apply needs a recently verified full backup containing database.sql.');
        }
        $receipt = (string) $args['receipt'];
        if (realpath(dirname($receipt)) !== $reportDir || is_link($receipt)
            || file_exists($receipt) || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Receipt path must be new and private.');
        }
    }
    $written = SourceOnlyPublisher::run(
        DatabaseConnection::fromEnvironment(), $ws, $year, $subject,
        $read($studyPath), $read($validatedPath), $read($decisionsPath), $apply,
        $inputRoot,
    );
    if ($written !== $expected) {
        throw new RuntimeException('Unexpected atomic batch count.');
    }
    $receiptData = [
        'format' => 'fanoos.source-only-receipt/1',
        'batch' => $key, 'applied' => $apply, 'source_rows' => $written,
        'questions_changed' => 0, 'choices_changed' => 0, 'answers_changed' => 0,
        'audit_sha256' => hash_file('sha256', $auditPath),
        'validated_sha256' => hash_file('sha256', $validatedPath),
    ];
    if ($apply) {
        $receiptData['backup_id'] = basename($backupPath);
        $receiptData['completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        $file = (string) $args['receipt'];
        $handle = @fopen($file, 'x');
        if ($handle === false) {
            throw new RuntimeException('Database already committed; receipt creation failed. Check production DB before any retry.');
        }
        chmod($file, 0600);
        fwrite($handle, json_encode($receiptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        fflush($handle);
        fclose($handle);
    }
    echo json_encode($receiptData, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Source-only completion failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
