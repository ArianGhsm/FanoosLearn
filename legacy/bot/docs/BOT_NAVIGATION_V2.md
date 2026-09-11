# Dent1402Bot canonical navigation v2

This document is the canonical navigation contract for the Telegram and Bale bot surfaces. It supplements `BOT_UX_SYSTEM.md`; business semantics, permissions, ordering and callback intent are shared. Platform adapters may only change rendering details required by the native platform.

## Canonical bot navigation

The authenticated Home information architecture is fixed in this order:

1. `🧭 مرکز نوید` — one primary full-width row.
2. `📚 جزوات` + `💳 اشتراک جزوات`.
3. `📊 نمرات` + `🗂 امور کلاس`.
4. `👤 حساب من` + `🔔 اعلان‌ها` + `❓ راهنما`.

Owners receive `🛠 مدیریت ربات` as an additional owner-only row after the canonical student rows. The owner button is not a website-dashboard shortcut. Website-only admin shortcuts do not belong in this menu.

Legacy/stale callbacks remain routable where practical, but they must land on the current destination. In particular, the old ClassOps owner callback routes to the current owner ClassOps screen instead of reviving the retired flat management UI.

Every submenu has one deterministic parent. `↩️` returns to that parent; `🏠 خانه` always returns to Home. Callback payloads remain short, versioned, and contain no authorization claims or personal data.

## Student ClassOps

Student `🗂 امور کلاس` is intentionally separate from owner management:

1. `📅 ماه پیش رو` + `📝 امتحان‌ها`
2. `📌 رویدادها` + `✅ کارها و ددلاین‌ها`
3. `👥 گروه‌بندی من` + `🔔 اعلان‌های امور کلاس`
4. `↩️ بازگشت`

No owner-management button is rendered in the student ClassOps home. Authorization is still checked by the signed website API and handler layer; button visibility is not an authorization boundary.

`ماه پیش رو` is a read projection over the existing canonical ClassOps store plus the authoritative Term 7 schedule/assignment resolver. It does not copy schedule data into bot storage. It covers today through the next 30 days, sorts chronologically, identifies overdue items, separates today/this week/next week/later, uses Tehran/Solar-Hijri visible dates, and paginates bounded output.

`گروه‌بندی من` uses `academicTerm7Self`. Owner grouping continues to use the existing Term 7 roster/assignment/leader authority; no bot-local grouping source is introduced.

## Owner management

The canonical path is:

`🏠 Home` → `🛠 مدیریت ربات` → `🗂 مدیریت امور کلاس`.

Owner ClassOps exposes bot-native workflows for adding supported exam/event/task/deadline/requirement items, viewing upcoming items, revision-aware editing, confirmed cancel/archive operations, notification status, canonical Term 7 grouping, and the existing digest views.

Create/update flows use the existing ClassOps Stage 2 preview/confirm contract. Updates carry `expectedRevision`; stale revisions fail closed. Destructive lifecycle actions require a separate confirmation screen before the existing server-issued opaque callback is executed.

## Service and notification status

Status markers have fixed semantics:

- `🟢` healthy/ready/successfully delivered
- `🟡` degraded, warning, scheduled, planned, retrying or otherwise requiring attention
- `🔴` unavailable/failed
- `⚪️` unknown, not checked, superseded or cancelled where no active failure is implied

A state is never shown as healthy merely because a menu rendered. `classopsRuntimeStatus` reports only checks actually proven by that signed request; the other bot platform remains `unknown` unless its state is independently available.

Owner ClassOps notification status reads the canonical notification store and the existing Stage 2 delivery-intent state. It does not create a second notification feed or delivery database. Draft/planned/scheduled/delivered/failed states remain distinct and platform state is shown only when it exists in the canonical delivery layer.

## Persistence and parity guard

This UI version introduces no Telegram-specific, Bale-specific, or shadow ClassOps persistence. Canonical authorities remain:

- ClassOps item store and Stage 2 state
- canonical notification subsystem
- Term 7 schedule/assignment authority
- website account/role authority
- existing payment and grade authorities

Both `service.py` and `bale_service.py` install the same shared navigation layer after the existing ClassOps and Term 7 integrations. Any future menu change must update both surfaces through the shared semantic layer and retain deterministic stale-callback routing.
