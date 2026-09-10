"""Provider-neutral V3 screens for learning, protected delivery and commerce.

These builders consume already-authorized canonical projections and emit the
exact semantic primitives owned by bot-01/core. They perform no backend I/O,
provider send, assessment scoring, payment verification or entitlement mutation.
Identifiers may travel only as callback-correlation params and are never visible
product copy or authority.
"""

from __future__ import annotations

from collections.abc import Mapping, Sequence
from enum import Enum
from typing import Any
from urllib.parse import urlparse

from ..core import (
    Action,
    ActionRow,
    Breadcrumb,
    CallbackIntent,
    Context,
    EditPolicy,
    Fact,
    ListItem,
    Pagination,
    ProtectContent,
    Screen,
    Section,
    Severity,
)
from .intents import LearningIntent


_PERSIAN_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
_RESOURCE_TYPES = {
    "booklet": "جزوه",
    "note": "جزوه",
    "summary": "خلاصه",
    "question_bank": "بانک سؤال",
    "questions": "بانک سؤال",
    "past_exam": "آزمون سال‌های قبل",
    "reference": "رفرنس",
    "slides": "اسلاید",
    "slide": "اسلاید",
    "audio": "فایل صوتی",
    "video": "ویدئو",
    "document": "فایل آموزشی",
    "pdf": "PDF",
}
_ORDER_STATES = {
    "pending": "در انتظار",
    "created": "ثبت‌شده",
    "payment_pending": "در انتظار پرداخت",
    "paid": "پرداخت تأیید شده",
    "succeeded": "پرداخت تأیید شده",
    "failed": "ناموفق",
    "cancelled": "لغوشده",
    "canceled": "لغوشده",
    "expired": "منقضی‌شده",
}
_PAYMENT_STATES = {
    "pending": "در انتظار پرداخت",
    "payment_pending": "در انتظار پرداخت",
    "paid": "پرداخت تأیید شده",
    "succeeded": "پرداخت تأیید شده",
    "verified": "پرداخت تأیید شده",
    "failed": "پرداخت ناموفق",
    "cancelled": "پرداخت لغوشده",
    "canceled": "پرداخت لغوشده",
    "expired": "فرصت پرداخت منقضی شده",
}
_ACCESS_STATES = {
    "active": "دسترسی فعال",
    "granted": "دسترسی فعال",
    "expired": "دسترسی منقضی‌شده",
    "revoked": "دسترسی لغوشده",
    "denied": "دسترسی فعال نیست",
    "none": "دسترسی فعال نیست",
    "unknown": "وضعیت نامشخص",
}
_ASSESSMENT_STATES = {
    "active": "فعال",
    "open": "فعال",
    "upcoming": "پیش‌رو",
    "scheduled": "پیش‌رو",
    "completed": "تکمیل‌شده",
    "closed": "پایان‌یافته",
    "practice": "تمرینی",
    "past_exam": "آزمون گذشته",
}


class ProtectedDeliveryState(str, Enum):
    CHECKING = "checking"
    PREPARING = "preparing"
    READY = "ready"
    EXPIRED = "expired"
    DENIED = "denied"
    UNSUPPORTED_CHANNEL = "unsupported_channel"
    TEMPORARY_FAILURE = "temporary_failure"

    def __str__(self) -> str:
        return self.value


def _clean(value: Any, limit: int = 120) -> str:
    text = " ".join(str(value or "").split())
    if len(text) <= limit:
        return text
    return text[: max(1, limit - 1)].rstrip() + "…"


def _digits(value: Any) -> str:
    return str(value).translate(_PERSIAN_DIGITS)


def _https(url: Any) -> str | None:
    value = str(url or "").strip()
    if not value:
        return None
    parsed = urlparse(value)
    return value if parsed.scheme == "https" and bool(parsed.netloc) else None


def _money(amount_minor: Any, currency: Any) -> str:
    try:
        amount = int(amount_minor)
    except (TypeError, ValueError):
        return "نامشخص"
    code = str(currency or "").strip().upper()
    amount_text = f"{amount:,}".translate(_PERSIAN_DIGITS)
    if code == "IRR":
        return f"{amount_text} ریال"
    return f"{amount_text} {code}" if code else amount_text


def _resource_type(item: Mapping[str, Any]) -> str:
    key = str(
        item.get("type_key")
        or item.get("resource_type")
        or item.get("type")
        or item.get("kind")
        or ""
    ).strip().lower()
    return _RESOURCE_TYPES.get(key, _clean(key.replace("_", " "), 40) or "منبع آموزشی")


