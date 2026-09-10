from __future__ import annotations

from dataclasses import dataclass, replace
from typing import Any

from .contract import CallbackAckIntent, ProviderContext, ProviderScreen, adapt_screen
from .policy import (
    BALE_CAPABILITIES,
    DEFAULT_DENSITY_POLICY,
    actions_for_provider,
    callback_ack_intent,
    compose_plain_text,
    is_owner_management_surface,
    keyboard_payload,
    navigation_actions,
    pack_actions,
    protection_decision,
    resolve_delivery_intent,
    validate_message_text,
)


@dataclass(frozen=True)
class BaleRenderPlan:
    """Bale-native plan. It intentionally contains no Telegram-only fields."""

    text: str
    keyboard: tuple[tuple[dict[str, str], ...], ...]
    delivery_intent: str
    callback_ack: CallbackAckIntent
    can_deliver_original: bool
    failure_reason: str = ""
    edit_failure_policy: str = "not_applicable"
    business_replay_allowed: bool = False


def _safe_bold(text: str) -> str:
    # Bale formats outgoing text as Markdown. Use documented emphasis only when
    # dynamic content cannot accidentally become Markdown syntax.
    if not text or any(token in text for token in ("*", "_", "[", "]", "(", ")", "`")):
        return text
    return f"\u200f *{text}* \u200f"


def _bale_text(screen: ProviderScreen) -> str:
    groups: list[str] = [_safe_bold(screen.title)]
    if screen.context:
        groups.append(screen.context)
    if screen.intro:
        groups.append(screen.intro)
    if screen.facts:
        groups.append(
            "\n".join(
                f"• {fact.label}: {fact.value}" if fact.label else f"• {fact.value}"
                for fact in screen.facts
            )
        )
    if screen.list_items:
        groups.append("\n".join(f"• {item}" for item in screen.list_items))
    for section in screen.sections:
        parts: list[str] = []
        if section.title:
            parts.append(_safe_bold(section.title))
        if section.body:
            parts.append(section.body)
        parts.extend(f"• {item}" for item in section.items)
        if parts:
            groups.append("\n".join(parts))
    if screen.pagination:
        groups.append(screen.pagination)
    if screen.footer:
        groups.append(screen.footer)
    return "\n\n".join(group.strip() for group in groups if group.strip()).strip()


def _owner_unavailable(screen: ProviderScreen) -> ProviderScreen:
    return ProviderScreen(
        title="⚙️ مدیریت",
        semantic_kind="owner_management_provider_unavailable",
        intro="مدیریت سرور از بله انجام نمی‌شود. این بخش فقط در تلگرام خصوصی و پس از تأیید دسترسی مدیریتی در دسترس است.",
        severity="info",
        actions=navigation_actions(screen.actions),
        edit_policy="new_message",
    )


def _protected_unavailable(screen: ProviderScreen, failure_text: str) -> ProviderScreen:
    return ProviderScreen(
        title="🔒 ارسال محافظت‌شده",
        semantic_kind="protected_provider_unavailable",
        intro=failure_text,
        severity="warning",
        actions=navigation_actions(screen.actions),
        edit_policy="new_message",
    )


class BaleV3Renderer:
    capabilities = BALE_CAPABILITIES

    def render(
        self,
        source_screen: Any,
        *,
        context: ProviderContext | None = None,
    ) -> BaleRenderPlan:
        context = context or ProviderContext()
        screen = adapt_screen(source_screen)
        DEFAULT_DENSITY_POLICY.validate(screen)

        owner_surface = is_owner_management_surface(screen)
        if owner_surface:
            screen = _owner_unavailable(screen)
            screen = replace(
                screen,
                actions=actions_for_provider(screen, context, "bale"),
            )
            rows = pack_actions(screen.actions, self.capabilities)
            text = _bale_text(screen)
            validate_message_text(text, self.capabilities)
            return BaleRenderPlan(
                text=text,
                keyboard=keyboard_payload(rows),
                delivery_intent="new_message",
                callback_ack=callback_ack_intent(context),
                can_deliver_original=False,
                failure_reason="owner_management_telegram_only",
                edit_failure_policy="not_applicable",
            )

        protection = protection_decision(screen, self.capabilities)
        if screen.protect_content and not protection.can_deliver_original:
            fail_screen = _protected_unavailable(screen, protection.failure_text)
            fail_screen = replace(
                fail_screen,
                actions=actions_for_provider(fail_screen, context, "bale"),
            )
            rows = pack_actions(fail_screen.actions, self.capabilities)
            text = _bale_text(fail_screen)
            validate_message_text(text, self.capabilities)
            return BaleRenderPlan(
                text=text,
                keyboard=keyboard_payload(rows),
                delivery_intent="new_message",
                callback_ack=callback_ack_intent(context),
                can_deliver_original=False,
                failure_reason="forward_protection_unavailable",
                edit_failure_policy="not_applicable",
            )

        screen = replace(
            screen,
            actions=actions_for_provider(screen, context, "bale"),
        )
        rows = pack_actions(screen.actions, self.capabilities)
        text = _bale_text(screen) or compose_plain_text(screen)
        validate_message_text(text, self.capabilities)
        delivery = resolve_delivery_intent(screen, context, self.capabilities)
        return BaleRenderPlan(
            text=text,
            keyboard=keyboard_payload(rows),
            delivery_intent=delivery,
            callback_ack=callback_ack_intent(context),
            can_deliver_original=True,
            edit_failure_policy=(
                "new_message_same_screen_once"
                if delivery == "edit_if_safe"
                else "not_applicable"
            ),
        )
