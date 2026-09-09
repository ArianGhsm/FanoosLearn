from __future__ import annotations

from collections import OrderedDict
from collections.abc import Iterable, Mapping, Sequence

from ...formatting import (
    format_datetime,
    format_human_number,
    format_score,
    format_time,
    is_uuid,
    truncate_text,
)
from ..core import (
    Action,
    ActionRow,
    Breadcrumb,
    CallbackIntent,
    Context,
    Fact,
    ListItem,
    Pagination,
    Screen,
    Section,
    Severity,
)
from . import actions

# A page can still fit its worst-case one-action-per-row buttons plus the final
# navigation row under bot-01/core's 10-row screen bound.
COURSE_PAGE_SIZE = 8
SCHEDULE_PAGE_SIZE = 8
GRADE_PAGE_SIZE = 16
ANNOUNCEMENT_PAGE_SIZE = 8

_PAIR_MAX_LABEL = 22
_PAIR_MAX_COMBINED = 36

_INTENT_NAMES = {
    actions.HOME: "home",
    actions.RETRY: "retry",
}


def _text(value: object, limit: int = 120) -> str:
    return truncate_text(" ".join(str(value or "").split()), limit)


def _action(action_id: str, label: str, payload: Mapping[str, object] | None = None) -> Action:
    raw = dict(payload or {})
    route_ref = ""
    for key in ("route_ref", "page_ref", "link_ref"):
        value = str(raw.pop(key, "") or "")
        if value:
            route_ref = value
            break

    params: tuple[tuple[str, str], ...] = ()
    if not route_ref:
        params = tuple(
            (str(key), str(value))
            for key, value in raw.items()
            if value is not None and str(value)
        )
    intent = CallbackIntent(
        name=_INTENT_NAMES.get(action_id, action_id),
        params=params,
        route_ref=route_ref or None,
    )
    return Action(identifier=action_id, label=label, intent=intent)


def _breadcrumbs(*parts: str) -> tuple[Breadcrumb, ...]:
    return tuple(Breadcrumb(value) for value in (_text(part, 80) for part in parts) if value)


def _course_list_context(workspace_label: str, selected_term: str) -> Context | None:
    workspace = _text(workspace_label, 70)
    term = _text(selected_term, 60)
    if workspace:
        return Context("فضای آموزشی", workspace, f"ترم: {term}" if term else "")
    if term:
        return Context("ترم", term)
    return None


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
        rows.append(
            ActionRow(
                tuple(
                    _action(action_id, label, payload)
                    for action_id, label, payload in pair
                )
            )
        )
        index += len(pair)
    return tuple(rows)


def _nav_rows(
    back_id: str | None = None,
    back_label: str = "‹ بازگشت",
    back_payload: Mapping[str, object] | None = None,
) -> tuple[ActionRow, ...]:
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
    safe_page = max(1, page)
    return Pagination(
        page=safe_page,
        label=f"صفحه {format_human_number(safe_page)}",
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
        raw_title = _text(source.get("course_title") or source.get("title"), 90)
        if not is_uuid(course_id) or not raw_title:
            continue
        current = by_id.get(course_id)
        if current is None:
            by_id[course_id] = {
                "course_id": course_id,
                "course_title": raw_title,
                "course_code": _course_code(source),
                "term": _course_term(source),
            }
            continue
        if not current.get("course_code"):
            current["course_code"] = _course_code(source)
        incoming_term = _course_term(source)
        existing_term = str(current.get("term") or "")
        if incoming_term and existing_term and incoming_term != existing_term:
            # Multiple offering terms are canonical, but none is assumed to be
            # the selected/current term unless the projection says so.
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
        list_items.append(ListItem(title=title, meta=meta))
        buttons.append((actions.COURSE_OPEN, truncate_text(title, 28), {"course_id": course_id}))

    return Screen(
        identifier="academic.course.list",
        title="📚 درس‌ها",
        intro="یک درس را برای دیدن بخش‌های آموزشی آن انتخاب کنید.",
        context=_course_list_context(workspace_label, selected_term),
        breadcrumb=_breadcrumbs("درس‌ها"),
        sections=(Section(items=tuple(list_items)),),
        action_rows=_pack_actions(buttons) + _nav_rows(),
        pagination=_page(
            page=page,
            previous_ref=previous_ref,
            next_ref=next_ref,
            action_id=actions.COURSES_PAGE,
        ),
        severity=Severity.INFO,
    )


def course_empty_screen(*, workspace_label: str = "") -> Screen:
    body = "برای این فضای آموزشی هنوز درسی در فهرست قابل‌نمایش منتشر نشده است."
    if workspace_label:
        body = f"برای «{_text(workspace_label, 70)}» هنوز درسی در فهرست قابل‌نمایش منتشر نشده است."
    return Screen(
        identifier="academic.course.empty",
        title="📚 درس‌ها",
        intro=body,
        breadcrumb=_breadcrumbs("درس‌ها"),
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
        identifier="academic.course.unavailable",
        title="📚 درس در دسترس نیست",
        intro=message,
        breadcrumb=_breadcrumbs("درس‌ها"),
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
        identifier="academic.course.detail",
        title=f"📚 {title}",
        breadcrumb=_breadcrumbs("درس‌ها", title),
        intro="بخش موردنظر این درس را انتخاب کنید.",
        sections=(Section(facts=facts),) if facts else (),
        action_rows=_pack_actions(action_specs) + _nav_rows(actions.COURSES, "‹ درس‌ها"),
        severity=Severity.INFO,
    )


def schedule_hub_screen(
    *,
    course: Mapping[str, object] | None = None,
    upcoming_supported: bool = True,
) -> Screen:
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
        identifier="academic.schedule.hub",
        title="📅 برنامه" if not course_title else f"📅 برنامه · {course_title}",
        breadcrumb=(
            _breadcrumbs("درس‌ها", course_title, "برنامه")
            if course_title
            else _breadcrumbs("برنامه")
        ),
        intro="بازه موردنظر را انتخاب کنید. تاریخ و ساعت از منطقه زمانی فضای آموزشی می‌آید.",
        action_rows=_pack_actions(specs) + _nav_rows(back_id, "‹ بازگشت به درس", payload),
        severity=Severity.INFO,
    )


