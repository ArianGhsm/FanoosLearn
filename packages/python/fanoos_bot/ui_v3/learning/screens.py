"""Provider-neutral V3 screens for learning, protected delivery and commerce.

The builders in this module are deliberately presentation-only. They consume
already-authorized canonical projections and emit bot-01 semantic primitives.
They never call providers, score assessments, infer payment success, grant access,
or turn an identifier/capability into authority.

bot-01/core is a parallel dependency defined by the Design Lock. Constructor
usage is intentionally concentrated in the small helpers below so integration can
reconcile exact core signatures without changing domain copy or business meaning.
"""

from __future__ import annotations

from collections.abc import Mapping, Sequence
from enum import StrEnum
from typing import Any
from urllib.parse import urlparse

from ..core import (
    Action,
    ActionRow,
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


class ProtectedDeliveryState(StrEnum):
    CHECKING = "checking"
    PREPARING = "preparing"
    READY = "ready"
    EXPIRED = "expired"
    DENIED = "denied"
    UNSUPPORTED_CHANNEL = "unsupported_channel"
    TEMPORARY_FAILURE = "temporary_failure"


def _enum_value(enum_type: Any, value: str, *names: str) -> Any:
    """Resolve a Design-Lock enum without inventing a fallback authority."""
    for name in names:
        candidate = getattr(enum_type, name, None)
        if candidate is not None:
            return candidate
    try:
        return enum_type(value)
    except Exception:
        return value


def _severity(value: str) -> Any:
    names = {
        "info": ("INFO",),
        "success": ("SUCCESS",),
        "warning": ("WARNING", "WARN"),
        "danger": ("DANGER", "ERROR"),
    }
    return _enum_value(Severity, value, *names[value])


def _protect(required: bool) -> Any:
    if required:
        return _enum_value(ProtectContent, "required", "REQUIRED", "PROTECTED")
    return _enum_value(ProtectContent, "none", "NONE", "DEFAULT")


def _edit(safe: bool = True) -> Any:
    if safe:
        return _enum_value(EditPolicy, "safe", "SAFE", "PREFER_EDIT")
    return _enum_value(EditPolicy, "new_message", "NEW_MESSAGE", "NEVER_EDIT")


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
    if not code:
        return amount_text
    return f"{amount_text} {code}"


def _resource_type(item: Mapping[str, Any]) -> str:
    key = str(
        item.get("type_key")
        or item.get("resource_type")
        or item.get("type")
        or item.get("kind")
        or ""
    ).strip().lower()
    return _RESOURCE_TYPES.get(key, _clean(key.replace("_", " "), 40) or "منبع آموزشی")


def _action(
    label: str,
    intent: LearningIntent,
    *,
    payload: Mapping[str, Any] | None = None,
    url: str | None = None,
    emphasis: str = "secondary",
) -> Action:
    return Action(
        label=label,
        intent=str(intent),
        payload=dict(payload or {}),
        url=url,
        emphasis=emphasis,
    )


def _row(*actions: Action) -> ActionRow:
    return ActionRow(actions=tuple(actions))


def _fact(label: str, value: Any) -> Fact:
    return Fact(label=label, value=_clean(value, 160))


def _item(
    title: str,
    *,
    subtitle: str = "",
    meta: str = "",
    status: str = "",
    intent: LearningIntent | None = None,
    payload: Mapping[str, Any] | None = None,
) -> ListItem:
    return ListItem(
        title=_clean(title, 105),
        subtitle=_clean(subtitle, 120),
        meta=_clean(meta, 80),
        status=_clean(status, 50),
        intent=str(intent) if intent else None,
        payload=dict(payload or {}),
    )


def _section(
    title: str,
    *,
    body: str = "",
    facts: Sequence[Fact] = (),
    items: Sequence[ListItem] = (),
) -> Section:
    return Section(
        title=title,
        body=body,
        facts=tuple(facts),
        items=tuple(items),
    )


def _context(breadcrumb: str = "", label: str = "") -> Context | None:
    if not breadcrumb and not label:
        return None
    return Context(breadcrumb=breadcrumb, label=label)


def _pagination(
    *,
    page: int,
    previous_payload: Mapping[str, Any] | None,
    next_payload: Mapping[str, Any] | None,
    previous_intent: LearningIntent,
    next_intent: LearningIntent,
) -> Pagination:
    return Pagination(
        label=f"صفحه {_digits(max(1, page))}",
        previous_action=(
            _action("‹ قبلی", previous_intent, payload=previous_payload)
            if previous_payload is not None
            else None
        ),
        next_action=(
            _action("بعدی ›", next_intent, payload=next_payload)
            if next_payload is not None
            else None
        ),
    )


def _screen(
    *,
    title: str,
    semantic_kind: str,
    intro: str = "",
    breadcrumb: str = "",
    context_label: str = "",
    sections: Sequence[Section] = (),
    rows: Sequence[ActionRow] = (),
    pagination: Pagination | None = None,
    severity: str = "info",
    protect_content: bool = False,
    safe_edit: bool = True,
    footer: str = "",
) -> Screen:
    return Screen(
        title=title,
        semantic_kind=semantic_kind,
        context=_context(breadcrumb, context_label),
        intro=intro,
        sections=tuple(sections),
        action_rows=tuple(rows),
        pagination=pagination,
        severity=_severity(severity),
        protect_content=_protect(protect_content),
        edit_policy=_edit(safe_edit),
        footer=footer,
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
    """Build a bounded resource library from the authorized bot catalog projection."""
    raw_items = [item for item in projection.get("items", ()) if isinstance(item, Mapping)]
    items: list[ListItem] = []
    for entry in raw_items[:8]:
        resource_id = str(entry.get("resource_id") or "")
        title = _clean(entry.get("title") or "منبع آموزشی", 105)
        course = _clean(entry.get("course_title"), 70)
        kind = _resource_type(entry)
        version_note = "نسخهٔ جاری مجاز" if entry.get("resource_version_id") else ""
        items.append(
            _item(
                title,
                subtitle=" · ".join(value for value in (course, kind) if value),
                meta=version_note,
                status="قابل دریافت" if entry.get("delivery_supported") else "اطلاعات منبع",
                intent=LearningIntent.RESOURCE_OPEN if resource_id else None,
                payload={"resource_id": resource_id} if resource_id else None,
            )
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
    rows.extend(_back_home_rows(LearningIntent.BACK))

    section = _section(
        "📚 کتابخانه",
        body=(
            "منبعی با این فیلترها پیدا نشد. فیلترها را تغییر دهید یا بعداً دوباره بررسی کنید."
            if not items
            else ""
        ),
        items=items,
    )
    pager = _pagination(
        page=page,
        previous_payload={"cursor": previous_cursor} if previous_cursor is not None else None,
        next_payload={"cursor": next_cursor} if next_cursor else None,
        previous_intent=LearningIntent.RESOURCES_PAGE_PREVIOUS,
        next_intent=LearningIntent.RESOURCES_PAGE_NEXT,
    )
    return _screen(
        title="📚 منابع و یادگیری",
        semantic_kind="learning.resource_hub",
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
    """Resource detail; raw IDs are action payloads only and never display copy."""
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
    if canonical_protected_state:
        facts.append(_fact("حفاظت", canonical_protected_state))
    else:
        facts.append(_fact("حفاظت", "هنگام دریافت بر اساس سیاست منبع بررسی می‌شود"))

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
                    payload={"resource_id": resource_id, "resource_version_id": version_id},
                    emphasis="primary",
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
        title=f"📚 {title}",
        semantic_kind="learning.resource_detail",
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

    job_ref/resource_id are correlation/action inputs only. They must never be
    rendered and never substitute for backend authorization.
    """
    try:
        resolved = ProtectedDeliveryState(str(state))
    except ValueError:
        resolved = ProtectedDeliveryState.TEMPORARY_FAILURE

    resource_title = _clean(resource_title, 90) or "این منبع"
    safe_web = _https(secure_web_url)
    payload = {key: value for key, value in {"resource_id": resource_id, "job_ref": job_ref}.items() if value}
    rows: list[ActionRow] = []
    severity = "info"
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
        severity = "success"
        protect = True
        safe_edit = False
    elif resolved is ProtectedDeliveryState.EXPIRED:
        title = "⚠️ درخواست دریافت منقضی شده"
        intro = "این درخواست دیگر معتبر نیست."
        body = "از جزئیات منبع یک درخواست دریافت تازه شروع کنید؛ مسیر منقضی دوباره استفاده نمی‌شود."
        severity = "warning"
        rows.append(_row(_action("📚 بازگشت به منبع", LearningIntent.PROTECTED_OPEN_RESOURCE, payload=payload)))
    elif resolved is ProtectedDeliveryState.DENIED:
        title = "🔒 دسترسی به این منبع فعال نیست"
        intro = f"backend دریافت «{resource_title}» را برای وضعیت فعلی حساب تأیید نکرد."
        body = "اگر اخیراً خرید یا دسترسی شما تغییر کرده است، وضعیت را از بخش خرید و دسترسی بررسی کنید."
        severity = "danger"
        rows.append(_row(_action("💳 خرید و دسترسی", LearningIntent.COMMERCE_OPEN)))
        if safe_web:
            rows.append(_row(_action("🌐 بررسی در فانوس", LearningIntent.COMMERCE_OPEN_WEB, url=safe_web)))
    elif resolved is ProtectedDeliveryState.UNSUPPORTED_CHANNEL:
        title = "⚠️ ارسال محافظت‌شده در این پیام‌رسان ممکن نیست"
        intro = f"«{resource_title}» به حفاظتی نیاز دارد که در {provider_label} تأیید نشده است."
        body = "نسخهٔ بدون حفاظت ارسال نمی‌شود. این محدودیت امنیتی عمداً fail-closed است."
        severity = "warning"
        if safe_web:
            rows.append(_row(_action("🌐 دریافت امن در فانوس", LearningIntent.PROTECTED_OPEN_RESOURCE, url=safe_web, emphasis="primary")))
    else:
        title = "❌ دریافت موقتاً انجام نشد"
        intro = "سرویس دریافت امن موقتاً پاسخ قابل اتکا نداد."
        body = "کمی بعد دوباره تلاش کنید. درخواست قبلی به‌عنوان موفق نمایش داده نمی‌شود."
        severity = "danger"
        rows.append(_row(_action("🔄 تلاش دوباره", LearningIntent.PROTECTED_RETRY, payload=payload)))

    rows.extend(_back_home_rows(LearningIntent.RESOURCES_OPEN))
    return _screen(
        title=title,
        semantic_kind=f"learning.protected.{resolved.value}",
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
    """Assessment destination; `None` explicitly means no bot-safe projection."""
    safe_web = _https(web_url)
    rows: list[ActionRow] = []
    sections: list[Section] = []

    if assessments is None:
        sections.append(
            _section(
                "📝 وضعیت ربات",
                body="فهرست و تلاش آزمون هنوز projection امن و اختصاصی ربات ندارد؛ پاسخ و امتیاز محلی ساخته نمی‌شود.",
            )
        )
        sections.append(
            _section(
                "🌐 ادامه در فانوس",
                body="فهرست آزمون‌ها، شروع یا ادامهٔ تلاش و نتیجه از رابط وب canonical انجام می‌شود.",
            )
        )
        if safe_web:
            rows.append(_row(_action("🌐 باز کردن آزمون‌ها", LearningIntent.ASSESSMENT_OPEN_WEB, url=safe_web, emphasis="primary")))
    else:
        groups: dict[str, list[ListItem]] = {"active": [], "upcoming": [], "completed": [], "practice": []}
        for assessment in assessments[:12]:
            state = str(assessment.get("state") or assessment.get("status") or "").lower()
            kind = str(assessment.get("kind") or assessment.get("type") or "").lower()
            bucket = "practice" if kind in {"practice", "past_exam"} else state
            if bucket not in groups:
                bucket = "completed" if state in {"closed", "completed"} else "upcoming"
            aid = str(assessment.get("assessment_id") or assessment.get("id") or "")
            course = _clean(assessment.get("course_title"), 70)
            deadline = _clean(assessment.get("deadline") or assessment.get("ends_at"), 60)
            groups[bucket].append(
                _item(
                    assessment.get("title") or "آزمون",
                    subtitle=course,
                    meta=f"مهلت: {deadline}" if deadline else "",
                    status=_ASSESSMENT_STATES.get(state, _clean(state, 30)),
                    intent=LearningIntent.ASSESSMENT_OPEN if aid else None,
                    payload={"assessment_id": aid} if aid else None,
                )
            )
        labels = (("active", "فعال"), ("upcoming", "پیش‌رو"), ("practice", "تمرینی / آزمون‌های گذشته"), ("completed", "پایان‌یافته"))
        for key, label in labels:
            if groups[key]:
                sections.append(_section(label, items=groups[key][:6]))
        if not sections:
            sections.append(_section("📝 آزمون‌ها", body="در حال حاضر آزمونی برای نمایش وجود ندارد."))
        rows.append(_row(_action("درس", LearningIntent.ASSESSMENTS_FILTER_COURSE), _action("وضعیت", LearningIntent.ASSESSMENTS_FILTER_STATE)))
        if safe_web:
            rows.append(_row(_action("🌐 همه آزمون‌ها در فانوس", LearningIntent.ASSESSMENT_OPEN_WEB, url=safe_web)))

    rows.extend(_back_home_rows(LearningIntent.BACK))
    return _screen(
        title="📝 آزمون‌ها",
        semantic_kind="learning.assessment_hub",
        intro=f"درس: {course_label}" if course_label else "وضعیت آزمون‌ها و مسیر امن ادامه",
        sections=sections[:3],
        rows=rows,
        severity="warning" if assessments is None else "info",
    )


def assessment_detail_screen(
    assessment: Mapping[str, Any],
    *,
    web_url: str | None = None,
    native_attempt_supported: bool = False,
) -> Screen:
    """Assessment metadata only. No answer, key, scoring or client authority."""
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
        rows.append(_row(_action(label, LearningIntent.ASSESSMENT_OPEN_WEB, url=safe_web, emphasis="primary")))
    rows.extend(_back_home_rows(LearningIntent.ASSESSMENTS_OPEN))
    return _screen(
        title=f"📝 {title}",
        semantic_kind="learning.assessment_detail",
        breadcrumb="آزمون‌ها › جزئیات",
        intro=(
            "تلاش داخل ربات فقط پس از اضافه‌شدن قرارداد canonical فعال می‌شود."
            if not native_attempt_supported
            else "جزئیات این آزمون از projection canonical نمایش داده می‌شود."
        ),
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
    """Human purchase/access center without raw-id purchase UX."""
    safe_web = _https(web_url)
    sections: list[Section] = []
    rows: list[ActionRow] = []

    if order_summary is None:
        sections.append(_section("سفارش‌ها", body="فهرست سفارش‌های حساب در bot-safe API فعلی ارائه نشده است."))
    else:
        order_items = []
        for order in order_summary[:5]:
            order_id = str(order.get("order_id") or order.get("id") or "")
            status = _ORDER_STATES.get(str(order.get("status") or "").lower(), "وضعیت نامشخص")
            order_items.append(
                _item(
                    order.get("title") or "سفارش فانوس",
                    subtitle=_money(order.get("amount_minor"), order.get("currency")),
                    status=status,
                    intent=LearningIntent.ORDER_OPEN if order_id else None,
                    payload={"order_id": order_id} if order_id else None,
                )
            )
        sections.append(_section("سفارش‌ها", body="سفارشی برای نمایش وجود ندارد." if not order_items else "", items=order_items))

    if access_summary is None:
        sections.append(_section("دسترسی‌ها", body="فهرست کامل دسترسی‌های حساب هنوز projection مستقل ربات ندارد."))
    else:
        access_items = []
        for access in access_summary[:5]:
            state = str(access.get("state") or access.get("status") or "unknown").lower()
            access_items.append(_item(access.get("title") or "دسترسی", status=_ACCESS_STATES.get(state, "وضعیت نامشخص")))
        sections.append(_section("دسترسی‌ها", body="دسترسی فعالی برای نمایش وجود ندارد." if not access_items else "", items=access_items))

    if catalog is None:
        sections.append(_section("خرید", body="کاتالوگ bot-safe در قرارداد فعلی وجود ندارد؛ شناسهٔ محصول از کاربر درخواست نمی‌شود."))
    elif catalog:
        sections.append(
            _section(
                "قابل تهیه",
                items=[_item(product.get("title") or "محصول", subtitle=_money(product.get("amount_minor"), product.get("currency"))) for product in catalog[:5]],
            )
        )

    if safe_web:
        rows.append(_row(_action("🌐 خرید و دسترسی در فانوس", LearningIntent.COMMERCE_OPEN_WEB, url=safe_web, emphasis="primary")))
    rows.extend(_back_home_rows(LearningIntent.BACK))
    return _screen(
        title="💳 خرید و دسترسی",
        semantic_kind="learning.commerce_hub",
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
    """Order, payment and entitlement are intentionally rendered separately."""
    raw_order_state = str(order.get("status") or "").lower()
    order_state = _ORDER_STATES.get(raw_order_state, "وضعیت نامشخص")

    if payment_status is None:
        payment_label = "در projection فعلی جداگانه گزارش نشده"
    else:
        payment_label = _PAYMENT_STATES.get(str(payment_status).lower(), "وضعیت نامشخص")

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
    rows: list[ActionRow] = [_row(_action("🔄 تازه‌سازی وضعیت", LearningIntent.ORDER_REFRESH))]
    safe_payment = _https(payment_url)
    safe_web = _https(web_url)
    if safe_payment:
        rows.insert(0, _row(_action("💳 ادامه پرداخت", LearningIntent.PAYMENT_OPEN_WEB, url=safe_payment, emphasis="primary")))
    elif raw_order_state in {"failed", "cancelled", "canceled", "expired"} and safe_web:
        rows.insert(0, _row(_action("🌐 تلاش دوباره در فانوس", LearningIntent.PAYMENT_RETRY_WEB, url=safe_web, emphasis="primary")))
    if safe_web:
        rows.append(_row(_action("🌐 جزئیات در فانوس", LearningIntent.COMMERCE_OPEN_WEB, url=safe_web)))
    rows.extend(_back_home_rows(LearningIntent.COMMERCE_OPEN))

    severity = "success" if access_label == "دسترسی فعال" else ("danger" if raw_order_state == "failed" else "info")
    return _screen(
        title=f"💳 {_clean(order.get('title') or 'وضعیت سفارش', 100)}",
        semantic_kind="learning.order_access_detail",
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
    """Forms list when a bot-safe projection exists; otherwise explicit web handoff."""
    safe_web = _https(web_url)
    rows: list[ActionRow] = []
    if forms is None:
        section = _section(
            "فرم‌ها و خدمات",
            body="فرم‌های فعال در API وب canonical هستند، اما projection امن اختصاصی ربات در قرارداد فعلی وجود ندارد.",
        )
        severity = "warning"
    else:
        items = []
        for form in forms[:8]:
            form_id = str(form.get("form_id") or form.get("id") or "")
            items.append(
                _item(
                    form.get("title") or "فرم",
                    subtitle=_clean(form.get("description"), 90),
                    status=_clean(form.get("state") or form.get("status"), 40),
                    intent=LearningIntent.FORM_OPEN if form_id else None,
                    payload={"form_id": form_id} if form_id else None,
                )
            )
        section = _section("فرم‌های فعال", body="فرم فعالی برای نمایش وجود ندارد." if not items else "", items=items)
        severity = "info"
    if safe_web:
        rows.append(_row(_action("🌐 باز کردن فرم‌ها در فانوس", LearningIntent.FORM_OPEN_WEB, url=safe_web, emphasis="primary")))
    rows.extend(_back_home_rows(LearningIntent.BACK))
    return _screen(
        title="📝 فرم‌ها و خدمات",
        semantic_kind="learning.forms_hub",
        intro="ثبت پاسخ فقط از مسیر canonical انجام می‌شود.",
        sections=(section,),
        rows=rows,
        severity=severity,
    )


def form_detail_screen(
    form: Mapping[str, Any],
    *,
    web_url: str | None = None,
    native_submission_supported: bool = False,
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
        rows.append(_row(_action("🌐 تکمیل در فانوس", LearningIntent.FORM_OPEN_WEB, url=safe_web, emphasis="primary")))
    rows.extend(_back_home_rows(LearningIntent.FORMS_OPEN))
    return _screen(
        title=f"📝 {_clean(form.get('title') or 'فرم', 105)}",
        semantic_kind="learning.form_detail",
        breadcrumb="فرم‌ها و خدمات › جزئیات",
        intro=_clean(form.get("description"), 450),
        sections=(_section("جزئیات", facts=facts),),
        rows=rows,
        footer=(
            "ارسال داخل ربات فقط پس از قرارداد bot-safe صریح فعال می‌شود."
            if not native_submission_supported
            else "ارسال باید همچنان توسط backend canonical ثبت شود."
        ),
    )


def domain_state_screen(
    domain: str,
    *,
    state: str,
    recoverable: bool = True,
    web_url: str | None = None,
) -> Screen:
    """Shared empty/error/denied family for learning domains."""
    domain_labels = {
        "resources": ("📚 منابع و یادگیری", LearningIntent.RESOURCES_OPEN),
        "assessments": ("📝 آزمون‌ها", LearningIntent.ASSESSMENTS_OPEN),
        "commerce": ("💳 خرید و دسترسی", LearningIntent.COMMERCE_OPEN),
        "forms": ("📝 فرم‌ها و خدمات", LearningIntent.FORMS_OPEN),
    }
    title, back_intent = domain_labels.get(domain, ("ℹ️ فانوس", LearningIntent.BACK))
    state_key = str(state or "error").lower()
    if state_key == "empty":
        intro = "در حال حاضر موردی برای نمایش وجود ندارد."
        body = "این وضعیت می‌تواند طبیعی باشد؛ بعداً دوباره بررسی کنید یا به بخش قبلی برگردید."
        severity = "info"
    elif state_key in {"denied", "forbidden"}:
        intro = "اجازه مشاهدهٔ این بخش برای وضعیت فعلی حساب تأیید نشد."
        body = "فضای آموزشی و دسترسی حساب را بررسی کنید. جزئیات امنیتی نمایش داده نمی‌شود."
        severity = "danger"
    elif state_key == "unavailable":
        intro = "این قابلیت در این کانال در دسترس نیست."
        body = "اگر مسیر امن دیگری وجود داشته باشد، از دکمهٔ فانوس استفاده کنید."
        severity = "warning"
    else:
        intro = "این بخش موقتاً بارگذاری نشد."
        body = "دادهٔ قبلی به‌عنوان وضعیت تازه نمایش داده نمی‌شود."
        severity = "danger"

    rows: list[ActionRow] = []
    if recoverable and state_key not in {"denied", "forbidden"}:
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
        title=title,
        semantic_kind=f"learning.{domain}.{state_key}",
        intro=intro,
        sections=(_section("گام بعدی", body=body),),
        rows=rows,
        severity=severity,
    )
