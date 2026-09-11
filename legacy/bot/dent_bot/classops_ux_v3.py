from __future__ import annotations

import html
from copy import deepcopy
from datetime import date, datetime, timedelta
from typing import Any, Callable
from zoneinfo import ZoneInfo

from . import class_operations as classops
from .app import DentBotApp
from .persian_datetime import PERSIAN_WEEKDAYS, format_jalali_datetime, gregorian_to_jalali, to_persian_digits
from .site_api import SiteApiError
from .ui import Screen, button, keyboard, native_rich_text

_TEHRAN = ZoneInfo("Asia/Tehran")
_MONTH_PAGE_SIZE = 7
_DAILY_PAGE_SIZE = 8
_WEEKLY_DAY_PREVIEW = 3
_MONTH_DAY_PREVIEW = 4
_INSTALLED = False

ITEM_META: dict[str, tuple[str, str]] = {
    "exam": ("📝", "امتحان"), "event": ("📌", "رویداد"), "class_change": ("⚠️", "تغییر مهم"),
    "task": ("✅", "کار"), "deadline": ("⏰", "ددلاین"), "requirement": ("📎", "الزام"),
    "practical": ("🦷", "کارآموزی"), "theory": ("📚", "جلسه آموزشی"),
    "critical_notice": ("⚠️", "اطلاعیه مهم"), "announcement": ("🔔", "اطلاعیه"),
    "service_reminder": ("🔔", "یادآوری"),
}
FILTER_TYPES = {"exams": {"exam"}, "events": {"event", "class_change", "announcement", "critical_notice"}, "tasks": {"task", "deadline", "requirement"}}
FILTER_TITLES = {"exams": "📝 امتحان‌ها", "events": "📌 رویدادها و تغییرات", "tasks": "✅ کارها و ددلاین‌ها"}

STATUS_META = {
    "completed": ("✅", "انجام‌شده"), "cancelled": ("❌", "لغوشده"), "canceled": ("❌", "لغوشده"),
    "scheduled": ("🟡", "زمان‌بندی‌شده"), "active": ("🟢", "فعال"), "pending": ("🟡", "در انتظار"),
    "submitted": ("🟢", "ارسال‌شده"), "failed": ("🔴", "ناموفق"), "retry": ("🟡", "تلاش مجدد"),
    "leased": ("🟡", "در حال ارسال"), "delivered": ("🟢", "تحویل‌شده"), "superseded": ("⚪️", "جایگزین‌شده"),
    "unknown": ("⚪️", "نامشخص"), "planned": ("🟡", "برنامه‌ریزی‌شده"),
}


def _plain(value: object, limit: int = 180) -> str:
    raw = " ".join(str(value or "").replace("\x00", "").split())
    if len(raw) > limit:
        raw = raw[: max(1, limit - 1)].rstrip() + "…"
    return to_persian_digits(raw)


def _esc(value: object, limit: int = 180) -> str:
    return html.escape(_plain(value, limit))


def _meta(item: dict[str, Any]) -> tuple[str, str]:
    return ITEM_META.get(str(item.get("type") or ""), ("•", "مورد کلاس"))


def _account_academic_context_block(payload: dict[str, Any] | None) -> str:
    context = dict(payload or {})
    if not context or not context.get("eligible"):
        return "<b>🎓 وضعیت تحصیلی</b>\n<blockquote>اطلاعات امور کلاس برای این حساب فعلاً در دسترس نیست.</blockquote>"
    term = dict(context.get("academicTerm") or {})
    schedule = dict(context.get("scheduleContext") or {})
    groups = dict(context.get("groups") or {})
    morning = dict(groups.get("morning") or {})
    afternoon = dict(groups.get("afternoon") or {})
    lines = ["<b>🎓 وضعیت تحصیلی</b>"]
    term_label = str(term.get("termLabel") or "").strip()
    lines.append(f"<code>ترم</code>  <b>{html.escape(term_label or '—')}</b>")
    rotation = str(schedule.get("rotationLabel") or "").strip()
    if not rotation and term.get("state") == "active" and not schedule.get("inSchedule"):
        rotation = "هنوز شروع نشده"
    lines.append(f"<code>روتیشن</code>  <b>{html.escape(rotation or '—')}</b>")
    for label, group in (("صبح", morning), ("عصر", afternoon)):
        number = group.get("group")
        number_text = f"گروه {to_persian_digits(number)}" if isinstance(number, int) else "—"
        leader = str(group.get("leaderName") or "").strip()
        suffix = f" · سرگروه: {html.escape(leader)}" if leader else ""
        lines.append(f"<code>{label}</code>  <b>{html.escape(number_text)}</b>{suffix}")
    practical = [str(item.get("title") or "").strip() for item in schedule.get("currentPractical", []) if isinstance(item, dict)]
    if practical:
        lines.append("<code>بخش فعلی</code>  <b>" + html.escape("، ".join(value for value in practical if value)) + "</b>")
    period = dict(schedule.get("rotationPeriod") or {})
    if period.get("from") and period.get("through"):
        lines.append(f"<code>بازه</code>  {html.escape(to_persian_digits(period['from']))} تا {html.escape(to_persian_digits(period['through']))}")
    next_item = dict(schedule.get("nextPractical") or {})
    next_events = [str(item.get("title") or "").strip() for item in next_item.get("events", []) if isinstance(item, dict)]
    if next_item.get("date") and next_events:
        lines.append(f"<code>بعدی</code>  {html.escape(to_persian_digits(next_item['date']))} · {html.escape('، '.join(next_events))}")
    return "\n".join(lines)


