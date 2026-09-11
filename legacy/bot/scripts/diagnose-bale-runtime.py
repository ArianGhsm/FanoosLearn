#!/usr/bin/env python3
"""Run secret-safe Bale runtime diagnostics on a configured server."""

from __future__ import annotations

import json
import sqlite3

from dent_bot.api import BaleBotApi, BotApiError
from dent_bot.bale_config import load_settings
from dent_bot.site_api import SiteApiClient, SiteApiError


def main() -> int:
    result: dict[str, object] = {
        "configuration": False,
        "state_database": False,
        "bale_api": False,
        "bot_identity": False,
        "signed_site_api": False,
        "owner_account_linked": False,
    }
    try:
        settings = load_settings()
        result["configuration"] = True
    except (OSError, ValueError) as error:
        result["configuration_error"] = type(error).__name__
        result["configuration_reason"] = str(error)[:120]
        print(json.dumps(result, separators=(",", ":")))
        return 2

    try:
        with sqlite3.connect(f"file:{settings.state_db}?mode=ro", uri=True, timeout=5) as connection:
            result["state_database"] = connection.execute(
                "SELECT 1 FROM sqlite_master WHERE type='table' AND name='runtime_state'"
            ).fetchone() == (1,)
    except (OSError, sqlite3.Error) as error:
        result["state_error"] = type(error).__name__

    try:
        api = BaleBotApi(settings.token)
        me = dict(api.call("getMe") or {})
        result["bale_api"] = True
        result["bot_identity"] = (
            str(me.get("username") or "").casefold()
            == settings.bot_username.casefold()
        )
    except BotApiError as error:
        result["bale_api_error"] = "transient" if error.transient else "rejected"

    try:
        client = SiteApiClient(
            settings.site_api_url,
            settings.site_service_secret,
            platform="bale",
            relay_secret=settings.site_relay_secret,
        )
        account = client.account(settings.owner_id)
        result["signed_site_api"] = account.get("success") is True
        result["owner_account_linked"] = account.get("linked") is True
    except SiteApiError as error:
        result["site_api_error"] = error.code
        result["site_http_status"] = error.status
    except OSError:
        result["site_api_error"] = "network"

    ready = all(
        result.get(key) is True
        for key in (
            "configuration",
            "state_database",
            "bale_api",
            "bot_identity",
            "signed_site_api",
            "owner_account_linked",
        )
    )
    result["ready"] = ready
    print(json.dumps(result, separators=(",", ":")))
    return 0 if ready else 2


if __name__ == "__main__":
    raise SystemExit(main())
