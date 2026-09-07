<?php

declare(strict_types=1);

namespace Fanoos\Platform\Http;

use Fanoos\Platform\Support\PlatformException;

final class Request
{
    /** @param array<string, string> $headers @param array<string, string> $query @param array<string, mixed> $body */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly string $source = 'unknown',
        public readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') && is_string($value)) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $raw = file_get_contents('php://input');
        $raw = is_string($raw) ? $raw : '';
        $body = [];
        if (trim($raw) !== '') {
            try {
                $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new PlatformException('invalid_json', 'Request body is not valid JSON.', 400);
            }
            if (!is_array($decoded)) {
                throw new PlatformException('invalid_json', 'Request body must be a JSON object.', 400);
            }
            $body = $decoded;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            parse_url($uri, PHP_URL_PATH) ?: '/',
            $headers,
            array_map('strval', $_GET),
            $body,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            $raw,
        );
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function bearerToken(): string
    {
        $authorization = $this->header('authorization');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $match)) {
            return trim($match[1]);
        }
        return isset($_COOKIE['fanoos_session']) ? (string) $_COOKIE['fanoos_session'] : '';
    }
}
