from __future__ import annotations

import re
from datetime import datetime, timezone

from .api import BotApiError
from .navid import decode_image_data_uri
from .persian_datetime import format_jalali_datetime
from .state import BotState


_OPAQUE_REF = re.compile(r"[A-Za-z0-9_-]{12,80}")
_CONNECTOR_LABELS = {
    "navid": "نوید",
    "food": "تغذیه",
    "saba": "سرویس",
}
_DIGIT_TRANSLATION = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def clean_captcha_answer(value: str) -> str:
    answer = re.sub(r"\s+", "", value.translate(_DIGIT_TRANSLATION))
    return answer if re.fullmatch(r"[A-Za-z0-9]{4,12}", answer) else ""


def challenge_expired(expires_at: str, *, now: datetime | None = None) -> bool:
    try:
        parsed = datetime.fromisoformat(expires_at.strip().replace("Z", "+00:00"))
    except (TypeError, ValueError):
        return True
    if parsed.tzinfo is None:
        return True
    current = now or datetime.now(timezone.utc)
    return parsed.astimezone(timezone.utc) <= current.astimezone(timezone.utc)


def challenge_caption(*, connector: str, expires_at: str) -> str:
    label = _CONNECTOR_LABELS.get(connector, "سامانه دانشگاه")
    rendered_expiry = format_jalali_datetime(expires_at)
    expiry_line = f"\nاعتبار: <b>{rendered_expiry}</b>" if rendered_expiry else ""
    return (
        f"<b>🔐 کپچای {label}</b>\n\n"
        "کد داخل تصویر را فقط با <b>Reply به همین پیام</b> بفرست. "
        "پاسخ یک‌بار مصرف است و فقط همان عملیات نیمه‌تمام را ادامه می‌دهد."
        f"{expiry_line}\n\n"
        "<blockquote>رمز حساب یا کد ورود را در چت نفرست.</blockquote>"
    )


def send_private_challenge(*, api, state: BotState, user_id: int, payload: dict) -> dict:
    challenge = dict(payload.get("challenge") or {})
    challenge_ref = str(challenge.get("ref") or "")
    job_ref = str(payload.get("jobRef") or challenge.get("jobRef") or "")
    connector = str(challenge.get("connector") or payload.get("connector") or "")
    expires_at = str(challenge.get("expiresAt") or "")
    if (
        _OPAQUE_REF.fullmatch(challenge_ref) is None
        or _OPAQUE_REF.fullmatch(job_ref) is None
        or connector not in _CONNECTOR_LABELS
        or challenge_expired(expires_at)
    ):
        raise BotApiError("Integration challenge is invalid")

    existing = state.integration_challenge(user_id)
    if existing and existing.get("challenge_ref") == challenge_ref and not challenge_expired(str(existing.get("expires_at") or "")):
        return {"message_id": int(existing["message_id"]), "already_sent": True}

    image = decode_image_data_uri(challenge.get("imageDataUri"), max_bytes=2 * 1024 * 1024)
    message = api.send_photo_bytes(
        user_id,
        image,
        caption=challenge_caption(connector=connector, expires_at=expires_at),
        reply_markup={
            "force_reply": True,
            "selective": True,
            "input_field_placeholder": "کد داخل تصویر",
        },
        filename=f"{connector}-captcha.png",
    )
    message_id = int(message.get("message_id") or 0)
    if message_id <= 0:
        raise BotApiError("Bot platform did not return a captcha message id")
    state.set_integration_challenge(
        user_id,
        challenge_ref=challenge_ref,
        job_ref=job_ref,
        connector=connector,
        message_id=message_id,
        expires_at=expires_at,
    )
    return {"message_id": message_id, "already_sent": False}
