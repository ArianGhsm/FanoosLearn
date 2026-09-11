from __future__ import annotations

import json
import os
import sqlite3

from .api import BOT_COMMANDS, BotApiError, TelegramBotApi
from .config import load_settings


def check_runtime(*, settings, api, expected_username: str, require_commands: bool) -> dict[str, object]:
    me = dict(api.call("getMe") or {})
    commands: list = []
    if require_commands:
        commands = list(
            api.call(
                "getMyCommands",
                {"scope": {"type": "all_private_chats"}, "language_code": "fa"},
            )
            or []
        )
    state_ok = False
    if settings.state_db.is_file() and os.access(settings.state_db, os.R_OK | os.W_OK):
        with sqlite3.connect(f"file:{settings.state_db}?mode=ro", uri=True, timeout=5) as connection:
            state_ok = connection.execute(
                "SELECT 1 FROM sqlite_master WHERE type='table' AND name='runtime_state'"
            ).fetchone() == (1,)
    payment_offers_path = getattr(settings, "payment_offers_db", settings.state_db)
    payment_offers_ok = False
    if payment_offers_path.is_file() and os.access(payment_offers_path, os.R_OK | os.W_OK):
        with sqlite3.connect(f"file:{payment_offers_path}?mode=ro", uri=True, timeout=5) as connection:
            payment_offers_ok = connection.execute(
                "SELECT 1 FROM sqlite_master WHERE type='table' AND name='payment_offers'"
            ).fetchone() == (1,)
    username_ok = str(me.get("username") or "").casefold() == expected_username.lstrip("@").casefold()
    expected_command_names = {str(item["command"]) for item in BOT_COMMANDS}
    actual_command_names = {
        str(item.get("command") or "") for item in commands if isinstance(item, dict)
    }
    commands_ok = not require_commands or expected_command_names.issubset(actual_command_names)
    required_channel = str(getattr(settings, "required_channel_username", "") or "").strip().lstrip("@")
    required_channel_admin: bool | str = "not-required"
    required_channel_ok = True
    if required_channel:
        bot_member = dict(api.call(
            "getChatMember",
            {"chat_id": f"@{required_channel}", "user_id": int(me.get("id") or 0)},
        ) or {})
        required_channel_admin = str(bot_member.get("status") or "") in {"creator", "administrator"}
        required_channel_ok = required_channel_admin is True
    booklet_source_id = int(getattr(settings, "booklet_source_channel_id", 0) or 0)
    booklet_source_admin: bool | str = "not-required"
    booklet_source_title: bool | str = "not-required"
    booklet_source_ok = True
    if booklet_source_id < 0:
        source_chat = dict(api.call("getChat", {"chat_id": booklet_source_id}) or {})
        source_member = dict(api.call(
            "getChatMember",
            {"chat_id": booklet_source_id, "user_id": int(me.get("id") or 0)},
        ) or {})
        booklet_source_admin = str(source_member.get("status") or "") in {"creator", "administrator"}
        expected_source_title = str(getattr(settings, "booklet_source_channel_title", "") or "")
        booklet_source_title = not expected_source_title or str(source_chat.get("title") or "") == expected_source_title
        booklet_source_ok = booklet_source_admin is True and booklet_source_title is True
    return {
        "ready": username_ok and commands_ok and state_ok and payment_offers_ok and required_channel_ok and booklet_source_ok,
        "bot_identity": username_ok,
        "persian_commands": len(commands) if require_commands else "not-required",
        "expected_commands": commands_ok,
        "owner_configured": settings.owner_id > 0,
        "state_database": state_ok,
        "shared_payment_offers": payment_offers_ok,
        "required_channel_admin": required_channel_admin,
        "booklet_source_admin": booklet_source_admin,
        "booklet_source_title": booklet_source_title,
    }


def check() -> dict[str, object]:
    settings = load_settings()
    proxy_url = getattr(settings, "telegram_proxy_url", "")
    api = TelegramBotApi(settings.token, proxy_url=proxy_url) if proxy_url else TelegramBotApi(settings.token)
    expected = os.getenv("DENT_BOT_EXPECTED_USERNAME", "Dent1402Bot")
    return check_runtime(settings=settings, api=api, expected_username=expected, require_commands=True)


def main() -> int:
    try:
        result = check()
    except (BotApiError, OSError, ValueError, sqlite3.Error):
        result = {"ready": False, "error": "health-check-failed"}
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result.get("ready") is True else 2


if __name__ == "__main__":
    raise SystemExit(main())
