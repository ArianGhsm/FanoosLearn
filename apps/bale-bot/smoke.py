#!/usr/bin/env python3
from __future__ import annotations

import argparse
import os

from runtime import BaleTransport, required
from fanoos_bot.models import Button, Screen


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Owner-only Bale transport smoke. Dry-run unless --apply is provided."
    )
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    chat = os.getenv("FANOOS_SMOKE_CHAT_ID", "").strip()
    if not args.apply:
        print(
            "dry-run: getMe, send, edit and inline keyboard are ready; "
            "pass --apply in an owner-only environment"
        )
        return
    if not chat:
        raise RuntimeError("FANOOS_SMOKE_CHAT_ID is required with --apply")

    transport = BaleTransport(required("FANOOS_BALE_BOT_TOKEN"))
    me = transport.get_me()
    print("getMe", bool(isinstance(me, dict) and me.get("id")))

    screen = Screen(
        "آزمون انتقال بله برای فانوس\n\n• متن فارسی\n• fallback ساختاریافته",
        ((Button("🏠 خانه", callback="home"),),),
        edit=True,
    )
    sent = transport.send_screen(chat, screen)
    message_id = sent.get("message_id") if isinstance(sent, dict) else None
    print("send_keyboard", bool(message_id))
    if message_id is not None:
        edited = transport.edit_screen(
            chat,
            int(message_id),
            Screen(
                "آزمون انتقال بله برای فانوس\n\n✅ ویرایش آزمایشی انجام شد",
                ((Button("🏠 خانه", callback="home"),),),
                edit=True,
            ),
        )
        print("edit", bool(edited))


if __name__ == "__main__":
    main()