def _params(payload: Mapping[str, Any] | None) -> tuple[tuple[str, str], ...]:
    result: list[tuple[str, str]] = []
    for key, value in (payload or {}).items():
        rendered = str(value or "").strip()
        if rendered:
            result.append((str(key), rendered[:256]))
    return tuple(result)


def _action(
    label: str,
    intent: LearningIntent,
    *,
    payload: Mapping[str, Any] | None = None,
    url: str | None = None,
) -> Action:
    identifier = str(intent)
    if url is not None:
        return Action(identifier=identifier, label=label, url=url)
    return Action(
        identifier=identifier,
        label=label,
        intent=CallbackIntent(name=identifier, params=_params(payload)),
    )


def _row(*actions: Action) -> ActionRow:
    return ActionRow(actions=tuple(actions))


def _pair_rows(actions: Sequence[Action]) -> list[ActionRow]:
    return [_row(*actions[index : index + 2]) for index in range(0, len(actions), 2)]


def _fact(label: str, value: Any) -> Fact:
    return Fact(label=label, value=_clean(value, 160))


def _item(title: Any, *, description: str = "", meta: str = "", marker: str = "") -> ListItem:
    return ListItem(
        title=_clean(title, 105),
        description=_clean(description, 140),
        meta=_clean(meta, 90),
        marker=_clean(marker, 20),
    )


def _section(
    title: str,
    *,
    body: str = "",
    facts: Sequence[Fact] = (),
    items: Sequence[ListItem] = (),
) -> Section:
    return Section(title=title, body=body, facts=tuple(facts), items=tuple(items))


def _breadcrumbs(path: str) -> tuple[Breadcrumb, ...]:
    return tuple(Breadcrumb(label=part.strip()) for part in path.split("›") if part.strip())


def _pagination(
    *,
    page: int,
    previous_payload: Mapping[str, Any] | None,
    next_payload: Mapping[str, Any] | None,
    previous_intent: LearningIntent,
    next_intent: LearningIntent,
) -> Pagination:
    previous = (
        _action("‹ قبلی", previous_intent, payload=previous_payload)
        if previous_payload is not None
        else None
    )
    next_action = (
        _action("بعدی ›", next_intent, payload=next_payload)
        if next_payload is not None
        else None
    )
    return Pagination(
        page=max(1, page),
        previous=previous,
        next=next_action,
        label=f"صفحه {_digits(max(1, page))}",
    )


def _screen(
    *,
    identifier: str,
    title: str,
    intro: str = "",
    breadcrumb: str = "",
    context_label: str = "",
    context_value: str = "",
    sections: Sequence[Section] = (),
    rows: Sequence[ActionRow] = (),
    pagination: Pagination | None = None,
    severity: Severity = Severity.NEUTRAL,
    protect_content: bool = False,
    safe_edit: bool = True,
    footer: str = "",
) -> Screen:
    context = None
    if context_label and context_value:
        context = Context(label=context_label, value=context_value)
    return Screen(
        identifier=identifier,
        title=title,
        intro=intro,
        severity=severity,
        context=context,
        breadcrumb=_breadcrumbs(breadcrumb),
        sections=tuple(sections),
        action_rows=tuple(rows),
        pagination=pagination,
        footer=footer,
        protect_content=ProtectContent.REQUIRED if protect_content else ProtectContent.INHERIT,
        edit_policy=EditPolicy.EDIT_IF_SAFE if safe_edit else EditPolicy.SEND_NEW,
        rtl=True,
    )


def _back_home_rows(back_intent: LearningIntent) -> tuple[ActionRow, ...]:
    return (
        _row(
            _action("‹ بازگشت", back_intent),
            _action("🏠 خانه", LearningIntent.HOME),
        ),
    )


