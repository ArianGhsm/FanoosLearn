import tempfile
import unittest
from pathlib import Path

from fanoos_bot.capabilities import BALE, TELEGRAM
from fanoos_bot.models import ActionResult, DeliveryReceiptContext, Screen
from fanoos_bot.runtime import BotRuntime, NotificationPump, UpdateContext
from fanoos_bot.state import LocalState


class RecordingTransport:
    def __init__(self):
        self.events = []
        self.n = 0

    def answer_callback(self, callback_id):
        self.events.append(("ack", callback_id))

    def send_screen(self, chat, screen):
        self.events.append(("send", screen.protect_content, screen.text))
        self.n += 1
        return {"message_id": self.n}

    def edit_screen(self, chat, message_id, screen):
        self.events.append(("edit", screen.text))
        return {"message_id": message_id}


class App:
    def __init__(self):
        self.backend = self

    def callback(self, *args):
        return ActionResult(Screen("done"))

    def home(self, *args):
        return ActionResult(Screen("home"))

    def help(self):
        return ActionResult(Screen("help"))

    def notification_receipt(self, *args):
        self.receipt = args


class Backend:
    def __init__(self):
        self.claimed = False
        self.receipts = []

    def claim_notification(self, platform):
        if self.claimed:
            return {"delivery": None}
        self.claimed = True
        return {
            "delivery": {
                "delivery_id": "d1",
                "lease_token": "l",
                "subject": "42",
                "payload": {"title": "T", "body": "B"},
            }
        }

    def notification_receipt(self, *args):
        self.receipts.append(args)


class DeliveryBackend:
    def __init__(self, fail=True):
        self.fail = fail
        self.receipts = []

    def delivery_receipt(self, *args):
        self.receipts.append(args)
        if self.fail:
            raise RuntimeError("backend unavailable")
        return {"recorded": True}


class ProtectedApp:
    def __init__(self, backend, result):
        self.backend = backend
        self.result = result
        self.calls = 0

    def protected_resource(self, subject, resource_id):
        self.calls += 1
        return self.result

    def home(self, *args):
        return ActionResult(Screen("home"))


class RuntimeTest(unittest.TestCase):
    def test_telegram_protection_serializable(self):
        self.assertTrue(TELEGRAM.supports_forward_protection)

    def test_bale_no_protection_capability(self):
        self.assertFalse(BALE.supports_forward_protection)

    def test_callback_ack_precedes_send(self):
        transport = RecordingTransport()
        app = App()
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "s")
        runtime = BotRuntime("telegram", transport, app, state)
        runtime.handle_callback(UpdateContext("1", "1", True, 1, "cb", "9"), "home")
        self.assertEqual(transport.events[0], ("ack", "cb"))
        state.close()
        temp.cleanup()

    def test_notification_restart_dedupe_receipt(self):
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "s")
        backend = Backend()
        transport = RecordingTransport()
        pump = NotificationPump("telegram", backend, transport, state)
        self.assertTrue(pump.run_once())
        self.assertEqual(len(backend.receipts), 1)
        state.close()
        temp.cleanup()

    def test_protected_send_is_not_repeated_when_backend_receipt_fails(self):
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "s")
        backend = DeliveryBackend(fail=True)
        result = ActionResult(
            Screen("protected", protect_content=True),
            DeliveryReceiptContext("workspace", "issuance", "delivery-idem"),
        )
        app = ProtectedApp(backend, result)
        transport = RecordingTransport()
        runtime = BotRuntime("telegram", transport, app, state)
        ctx = UpdateContext("1", "1", True, 1, None, "update-9")

        first = runtime.handle_message(ctx, "/resource resource-1")
        second = runtime.handle_message(ctx, "/resource resource-1")

        self.assertEqual(first, "1")
        self.assertEqual(second, "1")
        self.assertEqual(transport.n, 1)
        self.assertEqual(app.calls, 1)
        self.assertEqual(len(state.pending_delivery_receipts()), 1)

        backend.fail = False
        self.assertTrue(runtime.flush_delivery_receipt_once())
        self.assertEqual(state.pending_delivery_receipts(), [])
        state.close()
        temp.cleanup()

    def test_delivery_receipt_outbox_full_fails_closed_before_send(self):
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "s")
        state.PENDING_DELIVERY_RECEIPT_LIMIT = 1
        state.record_delivery_outcome(
            "telegram", "workspace-0", "issuance-0", "idem-0", "failed", error_code="x"
        )
        backend = DeliveryBackend(fail=True)
        result = ActionResult(
            Screen("protected", protect_content=True),
            DeliveryReceiptContext("workspace-1", "issuance-1", "idem-1"),
        )
        app = ProtectedApp(backend, result)
        transport = RecordingTransport()
        runtime = BotRuntime("telegram", transport, app, state)

        with self.assertRaisesRegex(RuntimeError, "outbox is full"):
            runtime.handle_message(
                UpdateContext("1", "1", True, 1, None, "update-10"),
                "/resource resource-2",
            )
        self.assertEqual(transport.n, 0)
        state.close()
        temp.cleanup()

    def test_receipt_bearing_delivery_rejects_multi_message_partial_send(self):
        temp = tempfile.TemporaryDirectory()
        state = LocalState(Path(temp.name) / "s")
        backend = DeliveryBackend(fail=False)
        result = ActionResult(
            Screen("x" * 5000, protect_content=True),
            DeliveryReceiptContext("workspace", "issuance", "oversize-idem"),
        )
        app = ProtectedApp(backend, result)
        transport = RecordingTransport()
        runtime = BotRuntime("telegram", transport, app, state)

        runtime.handle_message(
            UpdateContext("1", "1", True, 1, None, "update-11"),
            "/resource resource-3",
        )
        self.assertEqual(transport.n, 1)
        self.assertNotEqual(transport.events[0][2], "x" * 5000)
        self.assertEqual(backend.receipts[-1][4], "failed")
        state.close()
        temp.cleanup()
