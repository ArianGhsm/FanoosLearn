# FANOOS Bot Stage 3 — Telegram Native Handoff

## Scope

Telegram is a first-class presentation channel over the shared FANOOS semantic contract. The application computes one canonical result; `TelegramV3Renderer` turns that result into RTL rich/plain text and an inline keyboard. This stage contains local source and deterministic validation only. It does not deploy, restart a bot, or send a live Telegram message.

## Runtime path

```text
Telegram update
  → BotRuntime (callback ACK first)
  → integrated BotApplication
  → canonical backend read/mutation
  → shared semantic Screen
  → core_to_runtime / ProviderContext
  → TelegramV3Renderer
  → one send/edit provider operation
```

`apps/telegram-bot/runtime.py` imports the integrated application and `JsonBotApiTransport`. The transport advertises `supports_v3_context`, so normal prepared screens use the V3 renderer while the narrow legacy screen/protected-delivery seam remains compatible. No renderer code owns membership, authorization, payment, entitlement or notification truth.

## Telegram presentation contract

- `TelegramV3Renderer` renders headings, context, Persian-first intro/status copy, facts, lists, sections, pagination and RTL rich HTML.
- `pack_actions` groups readable short actions into two-column rows, isolates primary/destructive actions, and keeps pagination plus Back/Home at the bottom.
- Every callback is validated in UTF-8 bytes against Telegram’s 64-byte limit. The transport repeats this guard for the legacy protected keyboard path before any network call.
- Core navigation defaults to edit-safe rendering. Durable/protected screens request a new message. An edit failure may fall back to one new presentation of the same already-computed result.
- Rich-message failure falls back once to the same screen’s plain text; the application callback is not run again.
- Protected V3 results are exactly one plain `sendMessage` with `protect_content=true`. Protected legacy text keeps its accepted one-operation seam; it never receives an ambiguous second send.
- Owner/deployment surfaces remain capability-gated by private Telegram context plus the canonical `deployment.manage` permission. Renderer code only filters presentation actions.

## Provider/API boundaries

Provider/API errors stay in transport/runtime logs and are never used as user-facing copy. Callback ACK is best-effort but is attempted before dedupe, backend work and rendering. Update offsets, processed-update dedupe and delivery receipts remain bounded transport state; the backend remains canonical.

## Validation

`tests/ux-v3/test_bot_telegram_runtime_contract.py` covers:

- semantic RTL rich sections and two-column keyboard packing;
- integrated Home reaching `sendRichMessage` through `BotRuntime`;
- callback ACK ordering and Rich→plain fallback without business replay;
- edit failure fallback without callback replay;
- Unicode callback byte rejection, including the legacy protected path;
- one-operation protected V3 sending.

The existing Telegram/Bale presentation, runtime, protected-delivery, notification, UX-v2 and UX-v3 suites remain regression gates. Live Bot API acceptance (real rich/edit behavior, provider limits, callback interaction and protected media) is intentionally deferred to the deployment stage.

## Next handoff

Bot Stage 4 may extend provider-native account/academic surfaces using the same shared `Screen`/intent contract. Keep business decisions in `integrated_application` plus the canonical backend, preserve subject-bound route refs and receipt/idempotency semantics, and do not introduce Telegram-only domain state.
