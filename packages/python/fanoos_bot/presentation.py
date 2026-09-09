from __future__ import annotations

from collections.abc import Iterable

from .formatting import format_datetime, to_persian_digits, truncate_text
from .models import Button, Screen, ScreenPresentation, SemanticSection


def render_fallback(presentation: ScreenPresentation) -> str:
    lines: list[str] = [presentation.title]
    if presentation.breadcrumb:
        lines.extend(("", presentation.breadcrumb))
    if presentation.intro:
        lines.extend(("", presentation.intro))
    if presentation.facts:
        if lines[-1] != "":
            lines.append("")
        lines.extend(f"{label}: {value}" for label, value in presentation.facts)
    if presentation.list_items:
        if lines[-1] != "":
            lines.append("")
        lines.extend(f"• {item}" for item in presentation.list_items)
    for section in presentation.sections:
        if lines[-1] != "":
            lines.append("")
        if section.title:
            lines.append(section.title)
        if section.body:
            lines.append(section.body)
        lines.extend(f"• {item}" for item in section.items)
    if presentation.pagination:
        if lines[-1] != "":
            lines.append("")
        lines.append(presentation.pagination)
    if presentation.footer:
        if lines[-1] != "":
            lines.append("")
        lines.append(presentation.footer)
    return "\n".join(lines).strip()


def semantic_screen(
    title: str,
    semantic_kind: str,
    *,
    severity: str = "info",
    breadcrumb: str = "",
    intro: str = "",
    facts: Iterable[tuple[str, str]] = (),
    list_items: Iterable[str] = (),
    sections: Iterable[SemanticSection] = (),
    pagination: str = "",
    footer: str = "",
    rows: tuple[tuple[Button, ...], ...] = (),
    edit: bool = False,
    protect_content: bool = False,
    text: str | None = None,
) -> Screen:
    presentation = ScreenPresentation(
        title=title,
        semantic_kind=semantic_kind,
        severity=severity,
        breadcrumb=breadcrumb,
        intro=intro,
        facts=tuple((str(label), str(value)) for label, value in facts),
        list_items=tuple(str(item) for item in list_items),
        sections=tuple(sections),
        pagination=pagination,
        footer=footer,
        rtl=True,
    )
    return Screen(
        text if text is not None else render_fallback(presentation),
        rows,
        edit,
        protect_content,
        presentation,
    )


def error_screen(
    message: str,
    *,
    title: str = "❌ خطا",
    kind: str = "error",
    breadcrumb: str = "",
    rows=(),
) -> Screen:
    return semantic_screen(
        title,
        kind,
        severity="error",
        breadcrumb=breadcrumb,
        intro=message,
        rows=rows,
    )


def warning_screen(
    message: str,
    *,
    title: str = "⚠️ هشدار",
    kind: str = "warning",
    breadcrumb: str = "",
    rows=(),
) -> Screen:
    return semantic_screen(
        title,
        kind,
        severity="warning",
        breadcrumb=breadcrumb,
        intro=message,
        rows=rows,
    )


def success_screen(
    message: str,
    *,
    title: str = "✅ موفق",
    kind: str = "success",
    breadcrumb: str = "",
    rows=(),
) -> Screen:
    return semantic_screen(
        title,
        kind,
        severity="success",
        breadcrumb=breadcrumb,
        intro=message,
        rows=rows,
    )


def notification_detail_screen(payload: dict, *, unread: bool | None = None, rows=()) -> Screen:
    title = truncate_text(payload.get("title") or "اعلان", 120)
    body = truncate_text(payload.get("body") or "", 2400)
    timestamp = (
        payload.get("effective_at")
        or payload.get("effectiveAt")
        or payload.get("published_at")
        or payload.get("publishedAt")
        or payload.get("created_at")
        or payload.get("createdAt")
    )
    facts = []
    rendered_time = format_datetime(timestamp)
    if rendered_time:
        facts.append(("زمان", rendered_time))
    if unread is not None:
        facts.append(("وضعیت", "خوانده‌نشده" if unread else "خوانده‌شده"))
    return semantic_screen(
        f"🔔 {title}",
        "notification_detail",
        breadcrumb="اعلان‌ها",
        intro=body,
        facts=facts,
        rows=rows,
    )


def notification_center_screen(items: Iterable[dict], *, unread_count: int = 0, rows=()) -> Screen:
    normalized = [item for item in items if isinstance(item, dict)]
    summaries = []
    for item in normalized[:10]:
        marker = "●" if item.get("unread") else "✓"
        summaries.append(f"{marker} {truncate_text(item.get('title') or 'اعلان', 80)}")
    intro = (
        f"{to_persian_digits(max(0, unread_count))} اعلان خوانده‌نشده"
        if unread_count
        else "اعلان خوانده‌نشده‌ای ندارید."
    )
    if not summaries:
        intro = "فعلاً اعلانی برای حساب شما نیست."
    return semantic_screen(
        "🔔 مرکز اعلان‌ها",
        "notification_center",
        intro=intro,
        list_items=summaries,
        rows=rows,
    )
