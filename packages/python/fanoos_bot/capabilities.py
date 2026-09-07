from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True, slots=True)
class PlatformCapabilities:
    platform: str
    max_text_chars: int
    max_callback_bytes: int
    edit_message: bool
    inline_keyboard: bool
    callback_query: bool
    chat_action: bool
    rich_messages: bool
    max_download_bytes: int
    max_document_upload_bytes: int
    forward_protection: bool
    start_payload: bool
    retry_after: bool


TELEGRAM = PlatformCapabilities(
    platform="telegram",
    max_text_chars=4096,
    max_callback_bytes=64,
    edit_message=True,
    inline_keyboard=True,
    callback_query=True,
    chat_action=True,
    rich_messages=True,
    max_download_bytes=20 * 1024 * 1024,
    max_document_upload_bytes=50 * 1024 * 1024,
    forward_protection=True,
    start_payload=True,
    retry_after=True,
)

BALE = PlatformCapabilities(
    platform="bale",
    max_text_chars=4096,
    max_callback_bytes=64,
    edit_message=True,
    inline_keyboard=True,
    callback_query=True,
    chat_action=True,
    rich_messages=False,
    max_download_bytes=20 * 1024 * 1024,
    max_document_upload_bytes=50 * 1024 * 1024,
    forward_protection=False,
    start_payload=False,
    retry_after=True,
)

BY_PLATFORM = {"telegram": TELEGRAM, "bale": BALE}
