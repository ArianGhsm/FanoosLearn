from __future__ import annotations

import tempfile
import time
import unittest
from datetime import datetime, timezone
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.persian_datetime import jalali_to_gregorian
from dent_bot.runtime import dispatch_payment_result_batch
from dent_bot.protected_media import DeliveryJob, ProtectedMediaDispatcher
from dent_bot.pdf_fingerprint import WATERMARK_VERSION
from dent_bot.state import BotState
from dent_bot.subscriptions import (
    billing_period_for,
    subscription_identity_from_directory,
)


MEHR_MIDDLE = datetime(2026, 10, 12, 8, 0, tzinfo=timezone.utc)
ABAN_START = datetime(2026, 10, 23, 0, 0, tzinfo=timezone.utc)


class Api:
    def __init__(self) -> None:
        self.sent: list[tuple] = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}


class TermSubscriptionTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        root = Path(self.temp.name)
        self.state = BotState(root / "telegram.sqlite3", payment_offers_path=root / "shared.sqlite3")
        identity = subscription_identity_from_directory({"studentNumber": "40211272010", "name": "دانشجوی تست"})
        assert identity is not None
        self.identity = identity

    def tearDown(self) -> None:
        self.state.close()
        self.temp.cleanup()

    def _paid(self, period_key: str, *, token: str = "o" * 20, delivery: str = "delivery-1") -> dict:
        policy = self.state.term_access_policy(7)
        assert policy is not None
        request_id = f"request-{period_key}".ljust(40, "x")
        self.state.begin_term_subscription_checkout(
            request_id=request_id,
            platform="telegram",
            platform_user_id=20,
            subject_key=self.identity.subject_key,
            student_number=self.identity.student_number,
            display_name=self.identity.display_name,
            term=7,
            billing_period=period_key,
            amount_rials=1_500_000,
            offer_ref=str(policy["offerRef"]),
            offer_version=int(policy["version"]),
        )
        self.state.bind_term_subscription_order(request_id, token)
        return self.state.activate_paid_term_subscription(
            order_token=token,
            delivery_id=delivery,
            platform="telegram",
            platform_user_id=20,
            amount_rials=1_500_000,
            verified_at="2026-09-24T08:00:00Z",
        )

    def test_default_policy_is_safe_and_uses_exact_persian_boundary(self) -> None:
        policy = self.state.term_access_policy(7)
        self.assertEqual(policy["monthlyPriceRials"], 1_500_000)
        self.assertEqual(policy["activeFromJalali"], "1405-07-01")
        self.assertTrue(policy["enabled"])
        self.assertEqual(jalali_to_gregorian(1405, 7, 1).isoformat(), "2026-09-23")
        self.assertEqual(jalali_to_gregorian(1405, 8, 1).isoformat(), "2026-10-23")
        self.assertEqual(self.state.complimentary_term_access(7), [])
        before = self.state.term_access_decision(self.identity.subject_key, 7, now=datetime(2026, 9, 22, 12, tzinfo=timezone.utc))
        self.assertTrue(before["allowed"])
        self.assertEqual(before["accessPath"], "open")

    def test_paid_mid_month_expires_at_next_jalali_month_and_duplicate_is_idempotent(self) -> None:
        denied = self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)
        self.assertFalse(denied["allowed"])
        entitlement = self._paid("term7-1405-07")
        duplicate = self.state.activate_paid_term_subscription(
            order_token="o" * 20,
            delivery_id="delivery-1",
            platform="telegram",
            platform_user_id=20,
            amount_rials=1_500_000,
            verified_at="2026-09-24T08:00:00Z",
        )
        self.assertEqual(entitlement["id"], duplicate["id"])
        during = self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)
        self.assertTrue(during["allowed"])
        self.assertEqual(during["accessPath"], "paid")
        after = self.state.term_access_decision(self.identity.subject_key, 7, now=ABAN_START)
        self.assertFalse(after["allowed"])
        self.assertEqual(after["latestPayment"]["billingPeriod"], "term7-1405-07")
        self._paid("term7-1405-08", token="n" * 20, delivery="delivery-aban")
        restored = self.state.term_access_decision(self.identity.subject_key, 7, now=ABAN_START)
        self.assertTrue(restored["allowed"])
        self.assertEqual(restored["billingPeriod"], "term7-1405-08")
        count = self.state.payment_connection.execute(
            "SELECT COUNT(*) FROM term_access_entitlements WHERE access_type='paid_subscription'"
        ).fetchone()[0]
        self.assertEqual(count, 2)

    def test_complimentary_revoke_is_immediate_but_paid_fallback_remains(self) -> None:
        grant = self.state.grant_complimentary_term_access(
            term=7,
            student_number=self.identity.student_number,
            display_name=self.identity.display_name,
            actor_user_id=10,
            actor_platform="telegram",
            note="test",
        )
        self.assertEqual(
            self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)["accessPath"],
            "complimentary",
        )
        self.state.revoke_complimentary_term_access(
            grant["id"], actor_user_id=10, actor_platform="telegram", note="revoked"
        )
        self.assertFalse(self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)["allowed"])
        self._paid("term7-1405-07")
        self.assertEqual(
            self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)["accessPath"],
            "paid",
        )

    def test_term_isolation_and_old_products_do_not_create_access(self) -> None:
        self.state.create_payment_offer("محصول قدیمی", 500_000)
        self.assertTrue(self.state.term_access_decision("", 8, now=MEHR_MIDDLE)["allowed"])
        self.assertFalse(self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)["allowed"])
        count = self.state.payment_connection.execute("SELECT COUNT(*) FROM term_access_entitlements").fetchone()[0]
        self.assertEqual(count, 0)

    def test_internal_subscription_offer_cannot_be_bought_or_mutated_as_a_generic_product(self) -> None:
        policy = self.state.update_term_access_policy(
            7, {"activeFromJalali": "1405-06-01"}, actor_user_id=10, actor_platform="telegram"
        )
        offer_ref = str(policy["offerRef"])
        identity = {"authenticated": True, "studentNumber": self.identity.student_number, "cohortKey": ""}
        self.assertNotIn(offer_ref, {item["ref"] for item in self.state.eligible_payment_offers(identity)})
        self.assertIsNone(self.state.payment_offer_for_user(offer_ref, identity))
        self.assertIsNone(
            self.state.update_payment_offer(
                offer_ref, {"amountRials": 9_999_990}, actor_user_id=10, actor_platform="telegram"
            )
        )
        self.assertEqual(self.state.term_access_policy(7)["monthlyPriceRials"], 1_500_000)

    def test_only_verified_success_delivery_activates_access(self) -> None:
        policy = self.state.term_access_policy(7)
        request_id = "verified-checkout-request".ljust(40, "x")
        token = "verifiedOrderToken1234567890"
        self.state.begin_term_subscription_checkout(
            request_id=request_id, platform="telegram", platform_user_id=20,
            subject_key=self.identity.subject_key, student_number=self.identity.student_number,
            display_name=self.identity.display_name, term=7, billing_period="term7-1405-07",
            amount_rials=1_500_000, offer_ref=policy["offerRef"], offer_version=policy["version"],
        )
        self.state.bind_term_subscription_order(request_id, token)

        class Settings:
            owner_id = 10
            platform = "telegram"
            payment_result_push_enabled = True
            payment_result_batch_size = 10

        class Site:
            def __init__(self) -> None:
                self.status = "pending"
                self.acks = []

            def claim_payment_result_deliveries(self, _owner, *, limit):
                return {"deliveries": [{
                    "deliveryId": "subscription-delivery-1", "platform": "telegram", "chatId": "20",
                    "order": {
                        "orderToken": token, "status": self.status, "amountRials": 1_500_000,
                        "title": "اشتراک", "verifiedAt": "2026-09-24T08:00:00Z",
                    },
                }]}

            def ack_payment_result_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
                self.acks.append((owner_id, delivery_id, delivered, reason_code))

        site = Site()
        api = Api()
        pending = dispatch_payment_result_batch(settings=Settings(), api=api, state=self.state, site_api=site)
        self.assertEqual(pending["activated"], 0)
        self.assertFalse(self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)["allowed"])
        site.status = "success"
        success = dispatch_payment_result_batch(settings=Settings(), api=api, state=self.state, site_api=site)
        self.assertEqual(success["activated"], 1)
        self.assertTrue(self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)["allowed"])
        replay = dispatch_payment_result_batch(settings=Settings(), api=api, state=self.state, site_api=site)
        self.assertEqual(replay["sent"], 0)
        self.assertEqual(len(api.sent), 1)

    def test_cross_platform_owner_receipt_is_delivered_without_activating_the_checkout(self) -> None:
        policy = self.state.term_access_policy(7)
        request_id = "bale-checkout-owner-receipt".ljust(40, "x")
        token = "baleOrderToken123456789012"
        self.state.begin_term_subscription_checkout(
            request_id=request_id, platform="bale", platform_user_id=30,
            subject_key=self.identity.subject_key, student_number=self.identity.student_number,
            display_name=self.identity.display_name, term=7, billing_period="term7-1405-07",
            amount_rials=1_500_000, offer_ref=policy["offerRef"], offer_version=policy["version"],
        )
        self.state.bind_term_subscription_order(request_id, token)

        class Settings:
            owner_id = 10
            platform = "telegram"
            payment_result_push_enabled = True
            payment_result_batch_size = 10

        class Site:
            def __init__(self) -> None:
                self.acks = []

            def claim_payment_result_deliveries(self, _owner, *, limit):
                return {"deliveries": [{
                    "deliveryId": "owner-receipt-telegram", "deliveryKind": "owner",
                    "platform": "telegram", "chatId": "10",
                    "order": {
                        "orderToken": token, "status": "success", "amountRials": 1_500_000,
                        "title": "اشتراک", "verifiedAt": "2026-09-24T08:00:00Z",
                    },
                }]}

            def ack_payment_result_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
                self.acks.append((owner_id, delivery_id, delivered, reason_code))

        api = Api()
        result = dispatch_payment_result_batch(settings=Settings(), api=api, state=self.state, site_api=Site())
        self.assertEqual(result["sent"], 1)
        self.assertEqual(result["activated"], 0)
        self.assertEqual(len(api.sent), 1)
        self.assertFalse(self.state.term_access_decision(self.identity.subject_key, 7, now=MEHR_MIDDLE)["allowed"])

    def test_renewal_notice_is_same_platform_and_deduplicated(self) -> None:
        self._paid("term7-1405-06", token="p" * 20, delivery="previous-delivery")
        notices = self.state.claim_term_renewal_notices(
            platform="telegram", now=datetime(2026, 9, 23, 8, tzinfo=timezone.utc)
        )
        self.assertEqual(len(notices), 1)
        self.state.finish_term_renewal_notice(
            term=7, billing_period="term7-1405-07", platform="telegram", platform_user_id=20, sent=True
        )
        self.assertEqual(
            self.state.claim_term_renewal_notices(
                platform="telegram", now=datetime(2026, 9, 23, 9, tzinfo=timezone.utc)
            ),
            [],
        )
        self.assertEqual(
            self.state.claim_term_renewal_notices(
                platform="bale", now=datetime(2026, 9, 23, 9, tzinfo=timezone.utc)
            ),
            [],
        )

    def test_period_keys_are_term_scoped(self) -> None:
        self.assertEqual(billing_period_for(7, MEHR_MIDDLE).key, "term7-1405-07")
        self.assertEqual(billing_period_for(8, MEHR_MIDDLE).key, "term8-1405-07")

    def test_complimentary_lookup_and_revoke_are_not_hardcoded_to_term_seven(self) -> None:
        self.state.update_term_access_policy(
            8, {}, actor_user_id=10, actor_platform="telegram", note="test policy"
        )
        grant = self.state.grant_complimentary_term_access(
            term=8, student_number=self.identity.student_number, display_name=self.identity.display_name,
            actor_user_id=10, actor_platform="telegram",
        )
        self.assertEqual(self.state.complimentary_term_access_by_id(grant["id"])["term"], 8)
        revoked = self.state.revoke_complimentary_term_access(
            grant["id"], actor_user_id=10, actor_platform="telegram", note="done"
        )
        self.assertEqual(revoked["term"], 8)
        self.assertIsNone(self.state.complimentary_term_access_by_id(grant["id"]))

    def test_cached_file_id_is_never_treated_as_permission(self) -> None:
        self.state.replace_protected_media_message(-1001234567890, 91, [{
            "contentKind": "booklet", "courseCode": "ENT", "courseName": "ENT",
            "courseTag": "ent", "term": 7, "sessionNo": 4,
            "telegramMethod": "sendDocument", "fileId": "source-file",
            "fileUniqueId": "source-unique", "fileName": "source.pdf",
            "mimeType": "application/pdf", "caption": "fixture",
        }])
        source = self.state.protected_media_for(
            course_code="ENT", term=7, session_no=4, content_kind="booklet"
        )[0]
        document_id = ProtectedMediaDispatcher._document_id(source)
        issuance = self.state.create_booklet_issuance(
            issuance_id="iss_cache_auth_test", user_id=20, source_id=source["id"],
            document_id=document_id, trace_code="TRACE-CACHE", fingerprint_hash="a" * 64,
            watermark_version=WATERMARK_VERSION, source_hash="b" * 64,
        )
        self.state.mark_booklet_issuance_processing(issuance["issuanceId"])
        self.state.complete_booklet_issuance(
            issuance["issuanceId"], telegram_file_id="cached-personalized-file"
        )

        class CacheApi:
            def __init__(self) -> None:
                self.cached_sends = 0

            def send_protected_media(self, *_args, **_kwargs):
                self.cached_sends += 1
                return {"message_id": 1}

        api = CacheApi()
        dispatcher = ProtectedMediaDispatcher(
            api=api, state=self.state, authorize=lambda _user, _source: False,
            temp_root=Path(self.temp.name) / "jobs", workers=1, max_queue=20,
        )
        try:
            with self.assertRaises(PermissionError):
                dispatcher._send_pdf(
                    DeliveryJob(20, int(source["id"]), document_id, time.monotonic()), source
                )
            self.assertEqual(api.cached_sends, 0)
        finally:
            dispatcher.close()

    def test_paid_and_complimentary_users_share_the_same_fresh_delivery_authorizer(self) -> None:
        self.state.update_term_access_policy(
            7, {"activeFromJalali": "1399-01-01"}, actor_user_id=10, actor_platform="telegram"
        )

        class AccessApi:
            @staticmethod
            def is_chat_member(_channel, _user_id):
                return True

        class AccessSite:
            @staticmethod
            def account(_user_id):
                return {
                    "linked": True, "authComplete": True,
                    "user": {"studentNumber": "40211272010", "name": "دانشجوی تست"},
                }

        app = DentBotApp(
            AccessApi(), self.state, owner_id=10, site_url="https://example.test",
            site_api=AccessSite(), platform="telegram", required_channel_username="Dent1402Booklets",
        )
        source = {"courseCode": "ENT", "term": 7}
        self.assertFalse(app.booklet_access_allowed(20, source))
        grant = self.state.grant_complimentary_term_access(
            term=7, student_number=self.identity.student_number, display_name=self.identity.display_name,
            actor_user_id=10, actor_platform="telegram",
        )
        self.assertTrue(app.booklet_access_allowed(20, source))
        self.state.revoke_complimentary_term_access(
            grant["id"], actor_user_id=10, actor_platform="telegram", note="test revoke"
        )
        self.assertFalse(app.booklet_access_allowed(20, source))
        self._paid(billing_period_for(7).key, token="c" * 20, delivery="current-period-delivery")
        self.assertTrue(app.booklet_access_allowed(20, source))


if __name__ == "__main__":
    unittest.main()
