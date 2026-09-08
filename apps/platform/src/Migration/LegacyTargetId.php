<?php

declare(strict_types=1);

namespace Fanoos\Platform\Migration;

use RuntimeException;

final class LegacyTargetId
{
    public static function derive(
        string $hmacKey,
        string $sourceSystemKey,
        string $entityType,
        string $sourceKey,
    ): string {
        if (strlen($hmacKey) < 16) {
            throw new RuntimeException('Legacy target ID derivation requires an HMAC key of at least 16 bytes.');
        }
        if ($sourceSystemKey === '' || $entityType === '' || $sourceKey === '') {
            throw new RuntimeException('Legacy target ID derivation requires non-empty source and entity identities.');
        }

        $bytes = substr(hash_hmac(
            'sha256',
            $sourceSystemKey . "\0" . $entityType . "\0" . $sourceKey,
            $hmacKey,
            true,
        ), 0, 16);

        // Deterministic UUID namespace: version 5 bits + RFC 4122 variant.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x50);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
