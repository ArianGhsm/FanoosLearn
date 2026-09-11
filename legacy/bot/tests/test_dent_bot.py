from __future__ import annotations

import tempfile
import unittest
import hashlib
import hmac
import json
import os
import signal
import ssl
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path
from unittest.mock import patch

from dent_bot.app import DentBotApp
from dent_bot.api import BOT_COMMANDS, BaleBotApi, BotApiError, TelegramBotApi, _HttpsConnectionPool
from dent_bot.state import BotState
from dent_bot.ui import grades_screen, home, navid_screen, section
from dent_bot.health import check
from dent_bot.site_api import SiteApiClient, SiteApiError
from dent_bot.site_health import check as check_site_health
from dent_bot.config import load_optional_relay_secret
from dent_bot.runtime import (
    configure_profile_safely,
    dispatch_account_disconnect_batch,
    dispatch_navid_daily,
    dispatch_notification_batch,
    run_service,
)
from dent_bot.ui import account_disconnect_notice_screen, notification_push_screen
from dent_bot.persian_datetime import format_jalali_datetime


class FakeApi:
    def __init__(self) -> None:
        self.sent: list[tuple] = []
        self.edited: list[tuple] = []
        self.answered: list[tuple] = []
        self.photos: list[tuple] = []

    def send(self, chat_id, text, reply_markup):
        self.sent.append((chat_id, text, reply_markup))
        return {"message_id": 1}

    def edit(self, chat_id, message_id, text, reply_markup):
        self.edited.append((chat_id, message_id, text, reply_markup))
        return {"message_id": message_id}

    def answer_callback(self, callback_id, text="", *, show_alert=False):
        self.answered.append((callback_id, text, show_alert))
        return True

    def send_photo_bytes(self, chat_id, photo, *, caption, reply_markup, filename="navid-captcha.png"):
        self.photos.append((chat_id, photo, caption, reply_markup, filename))
        return {"message_id": 77}


class LinkedSiteStub:
    def account(self, _user_id):
        return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}


