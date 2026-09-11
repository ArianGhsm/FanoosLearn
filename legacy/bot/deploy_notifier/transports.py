from __future__ import annotations

import json
import html
import re
import urllib.error
import urllib.request
from urllib.parse import urlsplit

from dent_bot.api import _html_rich_text_to_bale_markdown, _persianize_visible_rich_text
from dent_bot.site_api import SiteApiClient, SiteApiError

from .core import DeliveryResult, DeployEvent


def validate_loopback_http_proxy(value: str) -> str:
    proxy_url = value.strip()
    if not proxy_url:
        return ""
    parsed = urlsplit(proxy_url)
    if (
        parsed.scheme != "http"
        or parsed.hostname not in {"127.0.0.1", "localhost"}
        or parsed.username
        or parsed.password
        or parsed.path not in {"", "/"}
        or parsed.query
        or parsed.fragment
        or parsed.port is None
    ):
        raise ValueError("Deploy Telegram proxy must be an unauthenticated loopback HTTP proxy with an explicit port")
    return proxy_url


class BotApiTransport:
    def __init__(
        self,
        *,
        name: str,
        api_root: str,
        token: str,
        chat_id: str,
        timeout: float,
        parse_mode: str = "",
        proxy_url: str = "",
        bale_markdown: bool = False,
    ) -> None:
        self.name = name
        self._endpoint = f"{api_root.rstrip('/')}/bot{token}/sendMessage"
        self._chat_id = chat_id
        self._timeout = timeout
        self._parse_mode = parse_mode
        self._proxy_url = validate_loopback_http_proxy(proxy_url)
        self._bale_markdown = bale_markdown
        self._opener = (
            urllib.request.build_opener(urllib.request.ProxyHandler({"https": self._proxy_url}))
            if self._proxy_url
            else None
        )

    def _send_fields(self, fields: dict[str, object]) -> DeliveryResult:
        payload = json.dumps(fields, ensure_ascii=False).encode("utf-8")
        request = urllib.request.Request(
            self._endpoint,
            data=payload,
            method="POST",
            headers={"Content-Type": "application/json", "Accept": "application/json"},
        )
        try:
            open_request = self._opener.open if self._opener is not None else urllib.request.urlopen
            with open_request(request, timeout=self._timeout) as response:
                body = response.read(65536)
            decoded = json.loads(body.decode("utf-8"))
            if isinstance(decoded, dict) and decoded.get("ok") is True:
                return DeliveryResult(True)
            return DeliveryResult(False, "api-rejected-request")
        except urllib.error.HTTPError as error:
            return DeliveryResult(False, f"http-{int(error.code)}")
        except (urllib.error.URLError, TimeoutError, OSError):
            return DeliveryResult(False, "network-error")
        except (UnicodeError, json.JSONDecodeError):
            return DeliveryResult(False, "invalid-api-response")

    @staticmethod
    def _plain_text(message: str) -> str:
        without_tags = re.sub(r"<[^>]+>", "", message)
        return html.unescape(without_tags)

    def send(self, event: DeployEvent) -> DeliveryResult:
        html_message = _persianize_visible_rich_text(event.message(html_format=True, platform=self.name))
        message = _html_rich_text_to_bale_markdown(html_message) if self._bale_markdown else html_message
        fields: dict[str, object] = {
            "chat_id": self._chat_id,
            "text": message,
            "disable_web_page_preview": True,
        }
        if self._parse_mode:
            fields["parse_mode"] = self._parse_mode
        result = self._send_fields(fields)
        if result.ok or not self._parse_mode or result.reason not in {"http-400", "api-rejected-request"}:
            return result
        fallback = dict(fields)
        fallback.pop("parse_mode", None)
        fallback["text"] = self._plain_text(message)
        return self._send_fields(fallback)


class SiteNotificationTransport:
    name = "site"

    def __init__(self, *, client: SiteApiClient, owner_id: int) -> None:
        self._client = client
        self._owner_id = owner_id

    def send(self, event: DeployEvent) -> DeliveryResult:
        try:
            self._client.request(
                "createDeployNotification",
                self._owner_id,
                event=event.to_dict(),
            )
            return DeliveryResult(True)
        except SiteApiError as error:
            reason = error.code.strip().lower().replace("_", "-") or "site-api-error"
            return DeliveryResult(False, reason[:120])
