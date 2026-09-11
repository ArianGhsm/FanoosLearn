from __future__ import annotations

import unittest

from dent_bot.term7_group_management import (
    _assignment_summary,
    _choose_group_screen,
    _group_index,
    _group_screen,
    _home_screen,
    _owner_screen_with_term7,
    _student_screen,
)
from dent_bot.ui import Screen, button, keyboard


ROSTER = [
    {
        "studentNumber": "40211272991",
        "name": "دانشجوی الف",
        "assignment": {
            "group10": 1,
            "group8": 12,
            "group10Status": "leader",
            "group10StatusLabel": "سرگروه",
            "group8Status": "member",
            "group8StatusLabel": "عضو",
        },
    },
    {
        "studentNumber": "40211272992",
        "name": "دانشجوی ب",
        "assignment": {
            "group10": 1,
            "group8": None,
            "group10Status": "member",
            "group10StatusLabel": "عضو",
            "group8Status": "unassigned",
            "group8StatusLabel": "بدون گروه",
        },
    },
]


class Term7GroupManagementTests(unittest.TestCase):
    @staticmethod
    def callbacks(screen):
        return [
            item["callback_data"]
            for row in screen.keyboard.get("inline_keyboard", [])
            for item in row
            if isinstance(item, dict) and "callback_data" in item
        ]

    def test_student_summary_is_persian_and_status_aware(self):
        text = _assignment_summary(ROSTER[0]["assignment"])
        self.assertIn("صبح: گروه ۱ · سرگروه", text)
        self.assertIn("عصر: گروه ۱۲ · عضو", text)

    def test_group_index_is_native_rich_and_has_no_fake_table(self):
        screen = _group_index(ROSTER, "group10")
        self.assertTrue(hasattr(screen.text, "rich_html"))
        self.assertIn("<table bordered striped compact>", screen.text.rich_html)
        self.assertNotIn("<pre>", screen.text.rich_html)
        self.assertIn("دانشجوی الف", screen.text.rich_html)

    def test_callbacks_remain_within_transport_limit(self):
        screens = [
            _home_screen(ROSTER),
            _group_screen(ROSTER, "group10", 1),
            _student_screen(ROSTER[0]),
            _choose_group_screen(ROSTER[0], "group8"),
        ]
        for screen in screens:
            for callback in self.callbacks(screen):
                self.assertLessEqual(len(callback.encode("utf-8")), 64, callback)

    def test_leader_toggle_and_clear_group_are_explicit(self):
        student = _student_screen(ROSTER[0])
        labels = [item.get("text") for row in student.keyboard["inline_keyboard"] for item in row]
        self.assertIn("برداشتن سرگروهی صبح", labels)
        choose = _choose_group_screen(ROSTER[0], "group10")
        labels = [item.get("text") for row in choose.keyboard["inline_keyboard"] for item in row]
        self.assertIn("پاک‌کردن گروه", labels)

    def test_owner_screen_gets_single_term7_management_entry(self):
        def original(*args, **kwargs):
            return Screen("owner", keyboard([button("خانه", action="home")]))

        first = _owner_screen_with_term7(original)
        second = _owner_screen_with_term7(lambda: first)
        labels = [item.get("text") for row in second.keyboard["inline_keyboard"] for item in row]
        self.assertEqual(labels.count("گروه‌بندی ترم ۷"), 1)


if __name__ == "__main__":
    unittest.main()
