# FANOOS Stage 11 — Web Deploy Handoff

This is a handoff only. Stage 11 did not deploy, migrate, restart or send live service messages.

## Release preparation

1. Build and test from the exact integrated commit SHA. Do not deploy a moving branch or a dirty worktree.
2. Set `FANOOS_ASSET_VERSION` to that exact SHA (or another approved release identifier matching `[A-Za-z0-9._-]{1,80}`) in the web runtime environment. This pins every CSS and module bootstrap URL to the release.
3. Serve `apps/platform/public` as the document root with the checked-in `.htaccess` enabled. Confirm Apache allows the required headers, rewrite and expires modules.
4. Keep runtime secrets, database credentials, private object storage and logs outside the checkout. Confirm the protected object root is not web-readable.
5. Run migrations and backups only through the existing release gates; Stage 11 introduces no schema migration.

## Pre-live checks

- Verify PHP has `pdo_mysql`, `mbstring` and `fileinfo` (`finfo`) and run `php tests/run.php` against the production-like test database.
- Run the Stage 11 Node, Python, PHP lint, text, secret and runtime-state checks listed in `docs/rebuild/11_WEB_ACCEPTANCE.md`.
- Render `/health` and the public shell, then verify one authenticated member, one no-workspace member and one multi-workspace member.
- Exercise course → session → resource → assessment/grade flow; notification read/prefs; search; forms; catalog/order/access states; and a denied cross-workspace/protected-resource request.
- Verify mobile keyboard/touch behavior, cache headers, same-origin asset loading and absence of browser console errors on the primary journeys.

## Rollback

Keep the previous exact release SHA and verified database/object backup. Roll back the web checkout and `FANOOS_ASSET_VERSION` together, then re-run `/health` and the authenticated smoke journeys. Do not restore production data from Git or overwrite a newer database with an unverified checkout.

## Known local limitation

The current development laptop cannot complete `php tests/run.php` because `finfo` is not installed. This is a release-environment prerequisite, not evidence of an application failure. No live deployment was attempted from this workstation.
