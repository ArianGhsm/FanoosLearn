<?php

declare(strict_types=1);

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Identity\StudentRegistrationService;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\Transaction;

/**
 * Makes an existing workspace the library of a discipline: the workspace
 * every student of that discipline becomes a member of, from any university
 * and entry year (see migration 0024).
 *
 * The workspace keeps its place in the directory -- re-parenting it would
 * leave its RBAC scope chain pointing at the old cohort. Being a library is
 * what takes it out of the bot's class-join lookups (ClassMembershipService,
 * DirectoryReadService): it is not a class anyone joins by picking a program
 * and a year.
 *
 * Students who signed up while the discipline had no library are enrolled
 * now. Re-running is safe.
 *
 * Usage:
 *   php scripts/ops/provision-discipline-library.php \
 *       --discipline=<code, e.g. medicine> --workspace=<uuid> [--name=<new workspace name>]
 */

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

function argument(string $name): ?string
{
    foreach ($GLOBALS['argv'] as $argument) {
        if (str_starts_with($argument, "--{$name}=")) {
            return substr($argument, strlen($name) + 3);
        }
    }

    return null;
}

try {
    $code = (string) argument('discipline');
    $workspaceId = (string) argument('workspace');
    $name = argument('name');
    if ($code === '' || preg_match('/^[0-9a-f-]{36}$/', $workspaceId) !== 1) {
        throw new RuntimeException('Usage: --discipline=<code> --workspace=<uuid> [--name=<name>]');
    }

    $database = DatabaseConnection::fromEnvironment();
    $audit = new AuditLogger($database);

    $disciplineId = Transaction::run($database, static function () use ($database, $audit, $code, $workspaceId, $name): string {
        $discipline = $database->prepare('SELECT id, library_workspace_id FROM academic_disciplines WHERE code = :code FOR UPDATE');
        $discipline->execute(['code' => $code]);
        $row = $discipline->fetch();
        if ($row === false) {
            throw new RuntimeException("No discipline with code {$code}.");
        }
        if ($row['library_workspace_id'] !== null && $row['library_workspace_id'] !== $workspaceId) {
            throw new RuntimeException('This discipline already has a different library; refusing to move its students silently.');
        }

        $workspace = $database->prepare("SELECT 1 FROM tenant_workspaces WHERE id = :id AND status = 'active' AND archived_at IS NULL");
        $workspace->execute(['id' => $workspaceId]);
        if ($workspace->fetchColumn() === false) {
            throw new RuntimeException('Workspace not found or not active.');
        }

        $database->prepare('UPDATE academic_disciplines SET library_workspace_id = :workspace, updated_at = UTC_TIMESTAMP(6) WHERE id = :id')
            ->execute(['workspace' => $workspaceId, 'id' => $row['id']]);
        if ($name !== null && trim($name) !== '') {
            $database->prepare('UPDATE tenant_workspaces SET name = :name, updated_at = UTC_TIMESTAMP(6), version = version + 1 WHERE id = :id')
                ->execute(['name' => mb_substr(trim($name), 0, 200), 'id' => $workspaceId]);
        }
        $audit->record($workspaceId, null, 'discipline.library.provision', 'academic_discipline', (string) $row['id'], 'success', ['code' => $code]);

        return (string) $row['id'];
    });

    $registration = new StudentRegistrationService($database, new AuthService($database, new PasswordHasher(), $audit), new PasswordHasher(), $audit);
    $enrolled = $registration->enrolDisciplineStudents($disciplineId);

    echo json_encode(['discipline' => $code, 'library_workspace_id' => $workspaceId, 'students_enrolled' => $enrolled], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
