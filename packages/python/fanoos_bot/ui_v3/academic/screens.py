from __future__ import annotations

from collections import OrderedDict
from collections.abc import Iterable, Mapping, Sequence
from datetime import datetime
from typing import Any

from ...formatting import (
    format_datetime,
    format_human_number,
    format_score,
    format_time,
    is_uuid,
    truncate_text,
)
from ..core import Action, ActionRow, Context, Fact, ListItem, Pagination, Screen, Section, Severity
from . import actions

COURSE_PAGE_SIZE = 10
SCHEDULE_PAGE_SIZE = 12
GRADE_PAGE_SIZE = 16
ANNOUNCEMENT_PAGE_SIZE = 8

_PAIR_MAX_LABEL = 22
_PAIR_MAX_COMBINED = 36


def _text(value: object, limit: int = 120) -> str:
    return truncate_text(" ".join(str(value or "").split()), limit)


def _action(action_id: str, label: str, payload: Mapping[str, object] | None = None) -> Action:
    return Action(id=action_id, label=label, payload=dict(payload or {}))


def _context(*parts: str) -> Context:
    labels = tuple(value for value in (_text(part, 80) for part in parts) if value)
    return Context(breadcrumb=" › ".join(labels))


def _pack_actions(items: Sequence[tuple[str, str, Mapping[str, object] | None]]) -> tuple[ActionRow, ...]:
    """Pack short peer actions two per row without squeezing long Persian labels."""
    rows: list[ActionRow] = []
    index = 0
    while index < len(items):
        current = items[index]
        pair: list[tuple[str, str, Mapping[str, object] | None]] = [current]
        if index + 1 < len(items):
            following = items[index + 1]
            if (
                len(current[1]) <= _PAIR_MAX_LABEL
                and len(following[1]) <= _PAIR_MAX_LABEL
                and len(current[1]) + len(following[1]) <= _PAIR_MAX_COMBINED
            ):
                pair.append(following)
        rows.append(ActionRow(actions=tuple(_action(action_id, label, payload) for action_id, label, payload in pair)))
        index += len(pair)
    return tuple(rows)


def _nav_rows(back_id: str | None = None, back_label: str = "‹ بازگشت", back_payload: Mapping[str, object] | None = None) -> tuple[ActionRow, ...]:
    nav: list[tuple[str, str, Mapping[str, object] | None]] = []
    if back_id and back_id != actions.HOME:
        nav.append((back_id, back_label, back_payload))
    nav.append((actions.HOME, "🏠 خانه", None))
    return _pack_actions(nav)


def _page(
    *,
    page: int,
    previous_ref: str | None,
    next_ref: str | None,
    action_id: str,
) -> Pagination | None:
    previous = _action(action_id, "‹ قبلی", {"page_ref": previous_ref}) if previous_ref else None
    following = _action(action_id, "بعدی ›", {"page_ref": next_ref}) if next_ref else None
    if not previous and not following and page <= 1:
        return None
    return Pagination(
        label=f"صفحه {format_human_number(max(1, page))}",
        previous=previous,
        next=following,
    )


def _timezone_footer(timezone_name: object) -> str:
    name = _text(timezone_name, 64)
    if not name:
        return "زمان‌ها بر اساس منطقه زمانی فضای آموزشی نمایش داده می‌شوند."
    return f"زمان‌ها بر اساس منطقه زمانی فضای آموزشی ({name}) نمایش داده می‌شوند."


def _course_term(course: Mapping[str, object]) -> str:
    return _text(course.get("term_name") or course.get("term_key"), 60)


def _course_title(course: Mapping[str, object]) -> str:
    return _text(course.get("course_title") or course.get("title"), 90) or "درس"


def _course_code(course: Mapping[str, object]) -> str:
    return _text(course.get("course_code") or course.get("code"), 30)


