from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Iterable


class ProviderContractError(ValueError):
    """Raised when a semantic screen cannot be adapted without guessing."""


@dataclass(frozen=True)
class ProviderContext:
    """Provider facts supplied by integration, never by presentation code.

    ``canonical_permissions`` must contain only permissions already returned by
    the canonical backend for the linked user/context. Provider renderers never
    derive or grant permissions themselves.
    """

    private_chat: bool = False
    callback_query: bool = False
    current_message_id: int | None = None
    canonical_permissions: frozenset[str] = frozenset()

    @property
    def can_manage_deployments(self) -> bool:
        return self.private_chat and "deployment.manage" in self.canonical_permissions


@dataclass(frozen=True)
class CallbackAckIntent:
    required: bool
    timing: str = "before_business_action"
    text: str = ""


@dataclass(frozen=True)
class ProviderFact:
    label: str
    value: str


@dataclass(frozen=True)
class ProviderSection:
    title: str = ""
    body: str = ""
    items: tuple[str, ...] = ()


@dataclass(frozen=True)
class ProviderAction:
    label: str
    callback: str | None = None
    url: str | None = None
    role: str = "secondary"
    semantic_id: str = ""
    requires_permission: str = ""
    full_width: bool = False

    def __post_init__(self) -> None:
        if not self.label.strip():
            raise ProviderContractError("action label is required")
        if (self.callback is None) == (self.url is None):
            raise ProviderContractError("action requires exactly one callback or URL")


@dataclass(frozen=True)
class ProviderScreen:
    title: str
    semantic_kind: str = "screen"
    context: str = ""
    intro: str = ""
    facts: tuple[ProviderFact, ...] = ()
    list_items: tuple[str, ...] = ()
    sections: tuple[ProviderSection, ...] = ()
    pagination: str = ""
    footer: str = ""
    severity: str = "info"
    actions: tuple[ProviderAction, ...] = ()
    protect_content: bool = False
    edit_policy: str = "new_message"
    rtl: bool = True
    plain_text: str = ""


_MISSING = object()


def _value(source: Any, name: str, default: Any = None) -> Any:
    if source is None:
        return default
    if isinstance(source, dict):
        return source.get(name, default)
    value = getattr(source, name, default)
    return default if callable(value) else value


def _first_value(source: Any, names: Iterable[str], default: Any = None) -> Any:
    for name in names:
        value = _value(source, name, _MISSING)
        if value is not _MISSING and value is not None:
            return value
    return default


def _enum_text(value: Any, default: str = "") -> str:
    if value is None:
        return default
    raw = getattr(value, "value", value)
    return str(raw).strip() if raw is not None else default


def _string(value: Any) -> str:
    if value is None:
        return ""
    return str(value).strip()


def _presentation(screen: Any) -> Any:
    return _first_value(
        screen,
        ("presentation", "presentation_metadata", "semantic", "semantic_screen"),
        None,
    )


def _context_text(screen: Any, presentation: Any) -> str:
    raw = _first_value(presentation, ("context", "breadcrumb"), None)
    if raw is None:
        raw = _first_value(screen, ("context", "breadcrumb"), "")
    if isinstance(raw, str):
        return raw.strip()
    return _string(_first_value(raw, ("label", "title", "text", "breadcrumb"), ""))


def _facts(value: Any) -> tuple[ProviderFact, ...]:
    if not isinstance(value, (list, tuple)):
        return ()
    out: list[ProviderFact] = []
    for fact in value:
        if isinstance(fact, (list, tuple)) and len(fact) == 2:
            label, item_value = fact
        else:
            label = _first_value(fact, ("label", "title", "name"), "")
            item_value = _first_value(fact, ("value", "text", "body"), "")
        label_text = _string(label)
        value_text = _string(item_value)
        if label_text or value_text:
            out.append(ProviderFact(label_text, value_text))
    return tuple(out)


