from __future__ import annotations

from typing import Any, Callable

from . import class_operations as classops
from .app import DentBotApp
from .bot_home_classops_ux_v2 import (
    _handle_v2_callback,
    _owner,
    notification_status_screen,
    owner_classops_screen,
)
from .persian_datetime import to_persian_digits
from .state import BotState
from .ui import Screen, button, keyboard

_INSTALLED = False


def _callback_data(callback: dict[str, Any]) -> tuple[dict[str, Any], int, int, str]:
    callback = dict(callback or {})
    message = dict(callback.get("message") or {})
    sender = dict(callback.get("from") or message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        return callback, 0, 0, ""
    raw = str(callback.get("data") or "")
    return callback, chat_id, user_id, raw[3:] if raw.startswith("v1:") else raw


def _owner_confirmation_screen(token: str, verb: str) -> Screen:
    label = "لغو" if verb == "cancel" else "بایگانی"
    return Screen(
        f"<b>⚠️ تأیید {label}</b>\n\n"
        "این تغییر روی آیتم canonical اعمال می‌شود. فقط با دکمه تأیید نهایی اجرا خواهد شد.",
        keyboard(
            [button(f"تأیید {label}", action=token, style="danger")],
            [button("↩️ انصراف", action="classops-v2:future:0")],
            [button("🏠 خانه", action="home")],
        ),
    )


def _notification_status_with_ack_screen(status: dict[str, Any], ack_payload: dict[str, Any]) -> Screen:
    base = notification_status_screen(status)
    ack = dict(ack_payload.get("ack") or {})
    eligible = int(ack.get("eligible") or 0)
    acked = int(ack.get("acked") or 0)
    pending = int(ack.get("pending") or 0)
    notice_count = int(ack.get("noticeCount") or 0)
    lines = [str(base.text), "", "<b>تأیید اطلاعیه‌های مهم</b>"]
    if notice_count == 0:
        lines.append("⚪️ اطلاعیه فعالی که نیازمند تأیید باشد وجود ندارد.")
    else:
        lines.append(f"✅ تأییدشده: <b>{to_persian_digits(acked)}</b> از {to_persian_digits(eligible)}")
        lines.append(f"🟡 در انتظار تأیید: <b>{to_persian_digits(pending)}</b>")
        lines.append(f"• اطلاعیه‌های مشمول: {to_persian_digits(notice_count)}")
    return Screen("\n".join(lines), base.keyboard)


def install_bot_home_classops_ux_v2_compat(*, base_callback: Callable[..., Any]) -> None:
    """Install the v2 runtime bridge over the current DentBot callback contract."""
    # The v2 message wrapper shipped with a stale reader name. Preserve the
    # canonical BotState implementation and expose only a compatibility alias.
    if not hasattr(BotState, "get_dialog"):
        setattr(BotState, "get_dialog", BotState.dialog)

    global _INSTALLED
    if _INSTALLED:
        return
    _INSTALLED = True

    previous_detail = classops._detail_screen

    def detail_with_owner_back(item: dict[str, Any], actions: dict[str, Any]) -> Screen:
        screen = previous_detail(item, actions)
        owner_actions = any(str(actions.get(key) or "").startswith("cxo_") for key in ("cancel", "archive"))
        if not owner_actions:
            return screen
        rows: list[list[dict[str, Any]]] = []
        for row in dict(screen.keyboard or {}).get("inline_keyboard", []):
            replaced: list[dict[str, Any]] = []
            for entry in row:
                current = dict(entry)
                raw = str(current.get("callback_data") or "")
                action = raw[3:] if raw.startswith("v1:") else raw
                if action == "class-operations:list:all":
                    current["callback_data"] = "v1:classops-v2:future:0"
                    current["text"] = "↩️ آیتم‌های آینده"
                replaced.append(current)
            rows.append(replaced)
        return Screen(screen.text, {"inline_keyboard": rows})

    classops._detail_screen = detail_with_owner_back

    def callback_with_stale_owner_guard(
        self: DentBotApp,
        callback: dict[str, Any],
        *,
        interaction_version: int | None = None,
    ) -> Any:
        callback, chat_id, user_id, data = _callback_data(callback)

        def original_from_update(app: DentBotApp, update: dict[str, Any]) -> Any:
            payload = dict(update.get("callback_query") or {})
            return base_callback(app, payload, interaction_version=interaction_version)

        def dispatch_v2_or_base() -> Any:
            update = {"callback_query": callback}
            if _handle_v2_callback(self, update, original_from_update):
                return None
            return base_callback(self, callback, interaction_version=interaction_version)

        if data == "class-operations:owner":
            if chat_id == 0 or user_id == 0:
                return None
            if not _owner(self, user_id):
                return dispatch_v2_or_base()
            try:
                capabilities = self.site_api.request("classopsCapabilities", user_id)
            except Exception:
                return dispatch_v2_or_base()
            if str(capabilities.get("role") or "") != "owner":
                return dispatch_v2_or_base()
            callback_id = str(callback.get("id") or "")
            if callback_id:
                try:
                    self.api.answer_callback(callback_id)
                except Exception:
                    pass
            classops._render_screen(self, chat_id, owner_classops_screen(), callback=callback)
            return None

        if data == "classops-v2:notification-status" and _owner(self, user_id):
            try:
                status = self.site_api.request("classopsNotificationStatus", user_id)
                ack_status = self.site_api.request("classopsAckStatusV2", user_id)
            except Exception:
                return dispatch_v2_or_base()
            callback_id = str(callback.get("id") or "")
            if callback_id:
                try:
                    self.api.answer_callback(callback_id)
                except Exception:
                    pass
            classops._render_screen(
                self,
                chat_id,
                _notification_status_with_ack_screen(status, ack_status),
                callback=callback,
            )
            return None

        if data.startswith("classops-v2:confirm-cancel:cxo_") or data.startswith("classops-v2:confirm-archive:cxo_"):
            if chat_id == 0 or user_id == 0 or not _owner(self, user_id):
                return dispatch_v2_or_base()
            verb = "cancel" if ":confirm-cancel:" in data else "archive"
            token = data.rsplit(":", 1)[-1]
            callback_id = str(callback.get("id") or "")
            if callback_id:
                try:
                    self.api.answer_callback(callback_id)
                except Exception:
                    pass
            classops._render_screen(self, chat_id, _owner_confirmation_screen(token, verb), callback=callback)
            return None

        return dispatch_v2_or_base()

    DentBotApp._callback = callback_with_stale_owner_guard
