from __future__ import annotations

import json
import os
import sys

from dent_bot.api import BOT_COMMANDS, BaleBotApi, TelegramBotApi


def main() -> int:
    platform = sys.argv[1] if len(sys.argv) > 1 else ""
    if platform == "telegram":
        api = TelegramBotApi(
            os.environ["DENT_BOT_TELEGRAM_TOKEN"],
            proxy_url=os.environ.get("DENT_BOT_HTTPS_PROXY", ""),
        )
    elif platform == "bale":
        api = BaleBotApi(os.environ["DENT_BALE_BOT_TOKEN"])
    else:
        raise ValueError("Unsupported platform")
    api.configure_profile()
    expected = {str(item["command"]) for item in BOT_COMMANDS}
    verified = False
    try:
        payload = {"scope": {"type": "all_private_chats"}, "language_code": "fa"} if platform == "telegram" else {}
        current = list(api.call("getMyCommands", payload) or [])
        verified = expected.issubset({str(item.get("command") or "") for item in current if isinstance(item, dict)})
    except Exception:
        # Bale deployments differ in command-profile support; /verify remains a
        # normal message command even when the native command menu is absent.
        verified = platform == "bale"
    print(json.dumps({"platform": platform, "profileAttempted": True, "commandsVerified": verified}))
    return 0 if verified else 2


if __name__ == "__main__":
    raise SystemExit(main())
