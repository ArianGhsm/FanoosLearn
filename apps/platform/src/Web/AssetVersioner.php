<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * Appends a cache-busting version to every asset URL.
 *
 * In a release the version is the deployed commit, so one release ships one
 * version for every file and a browser holding last release's CSS fetches
 * the new one. Locally it falls back to the file's own mtime+size, so an
 * edit is visible on reload without touching an environment variable.
 *
 * This matters more than it looks: releases are immutable directories behind
 * a symlink, so without a changing query string a returning visitor keeps a
 * stylesheet from a build that no longer exists.
 */
final class AssetVersioner
{
    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, string>|null */
    private ?array $modules = null;

    public function __construct(
        private readonly string $publicRoot,
        private readonly ?string $releaseVersion = null,
    ) {
    }

    public function url(string $path): string
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        $version = $this->releaseVersion;
        if ($version === null || !preg_match('/^[A-Za-z0-9._-]{1,80}$/', $version)) {
            $file = $this->publicRoot . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $version = is_file($file)
                ? ((string) filemtime($file)) . '-' . ((string) filesize($file))
                : 'dev';
        }

        return $this->cache[$path] = $path . '?v=' . rawurlencode($version);
    }

    /**
     * Every JavaScript module under /assets/web, mapped to its versioned URL.
     *
     * A page's top-level scripts get their version from url(), but the
     * modules they import are named by plain relative specifiers
     * ('./runner-view.js'), and nginx serves every asset as immutable for a
     * year (ops/nginx/fanoos-performance.conf). Without this map a returning
     * browser re-ran last month's runner-view.js under this release's
     * runner.js. The page emits it as an import map, which the browser
     * applies to every import, nested ones included.
     *
     * @return array<string, string>
     */
    public function moduleMap(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $base = $this->publicRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'web';
        $paths = [];
        if (is_dir($base)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'js') {
                    $relative = substr($file->getPathname(), strlen($base));
                    $paths[] = '/assets/web' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                }
            }
        }
        sort($paths);

        $map = [];
        foreach ($paths as $path) {
            $map[$path] = $this->url($path);
        }

        return $this->modules = $map;
    }
}
