-- fanoos:rollback-compatible=contract
--
-- Contract: drops iam_sessions.csrf_token_digest. Dead since SessionCsrf's
-- derivation (apps/platform/src/Identity/SessionCsrf.php) replaced it --
-- zero readers or writers remain (verified by repo-wide search). Left in
-- place it is a trap: the next person to find it will reasonably assume
-- it is authoritative.
--
-- This is genuinely destructive -- rolling back to a release before this
-- migration needs the column back -- so it is NOT eligible for the
-- unattended updater (MigrationPreflight refuses any migration that is
-- not declared expand-compatible) and must be applied by
-- scripts/ops/apply-contract-migration.php after a verified, restore-
-- rehearsed backup, never by a release.

ALTER TABLE iam_sessions DROP COLUMN csrf_token_digest;
