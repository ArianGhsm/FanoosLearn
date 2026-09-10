from __future__ import annotations

from dataclasses import dataclass
from enum import Enum
import re
from typing import Any, Iterable


_IDENTIFIER_RE = re.compile(r"^[a-z0-9][a-z0-9_.:-]{0,63}$")
_PARAM_KEY_RE = re.compile(r"^[a-z][a-z0-9_]{0,15}$")
_PARAM_VALUE_RE = re.compile(r"^[A-Za-z0-9._:@/+~-]{1,64}$")
_ROUTE_REF_RE = re.compile(r"^[A-Za-z0-9_-]{6,40}$")


class Severity(str, Enum):
    NEUTRAL = "neutral"
    INFO = "info"
    SUCCESS = "success"
    WARNING = "warning"
    ERROR = "error"


class ProtectContent(str, Enum):
    """Presentation requirement; REQUIRED may never be silently downgraded."""

    INHERIT = "inherit"
    REQUIRED = "required"


class EditPolicy(str, Enum):
    AUTO = "auto"
    EDIT_IF_SAFE = "edit_if_safe"
    SEND_NEW = "send_new"


@dataclass(frozen=True)
class CallbackIntent:
    """Provider-neutral presentation intent.

    Params are routing correlation only. They are never authorization, membership,
    payment, entitlement, scoring, or deployment truth. Provider integration must
    re-read/re-authorize canonical backend state before displaying or mutating it.
    """

    name: str
    params: tuple[tuple[str, str], ...] = ()
    route_ref: str | None = None

    def __post_init__(self) -> None:
        if not _IDENTIFIER_RE.fullmatch(self.name):
            raise ValueError("invalid callback intent name")
        seen: set[str] = set()
        for key, value in self.params:
            if not _PARAM_KEY_RE.fullmatch(key) or key in seen:
                raise ValueError("invalid callback intent parameter")
            if not isinstance(value, str) or not value or len(value) > 256:
                raise ValueError("invalid callback intent value")
            seen.add(key)
        if self.route_ref is not None and not _ROUTE_REF_RE.fullmatch(self.route_ref):
            raise ValueError("invalid presentation route ref")

    def compact(self, *, max_bytes: int = 64) -> str | None:
        """Return a safe compact provider payload when one fits.

        `None` tells the provider worker to allocate a short, subject-bound,
        expiring presentation route reference rather than serializing long state.
        """

        if self.route_ref:
            candidate = f"r:{self.route_ref}"
            return candidate if len(candidate.encode("utf-8")) <= max_bytes else None
        if not self.params:
            return self.name if len(self.name.encode("utf-8")) <= max_bytes else None
        if any(not _PARAM_VALUE_RE.fullmatch(value) for _, value in self.params):
            return None
        suffix = "&".join(f"{key}={value}" for key, value in self.params)
        candidate = f"{self.name}|{suffix}"
        return candidate if len(candidate.encode("utf-8")) <= max_bytes else None

    def to_dict(self) -> dict[str, Any]:
        return {
            "name": self.name,
            "params": [{"key": key, "value": value} for key, value in self.params],
            "route_ref": self.route_ref,
        }


@dataclass(frozen=True)
class Fact:
    label: str
    value: str

    def __post_init__(self) -> None:
        if not self.label.strip() or not self.value.strip():
            raise ValueError("fact label and value are required")

    def to_dict(self) -> dict[str, str]:
        return {"label": self.label, "value": self.value}


@dataclass(frozen=True)
class ListItem:
    title: str
    description: str = ""
    meta: str = ""
    marker: str = ""

    def __post_init__(self) -> None:
        if not self.title.strip():
            raise ValueError("list item title is required")

    def to_dict(self) -> dict[str, str]:
        return {
            "title": self.title,
            "description": self.description,
            "meta": self.meta,
            "marker": self.marker,
        }


@dataclass(frozen=True)
class Section:
    title: str = ""
    body: str = ""
    facts: tuple[Fact, ...] = ()
    items: tuple[ListItem, ...] = ()

    def __post_init__(self) -> None:
        if not (self.title.strip() or self.body.strip() or self.facts or self.items):
            raise ValueError("section must contain display content")

    def to_dict(self) -> dict[str, Any]:
        return {
            "title": self.title,
            "body": self.body,
            "facts": [fact.to_dict() for fact in self.facts],
            "items": [item.to_dict() for item in self.items],
        }


@dataclass(frozen=True)
class Context:
    label: str
    value: str
    detail: str = ""

    def __post_init__(self) -> None:
        if not self.label.strip() or not self.value.strip():
            raise ValueError("context label and value are required")

    def to_dict(self) -> dict[str, str]:
        return {"label": self.label, "value": self.value, "detail": self.detail}


@dataclass(frozen=True)
class Breadcrumb:
    label: str
    intent: CallbackIntent | None = None

    def __post_init__(self) -> None:
        if not self.label.strip():
            raise ValueError("breadcrumb label is required")

    def to_dict(self) -> dict[str, Any]:
        return {"label": self.label, "intent": self.intent.to_dict() if self.intent else None}


