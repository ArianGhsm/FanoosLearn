<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Migration\AdditiveAlterGuard;
use Fanoos\Platform\Migration\MigrationPreflight;
use Fanoos\Platform\Migration\MigrationRunner;
use Fanoos\Platform\Migration\MigrationSafety;
use Fanoos\Platform\Operations\ProcessRunner;
use PDO;
use RuntimeException;
use Throwable;

final class MigrationSafetyTest
{
    private int $assertions = 0;

    public function __construct(
        private readonly PDO $database,
        private readonly string $root,
    ) {
    }

    public function run(): int
    {
        $suffix = bin2hex(random_bytes(5));
        $completeTable = 'migration_complete_' . $suffix;
        $partialTable = 'migration_partial_' . $suffix;
        $directory = sys_get_temp_dir() . '/fanoos-migration-' . $suffix;
        mkdir($directory, 0700, true);
        try {
            $this->database->exec("CREATE TABLE `{$completeTable}` (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB");
            $statement = "ALTER TABLE `{$completeTable}` ADD COLUMN recovered_col INT NULL, ADD UNIQUE KEY uq_{$suffix}_recovered (recovered_col)";
            $this->database->exec($statement);
            file_put_contents($directory . '/9000_fault_recovery.sql', $statement . ';');
            $result = (new MigrationRunner($this->database, $directory))->run();
            self::assert($result['applied'] === ['9000_fault_recovery.sql'], 'Runner did not ledger a fully committed additive statement after interruption.');
            $rerun = (new MigrationRunner($this->database, $directory))->run();
            self::assert($rerun['skipped'] === ['9000_fault_recovery.sql'], 'Recovered migration was not a deterministic no-op on rerun.');

            $this->database->exec("CREATE TABLE `{$partialTable}` (id INT NOT NULL PRIMARY KEY, recovered_col INT NULL) ENGINE=InnoDB");
            $partial = "ALTER TABLE `{$partialTable}` ADD COLUMN recovered_col INT NULL, ADD UNIQUE KEY uq_{$suffix}_partial (recovered_col)";
            $blocked = false;
            try {
                (new AdditiveAlterGuard($this->database))->decision($partial);
            } catch (RuntimeException) {
                $blocked = true;
            }
            self::assert($blocked, 'Partially applied additive DDL was not failed closed.');
        } finally {
            $this->database->exec("DROP TABLE IF EXISTS `{$completeTable}`");
            $this->database->exec("DROP TABLE IF EXISTS `{$partialTable}`");
            @unlink($directory . '/9000_fault_recovery.sql');
            @rmdir($directory);
            $this->database->prepare("DELETE FROM schema_migrations WHERE migration_name = '9000_fault_recovery.sql'")->execute();
        }

        $this->assertRollbackModeVocabulary();
        $this->assertPreflightRefusesAnUndeclaredContractMigration($suffix);
        $this->assertUnattendedRunnerRefusesAPendingDestructiveMigration($suffix);
        $this->assertSupervisedRunnerGatesOnDeclaredContract($suffix);
        $this->assertApplyContractMigrationScriptPreBackupRefusals();

        return $this->assertions;
    }

    /**
     * MigrationSafety::declaredRollbackMode()/isDeclaredContract() parse the
     * full expand/contract/manual/absent vocabulary. Pure string predicates,
     * no database needed -- kept here rather than in SchemaContractTest
     * (which runs without a database at all) because everything else this
     * class tests is these same predicates composed with a real MySQL
     * connection, and splitting the vocabulary check into its own class
     * would mean adding a fifth thing to hand-wire into tests/run.php for
     * three assertions.
     */
    private function assertRollbackModeVocabulary(): void
    {
        self::assert(MigrationSafety::declaredRollbackMode("-- fanoos:rollback-compatible=expand\nX") === 'expand', 'declaredRollbackMode did not parse expand.');
        self::assert(MigrationSafety::declaredRollbackMode("-- fanoos:rollback-compatible=contract\nX") === 'contract', 'declaredRollbackMode did not parse contract.');
        self::assert(MigrationSafety::declaredRollbackMode("-- fanoos:rollback-compatible=manual\nX") === 'manual', 'declaredRollbackMode did not parse manual.');
        self::assert(MigrationSafety::declaredRollbackMode("ALTER TABLE t ADD COLUMN c INT;") === null, 'declaredRollbackMode must return null for an undeclared migration.');
        self::assert(MigrationSafety::declaresExpandCompatible("-- fanoos:rollback-compatible=expand\nX") === true, 'declaresExpandCompatible regressed for expand.');
        self::assert(MigrationSafety::declaresExpandCompatible("-- fanoos:rollback-compatible=contract\nX") === false, 'declaresExpandCompatible must stay false for contract.');
        self::assert(MigrationSafety::isDeclaredContract("-- fanoos:rollback-compatible=contract\nX") === true, 'isDeclaredContract did not recognize contract.');
        self::assert(MigrationSafety::isDeclaredContract("-- fanoos:rollback-compatible=expand\nX") === false, 'isDeclaredContract must stay false for expand.');
        self::assert(MigrationSafety::isDeclaredContract("-- fanoos:rollback-compatible=manual\nX") === false, 'isDeclaredContract must stay false for manual.');
    }