def _dedupe_courses(rows: Iterable[Mapping[str, object]]) -> tuple[dict[str, object], ...]:
    by_id: OrderedDict[str, dict[str, object]] = OrderedDict()
    for source in rows:
        course_id = str(source.get("course_id") or "")
        if not is_uuid(course_id):
            continue
        title = _course_title(source)
        if title == "درس":
            continue
        current = by_id.get(course_id)
        if current is None:
            by_id[course_id] = {
                "course_id": course_id,
                "course_title": title,
                "course_code": _course_code(source),
                "term": _course_term(source),
            }
            continue
        if not current.get("course_code"):
            current["course_code"] = _course_code(source)
        incoming_term = _course_term(source)
        existing_term = str(current.get("term") or "")
        if incoming_term and existing_term and incoming_term != existing_term:
            # Multiple offering terms are canonical facts, but none is assumed to
            # be the selected/current term unless the projection says so.
            current["term"] = ""
        elif incoming_term and not existing_term:
            current["term"] = incoming_term
    return tuple(by_id.values())


def course_list_screen(
    courses: Iterable[Mapping[str, object]],
    *,
    page: int = 1,
    previous_ref: str | None = None,
    next_ref: str | None = None,
    workspace_label: str = "",
    selected_term: str = "",
) -> Screen:
    normalized = _dedupe_courses(courses)[:COURSE_PAGE_SIZE]
    if not normalized:
        return course_empty_screen(workspace_label=workspace_label)

    list_items: list[ListItem] = []
    buttons: list[tuple[str, str, Mapping[str, object] | None]] = []
    for course in normalized:
        course_id = str(course["course_id"])
        title = str(course["course_title"])
        code = str(course.get("course_code") or "")
        term = str(course.get("term") or "")
        meta = " · ".join(value for value in (code, term) if value)
        list_items.append(
            ListItem(
                title=title,
                subtitle=meta,
                action=_action(actions.COURSE_OPEN, "باز کردن درس", {"course_id": course_id}),
            )
        )
        buttons.append((actions.COURSE_OPEN, truncate_text(title, 28), {"course_id": course_id}))

    facts: list[Fact] = []
    if workspace_label:
        facts.append(Fact(label="فضای آموزشی", value=_text(workspace_label, 70)))
    if selected_term:
        facts.append(Fact(label="ترم", value=_text(selected_term, 60)))

    return Screen(
        id="academic.course.list",
        title="📚 درس‌ها",
        context=_context("درس‌ها"),
        intro="یک درس را برای دیدن بخش‌های آموزشی آن انتخاب کنید.",
        sections=(Section(items=tuple(list_items), facts=tuple(facts)),),
        action_rows=_pack_actions(buttons) + _nav_rows(),
        pagination=_page(page=page, previous_ref=previous_ref, next_ref=next_ref, action_id=actions.COURSES_PAGE),
        severity=Severity.INFO,
    )


def course_empty_screen(*, workspace_label: str = "") -> Screen:
    body = "برای این فضای آموزشی هنوز درسی در فهرست قابل‌نمایش منتشر نشده است."
    if workspace_label:
        body = f"برای «{_text(workspace_label, 70)}» هنوز درسی در فهرست قابل‌نمایش منتشر نشده است."
    return Screen(
        id="academic.course.empty",
        title="📚 درس‌ها",
        context=_context("درس‌ها"),
        intro=body,
        sections=(Section(body="بعداً دوباره این بخش را بررسی کنید یا فضای آموزشی فعال را تغییر دهید."),),
        action_rows=_nav_rows(),
        severity=Severity.INFO,
    )


def course_unavailable_screen(*, permission_denied: bool = False) -> Screen:
    message = (
        "اجازه مشاهده این درس را ندارید. دسترسی از روی عضویت و مجوزهای فعلی فضای آموزشی بررسی می‌شود."
        if permission_denied
        else "این درس در فهرست مجاز فعلی پیدا نشد. فهرست درس‌ها را دوباره باز کنید."
    )
    return Screen(
        id="academic.course.unavailable",
        title="📚 درس در دسترس نیست",
        context=_context("درس‌ها"),
        intro=message,
        action_rows=_nav_rows(actions.COURSES, "‹ درس‌ها"),
        severity=Severity.WARNING,
    )


