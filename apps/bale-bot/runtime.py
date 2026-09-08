#!/usr/bin/env python3
from __future__ import annotations

import logging
import os
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "packages/python"))

from fanoos_bot.api import FanoosApiClient
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.botapi import BotApiError, JsonBotApiTransport
from fanoos_bot.capabilities import BALE
from fanoos_bot.runtime import BotRuntime, NotificationPump, UpdateContext
from fanoos_bot.state import LocalState


class BaleTransport(JsonBotApiTransport):
    def __init__(self, token: str):
        super().__init__("https://tapi.bale.ai", token, BALE)


def required(name: str) -> str:
    value = os.getenv(name, "").strip()
    if not value:
        raise RuntimeError(f"{name} is required")
    return value


def build():
    state = LocalState(os.getenv("FANOOS_BALE_STATE", "/var/lib/fanoos/bale/state.sqlite3"))
    backend = FanoosApiClient(
        required("FANOOS_API_ORIGIN"),
        required("FANOOS_BALE_SERVICE_KEY_ID"),
        required("FANOOS_BALE_SERVICE_SECRET"),
    )
    app = BotApplication(
        backend,
        state,
        "bale",
        ApplicationConfig(os.getenv("FANOOS_WEB_BASE_URL", "").strip()),
    )
    transport = BaleTransport(required("FANOOS_BALE_BOT_TOKEN"))
    return (
        state,
        backend,
        transport,
        BotRuntime("bale", transport, app, state),
        NotificationPump("bale", backend, transport, state),
    )


def context(update: dict):
    uid = str(update.get("update_id", ""))
    if isinstance(update.get("message"), dict):
        message = update["message"]
        chat = message.get("chat") or {}
        user = message.get("from") or {}
        return (
            UpdateContext(
                str(user.get("id", "")),
                str(chat.get("id", "")),
                chat.get("type") == "private",
                message.get("message_id"),
                None,
                uid,
            ),
            str(message.get("text") or ""),
            None,
        )
    if isinstance(update.get("callback_query"), dict):
        query = update["callback_query"]
        user = query.get("from") or {}
        message = query.get("message") or {}
        chat = message.get("chat") or {}
        return (
            UpdateContext(
                str(user.get("id", "")),
                str(chat.get("id", "")),
                chat.get("type") == "private",
                message.get("message_id"),
                str(query.get("id") or ""),
                uid,
            ),
            "",
            str(query.get("data") or ""),
        )
    return None


def _handle_update(runtime: BotRuntime, update: dict) -> None:
    parsed = context(update)
    if not parsed:
        return
    ctx, text, callback = parsed
    if callback is not None:
        runtime.handle_callback(ctx, callback)
    elif text:
        runtime.handle_message(ctx, text)


def main():
    logging.basicConfig(
        level=os.getenv("LOG_LEVEL", "INFO"),
        format="%(asctime)s %(levelname)s %(message)s",
    )
    state, backend, transport, runtime, pump = build()
    offset = state.get_offset("bale")
    try:
        while True:
            try:
                updates = transport.get_updates(offset, 20) or []
                for update in updates:
                    update_id = int(update.get("update_id", 0))
                    try:
                        _handle_update(runtime, update)
                    except BotApiError as exc:
                        logging.warning(
                            "bale presentation failure code=%s transient=%s",
                            exc.code,
                            exc.transient,
                        )
                    finally:
                        offset = max(offset, update_id + 1)
                        state.set_offset("bale", offset)
                for _ in range(5):
                    if not runtime.flush_delivery_receipt_once():
                        break
                for _ in range(5):
                    if not pump.run_once():
                        break
            except BotApiError as exc:
                logging.warning(
                    "bale transport failure code=%s transient=%s",
                    exc.code,
                    exc.transient,
                )
                time.sleep(min(10, max(1, exc.retry_after or 2)))
            except Exception as exc:
                logging.error(
                    "bale runtime iteration failed type=%s",
                    type(exc).__name__,
                )
                time.sleep(2)
    finally:
        state.close()


if __name__ == "__main__":
    main()
