from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState


class ClassProvisioningBackend:
    """Owner/permission matrix plus a recording create_class, mirroring the real
    ClassProvisioningService contract: POST /classes returns workspace_id,
    workspace_slug, workspace_created and the resolved directory ids."""

    def __init__(self):
        self.subjects_that_can_manage: set[str] = set()
        self.create_class_calls: list[tuple[str, str, dict]] = []
        self.create_class_result: dict = {
            "workspace_id": "22222222-2222-4222-8222-222222222222",
            "workspace_slug": "dentistry-1402-tums",
            "workspace_created": True,
            "cohort_id": "33333333-3333-4333-8333-333333333333",
            "program_id": "44444444-4444-4444-8444-444444444444",
            "faculty_id": "55555555-5555-4555-8555-555555555555",
            "institution_id": "66666666-6666-4666-8666-666666666666",
        }
        self.create_class_error: Exception | None = None

    def workspaces(self, platform, subject):
        return {"workspaces": [], "selected_workspace_id": None}

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject in self.subjects_that_can_manage}

    def create_class(self, platform, subject, identity):
        self.create_class_calls.append((platform, subject, dict(identity)))
        if self.create_class_error is not None:
            raise self.create_class_error
        return dict(self.create_class_result)


FULL_ANSWERS = [
    "ایران",
    "IR",
    "تهران",
    "تهران",
    "دانشگاه علوم پزشکی تهران",
    "دانشکده دندانپزشکی",
    "دندانپزشکی عمومی",
    "دکترای حرفه‌ای",
    "۱۴۰۲",
    "دندانپزشکی ۱۴۰۲ - علوم پزشکی تهران",
]

EXPECTED_IDENTITY = {
    "country": {"code": "IR", "name": "ایران"},
    "province": {"name": "تهران"},
    "city": {"name": "تهران"},
    "institution": {"name": "دانشگاه علوم پزشکی تهران"},
    "faculty": {"name": "دانشکده دندانپزشکی"},
    "department": None,
    "program": {"name": "دندانپزشکی عمومی", "degree_level": "دکترای حرفه‌ای"},
    "cohort": {"entry_year": 1402, "label": "ورودی ۱۴۰۲"},
    "workspace": {"name": "دندانپزشکی ۱۴۰۲ - علوم پزشکی تهران"},
}