def course_detail_screen(
    course: Mapping[str, object],
    *,
    supported_actions: Iterable[str] = ("schedule", "resources", "grades"),
) -> Screen:
    course_id = str(course.get("course_id") or "")
    if not is_uuid(course_id):
        return course_unavailable_screen()
    title = _course_title(course)
    code = _course_code(course)
    term = _course_term(course)

    action_meta: dict[str, tuple[str, str]] = {
        "schedule": (actions.COURSE_SCHEDULE, "📅 برنامه"),
        "resources": (actions.COURSE_RESOURCES, "📚 منابع"),
        "assessments": (actions.COURSE_ASSESSMENTS, "📝 آزمون‌ها"),
        "grades": (actions.COURSE_GRADES, "🎓 نمرات"),
        "announcements": (actions.COURSE_ANNOUNCEMENTS, "📢 اطلاعیه‌ها"),
        "sessions": (actions.COURSE_SESSIONS, "🗂 جلسات"),
    }
    requested = set(supported_actions)
    action_specs = [
        (action_id, label, {"course_id": course_id})
        for key, (action_id, label) in action_meta.items()
        if key in requested
    ]
    facts = tuple(
        Fact(label=label, value=value)
        for label, value in (("کد درس", code), ("ترم", term))
        if value
    )
    return Screen(
        id="academic.course.detail",
        title=f"📚 {title}",
        context=_context("درس‌ها", title),
        intro="بخش موردنظر این درس را انتخاب کنید.",
        sections=(Section(facts=facts),) if facts else (),
        action_rows=_pack_actions(action_specs) + _nav_rows(actions.COURSES, "‹ درس‌ها"),
        severity=Severity.INFO,
    )


def schedule_hub_screen(*, course: Mapping[str, object] | None = None, upcoming_supported: bool = True) -> Screen:
    course_id = str((course or {}).get("course_id") or "")
    course_title = _course_title(course or {}) if course else ""
    payload = {"course_id": course_id} if is_uuid(course_id) else None
    specs: list[tuple[str, str, Mapping[str, object] | None]] = [
        (actions.SCHEDULE_TODAY, "📅 امروز", payload),
        (actions.SCHEDULE_TOMORROW, "📅 فردا", payload),
    ]
    if upcoming_supported:
        specs.append((actions.SCHEDULE_UPCOMING, "🗓 پیشِ رو", payload))
    back_id = actions.COURSE_OPEN if payload else None
    return Screen(
        id="academic.schedule.hub",
        title="📅 برنامه" if not course_title else f"📅 برنامه · {course_title}",
        context=_context("درس‌ها", course_title, "برنامه") if course_title else _context("برنامه"),
        intro="بازه موردنظر را انتخاب کنید. تاریخ و ساعت از منطقه زمانی فضای آموزشی می‌آید.",
        action_rows=_pack_actions(specs) + _nav_rows(back_id, "‹ بازگشت به درس", payload),
        severity=Severity.INFO,
    )


def _event_title(item: Mapping[str, object]) -> str:
    return _text(item.get("title") or item.get("course_title"), 100) or "رویداد آموزشی"


def _event_subtitle(item: Mapping[str, object], timezone_name: str) -> str:
    start = format_time(item.get("starts_at"), timezone_name)
    course = _text(item.get("course_title"), 70)
    location = _text(item.get("location_text"), 80)
    return " · ".join(value for value in (start, course, location) if value)


