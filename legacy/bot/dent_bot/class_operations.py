from __future__ import annotations

import html
from datetime import date
from typing import Any, Callable

from .api import BotApiError
from .app import DentBotApp
from .classops_runtime import install_classops_runtime
from .persian_datetime import (
    PERSIAN_WEEKDAYS,
    format_jalali_datetime,
    gregorian_to_jalali,
    to_persian_digits,
)
from .site_api import SiteApiError
from .ui import Screen, button, frame, keyboard, native_rich_text

_INSTALLED = False
_DIALOG_KIND = "class-operations-product"

_ITEM_LABELS = {
    "announcement": "اطلاعیه",
    "event": "رویداد",
    "class_change": "تغییر کلاس",
    "deadline": "مهلت",
    "task": "تکلیف",
    "requirement": "مورد الزامی",
    "exam": "امتحان",
    "critical_notice": "اطلاعیه مهم",
    "service_reminder": "یادآوری",
    "schedule_ref": "برنامه کلاس",
}

_STATE_LABELS = {
    "draft": "پیش‌نویس",
    "scheduled": "زمان‌بندی‌شده",
    "active": "فعال",
    "completed": "انجام‌شده",
    "cancelled": "لغوشده",
    "canceled": "لغوشده",
    "archived": "بایگانی‌شده",
    "pending": "در انتظار",
    "submitted": "ارسال‌شده",
    "needs_revision": "نیازمند اصلاح",
    "waived": "نیاز نیست",
    "overdue": "عقب‌افتاده",
    "acked": "تأییدشده",
    "not_required": "نیاز به تأیید ندارد",
    "superseded": "جایگزین‌شده",
}

_IMPORTANCE_LABELS = {
    "normal": "عادی",
    "important": "مهم",
    "critical": "فوری",
}

_FILTERS: dict[str, set[str]] = {
    "schedule": {"event", "class_change", "deadline", "schedule_ref"},
    "tasks": {"task", "requirement"},
    "exams": {"exam"},
    "important": {"critical_notice", "announcement"},
    "services": {"service_reminder"},
}

_FILTER_TITLES = {
    "all": "همه موارد",
    "schedule": "برنامه و تغییرات",
    "tasks": "تکالیف و کارها",
    "exams": "امتحان‌ها",
    "important": "اطلاعیه‌ها",
    "services": "یادآوری‌ها",
}

_DIGEST_SECTION_ICONS = {
    "critical_ack": "🚨",
    "changes": "🔄",
    "schedule": "📅",
    "deadlines": "⏳",
    "tasks_requirements": "✅",
    "outstanding_tasks": "✅",
    "exams": "📝",
    "service_reminders": "🔔",
    "other": "•",
}

_EDIT_FALLBACK_MARKERS = (
    "message to edit not found",
    "message can't be edited",
    "message_id_invalid",
    "message_id invalid",
    "message edit time expired",
)


def _visible(value: object, limit: int = 1800) -> str:
    raw = str(value or "").replace("\x00", "").strip()[:limit]
    return html.escape(to_persian_digits(raw))


def _plain_visible(value: object, limit: int = 1800) -> str:
    raw = str(value or "").replace("\x00", "").strip()[:limit]
    return to_persian_digits(raw)


def _site_url(app: DentBotApp) -> str:
    value = str(getattr(app, "site_url", "") or "").rstrip("/")
    return value if value.startswith("https://") else ""


def _item_label(item_type: object) -> str:
    return _ITEM_LABELS.get(str(item_type or ""), "مورد کلاس")


def _state_label(state: object) -> str:
    raw = str(state or "")
    return _STATE_LABELS.get(raw, "نامشخص" if raw else "")


def _importance_label(value: object) -> str:
    return _IMPORTANCE_LABELS.get(str(value or ""), "")


def _format_time(value: object) -> str:
    return format_jalali_datetime(value)


def _format_local_date(value: object) -> str:
    raw = str(value or "").strip()
    if not raw:
        return ""
    try:
        parsed = date.fromisoformat(raw)
    except ValueError:
        return ""
    year, month, day = gregorian_to_jalali(parsed)
    return to_persian_digits(f"{PERSIAN_WEEKDAYS[parsed.weekday()]} {year}/{month}/{day}")


def _timing_values(timing: dict[str, Any]) -> list[tuple[str, str]]:
    values: list[tuple[str, str]] = []
    if bool(timing.get("allDay")) and timing.get("localDate"):
        local_date = _format_local_date(timing.get("localDate"))
        if local_date:
            values.append(("زمان", f"{local_date} · تمام‌روز"))
        return values
    for key, label in (
        ("startsAt", "شروع"),
        ("startsAtUtc", "شروع"),
        ("endsAt", "پایان"),
        ("endsAtUtc", "پایان"),
        ("dueAt", "مهلت"),
        ("dueAtUtc", "مهلت"),
    ):
        if any(existing == label for existing, _ in values):
            continue
        rendered = _format_time(timing.get(key))
        if rendered:
            values.append((label, rendered))
    return values


def _item_effective_time(item: dict[str, Any]) -> str:
    timing = dict(item.get("timing") or {})
    if bool(timing.get("allDay")):
        return _format_local_date(timing.get("localDate")) or "تمام‌روز"
    for key in ("dueAt", "dueAtUtc", "startsAt", "startsAtUtc"):
        rendered = _format_time(timing.get(key))
        if rendered:
            return rendered
    return "—"


