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

$patterns = [
    'private key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
    'GitHub token' => '/\bgh[pousr]_[A-Za-z0-9]{30,}\b/',
    'Telegram-style bot token' => '/\b[0-9]{8,12}:[A-Za-z0-9_-]{30,}\b/',
    'AWS access key' => '/\bAKIA[0-9A-Z]{16}\b/',
];
$violations = [];
foreach (array_filter(explode("\0", $output)) as $relative) {
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    if ($contents === false || str_contains($contents, "\0")) {
        continue;
    }
    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $contents)) {
            $violations[] = "{$relative}: {$label}";
        }
    }
}
if ($violations !== []) {
    fwrite(STDERR, "Potential tracked secrets detected:\n" . implode("\n", $violations) . "\n");
    exit(1);
}
echo "PASS tracked secret patterns\n";
