<?php

declare(strict_types=1);

namespace Fanoos\Platform\Storage;

final class InspectedUpload
{
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $displayName,
        public readonly string $detectedMime,
        public readonly int $bytes,
        public readonly string $sha256,
    ) {
    }
}
