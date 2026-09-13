from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState

WS1 = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa"
WS2 = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb"
ANN1 = "11111111-1111-4111-8111-111111111111"


class AnnouncementBackend:
    """Mirrors the real contract: announcements/list is a shared read (any
    active member), gated per-item only by workspace membership; the
    additive can_publish flag reflects notification.broadcast for that
    (subject, workspace) pair, computed the same way academic_terms_list's
    can_override is. announcement_publish re-checks the same permission
    server-side regardless of what can_publish already said."""

    def __init__(self):
        self.member_of: dict[str, set[str]] = {}
        self.representative_of: dict[str, set[str]] = {}
        self.workspaces_result: dict[str, dict] = {}
        self.published: dict[str, list[dict]] = {}
        self.publish_calls: list[tuple] = []
        self.publish_error: Exception | None = None

    def workspaces(self, platform, subject):
        return dict(self.workspaces_result.get(subject) or {"workspaces": [], "selected_workspace_id": None})

    def select_workspace(self, platform, subject, workspace_id):
        return {}

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": False}

    def representative_requests_list(self, platform, subject, workspace_id):
        raise FanoosApiError("forbidden", "no membership.approve", 403)

    def academic_terms_list(self, platform, subject, workspace_id):
        raise FanoosApiError("forbidden", "no academic.manage", 403)

    def announcements(self, platform, subject, workspace_id, limit=20, cursor=None, course_id=None):
        if workspace_id not in self.member_of.get(subject, set()):
            raise FanoosApiError("forbidden", "not an active member of this workspace", 403)
        items = list(self.published.get(workspace_id, []))
        return {
            "items": items[:limit],
            "next_cursor": None,
            "can_publish": workspace_id in self.representative_of.get(subject, set()),
        }

    def announcement_publish(self, platform, subject, workspace_id, title, body, course_id=None):
        self.publish_calls.append((platform, subject, workspace_id, title, body))
        if self.publish_error is not None:
            raise self.publish_error
        if workspace_id not in self.representative_of.get(subject, set()):
            raise FanoosApiError("forbidden", "no notification.broadcast", 403)
        item = {
            "id": ANN1,
            "title": title,
            "body": body,
            "published_at": "2026-01-01T08:00:00+00:00",
        }
        self.published.setdefault(workspace_id, []).append(item)
        return {"id": ANN1}