class DentBotTests(unittest.TestCase):
    def test_telegram_chat_membership_statuses_fail_closed(self) -> None:
        api = TelegramBotApi("123:test", api_root="https://example.test")
        try:
            for result, expected in (
                ({"status": "creator"}, True),
                ({"status": "administrator"}, True),
                ({"status": "member"}, True),
                ({"status": "restricted", "is_member": True}, True),
                ({"status": "restricted", "is_member": False}, False),
                ({"status": "left"}, False),
                ({"status": "kicked"}, False),
            ):
                with self.subTest(result=result), patch.object(api, "call", return_value=result):
                    self.assertIs(api.is_chat_member("@Dent1402Booklets", 20), expected)
            with patch.object(api, "call", return_value={"status": "mystery"}):
                with self.assertRaises(BotApiError):
                    api.is_chat_member("@Dent1402Booklets", 20)
        finally:
            api.close()

    def test_callback_alert_is_visible_and_reports_transport_failure(self) -> None:
        api = TelegramBotApi("123:test", api_root="https://example.test")
        try:
            with patch.object(api, "call", return_value=True) as call:
                self.assertTrue(api.answer_callback("callback", "نتیجه 1402", show_alert=True))
                payload = call.call_args.args[1]
                self.assertIs(payload["show_alert"], True)
                self.assertEqual(payload["text"], "نتیجه ۱۴۰۲")
            with patch.object(api, "call", side_effect=BotApiError("expired")):
                self.assertFalse(api.answer_callback("callback", "نتیجه", show_alert=True))
        finally:
            api.close()

    def test_transient_profile_failure_does_not_kill_polling_startup(self) -> None:
        class Api:
            def configure_profile(self):
                raise BotApiError("temporary tunnel switch", transient=True)

        self.assertFalse(configure_profile_safely(Api(), "Telegram-test"))

    def test_permanent_profile_failure_still_fails_closed(self) -> None:
        class Api:
            def configure_profile(self):
                raise BotApiError("invalid token", transient=False)

        with self.assertRaises(BotApiError):
            configure_profile_safely(Api(), "Telegram-test")

    def test_background_site_timeout_does_not_block_telegram_polling(self) -> None:
        class Settings:
            owner_id = 10
            platform = "telegram"
            site_url = "https://example.test"
            site_api_url = "https://relay.example.test/api/site-api"
            site_service_secret = bytes.fromhex("11" * 32)
            site_relay_secret = "22" * 32
            poll_timeout = 1
            notification_poll_seconds = 30
            notification_batch_size = 10
            navid_daily_enabled = False

            def __init__(self, path):
                self.state_db = path

        class PollingApi(FakeApi):
            def __init__(self):
                super().__init__()
                self.poll_times = []
                self.calls = 0

            def configure_profile(self):
                return None

            def get_updates(self, _offset, _timeout):
                self.poll_times.append(time.monotonic())
                self.calls += 1
                if self.calls == 1:
                    return [{"update_id": 1, "message": {"text": "/start", "from": {"id": 20}, "chat": {"id": 20, "type": "private"}}}]
                signal.raise_signal(signal.SIGINT)
                return []

        class SlowSiteApi:
            def __init__(self, *_args, **_kwargs):
                pass

            def claim_notification_deliveries(self, *_args, **_kwargs):
                time.sleep(0.5)
                raise SiteApiError("unavailable", code="SITE_UNAVAILABLE")

        with tempfile.TemporaryDirectory() as directory, patch("dent_bot.runtime.SiteApiClient", SlowSiteApi):
            api = PollingApi()
            run_service(settings=Settings(Path(directory) / "state.sqlite3"), api=api, platform_name="Telegram-test")
            self.assertGreaterEqual(len(api.poll_times), 2)
            self.assertLess(api.poll_times[1] - api.poll_times[0], 0.2)
            self.assertEqual(len(api.sent), 1)

    def test_navid_daily_scheduler_sends_one_owner_only_force_reply_challenge(self) -> None:
        class Settings:
            owner_id = 10
            platform = "telegram"
            navid_daily_enabled = True
            navid_daily_hour = 9
            navid_daily_timezone = "Asia/Tehran"
            navid_retry_seconds = 3600

        class NavidApi:
            def __init__(self):
                self.calls = 0

            def navid_daily_start(self, owner_id, *, date, refresh=False):
                self.calls += 1
                self.request = (owner_id, date, refresh)
                return {
                    "success": True,
                    "status": "challenge-ready",
                    "captchaDataUri": "data:image/png;base64,iVBORw0KGgo=",
                    "expiresAt": "2026-08-13T09:10:00+03:30",
                }

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = NavidApi()
            now = datetime(2026, 8, 13, 9, 0)
            try:
                first = dispatch_navid_daily(settings=Settings(), api=api, state=state, site_api=site, now=now)
                second = dispatch_navid_daily(settings=Settings(), api=api, state=state, site_api=site, now=now)
                self.assertEqual(first, "challenge-ready")
                self.assertEqual(second, "throttled")
                self.assertEqual(site.calls, 1)
                self.assertEqual(api.photos[0][0], 10)
                self.assertTrue(api.photos[0][3]["force_reply"])
                self.assertEqual(state.navid_challenge()["message_id"], 77)
            finally:
                state.close()

    def test_navid_daily_scheduler_can_be_elected_on_bale(self) -> None:
        class Settings:
            owner_id = 10
            platform = "bale"
            navid_daily_enabled = True
            navid_daily_hour = 9
            navid_daily_timezone = "Asia/Tehran"
            navid_retry_seconds = 3600

        class NavidApi:
            def navid_daily_start(self, owner_id, *, date, refresh=False):
                return {
                    "success": True,
                    "status": "challenge-ready",
                    "captchaDataUri": "data:image/png;base64,iVBORw0KGgo=",
                    "expiresAt": "2026-08-13T09:10:00+03:30",
                }

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                result = dispatch_navid_daily(
                    settings=Settings(),
                    api=api,
                    state=state,
                    site_api=NavidApi(),
                    now=datetime(2026, 8, 13, 9, 0),
                )
                self.assertEqual(result, "challenge-ready")
                self.assertEqual(len(api.photos), 1)
            finally:
                state.close()

    def test_navid_captcha_reply_is_bound_to_owner_and_challenge_message(self) -> None:
        class NavidApi:
            def __init__(self):
                self.completed = []

            def account(self, _user_id):
                return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}

            def navid_daily_complete(self, owner_id, *, date, captcha_code):
                self.completed.append((owner_id, date, captcha_code))
                return {"success": True, "status": "completed", "summary": {"newEvents": 2}}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            state.set_navid_challenge("2026-08-13", 77, "2026-08-13T09:10:00+03:30")
            api = FakeApi()
            site = NavidApi()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
            try:
                app.handle({"message": {"message_id": 80, "text": "AB12", "from": {"id": 20}, "chat": {"id": 20, "type": "private"}, "reply_to_message": {"message_id": 77}}})
                app.handle({"message": {"message_id": 81, "text": "AB12", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}, "reply_to_message": {"message_id": 76}}})
                self.assertEqual(site.completed, [])
                app.handle({"message": {"message_id": 82, "text": "ab-12", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}, "reply_to_message": {"message_id": 77}}})
                self.assertEqual(site.completed, [(10, "2026-08-13", "AB12")])
                self.assertIsNone(state.navid_challenge())
                self.assertIn("2", api.sent[-1][1])
            finally:
                state.close()

    def test_interactive_navid_challenge_is_available_to_both_platforms(self) -> None:
        class NavidApi:
            def navid_daily_start(self, owner_id, *, date, refresh=False):
                return {
                    "success": True,
                    "status": "challenge-ready",
                    "captchaDataUri": "data:image/png;base64,iVBORw0KGgo=",
                    "expiresAt": "2026-08-13T09:10:00+03:30",
                }

        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                app = DentBotApp(
                    api,
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=NavidApi(),
                    platform=platform,
                )
                try:
                    app._send_navid_challenge(10, refresh=True)
                    self.assertEqual(len(api.photos), 1)
                    self.assertEqual(state.navid_challenge()["message_id"], 77)
                finally:
                    state.close()

    def test_telegram_and_bale_share_commands_and_feature_keyboards(self) -> None:
        expected_commands = [dict(item) for item in BOT_COMMANDS]

        class CapturingTelegramApi(TelegramBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=40):
                self.calls.append((method, payload))
                return True

        class CapturingBaleApi(BaleBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=40):
                self.calls.append((method, payload))
                return True

        telegram_api = CapturingTelegramApi()
        bale_api = CapturingBaleApi()
        telegram_api.configure_profile()
        bale_api.configure_profile()
        self.assertEqual(telegram_api.calls[0][1]["commands"], expected_commands)
        self.assertEqual(bale_api.calls[0][1]["commands"], expected_commands)

        payload = {
            "name": "دانشجو",
            "grades": [{"label": "درس", "value": "18", "maxScore": "20"}],
            "stats": [],
        }
        telegram_grades = grades_screen("https://example.test", payload, platform="telegram")
        bale_grades = grades_screen("https://example.test", payload, platform="bale")
        self.assertEqual(telegram_grades.keyboard, bale_grades.keyboard)
        navid_payload = {"status": {}, "automation": {}, "assignments": []}
        telegram_navid = navid_screen(navid_payload, platform="telegram", site_url="https://example.test")
        bale_navid = navid_screen(navid_payload, platform="bale", site_url="https://example.test")
        self.assertEqual(telegram_navid.keyboard, bale_navid.keyboard)
        owner_callbacks = {
            button.get("callback_data")
            for row in home("https://example.test", is_owner=True).keyboard["inline_keyboard"]
            for button in row
            if button.get("callback_data")
        }
        self.assertIn("v1:navid", owner_callbacks)
        self.assertIn("v1:admin", owner_callbacks)

    def test_notification_feed_marks_seen_only_after_explicit_open(self) -> None:
        class NotificationApi(LinkedSiteStub):
            def __init__(self):
                self.marked = []

            def notifications(self, _user_id, *, limit=20):
                return {
                    "success": True,
                    "data": {
                        "summary": {"unreadCount": 1},
                        "items": [{
                            "id": "nt-example123",
                            "title": "اعلان آزمون",
                            "body": "زمان آزمون تغییر کرد.",
                            "effectiveAt": "2026-08-13T12:30:00+00:00",
                            "unread": not self.marked,
                            "ctaLabel": "مشاهده آزمون",
                            "ctaHref": "/exams/",
                        }],
                    },
                }

            def mark_notification_read(self, user_id, notification_id):
                self.marked.append((user_id, notification_id))
                return {"success": True}

        with tempfile.TemporaryDirectory() as directory:
            site = NotificationApi()
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(FakeApi(), state, owner_id=10, site_url="https://example.test", site_api=site)
                listing = app._dynamic_screen("notifications", 20)
                self.assertEqual(site.marked, [])
                action = listing.keyboard["inline_keyboard"][0][0]["callback_data"][3:]
                detail = app._dynamic_screen(action, 20)
                self.assertEqual(site.marked, [(20, "nt-example123")])
                self.assertIn("https://example.test/exams/", str(detail.keyboard))
                self.assertIn("۱۴۰۵/۵/۲۲", detail.text)
            finally:
                state.close()

    def test_notification_audience_is_owner_only(self) -> None:
        class NotificationApi(LinkedSiteStub):
            def __init__(self):
                self.audience_calls = 0

            def notification_audience(self, _user_id, _notification_id):
                self.audience_calls += 1
                return {"success": True, "data": {"record": {"title": "اعلان"}, "summary": {"recipientCount": 2, "viewedCount": 1, "pendingCount": 1}}}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            site = NotificationApi()
            try:
                ref = state.remember_notification("nt-example123")
                normal = DentBotApp(FakeApi(), state, owner_id=10, site_url="https://example.test", site_api=site)
                screen = normal._dynamic_screen(f"notification-audience:{ref}", 20)
                self.assertEqual(site.audience_calls, 0)
                self.assertIn("دنت‌یار", screen.text)
                owner = normal._dynamic_screen(f"notification-audience:{ref}", 10)
                self.assertEqual(site.audience_calls, 1)
                self.assertIn("مشاهده‌شده", owner.text)
            finally:
                state.close()

    def test_notification_dispatch_receipt_prevents_duplicate_send(self) -> None:
        class Settings:
            owner_id = 10
            platform = "telegram"
            notification_batch_size = 10

        class DeliveryApi:
            def __init__(self):
                self.acks = []

            def claim_notification_deliveries(self, _owner_id, *, limit):
                self.assert_limit = limit
                return {"success": True, "deliveries": [{
                    "deliveryId": "nd-1234567890abcdef1234567890abcdef",
                    "chatId": "20",
                    "notification": {"id": "nt-example123", "title": "خبر", "body": "متن"},
                }]}

            def ack_notification_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
                self.acks.append((owner_id, delivery_id, delivered, reason_code))
                return {"success": True}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = DeliveryApi()
            try:
                first = dispatch_notification_batch(settings=Settings(), api=api, state=state, site_api=site)
                second = dispatch_notification_batch(settings=Settings(), api=api, state=state, site_api=site)
                self.assertEqual(first["sent"], 1)
                self.assertEqual(second["sent"], 0)
                self.assertEqual(second["acknowledged"], 1)
                self.assertEqual(len(api.sent), 1)
                self.assertEqual(len(site.acks), 2)
            finally:
                state.close()

    def test_notification_dispatch_cancels_retired_exam_reminder_on_both_platforms(self) -> None:
        class Settings:
            owner_id = 10
            notification_batch_size = 10

        class DeliveryApi:
            def __init__(self):
                self.acks = []

            def claim_notification_deliveries(self, _owner_id, *, limit):
                self.assert_limit = limit
                return {"success": True, "deliveries": [{
                    "deliveryId": "nd-fedcba0987654321fedcba0987654321",
                    "chatId": "20",
                    "notification": {
                        "id": "nt-retired-exam",
                        "source": "exams",
                        "sourceKey": "exam-resume-1d:student:catalog:course:exam",
                        "title": "ادامه آزمون نیمه‌کاره",
                        "body": "نباید ارسال شود",
                    },
                }]}

            def ack_notification_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
                self.acks.append((owner_id, delivery_id, delivered, reason_code))
                return {"success": True}

        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                Settings.platform = platform
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                site = DeliveryApi()
                try:
                    counts = dispatch_notification_batch(settings=Settings(), api=api, state=state, site_api=site)
                    self.assertEqual(counts["claimed"], 1)
                    self.assertEqual(counts["canceled"], 1)
                    self.assertEqual(counts["sent"], 0)
                    self.assertEqual(api.sent, [])
                    self.assertEqual(
                        site.acks,
                        [(10, "nd-fedcba0987654321fedcba0987654321", False, "EXAM_REMINDER_RETIRED")],
                    )
                finally:
                    state.close()

    def test_account_disconnect_notice_is_same_platform_and_idempotent(self) -> None:
        class Settings:
            owner_id = 10
            platform = "telegram"
            notification_batch_size = 10

        class DeliveryApi:
            def __init__(self):
                self.acks = []

            def claim_account_disconnect_deliveries(self, _owner_id, *, limit):
                self.assert_limit = limit
                return {"success": True, "deliveries": [{
                    "deliveryId": "bd-1234567890abcdef1234567890abcdef",
                    "platform": "telegram",
                    "chatId": "20",
                    "disconnectedAt": "2026-08-27T18:30:00+03:30",
                }]}

            def ack_account_disconnect_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
                self.acks.append((owner_id, delivery_id, delivered, reason_code))
                return {"success": True}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = DeliveryApi()
            try:
                first = dispatch_account_disconnect_batch(settings=Settings(), api=api, state=state, site_api=site)
                second = dispatch_account_disconnect_batch(settings=Settings(), api=api, state=state, site_api=site)
                self.assertEqual(first["sent"], 1)
                self.assertEqual(second["sent"], 0)
                self.assertEqual(second["acknowledged"], 1)
                self.assertEqual(len(api.sent), 1)
                self.assertIn("اتصال حساب قطع شد", api.sent[0][1])
                self.assertIn("اتصال دوباره", str(api.sent[0][2]))
                self.assertEqual(len(site.acks), 2)
            finally:
                state.close()

    def test_account_disconnect_notice_uses_persian_tehran_time(self) -> None:
        screen = account_disconnect_notice_screen(
            "2026-08-27T18:30:00+03:30",
            platform="bale",
        )
        self.assertIn("بله", screen.text)
        self.assertIn("ساعت ۱۸:۳۰", screen.text)
        self.assertNotIn("2026", screen.text)

    def test_bale_notification_uses_plain_date_fallback(self) -> None:
        screen = notification_push_screen(
            {"id": "nt-example123", "title": "خبر", "body": "متن", "effectiveAt": "2026-08-13T12:30:00+00:00"},
            "abcd1234abcd1234",
            platform="bale",
        )
        self.assertNotIn("tg-time", screen.text)
        self.assertIn("۱۴۰۵/۵/۲۲", screen.text)

    def test_notification_center_is_compact_and_limits_item_rows(self) -> None:
        from dent_bot.ui import notification_list_screen

        items = [
            {"id": f"nt-{index}", "title": f"اعلان شماره {index}", "unread": index < 3}
            for index in range(9)
        ]
        screen = notification_list_screen(
            {"data": {"summary": {"unreadCount": 3}, "items": items}},
            {item["id"]: f"ref{index}" for index, item in enumerate(items)},
            platform="telegram",
            site_url="https://example.test",
        )
        rows = screen.keyboard["inline_keyboard"]
        self.assertIn("۳ خوانده‌نشده", screen.text)
        self.assertIn("۶ اعلان تازه‌تر", screen.text)
        self.assertEqual(len(rows), 8)
        self.assertEqual(len(rows[-2]), 2)
        self.assertNotIn("اعلان شماره 8", str(rows))

    def test_open_notification_does_not_repeat_seen_button(self) -> None:
        from dent_bot.ui import notification_detail_screen

        screen = notification_detail_screen(
            {"title": "خبر", "body": "متن"},
            "abcd1234abcd1234",
            platform="telegram",
            is_owner=False,
            show_mark_read=False,
        )
        self.assertNotIn("مشاهده شد", str(screen.keyboard))

    def test_notification_actions_are_bounded_and_render_for_both_platforms(self) -> None:
        from dent_bot.ui import notification_detail_screen

        item = {
            "title": "یادآوری تکلیف",
            "body": "اگر تحویل داده‌ای، یادآوری را متوقف کن.",
            "actions": [
                {"ref": "submitted", "label": "تحویل دادم؛ دیگه نگو", "style": "success"},
                {"ref": "bad:ref", "label": "نباید نمایش داده شود"},
            ],
        }
        for platform in ("telegram", "bale"):
            screen = notification_detail_screen(
                item,
                "abcd1234abcd1234",
                platform=platform,
                is_owner=False,
            )
            encoded = str(screen.keyboard)
            self.assertIn("notification-action:abcd1234abcd1234:submitted", encoded)
            self.assertNotIn("bad:ref", encoded)

    def test_notification_action_writes_only_to_canonical_site_state(self) -> None:
        class ActionSite(LinkedSiteStub):
            def __init__(self):
                self.actions = []

            def perform_notification_action(self, user_id, notification_id, action_ref):
                self.actions.append((user_id, notification_id, action_ref))
                return {"success": True, "message": "یادآوری این تکلیف متوقف شد."}

            def notifications(self, _user_id, *, limit):
                self.limit = limit
                return {"data": {"items": [{"id": "nt-1", "title": "تکلیف", "body": "ثبت شد"}]}}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = ActionSite()
            try:
                ref = state.remember_notification("nt-1")
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                app.handle({"callback_query": {
                    "id": "cb-1",
                    "from": {"id": 20},
                    "data": f"v1:notification-action:{ref}:submitted",
                    "message": {"message_id": 8, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(site.actions, [(20, "nt-1", "submitted")])
                self.assertIn("یادآوری این تکلیف متوقف شد", api.edited[0][2])
                columns = [row[1] for row in state.connection.execute("PRAGMA table_info(bot_users)")]
                self.assertNotIn("reminder_ack", columns)
            finally:
                state.close()

    def test_site_api_signs_body_with_timestamp_and_nonce(self) -> None:
        captured = {}

        class Response:
            status = 200

            def __enter__(self):
                return self

            def __exit__(self, *_args):
                return None

            def read(self, _limit):
                return b'{"success":true,"linked":false}'

        def open_request(request, timeout):
            captured["request"] = request
            captured["timeout"] = timeout
            return Response()

        secret = bytes.fromhex("11" * 32)
        client = SiteApiClient("https://example.test/api/bot_api.php?action=service", secret, platform="telegram")
        with patch("urllib.request.urlopen", side_effect=open_request):
            result = client.account(42)
        request = captured["request"]
        timestamp = request.get_header("X-dent-timestamp")
        nonce = request.get_header("X-dent-nonce")
        signature = request.get_header("X-dent-signature")
        expected = hmac.new(
            secret,
            f"{timestamp}\n{nonce}\n{hashlib.sha256(request.data).hexdigest()}".encode("ascii"),
            hashlib.sha256,
        ).hexdigest()
        self.assertFalse(result["linked"])
        self.assertEqual(signature, expected)
        self.assertNotIn(secret.hex(), request.data.decode("utf-8"))

    def test_site_link_requests_the_canonical_auth_contract(self) -> None:
        captured = {}

        class Response:
            status = 200

            def __enter__(self):
                return self

            def __exit__(self, *_args):
                return None

            def read(self, _limit):
                return b'{"success":true,"alreadyLinked":false,"linkUrl":"https://example.test/link"}'

        def open_request(request, timeout):
            captured["request"] = request
            return Response()

        client = SiteApiClient(
            "https://example.test/api/bot_api.php?action=service",
            bytes.fromhex("11" * 32),
            platform="telegram",
        )
        with patch("urllib.request.urlopen", side_effect=open_request):
            client.start_link(42, platform_profile={"displayName": "Test"})
        payload = json.loads(captured["request"].data.decode("utf-8"))
        self.assertEqual(payload["authVersion"], "bot-canonical-auth-v1")

    def test_site_api_adds_relay_authorization_without_changing_hmac(self) -> None:
        captured = {}

        class Response:
            status = 200

            def __enter__(self):
                return self

            def __exit__(self, *_args):
                return None

            def read(self, _limit):
                return b'{"success":true}'

        def open_request(request, timeout):
            captured["request"] = request
            return Response()

        hmac_secret = bytes.fromhex("11" * 32)
        relay_secret = "22" * 32
        client = SiteApiClient(
            "https://relay.example.test/v1/site-api",
            hmac_secret,
            platform="telegram",
            relay_secret=relay_secret,
        )
        with patch("urllib.request.urlopen", side_effect=open_request):
            client.account(42)
        request = captured["request"]
        self.assertEqual(request.get_header("Authorization"), f"Bearer {relay_secret}")
        timestamp = request.get_header("X-dent-timestamp")
        nonce = request.get_header("X-dent-nonce")
        expected = hmac.new(
            hmac_secret,
            f"{timestamp}\n{nonce}\n{hashlib.sha256(request.data).hexdigest()}".encode("ascii"),
            hashlib.sha256,
        ).hexdigest()
        self.assertEqual(request.get_header("X-dent-signature"), expected)
        self.assertNotIn(relay_secret, request.data.decode("utf-8"))

    def test_relay_secret_requires_strong_explicit_encoding(self) -> None:
        for weak in ("changeme", "replace-me", "secret", "development", "test", "abc"):
            with self.subTest(weak=weak), patch.dict(os.environ, {"DENT_BOT_SITE_RELAY_SECRET": weak}):
                with self.assertRaises(ValueError):
                    load_optional_relay_secret()
        with patch.dict(os.environ, {"DENT_BOT_SITE_RELAY_SECRET": "33" * 32}):
            self.assertEqual(load_optional_relay_secret(), "33" * 32)

    def test_retired_cloudflare_worker_is_not_reintroduced(self) -> None:
        worker = Path(__file__).parents[1] / "relay" / "cloudflare-worker" / "src" / "index.js"
        self.assertFalse(worker.exists())

    def test_site_health_exposes_no_identifiers_or_secrets(self) -> None:
        class Settings:
            site_api_url = "https://relay.example.test/v1/site-api"
            site_service_secret = bytes.fromhex("11" * 32)
            site_relay_secret = "22" * 32
            platform = "telegram"
            owner_id = 123456

        class Client:
            def __init__(self, *_args, **_kwargs):
                pass

            def account(self, _owner_id):
                return {"success": True, "linked": True, "authComplete": True, "private": "must-not-leak"}

        with patch("dent_bot.site_health.load_settings", return_value=Settings()), patch(
            "dent_bot.site_health.SiteApiClient", Client
        ):
            result = check_site_health()
        encoded = json.dumps(result)
        self.assertTrue(result["ready"])
        self.assertNotIn("123456", encoded)
        self.assertNotIn("must-not-leak", encoded)
        self.assertNotIn(Settings.site_relay_secret, encoded)

    def test_grades_are_fetched_fresh_and_not_written_to_bot_state(self) -> None:
        class GradesApi(LinkedSiteStub):
            def __init__(self):
                self.calls = 0

            def grades(self, _user_id):
                self.calls += 1
                return {"success": True, "grades": [{"label": "اندو", "value": str(17 + self.calls), "maxScore": 20}]}

        with tempfile.TemporaryDirectory() as directory:
            api = FakeApi()
            site = GradesApi()
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                first = app._dynamic_screen("grades", 10)
                second = app._dynamic_screen("grades", 10)
                self.assertIn("۱۸", first.text)
                self.assertIn("۱۹", second.text)
                columns = [row[1] for row in state.connection.execute("PRAGMA table_info(bot_users)")]
                self.assertNotIn("grades", columns)
                self.assertEqual(site.calls, 2)
            finally:
                state.close()

    def test_bot_offer_amount_is_sent_to_site_only_when_checkout_is_created(self) -> None:
        class PaymentApi(LinkedSiteStub):
            def __init__(self):
                self.create_calls = []

            def create_bot_payment(self, user_id, **fields):
                self.create_calls.append((user_id, fields))
                return {
                    "success": True,
                    "orderToken": "order_token_123456789012",
                    "amountRials": 300000,
                    "redirectUrl": "https://gateway.example.test/start/abc",
                }

        with tempfile.TemporaryDirectory() as directory:
            api = FakeApi()
            site = PaymentApi()
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                offer = state.create_payment_offer("بسته آزمون", 300000, "ثبت‌نام")
                confirm = app._dynamic_screen(f"payment-confirm:{offer['ref']}", 20)
                self.assertIn("30,000 تومان", confirm.text)
                created = app._dynamic_screen(f"payment-create:{offer['ref']}", 20, request_id="callback-unique")
                self.assertIn("https://gateway.example.test/start/abc", str(created.keyboard))
                self.assertEqual(site.create_calls[0][0], 20)
                self.assertEqual(site.create_calls[0][1]["offer_ref"], offer["ref"])
                self.assertEqual(site.create_calls[0][1]["amount_rials"], 300000)
                self.assertEqual(len(site.create_calls[0][1]["request_id"]), 64)
            finally:
                state.close()

    def test_payment_menu_is_local_and_does_not_call_site(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                offer = state.create_payment_offer("هزینه اردو", 500000, "ثبت‌نام نهایی")
                app = DentBotApp(FakeApi(), state, owner_id=10, site_url="https://example.test", site_api=LinkedSiteStub())
                screen = app._dynamic_screen("payments", 20)
                self.assertIn("هزینه اردو", screen.text)
                self.assertIn(offer["ref"], str(screen.keyboard))
            finally:
                state.close()

    def test_inactive_offer_is_hidden_from_user_menu(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                offer = state.create_payment_offer("هزینه اردو", 500000)
                state.set_payment_offer_status(offer["ref"], "inactive")
                app = DentBotApp(FakeApi(), state, owner_id=10, site_url="https://example.test", site_api=LinkedSiteStub())
                screen = app._dynamic_screen("payments", 20)
                self.assertNotIn("هزینه اردو", screen.text)
                self.assertIn("هزینه اردو", app._dynamic_screen("admin-payments", 10).text)
            finally:
                state.close()

    def test_owner_can_create_bot_offer_with_wizard_but_regular_user_cannot(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=LinkedSiteStub())
                app.handle({"message": {"text": "/product", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}}})
                self.assertIn("عنوان محصول", api.sent[-1][1])
                app.handle({"message": {"text": "هزینه آزمون", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}}})
                self.assertEqual(state.dialog(10)["step"], "amount")
                app._dynamic_screen("payment-offer-amount:50000", 10)
                app._dynamic_screen("payment-offer-audience:all", 10)
                saved = app._dynamic_screen("payment-offer-publish", 10)
                self.assertEqual(state.payment_offers()[0]["amountRials"], 500000)
                self.assertIn("محصول ربات ساخته شد", saved.text)
                self.assertIsNone(state.dialog(10))
                app.handle({"message": {"text": "/product ممنوع | 50000", "from": {"id": 20}, "chat": {"id": 20, "type": "private"}}})
                self.assertEqual(len(state.payment_offers()), 1)
                self.assertIn("اجازه", api.sent[-1][1])
            finally:
                state.close()

    def test_payment_offer_wizard_supports_custom_amount_and_description(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=LinkedSiteStub())
                app.handle({"message": {"text": "/product", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}}})
                app.handle({"message": {"text": "ثبت‌نام کارگاه", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}}})
                app._dynamic_screen("payment-offer-custom-amount", 10)
                app.handle({"message": {"text": "۱۲۵٬۰۰۰ تومان", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}}})
                app._dynamic_screen("payment-offer-audience:all", 10)
                app._dynamic_screen("payment-offer-description", 10)
                app.handle({"message": {"text": "ظرفیت محدود", "from": {"id": 10}, "chat": {"id": 10, "type": "private"}}})
                preview = app._dynamic_screen("payment-offer-publish", 10)
                offer = state.payment_offers()[0]
                self.assertEqual(offer["amountRials"], 1_250_000)
                self.assertEqual(offer["description"], "ظرفیت محدود")
                self.assertIn("ثبت‌نام کارگاه", preview.text)
            finally:
                state.close()

    def test_site_circuit_breaker_fails_fast_after_three_network_failures(self) -> None:
        import urllib.error
        from unittest.mock import patch

        client = SiteApiClient(
            "https://example.test/api",
            b"x" * 32,
            platform="telegram",
            timeout=0.1,
        )
        with patch("urllib.request.urlopen", side_effect=urllib.error.URLError("offline")):
            for _ in range(3):
                with self.assertRaises(SiteApiError):
                    client.account(10)
            started = time.monotonic()
            with self.assertRaisesRegex(SiteApiError, "بازیابی"):
                client.account(10)
            self.assertLess(time.monotonic() - started, 0.1)

    def test_manual_identity_claim_is_disabled_and_routes_to_site_auth(self) -> None:
        class IdentityApi(LinkedSiteStub):
            def __init__(self) -> None:
                self.names = []

            def submit_identity_claim(self, _user_id, *, name, telegram_profile):
                self.names.append((name, telegram_profile))
                return {"success": True, "status": "pending"}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = IdentityApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                prompt = app._dynamic_screen("identity-claim", 20)
                self.assertIn("تأیید دستی هویت بازنشسته", prompt.text)
                self.assertIn("کد یک", str(prompt.keyboard))
                self.assertEqual(site.names, [])
                self.assertIsNone(state.dialog(20))
            finally:
                state.close()

    def test_legacy_owner_identity_claim_screen_is_disabled_without_api_call(self) -> None:
        class IdentityApi(LinkedSiteStub):
            def __init__(self):
                self.calls = 0

            def identity_claims(self, _owner_id):
                self.calls += 1
                raise AssertionError("legacy identityClaims must never be called")

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                site = IdentityApi()
                app = DentBotApp(FakeApi(), state, owner_id=10, site_url="https://example.test", site_api=site)
                owner = app._dynamic_screen("identity-claims", 10)
                self.assertIn("بازنشسته", owner.text)
                self.assertEqual(site.calls, 0)
            finally:
                state.close()

    def test_legacy_owner_approval_button_is_a_noop(self) -> None:
        class IdentityApi(LinkedSiteStub):
            def __init__(self):
                self.resolved = []

            def resolve_identity_claim(self, _owner_id, *, claim_ref, decision):
                self.resolved.append((claim_ref, decision))
                raise AssertionError("legacy resolution must never be called")

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = IdentityApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                screen = app._dynamic_screen("identity-approve:abcdefghijkl", 10)
                self.assertIn("دیگر هیچ هویتی را تأیید یا رد نمی‌کند", screen.text)
                self.assertEqual(site.resolved, [])
                self.assertEqual(api.sent, [])
            finally:
                state.close()

    def test_owner_manual_identity_mapping_wizard_is_disabled(self) -> None:
        class IdentityApi(LinkedSiteStub):
            def __init__(self):
                self.saved = []

            def set_identity_mapping(self, _owner_id, **fields):
                self.saved.append(fields)
                return {"success": True}

            def identity_mappings(self, _owner_id):
                return {"success": True, "mappings": []}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = IdentityApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                screen = app._dynamic_screen("identity-mapping-new", 10)
                self.assertIn("اتصال و تأیید دستی هویت بازنشسته", screen.text)
                self.assertEqual(site.saved, [])
                self.assertIsNone(state.dialog(10))
            finally:
                state.close()

    def test_profile_edit_cancel_clears_dialog_before_next_message(self) -> None:
        class IdentityApi:
            def __init__(self):
                self.requested = []

            def account(self, _user_id):
                return {
                    "success": True, "linked": True, "authComplete": True,
                    "user": {"name": "دانشجوی آزمایشی", "studentNumber": "402000001"},
                    "onboardingProfile": {
                        "firstName": "دانشجوی", "lastName": "آزمایشی", "major": "دندانپزشکی",
                        "institution": "دانشگاه علوم پزشکی تهران", "province": "تهران",
                        "admissionType": "نیمسال اول (روزانه یا تعهدی)", "studentNumber": "402000001",
                    },
                }

            def request_profile_edit(self, _user_id, *, field, value):
                self.requested.append((field, value))
                return {"success": True}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = IdentityApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                app._dynamic_screen("profile-edit-field:lastName", 20)
                self.assertEqual(state.dialog(20)["kind"], "profile-edit-v1")
                screen = app._dynamic_screen("profile-edit-cancel", 20)
                self.assertIn("مشخصات", screen.text)
                self.assertIsNone(state.dialog(20))
                app.handle({"message": {"text": "پیام عادی", "from": {"id": 20}, "chat": {"id": 20, "type": "private"}}})
                self.assertEqual(site.requested, [])
            finally:
                state.close()

    def test_rapid_repeated_callbacks_coalesce_before_slow_site_call(self) -> None:
        import threading

        class SlowSite(LinkedSiteStub):
            def __init__(self) -> None:
                self.calls = 0

            def grades(self, _user_id):
                self.calls += 1
                time.sleep(0.05)
                return {"success": True, "grades": []}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = SlowSite()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
                threads = []
                for index in range(12):
                    update = {"callback_query": {
                        "id": f"callback-{index}", "from": {"id": 20}, "data": "v1:grades",
                        "message": {"message_id": 7, "chat": {"id": 20, "type": "private"}},
                    }}
                    thread = threading.Thread(target=app.handle, args=(update,))
                    threads.append(thread)
                    thread.start()
                for thread in threads:
                    thread.join()
                self.assertEqual(site.calls, 1)
                self.assertEqual(len(api.sent), 1)
                self.assertEqual(len(api.edited), 0)
                self.assertEqual(len(api.answered), 12)
            finally:
                state.close()

    def test_plain_menu_callback_sends_native_rich_report_as_a_new_message(self) -> None:
        class GradesSite(LinkedSiteStub):
            def grades(self, _user_id):
                return {"name": "دانشجو", "grades": [{"label": "درس", "value": 18, "maxScore": 20}], "stats": []}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=GradesSite())
                app.handle({"callback_query": {
                    "id": "plain-to-rich", "from": {"id": 20}, "data": "v1:grades",
                    "message": {"message_id": 7, "text": "منوی اصلی", "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(len(api.sent), 1)
                self.assertTrue(getattr(api.sent[0][1], "rich_html", ""))
                self.assertEqual(api.edited, [])
            finally:
                state.close()

    def test_rich_report_callback_edits_an_existing_rich_message(self) -> None:
        class GradesSite(LinkedSiteStub):
            def grades(self, _user_id):
                return {"name": "دانشجو", "grades": [{"label": "درس", "value": 19, "maxScore": 20}], "stats": []}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=GradesSite())
                app.handle({"callback_query": {
                    "id": "rich-to-rich", "from": {"id": 20}, "data": "v1:grades",
                    "message": {"message_id": 8, "rich_message": {"blocks": []}, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(len(api.edited), 1)
                self.assertTrue(getattr(api.edited[0][2], "rich_html", ""))
                self.assertEqual(api.sent, [])
            finally:
                state.close()

    def test_telegram_grades_and_navid_use_bounded_structured_rich_text(self) -> None:
        grades = grades_screen("https://example.test", {
            "name": "دانشجوی آزمایشی",
            "grades": [{"label": f"درس {index}", "value": 18, "maxScore": 20} for index in range(12)],
            "stats": [],
        }, platform="telegram")
        self.assertIn("<b><u>📊 کارنامه من</u></b>", grades.text)
        self.assertIn("<blockquote>🎯 نمره:", grades.text)
        self.assertNotIn("<pre>", grades.text)
        self.assertIn("درس 11", grades.text)
        self.assertIn("<table bordered striped compact>", grades.text.rich_html)
        self.assertIn("<th>میانگین</th>", grades.text.rich_html)
        self.assertIn("درس 11", grades.text.rich_html)
        navid = navid_screen({
            "status": {"state": {"actionRequired": "captcha", "lastSuccessAt": "2026-08-14T01:00:00+00:00"}, "snapshotCounts": {"courses": 6, "assignments": 2}},
            "automation": {},
            "assignments": [{"title": "تمرین", "courseTitle": "اندو", "endDateIso": "2026-08-15T10:00:00+03:30"}],
        }, platform="telegram", site_url="https://example.test")
        self.assertIn("مرکز نوید", navid.text)
        self.assertNotIn("<tg-time", navid.text)
        self.assertIn("<b><u>🎓 مرکز نوید</u></b>", navid.text)
        self.assertIn("<blockquote>📚 درس:", navid.text)
        self.assertNotIn("<pre>", navid.text)
        self.assertIn("۱۴۰۵/۵/۲۴", navid.text)
        self.assertIn("<table bordered striped compact>", navid.text.rich_html)
        self.assertIn("<th>مهلت</th>", navid.text.rich_html)
        self.assertIn("بررسی امروز", str(navid.keyboard))

    def test_telegram_grade_report_uses_native_send_rich_message_and_rtl_table(self) -> None:
        class CapturingApi(TelegramBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=40):
                self.calls.append((method, payload, timeout))
                return {"message_id": 9}

        screen = grades_screen("https://example.test", {
            "name": "دانشجوی 1402",
            "grades": [{"label": "درس 12", "value": "18", "maxScore": "20"}],
            "stats": [{"label": "درس 12", "classAverage": "17.5", "rank": 2, "totalWithScore": 96}],
        })
        api = CapturingApi()
        api.send(10, screen.text, screen.keyboard)
        method, payload, _timeout = api.calls[0]
        self.assertEqual(method, "sendRichMessage")
        self.assertTrue(payload["rich_message"]["is_rtl"])
        rich_html = payload["rich_message"]["html"]
        self.assertIn("<table bordered striped compact>", rich_html)
        self.assertIn("دانشجوی ۱۴۰۲", rich_html)
        self.assertIn("درس ۱۲", rich_html)
        self.assertIn("۱۸ از ۲۰", rich_html)
        self.assertNotIn("https://example.test/grades/", rich_html)

    def test_transport_persianizes_every_visible_digit_without_mutating_actions_or_urls(self) -> None:
        class CapturingApi(TelegramBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=40):
                self.calls.append((method, payload, timeout))
                return {"message_id": 9}

        api = CapturingApi()
        api.send(
            10,
            '<b>ورودی 1402</b>\n<a href="https://example.test/course/1402">درس 12</a>\nhttps://t.me/test1402',
            {
                "inline_keyboard": [[{
                    "text": "مرحله 12",
                    "callback_data": "v1:step-12",
                    "url": "https://example.test/item/12",
                }]],
                "input_field_placeholder": "کد 6 رقمی",
            },
        )
        payload = api.calls[0][1]
        self.assertIn("ورودی ۱۴۰۲", payload["text"])
        self.assertIn('href="https://example.test/course/1402"', payload["text"])
        self.assertIn(">درس ۱۲</a>", payload["text"])
        self.assertIn("https://t.me/test1402", payload["text"])
        button_payload = payload["reply_markup"]["inline_keyboard"][0][0]
        self.assertEqual(button_payload["text"], "مرحله ۱۲")
        self.assertEqual(button_payload["callback_data"], "v1:step-12")
        self.assertEqual(button_payload["url"], "https://example.test/item/12")
        self.assertEqual(payload["reply_markup"]["input_field_placeholder"], "کد ۶ رقمی")

    def test_all_bot_dates_use_jalali_tehran_literal(self) -> None:
        rendered = format_jalali_datetime("2026-08-29T15:34:00+03:30")
        self.assertEqual(rendered, "ساعت ۱۵:۳۴ شنبه ۱۴۰۵/۶/۷")
        self.assertNotIn("2026", rendered)

    def test_https_pool_reuses_a_healthy_connection(self) -> None:
        import queue

        class Socket:
            def settimeout(self, _timeout):
                pass

        class Response:
            will_close = False
            status = 200

            def read(self, _limit):
                return b'{"ok":true,"result":true}'

        class Connection:
            def __init__(self):
                self.sock = Socket()
                self.timeout = 0
                self.requests = 0

            def request(self, *_args, **_kwargs):
                self.requests += 1

            def getresponse(self):
                return Response()

            def close(self):
                pass

        connection = Connection()
        pool = object.__new__(_HttpsConnectionPool)
        pool.prefix = ""
        pool.connections = queue.LifoQueue(maxsize=1)
        pool.connections.put(connection)
        first = pool.post("/one", b"{}", {}, timeout=2)
        second = pool.post("/two", b"{}", {}, timeout=2)
        self.assertEqual(first[0], 200)
        self.assertEqual(second[0], 200)
        self.assertEqual(connection.requests, 2)

    def test_https_pool_retries_once_when_stale_socket_fails_during_request(self) -> None:
        import queue

        class Socket:
            def settimeout(self, _timeout):
                pass

        class Response:
            will_close = False
            status = 200

            def read(self, _limit):
                return b'{"ok":true,"result":true}'

        class Connection:
            def __init__(self, *, fails=False):
                self.sock = Socket()
                self.timeout = 0
                self.fails = fails
                self.requests = 0
                self.closed = False

            def request(self, *_args, **_kwargs):
                self.requests += 1
                if self.fails:
                    raise ssl.SSLEOFError("stale pooled TLS connection")

            def getresponse(self):
                return Response()

            def close(self):
                self.closed = True

        stale = Connection(fails=True)
        fresh = Connection()
        pool = object.__new__(_HttpsConnectionPool)
        pool.prefix = ""
        pool.connections = queue.LifoQueue(maxsize=1)
        pool.connections.put(stale)
        pool._new = lambda _timeout: fresh

        status, _raw = pool.post("/start", b"{}", {}, timeout=2)

        self.assertEqual(status, 200)
        self.assertTrue(stale.closed)
        self.assertEqual(stale.requests, 1)
        self.assertEqual(fresh.requests, 1)
        self.assertIs(pool.connections.get_nowait(), fresh)

    def test_https_pool_streams_download_and_hashes_without_second_disk_pass(self) -> None:
        import hashlib
        import queue
        import tempfile

        payload = b"streamed-pdf-fixture" * 20000

        class Socket:
            def settimeout(self, _timeout):
                pass

        class Response:
            will_close = False
            status = 200

            def __init__(self):
                self.offset = 0

            def getheader(self, name):
                return str(len(payload)) if name == "Content-Length" else None

            def read(self, limit):
                chunk = payload[self.offset:self.offset + limit]
                self.offset += len(chunk)
                return chunk

        class Connection:
            def __init__(self):
                self.sock = Socket()
                self.timeout = 0

            def request(self, *_args, **_kwargs):
                pass

            def getresponse(self):
                return Response()

            def close(self):
                pass

        pool = object.__new__(_HttpsConnectionPool)
        pool.prefix = ""
        pool.connections = queue.LifoQueue(maxsize=1)
        pool.connections.put(Connection())
        with tempfile.TemporaryDirectory() as directory:
            destination = Path(directory) / "source.pdf"
            received, digest = pool.get_to_file(
                "/file.pdf", destination, timeout=2, max_bytes=len(payload) + 1,
            )
            self.assertEqual(received, len(payload))
            self.assertEqual(digest, hashlib.sha256(payload).hexdigest())
            self.assertEqual(destination.read_bytes(), payload)

    def test_bale_adapter_uses_shared_update_contract(self) -> None:
        class CapturingBaleApi(BaleBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=40):
                self.calls.append((method, payload, timeout))
                return [{"update_id": 7}]

        api = CapturingBaleApi()
        self.assertEqual(api.get_updates(5, 30), [{"update_id": 7}])
        self.assertEqual(api.calls[0][0], "getUpdates")
        self.assertEqual(api.calls[0][1], {"offset": 5, "timeout": 30})

    def test_reply_keyboard_cleanup_uses_remove_keyboard_on_both_transports(self) -> None:
        for api_class in (TelegramBotApi, BaleBotApi):
            class CapturingApi(api_class):
                def __init__(self) -> None:
                    self.calls = []

                def call(self, method, payload=None, *, timeout=40):
                    self.calls.append((method, payload, timeout))
                    return {"message_id": 42}

            with self.subTest(api=api_class.__name__):
                api = CapturingApi()
                api.remove_reply_keyboard(10)
                method, payload, _timeout = api.calls[0]
                self.assertEqual(method, "sendMessage")
                self.assertEqual(payload["reply_markup"], {"remove_keyboard": True})
                self.assertEqual(payload["text"], "⌨️")
                self.assertNotIn("احراز هویت", payload["text"])
                self.assertEqual("HTML" in str(payload.get("parse_mode")), api_class is TelegramBotApi)
                self.assertEqual(api.calls[1][0], "deleteMessage")
                self.assertEqual(api.calls[1][1], {"chat_id": 10, "message_id": 42})

    def test_telegram_transport_uses_only_configured_loopback_connect_proxy(self) -> None:
        pool = _HttpsConnectionPool(
            "https://api.telegram.org",
            size=1,
            proxy_url="http://127.0.0.1:11080",
        )
        connection = pool.connections.get_nowait()
        try:
            self.assertEqual(connection.host, "127.0.0.1")
            self.assertEqual(connection.port, 11080)
            self.assertEqual(connection._tunnel_host, "api.telegram.org")
            self.assertEqual(connection._tunnel_port, 443)
        finally:
            connection.close()
        with self.assertRaises(ValueError):
            _HttpsConnectionPool(
                "https://api.telegram.org",
                size=1,
                proxy_url="http://example.test:8080",
            )

    def test_bale_adapter_translates_html_to_native_markdown_without_parse_mode(self) -> None:
        class CapturingBaleApi(BaleBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=40):
                self.calls.append((method, payload, timeout))
                return {"message_id": 9}

        screen = grades_screen("https://example.test", {
            "name": "آرین قاسم پور",
            "grades": [{
                "label": "میان‌ترم فارماکولوژی",
                "value": "7.19",
                "maxScore": "9",
            }],
            "stats": [{
                "label": "میان‌ترم فارماکولوژی",
                "classAverage": "6.56",
                "rank": 17,
                "totalWithScore": 96,
            }],
        }, platform="bale")
        api = CapturingBaleApi()
        api.send(10, screen.text, screen.keyboard)
        payload = api.calls[0][1]
        self.assertNotIn("parse_mode", payload)
        self.assertNotIn("<b>", payload["text"])
        self.assertNotIn("<blockquote", payload["text"])
        self.assertIn(" *📊 کارنامه من* ", payload["text"])
        self.assertIn("میان‌ترم فارماکولوژی", payload["text"])
        self.assertIn("▎ 🎯 نمره: `۷.۱۹ از ۹`", payload["text"])
        self.assertNotIn("```", payload["text"])
        self.assertIn("۶.۵۶", payload["text"])
        self.assertIn("۱۷ از ۹۶", payload["text"])
        self.assertIn("▎ تعداد نمره‌های ثبت‌شده:", payload["text"])

    def test_bale_adapter_preserves_links_code_quotes_and_escapes_plain_markdown(self) -> None:
        class CapturingBaleApi(BaleBotApi):
            def __init__(self) -> None:
                self.calls = []

            def call(self, method, payload=None, *, timeout=40):
                self.calls.append((method, payload, timeout))
                return {"message_id": 10}

        api = CapturingBaleApi()
        api.send(
            10,
            '<b>عنوان</b> <a href="https://example.test/a?q=1&amp;x=2">پیوند</a> '
            '<code>A_1</code><blockquote>هشدار <i>مهم</i></blockquote>متن [عادی]',
            {"inline_keyboard": []},
        )
        payload = api.calls[0][1]
        self.assertNotIn("parse_mode", payload)
        self.assertIn(" *عنوان* ", payload["text"])
        self.assertIn("[پیوند](https://example.test/a?q=1&x=2)", payload["text"])
        self.assertIn("`A_۱`", payload["text"])
        self.assertIn("▎ هشدار  _مهم_ ", payload["text"])
        self.assertIn(r"متن \[عادی\]", payload["text"])

    def test_health_result_contains_no_owner_identifier_or_token(self) -> None:
        import dent_bot.health as health
        from unittest.mock import patch

        class Settings:
            token = "known-test-token"
            owner_id = 123456
            state_db = Path("unused.sqlite3")

        class Api:
            def __init__(self, token):
                self.token = token

            def call(self, method, payload=None):
                if method == "getMe":
                    return {"username": "Dent1402Bot"}
                return [{"command": "start"}, {"command": "menu"}, {"command": "help"}]

        with patch.object(health, "load_settings", return_value=Settings()), patch.object(
            health, "TelegramBotApi", Api
        ), patch.object(Path, "is_file", return_value=False):
            result = check()
        encoded = str(result)
        self.assertNotIn("known-test-token", encoded)
        self.assertNotIn("123456", encoded)

    def test_transport_retries_without_styles_for_older_bot_api(self) -> None:
        class CompatibilityApi(TelegramBotApi):
            def __init__(self) -> None:
                self.payloads = []

            def call(self, method, payload=None, *, timeout=40):
                self.payloads.append((method, payload))
                if len(self.payloads) == 1:
                    raise BotApiError("can't parse inline keyboard button: unsupported field style")
                return {"message_id": 9}

        api = CompatibilityApi()
        screen = home("https://example.test", is_owner=True)
        result = api.send(10, screen.text, screen.keyboard)
        self.assertEqual(result["message_id"], 9)
        first_buttons = api.payloads[0][1]["reply_markup"]["inline_keyboard"]
        second_buttons = api.payloads[1][1]["reply_markup"]["inline_keyboard"]
        self.assertTrue(any("style" in item for row in first_buttons for item in row))
        self.assertFalse(any("style" in item for row in second_buttons for item in row))

    def test_expired_callback_acknowledgement_is_harmless(self) -> None:
        class ExpiredCallbackApi(TelegramBotApi):
            def __init__(self) -> None:
                pass

            def call(self, method, payload=None, *, timeout=40):
                raise BotApiError("Bad Request: query is too old and query ID is invalid")

        ExpiredCallbackApi().answer_callback("expired-callback")

    def test_main_menu_uses_semantic_button_styles(self) -> None:
        screen = home("https://example.test", is_owner=False)
        buttons = [item for row in screen.keyboard["inline_keyboard"] for item in row]
        self.assertIn("primary", {item.get("style") for item in buttons})
        self.assertIn("success", {item.get("style") for item in buttons})
        self.assertNotIn("danger", {item.get("style") for item in buttons})

    def test_owner_menu_is_not_visible_to_normal_users(self) -> None:
        normal = home("https://example.test", is_owner=False)
        owner = home("https://example.test", is_owner=True)
        normal_labels = [item["text"] for row in normal.keyboard["inline_keyboard"] for item in row]
        owner_labels = [item["text"] for row in owner.keyboard["inline_keyboard"] for item in row]
        self.assertFalse(any("مدیریت دنت‌یار" in label for label in normal_labels))
        self.assertTrue(any("مدیریت دنت‌یار" in label for label in owner_labels))

    def test_non_owner_admin_callback_fails_closed_to_home(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            api = FakeApi()
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(api, state, owner_id=10, site_url="https://example.test")
                app.handle(
                    {
                        "callback_query": {
                            "id": "callback-1",
                            "from": {"id": 20},
                            "data": "v1:admin",
                            "message": {"message_id": 7, "chat": {"id": 20, "type": "private"}},
                        }
                    }
                )
                self.assertNotIn("مدیریت دنت‌یار", api.edited[0][2])
                self.assertEqual(api.answered, [("callback-1", "", False)])
            finally:
                state.close()

    def test_offset_and_user_state_persist(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "state.sqlite3"
            first = BotState(path)
            first.save_offset(42)
            first.touch_user(100, "notes")
            first.close()
            second = BotState(path)
            try:
                self.assertEqual(second.offset(), 42)
                row = second.connection.execute(
                    "SELECT last_section FROM bot_users WHERE telegram_user_id=100"
                ).fetchone()
                self.assertEqual(row, ("notes",))
            finally:
                second.close()

    def test_telegram_and_bale_runtime_states_share_payment_offers(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            shared = root / "shared" / "payment-offers.sqlite3"
            telegram = BotState(root / "telegram.sqlite3", payment_offers_path=shared)
            bale = BotState(root / "bale.sqlite3", payment_offers_path=shared)
            try:
                created = telegram.create_payment_offer("هزینه مشترک", 750000, "برای هر دو پیام‌رسان")
                self.assertEqual(bale.payment_offer(created["ref"])["title"], "هزینه مشترک")
                bale.set_payment_offer_status(created["ref"], "inactive")
                self.assertEqual(telegram.payment_offer(created["ref"], require_active=False)["status"], "paused")
                self.assertIsNone(telegram.payment_offer(created["ref"]))
            finally:
                telegram.close()
                bale.close()

    def test_payment_offer_migration_unions_both_platform_databases(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            telegram_path = root / "telegram.sqlite3"
            bale_path = root / "bale.sqlite3"
            target = root / "shared.sqlite3"
            telegram = BotState(telegram_path)
            bale = BotState(bale_path)
            try:
                telegram_offer = telegram.create_payment_offer("تلگرام", 100000)
                bale_offer = bale.create_payment_offer("بله", 200000)
            finally:
                telegram.close()
                bale.close()
            script = Path(__file__).resolve().parents[1] / "scripts" / "migrate-payment-offers-to-shared.py"
            result = subprocess.run(
                [
                    sys.executable,
                    str(script),
                    "--telegram",
                    str(telegram_path),
                    "--bale",
                    str(bale_path),
                    "--target",
                    str(target),
                ],
                cwd=Path(__file__).resolve().parents[1],
                capture_output=True,
                text=True,
                check=False,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            runtime = BotState(root / "runtime.sqlite3", payment_offers_path=target)
            try:
                self.assertIsNotNone(runtime.payment_offer(telegram_offer["ref"]))
                self.assertIsNotNone(runtime.payment_offer(bale_offer["ref"]))
                self.assertEqual(len(runtime.payment_offers()), 2)
            finally:
                runtime.close()

    def test_protected_booklet_text_does_not_claim_screenshot_prevention(self) -> None:
        screen = section("notes", "https://example.test", is_owner=False)
        self.assertNotIn("جلوگیری از اسکرین", screen.text)
        self.assertIn("داخل همین ربات", screen.text)
        self.assertNotIn("https://example.test/notes/", str(screen.keyboard))


if __name__ == "__main__":
    unittest.main()
