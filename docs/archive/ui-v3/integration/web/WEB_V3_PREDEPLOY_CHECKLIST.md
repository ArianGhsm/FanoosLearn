# FANOOS Website V3 — Pre-deploy Source Checklist

Design Lock: `FANOOS-UX-2026.09-R1`

This checklist is deliberately **source-only**. Completing it does not deploy Website V3.

## Source gates

- [x] Repository identity verified as `ArianGhsm/FanoosLearn`.
- [x] Design Lock ID verified.
- [x] Eight worker branches exist and descend from the original parallel base.
- [x] Eight worker handoffs read and source-complete.
- [x] Worker ownership boundaries verified.
- [x] Worker heads re-read and stable for integration snapshot.
- [x] All eight worker source/handoff trees integrated.
- [x] Canonical V3 entrypoint replaces V2 as normal Website boot path.
- [x] One Shell router / one route listener authority.
- [x] One canonical V3 runtime context contract.
- [x] Route and workspace switch abort semantics wired.
- [x] Home adapter and partial-failure orchestration wired.
- [x] Course Schedule/Resources/Assessments/Grades slots wired.
- [x] Learning canonicalized to `/resources`.
- [x] Secure delivery tokens remain ephemeral and hidden.
- [x] Assessment browser scoring/answer authority absent.
- [x] Payment/access semantics remain separate.
- [x] Management mutations fail closed without granular capability projection.
- [x] No Website Update Server surface.
- [x] V2 regression source retained as test fixture, not production entrypoint.
- [x] Deterministic V3 static integration test added.
- [x] CI discovery extended without weakening PHP, Python, MySQL or security lanes.
- [ ] Exact PR-head CI green — must be verified on the final PR head before merge.
- [ ] Full PR diff self-review complete — must be performed after PR creation.
- [ ] Post-merge main CI green if GitHub triggers it.

## Deterministic checks expected in CI

- PHP lint for repository PHP files and the new `index.php`.
- repository DB/static contract check.
- secret/text/stage safety checks.
- exact-commit release artifact build.
- existing V2 Web regression suite against the retained fixture/source.
- V3 ES-module syntax checks for every `assets/ui-v3/**/*.js` file.
- `tests/ux-v3/web_integration_contract_test.js`.
- existing deterministic Telegram/Bale worker tests (unchanged by this Website task).
- existing MySQL migration/tenant-isolation integration suite (unchanged, but must stay green).

No backend projection/contract source was changed by this integration, so no new DB migration or backend behavior test was added solely for Website V3.

## Asset/cache notes for a later deploy task

A future deploy must treat `index.php` and `assets/ui-v3/**` as a coherent release. The HTML changes the primary asset graph from V2/legacy assets to V3 modules. Existing cache headers/CDN/proxy behavior must be checked so an old cached `index.php` cannot point at a partially updated asset set and a new cached page cannot receive missing V3 assets.

No cache purge, server command or runtime updater action is performed by this task.

## Runtime/browser validation still required after deployment

- signed-out first paint has no V2 flash;
- signed-in shell and real session lifecycle;
- workspace select/switch under real latency;
- Home partial network failure behavior;
- course detail tabs and all four integrated slots;
- workspace timezone behavior around date boundaries/DST where applicable;
- protected resource structured/download delivery;
- assessment attempt/save/conflict/submit/review;
- order/payment/access states;
- authorized and unauthorized management accounts;
- responsive checks at 320, 360, 390/430, 768, 1024, 1280+ and wide desktop;
- keyboard navigation, focus return/containment, screen reader landmarks/live regions;
- browser console/network checks for duplicate assets, stale requests and uncaught errors.

`LIVE_BROWSER_VALIDATION_REQUIRED=true`

`DEPLOYED=false`
