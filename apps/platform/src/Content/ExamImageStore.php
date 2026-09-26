<?php

declare(strict_types=1);

namespace Fanoos\Platform\Content;

use Fanoos\Platform\Support\PlatformException;
use RuntimeException;

/**
 * The images questions show: a stem photo ("which lesion is this?") or a
 * picture as an option.
 *
 * Content-addressed: an image is stored under the SHA-256 of its bytes, so
 * a question definition refers to exactly one immutable file, re-importing
 * the same bank writes nothing new, and two questions sharing a figure share
 * one file. The key is all a definition holds; the student-facing API never
 * sees it -- it asks for "the stem image of question q… in assessment a…" and
 * ExamService decides whether that student may see it.
 *
 * Only JPEG, PNG and WebP, recognised by their magic bytes rather than a
 * file extension, so nothing else can be stored here and later served with
 * an image content type.
 */
final class ExamImageStore
{
    public const KEY_PATTERN = '/^[a-f0-9]{64}\.(jpg|png|webp)$/';
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    private readonly string $root;

    public function __construct(string $storageRoot)
    {
        $root = rtrim($storageRoot, '/\\');
        if ($root === '') {
            throw new RuntimeException('Exam image storage root is not configured.');
        }
        $this->root = $root . '/exam-images';
    }

    /** Stores the bytes if they are not already there, and returns their key. */
    public function put(string $bytes): string
    {
        $extension = self::sniff($bytes);
        if ($extension === null) {
            throw new PlatformException('exam_image_type_invalid', 'Only JPEG, PNG and WebP images can be attached to a question.', 422);
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new PlatformException('exam_image_too_large', 'A question image may be at most 5 MB.', 422);
        }

        $key = hash('sha256', $bytes) . '.' . $extension;
        $path = $this->path($key);
        if (is_file($path)) {
            return $key;
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the exam image directory.');
        }
        // Written aside and renamed into place, so a reader never sees half a file.
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes)) {
            @unlink($temporary);
            throw new RuntimeException('Could not write an exam image.');
        }
        chmod($temporary, 0640);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Could not store an exam image.');
        }

        return $key;
    }

    public function has(string $key): bool
    {
        return preg_match(self::KEY_PATTERN, $key) === 1 && is_file($this->path($key));
    }

    /**
     * @return array{stream: resource, mime: string, length: int}
     */
    public function open(string $key): array
    {
        if (preg_match(self::KEY_PATTERN, $key, $match) !== 1) {
            throw new PlatformException('exam_image_not_found', 'Image was not found.', 404);
        }
        $path = $this->path($key);
        $stream = is_file($path) ? fopen($path, 'rb') : false;
        if ($stream === false) {
            throw new PlatformException('exam_image_not_found', 'Image was not found.', 404);
        }

        return ['stream' => $stream, 'mime' => self::MIME[$match[1]], 'length' => (int) filesize($path)];
    }

    /** jpg / png / webp from the leading bytes, or null. */
    public static function sniff(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'png';
        }
        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    /** Two levels of fan-out, so no directory holds more than a few hundred files. */
    private function path(string $key): string
    {
        return $this->root . '/' . substr($key, 0, 2) . '/' . substr($key, 2, 2) . '/' . $key;
    }
}
