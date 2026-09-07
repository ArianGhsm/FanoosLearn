<?php

declare(strict_types=1);

namespace Fanoos\Platform\Operations;

use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\RuntimeConfig;
use RuntimeException;
use Throwable;

final class CanonicalMainUpdateExecutor implements DeploymentExecutor
{
    private const REPOSITORY = 'ArianGhsm/FanoosLearn';

    private string $repoRoot;
    private string $deployRoot;
    private string $publicLink;
    private string $targetKey;
    private GitHubCiVerifier $ci;

    public function __construct(private readonly RuntimeConfig $config)
    {
        $this->repoRoot = rtrim($config->requireString('FANOOS_UPDATER_REPO_ROOT'), '/\\');
        $this->deployRoot = rtrim($config->requireString('FANOOS_DEPLOY_ROOT'), '/\\');
        $this->publicLink = rtrim($config->requireString('FANOOS_PUBLIC_LINK'), '/\\');
        $this->targetKey = $config->requireString('FANOOS_UPDATER_TARGET_KEY');
        $this->ci = new GitHubCiVerifier($config->requireString('FANOOS_GITHUB_UPDATER_TOKEN'));
    }

    public function preflight(string $requestId, string $targetKey): array
    {
        $this->requireRequestId($requestId);
        $this->requireTarget($targetKey);
        if (!is_dir($this->repoRoot) || !is_dir($this->deployRoot)) {
            throw new PlatformException('deployment_preflight_failed', 'Updater repository or deployment root is unavailable.', 500);
        }
        $remote = trim(ProcessRunner::run(['git', '-C', $this->repoRoot, 'remote', 'get-url', 'origin']));
        $allowedRemotes = [
            'git@github.com:' . self::REPOSITORY . '.git',
            'https://github.com/' . self::REPOSITORY . '.git',
        ];
        if (!in_array($remote, $allowedRemotes, true)) {
            throw new PlatformException('repository_identity_mismatch', 'Updater repository identity is not canonical FANOOS.', 500);
        }
        if (trim(ProcessRunner::run(['git', '-C', $this->repoRoot, 'status', '--porcelain', '--untracked-files=no'])) !== '') {
            throw new PlatformException('deployment_worktree_dirty', 'Updater checkout has tracked changes.', 500);
        }

        ProcessRunner::run(['git', '-C', $this->repoRoot, 'fetch', '--prune', 'origin', 'refs/heads/main:refs/remotes/origin/main']);
        $candidate = trim(ProcessRunner::run(['git', '-C', $this->repoRoot, 'rev-parse', '--verify', 'refs/remotes/origin/main^{commit}']));
        $this->requireSha($candidate);
        $current = $this->currentSha();
        if (!hash_equals($current, $candidate)) {
            try {
                ProcessRunner::run(['git', '-C', $this->repoRoot, 'merge-base', '--is-ancestor', $current, $candidate]);
            } catch (Throwable) {
                throw new PlatformException('deployment_non_fast_forward', 'Canonical main is not a fast-forward from the active release.', 409);
            }
        }
        $this->ci->assertGreen($candidate);

        $minimumFree = $this->config->positiveInt('FANOOS_UPDATER_MIN_FREE_BYTES', 1073741824);
        $free = disk_free_space($this->deployRoot);
        if ($free === false || $free < $minimumFree) {
            throw new PlatformException('deployment_disk_low', 'Deployment target does not have enough free disk space.', 503);
        }
        ProcessRunner::run(['php', '-v']);
        ProcessRunner::run(['git', '--version']);
        ProcessRunner::run(['tar', '--version']);
        ProcessRunner::run(['php', $this->currentPath() . '/scripts/ops/health.php']);

        return ['current_sha' => $current, 'candidate_sha' => $candidate, 'noop' => hash_equals($current, $candidate)];
    }

    public function backup(string $requestId, string $targetKey, string $currentSha, string $candidateSha): array
    {
        $this->requireRequestId($requestId);
        $this->requireTarget($targetKey);
        $this->requireSha($currentSha);
        $this->requireSha($candidateSha);
        try {
            $path = trim(ProcessRunner::run(['php', $this->currentPath() . '/scripts/ops/backup.php']));
            if ($path === '' || !is_dir($path)) {
                throw new RuntimeException('Backup command did not produce a finalized directory.');
            }
            ProcessRunner::run(['php', $this->currentPath() . '/scripts/ops/verify-backup.php', $path]);
            return ['backup_id' => basename($path)];
        } catch (Throwable) {
            throw new PlatformException('backup_verification_failed', 'Verified production backup failed.', 500);
        }
    }

