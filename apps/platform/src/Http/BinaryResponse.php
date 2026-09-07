<?php

declare(strict_types=1);

namespace Fanoos\Platform\Http;

use RuntimeException;

final class BinaryResponse
{
    /** @param resource $stream @param list<string> $headers */
    public function __construct(
        public readonly int $status,
        private $stream,
        public readonly string $mime,
        public readonly int $length,
        public readonly array $headers = [],
    ) {
        if (!is_resource($stream) || $length < 0 || !preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $mime)) {
            throw new RuntimeException('Binary response is invalid.');
        }
    }

    public function emit(): void
    {
        http_response_code($this->status);
        header('Content-Type: ' . $this->mime);
        header('Content-Length: ' . $this->length);
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="fanoos-protected.pdf"');
        foreach ($this->headers as $header) {
            header($header, false);
        }
        try {
            $copied = stream_copy_to_stream($this->stream, fopen('php://output', 'wb'));
            if ($copied === false) {
                throw new RuntimeException('Binary response could not be streamed.');
            }
        } finally {
            fclose($this->stream);
        }
    }
}