@dataclass(frozen=True)
class Action:
    identifier: str
    label: str
    intent: CallbackIntent | None = None
    url: str | None = None
    destructive: bool = False

    def __post_init__(self) -> None:
        if not _IDENTIFIER_RE.fullmatch(self.identifier):
            raise ValueError("invalid action identifier")
        if not self.label.strip() or len(self.label) > 80:
            raise ValueError("action label is required and must be bounded")
        if (self.intent is None) == (self.url is None):
            raise ValueError("action requires exactly one callback intent or URL")
        if self.url is not None and not self.url.startswith(("https://", "http://")):
            raise ValueError("external action URL must use HTTP(S)")

    def to_dict(self) -> dict[str, Any]:
        return {
            "identifier": self.identifier,
            "label": self.label,
            "intent": self.intent.to_dict() if self.intent else None,
            "url": self.url,
            "destructive": self.destructive,
        }


@dataclass(frozen=True)
class ActionRow:
    actions: tuple[Action, ...]

    def __post_init__(self) -> None:
        if not 1 <= len(self.actions) <= 2:
            raise ValueError("bot action rows contain one or two actions")

    def to_dict(self) -> list[dict[str, Any]]:
        return [action.to_dict() for action in self.actions]


@dataclass(frozen=True)
class Pagination:
    page: int
    total_pages: int | None = None
    previous: Action | None = None
    next: Action | None = None
    label: str = ""

    def __post_init__(self) -> None:
        if self.page < 1:
            raise ValueError("page must be positive")
        if self.total_pages is not None and self.total_pages < self.page:
            raise ValueError("total pages cannot be below current page")
        for action in (self.previous, self.next):
            if action is not None and action.intent is None:
                raise ValueError("pagination actions must be callback intents")

    def to_dict(self) -> dict[str, Any]:
        return {
            "page": self.page,
            "total_pages": self.total_pages,
            "previous": self.previous.to_dict() if self.previous else None,
            "next": self.next.to_dict() if self.next else None,
            "label": self.label,
        }


@dataclass(frozen=True)
class Screen:
    identifier: str
    title: str
    intro: str = ""
    severity: Severity = Severity.NEUTRAL
    context: Context | None = None
    breadcrumb: tuple[Breadcrumb, ...] = ()
    sections: tuple[Section, ...] = ()
    action_rows: tuple[ActionRow, ...] = ()
    pagination: Pagination | None = None
    footer: str = ""
    protect_content: ProtectContent = ProtectContent.INHERIT
    edit_policy: EditPolicy = EditPolicy.EDIT_IF_SAFE
    rtl: bool = True

    def __post_init__(self) -> None:
        if not _IDENTIFIER_RE.fullmatch(self.identifier):
            raise ValueError("invalid screen identifier")
        if not self.title.strip():
            raise ValueError("screen title is required")
        if len(self.action_rows) > 10:
            raise ValueError("screen action rows must remain bounded")

    def to_dict(self) -> dict[str, Any]:
        return {
            "identifier": self.identifier,
            "title": self.title,
            "intro": self.intro,
            "severity": self.severity.value,
            "context": self.context.to_dict() if self.context else None,
            "breadcrumb": [crumb.to_dict() for crumb in self.breadcrumb],
            "sections": [section.to_dict() for section in self.sections],
            "action_rows": [row.to_dict() for row in self.action_rows],
            "pagination": self.pagination.to_dict() if self.pagination else None,
            "footer": self.footer,
            "protect_content": self.protect_content.value,
            "edit_policy": self.edit_policy.value,
            "rtl": self.rtl,
        }

    def plain_text(self) -> str:
        lines: list[str] = [self.title]
        if self.context:
            context_line = f"{self.context.label}: {self.context.value}"
            if self.context.detail:
                context_line += f" · {self.context.detail}"
            lines.extend(("", context_line))
        if self.breadcrumb:
            lines.extend(("", " › ".join(crumb.label for crumb in self.breadcrumb)))
        if self.intro:
            lines.extend(("", self.intro))
        for section in self.sections:
            lines.append("")
            if section.title:
                lines.append(section.title)
            if section.body:
                lines.append(section.body)
            lines.extend(f"{fact.label}: {fact.value}" for fact in section.facts)
            for item in section.items:
                prefix = f"{item.marker} " if item.marker else "• "
                lines.append(f"{prefix}{item.title}")
                if item.description:
                    lines.append(item.description)
                if item.meta:
                    lines.append(item.meta)
        if self.pagination and self.pagination.label:
            lines.extend(("", self.pagination.label))
        if self.footer:
            lines.extend(("", self.footer))
        return "\n".join(lines).strip()


def rows(*rows: Iterable[Action]) -> tuple[ActionRow, ...]:
    """Convenience constructor that preserves the one/two-action row contract."""

    return tuple(ActionRow(tuple(row)) for row in rows)
