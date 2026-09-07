<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$process = proc_open(
    ['git', '-C', $root, 'ls-files', '--cached', '--others', '--exclude-standard', '-z'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not enumerate tracked files.\n");
    exit(1);
}
$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0 || !is_string($output)) {
    fwrite(STDERR, "Could not enumerate tracked files: {$errors}\n");
    exit(1);
}

$secretPatterns = [
    'private key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    'GitHub token' => '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/',
    'Telegram-style bot token' => '/\b[0-9]{8,12}:[A-Za-z0-9_-]{30,}\b/',
    'AWS access key' => '/\bAKIA[0-9A-Z]{16}\b/',
];

$prohibitedPathPatterns = [
    'real env file' => '#(^|/)\.env(?:\..+)?$#',
    'local operator state' => '#^\.codex-local/#',
    'server mirror' => '#^server-only/#',
    'runtime state tree' => '#^(?:runtime|storage|backups|logs|tmp)/#',
    'database state file' => '#\.(?:sqlite|sqlite3|db)(?:-(?:wal|shm))?$#i',
    'runtime log/pid/lock' => '#\.(?:log|pid|lock)$#i',
    'private key file' => '#\.(?:pem|key|p12|pfx)$#i',
];

$allowedPaths = [
    '.env.example',
    'database/docker-compose.test.yml',
];

$violations = [];
foreach (array_filter(explode("\0", $output)) as $relative) {
    $relative = str_replace('\\', '/', $relative);

    if (!in_array($relative, $allowedPaths, true)) {
        foreach ($prohibitedPathPatterns as $label => $pattern) {
            if (preg_match($pattern, $relative)) {
                $violations[] = "{$relative}: {$label}";
            }
        }
    }

    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    if ($contents === false || str_contains($contents, "\0")) {
        continue;
    }
    foreach ($secretPatterns as $label => $pattern) {
        if (preg_match($pattern, $contents)) {
            $violations[] = "{$relative}: {$label}";
        }
    }
}

$violations = array_values(array_unique($violations));
if ($violations !== []) {
    fwrite(STDERR, "Potential secret/runtime-state material detected:\n" . implode("\n", $violations) . "\n");
    exit(1);
}
echo "PASS secret and runtime-state guards\n";
