<?php

declare(strict_types=1);

namespace Fanoos\Tests\Operations;

use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use PDO;
use RuntimeException;

final class OwnerBootstrapTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database, private readonly string $root)
    {
    }

    public function run(): int
    {
        $subjectKey = 'owner-bootstrap-test-subject-key-000000';
        $access = new AccessGate($this->database, new ScopeAuthorizer($this->database));

        $subjectOne = (string) random_int(100000000, 999999999);
        $first = $this->runScript('Bootstrap Owner Fixture', 'telegram', $subjectOne, null, $subjectKey);
        self::assert($first['exit'] === 0, 'First bootstrap run did not succeed: ' . $first['stderr']);
        self::assert(str_contains($first['stdout'], 'Owner user: created'), 'First run did not report a created owner user.');
        self::assert(str_contains($first['stdout'], 'Role assignment (platform-super-admin): created'), 'First run did not report a created role assignment.');
        self::assert(str_contains($first['stdout'], 'Messaging link (telegram): created'), 'First run did not report a created messaging link.');
        self::assert(!str_contains($first['stdout'], $subjectOne), 'Bootstrap output leaked the raw platform subject id.');

        $userId = $this->extractUserId($first['stdout']);
        $decision = $access->platform($userId, 'deployment.manage');
        self::assert($decision->allowed, 'Bootstrapped super-admin owner was not granted deployment.manage through the platform authorization path.');

        $userCount = $this->database->prepare('SELECT COUNT(*) FROM iam_users WHERE id = :id');
        $userCount->execute(['id' => $userId]);
        self::assert((int) $userCount->fetchColumn() === 1, 'Bootstrapped owner user row is not unique.');

        $second = $this->runScript('Bootstrap Owner Fixture', 'telegram', $subjectOne, null, $subjectKey);
        self::assert($second['exit'] === 0, 'Re-running bootstrap with the same arguments failed: ' . $second['stderr']);
        self::assert(str_contains($second['stdout'], 'Owner user: already existed'), 'Re-run did not report the owner user as already existing.');
        self::assert(str_contains($second['stdout'], 'Role assignment (platform-super-admin): already existed'), 'Re-run did not report the role assignment as already existing.');
        self::assert(str_contains($second['stdout'], 'Messaging link (telegram): already existed'), 'Re-run did not report the messaging link as already existing.');
        self::assert(str_contains($second['stdout'], "Owner user id: {$userId}"), 'Re-run created a different owner user id instead of reusing the existing one.');

        $roleAssignmentCount = $this->database->prepare('SELECT COUNT(*) FROM rbac_role_assignments WHERE user_id = :id');
        $roleAssignmentCount->execute(['id' => $userId]);
        self::assert((int) $roleAssignmentCount->fetchColumn() === 1, 'Re-running bootstrap duplicated the role assignment.');

        $linkCount = $this->database->prepare('SELECT COUNT(*) FROM messaging_links WHERE user_id = :id');
        $linkCount->execute(['id' => $userId]);
        self::assert((int) $linkCount->fetchColumn() === 1, 'Re-running bootstrap duplicated the messaging link.');

        $subjectTwo = (string) random_int(100000000, 999999999);
        $operator = $this->runScript('Bootstrap Deployment Operator', 'bale', $subjectTwo, 'platform-deployment-operator', $subjectKey);
        self::assert($operator['exit'] === 0, 'Deployment-operator bootstrap run did not succeed: ' . $operator['stderr']);
        $operatorId = $this->extractUserId($operator['stdout']);
        self::assert($operatorId !== $userId, 'Distinct platform subjects were bootstrapped into the same owner user.');
        $operatorDecision = $access->platform($operatorId, 'deployment.manage');
        self::assert($operatorDecision->allowed, 'Bootstrapped deployment-operator owner was not granted deployment.manage through the platform authorization path.');

        $rejected = $this->runScript('Bootstrap Invalid Platform', 'signal', (string) random_int(100000000, 999999999), null, $subjectKey);
        self::assert($rejected['exit'] !== 0, 'Bootstrap accepted an unsupported messaging platform.');

        return $this->assertions;
    }

    private function extractUserId(string $stdout): string
    {
        if (!preg_match('/Owner user id: ([0-9a-f-]{36})/', $stdout, $matches)) {
            throw new RuntimeException('Bootstrap output did not report an owner user id.');
        }
        return $matches[1];
    }

    /** @return array{exit:int,stdout:string,stderr:string} */
    private function runScript(string $displayName, string $platform, string $subject, ?string $roleKey, string $subjectKey): array
    {
        $script = $this->root . '/scripts/ops/bootstrap-owner.php';
        $arguments = [PHP_BINARY, $script, $displayName, $platform, $subject];
        if ($roleKey !== null) {
            $arguments[] = $roleKey;
        }
        $command = implode(' ', array_map('escapeshellarg', $arguments));

        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        $environment['FANOOS_MESSAGING_SUBJECT_KEY'] = $subjectKey;

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root, $environment);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not launch the bootstrap-owner script.');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return ['exit' => $exit, 'stdout' => $stdout === false ? '' : $stdout, 'stderr' => $stderr === false ? '' : $stderr];
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
