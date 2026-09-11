from __future__ import annotations

import html
import re
from copy import deepcopy
from datetime import datetime, time, timezone
from typing import Any, Callable
from zoneinfo import ZoneInfo

from . import app as app_module
from . import class_operations as classops
from .app import DentBotApp
from .persian_datetime import (
    PERSIAN_WEEKDAYS,
    format_jalali_datetime,
    gregorian_to_jalali,
    jalali_to_gregorian,
    to_persian_digits,
)
from .site_api import SiteApiError
from .ui import Screen, button, frame, keyboard

_INSTALLED = False
_DIALOG_KIND = "classops-ux-v2"
_TEHRAN = ZoneInfo("Asia/Tehran")
_PAGE_SIZE = 7

CANONICAL_HOME_ROWS: tuple[tuple[tuple[str, str], ...], ...] = (
    (("🧭 مرکز نوید", "navid-center"),),
    (("📚 جزوات", "notes"), ("💳 اشتراک جزوات", "term-subscription:7")),
    (("📊 نمرات", "grades"), ("🗂 امور کلاس", "class-operations")),
    (("👤 حساب من", "account"), ("🔔 اعلان‌ها", "notifications"), ("❓ راهنما", "help")),
)

STATUS_MARKERS = {
    "ready": ("🟢", "سالم"),
    "healthy": ("🟢", "سالم"),
    "active": ("🟢", "فعال"),
    "delivered": ("🟢", "تحویل‌شده"),
    "degraded": ("🟡", "نیازمند توجه"),
    "warning": ("🟡", "نیازمند توجه"),
    "planned": ("🟡", "برنامه‌ریزی‌شده"),
    "scheduled": ("🟡", "زمان‌بندی‌شده"),
    "leased": ("🟡", "در حال ارسال"),
    "retry": ("🟡", "در انتظار تلاش مجدد"),
    "pending": ("🟡", "در انتظار"),
    "failed": ("🔴", "خطا"),
    "unavailable": ("🔴", "در دسترس نیست"),
    "error": ("🔴", "خطا"),
    "unknown": ("⚪️", "بررسی‌نشده"),
    "not_checked": ("⚪️", "بررسی‌نشده"),
    "superseded": ("⚪️", "جایگزین‌شده"),
    "cancelled": ("⚪️", "لغوشده"),
    "canceled": ("⚪️", "لغوشده"),
}

_CREATE_TYPES = {
    "exam": ("📝", "امتحان"),
    "event": ("📌", "رویداد"),
    "task": ("✅", "کار"),
    "deadline": ("⏳", "ددلاین"),
    "requirement": ("📎", "الزام"),
}

_LIST_TYPES = {
    "exams": {"exam"},
    "events": {"event", "class_change"},
    "tasks": {"task", "deadline", "requirement"},
}

_PERSIAN_TO_LATIN = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def _owner(app: DentBotApp, user_id: int) -> bool:
    return user_id == int(getattr(app, "owner_id", -1))


def status_marker(state: object) -> tuple[str, str]:
    return STATUS_MARKERS.get(str(state or "unknown").strip().lower(), STATUS_MARKERS["unknown"])


def canonical_home_screen(*, is_owner: bool) -> Screen:
    rows = [
        [button(label, action=action) for label, action in row]
        for row in CANONICAL_HOME_ROWS
    ]
    if is_owner:
        rows.append([button("🛠 مدیریت ربات", action="admin", style="primary")])
    return Screen(
        "<b>دنت‌یار | ورودی ۱۴۰۲</b>\n\n"
        "سرویس موردنظرت را از منوی زیر انتخاب کن.\n"
        "اطلاعات شخصی فقط از حساب متصل و منبع رسمی نمایش داده می‌شود.",
        keyboard(*rows),
    )


def owner_management_screen() -> Screen:
    return Screen(
        "<b>🛠 مدیریت ربات</b>\n\n"
        "مدیریت سرویس‌های ربات، عملیات کلاس و مسیرهای داخلی ربات.",
        keyboard(
            [button("🖥 وضعیت سرویس‌ها", action="system-status"), button("🗂 مدیریت امور کلاس", action="classops-v2:owner")],
            [button("🧭 مرکز نوید", action="navid"), button("💳 پرداخت‌ها", action="admin-payments")],
            [button("📊 مدیریت نمرات", action="admin-grades"), button("✏️ درخواست‌های مشخصات", action="profile-edit-requests")],
            [button("🔗 اتصال حساب‌ها", action="identity-mappings")],
            [button("↩️ بازگشت", action="home")],
        ),
    )