def schedule_list_screen(
    items: Iterable[Mapping[str, object]],
    *,
    label: str,
    timezone_name: str,
    page: int = 1,
    previous_ref: str | None = None,
    next_ref: str | None = None,
    course: Mapping[str, object] | None = None,
) -> Screen:
    visible = tuple(item for item in items if isinstance(item, Mapping))[:SCHEDULE_PAGE_SIZE]
    course_id = str((course or {}).get("course_id") or "")
    course_title = _course_title(course or {}) if course else ""
    list_items: list[ListItem] = []
    for item in visible:
        event_id = str(item.get("id") or item.get("event_id") or "")
        action = _action(actions.SCHEDULE_EVENT_OPEN, "جزئیات", {"event_id": event_id}) if is_uuid(event_id) else None
        list_items.append(ListItem(title=_event_title(item), subtitle=_event_subtitle(item, timezone_name), action=action))

    intro = "" if list_items else f"برای {label} برنامه‌ای ثبت نشده است."
    payload = {"course_id": course_id} if is_uuid(course_id) else None
    back_id = actions.COURSE_SCHEDULE if payload else actions.SCHEDULE
    back_label = "‹ برنامه درس" if payload else "‹ برنامه"
    title = f"📅 {label}" if not course_title else f"📅 {label} · {course_title}"
    context = _context("درس‌ها", course_title, "برنامه", label) if course_title else _context("برنامه", label)
    return Screen(
        id="academic.schedule.list",
        title=title,
        context=context,
        intro=intro,
        sections=(Section(items=tuple(list_items)),) if list_items else (),
        action_rows=_nav_rows(back_id, back_label, payload),
        pagination=_page(page=page, previous_ref=previous_ref, next_ref=next_ref, action_id=actions.SCHEDULE_PAGE),
        severity=Severity.INFO,
        footer=_timezone_footer(timezone_name),
    )


def event_detail_screen(
    event: Mapping[str, object],
    *,
    timezone_name: str,
    back_payload: Mapping[str, object] | None = None,
) -> Screen:
    title = _event_title(event)
    start = format_time(event.get("starts_at"), timezone_name)
    end = format_time(event.get("ends_at"), timezone_name) if event.get("ends_at") else ""
    when = f"{start} تا {end}" if end else start
    facts = [Fact(label="زمان", value=when)]
    course = _text(event.get("course_title"), 80)
    location = _text(event.get("location_text"), 100)
    if course:
        facts.append(Fact(label="درس", value=course))
    if location:
        facts.append(Fact(label="مکان", value=location))
    return Screen(
        id="academic.schedule.event.detail",
        title=f"📅 {title}",
        context=_context("برنامه", "جزئیات رویداد"),
        sections=(Section(facts=tuple(facts)),),
        action_rows=_nav_rows(actions.SCHEDULE, "‹ برنامه", back_payload),
        severity=Severity.INFO,
        footer=_timezone_footer(timezone_name),
    )


def _grade_title(item: Mapping[str, object]) -> str:
    return _text(item.get("item_title") or item.get("gradebook_title"), 90) or "نمره"


def _grade_value(item: Mapping[str, object]) -> str:
    score = format_score(item.get("score"))
    maximum = item.get("max_score")
    return f"{score} از {format_score(maximum)}" if maximum is not None else score


