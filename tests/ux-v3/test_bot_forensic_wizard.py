from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

import fanoos_bot.forensic_admin as forensic_admin_module
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.forensic_admin import InvestigationOutcome
from fanoos_bot.forensic_detector import ChannelResult, Detection
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState

WS1 = "11111111-1111-4111-8111-111111111111"
WS2 = "22222222-2222-4222-8222-222222222222"
RES1 = "33333333-3333-4333-8333-333333333333"
RES2 = "44444444-4444-4444-8444-444444444444"
FINGERPRINT_KEY = b"k" * 32


class ForensicBackend:
    """Mirrors the real contract: workspace listing reuses
    representatives/workspaces (owner-scoped, no membership requirement,
    exactly like appoint_wizard's own first step); resource listing is the
    new protected-media/forensic/resources read, scoped by
    protected_media.forensic.investigate rather than membership.
    """

    def __init__(self):
        self.subjects_that_can_manage: set[str] = set()

        self.workspaces_by_page = {
            None: {"items": [{"id": WS1, "name": "دندانپزشکی ۱۴۰۲"}], "next_cursor": "cursor-2"},
            "cursor-2": {"items": [{"id": WS2, "name": "پزشکی ۱۴۰۱"}], "next_cursor": None},
        }
        self.resources_by_workspace = {
            WS1: {"items": [{"resource_id": RES1, "title": "جزوه آناتومی", "candidate_count": 2}], "next_cursor": None},
        }
        self.candidates_by_resource: dict[str, list[dict]] = {}
        self.candidates_error: Exception | None = None

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject in self.subjects_that_can_manage}

    def representative_workspaces(self, platform, subject, limit=10, cursor=None):
        return dict(self.workspaces_by_page.get(cursor) or {"items": [], "next_cursor": None})

    def media_forensic_resources(self, platform, subject, workspace_id, limit=10, cursor=None):
        return dict(self.resources_by_workspace.get(workspace_id) or {"items": [], "next_cursor": None})

    def media_forensic_candidates(self, platform, subject, workspace_id, resource_id):
        if self.candidates_error is not None:
            raise self.candidates_error
        return {"candidates": list(self.candidates_by_resource.get(resource_id, []))}

    def media_forensic_source(self, platform, subject, workspace_id, job_id, max_bytes):
        return b"%PDF-1.4 fixture"