def account_screen_with_academic_context(
    base: Screen, linked_user: dict[str, Any] | None, academic_context: dict[str, Any] | None
) -> Screen:
    if str(dict(linked_user or {}).get("cohortKey") or "") != "dentistry-1402":
        return base
    block = _account_academic_context_block(academic_context)
    text = str(base.text)
    marker = "\n\n<b>🆔 کد DIS</b>"
    if marker in text:
        text = text.replace(marker, "\n\n" + block + marker, 1)
    else:
        text += "\n\n" + block
    rows = deepcopy(dict(base.keyboard or {}).get("inline_keyboard", []))
    academic_row = [button("👥 گروه‌بندی من", action="c3:g"), button("🗂 امور کلاس", action="class-operations")]
    insert_at = 1 if rows else 0
    rows.insert(insert_at, academic_row)
    return Screen(text, {"inline_keyboard": rows})


def _destination_label(value: object) -> str:
    raw = str(value or "").strip().lower()
    labels = {
        "private.telegram": "پیام خصوصی", "private.bale": "پیام خصوصی",
        "private_users": "پیام خصوصی", "group.telegram": "گروه کلاس",
        "group.bale": "گروه کلاس", "class_group": "گروه کلاس",
        "channel.telegram": "کانال اطلاع‌رسانی", "channel.bale": "کانال اطلاع‌رسانی",
        "information_channel": "کانال اطلاع‌رسانی",
    }
    return labels.get(raw, "مسیر نامشخص")


def _status(item: dict[str, Any]) -> tuple[str, str]:
    if bool(item.get("overdue")):
        return "⏰", "از موعد گذشته"
    return STATUS_META.get(str(item.get("status") or "unknown"), STATUS_META["unknown"])


def _date_parts(local_date: object) -> tuple[date | None, str, str]:
    try:
        parsed = date.fromisoformat(str(local_date or ""))
    except ValueError:
        return None, "تاریخ نامشخص", ""
    jy, jm, jd = gregorian_to_jalali(parsed)
    return parsed, PERSIAN_WEEKDAYS[parsed.weekday()], to_persian_digits(f"{jy}/{jm:02d}/{jd:02d}")


def _relative_day(parsed: date | None) -> str:
    if parsed is None:
        return ""
    delta = (parsed - datetime.now(_TEHRAN).date()).days
    if delta == 0:
        return "امروز"
    if delta == 1:
        return "فردا"
    if delta < 0:
        return "گذشته"
    return ""


def _clock(value: object) -> str:
    raw = str(value or "").strip()
    if not raw:
        return ""
    try:
        parsed = datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError:
        return ""
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=_TEHRAN)
    return to_persian_digits(parsed.astimezone(_TEHRAN).strftime("%H:%M"))


def _item_sort_key(item: dict[str, Any]) -> tuple[str, str]:
    values = [str(item.get(key) or "").strip() for key in ("startsAt", "dueAt", "endsAt")]
    effective = next((value for value in values if value), "9999-12-31T23:59:59+00:00")
    return effective, _plain(item.get("title"), 160)


def _row_time(item: dict[str, Any]) -> str:
    start = _clock(item.get("startsAt"))
    end = _clock(item.get("endsAt"))
    due = _clock(item.get("dueAt"))
    if due:
        return due
    if start and end:
        return f"{start}–{end}"
    if start:
        return start
    label = _plain(item.get("timeLabel"), 20)
    return label or "—"


def _day_title(day: dict[str, Any]) -> tuple[str, str]:
    parsed, weekday, jalali = _date_parts(day.get("localDate"))
    relative = _relative_day(parsed)
    title = f"{weekday} {jalali}".strip()
    return title, relative


def _item_button(item: dict[str, Any], back: str) -> dict[str, Any] | None:
    ref = str(item.get("ref") or "")
    if not ref.startswith("cop_"):
        return None
    label = _plain(item.get("title") or _meta(item)[1], 30)
    return button(label, action=f"c3:i:{ref}:{back}")


def _rich_day_table(day: dict[str, Any], *, limit: int | None = None) -> str:
    items = [item for item in day.get("items", []) if isinstance(item, dict)]
    visible = items if limit is None else items[: max(0, limit)]
    if not visible:
        return "<blockquote>برای این روز موردی ثبت نشده است.</blockquote>"
    parts = ["<table bordered striped compact><tr><th>زمان</th><th>مورد</th><th>وضعیت</th></tr>"]
    for item in visible:
        icon, label = _meta(item); marker, state = _status(item)
        parts.append(f"<tr><td><code>{_esc(_row_time(item), 30)}</code></td><td>{icon} <b>{_esc(item.get('title') or label, 120)}</b><br/>{_esc(label, 40)}</td><td>{marker} {_esc(state, 60)}</td></tr>")
    parts.append("</table>")
    omitted = len(items) - len(visible)
    if omitted > 0:
        parts.append(f"<footer>{to_persian_digits(omitted)} مورد دیگر در نمای روزانه قابل مشاهده است.</footer>")
    return "".join(parts)


