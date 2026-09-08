#!/usr/bin/env python3
from __future__ import annotations

import argparse
import os

from runtime import TelegramTransport, required
from fanoos_bot.models import Button, Screen


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Owner-only Telegram transport smoke. Dry-run unless --apply is provided."
    )
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    chat = os.getenv("FANOOS_SMOKE_CHAT_ID", "").strip()
    if not args.apply:
        print(
            "dry-run: getMe, plain send, Rich send/edit, forced plain fallback, "
            "and protect_content fixture are ready; pass --apply in an owner-only environment"
        )
        return
    if not chat:
        raise RuntimeError("FANOOS_SMOKE_CHAT_ID is required with --apply")

    token = required("FANOOS_TELEGRAM_BOT_TOKEN")
    rich = TelegramTransport(token, rich_ui_enabled=True, activity_ui_enabled=False)
    me = rich.get_me()
    print("getMe", bool(isinstance(me, dict) and me.get("id")))

    plain = TelegramTransport(token, rich_ui_enabled=False, activity_ui_enabled=False)
    sent_plain = plain.send_screen(chat, Screen("ℹ️ آزمون سادهٔ انتقال فانوس"))
    print("plain_send", bool(sent_plain))

    rich_screen = Screen(
        "آزمون رابط غنی فانوس\n\n• بند اول\n• بند دوم\n\nℹ️ فقط دادهٔ آزمایشی",
        ((Button("🏠 خانه", callback="home"),),),
        edit=True,
    )
    sent_rich = rich.send_screen(chat, rich_screen)
    rich_id = sent_rich.get("message_id") if isinstance(sent_rich, dict) else None
    print("rich_send", bool(rich_id))
    if rich_id is not None:
        edited = rich.edit_screen(
            chat,
            int(rich_id),
            Screen(
                "آزمون رابط غنی فانوس\n\n✅ ویرایش آزمایشی انجام شد",
                ((Button("🏠 خانه", callback="home"),),),
                edit=True,
            ),
        )
        print("rich_edit", bool(edited))

    fallback = plain.send_screen(
        chat,
        Screen(
            "حالت fallback فانوس\n\n• همان callbackها\n• بدون Rich Message",
            ((Button("🏠 خانه", callback="home"),),),
        ),
    )
    print("fallback_mode", bool(fallback))

    protected = rich.send_screen(
        chat,
        Screen(
            "🔒 fixture محافظت‌شده؛ حاوی هیچ دادهٔ واقعی نیست.",
            protect_content=True,
        ),
    )
    print("protect_content_fixture", bool(protected))


if __name__ == "__main__":
    main()
