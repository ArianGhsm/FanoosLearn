from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.activity import ActivityController
from fanoos_bot.botapi import BotApiError, JsonBotApiTransport
from fanoos_bot.capabilities import TELEGRAM
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.models import ActionResult, Button, Screen
from fanoos_bot.runtime import BotRuntime, UpdateContext
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.core import (
    ProtectContent,
    Screen as CoreScreen,
)
from fanoos_bot.ui_v3.providers import (
    ProviderAction,
    ProviderContext,
    ProviderScreen,
    ProviderSection,
    TelegramV3Renderer,
)
from fanoos_bot.ui_v3.wiring import core_to_runtime


WORKSPACE = "11111111-1111-4111-8111-111111111111"


class RecordingTelegram(JsonBotApiTransport):
    def __init__(self, *, failures=None):
        super().__init__(
            "https://example.invalid",
            "token",
            TELEGRAM,
            rich_ui_enabled=True,
            activity_ui_enabled=False,
        )
        self.calls: list[tuple[str, dict]] = []
        self.failures = list(failures or [])

    def _call(self, method, payload=None):
        self.calls.append((method, dict(payload or {})))
        if self.failures and self.failures[0][0] == method:
            _, error = self.failures.pop(0)
            raise error
        return {"message_id": len(self.calls)}


class HomeBackend:
    def workspaces(self, platform, subject):
        return {"workspaces": [{"id": WORKSPACE, "name": "فضای نمونه"}], "selected_workspace_id": WORKSPACE}

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {
            "items": [{"course_title": "فیزیولوژی", "starts_at": "2026-09-11T08:30:00+03:30"}],
            "timezone": "Asia/Tehran",
            "next_cursor": None,
        }

    def announcements(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [{"title": "اطلاعیه مهم آموزشی"}], "next_cursor": None}


class CountingApp:
    def __init__(self, result, events):
        self.backend = object()
        self.result = result
        self.events = events
        self.calls = 0

    def callback(self, subject, private, value):
        self.calls += 1
        self.events.append("app")
        return self.result


class TelegramRuntimeContractTest(unittest.TestCase):
    def test_semantic_renderer_produces_rtl_rich_sections_and_packed_keyboard(self):
        source = ProviderScreen(
            title="🏠 خانه",
            semantic_kind="home.active",
            context="فضای نمونه",
            intro="نمای سریع آموزشی",
            sections=(
                ProviderSection("📅 برنامه", "فیزیولوژی · ۰۸:۳۰"),
                ProviderSection("📢 تازه", "اطلاعیه مهم آموزشی"),
            ),
            actions=(
                ProviderAction("📚 درس‌ها", callback="courses"),
                ProviderAction("📅 برنامه", callback="schedule"),
            ),
            edit_policy="edit_if_safe",
        )
        # ProviderScreen is the public provider-neutral contract; the renderer
        # must produce one RTL rich plan and keep short actions in one row.
        plan = TelegramV3Renderer().render(source, context=ProviderContext())
        self.assertTrue(plan.rich_message["is_rtl"])
        self.assertIn("<h3>🏠 خانه</h3>", plan.rich_message["html"])
        self.assertIn("اطلاعیه مهم آموزشی", plan.plain_text)
        self.assertEqual(len(plan.keyboard), 1)
        self.assertEqual(len(plan.keyboard[0]), 2)

    def test_normal_integrated_home_uses_telegram_v3_transport(self):
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "state.sqlite3")
        try:
            app = BotApplication(HomeBackend(), state, "telegram", ApplicationConfig("https://fanoos.test/"))
            transport = RecordingTelegram()
            runtime = BotRuntime(
                "telegram",
                transport,
                app,
                state,
                activity=ActivityController(transport, enabled=False),
            )
            runtime.handle_message(UpdateContext("student", "42", True), "/home")
            self.assertEqual(transport.calls[0][0], "sendRichMessage")
            self.assertIn("اطلاعیه مهم آموزشی", transport.calls[0][1]["rich_message"]["html"])
            self.assertIn("inline_keyboard", transport.calls[0][1]["reply_markup"])
        finally:
            state.close()
            temp.cleanup()

    def test_callback_ack_precedes_application_and_rich_failure_plain_fallback_does_not_replay(self):
        events = []
        transport = RecordingTelegram(failures=[("sendRichMessage", BotApiError("400"))])
        app = CountingApp(ActionResult(Screen("خانه\n\nمتن")), events)
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "state.sqlite3")
        try:
            runtime = BotRuntime(
                "telegram",
                transport,
                app,
                state,
                activity=ActivityController(transport, enabled=False),
            )
            original_call = transport._call

            def record_call(method, payload=None):
                events.append(method)
                return original_call(method, payload)

            transport._call = record_call
            runtime.handle_callback(UpdateContext("student", "42", True, callback_id="cb"), "home")
            self.assertEqual(events[0], "answerCallbackQuery")
            self.assertEqual(events[1], "app")
            self.assertEqual([method for method, _ in transport.calls], ["answerCallbackQuery", "sendRichMessage", "sendMessage"])
            self.assertEqual(app.calls, 1)
        finally:
            state.close()
            temp.cleanup()

    def test_edit_failure_falls_back_to_one_new_presentation_without_replaying_callback(self):
        events = []
        transport = RecordingTelegram(
            failures=[("editMessageText", BotApiError("400")), ("editMessageText", BotApiError("400"))]
        )
        app = CountingApp(ActionResult(Screen("خانه\n\nمتن", edit=True)), events)
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "state.sqlite3")
        try:
            runtime = BotRuntime(
                "telegram",
                transport,
                app,
                state,
                activity=ActivityController(transport, enabled=False),
            )
            runtime.handle_callback(UpdateContext("student", "42", True, 77, "cb"), "home")
            self.assertEqual(app.calls, 1)
            self.assertEqual(
                [method for method, _ in transport.calls],
                ["answerCallbackQuery", "editMessageText", "editMessageText", "sendRichMessage"],
            )
        finally:
            state.close()
            temp.cleanup()

    def test_callback_limit_is_checked_for_legacy_protected_keyboard(self):
        transport = RecordingTelegram()
        callback = "ا" * 33  # 66 UTF-8 bytes, beyond Telegram's 64-byte limit.
        screen = Screen("محتوای محافظت‌شده", ((Button("دریافت", callback=callback),),), protect_content=True)
        with self.assertRaisesRegex(BotApiError, "callback_too_large"):
            transport.send_screen("42", screen)
        self.assertEqual(transport.calls, [])

    def test_v3_protected_screen_is_one_plain_protected_operation(self):
        source = CoreScreen(
            identifier="learning.protected.ready",
            title="🔒 دریافت امن",
            intro="نسخه مجاز آماده ارسال است.",
            protect_content=ProtectContent.REQUIRED,
        )
        class RouteState:
            def create_route(self, *args, **kwargs):
                return "route-1"

        transport = RecordingTelegram()
        runtime_screen = core_to_runtime(RouteState(), "student", source)
        transport.send_screen("42", runtime_screen)
        self.assertEqual([method for method, _ in transport.calls], ["sendMessage"])
        self.assertTrue(transport.calls[0][1]["protect_content"])


if __name__ == "__main__":
    unittest.main()
