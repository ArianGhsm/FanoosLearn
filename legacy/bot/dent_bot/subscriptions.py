from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, time, timezone
import hashlib
import re

from .payments import normalize_student_number, parse_iso, utc_now
from .persian_datetime import (
    PERSIAN_MONTH_NAMES,
    gregorian_to_jalali,
    jalali_month_days,
    jalali_to_gregorian,
    next_jalali_month,
    previous_jalali_month,
    tehran_timezone,
)


DEFAULT_TERM = 7
DEFAULT_MONTHLY_PRICE_RIALS = 1_500_000
DEFAULT_ACTIVE_FROM_JALALI = "1405-07-01"
SUBSCRIPTION_OFFER_REF_PREFIX = "booklet-subscription-term-"
PERIOD_PATTERN = re.compile(r"^term(?P<term>[1-9]|1[0-2])-(?P<year>1[34][0-9]{2})-(?P<month>0[1-9]|1[0-2])$")


@dataclass(frozen=True)
class SubscriptionIdentity:
    subject_key: str
    student_number: str
    display_name: str


@dataclass(frozen=True)
class PersianBillingPeriod:
    term: int
    year: int
    month: int
    starts_at: datetime
    expires_at: datetime

    @property
    def key(self) -> str:
        return f"term{self.term}-{self.year:04d}-{self.month:02d}"

    @property
    def month_label(self) -> str:
        return f"{PERSIAN_MONTH_NAMES[self.month - 1]} {self.year}"

    @property
    def end_label(self) -> str:
        return f"{jalali_month_days(self.year, self.month)} {PERSIAN_MONTH_NAMES[self.month - 1]} {self.year}"

    @property
    def previous_key(self) -> str:
        year, month = previous_jalali_month(self.year, self.month)
        return f"term{self.term}-{year:04d}-{month:02d}"


def parse_jalali_date(value: object) -> tuple[int, int, int]:
    raw = str(value or "").strip()
    match = re.fullmatch(r"(1[34][0-9]{2})-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])", raw)
    if not match:
        raise ValueError("Invalid Jalali date")
    result = tuple(int(part) for part in match.groups())
    jalali_to_gregorian(*result)
    return result


def jalali_midnight_utc(year: int, month: int, day: int) -> datetime:
    local = datetime.combine(jalali_to_gregorian(year, month, day), time.min, tzinfo=tehran_timezone())
    return local.astimezone(timezone.utc)


def billing_period_for(term: int, now: datetime | None = None) -> PersianBillingPeriod:
    current = now or utc_now()
    if current.tzinfo is None:
        current = current.replace(tzinfo=timezone.utc)
    local = current.astimezone(tehran_timezone())
    year, month, _day = gregorian_to_jalali(local.date())
    next_year, next_month = next_jalali_month(year, month)
    return PersianBillingPeriod(
        term=int(term),
        year=year,
        month=month,
        starts_at=jalali_midnight_utc(year, month, 1),
        expires_at=jalali_midnight_utc(next_year, next_month, 1),
    )


def billing_period_from_key(value: object) -> PersianBillingPeriod:
    match = PERIOD_PATTERN.fullmatch(str(value or "").strip())
    if not match:
        raise ValueError("Invalid billing period")
    term = int(match.group("term"))
    year = int(match.group("year"))
    month = int(match.group("month"))
    next_year, next_month = next_jalali_month(year, month)
    return PersianBillingPeriod(
        term=term,
        year=year,
        month=month,
        starts_at=jalali_midnight_utc(year, month, 1),
        expires_at=jalali_midnight_utc(next_year, next_month, 1),
    )


def policy_is_effective(policy: dict, now: datetime | None = None) -> bool:
    if not bool(policy.get("enabled")) or str(policy.get("mode") or "open") != "subscription":
        return False
    year, month, day = parse_jalali_date(policy.get("activeFromJalali"))
    return (now or utc_now()).astimezone(tehran_timezone()) >= datetime.combine(
        jalali_to_gregorian(year, month, day), time.min, tzinfo=tehran_timezone()
    )


def subscription_identity_from_account(account: object) -> SubscriptionIdentity | None:
    source = dict(account) if isinstance(account, dict) else {}
    if source.get("linked") is not True or source.get("authComplete") is not True:
        return None
    user = dict(source.get("user") or {}) if isinstance(source.get("user"), dict) else {}
    profile = dict(source.get("onboardingProfile") or {}) if isinstance(source.get("onboardingProfile"), dict) else {}
    student_number = normalize_student_number(user.get("studentNumber") or profile.get("studentNumber"))
    if not student_number:
        return None
    name = " ".join(str(user.get("name") or "").split())
    if not name:
        name = " ".join(
            part for part in (
                " ".join(str(profile.get("firstName") or "").split()),
                " ".join(str(profile.get("lastName") or "").split()),
            ) if part
        )
    # The canonical student number is hashed in the internal subject key so the
    # authorization join does not depend on a display name or platform handle.
    subject = "student:" + hashlib.sha256(student_number.encode("ascii")).hexdigest()
    return SubscriptionIdentity(subject, student_number, name[:160])


def subscription_identity_from_directory(item: object) -> SubscriptionIdentity | None:
    source = dict(item) if isinstance(item, dict) else {}
    student_number = normalize_student_number(source.get("studentNumber"))
    if not student_number:
        return None
    name = " ".join(str(source.get("name") or "").split())[:160]
    subject = "student:" + hashlib.sha256(student_number.encode("ascii")).hexdigest()
    return SubscriptionIdentity(subject, student_number, name)


def utc_iso(value: datetime) -> str:
    parsed = value if value.tzinfo is not None else value.replace(tzinfo=timezone.utc)
    return parsed.astimezone(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")


def access_expiry_is_valid(value: object, now: datetime) -> bool:
    parsed = parse_iso(value)
    return parsed is None or parsed > now.astimezone(timezone.utc)
