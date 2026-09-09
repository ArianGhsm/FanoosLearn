# FANOOS UI V2 — Bale Presentation

Verification date: 2026-09-09.

FANOOS does not pretend Bale is Telegram. A 2026-09-09 re-check did not surface an official public Rich-message contract equivalent to Telegram's current Rich Message API. The implementation therefore remains bound to the repository's verified Bale capability matrix rather than copying Telegram-only payload fields.

## First-class shared semantics

Bale receives the same semantic screen:

- Persian title;
- breadcrumb/context;
- concise intro/status;
- facts;
- bounded lists;
- sections;
- pagination;
- inline actions where the verified provider surface supports them.

`BalePresentation` renders lists as contiguous bullets and sections as readable paragraph groups. A semantic table, if one is ever supplied, is converted into labeled rows rather than an ASCII grid.

## Explicit non-parity

Bale does not receive:

- Telegram `rich_message` fields;
- Rich Message drafts;
- Telegram-specific RTL/Rich block payloads;
- Telegram deployment controls.

## Protected delivery

The current FANOOS Bale capability contract has no verified forward-protection capability. If canonical delivery requires forwarding/saving protection, Bale fails closed, records the failed outcome through the backend receipt path and does not fall back to the original source.