def resource_hub_screen(
    projection: Mapping[str, Any],
    *,
    page: int = 1,
    previous_cursor: str | None = None,
    next_cursor: str | None = None,
    course_label: str = "",
    type_label: str = "",
    recent: bool = False,
    canonical_courses: Sequence[Mapping[str, Any]] = (),
    canonical_types: Sequence[str] = (),
) -> Screen:
    """Build a bounded resource library from the authorized bot catalog."""
    raw_items = [item for item in projection.get("items", ()) if isinstance(item, Mapping)][:8]
    display: list[ListItem] = []
    open_actions: list[Action] = []
    for entry in raw_items:
        resource_id = str(entry.get("resource_id") or "")
        title = _clean(entry.get("title") or "منبع آموزشی", 105)
        course = _clean(entry.get("course_title"), 70)
        kind = _resource_type(entry)
        status = "قابل دریافت" if entry.get("delivery_supported") else "اطلاعات منبع"
        display.append(
            _item(
                title,
                description=" · ".join(value for value in (course, kind) if value),
                meta="نسخهٔ جاری مجاز" if entry.get("resource_version_id") else "",
                marker="🔒" if entry.get("delivery_supported") else "📄",
            )
        )
        if resource_id and len(open_actions) < 6:
            open_actions.append(
                _action(_clean(title, 30), LearningIntent.RESOURCE_OPEN, payload={"resource": resource_id})
            )

    active_filters = []
    if course_label:
        active_filters.append(f"درس: {course_label}")
    if type_label:
        active_filters.append(f"نوع: {type_label}")
    if recent:
        active_filters.append("تازه‌ترین‌ها")

    rows: list[ActionRow] = []
    filter_actions: list[Action] = []
    if canonical_courses:
        filter_actions.append(_action("درس", LearningIntent.RESOURCES_FILTER_COURSE))
    if canonical_types:
        filter_actions.append(_action("نوع", LearningIntent.RESOURCES_FILTER_TYPE))
    if filter_actions:
        rows.append(_row(*filter_actions[:2]))
    rows.append(_row(_action("تازه‌ها", LearningIntent.RESOURCES_RECENT)))
    rows.extend(_pair_rows(open_actions))
    rows.extend(_back_home_rows(LearningIntent.BACK))

    section = _section(
        "📚 کتابخانه",
        body=(
            "منبعی با این فیلترها پیدا نشد. فیلترها را تغییر دهید یا بعداً دوباره بررسی کنید."
            if not display
            else ""
        ),
        items=display,
    )
    pager = _pagination(
        page=page,
        previous_payload={"cursor": previous_cursor or ""} if previous_cursor is not None else None,
        next_payload={"cursor": next_cursor} if next_cursor else None,
        previous_intent=LearningIntent.RESOURCES_PAGE_PREVIOUS,
        next_intent=LearningIntent.RESOURCES_PAGE_NEXT,
    )
    return _screen(
        identifier="learning.resource_hub",
        title="📚 منابع و یادگیری",
        intro=" · ".join(active_filters) if active_filters else "منابع مجاز فضای آموزشی شما",
        sections=(section,),
        rows=rows,
        pagination=pager,
        footer="فقط اطلاعاتی نمایش داده می‌شود که دسترسی آن در backend تأیید شده است.",
    )


def resource_detail_screen(
    resource: Mapping[str, Any],
    *,
    canonical_protected_state: str | None = None,
    web_url: str | None = None,
) -> Screen:
    """Resource detail; raw IDs remain callback correlation only."""
    title = _clean(resource.get("title") or "منبع آموزشی", 110)
    resource_id = str(resource.get("resource_id") or "")
    version_id = str(resource.get("resource_version_id") or "")
    course = _clean(resource.get("course_title"), 80)
    facts = [
        _fact("نوع", _resource_type(resource)),
        _fact("نسخه", "نسخهٔ جاری مجاز" if version_id else "اطلاعات نسخه در دسترس نیست"),
        _fact("دسترسی", "فعال برای این حساب"),
    ]
    if course:
        facts.insert(0, _fact("درس", course))
    facts.append(
        _fact(
            "حفاظت",
            canonical_protected_state
            or "هنگام دریافت بر اساس سیاست منبع بررسی می‌شود",
        )
    )

    details = []
    topic = _clean(resource.get("topic"), 100)
    if topic:
        details.append(_fact("موضوع", topic))
    professor = _clean(resource.get("professor_name"), 80)
    if professor:
        details.append(_fact("استاد", professor))
    format_key = _clean(resource.get("format_key"), 40)
    if format_key:
        details.append(_fact("فرمت", format_key.upper() if format_key.isascii() else format_key))

    rows: list[ActionRow] = []
    if bool(resource.get("delivery_supported")) and resource_id:
        rows.append(
            _row(
                _action(
                    "🔒 دریافت امن",
                    LearningIntent.RESOURCE_DELIVER,
                    payload={"resource": resource_id, "version": version_id},
                )
            )
        )
    safe_web = _https(web_url)
    if safe_web:
        rows.append(_row(_action("🌐 مشاهده در فانوس", LearningIntent.RESOURCES_OPEN, url=safe_web)))
    rows.extend(_back_home_rows(LearningIntent.RESOURCES_OPEN))

    sections = [_section("جزئیات", facts=facts)]
    if details:
        sections.append(_section("اطلاعات تکمیلی", facts=details))
    return _screen(
        identifier="learning.resource_detail",
        title=f"📚 {title}",
        breadcrumb="منابع › جزئیات",
        intro=_clean(resource.get("description"), 500),
        sections=sections,
        rows=rows,
        footer=(
            "برای این منبع تحویل مستقیم در پیام‌رسان ارائه نشده است."
            if not resource.get("delivery_supported")
            else "مجوز دریافت هنگام اقدام دوباره در backend بررسی می‌شود."
        ),
    )


