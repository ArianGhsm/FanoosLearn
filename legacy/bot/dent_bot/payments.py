from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Iterable


PRODUCT_STATUSES = {"draft", "scheduled", "active", "paused", "expired", "archived"}
AUDIENCE_MODES = {"all", "open", "cohorts", "users", "lists"}


def utc_now() -> datetime:
    return datetime.now(timezone.utc)


def iso_utc(value: str | None, *, allow_empty: bool = True) -> str:
    raw = str(value or "").strip()
    if not raw:
        if allow_empty:
            return ""
        raise ValueError("A date is required")
    try:
        parsed = datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError as error:
        raise ValueError("Invalid ISO date") from error
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=timezone.utc)
    return parsed.astimezone(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")


def parse_iso(value: object) -> datetime | None:
    raw = str(value or "").strip()
    if not raw:
        return None
    try:
        parsed = datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError:
        return None
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=timezone.utc)
    return parsed.astimezone(timezone.utc)


def normalize_student_number(value: object) -> str:
    translated = str(value or "").translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
    return "".join(character for character in translated if character.isdigit())[:24]


def normalize_audience(value: object) -> dict:
    source = dict(value) if isinstance(value, dict) else {}
    mode = str(source.get("mode") or "all").strip().lower()
    if mode not in AUDIENCE_MODES:
        raise ValueError("Invalid product audience")

    def strings(key: str, *, limit: int = 200) -> list[str]:
        values = source.get(key) if isinstance(source.get(key), list) else []
        result: list[str] = []
        for item in values:
            cleaned = " ".join(str(item or "").split())[:80]
            if cleaned and cleaned not in result:
                result.append(cleaned)
            if len(result) >= limit:
                break
        return result

    cohorts = strings("cohorts", limit=32)
    users = [value for item in strings("studentNumbers") if (value := normalize_student_number(item))]
    lists = strings("listRefs", limit=32)
    if mode == "cohorts" and not cohorts:
        raise ValueError("At least one cohort is required")
    if mode == "users" and not users:
        raise ValueError("At least one user is required")
    if mode == "lists" and not lists:
        raise ValueError("At least one saved audience is required")
    return {"mode": mode, "cohorts": cohorts, "studentNumbers": users, "listRefs": lists}


def identity_from_account(account: object) -> dict:
    source = dict(account) if isinstance(account, dict) else {}
    user = dict(source.get("user") or {}) if isinstance(source.get("user"), dict) else {}
    profile = dict(source.get("onboardingProfile") or {}) if isinstance(source.get("onboardingProfile"), dict) else {}
    student_number = normalize_student_number(user.get("studentNumber") or profile.get("studentNumber"))
    cohort = " ".join(str(user.get("cohortKey") or profile.get("cohortKey") or "").split())[:80]
    return {
        "studentNumber": student_number,
        "cohortKey": cohort,
        "authenticated": bool(
            (source.get("linked") is True and source.get("authComplete") is True)
            or (str(profile.get("verifiedAt") or "").strip() and not profile.get("isClassMember"))
        ),
    }


def effective_status(offer: dict, *, now: datetime | None = None) -> str:
    current = now or utc_now()
    status = str(offer.get("status") or "draft")
    if status in {"draft", "paused", "archived"}:
        return status
    available_from = parse_iso(offer.get("availableFrom"))
    expires_at = parse_iso(offer.get("expiresAt"))
    if expires_at is not None and expires_at <= current:
        return "expired"
    if available_from is not None and available_from > current:
        return "scheduled"
    return "active" if status in {"active", "scheduled", "expired"} else status


@dataclass(frozen=True)
class EligibilityDecision:
    allowed: bool
    reason: str
    status: str


def product_eligibility(
    offer: dict,
    identity: dict,
    *,
    via_link: bool = False,
    saved_members: Iterable[str] = (),
    now: datetime | None = None,
) -> EligibilityDecision:
    status = effective_status(offer, now=now)
    if status != "active":
        return EligibilityDecision(False, status, status)
    if not bool(identity.get("authenticated")):
        return EligibilityDecision(False, "authentication-required", status)
    audience = normalize_audience(offer.get("audience"))
    mode = audience["mode"]
    student_number = normalize_student_number(identity.get("studentNumber"))
    cohort = str(identity.get("cohortKey") or "")
    if mode == "all":
        return EligibilityDecision(True, "eligible", status)
    if mode == "open":
        return EligibilityDecision(bool(via_link), "eligible" if via_link else "link-only", status)
    if mode == "cohorts":
        allowed = bool(cohort and cohort in audience["cohorts"])
        return EligibilityDecision(allowed, "eligible" if allowed else "audience-denied", status)
    if mode == "users":
        allowed = bool(student_number and student_number in audience["studentNumbers"])
        return EligibilityDecision(allowed, "eligible" if allowed else "audience-denied", status)
    resolved = {normalize_student_number(value) for value in saved_members}
    allowed = bool(student_number and student_number in resolved)
    return EligibilityDecision(allowed, "eligible" if allowed else "audience-denied", status)


def audience_label(value: object) -> str:
    audience = normalize_audience(value)
    mode = audience["mode"]
    if mode == "all":
        return "همهٔ کاربران احرازشده"
    if mode == "open":
        return "فقط دارندگان لینک"
    if mode == "cohorts":
        return f"{len(audience['cohorts'])} ورودی/گروه"
    if mode == "users":
        return f"{len(audience['studentNumbers'])} کاربر مشخص"
    return f"{len(audience['listRefs'])} فهرست ذخیره‌شده"
