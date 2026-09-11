from __future__ import annotations

import re
import unittest
from unittest.mock import patch

from dent_bot.api import BaleBotApi, BotApiError, TelegramBotApi
from dent_bot.class_operations import (
    _ai_draft_screen,
    _class_home_screen,
    _compose_prompt,
    _detail_screen,
    _digest_screen,
    _home_keyboard,
    _legacy_action,
    _list_screen,
    _preview_screen,
    _render_screen,
)
from dent_bot.ui import Screen, button, keyboard


class _App:
    site_url = "https://example.test"
    platform = "telegram"


class _CaptureApi:
    def __init__(self, *, edit_error: Exception | None = None) -> None:
        self.sent = []
        self.edited = []
        self.edit_error = edit_error

    def send(self, chat_id, text, reply_markup):
        self.sent.append((chat_id, text, reply_markup))
        return {"message_id": 10}

    def edit(self, chat_id, message_id, text, reply_markup):
        self.edited.append((chat_id, message_id, text, reply_markup))
        if self.edit_error is not None:
            raise self.edit_error
        return {"message_id": message_id}


class _RenderApp:
    platform = "telegram"

    def __init__(self, *, edit_error: Exception | None = None) -> None:
        self.api = _CaptureApi(edit_error=edit_error)


class ClassOperationsProductTests(unittest.TestCase):
    def _sample_item(self) -> dict:
        return {
            "id": "cop_123456789012345678901234",
            "type": "task",
            "status": "active",
            "title": "تحویل تمرین 2",
            "description": "شرح طولانی تمرین 3",
            "courseTitle": "ترمیمی 1",
            "timing": {"dueAt": "2026-09-08T16:30:00+03:30"},
            "location": "کلاس 4",
            "importance": "important",
            "task": {"state": "pending"},
        }

    def test_main_menu_uses_distinct_class_operations_semantic_icon(self) -> None:
        original = Screen(
            "<b>خانه</b>",
            keyboard(
                [button("📚 جزوات", action="notes")],
                [button("📝 آزمون‌ها", action="exams")],
                [button("🔔 اعلان‌ها", action="notifications"), button("🛟 راهنما", action="help")],
            ),
        )
        updated = _home_keyboard(original)
        buttons = [item for row in updated.keyboard["inline_keyboard"] for item in row]
        labels = [str(item.get("text") or "") for item in buttons]
        callbacks = [str(item.get("callback_data") or "") for item in buttons]
        self.assertIn("📅 امور کلاس", labels)
        self.assertNotIn("📚 امور کلاس", labels)
        self.assertIn("📚 جزوات", labels)
        self.assertIn("v1:class-operations", callbacks)
        self.assertLess(callbacks.index("v1:class-operations"), callbacks.index("v1:notifications"))

    def test_student_class_home_matches_compact_core_bot_hub(self) -> None:
        screen = _class_home_screen(
            _App(),
            role="student",
            items=[{"type": "task", "status": "active", "title": "تحویل تمرین"}],
        )
        rendered = str(screen.text) + str(screen.keyboard)
        self.assertIn("📅 امور کلاس", rendered)
        self.assertIn("تکالیف و کارها", rendered)
        self.assertIn("۱ کار", rendered)
        self.assertFalse(bool(getattr(screen.text, "rich_html", "")))
        self.assertNotIn("<table", str(screen.text))
        self.assertNotIn("canonical", rendered.lower())
        self.assertNotIn("ClassOps", rendered)
        self.assertNotIn("مرکز عملیات وب", rendered)

    def test_owner_class_home_has_management_entry(self) -> None:
        screen = _class_home_screen(_App(), role="owner", items=[])
        self.assertIn("مدیریت امور کلاس", str(screen.keyboard))

    def test_compose_flow_is_button_led_not_formatted_command_led(self) -> None:
        title = _compose_prompt("announcement")
        body = _compose_prompt("announcement-description")
        ai = _compose_prompt("ai")
        combined = str(title.text) + str(body.text) + str(ai.text)
        self.assertIn("عنوان کوتاه اطلاعیه", combined)
        self.assertIn("متن اطلاعیه را بفرست", combined)
        self.assertIn("متن خام اطلاعیه", combined)
        self.assertNotIn("/classops draft", combined)
        self.assertNotIn("/classops ai", combined)
        for screen in (title, body, ai):
            self.assertIn("انصراف", str(screen.keyboard))

    def test_legacy_classops_callback_opens_new_presentation(self) -> None:
        self.assertEqual(_legacy_action("classops:menu"), "class-operations")
        self.assertEqual(_legacy_action("classops:items"), "class-operations:list:all")
        self.assertEqual(_legacy_action("classops:tomorrow"), "class-operations:tomorrow")
        self.assertEqual(_legacy_action("classops:item:cop_123"), "class-operations:item:cop_123")

    def test_list_is_native_rich_and_translates_types_time_and_digits(self) -> None:
        screen = _list_screen(
            [{
                "id": "cop_123456789012345678901234",
                "type": "class_change",
                "status": "active",
                "title": "جابجایی ترمیمی 2",
                "timing": {"startsAt": "2026-09-08T10:00:00+03:30"},
            }],
            "schedule",
        )
        rich = screen.text.rich_html
        combined = str(screen.text) + rich
        self.assertIn("تغییر کلاس", combined)
        self.assertNotIn("class_change", combined)
        self.assertIn("<table bordered striped compact>", rich)
        self.assertNotIn("<pre>", rich)
        self.assertNotIn("2026-09-08", combined)
        self.assertNotRegex(combined, r"(?<![A-Za-z])\b2\b")
        self.assertIn("۲", combined)
        self.assertIn("۱۴۰۵", combined)

    def test_student_detail_uses_rich_facts_details_and_ack(self) -> None:
        screen = _detail_screen(
            {
                "id": "cop_123456789012345678901234",
                "type": "critical_notice",
                "title": "اطلاعیه مهم 2",
                "status": "active",
                "description": "متن توضیح 3",
                "timing": {"startsAt": "2026-09-08T08:00:00+03:30"},
                "ack": {"acked": False},
            },
            {"ack": "cxo_12345678901234567890"},
        )
        button_rows = screen.keyboard["inline_keyboard"]
        ack_buttons = [item for row in button_rows for item in row if "دیدم و تأیید می‌کنم" in str(item.get("text") or "")]
        combined = str(screen.text) + screen.text.rich_html
        self.assertIn("نیازمند تأیید", combined)
        self.assertEqual(len(ack_buttons), 1)
        self.assertEqual(ack_buttons[0].get("style"), "success")
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertIn("<details>", screen.text.rich_html)
        self.assertIn("<blockquote expandable>", str(screen.text))
        self.assertNotIn("revision", combined.lower())
        self.assertNotIn("2026-09-08", combined)
        self.assertIn("۱۴۰۵", combined)
        self.assertIn("۲", combined)
        self.assertIn("۳", combined)

    def test_saba_copy_preserves_reminder_only_boundary(self) -> None:
        screen = _detail_screen(
            {
                "id": "cop_123456789012345678901234",
                "type": "service_reminder",
                "title": "ثبت صبا",
                "status": "active",
                "service": {"state": {"state": "pending"}},
            },
            {},
        )
        combined = str(screen.text) + screen.text.rich_html
        self.assertIn("انجام واقعی در صبا را تأیید نمی‌کند", combined)
        self.assertNotIn("password", combined.lower())
        self.assertNotIn("session", combined.lower())

    def test_preview_is_native_rich_persian_and_hides_internal_values(self) -> None:
        screen = _preview_screen(
            _App(),
            {
                "preview": {
                    "item": {
                        "type": "announcement",
                        "title": "اطلاعیه 12",
                        "description": "متن 4",
                        "timing": {"startsAt": "2026-09-08T10:00:00+03:30"},
                    },
                    "audience": {"total": 12, "resolutionHash": "secret-ish-hash"},
                    "destinations": [{"bindingRef": "private_users"}],
                },
                "confirmToken": "cxo_12345678901234567890",
            },
        )
        combined = str(screen.text) + screen.text.rich_html + str(screen.keyboard)
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertIn("۱۲", combined)
        self.assertIn("۴", combined)
        self.assertIn("۱۴۰۵", combined)
        self.assertNotIn("2026-09-08", combined)
        self.assertNotIn("secret-ish-hash", combined)
        self.assertNotIn("revision", combined.lower())
        self.assertNotIn("audience", combined.lower())
        self.assertNotIn("private_users", combined)
        self.assertNotIn("cxo_12345678901234567890", str(screen.text) + screen.text.rich_html)

    def test_ai_draft_is_native_rich_and_stays_preview_only(self) -> None:
        screen = _ai_draft_screen(
            _App(),
            {
                "draft": {
                    "fields": {"type": "announcement", "title": "اطلاعیه 2", "description": "متن 3"},
                    "unresolved": ["زمان 4"],
                }
            },
        )
        combined = str(screen.text) + screen.text.rich_html
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertIn("بدون تأیید تو چیزی ثبت یا ارسال نمی‌شود", combined)
        self.assertIn("۲", combined)
        self.assertIn("۳", combined)
        self.assertIn("۴", combined)
        self.assertNotIn("provider", combined.lower())
        self.assertNotIn("token", combined.lower())

    def test_tomorrow_and_weekly_digest_renderer_preserves_sections_and_budget(self) -> None:
        response = {
            "digest": {
                "sections": [
                    {
                        "key": "tasks_requirements",
                        "label": "تکلیف‌ها و الزامات باز",
                        "omitted": 1,
                        "items": [{
                            "itemType": "task",
                            "title": "تمرین 2",
                            "course": {"title": "ترمیمی 1"},
                            "location": "کلاس 4",
                            "timing": {"allDay": False, "dueAtUtc": "2026-09-09T16:30:00Z"},
                            "changeLabel": None,
                        }],
                    }
                ],
                "budget": {"truncated": True, "omittedItems": 1},
            }
        }
        for title in ("🌤 فردا", "🗓 هفته پیش رو"):
            with self.subTest(title=title):
                screen = _digest_screen(response, title=title)
                combined = str(screen.text) + screen.text.rich_html
                self.assertIn("<table bordered striped compact>", screen.text.rich_html)
                self.assertIn("تکلیف‌ها و الزامات باز", combined)
                self.assertIn("۱ مورد دیگر", combined)
                self.assertNotIn("2026-09-09", combined)
                self.assertIn("۱۴۰۵", combined)
                self.assertNotIn("<pre>", screen.text.rich_html)

    def test_all_callbacks_edit_the_same_message_even_when_target_is_rich(self) -> None:
        rich_screen = _list_screen([self._sample_item()], "tasks")
        regular_screen = _class_home_screen(_App(), role="student", items=[self._sample_item()])

        app = _RenderApp()
        _render_screen(app, 10, rich_screen, callback={"message": {"message_id": 7, "text": "menu"}})
        self.assertEqual(len(app.api.sent), 0)
        self.assertEqual(len(app.api.edited), 1)
        self.assertEqual(app.api.edited[0][1], 7)

        app2 = _RenderApp()
        _render_screen(app2, 10, rich_screen, callback={"message": {"message_id": 8, "rich_message": {"html": "<h2>old</h2>"}}})
        self.assertEqual(len(app2.api.sent), 0)
        self.assertEqual(len(app2.api.edited), 1)
        self.assertEqual(app2.api.edited[0][1], 8)

        app3 = _RenderApp()
        _render_screen(app3, 10, regular_screen, callback={"message": {"message_id": 9, "rich_message": {"html": "<h2>old</h2>"}}})
        self.assertEqual(len(app3.api.sent), 0)
        self.assertEqual(len(app3.api.edited), 1)
        self.assertEqual(app3.api.edited[0][1], 9)

    def test_new_message_is_only_fallback_for_truly_uneditable_message(self) -> None:
        screen = _list_screen([self._sample_item()], "tasks")
        app = _RenderApp(edit_error=BotApiError("Bad Request: message can't be edited"))
        _render_screen(app, 10, screen, callback={"message": {"message_id": 7, "text": "menu"}})
        self.assertEqual(len(app.api.edited), 1)
        self.assertEqual(len(app.api.sent), 1)

        fatal = _RenderApp(edit_error=BotApiError("Bad Request: malformed rich html"))
        with self.assertRaises(BotApiError):
            _render_screen(fatal, 10, screen, callback={"message": {"message_id": 7, "text": "menu"}})
        self.assertEqual(len(fatal.api.sent), 0)

    def test_native_telegram_transport_uses_rtl_rich_message(self) -> None:
        screen = _list_screen([self._sample_item()], "tasks")
        api = TelegramBotApi("123:test", api_root="https://example.test")
        try:
            with patch.object(api, "call", return_value={"message_id": 1}) as call:
                api.send(10, screen.text, screen.keyboard)
                method, payload = call.call_args.args[:2]
                self.assertEqual(method, "sendRichMessage")
                self.assertIs(payload["rich_message"]["is_rtl"], True)
                self.assertIn("<table bordered striped compact>", payload["rich_message"]["html"])
        finally:
            api.close()

    def test_malformed_native_rich_is_not_silently_degraded(self) -> None:
        screen = _list_screen([self._sample_item()], "tasks")
        api = TelegramBotApi("123:test", api_root="https://example.test")
        try:
            with patch.object(api, "call", side_effect=BotApiError("Bad Request: malformed rich html")):
                with self.assertRaises(BotApiError):
                    api.send(10, screen.text, screen.keyboard)
        finally:
            api.close()

    def test_bale_fallback_is_readable_and_contains_no_raw_telegram_html(self) -> None:
        screen = _detail_screen(self._sample_item(), {})
        api = BaleBotApi("123:test")
        try:
            with patch.object(api, "call", return_value={"message_id": 1}) as call:
                api.send(10, screen.text, screen.keyboard)
                method, payload = call.call_args.args[:2]
                self.assertEqual(method, "sendMessage")
                rendered = str(payload.get("text") or "")
                self.assertNotRegex(rendered, re.compile(r"</?(?:b|i|code|pre|blockquote|table|tr|td|th|details)\b", re.I))
                self.assertNotIn("parse_mode", payload)
                self.assertIn("۱۴۰۵", rendered)
                self.assertIn("۲", rendered)
        finally:
            api.close()

    def test_copy_guard_for_production_class_operations_surfaces(self) -> None:
        screens = [
            _class_home_screen(_App(), role="student", items=[self._sample_item()]),
            _list_screen([self._sample_item()], "all"),
            _detail_screen(self._sample_item(), {}),
            _preview_screen(_App(), {"preview": {"item": self._sample_item(), "audience": {"total": 2}, "destinations": []}}),
        ]
        forbidden = ("ClassOps", "canonical", "audience hash", "delivery intent", "capability enum")
        iso_pattern = re.compile(r"\b20\d{2}-\d{2}-\d{2}T")
        for screen in screens:
            combined = str(screen.text) + str(getattr(screen.text, "rich_html", ""))
            for needle in forbidden:
                self.assertNotIn(needle.lower(), combined.lower())
            self.assertIsNone(iso_pattern.search(combined))


if __name__ == "__main__":
    unittest.main()
