<?php

declare(strict_types=1);

namespace Fanoos\Platform\Storage;

use RuntimeException;

final class UploadInspector
{
    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'application/json',
        'application/pdf',
        'image/jpeg',
        'image/png',
        'text/plain',
    ];

    public function __construct(private readonly int $maxBytes)
    {
        if ($maxBytes < 1) {
            throw new RuntimeException('Upload limit must be positive.');
        }
    }

    public function inspect(string $sourcePath, string $clientName): InspectedUpload
    {
        if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath)) {
            throw new RuntimeException('Upload source must be a readable, non-symlink regular file.');
        }
        if ($clientName === '' || strlen($clientName) > 255 || preg_match('/[\x00-\x1F\x7F]/', $clientName)) {
            throw new RuntimeException('Upload display name is invalid.');
        }

        $bytes = filesize($sourcePath);
        if ($bytes === false || $bytes < 1 || $bytes > $this->maxBytes) {
            throw new RuntimeException('Upload size is outside the permitted range.');
        }

        $detector = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $detector->file($sourcePath);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('Detected upload type is not allowed.');
        }

        $prefix = file_get_contents($sourcePath, false, null, 0, 512);
        if ($prefix === false || preg_match('/<\?(?:php|=)|<script\b|<!doctype\s+html|<html\b/i', $prefix)) {
            throw new RuntimeException('Active content signature is not allowed.');
        }
        if ($mime === 'application/pdf' && !str_starts_with($prefix, '%PDF-')) {
            throw new RuntimeException('PDF signature does not match the detected type.');
        }
        if (in_array($mime, ['image/jpeg', 'image/png'], true) && @getimagesize($sourcePath) === false) {
            throw new RuntimeException('Image structure could not be verified.');
        }
        if ($mime === 'application/json') {
            $contents = file_get_contents($sourcePath);
            try {
                if ($contents === false) {
                    throw new \JsonException('Unreadable JSON.');
                }
                json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new RuntimeException('JSON upload is malformed.');
            }
        }

        $digest = hash_file('sha256', $sourcePath);
        if ($digest === false) {
            throw new RuntimeException('Upload checksum could not be computed.');
        }

        return new InspectedUpload($sourcePath, basename(str_replace('\\', '/', $clientName)), $mime, $bytes, $digest);
    }
}
