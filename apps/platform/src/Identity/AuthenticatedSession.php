<?php

declare(strict_types=1);

namespace Fanoos\Platform\Identity;

final class AuthenticatedSession
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userId,
        public readonly string $token,
        public readonly string $csrfToken,
        public readonly ?string $selectedWorkspaceId,
        public readonly string $expiresAt,
    ) {
    }
}
