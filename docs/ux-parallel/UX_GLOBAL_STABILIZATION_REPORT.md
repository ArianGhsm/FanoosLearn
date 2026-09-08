# FANOOS UX Global Stabilization Report

## Scope and provenance

- Repository: `ArianGhsm/FanoosLearn`
- Starting integrated main SHA: `f61be504b24975b82a35702705eecdcd799cee7a`
- Stabilization branch: `stabilize/ux-global-debug`
- Pull request: `#15` — `fix(ux): stabilize integrated web and bot presentation`
- Deployment: **not performed**
- Stage 8 migration/cutover: **not performed**

The stabilization pass treats the four UX workstreams as one integrated product and preserves the existing backend, authorization, payment, entitlement, protected-delivery, notification, and deployment-control authorities.

## Test inventory

Repository-side deterministic coverage used by the final gate includes:

- PHP syntax across application/operations/scripts/tests
- database/schema static contracts
- repository secret/text safety checks
- exact-commit release build
- PHP 8.2 and PHP 8.4 static/unit lanes
- MySQL 8.4 integration suite via `tests/run.php`
- tenant isolation and migration recovery
- core platform, content, commerce, entitlement, protected delivery, service-auth/linking, deployment control, and bot/platform handoff tests
- Python compile/import checks
- bot application/runtime/transport tests
- Telegram/Bale Worker 3 and Worker 4 UX tests
- protected-media and notification worker deterministic tests
- Worker 1 static web quality
- Worker 2 domain presentation tests
- integrated web UX guards
- added domain fuzz/edge tests
- added integrated bot semantic/transport stabilization tests

CI was extended so the domain fuzz suite runs in the normal `web-ux` job rather than remaining local-only.

## Bugs found and fixed

| Severity | Root cause | Fix | Regression coverage |
| --- | --- | --- | --- |
| High | Protected inline `Screen.text` could be replaced by the generic semantic `protected_delivery_ready` summary, allowing a transport receipt to describe a send that did not contain the authorized payload. | Protected inline delivery preserves exact authorized `Screen.text`; Telegram uses one plain provider operation for this path; Bale also preserves the exact payload. | `tests/bots/test_ux_stabilization.py` protected payload and one-operation tests. |
| High | Rich-first retry behavior is acceptable for ordinary presentation, but an ambiguous Rich failure on protected content must not cause a second provider send. | Real protected semantic delivery bypasses Rich and performs one exact protected `sendMessage` operation. | One-provider-operation failure test. |
| Medium | Browser `sessionStorage` could restore a workspace independently of the canonical session selection. | Workspace state now comes only from backend `selected_workspace_id`; stale `fanoos_workspace` caching was removed entirely. | Integrated web guard rejects any `fanoos_workspace` use. |
| Medium | A GET from the previous workspace could resolve after a workspace mutation and overwrite the current view. | Workspace mutation immediately advances the request serial and blocks view/search loads until server success/failure resolves. | Integrated web race guards. |
| Medium | Logout network/response ambiguity was previously treated as confirmed logout and later as confirmed active session. Both can invent server truth. | Presentation state is cleared only after confirmed success. Ambiguous failure says only that the final logout state was not confirmed. | Integrated logout semantic guards. |
| Medium | Dynamic domain-asset injection added a duplicate/race-sensitive loading path. | Domain CSS/JS are parser-declared local assets exactly once; `domain-ux.js` precedes `app.js`; obsolete loader removed. | Integrated asset-order and duplicate guards. |
| Medium | Database-style naive UTC timestamps could be interpreted in the browser's local timezone, making website time differ from canonical workspace time/bot output. | Canonical account projection exposes `workspace.timezone_name`; web render context formats database instants in that workspace timezone; malformed/naive timestamps without explicit UTC assumption fail closed. | Worker 2 datetime regression tests plus integrated projection guard. |
| Medium | Bot entitlement rendering mapped any non-`True` value, including missing/unknown values, to a negative state. | `True` → active, `False` → inactive, missing/unknown → unknown. | Bot stabilization entitlement test. |
| Low/Medium | Telegram could heuristically reinterpret plain text after malformed semantic metadata, despite metadata being authoritative when present. | Metadata-present malformed/unsupported input falls back exactly to `Screen.text`; heuristics are only for screens with no metadata. | Invalid metadata regression test. |
| Low | Website/bot payment/resource labels had minor semantic drift. | Shared Persian vocabulary aligned for confirmed payment and common resource types. | Worker 2 and bot localization tests. |
| Low | CSS unresolved custom properties were not explicitly caught by the integration suite. | Added static custom-property definition/use validation. | `integration_web_ux_test.js`. |

