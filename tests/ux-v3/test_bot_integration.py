from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.botapi import BotApiError, JsonBotApiTransport
from fanoos_bot.capabilities import BALE, TELEGRAM
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.models import Screen as RuntimeScreen, ScreenPresentation
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.academic import course_list_screen
from fanoos_bot.ui_v3.core import Action, ActionRow, CallbackIntent, ProtectContent, Screen as CoreScreen
from fanoos_bot.ui_v3.learning import order_access_detail_screen
from fanoos_bot.ui_v3.providers import BaleV3Renderer, ProviderContext, TelegramV3Renderer
from fanoos_bot.ui_v3.providers.core_adapter import provider_screen
from fanoos_bot.ui_v3.wiring import core_to_runtime, decode_v3_intent


WORKSPACE = "11111111-1111-4111-8111-111111111111"
COURSE = "22222222-2222-4222-8222-222222222222"


class WorkspaceBackend:
    def __init__(self, workspaces=None, selected=None):
        self.rows = list(workspaces or [])
        self.selected = selected
        self.select_calls = []

    def workspaces(self, platform, subject):
        return {"workspaces": list(self.rows), "selected_workspace_id": self.selected}

    def select_workspace(self, platform, subject, workspace_id):
        self.select_calls.append((platform, str(subject), workspace_id))
        if workspace_id not in {str(row.get("id")) for row in self.rows}:
            raise RuntimeError("workspace_forbidden")
        self.selected = workspace_id
        return {"selected_workspace_id": workspace_id}

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {"items": [], "timezone": "Asia/Tehran", "next_cursor": None}

    def grades(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [], "next_cursor": None}

    def resources(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [], "next_cursor": None}

    def announcements(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [], "next_cursor": None}


class RecordingTransport(JsonBotApiTransport):
    def __init__(self, capabilities, *, rich=True):
        self.calls = []
        super().__init__(
            "https://provider.invalid",
            "test-token",
            capabilities,
            rich_ui_enabled=rich,
            activity_ui_enabled=False,
        )

    def _call(self, method, payload=None):
        self.calls.append((method, dict(payload or {})))
        return {"message_id": len(self.calls)}


class RouteApp:
    def __init__(self, state, platform="telegram"):
        self.state = state
        self.platform = platform