def grade_list_screen(
    items: Iterable[Mapping[str, object]],
    *,
    page: int = 1,
    previous_ref: str | None = None,
    next_ref: str | None = None,
) -> Screen:
    visible = tuple(item for item in items if isinstance(item, Mapping))[:GRADE_PAGE_SIZE]
    grouped: OrderedDict[tuple[str, str], list[Mapping[str, object]]] = OrderedDict()
    for item in visible:
        key = (str(item.get("course_id") or ""), _text(item.get("course_title"), 80) or "درس")
        grouped.setdefault(key, []).append(item)

    sections: list[Section] = []
    for (course_id, course_title), course_items in grouped.items():
        rows = tuple(
            ListItem(
                title=_grade_title(item),
                subtitle=_grade_value(item),
                facts=(Fact(label="وضعیت", value="منتشرشده"),),
                action=(
                    _action(actions.COURSE_GRADE_OPEN, "نمرات درس", {"course_id": course_id})
                    if is_uuid(course_id)
                    else None
                ),
            )
            for item in course_items
        )
        sections.append(Section(title=course_title, items=rows))

    return Screen(
        id="academic.grades.list",
        title="🎓 نمرات",
        context=_context("نمرات"),
        intro="" if sections else "نمره منتشرشده‌ای برای شما پیدا نشد.",
        sections=tuple(sections),
        action_rows=_nav_rows(),
        pagination=_page(page=page, previous_ref=previous_ref, next_ref=next_ref, action_id=actions.GRADES_PAGE),
        severity=Severity.INFO,
        footer="فقط نمره‌های منتشرشده نمایش داده می‌شوند. معدل یا میانگین در این بخش محاسبه نمی‌شود.",
    )


def course_grade_detail_screen(course: Mapping[str, object], items: Iterable[Mapping[str, object]]) -> Screen:
    course_id = str(course.get("course_id") or "")
    if not is_uuid(course_id):
        return course_unavailable_screen()
    title = _course_title(course)
    visible = tuple(item for item in items if str(item.get("course_id") or "") == course_id)[:GRADE_PAGE_SIZE]
    grade_items = tuple(
        ListItem(
            title=_grade_title(item),
            subtitle=_grade_value(item),
            facts=(Fact(label="وضعیت", value="منتشرشده"),),
        )
        for item in visible
    )
    return Screen(
        id="academic.grades.course",
        title=f"🎓 نمرات · {title}",
        context=_context("درس‌ها", title, "نمرات"),
        intro="" if grade_items else "نمره منتشرشده‌ای برای این درس پیدا نشد.",
        sections=(Section(items=grade_items),) if grade_items else (),
        action_rows=_nav_rows(actions.COURSE_OPEN, "‹ بازگشت به درس", {"course_id": course_id}),
        severity=Severity.INFO,
        footer="هیچ معدل یا میانگینی از روی داده ناقص محاسبه نمی‌شود.",
    )


def _announcement_time(item: Mapping[str, object]) -> str:
    value = item.get("published_at") or item.get("effective_at") or item.get("created_at")
    return format_datetime(value) if value else ""


def announcement_list_screen(
    items: Iterable[Mapping[str, object]],
    *,
    page: int = 1,
    previous_ref: str | None = None,
    next_ref: str | None = None,
) -> Screen:
    visible = tuple(item for item in items if isinstance(item, Mapping))[:ANNOUNCEMENT_PAGE_SIZE]
    list_items: list[ListItem] = []
    for item in visible:
        announcement_id = str(item.get("id") or item.get("announcement_id") or "")
        published = _announcement_time(item)
        read_label = "خوانده‌شده" if item.get("read_at") else ""
        subtitle = " · ".join(value for value in (published, read_label) if value)
        action = _action(actions.ANNOUNCEMENT_OPEN, "مشاهده اطلاعیه", {"announcement_id": announcement_id}) if is_uuid(announcement_id) else None
        list_items.append(ListItem(title=_text(item.get("title"), 100) or "اطلاعیه", subtitle=subtitle, action=action))
    return Screen(
        id="academic.announcements.list",
        title="📢 اطلاعیه‌ها",
        context=_context("اطلاعیه‌ها"),
        intro="" if list_items else "اطلاعیه منتشرشده‌ای برای این فضای آموزشی پیدا نشد.",
        sections=(Section(items=tuple(list_items)),) if list_items else (),
        action_rows=_nav_rows(),
        pagination=_page(page=page, previous_ref=previous_ref, next_ref=next_ref, action_id=actions.ANNOUNCEMENTS_PAGE),
        severity=Severity.INFO,
    )


