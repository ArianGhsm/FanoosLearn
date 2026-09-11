from __future__ import annotations

from datetime import date, datetime, timedelta, timezone
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError


PERSIAN_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
PERSIAN_WEEKDAYS = (
    "دوشنبه",
    "سه‌شنبه",
    "چهارشنبه",
    "پنجشنبه",
    "جمعه",
    "شنبه",
    "یکشنبه",
)
PERSIAN_MONTH_NAMES = (
    "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
    "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند",
)


def to_persian_digits(value: object) -> str:
    return str("" if value is None else value).translate(PERSIAN_DIGITS)


def tehran_timezone():
    try:
        return ZoneInfo("Asia/Tehran")
    except ZoneInfoNotFoundError:
        return timezone(timedelta(hours=3, minutes=30))


def gregorian_to_jalali(value: date) -> tuple[int, int, int]:
    gy = value.year
    gm = value.month
    gd = value.day
    month_days = (0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334)
    adjusted_year = gy + 1 if gm > 2 else gy
    days = (
        355666
        + (365 * gy)
        + ((adjusted_year + 3) // 4)
        - ((adjusted_year + 99) // 100)
        + ((adjusted_year + 399) // 400)
        + gd
        + month_days[gm - 1]
    )
    jy = -1595 + (33 * (days // 12053))
    days %= 12053
    jy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        jy += (days - 1) // 365
        days = (days - 1) % 365
    if days < 186:
        jm = 1 + (days // 31)
        jd = 1 + (days % 31)
    else:
        jm = 7 + ((days - 186) // 30)
        jd = 1 + ((days - 186) % 30)
    return jy, jm, jd


def jalali_to_gregorian(year: int, month: int, day: int) -> date:
    """Convert an Iranian Solar Hijri date to Gregorian without approximation."""
    jy, jm, jd = int(year), int(month), int(day)
    if not 1 <= jm <= 12 or not 1 <= jd <= 31:
        raise ValueError("Invalid Jalali date")
    jy += 1595
    days = -355668 + (365 * jy) + ((jy // 33) * 8) + (((jy % 33) + 3) // 4) + jd
    days += (jm - 1) * 31 if jm < 7 else ((jm - 7) * 30) + 186
    gy = 400 * (days // 146097)
    days %= 146097
    if days > 36524:
        gy += 100 * ((days - 1) // 36524)
        days = (days - 1) % 36524
        if days >= 365:
            days += 1
    gy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        gy += (days - 1) // 365
        days = (days - 1) % 365
    gd = days + 1
    leap = gy % 4 == 0 and (gy % 100 != 0 or gy % 400 == 0)
    month_lengths = (31, 29 if leap else 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31)
    gm = 1
    for length in month_lengths:
        if gd <= length:
            break
        gd -= length
        gm += 1
    converted = date(gy, gm, gd)
    # Reject impossible dates such as 31 Shahrivar/Esfand without duplicating
    # leap-year rules: a valid input must round-trip exactly.
    if gregorian_to_jalali(converted) != (int(year), int(month), int(day)):
        raise ValueError("Invalid Jalali date")
    return converted


def next_jalali_month(year: int, month: int) -> tuple[int, int]:
    year, month = int(year), int(month)
    if not 1 <= month <= 12:
        raise ValueError("Invalid Jalali month")
    return (year + 1, 1) if month == 12 else (year, month + 1)


def previous_jalali_month(year: int, month: int) -> tuple[int, int]:
    year, month = int(year), int(month)
    if not 1 <= month <= 12:
        raise ValueError("Invalid Jalali month")
    return (year - 1, 12) if month == 1 else (year, month - 1)


def jalali_month_days(year: int, month: int) -> int:
    start = jalali_to_gregorian(year, month, 1)
    next_year, next_month = next_jalali_month(year, month)
    return (jalali_to_gregorian(next_year, next_month, 1) - start).days


def parse_datetime(value: object) -> datetime | None:
    raw = str(value or "").strip()
    if not raw:
        return None
    try:
        parsed = datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError:
        return None
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=timezone.utc)
    return parsed.astimezone(tehran_timezone())


def format_jalali_datetime(value: object) -> str:
    parsed = parse_datetime(value)
    if parsed is None:
        return ""
    year, month, day = gregorian_to_jalali(parsed.date())
    rendered = (
        f"ساعت {parsed:%H:%M} {PERSIAN_WEEKDAYS[parsed.weekday()]} "
        f"{year}/{month}/{day}"
    )
    return to_persian_digits(rendered)
