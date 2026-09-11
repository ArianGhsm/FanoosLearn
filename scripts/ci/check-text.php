<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$extensions = ['php', 'sql', 'md', 'yml', 'yaml', 'json', 'sh', 'example', 'txt'];
$output = shell_exec('git -C ' . escapeshellarg($root) . ' ls-files --cached --others --exclude-standard -z');
if (!is_string($output)) {
    fwrite(STDERR, "Could not enumerate tracked text files.\n");
    exit(1);
}
$violations = [];
foreach (array_filter(explode("\0", $output)) as $relative) {
    // legacy/ is imported Dentistry1402TUMS source kept only to migrate from. It is
    // outside the execution path, and its historical encoding is not FANOOS's to fix;
    // files graduate into this guard when they are moved into FANOOS proper.
    if (str_starts_with($relative, 'legacy/')) {
        continue;
    }
    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, true) && !str_starts_with(basename($relative), '.env')) {
        continue;
    }
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    if ($contents === false || str_starts_with($contents, "\xEF\xBB\xBF") || preg_match('//u', $contents) !== 1) {
        $violations[] = $relative;
    }
}
if ($violations !== []) {
    fwrite(STDERR, "Invalid UTF-8 or BOM in:\n" . implode("\n", $violations) . "\n");
    exit(1);
}
echo "PASS tracked text encoding\n";
