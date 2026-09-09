# FANOOS UI V2 — Shared Bot Information Architecture

Telegram and Bale share one semantic product model. Provider packing/rendering may differ; meaning and backend authority do not.

## Primary spine

```text
🏠 خانه
├─ 📚 درس‌ها
├─ 📅 برنامه
├─ 🔔 اعلان‌ها
└─ ➕ بیشتر
```

Home itself is the `🏠 خانه` surface. It includes bounded context, not a second canonical store.

## More

```text
➕ بیشتر
├─ 🎓 نمرات
├─ 📚 منابع
├─ 📝 آزمون‌ها
├─ 💳 خرید و دسترسی
├─ 🏫 فضای آموزشی
├─ 👤 حساب
└─ ⚙️ مدیریت        only after canonical authorization
```

`⚙️ مدیریت` is never shown on Bale. On Telegram it appears only in private chat after `deployment.overview` confirms `deployment.manage`.

## Course spine

```text
📚 درس‌ها
└─ درس
   ├─ 📅 برنامه
   ├─ 📚 منابع
   ├─ 📝 آزمون‌ها
   ├─ 🎓 نمرات
   └─ 📢 اطلاعیه‌ها
```

The current backend allows real course-scoped schedule/resources/grades because those projections contain canonical course IDs. Assessment and course-announcement surfaces are intentionally gap states until safe projections exist.

## Announcement vs notification semantics

- `📢 اطلاعیه‌ها`: canonical workspace broadcast projection currently available through `/announcements/list`.
- `🔔 اعلان‌ها`: personal delivery/inbox concept. The bot does not treat `NotificationPump` state as inbox truth.

## Navigation rules

Every secondary surface exposes a contextual back target and/or `🏠 خانه`. Standard concepts:

- `home`
- `back` represented by the specific semantic parent callback
- `courses`
- `course:<stable-id>`
- pagination through short opaque local route refs
- `workspaces`
- retry only where retry cannot replay a business mutation
- confirmation/cancel for unlink and deployment

Callbacks may carry stable canonical IDs, but IDs are never rendered in user copy. Cursor/history state is stored as bounded, subject-bound presentation correlation and reauthorized on the next backend read.

## Command strategy

Advertised commands are only:

- `/start`
- `/home`
- `/help`

Existing technical commands may remain accepted for backward compatibility, but they are not presented as normal product navigation.
