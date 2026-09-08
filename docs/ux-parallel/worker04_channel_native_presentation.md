# Worker 04 — Channel-native presentation handoff

## Scope

- Repository: `ArianGhsm/FanoosLearn`
- Parallel base SHA: `fef86a4adcad99cb75327582750dd4cd2df88dea`
- Worker branch: `ux/04-channel-native-presentation`
- Scope: Telegram/Bale transport presentation, runtime feedback, bounded transport fallback, and transport smoke artifacts only.
- No backend/API/schema/business/auth/payment/entitlement contract was changed.
- No deployment or live bot send was performed by this worker.

## Official capability evidence

Evidence was re-checked on **2026-09-08** before implementation.

### Telegram

Official source: <https://core.telegram.org/bots/api>

Current published version at implementation time:

- **Telegram Bot API 10.3 — 2026-08-24**
- Bot API 10.1 introduced Rich Messages, `sendRichMessage`, `sendRichMessageDraft`, and rich editing through `editMessageText.rich_message`.
- Bot API 10.2 added explicit outgoing rich block types.
- Bot API 10.3 added Rich Message buttons, compact tables, and current draft stop controls.
- `InputRichMessage` supports exactly one of `html`, `markdown`, or `blocks`, plus `is_rtl`.
- Official Rich HTML includes headings, paragraphs, dividers, lists, quotations, tables, and details.
- `sendRichMessageDraft` is a private-chat, non-durable 30-second preview. A stable non-zero `draft_id` updates the same draft. `<tg-thinking>` is draft-only.
- `sendRichMessage` and ordinary message methods support `protect_content`.
- Inline callback data is 1–64 bytes.
- `ResponseParameters.retry_after` is the official rate-limit retry hint.
- Direct Bot API document upload is bounded at 50 MB; `getFile` download is bounded at 20 MB.
- Telegram reply handling uses `reply_parameters`.

### Bale

Official source: <https://docs.bale.ai/>

The official Bale documentation did **not** publish a Bot API version/date on the page inspected on 2026-09-08, so no version number is invented in this handoff.

Officially documented primitives used here:

- `getUpdates`
- `sendMessage`
- `editMessageText`
- `sendChatAction`
- `answerCallbackQuery`
- inline keyboard/callback data (1–64 bytes)
- `ResponseParameters.retry_after`
- `getFile` up to 20,000,000 bytes
- document/audio/voice upload up to 50,000,000 bytes
- reply via `reply_to_message_id`

No native Telegram-style Rich Message, Rich Draft, `is_rtl`, or `protect_content` equivalent was found in the current official Bale documentation. FANOOS therefore does not infer or emulate those capabilities.

## Capability matrix

| Capability | Telegram | Bale | FANOOS policy |
| --- | --- | --- | --- |
| Long polling / `getUpdates` | Yes | Yes | Existing independent offsets remain transport-local |
| Text message | Yes | Yes | 4096-char plain fallback |
| Edit text | Yes | Yes | Edit where safe; failed edit becomes a new send |
| Inline keyboard | Yes | Yes | Same frozen callback strings |
| Callback data | 1–64 bytes | 1–64 bytes | UTF-8 byte validation, never character-count guessing |
| Callback acknowledgement | Yes | Yes | Best-effort before nontrivial local/backend work |
| Chat action | Yes | Yes | Immediate truthful activity only |
| Native Rich Message | Yes | Not documented | Telegram Rich-first; Bale plain native fallback |
| Rich edit | Yes | Not documented | Telegram only |
| Rich Thinking Draft | Yes | Not documented | Telegram private chat only |
| Explicit Rich RTL | `is_rtl=true` | Not documented | Telegram Rich output is RTL; Bale relies on native Persian text direction |
| Forward/save protection | `protect_content` | Not documented | Telegram enforced; Bale protected delivery fails closed |
| Document upload | 50 MB | 50,000,000 bytes | Registry enforcement before upload |
| File download | 20 MB | 20,000,000 bytes | Registry evidence only; download path remains elsewhere |
| Reply field | `reply_parameters` | `reply_to_message_id` | Platform-specific payload |
| `retry_after` | Yes | Yes | Preserved in `BotApiError`; presentation never replays business logic |

`DocumentPayload.caption` remains capped at 1024 by the frozen shared model. Bale's transport registry records its broader official caption capability without weakening or editing the shared model.

## Telegram Rich architecture

`packages/python/fanoos_bot/telegram_presentation.py` is a transport presentation adapter.

Baseline `Screen(text, rows, edit, protect_content)` works without Worker 3:

