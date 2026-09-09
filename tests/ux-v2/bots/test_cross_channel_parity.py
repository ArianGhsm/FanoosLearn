from __future__ import annotations

import json
import subprocess
import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState


ROOT = Path(__file__).resolve().parents[3]
FIXTURE = ROOT / "tests/ux-v2/parity/canonical_fixture.json"
WEB_PROJECTION = ROOT / "tests/ux-v2/parity/web_semantic_projection.js"


class CanonicalBackend:
    PRODUCT = "dddddddd-dddd-4ddd-8ddd-dddddddddddd"

    def __init__(self, fixture: dict):
        self.fixture = fixture
        self.selected = fixture["workspace"]["id"]
        self.receipts: list[tuple] = []

    def workspaces(self, platform, subject):
        return {
            "workspaces": [self.fixture["workspace"]],
            "selected_workspace_id": self.selected,
        }

    def select_workspace(self, platform, subject, workspace_id):
        if workspace_id != self.fixture["workspace"]["id"]:
            raise FanoosApiError("workspace_forbidden", "foreign", 403)
        self.selected = workspace_id
        return {"selected_workspace_id": workspace_id}

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        if workspace_id != self.fixture["workspace"]["id"]:
            raise FanoosApiError("workspace_forbidden", "foreign", 403)
        return {
            "timezone": self.fixture["workspace"]["timezone_name"],
            "from_date": from_date,
            "to_date": to_date,
            "items": [self.fixture["schedule"]],
            "courses": [self.fixture["course"]],
            "next_cursor": None,
        }

    def grades(self, *args):
        return {"items": [self.fixture["grade"]], "next_cursor": None}

    def announcements(self, *args):
        return {"items": [self.fixture["announcement"]], "next_cursor": None}

    def resources(self, *args):
        return {"items": [self.fixture["resource"]], "next_cursor": None}

    def create_order(self, platform, subject, workspace_id, product_id, idempotency_key):
        order = self.fixture["order"]
        return {
            "order_id": "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee",
            "title": order["title"],
            "amount_minor": order["amount_minor"],
            "currency": order["currency"],
            "status": order["status"],
            "payment_url": "https://fanoos.test/pay",
        }

    def order_status(self, *args):
        return {
            "status": self.fixture["payment"]["status"],
            "entitlement": {"granted": self.fixture["entitlement"]["granted"]},
        }

    def delivery_issue(self, platform, subject, workspace_id, resource_id):
        if resource_id != self.fixture["resource"]["resource_id"]:
            raise FanoosApiError("resource_access_denied", "foreign", 403)
        return {
            "issuance_id": "ffffffff-ffff-4fff-8fff-ffffffffffff",
            "delivery_token": "opaque-delivery-token",
        }

    def delivery_consume(self, platform, subject, workspace_id, token):
        return {
            "issuance_id": "ffffffff-ffff-4fff-8fff-ffffffffffff",
            "content": {"text": "محتوای مجاز"},
            "object_id": None,
            "forward_protection_required": True,
        }

    def delivery_receipt(self, *args):
        self.receipts.append(args)
        return {}

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject == "owner"}

    def unlink(self, *args):
        return {"revoked": True}


class CrossChannelSemanticParityTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.fixture = json.loads(FIXTURE.read_text(encoding="utf-8"))
        raw = subprocess.check_output(
            ["node", str(WEB_PROJECTION), str(FIXTURE)],
            cwd=ROOT,
            text=True,
        )
        cls.web = json.loads(raw)

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.backend = CanonicalBackend(self.fixture)
        self.telegram_state = LocalState(Path(self.temp.name) / "telegram.db")
        self.bale_state = LocalState(Path(self.temp.name) / "bale.db")
        config = ApplicationConfig("https://fanoos.test/", "production")
        self.telegram = BotApplication(self.backend, self.telegram_state, "telegram", config)
        self.bale = BotApplication(self.backend, self.bale_state, "bale", ApplicationConfig("https://fanoos.test/"))

    def tearDown(self):
        self.telegram_state.close()
        self.bale_state.close()
        self.temp.cleanup()

    def test_workspace_course_schedule_grade_announcement_resource_parity(self):
        for app in (self.telegram, self.bale):
            home = app.home("student").screen.text
            self.assertIn(self.web["workspace"]["name"], home)

            courses = app.courses("student").screen.text
            self.assertIn(self.web["course"]["title"], courses)
            self.assertIn(self.web["course"]["code"], courses)
            self.assertNotIn(self.web["course"]["id"], courses)

            schedule = app.day_schedule("student", 0).screen.text
            self.assertIn(self.web["schedule"]["title"], schedule)
            self.assertIn(self.web["schedule"]["course"], schedule)
            self.assertIn(self.web["schedule"]["time"], schedule)
            self.assertIn(self.web["schedule"]["location"], schedule)

            grades = app.grades("student").screen.text
            self.assertIn(self.web["grade"]["course"], grades)
            self.assertIn("۱۸ از ۲۰", grades)

            announcements = app.announcements("student").screen.text
            self.assertIn(self.web["announcement"]["title"], announcements)
            self.assertIn(self.web["announcement"]["body"], announcements)

            resource = app.resource_detail("student", self.fixture["resource"]["resource_id"]).screen.text
            self.assertIn(self.web["resource"]["title"], resource)
            self.assertIn(self.web["resource"]["course"], resource)
            self.assertIn(self.web["resource"]["type"], resource)
            self.assertNotIn(self.fixture["resource"]["resource_id"], resource)

    def test_assessment_capability_difference_is_explicit_and_safe(self):
        self.assertEqual(self.web["assessment"]["type"], "تمرین")
        for app in (self.telegram, self.bale):
            screen = app.assessments("student").screen
            self.assertIn("آزمون‌ها", screen.text)
            self.assertIn("projection", screen.text)
            self.assertNotIn("امتیاز", screen.text.replace("امتیاز یا وضعیت آزمون", ""))
            self.assertTrue(any(button.url == "https://fanoos.test/" for row in screen.rows for button in row if button.url))

    def test_commerce_payment_and_entitlement_meanings_match(self):
        for app in (self.telegram, self.bale):
            order = app.create_order("student", CanonicalBackend.PRODUCT, "parity-order-1").screen.text
            self.assertIn(self.web["order"]["title"], order)
            self.assertIn(self.web["order"]["amount"], order)
            self.assertIn(self.web["order"]["status"], order)

            status = app.order_status("student", "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee").screen.text
            self.assertIn(self.web["payment"]["status"], status)
            self.assertIn("دسترسی: فعال نیست", status)

    def test_security_parity_and_channel_specific_protection(self):
        for app in (self.telegram, self.bale):
            denied = app.select_workspace("student", "99999999-9999-4999-8999-999999999999").screen.text
            self.assertIn("در دسترس نیست", denied)
            self.assertNotIn("workspace_forbidden", denied)

        telegram_delivery = self.telegram.protected_resource("student", self.fixture["resource"]["resource_id"])
        self.assertTrue(telegram_delivery.screen.protect_content)
        bale_delivery = self.bale.protected_resource("student", self.fixture["resource"]["resource_id"])
        self.assertIn("ارسال انجام نشد", bale_delivery.screen.text)
        self.assertFalse(bale_delivery.screen.protect_content)

    def test_owner_deployment_surface_exists_only_on_private_telegram_path(self):
        telegram_owner = self.telegram.more("owner", private=True).screen
        owner_labels = [button.text for row in telegram_owner.rows for button in row]
        self.assertIn("⚙️ مدیریت", owner_labels)

        telegram_public = self.telegram.more("owner", private=False).screen
        self.assertNotIn("⚙️ مدیریت", [button.text for row in telegram_public.rows for button in row])

        bale_owner = self.bale.more("owner", private=True).screen
        self.assertNotIn("⚙️ مدیریت", [button.text for row in bale_owner.rows for button in row])


if __name__ == "__main__":
    unittest.main()
