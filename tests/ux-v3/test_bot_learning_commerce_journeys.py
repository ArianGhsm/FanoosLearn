from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.runtime import BotRuntime, UpdateContext
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.learning import ProtectedDeliveryState, protected_delivery_screen


WORKSPACE = "11111111-1111-4111-8111-111111111111"
COURSE = "22222222-2222-4222-8222-222222222222"
RESOURCE = "33333333-3333-4333-8333-333333333333"
RESOURCE_TWO = "44444444-4444-4444-8444-444444444444"
ASSESSMENT = "55555555-5555-4555-8555-555555555555"
ORDER = "66666666-6666-4666-8666-666666666666"
PRODUCT = "77777777-7777-4777-8777-777777777777"


class LearningBackend:
    def __init__(self, *, assessments=True, commerce=True):
        self.assessments_enabled = assessments
        self.commerce_enabled = commerce
        self.create_order_calls = 0

    def workspaces(self, platform, subject):
        return {"workspaces": [{"id": WORKSPACE, "name": "فضای نمونه"}], "selected_workspace_id": WORKSPACE}

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {
            "items": [],
            "courses": [{"course_id": COURSE, "course_title": "زیست‌شناسی"}],
            "timezone": "Asia/Tehran",
            "next_cursor": None,
        }

    def resources(self, platform, subject, workspace_id, limit, cursor):
        return {
            "items": [
                {
                    "resource_id": RESOURCE,
                    "title": "جزوه سلول",
                    "course_id": COURSE,
                    "course_title": "زیست‌شناسی",
                    "type_key": "booklet",
                    "delivery_supported": True,
                    "access_state": "active",
                },
                {
                    "resource_id": RESOURCE_TWO,
                    "title": "آزمونک سلول",
                    "course_id": COURSE,
                    "course_title": "زیست‌شناسی",
                    "type_key": "quiz",
                    "delivery_supported": False,
                    "access_state": "denied",
                },
            ],
            "next_cursor": None,
        }

    def assessments(self, platform, subject, workspace_id, limit, cursor):
        if not self.assessments_enabled:
            return None
        return {
            "items": [
                {
                    "assessment_id": ASSESSMENT,
                    "title": "تمرین سلول",
                    "course_id": COURSE,
                    "course_title": "زیست‌شناسی",
                    "state": "active",
                }
            ]
        }

    def commerce_projection(self, platform, subject, workspace_id, limit, cursor):
        if not self.commerce_enabled:
            return None
        return {
            "catalog": [{"product_id": PRODUCT, "title": "دسترسی جزوه", "amount_minor": 120000, "currency": "IRR"}],
            "orders": [{"order_id": ORDER, "title": "دسترسی جزوه", "status": "paid", "amount_minor": 120000, "currency": "IRR"}],
            "access": [{"title": "دسترسی جزوه", "state": "denied"}],
        }

    def order_status(self, platform, subject, workspace_id, order_id):
        return {
            "order_id": order_id,
            "title": "دسترسی جزوه",
            "status": "paid",
            "amount_minor": 120000,
            "currency": "IRR",
            "entitlement": {"granted": False},
        }

    def create_order(self, *args):
        self.create_order_calls += 1
        raise AssertionError("normal /buy UX must not create an order from a raw id")


class LearningCommerceJourneyTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.state = LocalState(Path(self.temp.name) / "state.sqlite3")
        self.backend = LearningBackend()
        self.app = BotApplication(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/"),
        )

    def tearDown(self):
        self.state.close()
        self.temp.cleanup()

    @staticmethod
    def text(result):
        return result.screen.plain_text()

    def test_resource_hub_filters_from_authorized_projection_and_hides_ids(self):
        result = self.app.resources("student")
        self.assertEqual(result.screen.identifier, "learning.resource_hub")
        self.assertIn("جزوه سلول", self.text(result))
        self.assertNotIn(RESOURCE, self.text(result))

        filtered = self.app.callback(
            "student", True,
            f"learning.resources.apply.course|course={COURSE}",
        )
        self.assertIn("درس: زیست‌شناسی", self.text(filtered))
        self.assertIn("جزوه سلول", self.text(filtered))

        recent = self.app.callback("student", True, "learning.resources.recent")
        self.assertIn("تازه‌ترین‌ها", self.text(recent))

    def test_resource_detail_honors_denied_access_without_delivery_button(self):
        result = self.app.resource_detail("student", RESOURCE_TWO)
        self.assertIn("دسترسی فعال نیست", self.text(result))
        labels = [action.label for row in result.screen.action_rows for action in row.actions]
        self.assertNotIn("🔒 دریافت امن", labels)

    def test_assessment_projection_is_metadata_only_and_detail_continues_to_web(self):
        result = self.app.assessments("student")
        self.assertIn("تمرین سلول", self.text(result))
        self.assertNotIn(ASSESSMENT, self.text(result))
        detail = self.app.callback("student", True, f"learning.assessment.open|assessment={ASSESSMENT}")
        labels = [action.label for row in detail.screen.action_rows for action in row.actions]
        self.assertIn("🌐 ادامه آزمون در فانوس", labels)
        self.assertNotIn("سؤال", self.text(detail))

    def test_assessment_without_bot_safe_projection_uses_honest_website_fallback(self):
        self.app.backend = LearningBackend(assessments=False)
        result = self.app.assessments("student")
        self.assertIn("projection امن", self.text(result))
        self.assertIn("canonical", self.text(result))

    def test_commerce_separates_paid_order_from_denied_entitlement_and_hides_product_id(self):
        result = self.app.payments("student")
        self.assertIn("دسترسی جزوه", self.text(result))
        self.assertNotIn(PRODUCT, self.text(result))
        detail = self.app.order_status("student", ORDER)
        self.assertIn("دسترسی فعال نیست", self.text(detail))
        self.assertIn("پرداخت تأیید شده", self.text(detail))

    def test_buy_command_opens_canonical_purchase_center(self):
        runtime = BotRuntime("telegram", object(), self.app, self.state)
        result = runtime._message_result(UpdateContext("student", "chat", True), "/buy", PRODUCT)
        self.assertEqual(result.screen.identifier, "learning.commerce_hub")
        self.assertEqual(self.backend.create_order_calls, 0)

    def test_protected_state_family_has_safe_unavailable_terminal(self):
        screen = protected_delivery_screen(ProtectedDeliveryState.UNAVAILABLE, resource_title="جزوه سلول")
        self.assertEqual(screen.identifier, "learning.protected.unavailable")
        self.assertIn("در دسترس نیست", screen.plain_text())
        self.assertNotIn(RESOURCE, screen.plain_text())


if __name__ == "__main__":
    unittest.main()
