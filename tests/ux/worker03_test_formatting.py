import unittest

from fanoos_bot.formatting import (
    format_datetime,
    format_human_number,
    format_money,
    format_time,
    isolate_ltr,
    short_sha,
    to_persian_digits,
    truncate_text,
)
from fanoos_bot.localization import (
    deployment_state_label,
    error_message,
    order_status_label,
    platform_label,
    resource_type_label,
)


class Worker03FormattingTest(unittest.TestCase):
    def test_human_numbers_use_persian_digits(self):
        self.assertEqual(format_human_number(12345), "۱۲٬۳۴۵")
        self.assertEqual(format_human_number("18.5"), "۱۸٫۵")
        self.assertEqual(format_money(1250, "IRR"), "۱٬۲۵۰ ریال")
        self.assertEqual(format_money(1250, "USD"), "۱۲٫۵ دلار آمریکا")

    def test_date_and_time_are_persian_and_parse_iso(self):
        value = "2026-09-08T08:05:00+03:30"
        self.assertEqual(format_time(value), "۰۸:۰۵")
        self.assertEqual(format_datetime(value), "۲۰۲۶/۰۹/۰۸، ۰۸:۰۵")

    def test_uuid_and_sha_are_not_persianized(self):
        uuid = "11111111-1111-4111-8111-111111111111"
        sha = "a123456789012345678901234567890123456789"
        self.assertEqual(to_persian_digits(uuid), uuid)
        self.assertEqual(to_persian_digits(sha), sha)
        rendered = short_sha(sha)
        self.assertIn("a12345678901", rendered)
        self.assertNotIn("۱۲۳", rendered)

    def test_mixed_ltr_is_isolated(self):
        rendered = isolate_ltr("FANOOS-1")
        self.assertTrue(rendered.startswith(chr(0x2066)))
        self.assertTrue(rendered.endswith(chr(0x2069)))

    def test_unicode_truncation_does_not_end_on_joiner(self):
        text = "شروع 👨‍👩‍👧 پایان"
        rendered = truncate_text(text, 9)
        self.assertTrue(rendered.endswith("…"))
        self.assertNotEqual(rendered[-2], chr(0x200D))

    def test_localization_maps_hide_raw_values(self):
        self.assertEqual(platform_label("telegram"), "تلگرام")
        self.assertEqual(platform_label("bale"), "بله")
        self.assertEqual(order_status_label("pending"), "در انتظار پرداخت")
        self.assertEqual(order_status_label("paid"), "پرداخت تأیید شده")
        self.assertEqual(deployment_state_label("HEALTHCHECK"), "بررسی سلامت")
        self.assertEqual(resource_type_label("booklet"), "جزوه")
        self.assertIn("کد اتصال تازه", error_message("link_challenge_expired", 410))
        self.assertNotIn("service_scope_denied", error_message("service_scope_denied", 403))
