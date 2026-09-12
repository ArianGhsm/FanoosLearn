from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.wiring import dispatch_v3_intent

WORKSPACE = "11111111-1111-4111-8111-111111111111"


class RouteBackend:
    """Workspace membership and management authority vary independently, matching
    a platform owner who by design holds no workspace membership."""

    def __init__(self):
        self.subjects_with_workspace: set[str] = set()
        self.subjects_that_can_manage: set[str] = set()
        self.raise_on_overview = False

    def workspaces(self, platform, subject):
        if subject in self.subjects_with_workspace:
            return {
                "workspaces": [{"id": WORKSPACE, "name": "فضای نمونه"}],
                "selected_workspace_id": WORKSPACE,
            }
        return {"workspaces": [], "selected_workspace_id": None}

    def deployment_overview(self, subject, target):
        if self.raise_on_overview:
            raise RuntimeError("egress proxy unreachable")
        return {"can_manage_deployments": subject in self.subjects_that_can_manage}


def _core_action_labels(screen) -> list[str]:
    return [action.label for row in screen.action_rows for action in row.actions]


def _runtime_button_labels(screen) -> list[str]:
    return [button.text for row in screen.rows for button in row]


def _core_action_label(screen, intent_name: str) -> str | None:
    for row in screen.action_rows:
        for action in row.actions:
            if action.intent is not None and action.intent.name == intent_name:
                return action.label
    return None


class NoWorkspaceManagementRouteTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.state = LocalState(Path(self.tmp.name) / "state.sqlite3")
        self.backend = RouteBackend()
        self.app = BotApplication(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/", "platform-primary"),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    # The two tests that used to live here -- a workspace-less owner's
    # onboarding screen offering an indirect route into more(), and that
    # route actually reaching management -- covered a screen and a hop that
    # no longer exist: home() renders the one shell directly for every
    # viewer now (docs/product/01_FRONT_DOOR.md #11's correction), and an
    # owner without a class reaches management straight from /start with no
    # "more" indirection at all. That direct route is covered by
    # test_bot_home_shell.py's test_owner_without_any_class_still_reaches_management_from_start.
    # Deleted rather than weakened: their subject (the indirect route) is
    # gone, not failing.

    def test_workspaceless_owner_screen_does_not_read_as_a_hard_stop(self):
        self.backend.subjects_that_can_manage.add("owner")
        more_result = dispatch_v3_intent(self.app, "owner", True, "more", {})
        presentation = more_result.screen.presentation
        self.assertEqual(presentation.semantic_kind, "management_without_workspace")
        self.assertNotIn("ابتدا یک فضای آموزشی فعال انتخاب کنید.", more_result.screen.text)
        self.assertIn("⚙️ مدیریت", _runtime_button_labels(more_result.screen))

    def test_workspaceless_non_owner_more_screen_unchanged(self):
        more_result = dispatch_v3_intent(self.app, "student", True, "more", {})
        presentation = more_result.screen.presentation
        self.assertEqual(presentation.semantic_kind, "workspace_required")
        self.assertIn("ابتدا یک فضای آموزشی فعال انتخاب کنید.", more_result.screen.text)
        self.assertNotIn("⚙️ مدیریت", _runtime_button_labels(more_result.screen))

    def test_workspaceless_non_owner_sees_the_shell_without_management(self):
        result = self.app.home("student")
        self.assertEqual(result.screen.identifier, "home.active")
        self.assertIsNone(_core_action_label(result.screen, "core.manage"))
        labels = _core_action_labels(result.screen)
        self.assertTrue(any("فضای آموزشی" in label for label in labels))
        self.assertTrue(any("حساب" in label for label in labels))

    def test_owner_with_selected_workspace_still_reaches_management_via_home(self):
        # The bot's shell (docs/product/01_FRONT_DOOR.md) puts management
        # directly on the home menu -- "🛠 مدیریت ربات" was never behind a
        # "more" hop in the legacy bot either -- rather than nested one level
        # under "بیشتر" the way bot-01's original home screen had it.
        self.backend.subjects_that_can_manage.add("owner")
        self.backend.subjects_with_workspace.add("owner")
        home = self.app.home("owner")
        self.assertEqual(home.screen.identifier, "home.active")
        self.assertEqual(_core_action_label(home.screen, "core.manage"), "🛠 مدیریت")

        manage_result = dispatch_v3_intent(self.app, "owner", True, "core.manage", {})
        self.assertEqual(manage_result.screen.presentation.semantic_kind, "management")
        labels = _runtime_button_labels(manage_result.screen)
        self.assertIn("➕ ساخت کلاس", labels)
        self.assertIn("➕ انتصاب نماینده", labels)

    def test_deployment_overview_failure_falls_back_without_crash_and_is_logged(self):
        self.backend.subjects_that_can_manage.add("owner")
        self.backend.raise_on_overview = True
        with self.assertLogs(level="WARNING") as captured:
            result = self.app.home("owner")
        self.assertEqual(result.screen.identifier, "home.active")
        self.assertIsNone(_core_action_label(result.screen, "core.manage"))
        self.assertTrue(
            any("deployment_overview" in message for message in captured.output)
        )


if __name__ == "__main__":
    unittest.main()
