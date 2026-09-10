from __future__ import annotations

from typing import Any

from ..core import EditPolicy, ProtectContent, Screen as CoreScreen
from .contract import ProviderAction, ProviderFact, ProviderScreen, ProviderSection, adapt_screen


def _item_text(item: Any) -> str:
    marker = str(getattr(item, "marker", "") or "").strip()
    title = str(getattr(item, "title", "") or "").strip()
    description = str(getattr(item, "description", "") or "").strip()
    meta = str(getattr(item, "meta", "") or "").strip()
    head = " ".join(part for part in (marker, title) if part)
    tail = " · ".join(part for part in (description, meta) if part)
    return f"{head} — {tail}" if head and tail else head or tail


def _provider_action(action: Any, *, role: str = "secondary") -> ProviderAction:
    intent = getattr(action, "intent", None)
    callback = intent.compact() if intent is not None else None
    if intent is not None and callback is None:
        raise ValueError("unresolved V3 callback intent reached provider adapter")
    label = str(getattr(action, "label", "") or "").strip()
    semantic_id = str(getattr(action, "identifier", "") or "").strip()
    destructive = bool(getattr(action, "destructive", False))
    normalized_role = "destructive" if destructive else role
    if "خانه" in label:
        normalized_role = "home"
    elif "بازگشت" in label or label.startswith("‹"):
        normalized_role = "back"
    elif "قبلی" in label or "بعدی" in label:
        normalized_role = "pagination"
    elif destructive:
        normalized_role = "destructive"
    elif any(token in label for token in ("دریافت امن", "تأیید", "انتخاب", "پرداخت", "تلاش دوباره")):
        normalized_role = "primary"
    permission = ""
    if "به‌روزرسانی سرور" in label or semantic_id.startswith("deployment."):
        permission = "deployment.manage"
    return ProviderAction(
        label=label,
        callback=callback,
        url=getattr(action, "url", None),
        role=normalized_role,
        semantic_id=semantic_id,
        requires_permission=permission,
        full_width=normalized_role in {"primary", "destructive"},
    )


def core_screen_to_provider(screen: CoreScreen) -> ProviderScreen:
    """Losslessly adapt bot-01 Screen display semantics for bot-04 renderers.

    Callback intents must already be compact or replaced with a subject-bound
    presentation route reference by integration. No authorization is inferred here.
    """
    context = ""
    if screen.context:
        context = f"{screen.context.label}: {screen.context.value}"
        if screen.context.detail:
            context += f" · {screen.context.detail}"

    sections: list[ProviderSection] = []
    for section in screen.sections:
        items = [f"{fact.label}: {fact.value}" for fact in section.facts]
        items.extend(filter(None, (_item_text(item) for item in section.items)))
        sections.append(
            ProviderSection(
                title=section.title,
                body=section.body,
                items=tuple(items),
            )
        )

    actions: list[ProviderAction] = []
    for row in screen.action_rows:
        actions.extend(_provider_action(action) for action in row.actions)
    if screen.pagination:
        if screen.pagination.previous:
            actions.append(_provider_action(screen.pagination.previous, role="pagination"))
        if screen.pagination.next:
            actions.append(_provider_action(screen.pagination.next, role="pagination"))

    pagination = ""
    if screen.pagination:
        pagination = screen.pagination.label or f"صفحه {screen.pagination.page}"
        if screen.pagination.total_pages:
            pagination = f"{pagination} از {screen.pagination.total_pages}"

    breadcrumb = " › ".join(crumb.label for crumb in screen.breadcrumb)
    if breadcrumb:
        context = f"{context}\n{breadcrumb}".strip() if context else breadcrumb

    return ProviderScreen(
        title=screen.title,
        semantic_kind=screen.identifier,
        context=context,
        intro=screen.intro,
        facts=(),
        list_items=(),
        sections=tuple(sections),
        pagination=pagination,
        footer=screen.footer,
        severity=screen.severity.value,
        actions=tuple(actions),
        protect_content=screen.protect_content is ProtectContent.REQUIRED,
        edit_policy=screen.edit_policy.value,
        rtl=screen.rtl,
        plain_text=screen.plain_text(),
    )


def provider_screen(source: Any) -> ProviderScreen:
    if isinstance(source, CoreScreen):
        return core_screen_to_provider(source)
    return adapt_screen(source)


__all__ = ["core_screen_to_provider", "provider_screen"]
