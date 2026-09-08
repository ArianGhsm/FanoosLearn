import tempfile
import time
import unittest
from dataclasses import replace
from pathlib import Path

from fanoos_bot.activity import ActivityController, ActivitySession
from fanoos_bot.bale_presentation import BalePresentation
from fanoos_bot.botapi import BotApiError, JsonBotApiTransport
from fanoos_bot.capabilities import BALE, TELEGRAM
from fanoos_bot.chunking import chunks
from fanoos_bot.models import ActionResult, Button, Screen
from fanoos_bot.runtime import BotRuntime, UpdateContext
from fanoos_bot.telegram_presentation import TelegramPresentation


class RecordingApi(JsonBotApiTransport):
    def __init__(self, capabilities, *, rich=True, failures=None):
        super().__init__(
            "https://example.invalid",
            "token",
            capabilities,
            rich_ui_enabled=rich,
            activity_ui_enabled=False,
        )
        self.calls = []
        self.failures = list(failures or [])
        self.next_id = 10

    def _call(self, method, payload=None):
        self.calls.append((method, payload or {}))
        if self.failures:
            expected, exc = self.failures[0]
            if expected == method:
                self.failures.pop(0)
                raise exc
        self.next_id += 1
        return {"message_id": self.next_id}


class RecordingMultipartApi(RecordingApi):
    def __init__(self, capabilities, *, rich=True):
        super().__init__(capabilities, rich=rich)
        self.multipart = []

    def _multipart_call(self, method, body, boundary):
        self.multipart.append((method, body, boundary))
        return {"message_id": 99}


class MetadataScreen:
    def __init__(self, text, presentation):
        self.text = text
        self.rows = ()
        self.edit = False
        self.protect_content = False
        self.presentation = presentation


class SimpleState:
    def processed_update(self, platform, event_id):
        return None

    def delivery_receipt_capacity_available(self, key):
        return True

    def pending_delivery_receipt(self, key):
        return None

    def pending_delivery_receipts(self, limit=25):
        return []


class CountingApp:
    def __init__(self, result, events=None):
        self.backend = self
        self.result = result
        self.calls = 0
        self.events = events

    def callback(self, subject, private, value):
        self.calls += 1
        if self.events is not None:
            self.events.append("app")
        return self.result


class CallbackTransport:
    def __init__(self, events, *, ack_failure=False):
        self.events = events
        self.ack_failure = ack_failure
        self.capabilities = TELEGRAM

    def answer_callback(self, callback_id):
        self.events.append("ack")
        if self.ack_failure:
            raise BotApiError("network_unavailable", transient=True)

    def send_screen(self, chat_id, screen):
        self.events.append("send")
        return {"message_id": 9}


class DraftTransport:
    def __init__(self):
        self.capabilities = TELEGRAM
        self.activity_ui_enabled = True
        self.actions = []
        self.drafts = []

    def chat_action(self, chat_id, action="typing"):
        self.actions.append((chat_id, action))
        return True

    def send_rich_draft(self, chat_id, draft_id, text):
        self.drafts.append((chat_id, draft_id, text))
        return True


