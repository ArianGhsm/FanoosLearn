from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.core import Action, ActionRow, CallbackIntent, Screen
from fanoos_bot.ui_v3.providers import BaleV3Renderer, ProviderContext, TelegramV3Renderer
from fanoos_bot.ui_v3.providers.core_adapter import provider_screen
from fanoos_bot.ui_v3.wiring import INTENT_REGISTRY, core_to_runtime, decode_v3_intent


ROOT = Path(__file__).resolve().parents[2]


class ArchitectureContractTest(unittest.TestCase):
    def test_stage_documents_describe_one_shared_layer_and_screen_map(self):
        architecture = (ROOT / "docs/rebuild/bots/01_BOT_ARCHITECTURE.md").read_text(encoding="utf-8")
        screen_map = (ROOT / "docs/rebuild/bots/01_SCREEN_MAP.md").read_text(encoding="utf-8")
        for phrase in ("provider-neutral", "INTENT_REGISTRY", "LocalState", "Telegram", "Bale", "canonical backend"):
            self.assertIn(phrase, architecture)
        for phrase in ("onboarding.unlinked", "home.active", "learning.resource_hub", "deployment_confirmation", "state.empty"):
            self.assertIn(phrase, screen_map)

    def test_registry_and_route_reference_are_bounded_and_subject_bound(self):
        self.assertEqual(len(INTENT_REGISTRY), len(set(INTENT_REGISTRY)))
        with tempfile.TemporaryDirectory() as root:
            state = LocalState(Path(root) / "state.sqlite3")
            try:
                app = type("App", (), {"state": state, "platform": "telegram"})()
                source = Screen(
                    identifier="academic.course.detail",
                    title="📚 درس",
                    action_rows=(),
                )
                action = Action(
                    "academic.course.open",
                    "جزئیات درس",
                    intent=CallbackIntent(
                        "academic.course.open",
                        (("course_id", "11111111-1111-4111-8111-111111111111"),),
                    ),
                )
                source = Screen(identifier=source.identifier, title=source.title, action_rows=(ActionRow((action,)),))
                runtime = core_to_runtime(app, "student-a", source)
                callback = runtime.rows[0][0].callback
                self.assertIsNotNone(callback)
                self.assertLessEqual(len(callback.encode("utf-8")), 64)
                self.assertEqual(decode_v3_intent(app, "student-a", callback)[0], "academic.course.open")
                self.assertIsNone(decode_v3_intent(app, "student-b", callback))
            finally:
                state.close()

    def test_provider_fallback_is_human_and_cross_channel(self):
        source = Screen(
            identifier="state.error",
            title="⚠️ اتصال به فانوس",
            intro="سرویس موقتاً پاسخ نمی‌دهد. دوباره امتحان کنید.",
            action_rows=(),
        )
        telegram = TelegramV3Renderer().render(provider_screen(source), context=ProviderContext(private_chat=True))
        bale = BaleV3Renderer().render(provider_screen(source), context=ProviderContext(private_chat=True))
        for text in (telegram.plain_text, bale.text):
            self.assertIn("دوباره امتحان کنید", text)
            self.assertNotRegex(text, r"[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}")
            self.assertNotIn("FanoosApiError", text)

    def test_backend_errors_are_localized_before_they_reach_copy(self):
        with tempfile.TemporaryDirectory() as root:
            state = LocalState(Path(root) / "state.sqlite3")
            try:
                app = BotApplication(
                    backend=object(),
                    state=state,
                    platform="telegram",
                    config=ApplicationConfig("https://fanoos.test/"),
                )
                result = app._error(FanoosApiError("internal_stack_trace", "secret", 500))
                self.assertNotIn("internal_stack_trace", result.screen.text)
                self.assertNotIn("secret", result.screen.text)
                self.assertIn("سرویس", result.screen.text)
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
