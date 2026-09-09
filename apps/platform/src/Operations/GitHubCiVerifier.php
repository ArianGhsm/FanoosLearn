<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use Closure;
use RuntimeException;

final class GitHubCiVerifier
{
    private const REPOSITORY = 'ArianGhsm/FanoosLearn';
    private const WORKFLOW_NAME = 'CI';
    private const WORKFLOW_PATH = '.github/workflows/ci.yml';
    private const REQUIRED_JOBS = [
        'static-and-unit (8.2)',
        'static-and-unit (8.4)',
        'web-ux',
        'python-bot-worker',
        'mysql-integration',
    ];

    /** @var Closure(string): array<string, mixed> */
    private Closure $fetchJson;

    public function __construct(private readonly string $token, ?Closure $fetchJson = null)
    {
        if (strlen($token) < 20) {
            throw new RuntimeException('GitHub updater credential is unavailable.');
        }

        $this->fetchJson = $fetchJson ?? fn (string $url): array => $this->requestJson($url);
    }

    public function assertGreen(string $sha): void
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
            throw new RuntimeException('CI verification requires an exact commit SHA.');
        }

        $runsUrl = 'https://api.github.com/repos/' . self::REPOSITORY
            . '/actions/runs?head_sha=' . rawurlencode($sha)
            . '&branch=main&event=push&per_page=20';
        $payload = ($this->fetchJson)($runsUrl);
        $runs = is_array($payload['workflow_runs'] ?? null) ? $payload['workflow_runs'] : [];

        $runId = null;
        foreach ($runs as $run) {
            if (!is_array($run)) {
                continue;
            }
            if (($run['head_sha'] ?? null) !== $sha
                || ($run['head_branch'] ?? null) !== 'main'
                || ($run['event'] ?? null) !== 'push'
                || ($run['name'] ?? null) !== self::WORKFLOW_NAME
                || ($run['path'] ?? null) !== self::WORKFLOW_PATH
                || ($run['status'] ?? null) !== 'completed'
                || ($run['conclusion'] ?? null) !== 'success') {
                continue;
            }
            if (is_int($run['id'] ?? null) || (is_string($run['id'] ?? null) && ctype_digit($run['id']))) {
                $runId = (int) $run['id'];
                break;
            }
        }

        if ($runId === null || $runId <= 0) {
            throw new RuntimeException('Required GitHub Actions workflow is not green for the canonical main SHA.');
        }

        $jobsUrl = 'https://api.github.com/repos/' . self::REPOSITORY
            . '/actions/runs/' . $runId . '/jobs?filter=latest&per_page=100';
        $jobsPayload = ($this->fetchJson)($jobsUrl);
        $jobs = is_array($jobsPayload['jobs'] ?? null) ? $jobsPayload['jobs'] : [];
        $results = [];
        foreach ($jobs as $job) {
            if (!is_array($job) || !is_string($job['name'] ?? null)) {
                continue;
            }
            if (($job['head_sha'] ?? null) !== $sha
                || ($job['head_branch'] ?? null) !== 'main'
                || ($job['workflow_name'] ?? null) !== self::WORKFLOW_NAME) {
                continue;
            }
            $results[(string) $job['name']] = [
                'status' => (string) ($job['status'] ?? ''),
                'conclusion' => (string) ($job['conclusion'] ?? ''),
            ];
        }

        foreach (self::REQUIRED_JOBS as $required) {
            if (($results[$required]['status'] ?? null) !== 'completed'
                || ($results[$required]['conclusion'] ?? null) !== 'success') {
                throw new RuntimeException('Required GitHub Actions jobs are not green for the canonical main SHA.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function requestJson(string $url): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $this->token,
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: fanoos-updater',
            ]),
        ]]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                $status = (int) $match[1];
            }
        }
        if (!is_string($body) || $status !== 200) {
            throw new RuntimeException('GitHub Actions verification request failed.');
        }

        $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('GitHub Actions verification returned an invalid payload.');
        }

        return $decoded;
    }
}
