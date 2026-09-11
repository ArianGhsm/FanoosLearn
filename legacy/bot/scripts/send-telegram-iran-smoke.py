#!/usr/bin/env python3
"""Send and edit one owner-only Telegram message through the configured proxy."""

from __future__ import annotations

from dent_bot.api import TelegramBotApi
from dent_bot.config import load_settings


def main() -> int:
    settings = load_settings()
    api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
    webhook = dict(api.call("getWebhookInfo") or {})
    if str(webhook.get("url") or ""):
        print("TELEGRAM_PROXY_SMOKE_FAILED")
        return 2
    keyboard = {
        "inline_keyboard": [[
            {"text": "باز کردن سایت", "url": f"{settings.site_url}/app/", "style": "primary"}
        ]]
    }
    sent = api.send(
        settings.owner_id,
        "<b>✅ انتقال ربات تلگرام به سرور ایران</b>\n\n"
        "اتصال واقعی Telegram API از مسیر اختصاصی proxy برقرار شد.\n"
        "<blockquote>بله، سایت، SSH و بکاپ همچنان از مسیر مستقیم ایران استفاده می‌کنند.</blockquote>\n"
        "نسخهٔ آزمایش: <code>IRAN-EGRESS-OK</code>",
        keyboard,
    )
    message_id = int(sent.get("message_id") or 0)
    if message_id <= 0:
        print("TELEGRAM_PROXY_SMOKE_FAILED")
        return 2
    edited = api.edit(
        settings.owner_id,
        message_id,
        "<b>✅ انتقال ربات تلگرام به سرور ایران تأیید شد</b>\n\n"
        "ارسال و ویرایش پیام هر دو از proxy محلی اختصاصی تلگرام عبور کردند.\n"
        "<code>IRAN-EGRESS-SEND-EDIT-OK</code>",
        keyboard,
    )
    print("TELEGRAM_PROXY_SMOKE_OK" if int(edited.get("message_id") or 0) == message_id else "TELEGRAM_PROXY_SMOKE_FAILED")
    return 0 if int(edited.get("message_id") or 0) == message_id else 2


if __name__ == "__main__":
    raise SystemExit(main())
