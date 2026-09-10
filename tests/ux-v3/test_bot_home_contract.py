from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.core import Screen as CoreScreen


WORKSPACE = "11111111-1111-4111-8111-111111111111"


class Backend:
    def workspaces(self, platform, subject):
        return {
            "workspaces": [{"id": WORKSPACE, "name": "دانشکده دندان‌پزشکی"}],
            "selected_workspace_id": WORKSPACE,
        }

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {"items": [], "courses": [], "timezone": "Asia/Tehran", "next_cursor": None}

    def announcements(self, platform, subject, workspace_id, limit, cursor):
        raise RuntimeError("temporary upstream failure")


class HomeContractTest(unittest.TestCase):
    def test_home_distinguishes_empty_schedule_from_unavailable_announcements(self):
        with tempfile.TemporaryDirectory() as root:
            state = LocalState(Path(root) / "state.sqlite3")
            try:
                app = BotApplication(
                    Backend(), state, "telegram", ApplicationConfig("https://fanoos.test/")
                )
                result = app.home("student")
                self.assertIsInstance(result.screen, CoreScreen)
                self.assertEqual(result.screen.identifier, "home.active")
                schedule_item = result.screen.sections[0].items[0]
                announcement_item = result.screen.sections[1].items[0]
                self.assertEqual(schedule_item.marker, "—")
                self.assertEqual(announcement_item.marker, "⚠️")
                self.assertIn("ثبت نشده", schedule_item.title)
                self.assertIn("قابل دریافت نیست", announcement_item.title)
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