- first non-empty line becomes a heading only when it is short/title-like;
- consecutive `•` lines become a native list;
- `⚠️`, `❌`, `✅`, `ℹ️` prefixed lines become quotations;
- explicit divider-only lines become native dividers;
- everything else is rendered as a paragraph;
- original plain text remains the fallback and order is preserved;
- malformed input disables Rich rendering for that screen instead of guessing.

No table is inferred from arbitrary text. Native `<table>` is emitted only from explicit semantic metadata that contains a valid rectangular data matrix. No `<pre>`, code fence, spacing grid, or ASCII pseudo-table is generated.

Rich actions remain the existing inline keyboard. Rich Message buttons are deliberately not substituted for callback actions, so callback semantics remain frozen.

### Rich feature flag

`FANOOS_TELEGRAM_RICH_UI_ENABLED=true`

- default: `true`
- non-secret
- `false` is a presentation rollback only
- same callback keyboard and business action remain functional in plain mode

## Bale native fallback

`packages/python/fanoos_bot/bale_presentation.py` emits no Telegram-only fields.

Without semantic metadata it preserves the canonical Persian `Screen.text` except newline normalization.

When additive semantic metadata is available later:

- heading/paragraph/quote text remains ordinary native text;
- lists use `•`;
- divider is a Unicode em dash;
- a matrix becomes readable row bullets such as `• ستون: مقدار · ستون: مقدار`;
- it never produces an ASCII/monospace table.

Bale inline keyboards and edits use only officially documented Bale primitives. Protected content is never downgraded: if forward/save protection is required, the adapter fails closed before a network send.

## Callback and replay policy

`BotRuntime.handle_callback` now follows this ordering:

1. best-effort callback acknowledgement;
2. processed-update lookup;
3. activity feedback;
4. application/backend callback;
5. presentation delivery.

A failed acknowledgement does not block the action. No normal navigation callback uses an alert popup.

Rich/plain/edit fallback is entirely inside the transport after the application result already exists:

- Rich send fails -> at most one plain send;
- Rich edit fails -> at most one plain edit;
- edit is not possible/deleted/old/fails -> one new send;
- no fallback calls `application.callback`, order creation, protected issuance, payment verification, deployment request, or any other business operation again.

The Telegram and Bale polling runtimes treat a `BotApiError` raised **after an update is handed to the application** as a presentation failure and advance that update offset. This prevents a provider render/send failure from replaying the business action. Polling-level `getUpdates` failures remain retryable because no application action has run.

Backend idempotency remains authoritative for double taps and all durable effects.

## Waiting/activity policy

`packages/python/fanoos_bot/activity.py` adds a minimal synchronous-runtime activity controller without inventing a job subsystem.

- immediate feedback: `sendChatAction(typing)`;
- after 3 seconds, if the same operation is still running:
  - Telegram private chat + Rich enabled -> one Rich Thinking draft owner;
  - Bale or unsupported Rich path -> ChatAction remains the safe fallback;
- Telegram uses one random non-zero `draft_id` for the operation and reuses it on heartbeat;
- final/error context exit stops future activity updates;
- no percent is emitted without a real completed/total instrument;
- no ETA is emitted;
- no fake timer/progress animation is emitted;
- `can_stop=false` because this wave does not add a new cancellable job contract.

Feature flag:

`FANOOS_TELEGRAM_ACTIVITY_UI_ENABLED=true`

Disabling it affects presentation only.

## Edit/send and protected delivery

- Navigation/status screens use edit when `Screen.edit` and a message ID are available.
- Protected screens are sent as new protected messages rather than trying to alter protection through edit.
- Failed edit falls back to new send inside the transport.
- Receipt-bearing protected text remains atomic; oversized protected text is not split into a partial protected delivery.
- Telegram `protect_content` is serialized on rich/plain/document sends when required.
- Bale rejects protected sends/documents because no official equivalent is documented.
- Document size and caption limits are checked before upload.
- Multipart filenames are sanitized to a bounded ASCII basename; the local temporary path is never included in the provider filename.
- Existing delivery receipt outbox/idempotency behavior is unchanged.

## Update-control presentation boundary

No deployment control-plane contract was changed.

Worker 4 only improves transport behavior around existing update callbacks/status screens:

- callback acknowledgement happens first;
- presentation failure cannot cause duplicate update/deployment business calls;
- existing durable backend request/status remains canonical after restart;
- no SHA/ref/path/shell input was added;
- no raw server log is rendered to users;
- success/failure/rollback semantics remain owned by the existing backend/application projection.

## Worker 3 semantic metadata integration expectation

