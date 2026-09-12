"""Tests for the bot's one home screen and main menu.

Provenance: this shell's layout and wording were ported from the legacy Dent
bot (see ui_v3/core/home.py's home_screen() docstring); it is no longer a
second, alternative shell next to a FANOOS-native one -- that one was removed
once this became the only call site for /start's home screen, so these tests
just cover "the shell," not "the legacy shell" as opposed to something else.
"""

from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.integrated_application import ApplicationConfig, BotApplication
from fanoos_bot.join_wizard import RawKeyboardHandoff, RawKeyboardSend
from fanoos_bot.models import ActionResult, Screen as RuntimeScreen
from fanoos_bot.state import LocalState
from fanoos_bot.ui_v3.core import Screen as CoreScreen

WORKSPACE_A = "11111111-1111-4111-8111-111111111111"
WORKSPACE_B = "22222222-2222-4222-8222-222222222222"


class Backend:
    """A hand-written multi-workspace stub, in the same style as the other
    ux-v3 test backends (LearningBackend, RepresentativeBackend, ...): each
    method is keyed by workspace so tenant isolation is exercised directly,
    not assumed."""

    def __init__(self):
        self.workspaces_by_subject: dict[str, list[dict]] = {}
        self.selected_by_subject: dict[str, str | None] = {}
        self.schedule_by_workspace: dict[str, dict] = {}
        self.announcements_by_workspace: dict[str, dict] = {}
        self.grades_by_workspace: dict[str, dict] = {}
        self.forbidden_grades_workspaces: set[str] = set()
        self.can_manage_subjects: set[str] = set()
        self.representative_by_workspace: dict[str, str] = {}
        self.pending_by_workspace: dict[str, list[dict]] = {}
        self.upgrade_requests: list[tuple[str, str]] = []

    def workspaces(self, platform, subject):
        return {
            "workspaces": self.workspaces_by_subject.get(subject, []),
            "selected_workspace_id": self.selected_by_subject.get(subject),
        }

    def select_workspace(self, platform, subject, workspace_id):
        self.selected_by_subject[subject] = workspace_id

    def schedule(self, platform, subject, workspace_id, from_date, to_date, limit, cursor):
        return self.schedule_by_workspace.get(
            workspace_id, {"items": [], "courses": [], "timezone": "Asia/Tehran", "next_cursor": None}
        )

    def announcements(self, platform, subject, workspace_id, limit, cursor, course_id=None):
        return self.announcements_by_workspace.get(workspace_id, {"items": [], "next_cursor": None})

    def grades(self, platform, subject, workspace_id, limit, cursor):
        if workspace_id in self.forbidden_grades_workspaces:
            raise FanoosApiError("forbidden", "no grade.view_self", status=403)
        return self.grades_by_workspace.get(workspace_id, {"items": [], "next_cursor": None})

    def resources(self, platform, subject, workspace_id, limit, cursor):
        return {"items": [], "next_cursor": None}

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject in self.can_manage_subjects}

    def representative_requests_list(self, platform, subject, workspace_id):
        if self.representative_by_workspace.get(workspace_id) != subject:
            raise FanoosApiError("forbidden", "no membership.approve", status=403)
        return {"items": self.pending_by_workspace.get(workspace_id, [])}

    def representative_requests_approve(self, platform, subject, workspace_id, request_id):
        if self.representative_by_workspace.get(workspace_id) != subject:
            raise FanoosApiError("forbidden", "no membership.approve", status=403)
        self.pending_by_workspace[workspace_id] = [
            item for item in self.pending_by_workspace.get(workspace_id, [])
            if item.get("request_id") != request_id
        ]

    def onboarding_upgrade_request(self, platform, subject, workspace_id):
        self.upgrade_requests.append((subject, workspace_id))


def _core_actions(screen: CoreScreen):
    return [action for row in screen.action_rows for action in row.actions]


def _core_action_labels(screen: CoreScreen) -> list[str]:
    return [action.label for action in _core_actions(screen)]


def _make_app(backend: Backend):
    tmp = tempfile.TemporaryDirectory()
    state = LocalState(Path(tmp.name) / "state.sqlite3")
    app = BotApplication(backend, state, "telegram", ApplicationConfig("https://fanoos.test/", "platform-primary"))
    return tmp, state, app


