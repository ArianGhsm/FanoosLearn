# FANOOS Bot V3 — Pre-Deploy Source Checklist

Design Lock: `FANOOS-UX-2026.09-R1`

This checklist is for source readiness only. Deployment is explicitly outside this integration task.

## Integration gates

- [x] Website V3 integration handoff exists and declares `WEB_V3_INTEGRATED=true`.
- [x] Website V3 integration CI was green before Bot integration began.
- [x] Bot integration branch was created from the website-integrated `main`.
- [x] All four Bot worker heads were re-read and provenance recorded.
- [x] Worker source stayed within declared ownership boundaries.
- [x] No worker branch was modified during integration.
- [x] Shared Stage 7 registry and internal/public API contracts were re-read.
- [x] No new backend endpoint, schema migration or auth semantic was required.
- [x] Bot-01 core semantics, bot-02 academic, bot-03 learning/commerce and bot-04 provider rendering are connected through one integration-owned bridge.
- [x] Telegram and Bale runtime entrypoints use `integrated_application.BotApplication`.
- [x] Telegram/Bale transport now uses bot-04 V3 provider renderers as the user-facing presentation path.
- [x] Single workspace membership is not implicitly selected.
- [x] Long callbacks use bounded subject-bound route references.
- [x] Protected Telegram sends stay atomic and protected.
- [x] Bale protection remains fail-closed.
- [x] Owner management remains private-Telegram + canonical `deployment.manage` gated.
- [x] Existing receipt/idempotency/notification/protected-media business semantics are preserved.
- [x] Deterministic Bot V3 integration tests are included in the central bot test runner.
- [ ] Exact-head pull-request CI is green.
- [ ] Integration PR is self-reviewed against its complete diff.
- [ ] Integration PR is merged to `main`.
- [ ] Post-merge `main` SHA is recorded as the source-ready SHA.

The final four boxes above are release-integration gates and must only be checked after GitHub reports the corresponding state. Their unchecked state in this committed checklist must not be interpreted as a product defect before the PR is opened.

## Deployment-stage gates — intentionally not executed here

- [ ] Production deployment performed.
- [ ] Live browser validation performed.
- [ ] Telegram runtime validation performed.
- [ ] Bale runtime validation performed.
- [ ] Protected-media runtime validation performed.
- [ ] Owner-management live validation performed.

These remain unchecked after source integration by design. Source readiness does not imply deployment or live-provider validation.

## Fail-closed release rule

Do not mark `FANOOS_V3_SOURCE_READY_FOR_DEPLOY=true` unless the exact integration PR head passes CI, the complete PR diff is reviewed, the PR is merged, and the resulting `main` includes both Website V3 and Bot V3 integration. Do not mark `DEPLOYED=true` in this phase.
