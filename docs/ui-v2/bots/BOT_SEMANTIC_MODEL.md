# FANOOS UI V2 — Semantic Screen Model

`Screen.text` remains a complete provider-neutral fallback. `ScreenPresentation` is additive presentation metadata only.

## V2 fields

```text
title
semantic_kind
severity
breadcrumb
intro
facts
list_items
sections
pagination
footer
rtl
```

Actions remain in `Screen.rows` as `Button(callback|url)`. The semantic model carries no role, permission grant, entitlement grant, payment proof, storage path or deployment authorization.

## Rendering

`semantic_adapter.semantic_mapping()` maps the screen to neutral blocks:

- heading
- contextual breadcrumb paragraph
- status/paragraph intro
- facts list
- content list
- section heading/body/items
- pagination paragraph
- footer

Telegram transforms these to verified Rich Message HTML/block semantics. Bale transforms the same semantics into readable bounded text. If semantic rendering fails, `Screen.text` remains the fallback.

## Breadcrumb convention

Examples:

```text
درس‌ها › ترمیمی ۱
درس‌ها › ترمیمی ۱ › منابع
برنامه › ۷ روز آینده
بیشتر › نمرات
اعلان‌ها › اطلاعیه‌ها
بیشتر › مدیریت › به‌روزرسانی سرور
```

Breadcrumbs are human labels only; they are not routing or authorization inputs.

## Local presentation routes

`LocalState.presentation_routes` stores:

- short random ref;
- provider;
- provider subject;
- route kind;
- bounded JSON such as cursor/history;
- expiry.

It is disposable, subject-bound and restart-safe. It may remember pagination/presentation context. It must never store or assert roles, membership, grades, orders, payment results, entitlements or deployment permissions.

A route ref always triggers a fresh backend read before domain data is displayed or acted upon.
