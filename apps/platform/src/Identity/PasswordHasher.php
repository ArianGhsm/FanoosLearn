<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

final class PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $digest): bool
    {
        if (str_starts_with($digest, 'pbkdf2_sha256$')) {
            $parts = explode('$', $digest, 4);
            if (count($parts) !== 4 || !ctype_digit($parts[1])) {
                return false;
            }
            $iterations = (int) $parts[1];
            if ($iterations < 100000 || $iterations > 2000000 || $parts[2] === '') {
                return false;
            }
            $expected = base64_decode(rawurldecode($parts[3]), true);
            if ($expected === false || $expected === '') {
                return false;
            }
            $actual = hash_pbkdf2('sha256', $password, $parts[2], $iterations, strlen($expected), true);
            return hash_equals($expected, $actual);
        }

        $info = password_get_info($digest);
        return ($info['algoName'] ?? 'unknown') !== 'unknown' && password_verify($password, $digest);
    }

    public function needsRehash(string $digest): bool
    {
        return str_starts_with($digest, 'pbkdf2_sha256$') || password_needs_rehash($digest, PASSWORD_DEFAULT);
    }
}
