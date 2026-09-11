from __future__ import annotations

import base64
import tempfile
import unittest
from pathlib import Path

from dent_bot.runtime import dispatch_navid_group_batch
from dent_bot.state import BotState
from dent_bot.ui import navid_assignment_photo_caption, navid_screen


PNG = b"\x89PNG\r\n\x1a\npreview"
PNG_DATA_URI = "data:image/png;base64," + base64.b64encode(PNG).decode("ascii")


class PhotoApi:
    def __init__(self) -> None:
        self.photos: list[tuple] = []

    def send_photo_bytes(self, chat_id, photo, *, caption, reply_markup, filename):
        self.photos.append((chat_id, photo, caption, reply_markup, filename))
        return {"message_id": 7}


class GroupSite:
    def __init__(self) -> None:
        self.acks: list[tuple] = []

    def claim_navid_group_deliveries(self, owner_id, *, limit=3):
        del owner_id, limit
        return {
            "deliveries": [{
                "deliveryId": "ng-example-1",
                "eventType": "week",
                "screenshotDataUri": PNG_DATA_URI,
                "assignment": {
                    "title": "جلسه ۱۲، دکتر مصطفوی",
                    "courseTitle": "مبانی پروتز کامل",
                    "description": "متن تکلیف",
                    "createdDateShamsi": "۱۴۰۵/۰۴/۱۹",
                    "endDateShamsi": "۱۴۰۵/۰۶/۰۳",
                },
            }]
        }

    def ack_navid_group_delivery(self, owner_id, delivery_id, *, delivered, reason_code=""):
        self.acks.append((owner_id, delivery_id, delivered, reason_code))
        return {"success": True}


class Settings:
    owner_id = 10
    platform = "telegram"
    navid_group_enabled = True
    navid_group_chat_id = -100123


class NavidRichGroupTests(unittest.TestCase):
    def test_owner_navid_screen_is_bounded_structured_rich_text(self) -> None:
        screen = navid_screen(
            {
                "status": {"state": {}, "snapshotCounts": {"courses": 1, "assignments": 1}},
                "automation": {},
                "assignments": [{
                    "title": "جلسه ۱۲، دکتر مصطفوی، بالانس بعد از پخت و تحویل",
                    "courseTitle": "مبانی پروتز کامل (نظری)",
                    "description": "شرح تکلیف پروتز",
                    "endDateShamsi": "۱۴۰۵/۰۶/۰۳",
                }],
            },
            platform="telegram",
            site_url="https://example.test",
        )
        self.assertIn("<b><u>🎓 مرکز نوید</u></b>", screen.text)
        self.assertIn("<blockquote>📚 درس:", screen.text)
        self.assertNotIn("<pre>", screen.text)
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertIn("<th>مهلت</th>", screen.text.rich_html)
        self.assertIn("<blockquote expandable>", screen.text)
        self.assertIn("شرح تکلیف پروتز", screen.text)
        self.assertNotIn("<tg-time", screen.text)

    def test_photo_caption_has_structured_fields_and_threshold_label(self) -> None:
        caption = navid_assignment_photo_caption(
            {
                "title": "تکلیف پروتز",
                "courseTitle": "پروتز کامل",
                "description": "توضیح",
                "createdDateShamsi": "۱۴۰۵/۰۴/۱۹",
                "endDateShamsi": "۱۴۰۵/۰۶/۰۳",
            },
            event_type="week",
            platform="telegram",
        )
        self.assertIn("یک هفته تا پایان مهلت", caption)
        self.assertIn("📚 <b>درس:</b>", caption)
        self.assertIn("⏳ <b>مهلت:</b>", caption)
        self.assertNotIn("<pre>", caption)
        self.assertIn("منبع: آخرین snapshot", caption)

    def test_group_delivery_is_receipted_before_site_ack_and_not_resent(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                api = PhotoApi()
                site = GroupSite()
                first = dispatch_navid_group_batch(settings=Settings(), api=api, state=state, site_api=site)
                second = dispatch_navid_group_batch(settings=Settings(), api=api, state=state, site_api=site)
                self.assertEqual(first, {"claimed": 1, "sent": 1, "acknowledged": 1, "failed": 0})
                self.assertEqual(second, {"claimed": 1, "sent": 0, "acknowledged": 1, "failed": 0})
                self.assertEqual(len(api.photos), 1)
                self.assertEqual(api.photos[0][0], -100123)
                self.assertTrue(state.has_notification_delivery("navid-group:ng-example-1"))
                self.assertEqual(site.acks[-1], (10, "ng-example-1", True, ""))
            finally:
                state.close()

    def test_group_delivery_disabled_by_default(self) -> None:
        class Disabled(Settings):
            navid_group_enabled = False

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                api = PhotoApi()
                result = dispatch_navid_group_batch(
                    settings=Disabled(), api=api, state=state, site_api=GroupSite()
                )
                self.assertEqual(result["claimed"], 0)
                self.assertEqual(api.photos, [])
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