def _item_course(item: dict[str, Any]) -> str:
    direct = str(item.get("courseTitle") or "").strip()
    if direct:
        return direct
    course = item.get("course")
    if isinstance(course, dict):
        return str(course.get("title") or "").strip()
    return ""


def _fact_table(caption: str, rows: list[tuple[str, str]]) -> str:
    if not rows:
        return ""
    parts = [f"<table bordered striped compact><caption>{_visible(caption, 100)}</caption>"]
    for label, value in rows:
        parts.append(f"<tr><th>{_visible(label, 80)}</th><td>{_visible(value, 1000)}</td></tr>")
    parts.append("</table>")
    return "".join(parts)


def _message_id(callback: dict[str, Any] | None) -> int:
    try:
        return int(dict((callback or {}).get("message") or {}).get("message_id") or 0)
    except (TypeError, ValueError):
        return 0


def _render_screen(
    app: DentBotApp,
    chat_id: int,
    screen: Screen,
    *,
    callback: dict[str, Any] | None = None,
    message_id: int = 0,
) -> None:
    """Keep Class Operations in one app-like bot message.

    Telegram's current editMessageText.rich_message path has been verified against
    the production Bot API. Every Class Operations callback therefore edits the
    existing message, including regular->native-rich transitions. Sending a new
    message is reserved for commands/user text or an actually uneditable message.
    """
    target_message_id = int(message_id or _message_id(callback))
    if target_message_id > 0:
        try:
            app.api.edit(chat_id, target_message_id, screen.text, screen.keyboard)
            return
        except BotApiError as error:
            lowered = str(error).lower()
            if "message is not modified" in lowered:
                return
            if not any(marker in lowered for marker in _EDIT_FALLBACK_MARKERS):
                raise
    app.api.send(chat_id, screen.text, screen.keyboard)


def _home_keyboard(screen: Screen) -> Screen:
    rows = [list(row) for row in screen.keyboard.get("inline_keyboard", [])]
    if any(
        str(item.get("callback_data") or "").endswith(":class-operations")
        for row in rows for item in row if isinstance(item, dict)
    ):
        return screen
    entry = [button("📅 امور کلاس", action="class-operations", style="primary")]
    insert_at = len(rows)
    for index, row in enumerate(rows):
        callbacks = {str(item.get("callback_data") or "") for item in row if isinstance(item, dict)}
        if any(value.endswith(":notifications") or value.endswith(":help") for value in callbacks):
            insert_at = index
            break
    rows.insert(insert_at, entry)
    return Screen(screen.text, keyboard(*rows))


def _class_home_screen(app: DentBotApp, *, role: str, items: list[dict[str, Any]]) -> Screen:
    active = [
        item for item in items
        if str(item.get("status") or "") not in {"cancelled", "canceled", "archived", "completed"}
    ]
    schedule = sum(1 for item in active if str(item.get("type") or "") in _FILTERS["schedule"])
    tasks = sum(1 for item in active if str(item.get("type") or "") in _FILTERS["tasks"])
    exams = sum(1 for item in active if str(item.get("type") or "") == "exam")
    important = sum(1 for item in active if str(item.get("type") or "") == "critical_notice")
    summary_bits = []
    if schedule:
        summary_bits.append(f"{to_persian_digits(schedule)} برنامه/تغییر")
    if tasks:
        summary_bits.append(f"{to_persian_digits(tasks)} کار")
    if exams:
        summary_bits.append(f"{to_persian_digits(exams)} امتحان")
    if important:
        summary_bits.append(f"{to_persian_digits(important)} فوری")
    status = " · ".join(summary_bits) if summary_bits else "فعلاً مورد فعالی ثبت نشده است."
    rows = [
        [
            button("📅 برنامه و تغییرات", action="class-operations:list:schedule", style="primary"),
            button("✅ تکالیف و کارها", action="class-operations:list:tasks"),
        ],
        [
            button("📝 امتحان‌ها", action="class-operations:list:exams"),
            button("🔔 اطلاعیه‌ها", action="class-operations:list:important"),
        ],
        [
            button("🌤 فردا", action="class-operations:tomorrow"),
            button("🗓 هفته پیش رو", action="class-operations:weekly"),
        ],
        [button("همه موارد", action="class-operations:list:all")],
    ]
    if role == "owner":
        rows.append([button("⚙️ مدیریت امور کلاس", action="class-operations:owner")])
    rows.append([button("🏠 منوی اصلی", action="home")])
    return Screen(
        frame(
            "📅 امور کلاس",
            "برنامه، تکلیف، امتحان و اطلاعیه‌های کلاس را از همین پیام دنبال کن.",
            status,
        ),
        keyboard(*rows),
    )


def _item_line(item: dict[str, Any]) -> str:
    kind = _item_label(item.get("type"))
    title = _visible(item.get("title") or kind, 90)
    when = _item_effective_time(item)
    state = _state_label(item.get("status"))
    meta = " · ".join(value for value in (kind, state, when if when != "—" else "") if value)
    return f"• <b>{title}</b>" + (f"\n  {_visible(meta)}" if meta else "")