def protected_delivery_screen(
    state: ProtectedDeliveryState | str,
    *,
    resource_title: str = "این منبع",
    provider_label: str = "این پیام‌رسان",
    resource_id: str = "",
    job_ref: str = "",
    secure_web_url: str | None = None,
) -> Screen:
    """Complete protected-content screen-state family.

    resource/job references are transport correlation only and never authorization.
    """
    raw_state = state.value if isinstance(state, ProtectedDeliveryState) else str(state)
    try:
        resolved = ProtectedDeliveryState(raw_state)
    except ValueError:
        resolved = ProtectedDeliveryState.TEMPORARY_FAILURE

    resource_title = _clean(resource_title, 90) or "این منبع"
    safe_web = _https(secure_web_url)
    payload = {
        key: value
        for key, value in {"resource": resource_id, "job": job_ref}.items()
        if value
    }
    rows: list[ActionRow] = []
    severity = Severity.INFO
    protect = False
    safe_edit = True

    if resolved is ProtectedDeliveryState.CHECKING:
        title = "🔒 بررسی دسترسی"
        intro = f"دسترسی شما به «{resource_title}» در حال بررسی است."
        body = "این مرحله فقط وضعیت فعلی مجوز و دسترسی را از backend می‌گیرد."
        rows.append(_row(_action("🔄 بررسی دوباره", LearningIntent.PROTECTED_CHECK, payload=payload)))
    elif resolved is ProtectedDeliveryState.PREPARING:
        title = "🔒 آماده‌سازی نسخه محافظت‌شده"
        intro = f"نسخهٔ قابل ارسال «{resource_title}» هنوز آماده نشده است."
        body = "آماده‌سازی ادامه دارد؛ درصد یا زمان پایان تا وقتی اندازه‌گیری واقعی وجود نداشته باشد نمایش داده نمی‌شود."
        rows.append(_row(_action("🔄 بررسی آماده‌شدن", LearningIntent.PROTECTED_REFRESH, payload=payload)))
    elif resolved is ProtectedDeliveryState.READY:
        title = "✅ نسخه محافظت‌شده آماده است"
        intro = f"«{resource_title}» برای تحویل امن آماده است."
        body = "ارسال فقط با سیاست حفاظتی تأییدشده و مجوز فعلی انجام می‌شود."
        severity = Severity.SUCCESS
        protect = True
        safe_edit = False
    elif resolved is ProtectedDeliveryState.EXPIRED:
        title = "⚠️ درخواست دریافت منقضی شده"
        intro = "این درخواست دیگر معتبر نیست."
        body = "از جزئیات منبع یک درخواست دریافت تازه شروع کنید؛ مسیر منقضی دوباره استفاده نمی‌شود."
        severity = Severity.WARNING
        rows.append(_row(_action("📚 بازگشت به منبع", LearningIntent.PROTECTED_OPEN_RESOURCE, payload=payload)))
    elif resolved is ProtectedDeliveryState.DENIED:
        title = "🔒 دسترسی به این منبع فعال نیست"
        intro = f"backend دریافت «{resource_title}» را برای وضعیت فعلی حساب تأیید نکرد."
        body = "اگر اخیراً خرید یا دسترسی شما تغییر کرده است، وضعیت را از بخش خرید و دسترسی بررسی کنید."
        severity = Severity.ERROR
        rows.append(_row(_action("💳 خرید و دسترسی", LearningIntent.COMMERCE_OPEN)))
        if safe_web:
            rows.append(_row(_action("🌐 بررسی در فانوس", LearningIntent.COMMERCE_OPEN_WEB, url=safe_web)))
    elif resolved is ProtectedDeliveryState.UNSUPPORTED_CHANNEL:
        title = "⚠️ ارسال محافظت‌شده در این پیام‌رسان ممکن نیست"
        intro = f"«{resource_title}» به حفاظتی نیاز دارد که در {provider_label} تأیید نشده است."
        body = "نسخهٔ بدون حفاظت ارسال نمی‌شود. این محدودیت امنیتی عمداً fail-closed است."
        severity = Severity.WARNING
        if safe_web:
            rows.append(_row(_action("🌐 دریافت امن در فانوس", LearningIntent.PROTECTED_OPEN_RESOURCE, url=safe_web)))
    else:
        title = "❌ دریافت موقتاً انجام نشد"
        intro = "سرویس دریافت امن موقتاً پاسخ قابل اتکا نداد."
        body = "کمی بعد دوباره تلاش کنید. درخواست قبلی به‌عنوان موفق نمایش داده نمی‌شود."
        severity = Severity.ERROR
        rows.append(_row(_action("🔄 تلاش دوباره", LearningIntent.PROTECTED_RETRY, payload=payload)))

    rows.extend(_back_home_rows(LearningIntent.RESOURCES_OPEN))
    return _screen(
        identifier=f"learning.protected.{resolved.value}",
        title=title,
        intro=intro,
        sections=(_section("وضعیت", body=body),),
        rows=rows,
        severity=severity,
        protect_content=protect,
        safe_edit=safe_edit,
    )


