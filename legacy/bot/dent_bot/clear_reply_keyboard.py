from __future__ import annotations

import argparse
import json

from .api import BaleBotApi, TelegramBotApi
from .bale_config import load_settings as load_bale_settings
from .config import load_settings as load_telegram_settings
from .keyboard_invariant import KEYBOARD_CLEANUP_VERSION
from .state import BotState


def main() -> int:
    parser = argparse.ArgumentParser(description="Clear the owner's stale bot reply keyboard.")
    parser.add_argument("platform", choices=("telegram", "bale"))
    args = parser.parse_args()

    if args.platform == "bale":
        settings = load_bale_settings()
        api = BaleBotApi(settings.token)
    else:
        settings = load_telegram_settings()
        api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
    state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
    try:
        result = api.remove_reply_keyboard(settings.owner_id)
        if not int(result.get("message_id") or 0):
            raise RuntimeError("Bot API did not return a message identifier")
        state.mark_reply_keyboard_removed(settings.owner_id, KEYBOARD_CLEANUP_VERSION)
        print(json.dumps({"success": True, "platform": args.platform}, separators=(",", ":")))
        return 0
    finally:
        state.close()
        api.close()


if __name__ == "__main__":
    raise SystemExit(main())
