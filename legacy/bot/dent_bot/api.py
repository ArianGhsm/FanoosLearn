from __future__ import annotations

import json
import html
import hashlib
import http.client
import copy
import queue
import re
import secrets
import ssl
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import quote, urlsplit

from .persian_datetime import to_persian_digits


BOT_COMMANDS = (
    {"command": "start", "description": "باز کردن دنت‌یار"},
    {"command": "menu", "description": "منوی اصلی"},
    {"command": "verify", "description": "احراز هویت و اتصال حساب"},
    {"command": "help", "description": "راهنما و پشتیبانی"},
)


def _shared_bot_commands() -> list[dict[str, str]]:
    return [dict(item) for item in BOT_COMMANDS]


class BotApiError(RuntimeError):
    def __init__(self, message: str, *, transient: bool = False) -> None:
        super().__init__(message)
        self.transient = transient


def _escape_bale_markdown(value: str) -> str:
    """Escape user-controlled text before placing it in Bale Markdown."""
    return re.sub(r"([\\*_`\[\]()])", r"\\\1", value)


class _BaleMarkdownParser(HTMLParser):
    """Translate the bot's bounded Telegram HTML subset into Bale Markdown."""

    _CONTAINERS = {"b", "strong", "i", "em", "u", "s", "code", "pre", "blockquote", "a"}

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self._frames: list[dict[str, object]] = [{"tag": "root", "attrs": {}, "parts": []}]

    def _append(self, value: str) -> None:
        parts = self._frames[-1]["parts"]
        assert isinstance(parts, list)
        parts.append(value)

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        normalized = tag.lower()
        if normalized == "br":
            self._append("\n")
            return
        if normalized in self._CONTAINERS:
            self._frames.append({"tag": normalized, "attrs": dict(attrs), "parts": []})

    def handle_startendtag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        self.handle_starttag(tag, attrs)

    def handle_endtag(self, tag: str) -> None:
        normalized = tag.lower()
        if len(self._frames) == 1 or self._frames[-1]["tag"] != normalized:
            return
        frame = self._frames.pop()
        parts = frame["parts"]
        assert isinstance(parts, list)
        content = "".join(str(part) for part in parts)
        attrs = frame["attrs"]
        assert isinstance(attrs, dict)
        self._append(self._render(normalized, content, attrs))

    def handle_data(self, data: str) -> None:
        in_literal = any(frame["tag"] in {"code", "pre"} for frame in self._frames)
        self._append(data if in_literal else _escape_bale_markdown(data))

    @staticmethod
    def _render(tag: str, content: str, attrs: dict[str, str | None]) -> str:
        if tag in {"b", "strong"}:
            return f" *{content.strip()}* " if content.strip() else ""
        if tag in {"i", "em"}:
            return f" _{content.strip()}_ " if content.strip() else ""
        if tag == "code":
            return f"`{content.replace('`', 'ˋ')}`" if content else ""
        if tag == "pre":
            literal = content.strip("\n").replace("```", "ˋˋˋ")
            return f"\n```\n{literal}\n```\n" if literal else ""
        if tag == "blockquote":
            lines = content.strip("\n").splitlines()
            quoted = "\n".join(f"▎ {line}" if line else "▎" for line in lines)
            return f"\n{quoted}\n" if quoted else ""
        if tag == "a":
            href = html.unescape(str(attrs.get("href") or "")).strip()
            parsed = urlsplit(href)
            if parsed.scheme in {"http", "https"} and parsed.netloc:
                safe_href = quote(href, safe=":/?#[]@!$&'*+,;=%~._-")
                return f"[{content}]({safe_href})"
            return content
        # Bale has no dependable underline/strikethrough equivalent.
        return content

    def rendered(self) -> str:
        while len(self._frames) > 1:
            frame = self._frames.pop()
            parts = frame["parts"]
            assert isinstance(parts, list)
            self._append("".join(str(part) for part in parts))
        root_parts = self._frames[0]["parts"]
        assert isinstance(root_parts, list)
        rendered = "".join(str(part) for part in root_parts).strip("\n")
        rendered = re.sub(r"\n[ \t]+\n", "\n\n", rendered)
        rendered = re.sub(r"\n{3,}", "\n\n", rendered)
        return rendered


def _html_rich_text_to_bale_markdown(value: str) -> str:
    parser = _BaleMarkdownParser()
    parser.feed(value)
    parser.close()
    return parser.rendered()


