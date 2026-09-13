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
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.botapi import BotApiError, JsonBotApiTransport
from fanoos_bot.capabilities import TELEGRAM
from fanoos_bot.runtime import BotRuntime, IncomingDocument, NotificationPump, UpdateContext
from fanoos_bot.state import LocalState


class TelegramTransport(JsonBotApiTransport):
    def __init__(
        self,
        token: str,
        *,
        rich_ui_enabled: bool | None = None,
        activity_ui_enabled: bool | None = None,
    ):
        super().__init__(
            "https://api.telegram.org",
            token,
            TELEGRAM,
            rich_ui_enabled=rich_ui_enabled,
            activity_ui_enabled=activity_ui_enabled,
        )


def required(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(f"{name} is required")
    return value


def build():
    state = LocalState(
        os.getenv("FANOOS_TELEGRAM_STATE", "/var/lib/fanoos/telegram/state.sqlite3")
    )
    backend = FanoosApiClient(
        required("FANOOS_API_ORIGIN"),
        required("FANOOS_TELEGRAM_SERVICE_KEY_ID"),
        required("FANOOS_TELEGRAM_SERVICE_SECRET"),
    )
    app = BotApplication(
        backend,
        state,
        "telegram",
        ApplicationConfig(
            os.getenv("FANOOS_WEB_BASE_URL", "").strip(),
            os.getenv("FANOOS_DEPLOYMENT_TARGET_KEY", "").strip(),
            os.getenv("FANOOS_PROTECTED_RENDERER_VERSION", "fanoos-raster-v2"),
            os.getenv("FANOOS_DEFAULT_COUNTRY_CODE", "").strip(),
            os.getenv("FANOOS_DEFAULT_COUNTRY_NAME", "").strip(),
            # Fail closed at boot, never a default: this key decodes marks
            # already distributed in production and must never be rotated.
            required("FANOOS_PROTECTED_MEDIA_FINGERPRINT_KEY").encode(),
        ),
    )
    transport = TelegramTransport(required("FANOOS_TELEGRAM_BOT_TOKEN"))
    return (
        state,
        backend,
        transport,
        BotRuntime("telegram", transport, app, state),
        NotificationPump("telegram", backend, transport, state),
    )


def context(update: dict):
    uid = str(update.get("update_id", ""))
    if isinstance(update.get("message"), dict):
        message = update["message"]
        chat = message.get("chat") or {}
        user = message.get("from") or {}
        contact = message.get("contact") if isinstance(message.get("contact"), dict) else None
        # Only trust a contact as "the sender's own phone" when Telegram's own
        # user_id on the shared card matches the sender -- the join wizard's
        # request_contact button only ever produces this, but a message can
        # also carry an arbitrary forwarded contact card, which must not be
        # accepted as proof of the sender's own number.
        contact_phone = None
        if contact and str(contact.get("user_id") or "") == str(user.get("id") or ""):
            contact_phone = str(contact.get("phone_number") or "") or None
        document_payload = message.get("document") if isinstance(message.get("document"), dict) else None
        document = None
        if document_payload and str(document_payload.get("file_id") or ""):
            file_size = document_payload.get("file_size")
            document = IncomingDocument(
                file_id=str(document_payload["file_id"]),
                file_size=int(file_size) if isinstance(file_size, (int, float)) else None,
                file_name=str(document_payload.get("file_name") or ""),
                mime_type=str(document_payload.get("mime_type") or ""),
            )
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
            contact_phone,
            document,
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
            None,
            None,
        )
    return None


def _handle_update(runtime: BotRuntime, update: dict) -> None:
    parsed = context(update)
    if not parsed:
        return
    ctx, text, callback, contact_phone, document = parsed
    if callback is not None:
        runtime.handle_callback(ctx, callback)
    elif document is not None:
        runtime.handle_document(ctx, document)
    elif contact_phone:
        runtime.handle_contact(ctx, contact_phone)
    elif text:
        runtime.handle_message(ctx, text)


def main():
    logging.basicConfig(
        level=os.getenv("LOG_LEVEL", "INFO"),
        format="%(asctime)s %(levelname)s %(message)s",
    )
    state, backend, transport, runtime, pump = build()
    offset = state.get_offset("telegram")
    try:
        while True:
            try:
                updates = transport.get_updates(offset, 20) or []
                for update in updates:
                    update_id = int(update.get("update_id", 0))
                    try:
                        _handle_update(runtime, update)
                    except BotApiError as exc:
                        # The application result has already been computed.
                        # Do not replay it merely because presentation failed.
                        logging.warning(
                            "telegram presentation failure code=%s transient=%s",
                            exc.code,
                            exc.transient,
                        )
                    finally:
                        offset = max(offset, update_id + 1)
                        state.set_offset("telegram", offset)
                for _ in range(5):
                    if not runtime.flush_delivery_receipt_once():
                        break
                for _ in range(5):
                    if not pump.run_once():
                        break
            except BotApiError as exc:
                # Polling-level transport errors are safe to retry; no update
                # has been handed to application business logic.
                logging.warning(
                    "telegram transport failure code=%s transient=%s",
                    exc.code,
                    exc.transient,
                )
                time.sleep(min(10, max(1, exc.retry_after or 2)))
            except Exception as exc:
                logging.error(
                    "telegram runtime iteration failed type=%s",
                    type(exc).__name__,
                )
                time.sleep(2)
    finally:
        state.close()


if __name__ == "__main__":
    main()
