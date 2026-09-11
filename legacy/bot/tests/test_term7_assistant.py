import unittest

from dent_bot.ui import account_screen, notification_detail_screen


class Term7AssistantUiTests(unittest.TestCase):
    def test_account_dis_is_private_and_default_password_only_when_present(self):
        with_dis = account_screen(
            "https://dentistry1402tums.ir",
            platform="telegram",
            linked_user={"name": "دانشجو", "roleLabel": "دانشجو", "disNumber": "123456"},
        )
        self.assertIn("کد DIS", with_dis.text)
        self.assertIn("123456", with_dis.text)
        self.assertIn("111", with_dis.text)

        without_dis = account_screen(
            "https://dentistry1402tums.ir",
            platform="bale",
            linked_user={"name": "دانشجو", "roleLabel": "دانشجو", "disNumber": ""},
        )
        self.assertIn("ثبت نشده", without_dis.text)
        self.assertNotIn("111", without_dis.text)

    def test_food_notification_url_and_action_have_telegram_bale_parity(self):
        item = {
            "title": "🍽 یادآوری رزرو غذا",
            "body": "اگر رزرو کردی، تأیید کن.",
            "ctaLabel": "🍽 رزرو غذا",
            "ctaUrl": "http://foodstu.tums.ac.ir",
            "actions": [{"ref": "a1b2c3d4e5f6g7h8", "label": "✅ رزرو کردم", "style": "success"}],
        }
        telegram = notification_detail_screen(item, "ref123", platform="telegram", is_owner=False)
        bale = notification_detail_screen(item, "ref123", platform="bale", is_owner=False)
        self.assertEqual(telegram.keyboard, bale.keyboard)
        self.assertIn("http://foodstu.tums.ac.ir", str(telegram.keyboard))
        self.assertIn("notification-action:ref123:a1b2c3d4e5f6g7h8", str(telegram.keyboard))

    def test_notification_persian_html_is_escaped_without_breaking_structure(self):
        screen = notification_detail_screen(
            {"title": "برنامه <فردا>", "body": "📚 نظری\n• اندو 1 & عملی"},
            "ref123",
            platform="telegram",
            is_owner=False,
        )
        self.assertIn("&lt;فردا&gt;", screen.text)
        self.assertIn("&amp;", screen.text)
        self.assertIn("📚 نظری", screen.text)


if __name__ == "__main__":
    unittest.main()
