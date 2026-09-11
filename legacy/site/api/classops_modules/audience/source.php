<?php
declare(strict_types=1);

/**
 * Read-only source boundary for ClassOps audience resolution.
 *
 * Implementations may read canonical product state, but must not persist
 * ClassOps audience state or depend on delivery/platform links.
 */
interface DentClassOpsAudienceSourceV1
{
    /**
     * @return array{
     *   cohortKey:string,
     *   roster:array<int,array{studentNumber:string,cohortKey:string}>,
     *   identityCohorts:array<string,string>,
     *   selectors:array<string,array{kind:string,key:string,cohortKey:string,studentNumbers:array<int,string>,sourceRef:string}>,
     *   source:array{type:string,version:string}
     * }
     */
    public function loadAudienceContext(string $cohortKey): array;
}

final class DentClassOpsAudienceSourceException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}
