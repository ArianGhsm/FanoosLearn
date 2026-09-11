from __future__ import annotations

from datetime import datetime, timedelta, timezone
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.core import Screen as CoreScreen
from fanoos_bot.ui_v3.wiring import core_to_runtime


# Fixed reference instant for the today/tomorrow schedule test below, so it does
# not depend on the real system clock. The bot computes "today" in the
# workspace's local timezone (Asia/Tehran, UTC+03:30), not UTC; near the last
# ~3.5 hours of any UTC calendar day, Tehran has already rolled over to the
# next date, so a fixture built from real datetime.now(timezone.utc).date()
# drifted out of sync with the bot's own local-date computation and made this
# test fail depending on what time of day it happened to run.
FROZEN_NOW = datetime(2027, 3, 1, 9, 0, 0, tzinfo=timezone.utc)


class _FrozenDateTime(datetime):
    @classmethod
    def now(cls, tz=None):
        return FROZEN_NOW.astimezone(tz) if tz is not None else FROZEN_NOW.replace(tzinfo=None)


WORKSPACE = "11111111-1111-4111-8111-111111111111"
COURSE = "22222222-2222-4222-8222-222222222222"
OTHER_COURSE = "33333333-3333-4333-8333-333333333333"
EVENT = "44444444-4444-4444-8444-444444444444"
TOMORROW_EVENT = "55555555-5555-4555-8555-555555555555"
ANNOUNCEMENT = "66666666-6666-4666-8666-666666666666"
OTHER_ANNOUNCEMENT = "77777777-7777-4777-8777-777777777777"
FORM = "88888888-8888-4888-8888-888888888888"
CURSOR = "page-2"


def text(screen: CoreScreen) -> str:
    if isinstance(screen, CoreScreen):
        return screen.plain_text()
    plain = getattr(screen, "text", None)
    return str(plain if plain is not None else screen)


def identifier(screen) -> str:
    if isinstance(screen, CoreScreen):
        return screen.identifier
    presentation = getattr(screen, "presentation", None)
    return str(getattr(presentation, "semantic_kind", ""))


def callback(action) -> str:
    assert action is not None and action.intent is not None
    value = action.intent.compact()
    assert value is not None
    return value


class AcademicBackend:
    def __init__(self):
        self.schedule_calls: list[tuple[str, str]] = []
        self.announcement_course_filters: list[str | None] = []
        self.selected = WORKSPACE
        self.course = {
            "course_id": COURSE,
            "course_code": "BIO-1",
            "course_title": "زیست‌شناسی سلولی",
            "term_name": "نیمسال اول",
        }
        self.other_course = {
            "course_id": OTHER_COURSE,
            "course_code": "CHEM-1",
            "course_title": "شیمی عمومی",
            "term_name": "نیمسال اول",
        }

    def workspaces(self, platform, subject):
        return {
            "workspaces": [{"id": WORKSPACE, "name": "دانشگاه فانوس"}],
            "selected_workspace_id": self.selected,
        }

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        self.schedule_calls.append((from_date, to_date))
        if workspace_id != WORKSPACE:
            raise AssertionError("the application must re-check workspace authority")
        # The application gracefully falls back to UTC when the local test
        # runtime does not ship the optional IANA tzdata package.
        today = datetime.now(timezone.utc).date()
        today_value = today.isoformat()
        tomorrow_value = (today + timedelta(days=1)).isoformat()
        today_event = {
            "id": EVENT,
            "title": "کلاس زیست",
            "starts_at": f"{today_value}T08:00:00+03:30",
            "ends_at": f"{today_value}T10:00:00+03:30",
            "location_text": "ساختمان فانوس",
            "course_id": COURSE,
            "course_title": self.course["course_title"],
        }
        tomorrow_event = {
            "id": TOMORROW_EVENT,
            "title": "کارگاه فردا",
            "starts_at": f"{tomorrow_value}T09:00:00+03:30",
            "ends_at": f"{tomorrow_value}T11:00:00+03:30",
            "location_text": "آزمایشگاه",
            "course_id": COURSE,
            "course_title": self.course["course_title"],
        }
        if from_date == today_value and to_date == today_value:
            items = [today_event]
        elif from_date == tomorrow_value and to_date == tomorrow_value:
            items = [tomorrow_event]
        else:
            items = [today_event, tomorrow_event]
        return {
            "timezone": "Asia/Tehran",
            "items": items,
            "courses": [self.course, self.other_course],
            "next_cursor": None,
        }

    def grades(self, platform, subject, workspace_id, limit, cursor):
        if cursor == CURSOR:
            return {
                "items": [
                    {
                        "item_title": "پروژه نهایی",
                        "score": 19,
                        "max_score": 20,
                        "result_status": "published",
                        "course_id": COURSE,
                        "course_title": self.course["course_title"],
                    }
                ],
                "next_cursor": None,
            }
        items = [
            {
                "item_title": "کوییز اول",
                "score": 18,
                "max_score": 20,
                "result_status": "published",
                "course_id": COURSE,
                "course_title": self.course["course_title"],
            },
            {
                "item_title": "نمره پیش‌نویس",
                "score": 20,
                "max_score": 20,
                "result_status": "draft",
                "course_id": COURSE,
                "course_title": self.course["course_title"],
            },
        ]
        return {"items": items, "next_cursor": CURSOR}

    def announcements(self, platform, subject, workspace_id, limit, cursor, course_id=None):
        self.announcement_course_filters.append(course_id)
        items = [
            {
                "id": ANNOUNCEMENT,
                "title": "اطلاعیه مهم زیست",
                "body": "متن اطلاعیه با نویسه‌های فارسی و یونیکد ✓",
                "published_at": "2026-09-10T09:00:00+03:30",
                "course_id": COURSE,
            },
            {
                "id": OTHER_ANNOUNCEMENT,
                "title": "اطلاعیه عمومی",
                "body": "برای همه درس‌ها",
                "published_at": "2026-09-09T09:00:00+03:30",
            },
        ]
        if course_id:
            items = [item for item in items if item.get("course_id") == course_id]
        return {"items": items, "next_cursor": None}

    def forms(self, platform, subject, workspace_id, limit, cursor):
        return {
            "items": [
                {
                    "form_id": FORM,
                    "title": "ارزیابی کارگاه",
                    "description": "لطفاً نظر خود را ثبت کنید.",
                    "status": "open",
                    "allow_multiple": "false",
                    "closes_at": "2026-09-30T12:00:00+00:00",
                }
            ],
            "next_cursor": None,
        }


