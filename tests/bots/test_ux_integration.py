import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.bale_presentation import BalePresentation
from fanoos_bot.state import LocalState
from fanoos_bot.telegram_presentation import TelegramPresentation

from test_application import FakeBackend


class IntegratedBotPresentationTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.state = LocalState(Path(self.tmp.name) / "state.db")
        self.backend = FakeBackend()
        self.backend.selected = "11111111-1111-4111-8111-111111111111"
        self.app = BotApplication(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/", "prod"),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    def test_real_home_semantics_render_as_telegram_rich_rtl(self):
        screen = self.app.home("1").screen
        self.assertIsNotNone(screen.presentation)
        rendered = TelegramPresentation(enabled=True).render(screen)
        self.assertIsNotNone(rendered.rich_message)
        self.assertTrue(rendered.rich_message["is_rtl"])
        self.assertIn(screen.presentation.title, rendered.rich_message["html"])
        self.assertIn("دانشکده دندان‌پزشکی", rendered.rich_message["html"])
        self.assertIn("<h3>", rendered.rich_message["html"])

    def test_same_real_home_semantics_render_readably_in_bale(self):
        screen = self.app.home("1").screen
        rendered = BalePresentation().render(screen)
        self.assertIn(screen.presentation.title, rendered.text)
        self.assertIn("دانشکده دندان‌پزشکی", rendered.text)
        self.assertNotIn("rich_message", rendered.text)

    def test_rich_disabled_keeps_complete_plain_fallback_and_callbacks(self):
        screen = self.app.home("1").screen
        callbacks_before = [button.callback for row in screen.rows for button in row if button.callback]
        rendered = TelegramPresentation(enabled=False).render(screen)
        callbacks_after = [button.callback for row in screen.rows for button in row if button.callback]
        self.assertIsNone(rendered.rich_message)
        self.assertEqual(rendered.plain_text, screen.text)
        self.assertEqual(callbacks_before, callbacks_after)
        self.assertIn("🏠 فانوس", rendered.plain_text)

    def test_error_semantics_use_transport_adapter_without_raw_code(self):
        screen = self.app.select_workspace(
            "1", "99999999-9999-4999-8999-999999999999"
        ).screen
        telegram = TelegramPresentation(enabled=True).render(screen)
        bale = BalePresentation().render(screen)
        self.assertIsNotNone(telegram.rich_message)
        self.assertNotIn("workspace_forbidden", telegram.rich_message["html"])
        self.assertNotIn("workspace_forbidden", bale.text)
        self.assertIn("در دسترس نیست", telegram.rich_message["html"])
        self.assertIn("در دسترس نیست", bale.text)


if __name__ == "__main__":
    unittest.main()