def _items(value: Any) -> tuple[str, ...]:
    if not isinstance(value, (list, tuple)):
        return ()
    out: list[str] = []
    for item in value:
        text = _string(_first_value(item, ("text", "label", "title"), item))
        if text:
            out.append(text)
    return tuple(out)


def _sections(value: Any) -> tuple[ProviderSection, ...]:
    if not isinstance(value, (list, tuple)):
        return ()
    out: list[ProviderSection] = []
    for section in value:
        title = _string(_first_value(section, ("title", "heading", "label"), ""))
        body = _string(_first_value(section, ("body", "text", "intro"), ""))
        items = _items(_first_value(section, ("items", "list_items", "rows"), ()))
        if title or body or items:
            out.append(ProviderSection(title, body, items))
    return tuple(out)


def _infer_action_metadata(
    label: str,
    role: str,
    semantic_id: str,
    permission: str,
) -> tuple[str, str, str]:
    normalized = label.replace("\u200c", " ").strip()
    lowered_role = role.lower().strip()

    if not lowered_role:
        lowered_role = "secondary"

    if "خانه" in normalized:
        lowered_role = "home"
        semantic_id = semantic_id or "navigation.home"
    elif "بازگشت" in normalized:
        lowered_role = "back"
        semantic_id = semantic_id or "navigation.back"
    elif "صفحه قبل" in normalized or "قبلی" in normalized:
        lowered_role = "pagination"
        semantic_id = semantic_id or "pagination.previous"
    elif "صفحه بعد" in normalized or "بعدی" in normalized:
        lowered_role = "pagination"
        semantic_id = semantic_id or "pagination.next"
    elif normalized in {"لغو", "انصراف"}:
        lowered_role = "back"
        semantic_id = semantic_id or "navigation.cancel"
    elif any(token in normalized for token in ("حذف", "قطع اتصال", "لغو دسترسی", "باطل")):
        lowered_role = "destructive"
    elif any(
        token in normalized
        for token in (
            "دریافت امن",
            "ارسال",
            "پرداخت",
            "ثبت",
            "شروع",
            "تأیید",
            "تلاش دوباره",
            "انتخاب",
        )
    ):
        lowered_role = "primary"

    if "به روز رسانی سرور" in normalized or "به‌روزرسانی سرور" in normalized:
        semantic_id = semantic_id or "deployment.update"
        permission = permission or "deployment.manage"
        lowered_role = "primary"
    elif normalized.startswith("⚙️ مدیریت") or normalized == "مدیریت":
        semantic_id = semantic_id or "deployment.management"
        permission = permission or "deployment.manage"

    return lowered_role, semantic_id, permission


def _action(value: Any) -> ProviderAction:
    label = _string(_first_value(value, ("label", "text", "title"), ""))
    callback = _first_value(value, ("callback", "callback_data", "data"), None)
    url = _first_value(value, ("url", "href"), None)
    callback_text = None if callback is None else str(callback)
    url_text = None if url is None else str(url)

    role = _enum_text(_first_value(value, ("role", "action_role", "kind"), "secondary"), "secondary")
    semantic_id = _string(_first_value(value, ("semantic_id", "action_id", "id", "key"), ""))
    permission = _string(
        _first_value(value, ("requires_permission", "required_permission", "capability"), "")
    )
    role, semantic_id, permission = _infer_action_metadata(
        label,
        role,
        semantic_id,
        permission,
    )

    explicit_full = bool(_first_value(value, ("full_width", "is_full_width"), False))
    return ProviderAction(
        label=label,
        callback=callback_text,
        url=url_text,
        role=role,
        semantic_id=semantic_id,
        requires_permission=permission,
        full_width=explicit_full,
    )


def _looks_like_action(value: Any) -> bool:
    return any(
        _first_value(value, (name,), None) is not None
        for name in ("callback", "callback_data", "data", "url", "href")
    )


