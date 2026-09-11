from __future__ import annotations

import os
import base64
from dataclasses import dataclass
from pathlib import Path
from urllib.parse import urlsplit


@dataclass(frozen=True)
class BotSettings:
    token: str
    bot_username: str
    owner_id: int
    site_url: str
    state_db: Path
    payment_offers_db: Path
    poll_timeout: int
    update_workers: int
    site_timeout_seconds: float
    platform: str
    site_api_url: str
    site_service_secret: bytes
    site_relay_secret: str
    notification_poll_seconds: int
    notification_batch_size: int
    payment_result_push_enabled: bool
    payment_result_poll_seconds: int
    payment_result_batch_size: int
    navid_daily_enabled: bool
    navid_daily_hour: int
    navid_daily_timezone: str
    navid_retry_seconds: int
    navid_group_enabled: bool
    navid_group_chat_id: int
    navid_group_poll_seconds: int
    telegram_proxy_url: str
    exams_v1_enabled: bool
    student_assistant_v1_enabled: bool
    required_channel_username: str
    booklet_source_channel_id: int
    booklet_source_channel_title: str
    booklet_media_workers: int
    booklet_media_queue_size: int
    booklet_access_mode: str
    booklet_fingerprint_key: bytes
    booklet_watermark_font: Path
    booklet_temp_root: Path
    booklet_qpdf_binary: str
    booklet_max_download_bytes: int
    booklet_pdf_normalizer: str
    booklet_raster_dpi: int
    booklet_raster_jpeg_quality: int
    booklet_max_output_bytes: int
    booklet_processing_timeout_seconds: int
    booklet_orphan_max_age_seconds: int
    booklet_rate_window_seconds: int
    booklet_rate_max_requests: int
    booklet_same_document_cooldown_seconds: int


def decode_service_secret(raw: str) -> bytes:
    raw = raw.strip()
    decoded = b""
    if len(raw) >= 64:
        try:
            decoded = bytes.fromhex(raw[:64])
        except ValueError:
            decoded = b""
    if not decoded and len(raw) >= 43:
        try:
            decoded = base64.urlsafe_b64decode(raw + "=" * ((4 - len(raw) % 4) % 4))
        except (ValueError, TypeError):
            decoded = b""
    if len(decoded) < 32:
        raise ValueError("Site bot service secret is not configured")
    return decoded[:32]


def load_service_secret() -> bytes:
    return decode_service_secret(os.getenv("DENT_BOT_SITE_SERVICE_SECRET", ""))


def load_optional_relay_secret() -> str:
    raw = os.getenv("DENT_BOT_SITE_RELAY_SECRET", "").strip()
    if not raw:
        return ""
    lowered = raw.lower()
    if lowered in {"changeme", "replace-me", "secret", "development", "test"}:
        raise ValueError("Site relay secret is weak")
    decoded = b""
    if len(raw) == 64:
        try:
            decoded = bytes.fromhex(raw)
        except ValueError:
            decoded = b""
    if not decoded and len(raw) >= 43:
        try:
            decoded = base64.urlsafe_b64decode(raw + "=" * ((4 - len(raw) % 4) % 4))
        except (ValueError, TypeError):
            decoded = b""
    if len(decoded) < 32:
        raise ValueError("Site relay secret must contain at least 32 random bytes")
    return raw


def load_booklet_fingerprint_key(*, required: bool = True) -> bytes:
    raw = os.getenv("DENT_BOT_BOOKLET_FINGERPRINT_KEY", "").strip()
    if not raw:
        if not required:
            return b""
        raise ValueError("Booklet fingerprint key is not configured")
    decoded = b""
    if len(raw) == 64:
        try:
            decoded = bytes.fromhex(raw)
        except ValueError:
            decoded = b""
    if not decoded:
        try:
            decoded = base64.urlsafe_b64decode(raw + "=" * ((4 - len(raw) % 4) % 4))
        except (ValueError, TypeError):
            decoded = b""
    if len(decoded) < 32:
        raise ValueError("Booklet fingerprint key must contain at least 32 random bytes")
    return decoded[:32]


def validate_site_api_url(value: str) -> str:
    parsed = urlsplit(value)
    if parsed.scheme != "https" or not parsed.netloc or parsed.username or parsed.password:
        raise ValueError("Site bot API URL must be a public HTTPS URL")
    return value


