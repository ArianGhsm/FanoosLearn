from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.botapi import BotApiError
from fanoos_bot.capabilities import BALE, TELEGRAM
from fanoos_bot.models import ActionResult, Screen
from fanoos_bot.runtime import BotRuntime, NotificationPump, UpdateContext
from fanoos_bot.state import LocalState


WORKSPACE = "11111111-1111-4111-8111-111111111111"
REQUEST = "22222222-2222-4222-8222-222222222222"


class OwnerBackend:
    def __init__(self, can_manage: bool = True):
        self.can_manage = can_manage
        self.overview_calls = 0
        self.deploy_calls = 0
        self.status_calls = 0

    def workspaces(self, platform, subject):
        return {"workspaces": [{"id": WORKSPACE, "name": "فضای نمونه"}], "selected_workspace_id": WORKSPACE}

    def deployment_overview(self, subject, target):
        self.overview_calls += 1
        return {
            "can_manage_deployments": self.can_manage and subject == "owner",
            "current_release_sha": "a" * 40,
            "candidate_sha": "b" * 40,
            "update_available": True,
            "health": {"status": "healthy"},
        }

    def request_deployment(self, subject, target, idempotency_key):
        self.deploy_calls += 1
        return {"request_id": REQUEST, "state": "REQUESTED", "candidate_sha": "b" * 40}

    def deployment_status(self, subject, request_id):
        self.status_calls += 1
        return {"request_id": request_id, "state": "HEALTHCHECK", "candidate_sha": "b" * 40}


class RecordingTransport:
    capabilities = TELEGRAM

    def __init__(self, *, fail: Exception | None = None):
        self.fail = fail
        self.sent = 0

    def answer_callback(self, callback_id):
        return None

    def send_screen(self, chat_id, screen):
        if self.fail is not None:
            raise self.fail
        self.sent += 1
        return {"message_id": self.sent}


class CountingApp:
    def __init__(self):
        self.backend = object()
        self.calls = 0

    def callback(self, subject, private, value):
        self.calls += 1
        return ActionResult(Screen("پاسخ"))


class NotificationBackend:
    def __init__(self):
        self.receipts = []

    def claim_notification(self, platform):
        return {
            "delivery": {
                "delivery_id": "notification-1",
                "lease_token": "lease-1",
                "subject": "student",
                "payload": {"title": "اعلان", "body": "متن اعلان"},
            }
        }

    def notification_receipt(self, *args):
        self.receipts.append(args)
        return {"recorded": True}


class AdminReliabilityJourneyTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.state = LocalState(Path(self.temp.name) / "state.sqlite3")

    def tearDown(self):
        self.state.close()
        self.temp.cleanup()

    def test_duplicate_callback_is_durable_and_application_runs_once(self):
        app = CountingApp()
        runtime = BotRuntime("telegram", RecordingTransport(), app, self.state)
        ctx = UpdateContext("student", "chat", True, callback_id="cb", event_id="event-1")
        self.assertEqual(runtime.handle_callback(ctx, "home"), "1")
        self.assertEqual(runtime.handle_callback(ctx, "home"), "1")
        self.assertEqual(app.calls, 1)
        self.assertEqual(self.state.processed_update("telegram", "event-1"), "1")

    def test_renderer_failure_consumes_callback_without_replaying_business_logic(self):
        app = CountingApp()
        runtime = BotRuntime(
            "telegram",
            RecordingTransport(fail=BotApiError("network_unavailable", transient=True)),
            app,
            self.state,
        )
        ctx = UpdateContext("student", "chat", True, event_id="event-2")
        with self.assertRaises(BotApiError):
            runtime.handle_callback(ctx, "home")
        self.assertTrue(runtime.handle_callback(ctx, "home").startswith("failed:"))
        self.assertEqual(app.calls, 1)

    def test_notification_non_provider_failure_closes_lease_with_safe_receipt(self):
        backend = NotificationBackend()
        pump = NotificationPump(
            "telegram", backend, RecordingTransport(fail=ValueError("renderer broke")), self.state
        )
        self.assertTrue(pump.run_once())
        self.assertEqual(backend.receipts[-1][4:6], ("failed", None))
        self.assertEqual(backend.receipts[-1][6], "notification_delivery_failed")

    def test_owner_update_is_private_capability_gated_and_idempotent(self):
        backend = OwnerBackend()
        app = BotApplication(backend, self.state, "telegram", ApplicationConfig(deployment_target_key="prod"))
        self.assertIn("خصوصی", app.update_begin("owner", False).screen.text)
        denied = app.update_begin("student", True)
        self.assertIn("اجازه", denied.screen.text)
        confirmation = app.update_begin("owner", True)
        self.assertIn("نسخه جدید موجود است", confirmation.screen.text)
        ref = confirmation.screen.rows[0][0].callback.split(":", 1)[1]
        self.assertIn("درخواست ثبت شده", app.update_confirm("owner", True, ref).screen.text)
        self.assertIn("منقضی", app.update_confirm("owner", True, ref).screen.text)
        self.assertEqual(backend.deploy_calls, 1)

    def test_bale_has_no_deployment_control_and_arbitrary_status_id_is_rejected(self):
        backend = OwnerBackend()
        app = BotApplication(backend, self.state, "bale", ApplicationConfig(deployment_target_key="prod"))
        self.assertIn("فعال نیست", app.update_begin("owner", True).screen.text)
        telegram = BotApplication(
            backend, self.state, "telegram", ApplicationConfig(deployment_target_key="prod")
        )
        self.assertIn("در دسترس نیست", telegram.update_status("owner", True, "branch/main").screen.text)
        self.assertEqual(backend.status_calls, 0)
        self.assertFalse(BALE.supports_forward_protection)


if __name__ == "__main__":
    unittest.main()