_HTML_TAG_SPLIT = re.compile(r"(<[^>]+>)")
_VISIBLE_URL = re.compile(r"(https?://[^\s<]+|(?:t|ble)\.me/[^\s<]+)", re.IGNORECASE)
_VISIBLE_TEXT_FIELDS = {"text", "input_field_placeholder"}


def _persianize_plain_visible_text(value: str) -> str:
    """Persianize digits without corrupting a visible literal URL."""
    return "".join(
        part if _VISIBLE_URL.fullmatch(part) else to_persian_digits(part)
        for part in _VISIBLE_URL.split(str(value))
    )


def _persianize_visible_rich_text(value: str) -> str:
    """Persianize visible HTML text while leaving tags and hrefs byte-stable."""
    return "".join(
        part if part.startswith("<") and part.endswith(">") else _persianize_plain_visible_text(part)
        for part in _HTML_TAG_SPLIT.split(str(value))
    )


def _persianize_reply_markup(reply_markup: dict) -> dict:
    """Copy a keyboard and Persianize labels only, never actions or URLs."""
    result = copy.deepcopy(reply_markup)

    def visit(value: object, *, key: str = "") -> object:
        if isinstance(value, dict):
            return {item_key: visit(item_value, key=str(item_key)) for item_key, item_value in value.items()}
        if isinstance(value, list):
            return [visit(item, key=key) for item in value]
        if isinstance(value, str) and key in _VISIBLE_TEXT_FIELDS:
            return _persianize_plain_visible_text(value)
        return value

    converted = visit(result)
    return dict(converted) if isinstance(converted, dict) else {}