No Critical issue remained after repository-side fixes. No unresolved High repository-side issue is known at the merge gate.

## Web functional result

Repository-side flow inspection covers login → canonical account/workspace projection → dashboard → module navigation → search → logout.

Verified properties:

- domain assets load exactly once with deterministic local ordering;
- all module selectors remain present;
- canonical workspace state changes only after successful server mutation;
- stale view/search responses cannot overwrite a later workspace selection;
- submit/switch/search controls release on both success and failure paths;
- `sessionStorage` contains presentation/session helper state only and no workspace business truth;
- normal retry callbacks repeat safe GETs only;
- `.active` and `aria-pressed` remain synchronized;
- successful login moves focus to the dashboard region;
- successful logout clears presentation-only storage; ambiguous logout does not claim a server outcome.

## Domain edge/fuzz result

The dedicated domain renderers for schedule, grades, announcements, academics, resources, assessments, forms, orders, and search are exercised with empty values, nulls, missing optionals, unknown status, long Persian/mixed text, malicious HTML, bidi controls, extreme numbers, zero/malformed money, timezone-aware/naive/invalid datetimes, duplicates, and long technical tokens.

Expected deterministic properties hold in repository tests:

- no crash;
- no HTML/script/image injection from untrusted text;
- no raw object dump;
- no workspace/source/provider identifier leak from generic row iteration;
- unknown business state is not invented;
- long LTR technical values use isolation semantics.

## Accessibility/static visual result

Static checks confirm Persian/RTL document semantics, local design tokens, visible-focus infrastructure, landmarks/labels/live regions, synchronized pressed state, reduced-motion support inherited from the shell, local SVG use, no unresolved `var()` use, and no remote font/icon/CDN dependency introduced by this wave.

`LIVE_BROWSER_VALIDATION_REQUIRED=true`

Repository inspection cannot truthfully certify 320px real-layout behavior, computed visual overflow, keyboard behavior in each browser, or screen-reader output without a live browser/runtime pass.

## Bot shared application result

Fake-backend and existing deterministic suites exercise linked/unlinked start/account behavior, link failures, unlink, workspace list/switch, today/tomorrow, grades, announcements, resources, order/payment status, protected delivery, notifications, and owner update-control screens.

Presentation invariants after stabilization:

- Persian user-facing status text; no provider/internal error strings in normal UI;
- UUIDs remain callbacks/internal references rather than ordinary presentation facts;
- callback payloads/rows are not mutated by semantic adapters;
- payment success is only described from backend-confirmed status;
- entitlement language distinguishes false from unknown;
- protected delivery never reports success for a substituted generic payload.

## Worker 3 → Worker 4 adapter result

Actual `BotApplication` screens for Home, Workspace, Grades, Announcements, Resources, Payment, Update Server, and Error are rendered through both `TelegramPresentation` and `BalePresentation` in regression tests.

Verified:

- semantic metadata is preferred when valid;
- title/intro/facts/list/sections/footer survive the shared adapter;
- Telegram Rich output is RTL and bounded by existing adapter limits;
- `Screen.rows` remain unchanged;
- `Screen.text` remains the complete fallback;
- Bale receives structured readable plain text and no Telegram Rich payload;
- protected inline payloads are an explicit exception where the authorized `Screen.text` is canonical and must not be replaced by a generic semantic summary.

## Callback and transport failure result

