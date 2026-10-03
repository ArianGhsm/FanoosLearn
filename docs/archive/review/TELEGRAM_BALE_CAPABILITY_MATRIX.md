# Telegram + Bale official capability matrix for FANOOS Stage 7

Checked: 2026-09-07  
Purpose: freeze only capabilities that are documented by the current official platform documentation before Stage 7 bot implementation.

Official sources checked:

- Telegram Bot API: <https://core.telegram.org/bots/api>
- Telegram Bot Features / deep linking: <https://core.telegram.org/bots/features>
- Bale Bot API documentation: <https://docs.bale.ai/>

Telegram's current official Bot API page identifies **Bot API 10.3 (2026-08-24)** and includes native Rich Messages. Bale's documentation does not expose an equivalent version number in the inspected page, so Bale capabilities below are recorded as observed from the official documentation on the audit date rather than assigned an invented API version.

## Status language

- **SUPPORTED**: directly documented by the official API checked above.
- **SUPPORTED WITH LIMITS**: documented, but FANOOS must preserve an explicit documented limit or client caveat.
- **UNDOCUMENTED**: no official support was found in the inspected official documentation. FANOOS must not send a Telegram-shaped field and hope Bale accepts it.
- **NOT BASELINE**: possible through a different deployment/API product but not part of the ordinary FANOOS Bot API baseline.

## Capability matrix

| Capability | Telegram Bot API | Bale Bot API | Stage 7 decision |
| --- | --- | --- | --- |
| Long polling / `getUpdates` | **SUPPORTED** | **SUPPORTED** | Both adapters may poll. Offset/state is transport-local and separate. |
| Webhook | **SUPPORTED** | **SUPPORTED** | Both can use webhook; do not run polling and webhook simultaneously for one token. Bale currently documents webhook ports 443 and 88. |
| Text message length | **1–4096 characters** for `sendMessage` after entity parsing | **1–4096 characters** | Shared semantic chunker must split Unicode/Persian safely and each adapter must enforce its platform limit. |
| Edit message text | **SUPPORTED**, 1–4096 characters for normal edited text | **SUPPORTED**, 1–4096 characters | Use edit only behind capability checks; fall back to a new semantic message where edit fails or is unsuitable. |
| Inline keyboard | **SUPPORTED** | **SUPPORTED** | Shared view model; platform-specific serialization. |
| Callback query | **SUPPORTED** | **SUPPORTED** | Acknowledge immediately before expensive backend/file I/O. Bale documents an old-client caveat for callback-query acknowledgement. |
| Callback data | **1–64 bytes** | **1–64 bytes** | Enforce UTF-8 **bytes**, not Python character count. Use compact opaque references; never embed entitlement, shell or arbitrary ref data. |
| Chat action | **SUPPORTED**, status lasts at most about 5 seconds | **SUPPORTED**, status lasts at most 6 seconds | Use only for a noticeable operation. Never use chat action or messages to fabricate percentage/ETA. |
| Native rich/structured messages | **SUPPORTED**; Bot API 10.3 includes Rich Messages with structured blocks/tables/media | **UNDOCUMENTED** equivalent | Telegram may use native rich messages when stable/client-appropriate. Bale gets semantic Markdown/text + keyboards; do not fake a rich table with fragile ASCII art. |
| File download through normal cloud bot file API | **20 MB** via `getFile` | **20 MB** via `getFile` | Treat larger inbound files as unsupported unless FANOOS deliberately selects a different official transport and updates this registry. |
| General file upload through normal multipart Bot API | **50 MB** for non-photo files; photos 10 MB | **50 MB** for non-image files; images 10 MB | Preflight file size before upload; do not copy Voice's project-specific 2 GB constants. |
| File send by URL | Photos 5 MB, other content 20 MB; method/type restrictions apply | Images 5 MB, other content 20 MB; method/type restrictions apply | Prefer server-controlled upload or platform `file_id` according to the secure-delivery policy, not arbitrary remote URLs. |
| Reuse platform `file_id` | **SUPPORTED**; ID is bot-specific | **SUPPORTED**; ID is bot-specific | Bounded transport cache only. `file_id` never proves entitlement and cannot cross bot tokens/platforms. |
| `protect_content` / forward-save restriction | **SUPPORTED**; documented to protect sent content from forwarding and saving | **UNDOCUMENTED** in current official Bale bot docs | Telegram sets protection whenever backend issuance requires it. Bale direct protected delivery must fail closed while no official equivalent is documented. |
| Bot `/start` deep-link payload | **SUPPORTED**, up to 64 allowed characters (`A-Z`, `a-z`, `0-9`, `_`, `-`) | **UNDOCUMENTED** in current official Bale bot docs | Telegram may carry only an opaque one-time challenge reference. Do not assume Bale start payloads; use a normal HTTPS/account-link flow or another explicitly contracted path. |
| Reply/quote | **SUPPORTED** via `reply_parameters` and current reply semantics | **SUPPORTED** via `reply_to_message_id` | Adapter-owned capability difference; shared semantics should express “reply to” without sharing wire fields. |
| Send document/media | **SUPPORTED** | **SUPPORTED** | Adapter handles MIME/size/API differences. Protected-media authorization remains backend-owned. |
| Rate-limit retry hint | **SUPPORTED** via `ResponseParameters.retry_after` | **SUPPORTED** via `ResponseParameters.retry_after` | Honor server `retry_after`, jitter retries and maintain idempotency. Do not hard-code an undocumented universal TPS ceiling. |
| Normal broadcast/rate behavior | Server flood control; paid broadcast is a separate optional Telegram feature | Normal API rate is documented as interaction-dependent; Bale Business API has separate higher quota/charging | Stage 7 baseline uses ordinary APIs and backpressure. Paid/bulk products are outside baseline unless explicitly approved later. |
| Web app button | **SUPPORTED** | **SUPPORTED** | Presence of web-app support does not prove a protected-delivery or account-link security contract. |

