from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.core import Screen as CoreScreen


WORKSPACE = "11111111-1111-4111-8111-111111111111"
COURSE = "22222222-2222-4222-8222-222222222222"


class Backend:
    def workspaces(self, platform, subject):
        return {
            "workspaces": [{"id": WORKSPACE, "name": "دانشکده دندان‌پزشکی"}],
            "selected_workspace_id": WORKSPACE,
        }

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {
            "items": [],
            "courses": [
                {
                    "course_id": COURSE,
                    "course_code": "ENDO-1",
                    "course_title": "اندودانتیکس ۱",
                    "term_key": "1405-1",
                    "term_name": "نیمسال اول",
                }
            ],
            "timezone": "Asia/Tehran",
            "next_cursor": None,
        }


class AcademicContractTest(unittest.TestCase):
    def test_courses_use_attached_canonical_projection_even_without_activity(self):
        with tempfile.TemporaryDirectory() as root:
            state = LocalState(Path(root) / "state.sqlite3")
            try:
                app = BotApplication(
                    Backend(), state, "telegram", ApplicationConfig("https://fanoos.test/", "prod")
                )
                result = app.courses("student")
                self.assertIsInstance(result.screen, CoreScreen)
                self.assertEqual(result.screen.identifier, "academic.course.list")
                self.assertIn("اندودانتیکس ۱", result.screen.plain_text())
                self.assertNotIn(COURSE, result.screen.plain_text())
            finally:
                state.close()

    def test_course_detail_hides_domains_without_bot_safe_course_contract(self):
        with tempfile.TemporaryDirectory() as root:
            state = LocalState(Path(root) / "state.sqlite3")
            try:
                app = BotApplication(
                    Backend(), state, "telegram", ApplicationConfig("https://fanoos.test/", "prod")
                )
                result = app.course_detail("student", COURSE)
                self.assertEqual(result.screen.identifier, "academic.course.detail")
                identifiers = {
                    action.identifier
                    for row in result.screen.action_rows
                    for action in row.actions
                }
                self.assertIn("academic.course.schedule", identifiers)
                self.assertIn("academic.course.resources", identifiers)
                self.assertIn("academic.course.grades", identifiers)
                self.assertNotIn("academic.course.assessments", identifiers)
                self.assertIn("academic.course.announcements", identifiers)
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
