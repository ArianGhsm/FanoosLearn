from __future__ import annotations

from datetime import date

from .formatting import from_persian_digits, to_persian_digits

# Ported from legacy/bot/dent_bot/persian_datetime.py (docs/product/01_FRONT_DOOR.md
# #4 names it as the proven implementation): the bot displays Jalali dates, the
# schema stores Gregorian DATE columns as-is, and this module is the only place
# that converts between the two -- never in storage.


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


def parse_jalali_date_input(text: str) -> str | None:
    """A free-typed Jalali date (Persian or Latin digits, '/' or '-' separated)
    to a Gregorian ISO date string (YYYY-MM-DD), or None if it cannot be parsed."""
    normalized = from_persian_digits(text or "").strip().replace("-", "/").replace(".", "/")
    parts = normalized.split("/")
    if len(parts) != 3 or not all(part.isdigit() for part in parts):
        return None
    year, month, day = (int(part) for part in parts)
    try:
        return jalali_to_gregorian(year, month, day).isoformat()
    except ValueError:
        return None


def format_jalali_date(iso_date: str) -> str:
    """A Gregorian ISO date string (YYYY-MM-DD) as a Persian-digit Jalali date."""
    try:
        year, month, day = (int(part) for part in str(iso_date).split("-"))
        gregorian = date(year, month, day)
    except (ValueError, TypeError):
        return str(iso_date or "")
    jy, jm, jd = gregorian_to_jalali(gregorian)
    return to_persian_digits(f"{jy}/{jm:02d}/{jd:02d}")