## Telegram notes

### Ordinary cloud API versus local Bot API server

The FANOOS baseline in this matrix is the ordinary Telegram Cloud Bot API. Telegram documents a separate local Bot API server option with different file behavior, including much larger uploads/downloads. That is a runtime architecture choice, not a free capability increase. Stage 7 must not set 2 GB limits merely because a legacy project used a different transport arrangement.

If FANOOS later deploys Telegram's local Bot API server, the capability registry must be changed together with deployment/health/backup/smoke evidence and tests that prove which transport is active.

### Rich Messages

Telegram native Rich Messages are now official in Bot API 10.3. They are a Telegram presentation optimization, not shared business semantics. The shared layer should emit structured semantic data; the Telegram adapter may render an approved subset natively and must retain a plain-text fallback for unsupported clients/content shapes.

### Callback handling

Telegram documents that clients show a progress bar after an inline callback until `answerCallbackQuery` is called. Therefore:

1. validate only the minimum envelope needed to identify the callback;
2. acknowledge the callback promptly;
3. perform backend authorization/I/O afterwards;
4. edit/send the terminal semantic result.

Acknowledgement is not authorization.

### Deep links and account linking

Telegram explicitly presents deep links as a mechanism that can help connect a Telegram account to an account on another platform. FANOOS must still use an opaque, short-lived backend challenge. A `/start` parameter is transport data, not identity proof.

## Bale notes

### Do not infer parity from Telegram compatibility

Bale documents that its Bot API is based on Telegram's API with changes. That statement is not a license to send every current Telegram field. FANOOS must maintain an allowlisted capability/serialization layer and mark missing features explicitly.

### Callback compatibility caveat

Bale documents that callback-query acknowledgement was added to clients in Khordad 1404 and provides a way to recognize older clients. The adapter therefore needs a semantic fallback (for example a normal response message) for old clients while still acknowledging supported callbacks promptly.

### No documented `protect_content`

A search of the current official Bale Bot API page found no `protect_content` field. Consequently:

- do not serialize Telegram's `protect_content` to Bale;
- do not claim Bale protected-forward parity;
- if a FANOOS delivery issuance says `forward_protection_required=true`, the Bale adapter must refuse direct file delivery;
- a controlled web alternative may be used only if the canonical FANOOS platform later defines and authorizes that path.

This is fail-closed behavior, not a statement that screenshots/copying can ever be made impossible.

### No documented bot `/start` payload

The current Bale bot API page did not document Telegram-style start/deep-link payload semantics. The Stage 7 implementation must not depend on a Bale start parameter for secure account linking. A normal HTTPS link/challenge can still be shown in Bale because URL buttons are documented.

### File limits and Business API

For the ordinary Bale Bot API, official docs state:

- direct multipart images: up to 10 MB;
- direct multipart other files: up to 50 MB;
- URL images: up to 5 MB;
- URL other files: up to 20 MB;
- `getFile`: up to 20 MB.

Bale also documents a separate Business API for higher/independent sending quota; file sending there is restricted to existing `file_id` values. FANOOS should not silently switch products or assume paid/bulk entitlement in the normal adapter.

## Capability registry requirements for Stage 7 code

The Stage 7 shared package should expose capability facts rather than platform-name conditionals scattered through conversation handlers. At minimum, each runtime capability record should cover:

- max text characters;
- max callback bytes;
- edit support;
- inline keyboard/callback support;
- callback acknowledgement behavior;
- chat-action support/duration;
- native-rich support;
- download/upload limits for the configured transport;
- protected-forward support;
- start/deep-link support;
- reply shape;
- file/media support;
- retry-after support.

The registry is not permission or entitlement state. Security decisions still come from the FANOOS backend.

## Live-smoke rule

Official documentation is necessary but not sufficient for a parity claim. Before production parity is declared, a real non-production Telegram bot and Bale bot must pass a live smoke covering at least `getMe`, update receipt, text, keyboard/callback acknowledgement, edit, one allowed document send, rate/retry handling where safely inducible, and each claimed special capability. Protected Telegram delivery needs its own smoke. Bale forward protection must remain `UNSUPPORTED` until official documentation and live evidence support it.