def assessment_hub_screen(
    assessments: Sequence[Mapping[str, Any]] | None,
    *,
    course_label: str = "",
    web_url: str | None = None,
) -> Screen:
    """Assessment hub. `None` explicitly means no bot-safe projection."""
    safe_web = _https(web_url)
    rows: list[ActionRow] = []
    sections: list[Section] = []

    if assessments is None:
        sections.extend(
            (
                _section(
                    "📝 وضعیت ربات",
                    body="فهرست و تلاش آزمون هنوز projection امن و اختصاصی ربات ندارد؛ پاسخ و امتیاز محلی ساخته نمی‌شود.",
                ),
                _section(
                    "🌐 ادامه در فانوس",
                    body="فهرست آزمون‌ها، شروع یا ادامهٔ تلاش و نتیجه از رابط وب canonical انجام می‌شود.",
                ),
            )
        )
        if safe_web:
            rows.append(_row(_action("🌐 باز کردن آزمون‌ها", LearningIntent.ASSESSMENT_OPEN_WEB, url=safe_web)))
        severity = Severity.WARNING
    else:
        groups: dict[str, list[ListItem]] = {
            "active": [], "upcoming": [], "completed": [], "practice": []
        }
        open_actions: list[Action] = []
        for assessment in assessments[:12]:
            state = str(assessment.get("state") or assessment.get("status") or "").lower()
            kind = str(assessment.get("kind") or assessment.get("type") or "").lower()
            bucket = "practice" if kind in {"practice", "past_exam"} else state
            if bucket not in groups:
                bucket = "completed" if state in {"closed", "completed"} else "upcoming"
            assessment_id = str(assessment.get("assessment_id") or assessment.get("id") or "")
            course = _clean(assessment.get("course_title"), 70)
            deadline = _clean(assessment.get("deadline") or assessment.get("ends_at"), 60)
            groups[bucket].append(
                _item(
                    assessment.get("title") or "آزمون",
                    description=course,
                    meta=f"مهلت: {deadline}" if deadline else "",
                    marker=_ASSESSMENT_STATES.get(state, "📝"),
                )
            )
            if assessment_id and len(open_actions) < 6:
                open_actions.append(
                    _action(
                        _clean(assessment.get("title") or "مشاهده آزمون", 28),
                        LearningIntent.ASSESSMENT_OPEN,
                        payload={"assessment": assessment_id},
                    )
                )
        labels = (
            ("active", "فعال"),
            ("upcoming", "پیش‌رو"),
            ("practice", "تمرینی / آزمون‌های گذشته"),
            ("completed", "پایان‌یافته"),
        )
        for key, label in labels:
            if groups[key]:
                sections.append(_section(label, items=groups[key][:6]))
        if not sections:
            sections.append(_section("📝 آزمون‌ها", body="در حال حاضر آزمونی برای نمایش وجود ندارد."))
        rows.append(
            _row(
                _action("درس", LearningIntent.ASSESSMENTS_FILTER_COURSE),
                _action("وضعیت", LearningIntent.ASSESSMENTS_FILTER_STATE),
            )
        )
        rows.extend(_pair_rows(open_actions))
        if safe_web:
            rows.append(_row(_action("🌐 همه آزمون‌ها در فانوس", LearningIntent.ASSESSMENT_OPEN_WEB, url=safe_web)))
        severity = Severity.NEUTRAL

    rows.extend(_back_home_rows(LearningIntent.BACK))
    return _screen(
        identifier="learning.assessment_hub",
        title="📝 آزمون‌ها",
        intro=f"درس: {course_label}" if course_label else "وضعیت آزمون‌ها و مسیر امن ادامه",
        sections=sections[:3],
        rows=rows,
        severity=severity,
    )