class Worker04PresentationTest(unittest.TestCase):
    def test_official_capability_matrix(self):
        self.assertTrue(TELEGRAM.supports_native_rich)
        self.assertTrue(TELEGRAM.supports_rich_edit)
        self.assertTrue(TELEGRAM.supports_rich_draft)
        self.assertTrue(TELEGRAM.supports_rtl)
        self.assertTrue(TELEGRAM.supports_forward_protection)
        self.assertEqual(TELEGRAM.max_callback_bytes, 64)
        self.assertFalse(BALE.supports_native_rich)
        self.assertFalse(BALE.supports_forward_protection)
        self.assertEqual(BALE.reply_style, "reply_to_message_id")
        self.assertEqual(BALE.max_download_bytes, 20_000_000)
        self.assertEqual(BALE.max_document_upload_bytes, 50_000_000)

    def test_baseline_screen_heuristics_make_rtl_heading_list_and_quote(self):
        rendered = TelegramPresentation(enabled=True).render(
            Screen("برنامه امروز\n\n• کلاس اول\n• کلاس دوم\n\n⚠️ زمان ممکن است تغییر کند.")
        )
        self.assertIsNotNone(rendered.rich_message)
        rich = rendered.rich_message
        self.assertTrue(rich["is_rtl"])
        self.assertIn("<h3>برنامه امروز</h3>", rich["html"])
        self.assertIn("<ul><li>کلاس اول</li><li>کلاس دوم</li></ul>", rich["html"])
        self.assertIn("<blockquote>⚠️ زمان ممکن است تغییر کند.</blockquote>", rich["html"])
        self.assertNotIn("<pre", rich["html"])

    def test_semantic_metadata_prefers_native_table(self):
        screen = MetadataScreen(
            "نمرات\nدرس: ترمیمی، نمره: ۱۸",
            {
                "blocks": [
                    {"kind": "heading", "text": "نمرات"},
                    {
                        "kind": "table",
                        "headers": ["درس", "نمره"],
                        "rows": [["ترمیمی", "۱۸"], ["پریو", "۱۷٫۵"]],
                    },
                ]
            },
        )
        rendered = TelegramPresentation(enabled=True).render(screen)
        self.assertIn("<table bordered striped compact>", rendered.rich_message["html"])
        self.assertIn("<th>درس</th>", rendered.rich_message["html"])
        self.assertNotIn("|", rendered.rich_message["html"])

    def test_malformed_metadata_fails_to_plain_without_rewriting(self):
        screen = MetadataScreen(
            "متن اصلی",
            {"blocks": [{"kind": "table", "headers": ["الف"], "rows": [["۱", "۲"]]}]},
        )
        rendered = TelegramPresentation(enabled=True).render(screen)
        self.assertEqual(rendered.plain_text, "متن اصلی")
        self.assertIsNone(rendered.rich_message)

    def test_rich_send_preserves_keyboard_rtl_and_protection(self):
        transport = RecordingApi(TELEGRAM)
        transport.send_screen(
            "42",
            Screen(
                "منابع\n\n• جزوه",
                ((Button("🏠 خانه", callback="home"),),),
                protect_content=True,
            ),
        )
        method, payload = transport.calls[0]
        self.assertEqual(method, "sendRichMessage")
        self.assertTrue(payload["rich_message"]["is_rtl"])
        self.assertTrue(payload["protect_content"])
        self.assertEqual(
            payload["reply_markup"]["inline_keyboard"][0][0]["callback_data"],
            "home",
        )

    def test_rich_400_falls_back_once_to_plain(self):
        transport = RecordingApi(
            TELEGRAM,
            failures=[("sendRichMessage", BotApiError("400"))],
        )
        transport.send_screen("42", Screen("خانه\n\nمتن"))
        self.assertEqual([method for method, _ in transport.calls], ["sendRichMessage", "sendMessage"])

    def test_rich_429_with_retry_after_is_bounded_plain_fallback(self):
        transport = RecordingApi(
            TELEGRAM,
            failures=[
                (
                    "sendRichMessage",
                    BotApiError("429", retry_after=2, transient=True),
                )
            ],
        )
        transport.send_screen("42", Screen("خانه\n\nمتن"))
        self.assertEqual(len(transport.calls), 2)
        self.assertEqual(transport.calls[-1][0], "sendMessage")

    def test_rich_timeout_falls_back_without_business_replay(self):
        transport = RecordingApi(
            TELEGRAM,
            failures=[
                (
                    "sendRichMessage",
                    BotApiError("network_unavailable", transient=True),
                )
            ],
        )
        app = CountingApp(ActionResult(Screen("خانه\n\nمتن")))
        runtime = BotRuntime(
            "telegram",
            transport,
            app,
            SimpleState(),
            activity=ActivityController(transport, enabled=False),
        )
        runtime.handle_callback(
            UpdateContext("1", "1", True, 1, None, "u-1"),
            "home",
        )
        self.assertEqual(app.calls, 1)
        self.assertEqual(
            [method for method, _ in transport.calls],
            ["sendRichMessage", "sendMessage"],
        )

    def test_edit_failure_falls_back_to_send_without_action_replay(self):
        transport = RecordingApi(
            TELEGRAM,
            failures=[
                ("editMessageText", BotApiError("400")),
                ("editMessageText", BotApiError("400")),
            ],
        )
        app = CountingApp(ActionResult(Screen("خانه\n\nمتن", edit=True)))
        runtime = BotRuntime(
            "telegram",
            transport,
            app,
            SimpleState(),
            activity=ActivityController(transport, enabled=False),
        )
        runtime.handle_callback(
            UpdateContext("1", "1", True, 77, None, "u-2"),
            "home",
        )
        self.assertEqual(app.calls, 1)
        self.assertEqual(
            [method for method, _ in transport.calls],
            ["editMessageText", "editMessageText", "sendRichMessage"],
        )

    def test_feature_flag_disables_rich_without_changing_callbacks(self):
        transport = RecordingApi(TELEGRAM, rich=False)
        transport.send_screen(
            "42",
            Screen("خانه", ((Button("🏠 خانه", callback="home"),),)),
        )
        method, payload = transport.calls[0]
        self.assertEqual(method, "sendMessage")
        self.assertEqual(
            payload["reply_markup"]["inline_keyboard"][0][0]["callback_data"],
            "home",
        )

    def test_rich_draft_uses_thinking_rtl_and_cannot_stop(self):
        transport = RecordingApi(TELEGRAM)
        transport.activity_ui_enabled = True
        self.assertTrue(transport.send_rich_draft("42", 7, "در حال پردازش…"))
        method, payload = transport.calls[0]
        self.assertEqual(method, "sendRichMessageDraft")
        self.assertEqual(payload["draft_id"], 7)
        self.assertFalse(payload["can_stop"])
        self.assertTrue(payload["rich_message"]["is_rtl"])
        self.assertIn("<tg-thinking>", payload["rich_message"]["html"])
        self.assertNotIn("%", payload["rich_message"]["html"])

    def test_bale_never_emits_telegram_rich_or_reply_parameters(self):
        transport = RecordingApi(BALE)
        transport.send_screen(
            "42",
            Screen("سلام", ((Button("🏠 خانه", callback="home"),),)),
            reply_to=7,
        )
        method, payload = transport.calls[0]
        self.assertEqual(method, "sendMessage")
        self.assertNotIn("rich_message", payload)
        self.assertNotIn("reply_parameters", payload)
        self.assertEqual(payload["reply_to_message_id"], 7)
        self.assertIn("inline_keyboard", payload["reply_markup"])

    def test_bale_forward_protection_is_fail_closed_before_network(self):
        transport = RecordingApi(BALE)
        with self.assertRaisesRegex(BotApiError, "forward_protection_unsupported"):
            transport.send_screen("42", Screen("محافظت‌شده", protect_content=True))
        self.assertEqual(transport.calls, [])

    def test_bale_edit_failure_falls_back_to_new_plain_send(self):
        transport = RecordingApi(
            BALE,
            failures=[("editMessageText", BotApiError("400"))],
        )
        transport.edit_screen("42", 9, Screen("خانه", edit=True))
        self.assertEqual(
            [method for method, _ in transport.calls],
            ["editMessageText", "sendMessage"],
        )

    def test_bale_unsupported_keyboard_fails_explicitly(self):
        capability = replace(BALE, supports_inline_callback=False)
        transport = RecordingApi(capability)
        with self.assertRaisesRegex(BotApiError, "inline_keyboard_unsupported"):
            transport.send_screen(
                "42",
                Screen("خانه", ((Button("خانه", callback="home"),),)),
            )
        self.assertEqual(transport.calls, [])

    def test_same_screen_preserves_callback_action_on_both_platforms(self):
        screen = Screen(
            "خانه",
            ((Button("📅 برنامه", callback="schedule"), Button("سایت", url="https://example.com")),),
        )
        telegram = RecordingApi(TELEGRAM)
        bale = RecordingApi(BALE)
        telegram.send_screen("42", screen)
        bale.send_screen("42", screen)
        tg_markup = telegram.calls[0][1]["reply_markup"]["inline_keyboard"]
        bale_markup = bale.calls[0][1]["reply_markup"]["inline_keyboard"]
        self.assertEqual(tg_markup, bale_markup)
        self.assertEqual(tg_markup[0][0]["callback_data"], "schedule")

    def test_bale_plain_renderer_preserves_unicode_and_order(self):
        text = "نمرات 🎓\n\n• مورد اول\n• مورد دوم"
        self.assertEqual(BalePresentation().render(Screen(text)).text, text)

    def test_bale_semantic_table_becomes_readable_bullets_not_ascii_grid(self):
        screen = MetadataScreen(
            "fallback",
            {
                "blocks": [
                    {"kind": "heading", "text": "نمرات"},
                    {
                        "kind": "table",
                        "headers": ["درس", "نمره"],
                        "rows": [["ترمیمی", "۱۸"]],
                    },
                ]
            },
        )
        rendered = BalePresentation().render(screen).text
        self.assertIn("• درس: ترمیمی · نمره: ۱۸", rendered)
        self.assertNotIn("|", rendered)
        self.assertNotIn("+---", rendered)

    def test_callback_ack_is_before_application_and_failure_is_best_effort(self):
        for ack_failure in (False, True):
            events = []
            transport = CallbackTransport(events, ack_failure=ack_failure)
            app = CountingApp(ActionResult(Screen("done")), events)
            runtime = BotRuntime(
                "telegram",
                transport,
                app,
                SimpleState(),
                activity=ActivityController(transport, enabled=False),
            )
            runtime.handle_callback(
                UpdateContext("1", "1", True, 1, "cb", "u-3"),
                "home",
            )
            self.assertEqual(events[:2], ["ack", "app"])
            self.assertEqual(app.calls, 1)

    def test_unknown_total_activity_uses_one_stable_thinking_draft(self):
        transport = DraftTransport()
        session = ActivitySession(
            transport,
            "42",
            private=True,
            start_after=0.01,
            heartbeat=1,
        )
        session.__enter__()
        time.sleep(0.04)
        session.close()
        self.assertGreaterEqual(len(transport.actions), 1)
        self.assertEqual(len(transport.drafts), 1)
        self.assertNotIn("%", transport.drafts[0][2])
        self.assertNotIn("ETA", transport.drafts[0][2])

    def test_unicode_chunking_does_not_split_combining_character(self):
        text = ("سلام🙂e\u0301" * 30)
        parts = chunks(text, 64)
        self.assertEqual("".join(parts), text)
        self.assertTrue(all(len(part) <= 64 for part in parts))
        self.assertTrue(all(not part.startswith("\u0301") for part in parts[1:]))

    def test_callback_limit_is_utf8_bytes(self):
        transport = RecordingApi(TELEGRAM)
        with self.assertRaises(ValueError):
            transport.send_screen(
                "42",
                Screen(
                    "x",
                    ((Button("bad", callback="home:" + ("x" * 65)),),),
                ),
            )

    def test_telegram_document_keeps_protection_and_hides_temp_path(self):
        transport = RecordingMultipartApi(TELEGRAM)
        with tempfile.TemporaryDirectory() as root:
            path = Path(root) / "جزوه محرمانه.pdf"
            path.write_bytes(b"%PDF-fixture")
            transport.send_document(
                "42",
                path,
                caption="🔒 fixture",
                protect_content=True,
            )
        method, body, _ = transport.multipart[0]
        self.assertEqual(method, "sendDocument")
        decoded = body.decode("utf-8", errors="ignore")
        self.assertIn('name="protect_content"', decoded)
        self.assertIn("true", decoded)
        self.assertNotIn(root, decoded)
        self.assertNotIn("جزوه محرمانه", decoded)

    def test_document_caption_limit_fails_before_network(self):
        transport = RecordingMultipartApi(TELEGRAM)
        with tempfile.TemporaryDirectory() as root:
            path = Path(root) / "a.pdf"
            path.write_bytes(b"%PDF-fixture")
            with self.assertRaisesRegex(BotApiError, "caption_too_large"):
                transport.send_document("42", path, caption="x" * 1025)
        self.assertEqual(transport.multipart, [])

    def test_bale_file_size_is_checked_before_transport(self):
        tiny = replace(BALE, max_document_upload_bytes=3)
        transport = RecordingApi(tiny)
        with tempfile.TemporaryDirectory() as root:
            path = Path(root) / "secret-name.pdf"
            path.write_bytes(b"1234")
            with self.assertRaisesRegex(BotApiError, "file_too_large"):
                transport.send_document("42", path)
        self.assertEqual(transport.calls, [])


if __name__ == "__main__":
    unittest.main()