def announcement_detail_screen(
    item: Mapping[str, object],
    *,
    safe_link_ref: str | None = None,
) -> Screen:
    title = _text(item.get("title"), 120) or "اطلاعیه"
    body = truncate_text(str(item.get("body") or "").strip(), 2200)
    facts: list[Fact] = []
    published = _announcement_time(item)
    if published:
        facts.append(Fact(label="زمان انتشار", value=published))
    if item.get("read_at"):
        facts.append(Fact(label="وضعیت", value="خوانده‌شده"))
    rows: tuple[ActionRow, ...] = ()
    if safe_link_ref:
        rows += _pack_actions(((actions.ANNOUNCEMENT_LINK_OPEN, "🔗 باز کردن پیوند", {"link_ref": safe_link_ref}),))
    rows += _nav_rows(actions.ANNOUNCEMENTS, "‹ اطلاعیه‌ها")
    return Screen(
        id="academic.announcement.detail",
        title=f"📢 {title}",
        context=_context("اطلاعیه‌ها", title),
        intro=body or "متنی برای این اطلاعیه ثبت نشده است.",
        sections=(Section(facts=tuple(facts)),) if facts else (),
        action_rows=rows,
        severity=Severity.INFO,
    )


def notifications_entry_screen() -> Screen:
    return Screen(
        id="academic.notifications.entry",
        title="🔔 اعلان‌های شخصی",
        context=_context("اعلان‌های شخصی"),
        intro="اعلان‌های شخصی ممکن است از مسیر پیام‌رسان به شما تحویل شوند، اما تحویل push یک صندوق ورودی دائمی نیست.",
        sections=(
            Section(
                title="📢 اطلاعیه‌ها",
                body="اطلاعیه‌های منتشرشده فضای آموزشی فهرست مستقل و قابل‌مشاهده دارند.",
            ),
            Section(
                title="🔔 تاریخچه شخصی",
                body="در قرارداد فعلی، projection قابل‌اعتماد برای تاریخچه اعلان‌های شخصی وجود ندارد؛ بنابراین تاریخچه محلی ساخته نمی‌شود.",
            ),
        ),
        action_rows=_pack_actions(((actions.ANNOUNCEMENTS, "📢 اطلاعیه‌ها", None),)) + _nav_rows(),
        severity=Severity.INFO,
    )


def academic_error_screen(*, area_label: str, retry_action: str | None = None, back_action: str | None = None) -> Screen:
    specs: list[tuple[str, str, Mapping[str, object] | None]] = []
    if retry_action:
        specs.append((retry_action, "🔄 تلاش دوباره", None))
    rows = _pack_actions(specs) + _nav_rows(back_action)
    return Screen(
        id="academic.error",
        title=f"❌ {area_label}",
        intro="این بخش فعلاً بارگذاری نشد. دوباره تلاش کنید؛ اگر مشکل ادامه داشت از خانه مسیر را از نو باز کنید.",
        action_rows=rows,
        severity=Severity.ERROR,
    )


def academic_loading_screen(*, area_label: str) -> Screen:
    return Screen(
        id="academic.loading",
        title=area_label,
        intro="در حال دریافت اطلاعات از فانوس…",
        action_rows=_nav_rows(),
        severity=Severity.INFO,
    )


__all__ = [
    "COURSE_PAGE_SIZE",
    "SCHEDULE_PAGE_SIZE",
    "GRADE_PAGE_SIZE",
    "ANNOUNCEMENT_PAGE_SIZE",
    "course_list_screen",
    "course_empty_screen",
    "course_unavailable_screen",
    "course_detail_screen",
    "schedule_hub_screen",
    "schedule_list_screen",
    "event_detail_screen",
    "grade_list_screen",
    "course_grade_detail_screen",
    "announcement_list_screen",
    "announcement_detail_screen",
    "notifications_entry_screen",
    "academic_error_screen",
    "academic_loading_screen",
]
