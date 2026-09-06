<?php

declare(strict_types=1);

namespace Fanoos\Platform\Http;

final class Response
{
    /** @param array<string, mixed> $payload @param list<string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $payload,
        public readonly array $headers = [],
    ) {
    }

    public function emit(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        foreach ($this->headers as $header) {
            header($header, false);
        }
        echo json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