def _list_screen(items: list[dict[str, Any]], filter_name: str) -> Screen:
    allowed = _FILTERS.get(filter_name)
    filtered = [item for item in items if allowed is None or str(item.get("type") or "") in allowed]
    title = _FILTER_TITLES.get(filter_name, _FILTER_TITLES["all"])
    visible_items = filtered[:8]
    fallback = [f"<b>{html.escape(title)}</b>", ""]
    rows: list[list[dict]] = []
    for item in visible_items:
        fallback.append(_item_line(item))
        item_id = str(item.get("id") or "")
        if item_id.startswith("cop_"):
            label = _plain_visible(item.get("title") or _item_label(item.get("type")), 28)
            rows.append([button(label, action=f"class-operations:item:{item_id}")])
    if not filtered:
        fallback.append("موردی در این بخش ثبت نشده است.")
    elif len(filtered) > len(visible_items):
        fallback.extend(("", f"{to_persian_digits(len(filtered) - len(visible_items))} مورد دیگر نمایش داده نشده است."))

    rich = [f"<h2>{html.escape(title)}</h2>"]
    if visible_items:
        rich.append(
            "<table bordered striped compact>"
            "<tr><th>مورد</th><th>زمان</th><th>وضعیت</th></tr>"
        )
        for item in visible_items:
            kind = _item_label(item.get("type"))
            rich.append(
                f"<tr><td><b>{_visible(item.get('title') or kind, 100)}</b><br/>{_visible(kind)}</td>"
                f"<td>{_visible(_item_effective_time(item), 120)}</td>"
                f"<td>{_visible(_state_label(item.get('status')) or '—', 80)}</td></tr>"
            )
        rich.append("</table>")
        if len(filtered) > len(visible_items):
            rich.append(
                f"<footer>{to_persian_digits(len(filtered) - len(visible_items))} مورد دیگر در این فهرست نمایش داده نشده است.</footer>"
            )
    else:
        rich.append("<blockquote>موردی در این بخش ثبت نشده است.</blockquote>")

    rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _detail_screen(item: dict[str, Any], actions: dict[str, Any]) -> Screen:
    kind = _item_label(item.get("type"))
    title_plain = _plain_visible(item.get("title") or kind, 180)
    description_plain = _plain_visible(item.get("description"), 2600)
    facts: list[tuple[str, str]] = [("نوع", kind)]
    status = _state_label(item.get("status"))
    if status:
        facts.append(("وضعیت", status))
    course = _item_course(item)
    if course:
        facts.append(("درس", course))
    facts.extend(_timing_values(dict(item.get("timing") or {})))
    if item.get("location"):
        facts.append(("مکان", str(item.get("location") or "")))
    importance = _importance_label(item.get("importance"))
    if importance and importance != "عادی":
        facts.append(("اهمیت", importance))

    task = dict(item.get("task") or {})
    if task:
        facts.append(("وضعیت من", _state_label(task.get("state") or "pending") or "در انتظار"))

    ack = dict(item.get("ack") or {})
    ack_line = ""
    if ack:
        ack_line = (
            "✅ این اطلاعیه را تأیید کرده‌ای."
            if ack.get("acked")
            else "⏳ این اطلاعیه هنوز نیازمند تأیید تو است."
        )

    service = dict(item.get("service") or {})
    service_line = ""
    if service:
        local = dict(service.get("state") or {})
        facts.append(("وضعیت یادآوری", _state_label(local.get("state") or "pending") or "در انتظار"))
        service_line = "این وضعیت فقط در ربات ثبت می‌شود و انجام واقعی در صبا را تأیید نمی‌کند."

    fallback = [f"<b>{_visible(title_plain)}</b>", ""]
    for label, value in facts:
        fallback.append(f"<b>{_visible(label)}:</b> {_visible(value)}")
    if ack_line:
        fallback.extend(("", f"<blockquote>{_visible(ack_line)}</blockquote>"))
    if service_line:
        fallback.extend(("", f"<blockquote>{_visible(service_line)}</blockquote>"))
    if description_plain:
        fallback.extend(("", f"<blockquote expandable><b>توضیحات</b>\n{_visible(description_plain, 2600)}</blockquote>"))

    rich = [f"<h2>{_visible(title_plain)}</h2>", _fact_table("جزئیات", facts)]
    if ack_line:
        rich.append(f"<blockquote>{_visible(ack_line)}</blockquote>")
    if service_line:
        rich.append(f"<blockquote>{_visible(service_line)}</blockquote>")
    if description_plain:
        rich.append(f"<details><summary>توضیحات</summary><p>{_visible(description_plain, 2600)}</p></details>")

    labels = {
        "task_submit": "📤 ثبت به‌عنوان ارسال‌شده",
        "task_complete": "✅ انجام شد",
        "ack": "✅ دیدم و تأیید می‌کنم",
        "service_completed": "✅ انجام شد",
        "service_waived": "نیاز نیست",
        "cancel": "لغو مورد",
        "archive": "بایگانی",
    }
    rows: list[list[dict]] = []
    for key in ("task_submit", "task_complete", "ack", "service_completed", "service_waived"):
        token = str(actions.get(key) or "")
        if token.startswith("cxo_"):
            rows.append([button(labels[key], action=token, style="success")])
    for key in ("cancel", "archive"):
        token = str(actions.get(key) or "")
        if token.startswith("cxo_"):
            rows.append([button(labels[key], action=token, style="danger")])
    rows.append([button("↩️ فهرست", action="class-operations:list:all"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _digest_item_time(item: dict[str, Any]) -> str:
    timing = dict(item.get("timing") or {})
    if bool(timing.get("allDay")):
        local = _format_local_date(timing.get("localDate"))
        return f"{local} · تمام‌روز" if local else "تمام‌روز"
    due = _format_time(timing.get("dueAtUtc") or timing.get("dueAt"))
    if due:
        return f"{due} · مهلت"
    start = _format_time(timing.get("startsAtUtc") or timing.get("startsAt"))
    if not start:
        return _format_time(item.get("effectiveAtUtc"))
    end = _format_time(timing.get("endsAtUtc") or timing.get("endsAt"))
    return f"{start} تا {end}" if end and end != start else start


def _digest_screen(response: dict[str, Any], *, title: str) -> Screen:
    digest = dict(response.get("digest") or {})
    sections = [section for section in digest.get("sections", []) if isinstance(section, dict)]
    budget = dict(digest.get("budget") or {})
    fallback = [f"<b>{html.escape(title)}</b>"]
    rich = [f"<h2>{html.escape(title)}</h2>"]
    has_items = False

    for section in sections:
        items = [item for item in section.get("items", []) if isinstance(item, dict)]
        if not items:
            continue
        has_items = True
        label = _plain_visible(section.get("label") or "موارد", 120)
        icon = _DIGEST_SECTION_ICONS.get(str(section.get("key") or ""), "•")
        fallback.extend(("", f"<b>{icon} {_visible(label)}</b>"))
        rich.append(
            f"<table bordered striped compact><caption>{html.escape(icon)} {_visible(label)}</caption>"
            "<tr><th>مورد</th><th>زمان</th><th>جزئیات</th></tr>"
        )
        for item in items:
            item_title = _plain_visible(item.get("title") or _item_label(item.get("itemType")), 120)
            change = _plain_visible(item.get("changeLabel"), 40)
            rendered_title = f"{change} · {item_title}" if change else item_title
            when = _digest_item_time(item)
            course = (
                str(dict(item.get("course") or {}).get("title") or "").strip()
                if isinstance(item.get("course"), dict) else ""
            )
            location = str(item.get("location") or "").strip()
            detail_bits = [bit for bit in (course, location) if bit]
            fallback.append(
                "• <b>" + _visible(rendered_title) + "</b>"
                + (f"\n  {_visible(when)}" if when else "")
                + (f"\n  {_visible(' · '.join(detail_bits))}" if detail_bits else "")
            )
            rich.append(
                f"<tr><td><b>{_visible(rendered_title)}</b></td>"
                f"<td>{_visible(when or '—')}</td>"
                f"<td>{_visible(' · '.join(detail_bits) or '—')}</td></tr>"
            )
        rich.append("</table>")

    if not has_items:
        fallback.extend(("", "برای این بازه موردی ثبت نشده است."))
        rich.append("<blockquote>برای این بازه موردی ثبت نشده است.</blockquote>")

    if bool(budget.get("truncated")):
        omitted = max(0, int(budget.get("omittedItems") or 0))
        notice = f"{to_persian_digits(omitted)} مورد دیگر در این خلاصه نمایش داده نشده است."
        fallback.extend(("", f"<blockquote>{notice}</blockquote>"))
        rich.append(f"<footer>{notice}</footer>")

    return Screen(
        native_rich_text("\n".join(fallback), "".join(rich)),
        keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
    )


def _owner_screen(app: DentBotApp, capabilities: dict[str, Any]) -> Screen:
    ai_state = str(dict(capabilities.get("ai") or {}).get("state") or "unconfigured")
    status = (
        "پیش‌نویس هوشمند آماده استفاده است؛ ثبت نهایی همیشه با تأیید تو انجام می‌شود."
        if ai_state == "configured"
        else "پیش‌نویس هوشمند فعلاً غیرفعال است؛ ثبت دستی و مدیریت سایت در دسترس‌اند."
    )
    rows = [
        [button("✍️ ثبت اطلاعیه", action="class-operations:compose:announcement", style="primary")],
    ]
    if ai_state == "configured":
        rows.append([button("🤖 پیش‌نویس هوشمند", action="class-operations:compose:ai")])
    rows.append([button("📋 موارد فعال", action="class-operations:list:all")])
    site = _site_url(app)
    if site:
        rows.append([button("مدیریت کامل در سایت", url=site + "/classops/")])
    rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen(
        frame(
            "⚙️ مدیریت امور کلاس",
            "کارهای سریع را همین‌جا انجام بده؛ تنظیمات کامل مخاطب، زمان‌بندی و جزئیات در سایت است.",
            status,
        ),
        keyboard(*rows),
    )


def _compose_prompt(kind: str) -> Screen:
    if kind == "ai":
        return Screen(
            frame(
                "🤖 پیش‌نویس هوشمند",
                "متن خام اطلاعیه یا تغییر موردنظر را در یک پیام بفرست.",
                "هوش مصنوعی فقط پیش‌نویس می‌سازد؛ بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود.",
            ),
            keyboard(
                [button("انصراف", action="class-operations:compose:cancel", style="danger")],
                [button("↩️ مدیریت امور کلاس", action="class-operations:owner")],
            ),
        )
    if kind == "announcement-description":
        return Screen(
            frame(
                "✍️ توضیحات اطلاعیه",
                "متن اطلاعیه را بفرست. اگر توضیح لازم نیست فقط «-» بفرست.",
                "قبل از ثبت، پیش‌نمایش مخاطبان و مسیر ارسال را می‌بینی.",
            ),
            keyboard(
                [button("انصراف", action="class-operations:compose:cancel", style="danger")],
                [button("↩️ مدیریت امور کلاس", action="class-operations:owner")],
            ),
        )
    return Screen(
        frame(
            "✍️ اطلاعیه جدید",
            "عنوان کوتاه اطلاعیه را در یک پیام بفرست.",
            "بعد از عنوان، متن اطلاعیه را می‌گیریم و پیش‌نمایش نهایی نشان داده می‌شود.",
        ),
        keyboard(
            [button("انصراف", action="class-operations:compose:cancel", style="danger")],
            [button("↩️ مدیریت امور کلاس", action="class-operations:owner")],
        ),
    )


def _quick_announcement_help() -> Screen:
    return Screen(
        frame(
            "✍️ اطلاعیه جدید",
            "از دکمه «ثبت اطلاعیه» در مدیریت امور کلاس استفاده کن.",
            "مسیر مرحله‌ای ربات جایگزین دستور متنی شده است؛ پیش از تأیید نهایی چیزی منتشر نمی‌شود.",
        ),
        keyboard([button("⚙️ مدیریت امور کلاس", action="class-operations:owner")], [button("🏠 خانه", action="home")]),
    )


def _ai_help() -> Screen:
    return Screen(
        frame(
            "🤖 پیش‌نویس هوشمند",
            "از دکمه «پیش‌نویس هوشمند» در مدیریت امور کلاس استفاده کن و متن را همان‌جا بفرست.",
            "بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود.",
        ),
        keyboard([button("⚙️ مدیریت امور کلاس", action="class-operations:owner")], [button("🏠 خانه", action="home")]),
    )


def _preview_screen(app: DentBotApp, response: dict[str, Any]) -> Screen:
    preview = dict(response.get("preview") or {})
    item = dict(preview.get("item") or {})
    audience = dict(preview.get("audience") or {})
    destinations = [value for value in preview.get("destinations", []) if isinstance(value, dict)]
    token = str(response.get("confirmToken") or "")
    facts: list[tuple[str, str]] = [
        ("نوع", _item_label(item.get("type"))),
        ("عنوان", str(item.get("title") or "بدون عنوان")),
    ]
    course = _item_course(item)
    if course:
        facts.append(("درس", course))
    facts.extend(_timing_values(dict(item.get("timing") or {})))
    if item.get("location"):
        facts.append(("مکان", str(item.get("location") or "")))
    facts.extend((
        ("مخاطبان", f"{to_persian_digits(int(audience.get('total') or 0))} نفر"),
        ("مسیرهای ارسال", to_persian_digits(len(destinations))),
    ))
    description = _plain_visible(item.get("description"), 1600)
    warnings = [str(value) for value in audience.get("warnings", []) if value]
    unresolved_count = max(0, int(audience.get("unresolvedCount") or 0))

    fallback = ["<b>👁 پیش‌نمایش قبل از ثبت</b>", ""]
    for label, value in facts:
        fallback.append(f"<b>{_visible(label)}:</b> {_visible(value)}")
    if description:
        fallback.extend(("", f"<blockquote expandable><b>متن</b>\n{_visible(description, 1600)}</blockquote>"))
    if unresolved_count or warnings:
        fallback.extend(("", "<blockquote>⚠️ این پیش‌نمایش هنوز مورد نیازمند بررسی دارد.</blockquote>"))

    rich = ["<h2>👁 پیش‌نمایش قبل از ثبت</h2>", _fact_table("جزئیات", facts)]
    if description:
        rich.append(f"<details><summary>متن اطلاعیه</summary><p>{_visible(description, 1600)}</p></details>")
    if unresolved_count or warnings:
        bits = []
        if unresolved_count:
            bits.append(f"{to_persian_digits(unresolved_count)} مورد حل‌نشده")
        if warnings:
            bits.append("هشدار مخاطبان")
        rich.append(f"<blockquote>⚠️ {_visible(' · '.join(bits))}</blockquote>")
    rich.append("<footer>تا قبل از تأیید صریح، چیزی ثبت یا ارسال نمی‌شود.</footer>")

    rows: list[list[dict]] = []
    if token.startswith("cxo_"):
        rows.append([button("✅ تأیید و ثبت", action=token, style="success")])
    site = _site_url(app)
    if site:
        rows.append([button("ویرایش کامل در سایت", url=site + "/classops/")])
    rows.append([button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _ai_draft_screen(app: DentBotApp, response: dict[str, Any]) -> Screen:
    draft = dict(response.get("draft") or {})
    fields = dict(draft.get("fields") or {})
    unresolved = [str(value) for value in draft.get("unresolved", []) if value]
    facts: list[tuple[str, str]] = []
    labels = {"type": "نوع", "title": "عنوان", "location": "مکان", "importance": "اهمیت"}
    for key in ("type", "title", "location", "importance"):
        value = fields.get(key)
        if value in (None, ""):
            continue
        if key == "type":
            rendered = _item_label(value)
        elif key == "importance":
            rendered = _importance_label(value) or str(value)
        else:
            rendered = str(value)
        facts.append((labels[key], rendered))
    description = _plain_visible(fields.get("description"), 1600)

    fallback = [
        "<b>🤖 پیش‌نویس پیشنهادی</b>",
        "",
        "<blockquote>این فقط پیش‌نویس است؛ بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود.</blockquote>",
    ]
    for label, value in facts:
        fallback.append(f"<b>{_visible(label)}:</b> {_visible(value)}")
    if description:
        fallback.extend(("", f"<blockquote expandable><b>متن</b>\n{_visible(description, 1600)}</blockquote>"))
    if unresolved:
        fallback.extend(("", f"<blockquote>نیازمند تکمیل: {_visible('، '.join(unresolved), 1000)}</blockquote>"))

    rich = [
        "<h2>🤖 پیش‌نویس پیشنهادی</h2>",
        "<blockquote>این فقط پیش‌نویس است؛ بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود.</blockquote>",
    ]
    if facts:
        rich.append(_fact_table("فیلدهای پیشنهادی", facts))
    if description:
        rich.append(f"<details><summary>متن</summary><p>{_visible(description, 1600)}</p></details>")
    if unresolved:
        rich.append(f"<details><summary>نیازمند تکمیل</summary><p>{_visible('، '.join(unresolved), 1000)}</p></details>")

    rows: list[list[dict]] = []
    site = _site_url(app)
    if site:
        rows.append([button("ویرایش و ادامه در سایت", url=site + "/classops/", style="primary")])
    rows.append([button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _parse_context(update: dict[str, Any]) -> tuple[dict[str, Any], dict[str, Any], int, int, str, str]:
    callback = dict(update.get("callback_query") or {})
    message = dict(callback.get("message") or update.get("message") or {})
    sender = dict(callback.get("from") or message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
    except (TypeError, ValueError):
        chat_id = 0
    try:
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        user_id = 0
    text = str(message.get("text") or "").strip()
    raw_data = str(callback.get("data") or "").strip()
    data = raw_data[3:] if raw_data.startswith("v1:") else raw_data
    return callback, chat, chat_id, user_id, text, data


def _legacy_action(data: str) -> str:
    if not data.startswith("classops:"):
        return data
    suffix = data[len("classops:"):]
    mapping = {
        "menu": "class-operations",
        "items": "class-operations:list:all",
        "tomorrow": "class-operations:tomorrow",
        "weekly": "class-operations:weekly",
        "draft-help": "class-operations:new-announcement",
        "ai-help": "class-operations:ai",
    }
    if suffix.startswith("item:"):
        return "class-operations:" + suffix
    return mapping.get(suffix, data)


def _is_product_callback(data: object) -> bool:
    raw = str(data or "")
    if raw.startswith("v1:"):
        raw = raw[3:]
    return (
        raw == "class-operations"
        or raw.startswith("class-operations:")
        or raw.startswith("classops:")
        or raw.startswith("cxo_")
    )


def _is_product_command(text: object) -> bool:
    first = str(text or "").strip().lower().split(maxsplit=1)
    if not first:
        return False
    return first[0] == "/classops" or first[0].startswith("/classops@")


def _access_screen(app: DentBotApp, user_id: int) -> Screen | None:
    gate = getattr(app, "_private_access_gate", None)
    if not callable(gate):
        return None
    return gate(user_id)


def _owner_capabilities(app: DentBotApp, user_id: int) -> dict[str, Any] | None:
    response = app.site_api.request("classopsCapabilities", user_id)
    if str(response.get("role") or "student") != "owner":
        return None
    return dict(response.get("capabilities") or {})


def _dialog_origin(dialog: dict[str, Any] | None) -> int:
    try:
        return int(dict((dialog or {}).get("payload") or {}).get("originMessageId") or 0)
    except (TypeError, ValueError):
        return 0


def _build_announcement_request(title: str, description: str) -> dict[str, Any]:
    return {
        "item": {
            "cohortKey": "dentistry-1402",
            "type": "announcement",
            "title": title[:160],
            "description": description[:4000],
            "importance": "normal",
            "requireAck": False,
            "status": "draft",
        },
        "audienceSpec": {
            "version": "classops-audience-v1",
            "resolutionMode": "snapshot",
            "expression": {"op": "whole_cohort"},
            "includeStudentNumbers": [],
            "excludeStudentNumbers": [],
        },
        "destinations": ["private_users"],
    }


def _handle_dialog_message(app: DentBotApp, message: dict[str, Any], dialog: dict[str, Any]) -> bool:
    sender = dict(message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        user_id = int(sender.get("id") or 0)
        chat_id = int(chat.get("id") or user_id)
    except (TypeError, ValueError):
        return True
    if user_id != int(getattr(app, "owner_id", -1)):
        app.state.clear_dialog(user_id)
        return False
    text = str(message.get("text") or "").strip()
    if not text:
        return True
    payload = dict(dialog.get("payload") or {})
    origin_message_id = _dialog_origin(dialog)
    step = str(dialog.get("step") or "")

    def show(screen: Screen) -> None:
        _render_screen(app, chat_id, screen, message_id=origin_message_id)

    try:
        if step == "announcement-title":
            if len(text) < 3:
                show(Screen(frame("✍️ اطلاعیه جدید", "عنوان کمی کوتاه است؛ یک عنوان روشن بفرست.", "حداقل ۳ نویسه."), _compose_prompt("announcement").keyboard))
                return True
            payload["title"] = text[:160]
            app.state.update_dialog(user_id, step="announcement-description", payload=payload)
            show(_compose_prompt("announcement-description"))
            return True

        if step == "announcement-description":
            description = "" if text == "-" else text
            title = str(payload.get("title") or "").strip()
            app.state.clear_dialog(user_id)
            if not title:
                show(_quick_announcement_help())
                return True
            response = app.site_api.request(
                "classopsOwnerPreview",
                user_id,
                request=_build_announcement_request(title, description),
            )
            show(_preview_screen(app, response))
            return True

        if step == "ai":
            app.state.clear_dialog(user_id)
            response = app.site_api.request(
                "classopsOwnerAiDraft",
                user_id,
                ownerText=text[:6000],
                cohortKey="dentistry-1402",
            )
            show(_ai_draft_screen(app, response))
            return True

        app.state.clear_dialog(user_id)
        show(_owner_screen(app, _owner_capabilities(app, user_id) or {}))
        return True
    except SiteApiError:
        app.state.clear_dialog(user_id)
        show(
            Screen(
                frame("⚠️ امور کلاس", "این درخواست فعلاً کامل نشد.", "چند لحظه بعد دوباره امتحان کن."),
                keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
            )
        )
        return True


def _handle_product(app: DentBotApp, update: dict[str, Any]) -> bool:
    callback, chat, chat_id, user_id, text, data = _parse_context(update)
    data = _legacy_action(data)
    is_command = _is_product_command(text)
    is_callback = _is_product_callback(data)
    if not is_command and not is_callback:
        return False
    if chat_id == 0 or user_id == 0:
        return True
    if str(chat.get("type") or "private") != "private":
        app.api.send(chat_id, "امور کلاس فقط در گفت‌وگوی خصوصی ربات در دسترس است.", {"inline_keyboard": []})
        return True

    callback_id = str(callback.get("id") or "")
    if callback_id:
        try:
            app.api.answer_callback(callback_id)
        except Exception:
            pass

    def show(screen: Screen) -> None:
        _render_screen(app, chat_id, screen, callback=callback if callback else None)

    try:
        blocked = _access_screen(app, user_id)
        if blocked is not None:
            show(blocked)
            return True

        if data.startswith("cxo_"):
            app.site_api.request("classopsResolveAction", user_id, token=data)
            show(
                Screen(
                    frame("✅ انجام شد", "وضعیت این مورد به‌روزرسانی شد.", "تغییر ثبت شد."),
                    keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
                )
            )
            return True

        lower = text.lower()
        if is_command and lower.startswith("/classops draft "):
            body = text[len("/classops draft "):].strip()
            title, separator, description = body.partition("|")
            title = title.strip()
            description = description.strip() if separator else ""
            if not title:
                show(_quick_announcement_help())
                return True
            if _owner_capabilities(app, user_id) is None:
                show(Screen(frame("امور کلاس", "این گزینه فقط برای مدیریت کلاس در دسترس است."), keyboard([button("↩️ امور کلاس", action="class-operations")])))
                return True
            show(_preview_screen(app, app.site_api.request("classopsOwnerPreview", user_id, request=_build_announcement_request(title, description))))
            return True

        if is_command and lower.startswith("/classops ai "):
            owner_text = text[len("/classops ai "):].strip()
            if not owner_text:
                show(_ai_help())
                return True
            if _owner_capabilities(app, user_id) is None:
                show(Screen(frame("امور کلاس", "این گزینه فقط برای مدیریت کلاس در دسترس است."), keyboard([button("↩️ امور کلاس", action="class-operations")])))
                return True
            show(_ai_draft_screen(app, app.site_api.request("classopsOwnerAiDraft", user_id, ownerText=owner_text, cohortKey="dentistry-1402")))
            return True

        action = data or "class-operations"
        if is_command and not data:
            action = "class-operations"

        if action == "class-operations":
            capabilities = app.site_api.request("classopsCapabilities", user_id)
            listing = app.site_api.request("classopsList", user_id, limit=30)
            items = [
                item for item in dict(listing.get("data") or {}).get("items", [])
                if isinstance(item, dict)
            ]
            show(_class_home_screen(app, role=str(capabilities.get("role") or "student"), items=items))
            return True

        if action.startswith("class-operations:list:"):
            filter_name = action.rsplit(":", 1)[-1]
            listing = app.site_api.request("classopsList", user_id, limit=50)
            items = [
                item for item in dict(listing.get("data") or {}).get("items", [])
                if isinstance(item, dict)
            ]
            show(_list_screen(items, filter_name))
            return True

        if action.startswith("class-operations:item:cop_"):
            item_id = action[len("class-operations:item:"):]
            response = app.site_api.request("classopsGet", user_id, id=item_id)
            show(_detail_screen(dict(response.get("item") or {}), dict(response.get("actions") or {})))
            return True

        if action == "class-operations:tomorrow":
            show(_digest_screen(app.site_api.request("classopsTomorrowSummary", user_id), title="🌤 فردا"))
            return True

        if action == "class-operations:weekly":
            show(_digest_screen(app.site_api.request("classopsWeeklyDigest", user_id), title="🗓 هفته پیش رو"))
            return True

        if action == "class-operations:owner":
            app.state.clear_dialog(user_id)
            capabilities = _owner_capabilities(app, user_id)
            if capabilities is None:
                show(
                    Screen(
                        frame("امور کلاس", "این بخش فقط برای مدیریت کلاس در دسترس است."),
                        keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
                    )
                )
                return True
            show(_owner_screen(app, capabilities))
            return True

        if action == "class-operations:compose:cancel":
            app.state.clear_dialog(user_id)
            show(_owner_screen(app, _owner_capabilities(app, user_id) or {}))
            return True

        if action == "class-operations:compose:announcement":
            if _owner_capabilities(app, user_id) is None:
                show(Screen(frame("امور کلاس", "این گزینه فقط برای مدیریت کلاس در دسترس است."), keyboard([button("↩️ امور کلاس", action="class-operations")])))
                return True
            app.state.start_dialog(
                user_id,
                _DIALOG_KIND,
                "announcement-title",
                {"originMessageId": _message_id(callback)},
            )
            show(_compose_prompt("announcement"))
            return True

        if action == "class-operations:compose:ai":
            capabilities = _owner_capabilities(app, user_id)
            if capabilities is None:
                show(Screen(frame("امور کلاس", "این گزینه فقط برای مدیریت کلاس در دسترس است."), keyboard([button("↩️ امور کلاس", action="class-operations")])))
                return True
            ai_state = str(dict(capabilities.get("ai") or {}).get("state") or "unconfigured")
            if ai_state != "configured":
                show(
                    Screen(
                        frame("🤖 پیش‌نویس هوشمند", "هوش مصنوعی فعلاً فعال نیست.", "ثبت دستی همچنان در دسترس است."),
                        keyboard([button("✍️ ثبت اطلاعیه", action="class-operations:compose:announcement", style="primary")], [button("↩️ مدیریت امور کلاس", action="class-operations:owner")]),
                    )
                )
                return True
            app.state.start_dialog(user_id, _DIALOG_KIND, "ai", {"originMessageId": _message_id(callback)})
            show(_compose_prompt("ai"))
            return True

        if action == "class-operations:new-announcement":
            show(_quick_announcement_help())
            return True
        if action == "class-operations:ai":
            show(_ai_help())
            return True

        show(
            Screen(
                frame("امور کلاس", "این گزینه دیگر فعال نیست.", "از منوی امور کلاس مسیر جدید را انتخاب کن."),
                keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
            )
        )
        return True
    except SiteApiError:
        show(
            Screen(
                frame("⚠️ امور کلاس", "این بخش فعلاً در دسترس نیست.", "چند لحظه بعد دوباره امتحان کن."),
                keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
            )
        )
        return True


def install_class_operations_product() -> None:
    """Integrate Class Operations through DentBot's canonical interaction pipeline."""
    global _INSTALLED
    if _INSTALLED:
        return

    # Keep the ClassOps background companion, but restore the canonical DentBot
    # handle pipeline. The legacy runtime installer used to intercept handle()
    # before membership/auth/serialization; product routing now lives below those
    # gates in _callback/_message instead.
    canonical_handle = DentBotApp.handle
    install_classops_runtime()
    DentBotApp.handle = canonical_handle  # type: ignore[method-assign]

    from . import app as app_module
    original_home = app_module.home

    def home_wrapper(*args: Any, **kwargs: Any) -> Screen:
        return _home_keyboard(original_home(*args, **kwargs))

    app_module.home = home_wrapper  # type: ignore[assignment]

    original_callback: Callable[..., None] = DentBotApp._callback
    original_message: Callable[..., None] = DentBotApp._message

    def callback_wrapper(
        self: DentBotApp,
        callback: dict[str, Any],
        *,
        interaction_version: int | None = None,
    ) -> None:
        if _is_product_callback(callback.get("data")):
            _handle_product(self, {"callback_query": callback})
            return
        original_callback(self, callback, interaction_version=interaction_version)

    def message_wrapper(self: DentBotApp, message: dict[str, Any]) -> None:
        sender = dict(message.get("from") or {})
        user_id = sender.get("id")
        if isinstance(user_id, int):
            dialog = self.state.dialog(user_id)
            if dialog is not None and str(dialog.get("kind") or "") == _DIALOG_KIND:
                if _handle_dialog_message(self, message, dialog):
                    return
        if _is_product_command(message.get("text")):
            if _handle_product(self, {"message": message}):
                return
        original_message(self, message)

    DentBotApp._callback = callback_wrapper  # type: ignore[method-assign]
    DentBotApp._message = message_wrapper  # type: ignore[method-assign]
    _INSTALLED = True
