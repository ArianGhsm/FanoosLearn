from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any


@dataclass(frozen=True)
class Button:
    text: str
    callback: str | None = None
    url: str | None = None

    def __post_init__(self) -> None:
        if (self.callback is None) == (self.url is None):
            raise ValueError("button requires exactly one action")


@dataclass(frozen=True)
class SemanticSection:
    title: str = ""
    body: str = ""
    items: tuple[str, ...] = ()

    def to_dict(self) -> dict[str, Any]:
        return {
            "title": self.title,
            "body": self.body,
            "items": list(self.items),
        }


@dataclass(frozen=True)
class ScreenPresentation:
    """Framework-neutral display metadata.

    This is presentation-only metadata. Authorization, entitlement and business
    state remain authoritative in the backend projection used to build it.
    """

    title: str
    semantic_kind: str
    severity: str = "info"
    breadcrumb: str = ""
    intro: str = ""
    facts: tuple[tuple[str, str], ...] = ()
    list_items: tuple[str, ...] = ()
    sections: tuple[SemanticSection, ...] = ()
    pagination: str = ""
    footer: str = ""
    rtl: bool = True

    def to_dict(self) -> dict[str, Any]:
        return {
            "title": self.title,
            "semantic_kind": self.semantic_kind,
            "severity": self.severity,
            "breadcrumb": self.breadcrumb,
            "intro": self.intro,
            "facts": [{"label": label, "value": value} for label, value in self.facts],
            "list_items": list(self.list_items),
            "sections": [section.to_dict() for section in self.sections],
            "pagination": self.pagination,
            "footer": self.footer,
            "rtl": self.rtl,
        }


@dataclass(frozen=True)
class Screen:
    text: str
    rows: tuple[tuple[Button, ...], ...] = ()
    edit: bool = False
    protect_content: bool = False
    presentation: ScreenPresentation | None = None
    # Integration-only compatibility envelope. Runtime code may keep using the
    # stable V2-shaped transport fields while provider V3 renderers consume the
    # canonical bot-01 semantic Screen directly. This value is presentation only.
    v3: Any | None = None


@dataclass(frozen=True)
class DocumentPayload:
    data: bytes
    filename: str = "fanoos.pdf"
    caption: str = ""
    protect_content: bool = True

    def __post_init__(self) -> None:
        if not isinstance(self.data, bytes) or len(self.data) < 5 or not self.data.startswith(b"%PDF-"):
            raise ValueError("document payload must be PDF bytes")
        if not self.filename or "/" in self.filename or "\\" in self.filename or len(self.filename) > 96:
            raise ValueError("document filename is invalid")
        if len(self.caption) > 1024:
            raise ValueError("document caption is too long")


@dataclass(frozen=True)
class DeliveryReceiptContext:
    workspace_id: str
    issuance_id: str
    idempotency_key: str


@dataclass(frozen=True)
class ActionResult:
    screen: Any
    receipt: DeliveryReceiptContext | None = None
    document: DocumentPayload | None = None
    metadata: dict[str, Any] = field(default_factory=dict)
