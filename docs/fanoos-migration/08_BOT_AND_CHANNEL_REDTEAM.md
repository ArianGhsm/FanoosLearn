# Stage 8 — Bot / Notification / Channel Red-Team Closure

Status: **deterministic source tests PASS; live Telegram/Bale/process-restart evidence remains required**.

Telegram and Bale are thin clients of the same canonical Platform truth. Their local state may hold update offsets, bounded send/idempotency correlations and retry receipts, but no local row grants membership, payment success, entitlement, workspace authority or protected-content access.

## Notification and restart matrix

| Scenario | Expected invariant | Repository result | Live remainder |
| --- | --- | --- | --- |
| claim → send → receipt | one canonical delivery, safe provider ref, durable receipt | PASS | provider smoke |
| crash before send | lease/retry can resume; no false delivered receipt | PASS_WITH_RUNTIME_VALIDATION | kill process before provider call |
| crash after send before receipt | retry receipt without re-sending same protected/business delivery | PASS | real provider timing rehearsal |
| duplicate receipt | idempotent canonical receipt | PASS | provider retry observation |
| poison receipt | later receipts are not starved | PASS | process/log observation |
| Telegram/Bale separation | tokens, offsets, processes, local state independent | PASS | service layout inspection |
| update offset restart | provider update is not silently lost/replayed into business mutation | PASS_WITH_RUNTIME_VALIDATION | restart polling services |
| callback replay | business action idempotent; protected document not resent | PASS | live duplicate callback |
| render/ack failure | business semantics are not replayed merely because presentation failed | PASS | network-failure injection |
| notification render fallback | same canonical meaning; no raw object dump/error | PASS | provider rendering |
| long Persian/Unicode | bounded chunking; RTL/Unicode retained; callback limits respected | PASS | mobile provider UI |

## Canonical cross-channel truth

Workspace selection is persisted/resolved by Platform. Web `sessionStorage`, Telegram local SQLite/state and Bale local state are presentation conveniences only. Schedule/grade/resource/search/payment/entitlement decisions are re-read from Platform for the addressed workspace. Channel-local “paid”, prior delivery, button callback, cached file ID or notification receipt is never an entitlement source.

Payment UX is deliberately truthful: order/pending/verified/failure are canonical server states. Telegram/Bale cannot convert a redirect, callback, UI message or historical legacy payment label into a grant. Protected content is issued/consumed against current authorization and, for personalized media, authorized again at derivative redemption.

## Telegram

Telegram presentation is Rich-first. Semantic metadata is converted to Telegram-safe formatting/keyboards while preserving the authorized payload. Malformed semantic metadata falls back to exact safe plain content instead of heuristic re-interpretation. Protected document delivery is one provider operation with `protect_content` when the resource contract requires it; provider/receipt ambiguity does not cause the same update to resend a successfully submitted protected document.

Owner Update Server UX exists only in private Telegram and is a control-plane client, not a shell. The bot sees safe overview/request/status fields. It cannot choose remote/ref/branch/SHA/path/command/environment. Platform authorization for `deployment.manage` is mandatory even when the private-chat adapter gate passes.

Runtime acceptance: real `getMe`, private/non-private identity behavior, one safe message/edit/callback, one protected test PDF, duplicate callback, restart around update receipt, and Update Server permission/read/request/status smoke.

## Bale

Bale uses the same application/business semantics but an explicit capability matrix. Telegram-only rich formatting is not serialized into Bale. Where provider forward protection cannot be guaranteed, protected delivery fails closed rather than silently sending an unprotected original/derivative. This is an intentional capability difference, not a parity defect.

Runtime acceptance: provider identity, safe send/edit/callback where supported, long Persian text, restart/update offset behavior, order/entitlement projection, and a protected-resource attempt that demonstrates the configured fail-closed behavior.

## Protected media handoff

For an object-backed protected resource the bot consumes a current delivery issuance, Platform enqueues one idempotent media job, the worker redeems the source capability, validates/rasterizes/watermarks within limits, uploads exact checksummed derivative bytes through a job-bound capability, and Platform creates a personalized artifact. Bot then gets a user/workspace/platform-bound derivative capability and performs the provider send. No original-source fallback exists.

Local media correlation is bounded/disposable and cannot grant access. Recipient A’s artifact/capability cannot be used by B. A failed/expired/revoked authorization at final derivative issuance/redeem must stop delivery.

## Semantic UX gate

Final channel vocabulary remains aligned with the stabilized web UX: فضای آموزشی, برنامه, نمرات, اطلاعیه‌ها, منابع, تمرین و آزمون, خرید/سفارش, وضعیت پرداخت, دسترسی, حساب. Emoji may be used moderately as semantic markers; it must not replace labels or create fake progress. Errors shown to users are bounded product messages, not stack traces, SQL/provider payloads, paths, credentials or raw logs.

## Runtime evidence packet

Codex must return exact release SHA, service versions/process identifiers without secrets, provider account identity result, timestamped safe smoke results, restart tests, notification/receipt evidence, protected-media checksum/artifact evidence, Update Server authorization/status evidence, and redacted error excerpts for any failure. A repository PASS cannot be substituted for this packet.

`RUNTIME_VALIDATION_REQUIRED=true`