class MinimalBackend(AcademicBackend):
    forms = None


class AcademicJourneysTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.backend = AcademicBackend()
        self.tg_state = LocalState(Path(self.temp.name) / "telegram.sqlite3")
        self.bale_state = LocalState(Path(self.temp.name) / "bale.sqlite3")
        config = ApplicationConfig("https://fanoos.test/", "production")
        self.telegram = BotApplication(self.backend, self.tg_state, "telegram", config)
        self.bale = BotApplication(
            self.backend,
            self.bale_state,
            "bale",
            ApplicationConfig("https://fanoos.test/", "production"),
        )

    def tearDown(self):
        self.tg_state.close()
        self.bale_state.close()
        self.temp.cleanup()

    def test_courses_detail_scope_and_opaque_pagination(self):
        result = self.telegram.courses("student")
        self.assertEqual(identifier(result.screen), "academic.course.list")
        self.assertIn("دانشگاه فانوس", text(result.screen))
        self.assertNotIn(COURSE, text(result.screen))
        runtime = core_to_runtime(self.telegram, "student", result.screen)
        detail = self.telegram.callback("student", True, runtime.rows[0][0].callback)
        self.assertEqual(identifier(detail.screen), "academic.course.detail")
        self.assertIn(
            "academic.course.announcements",
            {
                action.identifier
                for row in detail.screen.action_rows
                for action in row.actions
            },
        )
        self.assertNotIn(COURSE, text(detail.screen))
        course_grades = self.telegram.course_grades("student", COURSE)
        self.assertEqual(identifier(course_grades.screen), "academic.grades.course")
        self.assertIn("۱۸ از ۲۰", text(course_grades.screen))
        foreign = self.telegram.course_detail("student", "99999999-9999-4999-8999-999999999999")
        self.assertEqual(identifier(foreign.screen), "academic.course.unavailable")

    def test_schedule_today_tomorrow_course_filter_and_event_detail(self):
        with mock.patch("fanoos_bot.application.datetime", _FrozenDateTime), \
                mock.patch(f"{__name__}.datetime", _FrozenDateTime):
            today = self.telegram.day_schedule("student", 0)
            tomorrow = self.telegram.day_schedule("student", 1)
            self.assertIn("کلاس زیست", text(today.screen))
            self.assertNotIn("کارگاه فردا", text(today.screen))
            self.assertIn("کارگاه فردا", text(tomorrow.screen))
            self.assertIn("منطقه زمانی فضای آموزشی", text(today.screen))
            course_schedule = self.telegram.course_schedule("student", COURSE)
            self.assertIn("برنامه درس", text(course_schedule.screen))
            self.assertIn("کلاس زیست", text(course_schedule.screen))
            event_action = next(
                action
                for row in course_schedule.screen.action_rows
                for action in row.actions
                if action.identifier == "academic.schedule.event.open"
            )
            detail = self.telegram.callback("student", True, callback(event_action))
            self.assertEqual(identifier(detail.screen), "academic.schedule.event.detail")
            self.assertIn("ساختمان فانوس", text(detail.screen))
            self.assertNotIn(EVENT, text(detail.screen))

    def test_grades_group_published_only_and_pagination(self):
        first = self.telegram.grades("student")
        first_text = text(first.screen)
        self.assertEqual(identifier(first.screen), "academic.grades.list")
        self.assertIn("زیست‌شناسی سلولی", first_text)
        self.assertIn("۱۸ از ۲۰", first_text)
        self.assertNotIn("پیش‌نویس", first_text)
        self.assertNotIn("معدل", first_text.split("فقط نمره")[0])
        self.assertNotIn(COURSE, first_text)
        self.assertIsNotNone(first.screen.pagination)
        second = self.telegram.callback("student", True, callback(first.screen.pagination.next))
        self.assertIn("پروژه نهایی", text(second.screen))
        self.assertIsNotNone(second.screen.pagination)
        self.assertIsNotNone(second.screen.pagination.previous)

    def test_announcements_detail_course_scope_and_navigation(self):
        global_list = self.telegram.announcements("student")
        self.assertIn("اطلاعیه عمومی", text(global_list.screen))
        self.assertNotIn(ANNOUNCEMENT, text(global_list.screen))
        detail_action = next(
            action
            for row in global_list.screen.action_rows
            for action in row.actions
            if action.identifier == "academic.announcement.open"
        )
        detail = self.telegram.callback("student", True, callback(detail_action))
        self.assertEqual(identifier(detail.screen), "academic.announcement.detail")
        self.assertIn("نویسه‌های فارسی", text(detail.screen))
        self.assertNotIn(ANNOUNCEMENT, text(detail.screen))
        original_announcements = self.backend.announcements
        self.backend.announcements = lambda *args, **kwargs: {
            "items": [
                {
                    "id": ANNOUNCEMENT,
                    "title": "اطلاعیه طولانی",
                    "body": "یادداشت فارسی ✓ " * 500,
                    "published_at": "2026-09-10T09:00:00+03:30",
                }
            ],
            "next_cursor": None,
        }
        long_detail = self.telegram.announcement_detail("student", ANNOUNCEMENT)
        self.assertLess(len(text(long_detail.screen)), 2500)
        self.assertIn("…", text(long_detail.screen))
        self.backend.announcements = original_announcements
        scoped = self.telegram.course_announcements("student", COURSE)
        scoped_text = text(scoped.screen)
        self.assertIn("اطلاعیه مهم زیست", scoped_text)
        self.assertNotIn("اطلاعیه عمومی", scoped_text)
        self.assertEqual(self.backend.announcement_course_filters[-1], COURSE)
        self.assertEqual(scoped.screen.action_rows[-1].actions[-1].identifier, "core.home")

    def test_forms_use_human_status_and_safe_web_fallback(self):
        forms = self.telegram.forms("student")
        self.assertIn("ارزیابی کارگاه", text(forms.screen))
        self.assertIn("باز", text(forms.screen))
        self.assertNotIn("open", text(forms.screen).lower())
        form_action = next(
            action
            for row in forms.screen.action_rows
            for action in row.actions
            if action.identifier == "learning.form.open"
        )
        detail = self.telegram.callback("student", True, callback(form_action))
        detail_text = text(detail.screen)
        self.assertIn("هر عضو یک پاسخ", detail_text)
        self.assertNotIn("false", detail_text.lower())
        self.assertNotIn(FORM, detail_text)

        minimal_state = LocalState(Path(self.temp.name) / "minimal.sqlite3")
        try:
            minimal = BotApplication(
                MinimalBackend(), minimal_state, "telegram", ApplicationConfig("https://fanoos.test/")
            )
            fallback = minimal.forms("student")
            self.assertIn("projection", text(fallback.screen))
            self.assertIn("https://fanoos.test/", " ".join(
                action.url or ""
                for row in fallback.screen.action_rows
                for action in row.actions
            ))
        finally:
            minimal_state.close()

    def test_telegram_and_bale_keep_identical_academic_semantics(self):
        journeys = (
            lambda app: app.courses("student"),
            lambda app: app.schedule_menu("student"),
            lambda app: app.day_schedule("student", 0),
            lambda app: app.grades("student"),
            lambda app: app.announcements("student"),
        )
        for journey in journeys:
            telegram = journey(self.telegram).screen
            bale = journey(self.bale).screen
            self.assertEqual(identifier(telegram), identifier(bale))
            self.assertEqual(text(telegram), text(bale))


if __name__ == "__main__":
    unittest.main()
