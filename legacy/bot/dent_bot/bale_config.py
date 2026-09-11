from __future__ import annotations

import os
from pathlib import Path
from urllib.parse import urlsplit

from .config import BotSettings, load_service_secret, validate_site_api_url


def load_settings() -> BotSettings:
    token = os.getenv("DENT_BALE_BOT_TOKEN", "").strip()
    if not token or token.startswith("<"):
        raise ValueError("Bale bot token is not configured")
    owner_raw = os.getenv("DENT_BALE_OWNER_ID", "").strip()
    if not owner_raw.isdigit():
        raise ValueError("Bale owner ID is not configured")
    site_url = os.getenv("DENT_BALE_SITE_URL", "https://dentistry1402tums.ir").strip().rstrip("/")
    parsed = urlsplit(site_url)
    if parsed.scheme != "https" or not parsed.netloc or parsed.username or parsed.password:
        raise ValueError("Site URL must be a public HTTPS origin")
    timeout = min(50, max(10, int(os.getenv("DENT_BALE_POLL_TIMEOUT_SECONDS", "30"))))
    notification_poll_seconds = min(300, max(15, int(os.getenv("DENT_BALE_NOTIFICATION_POLL_SECONDS", "30"))))
    notification_batch_size = min(20, max(1, int(os.getenv("DENT_BALE_NOTIFICATION_BATCH_SIZE", "10"))))
    navid_daily_enabled = os.getenv("DENT_BALE_NAVID_DAILY_ENABLED", "0").strip() == "1"
    navid_daily_hour = min(23, max(0, int(os.getenv("DENT_BALE_NAVID_DAILY_HOUR", "9"))))
    navid_retry_seconds = min(21600, max(300, int(os.getenv("DENT_BALE_NAVID_RETRY_SECONDS", "3600"))))
    return BotSettings(
        token=token,
        bot_username=os.getenv("DENT_BALE_EXPECTED_USERNAME", "dent1402bot").strip().lstrip("@") or "dent1402bot",
        owner_id=int(owner_raw),
        site_url=site_url,
        state_db=Path(os.getenv("DENT_BALE_STATE_DB", "/var/lib/integrated-dent/bale-bot/state.sqlite3")),
        payment_offers_db=Path(
            os.getenv("DENT_BALE_PAYMENT_OFFERS_DB", "/var/lib/integrated-dent/shared/payment-offers.sqlite3")
        ),
        poll_timeout=timeout,
        update_workers=min(16, max(2, int(os.getenv("DENT_BALE_UPDATE_WORKERS", "8")))),
        site_timeout_seconds=min(12.0, max(2.0, float(os.getenv("DENT_BALE_SITE_TIMEOUT_SECONDS", "6")))),
        platform="bale",
        site_api_url=validate_site_api_url(
            os.getenv("DENT_BALE_SITE_API_URL", f"{site_url}/api/bot_api.php?action=service").strip()
        ),
        site_service_secret=load_service_secret(),
        site_relay_secret=os.getenv("DENT_BOT_SITE_RELAY_SECRET", "").strip(),
        notification_poll_seconds=notification_poll_seconds,
        notification_batch_size=notification_batch_size,
        payment_result_push_enabled=os.getenv("DENT_BALE_PAYMENT_RESULT_PUSH_ENABLED", "0").strip() == "1",
        payment_result_poll_seconds=min(
            300, max(15, int(os.getenv("DENT_BALE_PAYMENT_RESULT_POLL_SECONDS", "30")))
        ),
        payment_result_batch_size=min(
            20, max(1, int(os.getenv("DENT_BALE_PAYMENT_RESULT_BATCH_SIZE", "10")))
        ),
        navid_daily_enabled=navid_daily_enabled,
        navid_daily_hour=navid_daily_hour,
        navid_daily_timezone=os.getenv("DENT_BALE_NAVID_DAILY_TIMEZONE", "Asia/Tehran").strip() or "Asia/Tehran",
        navid_retry_seconds=navid_retry_seconds,
        navid_group_enabled=os.getenv("DENT_BALE_NAVID_GROUP_ENABLED", "0").strip() == "1",
        navid_group_chat_id=int(os.getenv("DENT_BALE_NAVID_GROUP_CHAT_ID", "0").strip() or "0"),
        navid_group_poll_seconds=min(
            600, max(30, int(os.getenv("DENT_BALE_NAVID_GROUP_POLL_SECONDS", "60")))
        ),
        telegram_proxy_url="",
        exams_v1_enabled=os.getenv("DENT_BALE_EXAMS_V1_ENABLED", "0").strip() == "1",
        student_assistant_v1_enabled=os.getenv("DENT_BALE_STUDENT_ASSISTANT_V1_ENABLED", "0").strip() == "1",
        required_channel_username="",
        booklet_source_channel_id=0,
        booklet_source_channel_title="",
        booklet_media_workers=1,
        booklet_media_queue_size=4,
        booklet_access_mode="all-authenticated",
        booklet_fingerprint_key=b"",
        booklet_watermark_font=Path(""),
        booklet_temp_root=Path("/var/lib/integrated-dent/bale-bot/tmp/booklets"),
        booklet_qpdf_binary="qpdf",
        booklet_max_download_bytes=20 * 1024 * 1024,
        booklet_pdf_normalizer="none",
        booklet_raster_dpi=180,
        booklet_raster_jpeg_quality=88,
        booklet_max_output_bytes=49 * 1024 * 1024,
        booklet_processing_timeout_seconds=300,
        booklet_orphan_max_age_seconds=3600,
        booklet_rate_window_seconds=60,
        booklet_rate_max_requests=12,
        booklet_same_document_cooldown_seconds=3,
    )
