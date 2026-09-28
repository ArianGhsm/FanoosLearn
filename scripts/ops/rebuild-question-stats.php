<?php

declare(strict_types=1);

use Fanoos\Platform\Content\QuestionStatsRecorder;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\Transaction;

/**
 * Recomputes the per-question counters (exam_question_stats,
 * exam_question_choice_stats, exam_question_user_stats) from the scored
 * attempts -- once after migration 0028 so answers given before it count,
 * and whenever the counters are in doubt. Safe to repeat: it rebuilds from
 * scratch in one transaction, and exam_attempt_results is never touched.
 *
 * Usage:
 *   php scripts/ops/rebuild-question-stats.php [--workspace=<uuid>]
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $workspace = null;
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--workspace=') && preg_match('/^[0-9a-f-]{36}$/', substr($argument, 12)) === 1) {
            $workspace = substr($argument, 12);
        } else {
            throw new RuntimeException("Unknown argument: {$argument}");
        }
    }
    $database = DatabaseConnection::fromEnvironment();
    $summary = Transaction::run($database, static fn (): array => (new QuestionStatsRecorder($database))->rebuild($workspace));
    echo json_encode(['workspace' => $workspace ?? 'all'] + $summary) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Rebuilding question stats failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