class _BaseClassWizardTest:
    app_class = BotApplication

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = ClassProvisioningBackend()
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

    def _begin(self, subject="owner"):
        self.backend.subjects_that_can_manage.add(subject)
        return self.app.callback(subject, True, "clsnew")

    def _walk_to_review(self, subject="owner", answers=FULL_ANSWERS):
        result = self._begin(subject)
        for answer in answers:
            result = self.app.class_wizard_text(subject, answer, True)
        return result

    # 1) Owner walks the wizard end to end; the backend receives exactly the
    # identity they entered.
    def test_full_walkthrough_sends_exact_identity_to_backend(self):
        review = self._walk_to_review()
        self.assertEqual(review.screen.presentation.semantic_kind, "class_wizard_review")
        self.assertIn("✅ ساخت کلاس", self.labels(review.screen))

        created = self.app.callback("owner", True, "clsconfirm")
        self.assertEqual(len(self.backend.create_class_calls), 1)
        platform, subject, identity = self.backend.create_class_calls[0]
        self.assertEqual(platform, "telegram")
        self.assertEqual(subject, "owner")
        self.assertEqual(identity, EXPECTED_IDENTITY)
        self.assertEqual(created.screen.presentation.semantic_kind, "class_wizard_created")
        self.assertEqual(created.screen.presentation.severity, "success")

        # Wizard state is cleared once the class is created.
        self.assertIsNone(self.state.class_wizard("telegram", "owner"))

    def test_step_counter_uses_persian_digits(self):
        result = self._begin()
        self.assertIn("مرحله ۱ از ۱۱", result.screen.text)

    # 2) Back works from every step and returns the previous question with
    # prior answers intact.
    def test_back_returns_previous_question_and_keeps_prior_answers(self):
        self._begin()
        self.app.class_wizard_text("owner", FULL_ANSWERS[0], True)  # country_name
        self.app.class_wizard_text("owner", FULL_ANSWERS[1], True)  # country_code -> province step next

        back = self.app.callback("owner", True, "clsback")
        self.assertEqual(back.screen.presentation.semantic_kind, "class_wizard_step")
        self.assertIn("کد کشور", back.screen.text)
        self.assertIn("IR", back.screen.text)  # prior answer for this step is shown

        # Re-answering this step overwrites only this field.
        self.app.class_wizard_text("owner", "US", True)
        for answer in FULL_ANSWERS[2:]:
            result = self.app.class_wizard_text("owner", answer, True)
        self.assertEqual(result.screen.presentation.semantic_kind, "class_wizard_review")

        self.app.callback("owner", True, "clsconfirm")
        _, _, identity = self.backend.create_class_calls[-1]
        self.assertEqual(identity["country"], {"code": "US", "name": "ایران"})

    def test_back_from_first_step_stays_on_first_step(self):
        self._begin()
        result = self.app.callback("owner", True, "clsback")
        self.assertEqual(result.screen.presentation.semantic_kind, "class_wizard_step")
        self.assertIn("مرحله ۱ از ۱۱", result.screen.text)

    # 3) Cancel abandons the wizard and leaves no partial state behind.
    def test_cancel_clears_wizard_state(self):
        self._begin()
        self.app.class_wizard_text("owner", FULL_ANSWERS[0], True)
        cancelled = self.app.callback("owner", True, "clscancel")
        self.assertEqual(cancelled.screen.presentation.semantic_kind, "class_wizard_cancelled")
        self.assertIsNone(self.state.class_wizard("telegram", "owner"))
        # A later free-text message is not swallowed by a dead wizard.
        self.assertIsNone(self.app.class_wizard_text("owner", "چیزی", True))

    # 4) A non-owner never sees the entry point, and a direct attempt is
    # refused.
    def test_non_owner_does_not_see_entry_point_in_management(self):
        management = self.app.management("student", True)
        self.assertEqual(management.screen.presentation.semantic_kind, "management_denied")
        self.assertNotIn("➕ ساخت کلاس", self.labels(management.screen))

    def test_owner_sees_entry_point_in_management(self):
        self.backend.subjects_that_can_manage.add("owner")
        management = self.app.management("owner", True)
        self.assertIn("➕ ساخت کلاس", self.labels(management.screen))

    def test_direct_wizard_attempt_by_non_owner_is_refused(self):
        result = self.app.callback("student", True, "clsnew")
        self.assertEqual(result.screen.presentation.semantic_kind, "class_wizard_denied")
        self.assertIsNone(self.state.class_wizard("telegram", "student"))

    def test_direct_wizard_confirm_by_non_owner_is_refused(self):
        # Even if a stray confirm callback reaches the app with no wizard
        # in progress, nothing is created.
        result = self.app.callback("student", True, "clsconfirm")
        self.assertEqual(result.screen.presentation.semantic_kind, "class_wizard_expired")
        self.assertEqual(self.backend.create_class_calls, [])

    # 5) A backend failure mid-wizard is surfaced, not swallowed, and logged.
    def test_backend_failure_on_confirm_is_surfaced_and_logged(self):
        self._walk_to_review()
        self.backend.create_class_error = RuntimeError("egress proxy unreachable")
        with self.assertLogs(level="ERROR") as captured:
            failed = self.app.callback("owner", True, "clsconfirm")
        self.assertEqual(failed.screen.presentation.semantic_kind, "class_wizard_failed")
        self.assertEqual(failed.screen.presentation.severity, "error")
        self.assertTrue(any("create_class" in message for message in captured.output))
        # Wizard state survives a failure so the owner is not forced to retype.
        wizard = self.state.class_wizard("telegram", "owner")
        self.assertIsNotNone(wizard)

        # A retry after the transient failure clears succeeds normally.
        self.backend.create_class_error = None
        retried = self.app.callback("owner", True, "clsconfirm")
        self.assertEqual(retried.screen.presentation.semantic_kind, "class_wizard_created")

    # 6) The success screen reports whether the class was newly created or
    # reused (the only per-row signal ClassProvisioningService's POST
    # /classes response currently exposes is workspace_created).
    def test_success_screen_reports_newly_created_workspace(self):
        self.backend.create_class_result["workspace_created"] = True
        self._walk_to_review()
        created = self.app.callback("owner", True, "clsconfirm")
        self.assertIn("کلاس جدید ساخته شد", created.screen.text)

    def test_success_screen_reports_reused_workspace(self):
        self.backend.create_class_result["workspace_created"] = False
        self._walk_to_review()
        created = self.app.callback("owner", True, "clsconfirm")
        self.assertIn("کلاس موجود مطابقت داشت", created.screen.text)

    def test_invalid_entry_year_is_rejected_without_advancing(self):
        self._begin()
        for answer in FULL_ANSWERS[:8]:  # up to and including degree_level
            self.app.class_wizard_text("owner", answer, True)
        rejected = self.app.class_wizard_text("owner", "نامعتبر", True)
        self.assertEqual(rejected.screen.presentation.severity, "warning")
        self.assertIn("سال ورود", rejected.screen.text)
        wizard = self.state.class_wizard("telegram", "owner")
        self.assertEqual(wizard["step_index"], 8)  # still on entry_year

    def test_invalid_country_code_is_rejected(self):
        self._begin()
        self.app.class_wizard_text("owner", FULL_ANSWERS[0], True)
        rejected = self.app.class_wizard_text("owner", "ایران", True)
        self.assertIn("دو حرف انگلیسی", rejected.screen.text)


class BotApplicationClassWizardTest(_BaseClassWizardTest, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotApplicationClassWizardTest(_BaseClassWizardTest, unittest.TestCase):
    """Same matrix against the class the runtime actually constructs
    (apps/telegram-bot/runtime.py imports from integrated_application)."""

    app_class = IntegratedBotApplication

    def test_wizard_screens_carry_owner_permission_metadata(self):
        review = self._walk_to_review()
        prepared = self.app.prepare_result("owner", True, review)
        self.assertIn("deployment.manage", prepared.metadata.get("canonical_permissions", ()))


if __name__ == "__main__":
    unittest.main()
