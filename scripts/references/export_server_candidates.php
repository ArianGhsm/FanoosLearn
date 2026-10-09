<?php

declare(strict_types=1);

/**
 * Read-only export of currently unsourced exam questions for *research*.
 *
 * Deliberately NOT a fanoos.bank.sitting/1 import file; it omits existing
 * published content and cannot be imported to change questions. Its output
 * is private and must remain under the server research workspace.
 *
 * Usage:
 *   FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php \
 *     scripts/references/export_server_candidates.php \
 *     --workspace=<authorized-dentistry-workspace-uuid> \
 *     --year=1405 --subject=community-dentistry \
 *     --out=/srv/fanoos/shared/research/bank-sittings/1405/community-study.json
 */
use Fanoos\Platform\Support\DatabaseConnection;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $args = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--(workspace|year|round|subject|out)=(.*)$/D', $argument, $m) !== 1) {
            throw new RuntimeException('Unknown argument');
        }
        $args[$m[1]] = $m[2];
    }
    $workspace = (string) ($args['workspace'] ?? '');
    $year = filter_var($args['year'] ?? '', FILTER_VALIDATE_INT);
    $round = filter_var($args['round'] ?? '1', FILTER_VALIDATE_INT);
    $subject = (string) ($args['subject'] ?? '');
    $output = (string) ($args['out'] ?? '');
    if (!preg_match('/^[0-9a-f-]{36}$/', $workspace)
        || $year === false || $year < 1390 || $year > 1500 || $round === false || $round < 1 || $round > 9
        || !preg_match('/^[a-z][a-z0-9-]{0,59}$/', $subject)) {
        throw new RuntimeException('Invalid year, round, or subject');
    }
    $target = $output === '' ? false : realpath(dirname($output));
    $research = realpath('/srv/fanoos/shared/research');
    if ($target === false || $research === false
        || !str_starts_with($target . '/', $research . '/')
        || is_link($output) || basename($output) === '' || !str_ends_with($output, '.json')) {
        throw new RuntimeException('Output must be a regular JSON path inside an existing protected research subdirectory');
    }
    $database = DatabaseConnection::fromEnvironment();
    $query = $database->prepare(<<<'SQL'
SELECT q.id, q.number_in_sitting AS number, q.question_key, q.stem
FROM bank_questions q
JOIN bank_exam_sittings si ON si.id=q.sitting_id AND si.workspace_id=q.workspace_id
JOIN bank_exam_types et ON et.id=si.exam_type_id AND et.workspace_id=q.workspace_id
JOIN bank_subjects bs ON bs.id=q.subject_id AND bs.workspace_id=q.workspace_id
WHERE q.workspace_id=:workspace AND et.type_key='residency' AND si.exam_year=:year AND si.exam_round=:round
  AND bs.subject_key=:subject
  AND NOT EXISTS (SELECT 1 FROM bank_question_sources x WHERE x.question_id=q.id AND x.workspace_id=q.workspace_id)
ORDER BY q.number_in_sitting ASC
SQL);
    $query->execute(['workspace' => $workspace, 'year' => $year, 'round' => $round, 'subject' => $subject]);
    $candidates = $query->fetchAll();
    $choices = $database->prepare('SELECT position,text,image FROM bank_question_choices WHERE question_id=:id AND workspace_id=:workspace ORDER BY position');
    $answers = $database->prepare('SELECT choice_position,also_correct_positions,status FROM bank_official_answers WHERE question_id=:id AND workspace_id=:workspace ORDER BY recorded_at DESC,id DESC LIMIT 1');
    $questions = [];
    foreach ($candidates as $q) {
        $choices->execute(['id' => $q['id'], 'workspace' => $workspace]);
        $options = [];
        foreach ($choices->fetchAll() as $option) {
            if ($option['image'] !== null) {
                $options[] = ['text' => (string) $option['text'], 'image' => (string) $option['image']];
            } else {
                $options[] = (string) $option['text'];
            }
        }
        $answers->execute(['id' => $q['id'], 'workspace' => $workspace]);
        $a = $answers->fetch();
        if (!is_array($a)) {
            continue;
        }
        $questions[] = [
            'number' => (int) $q['number'],
            'subject' => $subject,
            'stem' => (string) $q['stem'],
            'choices' => $options,
            'answer' => [
                'choice' => $a['choice_position'] === null ? null : (int) $a['choice_position'],
                'also_correct' => $a['also_correct_positions'] ? json_decode((string) $a['also_correct_positions'], true) : [],
                'status' => (string) $a['status'],
            ],
        ];
    }
    $sitting = [
        'format' => 'fanoos.classification.study-only/1',
        'exam_type' => 'residency', 'year' => $year, 'round' => $round,
        'notes' => 'Read-only DB export for reference research. NEVER send to bank importer.',
        'questions' => $questions,
    ];
    $payload = json_encode($sitting, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $temp = tempnam($target, '.fanoos-study-');
    if ($temp === false) {
        throw new RuntimeException('Cannot create private temporary file');
    }
    try {
        chmod($temp, 0600);
        if (file_put_contents($temp, $payload, LOCK_EX) !== strlen($payload)) {
            throw new RuntimeException('Cannot write private export');
        }
        if (!rename($temp, $output)) {
            throw new RuntimeException('Cannot atomically publish private export');
        }
    } finally {
        if (is_file($temp)) {
            unlink($temp);
        }
    }
    echo json_encode(['year' => $year, 'subject' => $subject, 'questions' => count($questions),
        'file_sha256' => hash('sha256', $payload), 'format' => $sitting['format']], JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Study export failed: ' . $error->getMessage() . "\n");
    exit(1);
}
