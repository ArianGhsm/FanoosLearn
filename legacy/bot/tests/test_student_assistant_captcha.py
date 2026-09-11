from __future__ import annotations

import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.state import BotState
from dent_bot.student_assistant import (
    challenge_expired,
    clean_captcha_answer,
    send_private_challenge,
)
from dent_bot.ui import home, student_assistant_screen


PNG_HEADER_DATA_URI = "data:image/png;base64,iVBORw0KGgo="
CHALLENGE_REF = "challenge_1234567890"
JOB_REF = "integration_job_123456"


def future_iso() -> str:
    return (datetime.now(timezone.utc) + timedelta(minutes=10)).isoformat()


def challenge_payload(*, ref: str = CHALLENGE_REF) -> dict:
    return {
        "success": True,
        "status": "challenge",
        "jobRef": JOB_REF,
        "connector": "food",
        "challenge": {
            "ref": ref,
            "connector": "food",
            "imageDataUri": PNG_HEADER_DATA_URI,
            "expiresAt": future_iso(),
        },
        "view": {"title": "رزرو غذا", "statusText": "در انتظار تصویر"},
    }


class FakeApi:
    def __init__(self) -> None:
        self.sent = []
        self.photos = []

    def send(self, chat_id, text, reply_markup):
        self.sent.append((chat_id, text, reply_markup))
        return {"message_id": 200 + len(self.sent)}

    def send_photo_bytes(self, chat_id, photo, *, caption, reply_markup, filename="captcha.png"):
        self.photos.append((chat_id, photo, caption, reply_markup, filename))
        return {"message_id": 77 + len(self.photos)}


