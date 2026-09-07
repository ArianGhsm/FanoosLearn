from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any


@dataclass(frozen=True, slots=True)
class Button:
    label: str
    callback_data: str | None = None
    url: str | None = None

    def __post_init__(self) -> None:
        if bool(self.callback_data) == bool(self.url):
            raise ValueError("A button must have exactly one action")


@dataclass(slots=True)
class Screen:
    text: str
    rows: list[list[Button]] = field(default_factory=list)
    edit: bool = False
    protect_content: bool = False


@dataclass(frozen=True, slots=True)
class DeliveryReceiptContext:
    workspace_id: str
    issuance_id: str
    idempotency_key: str


@dataclass(slots=True)
class ActionResult:
    screen: Screen
    delivery_receipt: DeliveryReceiptContext | None = None
    metadata: dict[str, Any] = field(default_factory=dict)
