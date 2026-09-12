from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState

PROGRAM1 = "11111111-1111-4111-8111-111111111111"
PROGRAM2 = "22222222-2222-4222-8222-222222222222"


class ClassCreationRequestBackend:
    """Owner/permission matrix plus recording approve/decline calls, mirroring
    the real ClassCreationRequestService contract: pending requests grouped by
    (program_id, entry_year) with a demand_count, resolved directory labels
    and the earliest requested_at."""

    def __init__(self):
        self.subjects_that_can_manage: set[str] = set()
        self.groups_by_cursor: dict[str | None, dict] = {
            None: {
                "items": [{
                    "program_id": PROGRAM1,
                    "entry_year": 1402,
                    "institution_name": "دانشگاه علوم پزشکی تهران",
                    "faculty_name": "دانشکده دندانپزشکی",
                    "program_name": "دندانپزشکی عمومی",
                    "degree_level": "professional-doctorate",
                    "demand_count": 3,
                    "earliest_requested_at": "2026-01-01T00:00:00Z",
                }],
                "next_cursor": "cursor-2",
            },
            "cursor-2": {
                "items": [{
                    "program_id": PROGRAM2,
                    "entry_year": 1401,
                    "institution_name": "دانشگاه شیراز",
                    "faculty_name": "دانشکده پزشکی",
                    "program_name": "پزشکی عمومی",
                    "degree_level": "professional-doctorate",
                    "demand_count": 1,
                    "earliest_requested_at": "2025-06-01T00:00:00Z",
                }],
                "next_cursor": None,
            },
        }
        self.approve_calls: list[tuple] = []
        self.decline_calls: list[tuple] = []
        self.approve_error: Exception | None = None
        self.decline_error: Exception | None = None
        self.approve_result: dict = {"workspace_id": "ws-new", "workspace_created": True, "resolved_count": 3}
        self.decline_result: dict = {"declined_count": 3}

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject in self.subjects_that_can_manage}

    def class_creation_requests_list(self, platform, subject, limit=10, cursor=None):
        return {
            "items": list((self.groups_by_cursor.get(cursor) or {"items": []})["items"]),
            "next_cursor": (self.groups_by_cursor.get(cursor) or {"next_cursor": None})["next_cursor"],
        }

    def _remove_group(self, program_id, entry_year):
        for page in self.groups_by_cursor.values():
            page["items"] = [
                item for item in page["items"]
                if not (item["program_id"] == program_id and item["entry_year"] == entry_year)
            ]

    def class_creation_requests_approve(self, platform, subject, program_id, entry_year, cohort_label, workspace_name):
        self.approve_calls.append((platform, subject, program_id, entry_year, cohort_label, workspace_name))
        if self.approve_error is not None:
            raise self.approve_error
        self._remove_group(program_id, entry_year)
        return dict(self.approve_result)

    def class_creation_requests_decline(self, platform, subject, program_id, entry_year):
        self.decline_calls.append((platform, subject, program_id, entry_year))
        if self.decline_error is not None:
            raise self.decline_error
        self._remove_group(program_id, entry_year)
        return dict(self.decline_result)


