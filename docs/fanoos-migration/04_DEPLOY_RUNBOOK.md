# Fanoos deploy and rollback runbook

Status: procedures implemented locally; production deployment not yet authorized

Last updated: 2026-09-06

## Invariants

- Deploy only this Fanoos repository and an exact CI-passing commit from `main`.
- Never deploy from a dirty checkout, an archive assembled manually, or a legacy-project directory.
- Never put configuration, uploaded bytes, logs, backups, or credentials in a release.
- Back up before migrations or pointer changes.
- Treat migrations as forward-only and backward-compatible. Application rollback does not undo data changes.
- Stop if any command, backup verification, migration, seed, or readiness probe fails.

## Laptop and pull-request validation

From the repository root:

```bash
git status --short
git fetch origin
git pull --ff-only
php scripts/db/check.php
php scripts/ci/check-secrets.php
php scripts/ci/check-text.php
```

Run `php tests/run.php` only with `FANOOS_ALLOW_TEST_DB=1` and a disposable database whose name ends in `_test`. CI performs this with MySQL 8.4. Review and merge only when both CI jobs pass.

After the commit exists, build the reproducible source artifact:

```bash
php scripts/ops/build-release.php <full-commit-sha>
```

Verify that the artifact manifest names the requested SHA and that its SHA-256 equals the artifact digest. `var/releases` is ignored and is not a deployment source of truth; the Git commit is.

## One-time staging setup

Do not perform this section until every production gate in `04_INFRASTRUCTURE.md` is approved. First use a staging domain/database/storage tree.

1. Create an account-private `~/fanoos/shared` tree and directories for objects and backups outside `public_html`.
2. Create a dedicated least-privilege MySQL database/user.
3. Copy the shapes from `ops/cpanel/config.php.example` and `ops/cpanel/mysql-client.cnf.example` to private files. Replace every placeholder and set restrictive permissions. Do not copy populated files back into Git.
4. Verify PHP CLI and web handler versions/extensions match the declared minimum.
5. Create a cPanel-managed clone of the Fanoos GitHub repository. Confirm the branch, clean worktree, remote, and exact HEAD before enabling deployment.
6. Review `ops/cpanel/cpanel.yml.example`, especially both account-home paths. Only then copy it to root as `.cpanel.yml`, commit it through a pull request, and let CI inspect it.
7. Configure the domain/document root so the reviewed public symlink is the only exposed release path.

The template deliberately publishes to a `fanoos` subpath. Changing it to a domain root requires a separate, reviewed cutover plan; do not point the script at a non-symlink `public_html` directory.

## Deploy

Record the operator, ticket, target environment, source SHA, current SHA, CI URL, expected change, and rollback SHA. Confirm the most recent scheduled backup and enough free space.

In cPanel Git Version Control, update using fast-forward-only semantics and confirm HEAD equals the approved 40-character SHA. Trigger deployment only after reviewing `.cpanel.yml`. The guarded command represented by the template is:

```bash
FANOOS_DEPLOY_ROOT="$HOME/fanoos" \
FANOOS_PUBLIC_LINK="$HOME/public_html/fanoos" \
FANOOS_DEPLOY_CONFIRMED=1 \
bash scripts/ops/cpanel-deploy.sh <full-commit-sha>
```

The script performs, in order: clean/SHA/path guards, mandatory backup, immutable extraction, static contracts, migration, idempotent seed, pre-switch readiness, atomic pointers, and post-switch readiness. Preserve its complete output in the deployment record but redact private paths if the record is broadly visible.

After success:

```bash
php "$HOME/fanoos/current/scripts/ops/health.php"
```

Then check through HTTPS:

- liveness returns 200 and `status=ok`;
- readiness returns 200 and every named check is true;
- one authenticated tenant-safe read and write works;
- one allowed upload and controlled download works when Prompt 5 provides the endpoints;
- audit/log correlation appears without credentials or personal payloads;
- no elevated error rate, disk jump, or unexpected cron overlap occurs.

## Automatic failure behavior

Before pointer switch, failure leaves the old release serving traffic and a partial/new release available for diagnosis. Do not mark it `READY` manually. After pointer switch, a failing readiness check restores the previous application pointer when one existed. The database remains migrated; escalate if compatibility was violated.

Do not delete failed releases during incident response. Do not rerun migrations blindly; first inspect the migration ledger and error.

## Manual rollback

Use rollback for an application regression only when the target release has a `READY` marker and is compatible with the current schema:

```bash
FANOOS_DEPLOY_ROOT="$HOME/fanoos" \
FANOOS_PUBLIC_LINK="$HOME/public_html/fanoos" \
FANOOS_DEPLOY_CONFIRMED=1 \
bash scripts/ops/cpanel-rollback.sh <previous-full-commit-sha>
```

Verify readiness and smoke tests again. Record why rollback occurred and keep the failed release and logs. For data corruption or destructive operator error, do not use the application rollback as a substitute for the restore procedure.

## Emergency stop conditions

Stop and leave the old pointer untouched when backup cannot complete, manifest verification fails, free space is unsafe, target SHA differs, checkout is dirty, exact PHP/extension checks differ between CLI and web, storage is under the document root, config/defaults files are readable too broadly, or any tenant-isolation check fails.

## Current status

No server files, Git repositories, cron jobs, databases, symlinks, DNS, or cPanel settings were created or changed during Prompt 4. The procedure remains inactive until staging evidence and user authorization exist.