class _HttpsConnectionPool:
    def __init__(self, origin: str, *, size: int = 12, proxy_url: str = "") -> None:
        parsed = urlsplit(origin)
        if parsed.scheme != "https" or not parsed.hostname or parsed.username or parsed.password:
            raise ValueError("Bot API root must be a public HTTPS origin")
        self.host = parsed.hostname
        self.port = parsed.port or 443
        self.prefix = parsed.path.rstrip("/")
        self.context = ssl.create_default_context()
        self.proxy_host = ""
        self.proxy_port = 0
        if proxy_url:
            proxy = urlsplit(proxy_url)
            if (
                proxy.scheme != "http"
                or proxy.hostname not in {"127.0.0.1", "localhost"}
                or proxy.username
                or proxy.password
                or proxy.path not in {"", "/"}
                or proxy.query
                or proxy.fragment
            ):
                raise ValueError("Bot HTTPS proxy must be an unauthenticated loopback HTTP proxy")
            self.proxy_host = str(proxy.hostname)
            self.proxy_port = proxy.port or 80
        self.connections: queue.LifoQueue[http.client.HTTPSConnection] = queue.LifoQueue(maxsize=size)
        for _ in range(size):
            self.connections.put(self._new(8))

    def _new(self, timeout: float) -> http.client.HTTPSConnection:
        if self.proxy_host:
            connection = http.client.HTTPSConnection(
                self.proxy_host,
                self.proxy_port,
                timeout=timeout,
                context=self.context,
            )
            connection.set_tunnel(self.host, self.port)
            return connection
        return http.client.HTTPSConnection(self.host, self.port, timeout=timeout, context=self.context)

    def post(self, path: str, body: bytes, headers: dict[str, str], *, timeout: float) -> tuple[int, bytes]:
        try:
            connection = self.connections.get(timeout=max(1.0, timeout))
        except queue.Empty as error:
            raise BotApiError("Telegram connection pool is busy", transient=True) from error
        healthy = False
        try:
            connection.timeout = timeout
            if connection.sock is not None:
                connection.sock.settimeout(timeout)
            try:
                connection.request("POST", f"{self.prefix}{path}", body=body, headers=headers)
            except (OSError, TimeoutError, http.client.HTTPException):
                # A pooled TLS socket may have been closed while idle. If the
                # failure happens inside request()/sendall(), the HTTP request
                # was incomplete and cannot be processed by the Bot API, so one
                # retry on a new connection is safe and prevents the first user
                # interaction after an idle period from being lost. Failures
                # after request() returns are deliberately not retried because
                # the remote side may already have created the message.
                try:
                    connection.close()
                except OSError:
                    pass
                connection = self._new(timeout)
                connection.request("POST", f"{self.prefix}{path}", body=body, headers=headers)
            response = connection.getresponse()
            raw = response.read(2 * 1024 * 1024 + 1)
            if len(raw) > 2 * 1024 * 1024:
                raise BotApiError("Telegram response exceeded the configured bound", transient=True)
            healthy = not response.will_close
            return int(response.status), raw
        except (OSError, TimeoutError, http.client.HTTPException) as error:
            raise BotApiError("Telegram network request failed", transient=True) from error
        finally:
            if not healthy:
                try:
                    connection.close()
                except OSError:
                    pass
                connection = self._new(timeout)
            self.connections.put(connection)

    def get_to_file(self, path: str, destination: Path, *, timeout: float, max_bytes: int) -> tuple[int, str]:
        try:
            connection = self.connections.get(timeout=max(1.0, timeout))
        except queue.Empty as error:
            raise BotApiError("Telegram connection pool is busy", transient=True) from error
        healthy = False
        received = 0
        digest = hashlib.sha256()
        try:
            connection.timeout = timeout
            if connection.sock is not None:
                connection.sock.settimeout(timeout)
            connection.request("GET", f"{self.prefix}{path}", headers={"Accept": "application/octet-stream"})
            response = connection.getresponse()
            if int(response.status) != 200:
                response.read(64 * 1024)
                raise BotApiError("Telegram file download was rejected")
            declared = int(response.getheader("Content-Length") or 0)
            if declared > max_bytes:
                raise BotApiError("Telegram file exceeds the configured download limit")
            with destination.open("wb") as stream:
                while chunk := response.read(128 * 1024):
                    received += len(chunk)
                    if received > max_bytes:
                        raise BotApiError("Telegram file exceeds the configured download limit")
                    stream.write(chunk)
                    digest.update(chunk)
            if received <= 0:
                raise BotApiError("Telegram returned an empty file")
            healthy = not response.will_close
            return received, digest.hexdigest()
        except (OSError, TimeoutError, http.client.HTTPException) as error:
            raise BotApiError("Telegram file download failed", transient=True) from error
        finally:
            if not healthy:
                try:
                    connection.close()
                except OSError:
                    pass
                connection = self._new(timeout)
            self.connections.put(connection)

    def post_multipart_file(
        self,
        path: str,
        *,
        fields: dict[str, str],
        file_field: str,
        filename: str,
        content_type: str,
        file_path: Path,
        timeout: float,
    ) -> tuple[int, bytes]:
        boundary = "----Dent1402" + secrets.token_hex(12)
        prefix_parts: list[bytes] = []
        for name, value in fields.items():
            prefix_parts.extend([
                f"--{boundary}\r\n".encode("ascii"),
                f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode("ascii"),
                str(value).encode("utf-8"),
                b"\r\n",
            ])
        prefix_parts.extend([
            f"--{boundary}\r\n".encode("ascii"),
            f'Content-Disposition: form-data; name="{file_field}"; filename="{filename}"\r\n'.encode("ascii"),
            f"Content-Type: {content_type}\r\n\r\n".encode("ascii"),
        ])
        prefix = b"".join(prefix_parts)
        suffix = f"\r\n--{boundary}--\r\n".encode("ascii")
        content_length = len(prefix) + int(file_path.stat().st_size) + len(suffix)
        try:
            connection = self.connections.get(timeout=max(1.0, timeout))
        except queue.Empty as error:
            raise BotApiError("Telegram connection pool is busy", transient=True) from error
        healthy = False
        try:
            connection.timeout = timeout
            if connection.sock is not None:
                connection.sock.settimeout(timeout)
            connection.putrequest("POST", f"{self.prefix}{path}")
            connection.putheader("Content-Type", f"multipart/form-data; boundary={boundary}")
            connection.putheader("Content-Length", str(content_length))
            connection.putheader("Accept", "application/json")
            connection.putheader("Connection", "keep-alive")
            connection.endheaders()
            connection.send(prefix)
            with file_path.open("rb") as stream:
                while chunk := stream.read(128 * 1024):
                    connection.send(chunk)
            connection.send(suffix)
            response = connection.getresponse()
            raw = response.read(2 * 1024 * 1024 + 1)
            if len(raw) > 2 * 1024 * 1024:
                raise BotApiError("Telegram response exceeded the configured bound", transient=True)
            healthy = not response.will_close
            return int(response.status), raw
        except (OSError, TimeoutError, http.client.HTTPException) as error:
            raise BotApiError("Telegram file upload failed", transient=True) from error
        finally:
            if not healthy:
                try:
                    connection.close()
                except OSError:
                    pass
                connection = self._new(timeout)
            self.connections.put(connection)

    def close(self) -> None:
        while True:
            try:
                connection = self.connections.get_nowait()
            except queue.Empty:
                return
            try:
                connection.close()
            except OSError:
                pass


