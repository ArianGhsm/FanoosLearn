from __future__ import annotations

import html
from dataclasses import dataclass, replace
from typing import Any

from .contract import (
    CallbackAckIntent,
    ProviderContext,
    ProviderScreen,
    adapt_screen,
)
from .policy import (
    DEFAULT_DENSITY_POLICY,
    TELEGRAM_CAPABILITIES,
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
class TelegramRenderPlan:
    """Transport intent only; chat/message identifiers are supplied later."""

    plain_text: str
    rich_message: dict[str, Any] | None
    keyboard: tuple[tuple[dict[str, str], ...], ...]
    delivery_intent: str
    callback_ack: CallbackAckIntent
    protect_content: bool
    atomic: bool
    render_failure_policy: str
    edit_failure_policy: str
    business_replay_allowed: bool = False


def _escape(value: str) -> str:
    return html.escape(value, quote=True)


def _rich_text(value: str) -> str:
    return "<br>".join(_escape(line) for line in value.splitlines())


def _rich_html(screen: ProviderScreen) -> str:
    blocks: list[str] = [f"<h3>{_escape(screen.title)}</h3>"]

    if screen.context:
        blocks.append(f"<p>{_rich_text(screen.context)}</p>")
    if screen.intro:
        intro = _rich_text(screen.intro)
        if screen.severity in {"warning", "error", "success"}:
            blocks.append(f"<blockquote>{intro}</blockquote>")
        else:
            blocks.append(f"<p>{intro}</p>")

    if screen.facts:
        items = []
        for fact in screen.facts:
            if fact.label:
                items.append(f"<li><b>{_escape(fact.label)}:</b> {_escape(fact.value)}</li>")
            else:
                items.append(f"<li>{_escape(fact.value)}</li>")
        blocks.append("<ul>" + "".join(items) + "</ul>")

    if screen.list_items:
        blocks.append(
            "<ul>"
            + "".join(f"<li>{_escape(item)}</li>" for item in screen.list_items)
            + "</ul>"
        )

    for section in screen.sections:
        if section.title:
            blocks.append(f"<h4>{_escape(section.title)}</h4>")
        if section.body:
            blocks.append(f"<p>{_rich_text(section.body)}</p>")
        if section.items:
            blocks.append(
                "<ul>"
                + "".join(f"<li>{_escape(item)}</li>" for item in section.items)
                + "</ul>"
            )

    if screen.pagination:
        blocks.append(f"<p>{_escape(screen.pagination)}</p>")
    if screen.footer:
        blocks.append(f"<p>{_rich_text(screen.footer)}</p>")
    return "".join(blocks)


def _owner_denied(screen: ProviderScreen) -> ProviderScreen:
    return ProviderScreen(
        title="⚙️ مدیریت",
        semantic_kind="owner_management_denied",
        intro="این بخش فقط در گفت‌وگوی خصوصی تلگرام و پس از تأیید دسترسی مدیریتی در دسترس است.",
        severity="warning",
        actions=navigation_actions(screen.actions),
        edit_policy="new_message",
        rtl=True,
    )


class TelegramV3Renderer:
    capabilities = TELEGRAM_CAPABILITIES

    def render(
        self,
        source_screen: Any,
        *,
        context: ProviderContext | None = None,
    ) -> TelegramRenderPlan:
        context = context or ProviderContext()
        screen = adapt_screen(source_screen)
        DEFAULT_DENSITY_POLICY.validate(screen)

        if is_owner_management_surface(screen) and not context.can_manage_deployments:
            screen = _owner_denied(screen)

        screen = replace(
            screen,
            actions=actions_for_provider(screen, context, "telegram"),
        )
        rows = pack_actions(screen.actions, self.capabilities)
        keyboard = keyboard_payload(rows)
        protection = protection_decision(screen, self.capabilities)
        plain = compose_plain_text(screen)
        validate_message_text(plain, self.capabilities)
        ack = callback_ack_intent(context)

        if screen.protect_content:
            # Protection-sensitive content is one exact provider operation.
            # There is no Rich→plain second-send fallback after ambiguity.
            return TelegramRenderPlan(
                plain_text=plain,
                rich_message=None,
                keyboard=keyboard,
                delivery_intent="new_message",
                callback_ack=ack,
                protect_content=True,
                atomic=protection.atomic,
                render_failure_policy="no_second_provider_operation",
                edit_failure_policy="not_applicable",
            )

        rich_html = _rich_html(screen)
        if len(rich_html) > self.capabilities.max_rich_chars:
            rich_message = None
            render_failure = "plain_same_screen"
        else:
            rich_message = {"html": rich_html, "is_rtl": bool(screen.rtl)}
            render_failure = "plain_same_screen_once"

        delivery = resolve_delivery_intent(screen, context, self.capabilities)
        return TelegramRenderPlan(
            plain_text=plain,
            rich_message=rich_message,
            keyboard=keyboard,
            delivery_intent=delivery,
            callback_ack=ack,
            protect_content=False,
            atomic=False,
            render_failure_policy=render_failure,
            edit_failure_policy=(
                "new_message_same_screen_once"
                if delivery == "edit_if_safe"
                else "not_applicable"
            ),
        )