def service_status_screen(payload: dict[str, Any] | None, *, api_failed: bool = False) -> Screen:
    services = [item for item in dict(payload or {}).get("services", []) if isinstance(item, dict)]
    if api_failed:
        services = [
            {"label": "اتصال API سایت", "state": "unavailable"},
            {"label": "امور کلاس", "state": "unknown"},
            {"label": "اعلان‌ها", "state": "unknown"},
            {"label": "تلگرام", "state": "unknown"},
            {"label": "بله", "state": "unknown"},
        ]
    lines = ["<b>🖥 وضعیت سرویس‌ها</b>", ""]
    if not services:
        services = [{"label": "وضعیت سرویس‌ها", "state": "unknown"}]
    for item in services:
        marker, label = status_marker(item.get("state"))
        name = html.escape(str(item.get("label") or "سرویس"))
        lines.append(f"{marker} <b>{name}</b> · {label}")
    lines.extend(("", "وضعیت‌ها فقط بر اساس بررسی واقعی همین درخواست نمایش داده می‌شوند."))
    return Screen(
        "\n".join(lines),
        keyboard(
            [button("↻ تازه‌سازی", action="system-status", style="primary")],
            [button("↩️ مدیریت ربات", action="admin"), button("🏠 خانه", action="home")],
        ),
    )


def _assignment_summary(assignment_payload: dict[str, Any] | None) -> str:
    payload = dict(assignment_payload or {})
    if not payload.get("eligible"):
        return "گروه‌بندی ترم ۷ برای این حساب ثبت نشده است."
    assignment = dict(payload.get("assignment") or {})
    parts: list[str] = []
    for field, label in (("group10", "صبح"), ("group8", "عصر")):
        value = assignment.get(field)
        status = str(assignment.get(field + "StatusLabel") or "").strip()
        if isinstance(value, int):
            text = f"{label}: گروه {to_persian_digits(value)}"
            if status:
                text += f" ({status})"
            parts.append(text)
    return " · ".join(parts) if parts else "گروه‌بندی ترم ۷ هنوز کامل نشده است."


def classops_home_screen(items: list[dict[str, Any]], assignment_payload: dict[str, Any] | None = None) -> Screen:
    active = [item for item in items if str(item.get("status") or "") not in {"completed", "cancelled", "canceled", "archived"}]
    exam_count = sum(1 for item in active if str(item.get("type") or "") == "exam")
    task_count = sum(1 for item in active if str(item.get("type") or "") in {"task", "deadline", "requirement"})
    summary = _assignment_summary(assignment_payload)
    return Screen(
        "<b>🗂 امور کلاس</b>\n\n"
        f"{html.escape(summary)}\n"
        f"موارد باز: <b>{to_persian_digits(len(active))}</b> · "
        f"امتحان: <b>{to_persian_digits(exam_count)}</b> · "
        f"کار/ددلاین: <b>{to_persian_digits(task_count)}</b>",
        keyboard(
            [button("📅 ماه پیش رو", action="classops-v2:month:0"), button("📝 امتحان‌ها", action="classops-v2:list:exams")],
            [button("📌 رویدادها", action="classops-v2:list:events"), button("✅ کارها و ددلاین‌ها", action="classops-v2:list:tasks")],
            [button("👥 گروه‌بندی من", action="classops-v2:grouping"), button("🔔 اعلان‌های امور کلاس", action="classops-v2:student-notifications")],
            [button("↩️ بازگشت", action="home")],
        ),
    )


def owner_classops_screen() -> Screen:
    return Screen(
        "<b>🗂 مدیریت امور کلاس</b>\n\n"
        "ثبت و مدیریت آیتم‌ها از همان منبع اصلی امور کلاس انجام می‌شود؛ ثبت نهایی همیشه تأیید صریح می‌خواهد.",
        keyboard(
            [button("➕ امتحان", action="classops-v2:add:exam"), button("➕ رویداد", action="classops-v2:add:event")],
            [button("➕ کار", action="classops-v2:add:task"), button("➕ ددلاین", action="classops-v2:add:deadline")],
            [button("➕ الزام", action="classops-v2:add:requirement")],
            [button("📅 آیتم‌های آینده", action="classops-v2:future:0"), button("🔔 وضعیت اعلان‌ها", action="classops-v2:notification-status")],
            [button("👥 مدیریت گروه‌بندی", action="t7:home"), button("📊 خلاصه فردا", action="class-operations:tomorrow")],
            [button("↩️ مدیریت ربات", action="admin"), button("🏠 خانه", action="home")],
        ),
    )


def _parse_iso(value: object) -> datetime | None:
    raw = str(value or "").strip()
    if not raw:
        return None
    try:
        parsed = datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError:
        return None
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=timezone.utc)
    return parsed


def timeline_records_sorted(records: list[dict[str, Any]]) -> list[dict[str, Any]]:
    def key(item: dict[str, Any]) -> tuple[float, str]:
        parsed = _parse_iso(item.get("sortAt"))
        timestamp = parsed.timestamp() if parsed is not None else float("inf")
        return timestamp, str(item.get("title") or "")
    return sorted((dict(item) for item in records if isinstance(item, dict)), key=key)


