from __future__ import annotations

import json
import mimetypes
import os
import re
import secrets
from pathlib import Path
from typing import Any
from urllib import error, request

from .capabilities import PlatformCapabilities
from .models import Screen
from .telegram_presentation import TelegramPresentation
from .ui_v3.providers import BaleV3Renderer, ProviderContext, TelegramV3Renderer
from .ui_v3.providers.core_adapter import provider_screen


class BotApiError(RuntimeError):
    def __init__(self, code: str = "bot_api_error", *, retry_after: float | None = None, transient: bool = False, description: str = ""):
        super().__init__(code)
        self.code = code
        self.retry_after = retry_after
        self.transient = transient
        self.description = description


def _env_flag(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    value = raw.strip().lower()
    if value in {"1", "true", "yes", "on"}: return True
    if value in {"0", "false", "no", "off"}: return False
    return default


class JsonBotApiTransport:
    # Runtime checks this marker before passing provider-only presentation facts.
    supports_v3_context = True

    def __init__(self, endpoint: str, token: str, capabilities: PlatformCapabilities, *, timeout: float = 15, rich_ui_enabled: bool | None = None, activity_ui_enabled: bool | None = None):
        if not token or any(c.isspace() for c in token):
            raise ValueError("bot token missing")
        self.endpoint = endpoint.rstrip("/")
        self.token = token
        self.capabilities = capabilities
        self.timeout = timeout
        self.activity_ui_enabled = True if activity_ui_enabled is None else bool(activity_ui_enabled)
        self.rich_ui_enabled = _env_flag("FANOOS_TELEGRAM_RICH_UI_ENABLED", True) if rich_ui_enabled is None else bool(rich_ui_enabled)
        self.presentation = TelegramV3Renderer() if capabilities.platform == "telegram" else BaleV3Renderer()

    def _url(self, method: str) -> str:
        return f"{self.endpoint}/bot{self.token}/{method}"

    @staticmethod
    def _api_error(data: Any) -> BotApiError:
        params = data.get("parameters") if isinstance(data, dict) else {}
        retry = params.get("retry_after") if isinstance(params, dict) else None
        error_code = data.get("error_code") if isinstance(data, dict) else None
        description = str(data.get("description") or "") if isinstance(data, dict) else ""
        try: numeric = int(error_code or 0)
        except (TypeError, ValueError): numeric = 0
        return BotApiError(
            str(error_code or "bot_api_error"),
            retry_after=float(retry) if isinstance(retry, (int, float)) else None,
            transient=bool(retry) or numeric >= 500,
            description=description,
        )

    def _call(self, method: str, payload: dict[str, Any] | None = None) -> Any:
        request_timeout = self.timeout
        if method == "getUpdates" and isinstance(payload, dict):
            long_poll_timeout = payload.get("timeout")
            if isinstance(long_poll_timeout, (int, float)) and long_poll_timeout >= 0:
                request_timeout = max(self.timeout, float(long_poll_timeout) + 5)
        body = json.dumps(payload or {}, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        req = request.Request(self._url(method), data=body, headers={"Content-Type": "application/json", "Accept": "application/json"}, method="POST")
        data: Any
        try:
            with request.urlopen(req, timeout=request_timeout) as res:
                data = json.loads(res.read().decode("utf-8"))
        except error.HTTPError as exc:
            try: data = json.loads(exc.read().decode("utf-8"))
            except Exception: data = {"error_code": exc.code, "description": str(exc.reason or "")}
        except (error.URLError, TimeoutError, OSError) as exc:
            raise BotApiError("network_unavailable", transient=True) from exc
        if not isinstance(data, dict) or data.get("ok") is not True:
            raise self._api_error(data)
        return data.get("result")

    def _multipart_call(self, method: str, body: bytes, boundary: str) -> Any:
        req = request.Request(
            self._url(method), data=body,
            headers={"Content-Type": f"multipart/form-data; boundary={boundary}", "Accept": "application/json"},
            method="POST",
        )
        data: Any
        try:
            with request.urlopen(req, timeout=self.timeout) as res:
                data = json.loads(res.read().decode("utf-8"))
        except error.HTTPError as exc:
            try: data = json.loads(exc.read().decode("utf-8"))
            except Exception: data = {"error_code": exc.code, "description": str(exc.reason or "")}
        except (error.URLError, TimeoutError, OSError) as exc:
            raise BotApiError("network_unavailable", transient=True) from exc
        if not isinstance(data, dict) or data.get("ok") is not True:
            raise self._api_error(data)
        return data.get("result")

    def get_me(self): return self._call("getMe")

    def get_updates(self, offset: int, timeout: int = 20):
        return self._call("getUpdates", {"offset": offset, "timeout": timeout, "allowed_updates": ["message", "callback_query"]})

    def answer_callback(self, callback_id: str, text: str = ""):
        payload: dict[str, Any] = {"callback_query_id": callback_id}
        if text: payload["text"] = text
        return self._call("answerCallbackQuery", payload)

    def chat_action(self, chat_id: str, action: str = "typing"):
        if not self.capabilities.supports_chat_action: return False
        return self._call("sendChatAction", {"chat_id": chat_id, "action": action})

    def send_rich_draft(self, chat_id: str, draft_id: int, text: str) -> bool:
        if self.capabilities.platform != "telegram" or not self.capabilities.supports_rich_draft or not self.rich_ui_enabled or not self.activity_ui_enabled:
            return False
        try: numeric_chat_id = int(chat_id)
        except (TypeError, ValueError): return False
        if draft_id == 0: raise ValueError("draft_id must be non-zero")
        escaped = str(text).replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
        return bool(self._call("sendRichMessageDraft", {
            "chat_id": numeric_chat_id,
            "draft_id": int(draft_id),
            "rich_message": {"html": f"<tg-thinking>{escaped}</tg-thinking>", "is_rtl": True},
            "can_stop": False,
        }))

    @staticmethod
    def _markup(keyboard: tuple[tuple[dict[str, str], ...], ...]):
        if not keyboard: return None
        return {"inline_keyboard": [[dict(item) for item in row] for row in keyboard]}

    def _ensure_keyboard_supported(self, keyboard: tuple[tuple[dict[str, str], ...], ...]) -> None:
        if keyboard and not self.capabilities.supports_inline_callback:
            raise BotApiError("inline_keyboard_unsupported")
        for row in keyboard:
            for item in row:
                callback = item.get("callback_data")
                if callback is None:
                    continue
                callback_bytes = len(str(callback).encode("utf-8"))
                if not 1 <= callback_bytes <= self.capabilities.max_callback_bytes:
                    raise BotApiError("callback_too_large")

    def _apply_reply(self, payload: dict[str, Any], reply_to: int | None) -> None:
        if reply_to is None: return
        if self.capabilities.reply_style == "reply_to_message_id": payload["reply_to_message_id"] = reply_to
        else: payload["reply_parameters"] = {"message_id": reply_to}

    def _plain_payload(self, chat_id: str, text: str, *, reply_to: int | None = None, markup: dict[str, Any] | None = None, protect_content: bool = False) -> dict[str, Any]:
        payload: dict[str, Any] = {"chat_id": chat_id, "text": text}
        if markup: payload["reply_markup"] = markup
        if protect_content: payload["protect_content"] = True
        self._apply_reply(payload, reply_to)
        return payload

    def _rich_payload(self, chat_id: str, rich_message: dict[str, Any], *, reply_to: int | None = None, markup: dict[str, Any] | None = None, protect_content: bool = False) -> dict[str, Any]:
        payload: dict[str, Any] = {"chat_id": chat_id, "rich_message": rich_message}
        if markup: payload["reply_markup"] = markup
        if protect_content: payload["protect_content"] = True
        self._apply_reply(payload, reply_to)
        return payload

    @staticmethod
    def _is_v3_envelope(screen: Screen) -> bool:
        return getattr(screen, "v3", None) is not None

    def _render(self, screen: Screen, context: ProviderContext | None):
        source = screen.v3 if self._is_v3_envelope(screen) else screen
        return self.presentation.render(provider_screen(source), context=context or ProviderContext())

    def _send_legacy_protected_telegram(self, chat_id: str, screen: Screen, *, reply_to: int | None = None):
        """Preserve the accepted Stage 7 transport contract during V3 migration.

        A legacy protection-sensitive result has already been computed by the
        canonical application. It may use one protected Rich provider operation,
        but it never receives a second send fallback after an ambiguous attempt.
        Canonical V3 envelopes continue through TelegramV3Renderer's stricter
        protected plan.
        """
        rendered = TelegramPresentation(enabled=self.rich_ui_enabled).render(screen)
        keyboard = tuple(
            tuple(
                {"text": button.text, **({"callback_data": str(button.callback)} if button.callback is not None else {"url": str(button.url)})}
                for button in row
            )
            for row in screen.rows
        )
        self._ensure_keyboard_supported(keyboard)
        markup = self._markup(keyboard)
        if rendered.rich_message is not None and self.capabilities.supports_native_rich:
            return self._call(
                "sendRichMessage",
                self._rich_payload(
                    chat_id,
                    rendered.rich_message,
                    reply_to=reply_to,
                    markup=markup,
                    protect_content=True,
                ),
            )
        return self._call(
            "sendMessage",
            self._plain_payload(
                chat_id,
                rendered.plain_text,
                reply_to=reply_to,
                markup=markup,
                protect_content=True,
            ),
        )

    def send_screen(self, chat_id: str, screen: Screen, *, reply_to: int | None = None, context: ProviderContext | None = None):
        is_v3 = self._is_v3_envelope(screen)
        if not is_v3 and screen.protect_content:
            if not self.capabilities.supports_forward_protection:
                raise BotApiError("forward_protection_unsupported")
            if self.capabilities.platform == "telegram":
                return self._send_legacy_protected_telegram(chat_id, screen, reply_to=reply_to)

        plan = self._render(screen, context)
        self._ensure_keyboard_supported(plan.keyboard)
        markup = self._markup(plan.keyboard)
        if self.capabilities.platform == "telegram":
            text = plan.plain_text
            if len(text) > self.capabilities.max_text_chars:
                raise ValueError("screen must be paginated before transport")
            if plan.protect_content and not self.capabilities.supports_forward_protection:
                raise BotApiError("forward_protection_unsupported")
            # Canonical V3 protected content is exactly one protected provider operation.
            if plan.protect_content:
                return self._call("sendMessage", self._plain_payload(
                    chat_id, text, reply_to=reply_to, markup=markup, protect_content=True,
                ))
            if self.rich_ui_enabled and self.capabilities.supports_native_rich and plan.rich_message is not None:
                try:
                    return self._call("sendRichMessage", self._rich_payload(
                        chat_id, plan.rich_message, reply_to=reply_to, markup=markup,
                    ))
                except BotApiError:
                    # Presentation fallback only; application/business is not rerun.
                    pass
            return self._call("sendMessage", self._plain_payload(chat_id, text, reply_to=reply_to, markup=markup))

        # Bale renderer has already transformed V3 protected/owner-ineligible
        # screens into provider-native fail-closed output. Legacy protected
        # originals were refused above before any network call.
        text = plan.text
        if len(text) > self.capabilities.max_text_chars:
            raise ValueError("screen must be paginated before transport")
        return self._call("sendMessage", self._plain_payload(chat_id, text, reply_to=reply_to, markup=markup))

    def edit_screen(self, chat_id: str, message_id: int, screen: Screen, *, context: ProviderContext | None = None):
        context = context or ProviderContext(current_message_id=message_id)
        plan = self._render(screen, context)
        self._ensure_keyboard_supported(plan.keyboard)
        if self.capabilities.platform == "telegram":
            if plan.protect_content or plan.delivery_intent == "new_message" or not self.capabilities.supports_edit:
                return self.send_screen(chat_id, screen, context=context)
            markup = self._markup(plan.keyboard)
            if self.rich_ui_enabled and self.capabilities.supports_rich_edit and plan.rich_message is not None:
                try:
                    return self._call("editMessageText", {
                        "chat_id": chat_id,
                        "message_id": message_id,
                        "rich_message": plan.rich_message,
                        **({"reply_markup": markup} if markup else {}),
                    })
                except BotApiError:
                    pass
            try:
                payload: dict[str, Any] = {"chat_id": chat_id, "message_id": message_id, "text": plan.plain_text}
                if markup: payload["reply_markup"] = markup
                return self._call("editMessageText", payload)
            except BotApiError:
                return self.send_screen(chat_id, screen, context=context)

        if plan.delivery_intent == "new_message" or not self.capabilities.supports_edit or not plan.can_deliver_original:
            return self.send_screen(chat_id, screen, context=context)
        markup = self._markup(plan.keyboard)
        try:
            payload = {"chat_id": chat_id, "message_id": message_id, "text": plan.text}
            if markup: payload["reply_markup"] = markup
            return self._call("editMessageText", payload)
        except BotApiError:
            return self.send_screen(chat_id, screen, context=context)

    def send_reply_keyboard(
        self,
        chat_id: str,
        html_text: str,
        keyboard_rows: tuple[tuple[dict[str, Any], ...], ...],
        *,
        placeholder: str = "",
        reply_to: int | None = None,
    ):
        """Send a persistent reply keyboard, ported from the legacy bot's
        onboarding.py reply_keyboard() shape. This is a deliberate exception to
        the rest of the transport, which only ever emits inline keyboards
        (see _markup): the join wizard is the one flow the owner asked to keep
        as a native reply keyboard rather than ui_v3 inline screens.

        `html_text` is always Telegram-HTML (<b>/<u>/<code>/<blockquote>), the
        same style legacy's onboarding.py used. On Telegram it is sent as a
        native Rich Message (the same mechanism send_screen already uses for
        everything else), which supports that markup directly. Bale has no
        HTML rich-message equivalent, so its plain-text single-asterisk-bold
        convention (join_wizard.html_to_bale_text, matching
        ui_v3/providers/bale.py's own _safe_bold) is used instead.
        """
        markup: dict[str, Any] = {
            "keyboard": [[dict(item) for item in row] for row in keyboard_rows],
            "resize_keyboard": True,
            "one_time_keyboard": False,
        }
        if placeholder:
            markup["input_field_placeholder"] = placeholder[:64]
        if self.capabilities.platform == "telegram" and self.capabilities.supports_native_rich:
            return self._call("sendRichMessage", self._rich_payload(
                chat_id, {"html": html_text, "is_rtl": True}, reply_to=reply_to, markup=markup,
            ))
        from .join_wizard import html_to_bale_text

        text = html_to_bale_text(html_text)
        return self._call("sendMessage", self._plain_payload(chat_id, text, reply_to=reply_to, markup=markup))

    def remove_reply_keyboard(self, chat_id: str, text: str = "⌨️") -> dict:
        """Remove a persistent reply keyboard before returning to the rest of
        the (inline-keyboard) bot. Telegram and Bale keep a reply keyboard
        client-side until a later sendMessage explicitly carries
        remove_keyboard -- an inline keyboard on a newer message does not
        replace it. Ported verbatim (payload shape and the immediate cleanup
        delete) from the legacy bot's TelegramBotApi.remove_reply_keyboard
        (legacy/bot/dent_bot/api.py), which both Telegram and Bale used
        unchanged: send a message carrying remove_keyboard, then delete that
        transport-only message immediately so it doesn't linger as a stray
        "⌨️" in the chat history.
        """
        result = self._call("sendMessage", self._plain_payload(chat_id, text, markup={"remove_keyboard": True}))
        message_id = 0
        if isinstance(result, dict):
            try:
                message_id = int(result.get("message_id") or 0)
            except (TypeError, ValueError):
                message_id = 0
        if message_id > 0:
            try:
                self._call("deleteMessage", {"chat_id": chat_id, "message_id": message_id})
            except BotApiError:
                pass
        return result if isinstance(result, dict) else {}

    @staticmethod
    def _safe_filename(path: Path) -> str:
        stem = re.sub(r"[^A-Za-z0-9_-]+", "_", path.stem).strip("_")
        suffix = re.sub(r"[^A-Za-z0-9.]+", "", path.suffix)[:12]
        return ((stem or "document") + suffix)[:96]

    def send_document(self, chat_id: str, path: str | Path, *, caption: str = "", protect_content: bool = False):
        path = Path(path)
        size = path.stat().st_size
        if size > self.capabilities.max_document_upload_bytes: raise BotApiError("file_too_large")
        if len(caption) > self.capabilities.max_caption_chars: raise BotApiError("caption_too_large")
        if protect_content and not self.capabilities.supports_forward_protection: raise BotApiError("forward_protection_unsupported")
        boundary = "----fanoos" + secrets.token_hex(12)
        parts: list[bytes] = []
        def field(name: str, value: Any) -> None:
            parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n').encode("utf-8"))
        field("chat_id", chat_id)
        if caption: field("caption", caption)
        if protect_content: field("protect_content", "true")
        mime = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
        filename = self._safe_filename(path)
        parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="document"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode("utf-8"))
        parts.append(path.read_bytes())
        parts.append(f"\r\n--{boundary}--\r\n".encode("utf-8"))
        return self._multipart_call("sendDocument", b"".join(parts), boundary)

    def download_document(self, file_id: str, destination: Path, max_bytes: int) -> int:
        """Downloads a document a user sent to the bot (the inverse of
        send_document). Bounded by max_bytes: the Bot-API file endpoint gives
        no reliable Content-Length ahead of time, so this reads at most
        max_bytes+1 and rejects anything larger rather than buffering an
        unbounded response.
        """
        info = self._call("getFile", {"file_id": file_id})
        file_path = str((info or {}).get("file_path") or "") if isinstance(info, dict) else ""
        if not file_path:
            raise BotApiError("file_path_unavailable")
        url = f"{self.endpoint}/file/bot{self.token}/{file_path}"
        try:
            with request.urlopen(request.Request(url, method="GET"), timeout=self.timeout) as res:
                data = res.read(max_bytes + 1)
        except error.HTTPError as exc:
            raise BotApiError(str(exc.code), transient=exc.code >= 500) from exc
        except (error.URLError, TimeoutError, OSError) as exc:
            raise BotApiError("network_unavailable", transient=True) from exc
        if len(data) > max_bytes:
            raise BotApiError("file_too_large")
        destination.write_bytes(data)
        return len(data)