There is **no hard dependency** on a Worker 3 field in this branch.

The presentation adapters currently probe, in order:

1. `screen.presentation`
2. `screen.presentation_metadata`
3. `screen.semantic`
4. `screen.semantic_blocks`

Integration should preferably expose `screen.presentation` as an additive mapping/object with a `blocks` sequence. Supported block vocabulary:

```text
heading/title/section_heading:
  text
  optional level

paragraph/text:
  text

list/bullets/bullet_list:
  items[]

divider/separator

quote/warning/success/error/info:
  text

table:
  optional caption
  headers[]
  rows[]  # rectangular matrix; max 20 columns for Telegram
```

A block object may expose `kind`/`type` and matching attributes instead of a dictionary. Enum-like kinds with a `.value` string are accepted.

Integration must keep canonical `Screen.text` as the complete functional plain fallback. It must not remove `rows`, `edit`, or `protect_content`. If Worker 3 chooses a different metadata schema, Integration should add a narrow adapter at this extension point rather than changing callback/business semantics.

For a one-chunk screen, `BotRuntime` now passes the original `Screen` instance to the transport rather than reconstructing it, so additive metadata survives integration. Multi-chunk fallback intentionally reconstructs baseline chunks because semantic blocks cannot be safely split without explicit pagination metadata.

## Tests

Changed:

- `tests/bots/test_runtime_transport.py`
- `tests/ux/worker04_channel_presentation_test.py`

The existing bot test entrypoint does not discover `tests/ux` directly, so `test_runtime_transport.py` exposes a scoped `load_tests` bridge that includes the Worker 4 UX suite without modifying central CI/ops files.

Coverage includes:

### Telegram

- capability registry;
- Rich payload + RTL;
- heading/list/quote heuristic adapter;
- explicit native table metadata;
- feature-flag plain rollback;
- inline callback preservation;
- `protect_content`;
- Rich send 400 fallback;
- Rich send 429 fallback boundary;
- network timeout fallback without application replay;
- rich/plain edit failure -> new send;
- Rich Thinking draft payload;
- callback ACK ordering and ACK failure behavior;
- UTF-8 callback byte limit;
- Unicode-safe chunking;
- document caption/size/protection and safe filename behavior.

### Bale

- no Telegram Rich payload;
- `reply_to_message_id` rather than Telegram reply fields;
- native inline keyboard;
- edit -> send fallback;
- explicit unsupported-keyboard failure;
- protected send fail-closed;
- documented file-size boundary;
- Persian Unicode preservation;
- semantic matrix -> readable bullet fallback rather than ASCII table.

### Cross-platform

- same `Screen` callback strings survive unchanged on both transports;
- presentation fallback does not invoke application business actions twice;
- one-chunk `Screen` identity survives to the renderer for future Worker 3 metadata.

Local deterministic harness result before push: **37 tests passed** (12 runtime/baseline bridge tests + 25 Worker 4 presentation/runtime tests). Repository CI must still run on the pushed PR and is the authoritative full-tree validation.

## Smoke artifacts

No live send was performed in this worker chat.

`apps/telegram-bot/smoke.py` is dry-run by default. `--apply` in an owner-only runtime verifies:

- `getMe`
- plain send
- Rich send
- Rich edit
- forced plain fallback
- protected flag fixture containing no real protected data

`apps/bale-bot/smoke.py` is also dry-run by default. `--apply` verifies:

- `getMe`
- send
- inline keyboard
- edit

Both use runtime tokens from environment only. No token or runtime state is committed.

## Runtime/live validation still required

After Integration merges all workers, Codex/runtime validation should:

1. check out the exact integrated release SHA;
2. run the repository deterministic suite and required CI;
3. set/confirm `FANOOS_TELEGRAM_RICH_UI_ENABLED=true` and `FANOOS_TELEGRAM_ACTIVITY_UI_ENABLED=true`;
4. run Telegram smoke in an owner-only chat and visually inspect RTL heading/list/quote/native-table rendering;
5. force Rich off once and verify identical callback actions in plain mode;
6. verify a harmless protected Telegram fixture has forwarding/saving restricted;
7. run Bale smoke and verify send/edit/keyboard with no Telegram-only payload;
8. verify a protected Bale delivery remains fail-closed;
9. verify a long no-total owner-only operation shows truthful activity only, then terminates into the final persistent screen;
10. confirm no bot token, runtime database, logs, backups, or temporary files entered Git.

Do not deploy this worker branch directly. Integration must reconcile Worker 3 metadata first, then use the normal release/backup/CI/deployment gates.
