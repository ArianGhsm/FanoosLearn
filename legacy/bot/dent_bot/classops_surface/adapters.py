from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Mapping

from .model import ActionIntent, build_intent
from .render import SurfaceView


@dataclass(frozen=True)
class RenderedSurface:
    platform: str
    text: str
    keyboard: dict[str, Any]
    semantic_actions: tuple[str, ...]


def _callback_key(action: str) -> str:
    # This legacy semantic adapter never embeds identity, revision, payload or
    # secret data in callback_data. Sensitive real runtime actions use opaque
    # server-issued cxo_* references from classops_runtime.py.
    key = "classops:" + action
    if len(key.encode("utf-8")) > 64:
        raise ValueError("ClassOps callback key exceeds transport-safe length")
    return key


class BaseAdapter:
    platform = "base"
    supports_button_style = False

    def render(self, view: SurfaceView) -> RenderedSurface:
        rows: list[list[dict[str, Any]]] = []
        semantic: list[str] = []
        disabled_notes: list[str] = []
        for button in view.buttons:
            payload: dict[str, Any] = {"text": button.label}
            if button.enabled:
                payload["callback_data"] = _callback_key(button.action)
                semantic.append(button.action)
            else:
                # Telegram/Bale do not have a portable disabled-inline-button
                # primitive. Use one inert generic callback and render the reason
                # as text; never inject non-Bot-API fields into the button.
                payload["callback_data"] = _callback_key("disabled")
                if button.disabled_reason:
                    disabled_notes.append(f"• {button.label}: {button.disabled_reason}")
            if self.supports_button_style and button.style != "default":
                payload["style"] = button.style
            rows.append([payload])
        text = f"{view.title}\n\n{view.text}".strip()
        if disabled_notes:
            text += "\n\nقابلیت‌های در دسترس‌نبودن:\n" + "\n".join(disabled_notes)
        return RenderedSurface(
            platform=self.platform,
            text=text,
            keyboard={"inline_keyboard": rows},
            semantic_actions=tuple(semantic),
        )


class TelegramAdapter(BaseAdapter):
    platform = "telegram"
    supports_button_style = True


class BaleAdapter(BaseAdapter):
    platform = "bale"
    # Bale fallback deliberately omits Telegram-optional style metadata. Copy,
    # semantic action identity and confirmation rules remain identical.
    supports_button_style = False


def intent_from_callback(
    callback_data: str,
    *,
    actor_role: str,
    item_id: str | None = None,
    expected_revision: int | None = None,
    payload: Mapping[str, Any] | None = None,
    nonce: str,
) -> ActionIntent:
    prefix = "classops:"
    if not callback_data.startswith(prefix):
        raise ValueError("not a ClassOps callback")
    action = callback_data[len(prefix):]
    if action == "disabled":
        raise ValueError("disabled ClassOps action cannot produce an intent")
    return build_intent(
        action,
        actor_role,
        item_id=item_id,
        expected_revision=expected_revision,
        payload=payload,
        nonce=nonce,
        confirmed=False,
    )