    public function test(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->requireTarget($targetKey);
        $stage = $this->prepareStage($requestId, $candidateSha);
        try {
            ProcessRunner::run(['php', $stage . '/scripts/db/check.php']);
            ProcessRunner::run(['php', $stage . '/scripts/db/preflight.php']);
        } catch (Throwable) {
            throw new PlatformException('deployment_tests_failed', 'Candidate tests or migration preflight failed.', 500);
        }
    }

    public function migrate(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->requireTarget($targetKey);
        $stage = $this->candidatePath($requestId, $candidateSha);
        try {
            ProcessRunner::run(['php', $stage . '/scripts/db/preflight.php']);
            ProcessRunner::run(['php', $stage . '/scripts/db/migrate.php']);
            ProcessRunner::run(['php', $stage . '/scripts/db/seed.php']);
        } catch (Throwable) {
            throw new PlatformException('deployment_migration_failed', 'Forward-compatible migration failed.', 500);
        }
    }

    public function activate(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->requireTarget($targetKey);
        $this->requireSha($candidateSha);
        $stage = $this->candidatePath($requestId, $candidateSha);
        $releases = $this->deployRoot . '/releases';
        $release = $releases . '/' . $candidateSha;
        if (!is_dir($releases) && !mkdir($releases, 0750, true) && !is_dir($releases)) {
            throw new PlatformException('activation_failed', 'Release directory is unavailable.', 500);
        }
        if (!is_dir($release)) {
            if (!rename($stage, $release)) {
                throw new PlatformException('activation_failed', 'Candidate could not be moved into immutable releases.', 500);
            }
        } elseif (!$this->releaseMatches($release, $candidateSha)) {
            throw new PlatformException('activation_failed', 'Existing release path does not match the candidate SHA.', 500);
        }
        ProcessRunner::run(['php', $release . '/scripts/ops/health.php']);
        file_put_contents($release . '/READY', $candidateSha . PHP_EOL, LOCK_EX);
        $this->swapPointers($release, $requestId);
    }

    public function restart(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->requireRequestId($requestId);
        $this->requireTarget($targetKey);
        $this->requireSha($candidateSha);
        $this->restartConfiguredUnits();
    }

    public function health(string $requestId, string $targetKey, string $candidateSha): void
    {
        $this->requireRequestId($requestId);
        $this->requireTarget($targetKey);
        $this->requireSha($candidateSha);
        if (!hash_equals($candidateSha, $this->currentSha())) {
            throw new PlatformException('post_health_failed', 'Active release SHA does not match the candidate.', 500);
        }
        try {
            ProcessRunner::run(['php', $this->currentPath() . '/scripts/ops/health.php']);
            $smoke = $this->currentPath() . '/scripts/ops/update-smoke.php';
            if (is_file($smoke)) {
                ProcessRunner::run(['php', $smoke]);
            }
        } catch (Throwable) {
            throw new PlatformException('post_health_failed', 'Post-activation health or smoke check failed.', 500);
        }
    }

    public function rollback(string $requestId, string $targetKey, string $rollbackSha): void
    {
        $this->requireRequestId($requestId);
        $this->requireTarget($targetKey);
        $this->requireSha($rollbackSha);
        $release = $this->deployRoot . '/releases/' . $rollbackSha;
        if (!$this->releaseMatches($release, $rollbackSha)) {
            throw new PlatformException('rollback_unavailable', 'Previous application release is unavailable.', 500);
        }
        $this->swapPointers($release, $requestId . '-rollback');
        $this->restartConfiguredUnits();
        ProcessRunner::run(['php', $this->currentPath() . '/scripts/ops/health.php']);
    }

