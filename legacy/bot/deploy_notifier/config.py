from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path

from dent_bot.config import decode_service_secret, validate_site_api_url
from dent_bot.site_api import SiteApiClient

from .transports import BotApiTransport, SiteNotificationTransport


def _usable_secret_value(value: str) -> bool:
    stripped = value.strip()
    return bool(stripped) and not (stripped.startswith("<") and stripped.endswith(">"))


@dataclass(frozen=True)
class Settings:
    state_dir: Path
    channels: tuple[str, ...]
    timeout_seconds: float


def _channels() -> tuple[str, ...]:
    raw = os.getenv("DENT_DEPLOY_NOTIFY_CHANNELS", "telegram,bale,site")
    values = []
    for item in raw.split(","):
        name = item.strip().lower()
        if name and name not in values:
            values.append(name)
    unsupported = set(values) - {"telegram", "bale", "site"}
    if unsupported:
        raise ValueError("Unsupported deploy notification channel configured")
    return tuple(values)


def load_settings() -> Settings:
    timeout = float(os.getenv("DENT_DEPLOY_NOTIFY_TIMEOUT_SECONDS", "10"))
    timeout = min(30.0, max(1.0, timeout))
    return Settings(
        state_dir=Path(os.getenv("DENT_DEPLOY_NOTIFIER_STATE_DIR", "/var/lib/integrated-dent/deploy-notifier")),
        channels=_channels(),
        timeout_seconds=timeout,
    )


def load_transports(settings: Settings) -> dict[str, object]:
    definitions = {
        "telegram": (
            "https://api.telegram.org",
            os.getenv("DENT_DEPLOY_TELEGRAM_BOT_TOKEN", "").strip(),
            os.getenv("DENT_DEPLOY_TELEGRAM_CHAT_ID", "").strip(),
            "HTML",
            os.getenv("DENT_DEPLOY_TELEGRAM_PROXY_URL", "").strip(),
            False,
        ),
        "bale": (
            "https://tapi.bale.ai",
            os.getenv("DENT_DEPLOY_BALE_BOT_TOKEN", "").strip(),
            os.getenv("DENT_DEPLOY_BALE_CHAT_ID", "").strip(),
            "",
            "",
            True,
        ),
    }
    transports: dict[str, object] = {}
    for name in (channel for channel in settings.channels if channel in definitions):
        api_root, token, chat_id, parse_mode, proxy_url, bale_markdown = definitions[name]
        if _usable_secret_value(token) and _usable_secret_value(chat_id):
            transports[name] = BotApiTransport(
                name=name,
                api_root=api_root,
                token=token,
                chat_id=chat_id,
                timeout=settings.timeout_seconds,
                parse_mode=parse_mode,
                proxy_url=proxy_url,
                bale_markdown=bale_markdown,
            )
    if "site" in settings.channels:
        owner_raw = os.getenv("DENT_DEPLOY_SITE_OWNER_ID", "").strip()
        site_url = os.getenv("DENT_DEPLOY_SITE_API_URL", "").strip()
        secret_raw = os.getenv("DENT_DEPLOY_SITE_SERVICE_SECRET", "").strip()
        if _usable_secret_value(site_url) and _usable_secret_value(secret_raw) and owner_raw.isdigit():
            client = SiteApiClient(
                validate_site_api_url(site_url),
                decode_service_secret(secret_raw),
                platform=os.getenv("DENT_DEPLOY_SITE_PLATFORM", "telegram").strip() or "telegram",
                timeout=settings.timeout_seconds,
            )
            transports["site"] = SiteNotificationTransport(client=client, owner_id=int(owner_raw))
    return transports


def configured_channel_health(settings: Settings) -> dict[str, bool]:
    env_names = {
        "telegram": ("DENT_DEPLOY_TELEGRAM_BOT_TOKEN", "DENT_DEPLOY_TELEGRAM_CHAT_ID"),
        "bale": ("DENT_DEPLOY_BALE_BOT_TOKEN", "DENT_DEPLOY_BALE_CHAT_ID"),
        "site": ("DENT_DEPLOY_SITE_API_URL", "DENT_DEPLOY_SITE_SERVICE_SECRET", "DENT_DEPLOY_SITE_OWNER_ID"),
    }
    return {
        channel: all(_usable_secret_value(os.getenv(name, "")) for name in env_names[channel])
        for channel in settings.channels
    }
