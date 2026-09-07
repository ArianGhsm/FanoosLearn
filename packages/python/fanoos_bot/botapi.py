from __future__ import annotations

import json
import mimetypes
import secrets
import urllib.error
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Mapping, Sequence

from .callbacks import CallbackCodec
from .models import Button


@dataclass(slots=True)
class BotApiError(RuntimeError):
    safe_code: str
    message: str
    status: int
    retryable: bool = False
    retry_after: float | None = None

    def __str__(self) -> str:
        return f"{self.safe_code}: {self.message}"


class JsonBotApiTransport:
    """Small allowlisted HTTP Bot API client used by one messenger runtime."""

    def __init__(self, *, platform: str, endpoint_prefix: str, token: str, allow_protect_content: bool, timeout_seconds: float = 15.0) -> None:
        if platform not in {"telegram", "bale"}:
            raise ValueError("Unsupported platform")
        if not token or any(ch.isspace() for ch in token):
            raise ValueError("Bot token is missing or invalid")
        if not endpoint_prefix.startswith("https://"):
            raise ValueError("Bot API endpoint must use HTTPS")
        self.platform = platform
        self._base = endpoint_prefix.rstrip("/") + "/bot" + token + "/"
        self._allow_protect = allow_protect_content
        self.timeout_seconds = timeout_seconds

    def get_me(self) -> dict[str, Any]:
        result = self._json_call("getMe", {})
        if not isinstance(result, dict):
            raise BotApiError("provider_contract", "getMe result is invalid", 502)
        return result

    def get_updates(self, *, offset: int | None, timeout: int = 25, limit: int = 50) -> list[dict[str, Any]]:
        payload: dict[str, Any] = {"timeout": max(0, min(timeout, 50)), "limit": max(1, min(limit, 100))}
        if offset is not None:
            payload["offset"] = int(offset)
        result = self._json_call("getUpdates", payload, timeout=max(self.timeout_seconds, timeout + 10))
        if not isinstance(result, list):
            raise BotApiError("provider_contract", "getUpdates result is invalid", 502)
        return [item for item in result if isinstance(item, dict)]

    def send_text(self, chat_id: str, text: str, *, rows: list[list[Button]] | None = None, protect_content: bool = False) -> str:
        if len(text) < 1 or len(text) > 4096:
            raise BotApiError("text_length", "Message text exceeds provider contract", 400)
        if protect_content and not self._allow_protect:
            raise BotApiError("unsupported_forward_protection", "Official adapter capability does not support protected-content send", 400, retryable=False)
        payload: dict[str, Any] = {"chat_id": chat_id, "text": text}
        keyboard = self._keyboard(rows or [])
        if keyboard:
            payload["reply_markup"] = keyboard
        if protect_content:
            payload["protect_content"] = True
        return self._message_ref(self._json_call("sendMessage", payload))

    def edit_text(self, chat_id: str, message_id: str, text: str, *, rows: list[list[Button]] | None = None) -> str:
        if len(text) < 1 or len(text) > 4096:
            raise BotApiError("text_length", "Edited text exceeds provider contract", 400)
        payload: dict[str, Any] = {"chat_id": chat_id, "message_id": self._int_id(message_id), "text": text}
        keyboard = self._keyboard(rows or [])
        if keyboard:
            payload["reply_markup"] = keyboard
        return self._message_ref(self._json_call("editMessageText", payload), fallback=message_id)

    def answer_callback(self, callback_id: str, text: str | None = None) -> None:
        payload: dict[str, Any] = {"callback_query_id": callback_id}
        if text:
            payload["text"] = text[:200]
        self._json_call("answerCallbackQuery", payload)

    def send_chat_action(self, chat_id: str, action: str = "typing") -> None:
        if action not in {"typing", "upload_document"}:
            action = "typing"
        self._json_call("sendChatAction", {"chat_id": chat_id, "action": action})

    def send_document(self, chat_id: str, file_path: str, *, caption: str | None = None, protect_content: bool = False) -> str:
        if protect_content and not self._allow_protect:
            raise BotApiError("unsupported_forward_protection", "Protected document send is unavailable", 400)
        path = Path(file_path)
        if not path.is_file():
            raise BotApiError("document_unavailable", "Document is unavailable", 400)
        if path.stat().st_size > 50 * 1024 * 1024:
            raise BotApiError("document_too_large", "Document exceeds ordinary Bot API upload limit", 400)
        fields: dict[str, str] = {"chat_id": chat_id}
        if caption:
            fields["caption"] = caption[:1024]
        if protect_content:
            fields["protect_content"] = "true"
        return self._message_ref(self._multipart_call("sendDocument", fields, "document", path))

    @staticmethod
    def _keyboard(rows: Sequence[Sequence[Button]]) -> dict[str, Any] | None:
        if not rows:
            return None
        inline: list[list[dict[str, str]]] = []
        for row in rows:
            encoded: list[dict[str, str]] = []
            for button in row:
                item = {"text": button.label[:64]}
                if button.callback_data is not None:
                    item["callback_data"] = CallbackCodec.validate(button.callback_data)
                elif button.url is not None and button.url.startswith(("https://", "http://")):
                    item["url"] = button.url
                else:
                    raise ValueError("Unsafe button URL/action")
                encoded.append(item)
            if encoded:
                inline.append(encoded)
        return {"inline_keyboard": inline} if inline else None

    def _json_call(self, method: str, payload: Mapping[str, Any], timeout: float | None = None) -> Any:
        if method not in {"getMe", "getUpdates", "sendMessage", "editMessageText", "answerCallbackQuery", "sendChatAction"}:
            raise ValueError("Bot API method is not allowlisted")
        body = json.dumps(payload, ensure_ascii=False, separators=(",", ":")).encode("utf-8")
        request = urllib.request.Request(self._base + method, data=body, method="POST", headers={"Content-Type": "application/json; charset=utf-8", "Accept": "application/json"})
        return self._open(request, timeout or self.timeout_seconds)

    def _multipart_call(self, method: str, fields: Mapping[str, str], file_field: str, path: Path) -> Any:
        if method != "sendDocument":
            raise ValueError("Multipart method is not allowlisted")
        boundary = "----fanoos" + secrets.token_hex(12)
        body = bytearray()
        for key, value in fields.items():
            body.extend(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{key}\"\r\n\r\n{value}\r\n".encode())
        mime = mimetypes.guess_type(path.name)[0] or "application/octet-stream"
        body.extend(f"--{boundary}\r\nContent-Disposition: form-data; name=\"{file_field}\"; filename=\"document.pdf\"\r\nContent-Type: {mime}\r\n\r\n".encode())
        body.extend(path.read_bytes())
        body.extend(f"\r\n--{boundary}--\r\n".encode())
        request = urllib.request.Request(self._base + method, data=bytes(body), method="POST", headers={"Content-Type": f"multipart/form-data; boundary={boundary}", "Accept": "application/json"})
        return self._open(request, max(self.timeout_seconds, 60.0))

    def _open(self, request: urllib.request.Request, timeout: float) -> Any:
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                status, raw = response.status, response.read()
        except urllib.error.HTTPError as exc:
            status, raw = exc.code, exc.read()
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            raise BotApiError("provider_unavailable", "Messaging provider unavailable", 503, retryable=True) from exc
        try:
            envelope = json.loads(raw.decode("utf-8"))
        except (UnicodeDecodeError, json.JSONDecodeError) as exc:
            raise BotApiError("provider_contract", "Provider returned invalid JSON", status, retryable=status >= 500) from exc
        if not isinstance(envelope, dict) or envelope.get("ok") is not True:
            description = str(envelope.get("description") or "Provider request failed") if isinstance(envelope, dict) else "Provider request failed"
            parameters = envelope.get("parameters") if isinstance(envelope, dict) else None
            retry_after = None
            if isinstance(parameters, dict) and isinstance(parameters.get("retry_after"), (int, float)):
                retry_after = max(0.0, min(60.0, float(parameters["retry_after"])))
            error_code = envelope.get("error_code") if isinstance(envelope, dict) else status
            safe_code = f"provider_{error_code}" if isinstance(error_code, int) else "provider_error"
            raise BotApiError(safe_code, description[:300], int(error_code) if isinstance(error_code, int) else status, retryable=(status == 429 or status >= 500 or retry_after is not None), retry_after=retry_after)
        return envelope.get("result")

    @staticmethod
    def _message_ref(result: Any, fallback: str | None = None) -> str:
        if isinstance(result, dict) and isinstance(result.get("message_id"), int):
            return str(result["message_id"])
        if result is True and fallback is not None:
            return fallback
        raise BotApiError("provider_contract", "Provider message result lacks message_id", 502)

    @staticmethod
    def _int_id(value: str) -> int:
        try:
            return int(value)
        except ValueError as exc:
            raise ValueError("Invalid provider message id") from exc
