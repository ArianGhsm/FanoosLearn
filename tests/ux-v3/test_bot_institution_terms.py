from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState

INSTITUTION1 = "11111111-1111-4111-8111-111111111111"
INSTITUTION2 = "22222222-2222-4222-8222-222222222222"
WS1 = "33333333-3333-4333-8333-333333333333"


class TermBackend:
    """Owner/representative permission matrix plus recording set/override
    calls, mirroring the real InstitutionTermService contract."""

    def __init__(self):
        self.subjects_that_can_manage: set[str] = set()
        self.can_override_subjects: set[str] = set()
        self.workspaces_result: dict = {"workspaces": [{"id": WS1, "name": "دندانپزشکی ۱۴۰۲"}], "selected_workspace_id": WS1}
        self.institutions_by_cursor = {
            None: {"items": [{"id": INSTITUTION1, "name": "دانشگاه علوم پزشکی تهران"}], "next_cursor": "cursor-2"},
            "cursor-2": {"items": [{"id": INSTITUTION2, "name": "دانشگاه شیراز"}], "next_cursor": None},
        }
        self.set_calls: list[tuple] = []
        self.set_error: Exception | None = None
        self.set_result: dict = {"institution_term_id": "term-1", "applied_count": 2, "skipped_count": 0}

        self.workspace_terms: list[dict] = [
            {"id": "44444444-4444-4444-8444-444444444444", "term_key": "fall-1406", "name": "نیم‌سال اول ۱۴۰۶", "starts_on": "2027-09-23", "ends_on": "2028-01-20", "status": "planned", "origin": "inherited"},
        ]
        self.override_calls: list[tuple] = []
        self.override_error: Exception | None = None

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject in self.subjects_that_can_manage}

    def workspaces(self, platform, subject):
        return dict(self.workspaces_result)

    def select_workspace(self, platform, subject, workspace_id):
        return {}

    def institution_terms_institutions(self, platform, subject, limit=10, cursor=None):
        return dict(self.institutions_by_cursor.get(cursor) or {"items": [], "next_cursor": None})

    def institution_terms_set(self, platform, subject, institution_id, term_key, name, starts_on, ends_on, status="planned"):
        self.set_calls.append((platform, subject, institution_id, term_key, name, starts_on, ends_on, status))
        if self.set_error is not None:
            raise self.set_error
        return dict(self.set_result)

    def academic_terms_list(self, platform, subject, workspace_id):
        return {"items": list(self.workspace_terms), "can_override": subject in self.can_override_subjects}

    def academic_terms_override(self, platform, subject, workspace_id, term_key, name, starts_on, ends_on, status="planned"):
        self.override_calls.append((platform, subject, workspace_id, term_key, name, starts_on, ends_on, status))
        if self.override_error is not None:
            raise self.override_error
        return {"term_id": "at-1", "created": False}


