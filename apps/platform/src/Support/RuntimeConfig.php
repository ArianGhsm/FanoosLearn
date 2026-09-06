<?php

declare(strict_types=1);

namespace Fanoos\Platform\Support;

use RuntimeException;

final class RuntimeConfig
{
    /** @param array<string, scalar|null> $fileValues */
    private function __construct(private readonly array $fileValues)
    {
    }

    public static function load(): self
    {
        $configuredPath = getenv('FANOOS_CONFIG_FILE');
        $path = is_string($configuredPath) && $configuredPath !== '' ? $configuredPath : null;

        if ($path === null) {
            $userDirectory = getenv('HOME');
            if (is_string($userDirectory) && $userDirectory !== '') {
                $candidate = rtrim($userDirectory, '/\\') . '/fanoos/shared/config.php';
                if (is_file($candidate)) {
                    $path = $candidate;
                }
            }
        }

        if ($path === null) {
            return new self([]);
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('FANOOS_CONFIG_FILE is not a readable regular file.');
        }

        $values = require $path;
        if (!is_array($values)) {
            throw new RuntimeException('The runtime config file must return an array.');
        }

        foreach ($values as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                throw new RuntimeException('The runtime config file may contain scalar values only.');
            }
        }

        /** @var array<string, scalar|null> $values */
        return new self($values);
    }

    public function optionalString(string $name, ?string $default = null): ?string
    {
        $environmentValue = getenv($name);
        if ($environmentValue !== false) {
            return $environmentValue;
        }

        if (!array_key_exists($name, $this->fileValues) || $this->fileValues[$name] === null) {
            return $default;
        }

        return (string) $this->fileValues[$name];
    }

    public function requireString(string $name): string
    {
        $value = $this->optionalString($name);
        if ($value === null || $value === '') {
            throw new RuntimeException("{$name} is required.");
        }

        return $value;
    }

    public function positiveInt(string $name, int $default): int
    {
        $value = $this->optionalString($name, (string) $default);
        if ($value === null || !preg_match('/^[1-9][0-9]*$/', $value)) {
            throw new RuntimeException("{$name} must be a positive integer.");
        }

        return (int) $value;
    }
}
