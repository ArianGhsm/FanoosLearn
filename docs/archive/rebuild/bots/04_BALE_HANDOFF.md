# FANOOS Bot Stage 4 — Bale Native Handoff

## Scope

Bale is a first-class FANOOS channel over the same canonical application, semantic screens and provider-neutral intents used by Telegram. Bale receives readable Persian text and supported inline keyboards; it never receives Telegram-only rich payload fields. This stage is local source and deterministic validation only: no live Bale message, deployment, restart or migration was performed.

## Runtime path

```text
Bale update
  → apps/bale-bot/runtime.py
  → BotRuntime
  → integrated BotApplication
  → shared semantic Screen
  → ProviderContext + BaleV3Renderer
  → one Bale send/edit operation
```

`apps/bale-bot/runtime.py` imports `fanoos_bot.integrated_application.BotApplication` and uses `BaleTransport(JsonBotApiTransport)`. The transport keeps only bounded offset/dedupe/receipt state. Membership, workspace, authorization, payment, entitlement, content and notification truth remain backend-owned.

## Bale presentation contract

- `BaleV3Renderer` renders title, workspace/course context, Persian intro/status copy, facts, lists, sections, pagination and navigation in readable Markdown-safe text.
- Malformed presentation-only metadata falls back to the already-available plain screen text; it never becomes a provider error shown to the user.
- Shared `pack_actions` keeps short actions in two-column rows and preserves Back/Home/pagination semantics at the bottom.
- Callback payloads remain provider-neutral, UTF-8 byte-bounded and subject-bound through the existing shared wiring; no Bale-specific domain state is introduced.
- `edit_if_safe` screens use Bale `editMessageText` when a current message exists. Durable, protected or provider-fallback screens use a new message.
- Bale transport payloads contain `text`, supported inline keyboard data and Bale’s `reply_to_message_id` when applicable; they do not contain Telegram `rich_message` or `reply_parameters` fields.

## Capability differences and fail-closed behavior

Bale has no approved equivalent to Telegram forward protection in the current capability matrix. A protected original therefore cannot be delivered. The renderer returns a safe explanatory screen with navigation only and never exposes the protected source text; legacy protected originals are rejected before a provider call. The application does not retry or substitute an unprotected original.

Owner/deployment management is Telegram-private only. Bale filters Update Server/management actions even when a context contains the canonical `deployment.manage` permission and shows a provider-specific explanation with safe navigation. This is a presentation capability boundary, not a new authorization rule.

## Validation

`tests/ux-v3/test_bot_bale_runtime_contract.py` covers:

- provider-native Persian rendering with Back/Home and edit semantics;
- integrated Bale runtime reaching `sendMessage` through the shared application;
- absence of Telegram-only payload fields;
- protected-content fail-closed behavior without source disclosure;
- no Update Server action on Bale, including with owner permission facts.

The existing cross-channel, runtime, notification, protected-delivery, UX-v2 and UX-v3 suites remain regression gates. Real Bale API formatting, callback/edit interaction and live provider limits remain deferred to the deployment-stage smoke checklist.

## Next handoff

Bot Stage 5 can extend shared academic/content surfaces while retaining this capability matrix. Keep Telegram and Bale as renderers/adapters over one canonical backend, preserve Back/Home and receipt/idempotency semantics, and do not create channel-local domain truth or protected-content fallbacks.