class TelegramBotApi:
    def __init__(self, token: str, *, api_root: str = "https://api.telegram.org", proxy_url: str = "") -> None:
        self._path = f"/bot{token}"
        self._transport = _HttpsConnectionPool(api_root, proxy_url=proxy_url)

    def call(self, method: str, payload: dict | None = None, *, timeout: int = 8):
        body = json.dumps(payload or {}, ensure_ascii=False).encode("utf-8")
        try:
            _status, raw = self._transport.post(
                f"{self._path}/{method}",
                body,
                {"Content-Type": "application/json", "Accept": "application/json", "Connection": "keep-alive"},
                timeout=timeout,
            )
        except BotApiError:
            raise
        try:
            value = json.loads(raw.decode("utf-8"))
        except (UnicodeError, json.JSONDecodeError) as error:
            raise BotApiError("Telegram returned an invalid response") from error
        if not isinstance(value, dict) or value.get("ok") is not True:
            description = str(value.get("description", "request rejected")) if isinstance(value, dict) else "request rejected"
            raise BotApiError(description[:240])
        return value.get("result")

    def get_updates(self, offset: int, poll_timeout: int) -> list[dict]:
        result = self.call(
            "getUpdates",
            {
                "offset": offset,
                "timeout": poll_timeout,
                "allowed_updates": ["message", "callback_query", "inline_query", "channel_post", "edited_channel_post"],
            },
            timeout=poll_timeout + 10,
        )
        return list(result or [])

    def is_chat_member(self, chat_id: str | int, user_id: int) -> bool:
        """Return Telegram's current membership decision for one user.

        The caller deliberately performs this check for every private
        interaction. The required channel bot must remain an administrator so
        Telegram guarantees getChatMember for other users.
        """
        result = self.call(
            "getChatMember",
            {"chat_id": chat_id, "user_id": int(user_id)},
            timeout=6,
        )
        if not isinstance(result, dict):
            raise BotApiError("Telegram returned an invalid chat-member response", transient=True)
        status = str(result.get("status") or "")
        if status in {"creator", "administrator", "member"}:
            return True
        if status == "restricted":
            return result.get("is_member") is True
        if status in {"left", "kicked"}:
            return False
        raise BotApiError("Telegram returned an unknown chat-member status", transient=True)

    def _prepare_rich_text(self, value: str) -> str:
        return _persianize_visible_rich_text(value)

    def _prepare_reply_markup(self, reply_markup: dict) -> dict:
        return _persianize_reply_markup(reply_markup)

    def _parse_mode(self) -> str | None:
        return "HTML"

    def _supports_native_rich_messages(self) -> bool:
        return True

    @staticmethod
    def _rich_html(value: object) -> str:
        return str(getattr(value, "rich_html", "") or "")

    @staticmethod
    def _rich_fallback_allowed(error: BotApiError) -> bool:
        if error.transient:
            return False
        message = str(error).lower()
        return any(token in message for token in (
            "method not found", "unknown method", "unsupported method",
            "rich messages are not supported",
        ))

    def _send_native_rich(self, chat_id: int, rich_html: str, fallback_text: str, reply_markup: dict) -> dict:
        payload = {
            "chat_id": chat_id,
            "rich_message": {
                "html": _persianize_visible_rich_text(rich_html),
                "is_rtl": True,
            },
            "reply_markup": self._prepare_reply_markup(reply_markup),
        }
        try:
            return dict(self._call_with_style_fallback("sendRichMessage", payload) or {})
        except BotApiError as error:
            if not self._rich_fallback_allowed(error):
                raise
            return self.send(chat_id, str(fallback_text), reply_markup)

    def send(self, chat_id: int, text: str, reply_markup: dict) -> dict:
        rich_html = self._rich_html(text)
        if rich_html and self._supports_native_rich_messages():
            return self._send_native_rich(chat_id, rich_html, str(text), reply_markup)
        payload = {
            "chat_id": chat_id,
            "text": self._prepare_rich_text(text),
            "disable_web_page_preview": True,
            "reply_markup": self._prepare_reply_markup(reply_markup),
        }
        if self._parse_mode() is not None:
            payload["parse_mode"] = self._parse_mode()
        return dict(self._call_with_style_fallback("sendMessage", payload) or {})

    def remove_reply_keyboard(
        self,
        chat_id: int,
        text: str = "⌨️",
    ) -> dict:
        """Remove a persistent reply keyboard before returning to inline UI.

        Telegram and Bale keep reply keyboards client-side until a later
        sendMessage explicitly carries remove_keyboard. An inline keyboard on
        a newer message does not replace that persistent keyboard.
        """
        payload = {
            "chat_id": chat_id,
            "text": self._prepare_rich_text(text),
            "disable_web_page_preview": True,
            "reply_markup": {"remove_keyboard": True},
        }
        if self._parse_mode() is not None:
            payload["parse_mode"] = self._parse_mode()
        result = dict(self.call("sendMessage", payload) or {})
        # Telegram/Bale require a message carrying remove_keyboard; delete that
        # transport-only message immediately so authentication success is not
        # announced again on a later /start. If an older Bale API cannot delete
        # it, the harmless keyboard glyph remains instead of a false success.
        try:
            message_id = int(result.get("message_id") or 0)
            if message_id > 0:
                self.call("deleteMessage", {"chat_id": chat_id, "message_id": message_id})
        except (BotApiError, TypeError, ValueError):
            pass
        return result

    def send_protected_media(
        self,
        chat_id: int,
        source: dict,
        *,
        personalized_file_id: str = "",
    ) -> dict:
        """Deliver Telegram media with forwarding/saving protection enabled."""
        if personalized_file_id:
            method = str(source.get("telegramMethod") or "sendDocument")
            field = {"sendDocument": "document", "sendAudio": "audio", "sendVoice": "voice"}.get(method)
            if field is None:
                raise BotApiError("Unsupported protected media method")
            payload = {
                "chat_id": chat_id,
                field: personalized_file_id,
                "protect_content": True,
            }
            return dict(self.call(method, payload, timeout=30) or {})
        source_chat_id = int(source.get("sourceChatId") or 0)
        source_message_id = int(source.get("sourceMessageId") or 0)
        if source_chat_id >= 0 or source_message_id <= 0:
            raise BotApiError("Protected media source is invalid")
        return dict(self.call(
            "copyMessage",
            {
                "chat_id": chat_id,
                "from_chat_id": source_chat_id,
                "message_id": source_message_id,
                "protect_content": True,
            },
            timeout=30,
        ) or {})

    def download_file(self, file_id: str, destination: Path, *, max_bytes: int = 20 * 1024 * 1024) -> dict:
        info = dict(self.call("getFile", {"file_id": str(file_id)}, timeout=15) or {})
        file_path = str(info.get("file_path") or "").lstrip("/")
        declared = int(info.get("file_size") or 0)
        if not file_path or ".." in file_path.split("/"):
            raise BotApiError("Telegram returned an invalid file path")
        if declared > max_bytes:
            raise BotApiError("Telegram file exceeds the configured download limit")
        destination.parent.mkdir(parents=True, exist_ok=True)
        received, source_hash = self._transport.get_to_file(
            f"/file{self._path}/{quote(file_path, safe='/._-')}",
            destination,
            timeout=45,
            max_bytes=max_bytes,
        )
        return {
            "bytes": received,
            "sha256": source_hash,
            "filePath": file_path,
            "fileUniqueId": str(info.get("file_unique_id") or ""),
        }

    def send_protected_document_path(
        self,
        chat_id: int,
        document_path: Path,
        *,
        caption: str = "",
        filename: str = "personalized.pdf",
    ) -> dict:
        if not document_path.is_file() or document_path.stat().st_size <= 0:
            raise BotApiError("Personalized PDF is unavailable")
        fields = {
            "chat_id": str(int(chat_id)),
            "protect_content": "true",
        }
        if caption:
            fields["caption"] = self._prepare_rich_text(caption)
            if self._parse_mode() is not None:
                fields["parse_mode"] = str(self._parse_mode())
        _status, raw = self._transport.post_multipart_file(
            f"{self._path}/sendDocument",
            fields=fields,
            file_field="document",
            filename=re.sub(r"[^A-Za-z0-9._-]", "-", filename)[:100] or "personalized.pdf",
            content_type="application/pdf",
            file_path=document_path,
            timeout=90,
        )
        try:
            value = json.loads(raw.decode("utf-8"))
        except (UnicodeError, json.JSONDecodeError) as error:
            raise BotApiError("Telegram returned an invalid response") from error
        if not isinstance(value, dict) or value.get("ok") is not True:
            description = str(value.get("description", "request rejected")) if isinstance(value, dict) else "request rejected"
            raise BotApiError(description[:240])
        return dict(value.get("result") or {})

    def send_document_path(
        self,
        chat_id: int,
        document_path: Path,
        *,
        caption: str = "",
        filename: str = "report.csv",
        content_type: str = "text/csv",
    ) -> dict:
        if not document_path.is_file() or document_path.stat().st_size <= 0:
            raise BotApiError("Report file is unavailable")
        fields = {"chat_id": str(int(chat_id))}
        if caption:
            fields["caption"] = self._prepare_rich_text(caption)
            if self._parse_mode() is not None:
                fields["parse_mode"] = str(self._parse_mode())
        _status, raw = self._transport.post_multipart_file(
            f"{self._path}/sendDocument",
            fields=fields,
            file_field="document",
            filename=re.sub(r"[^A-Za-z0-9._-]", "-", filename)[:100] or "report.csv",
            content_type=content_type,
            file_path=document_path,
            timeout=60,
        )
        try:
            value = json.loads(raw.decode("utf-8"))
        except (UnicodeError, json.JSONDecodeError) as error:
            raise BotApiError("Bot API returned an invalid document response") from error
        if not isinstance(value, dict) or value.get("ok") is not True:
            description = str(value.get("description", "request rejected")) if isinstance(value, dict) else "request rejected"
            raise BotApiError(description[:240])
        return dict(value.get("result") or {})

    def answer_inline_query(self, inline_query_id: str, results: list[dict], *, cache_time: int = 5) -> bool:
        try:
            self.call(
                "answerInlineQuery",
                {
                    "inline_query_id": str(inline_query_id),
                    "results": results[:20],
                    "cache_time": max(0, min(300, int(cache_time))),
                    "is_personal": True,
                },
                timeout=6,
            )
            return True
        except BotApiError:
            return False

    def send_photo_bytes(
        self,
        chat_id: int,
        photo: bytes,
        *,
        caption: str,
        reply_markup: dict,
        filename: str = "navid-captcha.png",
    ) -> dict:
        if not photo or len(photo) > 5 * 1024 * 1024:
            raise BotApiError("Image size is invalid")
        boundary = "----Dent1402" + secrets.token_hex(12)
        chunks: list[bytes] = []

        def field(name: str, value: str) -> None:
            chunks.extend([
                f"--{boundary}\r\n".encode("ascii"),
                f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode("ascii"),
                value.encode("utf-8"),
                b"\r\n",
            ])

        field("chat_id", str(chat_id))
        field("caption", self._prepare_rich_text(caption))
        if self._parse_mode() is not None:
            field("parse_mode", str(self._parse_mode()))
        field("reply_markup", json.dumps(self._prepare_reply_markup(reply_markup), ensure_ascii=False, separators=(",", ":")))
        chunks.extend([
            f"--{boundary}\r\n".encode("ascii"),
            f'Content-Disposition: form-data; name="photo"; filename="{filename}"\r\n'.encode("ascii"),
            b"Content-Type: image/png\r\n\r\n",
            photo,
            b"\r\n",
            f"--{boundary}--\r\n".encode("ascii"),
        ])
        try:
            _status, raw = self._transport.post(
                f"{self._path}/sendPhoto",
                b"".join(chunks),
                {"Content-Type": f"multipart/form-data; boundary={boundary}", "Accept": "application/json", "Connection": "keep-alive"},
                timeout=15,
            )
        except BotApiError:
            raise
        try:
            value = json.loads(raw.decode("utf-8"))
        except (UnicodeError, json.JSONDecodeError) as error:
            raise BotApiError("Telegram returned an invalid response") from error
        if not isinstance(value, dict) or value.get("ok") is not True:
            description = str(value.get("description", "request rejected")) if isinstance(value, dict) else "request rejected"
            raise BotApiError(description[:240])
        return dict(value.get("result") or {})

    def edit(self, chat_id: int, message_id: int, text: str, reply_markup: dict) -> dict:
        rich_html = self._rich_html(text)
        if rich_html and self._supports_native_rich_messages():
            payload = {
                "chat_id": chat_id,
                "message_id": message_id,
                "rich_message": {
                    "html": _persianize_visible_rich_text(rich_html),
                    "is_rtl": True,
                },
                "reply_markup": self._prepare_reply_markup(reply_markup),
            }
            try:
                return dict(self._call_with_style_fallback("editMessageText", payload) or {})
            except BotApiError as error:
                if not self._rich_fallback_allowed(error):
                    raise
                return self.edit(chat_id, message_id, str(text), reply_markup)
        payload = {
            "chat_id": chat_id,
            "message_id": message_id,
            "text": self._prepare_rich_text(text),
            "disable_web_page_preview": True,
            "reply_markup": self._prepare_reply_markup(reply_markup),
        }
        if self._parse_mode() is not None:
            payload["parse_mode"] = self._parse_mode()
        try:
            return dict(self._call_with_style_fallback("editMessageText", payload) or {})
        except BotApiError as error:
            if not error.transient:
                raise
            return dict(self._call_with_style_fallback("editMessageText", payload) or {})

    @staticmethod
    def _without_button_styles(reply_markup: dict) -> dict:
        return {
            "inline_keyboard": [
                [{key: value for key, value in button.items() if key != "style"} for button in row]
                for row in reply_markup.get("inline_keyboard", [])
            ]
        }

    def _call_with_style_fallback(self, method: str, payload: dict):
        try:
            return self.call(method, payload)
        except BotApiError as error:
            text = str(error).lower()
            if "style" not in text and "inline keyboard button" not in text:
                raise
            fallback = dict(payload)
            fallback["reply_markup"] = self._without_button_styles(dict(payload["reply_markup"]))
            return self.call(method, fallback)

    def answer_callback(self, callback_id: str, text: str = "", *, show_alert: bool = False) -> bool:
        payload: dict[str, object] = {"callback_query_id": callback_id}
        if text:
            payload["text"] = _persianize_plain_visible_text(text[:180])
        if show_alert:
            payload["show_alert"] = True
        try:
            self.call("answerCallbackQuery", payload, timeout=3)
            return True
        except BotApiError:
            # Callback acknowledgement is best-effort. The actual screen must
            # still render when Telegram resets this short-lived request.
            return False

    def configure_profile(self) -> None:
        self.call(
            "setMyCommands",
            {
                "commands": [
                    *_shared_bot_commands(),
                ],
                "scope": {"type": "all_private_chats"},
                "language_code": "fa",
            },
        )

        self.call(
            "setMyShortDescription",
            {"short_description": "دسترسی سریع به آزمون‌ها، جزوات، نمرات و خدمات آموزشی"},
        )
        self.call(
            "setMyDescription",
            {
                "description": (
                    "دنت‌یار، همراه آموزشی ورودی ۱۴۰۲ دندان‌پزشکی تهران است. "
                    "آزمون‌ها، جزوات، نمرات و اعلان‌های مهم را از یک منوی ساده دنبال کنید."
                )
            },
        )

    def close(self) -> None:
        self._transport.close()


class BaleBotApi(TelegramBotApi):
    def __init__(self, token: str) -> None:
        super().__init__(token, api_root="https://tapi.bale.ai")

    def get_updates(self, offset: int, poll_timeout: int) -> list[dict]:
        result = self.call(
            "getUpdates",
            {"offset": offset, "timeout": poll_timeout},
            timeout=poll_timeout + 10,
        )
        return list(result or [])

    def _prepare_rich_text(self, value: str) -> str:
        return _html_rich_text_to_bale_markdown(_persianize_visible_rich_text(value))

    def _supports_native_rich_messages(self) -> bool:
        return False

    def _parse_mode(self) -> str | None:
        return None

    def send_protected_media(
        self,
        chat_id: int,
        source: dict,
        *,
        personalized_file_id: str = "",
    ) -> dict:
        raise BotApiError("Telegram protected media identifiers are unavailable in Bale")

    def configure_profile(self) -> None:
        try:
            self.call(
                "setMyCommands",
                {
                    "commands": [
                        *_shared_bot_commands(),
                    ]
                },
            )
        except BotApiError:
            # Some Bale Bot API revisions do not expose command-profile methods.
            pass