def classops_home_screen(items: list[dict[str, Any]], academic: dict[str, Any] | None) -> Screen:
    active = [item for item in items if str(item.get("status") or "") not in {"completed", "cancelled", "canceled", "archived"}]
    exams = sum(1 for item in active if str(item.get("type") or "") == "exam")
    tasks = sum(1 for item in active if str(item.get("type") or "") in FILTER_TYPES["tasks"])
    summary = f"موارد باز: <b>{to_persian_digits(len(active))}</b> · امتحان: <b>{to_persian_digits(exams)}</b> · کار/ددلاین: <b>{to_persian_digits(tasks)}</b>"
    if isinstance(academic, dict) and academic.get("eligible"):
        term = dict(academic.get("academicTerm") or {})
        schedule = dict(academic.get("scheduleContext") or {})
        groups = dict(academic.get("groups") or {})
        morning = dict(groups.get("morning") or {})
        afternoon = dict(groups.get("afternoon") or {})
        context_bits = [str(term.get("termLabel") or "").strip(), str(schedule.get("rotationLabel") or "").strip()]
        if isinstance(morning.get("group"), int): context_bits.append(f"صبح {to_persian_digits(morning['group'])}")
        if isinstance(afternoon.get("group"), int): context_bits.append(f"عصر {to_persian_digits(afternoon['group'])}")
        context = " · ".join(bit for bit in context_bits if bit)
        if context: summary += f"\n{html.escape(context)}"
    return Screen(
        "<b><u>🗂 امور کلاس</u></b>\n\n" + summary + "\n\n<blockquote>برنامه، امتحان و ددلاین مستقیماً از منبع اصلی همین حساب خوانده می‌شود.</blockquote>",
        keyboard(
            [button("📅 امروز", action="c3:d:today", style="primary"), button("🗓 هفته من", action="c3:w:0")],
            [button("📆 ماه پیش رو", action="c3:m:0")],
            [button("📝 امتحان‌ها", action="c3:l:exams"), button("📌 رویدادها", action="c3:l:events")],
            [button("✅ کارها و ددلاین‌ها", action="c3:l:tasks")],
            [button("👥 گروه‌بندی من", action="c3:g"), button("🔔 اعلان‌های امور کلاس", action="c3:n")],
            [button("🏠 خانه", action="home")],
        ),
    )


