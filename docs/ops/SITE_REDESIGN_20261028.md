# Website redesign checkpoint — 2026-10-28

Scope: website presentation only, based on origin/main 7c58cd8. The first pass adjusts shared page surfaces, controls and footer, and restyles the public landing page. It does not change product logic, data, bots or contracts. The existing red brand and semantic answer colours remain.

Status: incomplete. Review every signed-in and public page at mobile and desktop sizes, both themes, keyboard focus and reduced motion; finish page-specific layouts before proposing a release. The requested visual resources were consulted, but their optional libraries and skills are not installed in this PHP/browser-native project. Playwright browser checks remain to be set up and run.

Validation: `node --test tests/web/*.mjs` passed 156 tests. `php tests/run.php` passed website rendering and other available checks but the overall run failed because an isolated `_test` database was not configured. Run the complete suite with the documented test database before release.

Production was not changed. Continue on this branch, push changes, open a PR and follow the green CI, review and updater deployment workflow in `docs/WORKFLOW.md`. Rollback for this checkpoint is to revert its CSS commits; no data migration is involved.
