import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.callbacks import CallbackCodec
from fanoos_bot.state import LocalState


class ProductBackend:
    WORKSPACE = "11111111-1111-4111-8111-111111111111"
    COURSE = "77777777-7777-4777-8777-777777777777"
    RESOURCE = "66666666-6666-4666-8666-666666666666"

    def __init__(self):
        self.selected = self.WORKSPACE
        self.delivery_issues = 0

    def workspaces(self, p, s):
        return {
            "workspaces": [{"id": self.WORKSPACE, "name": "دندان‌پزشکی ۱۴۰۲"}],
            "selected_workspace_id": self.selected,
        }

    def select_workspace(self, p, s, w):
        if w != self.WORKSPACE:
            raise RuntimeError("forbidden")
        self.selected = w
        return {"selected_workspace_id": w}

    def schedule(self, p, s, w, f, t, limit, cursor):
        items = [{
            "id": "event-1",
            "title": "کلاس ترمیمی",
            "starts_at": f + "T08:00:00+03:30",
            "ends_at": f + "T09:30:00+03:30",
            "location_text": "کلینیک",
            "course_id": self.COURSE,
            "course_code": "REST-1",
            "course_title": "ترمیمی ۱",
        }]
        if limit == 20 and cursor is None:
            return {"timezone": "Asia/Tehran", "items": items, "next_cursor": "c2"}
        return {"timezone": "Asia/Tehran", "items": items, "next_cursor": None}

    def grades(self, p, s, w, limit, cursor):
        return {"items": [{
            "result_id": "grade-1",
            "course_id": self.COURSE,
            "course_code": "REST-1",
            "course_title": "ترمیمی ۱",
            "item_title": "کوییز",
            "score": "18",
            "max_score": "20",
        }], "next_cursor": None}

    def announcements(self, p, s, w, limit, cursor):
        return {"items": [{
            "id": "announcement-1",
            "title": "تغییر کلاس",
            "body": "کلاس در کلینیک برگزار می‌شود.",
            "published_at": "2026-09-09T09:00:00+03:30",
        }], "next_cursor": None}

    def resources(self, p, s, w, limit, cursor):
        return {"items": [{
            "resource_id": self.RESOURCE,
            "resource_version_id": "version-not-shown",
            "title": "جزوه ترمیمی",
            "description": "نسخه آموزشی جلسه",
            "type_key": "booklet",
            "course_id": self.COURSE,
            "course_code": "REST-1",
            "course_title": "ترمیمی ۱",
            "delivery_supported": True,
        }], "next_cursor": None}

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject == "owner"}

    def delivery_issue(self, p, s, w, r):
        self.delivery_issues += 1
        return {"issuance_id": "33333333-3333-4333-8333-333333333333", "delivery_token": "token"}

    def delivery_consume(self, p, s, w, token):
        return {
            "issuance_id": "33333333-3333-4333-8333-333333333333",
            "content": "محتوای امن",
            "forward_protection_required": True,
        }

    def delivery_receipt(self, *a):
        return {}


