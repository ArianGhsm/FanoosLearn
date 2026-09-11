from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.runtime import dispatch_payment_result_batch
from dent_bot.state import BotState
from dent_bot.ui import (
    bot_start_url,
    payment_created_screen,
    payment_status_screen,
    payment_success_push_screen,
    payment_owner_success_push_screen,
)


TOKEN = "orderToken_123456789012345678901234"


class FakeApi:
    def __init__(self) -> None:
        self.sent: list[tuple] = []

    def send(self, chat_id, text, reply_markup):
        self.sent.append((chat_id, text, reply_markup))
        return {"message_id": len(self.sent)}


class PaymentReturnTests(unittest.TestCase):
    def test_platform_deep_links_preserve_the_origin_surface(self) -> None:
        self.assertEqual(
            bot_start_url("Dent1402Bot", f"receipt_{TOKEN}", platform="telegram"),
            f"https://t.me/Dent1402Bot?start=receipt_{TOKEN}",
        )
        self.assertEqual(
            bot_start_url("dent1402bot", f"receipt_{TOKEN}", platform="bale"),
            f"https://ble.ir/dent1402bot?start=receipt_{TOKEN}",
        )
        self.assertEqual(bot_start_url("Dent1402Bot", "bad value", platform="telegram"), "")

    def test_gateway_result_screen_is_not_the_bot_purchase_destination(self) -> None:
        created = payment_created_screen(
            {
                "orderToken": TOKEN,
                "amountRials": 300000,
                "redirectUrl": "https://gateway.example.test/start",
            },
            platform="bale",
            return_to_bot_enabled=True,
        )
        self.assertIn("همین ربات", created.text)
        self.assertIn("بله", created.text)
        status = payment_status_screen(
            {
                "status": "success",
                "amountRials": 300000,
                "resultUrl": "https://site.example.test/buy/result",
            },
            platform="bale",
            order_token=TOKEN,
            return_to_bot_enabled=True,
        )
        self.assertNotIn("رسید سایت", status.text)
        self.assertNotIn("site.example.test", str(status.keyboard))
        self.assertIn("بله", status.text)

    def test_receipt_start_fetches_canonical_status_in_the_same_bot(self) -> None:
        class Site:
            def __init__(self) -> None:
                self.calls = []

            def account(self, _user_id):
                return {"success": True, "linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}

            def payment_status(self, user_id, order_token):
                self.calls.append((user_id, order_token))
                return {"success": True, "status": "success", "amountRials": 300000}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = Site()
            try:
                app = DentBotApp(
                    api,
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=site,
                    platform="telegram",
                )
                app.handle(
                    {
                        "message": {
                            "text": f"/start receipt_{TOKEN}",
                            "from": {"id": 20},
                            "chat": {"id": 20, "type": "private"},
                        }
                    }
                )
                self.assertEqual(site.calls, [(20, TOKEN)])
                self.assertIn("پرداخت موفق", api.sent[0][1])
                self.assertNotIn("رسید سایت", str(api.sent[0]))
            finally:
                state.close()

    def test_success_push_rejects_unverified_status(self) -> None:
        with self.assertRaises(ValueError):
            payment_success_push_screen(
                {"status": "pending", "orderToken": TOKEN, "amountRials": 300000},
                platform="telegram",
            )

    def test_verified_receipt_contains_tracking_time_and_fulfillment(self) -> None:
        payload = {
            "status": "success", "orderToken": TOKEN, "title": "دسترسی ویژه",
            "amountRials": 300000, "verifiedAt": "2026-08-29T08:00:00Z",
            "trackingRef": "TRACK-42",
            "fulfillment": {"text": "دسترسی شما فعال شد.", "url": "https://example.test/access"},
        }
        screen = payment_success_push_screen(payload, platform="telegram")
        self.assertIn("TRACK-42", screen.text)
        self.assertIn("دسترسی شما فعال شد", screen.text)
        self.assertIn("https://example.test/access", str(screen.keyboard))

    def test_owner_financial_notice_has_owner_actions(self) -> None:
        screen = payment_owner_success_push_screen({
            "status": "success", "payerName": "دانشجو", "studentNumber": "40211272010",
            "title": "محصول", "amountRials": 300000, "offerRef": "x" * 20,
            "verifiedAt": "2026-08-29T08:00:00Z", "trackingRef": "T-1",
        })
        self.assertIn("پرداخت جدید", screen.text)
        self.assertIn("payment-transactions", str(screen.keyboard))
        self.assertIn("payment-offer", str(screen.keyboard))

    def test_payment_delivery_is_same_platform_and_idempotent(self) -> None:
        class Settings:
            owner_id = 10
            platform = "telegram"
            payment_result_push_enabled = True
            payment_result_batch_size = 10

        class Site:
            def __init__(self) -> None:
                self.acks = []

            def claim_payment_result_deliveries(self, _owner_id, *, limit):
                self.limit = limit
                return {
                    "success": True,
                    "deliveries": [
                        {
                            "deliveryId": "prd_12345678901234567890",
                            "platform": "telegram",
                            "chatId": "20",
                            "order": {
                                "orderToken": TOKEN,
                                "status": "success",
                                "title": "ثبت‌نام آزمون",
                                "amountRials": 300000,
                            },
                        }
                    ],
                }

            def ack_payment_result_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
                self.acks.append((owner_id, delivery_id, delivered, reason_code))
                return {"success": True}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = Site()
            try:
                first = dispatch_payment_result_batch(settings=Settings(), api=api, state=state, site_api=site)
                second = dispatch_payment_result_batch(settings=Settings(), api=api, state=state, site_api=site)
                self.assertEqual(first["sent"], 1)
                self.assertEqual(second["sent"], 0)
                self.assertEqual(second["acknowledged"], 1)
                self.assertEqual(len(api.sent), 1)
                self.assertTrue(state.has_notification_delivery("payment-result:prd_12345678901234567890"))
            finally:
                state.close()

    def test_cross_platform_delivery_fails_closed(self) -> None:
        class Settings:
            owner_id = 10
            platform = "bale"
            payment_result_push_enabled = True
            payment_result_batch_size = 10

        class Site:
            def __init__(self) -> None:
                self.acks = []

            def claim_payment_result_deliveries(self, _owner_id, *, limit):
                return {
                    "success": True,
                    "deliveries": [{
                        "deliveryId": "prd_cross_platform_123",
                        "platform": "telegram",
                        "chatId": "20",
                        "order": {"orderToken": TOKEN, "status": "success", "amountRials": 300000},
                    }],
                }

            def ack_payment_result_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
                self.acks.append((owner_id, delivery_id, delivered, reason_code))
                return {"success": True}

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = Site()
            try:
                result = dispatch_payment_result_batch(settings=Settings(), api=api, state=state, site_api=site)
                self.assertEqual(result["failed"], 1)
                self.assertEqual(api.sent, [])
                self.assertEqual(site.acks[0][2:], (False, "INVALID_DELIVERY"))
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
