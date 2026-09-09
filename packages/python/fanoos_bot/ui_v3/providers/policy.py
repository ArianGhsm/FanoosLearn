from __future__ import annotations

import unicodedata
from dataclasses import dataclass, replace
from typing import Iterable

from .contract import (
    CallbackAckIntent,
    ProviderAction,
    ProviderContext,
    ProviderScreen,
)


class ProviderRenderError(ValueError):
    """Presentation-only failure safe for integration to map to a generic UI error."""


@dataclass(frozen=True)
class ProviderCapabilities:
    name: str
    max_text_chars: int
    max_callback_bytes: int
    supports_edit: bool
    supports_inline_keyboard: bool
    supports_callback_ack: bool
    supports_forward_protection: bool
    supports_native_rich: bool = False
    max_rich_chars: int = 0
    supports_rtl: bool = False
    text_format: str = "plain"


TELEGRAM_CAPABILITIES = ProviderCapabilities(
    name="telegram",
    max_text_chars=4096,
    max_callback_bytes=64,
    supports_edit=True,
    supports_inline_keyboard=True,
    supports_callback_ack=True,
    supports_forward_protection=True,
    supports_native_rich=True,
    max_rich_chars=32768,
    supports_rtl=True,
    text_format="rich_message",
)

BALE_CAPABILITIES = ProviderCapabilities(
    name="bale",
    max_text_chars=4096,
    max_callback_bytes=64,
    supports_edit=True,
    supports_inline_keyboard=True,
    supports_callback_ack=True,
    supports_forward_protection=False,
    text_format="markdown_text",
)


@dataclass(frozen=True)
class DensityAssessment:
    section_count: int
    list_item_count: int
    action_count: int
    over_typical_sections: bool
    over_typical_actions: bool
    requires_pagination: bool


@dataclass(frozen=True)
class MessageDensityPolicy:
    typical_max_sections: int = 3
    typical_max_actions: int = 8
    typical_max_list_items: int = 8
    hard_max_sections: int = 4
    hard_max_list_items: int = 12
    hard_max_actions: int = 12

    def assess(self, screen: ProviderScreen) -> DensityAssessment:
        list_count = len(screen.list_items) + sum(len(section.items) for section in screen.sections)
        sections = len(screen.sections)
        actions = len(screen.actions)
        return DensityAssessment(
            section_count=sections,
            list_item_count=list_count,
            action_count=actions,
            over_typical_sections=sections > self.typical_max_sections,
            over_typical_actions=actions > self.typical_max_actions,
            requires_pagination=(
                sections > self.typical_max_sections
                or list_count > self.typical_max_list_items
                or actions > self.hard_max_actions
            ),
        )

    def validate(self, screen: ProviderScreen) -> DensityAssessment:
        assessment = self.assess(screen)
        if assessment.section_count > self.hard_max_sections:
            raise ProviderRenderError("section_density_requires_upstream_pagination")
        if assessment.list_item_count > self.hard_max_list_items:
            raise ProviderRenderError("list_density_requires_upstream_pagination")
        if assessment.action_count > self.hard_max_actions:
            raise ProviderRenderError("action_density_requires_secondary_screen")
        return assessment


DEFAULT_DENSITY_POLICY = MessageDensityPolicy()


@dataclass(frozen=True)
class ProtectionDecision:
    required: bool
    provider_supported: bool
    can_deliver_original: bool
    atomic: bool
    failure_text: str = ""


def protection_decision(
    screen: ProviderScreen,
    capabilities: ProviderCapabilities,
) -> ProtectionDecision:
    if not screen.protect_content:
        return ProtectionDecision(False, capabilities.supports_forward_protection, True, False)
    if capabilities.supports_forward_protection:
        return ProtectionDecision(True, True, True, True)
    return ProtectionDecision(
        True,
        False,
        False,
        True,
        "🔒 این محتوا باید به‌صورت محافظت‌شده ارسال شود، اما این قابلیت در این پیام‌رسان در دسترس نیست. فایل ارسال نشد.",
    )


def callback_ack_intent(context: ProviderContext) -> CallbackAckIntent:
    return CallbackAckIntent(
        required=bool(context.callback_query),
        timing="before_business_action",
        text="",
    )


def resolve_delivery_intent(
    screen: ProviderScreen,
    context: ProviderContext,
    capabilities: ProviderCapabilities,
) -> str:
    if screen.protect_content:
        return "new_message"
    policy = screen.edit_policy.strip().lower()
    if policy in {"new", "new_message", "durable", "durable_new", "never_edit"}:
        return "new_message"
    if policy in {"edit", "edit_if_safe", "prefer_edit", "navigation"}:
        if capabilities.supports_edit and context.current_message_id is not None:
            return "edit_if_safe"
    return "new_message"


def is_owner_management_surface(screen: ProviderScreen) -> bool:
    kind = screen.semantic_kind.strip().lower()
    return (
        kind.startswith("deployment")
        or kind.startswith("owner_management")
        or "update_server" in kind
    )


def _is_management_action(action: ProviderAction) -> bool:
    return (
        action.requires_permission == "deployment.manage"
        or action.semantic_id.startswith("deployment.")
        or action.role == "owner"
    )


