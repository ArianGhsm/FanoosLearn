from __future__ import annotations

import base64
import binascii
from datetime import datetime, timedelta, timezone as dt_timezone
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from .api import BotApiError
from .persian_datetime import format_jalali_datetime
from .state import BotState
from .site_api import SiteApiClient


def local_now(timezone_name: str) -> datetime:
    try:
        zone = ZoneInfo(timezone_name)
    except ZoneInfoNotFoundError:
        # Iran has used a fixed UTC+03:30 offset since 2022. This fallback keeps
        # local development deterministic when the Windows tzdata package is
        # absent; production Ubuntu still uses the IANA zone database.
        zone = dt_timezone(timedelta(hours=3, minutes=30))
    return datetime.now(zone)


def decode_captcha_data_uri(value: object) -> bytes:
    return decode_image_data_uri(value, max_bytes=2 * 1024 * 1024)


def decode_image_data_uri(value: object, *, max_bytes: int = 5 * 1024 * 1024) -> bytes:
    raw = str(value or "").strip()
    is_png = raw.startswith("data:image/png;base64,")
    is_jpeg = raw.startswith("data:image/jpeg;base64,")
    if not (is_png or is_jpeg):
        raise BotApiError("Challenge image is invalid")
    encoded = raw.split(";base64,", 1)[1]
    try:
        image = base64.b64decode(encoded, validate=True)
    except (ValueError, binascii.Error) as error:
        raise BotApiError("Challenge image is invalid") from error
    signature_ok = (
        (is_png and image.startswith(b"\x89PNG\r\n\x1a\n"))
        or (is_jpeg and image.startswith(b"\xff\xd8\xff"))
    )
    if not image or len(image) > max_bytes or not signature_ok:
        raise BotApiError("Challenge image size or signature is invalid")
    return image


def captcha_caption(expires_at: str) -> str:
    rendered_expiry = format_jalali_datetime(expires_at)
    expiry = f"\n\nاعتبار: {rendered_expiry}" if rendered_expiry else ""
    return (
        "<b>🔐 بررسی روزانه نوید</b>\n\n"
        "کد داخل تصویر را با Reply به همین پیام بفرست. پس از تأیید، تکلیف‌های جدید "
        "در اعلان‌های سایت ثبت و برای اعضای متصل به ربات‌ها ارسال می‌شوند."
        f"{expiry}\n\n"
        "<blockquote>اگر کپچا منقضی شد، دستور /navid را بفرست.</blockquote>"
    )


def send_daily_challenge(
    *,
    api,
    state: BotState,
    site_api: SiteApiClient,
    owner_id: int,
    daily_date: str,
    refresh: bool,
) -> dict:
    result = site_api.navid_daily_start(owner_id, date=daily_date, refresh=refresh)
    if str(result.get("status") or "") == "already-completed":
        state.clear_navid_challenge()
        state.set_runtime_value("navid_completed_date", daily_date)
        return result
    image = decode_captcha_data_uri(result.get("captchaDataUri"))
    expires_at = str(result.get("expiresAt") or "")
    message = api.send_photo_bytes(
        owner_id,
        image,
        caption=captcha_caption(expires_at),
        reply_markup={
            "force_reply": True,
            "selective": True,
            "input_field_placeholder": "کد کپچا",
        },
    )
    message_id = int(message.get("message_id") or 0)
    if message_id <= 0:
        raise BotApiError("Bot platform did not return a captcha message id")
    state.set_navid_challenge(daily_date, message_id, expires_at)
    return result
