from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.state import BotState
from dent_bot.ui import exam_screen


class FakeTransport:
    def __init__(self) -> None:
        self.edited: list[tuple] = []

    def answer_callback(self, callback_id: str, text: str = "") -> None:
        del callback_id, text

    def edit(self, chat_id: int, message_id: int, text: str, keyboard: dict) -> None:
        self.edited.append((chat_id, message_id, text, keyboard))


class FakeExamSite:
    def __init__(self) -> None:
        self.hub_users: list[int] = []
        self.actions: list[tuple[int, str, str]] = []

    def account(self, _user_id: int) -> dict:
        return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}

    @staticmethod
    def _view(title: str) -> dict:
        return {
            "view": {
                "kind": "hub",
                "title": title,
                "actions": [{"label": "شروع", "ref": "startAb12", "style": "primary"}],
            }
        }

    def exam_hub(self, user_id: int) -> dict:
        self.hub_users.append(user_id)
        return self._view("آزمون‌های من")

    def exam_owner_hub(self, user_id: int) -> dict:
        return self._view("مدیریت آزمون‌ها")

    def perform_exam_action(self, user_id: int, *, action_ref: str, request_id: str) -> dict:
        self.actions.append((user_id, action_ref, request_id))
        return self._view("تلاش فعال")


class ExamBotTests(unittest.TestCase):
    def test_active_assessment_never_renders_feedback(self) -> None:
        screen = exam_screen(
            {
                "view": {
                    "kind": "question",
                    "title": "میان‌ترم",
                    "mode": "assessment",
                    "attemptStatus": "active",
                    "question": {
                        "text": "کدام گزینه درست است؟",
                        "feedback": {
                            "correct": True,
                            "summary": "پاسخ درست",
                            "explanation": "راز آزمون",
                        },
                    },
                }
            },
            site_url="https://example.test",
            is_owner=False,
        )
        self.assertNotIn("پاسخ درست", screen.text)
        self.assertNotIn("راز آزمون", screen.text)

    def test_learning_feedback_and_escaped_copy_render(self) -> None:
        screen = exam_screen(
            {
                "view": {
                    "kind": "question",
                    "title": "آموزشی <نمونه>",
                    "mode": "learning",
                    "attemptStatus": "active",
                    "question": {
                        "text": "سؤال <۱>",
                        "feedback": {"correct": False, "summary": "نادرست", "explanation": "توضیح آموزشی"},
                    },
                }
            },
            site_url="https://example.test",
            is_owner=False,
        )
        self.assertIn("توضیح آموزشی", screen.text)
        self.assertIn("&lt;نمونه&gt;", screen.text)
        self.assertNotIn("<نمونه>", screen.text)

    def test_actions_are_opaque_bounded_and_bad_urls_are_dropped(self) -> None:
        screen = exam_screen(
            {
                "view": {
                    "kind": "payment",
                    "title": "خرید آزمون",
                    "actions": [
                        {"label": "پرداخت و رفتن به درگاه", "ref": "payQuote_12", "style": "success", "row": 0},
                        {"label": "نامعتبر", "ref": "contains:price:500000", "row": 1},
                        {"label": "درگاه", "url": "https://gateway.example/start/token", "row": 2},
                        {"label": "ناامن", "url": "javascript:alert(1)", "row": 3},
                    ],
                }
            },
            site_url="https://example.test",
            is_owner=False,
        )
        buttons = [button for row in screen.keyboard["inline_keyboard"] for button in row]
        callback_values = [button["callback_data"] for button in buttons if "callback_data" in button]
        self.assertIn("v1:exam-action:payQuote_12", callback_values)
        self.assertTrue(all(len(value.encode("utf-8")) <= 64 for value in callback_values))
        self.assertFalse(any("500000" in value for value in callback_values))
        urls = [button["url"] for button in buttons if "url" in button]
        self.assertIn("https://gateway.example/start/token", urls)
        self.assertFalse(any(value.startswith("javascript:") for value in urls))

    def test_renderer_is_shared_for_telegram_and_bale(self) -> None:
        payload = FakeExamSite._view("همسان")
        telegram = exam_screen(payload, site_url="https://example.test", is_owner=True)
        bale = exam_screen(payload, site_url="https://example.test", is_owner=True)
        self.assertEqual(telegram, bale)

    def test_disabled_flag_keeps_existing_site_fallback_and_does_not_call_api(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                site = FakeExamSite()
                app = DentBotApp(
                    FakeTransport(), state, owner_id=10, site_url="https://example.test",
                    site_api=site, exams_v1_enabled=False,
                )
                screen = app._dynamic_screen("exams", 20, request_id="callback")
                self.assertEqual(site.hub_users, [])
                self.assertIn("ورود به آزمون‌ها", screen.text)
            finally:
                state.close()

    def test_enabled_hub_and_action_use_site_without_local_attempt_state(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                site = FakeExamSite()
                app = DentBotApp(
                    FakeTransport(), state, owner_id=10, site_url="https://example.test",
                    site_api=site, exams_v1_enabled=True,
                )
                hub = app._dynamic_screen("exams", 20, request_id="hub-callback")
                action = app._dynamic_screen("exam-action:startAb12", 20, request_id="action-callback")
                self.assertIn("آزمون‌های من", hub.text)
                self.assertIn("تلاش فعال", action.text)
                self.assertEqual(site.hub_users, [20])
                self.assertEqual(site.actions[0][0:2], (20, "startAb12"))
                self.assertEqual(len(site.actions[0][2]), 64)
                tables = {
                    row[0] for row in state.connection.execute(
                        "SELECT name FROM sqlite_master WHERE type='table'"
                    ).fetchall()
                }
                self.assertFalse(any("exam" in name or "attempt" in name or "answer" in name for name in tables))
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