def daily_screen(day: dict[str, Any], *, owner: bool = False, page: int = 0) -> Screen:
    items = sorted((item for item in day.get("items", []) if isinstance(item, dict)), key=_item_sort_key)
    max_page = max(0, (len(items) - 1) // _DAILY_PAGE_SIZE)
    page = max(0, min(page, max_page))
    visible = items[page * _DAILY_PAGE_SIZE:(page + 1) * _DAILY_PAGE_SIZE]
    title, relative = _day_title(day)
    heading = f"📅 {title}" + (f" · {relative}" if relative else "")
    if max_page:
        heading += f" · صفحه {to_persian_digits(page + 1)} از {to_persian_digits(max_page + 1)}"
    fallback = [f"<b><u>{html.escape(heading)}</u></b>", ""]
    rows: list[list[dict[str, Any]]] = []
    local_date = str(day.get("localDate") or "")
    back_date = local_date.replace("-", "")
    for item in visible:
        icon, label = _meta(item); marker, state = _status(item)
        fallback.append(f"<code>{html.escape(_row_time(item))}</code>  {icon} <b>{_esc(item.get('title') or label, 120)}</b>")
        meta = [label, state]
        if item.get("location"): meta.append("📍 " + _plain(item.get("location"), 70))
        fallback.append("   " + marker + " " + html.escape(" · ".join(meta)))
        prefix = "od" if owner else "d"
        item_button = _item_button(item, f"{prefix}{back_date}p{page}")
        if item_button is not None: rows.append([item_button])
    if not items:
        fallback.append("برای این روز موردی ثبت نشده است.")
    rich_day = dict(day); rich_day["items"] = visible
    rich = [f"<h2>{html.escape(heading)}</h2>", _rich_day_table(rich_day)]
    if max_page:
        nav = []
        prefix = "c3:o:d" if owner else "c3:d"
        if page > 0: nav.append(button("‹ موارد قبلی", action=f"{prefix}:{local_date}:{page - 1}"))
        if page < max_page: nav.append(button("موارد بعدی ›", action=f"{prefix}:{local_date}:{page + 1}"))
        if nav: rows.append(nav)
    if owner:
        rows.append([button("↩️ مدیریت امور کلاس", action="c3:owner"), button("🏠 خانه", action="home")])
    else:
        parsed, _, _ = _date_parts(local_date)
        if parsed:
            rows.append([
                button("‹ روز قبل", action=f"c3:d:{(parsed - timedelta(days=1)).isoformat()}:0"),
                button("روز بعد ›", action=f"c3:d:{(parsed + timedelta(days=1)).isoformat()}:0"),
            ])
        rows.append([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def weekly_screen(days: list[dict[str, Any]], week_offset: int, *, owner: bool = False) -> Screen:
    title = "🗓 این هفته" if week_offset == 0 else ("🗓 هفته بعد" if week_offset == 1 else "🗓 برنامه هفتگی")
    fallback = [f"<b><u>{title}</u></b>"]
    rich = [f"<h2>{title}</h2>"]
    rows: list[list[dict[str, Any]]] = []
    for day in sorted(days, key=lambda value: str(value.get("localDate") or "")):
        day_title, relative = _day_title(day)
        items = sorted((item for item in day.get("items", []) if isinstance(item, dict)), key=_item_sort_key)
        fallback.extend(("", f"<b>{html.escape(day_title)}</b>" + (f" · {relative}" if relative else "")))
        if not items:
            fallback.append("<code>—</code>  بدون مورد")
            rich.append(f"<p><b>{html.escape(day_title)}</b> · بدون مورد</p>")
            continue
        rich.append(f"<h3>{html.escape(day_title)} · {to_persian_digits(len(items))} مورد</h3>")
        rich.append(_rich_day_table(day, limit=_WEEKLY_DAY_PREVIEW))
        for item in items[:_WEEKLY_DAY_PREVIEW]:
            icon, label = _meta(item); marker, _ = _status(item)
            fallback.append(f"<code>{html.escape(_row_time(item))}</code>  {icon} <b>{_esc(item.get('title') or label, 90)}</b> {marker}")
        if len(items) > _WEEKLY_DAY_PREVIEW:
            fallback.append(f"+{to_persian_digits(len(items) - _WEEKLY_DAY_PREVIEW)} مورد دیگر؛ جزئیات در نمای روزانه")
        local = str(day.get("localDate") or "")
        drill_action = f"c3:o:d:{local}" if owner else f"c3:d:{local}"
        rows.append([button(f"{day_title} · {to_persian_digits(len(items))} مورد", action=drill_action)])
    prefix = "c3:o:w" if owner else "c3:w"
    nav = []
    if week_offset > 0: nav.append(button("‹ هفته قبل", action=f"{prefix}:{week_offset - 1}"))
    if week_offset < 8: nav.append(button("هفته بعد ›", action=f"{prefix}:{week_offset + 1}"))
    if nav: rows.append(nav)
    rows.append([button("↩️ مدیریت امور کلاس" if owner else "↩️ امور کلاس", action="c3:owner" if owner else "class-operations"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def month_screen(days: list[dict[str, Any]], page: int, *, owner: bool = False) -> Screen:
    ordered_days = sorted(days, key=lambda value: str(value.get("localDate") or ""))
    max_page = max(0, (len(ordered_days) - 1) // _MONTH_PAGE_SIZE)
    page = max(0, min(page, max_page))
    visible = ordered_days[page * _MONTH_PAGE_SIZE:(page + 1) * _MONTH_PAGE_SIZE]
    fallback = ["<b><u>📆 ماه پیش رو</u></b>", ""]
    rich = ["<h2>📆 ماه پیش رو</h2>"]
    rows: list[list[dict[str, Any]]] = []
    for day in visible:
        day_title, relative = _day_title(day)
        parsed, _, _ = _date_parts(day.get("localDate"))
        delta = (parsed - datetime.now(_TEHRAN).date()).days if parsed else 99
        items = sorted((item for item in day.get("items", []) if isinstance(item, dict)), key=_item_sort_key)
        accent = "🔵" if delta == 0 else ("📅" if 0 < delta <= 7 else ("📆" if 7 < delta <= 14 else "▫️"))
        fallback.append(f"{accent} <b>{html.escape(day_title)}</b> · {to_persian_digits(len(items))} مورد")
        if items:
            for item in items[:4]:
                icon, label = _meta(item); marker, _ = _status(item)
                fallback.append(f"   <code>{html.escape(_row_time(item))}</code> {icon} {_esc(item.get('title') or label, 76)} {marker}")
            if len(items) > 4: fallback.append(f"   +{to_persian_digits(len(items) - 4)} مورد دیگر")
            rich.append(f"<h3>{html.escape(day_title)} · {to_persian_digits(len(items))} مورد</h3>")
            rich.append(_rich_day_table(day, limit=_MONTH_DAY_PREVIEW))
            local = str(day.get("localDate") or "")
            drill_action = f"c3:o:d:{local}" if owner else f"c3:d:{local}"
            rows.append([button(f"{day_title} · {to_persian_digits(len(items))} مورد", action=drill_action)])
        else:
            fallback.append("   <code>—</code> بدون مورد")
    prefix = "c3:o:m" if owner else "c3:m"
    nav = []
    if page > 0: nav.append(button("‹ قبلی", action=f"{prefix}:{page - 1}"))
    if page < max_page: nav.append(button("بعدی ›", action=f"{prefix}:{page + 1}"))
    if nav: rows.append(nav)
    rows.append([button("↩️ مدیریت امور کلاس" if owner else "↩️ امور کلاس", action="c3:owner" if owner else "class-operations"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def filtered_screen(items: list[dict[str, Any]], mode: str, *, owner: bool = False) -> Screen:
    allowed = FILTER_TYPES.get(mode, set())
    filtered = [dict(item) for item in items if str(item.get("type") or "") in allowed]
    filtered.sort(key=lambda item: str(dict(item.get("timing") or {}).get("dueAt") or dict(item.get("timing") or {}).get("startsAt") or "9999"))
    title = FILTER_TITLES.get(mode, "🗂 موارد")
    fallback = [f"<b><u>{title}</u></b>", ""]
    rich = [f"<h2>{title}</h2>"]
    rows: list[list[dict[str, Any]]] = []
    if filtered:
        rich.append("<table bordered striped compact><tr><th>نوع</th><th>مورد</th><th>زمان</th><th>وضعیت</th></tr>")
        for item in filtered[:15]:
            icon, label = _meta(item); marker, state = _status(item)
            timing = dict(item.get("timing") or {})
            when = format_jalali_datetime(timing.get("dueAt") or timing.get("startsAt")) or "—"
            fallback.append(f"{icon} <b>{_esc(item.get('title') or label, 110)}</b>\n   <code>{html.escape(_plain(when, 80))}</code> · {marker} {html.escape(state)}")
            rich.append(f"<tr><td>{icon} {_esc(label, 40)}</td><td><b>{_esc(item.get('title') or label, 100)}</b></td><td>{_esc(when, 80)}</td><td>{marker} {_esc(state, 60)}</td></tr>")
            ref = str(item.get("id") or item.get("ref") or "")
            if ref.startswith("cop_"):
                back = "o" + mode[0] if owner else mode[0]
                rows.append([button(_plain(item.get("title") or label, 30), action=f"c3:i:{ref}:{back}")])
        rich.append("</table>")
    else:
        fallback.append("موردی در این بخش ثبت نشده است.")
        rich.append("<blockquote>موردی در این بخش ثبت نشده است.</blockquote>")
    rows.append([button("↩️ مدیریت امور کلاس" if owner else "↩️ امور کلاس", action="c3:owner" if owner else "class-operations"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def grouping_screen(payload: dict[str, Any]) -> Screen:
    if not payload.get("eligible"):
        return Screen(
            "<b><u>👥 گروه‌بندی من</u></b>\n\n<blockquote>برای این حساب گروه‌بندی رسمی ثبت نشده یا این ورودی مشمول برنامه ترم ۷ نیست.</blockquote>",
            keyboard([button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]),
        )
    groups = dict(payload.get("groups") or {})
    schedule = dict(payload.get("scheduleContext") or {})
    term = dict(payload.get("academicTerm") or {})
    fallback = ["<b><u>👥 گروه‌بندی من</u></b>", ""]
    rich = ["<h2>👥 گروه‌بندی من</h2>", "<table bordered striped compact>"]
    facts = [("ترم", term.get("termLabel") or "—"), ("روتیشن", schedule.get("rotationLabel") or "—")]
    for key, label in (("morning", "صبح"), ("afternoon", "عصر")):
        group = dict(groups.get(key) or {})
        number = group.get("group")
        value = f"گروه {to_persian_digits(number)}" if isinstance(number, int) else "بدون گروه"
        leader = str(group.get("leaderName") or "").strip()
        if leader: value += f" · سرگروه: {leader}"
        facts.append((label, value))
    for label, value in facts:
        fallback.append(f"<code>{html.escape(label)}</code>  <b>{_esc(value, 180)}</b>")
        rich.append(f"<tr><th>{html.escape(label)}</th><td><b>{_esc(value, 180)}</b></td></tr>")
    rich.append("</table>")
    for key, label in (("morning", "اعضای گروه صبح"), ("afternoon", "اعضای گروه عصر")):
        members = [_plain(name, 80) for name in dict(groups.get(key) or {}).get("members", []) if str(name).strip()]
        fallback.extend(("", f"<b>{label}</b>", "، ".join(html.escape(name) for name in members) if members else "عضوی ثبت نشده است."))
        rich.append(f"<details><summary>{label}</summary><p>{html.escape('، '.join(members) if members else 'عضوی ثبت نشده است.')}</p></details>")
    practical = [_plain(item.get("title"), 90) for item in schedule.get("currentPractical", []) if isinstance(item, dict) and item.get("title")]
    if practical:
        current_text = "، ".join(practical)
        fallback.extend(("", "<b>🦷 بخش فعلی</b>", html.escape(current_text)))
        rich.append(f"<blockquote>🦷 بخش فعلی: {html.escape(current_text)}</blockquote>")
    period = dict(schedule.get("rotationPeriod") or {})
    if period.get("from") and period.get("through"):
        period_text = f"{to_persian_digits(period['from'])} تا {to_persian_digits(period['through'])}"
        fallback.extend(("", f"<code>بازه</code>  {html.escape(period_text)}"))
        rich.append(f"<p><b>بازه:</b> {html.escape(period_text)}</p>")
    next_item = dict(schedule.get("nextPractical") or {})
    next_events = [_plain(item.get("title"), 80) for item in next_item.get("events", []) if isinstance(item, dict) and item.get("title")]
    if next_item.get("date") and next_events:
        next_text = f"{to_persian_digits(next_item['date'])} · {'، '.join(next_events)}"
        fallback.extend(("", f"<b>⏭ برنامه بعدی</b>\n{html.escape(next_text)}"))
        rich.append(f"<blockquote>⏭ برنامه بعدی: {html.escape(next_text)}</blockquote>")
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard([button("📅 امروز", action="c3:d:today")], [button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]))


def student_notifications_screen(response: dict[str, Any]) -> Screen:
    data = dict(response.get("data") or {})
    items = []
    for item in data.get("items", []):
        if not isinstance(item, dict): continue
        meta = dict(item.get("meta") or {})
        source = str(item.get("source") or meta.get("source") or "")
        source_key = str(item.get("sourceKey") or meta.get("sourceKey") or "")
        if source == "classops" or source_key.startswith("classops:") or source_key.startswith("item:"):
            items.append(item)
    fallback = ["<b><u>🔔 اعلان‌های امور کلاس</u></b>", ""]
    rich = ["<h2>🔔 اعلان‌های امور کلاس</h2>"]
    if items:
        rich.append("<table bordered striped compact><tr><th>اعلان</th><th>زمان</th><th>وضعیت</th></tr>")
        for item in items[:12]:
            seen = bool(item.get("read") or item.get("isRead"))
            marker, state = ("🟢", "مشاهده‌شده") if seen else ("🟡", "جدید")
            when = format_jalali_datetime(item.get("publishAt") or item.get("effectiveAt")) or "—"
            title = item.get("title") or "اعلان امور کلاس"
            fallback.append(f"{marker} <b>{_esc(title, 120)}</b>\n   <code>{_esc(when, 80)}</code> · {state}")
            rich.append(f"<tr><td><b>{_esc(title, 110)}</b></td><td>{_esc(when, 80)}</td><td>{marker} {state}</td></tr>")
        rich.append("</table>")
    else:
        fallback.append("اعلان فعالی از امور کلاس برای این حساب وجود ندارد.")
        rich.append("<blockquote>اعلان فعالی از امور کلاس برای این حساب وجود ندارد.</blockquote>")
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard([button("↻ تازه‌سازی", action="c3:n", style="primary")], [button("↩️ امور کلاس", action="class-operations"), button("🏠 خانه", action="home")]))


def owner_notification_status_screen(payload: dict[str, Any], ack_payload: dict[str, Any] | None = None) -> Screen:
    deliveries = [item for item in payload.get("deliveries", []) if isinstance(item, dict)]
    ack = dict(dict(ack_payload or {}).get("ack") or {})
    fallback = ["<b><u>🔔 وضعیت اعلان‌های امور کلاس</u></b>", ""]
    rich = ["<h2>🔔 وضعیت اعلان‌های امور کلاس</h2>"]
    if ack:
        summary = f"تأیید: {to_persian_digits(ack.get('acked') or 0)} · در انتظار: {to_persian_digits(ack.get('pending') or 0)}"
        fallback.append(f"<blockquote>{summary}</blockquote>")
        rich.append(f"<blockquote>{summary}</blockquote>")
    notices = [item for item in dict(ack_payload or {}).get("notices", []) if isinstance(item, dict)]
    if notices:
        fallback.extend(("", "<b>✅ تأیید اطلاعیه‌های مهم</b>"))
        rich.append("<table bordered striped compact><caption>تأیید اطلاعیه‌های مهم</caption><tr><th>آیتم</th><th>تأیید</th><th>در انتظار</th></tr>")
        for notice in notices[:10]:
            title = _plain(notice.get("title") or "اطلاعیه مهم", 90)
            acked = to_persian_digits(notice.get("acked") or 0); pending = to_persian_digits(notice.get("pending") or 0)
            fallback.append(f"• <b>{html.escape(title)}</b> · ✅ {acked} · 🟡 {pending}")
            rich.append(f"<tr><td><b>{html.escape(title)}</b></td><td>✅ {acked}</td><td>🟡 {pending}</td></tr>")
        rich.append("</table>")
    if deliveries:
        rich.append("<table bordered striped compact><tr><th>آیتم</th><th>زمان</th><th>مسیر</th><th>وضعیت</th></tr>")
        for entry in deliveries[:15]:
            state_key = str(entry.get("status") or "unknown")
            marker, state = STATUS_META.get(state_key, STATUS_META["unknown"])
            platform = {"telegram": "تلگرام", "bale": "بله"}.get(str(entry.get("platform") or ""), "نامشخص")
            when = format_jalali_datetime(entry.get("scheduledAt")) or "—"
            destination = _destination_label(entry.get("destination"))
            title = _plain(entry.get("itemTitle") or "آیتم امور کلاس", 90)
            fallback.append(f"{marker} <b>{html.escape(title)}</b>\n   <code>{html.escape(_plain(when, 80))}</code> · {platform} · {html.escape(destination)} · {state}")
            rich.append(f"<tr><td><b>{html.escape(title)}</b></td><td>{_esc(when, 80)}</td><td>{platform} · {html.escape(destination)}</td><td>{marker} {state}</td></tr>")
        rich.append("</table>")
    else:
        fallback.append("⚪️ سابقه ارسالی ثبت نشده است.")
        rich.append("<blockquote>سابقه ارسالی ثبت نشده است.</blockquote>")
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard([button("↻ تازه‌سازی", action="c3:o:n", style="primary")], [button("↩️ مدیریت امور کلاس", action="c3:owner")]))


def owner_home_screen() -> Screen:
    return Screen(
        "<b><u>🗂 مدیریت امور کلاس</u></b>\n\n"
        "<blockquote>نمای عملیاتی امور کلاس؛ تغییرات فقط پس از همان پیش‌نمایش و تأیید نهایی فعلی اجرا می‌شوند.</blockquote>",
        keyboard(
            [button("📅 امروز", action="c3:o:d:today", style="primary"), button("🗓 هفته جاری", action="c3:o:w:0")],
            [button("📆 ماه پیش رو", action="c3:o:m:0"), button("🔔 وضعیت اعلان‌ها", action="c3:o:n")],
            [button("📝 امتحان‌ها", action="c3:o:l:exams"), button("📌 رویدادها", action="c3:o:l:events")],
            [button("✅ کارها و ددلاین‌ها", action="c3:o:l:tasks")],
            [button("👥 مرور گروه‌بندی", action="t7")],
            [button("➕ امتحان", action="classops-v2:add:exam"), button("➕ رویداد", action="classops-v2:add:event")],
            [button("➕ کار", action="classops-v2:add:task"), button("➕ ددلاین", action="classops-v2:add:deadline")],
            [button("➕ الزام", action="classops-v2:add:requirement")],
            [button("↩️ مدیریت ربات", action="admin"), button("🏠 خانه", action="home")],
        ),
    )


def _back_action(token: str) -> tuple[str, str]:
    import re
    match = re.fullmatch(r"(od|d)(\d{8})p(\d{1,2})", token)
    if match:
        owner_prefix, raw, page = match.groups()
        local = f"{raw[:4]}-{raw[4:6]}-{raw[6:]}"
        prefix = "c3:o:d" if owner_prefix == "od" else "c3:d"
        return f"{prefix}:{local}:{int(page)}", "↩️ همان روز"
    legacy = re.fullmatch(r"(od|d)(\d{8})", token)
    if legacy:
        owner_prefix, raw = legacy.groups()
        local = f"{raw[:4]}-{raw[4:6]}-{raw[6:]}"
        prefix = "c3:o:d" if owner_prefix == "od" else "c3:d"
        return f"{prefix}:{local}:0", "↩️ همان روز"
    mapping = {"e": ("c3:l:exams", "↩️ امتحان‌ها"), "v": ("c3:l:events", "↩️ رویدادها"), "t": ("c3:l:tasks", "↩️ کارها")}
    if token in mapping: return mapping[token]
    if token.startswith("o"): return "c3:owner", "↩️ مدیریت امور کلاس"
    return "class-operations", "↩️ امور کلاس"


def detail_with_back(screen: Screen, token: str) -> Screen:
    action, label = _back_action(token)
    rows: list[list[dict[str, Any]]] = []
    replaced = False
    for row in dict(screen.keyboard or {}).get("inline_keyboard", []):
        new_row: list[dict[str, Any]] = []
        for entry in row:
            current = dict(entry)
            raw = str(current.get("callback_data") or "")
            stripped = raw[3:] if raw.startswith("v1:") else raw
            if stripped in {"class-operations:list:all", "class-operations"}:
                if not replaced:
                    current = button(label, action=action)
                    replaced = True
                else:
                    continue
            new_row.append(current)
        if new_row: rows.append(new_row)
    if not replaced:
        rows.append([button(label, action=action)])
    return Screen(screen.text, {"inline_keyboard": rows})


def _context(callback: dict[str, Any]) -> tuple[int, int, str]:
    message = dict(callback.get("message") or {})
    sender = dict(callback.get("from") or message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        return 0, 0, ""
    raw = str(callback.get("data") or "")
    return chat_id, user_id, raw[3:] if raw.startswith("v1:") else raw


def _week_start(week_offset: int) -> date:
    today = datetime.now(_TEHRAN).date()
    days_since_saturday = (today.weekday() - 5) % 7
    return today - timedelta(days=days_since_saturday) + timedelta(days=7 * week_offset)


def _timeline(app: DentBotApp, user_id: int, *, start_date: str = "", days: int = 1) -> list[dict[str, Any]]:
    fields: dict[str, Any] = {"days": days}
    if start_date: fields["startDate"] = start_date
    response = app.site_api.request("classopsTimelineV3", user_id, **fields)
    return [day for day in response.get("days", []) if isinstance(day, dict)]


def _list_items(app: DentBotApp, user_id: int) -> list[dict[str, Any]]:
    response = app.site_api.request("classopsList", user_id, limit=50)
    return [item for item in dict(response.get("data") or {}).get("items", []) if isinstance(item, dict)]


def _show(app: DentBotApp, chat_id: int, callback: dict[str, Any], screen: Screen) -> None:
    classops._render_screen(app, chat_id, screen, callback=callback)


def _owner_allowed(app: DentBotApp, user_id: int) -> bool:
    if user_id != int(getattr(app, "owner_id", -1)):
        return False
    try:
        return str(app.site_api.request("classopsCapabilities", user_id).get("role") or "") == "owner"
    except SiteApiError:
        return False


def _parse_daily_target(raw: str) -> tuple[str, int]:
    parts = raw.rsplit(":", 1)
    if len(parts) == 2 and parts[1].isdigit():
        target, page_text = parts
        page = int(page_text)
    else:
        target, page = raw, 0
    if page < 0 or page > 20:
        raise ValueError("daily page")
    if target != "today":
        date.fromisoformat(target)
    return target, page


def _daily_from_action(app: DentBotApp, user_id: int, target: str) -> dict[str, Any]:
    start_date = "" if target == "today" else target
    days = _timeline(app, user_id, start_date=start_date, days=1)
    return days[0] if days else {"localDate": start_date or datetime.now(_TEHRAN).date().isoformat(), "items": []}


def _error_screen(owner: bool = False) -> Screen:
    return Screen(
        "<b>⚠️ امور کلاس</b>\n\nاین نما فعلاً قابل دریافت نیست؛ داده‌ای تغییر نکرد.",
        keyboard([button("↩️ مدیریت امور کلاس" if owner else "↩️ امور کلاس", action="c3:owner" if owner else "class-operations"), button("🏠 خانه", action="home")]),
    )


def install_classops_ux_v3() -> None:
    global _INSTALLED
    if _INSTALLED:
        return
    _INSTALLED = True
    previous_callback = DentBotApp._callback

    def callback_v3(self: DentBotApp, callback: dict[str, Any], *, interaction_version: int | None = None) -> Any:
        chat_id, user_id, data = _context(callback)
        handled = data == "class-operations" or data in {"classops-v2:owner", "class-operations:owner"} or data.startswith("c3:")
        if not handled or chat_id == 0 or user_id == 0:
            return previous_callback(self, callback, interaction_version=interaction_version)
        callback_id = str(callback.get("id") or "")
        if callback_id:
            try: self.api.answer_callback(callback_id)
            except Exception: pass
        chat = dict(dict(callback.get("message") or {}).get("chat") or {})
        if str(chat.get("type") or "private") != "private":
            self.api.send(chat_id, "امور کلاس فقط در گفت‌وگوی خصوصی ربات در دسترس است.", {"inline_keyboard": []})
            return None
        blocked = getattr(self, "_private_access_gate", lambda _uid: None)(user_id)
        if blocked is not None:
            _show(self, chat_id, callback, blocked)
            return None
        owner_route = data in {"classops-v2:owner", "class-operations:owner"} or data == "c3:owner" or data.startswith("c3:o:")
        if owner_route and not _owner_allowed(self, user_id):
            return previous_callback(self, callback, interaction_version=interaction_version)
        try:
            if data == "class-operations":
                items = _list_items(self, user_id)
                academic = self.site_api.request("academicTerm7Self", user_id)
                _show(self, chat_id, callback, classops_home_screen(items, academic))
                return None
            if data in {"classops-v2:owner", "class-operations:owner", "c3:owner"}:
                _show(self, chat_id, callback, owner_home_screen())
                return None
            if data.startswith("c3:d:"):
                target, page = _parse_daily_target(data.split(":", 2)[2])
                _show(self, chat_id, callback, daily_screen(_daily_from_action(self, user_id, target), page=page))
                return None
            if data.startswith("c3:o:d:"):
                target, page = _parse_daily_target(data.split(":", 3)[3])
                _show(self, chat_id, callback, daily_screen(_daily_from_action(self, user_id, target), owner=True, page=page))
                return None
            if data.startswith("c3:w:") or data.startswith("c3:o:w:"):
                owner = data.startswith("c3:o:w:")
                week_offset = int(data.rsplit(":", 1)[-1])
                if week_offset < 0 or week_offset > 8: raise ValueError("week offset")
                start = _week_start(week_offset).isoformat()
                _show(self, chat_id, callback, weekly_screen(_timeline(self, user_id, start_date=start, days=7), week_offset, owner=owner))
                return None
            if data.startswith("c3:m:") or data.startswith("c3:o:m:"):
                owner = data.startswith("c3:o:m:")
                page = int(data.rsplit(":", 1)[-1])
                if page < 0 or page > 4: raise ValueError("month page")
                _show(self, chat_id, callback, month_screen(_timeline(self, user_id, days=31), page, owner=owner))
                return None
            if data.startswith("c3:l:") or data.startswith("c3:o:l:"):
                owner = data.startswith("c3:o:l:")
                mode = data.rsplit(":", 1)[-1]
                if mode not in FILTER_TYPES: raise ValueError("filter")
                _show(self, chat_id, callback, filtered_screen(_list_items(self, user_id), mode, owner=owner))
                return None
            if data == "c3:g":
                _show(self, chat_id, callback, grouping_screen(self.site_api.request("academicTerm7Self", user_id)))
                return None
            if data == "c3:n":
                _show(self, chat_id, callback, student_notifications_screen(self.site_api.notifications(user_id, limit=30)))
                return None
            if data == "c3:o:n":
                status = self.site_api.request("classopsNotificationStatusV3", user_id)
                ack = self.site_api.request("classopsAckStatusV2", user_id)
                _show(self, chat_id, callback, owner_notification_status_screen(status, ack))
                return None
            if data.startswith("c3:i:cop_"):
                parts = data.split(":", 3)
                if len(parts) != 4: raise ValueError("detail")
                item_id, back = parts[2], parts[3]
                response = self.site_api.request("classopsGet", user_id, id=item_id)
                screen = classops._detail_screen(dict(response.get("item") or {}), dict(response.get("actions") or {}))
                _show(self, chat_id, callback, detail_with_back(screen, back))
                return None
        except (SiteApiError, ValueError):
            _show(self, chat_id, callback, _error_screen(owner_route))
            return None
        return previous_callback(self, callback, interaction_version=interaction_version)

    DentBotApp._callback = callback_v3  # type: ignore[method-assign]

    previous_dynamic_screen = DentBotApp._dynamic_screen

    def dynamic_screen_v3(
        self: DentBotApp, name: str, user_id: int, *, request_id: str = "", sender: dict[str, Any] | None = None
    ) -> Screen:
        screen = previous_dynamic_screen(self, name, user_id, request_id=request_id, sender=sender)
        if name not in {"account", "check-link", "link-required"} or self.site_api is None:
            return screen
        try:
            account = self.site_api.account(user_id)
        except SiteApiError:
            return screen
        linked_user = (
            dict(account.get("user") or {})
            if account.get("linked") and account.get("authComplete")
            else None
        )
        if not linked_user or str(linked_user.get("cohortKey") or "") != "dentistry-1402":
            return screen
        try:
            academic_context = self.site_api.request("academicTerm7Self", user_id)
        except SiteApiError:
            academic_context = None
        return account_screen_with_academic_context(screen, linked_user, academic_context)

    DentBotApp._dynamic_screen = dynamic_screen_v3  # type: ignore[method-assign]

    previous_message = DentBotApp._message
    def message_v3(self: DentBotApp, message: dict[str, Any]) -> Any:
        text = str(message.get("text") or "").strip().lower()
        first = text.split(maxsplit=1)[0] if text else ""
        if first == "/classops" or first.startswith("/classops@"):
            sender = dict(message.get("from") or {}); chat = dict(message.get("chat") or {})
            try: user_id = int(sender.get("id") or 0); chat_id = int(chat.get("id") or user_id)
            except (TypeError, ValueError): return previous_message(self, message)
            blocked = getattr(self, "_private_access_gate", lambda _uid: None)(user_id)
            if blocked is not None: self.api.send(chat_id, blocked.text, blocked.keyboard); return None
            try:
                academic = self.site_api.request("academicTerm7Self", user_id)
                screen = classops_home_screen(_list_items(self, user_id), academic)
            except SiteApiError:
                screen = _error_screen(False)
            self.api.send(chat_id, screen.text, screen.keyboard); return None
        return previous_message(self, message)
    DentBotApp._message = message_v3  # type: ignore[method-assign]
