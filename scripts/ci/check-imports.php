<?php

declare(strict_types=1);

/**
 * Verifies that every `use Fanoos\...;` statement names a class that exists.
 *
 * `php -l` only parses; it never resolves a name. A `use` pointing at the
 * wrong namespace is therefore invisible to every static check in this
 * repository and only fails when that line finally runs -- which, for an
 * operator script, means half way through a production import. That is
 * exactly how it was found.
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

// tests/run.php registers this autoloader itself; without it here, every
// Fanoos\Tests\ import would look unresolvable.
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Fanoos' . chr(92) . 'Tests' . chr(92);
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $root . '/tests/' . str_replace(chr(92), '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$roots = ['apps', 'scripts', 'tests'];
$failures = [];
$checked = 0;

foreach ($roots as $directory) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (!preg_match_all('/^use\s+(Fanoos\\\\[A-Za-z0-9_\\\\]+)\s*;/m', $source, $matches)) {
            continue;
        }
        foreach ($matches[1] as $symbol) {
            $checked++;
            // A trait or interface is just as importable as a class.
            if (class_exists($symbol) || interface_exists($symbol) || trait_exists($symbol) || enum_exists($symbol)) {
                continue;
            }
            $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $failures[] = $relative . ': ' . $symbol;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, 'FAIL unresolvable imports:' . PHP_EOL);
    foreach ($failures as $failure) {
        fwrite(STDERR, '  ' . str_replace('\\', '/', $failure) . PHP_EOL);
    }
    exit(1);
}

echo "PASS {$checked} Fanoos imports resolve" . PHP_EOL;
