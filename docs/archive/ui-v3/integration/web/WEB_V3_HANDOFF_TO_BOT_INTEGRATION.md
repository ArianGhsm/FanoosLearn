# FANOOS Website V3 — Handoff to Bot V3 Integration

Design Lock ID: `FANOOS-UX-2026.09-R1`

Original parallel base: `9e72ef32b331ad41626229c28ed24dcc43a49292`

`WEB_V3_INTEGRATED=true`

The Website V3 source integration consists of all eight Website worker trees plus the canonical browser entrypoint, route/runtime adapter, cross-domain Course slots, secure learning delivery adapter, deterministic regression tests, CI discovery and the integration documentation in this directory.

Bot V3 final integration **must not** start from a Website worker branch and must not merge the Website workers individually. It must start from the resulting current `main` after the single Website V3 integration PR is merged and exact main state has been verified.

Website V3 preserves backend authority for authentication, workspace selection, permissions, protected-resource authorization, assessment scoring, payment and entitlement. Bot integration must preserve the same semantic distinctions across Telegram and Bale.

Known honest product gaps carried forward rather than fabricated in Website source:

- no durable browser personal-notification history projection;
- no canonical course↔announcement relation;
- no complete browser granular-management-permission projection;
- no authoritative learning-library total/cursor;
- no personalized continue-learning projection;
- no assessment deadline field in the current public assessment catalog.

These gaps are not permission to invent client or bot authority.

No deployment occurred in Website integration.

`DEPLOYED=false`

`LIVE_BROWSER_VALIDATION_REQUIRED=true`
