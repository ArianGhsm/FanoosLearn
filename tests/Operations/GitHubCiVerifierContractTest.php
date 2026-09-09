<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Operations\GitHubCiVerifier;
use RuntimeException;

final class GitHubCiVerifierContractTest
{
    public function run(): int
    {
        $sha = str_repeat('a', 40);
        $this->greenWorkflowIsAccepted($sha);
        $this->missingRequiredJobFailsClosed($sha);
        $this->wrongWorkflowShaFailsClosed($sha);

        return 3;
    }

    private function greenWorkflowIsAccepted(string $sha): void
    {
        $verifier = new GitHubCiVerifier(str_repeat('t', 24), $this->fixtureFetcher($sha));
        $verifier->assertGreen($sha);
    }

    private function missingRequiredJobFailsClosed(string $sha): void
    {
        $fetcher = $this->fixtureFetcher($sha, omitJob: 'web-ux');
        $verifier = new GitHubCiVerifier(str_repeat('t', 24), $fetcher);
        $this->expectRuntimeException(fn () => $verifier->assertGreen($sha));
    }

    private function wrongWorkflowShaFailsClosed(string $sha): void
    {
        $verifier = new GitHubCiVerifier(str_repeat('t', 24), $this->fixtureFetcher(str_repeat('b', 40)));
        $this->expectRuntimeException(fn () => $verifier->assertGreen($sha));
    }

    private function fixtureFetcher(string $sha, ?string $omitJob = null): \Closure
    {
        return static function (string $url) use ($sha, $omitJob): array {
            if (str_contains($url, '/actions/runs?')) {
                return ['workflow_runs' => [[
                    'id' => 97,
                    'name' => 'CI',
                    'path' => '.github/workflows/ci.yml',
                    'head_sha' => $sha,
                    'head_branch' => 'main',
                    'event' => 'push',
                    'status' => 'completed',
                    'conclusion' => 'success',
                ]]];
            }

            $names = [
                'static-and-unit (8.2)',
                'static-and-unit (8.4)',
                'web-ux',
                'python-bot-worker',
                'mysql-integration',
            ];
            $jobs = [];
            foreach ($names as $name) {
                if ($name === $omitJob) {
                    continue;
                }
                $jobs[] = [
                    'name' => $name,
                    'head_sha' => $sha,
                    'head_branch' => 'main',
                    'workflow_name' => 'CI',
                    'status' => 'completed',
                    'conclusion' => 'success',
                ];
            }
            return ['jobs' => $jobs];
        };
    }

    private function expectRuntimeException(\Closure $callback): void
    {
        try {
            $callback();
        } catch (RuntimeException) {
            return;
        }
        throw new RuntimeException('Expected GitHub CI verification to fail closed.');
    }
}
