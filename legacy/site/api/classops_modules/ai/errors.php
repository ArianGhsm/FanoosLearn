<?php
declare(strict_types=1);

final class DentClassOpsAiException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly int $httpStatus = 422
    ) {
        parent::__construct($message);
    }
}

function classops_ai_error(string $code, string $message, int $status = 422): never
{
    throw new DentClassOpsAiException($code, $message, $status);
}