class StudentAssistantCaptchaTests(unittest.TestCase):
    def test_answer_is_bounded_alphanumeric_and_normalizes_persian_digits(self) -> None:
        self.assertEqual(clean_captcha_answer(" A۷k ۲P "), "A7k2P")
        self.assertEqual(clean_captcha_answer("abc"), "")
        self.assertEqual(clean_captcha_answer("AB-12"), "")
        self.assertEqual(clean_captcha_answer("A" * 13), "")

    def test_expiry_is_timezone_aware_and_fails_closed(self) -> None:
        now = datetime(2026, 8, 25, 12, 0, tzinfo=timezone.utc)
        self.assertFalse(challenge_expired("2026-08-25T12:01:00+00:00", now=now))
        self.assertTrue(challenge_expired("2026-08-25T11:59:00+00:00", now=now))
        self.assertTrue(challenge_expired("not-a-date", now=now))

    def test_private_challenge_stores_only_binding_and_deduplicates_same_ref(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "state.sqlite3"
            state = BotState(path)
            api = FakeApi()
            try:
                first = send_private_challenge(api=api, state=state, user_id=20, payload=challenge_payload())
                second = send_private_challenge(api=api, state=state, user_id=20, payload=challenge_payload())
                self.assertFalse(first["already_sent"])
                self.assertTrue(second["already_sent"])
                self.assertEqual(len(api.photos), 1)
                self.assertEqual(api.photos[0][0], 20)
                self.assertIn("Reply", api.photos[0][2])
                binding = state.integration_challenge(20)
                self.assertEqual(binding["message_id"], 78)
                columns = {
                    row[1] for row in state.connection.execute("PRAGMA table_info(integration_captcha_challenges)")
                }
                self.assertNotIn("answer", columns)
                self.assertNotIn("image", columns)
                raw_values = " ".join(str(value) for value in state.connection.execute(
                    "SELECT * FROM integration_captcha_challenges"
                ).fetchone())
                self.assertNotIn("iVBOR", raw_values)
            finally:
                state.close()

    def test_exact_reply_is_consumed_once_and_resumes_same_job(self) -> None:
        class Site:
            def __init__(self) -> None:
                self.answers = []

            def account(self, _user_id):
                return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}

            def integration_challenge_answer(self, user_id, **fields):
                self.answers.append((user_id, fields))
                return {
                    "success": True,
                    "status": "preview",
                    "view": {
                        "title": "رزرو غذا",
                        "statusText": "آماده تأیید نهایی",
                        "actions": [{"ref": "confirm123", "label": "تأیید نهایی", "style": "success"}],
                    },
                }

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = Site()
            try:
                send_private_challenge(api=api, state=state, user_id=20, payload=challenge_payload())
                app = DentBotApp(
                    api,
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=site,
                    platform="telegram",
                    student_assistant_v1_enabled=True,
                )
                message = {
                    "message_id": 91,
                    "text": "A۷k۲P",
                    "from": {"id": 20},
                    "chat": {"id": 20, "type": "private"},
                    "reply_to_message": {"message_id": 78},
                }
                app.handle({"message": message})
                app.handle({"message": message})
                self.assertEqual(len(site.answers), 1)
                self.assertEqual(site.answers[0][0], 20)
                self.assertEqual(site.answers[0][1]["challenge_ref"], CHALLENGE_REF)
                self.assertEqual(site.answers[0][1]["job_ref"], JOB_REF)
                self.assertEqual(site.answers[0][1]["answer"], "A7k2P")
                self.assertEqual(len(site.answers[0][1]["request_id"]), 64)
                self.assertIsNone(state.integration_challenge(20))
                self.assertIn("آماده تأیید نهایی", api.sent[0][1])
            finally:
                state.close()

    def test_other_user_or_other_message_cannot_consume_binding(self) -> None:
        class Site:
            def __init__(self) -> None:
                self.answers = []

            def account(self, _user_id):
                return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}

            def integration_challenge_answer(self, user_id, **fields):
                self.answers.append((user_id, fields))
                return {"success": True, "status": "preview", "view": {"title": "عملیات"}}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = Site()
            try:
                send_private_challenge(api=api, state=state, user_id=20, payload=challenge_payload())
                app = DentBotApp(
                    api,
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=site,
                    student_assistant_v1_enabled=True,
                )
                app.handle({"message": {
                    "message_id": 92,
                    "text": "A7K2P",
                    "from": {"id": 21},
                    "chat": {"id": 21, "type": "private"},
                    "reply_to_message": {"message_id": 78},
                }})
                app.handle({"message": {
                    "message_id": 93,
                    "text": "A7K2P",
                    "from": {"id": 20},
                    "chat": {"id": 20, "type": "private"},
                    "reply_to_message": {"message_id": 999},
                }})
                self.assertEqual(site.answers, [])
                self.assertIsNotNone(state.integration_challenge(20))
            finally:
                state.close()

    def test_wrong_answer_can_replace_only_with_fresh_site_challenge(self) -> None:
        fresh_ref = "challenge_fresh_123456"

        class Site:
            def account(self, _user_id):
                return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}

            def integration_challenge_answer(self, _user_id, **_fields):
                return challenge_payload(ref=fresh_ref)

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                send_private_challenge(api=api, state=state, user_id=20, payload=challenge_payload())
                app = DentBotApp(
                    api,
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=Site(),
                    student_assistant_v1_enabled=True,
                )
                app.handle({"message": {
                    "message_id": 94,
                    "text": "WRNG1",
                    "from": {"id": 20},
                    "chat": {"id": 20, "type": "private"},
                    "reply_to_message": {"message_id": 78},
                }})
                self.assertEqual(len(api.photos), 2)
                self.assertEqual(state.integration_challenge(20)["challenge_ref"], fresh_ref)
                self.assertNotEqual(state.integration_challenge(20)["message_id"], 78)
            finally:
                state.close()

    def test_site_action_can_issue_challenge_on_both_platforms(self) -> None:
        class Site:
            def account(self, _user_id):
                return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}

            def student_assistant_summary(self, _user_id):
                return {"success": True, "view": {"title": "دستیار"}}

            def perform_integration_action(self, _user_id, **_fields):
                return challenge_payload()

        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                try:
                    app = DentBotApp(
                        api,
                        state,
                        owner_id=10,
                        site_url="https://example.test",
                        site_api=Site(),
                        platform=platform,
                        student_assistant_v1_enabled=True,
                    )
                    screen = app._dynamic_screen("assistant-action:startFood", 20, request_id="callback-1")
                    self.assertIn("کپچای تغذیه", screen.text)
                    self.assertEqual(len(api.photos), 1)
                    self.assertEqual(state.integration_challenge(20)["connector"], "food")
                finally:
                    state.close()

    def test_assistant_navigation_and_renderer_are_shared(self) -> None:
        payload = {
            "view": {
                "title": "دستیار دانشجو",
                "description": "رزرو و تکلیف‌ها",
                "connectors": [{"label": "تغذیه", "statusLabel": "متصل"}],
                "actions": [{"ref": "meal123", "label": "رزرو غذا", "style": "success"}],
            }
        }
        telegram = student_assistant_screen(payload)
        bale = student_assistant_screen(payload)
        self.assertEqual(telegram, bale)
        self.assertIn("assistant-action:meal123", str(telegram.keyboard))
        self.assertIn("دستیار دانشجو", str(home("https://example.test", is_owner=False, student_assistant_enabled=True).keyboard))
        self.assertNotIn("دستیار دانشجو", str(home("https://example.test", is_owner=False).keyboard))


if __name__ == "__main__":
    unittest.main()
