from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState

WS1 = "11111111-1111-4111-8111-111111111111"
WS2 = "22222222-2222-4222-8222-222222222222"
USER1 = "33333333-3333-4333-8333-333333333333"
USER2 = "44444444-4444-4444-8444-444444444444"
USER3 = "55555555-5555-4555-8555-555555555555"
REQ1 = "66666666-6666-4666-8666-666666666666"


class RepresentativeBackend:
    """Records every appointment/approval call, mirroring the real
    WorkspacePlatformService/ClassMembershipService contract: appointment is
    owner-gated (deployment_overview), approval/decline is representative-
    gated (membership.approve, surfaced here as forbidden for non-reps)."""

    def __init__(self):
        self.subjects_that_can_manage: set[str] = set()
        self.representative_subjects: set[str] = set()

        self.workspaces_by_page = {
            None: {"items": [{"id": WS1, "name": "دندانپزشکی ۱۴۰۲"}], "next_cursor": "cursor-2"},
            "cursor-2": {"items": [{"id": WS2, "name": "پزشکی ۱۴۰۱"}], "next_cursor": None},
        }
        self.candidates_by_workspace = {
            WS1: {"items": [{"user_id": USER1, "display_name": "آرین"}, {"user_id": USER2, "display_name": "سارا"}], "next_cursor": None},
        }
        self.appoint_calls: list[tuple] = []
        self.appoint_error: Exception | None = None

        self.pending_by_workspace: dict[str, list[dict]] = {
            WS1: [{"request_id": REQ1, "user_id": USER3, "display_name": "محمد", "requested_at": "2026-01-01"}],
        }
        self.approve_calls: list[tuple] = []
        self.decline_calls: list[tuple] = []

        self.workspaces_result: dict = {"workspaces": [{"id": WS1, "name": "دندانپزشکی ۱۴۰۲"}], "selected_workspace_id": WS1}

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject in self.subjects_that_can_manage}

    def workspaces(self, platform, subject):
        return dict(self.workspaces_result)

    def select_workspace(self, platform, subject, workspace_id):
        return {}

    def representative_workspaces(self, platform, subject, limit=10, cursor=None):
        return dict(self.workspaces_by_page.get(cursor) or {"items": [], "next_cursor": None})

    def representative_candidates(self, platform, subject, workspace_id, limit=10, cursor=None):
        return dict(self.candidates_by_workspace.get(workspace_id) or {"items": [], "next_cursor": None})

    def representative_appoint(self, platform, subject, workspace_id, target_user_id):
        self.appoint_calls.append((platform, subject, workspace_id, target_user_id))
        if self.appoint_error is not None:
            raise self.appoint_error
        return {"assignment_id": "assignment-1"}

    def representative_requests_list(self, platform, subject, workspace_id):
        if subject not in self.representative_subjects:
            raise FanoosApiError("forbidden", "اجازه انجام این عملیات را ندارید.", 403)
        return {"items": list(self.pending_by_workspace.get(workspace_id) or [])}

    def representative_requests_approve(self, platform, subject, workspace_id, request_id):
        self.approve_calls.append((platform, subject, workspace_id, request_id))
        self.pending_by_workspace[workspace_id] = [
            item for item in self.pending_by_workspace.get(workspace_id, []) if item["request_id"] != request_id
        ]
        return {"status": "approved", "already": False}

    def representative_requests_decline(self, platform, subject, workspace_id, request_id):
        self.decline_calls.append((platform, subject, workspace_id, request_id))
        self.pending_by_workspace[workspace_id] = [
            item for item in self.pending_by_workspace.get(workspace_id, []) if item["request_id"] != request_id
        ]
        return {"status": "declined", "already": False}


