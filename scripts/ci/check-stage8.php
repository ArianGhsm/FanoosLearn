<?php

declare(strict_types=1);

use Fanoos\Platform\Migration\LegacyBundleValidator;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

$required = [
    'docs/fanoos-migration/08_FINAL_ACCEPTANCE_MATRIX.md',
    'docs/fanoos-migration/08_LEGACY_DATA_MIGRATION_PLAN.md',
    'docs/fanoos-migration/08_DATA_RECONCILIATION.md',
    'docs/fanoos-migration/08_SECURITY_REDTEAM.md',
    'docs/fanoos-migration/08_BOT_AND_CHANNEL_REDTEAM.md',
    'docs/fanoos-migration/08_BACKUP_RESTORE_DR.md',
    'docs/fanoos-migration/08_STAGING_REHEARSAL.md',
    'docs/fanoos-migration/08_PRODUCTION_CUTOVER_ROLLBACK.md',
    'docs/fanoos-migration/08_CODEX_ONE_TIME_BOOTSTRAP_STAGING_CUTOVER.md',
    'apps/platform/src/Migration/LegacyTargetId.php',
    'apps/platform/src/Migration/LegacyBundleValidator.php',
    'apps/platform/src/Migration/LegacyImportEngine.php',
    'apps/platform/src/Migration/LegacyReconciler.php',
    'scripts/import/legacy-bundle.php',
    'scripts/import/legacy-reconcile.php',
    'scripts/import/legacy-bundle.sanitized.json',
    'tests/Integration/Stage8FinalClosureTest.php',
];

foreach ($required as $relative) {
    if (!is_file($root . '/' . $relative)) {
        throw new RuntimeException("Stage 8 required artifact is missing: {$relative}");
    }
}

$acceptance = (string) file_get_contents($root . '/docs/fanoos-migration/08_FINAL_ACCEPTANCE_MATRIX.md');
$handoff = (string) file_get_contents($root . '/docs/fanoos-migration/08_CODEX_ONE_TIME_BOOTSTRAP_STAGING_CUTOVER.md');
if (!str_contains($acceptance, 'aab498bee84f08979ec6bc6c5f4f0ffc03707b21')) {
    throw new RuntimeException('Stage 8 acceptance does not pin the accepted UX-stabilized starting SHA.');
}
if (!str_contains($handoff, 'RUNTIME_VALIDATION_REQUIRED') || !str_contains($handoff, 'STAGE8_RELEASE_CANDIDATE_SHA')) {
    throw new RuntimeException('Codex handoff must preserve the runtime evidence boundary and release SHA handoff key.');
}

$fixture = json_decode((string) file_get_contents($root . '/scripts/import/legacy-bundle.sanitized.json'), true, 64, JSON_THROW_ON_ERROR);
if (!is_array($fixture)) {
    throw new RuntimeException('Sanitized migration fixture is not a JSON object.');
}
$fixtureReport = (new LegacyBundleValidator('stage8-fixture-hmac-key'))->inspect($fixture);
if ($fixtureReport['valid'] !== true) {
    throw new RuntimeException('Sanitized Stage 8 migration fixture does not satisfy the versioned bundle contract: ' . implode(' | ', $fixtureReport['errors']));
}

fwrite(STDOUT, "PASS Stage 8 release-closure contracts\n");
