<?php

declare(strict_types=1);

namespace Fanoos\Platform\Messaging;

use RuntimeException;

final class ChannelSubjectProtector
{
    private string $encryptionKey;
    private string $digestKey;

    public function __construct(string $keyMaterial)
    {
        if (strlen($keyMaterial) < 32) {
            throw new RuntimeException('Messaging subject protection key must contain at least 32 bytes.');
        }
        $this->encryptionKey = hash_hmac('sha256', 'encryption', $keyMaterial, true);
        $this->digestKey = hash_hmac('sha256', 'digest', $keyMaterial, true);
    }

    public function digest(string $platform, string $subject): string
    {
        $subject = $this->normalize($subject);
        return hash_hmac('sha256', $platform . "\n" . $subject, $this->digestKey, true);
    }

    public function encrypt(string $platform, string $subject): string
    {
        $subject = $this->normalize($subject);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $subject,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $platform,
            16,
        );
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Messaging subject encryption failed.');
        }
        return $nonce . $tag . $ciphertext;
    }

    public function decrypt(string $platform, string $ciphertext): string
    {
        if (strlen($ciphertext) < 29) {
            throw new RuntimeException('Messaging subject ciphertext is invalid.');
        }
        $nonce = substr($ciphertext, 0, 12);
        $tag = substr($ciphertext, 12, 16);
        $payload = substr($ciphertext, 28);
        $subject = openssl_decrypt(
            $payload,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $platform,
        );
        if (!is_string($subject)) {
            throw new RuntimeException('Messaging subject decryption failed.');
        }
        return $this->normalize($subject);
    }

    private function normalize(string $subject): string
    {
        $subject = trim($subject);
        if ($subject === '' || strlen($subject) > 160 || preg_match('/[\x00-\x1F\x7F]/', $subject)) {
            throw new RuntimeException('Messaging platform subject is invalid.');
        }
        return $subject;
    }
}
