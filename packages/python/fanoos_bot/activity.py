from __future__ import annotations

import os
import secrets
import threading
from contextlib import AbstractContextManager
from typing import Any


def _env_flag(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    value = raw.strip().lower()
    if value in {"1", "true", "yes", "on"}:
        return True
    if value in {"0", "false", "no", "off"}:
        return False
    return default


class ActivitySession(AbstractContextManager["ActivitySession"]):
    """Presentation-only activity for a synchronous operation.

    A ChatAction is emitted immediately. If the same synchronous operation is
    still active after the threshold, Telegram can promote the one indicator to
    a Rich Thinking draft. No percentage or ETA is synthesized.
    """

    def __init__(
        self,
        transport: Any,
        chat_id: str,
        *,
        private: bool,
        start_after: float = 3.0,
        heartbeat: float = 10.0,
    ):
        self.transport = transport
        self.chat_id = chat_id
        self.private = private
        self.start_after = max(0.01, float(start_after))
        self.heartbeat = max(1.0, float(heartbeat))
        self._lock = threading.Lock()
        self._closed = False
        self._timer: threading.Timer | None = None
        self._draft_id = secrets.randbelow(2_147_483_646) + 1

    def _chat_action(self) -> None:
        method = getattr(self.transport, "chat_action", None)
        if not callable(method):
            return
        try:
            method(self.chat_id, "typing")
        except Exception:
            pass

    def _schedule(self, delay: float) -> None:
        with self._lock:
            if self._closed:
                return
            timer = threading.Timer(delay, self._tick)
            timer.daemon = True
            self._timer = timer
            timer.start()

    def _tick(self) -> None:
        with self._lock:
            if self._closed:
                return
        used_draft = False
        draft = getattr(self.transport, "send_rich_draft", None)
        if self.private and callable(draft):
            try:
                used_draft = bool(
                    draft(
                        self.chat_id,
                        self._draft_id,
                        "در حال پردازش…",
                    )
                )
            except Exception:
                used_draft = False
        if not used_draft:
            self._chat_action()
        self._schedule(self.heartbeat if used_draft else min(5.0, self.heartbeat))

    def __enter__(self) -> "ActivitySession":
        self._chat_action()
        self._schedule(self.start_after)
        return self

    def close(self) -> None:
        with self._lock:
            self._closed = True
            timer = self._timer
            self._timer = None
        if timer is not None:
            timer.cancel()

    def __exit__(self, exc_type, exc, tb) -> None:
        self.close()
        return None


class ActivityController:
    def __init__(
        self,
        transport: Any,
        *,
        enabled: bool | None = None,
        start_after: float = 3.0,
        heartbeat: float = 10.0,
    ):
        self.transport = transport
        platform = str(getattr(getattr(transport, "capabilities", None), "platform", ""))
        default = True
        if platform == "telegram":
            default = _env_flag("FANOOS_TELEGRAM_ACTIVITY_UI_ENABLED", True)
        transport_enabled = bool(getattr(transport, "activity_ui_enabled", True))
        self.enabled = transport_enabled and (default if enabled is None else bool(enabled))
        self.start_after = start_after
        self.heartbeat = heartbeat

    def operation(self, chat_id: str, *, private: bool) -> AbstractContextManager[Any]:
        if not self.enabled:
            return _NullActivity()
        return ActivitySession(
            self.transport,
            chat_id,
            private=private,
            start_after=self.start_after,
            heartbeat=self.heartbeat,
        )


class _NullActivity(AbstractContextManager["_NullActivity"]):
    def __enter__(self) -> "_NullActivity":
        return self

    def __exit__(self, exc_type, exc, tb) -> None:
        return None
