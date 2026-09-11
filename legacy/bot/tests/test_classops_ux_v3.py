from __future__ import annotations

import inspect
import unittest
from pathlib import Path

from dent_bot.classops_ux_v3 import (
    ITEM_META,
    classops_home_screen,
    daily_screen,
    detail_with_back,
    filtered_screen,
    grouping_screen,
    month_screen,
    owner_home_screen,
    owner_notification_status_screen,
    student_notifications_screen,
    weekly_screen,
    account_screen_with_academic_context,
)
from dent_bot.ui import Screen, account_screen, keyboard, button


ROOT = Path(__file__).resolve().parents[2]


def sample_item(**overrides):
    item = {
        "source": "classops", "ref": "cop_" + "a" * 24, "id": "cop_" + "a" * 24,
        "type": "event", "status": "active", "title": "جلسه ترمیمی",
        "localDate": "2026-09-09", "startsAt": "2026-09-09T08:00:00+03:30",
        "endsAt": "2026-09-09T10:00:00+03:30", "timing": {"startsAt": "2026-09-09T08:00:00+03:30"},
    }
    item.update(overrides)
    return item


class ClassOpsUxV3Tests(unittest.TestCase):
    def test_home_hierarchy_has_required_student_routes_without_management(self):
        screen = classops_home_screen([sample_item(type="exam"), sample_item(type="deadline")], {"eligible": False})
        labels = " | ".join(entry["text"] for row in screen.keyboard["inline_keyboard"] for entry in row)
        for expected in ("📅 امروز", "🗓 هفته من", "📆 ماه پیش رو", "📝 امتحان‌ها", "👥 گروه‌بندی من", "🔔 اعلان‌های امور کلاس"):
            self.assertIn(expected, labels)
        self.assertNotIn("مدیریت امور کلاس", labels)

    def test_daily_rows_are_chronological_table_like_and_emphasize_exam_deadline(self):
        day = {"localDate": "2026-09-09", "items": [
            sample_item(type="deadline", title="ددلاین", dueAt="2026-09-09T12:00:00+03:30", startsAt=""),
            sample_item(type="exam", title="امتحان", startsAt="2026-09-09T10:00:00+03:30"),
            sample_item(title="صبح", startsAt="2026-09-09T08:00:00+03:30"),
        ]}
        screen = daily_screen(day)
        self.assertIn("<code>۰۸:۰۰–۱۰:۰۰</code>", str(screen.text))
        self.assertIn("📝", str(screen.text)); self.assertIn("⏰", str(screen.text))
        self.assertLess(str(screen.text).find("صبح"), str(screen.text).find("امتحان"))
        self.assertLess(str(screen.text).find("امتحان"), str(screen.text).find("ددلاین"))
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)

    def test_empty_day_and_cancelled_item_are_explicit(self):
        self.assertIn("موردی ثبت نشده", str(daily_screen({"localDate": "2026-09-10", "items": []}).text))
        cancelled = daily_screen({"localDate": "2026-09-10", "items": [sample_item(status="cancelled")]})
        self.assertIn("❌", str(cancelled.text)); self.assertIn("لغوشده", str(cancelled.text))

    def test_long_title_is_bounded_and_escaped(self):
        title = "<unsafe>" + "الف" * 240
        screen = daily_screen({"localDate": "2026-09-09", "items": [sample_item(title=title)]})
        self.assertNotIn("<unsafe>", str(screen.text))
        self.assertIn("…", str(screen.text))

    def test_weekly_keeps_empty_days_and_navigation(self):
        days = [
            {"localDate": "2026-09-05", "items": []},
            {"localDate": "2026-09-06", "items": [sample_item(localDate="2026-09-06")]},
        ]
        screen = weekly_screen(days, 0)
        self.assertIn("بدون مورد", str(screen.text))
        self.assertIn("هفته بعد", str(screen.keyboard))
        self.assertLess(str(screen.text).find("شنبه"), str(screen.text).find("یکشنبه"))

    def test_crowded_daily_is_paginated_and_message_budgeted(self):
        items = [sample_item(ref="cop_" + f"{i:024x}", id="cop_" + f"{i:024x}", title=("آیتم شلوغ " + str(i) + " " + "الف" * 140)) for i in range(19)]
        day = {"localDate": "2026-09-09", "items": items}
        first = daily_screen(day, page=0)
        second = daily_screen(day, page=1)
        self.assertIn("صفحه ۱ از ۳", str(first.text))
        self.assertIn("موارد بعدی", str(first.keyboard))
        self.assertIn("موارد قبلی", str(second.keyboard))
        self.assertLess(len(str(first.text)), 3500)
        self.assertLess(len(first.text.rich_html), 7000)
        self.assertIn("p0", str(first.keyboard))

    def test_weekly_crowded_days_are_preview_bounded_with_day_drilldown(self):
        items = [sample_item(ref="cop_" + f"{i:024x}", id="cop_" + f"{i:024x}", title=f"مورد {i}") for i in range(12)]
        screen = weekly_screen([{"localDate": "2026-09-09", "items": items}], 0)
        self.assertIn("۹ مورد دیگر", str(screen.text))
        self.assertIn("نمای روزانه", str(screen.text))
        self.assertIn("c3:d:2026-09-09", str(screen.keyboard))
        self.assertLess(len(str(screen.text)), 3500)

    def test_month_groups_days_and_paginates_with_drilldown(self):
        days = [{"localDate": f"2026-09-{day:02d}", "items": [sample_item(localDate=f"2026-09-{day:02d}")] if day % 2 else []} for day in range(9, 20)]
        screen = month_screen(days, 0)
        self.assertIn("📆 ماه پیش رو", str(screen.text))
        self.assertIn("بعدی", str(screen.keyboard))
        self.assertIn("c3:d:2026-09-09", str(screen.keyboard))

    def test_detail_back_is_deterministic_for_day(self):
        base = Screen("detail", keyboard([button("↩️ فهرست", action="class-operations:list:all")]))
        screen = detail_with_back(base, "d20260909")
        self.assertIn("c3:d:2026-09-09", str(screen.keyboard))
        self.assertIn("همان روز", str(screen.keyboard))

    def test_grouping_full_and_missing_data(self):
        full = grouping_screen({"eligible": True, "academicTerm": {"termLabel": "ترم ۷"}, "scheduleContext": {"rotationLabel": "روتیشن اول", "currentPractical": [{"title": "ترمیمی عملی ۲"}], "rotationPeriod": {"from": "1405/06/28", "through": "1405/08/20"}, "nextPractical": {"date": "1405/06/29", "events": [{"title": "جراحی عملی ۲"}]}}, "groups": {
            "morning": {"group": 3, "leaderName": "سارا", "members": ["سارا", "مریم"]},
            "afternoon": {"group": 12, "leaderName": "علی", "members": ["علی"]},
        }})
        self.assertIn("گروه ۳", str(full.text)); self.assertIn("سارا", str(full.text)); self.assertIn("ترمیمی عملی ۲", str(full.text))
        self.assertIn("۱۴۰۵/۰۶/۲۸", str(full.text)); self.assertIn("برنامه بعدی", str(full.text)); self.assertIn("جراحی عملی ۲", str(full.text))
        missing = grouping_screen({"eligible": True, "academicTerm": {"termLabel": "ترم ۷"}, "scheduleContext": {}, "groups": {}})
        self.assertIn("بدون گروه", str(missing.text))
        self.assertIn("ترم ۷", str(missing.text))

    def test_target_account_gets_academic_context_non_target_does_not(self):
        academic = {"eligible": True, "academicTerm": {"termLabel": "ترم ۷", "state": "active"}, "scheduleContext": {"inSchedule": False}, "groups": {
            "morning": {"group": 3, "leaderName": "سارا"}, "afternoon": {"group": 12, "leaderName": "علی"},
        }}
        target_user = {"name": "دانشجو", "cohortKey": "dentistry-1402"}
        target_base = account_screen("https://example.test", platform="telegram", linked_user=target_user)
        target = account_screen_with_academic_context(target_base, target_user, academic)
        self.assertIn("🎓 وضعیت تحصیلی", str(target.text)); self.assertIn("ترم ۷", str(target.text)); self.assertIn("هنوز شروع نشده", str(target.text))
        other_user = {"name": "دانشجو", "cohortKey": "other"}
        other_base = account_screen("https://example.test", platform="telegram", linked_user=other_user)
        other = account_screen_with_academic_context(other_base, other_user, academic)
        self.assertNotIn("🎓 وضعیت تحصیلی", str(other.text)); self.assertNotIn("گروه‌بندی من", str(other.keyboard))

    def test_missing_target_assignment_does_not_fabricate_group(self):
        academic = {"eligible": True, "academicTerm": {"termLabel": "ترم ۷", "state": "active"}, "scheduleContext": {"inSchedule": False}, "groups": {}}
        target_user = {"name": "دانشجو", "cohortKey": "dentistry-1402"}
        base = account_screen("https://example.test", platform="bale", linked_user=target_user)
        screen = account_screen_with_academic_context(base, target_user, academic)
        self.assertIn("ترم ۷", str(screen.text)); self.assertIn("<b>—</b>", str(screen.text))

    def test_notification_status_semantics_cover_delivery_states_and_ack(self):
        payload = {"deliveries": [
            {"itemTitle": "الف", "status": "delivered", "platform": "telegram", "destination": "class", "scheduledAt": "2026-09-09T08:00:00Z"},
            {"itemTitle": "ب", "status": "pending", "platform": "bale", "destination": "class", "scheduledAt": "2026-09-09T09:00:00Z"},
            {"itemTitle": "ج", "status": "failed", "platform": "telegram", "destination": "class", "scheduledAt": "2026-09-09T10:00:00Z"},
            {"itemTitle": "د", "status": "mystery", "platform": "", "destination": "", "scheduledAt": ""},
        ]}
        screen = owner_notification_status_screen(payload, {"ack": {"acked": 2, "pending": 1}, "notices": [{"title": "اطلاعیه فوری", "acked": 2, "pending": 1}]})
        for marker in ("🟢", "🟡", "🔴", "⚪️"): self.assertIn(marker, str(screen.text))
        self.assertIn("تأیید", str(screen.text)); self.assertIn("اطلاعیه فوری", str(screen.text)); self.assertIn("تلگرام", str(screen.text)); self.assertIn("بله", str(screen.text))
        self.assertNotIn("group.telegram", str(screen.text)); self.assertNotIn("private.bale", str(screen.text))

    def test_student_notifications_only_show_classops(self):
        screen = student_notifications_screen({"data": {"items": [
            {"title": "کلاس", "source": "classops", "effectiveAt": "2026-09-09T08:00:00Z"},
            {"title": "غیرمرتبط", "source": "payments"},
        ]}})
        self.assertIn("کلاس", str(screen.text)); self.assertNotIn("غیرمرتبط", str(screen.text))

    def test_telegram_bale_semantics_are_shared_and_rich_has_fallback(self):
        day = {"localDate": "2026-09-09", "items": [sample_item()]}
        screen = daily_screen(day)
        self.assertTrue(hasattr(screen.text, "rich_html")); self.assertIn("<table", screen.text.rich_html)
        service = (ROOT / "bot_runtime/dent_bot/service.py").read_text(encoding="utf-8")
        bale = (ROOT / "bot_runtime/dent_bot/bale_service.py").read_text(encoding="utf-8")
        self.assertIn("install_classops_ux_v3()", service); self.assertIn("install_classops_ux_v3()", bale)

    def test_owner_management_has_structured_views_and_mutation_entries(self):
        screen = owner_home_screen()
        rendered = " | ".join(entry["text"] for row in screen.keyboard["inline_keyboard"] for entry in row)
        for label in ("📅 امروز", "🗓 هفته جاری", "📆 ماه پیش رو", "🔔 وضعیت اعلان‌ها", "👥 مرور گروه‌بندی", "➕ امتحان"):
            self.assertIn(label, rendered)
        self.assertIn("↩️ مدیریت ربات", rendered)

    def test_v3_user_surfaces_do_not_leak_internal_english_terms(self):
        screens = [
            classops_home_screen([], None),
            grouping_screen({"eligible": False}),
            student_notifications_screen({"data": {"items": []}}),
            owner_notification_status_screen({"deliveries": []}),
            owner_home_screen(),
        ]
        rendered = "\n".join(str(screen.text) for screen in screens)
        for forbidden in ("canonical", "mutation", "delivery", "ClassOps", "Term 7", "Rotation", "Group", "Status"):
            self.assertNotIn(forbidden, rendered)

    def test_canonical_event_type_mapping_is_consistent(self):
        self.assertEqual(ITEM_META["exam"], ("📝", "امتحان"))
        self.assertEqual(ITEM_META["practical"], ("🦷", "کارآموزی"))
        self.assertEqual(ITEM_META["theory"], ("📚", "جلسه آموزشی"))
        self.assertEqual(ITEM_META["class_change"], ("⚠️", "تغییر مهم"))

    def test_read_side_v3_has_no_shadow_persistence_or_mutation_calls(self):
        py_source = inspect.getsource(__import__("dent_bot.classops_ux_v3", fromlist=["*"]))
        php_source = (ROOT / "public_html/api/classops_bot_ux_v3.php").read_text(encoding="utf-8")
        for forbidden in ("sqlite3", "json.dump", "Path(\"classops", "classops_stage2_transaction", "classops_domain_store_update_item"):
            self.assertNotIn(forbidden, py_source + php_source)
        self.assertIn("classops_read_store()", php_source)
        self.assertIn("classops_stage2_student_item_projection", php_source)

    def test_callback_payloads_fit_shared_safe_limit(self):
        screens = [classops_home_screen([], None), owner_home_screen(), month_screen([{"localDate": "2026-09-09", "items": [sample_item()]}], 0)]
        for screen in screens:
            for row in screen.keyboard["inline_keyboard"]:
                for entry in row:
                    raw = str(entry.get("callback_data") or "")
                    if raw: self.assertLessEqual(len(raw.encode("utf-8")), 64)

    def test_no_user_facing_robot_misspelling_remains(self):
        for folder in (ROOT / "bot_runtime/dent_bot", ROOT / "bot_runtime/docs"):
            for path in folder.rglob("*"):
                if path.is_file() and path.suffix in {".py", ".md", ".txt"}:
                    self.assertNotIn("روبات", path.read_text(encoding="utf-8", errors="ignore"), str(path))
