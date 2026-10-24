<?php

declare(strict_types=1);

use Fanoos\Platform\Bank\SourceOnlyPublisher;
use Fanoos\Platform\Operations\BackupManifest;
use Fanoos\Platform\Operations\AtomicPrivateReceipt;
use Fanoos\Platform\Support\DatabaseConnection;

/**
 * Secure CLI: apply EXACT reviewed, scope-approved source links ONLY.
 * Never feed study-only JSON to the ordinary full-sitting bank importer.
 *
 * Preview: --workspace=UUID --year=1403 --subject=community-dentistry
 *          --stem=community --expected=9 --audit=<protected-audit-json>
 * Non-residency: add matching --type=national --round=2 flags.
 * Apply:   same options plus --apply --backup=<verified-backup-directory>
 *          --receipt=<new-private-report-json>
 */
require dirname(__DIR__, 2) . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(workspace|type|year|round|subject|stem|expected|audit|audit-sha|apply|backup|receipt|package-root|case-root)(?:=(.*))?$/D', $arg, $m)
            || isset($args[$m[1]])) {
            throw new RuntimeException('Unexpected/repeated option.');
        }
        $args[$m[1]] = $m[2] ?? '1';
    }
    $ws = (string) ($args['workspace'] ?? '');
    $year = filter_var($args['year'] ?? null, FILTER_VALIDATE_INT);
    $requestedType = (string) ($args['type'] ?? 'residency');
    $round = filter_var($args['round'] ?? '1', FILTER_VALIDATE_INT);
    $subject = (string) ($args['subject'] ?? '');
    $stem = (string) ($args['stem'] ?? '');
    $expected = filter_var($args['expected'] ?? null, FILTER_VALIDATE_INT);
    $apply = isset($args['apply']);
    $supportedTypes = ['residency', 'national', 'board', 'promotion'];
    $maxRound = in_array($requestedType, ['board', 'promotion'], true) ? 99 : 9;
    $isResidency = $requestedType === 'residency';
    $retrospective = $isResidency && $year !== false && $year >= 1300 && $year < 1396;
    $nearestSameLineageProxyBatch = $isResidency && $year !== false && $year >= 1396 && $year <= 1398
        && preg_match('/^same-lineage-proxy-[a-z0-9-]{1,35}$/D', $stem) === 1;
    $nonResidencySameLineageProxyBatch = !$isResidency
        && in_array($requestedType, ['national', 'board', 'promotion'], true)
        && preg_match('/^same-lineage-proxy-[a-z0-9-]{1,35}$/D', $stem) === 1;
    $sameLineageProxyBatch = $nearestSameLineageProxyBatch || $nonResidencySameLineageProxyBatch;
    $updated1397ProxyCounts = [
        'community-dentistry' => 10, 'dental-materials' => 9,
        'endodontics' => 19, 'operative-dentistry' => 15,
        'oral-medicine' => 20, 'oral-pathology' => 2,
        'oral-radiology' => 20, 'oral-surgery' => 19,
        'orthodontics' => 4, 'pediatric-dentistry' => 19,
        'periodontics' => 10, 'prosthodontics' => 16,
    ];
    $updated1397ProxyBatch = $isResidency && $year === 1397
        && isset($updated1397ProxyCounts[$subject])
        && $stem === 'proxy-' . $subject
        && $expected === $updated1397ProxyCounts[$subject];
    $exact1397EndodonticsQ88Batch = $isResidency && $year === 1397
        && $subject === 'endodontics' && $stem === 'official-endo-q88'
        && $expected === 1;
    $updatedHuppBatch = $isResidency && $year === 1398 && $subject === 'oral-surgery'
        && $stem === 'surgery-modern-hupp7' && $expected === 14;
    $updatedReferenceProxyBatch = $updated1397ProxyBatch || $updatedHuppBatch;
    if (preg_match('/^[0-9a-f-]{36}$/D', $ws) !== 1 || $year === false
        || !in_array($requestedType, $supportedTypes, true)
        || $round === false || $round < 1 || $round > $maxRound
        || ($isResidency && $round !== 1)
        || ($isResidency && $year < 1399 && !$retrospective && !$nearestSameLineageProxyBatch && !$updated1397ProxyBatch && !$exact1397EndodonticsQ88Batch && !(($year === 1398 && $subject === 'endodontics' && in_array($stem, ['endodontics', 'endodontics-q9', 'endodontics-q1', 'endodontics-location-final'], true)) || ($year === 1398 && $subject === 'periodontics' && in_array($stem, ['periodontics', 'periodontics-followup', 'periodontics-certified-20261012', 'periodontics-sourcefree'], true)) || ($year === 1398 && $subject === 'community-dentistry' && $stem === 'community') || ($year === 1398 && $subject === 'prosthodontics' && in_array($stem, ['prosthodontics', 'prostho-voided-q97'], true)) || ($year === 1398 && $subject === 'operative-dentistry' && in_array($stem, ['operative-q175-20261012', 'operative-q188-snowplow'], true)) || ($year === 1398 && $subject === 'pediatric-dentistry' && $stem === 'pediatric-q164-q168-20261012') || ($year === 1398 && $subject === 'oral-radiology' && $stem === 'radiology-four-20261012') || ($year === 1398 && $subject === 'pediatric-dentistry' && $stem === 'pediatric-q159-20261012') || ($year === 1398 && $subject === 'oral-radiology' && $stem === 'radiology-q219-q226-20261012') || ($year === 1398 && $subject === 'pediatric-dentistry' && $stem === 'pediatric-q152-20261012') || ($year === 1398 && $subject === 'oral-radiology' && $stem === 'radiology-seven-20261012') || ($year === 1398 && $subject === 'periodontics' && $stem === 'periodontics-q134-20261012') || ($year === 1398 && $subject === 'oral-radiology' && $stem === 'radiology-q218-20261012') || ($year === 1398 && $subject === 'oral-radiology' && $stem === 'radiology-q222-20261012') || ($year === 1398 && $subject === 'oral-radiology' && in_array($stem, ['radiology-q213-q216-20261013', 'radiology-q220-20261013'], true)) || ($year === 1398 && $subject === 'pediatric-dentistry' && $stem === 'pediatric-sourcefree') || ($year === 1398 && $subject === 'oral-surgery' && in_array($stem, ['surgery', 'surgery-modern-hupp7'], true))))
        || $year < ($isResidency ? 1300 : 1350) || $year > 1500 || $expected === false || $expected < 1 || $expected > 250
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $subject) !== 1
        || preg_match('/^[a-z][a-z0-9-]{1,59}$/D', $stem) !== 1) {
        throw new RuntimeException('Workspace, supported exam type/round, year/subject/stem and expected count are required; residency proxy batches remain limited to their audited paths.');
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
    $receiptDir = $reportDir;
    if (isset($args['package-root']) && isset($args['case-root'])) {
        throw new RuntimeException('Specify only one protected package source.');
    }
    if (($updatedReferenceProxyBatch || $exact1397EndodonticsQ88Batch || $sameLineageProxyBatch)
        && (!isset($args['case-root']) || isset($args['package-root']))) {
        throw new RuntimeException('A proxy source batch requires its exact immutable recovery case.');
    }
    if (isset($args['package-root'])) {
        // A coordinator-created, immutable snapshot, never a research worker's
        // mutable source folder. All original inputs remain SHA-pinned by audit.
        $candidate = (string) $args['package-root'];
        $label = basename($candidate);
        if ($root === false || preg_match('/^(?:W0[1-8]-audit-stage|W02-community-final-audit-stage|W03-endo-q1-audit-stage|W03-location-final-20261010|W01-radiology-next-20261012-v2|W01-1399-oralmedicine-q43-20261012)$/D', $label) !== 1
            || $candidate !== "{$root}/classification/coordinator/{$label}"
            || is_link($candidate) || realpath($candidate) !== $candidate) {
            throw new RuntimeException('Package root must be an exact coordinator WNN audit stage.');
        }
        if ($label === 'W03-endo-q1-audit-stage'
            && !($year === 1398 && $subject === 'endodontics' && $stem === 'endodontics-q1')) {
            throw new RuntimeException('Endodontics Q1 coordinator stage may only map the exact 1398 Q1 batch.');
        }
        if ($label === 'W03-location-final-20261010'
            && !($year === 1398 && $subject === 'endodontics'
                && $stem === 'endodontics-location-final')) {
            throw new RuntimeException('Endodontics final location audit stage is exclusive to exact 1398 endodontics.');
        }
        if ($label === 'W01-radiology-next-20261012-v2'
            && !($year === 1399 && $subject === 'oral-radiology'
                && $stem === 'radiology-remain' && $expected === 4)) {
            throw new RuntimeException('1399 W01 radiology evidence stage is exclusive to four separately verified sources.');
        }
        if ($label === 'W01-1399-oralmedicine-q43-20261012'
            && !($year === 1399 && $subject === 'oral-medicine'
                && $stem === 'oral-medicine-q43' && $expected === 1)) {
            throw new RuntimeException('1399 original Burket Q43 evidence stage is only for its exact batch.');
        }
        $inputRoot = $candidate;
        $auditDir = realpath($candidate . '/classification/reports');
    }
    if (isset($args['case-root'])) {
        // A protected immutable original study/review package is a direct
        // child of classification, never an arbitrary path or coordinator dir.
        $privateRoot = $root === false ? false : realpath($root . '/classification');
        $requested = (string) $args['case-root'];
        $case = realpath($requested);
        $exact1397UpdatedReferenceCase = $updated1397ProxyBatch
            && $case !== false
            && basename($case) === 'recovery-1397-updated-reference-proxy-20261021';
        $exact1397Q88OfficialCase = $exact1397EndodonticsQ88Batch
            && $case !== false
            && basename($case) === 'recovery-1397-official-endo-q88-20261021';
        $exact1398SurgeryCase = $year === 1398 && $subject === 'oral-surgery'
            && $stem === 'surgery' && $expected === 2
            && $case !== false && basename($case) === 'recovery-1398-oral-surgery-malamed7-20261021';
        $exact1398VoidedProsthoCase = $year === 1398 && $subject === 'prosthodontics'
            && $stem === 'prostho-voided-q97' && $expected === 1
            && $case !== false && basename($case) === 'recovery-1398-prostho-voided-q97-20261021';
        $exact1398RadiologyQ225Case = $year === 1398 && $subject === 'oral-radiology'
            && $stem === 'radiology-q219-q226-20261012' && $expected === 1
            && $case !== false && basename($case) === 'recovery-1398-radiology-q225-20261019';
        $exact1398OperativeQ188Case = $year === 1398 && $subject === 'operative-dentistry'
            && $stem === 'operative-q188-snowplow' && $expected === 1
            && $case !== false && basename($case) === 'recovery-1398-operative-q188-snowplow-20261021';
        $exact1398PediatricCase = $year === 1398 && $subject === 'pediatric-dentistry'
            && $stem === 'pediatric-sourcefree' && $expected === 16
            && $case !== false && basename($case) === 'recovery-1398-pediatric-sourcefree-20261021';
        $exact1398PeriodonticsCase = $year === 1398 && $subject === 'periodontics'
            && $stem === 'periodontics-sourcefree' && $expected === 14
            && $case !== false && basename($case) === 'recovery-1398-periodontics-sourcefree-20261021';
        $exact1398UpdatedHuppCase = $year === 1398 && $subject === 'oral-surgery'
            && $stem === 'surgery-modern-hupp7' && $expected === 14
            && $case !== false && basename($case) === 'recovery-1398-surgery-modern-hupp7-20261021';
        if ($year === false || ($year < 1399 && !$retrospective && !$sameLineageProxyBatch && !$exact1397UpdatedReferenceCase && !$exact1397Q88OfficialCase
                && !$exact1398SurgeryCase && !$exact1398VoidedProsthoCase
                && !$exact1398RadiologyQ225Case && !$exact1398OperativeQ188Case
                && !$exact1398PediatricCase && !$exact1398PeriodonticsCase
                && !$exact1398UpdatedHuppCase)
            || $privateRoot === false || $case === false
            || $requested !== $case || dirname($case) !== $privateRoot
            || !preg_match('/^recovery-' . $year . '-[a-z0-9-]+-20[0-9]{6}$/D', basename($case))
            || is_link($requested)) {
            throw new RuntimeException('Case must be a direct protected same-year recovery folder.');
        }
        $inputRoot = $case;
        $auditDir = realpath($case . '/classification/reports');
        $receiptDir = $auditDir;
    }
    $auditPath = (string) ($args['audit'] ?? '');
    if ($root === false || $reportDir === false || $auditDir === false
        || realpath(dirname($auditPath)) !== $auditDir
        || is_link($auditPath) || !is_file($auditPath)) {
        throw new RuntimeException('Audit must be a protected private report under the approved input root.');
    }
    $expectedAuditSha = strtolower((string) ($args['audit-sha'] ?? ''));
    if ((isset($args['case-root']) && $expectedAuditSha === '')
        || ($expectedAuditSha !== ''
            && (!preg_match('/^[a-f0-9]{64}$/D', $expectedAuditSha)
                || !hash_equals($expectedAuditSha, hash_file('sha256', $auditPath))))) {
        throw new RuntimeException('Missing or mismatched independent private audit SHA-256.');
    }
    $studyPath = "{$inputRoot}/bank-sittings/{$year}/{$stem}-study.json";
    $validatedPath = "{$inputRoot}/classification/sittings/{$year}-{$stem}-validated.json";
    $decisionsPath = $isResidency
        ? "{$inputRoot}/classification/decisions/{$year}-{$subject}.json"
        : "{$inputRoot}/classification/decisions/{$requestedType}-{$year}-{$round}/{$year}-{$subject}.json";
    if ($year === 1398 && $subject === 'periodontics' && in_array($stem, ['periodontics-followup', 'periodontics-certified-20261012', 'periodontics-q134-20261012'], true)) {
        $decisionsPath = "{$inputRoot}/classification/decisions/1398-{$stem}.json";
    }
    if ($year === 1398 && $subject === 'operative-dentistry' && $stem === 'operative-q175-20261012') {
        $decisionsPath = "{$inputRoot}/classification/decisions/1398-operative-q175-20261012.json";
    }
    if ($year === 1398 && in_array($stem, ['pediatric-q164-q168-20261012', 'radiology-four-20261012', 'pediatric-q159-20261012', 'radiology-q219-q226-20261012', 'pediatric-q152-20261012', 'radiology-seven-20261012', 'radiology-q218-20261012', 'radiology-q222-20261012', 'radiology-q213-q216-20261013', 'radiology-q220-20261013'], true)) {
        $decisionsPath = "{$inputRoot}/classification/decisions/1398-{$stem}.json";
    }
    $read = static function (string $path): array {
        if (!is_file($path) || is_link($path)) {
            throw new RuntimeException('Expected verified private file is unavailable.');
        }
        return json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    };
    $audit = $read($auditPath);
    if (($audit['format'] ?? '') !== 'fanoos.classification.provenance-audit/1'
        || ($audit['research_only'] ?? false) !== true) {
        throw new RuntimeException('Required no-question-change provenance audit absent.');
    }
    $legacyKey = "{$year}:{$subject}:{$stem}";
    $typedKey = "{$requestedType}:{$year}:{$round}:{$subject}:{$stem}";
    $batchKeys = $isResidency ? [$legacyKey, $typedKey] : [$typedKey];
    $matches = array_values(array_filter($audit['batches'] ?? [], static fn($b) => in_array(($b['batch'] ?? ''), $batchKeys, true)));
    if (count($matches) !== 1) {
        throw new RuntimeException('Exactly one batch audit required.');
    }
    $b = $matches[0];
    $key = (string) $b['batch'];
    if ((!$isResidency && (($b['exam_type'] ?? null) !== $requestedType || ($b['round'] ?? null) !== $round))
        || (isset($b['exam_type']) && $b['exam_type'] !== $requestedType)
        || (isset($b['round']) && $b['round'] !== $round)) {
        throw new RuntimeException('Private audit exam type or round does not match the requested sitting.');
    }
    if ($sameLineageProxyBatch
        && (($b['classification_basis'] ?? '') !== 'same_lineage_proxy'
            || !is_array($b['reference_years'] ?? null)
            || $b['reference_years'] === [$year])) {
        throw new RuntimeException('Same-lineage proxy case must attest its actual non-exam reference year(s).');
    }
    if (!$sameLineageProxyBatch
        && ($b['classification_basis'] ?? '') === 'same_lineage_proxy') {
        throw new RuntimeException('Generic proxy audit requires a same-lineage proxy batch stem and immutable recovery case.');
    }
    if ($retrospective && ($b['classification_basis'] ?? '') !== 'retrospective_proxy_1397_plus') {
        throw new RuntimeException('Pre-1396 source audit must attest a distinct retrospective proxy basis.');
    }
    if ($updatedReferenceProxyBatch && (($b['classification_basis'] ?? '') !== 'updated_reference_proxy'
            || ($b['reference_years'] ?? null) !== [1406])) {
        throw new RuntimeException('Updated-reference proxy audit must attest its exact official 1406 reference-year basis.');
    }
    if ($exact1397EndodonticsQ88Batch && (($b['classification_basis'] ?? '') !== 'official_exam_year'
            || ($b['reference_years'] ?? null) !== [1397])) {
        throw new RuntimeException('The exact 1397 Q88 batch must attest the original official 1397 reference year.');
    }
    if (!$updatedReferenceProxyBatch && ($b['classification_basis'] ?? '') === 'updated_reference_proxy') {
        throw new RuntimeException('Updated-reference proxy audit cannot be reused by another batch.');
    }
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
        if ((isset($args['case-root']) ? realpath(dirname($receipt)) !== $receiptDir : realpath(dirname($receipt)) !== $reportDir) || is_link($receipt)
            || file_exists($receipt) || !str_ends_with($receipt, '.json')) {
            throw new RuntimeException('Receipt path must be new and private.');
        }
    }
    // Keep the exact validated inputs in memory across commit so the
    // independent readback checks the same pinned content, not a later file
    // reread that could differ from the transaction inputs.
    $study = $read($studyPath);
    $validated = $read($validatedPath);
    $decisions = $read($decisionsPath);
    if (($study['exam_type'] ?? 'residency') !== $requestedType
        || ($validated['exam_type'] ?? 'residency') !== $requestedType
        || ($study['round'] ?? 1) !== $round || ($validated['round'] ?? 1) !== $round
        || ($study['year'] ?? null) !== $year || ($validated['year'] ?? null) !== $year) {
        throw new RuntimeException('Study and validated sitting type, year or round do not match the importer arguments.');
    }
    if (!$isResidency && (!isset($args['type']) || !isset($args['round']))) {
        throw new RuntimeException('National, board and promotion imports require explicit matching --type and --round flags.');
    }
    $receiptAuditSha = hash_file('sha256', $auditPath);
    $receiptValidatedSha = hash_file('sha256', $validatedPath);
    if (!is_string($receiptAuditSha) || !is_string($receiptValidatedSha)) {
        throw new RuntimeException('Pinned receipt input digest could not be read before publication.');
    }
    $readbackEvidence = null;
    $written = SourceOnlyPublisher::run(
        DatabaseConnection::fromEnvironment(), $ws, $year, $subject,
        $study, $validated, $decisions, $apply, $readbackEvidence,
    );
    $receiptData = [
        'format' => 'fanoos.source-only-receipt/1',
        'batch' => $key, 'applied' => $apply, 'source_rows' => $written,
        'questions_changed' => 0, 'choices_changed' => 0, 'answers_changed' => 0,
        'audit_sha256' => $receiptAuditSha,
        'classification_basis' => $retrospective ? 'retrospective_proxy_1397_plus'
            : ($updatedReferenceProxyBatch ? 'updated_reference_proxy'
                : ($sameLineageProxyBatch ? 'same_lineage_proxy' : 'official_exam_year')),
        'reference_years' => $retrospective ? ($b['reference_years'] ?? [])
            : (($updatedReferenceProxyBatch || $sameLineageProxyBatch)
                ? ($b['reference_years'] ?? []) : [$year]),
        'validated_sha256' => $receiptValidatedSha,
    ];
    if (!$isResidency || $round !== 1) {
        $receiptData['exam_type'] = $requestedType;
        $receiptData['round'] = $round;
    }
    if ($apply) {
        $receiptData['backup_id'] = basename($backupPath);
        $receiptData['completed_at_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        try {
            $readback = SourceOnlyPublisher::readback(
                DatabaseConnection::fromEnvironment(), $ws, $year, $subject,
                $study, $validated, $readbackEvidence ?? [],
            );
            if ($written !== $expected) {
                $readback['verified'] = false;
                $readback['mismatches'][] = 'operator_expected_source_count';
            }
        } catch (Throwable) {
            $readback = [
                'verified' => false,
                'mismatches' => ['post_commit_readback_unavailable'],
                'readback_error_code' => 'post_commit_readback_failed',
            ];
        }
        $receiptData['status'] = ($readback['verified'] ?? false) === true
            ? 'applied_verified' : 'manual_reconciliation_required';
        $receiptData['readback'] = $readback;
        $receiptData['reconciliation_required'] = $receiptData['status'] !== 'applied_verified';
        $encodedReceipt = json_encode($receiptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $file = (string) $args['receipt'];
        AtomicPrivateReceipt::publish($file, $encodedReceipt);
        if (($readback['verified'] ?? false) !== true) {
            throw new RuntimeException('Database commit returned but independent readback did not verify it. Manual reconciliation is required; do not retry or roll back blindly.');
        }
    } elseif ($written !== $expected) {
        throw new RuntimeException('Unexpected atomic preview batch count.');
    }
    echo json_encode($receiptData, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Source-only completion failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