    /**
     * MigrationPreflight::inspect() must refuse a pending migration that
     * declares contract-mode with the contract-specific message, not the
     * generic "not marked expand-compatible" message an undeclared
     * migration gets -- an operator reading deploy logs needs to see
     * immediately why, and what to do about it.
     */
    private function assertPreflightRefusesAnUndeclaredContractMigration(string $suffix): void
    {
        $directory = sys_get_temp_dir() . '/fanoos-migration-contract-preflight-' . $suffix;
        mkdir($directory, 0700, true);
        $name = '0001_preflight_contract_' . $suffix . '.sql';
        try {
            file_put_contents(
                $directory . '/' . $name,
                "-- fanoos:rollback-compatible=contract\nALTER TABLE whatever_table_{$suffix} DROP COLUMN whatever_col;\n",
            );
            $message = null;
            try {
                (new MigrationPreflight($this->database, $directory))->inspect();
            } catch (RuntimeException $refusal) {
                $message = $refusal->getMessage();
            }
            self::assert($message !== null, 'Preflight must refuse a pending contract-mode destructive migration.');
            self::assert(
                $message !== null && str_contains($message, 'apply-contract-migration.php') && str_contains($message, 'contract-mode'),
                'Preflight refusal for a declared-contract migration must name the supervised script, not the generic undeclared message: ' . ($message ?? ''),
            );
            self::assert(
                $message !== null && !str_contains($message, 'is not marked expand-compatible for unattended update'),
                'Preflight refusal for a declared-contract migration must not fall back to the generic undeclared message.',
            );
        } finally {
            @unlink($directory . '/' . $name);
            @rmdir($directory);
        }
    }

    /**
     * The actual gap this closes: MigrationRunner itself -- not just
     * MigrationPreflight -- must refuse a pending destructive migration on
     * an ordinary unattended run(), even when preflight is bypassed
     * entirely (an operator invoking scripts/db/migrate.php directly).
     * Before this change AdditiveAlterGuard had no opinion on DROP COLUMN
     * and would have let it straight through to $database->exec().
     */
    private function assertUnattendedRunnerRefusesAPendingDestructiveMigration(string $suffix): void
    {
        $directory = sys_get_temp_dir() . '/fanoos-migration-destructive-runner-' . $suffix;
        mkdir($directory, 0700, true);
        $name = '0001_unattended_destructive_' . $suffix . '.sql';
        try {
            file_put_contents(
                $directory . '/' . $name,
                "ALTER TABLE whatever_table_{$suffix} DROP COLUMN whatever_col;\n",
            );
            $message = null;
            try {
                (new MigrationRunner($this->database, $directory))->run();
            } catch (RuntimeException $refusal) {
                $message = $refusal->getMessage();
            }
            self::assert($message !== null, 'MigrationRunner::run() (unattended) must refuse a pending destructive migration rather than execute it.');
            self::assert(
                $message !== null && str_contains($message, 'supervised operator path'),
                'MigrationRunner refusal must point at the supervised operator path: ' . ($message ?? ''),
            );
            self::assert(
                (int) $this->database->query("SELECT COUNT(*) FROM schema_migrations WHERE migration_name = '{$name}'")->fetchColumn() === 0,
                'A migration the runner refused must not be ledgered.',
            );
        } finally {
            @unlink($directory . '/' . $name);
            @rmdir($directory);
        }
    }

