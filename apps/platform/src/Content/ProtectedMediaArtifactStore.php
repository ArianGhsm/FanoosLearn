<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use RuntimeException;

final class ProtectedMediaArtifactStore
{
    private string $root;

    public function __construct(string $root)
    {
        if ($root === '') {
            throw new RuntimeException('Protected media artifact root is required.');
        }
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Protected media artifact root could not be created.');
        }
        $resolved = realpath($root);
        if ($resolved === false || !is_writable($resolved)) {
            throw new RuntimeException('Protected media artifact root is not writable.');
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function put(string $artifactId, string $bytes, string $checksum): string
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $artifactId) || !preg_match('/^[0-9a-f]{64}$/', $checksum)) {
            throw new RuntimeException('Protected media artifact identity is invalid.');
        }
        $key = 'artifacts/' . strtolower(substr(str_replace('-', '', $artifactId), 0, 2)) . '/' . strtolower($artifactId) . '.pdf';
        $path = $this->pathForKey($key);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Protected media artifact directory could not be created.');
        }
        if (is_file($path)) {
            $existing = hash_file('sha256', $path);
            if (is_string($existing) && hash_equals($checksum, $existing)) {
                return $key;
            }
            throw new RuntimeException('Protected media artifact key already contains different bytes.');
        }
        $temporary = $directory . DIRECTORY_SEPARATOR . '.artifact-' . bin2hex(random_bytes(12));
        try {
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('Protected media artifact could not be staged.');
            }
            $written = hash_file('sha256', $temporary);
            if (!is_string($written) || !hash_equals($checksum, $written)) {
                throw new RuntimeException('Protected media artifact checksum changed while writing.');
            }
            @chmod($temporary, 0640);
            if (!@link($temporary, $path)) {
                if (is_file($path)) {
                    $raced = hash_file('sha256', $path);
                    if (is_string($raced) && hash_equals($checksum, $raced)) {
                        return $key;
                    }
                }
                throw new RuntimeException('Protected media artifact could not be finalized atomically.');
            }
            @unlink($temporary);
            return $key;
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /** @return resource */
    public function openRead(string $key)
    {
        $stream = @fopen($this->pathForKey($key), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Protected media artifact is unavailable.');
        }
        return $stream;
    }

    public function delete(string $key): void
    {
        $path = $this->pathForKey($key);
        if (is_file($path) && !@unlink($path)) {
            throw new RuntimeException('Protected media artifact could not be deleted.');
        }
    }

    private function pathForKey(string $key): string
    {
        if (!preg_match('#^artifacts/[0-9a-f]{2}/[0-9a-f-]{36}\.pdf$#', $key)) {
            throw new RuntimeException('Protected media artifact key is invalid.');
        }
        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    }
}
