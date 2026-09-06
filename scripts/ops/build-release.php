<?php

declare(strict_types=1);

use Fanoos\Platform\Operations\ProcessRunner;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    $trackedChanges = trim(ProcessRunner::run(['git', '-C', $root, 'status', '--porcelain', '--untracked-files=no']));
    if ($trackedChanges !== '') {
        throw new RuntimeException('Release builds require a clean tracked worktree.');
    }
    $requested = $argv[1] ?? 'HEAD';
    $commit = trim(ProcessRunner::run(['git', '-C', $root, 'rev-parse', '--verify', $requested . '^{commit}']));
    if (!preg_match('/^[a-f0-9]{40}$/', $commit)) {
        throw new RuntimeException('Requested release does not resolve to an exact commit.');
    }

    $outputRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'releases';
    if (!is_dir($outputRoot) && !mkdir($outputRoot, 0750, true) && !is_dir($outputRoot)) {
        throw new RuntimeException('Release output directory could not be created.');
    }
    $artifact = $outputRoot . DIRECTORY_SEPARATOR . 'fanoos-' . substr($commit, 0, 12) . '.tar.gz';
    ProcessRunner::run(['git', '-C', $root, 'archive', '--format=tar.gz', $commit], $artifact);
    $digest = hash_file('sha256', $artifact);
    if ($digest === false) {
        throw new RuntimeException('Release artifact checksum could not be computed.');
    }
    $manifestPath = $artifact . '.json';
    file_put_contents($manifestPath, json_encode([
        'format' => 1,
        'commit' => $commit,
        'artifact' => basename($artifact),
        'sha256' => $digest,
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);

    echo $artifact . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Release build failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
