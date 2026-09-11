from __future__ import annotations

import sqlite3
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.payments import identity_from_account, product_eligibility
from dent_bot.state import BotState
from dent_bot.ui import (
    home,
    payment_offers_screen,
    payment_offer_admin_detail_screen,
    payment_product_report_screen,
    payment_transaction_detail_screen,
    payment_transactions_screen,
)


class ApiStub:
    def __init__(self) -> None:
        self.sent: list[tuple[int, str, dict]] = []
        self.inline: list[dict] = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((int(chat_id), str(text), keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id, message_id, text, keyboard):
        self.sent.append((int(chat_id), str(text), keyboard))
        return {"message_id": message_id}

    def remove_reply_keyboard(self, chat_id, text=""):
        return {}

    def answer_callback(self, *_args, **_kwargs):
        return True

    def is_chat_member(self, *_args, **_kwargs):
        return True

    def answer_inline_query(self, query_id, results, **_kwargs):
        self.inline = list(results)
        return True


class SiteStub:
    def __init__(self, accounts=None, states=None) -> None:
        self.accounts = accounts or {}
        self.states = states or {}
        self.transaction_calls: list[dict] = []
        self.create_calls: list[dict] = []

    def account(self, user_id):
        return self.accounts.get(int(user_id), linked_account(str(user_id)))

    def payment_product_states(self, _user_id, refs):
        return {"states": {ref: dict(self.states.get(ref) or {}) for ref in refs}}

    def create_bot_payment(self, user_id, **fields):
        self.create_calls.append({"userId": int(user_id), **fields})
        return {
            "success": True,
            "orderToken": "order-token-123456789012345",
            "redirectUrl": "https://example.test/gateway/continue",
            "status": "pending",
        }

    def payment_owner_dashboard(self, _user_id):
        return {"today": {}, "week": {}, "total": {}}

    def payment_transactions(self, _user_id, **filters):
        self.transaction_calls.append(dict(filters))
        page = int(filters.get("page") or 0)
        limit = int(filters.get("limit") or 20)
        total = 150
        start = page * limit
        return {
            "items": [
                {
                    "orderId": index + 1,
                    "payerName": f"کاربر {index + 1}",
                    "title": "محصول تست",
                    "amountRials": 100000,
                    "status": "pending",
                    "statusLabel": "در انتظار",
                    "createdAt": "2026-08-29T08:00:00Z",
                }
                for index in range(start, min(start + limit, total))
            ],
            "total": total,
        }

    def payment_transaction(self, _user_id, *, order_id):
        return {"order": {"orderId": order_id, "status": "pending", "statusLabel": "در انتظار"}}


def linked_account(student="40211272010", cohort="dentistry-1402"):
    return {
        "linked": True,
        "authComplete": True,
        "user": {"studentNumber": student, "cohortKey": cohort, "name": "دانشجوی تست"},
    }


def verified_generic_account():
    return {
        "linked": False,
        "authComplete": False,
        "user": None,
        "onboardingProfile": {
            "firstName": "نیما",
            "lastName": "شرقی وند",
            "major": "پزشکی",
            "institution": "دانشگاه علوم پزشکی مشهد",
            "studentNumber": "",
            "verifiedAt": "2026-08-29T12:00:00+03:30",
            "isClassMember": False,
        },
    }


class PaymentProductsV2Tests(unittest.TestCase):
    def test_verified_non_class_user_can_open_and_create_deep_link_purchase(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            site = SiteStub(accounts={20: verified_generic_account()})
            try:
                item = state.create_payment_offer(
                    "ثبت‌نام عمومی",
                    250000,
                    "برای کاربران احراز هویت‌شده",
                    audience={"mode": "open"},
                )
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=site)
                app.handle({"message": {
                    "text": f"/start product_{item['shareToken']}",
                    "from": {"id": 20},
                    "chat": {"id": 20, "type": "private"},
                }})
                self.assertIn("ثبت‌نام عمومی", api.sent[-1][1])
                self.assertNotIn("اتصال حساب", api.sent[-1][1])

                app.handle({"callback_query": {
                    "id": "callback-generic-purchase",
                    "from": {"id": 20},
                    "data": f"v1:payment-create-link:{item['shareToken']}",
                    "message": {"message_id": 10, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(len(site.create_calls), 1)
                self.assertIn("gateway/continue", str(api.sent[-1][2]))
                self.assertNotIn("اتصال حساب", api.sent[-1][1])
            finally:
                state.close()

    def test_only_owner_and_private_payment_actions_require_canonical_link(self) -> None:
        for action in (
            "payment-confirm:offer",
            "payment-create:offer",
            "payment-create-link:token",
            "payment-status:order",
            "payments-page:1",
        ):
            self.assertFalse(DentBotApp._requires_canonical_link(action), action)
        for action in ("grades", "admin-payments", "payment-transaction:41"):
            self.assertTrue(DentBotApp._requires_canonical_link(action), action)

    def test_audience_and_lifecycle_are_backend_enforced(self) -> None:
        identity = identity_from_account(linked_account())
        now = datetime.now(timezone.utc)
        base = {"status": "active", "availableFrom": "", "expiresAt": ""}
        self.assertTrue(product_eligibility({**base, "audience": {"mode": "all"}}, identity).allowed)
        self.assertFalse(product_eligibility({**base, "audience": {"mode": "open"}}, identity).allowed)
        self.assertTrue(product_eligibility({**base, "audience": {"mode": "open"}}, identity, via_link=True).allowed)
        self.assertTrue(product_eligibility({**base, "audience": {"mode": "cohorts", "cohorts": ["dentistry-1402"]}}, identity).allowed)
        self.assertFalse(product_eligibility({**base, "audience": {"mode": "users", "studentNumbers": ["40200000000"]}}, identity).allowed)
        scheduled = {**base, "audience": {"mode": "all"}, "availableFrom": (now + timedelta(hours=1)).isoformat()}
        expired = {**base, "audience": {"mode": "all"}, "expiresAt": (now - timedelta(seconds=1)).isoformat()}
        self.assertEqual(product_eligibility(scheduled, identity).reason, "scheduled")
        self.assertEqual(product_eligibility(expired, identity).reason, "expired")

    def test_non_owner_menu_only_shows_products_when_purchasable(self) -> None:
        without = home("https://example.test", is_owner=False, has_products=False)
        with_product = home("https://example.test", is_owner=False, has_products=True)
        owner = home("https://example.test", is_owner=True, has_products=True)
        self.assertNotIn("پرداخت‌ها", str(without.keyboard))
        self.assertNotIn("محصولات", str(without.keyboard))
        self.assertIn("🛍 محصولات", str(with_product.keyboard))
        self.assertNotIn("🛍 محصولات", str(owner.keyboard))
        self.assertIn("admin-payments", str(owner.keyboard))

    def test_deep_link_denial_does_not_leak_title_or_price(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            try:
                item = state.create_payment_offer(
                    "عنوان خیلی محرمانه", 9876540,
                    audience={"mode": "users", "studentNumbers": ["40299999999"]},
                )
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=SiteStub())
                app.handle({"message": {"text": f"/start product_{item['shareToken']}", "from": {"id": 20}, "chat": {"id": 20, "type": "private"}}})
                rendered = api.sent[-1][1]
                self.assertNotIn("عنوان خیلی محرمانه", rendered)
                self.assertNotIn("987", rendered)
                self.assertIn("در دسترس این حساب نیست", rendered)
            finally:
                state.close()

    def test_quick_command_stops_at_audience_preview(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            try:
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=SiteStub())
                app.handle({"message": {"text": "/product 250000 آزمون جامع", "from": {"id": 1}, "chat": {"id": 1, "type": "private"}}})
                self.assertEqual(state.payment_offers(), [])
                dialog = state.dialog(1)
                self.assertEqual(dialog["step"], "audience")
                self.assertEqual(dialog["payload"]["amountRials"], 2_500_000)
                self.assertIn("چه کسانی", api.sent[-1][1])
            finally:
                state.close()

    def test_paid_one_time_product_has_receipt_and_no_pay_button(self) -> None:
        offer = {"ref": "x" * 20, "title": "آزمون", "amountRials": 100000, "maxPurchasesPerUser": 1}
        screen = payment_offers_screen(
            [offer],
            states={offer["ref"]: {"successCount": 1, "latestSuccessOrderToken": "o" * 24}},
        )
        self.assertIn("پرداخت شده", screen.text)
        self.assertIn("payment-status", str(screen.keyboard))
        self.assertNotIn("payment-create", str(screen.keyboard))

    def test_owner_inline_query_uses_opaque_product_token(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = ApiStub()
            try:
                item = state.create_payment_offer("آزمون اینلاین", 300000)
                app = DentBotApp(api, state, owner_id=1, site_url="https://example.test", site_api=SiteStub())
                app.handle({"inline_query": {"id": "iq-1", "query": "اینلاین", "from": {"id": 1}}})
                self.assertEqual(len(api.inline), 1)
                url = api.inline[0]["reply_markup"]["inline_keyboard"][0][0]["url"]
                self.assertIn(f"product_{item['shareToken']}", url)
                self.assertNotIn(item["ref"], url)
            finally:
                state.close()

    def test_legacy_schema_migrates_without_losing_offer(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "legacy.sqlite3"
            connection = sqlite3.connect(path)
            connection.executescript(
                "CREATE TABLE payment_offers(id INTEGER PRIMARY KEY,ref TEXT UNIQUE,title TEXT,description TEXT,amount_rials INTEGER,status TEXT,created_at TEXT,updated_at TEXT);"
                "INSERT INTO payment_offers VALUES(1,'legacy-ref-123456789','قدیمی','شرح',100000,'inactive',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP);"
            )
            connection.commit()
            connection.close()
            state = BotState(path)
            try:
                item = state.payment_offer("legacy-ref-123456789", require_active=False)
                self.assertEqual(item["title"], "قدیمی")
                self.assertEqual(item["status"], "paused")
                self.assertGreaterEqual(len(item["shareToken"]), 16)
            finally:
                state.close()

    def test_transaction_ui_has_filters_pagination_and_details(self) -> None:
        screen = payment_transactions_screen(
            {
                "items": [{
                    "orderId": 41, "payerName": "دانشجو", "title": "محصول",
                    "amountRials": 200000, "status": "pending", "statusLabel": "در انتظار",
                    "createdAt": "2026-08-29T08:00:00Z",
                }],
                "total": 12,
            },
            page=0,
            filters={"status": "pending"},
        )
        rendered = str(screen.keyboard)
        self.assertIn("payment-transaction:41", rendered)
        self.assertIn("payment-transaction-filters", rendered)
        self.assertIn("payment-transactions-page:1", rendered)
        self.assertIn("payment-tx-clear", rendered)

    def test_verified_transaction_is_immutable_in_owner_ui(self) -> None:
        pending = payment_transaction_detail_screen({"order": {"orderId": 7, "status": "pending"}})
        verified = payment_transaction_detail_screen({"order": {"orderId": 8, "status": "success"}})
        self.assertIn("payment-transaction-status:7:canceled", str(pending.keyboard))
        self.assertNotIn("payment-transaction-status", str(verified.keyboard))

    def test_product_report_exposes_period_capacity_and_conversion_context(self) -> None:
        screen = payment_product_report_screen(
            {
                "ref": "p" * 20, "title": "محصول", "amountRials": 500000,
                "capacity": 10, "expiresAt": "2026-09-10T08:00:00Z", "createdAt": "2026-08-29T08:00:00Z",
            },
            {
                "counts": {"success": 3, "uniquePayers": 3, "pending": 2, "failed": 1},
                "targetCount": 8, "unpaidCount": 5, "receivedRials": 1500000, "pendingRials": 1000000,
            },
            period="7",
        )
        self.assertIn("ظرفیت باقی", screen.text)
        self.assertIn("۷ روز", screen.text)
        self.assertIn("payment-offer-stats", str(screen.keyboard))

    def test_filtered_export_walks_all_transaction_pages(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            site = SiteStub()
            try:
                app = DentBotApp(ApiStub(), state, owner_id=1, site_url="https://example.test", site_api=site)
                app._payment_filters[1] = {"status": "success", "platform": "telegram"}
                rows = app._all_payment_transactions(1, filtered=True)
                self.assertEqual(len(rows), 150)
                self.assertEqual([call["page"] for call in site.transaction_calls], [0, 1])
                self.assertTrue(all(call["status"] == "success" for call in site.transaction_calls))
            finally:
                state.close()

    def test_owner_product_actions_fit_callback_limit_and_offer_share_fallbacks(self) -> None:
        screen = payment_offer_admin_detail_screen({
            "ref": "r" * 24, "shareToken": "s" * 22, "title": "محصول",
            "amountRials": 200000, "status": "active", "effectiveStatus": "active",
            "audience": {"mode": "users", "studentNumbers": ["40211272010"]},
        }, bot_username="Dent1402Bot", platform="telegram")
        callbacks = [
            button["callback_data"]
            for row in screen.keyboard["inline_keyboard"]
            for button in row
            if "callback_data" in button
        ]
        self.assertTrue(all(len(value.encode("utf-8")) <= 64 for value in callbacks))
        self.assertIn("payment-product-share-preview", str(screen.keyboard))
        self.assertIn("switch_inline_query", str(screen.keyboard))


if __name__ == "__main__":
    unittest.main()
