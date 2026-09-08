from __future__ import annotations

import json
import mimetypes
import re
import secrets
from pathlib import Path
from typing import Any
from urllib import error, request

from .bale_presentation import BalePresentation
from .callbacks import CallbackCodec
from .capabilities import PlatformCapabilities
from .models import Screen
from .telegram_presentation import TelegramPresentation


class BotApiError(RuntimeError):
    def __init__(
        self,
        code: str = "bot_api_error",
        *,
        retry_after: float | None = None,
        transient: bool = False,
        description: str = "",
    ):
        super().__init__(code)
        self.code = code
        self.retry_after = retry_after
        self.transient = transient
        self.description = description


class JsonBotApiTransport:
    def __init__(
        self,
        endpoint: str,
        token: str,
        capabilities: PlatformCapabilities,
        *,
        timeout: float = 15,
        rich_ui_enabled: bool | None = None,
        activity_ui_enabled: bool | None = None,
    ):
        if not token or any(c.isspace() for c in token):
            raise ValueError("bot token missing")
        self.endpoint = endpoint.rstrip("/")
        self.token = token
        self.capabilities = capabilities
        self.timeout = timeout
        self.activity_ui_enabled = (
            True if activity_ui_enabled is None else bool(activity_ui_enabled)
        )
        if capabilities.platform == "telegram":
            self.presentation = TelegramPresentation(
                enabled=rich_ui_enabled,
                max_rich_chars=capabilities.rich_max_chars or 32_768,
                max_blocks=capabilities.rich_max_blocks or 500,
                max_table_columns=capabilities.rich_max_table_columns or 20,
            )
        else:
            self.presentation = BalePresentation()

    def _url(self, method: str) -> str:
        return f"{self.endpoint}/bot{self.token}/{method}"

    @staticmethod
    def _api_error(data: Any) -> BotApiError:
        params = data.get("parameters") if isinstance(data, dict) else {}
        retry = params.get("retry_after") if isinstance(params, dict) else None
        error_code = data.get("error_code") if isinstance(data, dict) else None
        description = str(data.get("description") or "") if isinstance(data, dict) else ""
        try:
            numeric = int(error_code or 0)
        except (TypeError, ValueError):
            numeric = 0
        return BotApiError(
            str(error_code or "bot_api_error"),
            retry_after=float(retry) if isinstance(retry, (int, float)) else None,
            transient=bool(retry) or numeric >= 500,
            description=description,
        )

    def _call(self, method: str, payload: dict[str, Any] | None = None) -> Any:
        body = json.dumps(
            payload or {},
            ensure_ascii=False,
            separators=(",", ":"),
        ).encode("utf-8")
        req = request.Request(
            self._url(method),
            data=body,
            headers={"Content-Type": "application/json", "Accept": "application/json"},
            method="POST",
        )
        data: Any
        try:
            with request.urlopen(req, timeout=self.timeout) as res:
                data = json.loads(res.read().decode("utf-8"))
        except error.HTTPError as exc:
            try:
                data = json.loads(exc.read().decode("utf-8"))
            except Exception:
                data = {"error_code": exc.code, "description": str(exc.reason or "")}
        except (error.URLError, TimeoutError, OSError) as exc:
            raise BotApiError("network_unavailable", transient=True) from exc
        if not isinstance(data, dict) or data.get("ok") is not True:
            raise self._api_error(data)
        return data.get("result")

    def _multipart_call(self, method: str, body: bytes, boundary: str) -> Any:
        req = request.Request(
            self._url(method),
            data=body,
            headers={
                "Content-Type": f"multipart/form-data; boundary={boundary}",
                "Accept": "application/json",
            },
            method="POST",
        )
        data: Any
        try:
            with request.urlopen(req, timeout=self.timeout) as res:
                data = json.loads(res.read().decode("utf-8"))
        except error.HTTPError as exc:
            try:
                data = json.loads(exc.read().decode("utf-8"))
            except Exception:
                data = {"error_code": exc.code, "description": str(exc.reason or "")}
        except (error.URLError, TimeoutError, OSError) as exc:
            raise BotApiError("network_unavailable", transient=True) from exc
        if not isinstance(data, dict) or data.get("ok") is not True:
            raise self._api_error(data)
        return data.get("result")

    def get_me(self):
        return self._call("getMe")

    def get_updates(self, offset: int, timeout: int = 20):
        return self._call(
            "getUpdates",
            {
                "offset": offset,
                "timeout": timeout,
                "allowed_updates": ["message", "callback_query"],
            },
        )

    def answer_callback(self, callback_id: str, text: str = ""):
        payload: dict[str, Any] = {"callback_query_id": callback_id}
        if text:
            payload["text"] = text
        return self._call("answerCallbackQuery", payload)

    def chat_action(self, chat_id: str, action: str = "typing"):
        if not self.capabilities.supports_chat_action:
            return False
        return self._call("sendChatAction", {"chat_id": chat_id, "action": action})

    def send_rich_draft(self, chat_id: str, draft_id: int, text: str) -> bool:
        if (
            self.capabilities.platform != "telegram"
            or not self.capabilities.supports_rich_draft
            or not getattr(self.presentation, "enabled", False)
            or not self.activity_ui_enabled
        ):
            return False
        try:
            numeric_chat_id = int(chat_id)
        except (TypeError, ValueError):
            return False
        if draft_id == 0:
            raise ValueError("draft_id must be non-zero")
        escaped = (
            str(text)
            .replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
        )
        return bool(
            self._call(
                "sendRichMessageDraft",
                {
                    "chat_id": numeric_chat_id,
                    "draft_id": int(draft_id),
                    "rich_message": {
                        "html": f"<tg-thinking>{escaped}</tg-thinking>",
                        "is_rtl": True,
                    },
                    "can_stop": False,
                },
            )
        )

    def _markup(self, screen: Screen):
        if not screen.rows:
            return None
        if not self.capabilities.supports_inline_callback:
            raise BotApiError("inline_keyboard_unsupported")
        rows: list[list[dict[str, Any]]] = []
        for row in screen.rows:
            rendered_row: list[dict[str, Any]] = []
            for button in row:
                item: dict[str, Any] = {"text": button.text}
                if button.callback is not None:
                    callback = CallbackCodec.encode(*CallbackCodec.decode(button.callback))
                    if len(callback.encode("utf-8")) > self.capabilities.max_callback_bytes:
                        raise BotApiError("callback_too_large")
                    item["callback_data"] = callback
                else:
                    item["url"] = button.url
                rendered_row.append(item)
            rows.append(rendered_row)
        return {"inline_keyboard": rows}

    def _apply_reply(self, payload: dict[str, Any], reply_to: int | None) -> None:
        if reply_to is None:
            return
        if self.capabilities.reply_style == "reply_to_message_id":
            payload["reply_to_message_id"] = reply_to
        else:
            payload["reply_parameters"] = {"message_id": reply_to}

    def _plain_payload(
        self,
        chat_id: str,
        screen: Screen,
        *,
        text: str | None = None,
        reply_to: int | None = None,
        markup: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "chat_id": chat_id,
            "text": screen.text if text is None else text,
        }
        if markup:
            payload["reply_markup"] = markup
        if screen.protect_content:
            payload["protect_content"] = True
        self._apply_reply(payload, reply_to)
        return payload

    def _rich_payload(
        self,
        chat_id: str,
        screen: Screen,
        rich_message: dict[str, Any],
        *,
        reply_to: int | None = None,
        markup: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "chat_id": chat_id,
            "rich_message": rich_message,
        }
        if markup:
            payload["reply_markup"] = markup
        if screen.protect_content:
            payload["protect_content"] = True
        self._apply_reply(payload, reply_to)
        return payload

    def send_screen(self, chat_id: str, screen: Screen, *, reply_to: int | None = None):
        text = str(screen.text)
        if len(text) > self.capabilities.max_text_chars:
            raise ValueError("screen must be chunked before transport")
        if screen.protect_content and not self.capabilities.supports_forward_protection:
            raise BotApiError("forward_protection_unsupported")
        markup = self._markup(screen)
        rendered = self.presentation.render(screen)
        rich_message = getattr(rendered, "rich_message", None)
        rendered_plain = str(
            getattr(rendered, "text", getattr(rendered, "plain_text", screen.text))
        )
        if len(rendered_plain) > self.capabilities.max_text_chars:
            rendered_plain = text
        if self.capabilities.supports_native_rich and rich_message is not None:
            try:
                return self._call(
                    "sendRichMessage",
                    self._rich_payload(
                        chat_id,
                        screen,
                        rich_message,
                        reply_to=reply_to,
                        markup=markup,
                    ),
                )
            except BotApiError:
                # Rich rendering is presentation-only. At most one functional
                # plain operation is attempted; business logic is never rerun.
                pass
        return self._call(
            "sendMessage",
            self._plain_payload(
                chat_id,
                screen,
                text=rendered_plain,
                reply_to=reply_to,
                markup=markup,
            ),
        )

    def edit_screen(self, chat_id: str, message_id: int, screen: Screen):
        if not self.capabilities.supports_edit:
            return self.send_screen(chat_id, screen)
        if screen.protect_content:
            return self.send_screen(chat_id, screen)
        markup = self._markup(screen)
        rendered = self.presentation.render(screen)
        rich_message = getattr(rendered, "rich_message", None)
        rendered_plain = str(
            getattr(rendered, "text", getattr(rendered, "plain_text", screen.text))
        )
        if len(rendered_plain) > self.capabilities.max_text_chars:
            rendered_plain = str(screen.text)
        if self.capabilities.supports_rich_edit and rich_message is not None:
            try:
                return self._call(
                    "editMessageText",
                    {
                        "chat_id": chat_id,
                        "message_id": message_id,
                        "rich_message": rich_message,
                        **({"reply_markup": markup} if markup else {}),
                    },
                )
            except BotApiError:
                pass
        try:
            payload: dict[str, Any] = {
                "chat_id": chat_id,
                "message_id": message_id,
                "text": rendered_plain,
            }
            if markup:
                payload["reply_markup"] = markup
            return self._call("editMessageText", payload)
        except BotApiError:
            # Deleted, too old, uneditable, malformed legacy markup or an
            # ambiguous network failure becomes a new presentation operation.
            return self.send_screen(chat_id, screen)

    @staticmethod
    def _safe_filename(path: Path) -> str:
        stem = re.sub(r"[^A-Za-z0-9_-]+", "_", path.stem).strip("_")
        suffix = re.sub(r"[^A-Za-z0-9.]+", "", path.suffix)[:12]
        name = (stem or "document") + suffix
        return name[:96]

    def send_document(
        self,
        chat_id: str,
        path: str | Path,
        *,
        caption: str = "",
        protect_content: bool = False,
    ):
        path = Path(path)
        size = path.stat().st_size
        if size > self.capabilities.max_document_upload_bytes:
            raise BotApiError("file_too_large")
        if len(caption) > self.capabilities.max_caption_chars:
            raise BotApiError("caption_too_large")
        if protect_content and not self.capabilities.supports_forward_protection:
            raise BotApiError("forward_protection_unsupported")
        boundary = "----fanoos" + secrets.token_hex(12)
        parts: list[bytes] = []

        def field(name: str, value: Any) -> None:
            parts.append(
                (
                    f'--{boundary}\r\n'
                    f'Content-Disposition: form-data; name="{name}"\r\n\r\n'
                    f"{value}\r\n"
                ).encode("utf-8")
            )

        field("chat_id", chat_id)
        if caption:
            field("caption", caption)
        if protect_content:
            field("protect_content", "true")
        mime = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
        filename = self._safe_filename(path)
        parts.append(
            (
                f'--{boundary}\r\n'
                f'Content-Disposition: form-data; name="document"; filename="{filename}"\r\n'
                f"Content-Type: {mime}\r\n\r\n"
            ).encode("utf-8")
        )
        parts.append(path.read_bytes())
        parts.append(f"\r\n--{boundary}--\r\n".encode("utf-8"))
        return self._multipart_call("sendDocument", b"".join(parts), boundary)
