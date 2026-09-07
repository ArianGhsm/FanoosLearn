from __future__ import annotations
from dataclasses import dataclass

@dataclass(frozen=True)
class PlatformCapabilities:
    platform: str
    max_text_chars: int = 4096
    max_callback_bytes: int = 64
    max_download_bytes: int = 20 * 1024 * 1024
    max_document_upload_bytes: int = 50 * 1024 * 1024
    supports_edit: bool = True
    supports_inline_callback: bool = True
    supports_chat_action: bool = True
    supports_retry_after: bool = True
    supports_forward_protection: bool = False
    supports_start_payload: bool = False
    supports_native_rich: bool = False

TELEGRAM = PlatformCapabilities('telegram', supports_forward_protection=True, supports_start_payload=True, supports_native_rich=True)
BALE = PlatformCapabilities('bale')
REGISTRY = {'telegram': TELEGRAM, 'bale': BALE}

def for_platform(platform: str) -> PlatformCapabilities:
    try: return REGISTRY[platform.lower()]
    except KeyError as exc: raise ValueError('unsupported platform') from exc
