from __future__ import annotations

import inspect
import unittest
from pathlib import Path

import dent_bot.bot_home_classops_ux_v2_compat as compat


class BotHomeClassOpsUxV2CompatTests(unittest.TestCase):
    def test_notification_status_includes_truthful_ack_counts(self) -> None:
        screen = compat._notification_status_with_ack_screen(
            {
                "notificationCounts": {"active": 1},
                "deliveryCounts": {"delivered": 2},
                "platformCounts": {"telegram": {"delivered": 2}, "bale": {}},
            },
            {"ack": {"eligible": 5, "acked": 3, "pending": 2, "noticeCount": 1}},
        )
        text = str(screen.text)
        self.assertIn("تأییدشده", text)
        self.assertIn("۳", text)
        self.assertIn("در انتظار تأیید", text)
        self.assertIn("۲", text)

    def test_stale_owner_callback_is_intercepted(self) -> None:
        source = inspect.getsource(compat.install_bot_home_classops_ux_v2_compat)
        self.assertIn('data == "class-operations:owner"', source)
        self.assertIn('classopsCapabilities', source)
        self.assertIn('owner_classops_screen()', source)

    def test_owner_detail_back_is_deterministic(self) -> None:
        source = inspect.getsource(compat.install_bot_home_classops_ux_v2_compat)
        self.assertIn('classops-v2:future:0', source)
        self.assertIn('↩️ آیتم‌های آینده', source)
        confirmation = compat._owner_confirmation_screen("cxo_123", "cancel")
        self.assertIn("↩️ انصراف", str(confirmation.keyboard))
        self.assertIn("v1:classops-v2:future:0", str(confirmation.keyboard))

    def test_ack_api_is_owner_only_and_read_only(self) -> None:
        root = Path(__file__).resolve().parents[2]
        source = (root / "public_html" / "api" / "classops_bot_ux_v2.php").read_text(encoding="utf-8")
        self.assertIn("classops_bot_service_owner($request)", source)
        self.assertIn("classops_stage2_owner_ack_stats", source)
        self.assertNotIn("classops_stage2_transaction", source)
        self.assertNotIn("classops_domain_store_update_item", source)
        self.assertNotIn("notifications_with_store_lock", source)

    def test_bot_api_routes_ack_status_before_generic_classops_dispatch(self) -> None:
        root = Path(__file__).resolve().parents[2]
        source = (root / "public_html" / "api" / "bot_api.php").read_text(encoding="utf-8")
        specific = source.index("classops_bot_ux_v2_action")
        generic = source.index("classops_bot_service_action")
        self.assertLess(specific, generic)


if __name__ == "__main__":
    unittest.main()
