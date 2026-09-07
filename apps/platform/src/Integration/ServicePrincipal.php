<?php

declare(strict_types=1);

namespace Fanoos\Platform\Integration;

final class ServicePrincipal
{
    /** @param list<string> $allowedActions */
    public function __construct(
        public readonly string $serviceId,
        public readonly string $serviceKey,
        public readonly string $serviceType,
        public readonly array $allowedActions,
    ) {
    }

    public function allows(string $action): bool
    {
        return in_array($action, $this->allowedActions, true);
    }
}
