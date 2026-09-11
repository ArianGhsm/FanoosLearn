from __future__ import annotations

from .state import BotState


KEYBOARD_CLEANUP_VERSION = "reply-keyboard-v2"


class KeyboardInvariantApi:
    """Persist and enforce reply-keyboard cleanup across bot releases."""

    def __init__(self, delegate, state: BotState) -> None:
        self._delegate = delegate
        self._state = state

    def __getattr__(self, name: str):
        return getattr(self._delegate, name)

    @staticmethod
    def _has_reply_keyboard(reply_markup: dict | None) -> bool:
        return isinstance(reply_markup, dict) and isinstance(reply_markup.get("keyboard"), list)

    def _ensure_removed(self, chat_id: int) -> None:
        if not self._state.reply_keyboard_needs_removal(chat_id, KEYBOARD_CLEANUP_VERSION):
            return
        remove = getattr(self._delegate, "remove_reply_keyboard", None)
        if callable(remove):
            remove(chat_id)
        # Minimal unit-test doubles may omit the transport method. Production
        # adapters both implement it and have an explicit transport test.
        self._state.mark_reply_keyboard_removed(chat_id, KEYBOARD_CLEANUP_VERSION)

    def send(self, chat_id: int, text: str, reply_markup: dict) -> dict:
        if self._has_reply_keyboard(reply_markup):
            result = dict(self._delegate.send(chat_id, text, reply_markup) or {})
            self._state.mark_reply_keyboard_active(chat_id)
            return result
        self._ensure_removed(chat_id)
        return dict(self._delegate.send(chat_id, text, reply_markup) or {})

    def edit(self, chat_id: int, message_id: int, text: str, reply_markup: dict) -> dict:
        if not self._has_reply_keyboard(reply_markup):
            self._ensure_removed(chat_id)
        result = dict(self._delegate.edit(chat_id, message_id, text, reply_markup) or {})
        if self._has_reply_keyboard(reply_markup):
            self._state.mark_reply_keyboard_active(chat_id)
        return result

    def remove_reply_keyboard(self, chat_id: int, text: str = "") -> dict:
        # Callers may defensively request cleanup on every /start or /menu.
        # Once this cleanup version is durably recorded, repeating the transport
        # call would only create another misleading "authentication completed"
        # message. Keep the operation truly idempotent across restarts.
        if not self._state.reply_keyboard_needs_removal(chat_id, KEYBOARD_CLEANUP_VERSION):
            return {}
        remove = getattr(self._delegate, "remove_reply_keyboard", None)
        if not callable(remove):
            result = {}
        elif text:
            result = dict(remove(chat_id, text=text) or {})
        else:
            result = dict(remove(chat_id) or {})
        self._state.mark_reply_keyboard_removed(chat_id, KEYBOARD_CLEANUP_VERSION)
        return result
