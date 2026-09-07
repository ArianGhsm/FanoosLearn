<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

interface DeploymentExecutor
{
    /** @return array{current_sha:string,candidate_sha:string,noop:bool} */
    public function preflight(string $requestId, string $targetKey): array;

    /** @return array{backup_id:string} */
    public function backup(string $requestId, string $targetKey, string $currentSha, string $candidateSha): array;

    public function test(string $requestId, string $targetKey, string $candidateSha): void;

    public function migrate(string $requestId, string $targetKey, string $candidateSha): void;

    public function activate(string $requestId, string $targetKey, string $candidateSha): void;

    public function restart(string $requestId, string $targetKey, string $candidateSha): void;

    public function health(string $requestId, string $targetKey, string $candidateSha): void;

    public function rollback(string $requestId, string $targetKey, string $rollbackSha): void;
}