class _BaseRepresentativeTest:
    app_class = BotApplication

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = RepresentativeBackend()
        self.app = self.app_class(
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

    def _begin_appoint(self, subject="owner"):
        self.backend.subjects_that_can_manage.add(subject)
        return self.app.callback(subject, True, "repnew")

    # 1) Only an owner sees the entry point; a non-owner never does, and a
    # direct attempt is refused.
    def test_appointment_entry_point_appears_only_for_owner(self):
        self.backend.subjects_that_can_manage.add("owner")
        management = self.app.management("owner", True)
        self.assertIn("➕ انتصاب نماینده", self.labels(management.screen))

        denied_management = self.app.management("student", True)
        self.assertEqual(denied_management.screen.presentation.semantic_kind, "management_denied")

        direct = self.app.callback("student", True, "repnew")
        self.assertEqual(direct.screen.presentation.semantic_kind, "appoint_wizard_denied")
        self.assertIsNone(self.state.appoint_wizard("telegram", "student"))

    # 2) Full walkthrough: pick workspace, pick candidate, confirm -- the
    # backend receives exactly the ids chosen.
    def test_appoint_wizard_full_walkthrough(self):
        first_page = self._begin_appoint()
        self.assertEqual(first_page.screen.presentation.semantic_kind, "appoint_wizard_workspace")
        self.assertIn("بعدی ›", self.labels(first_page.screen))

        pick_ws_ref = first_page.screen.rows[0][0].callback
        candidates = self.app.callback("owner", True, pick_ws_ref)
        self.assertEqual(candidates.screen.presentation.semantic_kind, "appoint_wizard_candidate")
        self.assertIn("آرین", self.labels(candidates.screen))

        pick_candidate_ref = candidates.screen.rows[0][0].callback
        confirm = self.app.callback("owner", True, pick_candidate_ref)
        self.assertEqual(confirm.screen.presentation.semantic_kind, "appoint_wizard_confirm")
        self.assertIn("آرین", confirm.screen.text)
        self.assertIn("دندانپزشکی ۱۴۰۲", confirm.screen.text)

        created = self.app.callback("owner", True, "apconfirm")
        self.assertEqual(created.screen.presentation.semantic_kind, "appoint_wizard_created")
        self.assertEqual(len(self.backend.appoint_calls), 1)
        platform, subject, workspace_id, target_user_id = self.backend.appoint_calls[0]
        self.assertEqual(platform, "telegram")
        self.assertEqual(subject, "owner")
        self.assertEqual(workspace_id, WS1)
        self.assertEqual(target_user_id, USER1)
        self.assertIsNone(self.state.appoint_wizard("telegram", "owner"))

    def test_appoint_wizard_workspace_pagination_shows_second_page(self):
        first_page = self._begin_appoint()
        next_ref = next((btn.callback for row in first_page.screen.rows for btn in row if btn.text == "بعدی ›"), None)
        self.assertIsNotNone(next_ref)
        second_page = self.app.callback("owner", True, next_ref)
        self.assertIn("پزشکی ۱۴۰۱", self.labels(second_page.screen))

    def test_appoint_wizard_back_returns_from_candidates_to_workspaces(self):
        first_page = self._begin_appoint()
        pick_ws_ref = first_page.screen.rows[0][0].callback
        self.app.callback("owner", True, pick_ws_ref)
        back = self.app.callback("owner", True, "apback")
        self.assertEqual(back.screen.presentation.semantic_kind, "appoint_wizard_workspace")

    def test_appoint_wizard_cancel_clears_state(self):
        self._begin_appoint()
        cancelled = self.app.callback("owner", True, "apcancel")
        self.assertEqual(cancelled.screen.presentation.semantic_kind, "appoint_wizard_cancelled")
        self.assertIsNone(self.state.appoint_wizard("telegram", "owner"))

    def test_appoint_wizard_backend_failure_is_surfaced_and_logged(self):
        first_page = self._begin_appoint()
        pick_ws_ref = first_page.screen.rows[0][0].callback
        candidates = self.app.callback("owner", True, pick_ws_ref)
        pick_candidate_ref = candidates.screen.rows[0][0].callback
        self.app.callback("owner", True, pick_candidate_ref)

        self.backend.appoint_error = RuntimeError("egress proxy unreachable")
        with self.assertLogs(level="ERROR") as captured:
            failed = self.app.callback("owner", True, "apconfirm")
        self.assertEqual(failed.screen.presentation.semantic_kind, "appoint_wizard_failed")
        self.assertTrue(any("representative_appoint" in message for message in captured.output))

    # 3) The pending-requests entry point appears only for a representative
    # of the currently selected workspace, and lists only that workspace.
    def test_representative_requests_entry_point_appears_only_for_representative(self):
        without_access = self.app.more("someone", True)
        self.assertNotIn("📋 درخواست‌های عضویت", self.labels(without_access.screen))

        self.backend.representative_subjects.add("rep")
        with_access = self.app.more("rep", True)
        self.assertIn("📋 درخواست‌های عضویت", self.labels(with_access.screen))

    def test_representative_requests_lists_only_the_selected_workspace(self):
        self.backend.representative_subjects.add("rep")
        result = self.app.representative_requests("rep")
        self.assertEqual(result.screen.presentation.semantic_kind, "representative_requests")
        self.assertTrue(any("محمد" in label for label in self.labels(result.screen)))

    def test_representative_can_approve_and_decline(self):
        self.backend.representative_subjects.add("rep")
        listing = self.app.representative_requests("rep")
        approve_ref = next(btn.callback for row in listing.screen.rows for btn in row if btn.text.startswith("✅"))
        approved = self.app.callback("rep", True, approve_ref)
        self.assertEqual(len(self.backend.approve_calls), 1)
        _, _, workspace_id, request_id = self.backend.approve_calls[0]
        self.assertEqual(workspace_id, WS1)
        self.assertEqual(request_id, REQ1)
        # The list refreshes and the now-resolved request is gone.
        self.assertEqual(approved.screen.presentation.semantic_kind, "representative_requests_empty")

    def test_representative_can_decline(self):
        self.backend.representative_subjects.add("rep")
        listing = self.app.representative_requests("rep")
        decline_ref = next(btn.callback for row in listing.screen.rows for btn in row if btn.text == "❌ رد")
        self.app.callback("rep", True, decline_ref)
        self.assertEqual(len(self.backend.decline_calls), 1)

    # 4) A backend refusal is surfaced, not swallowed -- direct call and via
    # callback, plus confirming the expected-denial path does not spam logs
    # the way a genuine failure must.
    def test_non_representative_gets_a_surfaced_error_not_a_silent_pass(self):
        result = self.app.representative_requests("nobody")
        self.assertNotEqual(result.screen.presentation.semantic_kind, "representative_requests")
        self.assertNotEqual(result.screen.presentation.semantic_kind, "representative_requests_empty")
        self.assertEqual(result.screen.presentation.severity, "error")

    def test_more_screen_does_not_log_for_expected_forbidden_denial(self):
        with self.assertNoLogs(level="WARNING"):
            self.app.more("nobody", True)


class BotApplicationRepresentativeTest(_BaseRepresentativeTest, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotApplicationRepresentativeTest(_BaseRepresentativeTest, unittest.TestCase):
    """Same matrix against the class the runtime actually constructs
    (apps/telegram-bot/runtime.py imports from integrated_application)."""

    app_class = IntegratedBotApplication

    def test_appoint_wizard_screens_carry_owner_permission_metadata(self):
        first_page = self._begin_appoint()
        prepared = self.app.prepare_result("owner", True, first_page)
        self.assertIn("deployment.manage", prepared.metadata.get("canonical_permissions", ()))


if __name__ == "__main__":
    unittest.main()