def load_settings() -> BotSettings:
    token = os.getenv("DENT_BOT_TELEGRAM_TOKEN", "").strip()
    if not token or token.startswith("<"):
        raise ValueError("Telegram bot token is not configured")
    owner_raw = os.getenv("DENT_BOT_OWNER_TELEGRAM_ID", "").strip()
    if not owner_raw.isdigit():
        raise ValueError("Owner Telegram ID is not configured")
    site_url = os.getenv("DENT_BOT_SITE_URL", "https://dentistry1402tums.ir").strip().rstrip("/")
    parsed = urlsplit(site_url)
    if parsed.scheme != "https" or not parsed.netloc or parsed.username or parsed.password:
        raise ValueError("Site URL must be a public HTTPS origin")
    timeout = min(50, max(10, int(os.getenv("DENT_BOT_POLL_TIMEOUT_SECONDS", "30"))))
    update_workers = min(16, max(2, int(os.getenv("DENT_BOT_UPDATE_WORKERS", "8"))))
    site_timeout_seconds = min(12.0, max(2.0, float(os.getenv("DENT_BOT_SITE_TIMEOUT_SECONDS", "6"))))
    notification_poll_seconds = min(300, max(15, int(os.getenv("DENT_BOT_NOTIFICATION_POLL_SECONDS", "30"))))
    notification_batch_size = min(20, max(1, int(os.getenv("DENT_BOT_NOTIFICATION_BATCH_SIZE", "10"))))
    payment_result_push_enabled = os.getenv("DENT_BOT_PAYMENT_RESULT_PUSH_ENABLED", "0").strip() == "1"
    navid_daily_enabled = os.getenv("DENT_BOT_NAVID_DAILY_ENABLED", "0").strip() == "1"
    navid_daily_hour = min(23, max(0, int(os.getenv("DENT_BOT_NAVID_DAILY_HOUR", "9"))))
    navid_retry_seconds = min(21600, max(300, int(os.getenv("DENT_BOT_NAVID_RETRY_SECONDS", "3600"))))
    booklet_access_mode = os.getenv("DENT_BOT_BOOKLET_ACCESS_MODE", "all-authenticated").strip()
    if booklet_access_mode not in {"all-authenticated"}:
        raise ValueError("Unsupported booklet access mode")
    booklet_source_channel_id = int(os.getenv("DENT_BOT_BOOKLET_SOURCE_CHANNEL_ID", "0").strip() or "0")
    booklet_source_channel_title = os.getenv("DENT_BOT_BOOKLET_SOURCE_CHANNEL_TITLE", "").strip()
    if booklet_source_channel_id < 0 and not booklet_source_channel_title:
        raise ValueError("Protected booklet source channel title is not configured")
    booklet_pdf_normalizer = os.getenv("DENT_BOT_BOOKLET_PDF_NORMALIZER", "none").strip().lower()
    if booklet_pdf_normalizer not in {"none", "pikepdf"}:
        raise ValueError("Unsupported booklet PDF normalizer")
    return BotSettings(
        token=token,
        bot_username=os.getenv("DENT_BOT_EXPECTED_USERNAME", "Dent1402Bot").strip().lstrip("@") or "Dent1402Bot",
        owner_id=int(owner_raw),
        site_url=site_url,
        state_db=Path(os.getenv("DENT_BOT_STATE_DB", "/var/lib/integrated-dent/dent-bot/state.sqlite3")),
        payment_offers_db=Path(
            os.getenv("DENT_BOT_PAYMENT_OFFERS_DB", "/var/lib/integrated-dent/shared/payment-offers.sqlite3")
        ),
        poll_timeout=timeout,
        update_workers=update_workers,
        site_timeout_seconds=site_timeout_seconds,
        platform="telegram",
        site_api_url=validate_site_api_url(
            os.getenv("DENT_BOT_SITE_API_URL", f"{site_url}/api/bot_api.php?action=service").strip()
        ),
        site_service_secret=load_service_secret(),
        site_relay_secret=load_optional_relay_secret(),
        notification_poll_seconds=notification_poll_seconds,
        notification_batch_size=notification_batch_size,
        payment_result_push_enabled=payment_result_push_enabled,
        payment_result_poll_seconds=min(
            300, max(15, int(os.getenv("DENT_BOT_PAYMENT_RESULT_POLL_SECONDS", "30")))
        ),
        payment_result_batch_size=min(
            20, max(1, int(os.getenv("DENT_BOT_PAYMENT_RESULT_BATCH_SIZE", "10")))
        ),
        navid_daily_enabled=navid_daily_enabled,
        navid_daily_hour=navid_daily_hour,
        navid_daily_timezone=os.getenv("DENT_BOT_NAVID_DAILY_TIMEZONE", "Asia/Tehran").strip() or "Asia/Tehran",
        navid_retry_seconds=navid_retry_seconds,
        navid_group_enabled=os.getenv("DENT_BOT_NAVID_GROUP_ENABLED", "0").strip() == "1",
        navid_group_chat_id=int(os.getenv("DENT_BOT_NAVID_GROUP_CHAT_ID", "0").strip() or "0"),
        navid_group_poll_seconds=min(
            600, max(30, int(os.getenv("DENT_BOT_NAVID_GROUP_POLL_SECONDS", "60")))
        ),
        telegram_proxy_url=os.getenv("DENT_BOT_HTTPS_PROXY", "").strip(),
        exams_v1_enabled=os.getenv("DENT_BOT_EXAMS_V1_ENABLED", "0").strip() == "1",
        student_assistant_v1_enabled=os.getenv("DENT_BOT_STUDENT_ASSISTANT_V1_ENABLED", "0").strip() == "1",
        required_channel_username=(
            os.getenv("DENT_BOT_REQUIRED_CHANNEL_USERNAME", "Dent1402Booklets").strip().lstrip("@")
            or "Dent1402Booklets"
        ),
        booklet_source_channel_id=booklet_source_channel_id,
        booklet_source_channel_title=booklet_source_channel_title,
        # Production currently uses one worker. The upper bound is configurable
        # for a future server upgrade instead of baking today's 1 GB profile
        # into the architecture.
        booklet_media_workers=min(8, max(1, int(os.getenv("DENT_BOT_BOOKLET_MEDIA_WORKERS", "1")))),
        booklet_media_queue_size=min(500, max(20, int(os.getenv("DENT_BOT_BOOKLET_MEDIA_QUEUE_SIZE", "48")))),
        booklet_access_mode=booklet_access_mode,
        booklet_fingerprint_key=load_booklet_fingerprint_key(required=booklet_source_channel_id < 0),
        booklet_watermark_font=Path(os.getenv(
            "DENT_BOT_BOOKLET_WATERMARK_FONT",
            str(Path(__file__).resolve().parent / "assets" / "fonts" / "B_Nazanin_Bold.ttf"),
        )),
        booklet_temp_root=Path(os.getenv(
            "DENT_BOT_BOOKLET_TEMP_ROOT",
            "/var/lib/integrated-dent/dent-bot/tmp/booklets",
        )),
        booklet_qpdf_binary=os.getenv("DENT_BOT_BOOKLET_QPDF_BINARY", "qpdf").strip() or "qpdf",
        booklet_max_download_bytes=min(
            20 * 1024 * 1024,
            max(1024 * 1024, int(os.getenv("DENT_BOT_BOOKLET_MAX_DOWNLOAD_BYTES", str(20 * 1024 * 1024)))),
        ),
        booklet_pdf_normalizer=booklet_pdf_normalizer,
        booklet_raster_dpi=min(220, max(144, int(os.getenv("DENT_BOT_BOOKLET_RASTER_DPI", "180")))),
        booklet_raster_jpeg_quality=min(
            94, max(72, int(os.getenv("DENT_BOT_BOOKLET_RASTER_JPEG_QUALITY", "88")))
        ),
        booklet_max_output_bytes=min(
            49 * 1024 * 1024,
            max(5 * 1024 * 1024, int(os.getenv("DENT_BOT_BOOKLET_MAX_OUTPUT_BYTES", str(49 * 1024 * 1024)))),
        ),
        booklet_processing_timeout_seconds=min(
            900, max(30, int(os.getenv("DENT_BOT_BOOKLET_PROCESSING_TIMEOUT_SECONDS", "300")))
        ),
        booklet_orphan_max_age_seconds=min(
            86400, max(600, int(os.getenv("DENT_BOT_BOOKLET_ORPHAN_MAX_AGE_SECONDS", "3600")))
        ),
        booklet_rate_window_seconds=min(
            3600, max(30, int(os.getenv("DENT_BOT_BOOKLET_RATE_WINDOW_SECONDS", "60")))
        ),
        booklet_rate_max_requests=min(
            100, max(3, int(os.getenv("DENT_BOT_BOOKLET_RATE_MAX_REQUESTS", "12")))
        ),
        booklet_same_document_cooldown_seconds=min(
            60, max(1, int(os.getenv("DENT_BOT_BOOKLET_SAME_DOCUMENT_COOLDOWN_SECONDS", "3")))
        ),
    )