    /**
     * The supervised path (run($name)) is the mirror image: it must accept
     * a genuinely destructive statement when the one named file is declared
     * contract, and refuse it -- even though $onlySupervised was given --
     * when the file is declared expand or not declared at all. Also covers
     * the "refuses to run twice" backstop: re-running the same $name is a
     * checksum-match no-op.
     */
    private function assertSupervisedRunnerGatesOnDeclaredContract(string $suffix): void
    {
        $directory = sys_get_temp_dir() . '/fanoos-migration-supervised-' . $suffix;
        mkdir($directory, 0700, true);
        $contractTable = 'migration_supervised_contract_' . $suffix;
        $expandTable = 'migration_supervised_expand_' . $suffix;
        $contractName = '0001_supervised_contract_' . $suffix . '.sql';
        $expandName = '0002_supervised_expand_' . $suffix . '.sql';
        $undeclaredName = '0003_supervised_undeclared_' . $suffix . '.sql';
        try {
            $this->database->exec("CREATE TABLE `{$contractTable}` (id INT NOT NULL PRIMARY KEY, doomed_col INT NULL) ENGINE=InnoDB");
            $this->database->exec("CREATE TABLE `{$expandTable}` (id INT NOT NULL PRIMARY KEY, doomed_col INT NULL) ENGINE=InnoDB");

            file_put_contents(
                $directory . '/' . $contractName,
                "-- fanoos:rollback-compatible=contract\nALTER TABLE `{$contractTable}` DROP COLUMN doomed_col;\n",
            );
            file_put_contents(
                $directory . '/' . $expandName,
                "-- fanoos:rollback-compatible=expand\nALTER TABLE `{$expandTable}` DROP COLUMN doomed_col;\n",
            );
            file_put_contents(
                $directory . '/' . $undeclaredName,
                "ALTER TABLE `{$expandTable}` DROP COLUMN doomed_col;\n",
            );

            $runner = new MigrationRunner($this->database, $directory);

            // A declared-expand file must still be refused under the
            // supervised parameter -- $onlySupervised authorizes exactly
            // one filename, not "whatever that filename turns out to be".
            $expandRefused = false;
            try {
                $runner->run($expandName);
            } catch (RuntimeException) {
                $expandRefused = true;
            }
            self::assert($expandRefused, 'Supervised run() must refuse a named file that is declared expand, not contract.');

            $undeclaredRefused = false;
            try {
                $runner->run($undeclaredName);
            } catch (RuntimeException) {
                $undeclaredRefused = true;
            }
            self::assert($undeclaredRefused, 'Supervised run() must refuse a named file with no rollback-compatible declaration at all.');

            // The genuinely contract-declared file is authorized and its
            // destructive statement actually executes.
            $result = $runner->run($contractName);
            self::assert($result['applied'] === [$contractName], 'Supervised run() did not apply the one declared-contract file it was authorized for.');
            $columnCheck = $this->database->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :name LIMIT 1',
            );
            $columnCheck->execute(['table' => $contractTable, 'name' => 'doomed_col']);
            self::assert($columnCheck->fetchColumn() === false, 'Supervised run() must have actually executed the DROP COLUMN, not merely ledgered it.');

            // Every other file in the directory must have been left alone:
            // $onlySupervised applies exactly one migration, nothing else.
            self::assert(
                (int) $this->database->query("SELECT COUNT(*) FROM schema_migrations WHERE migration_name IN ('{$expandName}', '{$undeclaredName}')")->fetchColumn() === 0,
                'Supervised run() must not have ledgered any file other than the one it was authorized for.',
            );

            // Re-running the same authorized name is a checksum-match
            // no-op -- the second layer of "refuses to run twice", behind
            // the operator script's own pre-backup ledger check.
            $rerun = $runner->run($contractName);
            self::assert($rerun['skipped'] === [$contractName], 'Re-running the same supervised migration name must be a no-op, not re-execute the DROP.');
        } finally {
            $this->database->exec("DROP TABLE IF EXISTS `{$contractTable}`");
            $this->database->exec("DROP TABLE IF EXISTS `{$expandTable}`");
            @unlink($directory . '/' . $contractName);
            @unlink($directory . '/' . $expandName);
            @unlink($directory . '/' . $undeclaredName);
            @rmdir($directory);
            $this->database->prepare(
                "DELETE FROM schema_migrations WHERE migration_name IN (:a, :b, :c)",
            )->execute(['a' => $contractName, 'b' => $expandName, 'c' => $undeclaredName]);
        }
    }

    /**
     * scripts/ops/apply-contract-migration.php's own pre-backup refusals.
     * These are the parts testable without a real backup pipeline (no
     * FANOOS_BACKUP_ROOT/FANOOS_STORAGE_ROOT/FANOOS_MYSQL_DEFAULTS_FILE is
     * configured in the mysql-integration CI job, and none should need to
     * be for a refusal that happens before the script ever touches
     * backup.php):
     *
     *   (a) the target file is not declared contract -- refused immediately;
     *   (b) the target migration is already in schema_migrations -- refused
     *       before any backup is attempted. 0023_drop_iam_sessions_csrf_
     *       token_digest.sql is genuinely already applied by the time this
     *       runs (TenantIsolationTest applies every real migration,
     *       including the one supervised contract migration, earlier in
     *       tests/run.php), so this targets the real file and the real
     *       ledger rather than a synthetic fixture.
     *
     * (c) -- refusing when the restore-rehearsal confirmation env vars are
     * unset -- is deliberately NOT exercised as a live subprocess here: this
     * script checks that gate before taking a backup (see the comment in
     * the script itself), but reaching it still requires a target migration
     * that is declared contract, actually destructive, and *not yet
     * applied* -- and the only such file in this repository (0023) is
     * already applied by the time this test runs, for the same reason (b)
     * can target it for real. Writing a second, throwaway contract
     * migration into the real database/migrations/ directory just to
     * reach this gate was rejected: that directory is integration-only
     * (docs/workflow/INTEGRATION_ONLY_PATHS.md) and SchemaContractTest
     * enforces the numbered-with-no-gaps invariant over it, so mutating it
     * from a test -- even temporarily -- is the wrong tool. (c) is
     * therefore verified below by asserting, from the script's own source,
     * that the restore-rehearsal check appears before the call to
     * backup.php -- i.e. by direct code reading, not execution.
     */
    private function assertApplyContractMigrationScriptPreBackupRefusals(): void
    {
        $script = $this->root . '/scripts/ops/apply-contract-migration.php';

        $notContractMessage = $this->runApplyContractMigrationScript(['0008_stage7_platform_contracts.sql']);
        self::assert(
            $notContractMessage !== null && str_contains($notContractMessage, 'not declared contract-mode'),
            'The script must refuse a target file that is not declared contract-mode, before touching the database: ' . ($notContractMessage ?? '(script unexpectedly succeeded)'),
        );

        $alreadyAppliedMessage = $this->runApplyContractMigrationScript(['0023_drop_iam_sessions_csrf_token_digest.sql']);
        self::assert(
            $alreadyAppliedMessage !== null && str_contains($alreadyAppliedMessage, 'already applied'),
            'The script must refuse a target migration already present in schema_migrations, before attempting a backup: ' . ($alreadyAppliedMessage ?? '(script unexpectedly succeeded)'),
        );

        $source = (string) file_get_contents($script);
        $restoreCheckPosition = strpos($source, 'FANOOS_ALLOW_TEST_RESTORE');
        $backupCallPosition = strpos($source, "'/scripts/ops/backup.php'");
        self::assert(
            $restoreCheckPosition !== false && $backupCallPosition !== false && $restoreCheckPosition < $backupCallPosition,
            'The restore-rehearsal confirmation must be checked before a backup is taken, so an unset confirmation refuses cheaply instead of burning a backup cycle first.',
        );
    }

    /** @param list<string> $arguments */
    private function runApplyContractMigrationScript(array $arguments): ?string
    {
        try {
            ProcessRunner::run(['php', $this->root . '/scripts/ops/apply-contract-migration.php', ...$arguments]);
            return null;
        } catch (Throwable $error) {
            return $error->getMessage();
        }
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