class _BaseAnnouncementComposeTest:
    app_class = BotApplication

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = AnnouncementBackend()
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

    def _select(self, subject: str, workspace_id: str) -> None:
        self.backend.workspaces_result[subject] = {
            "workspaces": [{"id": workspace_id, "name": "کلاس آزمایشی"}],
            "selected_workspace_id": workspace_id,
        }
        self.backend.member_of.setdefault(subject, set()).add(workspace_id)

    def _make_representative(self, subject: str, workspace_id: str = WS1) -> None:
        self._select(subject, workspace_id)
        self.backend.representative_of.setdefault(subject, set()).add(workspace_id)

    def _begin(self, subject: str = "rep") -> object:
        self._make_representative(subject)
        return self.app.callback(subject, True, "annnew")

    def _walk_to_review(self, subject: str = "rep", title: str = "اطلاعیه آزمایشی", body: str = "متن اطلاعیه آزمایشی"):
        self._begin(subject)
        self.app.announcement_compose_text(subject, title, True)
        return self.app.announcement_compose_text(subject, body, True)

    # 1) Only a representative sees the entry point -- neither in the legacy
    # "بیشتر" menu nor by a direct attempt.
    def test_entry_point_visible_only_to_representative_in_more_menu(self):
        self._make_representative("rep")
        self._select("student", WS1)
        rep_more = self.app.more("rep", True)
        self.assertIn("📢 اطلاعیه‌های کلاس", self.labels(rep_more.screen))
        student_more = self.app.more("student", True)
        self.assertNotIn("📢 اطلاعیه‌های کلاس", self.labels(student_more.screen))

    def test_direct_compose_attempt_by_non_representative_is_refused(self):
        self._select("student", WS1)
        result = self.app.callback("student", True, "annnew")
        self.assertEqual(result.screen.presentation.semantic_kind, "announcement_compose_denied")
        self.assertIsNone(self.state.announcement_wizard("telegram", "student"))

    def test_direct_confirm_with_no_wizard_is_refused_and_publishes_nothing(self):
        self._select("student", WS1)
        result = self.app.callback("student", True, "annconf")
        self.assertEqual(result.screen.presentation.semantic_kind, "announcement_compose_expired")
        self.assertEqual(self.backend.publish_calls, [])

    # 2) compose -> preview shows exactly what will be sent.
    def test_step_counter_and_compose_flow_to_preview(self):
        begin = self._begin()
        self.assertEqual(begin.screen.presentation.semantic_kind, "announcement_compose_step")
        self.assertIn("مرحله ۱ از ۳", begin.screen.text)

        title_step = self.app.announcement_compose_text("rep", "اطلاعیه آزمایشی", True)
        self.assertEqual(title_step.screen.presentation.semantic_kind, "announcement_compose_step")
        self.assertIn("مرحله ۲ از ۳", title_step.screen.text)
        self.assertIn("متن اطلاعیه", title_step.screen.text)

        review = self.app.announcement_compose_text("rep", "متن اطلاعیه آزمایشی", True)
        self.assertEqual(review.screen.presentation.semantic_kind, "announcement_compose_review")
        self.assertIn("اطلاعیه آزمایشی", review.screen.text)
        self.assertIn("متن اطلاعیه آزمایشی", review.screen.text)
        self.assertIn("✅ تأیید و ارسال", self.labels(review.screen))

    def test_full_walkthrough_sends_exact_title_and_body_to_backend(self):
        self._walk_to_review(title="اطلاعیه نهایی", body="متن نهایی برای ارسال")
        sent = self.app.callback("rep", True, "annconf")
        self.assertEqual(sent.screen.presentation.semantic_kind, "announcement_compose_sent")
        self.assertEqual(len(self.backend.publish_calls), 1)
        platform, subject, workspace_id, title, body = self.backend.publish_calls[0]
        self.assertEqual((platform, subject, workspace_id), ("telegram", "rep", WS1))
        self.assertEqual(title, "اطلاعیه نهایی")
        self.assertEqual(body, "متن نهایی برای ارسال")
        self.assertIsNone(self.state.announcement_wizard("telegram", "rep"))

    # 3) cancelling at preview sends nothing.
    def test_cancelling_at_preview_sends_nothing(self):
        self._walk_to_review()
        cancelled = self.app.callback("rep", True, "anncncl")
        self.assertEqual(cancelled.screen.presentation.semantic_kind, "announcement_compose_cancelled")
        self.assertEqual(self.backend.publish_calls, [])
        self.assertIsNone(self.state.announcement_wizard("telegram", "rep"))

    # 4) back restores the previous step with its text intact; re-answering
    # overwrites only that field.
    def test_back_returns_previous_step_and_keeps_prior_answer(self):
        self._begin()
        self.app.announcement_compose_text("rep", "عنوان اول", True)
        back = self.app.callback("rep", True, "annback")
        self.assertEqual(back.screen.presentation.semantic_kind, "announcement_compose_step")
        self.assertIn("مرحله ۱ از ۳", back.screen.text)
        self.assertIn("عنوان اول", back.screen.text)

        self.app.announcement_compose_text("rep", "عنوان دوم", True)
        review = self.app.announcement_compose_text("rep", "متن نهایی", True)
        self.assertIn("عنوان دوم", review.screen.text)
        self.assertNotIn("عنوان اول", review.screen.text)

    def test_back_from_first_step_stays_on_first_step(self):
        self._begin()
        result = self.app.callback("rep", True, "annback")
        self.assertEqual(result.screen.presentation.semantic_kind, "announcement_compose_step")
        self.assertIn("مرحله ۱ از ۳", result.screen.text)

    # 5) cancel leaves no partial state, and a later free-text message is not
    # swallowed by a dead wizard.
    def test_cancel_clears_wizard_state(self):
        self._begin()
        self.app.announcement_compose_text("rep", "عنوان", True)
        cancelled = self.app.callback("rep", True, "anncncl")
        self.assertEqual(cancelled.screen.presentation.semantic_kind, "announcement_compose_cancelled")
        self.assertIsNone(self.state.announcement_wizard("telegram", "rep"))
        self.assertIsNone(self.app.announcement_compose_text("rep", "چیزی", True))

    def test_title_too_short_is_rejected_without_advancing(self):
        self._begin()
        rejected = self.app.announcement_compose_text("rep", "کم", True)
        self.assertEqual(rejected.screen.presentation.severity, "warning")
        wizard = self.state.announcement_wizard("telegram", "rep")
        self.assertEqual(wizard["step_index"], 0)

    def test_empty_body_is_rejected_without_advancing(self):
        self._begin()
        self.app.announcement_compose_text("rep", "عنوان معتبر", True)
        rejected = self.app.announcement_compose_text("rep", "   ", True)
        self.assertEqual(rejected.screen.presentation.severity, "warning")
        wizard = self.state.announcement_wizard("telegram", "rep")
        self.assertEqual(wizard["step_index"], 1)

    # 6) a backend refusal on confirm is surfaced and logged, never swallowed;
    # wizard state survives so a retry does not force retyping.
    def test_backend_failure_on_confirm_is_surfaced_and_logged_then_retryable(self):
        self._walk_to_review()
        self.backend.publish_error = RuntimeError("egress proxy unreachable")
        with self.assertLogs(level="ERROR") as captured:
            failed = self.app.callback("rep", True, "annconf")
        self.assertEqual(failed.screen.presentation.semantic_kind, "announcement_compose_failed")
        self.assertEqual(failed.screen.presentation.severity, "error")
        self.assertTrue(any("announcement compose confirm" in message for message in captured.output))
        self.assertIsNotNone(self.state.announcement_wizard("telegram", "rep"))

        self.backend.publish_error = None
        retried = self.app.callback("rep", True, "annconf")
        self.assertEqual(retried.screen.presentation.semantic_kind, "announcement_compose_sent")

    # 7) list and detail render, scoped to the representative's own class.
    def test_list_and_detail_render_scoped_to_own_class(self):
        self._walk_to_review(title="اطلاعیه فهرست", body="متن برای فهرست و جزئیات")
        self.app.callback("rep", True, "annconf")

        listing = self.app.callback("rep", True, "annlist")
        self.assertEqual(listing.screen.presentation.semantic_kind, "announcement_list")
        self.assertIn("اطلاعیه فهرست", listing.screen.text)

        detail_ref = listing.screen.rows[0][0].callback
        self.assertEqual(detail_ref, f"annview:{ANN1}")
        detail = self.app.callback("rep", True, detail_ref)
        self.assertEqual(detail.screen.presentation.semantic_kind, "announcement_detail")
        self.assertIn("متن برای فهرست و جزئیات", detail.screen.text)

    def test_detail_from_another_class_is_not_reachable(self):
        self._walk_to_review(title="اطلاعیه محرمانه کلاس A", body="فقط برای کلاس A")
        self.app.callback("rep", True, "annconf")

        self._make_representative("rep-b", WS2)
        leaked = self.app.callback("rep-b", True, f"annview:{ANN1}")
        self.assertNotEqual(leaked.screen.presentation.semantic_kind, "announcement_detail")