class BotV3IntegrationTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.state = LocalState(Path(self.tmp.name) / "state.sqlite3")

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    def test_single_membership_is_not_implicitly_selected(self):
        backend = WorkspaceBackend([{"id": WORKSPACE, "name": "دانشکده دندان‌پزشکی"}])
        app = BotApplication(
            backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/", "prod"),
        )
        result = app.home("student")
        self.assertIsInstance(result.screen, CoreScreen)
        self.assertEqual(result.screen.identifier, "workspace.list")
        self.assertEqual(backend.select_calls, [])
        self.assertIn("انتخاب", result.screen.intro)

    def test_zero_workspace_keeps_full_product_shell(self):
        app = BotApplication(
            WorkspaceBackend([]),
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/", "prod"),
        )
        result = app.home("student")
        self.assertEqual(result.screen.identifier, "onboarding.linked_no_workspace")
        labels = [action.label for row in result.screen.action_rows for action in row.actions]
        self.assertTrue(any("فضای آموزشی" in label for label in labels))
        self.assertTrue(any("حساب" in label for label in labels))
        self.assertTrue(any("راهنما" in label for label in labels))
        self.assertTrue(any("فانوس" in label for label in labels))

    def test_long_v3_callback_becomes_subject_bound_route_ref(self):
        app = RouteApp(self.state)
        screen = CoreScreen(
            identifier="academic.course.detail",
            title="📚 درس",
            action_rows=(
                ActionRow((
                    Action(
                        "academic.course.open",
                        "جزئیات درس",
                        intent=CallbackIntent(
                            "academic.course.open",
                            (("course_id", COURSE),),
                        ),
                    ),
                )),
            ),
        )
        runtime = core_to_runtime(app, "student-a", screen)
        callback = runtime.rows[0][0].callback
        self.assertIsNotNone(callback)
        self.assertTrue(callback.startswith("r:"))
        self.assertLessEqual(len(callback.encode("utf-8")), 64)
        decoded = decode_v3_intent(app, "student-a", callback)
        self.assertEqual(decoded, ("academic.course.open", {"course_id": COURSE}))
        self.assertIsNone(decode_v3_intent(app, "student-b", callback))

    def test_raw_course_uuid_is_not_visible_copy(self):
        screen = course_list_screen(
            [{"course_id": COURSE, "course_title": "اندودانتیکس ۱", "course_code": "ENDO-1"}],
            workspace_label="دانشکده دندان‌پزشکی",
        )
        self.assertNotIn(COURSE, screen.plain_text())
        self.assertIn("اندودانتیکس ۱", screen.plain_text())

    def test_telegram_v3_renders_rich_rtl_with_same_semantics(self):
        source = CoreScreen(identifier="home.active", title="🏠 خانه", intro="نمای سریع آموزشی")
        plan = TelegramV3Renderer().render(provider_screen(source), context=ProviderContext(private_chat=True))
        self.assertEqual(plan.plain_text.splitlines()[0], "🏠 خانه")
        self.assertIsNotNone(plan.rich_message)
        self.assertTrue(plan.rich_message["is_rtl"])
        self.assertIn("نمای سریع آموزشی", plan.rich_message["html"])

    def test_legacy_protected_telegram_send_is_one_atomic_provider_operation(self):
        transport = RecordingTransport(TELEGRAM, rich=True)
        screen = RuntimeScreen(
            "محتوای واقعی محافظت‌شده",
            protect_content=True,
            presentation=ScreenPresentation(
                title="🔒 محتوای محافظت‌شده",
                semantic_kind="protected_delivery_ready",
                severity="success",
            ),
        )
        transport.send_screen("42", screen)
        self.assertEqual(len(transport.calls), 1)
        method, payload = transport.calls[0]
        self.assertEqual(method, "sendRichMessage")
        self.assertTrue(payload["protect_content"])
        self.assertTrue(payload["rich_message"]["is_rtl"])

    def test_legacy_bale_protected_original_is_refused_before_network(self):
        transport = RecordingTransport(BALE)
        screen = RuntimeScreen(
            "محتوای محرمانه منبع",
            protect_content=True,
            presentation=ScreenPresentation(
                title="🔒 محتوای محافظت‌شده",
                semantic_kind="protected_delivery_ready",
                severity="success",
            ),
        )
        with self.assertRaisesRegex(BotApiError, "forward_protection_unsupported"):
            transport.send_screen("42", screen)
        self.assertEqual(transport.calls, [])

    def test_v3_bale_protected_policy_produces_safe_explanation(self):
        source = CoreScreen(
            identifier="learning.protected.ready",
            title="🔒 محتوای محافظت‌شده",
            intro="نسخه امن آماده است.",
            protect_content=ProtectContent.REQUIRED,
        )
        plan = BaleV3Renderer().render(
            provider_screen(source),
            context=ProviderContext(private_chat=True),
        )
        self.assertFalse(plan.can_deliver_original)
        self.assertEqual(plan.failure_reason, "forward_protection_unavailable")
        self.assertIn("محافظت", plan.text)
        self.assertNotIn("نسخه امن آماده است", plan.text)

    def test_owner_management_requires_private_telegram_and_permission(self):
        source = CoreScreen(
            identifier="management",
            title="⚙️ مدیریت",
            action_rows=(
                ActionRow((
                    Action(
                        "deployment.update",
                        "🔄 به‌روزرسانی سرور",
                        intent=CallbackIntent("home"),
                    ),
                )),
            ),
        )
        provider = provider_screen(source)
        denied = TelegramV3Renderer().render(
            provider,
            context=ProviderContext(private_chat=True, canonical_permissions=frozenset()),
        )
        allowed = TelegramV3Renderer().render(
            provider,
            context=ProviderContext(
                private_chat=True,
                canonical_permissions=frozenset({"deployment.manage"}),
            ),
        )
        bale = BaleV3Renderer().render(
            provider,
            context=ProviderContext(
                private_chat=True,
                canonical_permissions=frozenset({"deployment.manage"}),
            ),
        )
        self.assertFalse(any(item.get("text") == "🔄 به‌روزرسانی سرور" for row in denied.keyboard for item in row))
        self.assertTrue(any(item.get("text") == "🔄 به‌روزرسانی سرور" for row in allowed.keyboard for item in row))
        self.assertFalse(any(item.get("text") == "🔄 به‌روزرسانی سرور" for row in bale.keyboard for item in row))
        self.assertFalse(bale.can_deliver_original)

    def test_order_payment_and_entitlement_are_distinct_facts(self):
        screen = order_access_detail_screen(
            {
                "title": "دسترسی جزوه",
                "status": "paid",
                "amount_minor": 100000,
                "currency": "IRR",
                "entitlement": {"granted": True},
            },
            payment_status="paid",
        )
        facts = [fact for section in screen.sections for fact in section.facts]
        labels = [fact.label for fact in facts]
        self.assertIn("وضعیت سفارش", labels)
        self.assertIn("وضعیت پرداخت", labels)
        self.assertIn("دسترسی", labels)
        self.assertEqual(labels.count("وضعیت سفارش"), 1)
        self.assertEqual(labels.count("وضعیت پرداخت"), 1)
        self.assertEqual(labels.count("دسترسی"), 1)


if __name__ == "__main__":
    unittest.main()
