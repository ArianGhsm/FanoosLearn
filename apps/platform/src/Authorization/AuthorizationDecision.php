<?php

declare(strict_types=1);

namespace Fanoos\Platform\Authorization;

final class AuthorizationDecision
{
    /** @param list<string> $matchedAssignmentIds */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $decisionCode,
        public readonly string $policyVersion,
        public readonly array $matchedAssignmentIds = [],
    ) {
    }

    public static function deny(string $code): self
    {
        return new self(false, $code, ScopeAuthorizer::POLICY_VERSION);
    }
}
