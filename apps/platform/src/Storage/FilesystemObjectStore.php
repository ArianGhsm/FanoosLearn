<?php

declare(strict_types=1);

namespace Fanoos\Platform\Storage;

use RuntimeException;

final class FilesystemObjectStore
{
    private string $root;

    public function __construct(string $root)
    {
        if ($root === '') {
            throw new RuntimeException('Object storage root is required.');
        }
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Object storage root could not be created.');
        }
        $resolved = realpath($root);
        if ($resolved === false || !is_writable($resolved)) {
            throw new RuntimeException('Object storage root is not writable.');
        }
        $this->root = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function put(ObjectAddress $address, InspectedUpload $upload): string
    {
        $path = $this->pathForKey($address->key());
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Object directory could not be created.');
        }

        if (is_file($path)) {
            $existingDigest = hash_file('sha256', $path);
            if (hash_equals($upload->sha256, is_string($existingDigest) ? $existingDigest : '')) {
                return $address->key();
            }
            throw new RuntimeException('Object key already contains different bytes.');
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(12));
        try {
            if (!copy($upload->sourcePath, $temporary)) {
                throw new RuntimeException('Object bytes could not be staged.');
            }
            $writtenDigest = hash_file('sha256', $temporary);
            if (!is_string($writtenDigest) || !hash_equals($upload->sha256, $writtenDigest)) {
                throw new RuntimeException('Object checksum changed while writing.');
            }
            @chmod($temporary, 0640);
            if (!@link($temporary, $path)) {
                if (is_file($path)) {
                    $racedDigest = hash_file('sha256', $path);
                    if (is_string($racedDigest) && hash_equals($upload->sha256, $racedDigest)) {
                        return $address->key();
                    }
                    throw new RuntimeException('Object key concurrently received different bytes.');
                }
                throw new RuntimeException('Object could not be atomically finalized.');
            }
            unlink($temporary);
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return $address->key();
    }

    /** @return resource */
    public function openRead(ObjectAddress $address)
    {
        $stream = @fopen($this->pathForKey($address->key()), 'rb');
        if ($stream === false) {
            throw new RuntimeException('Object is unavailable.');
        }

        return $stream;
    }

    private function pathForKey(string $key): string
    {
        if (!preg_match('#^(?:private|protected|public)/[0-9a-f]{2}/[0-9a-f-]{36}/[0-9a-f]{2}/[0-9a-f-]{36}/[0-9a-f-]{36}\.bin$#', $key)) {
            throw new RuntimeException('Object key is invalid.');
        }

        return $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    }
}
