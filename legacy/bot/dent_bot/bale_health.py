from __future__ import annotations

import json
import os
import sqlite3

from .api import BaleBotApi, BotApiError
from .bale_config import load_settings
from .health import check_runtime


def main() -> int:
    try:
        settings = load_settings()
        api = BaleBotApi(settings.token)
        result = check_runtime(
            settings=settings,
            api=api,
            expected_username=os.getenv("DENT_BALE_EXPECTED_USERNAME", "dent1402bot"),
            require_commands=False,
        )
    except (BotApiError, OSError, ValueError, sqlite3.Error):
        result = {"ready": False, "error": "health-check-failed"}
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result.get("ready") is True else 2


if __name__ == "__main__":
    raise SystemExit(main())