def _event_title(item: Mapping[str, object]) -> str:
    return _text(item.get("title") or item.get("course_title"), 100) or "رویداد آموزشی"


def _event_item(item: Mapping[str, object], timezone_name: str) -> ListItem:
    title = _event_title(item)
    course = _text(item.get("course_title"), 70)
    start = format_time(item.get("starts_at"), timezone_name)
    location = _text(item.get("location_text"), 80)
    description = course if course and course != title else ""
    meta = " · ".join(value for value in (start, location) if value)
    return ListItem(title=title, description=description, meta=meta)


def schedule_list_screen(
    items: Iterable[Mapping[str, object]],
    *,
    label: str,
    timezone_name: str,
    page: int = 1,
    previous_ref: str | None = None,
    next_ref: str | None = None,
    course: Mapping[str, object] | None = None,
    event_route_refs: Mapping[str, str] | None = None,
) -> Screen:
    visible = tuple(item for item in items if isinstance(item, Mapping))[:SCHEDULE_PAGE_SIZE]
    course_id = str((course or {}).get("course_id") or "")
    course_title = _course_title(course or {}) if course else ""
    event_route_refs = event_route_refs or {}

    list_items: list[ListItem] = []
    detail_actions: list[tuple[str, str, Mapping[str, object] | None]] = []
    for item in visible:
        list_items.append(_event_item(item, timezone_name))
        event_id = str(item.get("id") or item.get("event_id") or "")
        if not is_uuid(event_id):
            continue
        title = _event_title(item)
        route_ref = str(event_route_refs.get(event_id) or "")
        payload: Mapping[str, object] = (
            {"route_ref": route_ref} if route_ref else {"event_id": event_id}
        )
        detail_actions.append(
            (actions.SCHEDULE_EVENT_OPEN, f"جزئیات · {truncate_text(title, 24)}", payload)
        )

    intro = "" if list_items else f"برای {label} برنامه‌ای ثبت نشده است."
    payload = {"course_id": course_id} if is_uuid(course_id) else None
    back_id = actions.COURSE_SCHEDULE if payload else actions.SCHEDULE
    back_label = "‹ برنامه درس" if payload else "‹ برنامه"
    title = f"📅 {label}" if not course_title else f"📅 {label} · {course_title}"
    breadcrumb = (
        _breadcrumbs("درس‌ها", course_title, "برنامه", label)
        if course_title
        else _breadcrumbs("برنامه", label)
    )
    return Screen(
        identifier="academic.schedule.list",
        title=title,
        breadcrumb=breadcrumb,
        intro=intro,
        sections=(Section(items=tuple(list_items)),) if list_items else (),
        action_rows=_pack_actions(detail_actions) + _nav_rows(back_id, back_label, payload),
        pagination=_page(
            page=page,
            previous_ref=previous_ref,
            next_ref=next_ref,
            action_id=actions.SCHEDULE_PAGE,
        ),
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
        identifier="academic.schedule.event.detail",
        title=f"📅 {title}",
        breadcrumb=_breadcrumbs("برنامه", "جزئیات رویداد"),
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
        key = (
            str(item.get("course_id") or ""),
            _text(item.get("course_title"), 80) or "درس",
        )
        grouped.setdefault(key, []).append(item)

    sections: list[Section] = []
    for (_, course_title), course_items in grouped.items():
        grade_items = tuple(
            ListItem(
                title=_grade_title(item),
                description=_grade_value(item),
                meta="منتشرشده",
            )
            for item in course_items
        )
        sections.append(Section(title=course_title, items=grade_items))

    return Screen(
        identifier="academic.grades.list",
        title="🎓 نمرات",
        breadcrumb=_breadcrumbs("نمرات"),
        intro="" if sections else "نمره منتشرشده‌ای برای شما پیدا نشد.",
        sections=tuple(sections),
        action_rows=_nav_rows(),
        pagination=_page(
            page=page,
            previous_ref=previous_ref,
            next_ref=next_ref,
            action_id=actions.GRADES_PAGE,
        ),
        severity=Severity.INFO,
        footer="فقط نمره‌های منتشرشده نمایش داده می‌شوند. معدل یا میانگین در این بخش محاسبه نمی‌شود.",
    )


def course_grade_detail_screen(
    course: Mapping[str, object],
    items: Iterable[Mapping[str, object]],
) -> Screen:
    course_id = str(course.get("course_id") or "")
    if not is_uuid(course_id):
        return course_unavailable_screen()
    title = _course_title(course)
    visible = tuple(
        item for item in items if str(item.get("course_id") or "") == course_id
    )[:GRADE_PAGE_SIZE]
    grade_items = tuple(
        ListItem(
            title=_grade_title(item),
            description=_grade_value(item),
            meta="منتشرشده",
        )
        for item in visible
    )
    return Screen(
        identifier="academic.grades.course",
        title=f"🎓 نمرات · {title}",
        breadcrumb=_breadcrumbs("درس‌ها", title, "نمرات"),
        intro="" if grade_items else "نمره منتشرشده‌ای برای این درس پیدا نشد.",
        sections=(Section(items=grade_items),) if grade_items else (),
        action_rows=_nav_rows(
            actions.COURSE_OPEN,
            "‹ بازگشت به درس",
            {"course_id": course_id},
        ),
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
    detail_route_refs: Mapping[str, str] | None = None,
) -> Screen:
    visible = tuple(item for item in items if isinstance(item, Mapping))[:ANNOUNCEMENT_PAGE_SIZE]
    detail_route_refs = detail_route_refs or {}
    list_items: list[ListItem] = []
    detail_actions: list[tuple[str, str, Mapping[str, object] | None]] = []
    for item in visible:
        announcement_id = str(item.get("id") or item.get("announcement_id") or "")
        published = _announcement_time(item)
        read_label = "خوانده‌شده" if item.get("read_at") else ""
        meta = " · ".join(value for value in (published, read_label) if value)
        title = _text(item.get("title"), 100) or "اطلاعیه"
        list_items.append(ListItem(title=title, meta=meta))
        if not is_uuid(announcement_id):
            continue
        route_ref = str(detail_route_refs.get(announcement_id) or "")
        payload: Mapping[str, object] = (
            {"route_ref": route_ref}
            if route_ref
            else {"announcement_id": announcement_id}
        )
        detail_actions.append(
            (
                actions.ANNOUNCEMENT_OPEN,
                f"مشاهده · {truncate_text(title, 24)}",
                payload,
            )
        )
    return Screen(
        identifier="academic.announcements.list",
        title="📢 اطلاعیه‌ها",
        breadcrumb=_breadcrumbs("اطلاعیه‌ها"),
        intro="" if list_items else "اطلاعیه منتشرشده‌ای برای این فضای آموزشی پیدا نشد.",
        sections=(Section(items=tuple(list_items)),) if list_items else (),
        action_rows=_pack_actions(detail_actions) + _nav_rows(),
        pagination=_page(
            page=page,
            previous_ref=previous_ref,
            next_ref=next_ref,
            action_id=actions.ANNOUNCEMENTS_PAGE,
        ),
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
        rows += _pack_actions(
            ((actions.ANNOUNCEMENT_LINK_OPEN, "🔗 باز کردن پیوند", {"link_ref": safe_link_ref}),)
        )
    rows += _nav_rows(actions.ANNOUNCEMENTS, "‹ اطلاعیه‌ها")
    return Screen(
        identifier="academic.announcement.detail",
        title=f"📢 {title}",
        breadcrumb=_breadcrumbs("اطلاعیه‌ها", title),
        intro=body or "متنی برای این اطلاعیه ثبت نشده است.",
        sections=(Section(facts=tuple(facts)),) if facts else (),
        action_rows=rows,
        severity=Severity.INFO,
    )


def notifications_entry_screen() -> Screen:
    return Screen(
        identifier="academic.notifications.entry",
        title="🔔 اعلان‌های شخصی",
        breadcrumb=_breadcrumbs("اعلان‌های شخصی"),
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


def academic_error_screen(
    *,
    area_label: str,
    retry_action: str | None = None,
    back_action: str | None = None,
) -> Screen:
    specs: list[tuple[str, str, Mapping[str, object] | None]] = []
    if retry_action:
        specs.append((retry_action, "🔄 تلاش دوباره", None))
    rows = _pack_actions(specs) + _nav_rows(back_action)
    return Screen(
        identifier="academic.error",
        title=f"❌ {area_label}",
        intro="این بخش فعلاً بارگذاری نشد. دوباره تلاش کنید؛ اگر مشکل ادامه داشت از خانه مسیر را از نو باز کنید.",
        action_rows=rows,
        severity=Severity.ERROR,
    )


def academic_loading_screen(*, area_label: str) -> Screen:
    return Screen(
        identifier="academic.loading",
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