Existing runtime/Worker 4 tests plus stabilization regressions cover callback ACK failure, ACK-before-business ordering, rich/plain fallback, edit fallback, callback size bounds, protected-send fail-closed behavior, duplicate protected callback/update dedupe, receipt outbox bounds, restart-visible processed-update/delivery state, notification send/receipt retry, and single-operation protected document delivery.

Critical invariant retained: a presentation/transport failure does not re-run application business logic. Polling offsets advance after an update has been handed to the application, while polling-level failures before dispatch remain retryable.

## Notification result

`NotificationPump` retains canonical backend claim/lease and receipt semantics. Worker 3 semantic notification rendering remains presentation-only.

The stabilization suite adds the important `send success → receipt failure → retry` scenario and verifies that the second iteration sends only the receipt and does not resend the notification.

No new notification inbox/list API was invented.

## Cross-channel consistency matrix

| Concept | Website | Telegram | Bale | Result |
| --- | --- | --- | --- | --- |
| Workspace | فضای آموزشی | فضای آموزشی | فضای آموزشی | PASS |
| Schedule | برنامه | برنامه | برنامه | PASS |
| Grade | نمرات | نمرات من | نمرات من | PASS |
| Announcement | اطلاعیه‌ها | اطلاعیه‌ها | اطلاعیه‌ها | PASS |
| Resource | منابع / localized resource type | منابع / same common type vocabulary | same | PASS |
| Assessment | تمرین و آزمون | shared semantic vocabulary where exposed | same | PASS |
| Purchase | خرید و دسترسی | خرید و دسترسی | خرید و دسترسی | PASS |
| Payment pending | در انتظار پرداخت | در انتظار پرداخت | در انتظار پرداخت | PASS |
| Payment success | پرداخت تأیید شده | پرداخت تأیید شده | پرداخت تأیید شده | PASS |
| Access granted | دسترسی فعال | فعال | فعال | PASS-semantic |
| Access denied | دسترسی فعال نیست / محدود | فعال نیست / denied error text | same | PASS-semantic |
| Account | حساب | حساب | حساب | PASS |
| Notification | اطلاعیه/notification semantics | semantic notification screen | same plain semantics | PASS |
| Update available/running/failed | not exposed as false site claim | localized owner control-plane state | feature unavailable warning | PASS-capability-aware |
| Generic error | localized safe error | localized safe error | localized safe error | PASS |
| Empty state | domain-specific Persian | domain-specific Persian | same semantic screen | PASS |

Appearance remains channel-specific; semantic/business meaning remains backend-authoritative.

## Security regression result

The unchanged MySQL integration suite continues to cover tenant isolation, session auth/CSRF, scoped RBAC, commerce/payment server authority, entitlement/protected-resource authorization, service HMAC/replay and messaging linking, protected delivery/media capability paths, notification receipt behavior, deployment management/control plane, and bot/platform handoff.

Repository secret/text safety checks and exact-commit artifact construction remain enabled. The stabilization PR contains no runtime database, environment file, credential, token, backup, log, PID, or production-state file.

Result: **PASS for deterministic repository-side security regression coverage**.

## CI coverage result

`.github/workflows/ci.yml` now runs all web UX suites, including the added fuzz test. Existing Python deterministic entrypoints already compile/run bot/worker code and bridge Worker 3/Worker 4 UX suites. PHP 8.2/8.4 and MySQL 8.4 lanes remain intact.

Merge rule: the exact final PR head must have full GitHub Actions CI green. A CI result from an older head is not sufficient. The exact final check is verified externally at merge time to avoid creating an infinite docs-only “record the latest CI run” commit loop.

## Remaining validation

`LIVE_BROWSER_VALIDATION_REQUIRED=true`

Required later: iOS Safari, Android Chrome, desktop browser layout/keyboard/screen-reader validation.

`RUNTIME_VALIDATION_REQUIRED=true`

Required later: live Telegram Rich/edit/fallback/protection behavior, Bale live send/edit/keyboard behavior and fail-closed protected-content behavior, and environment/service configuration. These are runtime validation items, not repository-side failures.

## Stabilization status

Repository-side deterministic stabilization is eligible for merge only when the exact final PR head is green and self-review shows no Critical/High unresolved issue.

No deployment is part of this report.
