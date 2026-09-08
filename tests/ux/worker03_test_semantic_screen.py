import json
import unittest

from fanoos_bot.models import Button, Screen, ScreenPresentation, SemanticSection
from fanoos_bot.presentation import (
    notification_center_screen,
    notification_detail_screen,
    render_fallback,
    semantic_screen,
)


class Worker03SemanticScreenTest(unittest.TestCase):
    def test_screen_positional_backward_compatibility(self):
        rows = ((Button("ادامه", callback="home"),),)
        screen = Screen("متن", rows, True, True)
        self.assertEqual(screen.text, "متن")
        self.assertEqual(screen.rows, rows)
        self.assertTrue(screen.edit)
        self.assertTrue(screen.protect_content)
        self.assertIsNone(screen.presentation)

    def test_semantic_metadata_is_simple_and_serializable(self):
        screen = semantic_screen(
            "🎓 نمرات من",
            "grades",
            facts=(("درس", "ترمیمی"),),
            list_items=("کوییز: ۱۸ از ۲۰",),
            sections=(SemanticSection("جزئیات", "منتشرشده"),),
        )
        self.assertIsInstance(screen.presentation, ScreenPresentation)
        encoded = json.dumps(screen.presentation.to_dict(), ensure_ascii=False)
        self.assertIn("نمرات من", encoded)
        self.assertIn('"rtl": true', encoded)

    def test_fallback_is_derived_from_semantics(self):
        presentation = ScreenPresentation(
            title="📚 منابع",
            semantic_kind="resources",
            intro="منابع قابل‌دسترسی",
            list_items=("جزوه اندو", "بانک سؤال"),
            footer="پایان",
        )
        text = render_fallback(presentation)
        self.assertEqual(
            text,
            "📚 منابع\n\nمنابع قابل‌دسترسی\n\n• جزوه اندو\n• بانک سؤال\n\nپایان",
        )

    def test_notification_helpers_are_persian_and_bounded(self):
        center = notification_center_screen(
            [{"title": "نمره جدید", "unread": True}, {"title": "اطلاعیه", "unread": False}],
            unread_count=1,
        )
        self.assertIn("🔔 مرکز اعلان‌ها", center.text)
        self.assertIn("۱ اعلان خوانده‌نشده", center.text)
        detail = notification_detail_screen(
            {
                "title": "اطلاعیه",
                "body": "متن",
                "effective_at": "2026-09-08T10:00:00+03:30",
            },
            unread=True,
        )
        self.assertIn("خوانده‌نشده", detail.text)
        self.assertIn("۲۰۲۶/۰۹/۰۸", detail.text)

    def test_title_severity_and_rtl_are_explicit(self):
        screen = semantic_screen("⚠️ هشدار", "warning", severity="warning", intro="متن")
        self.assertEqual(screen.presentation.title, "⚠️ هشدار")
        self.assertEqual(screen.presentation.severity, "warning")
        self.assertTrue(screen.presentation.rtl)
