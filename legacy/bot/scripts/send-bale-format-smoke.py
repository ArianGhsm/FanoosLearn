#!/usr/bin/env python3
"""Send one owner-only live message that exercises Bale-native Markdown."""

from __future__ import annotations

from dent_bot.api import BaleBotApi
from dent_bot.bale_config import load_settings


def main() -> int:
    settings = load_settings()
    api = BaleBotApi(settings.token)
    result = api.send(
        settings.owner_id,
        "<b>✅ آزمون قالب‌بندی بله</b>\n\n"
        "این عبارت باید <i>مورب</i> و شناسهٔ <code>BALE-OK-1405</code> باید کدنویسی‌شده باشد.\n"
        '<a href="https://dentistry1402tums.ir">پیوند آزمایشی سایت</a>\n'
        "<blockquote>این بخش باید به‌صورت نقل‌قول خوانا نمایش داده شود.</blockquote>",
        {"inline_keyboard": []},
    )
    print("BALE_FORMAT_SMOKE_OK" if result.get("message_id") else "BALE_FORMAT_SMOKE_FAILED")
    return 0 if result.get("message_id") else 2


if __name__ == "__main__":
    raise SystemExit(main())