def assessment_detail_screen(
    assessment: Mapping[str, Any],
    *,
    web_url: str | None = None,
) -> Screen:
    """Metadata only: no question, answer-key, scoring or attempt authority."""
    title = _clean(assessment.get("title") or "آزمون", 110)
    state_key = str(assessment.get("state") or assessment.get("status") or "unknown").lower()
    facts = []
    course = _clean(assessment.get("course_title"), 80)
    if course:
        facts.append(_fact("درس", course))
    deadline = _clean(assessment.get("deadline") or assessment.get("ends_at"), 80)
    if deadline:
        facts.append(_fact("مهلت", deadline))
    facts.append(_fact("وضعیت", _ASSESSMENT_STATES.get(state_key, "وضعیت نامشخص")))

    rows: list[ActionRow] = []
    safe_web = _https(web_url)
    if safe_web:
        label = "🌐 ادامه آزمون در فانوس" if state_key in {"active", "open"} else "🌐 مشاهده در فانوس"
        rows.append(_row(_action(label, LearningIntent.ASSESSMENT_OPEN_WEB, url=safe_web)))
    rows.extend(_back_home_rows(LearningIntent.ASSESSMENTS_OPEN))
    return _screen(
        identifier="learning.assessment_detail",
        title=f"📝 {title}",
        breadcrumb="آزمون‌ها › جزئیات",
        intro="تلاش داخل ربات فقط پس از اضافه‌شدن قرارداد bot-safe canonical فعال می‌شود.",
        sections=(_section("وضعیت آزمون", facts=facts),),
        rows=rows,
        footer="پاسخ‌ها و امتیازدهی همیشه تحت اختیار backend باقی می‌مانند.",
    )


def commerce_hub_screen(
    *,
    order_summary: Sequence[Mapping[str, Any]] | None = None,
    access_summary: Sequence[Mapping[str, Any]] | None = None,
    catalog: Sequence[Mapping[str, Any]] | None = None,
    web_url: str | None = None,
) -> Screen:
    """Human order/access center without a raw product-id purchase path."""
    safe_web = _https(web_url)
    sections: list[Section] = []
    rows: list[ActionRow] = []

    if order_summary is None:
        sections.append(_section("سفارش‌ها", body="فهرست سفارش‌های حساب در bot-safe API فعلی ارائه نشده است."))
    else:
        order_items: list[ListItem] = []
        order_actions: list[Action] = []
        for order in order_summary[:5]:
            order_id = str(order.get("order_id") or order.get("id") or "")
            status = _ORDER_STATES.get(str(order.get("status") or "").lower(), "وضعیت نامشخص")
            title = _clean(order.get("title") or "سفارش فانوس", 100)
            order_items.append(
                _item(
                    title,
                    description=_money(order.get("amount_minor"), order.get("currency")),
                    marker=status,
                )
            )
            if order_id and len(order_actions) < 4:
                order_actions.append(
                    _action(_clean(title, 28), LearningIntent.ORDER_OPEN, payload={"order": order_id})
                )
        sections.append(_section("سفارش‌ها", body="سفارشی برای نمایش وجود ندارد." if not order_items else "", items=order_items))
        rows.extend(_pair_rows(order_actions))

    if access_summary is None:
        sections.append(_section("دسترسی‌ها", body="فهرست کامل دسترسی‌های حساب هنوز projection مستقل ربات ندارد."))
    else:
        access_items = []
        for access in access_summary[:5]:
            state = str(access.get("state") or access.get("status") or "unknown").lower()
            access_items.append(
                _item(
                    access.get("title") or "دسترسی",
                    marker=_ACCESS_STATES.get(state, "وضعیت نامشخص"),
                )
            )
        sections.append(_section("دسترسی‌ها", body="دسترسی فعالی برای نمایش وجود ندارد." if not access_items else "", items=access_items))

    if catalog is None:
        sections.append(_section("خرید", body="کاتالوگ bot-safe در قرارداد فعلی وجود ندارد؛ شناسهٔ محصول از کاربر درخواست نمی‌شود."))
    elif catalog:
        sections.append(
            _section(
                "قابل تهیه",
                items=[
                    _item(
                        product.get("title") or "محصول",
                        description=_money(product.get("amount_minor"), product.get("currency")),
                    )
                    for product in catalog[:5]
                ],
            )
        )

    if safe_web:
        rows.append(_row(_action("🌐 خرید و دسترسی در فانوس", LearningIntent.COMMERCE_OPEN_WEB, url=safe_web)))
    rows.extend(_back_home_rows(LearningIntent.BACK))
    return _screen(
        identifier="learning.commerce_hub",
        title="💳 خرید و دسترسی",
        intro="پرداخت، سفارش و دسترسی سه وضعیت مستقل‌اند.",
        sections=sections[:3],
        rows=rows,
        footer="موفقیت صفحهٔ پرداخت به‌تنهایی دسترسی ایجاد نمی‌کند.",
    )


