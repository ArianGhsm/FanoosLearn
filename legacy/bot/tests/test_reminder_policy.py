from __future__ import annotations

import unittest
from datetime import datetime

from dent_bot.persian_datetime import tehran_timezone
from dent_bot.reminder_policy import due_reservation_slots, previous_evening, reservation_cycle


class ReminderPolicyTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tz = tehran_timezone()

    def test_meal_cycle_runs_sunday_through_tuesday_deadline(self) -> None:
        cycle = reservation_cycle(datetime(2026, 8, 23, 12, tzinfo=self.tz), "meal")
        self.assertEqual(cycle.deadline, datetime(2026, 8, 25, 23, 59, tzinfo=self.tz))
        self.assertEqual([slot.weekday() for slot in cycle.reminder_slots], [6, 0, 1, 1])
        self.assertEqual([slot.strftime("%H:%M") for slot in cycle.reminder_slots], ["20:00", "20:00", "16:00", "22:00"])

    def test_service_cycle_runs_wednesday_through_friday_deadline(self) -> None:
        cycle = reservation_cycle(datetime(2026, 8, 26, 12, tzinfo=self.tz), "service")
        self.assertEqual(cycle.deadline, datetime(2026, 8, 28, 23, 59, tzinfo=self.tz))
        self.assertEqual([slot.weekday() for slot in cycle.reminder_slots], [2, 3, 4, 4])

    def test_due_slots_recovers_a_missed_tick_without_duplicate_policy_state(self) -> None:
        due = due_reservation_slots(
            datetime(2026, 8, 24, 19, 55, tzinfo=self.tz),
            datetime(2026, 8, 24, 20, 5, tzinfo=self.tz),
            "meal",
        )
        self.assertEqual(due, (datetime(2026, 8, 24, 20, 0, tzinfo=self.tz),))

    def test_previous_evening_uses_tehran_calendar_day(self) -> None:
        reminder = previous_evening(datetime(2026, 8, 25, 8, 30, tzinfo=self.tz))
        self.assertEqual(reminder, datetime(2026, 8, 24, 20, 0, tzinfo=self.tz))


if __name__ == "__main__":
    unittest.main()