def _local_date_label(item: dict[str, Any]) -> str:
    timing = dict(item.get("timing") or {})
    local_date = str(timing.get("localDate") or "").strip()
    if local_date:
        try:
            parsed = datetime.fromisoformat(local_date).date()
            jy, jm, jd = gregorian_to_jalali(parsed)
            return to_persian_digits(f"{PERSIAN_WEEKDAYS[parsed.weekday()]} {jy}/{jm:02d}/{jd:02d}")
        except ValueError:
            pass
    rendered = format_jalali_datetime(item.get("sortAt"))
    return rendered or "زمان نامشخص"


def _timeline_bucket(item: dict[str, Any], now: datetime) -> str:
    if bool(item.get("overdue")):
        return "⛔ عقب‌افتاده"
    parsed = _parse_iso(item.get("sortAt"))
    if parsed is None:
        return "⚪️ بدون زمان"
    local = parsed.astimezone(_TEHRAN).date()
    delta = (local - now.astimezone(_TEHRAN).date()).days
    if delta == 0:
        return "🔵 امروز"
    if 0 < delta <= 7:
        return "📅 این هفته"
    if 7 < delta <= 14:
        return "📆 هفته بعد"
    return "🗓 ادامه ۳۰ روز"


def timeline_screen(records: list[dict[str, Any]], page: int = 0, *, owner: bool = False) -> Screen:
    ordered = timeline_records_sorted(records)
    max_page = max(0, (len(ordered) - 1) // _PAGE_SIZE)
    page = max(0, min(int(page), max_page))
    visible = ordered[page * _PAGE_SIZE:(page + 1) * _PAGE_SIZE]
    now = datetime.now(_TEHRAN)
    lines = ["<b>📅 ماه پیش رو</b>", ""]
    last_bucket = ""
    rows: list[list[dict]] = []
    for item in visible:
        bucket = _timeline_bucket(item, now)
        if bucket != last_bucket:
            lines.extend(("", f"<b>{bucket}</b>"))
            last_bucket = bucket
        marker = "🔴" if item.get("overdue") else ("🧩" if item.get("source") == "term7" else "•")
        title = html.escape(str(item.get("title") or "مورد کلاس")[:120])
        when = html.escape(_local_date_label(item))
        lines.append(f"{marker} <b>{title}</b>\n  {when}")
        description = " ".join(str(item.get("description") or "").split())
        if item.get("source") == "term7" and description:
            lines.append("  " + html.escape(description[:180]))
        item_id = str(item.get("id") or "")
        if item_id.startswith("cop_"):
            rows.append([button(str(item.get("title") or "جزئیات")[:28], action=f"class-operations:item:{item_id}")])
    if not visible:
        lines.append("در ۳۰ روز آینده موردی در منبع اصلی پیدا نشد.")
    nav: list[dict] = []
    if page > 0:
        nav.append(button("‹ قبلی", action=f"classops-v2:month:{page - 1}"))
    if page < max_page:
        nav.append(button("بعدی ›", action=f"classops-v2:month:{page + 1}"))
    if nav:
        rows.append(nav)
    back = "classops-v2:owner" if owner else "class-operations"
    rows.append([button("↩️ امور کلاس", action=back), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def filtered_items_screen(items: list[dict[str, Any]], mode: str) -> Screen:
    allowed = _LIST_TYPES.get(mode, set())
    filtered = [item for item in items if str(item.get("type") or "") in allowed]
    titles = {"exams": "📝 امتحان‌ها", "events": "📌 رویدادها", "tasks": "✅ کارها و ددلاین‌ها"}
    lines = [f"<b>{titles.get(mode, '🗂 موارد')}</b>", ""]
    rows: list[list[dict]] = []
    for item in filtered[:12]:
        label = html.escape(str(item.get("title") or "مورد")[:140])
        when = format_jalali_datetime(dict(item.get("timing") or {}).get("startsAt") or dict(item.get("timing") or {}).get("dueAt"))
        lines.append(f"• <b>{label}</b>" + (f"\n  {html.escape(when)}" if when else ""))
        item_id = str(item.get("id") or "")
        if item_id.startswith("cop_"):
            rows.append([button(str(item.get("title") or "جزئیات")[:28], action=f"class-operations:item:{item_id}")])
    if not filtered:
        lines.append("موردی در این بخش ثبت نشده است.")
    rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def grouping_screen(payload: dict[str, Any]) -> Screen:
    if not payload.get("eligible"):
        return Screen(
            "<b>👥 گروه‌بندی من</b>\n\nگروه‌بندی ترم ۷ برای این حساب ثبت نشده یا این حساب مشمول آن نیست.",
            keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
        )
    assignment = dict(payload.get("assignment") or {})
    lines = ["<b>👥 گروه‌بندی من</b>", ""]
    for field, heading, shift in (("group10", "گروه صبح", "صبح"), ("group8", "گروه عصر", "عصر")):
        group = assignment.get(field)
        status = str(assignment.get(field + "StatusLabel") or "عضو")
        lines.append(f"<b>{heading}</b>")
        if isinstance(group, int):
            lines.append(f"• گروه {to_persian_digits(group)} · نوبت {shift}\n• نقش: {html.escape(status)}")
        else:
            lines.append("• هنوز گروهی ثبت نشده است.")
        lines.append("")
    lines.append("برنامه دقیق روزها از «ماه پیش رو» و منبع رسمی ترم ۷ نمایش داده می‌شود.")
    return Screen(
        "\n".join(lines),
        keyboard([button("📅 ماه پیش رو", action="classops-v2:month:0")], [button("↩️ امور کلاس", action="class-operations")]),
    )


def student_notifications_screen(response: dict[str, Any]) -> Screen:
    data = dict(response.get("data") or {})
    raw = [item for item in data.get("items", []) if isinstance(item, dict)]
    items: list[dict[str, Any]] = []
    for item in raw:
        meta = dict(item.get("meta") or {})
        source = str(item.get("source") or meta.get("source") or "")
        source_key = str(item.get("sourceKey") or meta.get("sourceKey") or "")
        if source == "classops" or source_key.startswith("classops:"):
            items.append(item)
    lines = ["<b>🔔 اعلان‌های امور کلاس</b>", ""]
    for item in items[:10]:
        title = html.escape(str(item.get("title") or "اعلان امور کلاس")[:140])
        state = "خوانده‌شده" if item.get("read") or item.get("isRead") else "جدید"
        effective = format_jalali_datetime(item.get("publishAt") or item.get("effectiveAt"))
        lines.append(f"• <b>{title}</b> · {state}" + (f"\n  {html.escape(effective)}" if effective else ""))
    if not items:
        lines.append("اعلان فعالی از منبع امور کلاس برای این حساب دیده نمی‌شود.")
    return Screen("\n".join(lines), keyboard([button("↩️ امور کلاس", action="class-operations")], [button("🏠 خانه", action="home")]))


def notification_status_screen(payload: dict[str, Any]) -> Screen:
    notification_counts = dict(payload.get("notificationCounts") or {})
    delivery_counts = dict(payload.get("deliveryCounts") or {})
    platform_counts = dict(payload.get("platformCounts") or {})
    lines = ["<b>🔔 وضعیت اعلان‌های امور کلاس</b>", ""]
    lines.append("<b>اعلان‌ها</b>")
    for state in ("scheduled", "active", "delivered", "failed"):
        count = int(notification_counts.get(state) or 0)
        if count:
            marker, label = status_marker(state)
            lines.append(f"{marker} {label}: <b>{to_persian_digits(count)}</b>")
    if not any(int(value or 0) for value in notification_counts.values()):
        lines.append("⚪️ موردی ثبت نشده است.")
    lines.extend(("", "<b>سوابق ارسال</b>"))
    for state in ("planned", "leased", "retry", "delivered", "failed", "superseded", "cancelled"):
        count = int(delivery_counts.get(state) or 0)
        if count:
            marker, label = status_marker(state)
            lines.append(f"{marker} {label}: <b>{to_persian_digits(count)}</b>")
    for platform, label in (("telegram", "تلگرام"), ("bale", "بله")):
        counts = dict(platform_counts.get(platform) or {})
        total = sum(int(value or 0) for value in counts.values())
        lines.append(f"• {label}: {to_persian_digits(total)} intent")
    lines.extend(("", "این صفحه فقط وضعیت‌های ثبت‌شده در زیرسامانه‌های اصلی را نشان می‌دهد."))
    return Screen(
        "\n".join(lines),
        keyboard([button("↻ تازه‌سازی", action="classops-v2:notification-status")], [button("↩️ مدیریت امور کلاس", action="classops-v2:owner")]),
    )


def navid_center_screen(response: dict[str, Any]) -> Screen:
    view = dict(response.get("view") or {})
    connector = next((item for item in view.get("connectors", []) if isinstance(item, dict) and item.get("connector") == "navid"), None)
    lines = ["<b>🧭 مرکز نوید</b>", ""]
    rows: list[list[dict]] = []
    if connector is None:
        lines.append("وضعیت اتصال نوید در این لحظه قابل تشخیص نیست.")
    else:
        state = str(connector.get("status") or "unknown")
        mapped = {"ready": "ready", "unavailable": "unavailable", "not-configured": "unknown"}.get(state, "unknown")
        marker, label = status_marker(mapped)
        lines.append(f"{marker} وضعیت اتصال: <b>{html.escape(str(connector.get('statusLabel') or label))}</b>")
        masked = str(connector.get("maskedAccountLabel") or "").strip()
        if masked:
            lines.append(f"حساب: <code>{html.escape(masked)}</code>")
        for action in view.get("actions", []):
            if not isinstance(action, dict) or "نوید" not in str(action.get("label") or ""):
                continue
            ref = str(action.get("ref") or "")
            if re.fullmatch(r"[A-Za-z0-9_-]{12,20}", ref):
                rows.append([button("ادامه در نوید", action=f"assistant-action:{ref}", style="primary")])
    rows.append([button("↩️ بازگشت", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def _latin_digits(value: str) -> str:
    return value.translate(_PERSIAN_TO_LATIN)


def parse_jalali_datetime(value: str) -> str:
    clean = _latin_digits(value.strip())
    match = re.fullmatch(r"(1[34]\d{2})[/.-](\d{1,2})[/.-](\d{1,2})\s+(\d{1,2}):(\d{2})", clean)
    if not match:
        raise ValueError("invalid jalali datetime")
    jy, jm, jd, hour, minute = (int(part) for part in match.groups())
    if hour > 23 or minute > 59:
        raise ValueError("invalid time")
    gregorian = jalali_to_gregorian(jy, jm, jd)
    local = datetime.combine(gregorian, time(hour, minute), tzinfo=_TEHRAN)
    return local.isoformat(timespec="seconds")


def _audience_spec() -> dict[str, Any]:
    return {
        "version": "classops-audience-v1",
        "resolutionMode": "snapshot",
        "expression": {"op": "whole_cohort"},
        "includeStudentNumbers": [],
        "excludeStudentNumbers": [],
    }


def _timing_for(item_type: str, iso_value: str) -> dict[str, Any]:
    if item_type in {"task", "deadline", "requirement"}:
        return {"dueAt": iso_value}
    return {"startsAt": iso_value}


def build_create_request(item_type: str, title: str, description: str, iso_value: str) -> dict[str, Any]:
    if item_type not in _CREATE_TYPES:
        raise ValueError("unsupported item type")
    return {
        "item": {
            "cohortKey": "dentistry-1402",
            "type": item_type,
            "title": title[:160],
            "description": description[:4000],
            "timing": _timing_for(item_type, iso_value),
            "importance": "important" if item_type in {"exam", "deadline"} else "normal",
            "requireAck": False,
            "status": "draft",
        },
        "audienceSpec": _audience_spec(),
        "destinations": ["private_users"],
    }


def _composer_prompt(item_type: str, step: str, *, editing: bool = False) -> Screen:
    icon, label = _CREATE_TYPES.get(item_type, ("🗂", "آیتم"))
    prefix = "ویرایش" if editing else "افزودن"
    if step.endswith("title"):
        text = f"<b>{icon} {prefix} {label}</b>\n\nعنوان را بفرست." + ("\nبرای نگه‌داشتن عنوان فعلی «-» بفرست." if editing else "")
    elif step.endswith("description"):
        text = f"<b>{icon} {prefix} {label}</b>\n\nتوضیح را بفرست؛ برای خالی‌گذاشتن یا نگه‌داشتن مقدار فعلی «-» بفرست."
    else:
        text = f"<b>{icon} {prefix} {label}</b>\n\nتاریخ و ساعت شمسی را مثل <code>۱۴۰۵/۰۶/۲۰ ۱۰:۳۰</code> بفرست." + ("\nبرای نگه‌داشتن زمان فعلی «-» بفرست." if editing else "")
    return Screen(text, keyboard([button("انصراف", action="classops-v2:compose-cancel")]))


def _confirm_lifecycle_screen(token: str, verb: str, item_id: str = "") -> Screen:
    label = "لغو" if verb == "cancel" else "بایگانی"
    rows = [[button(f"تأیید {label}", action=token, style="danger")]]
    if item_id.startswith("cop_"):
        rows.append([button("↩️ برگشت به جزئیات", action=f"class-operations:item:{item_id}")])
    rows.append([button("🏠 خانه", action="home")])
    return Screen(
        f"<b>⚠️ تأیید {label}</b>\n\nاین تغییر روی آیتم اصلی اعمال می‌شود. برای ادامه، تأیید نهایی را بزن.",
        keyboard(*rows),
    )


def _decorate_owner_detail(screen: Screen, item: dict[str, Any], actions: dict[str, Any]) -> Screen:
    if not any(str(actions.get(key) or "").startswith("cxo_") for key in ("cancel", "archive")):
        return screen
    rows: list[list[dict]] = []
    for row in dict(screen.keyboard or {}).get("inline_keyboard", []):
        new_row: list[dict] = []
        for entry in row:
            current = dict(entry)
            data = str(current.get("callback_data") or "")
            raw = data[3:] if data.startswith("v1:") else data
            if raw == str(actions.get("cancel") or ""):
                current["callback_data"] = "v1:classops-v2:confirm-cancel:" + raw
            elif raw == str(actions.get("archive") or ""):
                current["callback_data"] = "v1:classops-v2:confirm-archive:" + raw
            new_row.append(current)
        rows.append(new_row)
    item_id = str(item.get("id") or "")
    if item_id.startswith("cop_") and str(item.get("status") or "") not in {"cancelled", "archived"}:
        rows.insert(0, [button("✏️ ویرایش", action=f"classops-v2:edit:{item_id}", style="primary")])
    return Screen(screen.text, {"inline_keyboard": rows})


def _parse_context(update: dict[str, Any]) -> tuple[dict[str, Any], dict[str, Any], int, int, str]:
    callback = dict(update.get("callback_query") or {})
    message = dict(callback.get("message") or update.get("message") or {})
    sender = dict(callback.get("from") or message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        return callback, chat, 0, 0, ""
    raw = str(callback.get("data") or "")
    data = raw[3:] if raw.startswith("v1:") else raw
    return callback, chat, chat_id, user_id, data


def _show(app: DentBotApp, chat_id: int, screen: Screen, callback: dict[str, Any] | None = None, *, message_id: int = 0) -> None:
    classops._render_screen(app, chat_id, screen, callback=callback, message_id=message_id)


def _callback_with_data(update: dict[str, Any], action: str) -> dict[str, Any]:
    clone = deepcopy(update)
    callback = dict(clone.get("callback_query") or {})
    callback["data"] = "v1:" + action
    clone["callback_query"] = callback
    return clone


def _start_dialog(app: DentBotApp, user_id: int, item_type: str, origin_message_id: int, *, edit_item: dict[str, Any] | None = None) -> Screen:
    payload: dict[str, Any] = {"itemType": item_type, "originMessageId": origin_message_id}
    if edit_item is not None:
        payload["itemId"] = str(edit_item.get("id") or "")
        payload["expectedRevision"] = int(edit_item.get("revision") or 0)
        payload["currentTitle"] = str(edit_item.get("title") or "")
        payload["currentDescription"] = str(edit_item.get("description") or "")
        payload["currentTiming"] = dict(edit_item.get("timing") or {})
        app.state.start_dialog(user_id, _DIALOG_KIND, "edit-title", payload)
        return _composer_prompt(item_type, "edit-title", editing=True)
    app.state.start_dialog(user_id, _DIALOG_KIND, "create-title", payload)
    return _composer_prompt(item_type, "create-title")


def _dialog_message(app: DentBotApp, message: dict[str, Any], dialog: dict[str, Any]) -> bool:
    sender = dict(message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        user_id = int(sender.get("id") or 0)
        chat_id = int(chat.get("id") or user_id)
    except (TypeError, ValueError):
        return True
    if not _owner(app, user_id):
        app.state.clear_dialog(user_id)
        return False
    text = str(message.get("text") or "").strip()
    if not text:
        return True
    payload = dict(dialog.get("payload") or {})
    item_type = str(payload.get("itemType") or "")
    step = str(dialog.get("step") or "")
    origin = int(payload.get("originMessageId") or 0)

    def show(screen: Screen) -> None:
        _show(app, chat_id, screen, message_id=origin)

    try:
        if step in {"create-title", "edit-title"}:
            if text != "-" and len(text) < 3:
                show(_composer_prompt(item_type, step, editing=step.startswith("edit")))
                return True
            if text != "-":
                payload["title"] = text[:160]
            next_step = "edit-description" if step.startswith("edit") else "create-description"
            app.state.update_dialog(user_id, step=next_step, payload=payload)
            show(_composer_prompt(item_type, next_step, editing=next_step.startswith("edit")))
            return True

        if step in {"create-description", "edit-description"}:
            if text != "-":
                payload["description"] = text[:4000]
            next_step = "edit-time" if step.startswith("edit") else "create-time"
            app.state.update_dialog(user_id, step=next_step, payload=payload)
            show(_composer_prompt(item_type, next_step, editing=next_step.startswith("edit")))
            return True

        if step in {"create-time", "edit-time"}:
            editing = step.startswith("edit")
            iso_value = ""
            if text != "-":
                try:
                    iso_value = parse_jalali_datetime(text)
                except ValueError:
                    show(Screen(
                        "<b>⚠️ تاریخ معتبر نیست</b>\n\nمثل <code>۱۴۰۵/۰۶/۲۰ ۱۰:۳۰</code> بفرست.",
                        _composer_prompt(item_type, step, editing=editing).keyboard,
                    ))
                    return True
            app.state.clear_dialog(user_id)
            if editing:
                patch: dict[str, Any] = {}
                if "title" in payload:
                    patch["title"] = str(payload["title"])
                if "description" in payload:
                    patch["description"] = str(payload["description"])
                if iso_value:
                    patch["timing"] = _timing_for(item_type, iso_value)
                if not patch:
                    show(Screen("<b>✏️ ویرایش آیتم</b>\n\nهیچ تغییری وارد نشد.", keyboard([button("↩️ مدیریت امور کلاس", action="classops-v2:owner")])))
                    return True
                response = app.site_api.request(
                    "classopsOwnerPreview",
                    user_id,
                    mode="update",
                    id=str(payload.get("itemId") or ""),
                    expectedRevision=int(payload.get("expectedRevision") or 0),
                    request={"item": patch},
                )
            else:
                if not iso_value:
                    show(Screen("<b>⚠️ زمان لازم است</b>\n\nبرای این نوع آیتم تاریخ و ساعت را وارد کن.", keyboard([button("↩️ مدیریت امور کلاس", action="classops-v2:owner")])))
                    return True
                response = app.site_api.request(
                    "classopsOwnerPreview",
                    user_id,
                    request=build_create_request(item_type, str(payload.get("title") or ""), str(payload.get("description") or ""), iso_value),
                )
            show(classops._preview_screen(app, response))
            return True
    except SiteApiError as error:
        app.state.clear_dialog(user_id)
        message_text = "نسخه یا داده تغییر کرده؛ صفحه را تازه کن و دوباره پیش‌نمایش بگیر." if error.status == 409 else "درخواست کامل نشد؛ داده‌ای ثبت نشد."
        show(Screen(f"<b>⚠️ امور کلاس</b>\n\n{message_text}", keyboard([button("↩️ مدیریت امور کلاس", action="classops-v2:owner")])))
        return True

    app.state.clear_dialog(user_id)
    show(owner_classops_screen())
    return True


def _handle_v2_callback(app: DentBotApp, update: dict[str, Any], original: Callable[[DentBotApp, dict[str, Any]], Any]) -> bool:
    callback, chat, chat_id, user_id, data = _parse_context(update)
    if chat_id == 0 or user_id == 0:
        return False
    handled = data in {"navid-center", "system-status", "class-operations"} or data.startswith("classops-v2:")
    if not handled:
        return False
    callback_id = str(callback.get("id") or "")
    if callback_id:
        try:
            app.api.answer_callback(callback_id)
        except Exception:
            pass
    if str(chat.get("type") or "private") != "private":
        app.api.send(chat_id, "این بخش فقط در گفت‌وگوی خصوصی ربات در دسترس است.", {"inline_keyboard": []})
        return True

    def show(screen: Screen) -> None:
        _show(app, chat_id, screen, callback)

    if data == "system-status":
        if not _owner(app, user_id):
            return bool(original(app, update))
        try:
            show(service_status_screen(app.site_api.request("classopsRuntimeStatus", user_id)))
        except SiteApiError:
            show(service_status_screen(None, api_failed=True))
        return True

    if data == "navid-center":
        if _owner(app, user_id):
            return bool(original(app, _callback_with_data(update, "navid")))
        try:
            show(navid_center_screen(app.site_api.student_assistant_summary(user_id)))
        except SiteApiError:
            show(Screen("<b>🧭 مرکز نوید</b>\n\nوضعیت نوید فعلاً قابل دریافت نیست.", keyboard([button("↩️ بازگشت", action="home")])))
        return True

    blocked = getattr(app, "_private_access_gate", lambda _uid: None)(user_id)
    if blocked is not None:
        show(blocked)
        return True

    try:
        if data == "class-operations":
            capabilities = app.site_api.request("classopsCapabilities", user_id)
            listing = app.site_api.request("classopsList", user_id, limit=30)
            assignment = app.site_api.request("academicTerm7Self", user_id)
            items = [item for item in dict(listing.get("data") or {}).get("items", []) if isinstance(item, dict)]
            show(classops_home_screen(items, assignment))
            return True

        if data.startswith("classops-v2:month:"):
            page = int(data.rsplit(":", 1)[-1])
            response = app.site_api.request("classopsUpcomingMonth", user_id)
            show(timeline_screen([item for item in response.get("records", []) if isinstance(item, dict)], page, owner=False))
            return True

        if data.startswith("classops-v2:list:"):
            mode = data.rsplit(":", 1)[-1]
            listing = app.site_api.request("classopsList", user_id, limit=50)
            items = [item for item in dict(listing.get("data") or {}).get("items", []) if isinstance(item, dict)]
            show(filtered_items_screen(items, mode))
            return True

        if data == "classops-v2:grouping":
            show(grouping_screen(app.site_api.request("academicTerm7Self", user_id)))
            return True

        if data == "classops-v2:student-notifications":
            show(student_notifications_screen(app.site_api.notifications(user_id, limit=20)))
            return True

        if data == "classops-v2:owner":
            if not _owner(app, user_id):
                return bool(original(app, update))
            capabilities = app.site_api.request("classopsCapabilities", user_id)
            if str(capabilities.get("role") or "") != "owner":
                return bool(original(app, update))
            app.state.clear_dialog(user_id)
            show(owner_classops_screen())
            return True

        if data.startswith("classops-v2:add:"):
            if not _owner(app, user_id):
                return bool(original(app, update))
            item_type = data.rsplit(":", 1)[-1]
            if item_type not in _CREATE_TYPES:
                show(owner_classops_screen())
                return True
            caps = app.site_api.request("classopsCapabilities", user_id)
            if str(caps.get("role") or "") != "owner":
                return bool(original(app, update))
            origin = int(dict(callback.get("message") or {}).get("message_id") or 0)
            show(_start_dialog(app, user_id, item_type, origin))
            return True

        if data == "classops-v2:compose-cancel":
            app.state.clear_dialog(user_id)
            show(owner_classops_screen() if _owner(app, user_id) else classops_home_screen([], None))
            return True

        if data.startswith("classops-v2:edit:cop_"):
            if not _owner(app, user_id):
                return bool(original(app, update))
            item_id = data[len("classops-v2:edit:"):]
            response = app.site_api.request("classopsGet", user_id, id=item_id)
            item = dict(response.get("item") or {})
            item_type = str(item.get("type") or "")
            if item_type not in _CREATE_TYPES:
                show(Screen("<b>✏️ ویرایش</b>\n\nویرایش این نوع آیتم در رابط فعلی پشتیبانی نمی‌شود.", keyboard([button("↩️ مدیریت امور کلاس", action="classops-v2:owner")])))
                return True
            origin = int(dict(callback.get("message") or {}).get("message_id") or 0)
            show(_start_dialog(app, user_id, item_type, origin, edit_item=item))
            return True

        if data.startswith("classops-v2:confirm-cancel:cxo_") or data.startswith("classops-v2:confirm-archive:cxo_"):
            if not _owner(app, user_id):
                return bool(original(app, update))
            verb = "cancel" if ":confirm-cancel:" in data else "archive"
            token = data.rsplit(":", 1)[-1]
            show(_confirm_lifecycle_screen(token, verb))
            return True

        if data.startswith("classops-v2:future:"):
            if not _owner(app, user_id):
                return bool(original(app, update))
            page = int(data.rsplit(":", 1)[-1])
            response = app.site_api.request("classopsUpcomingMonth", user_id)
            records = [item for item in response.get("records", []) if isinstance(item, dict) and item.get("source") == "classops"]
            show(timeline_screen(records, page, owner=True))
            return True

        if data == "classops-v2:notification-status":
            if not _owner(app, user_id):
                return bool(original(app, update))
            show(notification_status_screen(app.site_api.request("classopsNotificationStatus", user_id)))
            return True
    except (SiteApiError, ValueError):
        show(Screen(
            "<b>⚠️ امور کلاس</b>\n\nاین درخواست فعلاً کامل نشد؛ داده‌ای تغییر نکرد.",
            keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
        ))
        return True
    return False


def install_bot_home_classops_ux_v2() -> None:
    global _INSTALLED
    if _INSTALLED:
        return
    _INSTALLED = True

    def home_v2(
        site_url: str,
        *,
        is_owner: bool,
        student_assistant_enabled: bool = False,
        has_products: bool = False,
    ) -> Screen:
        del site_url, student_assistant_enabled, has_products
        return canonical_home_screen(is_owner=is_owner)

    original_section = app_module.section

    def section_v2(name: str, site_url: str, *, is_owner: bool) -> Screen:
        if name == "admin" and is_owner:
            return owner_management_screen()
        return original_section(name, site_url, is_owner=is_owner)

    app_module.home = home_v2
    app_module.section = section_v2

    original_detail = classops._detail_screen

    def detail_v2(item: dict[str, Any], actions: dict[str, Any]) -> Screen:
        return _decorate_owner_detail(original_detail(item, actions), item, actions)

    classops._detail_screen = detail_v2

    original_callback = DentBotApp._callback

    def callback_v2(self: DentBotApp, update: dict[str, Any]) -> Any:
        if _handle_v2_callback(self, update, original_callback):
            return None
        return original_callback(self, update)

    DentBotApp._callback = callback_v2

    original_message = DentBotApp._message

    def message_v2(self: DentBotApp, message: dict[str, Any]) -> Any:
        sender = dict(message.get("from") or {})
        try:
            user_id = int(sender.get("id") or 0)
        except (TypeError, ValueError):
            user_id = 0
        dialog = self.state.get_dialog(user_id) if user_id else None
        if dialog is not None and str(dialog.get("kind") or "") == _DIALOG_KIND:
            if _dialog_message(self, message, dialog):
                return None
        return original_message(self, message)

    DentBotApp._message = message_v2
