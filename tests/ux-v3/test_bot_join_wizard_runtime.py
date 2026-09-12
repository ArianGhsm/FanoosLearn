from __future__ import annotations

import importlib.util
import tempfile
import unittest
from pathlib import Path

from fanoos_bot.activity import ActivityController
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.botapi import JsonBotApiTransport
from fanoos_bot.capabilities import BALE, TELEGRAM
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.runtime import BotRuntime, UpdateContext
from fanoos_bot.state import LocalState

ROOT = Path(__file__).resolve().parents[2]


def _load_bot_runtime_module(app_dir: str, module_name: str):
    spec = importlib.util.spec_from_file_location(module_name, ROOT / "apps" / app_dir / "runtime.py")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class MinimalDirectoryBackend:
    """Just enough of the onboarding contract for the wizard to reach the
    contact step, plus workspaces() for the eventual home() handoff."""

    def directory_provinces(self, platform, limit=10, cursor=None):
        return {"items": [{"id": "prov-1", "name": "تهران"}], "next_cursor": None}

    def directory_institutions(self, platform, province_id, limit=10, cursor=None):
        return {"items": [{"id": "inst-1", "name": "دانشگاه تهران", "institution_type": "state"}], "next_cursor": None}

    def directory_faculties(self, platform, institution_id, limit=10, cursor=None):
        return {"items": [{"id": "fac-1", "name": "دانشکده فنی"}], "next_cursor": None}

    def directory_programs(self, platform, faculty_id, limit=10, cursor=None):
        return {"items": [{"id": "prog-1", "name": "مهندسی کامپیوتر"}], "next_cursor": None}

    def onboarding_otp_request(self, platform, subject, phone_number):
        return {"phone_masked": "0912***4321", "challenge_token": "chal-1"}

    def workspaces(self, platform, subject):
        return {"workspaces": [], "selected_workspace_id": None}


class RecordingTransport(JsonBotApiTransport):
    def __init__(self, capabilities):
        super().__init__("https://example.invalid", "token", capabilities)
        self.calls: list[tuple[str, dict]] = []

    def _call(self, method, payload=None):
        self.calls.append((method, dict(payload or {})))
        return {"message_id": len(self.calls)}


class ReplyKeyboardRuntimeTest(unittest.TestCase):
    """The join wizard is the one flow the owner asked to keep on native
    reply keyboards; this pins the runtime plumbing (packages/python/fanoos_bot/runtime.py's
    _send_raw_wizard_result) that makes botapi actually emit and later clear
    one, for both platforms."""

    def _runtime(self, capabilities):
        tmp = tempfile.TemporaryDirectory()
        state = LocalState(Path(tmp.name) / "state.sqlite3")
        backend = MinimalDirectoryBackend()
        app = BotApplication(backend, state, capabilities.platform, ApplicationConfig("https://fanoos.test/"))
        transport = RecordingTransport(capabilities)
        runtime = BotRuntime(
            capabilities.platform, transport, app, state, activity=ActivityController(transport, enabled=False)
        )
        return tmp, state, runtime, transport

    def test_telegram_join_sends_rich_reply_keyboard(self):
        tmp, state, runtime, transport = self._runtime(TELEGRAM)
        try:
            runtime.handle_message(UpdateContext("student", "42", True), "/join")
            method, payload = transport.calls[0]
            self.assertEqual(method, "sendRichMessage")
            self.assertIn("keyboard", payload["reply_markup"])
            self.assertTrue(payload["reply_markup"]["resize_keyboard"])
            self.assertNotIn("inline_keyboard", payload["reply_markup"])
        finally:
            state.close()
            tmp.cleanup()

    def test_bale_join_sends_plain_text_reply_keyboard_not_html(self):
        tmp, state, runtime, transport = self._runtime(BALE)
        try:
            runtime.handle_message(UpdateContext("student", "42", True), "/join")
            method, payload = transport.calls[0]
            self.assertEqual(method, "sendMessage")
            self.assertNotIn("<b>", payload["text"])
            self.assertIn("*", payload["text"])  # bale bold convention
            self.assertIn("keyboard", payload["reply_markup"])
        finally:
            state.close()
            tmp.cleanup()

    def test_cancel_clears_the_reply_keyboard_via_remove_reply_keyboard(self):
        tmp, state, runtime, transport = self._runtime(TELEGRAM)
        try:
            runtime.handle_message(UpdateContext("student", "42", True), "/join")
            runtime.handle_message(UpdateContext("student", "42", True), "انصراف")
            methods = [method for method, _ in transport.calls]
            # sendRichMessage (the step screen), then a sendMessage carrying
            # remove_keyboard, then the delete of that transport-only message.
            self.assertIn("sendMessage", methods)
            remove_call = next(payload for method, payload in transport.calls if method == "sendMessage")
            self.assertTrue(remove_call["reply_markup"]["remove_keyboard"])
            self.assertIn("deleteMessage", methods)
        finally:
            state.close()
            tmp.cleanup()