def actions_for_provider(
    screen: ProviderScreen,
    context: ProviderContext,
    provider: str,
) -> tuple[ProviderAction, ...]:
    out: list[ProviderAction] = []
    for action in screen.actions:
        if _is_management_action(action):
            if provider == "bale":
                continue
            if provider == "telegram" and not context.can_manage_deployments:
                continue
        if action.requires_permission and action.requires_permission != "deployment.manage":
            if action.requires_permission not in context.canonical_permissions:
                continue
        if provider == "bale" and action.semantic_id.startswith("deployment."):
            continue
        out.append(action)
    return tuple(out)


def navigation_actions(actions: Iterable[ProviderAction]) -> tuple[ProviderAction, ...]:
    return tuple(action for action in actions if action.role in {"home", "back"})


def with_actions(screen: ProviderScreen, actions: Iterable[ProviderAction]) -> ProviderScreen:
    return replace(screen, actions=tuple(actions))


def visual_units(text: str) -> int:
    units = 0
    for char in text:
        if unicodedata.combining(char):
            continue
        if char in {"\u200c", "\u200d", "\ufe0f"}:
            continue
        width = unicodedata.east_asian_width(char)
        units += 2 if width in {"W", "F"} else 1
    return units


def _full_width(action: ProviderAction) -> bool:
    return (
        action.full_width
        or action.role in {"primary", "destructive"}
        or visual_units(action.label) > 20
    )


def _validate_action(action: ProviderAction, capabilities: ProviderCapabilities) -> None:
    if action.callback is not None:
        callback_bytes = len(action.callback.encode("utf-8"))
        if not 1 <= callback_bytes <= capabilities.max_callback_bytes:
            raise ProviderRenderError("callback_data_out_of_bounds")
    if action.url is not None and not action.url.strip():
        raise ProviderRenderError("empty_action_url")


def _pairable(first: ProviderAction, second: ProviderAction) -> bool:
    if _full_width(first) or _full_width(second):
        return False
    first_width = visual_units(first.label)
    second_width = visual_units(second.label)
    return first_width <= 18 and second_width <= 18 and first_width + second_width <= 34


def pack_actions(
    actions: Iterable[ProviderAction],
    capabilities: ProviderCapabilities,
) -> tuple[tuple[ProviderAction, ...], ...]:
    """Pack actions into predictable provider rows without changing actions.

    Routine short actions use two columns. Strong primary/destructive/long
    actions are isolated. Pagination and Back/Home are moved to predictable
    bottom rows while preserving order within their semantic groups.
    """

    normalized = tuple(actions)
    for action in normalized:
        _validate_action(action, capabilities)

    content = [
        action
        for action in normalized
        if action.role not in {"pagination", "home", "back"}
    ]
    pagination = [action for action in normalized if action.role == "pagination"]
    navigation = [action for action in normalized if action.role in {"back", "home"}]

    rows: list[tuple[ProviderAction, ...]] = []
    index = 0
    while index < len(content):
        current = content[index]
        if _full_width(current):
            rows.append((current,))
            index += 1
            continue
        if index + 1 < len(content) and _pairable(current, content[index + 1]):
            rows.append((current, content[index + 1]))
            index += 2
            continue
        rows.append((current,))
        index += 1

    page_index = 0
    while page_index < len(pagination):
        rows.append(tuple(pagination[page_index : page_index + 2]))
        page_index += 2

    nav_index = 0
    while nav_index < len(navigation):
        current = navigation[nav_index]
        if nav_index + 1 < len(navigation) and _pairable(current, navigation[nav_index + 1]):
            rows.append((current, navigation[nav_index + 1]))
            nav_index += 2
        else:
            rows.append((current,))
            nav_index += 1

    return tuple(rows)


def keyboard_payload(
    rows: Iterable[Iterable[ProviderAction]],
) -> tuple[tuple[dict[str, str], ...], ...]:
    rendered: list[tuple[dict[str, str], ...]] = []
    for row in rows:
        rendered_row: list[dict[str, str]] = []
        for action in row:
            item = {"text": action.label}
            if action.callback is not None:
                item["callback_data"] = action.callback
            else:
                item["url"] = str(action.url)
            rendered_row.append(item)
        if rendered_row:
            rendered.append(tuple(rendered_row))
    return tuple(rendered)


def compose_plain_text(screen: ProviderScreen) -> str:
    if screen.protect_content and screen.plain_text:
        return screen.plain_text.replace("\r\n", "\n").replace("\r", "\n").strip()

    groups: list[str] = [screen.title.strip()]
    if screen.context:
        groups.append(screen.context.strip())
    if screen.intro:
        groups.append(screen.intro.strip())
    if screen.facts:
        groups.append(
            "\n".join(
                f"{fact.label}: {fact.value}" if fact.label else fact.value
                for fact in screen.facts
            )
        )
    if screen.list_items:
        groups.append("\n".join(f"• {item}" for item in screen.list_items))
    for section in screen.sections:
        parts: list[str] = []
        if section.title:
            parts.append(section.title)
        if section.body:
            parts.append(section.body)
        parts.extend(f"• {item}" for item in section.items)
        if parts:
            groups.append("\n".join(parts))
    if screen.pagination:
        groups.append(screen.pagination)
    if screen.footer:
        groups.append(screen.footer)

    text = "\n\n".join(group for group in groups if group).strip()
    return text or screen.plain_text.strip()


def validate_message_text(text: str, capabilities: ProviderCapabilities) -> None:
    if "\x00" in text:
        raise ProviderRenderError("invalid_message_text")
    if not text:
        raise ProviderRenderError("empty_message_text")
    if len(text) > capabilities.max_text_chars:
        raise ProviderRenderError("message_requires_upstream_pagination")
