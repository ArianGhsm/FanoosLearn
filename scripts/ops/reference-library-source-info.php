<?php

declare(strict_types=1);

function fail(string $message, int $status = 1): never
{
    fwrite(STDERR, 'REFERENCE SOURCE CHECK FAILED: ' . $message . PHP_EOL);
    exit($status);
}

$drivePath = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--drive-path=')) {
        $drivePath = substr($argument, strlen('--drive-path='));
    } else {
        fail('Usage: php scripts/ops/reference-library-source-info.php --drive-path=<mounted-drive-pdf>', 2);
    }
}
if (!is_string($drivePath) || $drivePath === '') {
    fail('Usage: php scripts/ops/reference-library-source-info.php --drive-path=<mounted-drive-pdf>', 2);
}

$realPath = realpath($drivePath);
if ($realPath === false || !is_file($realPath) || is_link($drivePath) || !is_readable($realPath)
    || !pathIsInsideDriveMount($realPath)) {
    fail('The source must be a readable regular file inside a mounted Google Drive filesystem.');
}

$bytes = filesize($realPath);
$digest = hash_file('sha256', $realPath);
$header = file_get_contents($realPath, false, null, 0, 8);
if ($bytes === false || $bytes < 1 || !is_string($digest) || !is_string($header) || !str_starts_with($header, '%PDF-')) {
    fail('The mounted Drive source is not a complete PDF file.');
}

echo json_encode([
    'file' => basename($realPath),
    'bytes' => $bytes,
    'sha256' => $digest,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

function pathIsInsideDriveMount(string $candidate): bool
{
    $mountInfo = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($mountInfo)) {
        return false;
    }

    foreach ($mountInfo as $line) {
        $separator = strpos($line, ' - ');
        if ($separator === false) {
            continue;
        }
        $left = preg_split('/\s+/', substr($line, 0, $separator));
        $right = preg_split('/\s+/', substr($line, $separator + 3));
        if (!is_array($left) || !isset($left[4]) || !is_array($right) || !isset($right[0], $right[1])
            || preg_match('/(?:drive|google|rclone)/i', $right[0] . ' ' . $right[1]) !== 1) {
            continue;
        }
        $mountPoint = strtr($left[4], ['\\040' => ' ', '\\011' => "\t", '\\134' => '\\']);
        $realMount = realpath($mountPoint);
        if ($realMount !== false
            && str_starts_with($candidate, rtrim($realMount, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }

    return false;
}
