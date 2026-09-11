from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.activity import ActivityController
from fanoos_bot.botapi import JsonBotApiTransport
from fanoos_bot.capabilities import BALE
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.runtime import BotRuntime, UpdateContext
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.core import ProtectContent, Screen as CoreScreen
from fanoos_bot.ui_v3.providers import (
    BaleV3Renderer,
    ProviderAction,
    ProviderContext,
    ProviderScreen,
)
from fanoos_bot.ui_v3.providers.core_adapter import provider_screen
from fanoos_bot.ui_v3.wiring import core_to_runtime


WORKSPACE = "11111111-1111-4111-8111-111111111111"


class RecordingBale(JsonBotApiTransport):
    def __init__(self):
        super().__init__("https://example.invalid", "token", BALE)
        self.calls: list[tuple[str, dict]] = []

    def _call(self, method, payload=None):
        self.calls.append((method, dict(payload or {})))
        return {"message_id": len(self.calls)}


class HomeBackend:
    def workspaces(self, platform, subject):
        return {"workspaces": [{"id": WORKSPACE, "name": "فضای نمونه"}], "selected_workspace_id": WORKSPACE}

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {"items": [], "timezone": "Asia/Tehran", "next_cursor": None}

    def announcements(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [], "next_cursor": None}


class RouteState:
    def create_route(self, *args, **kwargs):
        return "route-1"


class BaleRuntimeContractTest(unittest.TestCase):
    def test_bale_renderer_is_provider_native_and_keeps_back_home_edit_semantics(self):
        source = ProviderScreen(
            title="📚 درس‌ها",
            semantic_kind="academic.courses",
            context="فضای نمونه",
            intro="فهرست درس‌های شما",
            actions=(
                ProviderAction("بازگشت", callback="back", role="back"),
                ProviderAction("🏠 خانه", callback="home", role="home"),
            ),
            edit_policy="edit_if_safe",
        )
        plan = BaleV3Renderer().render(source, context=ProviderContext(current_message_id=9))
        self.assertEqual(plan.delivery_intent, "edit_if_safe")
        self.assertEqual(plan.callback_ack.required, False)
        self.assertEqual(plan.keyboard[0][0]["callback_data"], "back")
        self.assertEqual(plan.keyboard[0][1]["callback_data"], "home")
        self.assertNotIn("rich_message", plan.text)

    def test_integrated_bale_runtime_uses_shared_application_and_plain_native_payload(self):
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "state.sqlite3")
        try:
            app = BotApplication(HomeBackend(), state, "bale", ApplicationConfig("https://fanoos.test/"))
            transport = RecordingBale()
            runtime = BotRuntime(
                "bale",
                transport,
                app,
                state,
                activity=ActivityController(transport, enabled=False),
            )
            runtime.handle_message(UpdateContext("student", "42", True), "/home")
            self.assertEqual([method for method, _ in transport.calls], ["sendMessage"])
            payload = transport.calls[0][1]
            self.assertNotIn("rich_message", payload)
            self.assertIn("text", payload)
            self.assertIn("inline_keyboard", payload["reply_markup"])
            self.assertNotIn("reply_parameters", payload)
        finally:
            state.close()
            temp.cleanup()

    def test_bale_protected_content_fails_closed_without_original_text(self):
        source = CoreScreen(
            identifier="learning.protected.ready",
            title="🔒 دریافت امن",
            intro="اصل محرمانه نباید نمایش داده شود.",
            protect_content=ProtectContent.REQUIRED,
        )
        runtime_screen = core_to_runtime(RouteState(), "student", source)
        renderer = BaleV3Renderer()
        plan = renderer.render(provider_screen(runtime_screen.v3), context=ProviderContext())
        self.assertFalse(plan.can_deliver_original)
        self.assertEqual(plan.failure_reason, "forward_protection_unavailable")
        self.assertNotIn("اصل محرمانه", plan.text)

        transport = RecordingBale()
        transport.send_screen("42", runtime_screen)
        self.assertEqual([method for method, _ in transport.calls], ["sendMessage"])
        self.assertNotIn("اصل محرمانه", transport.calls[0][1]["text"])

    def test_bale_never_exposes_update_server_even_with_owner_permission(self):
        source = ProviderScreen(
            title="⚙️ مدیریت",
            semantic_kind="deployment.management",
            actions=(
                ProviderAction(
                    "🔄 به‌روزرسانی سرور",
                    callback="update",
                    requires_permission="deployment.manage",
                ),
                ProviderAction("🏠 خانه", callback="home", role="home"),
            ),
        )
        plan = BaleV3Renderer().render(
            source,
            context=ProviderContext(
                private_chat=True,
                canonical_permissions=frozenset({"deployment.manage"}),
            ),
        )
        self.assertNotIn("به‌روزرسانی سرور", plan.text)
        callbacks = {
            item.get("callback_data")
            for row in plan.keyboard
            for item in row
            if item.get("callback_data")
        }
        self.assertNotIn("update", callbacks)
        self.assertIn("home", callbacks)

    def test_malformed_presentation_metadata_falls_back_to_plain_text(self):
        class BrokenSection:
            body = ""
            items = ()

            @property
            def title(self):
                raise AttributeError("malformed title")

        source = ProviderScreen(
            title="فانوس",
            semantic_kind="core.help",
            sections=(BrokenSection(),),
            plain_text="راهنمای فانوس",
        )
        plan = BaleV3Renderer().render(source)
        self.assertEqual(plan.text, "راهنمای فانوس")


if __name__ == "__main__":
    unittest.main()
