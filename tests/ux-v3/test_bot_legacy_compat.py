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


class LegacyCompatibilityTest(unittest.TestCase):
    def test_secondary_legacy_screen_keeps_callback_codec_values(self):
        with tempfile.TemporaryDirectory() as root:
            state = LocalState(Path(root) / "state.sqlite3")
            try:
                app = BotApplication(
                    Backend(),
                    state,
                    "telegram",
                    ApplicationConfig("https://fanoos.test/"),
                )
                result = app.more("student", True)
                self.assertIsInstance(result.screen, RuntimeScreen)
                prepared = app.prepare_result("student", True, result)
                self.assertIs(prepared.screen, result.screen)
                callbacks = [
                    button.callback
                    for row in prepared.screen.rows
                    for button in row
                    if button.callback
                ]
                self.assertIn("assess", callbacks)
                self.assertIn("payments", callbacks)

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
                self.assertIn("assess", rendered_callbacks)
                self.assertIn("payments", rendered_callbacks)
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
