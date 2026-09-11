from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime, time, timedelta

from .persian_datetime import tehran_timezone


@dataclass(frozen=True)
class ReservationCycle:
    kind: str
    cycle_key: str
    deadline: datetime
    reminder_slots: tuple[datetime, ...]


_POLICIES = {
    "meal": {
        "deadline_weekday": 1,  # Tuesday
        "slots": ((-2, time(20, 0)), (-1, time(20, 0)), (0, time(16, 0)), (0, time(22, 0))),
    },
    "service": {
        "deadline_weekday": 4,  # Friday
        "slots": ((-2, time(20, 0)), (-1, time(20, 0)), (0, time(17, 30)), (0, time(22, 0))),
    },
}


def _local(value: datetime) -> datetime:
    timezone = tehran_timezone()
    return value.replace(tzinfo=timezone) if value.tzinfo is None else value.astimezone(timezone)


def reservation_cycle(now: datetime, kind: str) -> ReservationCycle:
    """Return the active Tehran-time reservation cycle without storing user state."""
    if kind not in _POLICIES:
        raise ValueError("Unknown reservation kind")
    current = _local(now)
    policy = _POLICIES[kind]
    days_until_deadline = (int(policy["deadline_weekday"]) - current.weekday()) % 7
    deadline_date = current.date() + timedelta(days=days_until_deadline)
    deadline = datetime.combine(deadline_date, time(23, 59), tzinfo=current.tzinfo)
    if current > deadline:
        deadline += timedelta(days=7)
    slots = tuple(
        datetime.combine(deadline.date() + timedelta(days=offset), slot_time, tzinfo=current.tzinfo)
        for offset, slot_time in policy["slots"]
    )
    return ReservationCycle(
        kind=kind,
        cycle_key=f"{kind}:{deadline.date().isoformat()}",
        deadline=deadline,
        reminder_slots=slots,
    )


def due_reservation_slots(last_check: datetime, now: datetime, kind: str) -> tuple[datetime, ...]:
    """Return missed/due slots once; canonical acknowledgements still live on the website."""
    previous = _local(last_check)
    current = _local(now)
    if current < previous:
        raise ValueError("now must not be before last_check")
    cycle = reservation_cycle(current, kind)
    return tuple(slot for slot in cycle.reminder_slots if previous < slot <= current <= cycle.deadline)


def previous_evening(event_start: datetime, *, hour: int = 20) -> datetime:
    """Default section/class/exam reminder time: 20:00 Tehran on the previous day."""
    if not 0 <= hour <= 23:
        raise ValueError("hour must be between 0 and 23")
    local_start = _local(event_start)
    return datetime.combine(local_start.date() - timedelta(days=1), time(hour, 0), tzinfo=local_start.tzinfo)
