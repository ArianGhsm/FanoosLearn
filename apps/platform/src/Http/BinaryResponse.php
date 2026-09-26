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
        // Protected media is a download nobody should cache; a question image
        // is shown inline, and caching it privately for a while keeps the
        // runner from re-fetching it every time it redraws the question.
        public readonly string $disposition = 'attachment; filename="fanoos-protected.pdf"',
        public readonly string $cacheControl = 'no-store, private',
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
        header('Cache-Control: ' . $this->cacheControl);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . $this->disposition);
        foreach ($this->headers as $header) {
            header($header, false);
        }
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            fclose($this->stream);
            throw new RuntimeException('Binary response output stream is unavailable.');
        }
        try {
            $copied = stream_copy_to_stream($this->stream, $output);
            if ($copied === false || $copied !== $this->length) {
                throw new RuntimeException('Binary response could not be streamed completely.');
            }
        } finally {
            fclose($this->stream);
            fclose($output);
        }
    }
}