    private function prepareStage(string $requestId, string $candidateSha): string
    {
        $this->requireRequestId($requestId);
        $this->requireSha($candidateSha);
        $root = $this->deployRoot . '/staging';
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new PlatformException('deployment_stage_failed', 'Staging directory could not be created.', 500);
        }
        $stage = $root . '/' . $requestId;
        if (is_dir($stage)) {
            if (!$this->releaseMatches($stage, $candidateSha)) {
                throw new PlatformException('deployment_stage_conflict', 'Existing staging directory belongs to another candidate.', 500);
            }
            return $stage;
        }
        if (!mkdir($stage, 0750)) {
            throw new PlatformException('deployment_stage_failed', 'Candidate staging directory could not be created.', 500);
        }
        $archive = $root . '/.' . $requestId . '.tar';
        try {
            ProcessRunner::run(['git', '-C', $this->repoRoot, 'archive', '--format=tar', $candidateSha], $archive);
            ProcessRunner::run(['tar', '-xf', $archive, '-C', $stage]);
            file_put_contents($stage . '/SOURCE_SHA', $candidateSha . PHP_EOL, LOCK_EX);
        } finally {
            if (is_file($archive)) {
                @unlink($archive);
            }
        }
        return $stage;
    }

    private function candidatePath(string $requestId, string $candidateSha): string
    {
        $stage = $this->deployRoot . '/staging/' . $requestId;
        if (is_dir($stage) && $this->releaseMatches($stage, $candidateSha)) {
            return $stage;
        }
        $release = $this->deployRoot . '/releases/' . $candidateSha;
        if ($this->releaseMatches($release, $candidateSha)) {
            return $release;
        }
        throw new PlatformException('deployment_stage_unavailable', 'Candidate release workspace is unavailable.', 500);
    }

    private function swapPointers(string $release, string $suffix): void
    {
        $current = $this->deployRoot . '/current';
        $next = $this->deployRoot . '/.current-next-' . preg_replace('/[^A-Za-z0-9_-]/', '', $suffix);
        $publicNext = $this->publicLink . '.next-' . preg_replace('/[^A-Za-z0-9_-]/', '', $suffix);
        if (file_exists($next) || is_link($next) || file_exists($publicNext) || is_link($publicNext)) {
            throw new PlatformException('activation_pointer_conflict', 'A staged activation pointer already exists.', 500);
        }
        if ((file_exists($current) && !is_link($current)) || (file_exists($this->publicLink) && !is_link($this->publicLink))) {
            throw new PlatformException('activation_pointer_invalid', 'Deployment pointer is not a symlink.', 500);
        }
        if (!symlink($release, $next) || !rename($next, $current)) {
            throw new PlatformException('activation_failed', 'Current release pointer could not be switched atomically.', 500);
        }
        if (!symlink($current . '/apps/platform/public', $publicNext) || !rename($publicNext, $this->publicLink)) {
            throw new PlatformException('activation_failed', 'Public release pointer could not be switched atomically.', 500);
        }
    }

    private function restartConfiguredUnits(): void
    {
        $raw = $this->config->optionalString('FANOOS_UPDATER_RESTART_UNITS', '') ?? '';
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $unit) {
            if (!preg_match('/^[A-Za-z0-9_.@:-]{1,128}$/', $unit)) {
                throw new PlatformException('restart_policy_invalid', 'Configured restart unit is invalid.', 500);
            }
            ProcessRunner::run(['systemctl', 'reload-or-restart', $unit]);
        }
    }

    private function currentPath(): string
    {
        $current = $this->deployRoot . '/current';
        if (!is_link($current) || !is_dir($current)) {
            throw new PlatformException('active_release_missing', 'Active immutable release is unavailable.', 500);
        }
        return $current;
    }

    private function currentSha(): string
    {
        $ready = $this->currentPath() . '/READY';
        $sha = is_file($ready) ? trim((string) file_get_contents($ready)) : '';
        $this->requireSha($sha);
        return $sha;
    }

    private function releaseMatches(string $path, string $sha): bool
    {
        if (!is_dir($path)) {
            return false;
        }
        foreach (['SOURCE_SHA', 'READY'] as $marker) {
            $file = $path . '/' . $marker;
            if (is_file($file) && hash_equals($sha, trim((string) file_get_contents($file)))) {
                return true;
            }
        }
        return false;
    }

    private function requireTarget(string $targetKey): void
    {
        if (!hash_equals($this->targetKey, $targetKey)) {
            throw new PlatformException('deployment_target_mismatch', 'Updater is not configured for this target.', 403);
        }
    }

    private function requireRequestId(string $requestId): void
    {
        if (!preg_match('/^[0-9a-f-]{36}$/i', $requestId)) {
            throw new PlatformException('deployment_request_invalid', 'Deployment request identifier is invalid.', 422);
        }
    }

    private function requireSha(string $sha): void
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $sha)) {
            throw new PlatformException('deployment_sha_invalid', 'Deployment SHA is invalid.', 500);
        }
    }
}
