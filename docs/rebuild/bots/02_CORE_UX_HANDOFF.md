# FANOOS Bot Stage 2 — Core UX Handoff

## Scope

Stage 2 turns the shared Bot Stage 1 semantic layer into the user-facing shell for Telegram and Bale. Both providers receive the same Persian-first `Screen` and callback intents; provider adapters only render that contract. This stage is local source work only: it does not deploy, migrate production, or send live messages.

## State model

The bot reads link and membership truth from the canonical backend on every entry point. A single membership is never selected implicitly.

| Backend state | Screen | User action |
| --- | --- | --- |
| Messaging account is not linked | `onboarding.unlinked` | Open FANOOS and start a fresh connection request |
| Linked, no workspace membership | `onboarding.linked_no_workspace` from Home; `workspace.empty` from the workspace list | Open Account/help/site or return Home; no fake create-workspace action |
| Linked, memberships but no selected workspace | `workspace.list` | Explicitly select a workspace |
| Linked, selected workspace | `home.active` | See the bounded academic summary and use the primary menu |

Backend link errors (`messaging_link_required` and `messaging_link_not_found`) are mapped to onboarding copy instead of exposing API codes. Empty or unavailable data is represented as an explicit factual slot state, never as fake progress or stale cached truth.

## Home and navigation

`home.active` always shows the selected workspace context, one schedule item from the current day (content, empty, or unavailable), and one latest-announcement slot (content, empty, or unavailable). Its primary actions are:

`📚 درس‌ها`, `📅 برنامه`, `🎓 نمرات`, `🔔 اعلان‌ها`, `📚 منابع`, `📝 آزمون‌ها`, `💳 خرید و دسترسی`, `👤 حساب`, and `بیشتر`.

Home, Back, and Cancel are semantic intents. Workspace lists use five options per page and a subject-bound `ws.page|p=N` callback for previous/next navigation. Callback payloads remain within the provider limit; long future intents use the existing expiring, subject-bound route mechanism.

## Account and unlink

Account shows the provider, linked state, workspace count, and selected workspace (or “انتخاب نشده”). Unlink requires the explicit confirmation screen and then calls the canonical backend revoke operation exactly once. The success screen explains that only this messaging connection was removed; the FANOOS account and learning data remain intact. Website actions are omitted when a local configuration has no website URL, so local tests never create invalid buttons.

## Implementation notes

- Reused the Stage 1 `Screen`, `Action`, `Pagination`, `LocalState`, and provider-neutral wiring instead of adding a second bot architecture.
- Added payment as a first-class primary action and a compact workspace-page intent in `ui_v3/core/actions.py`.
- Centralized workspace list rendering and onboarding/error mapping in `integrated_application.py` and `ui_v3/wiring.py`.
- Made shared onboarding, empty-workspace, and account screens safe for a missing website URL.
- No technical commands, UUIDs, backend error codes, or internal paths are presented as normal user navigation/copy.

## Validation and next handoff

`tests/ux-v3/test_bot_core_ux_contract.py` covers unlinked onboarding, the two zero states, explicit workspace selection and pagination, active Home slots/menu bounds, callbacks, account, and unlink. Existing Bot, worker, UX-v2, and UX-v3 suites remain the regression gate. Bot Stage 3 can build academic feature screens on this stable shell, keeping the same backend re-read, semantic-intent, provider-neutral contract.
