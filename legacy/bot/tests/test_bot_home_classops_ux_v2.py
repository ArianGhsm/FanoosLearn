from __future__ import annotations

import inspect
import unittest
from pathlib import Path

from dent_bot import class_operations
from dent_bot.bot_home_classops_ux_v2 import (
    CANONICAL_HOME_ROWS,
    build_create_request,
    canonical_home_screen,
    classops_home_screen,
    filtered_items_screen,
    grouping_screen,
    notification_status_screen,
    owner_classops_screen,
    owner_management_screen,
    parse_jalali_datetime,
    service_status_screen,
    status_marker,
    student_notifications_screen,
    timeline_records_sorted,
    timeline_screen,
)


class BotHomeClassOpsUxV2Tests(unittest.TestCase):
    def _rows(self, screen):
        return screen.keyboard["inline_keyboard"]

    def _labels(self, screen):
        return [[button["text"] for button in row] for row in self._rows(screen)]

    def _actions(self, screen):
        rows = []
        for row in self._rows(screen):
            rows.append([
                str(button.get("callback_data") or "").removeprefix("v1:")
                for button in row
            ])
        return rows

    def test_main_menu_exact_row_order(self) -> None:
        screen = canonical_home_screen(is_owner=False)
        self.assertEqual(
            self._labels(screen),
            [
                ["🧭 مرکز نوید"],
                ["📚 جزوات", "💳 اشتراک جزوات"],
                ["📊 نمرات", "🗂 امور کلاس"],
                ["👤 حساب من", "🔔 اعلان‌ها", "❓ راهنما"],
            ],
        )
        self.assertEqual(
            self._actions(screen),
            [[action for _label, action in row] for row in CANONICAL_HOME_ROWS],
        )

    def test_telegram_main_keyboard_layout_is_shared_semantics(self) -> None:
        telegram = canonical_home_screen(is_owner=False)
        bale = canonical_home_screen(is_owner=False)
        self.assertEqual(self._labels(telegram), self._labels(bale))
        self.assertEqual(self._actions(telegram), self._actions(bale))
        self.assertEqual(len(self._labels(telegram)[3]), 3)

    def test_owner_management_is_renamed_and_website_shortcut_removed(self) -> None:
        home = canonical_home_screen(is_owner=True)
        management = owner_management_screen()
        rendered = str(home.text) + str(home.keyboard) + str(management.text) + str(management.keyboard)
        self.assertIn("🛠 مدیریت ربات", rendered)
        self.assertNotIn("مدیریت دنتیار", rendered)
        self.assertNotIn("مدیریت دنت‌یار", rendered)
        self.assertNotIn("/admin/", rendered)
        self.assertNotIn("url", str(management.keyboard))

    def test_student_classops_home_has_canonical_navigation_without_management(self) -> None:
        screen = classops_home_screen(
            [{"type": "exam", "status": "scheduled"}, {"type": "task", "status": "active"}],
            {"eligible": True, "assignment": {"group10": 3, "group10StatusLabel": "عضو", "group8": 12, "group8StatusLabel": "سرگروه"}},
        )
        self.assertEqual(
            self._labels(screen),
            [
                ["📅 ماه پیش رو", "📝 امتحان‌ها"],
                ["📌 رویدادها", "✅ کارها و ددلاین‌ها"],
                ["👥 گروه‌بندی من", "🔔 اعلان‌های امور کلاس"],
                ["↩️ بازگشت"],
            ],
        )
        self.assertNotIn("مدیریت امور کلاس", str(screen.keyboard))
        self.assertIn("گروه ۳", str(screen.text))
        self.assertIn("گروه ۱۲", str(screen.text))

    def test_owner_classops_management_only_lives_under_owner_menu(self) -> None:
        owner = owner_management_screen()
        classops_owner = owner_classops_screen()
        student = classops_home_screen([], None)
        self.assertIn("🗂 مدیریت امور کلاس", str(owner.keyboard))
        self.assertIn("➕ امتحان", str(classops_owner.keyboard))
        self.assertNotIn("مدیریت امور کلاس", str(student.keyboard))
        self.assertIn("v1:admin", str(classops_owner.keyboard))

    def test_owner_authorization_is_handler_level_not_only_button_visibility(self) -> None:
        import dent_bot.bot_home_classops_ux_v2 as ux
        source = inspect.getsource(ux._handle_v2_callback)
        self.assertIn("_owner(app, user_id)", source)
        self.assertIn('classopsCapabilities', source)
        self.assertIn('role', source)
        php = Path(__file__).resolve().parents[2] / "public_html" / "api" / "classops_bot_service.php"
        php_source = php.read_text(encoding="utf-8")
        self.assertIn("classops_bot_service_owner($request)", php_source)
        self.assertIn("classops_stage2_is_owner", php_source)

    def test_service_status_emoji_mapping_is_truthful(self) -> None:
        self.assertEqual(status_marker("ready")[0], "🟢")
        self.assertEqual(status_marker("degraded")[0], "🟡")
        self.assertEqual(status_marker("failed")[0], "🔴")
        self.assertEqual(status_marker("unknown")[0], "⚪️")
        screen = service_status_screen({"services": [{"label": "API", "state": "failed"}]})
        self.assertIn("🔴", str(screen.text))
        self.assertNotIn("🟢 <b>API", str(screen.text))

    def test_upcoming_month_chronological_order_and_overdue_semantics(self) -> None:
        records = [
            {"title": "بعدی", "sortAt": "2026-09-11T08:00:00+03:30"},
            {"title": "اول", "sortAt": "2026-09-10T08:00:00+03:30"},
            {"title": "عقب", "sortAt": "2026-09-08T08:00:00+03:30", "overdue": True},
        ]
        ordered = timeline_records_sorted(records)
        self.assertEqual([item["title"] for item in ordered], ["عقب", "اول", "بعدی"])
        screen = timeline_screen(records)
        self.assertIn("عقب‌افتاده", str(screen.text))
        self.assertIn("🔴", str(screen.text))

    def test_upcoming_month_empty_state(self) -> None:
        screen = timeline_screen([])
        self.assertIn("در ۳۰ روز آینده", str(screen.text))
        self.assertIn("↩️ امور کلاس", str(screen.keyboard))

    def test_exam_event_task_creation_validation_and_jalali_conversion(self) -> None:
        iso = parse_jalali_datetime("۱۴۰۵/۰۶/۲۰ ۱۰:۳۰")
        self.assertTrue(iso.startswith("2026-09-11T10:30:00"), iso)
        for item_type in ("exam", "event", "task", "deadline", "requirement"):
            request = build_create_request(item_type, "عنوان معتبر", "توضیح", iso)
            self.assertEqual(request["item"]["type"], item_type)
            self.assertEqual(request["item"]["cohortKey"], "dentistry-1402")
            self.assertEqual(request["destinations"], ["private_users"])
            self.assertEqual(request["audienceSpec"]["expression"], {"op": "whole_cohort"})
        with self.assertRaises(ValueError):
            parse_jalali_datetime("فردا ساعت ده")
        with self.assertRaises(ValueError):
            build_create_request("unknown", "x", "", iso)

    def test_edit_and_delete_confirmation_are_revision_and_confirmation_aware(self) -> None:
        php = (Path(__file__).resolve().parents[2] / "public_html" / "api" / "classops_bot_service.php").read_text(encoding="utf-8")
        self.assertIn("expectedRevision", php)
        self.assertIn("CLASSOPS_REVISION_CONFLICT", php)
        self.assertIn("'mode' => $mode", php)
        import dent_bot.bot_home_classops_ux_v2 as ux
        source = inspect.getsource(ux._decorate_owner_detail)
        self.assertIn("confirm-cancel", source)
        self.assertIn("confirm-archive", source)
        self.assertIn("✏️ ویرایش", source)

    def test_grouping_rendering_complete_and_unassigned(self) -> None:
        complete = grouping_screen({
            "eligible": True,
            "assignment": {"group10": 5, "group10StatusLabel": "سرگروه", "group8": 14, "group8StatusLabel": "عضو"},
        })
        self.assertIn("گروه ۵", str(complete.text))
        self.assertIn("سرگروه", str(complete.text))
        self.assertIn("گروه ۱۴", str(complete.text))
        missing = grouping_screen({"eligible": False, "assignment": None})
        self.assertIn("ثبت نشده", str(missing.text))

    def test_notification_status_truthful_mapping(self) -> None:
        screen = notification_status_screen({
            "notificationCounts": {"scheduled": 2, "active": 1},
            "deliveryCounts": {"delivered": 3, "failed": 1, "retry": 2},
            "platformCounts": {"telegram": {"delivered": 2}, "bale": {"failed": 1}},
        })
        text = str(screen.text)
        self.assertIn("🟡", text)
        self.assertIn("🟢", text)
        self.assertIn("🔴", text)
        self.assertIn("تلگرام", text)
        self.assertIn("بله", text)

    def test_student_notification_view_only_accepts_classops_feed_records(self) -> None:
        screen = student_notifications_screen({
            "data": {"items": [
                {"title": "کلاس", "source": "classops", "publishAt": "2026-09-09T08:00:00Z"},
                {"title": "پرداخت", "source": "payments", "publishAt": "2026-09-09T09:00:00Z"},
            ]}
        })
        self.assertIn("کلاس", str(screen.text))
        self.assertNotIn("پرداخت", str(screen.text))

    def test_stale_classops_callbacks_remain_compatible(self) -> None:
        self.assertEqual(class_operations._legacy_action("classops:menu"), "class-operations")
        self.assertEqual(class_operations._legacy_action("classops:items"), "class-operations:list:all")
        self.assertEqual(class_operations._legacy_action("classops:tomorrow"), "class-operations:tomorrow")

    def test_telegram_bale_entrypoints_install_same_shared_layer(self) -> None:
        root = Path(__file__).resolve().parents[1] / "dent_bot"
        telegram = (root / "service.py").read_text(encoding="utf-8")
        bale = (root / "bale_service.py").read_text(encoding="utf-8")
        needle = "install_bot_home_classops_ux_v2()"
        self.assertIn(needle, telegram)
        self.assertIn(needle, bale)

    def test_persian_text_integrity_and_callback_budget(self) -> None:
        screen = canonical_home_screen(is_owner=True)
        for row in self._rows(screen):
            for item in row:
                data = str(item.get("callback_data") or "")
                self.assertLessEqual(len(data.encode("utf-8")), 64)
        source = inspect.getsource(__import__("dent_bot.bot_home_classops_ux_v2", fromlist=["*"]))
        self.assertNotIn("\ufffd", source)
        self.assertNotIn("?مدیریت", source)

    def test_no_shadow_persistence_was_introduced(self) -> None:
        import dent_bot.bot_home_classops_ux_v2 as ux
        source = inspect.getsource(ux)
        for forbidden in ("sqlite3", "json.dump", "open(\"classops", "Path(\"classops"):
            self.assertNotIn(forbidden, source)
        php = (Path(__file__).resolve().parents[2] / "public_html" / "api" / "classops_bot_service.php").read_text(encoding="utf-8")
        self.assertNotIn("classops-shadow", php)
        self.assertIn("notifications_read_store", php)
        self.assertIn("classops_stage2_read_state", php)

    def test_event_and_task_lists_do_not_dump_internal_ids(self) -> None:
        screen = filtered_items_screen([
            {"id": "cop_123456789012345678901234", "type": "event", "title": "جلسه کلاس", "timing": {"startsAt": "2026-09-10T08:00:00+03:30"}},
        ], "events")
        self.assertIn("جلسه کلاس", str(screen.text))
        self.assertNotIn("cop_123", str(screen.text))
        self.assertIn("cop_123", str(screen.keyboard))


if __name__ == "__main__":
    unittest.main()