def _actions(screen: Any) -> tuple[ProviderAction, ...]:
    raw_rows = _first_value(screen, ("action_rows", "rows"), None)
    out: list[ProviderAction] = []

    if isinstance(raw_rows, (list, tuple)):
        for raw_row in raw_rows:
            if _looks_like_action(raw_row):
                out.append(_action(raw_row))
                continue
            if isinstance(raw_row, (list, tuple)):
                for raw_action in raw_row:
                    out.append(_action(raw_action))
        if out:
            return tuple(out)

    raw_actions = _first_value(screen, ("actions",), ())
    if isinstance(raw_actions, (list, tuple)):
        return tuple(_action(item) for item in raw_actions)
    return ()


def _fallback_title(text: str) -> str:
    for line in text.replace("\r", "\n").splitlines():
        if line.strip():
            return line.strip()
    return "فانوس"


def adapt_screen(screen: Any) -> ProviderScreen:
    """Adapt current V2 and future bot-01 semantic screens without domain truth.

    The adapter reads display metadata only. It never turns a label, callback,
    workspace identifier, payment state or provider payload into authorization.
    """

    if isinstance(screen, ProviderScreen):
        return screen
    if screen is None:
        raise ProviderContractError("screen is required")

    presentation = _presentation(screen)
    plain_text = _string(_first_value(screen, ("text", "plain_text", "fallback_text"), ""))

    title = _string(
        _first_value(
            presentation,
            ("title", "heading"),
            _first_value(screen, ("title", "heading"), ""),
        )
    ) or _fallback_title(plain_text)

    semantic_kind = _enum_text(
        _first_value(
            presentation,
            ("semantic_kind", "screen_kind", "kind"),
            _first_value(screen, ("semantic_kind", "screen_kind", "kind"), "screen"),
        ),
        "screen",
    )
    severity = _enum_text(
        _first_value(
            presentation,
            ("severity",),
            _first_value(screen, ("severity",), "info"),
        ),
        "info",
    ).lower()

    intro = _string(
        _first_value(
            presentation,
            ("intro", "description"),
            _first_value(screen, ("intro", "description"), ""),
        )
    )
    facts = _facts(
        _first_value(
            presentation,
            ("facts",),
            _first_value(screen, ("facts",), ()),
        )
    )
    list_items = _items(
        _first_value(
            presentation,
            ("list_items", "items"),
            _first_value(screen, ("list_items", "items"), ()),
        )
    )
    sections = _sections(
        _first_value(
            presentation,
            ("sections",),
            _first_value(screen, ("sections",), ()),
        )
    )
    pagination = _string(
        _first_value(
            presentation,
            ("pagination", "page_label"),
            _first_value(screen, ("pagination", "page_label"), ""),
        )
    )
    footer = _string(
        _first_value(
            presentation,
            ("footer",),
            _first_value(screen, ("footer",), ""),
        )
    )
    rtl = bool(
        _first_value(
            presentation,
            ("rtl",),
            _first_value(screen, ("rtl",), True),
        )
    )

    protect_raw = _first_value(screen, ("protect_content", "protected", "protection"), False)
    if isinstance(protect_raw, bool):
        protect_content = protect_raw
    else:
        protect_content = bool(_first_value(protect_raw, ("required", "enabled"), False))

    edit_policy = _enum_text(_first_value(screen, ("edit_policy",), ""), "")
    if not edit_policy:
        edit_policy = "edit_if_safe" if bool(_first_value(screen, ("edit",), False)) else "new_message"

    return ProviderScreen(
        title=title,
        semantic_kind=semantic_kind,
        context=_context_text(screen, presentation),
        intro=intro,
        facts=facts,
        list_items=list_items,
        sections=sections,
        pagination=pagination,
        footer=footer,
        severity=severity,
        actions=_actions(screen),
        protect_content=protect_content,
        edit_policy=edit_policy,
        rtl=rtl,
        plain_text=plain_text,
    )
