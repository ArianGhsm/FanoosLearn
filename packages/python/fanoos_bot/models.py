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
class DeliveryReceiptContext:
    workspace_id: str
    issuance_id: str
    idempotency_key: str

@dataclass(frozen=True)
class ActionResult:
    screen: Screen
    receipt: DeliveryReceiptContext | None = None
    metadata: dict[str, Any] = field(default_factory=dict)
