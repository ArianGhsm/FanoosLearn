<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use RuntimeException;

final class GitHubCiVerifier
{
    private const REPOSITORY = 'ArianGhsm/FanoosLearn';
    private const REQUIRED_CHECKS = ['static-and-unit (8.2)', 'static-and-unit (8.4)', 'mysql-integration'];

    public function __construct(private readonly string $token)
    {
        if (strlen($token) < 20) {
            throw new RuntimeException('GitHub updater credential is unavailable.');
        }
    }

    public function assertGreen(string $sha): void
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
            throw new RuntimeException('CI verification requires an exact commit SHA.');
        }
        $url = 'https://api.github.com/repos/' . self::REPOSITORY . '/commits/' . $sha . '/check-runs?per_page=100';
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
            throw new RuntimeException('GitHub CI verification request failed.');
        }
        $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        $runs = is_array($payload['check_runs'] ?? null) ? $payload['check_runs'] : [];
        $results = [];
        foreach ($runs as $run) {
            if (is_array($run) && is_string($run['name'] ?? null)) {
                $results[(string) $run['name']] = (string) ($run['conclusion'] ?? '');
            }
        }
        foreach (self::REQUIRED_CHECKS as $required) {
            if (($results[$required] ?? null) !== 'success') {
                throw new RuntimeException('Required GitHub CI checks are not green for the canonical main SHA.');
            }
        }
    }
}