class LegacyShellJoinButtonTest(unittest.TestCase):
    """The legacy shell gives the join wizard a real inline-keyboard button
    (docs/product/01_FRONT_DOOR.md #2/#3) instead of leaving it behind the
    /join text command. An inline callback_query result is structurally
    different from a text message, so this pins that handle_callback (not
    just handle_message) also recognizes a RawKeyboardSend/RawKeyboardHandoff
    and routes it through _deliver_raw_wizard_result, on
    integrated_application.BotApplication -- the class the runtime actually
    constructs."""

    def _runtime(self):
        tmp = tempfile.TemporaryDirectory()
        state = LocalState(Path(tmp.name) / "state.sqlite3")
        backend = MinimalDirectoryBackend()
        app = IntegratedBotApplication(backend, state, TELEGRAM.platform, ApplicationConfig("https://fanoos.test/"))
        transport = RecordingTransport(TELEGRAM)
        runtime = BotRuntime(TELEGRAM.platform, transport, app, state, activity=ActivityController(transport, enabled=False))
        return tmp, state, runtime, transport

    def test_join_button_callback_sends_reply_keyboard_like_slash_join(self):
        tmp, state, runtime, transport = self._runtime()
        try:
            runtime.handle_callback(UpdateContext("student", "42", True), "core.join.begin")
            methods = [method for method, _ in transport.calls]
            self.assertIn("sendRichMessage", methods)
            method, payload = next((m, p) for m, p in transport.calls if m == "sendRichMessage")
            self.assertIn("keyboard", payload["reply_markup"])
            self.assertNotIn("inline_keyboard", payload["reply_markup"])
        finally:
            state.close()
            tmp.cleanup()


class ContactSpoofingGuardTest(unittest.TestCase):
    """apps/telegram-bot/runtime.py and apps/bale-bot/runtime.py both accept
    a raw Bot-API `message.contact` payload and must only trust it as proof
    of *the sender's own* phone number when the contact card's own user_id
    matches the sender -- otherwise a message can carry an arbitrary
    forwarded contact and impersonate someone else's phone at the OTP step."""

    def test_telegram_runtime_accepts_own_contact_and_rejects_forwarded_one(self):
        module = _load_bot_runtime_module("telegram-bot", "telegram_bot_runtime_under_test")
        own_update = {
            "update_id": 1,
            "message": {
                "message_id": 5,
                "chat": {"id": 42, "type": "private"},
                "from": {"id": 42},
                "contact": {"phone_number": "09121234567", "user_id": 42},
            },
        }
        ctx, text, callback, contact_phone = module.context(own_update)
        self.assertEqual(contact_phone, "09121234567")

        forwarded_update = {
            "update_id": 2,
            "message": {
                "message_id": 6,
                "chat": {"id": 42, "type": "private"},
                "from": {"id": 42},
                "contact": {"phone_number": "09120000000", "user_id": 999},
            },
        }
        ctx2, text2, callback2, contact_phone2 = module.context(forwarded_update)
        self.assertIsNone(contact_phone2)

    def test_bale_runtime_accepts_own_contact_and_rejects_forwarded_one(self):
        module = _load_bot_runtime_module("bale-bot", "bale_bot_runtime_under_test")
        own_update = {
            "update_id": 1,
            "message": {
                "message_id": 5,
                "chat": {"id": 42, "type": "private"},
                "from": {"id": 42},
                "contact": {"phone_number": "09121234567", "user_id": 42},
            },
        }
        ctx, text, callback, contact_phone = module.context(own_update)
        self.assertEqual(contact_phone, "09121234567")

        forwarded_update = {
            "update_id": 2,
            "message": {
                "message_id": 6,
                "chat": {"id": 42, "type": "private"},
                "from": {"id": 42},
                "contact": {"phone_number": "09120000000", "user_id": 999},
            },
        }
        ctx2, text2, callback2, contact_phone2 = module.context(forwarded_update)
        self.assertIsNone(contact_phone2)

    def test_telegram_handle_update_dispatches_contact_before_text(self):
        module = _load_bot_runtime_module("telegram-bot", "telegram_bot_runtime_dispatch_under_test")

        class RecordingRuntime:
            def __init__(self):
                self.calls = []

            def handle_contact(self, ctx, phone):
                self.calls.append(("contact", phone))

            def handle_message(self, ctx, text):
                self.calls.append(("message", text))

            def handle_callback(self, ctx, value):
                self.calls.append(("callback", value))

        runtime = RecordingRuntime()
        update = {
            "update_id": 1,
            "message": {
                "message_id": 5,
                "chat": {"id": 42, "type": "private"},
                "from": {"id": 42},
                "text": "should be ignored",
                "contact": {"phone_number": "09121234567", "user_id": 42},
            },
        }
        module._handle_update(runtime, update)
        self.assertEqual(runtime.calls, [("contact", "09121234567")])


if __name__ == "__main__":
    unittest.main()
