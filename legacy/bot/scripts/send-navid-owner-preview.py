from __future__ import annotations

import json
import os
import sys
from pathlib import Path

from dent_bot.api import TelegramBotApi
from dent_bot.ui import navid_assignment_photo_caption


def main() -> int:
    if len(sys.argv) != 3:
        raise SystemExit("usage: send-navid-owner-preview.py IMAGE ASSIGNMENT_JSON")
    image_path = Path(sys.argv[1])
    assignment_path = Path(sys.argv[2])
    image = image_path.read_bytes()
    assignment = json.loads(assignment_path.read_text(encoding="utf-8"))
    if not isinstance(assignment, dict):
        raise ValueError("Assignment payload must be an object")
    token = os.environ.get("DENT_BOT_TELEGRAM_TOKEN", "").strip()
    owner_id = os.environ.get("DENT_BOT_OWNER_TELEGRAM_ID", "").strip()
    if not token or not owner_id.isdigit():
        raise ValueError("Telegram owner preview environment is incomplete")
    api = TelegramBotApi(token, proxy_url=os.environ.get("DENT_BOT_HTTPS_PROXY", "").strip())
    api.send_photo_bytes(
        int(owner_id),
        image,
        caption=navid_assignment_photo_caption(assignment, event_type="test", platform="telegram"),
        reply_markup={"inline_keyboard": []},
        filename="navid-assignment-preview.png",
    )
    print("NAVID_OWNER_PREVIEW_SENT=true")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
