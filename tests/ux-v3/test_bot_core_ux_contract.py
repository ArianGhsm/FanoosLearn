from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState


WORKSPACE_IDS = tuple(
    f"{index:08d}-1111-4111-8111-111111111111" for index in range(1, 12)
)


class CoreUxBackend:
    def __init__(self, *, linked: bool = True, workspaces=None, selected=None):
        self.linked = linked
        self.rows = list(workspaces or [])
        self.selected = selected
        self.unlink_calls = []

    def workspaces(self, platform, subject):
        if not self.linked:
            raise FanoosApiError("messaging_link_required", "not linked", 403)
        return {"workspaces": list(self.rows), "selected_workspace_id": self.selected}

    def select_workspace(self, platform, subject, workspace_id):
        if workspace_id not in {str(row["id"]) for row in self.rows}:
            raise FanoosApiError("workspace_forbidden", "not a member", 403)
        self.selected = workspace_id
        return {"selected_workspace_id": workspace_id}

    def unlink(self, platform, subject):
        self.unlink_calls.append((platform, str(subject)))
        self.linked = False
        return {"revoked": True}

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return {
            "items": [
                {
                    "course_title": "فیزیولوژی",
                    "starts_at": "2026-09-11T08:30:00+03:30",
                    "location_text": "کلاس ۲۰۱",
                }
            ],
            "timezone": "Asia/Tehran",
            "next_cursor": None,
        }

    def announcements(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [{"title": "ثبت‌نام آزمون میان‌ترم"}], "next_cursor": None}


def make_app(backend):
    temp = tempfile.TemporaryDirectory()
    state = LocalState(Path(temp.name) / "state.sqlite3")
    app = BotApplication(
        backend,
        state,
        "telegram",
        ApplicationConfig("https://fanoos.test/", "prod"),
    )
    return temp, state, app


class BotCoreUxContractTest(unittest.TestCase):
    def test_unlinked_subject_gets_onboarding_instead_of_technical_error(self):
        temp, state, app = make_app(CoreUxBackend(linked=False))
        try:
            for result in (app.home("student"), app.workspaces("student"), app.account("student")):
                self.assertEqual(result.screen.identifier, "onboarding.unlinked")
                self.assertIn("اتصال", result.screen.plain_text())
                self.assertNotIn("messaging_link_required", result.screen.plain_text())
        finally:
            state.close()
            temp.cleanup()

    def test_zero_workspace_has_distinct_list_empty_state_without_loop(self):
        # home() renders the one shell (docs/product/01_FRONT_DOOR.md #11's
        # correction); the standalone workspaces() switcher is a separate,
        # still-real destination (reachable from the shell's own "🏫 فضای
        # آموزشی" row) and keeps its own distinct empty-state screen.
        temp, state, app = make_app(CoreUxBackend())
        try:
            home = app.home("student")
            listing = app.workspaces("student")
            self.assertEqual(home.screen.identifier, "home.active")
            self.assertEqual(listing.screen.identifier, "workspace.empty")
            self.assertNotIn("انتخاب فضای آموزشی", listing.screen.plain_text())
        finally:
            state.close()
            temp.cleanup()

    def test_workspace_picker_is_explicit_and_paginated(self):
        rows = [{"id": wid, "name": f"فضای {index}"} for index, wid in enumerate(WORKSPACE_IDS, 1)]
        temp, state, app = make_app(CoreUxBackend(workspaces=rows))
        try:
            first = app.workspaces("student")
            self.assertEqual(first.screen.identifier, "workspace.list")
            self.assertEqual(first.screen.pagination.page, 1)
            self.assertEqual(first.screen.pagination.total_pages, 3)
            next_callback = first.screen.pagination.next.intent.compact()
            self.assertEqual(next_callback, "ws.page|p=1")
            self.assertLessEqual(len(next_callback.encode("utf-8")), 64)
            second = app.callback("student", True, next_callback)
            self.assertEqual(second.screen.identifier, "workspace.list")
            self.assertEqual(second.screen.pagination.page, 2)
            self.assertIn("فضای 6", second.screen.plain_text())
            self.assertNotIn(WORKSPACE_IDS[0], first.screen.plain_text())
            # Both nav-row actions (back and home) point at the same "home"
            # intent, and home() now always renders the one shell -- with
            # nothing selected here, that shell holds its own embedded
            # picker rather than redirecting back to this same screen.
            for navigation_action in first.screen.action_rows[-1].actions:
                callback = navigation_action.intent.compact()
                self.assertIsNotNone(callback)
                self.assertLessEqual(len(callback.encode("utf-8")), 64)
                self.assertEqual(app.callback("student", True, callback).screen.identifier, "home.active")
        finally:
            state.close()
            temp.cleanup()

    def test_home_has_bounded_primary_menu_and_live_slots(self):
        workspace = {"id": WORKSPACE_IDS[0], "name": "فضای علوم پزشکی"}
        temp, state, app = make_app(CoreUxBackend(workspaces=[workspace], selected=workspace["id"]))
        try:
            result = app.home("student")
            screen = result.screen
            self.assertEqual(screen.identifier, "home.active")
            self.assertEqual(screen.context.value, "فضای علوم پزشکی")
            text = screen.plain_text()
            self.assertIn("فیزیولوژی", text)
            self.assertIn("ثبت‌نام آزمون میان‌ترم", text)
            labels = [action.label for row in screen.action_rows for action in row.actions]
            # The bot's shell (docs/product/01_FRONT_DOOR.md) puts these
            # directly on home rather than nested under "بیشتر" -- "امروز"
            # replaces the generic schedule-hub shortcut with the legacy
            # bot's own "📅 امروز" quick-access to today's schedule.
            for required in ("درس‌ها", "امروز", "نمرات", "اعلان‌ها", "منابع", "آزمون‌ها", "خرید و دسترسی", "حساب", "فضای آموزشی", "راهنما"):
                self.assertTrue(any(required in label for label in labels), required)
            self.assertLessEqual(len(screen.action_rows), 10)
        finally:
            state.close()
            temp.cleanup()

    def test_account_unlink_is_explicit_and_backend_owned(self):
        workspace = {"id": WORKSPACE_IDS[0], "name": "فضای علوم پزشکی"}
        backend = CoreUxBackend(workspaces=[workspace], selected=workspace["id"])
        temp, state, app = make_app(backend)
        try:
            self.assertEqual(app.account("student").screen.identifier, "account.linked")
            self.assertEqual(app.unlink_confirm("student").screen.identifier, "account.unlink_confirm")
            result = app.unlink("student")
            self.assertEqual(result.screen.identifier, "account.unlink_success")
            self.assertEqual(backend.unlink_calls, [("telegram", "student")])
            self.assertNotIn(WORKSPACE_IDS[0], result.screen.plain_text())
        finally:
            state.close()
            temp.cleanup()


if __name__ == "__main__":
    unittest.main()