class _BaseTermTest:
    app_class = BotApplication

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = TermBackend()
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

    def _begin_owner(self, subject="owner"):
        self.backend.subjects_that_can_manage.add(subject)
        return self.app.callback(subject, True, "trmlist")

    # 1) The owner's screen appears for owners only.
    def test_owner_entry_point_appears_only_for_owner(self):
        self.backend.subjects_that_can_manage.add("owner")
        management = self.app.management("owner", True)
        self.assertIn("📅 تنظیم ترم‌ها", self.labels(management.screen))

        denied_management = self.app.management("student", True)
        self.assertNotIn("📅 تنظیم ترم‌ها", self.labels(denied_management.screen))

        direct = self.app.callback("student", True, "trmlist")
        self.assertEqual(direct.screen.presentation.semantic_kind, "term_list_denied")

    # 2) The representative's screen appears only for a representative of
    # that class -- a plain member with no academic.manage sees no entry
    # point in "بیشتر", even though they can still (separately) view terms.
    def test_representative_entry_point_appears_only_for_that_class(self):
        without_override = self.app.more("someone", True)
        self.assertNotIn("📅 ترم‌های کلاس", self.labels(without_override.screen))

        self.backend.can_override_subjects.add("rep")
        with_override = self.app.more("rep", True)
        self.assertIn("📅 ترم‌های کلاس", self.labels(with_override.screen))

    # 3) Owner walkthrough: pick institution, answer term_key/name/dates,
    # confirm -- the backend receives exactly the Gregorian dates the owner
    # typed in Jalali.
    def test_owner_full_walkthrough_sends_gregorian_dates(self):
        first_page = self._begin_owner()
        self.assertEqual(first_page.screen.presentation.semantic_kind, "term_institution_list")
        self.assertIn("بعدی ›", self.labels(first_page.screen))

        pick_ref = first_page.screen.rows[0][0].callback
        step1 = self.app.callback("owner", True, pick_ref)
        self.assertEqual(step1.screen.presentation.semantic_kind, "term_wizard_step")
        self.assertIn("شناسه ترم", step1.screen.text)

        step2 = self.app.term_wizard_text("owner", "Fall 1406", True)
        self.assertIn("نام ترم", step2.screen.text)
        step3 = self.app.term_wizard_text("owner", "نیم‌سال اول ۱۴۰۶", True)
        self.assertIn("تاریخ شروع", step3.screen.text)
        step4 = self.app.term_wizard_text("owner", "1406/07/01", True)
        self.assertIn("تاریخ پایان", step4.screen.text)
        review = self.app.term_wizard_text("owner", "1406/10/15", True)
        self.assertEqual(review.screen.presentation.semantic_kind, "term_wizard_review")
        self.assertIn("✅ اعمال روی همه کلاس‌ها", self.labels(review.screen))

        created = self.app.callback("owner", True, "trmconf")
        self.assertEqual(created.screen.presentation.semantic_kind, "term_wizard_created")
        self.assertEqual(len(self.backend.set_calls), 1)
        platform, subject, institution_id, term_key, name, starts_on, ends_on, status = self.backend.set_calls[0]
        self.assertEqual(institution_id, INSTITUTION1)
        self.assertEqual(term_key, "fall-1406")
        self.assertEqual(name, "نیم‌سال اول ۱۴۰۶")
        self.assertEqual(starts_on, "2027-09-23")
        self.assertEqual(ends_on, "2028-01-05")
        self.assertIsNone(self.state.term_wizard("telegram", "owner"))

    def test_owner_wizard_rejects_invalid_term_key_without_advancing(self):
        first_page = self._begin_owner()
        pick_ref = first_page.screen.rows[0][0].callback
        self.app.callback("owner", True, pick_ref)
        # Only symbols/non-Latin text normalizes to an empty slug -- rejected.
        rejected = self.app.term_wizard_text("owner", "!!!", True)
        self.assertEqual(rejected.screen.presentation.severity, "warning")
        wizard = self.state.term_wizard("telegram", "owner")
        self.assertEqual(wizard["step_index"], 0)

    def test_owner_wizard_rejects_end_before_start(self):
        first_page = self._begin_owner()
        pick_ref = first_page.screen.rows[0][0].callback
        self.app.callback("owner", True, pick_ref)
        self.app.term_wizard_text("owner", "fall-1406", True)
        self.app.term_wizard_text("owner", "Fall 1406", True)
        self.app.term_wizard_text("owner", "1406/10/15", True)
        rejected = self.app.term_wizard_text("owner", "1406/07/01", True)
        self.assertEqual(rejected.screen.presentation.severity, "warning")
        self.assertIn("قبل از تاریخ شروع", rejected.screen.text)
        wizard = self.state.term_wizard("telegram", "owner")
        self.assertEqual(wizard["step_index"], 3)

    def test_owner_wizard_cancel_clears_state(self):
        first_page = self._begin_owner()
        pick_ref = first_page.screen.rows[0][0].callback
        self.app.callback("owner", True, pick_ref)
        self.app.term_wizard_text("owner", "fall-1406", True)
        cancelled = self.app.callback("owner", True, "trmcxl")
        self.assertEqual(cancelled.screen.presentation.semantic_kind, "term_wizard_cancelled")
        self.assertIsNone(self.state.term_wizard("telegram", "owner"))

    def test_owner_backend_failure_is_surfaced_and_logged(self):
        first_page = self._begin_owner()
        pick_ref = first_page.screen.rows[0][0].callback
        self.app.callback("owner", True, pick_ref)
        self.app.term_wizard_text("owner", "fall-1406", True)
        self.app.term_wizard_text("owner", "Fall 1406", True)
        self.app.term_wizard_text("owner", "1406/07/01", True)
        self.app.term_wizard_text("owner", "1406/10/15", True)

        self.backend.set_error = RuntimeError("egress proxy unreachable")
        with self.assertLogs(level="ERROR") as captured:
            failed = self.app.callback("owner", True, "trmconf")
        self.assertEqual(failed.screen.presentation.semantic_kind, "term_wizard_failed")
        self.assertTrue(any("institution term set" in message for message in captured.output))

    # 4) Representative walkthrough: pick a term, answer new dates, confirm.
    def test_representative_full_override_walkthrough(self):
        self.backend.can_override_subjects.add("rep")
        listing = self.app.class_terms("rep")
        self.assertEqual(listing.screen.presentation.semantic_kind, "class_terms")
        self.assertTrue(any("نیم‌سال اول" in label for label in self.labels(listing.screen)))

        pick_ref = listing.screen.rows[0][0].callback
        step1 = self.app.callback("rep", True, pick_ref)
        self.assertEqual(step1.screen.presentation.semantic_kind, "class_term_wizard_step")
        self.assertIn("تاریخ شروع", step1.screen.text)

        step2 = self.app.term_wizard_text("rep", "1406/07/15", True)
        self.assertIn("تاریخ پایان", step2.screen.text)
        review = self.app.term_wizard_text("rep", "1406/10/25", True)
        self.assertEqual(review.screen.presentation.semantic_kind, "class_term_wizard_review")

        saved = self.app.callback("rep", True, "trmconf")
        self.assertEqual(saved.screen.presentation.semantic_kind, "class_term_wizard_created")
        self.assertEqual(len(self.backend.override_calls), 1)
        platform, subject, workspace_id, term_key, name, starts_on, ends_on, status = self.backend.override_calls[0]
        self.assertEqual(workspace_id, WS1)
        self.assertEqual(term_key, "fall-1406")
        self.assertEqual(starts_on, "2027-10-07")
        self.assertEqual(ends_on, "2028-01-15")

    def test_representative_without_override_sees_read_only_list(self):
        listing = self.app.class_terms("plain-member")
        self.assertEqual(listing.screen.presentation.semantic_kind, "class_terms")
        self.assertIn("فقط نماینده کلاس", listing.screen.text)

    def test_representative_backend_failure_is_surfaced_and_logged(self):
        self.backend.can_override_subjects.add("rep")
        listing = self.app.class_terms("rep")
        pick_ref = listing.screen.rows[0][0].callback
        self.app.callback("rep", True, pick_ref)
        self.app.term_wizard_text("rep", "1406/07/15", True)
        self.app.term_wizard_text("rep", "1406/10/25", True)

        self.backend.override_error = FanoosApiError("forbidden", "اجازه انجام این عملیات را ندارید.", 403)
        with self.assertLogs(level="ERROR") as captured:
            failed = self.app.callback("rep", True, "trmconf")
        self.assertEqual(failed.screen.presentation.semantic_kind, "class_term_wizard_failed")
        self.assertTrue(any("class term override" in message for message in captured.output))


class BotApplicationTermTest(_BaseTermTest, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotApplicationTermTest(_BaseTermTest, unittest.TestCase):
    """Same matrix against the class the runtime actually constructs
    (apps/telegram-bot/runtime.py imports from integrated_application)."""

    app_class = IntegratedBotApplication

    def test_owner_wizard_screens_carry_owner_permission_metadata(self):
        first_page = self._begin_owner()
        prepared = self.app.prepare_result("owner", True, first_page)
        self.assertIn("deployment.manage", prepared.metadata.get("canonical_permissions", ()))

    def test_representative_wizard_screens_do_not_carry_owner_permission_metadata(self):
        self.backend.can_override_subjects.add("rep")
        listing = self.app.class_terms("rep")
        pick_ref = listing.screen.rows[0][0].callback
        step = self.app.callback("rep", True, pick_ref)
        prepared = self.app.prepare_result("rep", True, step)
        self.assertNotIn("deployment.manage", prepared.metadata.get("canonical_permissions", ()))


if __name__ == "__main__":
    unittest.main()
