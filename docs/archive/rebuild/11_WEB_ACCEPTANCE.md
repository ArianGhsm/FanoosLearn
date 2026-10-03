# FANOOS Stage 11 — Website Acceptance Checklist

## Product boundary

Stage 11 integrates the existing V3 public shell, authenticated workspace and student operations into one release candidate. FANOOS remains an independent product; no legacy runtime, database, bot identity, queue or storage tree is used. No production deployment, migration or service restart was performed.

## Journey acceptance

- [ ] Public landing page renders in Persian RTL with a keyboard skip link, usable focus states and an honest no-JavaScript message.
- [ ] Login/session bootstrap either opens the authenticated workspace or shows a truthful loading, unauthenticated or error state; no client-only authorization is assumed.
- [ ] A member with no workspace sees a clear zero-workspace state and cannot access workspace-scoped routes.
- [ ] A member with multiple workspaces can switch the active workspace; all subsequent course, schedule, resource, assessment, grade, announcement, form, order and notification requests remain workspace-scoped.
- [ ] Course and term journeys connect to sessions, resources, assessments, grades, announcements, forms, orders and protected entitlements without a second router or duplicate bootstrap.
- [ ] Search, notifications, management and content-production controls remain capability- and role-scoped; forbidden actions are not represented as successful UI operations.
- [ ] Empty, loading, partial and failure states are available for every student-facing collection; buttons do not silently dead-end.

## Design, mobile and accessibility

- [ ] V3 foundation tokens, Schoolhouse visual layer and mobile layer are loaded once in deterministic order.
- [ ] Navigation labels, page titles, badges and human state labels use the FANOOS terminology; raw enum values are not shown to students.
- [ ] Keyboard navigation, `:focus-visible`, skip-link behavior, semantic headings/landmarks and Persian `lang="fa" dir="rtl"` are preserved.
- [ ] Touch controls meet the 44px minimum token and responsive layouts remain usable at narrow mobile widths.
- [ ] Reduced-motion preferences are honored; no route depends on motion to communicate state.

## Security and data boundaries

- [ ] Browser rendering uses text/DOM APIs and safe internal links; no untrusted `innerHTML`, `document.write`, dynamic `eval` or remote asset CDN is present in V3 assets.
- [ ] Tenant, member, capability, payment, entitlement and protected-delivery authorization remains server-authoritative.
- [ ] Production serves the security headers in `apps/platform/public/.htaccess`: nosniff, same-origin framing, strict referrer policy, same-origin resource policy and restrictive Permissions-Policy.
- [ ] Runtime secrets, uploaded objects, logs, caches, PID/lock files and production state remain outside Git.

## Performance and release hygiene

- [ ] CSS and the single module bootstrap use `fanoosAsset()`; production sets `FANOOS_ASSET_VERSION` to the exact release SHA.
- [ ] Versioned static assets may use one-year immutable caching; the local fallback changes when an asset's mtime/size changes.
- [ ] V3 JS/CSS stays below the checked 800 KB aggregate budget and no individual route asset exceeds 120 KB.
- [ ] Schedule's existing data-attribute guard continues to prevent duplicate stylesheet injection.
- [ ] Obsolete V2 code remains only where it is a fixture/reference or is covered by a still-running test; it is not loaded by the primary V3 entrypoint.

## Local evidence

The following checks pass on the development workstation:

```text
node tests/ux-v3/web_integration_contract_test.js
node tests/ux-v3/web_acceptance_contract_test.js
node tests/ux-v3/web_academic_core_contract_test.js
node tests/ux-v3/web_content_pipeline_contract_test.js
node tests/ux-v3/web_schoolhouse_redesign_contract_test.js
node tests/ux-v3/web_shell_workspace_contract_test.js
node tests/ux-v3/web_student_operations_contract_test.js
node tests/ux-v2/web/product_dom_safety_test.js
node tests/ux-v2/web/product_ui_test.js
node tests/ux/domain_fuzz_test.js
node tests/ux/integration_web_ux_test.js
node tests/ux/worker02_domain_ux_test.js
python -m unittest tests/ux-v2/bots/test_integration_contracts.py
php scripts/ci/check-text.php
php scripts/ci/check-secrets.php
php scripts/ci/check-stage8.php
```

`php tests/run.php` remains an environment check rather than a passing result on this laptop: it stops before integration execution because the PHP `fileinfo` extension (`finfo`) is unavailable. Run the full PHP/MySQL suite in the release environment before approval.
