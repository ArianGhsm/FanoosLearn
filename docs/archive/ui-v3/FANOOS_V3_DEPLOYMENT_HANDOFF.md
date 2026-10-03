# FANOOS V3 — Deployment Handoff

Design Lock: `FANOOS-UX-2026.09-R1`

This handoff is produced by source integration and intentionally performs **no deployment**.

## Source-only state

```text
WEB_V3_INTEGRATED=true
BOT_V3_INTEGRATION_CANDIDATE=true
DEPLOYED=false
```

`BOT_V3_INTEGRATED=true` and `FANOOS_V3_SOURCE_READY_FOR_DEPLOY=true` may be declared externally only after the Bot integration PR passes exact-head CI, receives final diff/security review, is merged to `main`, and the exact post-merge `main` state is verified.

The source-ready SHA is not embedded here because a committed handoff must not attempt to self-reference its containing commit. The deployment task must consume the exact `FANOOS_V3_SOURCE_READY_SHA` reported after merge.

## Deployment input requirements

The later deployment task must begin from the exact source-ready `main` SHA and verify:

- repository identity is `ArianGhsm/FanoosLearn`;
- Design Lock ID is `FANOOS-UX-2026.09-R1`;
- Website V3 handoff is present;
- Bot V3 integration docs are present;
- deployment input SHA equals the source-ready SHA supplied by the completed integration task;
- no unreviewed source commit has been inserted after that SHA selection.

## Runtime surfaces requiring live validation

After deployment, validate all of the following against the deployed exact source SHA:

- Website V3 navigation, auth/workspace states and key academic/learning/commerce journeys;
- Telegram unlinked, zero-workspace, workspace selection, active Home, courses, schedule, grades, announcements, resources, account/unlink and safe error recovery;
- Telegram rich → plain presentation fallback on non-protected content without replaying business logic;
- Telegram callback ACK behavior and bounded callback routing;
- Telegram protected text/media atomic send behavior and real forward-protection capability;
- Bale equivalent semantic journeys with Bale-native formatting/buttons;
- Bale absence of Telegram-only payloads and absence of Update Server;
- Bale fail-closed behavior for protection-sensitive originals;
- protected-media personalized derivative end-to-end flow and receipt semantics;
- private Telegram owner-management gating using canonical `deployment.manage`;
- cross-workspace and cross-user isolation.

## Fail-closed deployment rules

Deployment must stop if any of the following occurs:

- source SHA differs from the accepted source-ready SHA;
- migrations or runtime configuration imply source changes not represented in the accepted commit;
- a provider requires protection semantics weaker than the accepted source policy;
- Telegram/Bale runtime points to a legacy primary application path instead of the integrated V3 application;
- canonical backend permission/entitlement/payment/grade semantics differ from source assumptions;
- a Critical or High security issue is found;
- live validation reveals contradictory Website/Telegram/Bale business semantics.

## Explicit non-actions in this handoff

This source-integration phase does not:

- access or modify any server;
- restart services;
- modify production database state;
- run production migrations;
- call live Telegram or Bale APIs;
- upload protected media to production providers;
- alter DNS, reverse proxy, systemd, container or secret configuration.

## Required status flags

```text
DEPLOYED=false
LIVE_BROWSER_VALIDATION_REQUIRED=true
TELEGRAM_RUNTIME_VALIDATION_REQUIRED=true
BALE_RUNTIME_VALIDATION_REQUIRED=true
PROTECTED_MEDIA_RUNTIME_VALIDATION_REQUIRED=true
```

The next authorized task after completed source integration is deployment + live validation from the exact source-ready SHA.
