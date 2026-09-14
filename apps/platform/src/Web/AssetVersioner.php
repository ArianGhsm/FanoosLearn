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
}