class BotApplicationAnnouncementComposeTest(_BaseAnnouncementComposeTest, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotApplicationAnnouncementComposeTest(_BaseAnnouncementComposeTest, unittest.TestCase):
    """Same matrix against the class the runtime actually constructs
    (apps/telegram-bot/runtime.py imports from integrated_application)."""

    app_class = IntegratedBotApplication

    def test_home_shell_shows_entry_point_only_to_representative(self):
        self._make_representative("rep")
        self._select("student", WS1)

        def has_announcements_row(screen) -> bool:
            for row in screen.action_rows:
                for action in row.actions:
                    if action.intent and action.intent.name == "core.ann.manage":
                        return True
            return False

        rep_home = self.app.home("rep")
        self.assertTrue(has_announcements_row(rep_home.screen))

        student_home = self.app.home("student")
        self.assertFalse(has_announcements_row(student_home.screen))

    def test_home_shell_entry_point_reaches_compose_hub(self):
        self._make_representative("rep")
        home = self.app.home("rep")
        intent = next(
            action.intent
            for row in home.screen.action_rows
            for action in row.actions
            if action.intent and action.intent.name == "core.ann.manage"
        )
        result = self.app.callback("rep", True, intent.compact())
        prepared = self.app.prepare_result("rep", True, result)
        self.assertEqual(prepared.screen.presentation.semantic_kind, "announcement_manage")
        self.assertIn("✍️ ثبت اطلاعیه", [button.text for row in prepared.screen.rows for button in row])


if __name__ == "__main__":
    unittest.main()