class _BaseForensicWizardTest:
    app_class = BotApplication

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = ForensicBackend()
        self.app = self.app_class(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/", "platform-primary", protected_media_fingerprint_key=FINGERPRINT_KEY),
        )
        self.evidence_dir = tempfile.TemporaryDirectory()
        self.evidence_path = Path(self.evidence_dir.name) / "evidence.pdf"
        self.evidence_path.write_bytes(b"not a real pdf, detect() is stubbed in these tests")

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()
        self.evidence_dir.cleanup()

    @staticmethod
    def labels(screen):
        return [button.text for row in screen.rows for button in row]

    def _begin(self, subject="owner"):
        self.backend.subjects_that_can_manage.add(subject)
        return self.app.callback(subject, True, "fornew")

    def _to_upload_step(self, subject="owner"):
        first_page = self._begin(subject)
        pick_ws_ref = first_page.screen.rows[0][0].callback
        resources = self.app.callback(subject, True, pick_ws_ref)
        pick_res_ref = resources.screen.rows[0][0].callback
        return self.app.callback(subject, True, pick_res_ref)

    # 1) Only an owner reaches the entry point; a representative, a limited
    # member and a plain student all get the same denial as every other
    # management row, and a direct attempt is refused too.
    def test_entry_point_appears_only_for_owner(self):
        self.backend.subjects_that_can_manage.add("owner")
        management = self.app.management("owner", True)
        self.assertIn("🔎 ردیابی نشت", self.labels(management.screen))

        for non_owner in ("representative", "limited-member", "student"):
            denied_management = self.app.management(non_owner, True)
            self.assertEqual(denied_management.screen.presentation.semantic_kind, "management_denied")
            self.assertNotIn("🔎 ردیابی نشت", self.labels(denied_management.screen))

            direct = self.app.callback(non_owner, True, "fornew")
            self.assertEqual(direct.screen.presentation.semantic_kind, "forensic_wizard_denied")
            self.assertIsNone(self.state.forensic_wizard("telegram", non_owner))

    # 2) Full walkthrough: entry -> workspace -> resource -> upload prompt ->
    # document received -> result rendered, against a stub backend.
    # forensic_admin.investigate is monkeypatched here (application.py calls
    # it by module reference) so this exercises the wizard's own plumbing
    # without depending on forensic_detector's real PDF/crypto logic, which
    # is out of scope for this surface and already covered elsewhere.
    def test_full_walkthrough_from_entry_to_result(self):
        first_page = self._begin()
        self.assertEqual(first_page.screen.presentation.semantic_kind, "forensic_wizard_workspace")
        self.assertIn("بعدی ›", self.labels(first_page.screen))

        pick_ws_ref = first_page.screen.rows[0][0].callback
        resources = self.app.callback("owner", True, pick_ws_ref)
        self.assertEqual(resources.screen.presentation.semantic_kind, "forensic_wizard_resource")
        self.assertTrue(any("آناتومی" in label for label in self.labels(resources.screen)))

        pick_res_ref = resources.screen.rows[0][0].callback
        upload_prompt = self.app.callback("owner", True, pick_res_ref)
        self.assertEqual(upload_prompt.screen.presentation.semantic_kind, "forensic_wizard_upload")
        self.assertIn("جزوه آناتومی", upload_prompt.screen.text)
        self.assertTrue(self.app.forensic_wizard_awaiting_document("owner"))

        single = Detection(
            issuance_id="job-1", user_id="user-1", document_id=RES1, confidence=0.98,
            successful_channels=("raster-constellation-repetition3-v1-ecc-fusion",), failed_channels=(),
            channel_results=(ChannelResult("x", True, 1.0, 120, 120, "valid", True),),
            evidence={}, verdict="attributed", watermark_version="recipient-pdf-v9",
        )
        captured = {}

        def fake_investigate(api, *, platform, subject, workspace_id, resource_id, evidence_path, secret, deadline):
            captured.update(platform=platform, subject=subject, workspace_id=workspace_id, resource_id=resource_id, secret=secret)
            return InvestigationOutcome(candidates_found=1, detections=(single,))

        original = forensic_admin_module.investigate
        forensic_admin_module.investigate = fake_investigate
        try:
            result = self.app.forensic_wizard_document("owner", self.evidence_path)
        finally:
            forensic_admin_module.investigate = original

        self.assertEqual(result.screen.presentation.semantic_kind, "forensic_wizard_result")
        self.assertIn("user-1", result.screen.text)
        self.assertIn("قطعی", result.screen.text)
        self.assertEqual(captured["workspace_id"], WS1)
        self.assertEqual(captured["resource_id"], RES1)
        self.assertEqual(captured["secret"], FINGERPRINT_KEY)
        # Single-use: once a document has been processed (success or
        # failure), the wizard state is gone -- a retry means a fresh upload.
        self.assertIsNone(self.state.forensic_wizard("telegram", "owner"))
        self.assertFalse(self.app.forensic_wizard_awaiting_document("owner"))

    def test_workspace_pagination_shows_second_page(self):
        first_page = self._begin()
        next_ref = next((btn.callback for row in first_page.screen.rows for btn in row if btn.text == "بعدی ›"), None)
        self.assertIsNotNone(next_ref)
        second_page = self.app.callback("owner", True, next_ref)
        self.assertIn("پزشکی ۱۴۰۱", self.labels(second_page.screen))

    def test_back_returns_from_resources_to_workspaces(self):
        first_page = self._begin()
        pick_ws_ref = first_page.screen.rows[0][0].callback
        self.app.callback("owner", True, pick_ws_ref)
        back = self.app.callback("owner", True, "forback")
        self.assertEqual(back.screen.presentation.semantic_kind, "forensic_wizard_workspace")

    def test_back_from_upload_returns_to_resources(self):
        self._to_upload_step()
        back = self.app.callback("owner", True, "forback")
        self.assertEqual(back.screen.presentation.semantic_kind, "forensic_wizard_resource")

    def test_cancel_clears_state(self):
        self._begin()
        cancelled = self.app.callback("owner", True, "forcancel")
        self.assertEqual(cancelled.screen.presentation.semantic_kind, "forensic_wizard_cancelled")
        self.assertIsNone(self.state.forensic_wizard("telegram", "owner"))

    # 3) A backend refusal is surfaced, never swallowed.
    def test_backend_failure_during_investigate_is_surfaced_and_logged(self):
        self._to_upload_step()
        self.backend.candidates_error = RuntimeError("egress proxy unreachable")
        with self.assertLogs(level="ERROR") as captured:
            result = self.app.forensic_wizard_document("owner", self.evidence_path)
        self.assertNotEqual(result.screen.presentation.semantic_kind, "forensic_wizard_result")
        self.assertEqual(result.screen.presentation.severity, "error")
        self.assertTrue(any("investigate" in message for message in captured.output))
        # Still single-use even on failure: no lingering wizard state to retry against.
        self.assertIsNone(self.state.forensic_wizard("telegram", "owner"))

    # 4) Bale never reaches this screen, at every entry point.
    def test_bale_never_reaches_the_wizard(self):
        bale_app = self.app_class(
            self.backend, self.state, "bale",
            ApplicationConfig("https://fanoos.test/", "platform-primary", protected_media_fingerprint_key=FINGERPRINT_KEY),
        )
        self.backend.subjects_that_can_manage.add("owner")
        direct = bale_app.forensic_wizard_begin("owner", True)
        self.assertEqual(direct.screen.presentation.semantic_kind, "forensic_wizard_unavailable")
        self.assertIsNone(self.state.forensic_wizard("bale", "owner"))

    def test_document_from_a_non_private_chat_is_never_accepted_as_evidence(self):
        # Wizard state is keyed by (platform, subject) alone, not by chat, so
        # a document arriving from a non-private update for the same subject
        # (e.g. a group the bot is also in) must not be treated as evidence
        # for a wizard the owner started privately.
        self._to_upload_step()
        self.assertTrue(self.app.forensic_wizard_awaiting_document("owner", True))
        self.assertFalse(self.app.forensic_wizard_awaiting_document("owner", False))
        result = self.app.forensic_wizard_document("owner", self.evidence_path, False)
        self.assertEqual(result.screen.presentation.semantic_kind, "forensic_wizard_expired")
        # The wizard itself is untouched -- a later private-chat upload can still complete it.
        self.assertTrue(self.app.forensic_wizard_awaiting_document("owner", True))

    def test_missing_fingerprint_key_fails_closed(self):
        unconfigured = self.app_class(
            self.backend, self.state, "telegram",
            ApplicationConfig("https://fanoos.test/", "platform-primary"),
        )
        self.backend.subjects_that_can_manage.add("owner")
        result = unconfigured.forensic_wizard_begin("owner", True)
        self.assertEqual(result.screen.presentation.semantic_kind, "forensic_wizard_unavailable")


class BotApplicationForensicWizardTest(_BaseForensicWizardTest, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotApplicationForensicWizardTest(_BaseForensicWizardTest, unittest.TestCase):
    """Same matrix against the class the runtime actually constructs
    (apps/telegram-bot/runtime.py imports from integrated_application)."""

    app_class = IntegratedBotApplication

    def test_forensic_screens_carry_owner_permission_metadata(self):
        first_page = self._begin()
        prepared = self.app.prepare_result("owner", True, first_page)
        self.assertIn("deployment.manage", prepared.metadata.get("canonical_permissions", ()))


if __name__ == "__main__":
    unittest.main()
