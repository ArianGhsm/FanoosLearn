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
            raise ValueError('button requires exactly one action')

@dataclass(frozen=True)
class Screen:
    text: str
    rows: tuple[tuple[Button, ...], ...] = ()
    edit: bool = False
    protect_content: bool = False

@dataclass(frozen=True)
class DocumentPayload:
    data: bytes
    filename: str = 'fanoos.pdf'
    caption: str = ''
    protect_content: bool = True
    def __post_init__(self) -> None:
        if not isinstance(self.data, bytes) or len(self.data) < 5 or not self.data.startswith(b'%PDF-'):
            raise ValueError('document payload must be PDF bytes')
        if not self.filename or '/' in self.filename or '\\' in self.filename or len(self.filename) > 96:
            raise ValueError('document filename is invalid')
        if len(self.caption) > 1024:
            raise ValueError('document caption is too long')

@dataclass(frozen=True)
class DeliveryReceiptContext:
    workspace_id: str
    issuance_id: str
    idempotency_key: str

@dataclass(frozen=True)
class ActionResult:
    screen: Screen
    receipt: DeliveryReceiptContext | None = None
    document: DocumentPayload | None = None
    metadata: dict[str, Any] = field(default_factory=dict)