class HomeShellTest(unittest.TestCase):
    def setUp(self):
        self.backend = Backend()
        self.tmp, self.state, self.app = _make_app(self.backend)

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    # 1. /start for a linked user with no workspace renders the shell's
    # equivalent screen, and offers a reachable route into the join wizard.
    def test_start_no_workspace_offers_join_route(self):
        result = self.app.start("newcomer")
        self.assertIsInstance(result.screen, CoreScreen)
        self.assertEqual(result.screen.identifier, "onboarding.linked_no_workspace")
        join_actions = [
            action for action in _core_actions(result.screen)
            if action.intent is not None and action.intent.name == "core.join.begin"
        ]
        self.assertEqual(len(join_actions), 1)

        callback = join_actions[0].intent.compact()
        begin = self.app.callback("newcomer", True, callback)
        self.assertIsInstance(begin, RawKeyboardSend)

    # 2. /start for a member of one class renders the shell home with that
    # class's real data.
    def test_start_single_class_shows_real_data(self):
        self.backend.workspaces_by_subject["student"] = [{"id": WORKSPACE_A, "name": "دانشکده دندان‌پزشکی تهران"}]
        self.backend.selected_by_subject["student"] = WORKSPACE_A
        self.backend.schedule_by_workspace[WORKSPACE_A] = {
            "items": [{"course_title": "تشریح", "starts_at": "2026-01-01T08:00:00+00:00", "location_text": "کلاس ۱"}],
            "courses": [],
            "timezone": "Asia/Tehran",
            "next_cursor": None,
        }
        self.backend.announcements_by_workspace[WORKSPACE_A] = {
            "items": [{"id": "33333333-3333-4333-8333-333333333333", "title": "امتحان میان‌ترم لغو شد"}],
            "next_cursor": None,
        }

        result = self.app.start("student")
        screen = result.screen
        self.assertIsInstance(screen, CoreScreen)
        self.assertEqual(screen.identifier, "home.active")
        self.assertIn("دانشکده دندان‌پزشکی تهران", screen.title)
        self.assertEqual(screen.context.value, "دانشکده دندان‌پزشکی تهران")
        text = screen.plain_text()
        self.assertIn("تشریح", text)
        self.assertIn("امتحان میان‌ترم لغو شد", text)
        # No management/representative rows for a plain member.
        labels = _core_action_labels(screen)
        self.assertFalse(any("مدیریت" in label for label in labels))
        self.assertFalse(any("درخواست‌های عضویت" in label for label in labels))

    # 3. A member of two classes can switch, and each screen shows only the
    # selected class's data (tenant isolation).
    def test_two_classes_switch_isolates_data(self):
        self.backend.workspaces_by_subject["dual"] = [
            {"id": WORKSPACE_A, "name": "دندان‌پزشکی تهران"},
            {"id": WORKSPACE_B, "name": "پزشکی شیراز"},
        ]
        self.backend.selected_by_subject["dual"] = WORKSPACE_A
        self.backend.announcements_by_workspace[WORKSPACE_A] = {
            "items": [{"id": "44444444-4444-4444-8444-444444444444", "title": "اطلاعیه تهران"}],
            "next_cursor": None,
        }
        self.backend.announcements_by_workspace[WORKSPACE_B] = {
            "items": [{"id": "55555555-5555-4555-8555-555555555555", "title": "اطلاعیه شیراز"}],
            "next_cursor": None,
        }

        home_a = self.app.home("dual")
        self.assertEqual(home_a.screen.context.value, "دندان‌پزشکی تهران")
        self.assertIn("اطلاعیه تهران", home_a.screen.plain_text())
        self.assertNotIn("اطلاعیه شیراز", home_a.screen.plain_text())

        self.app.select_workspace("dual", WORKSPACE_B)
        home_b = self.app.home("dual")
        self.assertEqual(home_b.screen.context.value, "پزشکی شیراز")
        self.assertIn("اطلاعیه شیراز", home_b.screen.plain_text())
        self.assertNotIn("اطلاعیه تهران", home_b.screen.plain_text())

    # 4. A limited member reaching a gated screen gets the explanatory screen
    # with the upgrade option, not a refusal and not the data.
    def test_limited_member_gated_screen_gets_upgrade_prompt_not_refusal(self):
        self.backend.workspaces_by_subject["limited"] = [{"id": WORKSPACE_A, "name": "دندان‌پزشکی تهران"}]
        self.backend.selected_by_subject["limited"] = WORKSPACE_A
        self.backend.forbidden_grades_workspaces.add(WORKSPACE_A)

        result = self.app.grades("limited")
        self.assertIsInstance(result.screen, RuntimeScreen)
        self.assertEqual(result.screen.presentation.semantic_kind, "join_upgrade_required")
        self.assertNotIn("500", result.screen.text)
        labels = [button.text for row in result.screen.rows for button in row]
        self.assertIn("✋ درخواست تأیید نماینده", labels)

        upgrade_callback = next(
            button.callback for row in result.screen.rows for button in row
            if button.text == "✋ درخواست تأیید نماینده"
        )
        # decode_v3_intent won't recognize this legacy-encoded callback; it
        # goes through the base app's CallbackCodec path instead.
        followup = self.app.join_wizard_request_upgrade("limited")
        self.assertEqual(followup.screen.presentation.semantic_kind, "join_upgrade_requested")
        self.assertEqual(self.backend.upgrade_requests, [("limited", WORKSPACE_A)])
        self.assertTrue(upgrade_callback)

    # 5. An owner still reaches class creation and representative
    # appointment from the shell.
    def test_owner_reaches_class_creation_and_representative_appointment(self):
        self.backend.workspaces_by_subject["owner"] = [{"id": WORKSPACE_A, "name": "دندان‌پزشکی تهران"}]
        self.backend.selected_by_subject["owner"] = WORKSPACE_A
        self.backend.can_manage_subjects.add("owner")

        home = self.app.home("owner")
        manage_actions = [
            action for action in _core_actions(home.screen)
            if action.intent is not None and action.intent.name == "core.manage"
        ]
        self.assertEqual(len(manage_actions), 1)

        manage_result = self.app.callback("owner", True, manage_actions[0].intent.compact())
        labels = [button.text for row in manage_result.screen.rows for button in row]
        self.assertIn("➕ ساخت کلاس", labels)
        self.assertIn("➕ انتصاب نماینده", labels)

    # 6. A representative still reaches pending requests, for their class
    # only.
    def test_representative_reaches_pending_requests_scoped_to_own_class(self):
        self.backend.workspaces_by_subject["rep"] = [{"id": WORKSPACE_A, "name": "دندان‌پزشکی تهران"}]
        self.backend.selected_by_subject["rep"] = WORKSPACE_A
        self.backend.representative_by_workspace[WORKSPACE_A] = "rep"
        self.backend.pending_by_workspace[WORKSPACE_A] = [
            {"request_id": "66666666-6666-4666-8666-666666666666", "display_name": "دانشجوی الف"}
        ]
        self.backend.representative_by_workspace[WORKSPACE_B] = "other-rep"
        self.backend.pending_by_workspace[WORKSPACE_B] = [
            {"request_id": "77777777-7777-4777-8777-777777777777", "display_name": "دانشجوی ب"}
        ]

        home = self.app.home("rep")
        rep_actions = [
            action for action in _core_actions(home.screen)
            if action.intent is not None and action.intent.name == "core.rep.requests"
        ]
        self.assertEqual(len(rep_actions), 1)

        result = self.app.callback("rep", True, rep_actions[0].intent.compact())
        labels = [button.text for row in result.screen.rows for button in row]
        self.assertTrue(any("دانشجوی الف" in label for label in labels))
        self.assertFalse(any("دانشجوی ب" in label for label in labels))

    # 7. Every menu entry either works or renders an honest not-available
    # screen -- no dead buttons.
    def test_every_home_menu_entry_resolves(self):
        self.backend.workspaces_by_subject["student"] = [{"id": WORKSPACE_A, "name": "دندان‌پزشکی تهران"}]
        self.backend.selected_by_subject["student"] = WORKSPACE_A
        home = self.app.home("student")
        for action in _core_actions(home.screen):
            with self.subTest(action=action.identifier):
                if action.url is not None:
                    self.assertTrue(action.url.startswith(("https://", "http://")))
                    continue
                value = action.intent.compact()
                self.assertIsNotNone(value, f"{action.identifier} callback does not fit in 64 bytes")
                outcome = self.app.callback("student", True, value)
                self.assertIsInstance(
                    outcome, (ActionResult, RawKeyboardSend, RawKeyboardHandoff),
                    f"{action.identifier} produced {type(outcome)!r}, not a renderable result",
                )

    # 8. Backend failures are surfaced and logged.
    def test_backend_failure_is_logged_and_degrades(self):
        class FlakyBackend(Backend):
            def deployment_overview(self, subject, target):
                raise RuntimeError("egress proxy unreachable")

        backend = FlakyBackend()
        backend.workspaces_by_subject["owner"] = [{"id": WORKSPACE_A, "name": "دندان‌پزشکی تهران"}]
        backend.selected_by_subject["owner"] = WORKSPACE_A
        tmp, state, app = _make_app(backend)
        try:
            with self.assertLogs(level="WARNING") as captured:
                result = app.home("owner")
            self.assertIsInstance(result.screen, CoreScreen)
            self.assertEqual(result.screen.identifier, "home.active")
            # The probe failure hid the management row rather than crashing home().
            self.assertFalse(any(
                action.intent is not None and action.intent.name == "core.manage"
                for action in _core_actions(result.screen)
            ))
            self.assertTrue(any("deployment_overview" in message for message in captured.output))
        finally:
            state.close()
            tmp.cleanup()


if __name__ == "__main__":
    unittest.main()