class BotProductV2Test(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = ProductBackend()
        self.app = BotApplication(self.backend, self.state, "telegram", ApplicationConfig("https://fanoos.test/", "prod"))

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    @staticmethod
    def labels(screen):
        return [button.text for row in screen.rows for button in row]

    def test_home_spine_and_context(self):
        screen = self.app.home("student").screen
        labels = self.labels(screen)
        self.assertIn("📚 درس‌ها", labels)
        self.assertIn("📅 برنامه", labels)
        self.assertIn("🔔 اعلان‌ها", labels)
        self.assertIn("➕ بیشتر", labels)
        self.assertIn("دندان‌پزشکی ۱۴۰۲", screen.text)
        self.assertIn("ترمیمی ۱", screen.text)
        self.assertNotIn(self.backend.WORKSPACE, screen.text)

    def test_course_journey_has_contextual_back_and_home(self):
        detail = self.app.course_detail("student", self.backend.COURSE).screen
        self.assertIn("درس‌ها › ترمیمی ۱", detail.text)
        self.assertIn("🏠 خانه", self.labels(detail))
        schedule = self.app.course_schedule("student", self.backend.COURSE).screen
        self.assertIn("درس‌ها › ترمیمی ۱ › برنامه", schedule.text)
        self.assertIn("‹ بازگشت به درس", self.labels(schedule))
        resources = self.app.course_resources("student", self.backend.COURSE).screen
        self.assertIn("جزوه ترمیمی", resources.text)
        grades = self.app.course_grades("student", self.backend.COURSE).screen
        self.assertIn("۱۸ از ۲۰", grades.text)

    def test_missing_course_assessment_and_announcement_projections_are_honest(self):
        exam = self.app.course_assessments("student", self.backend.COURSE).screen
        self.assertIn("projection امن آزمون", exam.text)
        self.assertNotIn("امتیاز", exam.text)
        ann = self.app.course_announcements("student", self.backend.COURSE).screen
        self.assertIn("شناسه درس", ann.text)

    def test_notification_center_does_not_fake_history(self):
        screen = self.app.notifications("student").screen
        self.assertIn("NotificationPump", screen.text)
        self.assertIn("تاریخچه", screen.text)
        self.assertIn("📢 اطلاعیه‌ها", self.labels(screen))

    def test_purchase_primary_path_never_asks_for_uuid(self):
        screen = self.app.payments("student").screen
        self.assertNotIn("شناسه محصول", screen.text)
        self.assertNotIn("/buy ", screen.text)
        self.assertIn("🌐 خرید و دسترسی در فانوس", self.labels(screen))

    def test_resource_detail_has_safe_metadata_only(self):
        screen = self.app.resource_detail("student", self.backend.RESOURCE).screen
        self.assertIn("نسخه جاری مجاز", screen.text)
        self.assertNotIn(self.backend.RESOURCE, screen.text)
        self.assertNotIn("version-not-shown", screen.text)
        self.assertIn("🔒 دریافت امن", self.labels(screen))

    def test_invalid_resource_id_never_reaches_delivery_authority(self):
        result = self.app.protected_resource("student", "tampered")
        self.assertIn("منقضی", result.screen.text)
        self.assertEqual(self.backend.delivery_issues, 0)

    def test_week_pagination_uses_short_opaque_subject_bound_route(self):
        first = self.app.week_schedule("student").screen
        next_buttons = [b for row in first.rows for b in row if b.text == "بعدی ›"]
        self.assertEqual(len(next_buttons), 1)
        callback = next_buttons[0].callback
        self.assertLessEqual(len(callback.encode("utf-8")), 64)
        action, ref = CallbackCodec.decode(callback)
        self.assertEqual(action, "schp")
        self.assertNotEqual(ref, "c2")
        route = self.state.route(ref, "telegram", "student", kind="schedule_page")
        self.assertIsNotNone(route)
        self.assertEqual(route["payload"]["cursor"], "c2")
        self.assertIsNone(self.state.route(ref, "telegram", "other-user", kind="schedule_page"))
        second = self.app.callback("student", True, callback).screen
        self.assertIn("صفحه ۲", second.text)

    def test_presentation_route_survives_restart_but_is_not_authority(self):
        ref = self.state.create_route("telegram", "student", "resources_page", {"cursor": "c2", "history": [""]})
        self.state.close()
        self.state = LocalState(self.path)
        self.app = BotApplication(self.backend, self.state, "telegram", ApplicationConfig("https://fanoos.test/", "prod"))
        row = self.state.route(ref, "telegram", "student", kind="resources_page")
        self.assertEqual(row["payload"]["cursor"], "c2")
        self.assertNotIn("role", row["payload"])
        self.assertNotIn("entitlement", row["payload"])
        self.assertNotIn("payment", row["payload"])

    def test_role_aware_more_and_bale_no_management(self):
        owner = self.app.more("owner", True).screen
        self.assertIn("⚙️ مدیریت", self.labels(owner))
        student = self.app.more("student", True).screen
        self.assertNotIn("⚙️ مدیریت", self.labels(student))
        bale = BotApplication(self.backend, self.state, "bale", ApplicationConfig("https://fanoos.test/", "prod"))
        bale_more = bale.more("owner", True).screen
        self.assertNotIn("⚙️ مدیریت", self.labels(bale_more))

    def test_bale_protected_resource_fails_closed(self):
        bale = BotApplication(self.backend, self.state, "bale", ApplicationConfig("https://fanoos.test/"))
        screen = bale.protected_resource("student", self.backend.RESOURCE).screen
        self.assertIn("ارسال انجام نشد", screen.text)

    def test_callback_tampering_and_long_value_fail_safe(self):
        bad = self.app.callback("student", True, "not valid!")
        self.assertIn("معتبر نیست", bad.screen.text)
        long = "x" * 65
        with self.assertRaises(ValueError):
            CallbackCodec.decode(long)

    def test_help_is_command_minimal(self):
        text = self.app.help().screen.text
        self.assertIn("/start", text)
        self.assertIn("/home", text)
        self.assertIn("/help", text)
        self.assertNotIn("/resource", text)
        self.assertNotIn("/order", text)
        self.assertNotIn("/update_server", text)


if __name__ == "__main__":
    unittest.main()