class _BaseClassCreationRequestTest:
    app_class = BotApplication

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = ClassCreationRequestBackend()
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
        return self.app.callback(subject, True, "cqlist")

    # 1) Only an owner sees the entry point from management, and for nobody
    # else -- neither in the menu nor via a direct callback attempt.
    def test_entry_point_appears_only_for_owner(self):
        self.backend.subjects_that_can_manage.add("owner")
        management = self.app.management("owner", True)
        self.assertIn("📋 درخواست‌های ساخت کلاس", self.labels(management.screen))

        denied_management = self.app.management("student", True)
        self.assertEqual(denied_management.screen.presentation.semantic_kind, "management_denied")
        self.assertNotIn("📋 درخواست‌های ساخت کلاس", self.labels(denied_management.screen))

        direct = self.app.callback("student", True, "cqlist")
        self.assertEqual(direct.screen.presentation.semantic_kind, "creq_list_denied")

    # 2) The demand count renders on the list, grouped per identity.
    def test_list_shows_demand_count_and_resolved_labels(self):
        first_page = self._begin()
        self.assertEqual(first_page.screen.presentation.semantic_kind, "creq_list")
        labels = self.labels(first_page.screen)
        self.assertTrue(any("۳ نفر" in label for label in labels), labels)
        self.assertTrue(any("دندانپزشکی عمومی" in label for label in labels), labels)
        self.assertIn("بعدی ›", labels)

    def test_list_pagination_shows_second_page(self):
        first_page = self._begin()
        next_ref = next(btn.callback for row in first_page.screen.rows for btn in row if btn.text == "بعدی ›")
        second_page = self.app.callback("owner", True, next_ref)
        self.assertTrue(any("پزشکی عمومی" in label for label in self.labels(second_page.screen)))

    # 3) Approve walkthrough: pick a group, answer the two missing fields
    # (cohort label, class name), confirm -- the backend receives exactly the
    # group identity plus what the owner typed, and the list updates.
    def test_full_approve_walkthrough(self):
        first_page = self._begin()
        pick_ref = first_page.screen.rows[0][0].callback
        detail = self.app.callback("owner", True, pick_ref)
        self.assertEqual(detail.screen.presentation.semantic_kind, "creq_detail")
        self.assertIn("۳ نفر", detail.screen.text)

        approve_ref = next(btn.callback for row in detail.screen.rows for btn in row if btn.text.startswith("✅"))
        step1 = self.app.callback("owner", True, approve_ref)
        self.assertEqual(step1.screen.presentation.semantic_kind, "creq_wizard_step")
        self.assertIn("برچسب ورودی", step1.screen.text)

        step2 = self.app.class_creation_request_wizard_text("owner", "ورودی ۱۴۰۲", True)
        self.assertEqual(step2.screen.presentation.semantic_kind, "creq_wizard_step")
        self.assertIn("نام نمایشی کلاس", step2.screen.text)

        review = self.app.class_creation_request_wizard_text("owner", "دندانپزشکی ۱۴۰۲ - علوم پزشکی تهران", True)
        self.assertEqual(review.screen.presentation.semantic_kind, "creq_wizard_review")
        self.assertIn("✅ ساخت کلاس", self.labels(review.screen))

        created = self.app.callback("owner", True, "cqaconf")
        self.assertEqual(created.screen.presentation.semantic_kind, "creq_wizard_created")
        self.assertEqual(len(self.backend.approve_calls), 1)
        platform, subject, program_id, entry_year, cohort_label, workspace_name = self.backend.approve_calls[0]
        self.assertEqual(platform, "telegram")
        self.assertEqual(subject, "owner")
        self.assertEqual(program_id, PROGRAM1)
        self.assertEqual(entry_year, 1402)
        self.assertEqual(cohort_label, "ورودی ۱۴۰۲")
        self.assertEqual(workspace_name, "دندانپزشکی ۱۴۰۲ - علوم پزشکی تهران")
        self.assertIsNone(self.state.creq_wizard("telegram", "owner"))

        # The list updates: the now-resolved group is gone.
        refreshed = self.app.callback("owner", True, "cqlist")
        self.assertFalse(any("دندانپزشکی عمومی" in label for label in self.labels(refreshed.screen)))

    def test_approve_wizard_cancel_clears_state(self):
        first_page = self._begin()
        pick_ref = first_page.screen.rows[0][0].callback
        detail = self.app.callback("owner", True, pick_ref)
        approve_ref = next(btn.callback for row in detail.screen.rows for btn in row if btn.text.startswith("✅"))
        self.app.callback("owner", True, approve_ref)

        cancelled = self.app.callback("owner", True, "cqacxl")
        self.assertEqual(cancelled.screen.presentation.semantic_kind, "creq_wizard_cancelled")
        self.assertIsNone(self.state.creq_wizard("telegram", "owner"))
        self.assertEqual(self.backend.approve_calls, [])

    # 4) Decline walkthrough: pick a group, confirm, the backend receives the
    # group identity and the list updates.
    def test_decline_walkthrough(self):
        first_page = self._begin()
        pick_ref = first_page.screen.rows[0][0].callback
        detail = self.app.callback("owner", True, pick_ref)
        decline_ref = next(btn.callback for row in detail.screen.rows for btn in row if btn.text.startswith("❌"))
        confirm = self.app.callback("owner", True, decline_ref)
        self.assertEqual(confirm.screen.presentation.semantic_kind, "creq_decline_confirm")

        declined = self.app.callback("owner", True, next(
            btn.callback for row in confirm.screen.rows for btn in row if btn.text.startswith("❌ بله")
        ))
        self.assertEqual(declined.screen.presentation.semantic_kind, "creq_declined")
        self.assertEqual(len(self.backend.decline_calls), 1)
        platform, subject, program_id, entry_year = self.backend.decline_calls[0]
        self.assertEqual(program_id, PROGRAM1)
        self.assertEqual(entry_year, 1402)

        refreshed = self.app.callback("owner", True, "cqlist")
        self.assertFalse(any("دندانپزشکی عمومی" in label for label in self.labels(refreshed.screen)))

    def test_decline_confirm_back_returns_to_detail_without_declining(self):
        first_page = self._begin()
        pick_ref = first_page.screen.rows[0][0].callback
        detail = self.app.callback("owner", True, pick_ref)
        decline_ref = next(btn.callback for row in detail.screen.rows for btn in row if btn.text.startswith("❌"))
        confirm = self.app.callback("owner", True, decline_ref)
        back_ref = next(btn.callback for row in confirm.screen.rows for btn in row if btn.text.startswith("↩️"))
        back = self.app.callback("owner", True, back_ref)
        self.assertEqual(back.screen.presentation.semantic_kind, "creq_detail")
        self.assertEqual(self.backend.decline_calls, [])

    # 5) A backend refusal is surfaced, never swallowed, for both approve and
    # decline.
    def test_approve_backend_failure_is_surfaced_and_logged(self):
        first_page = self._begin()
        pick_ref = first_page.screen.rows[0][0].callback
        detail = self.app.callback("owner", True, pick_ref)
        approve_ref = next(btn.callback for row in detail.screen.rows for btn in row if btn.text.startswith("✅"))
        self.app.callback("owner", True, approve_ref)
        self.app.class_creation_request_wizard_text("owner", "ورودی ۱۴۰۲", True)
        self.app.class_creation_request_wizard_text("owner", "دندانپزشکی ۱۴۰۲", True)

        self.backend.approve_error = RuntimeError("egress proxy unreachable")
        with self.assertLogs(level="ERROR") as captured:
            failed = self.app.callback("owner", True, "cqaconf")
        self.assertEqual(failed.screen.presentation.semantic_kind, "creq_wizard_failed")
        self.assertTrue(any("class creation request approve" in message for message in captured.output))
        # Wizard state survives a failure so the owner is not forced to retype.
        self.assertIsNotNone(self.state.creq_wizard("telegram", "owner"))

    def test_decline_backend_failure_is_surfaced_and_logged(self):
        first_page = self._begin()
        pick_ref = first_page.screen.rows[0][0].callback
        detail = self.app.callback("owner", True, pick_ref)
        decline_ref = next(btn.callback for row in detail.screen.rows for btn in row if btn.text.startswith("❌"))
        confirm = self.app.callback("owner", True, decline_ref)
        yes_ref = next(btn.callback for row in confirm.screen.rows for btn in row if btn.text.startswith("❌ بله"))

        self.backend.decline_error = RuntimeError("egress proxy unreachable")
        with self.assertLogs(level="ERROR") as captured:
            failed = self.app.callback("owner", True, yes_ref)
        self.assertNotEqual(failed.screen.presentation.semantic_kind, "creq_declined")
        self.assertEqual(failed.screen.presentation.severity, "error")
        self.assertTrue(any("class creation request decline" in message for message in captured.output))


class BotApplicationClassCreationRequestTest(_BaseClassCreationRequestTest, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotApplicationClassCreationRequestTest(_BaseClassCreationRequestTest, unittest.TestCase):
    """Same matrix against the class the runtime actually constructs
    (apps/telegram-bot/runtime.py imports from integrated_application)."""

    app_class = IntegratedBotApplication

    def test_wizard_screens_carry_owner_permission_metadata(self):
        first_page = self._begin()
        pick_ref = first_page.screen.rows[0][0].callback
        detail = self.app.callback("owner", True, pick_ref)
        prepared = self.app.prepare_result("owner", True, detail)
        self.assertIn("deployment.manage", prepared.metadata.get("canonical_permissions", ()))


if __name__ == "__main__":
    unittest.main()
