from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class PlatformCapabilities:
    platform: str
    max_text_chars: int = 4096
    max_callback_bytes: int = 64
    max_download_bytes: int = 20_000_000
    max_document_upload_bytes: int = 50_000_000
    max_caption_chars: int = 1024
    supports_edit: bool = True
    supports_inline_callback: bool = True
    supports_chat_action: bool = True
    supports_retry_after: bool = True
    supports_forward_protection: bool = False
    supports_start_payload: bool = False
    supports_native_rich: bool = False
    supports_rich_edit: bool = False
    supports_rich_draft: bool = False
    supports_rtl: bool = False
    rich_max_chars: int = 0
    rich_max_blocks: int = 0
    rich_max_nesting: int = 0
    rich_max_media: int = 0
    rich_max_table_columns: int = 0
    reply_style: str = "reply_parameters"


TELEGRAM = PlatformCapabilities(
    "telegram",
    supports_forward_protection=True,
    supports_start_payload=True,
    supports_native_rich=True,
    supports_rich_edit=True,
    supports_rich_draft=True,
    supports_rtl=True,
    rich_max_chars=32_768,
    rich_max_blocks=500,
    rich_max_nesting=16,
    rich_max_media=50,
    rich_max_table_columns=20,
    reply_style="reply_parameters",
)

BALE = PlatformCapabilities(
    "bale",
    max_caption_chars=4096,
    reply_style="reply_to_message_id",
)

REGISTRY = {"telegram": TELEGRAM, "bale": BALE}


def for_platform(platform: str) -> PlatformCapabilities:
    try:
        return REGISTRY[platform.lower()]
    except KeyError as exc:
        raise ValueError("unsupported platform") from exc