def order_access_detail_screen(
    order: Mapping[str, Any],
    *,
    payment_status: str | None = None,
    entitlement_status: str | None = None,
    payment_url: str | None = None,
    web_url: str | None = None,
) -> Screen:
    """Render order, payment and entitlement as intentionally separate facts."""
    raw_order_state = str(order.get("status") or "").lower()
    order_state = _ORDER_STATES.get(raw_order_state, "وضعیت نامشخص")
    payment_label = (
        "در projection فعلی جداگانه گزارش نشده"
        if payment_status is None
        else _PAYMENT_STATES.get(str(payment_status).lower(), "وضعیت نامشخص")
    )

    entitlement = order.get("entitlement") if isinstance(order.get("entitlement"), Mapping) else {}
    if entitlement_status is not None:
        access_label = _ACCESS_STATES.get(str(entitlement_status).lower(), "وضعیت نامشخص")
    elif entitlement.get("granted") is True:
        access_label = "دسترسی فعال"
    elif entitlement.get("granted") is False:
        access_label = "دسترسی فعال نیست"
    else:
        access_label = "وضعیت نامشخص"

    facts = (
        _fact("وضعیت سفارش", order_state),
        _fact("وضعیت پرداخت", payment_label),
        _fact("دسترسی", access_label),
        _fact("مبلغ", _money(order.get("amount_minor"), order.get("currency"))),
    )
    rows: list[ActionRow] = []
    safe_payment = _https(payment_url)
    safe_web = _https(web_url)
    if safe_payment:
        rows.append(_row(_action("💳 ادامه پرداخت", LearningIntent.PAYMENT_OPEN_WEB, url=safe_payment)))
    elif raw_order_state in {"failed", "cancelled", "canceled", "expired"} and safe_web:
        rows.append(_row(_action("🌐 تلاش دوباره در فانوس", LearningIntent.PAYMENT_RETRY_WEB, url=safe_web)))
    rows.append(_row(_action("🔄 تازه‌سازی وضعیت", LearningIntent.ORDER_REFRESH)))
    if safe_web:
        rows.append(_row(_action("🌐 جزئیات در فانوس", LearningIntent.COMMERCE_OPEN_WEB, url=safe_web)))
    rows.extend(_back_home_rows(LearningIntent.COMMERCE_OPEN))

    severity = Severity.SUCCESS if access_label == "دسترسی فعال" else (
        Severity.ERROR if raw_order_state == "failed" else Severity.INFO
    )
    return _screen(
        identifier="learning.order_access_detail",
        title=f"💳 {_clean(order.get('title') or 'وضعیت سفارش', 100)}",
        breadcrumb="خرید و دسترسی › سفارش",
        sections=(_section("وضعیت", facts=facts),),
        rows=rows,
        severity=severity,
        footer="پرداخت تأییدشده فقط وقتی به معنی دسترسی فعال است که entitlement نیز آن را تأیید کند.",
    )


def forms_hub_screen(
    forms: Sequence[Mapping[str, Any]] | None,
    *,
    web_url: str | None = None,
) -> Screen:
    """Forms list only with a bot-safe projection; otherwise safe web handoff."""
    safe_web = _https(web_url)
    rows: list[ActionRow] = []
    if forms is None:
        section = _section(
            "فرم‌ها و خدمات",
            body="فرم‌های فعال در API وب canonical هستند، اما projection امن اختصاصی ربات در قرارداد فعلی وجود ندارد.",
        )
        severity = Severity.WARNING
    else:
        items: list[ListItem] = []
        form_actions: list[Action] = []
        for form in forms[:8]:
            form_id = str(form.get("form_id") or form.get("id") or "")
            title = _clean(form.get("title") or "فرم", 100)
            items.append(
                _item(
                    title,
                    description=_clean(form.get("description"), 90),
                    marker=_clean(form.get("state") or form.get("status"), 40),
                )
            )
            if form_id and len(form_actions) < 6:
                form_actions.append(_action(_clean(title, 28), LearningIntent.FORM_OPEN, payload={"form": form_id}))
        section = _section("فرم‌های فعال", body="فرم فعالی برای نمایش وجود ندارد." if not items else "", items=items)
        rows.extend(_pair_rows(form_actions))
        severity = Severity.NEUTRAL
    if safe_web:
        rows.append(_row(_action("🌐 باز کردن فرم‌ها در فانوس", LearningIntent.FORM_OPEN_WEB, url=safe_web)))
    rows.extend(_back_home_rows(LearningIntent.BACK))
    return _screen(
        identifier="learning.forms_hub",
        title="📝 فرم‌ها و خدمات",
        intro="ثبت پاسخ فقط از مسیر canonical انجام می‌شود.",
        sections=(section,),
        rows=rows,
        severity=severity,
    )


