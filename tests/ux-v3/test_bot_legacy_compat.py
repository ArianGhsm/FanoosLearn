from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.models import Screen as RuntimeScreen
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.providers import ProviderContext, TelegramV3Renderer
from fanoos_bot.ui_v3.providers.core_adapter import provider_screen


WORKSPACE = "11111111-1111-4111-8111-111111111111"


class Backend:
    def workspaces(self, platform, subject):
        return {
            "workspaces": [{"id": WORKSPACE, "name": "دانشکده دندان‌پزشکی"}],
            "selected_workspace_id": WORKSPACE,
        }

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {"items": [], "timezone": "Asia/Tehran", "next_cursor": None}

    def announcements(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [], "next_cursor": None}


class LegacyCompatibilityTest(unittest.TestCase):
    def test_selected_home_keeps_existing_callback_codec_values(self):
        with tempfile.TemporaryDirectory() as root:
            state = LocalState(Path(root) / "state.sqlite3")
            try:
                app = BotApplication(
                    Backend(),
                    state,
                    "telegram",
                    ApplicationConfig("https://fanoos.test/", "prod"),
                )
                result = app.home("student")
                self.assertIsInstance(result.screen, RuntimeScreen)
                prepared = app.prepare_result("student", True, result)
                self.assertIs(prepared.screen, result.screen)
                callbacks = [
                    button.callback
                    for row in prepared.screen.rows
                    for button in row
                    if button.callback
                ]
                self.assertIn("notifs", callbacks)
                self.assertIn("today", callbacks)

                plan = TelegramV3Renderer().render(
                    provider_screen(prepared.screen),
                    context=ProviderContext(private_chat=True),
                )
                rendered_callbacks = {
                    item.get("callback_data")
                    for row in plan.keyboard
                    for item in row
                    if item.get("callback_data")
                }
                self.assertIn("notifs", rendered_callbacks)
                self.assertIn("today", rendered_callbacks)
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
