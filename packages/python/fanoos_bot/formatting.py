from __future__ import annotations

import re
import unicodedata
from datetime import datetime
from decimal import Decimal, InvalidOperation
from zoneinfo import ZoneInfo

_PERSIAN_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
_LRI = chr(0x2066)
_PDI = chr(0x2069)
_SHA_RE = re.compile(r"^[0-9a-f]{40}$", re.I)
_UUID_RE = re.compile(r"^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$", re.I)
_TIME_RE = re.compile(r"(?<!\d)([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?(?!\d)")
_CURRENCY_EXPONENT = {"USD": 2, "EUR": 2, "GBP": 2, "AED": 2, "TRY": 2, "IRR": 0}


def to_persian_digits(value: object) -> str:
    raw = str(value)
    if _SHA_RE.fullmatch(raw) or _UUID_RE.fullmatch(raw):
        return raw
    return raw.translate(_PERSIAN_DIGITS)


def format_human_number(value: object) -> str:
    if value is None or value == "":
        return "—"
    try:
        number = Decimal(str(value))
    except (InvalidOperation, ValueError):
        return to_persian_digits(value)
    if number == number.to_integral():
        rendered = f"{int(number):,}"
    else:
        rendered = format(number.normalize(), "f").rstrip("0").rstrip(".")
        whole, dot, fraction = rendered.partition(".")
        rendered = f"{int(whole):,}" + (f".{fraction}" if dot else "")
    return to_persian_digits(rendered).replace(",", "٬").replace(".", "٫")


def format_score(value: object) -> str:
    return format_human_number(value)


def format_money(amount_minor: object, currency: object) -> str:
    code = str(currency or "").upper().strip()
    try:
        amount = Decimal(str(amount_minor))
    except (InvalidOperation, ValueError):
        return "مبلغ نامشخص"
    exponent = _CURRENCY_EXPONENT.get(code, 0)
    if exponent:
        amount = amount / (Decimal(10) ** exponent)
    labels = {
        "IRR": "ریال",
        "USD": "دلار آمریکا",
        "EUR": "یورو",
        "GBP": "پوند",
        "AED": "درهم",
        "TRY": "لیر",
    }
    label = labels.get(code, "واحد پول")
    return f"{format_human_number(amount)} {label}"


def _parse_datetime(value: object) -> datetime | None:
    if isinstance(value, datetime):
        return value
    raw = str(value or "").strip()
    if not raw:
        return None
    try:
        return datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError:
        return None


def _apply_timezone(value: datetime, timezone_name: str | None) -> datetime:
    if not timezone_name:
        return value
    try:
        zone = ZoneInfo(timezone_name)
    except Exception:
        return value
    if value.tzinfo is None:
        return value.replace(tzinfo=zone)
    return value.astimezone(zone)


def format_time(value: object, timezone_name: str | None = None) -> str:
    parsed = _parse_datetime(value)
    if parsed is not None:
        parsed = _apply_timezone(parsed, timezone_name)
        return to_persian_digits(parsed.strftime("%H:%M"))
    raw = str(value or "").strip()
    match = _TIME_RE.search(raw)
    if match:
        return to_persian_digits(match.group(0)[:5])
    return to_persian_digits(raw) if raw else "زمان نامشخص"


def format_date(value: object, timezone_name: str | None = None) -> str:
    parsed = _parse_datetime(value)
    if parsed is None:
        raw = str(value or "").strip()
        return to_persian_digits(raw) if raw else ""
    parsed = _apply_timezone(parsed, timezone_name)
    return to_persian_digits(parsed.strftime("%Y/%m/%d"))


def format_datetime(value: object, timezone_name: str | None = None) -> str:
    parsed = _parse_datetime(value)
    if parsed is None:
        raw = str(value or "").strip()
        return to_persian_digits(raw) if raw else ""
    parsed = _apply_timezone(parsed, timezone_name)
    return to_persian_digits(parsed.strftime("%Y/%m/%d، %H:%M"))


def truncate_text(value: object, limit: int, suffix: str = "…") -> str:
    if limit < 1:
        return ""
    text = str(value or "")
    if len(text) <= limit:
        return text
    boundary = max(0, limit - len(suffix))
    variation = {chr(0xFE0E), chr(0xFE0F)}
    while boundary > 0:
        previous = text[boundary - 1]
        following = text[boundary] if boundary < len(text) else ""
        if (
            unicodedata.combining(following)
            or following in variation
            or following == chr(0x200D)
            or previous == chr(0x200D)
            or previous in variation
        ):
            boundary -= 1
            continue
        break
    return text[:boundary].rstrip() + suffix


def isolate_ltr(value: object) -> str:
    raw = str(value or "")
    return f"{_LRI}{raw}{_PDI}" if raw else ""


def short_sha(value: object, length: int = 12) -> str:
    raw = str(value or "")
    if not _SHA_RE.fullmatch(raw):
        return ""
    return isolate_ltr(raw[: max(7, min(length, 16))].lower())


def is_uuid(value: object) -> bool:
    return bool(_UUID_RE.fullmatch(str(value or "")))


def humanize_slug(value: object) -> str:
    raw = str(value or "").strip()
    if not raw or is_uuid(raw):
        return ""
    raw = re.sub(r"[-_]+", " ", raw)
    return " ".join(raw.split())
