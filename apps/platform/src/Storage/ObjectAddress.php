<?php

declare(strict_types=1);

namespace Fanoos\Platform\Storage;

use InvalidArgumentException;

final class ObjectAddress
{
    private const CLASSIFICATIONS = ['private', 'protected', 'public'];

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $objectId,
        public readonly string $versionId,
        public readonly string $classification = 'private',
    ) {
        foreach (['workspaceId' => $workspaceId, 'objectId' => $objectId, 'versionId' => $versionId] as $name => $value) {
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
                throw new InvalidArgumentException("{$name} must be a UUID.");
            }
        }
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            throw new InvalidArgumentException('Unknown object classification.');
        }
    }

    public function key(): string
    {
        $workspaceCompact = str_replace('-', '', strtolower($this->workspaceId));
        $objectCompact = str_replace('-', '', strtolower($this->objectId));

        return implode('/', [
            $this->classification,
            substr($workspaceCompact, 0, 2),
            strtolower($this->workspaceId),
            substr($objectCompact, 0, 2),
            strtolower($this->objectId),
            strtolower($this->versionId) . '.bin',
        ]);
    }
}