def form_detail_screen(
    form: Mapping[str, Any],
    *,
    web_url: str | None = None,
) -> Screen:
    safe_web = _https(web_url)
    facts = []
    status = _clean(form.get("state") or form.get("status"), 50)
    if status:
        facts.append(_fact("وضعیت", status))
    deadline = _clean(form.get("deadline") or form.get("closes_at"), 80)
    if deadline:
        facts.append(_fact("مهلت", deadline))
    rows: list[ActionRow] = []
    if safe_web:
        rows.append(_row(_action("🌐 تکمیل در فانوس", LearningIntent.FORM_OPEN_WEB, url=safe_web)))
    rows.extend(_back_home_rows(LearningIntent.FORMS_OPEN))
    return _screen(
        identifier="learning.form_detail",
        title=f"📝 {_clean(form.get('title') or 'فرم', 105)}",
        breadcrumb="فرم‌ها و خدمات › جزئیات",
        intro=_clean(form.get("description"), 450),
        sections=(_section("جزئیات", facts=facts) if facts else _section("جزئیات", body="اطلاعات تکمیلی ثبت نشده است."),),
        rows=rows,
        footer="ارسال داخل ربات فقط پس از قرارداد bot-safe صریح فعال می‌شود.",
    )


def domain_state_screen(
    domain: str,
    *,
    state: str,
    recoverable: bool = True,
    web_url: str | None = None,
) -> Screen:
    """Shared empty/error/denied/unavailable family for every owned domain."""
    domain_labels = {
        "resources": ("📚 منابع و یادگیری", LearningIntent.RESOURCES_OPEN),
        "assessments": ("📝 آزمون‌ها", LearningIntent.ASSESSMENTS_OPEN),
        "commerce": ("💳 خرید و دسترسی", LearningIntent.COMMERCE_OPEN),
        "forms": ("📝 فرم‌ها و خدمات", LearningIntent.FORMS_OPEN),
    }
    title, back_intent = domain_labels.get(domain, ("ℹ️ فانوس", LearningIntent.BACK))
    requested = str(state or "error").lower()
    state_key = requested if requested in {"empty", "denied", "unavailable", "error"} else "error"

    if state_key == "empty":
        intro = "در حال حاضر موردی برای نمایش وجود ندارد."
        body = "این وضعیت می‌تواند طبیعی باشد؛ بعداً دوباره بررسی کنید یا به بخش قبلی برگردید."
        severity = Severity.INFO
    elif state_key == "denied":
        intro = "اجازه مشاهدهٔ این بخش برای وضعیت فعلی حساب تأیید نشد."
        body = "فضای آموزشی و دسترسی حساب را بررسی کنید. جزئیات امنیتی نمایش داده نمی‌شود."
        severity = Severity.ERROR
    elif state_key == "unavailable":
        intro = "این قابلیت در این کانال در دسترس نیست."
        body = "اگر مسیر امن دیگری وجود داشته باشد، از دکمهٔ فانوس استفاده کنید."
        severity = Severity.WARNING
    else:
        intro = "این بخش موقتاً بارگذاری نشد."
        body = "دادهٔ قبلی به‌عنوان وضعیت تازه نمایش داده نمی‌شود."
        severity = Severity.ERROR

    rows: list[ActionRow] = []
    if recoverable and state_key != "denied":
        rows.append(_row(_action("🔄 تلاش دوباره", back_intent)))
    safe_web = _https(web_url)
    if safe_web:
        web_intent = {
            "assessments": LearningIntent.ASSESSMENT_OPEN_WEB,
            "commerce": LearningIntent.COMMERCE_OPEN_WEB,
            "forms": LearningIntent.FORM_OPEN_WEB,
        }.get(domain, LearningIntent.RESOURCES_OPEN)
        rows.append(_row(_action("🌐 باز کردن فانوس", web_intent, url=safe_web)))
    rows.extend(_back_home_rows(back_intent))
    return _screen(
        identifier=f"learning.{domain if domain in domain_labels else 'generic'}.{state_key}",
        title=title,
        intro=intro,
        sections=(_section("گام بعدی", body=body),),
        rows=rows,
        severity=severity,
    )
