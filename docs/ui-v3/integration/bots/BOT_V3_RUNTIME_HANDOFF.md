# FANOOS Bot V3 — Runtime Handoff

Design Lock: `FANOOS-UX-2026.09-R1`

This document hands the merged source candidate to the later deployment/runtime-validation task. It does not authorize or perform deployment.

## Runtime entrypoints

The canonical application entrypoint for both messaging providers is:

- `packages/python/fanoos_bot/integrated_application.py`

Provider runtimes remain:

- `apps/telegram-bot/runtime.py`
- `apps/bale-bot/runtime.py`

Both must instantiate `fanoos_bot.integrated_application.BotApplication`. Business authority remains in the existing signed backend/internal API paths; V3 presentation code must not create parallel business truth.

## Request path

The intended runtime path is:

`incoming update → callback ACK when applicable → integrated application → canonical backend read/mutation → semantic V3 Screen or accepted migration payload → provider adapter → Telegram/Bale renderer → one provider operation → existing receipt/idempotency handling`

Important invariants:

- callback ACK is best-effort and occurs before the business callback action where provider semantics require it;
- renderer failure must not replay the application/business action;
- route refs are presentation correlation only and are bound to platform + subject + expiry;
- selected workspace is canonical backend state, never inferred from a single membership;
- provider-specific capability differences must not fork business semantics.

## Telegram runtime

Expected source behavior:

- V3 non-protected screens use Telegram V3 rendering with Persian/RTL rich output when supported;
- a Rich presentation failure may fall back once to plain output of the same already-computed screen;
- protected content is one atomic protected provider operation; no ambiguous second send is allowed;
- accepted Stage 7 protected transport behavior is retained at the narrow migration seam without business replay;
- owner deployment management is only reachable in private Telegram after canonical backend authorization and `deployment.manage` gating;
- callback payloads remain within provider limits.

Deployment-stage validation required:

- real callback ACK ordering;
- rich-message acceptance on the real Bot API endpoint;
- edit fallback behavior;
- `protect_content` behavior on real text/media;
- owner private-chat gating.

## Bale runtime

Expected source behavior:

- Bale consumes the same semantic application result through the Bale V3 renderer;
- no Telegram-only `rich_message`, Telegram reply model or Update Server action is emitted;
- existing supported inline callbacks remain bounded;
- protected originals fail closed where an approved equivalent forward-protection capability is unavailable;
- an explanatory V3 fail-closed screen may be shown only when it does not expose the protected original.

Deployment-stage validation required:

- real Bale text/keyboard rendering;
- callback handling/edit behavior;
- provider limits;
- protected-content fail-closed behavior.

## Protected media

The existing canonical derivative flow is retained:

`authorize → issue → consume/prepare derivative → provider protected send where supported → receipt`

Do not add a source/original fallback. Do not retry a protection-sensitive provider send after an ambiguous provider result. Existing delivery receipts and idempotency remain authoritative.

## Owner / deployment control plane

This source integration does not expand deployment authority. The runtime must continue to rely on the canonical backend deployment decision. The messaging presentation layer must never accept arbitrary branch, ref, SHA, remote, filesystem path, shell command or deployment command from a callback payload.

Bale has no owner Update Server equivalent.

## Configuration and secrets

No new secret is introduced by Bot V3 integration. Existing secrets/configuration must continue to be injected through the established deployment mechanism. Do not commit provider tokens, service credentials, HMAC material or production identifiers.

## Required post-deploy validation flags

```text
LIVE_BROWSER_VALIDATION_REQUIRED=true
TELEGRAM_RUNTIME_VALIDATION_REQUIRED=true
BALE_RUNTIME_VALIDATION_REQUIRED=true
PROTECTED_MEDIA_RUNTIME_VALIDATION_REQUIRED=true
DEPLOYED=false
```

`DEPLOYED=false` is intentional in this source handoff and must not be changed by the source-integration PR.
