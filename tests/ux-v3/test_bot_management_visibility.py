from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState


WORKSPACE = "11111111-1111-4111-8111-111111111111"


class ManagementVisibilityBackend:
    """Backend fixture where workspace membership and management authority vary
    independently per subject, matching a platform owner who deliberately has
    no workspace membership."""

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


class BotManagementVisibilityTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = ManagementVisibilityBackend()
        self.app = BotApplication(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/", "platform-primary"),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    @staticmethod
    def labels(screen):
        return [button.text for row in screen.rows for button in row]

    def test_workspaceless_owner_still_sees_management_entry_point(self):
        self.backend.subjects_that_can_manage.add("owner")
        result = self.app.more("owner", True)
        labels = self.labels(result.screen)
        self.assertIn("⚙️ مدیریت", labels)

    def test_workspaceless_owner_also_keeps_workspace_selection_affordance(self):
        self.backend.subjects_that_can_manage.add("owner")
        result = self.app.more("owner", True)
        labels = self.labels(result.screen)
        self.assertIn("🏫 انتخاب فضای آموزشی", labels)
        self.assertEqual(result.screen.presentation.semantic_kind, "workspace_required")

    def test_workspaceless_non_owner_sees_unchanged_blocked_screen(self):
        result = self.app.more("student", True)
        labels = self.labels(result.screen)
        self.assertNotIn("⚙️ مدیریت", labels)
        self.assertIn("🏫 انتخاب فضای آموزشی", labels)
        self.assertEqual(result.screen.presentation.semantic_kind, "workspace_required")

    def test_owner_with_workspace_still_sees_management_entry_point(self):
        self.backend.subjects_that_can_manage.add("owner")
        self.backend.subjects_with_workspace.add("owner")
        result = self.app.more("owner", True)
        labels = self.labels(result.screen)
        self.assertIn("⚙️ مدیریت", labels)
        self.assertIn("🎓 نمرات", labels)

    def test_deployment_overview_failure_does_not_crash_and_is_logged(self):
        self.backend.subjects_with_workspace.add("owner")
        self.backend.raise_on_overview = True
        with self.assertLogs(level="WARNING") as captured:
            result = self.app.more("owner", True)
        labels = self.labels(result.screen)
        self.assertNotIn("⚙️ مدیریت", labels)
        self.assertIn("🎓 نمرات", labels)
        self.assertTrue(
            any("deployment_overview" in message for message in captured.output)
        )


class IntegratedBotManagementVisibilityTest(unittest.TestCase):
    """Same owner/non-owner matrix, run against the class the runtime actually
    constructs (apps/telegram-bot/runtime.py imports from integrated_application,
    not application). more() is inherited unchanged, but this closes the gap that
    let a green suite hide an unreachable fix in production."""

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = ManagementVisibilityBackend()
        self.app = IntegratedBotApplication(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/", "platform-primary"),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    @staticmethod
    def labels(screen):
        return [button.text for row in screen.rows for button in row]

    def test_workspaceless_owner_still_sees_management_entry_point(self):
        self.backend.subjects_that_can_manage.add("owner")
        result = self.app.more("owner", True)
        labels = self.labels(result.screen)
        self.assertIn("⚙️ مدیریت", labels)

    def test_workspaceless_non_owner_sees_unchanged_blocked_screen(self):
        result = self.app.more("student", True)
        labels = self.labels(result.screen)
        self.assertNotIn("⚙️ مدیریت", labels)
        self.assertIn("🏫 انتخاب فضای آموزشی", labels)

    def test_owner_with_workspace_still_sees_management_entry_point(self):
        self.backend.subjects_that_can_manage.add("owner")
        self.backend.subjects_with_workspace.add("owner")
        result = self.app.more("owner", True)
        labels = self.labels(result.screen)
        self.assertIn("⚙️ مدیریت", labels)
        self.assertIn("🎓 نمرات", labels)

    def test_deployment_overview_failure_does_not_crash_and_is_logged(self):
        self.backend.subjects_with_workspace.add("owner")
        self.backend.raise_on_overview = True
        with self.assertLogs(level="WARNING") as captured:
            result = self.app.more("owner", True)
        labels = self.labels(result.screen)
        self.assertNotIn("⚙️ مدیریت", labels)
        self.assertTrue(
            any("deployment_overview" in message for message in captured.output)
        )


if __name__ == "__main__":
    unittest.main()
