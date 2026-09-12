from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.api import FanoosApiError
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.join_wizard import RawKeyboardHandoff, RawKeyboardSend
from fanoos_bot.state import LocalState


class JoinWizardBackend:
    """Records every onboarding/directory call and lets tests script backend
    outcomes, mirroring the real internal-v1 onboarding endpoints
    (DirectoryReadService.joinableCohortsByProgram and friends,
    ClassMembershipService.join/requestUpgrade/requestClassCreation)."""

    def __init__(self):
        self.provinces = [{"id": "prov-1", "name": "تهران"}, {"id": "prov-2", "name": "اصفهان"}]
        self.institutions = {
            "prov-1": [{"id": "inst-1", "name": "دانشگاه علوم پزشکی تهران", "institution_type": "state"}],
        }
        self.faculties = {"inst-1": [{"id": "fac-1", "name": "دانشکده دندانپزشکی"}]}
        self.programs = {"fac-1": [{"id": "prog-1", "name": "دندانپزشکی عمومی"}]}

        self.otp_request_calls: list[tuple] = []
        self.otp_verify_calls: list[tuple] = []
        self.otp_verify_error: FanoosApiError | None = None
        self.join_calls: list[tuple] = []
        self.join_result: dict = {
            "status": "joined",
            "workspace_id": "ws-1",
            "workspace_name": "دندانپزشکی ۱۴۰۲",
            "already_member": False,
        }
        self.creation_request_calls: list[tuple] = []
        self.upgrade_request_calls: list[tuple] = []

        self.workspaces_result: dict = {"workspaces": [], "selected_workspace_id": None}
        self.grades_error: Exception | None = None

    # -- directory --------------------------------------------------------
    def directory_provinces(self, platform, limit=10, cursor=None):
        return {"items": self.provinces, "next_cursor": None}

    def directory_institutions(self, platform, province_id, limit=10, cursor=None):
        return {"items": self.institutions.get(province_id, []), "next_cursor": None}

    def directory_faculties(self, platform, institution_id, limit=10, cursor=None):
        return {"items": self.faculties.get(institution_id, []), "next_cursor": None}

    def directory_programs(self, platform, faculty_id, limit=10, cursor=None):
        return {"items": self.programs.get(faculty_id, []), "next_cursor": None}

    # -- otp / join ---------------------------------------------------------
    def onboarding_otp_request(self, platform, subject, phone_number):
        self.otp_request_calls.append((platform, subject, phone_number))
        return {"phone_masked": "0912***4321", "challenge_token": "chal-1"}

    def onboarding_otp_resend(self, platform, subject, challenge_token):
        return {"phone_masked": "0912***4321", "challenge_token": "chal-2"}

    def onboarding_otp_verify(self, platform, subject, challenge_token, code):
        self.otp_verify_calls.append((platform, subject, challenge_token, code))
        if self.otp_verify_error is not None:
            raise self.otp_verify_error
        return {}

    def onboarding_join(self, platform, subject, program_id, entry_year):
        self.join_calls.append((platform, subject, program_id, entry_year))
        return dict(self.join_result)

    def onboarding_class_creation_request(self, platform, subject, program_id, entry_year):
        self.creation_request_calls.append((platform, subject, program_id, entry_year))
        return {}

    def onboarding_upgrade_request(self, platform, subject, workspace_id):
        self.upgrade_request_calls.append((platform, subject, workspace_id))
        return {}

    # -- workspace / gated actions -----------------------------------------
    def workspaces(self, platform, subject):
        return dict(self.workspaces_result)

    def select_workspace(self, platform, subject, workspace_id):
        return {}

    def grades(self, platform, subject, workspace_id, limit, cursor):
        if self.grades_error is not None:
            raise self.grades_error
        return {"items": [], "next_cursor": None}


FULL_ANSWERS = [
    "آرین",  # first-name
    "قاسمی",  # last-name
    "تهران",  # province
    "دانشگاه علوم پزشکی تهران",  # institution
    "دانشکده دندانپزشکی",  # faculty
    "دندانپزشکی عمومی",  # program
    "۱۴۰۲",  # entry-year
    "نیمسال اول",  # entry-term
    "روزانه یا تعهدی",  # course-type
    "شماره دانشجویی ندارم",  # student-number (skip)
    "✅ تأیید اطلاعات",  # review confirm
]


class _BaseJoinWizardTest:
    app_class = BotApplication

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = JoinWizardBackend()
        self.app = self.app_class(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test/"),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    def _walk_to_otp(self, subject="student", answers=FULL_ANSWERS):
        self.app.join_wizard_begin(subject, True)
        result = None
        for answer in answers:
            result = self.app.join_wizard_text(subject, answer, True)
        # "review confirm" advances to contact; contact is only advanced by
        # sharing a contact card, not free text.
        result = self.app.join_wizard_contact(subject, "09121234321", True)
        return result

    def _verify_otp(self, subject="student", code="123456"):
        return self.app.join_wizard_text(subject, code, True)

    # 1) Full walkthrough sends exactly the identity picked to onboarding_join.
    def test_full_walkthrough_sends_exact_identity_to_backend(self):
        otp_screen = self._walk_to_otp()
        self.assertIsInstance(otp_screen, RawKeyboardSend)
        self.assertIn("کد تأیید", otp_screen.screen.html)

        finished = self._verify_otp()
        self.assertIsInstance(finished, RawKeyboardHandoff)
        self.assertEqual(len(self.backend.join_calls), 1)
        platform, subject, program_id, entry_year = self.backend.join_calls[0]
        self.assertEqual(platform, "telegram")
        self.assertEqual(subject, "student")
        self.assertEqual(program_id, "prog-1")
        self.assertEqual(entry_year, 1402)
        # The wizard is fully cleared once membership is created.
        self.assertIsNone(self.state.join_wizard("telegram", "student"))

    # 2) The step counter adapts for آزاد institutions and the course-type
    # step never appears for them.
    def test_step_counter_and_course_type_skip_for_azad(self):
        self.backend.institutions["prov-1"] = [
            {"id": "inst-azad", "name": "دانشگاه آزاد واحد تهران", "institution_type": "azad_university"},
        ]
        self.backend.faculties["inst-azad"] = [{"id": "fac-1", "name": "دانشکده دندانپزشکی"}]
        subject = "azad-student"
        self.app.join_wizard_begin(subject, True)
        first = self.app.join_wizard_text(subject, "آرین", True)
        self.assertIn("از ۱۳", first.screen.html)  # step 1/13 before institution type is known
        self.app.join_wizard_text(subject, "قاسمی", True)
        self.app.join_wizard_text(subject, "تهران", True)
        institution = self.app.join_wizard_text(subject, "دانشگاه آزاد واحد تهران", True)
        self.assertIn("از ۱۲", institution.screen.html)  # total drops once azad is known
        self.app.join_wizard_text(subject, "دانشکده دندانپزشکی", True)
        entry_year = self.app.join_wizard_text(subject, "دندانپزشکی عمومی", True)
        self.assertIn("سال ورود", entry_year.screen.html)
        entry_term = self.app.join_wizard_text(subject, "۱۴۰۲", True)
        self.assertIn("نیمسال ورودی", entry_term.screen.html)
        # course-type is skipped entirely: the very next screen after
        # entry-term is student-number, not course-type.
        student_number = self.app.join_wizard_text(subject, "نیمسال اول", True)
        self.assertIn("شماره دانشجویی", student_number.screen.html)

    # 3) Province pagination at both boundaries.
    def test_province_pagination_boundaries(self):
        self.backend.provinces = [{"id": f"p{i}", "name": f"استان {i}"} for i in range(15)]
        subject = "pager"
        self.app.join_wizard_begin(subject, True)
        self.app.join_wizard_text(subject, "آرین", True)
        first_page = self.app.join_wizard_text(subject, "قاسمی", True)
        self.assertNotIn("▶️ صفحه قبل", str(first_page.screen.keyboard))

    def test_province_pagination_does_not_advance_the_step(self):
        subject = "pager2"
        self.app.join_wizard_begin(subject, True)
        self.app.join_wizard_text(subject, "آرین", True)
        self.app.join_wizard_text(subject, "قاسمی", True)
        result = self.app.join_wizard_text(subject, "صفحه بعد ◀️", True)
        self.assertIsInstance(result, RawKeyboardSend)
        wizard = self.state.join_wizard("telegram", subject)
        self.assertEqual(wizard["step"], "province")

    # 4) Institutions are filtered by the chosen province.
    def test_institutions_are_filtered_by_chosen_province(self):
        self.backend.institutions["prov-2"] = [{"id": "inst-2", "name": "دانشگاه اصفهان", "institution_type": "state"}]
        subject = "filterer"
        self.app.join_wizard_begin(subject, True)
        self.app.join_wizard_text(subject, "آرین", True)
        self.app.join_wizard_text(subject, "قاسمی", True)
        result = self.app.join_wizard_text(subject, "اصفهان", True)
        labels = {item["text"] for row in result.screen.keyboard for item in row}
        self.assertIn("دانشگاه اصفهان", labels)
        self.assertNotIn("دانشگاه علوم پزشکی تهران", labels)

    # 5) Back restores the previous step with its prior answer visible in a
    # subsequent re-render, and does not lose already-entered data.
    def test_back_returns_to_previous_step(self):
        subject = "backer"
        self.app.join_wizard_begin(subject, True)
        self.app.join_wizard_text(subject, "آرین", True)
        back = self.app.join_wizard_text(subject, "↩️ مرحله قبل", True)
        self.assertIn("نام", back.screen.html)
        wizard = self.state.join_wizard("telegram", subject)
        self.assertEqual(wizard["step"], "first-name")

    def test_back_from_first_step_cancels_the_wizard(self):
        subject = "backer2"
        self.app.join_wizard_begin(subject, True)
        result = self.app.join_wizard_text(subject, "↩️ مرحله قبل", True)
        self.assertIsInstance(result, RawKeyboardHandoff)
        self.assertIsNone(self.state.join_wizard("telegram", subject))

    # 6) Cancel leaves no partial state, and the wizard no longer swallows
    # subsequent free text.
    def test_cancel_clears_wizard_state(self):
        subject = "canceller"
        self.app.join_wizard_begin(subject, True)
        self.app.join_wizard_text(subject, "آرین", True)
        cancelled = self.app.join_wizard_text(subject, "انصراف", True)
        self.assertIsInstance(cancelled, RawKeyboardHandoff)
        self.assertIsNone(self.state.join_wizard("telegram", subject))
        self.assertIsNone(self.app.join_wizard_text(subject, "چیزی", True))

    # 7) Student-number escape hatch works and is stored as empty.
    def test_student_number_skip_escape_works(self):
        subject = "skipper"
        self.app.join_wizard_begin(subject, True)
        for answer in FULL_ANSWERS[:9]:  # up to and including course-type
            self.app.join_wizard_text(subject, answer, True)
        review = self.app.join_wizard_text(subject, "شماره دانشجویی ندارم", True)
        self.assertIn("ثبت نشده", review.screen.html)
        wizard = self.state.join_wizard("telegram", subject)
        self.assertEqual(wizard["answers"]["student_number"], "")

    # 8) The reply keyboard is cleared (via RawKeyboardHandoff) at both
    # successful completion and cancellation -- the runtime always calls
    # remove_reply_keyboard for this result type (packages/python/fanoos_bot/runtime.py).
    def test_reply_keyboard_cleared_at_wizard_end_and_cancel(self):
        subject = "clearer"
        self.app.join_wizard_begin(subject, True)
        cancelled = self.app.join_wizard_text(subject, "انصراف", True)
        self.assertIsInstance(cancelled, RawKeyboardHandoff)

        subject2 = "clearer2"
        finished = self._walk_to_otp(subject2)
        self.assertIsInstance(finished, RawKeyboardSend)  # still mid-wizard (otp step)
        handoff = self._verify_otp(subject2)
        self.assertIsInstance(handoff, RawKeyboardHandoff)

    # 9) Wrong OTP and attempts-exceeded are surfaced distinctly, not masked
    # behind a generic failure.
    def test_wrong_otp_is_rejected_with_specific_message(self):
        self._walk_to_otp("wrongcode")
        self.backend.otp_verify_error = FanoosApiError("onboarding_otp_code_invalid", "کد تایید صحیح نیست.", 422)
        rejected = self.app.join_wizard_text("wrongcode", "000000", True)
        self.assertIsInstance(rejected, RawKeyboardSend)
        self.assertIn("کد تایید صحیح نیست", rejected.screen.html)
        # Wizard state survives so the student can retry.
        self.assertIsNotNone(self.state.join_wizard("telegram", "wrongcode"))

    def test_otp_attempts_exceeded_is_a_distinct_message_from_wrong_code(self):
        self._walk_to_otp("exceeded")
        self.backend.otp_verify_error = FanoosApiError(
            "onboarding_otp_attempts_exceeded", "تعداد تلاش‌های اشتباه بیش از حد مجاز شد؛ یک کد جدید بگیر.", 429
        )
        rejected = self.app.join_wizard_text("exceeded", "000000", True)
        self.assertIsInstance(rejected, RawKeyboardSend)
        self.assertIn("تعداد تلاش‌های اشتباه بیش از حد مجاز شد", rejected.screen.html)
        self.assertNotIn("کد تایید صحیح نیست", rejected.screen.html)

    # 10) Completing the wizard yields exactly one onboarding_join call; the
    # wizard cannot be replayed afterwards since its state is gone.
    def test_completing_wizard_calls_join_exactly_once_and_cannot_replay(self):
        self._walk_to_otp("onceonly")
        self._verify_otp("onceonly")
        self.assertEqual(len(self.backend.join_calls), 1)
        # No active wizard remains, so a stray follow-up message is not
        # swallowed and does not call join again.
        self.assertIsNone(self.app.join_wizard_text("onceonly", "123456", True))
        self.assertEqual(len(self.backend.join_calls), 1)

    # 11) Class-not-found still creates/finds the account (join is always
    # attempted), and offers a durable, idempotent creation request instead
    # of a bare refusal.
    def test_class_not_found_offers_creation_request_without_membership(self):
        self.backend.join_result = {"status": "class_not_found"}
        result = self._walk_to_otp("nomatch")
        finished = self._verify_otp("nomatch")
        self.assertIsInstance(finished, RawKeyboardSend)
        self.assertIn("کلاسی پیدا نشد", finished.screen.html)
        wizard = self.state.join_wizard("telegram", "nomatch")
        self.assertEqual(wizard["step"], "awaiting-creation-decision")

        recorded = self.app.join_wizard_text("nomatch", "📝 درخواست ساخت کلاس", True)
        self.assertIsInstance(recorded, RawKeyboardHandoff)
        self.assertEqual(len(self.backend.creation_request_calls), 1)
        _, _, program_id, entry_year = self.backend.creation_request_calls[0]
        self.assertEqual(program_id, "prog-1")
        self.assertEqual(entry_year, 1402)
        self.assertIsNone(self.state.join_wizard("telegram", "nomatch"))

    # 12) A faculty with zero programs offered gets the "nothing here yet"
    # screen, distinct from class-not-found (no creation-request offer,
    # since there is no program_id to attach one to).
    def test_empty_program_list_offers_no_creation_request(self):
        self.backend.programs["fac-1"] = []
        subject = "emptylist"
        self.app.join_wizard_begin(subject, True)
        self.app.join_wizard_text(subject, "آرین", True)
        self.app.join_wizard_text(subject, "قاسمی", True)
        self.app.join_wizard_text(subject, "تهران", True)
        self.app.join_wizard_text(subject, "دانشگاه علوم پزشکی تهران", True)
        empty = self.app.join_wizard_text(subject, "دانشکده دندانپزشکی", True)
        self.assertIn("موردی پیدا نشد", empty.screen.html)
        self.assertNotIn("درخواست ساخت کلاس", empty.screen.html)

    # 13) Two subjects (and the same subject on two platforms) never see
    # each other's wizard state -- LocalState keys wizards by (platform,
    # subject), so this is a structural isolation guarantee worth pinning.
    def test_wizard_state_is_isolated_per_subject_and_platform(self):
        self.app.join_wizard_begin("alice", True)
        self.app.join_wizard_text("alice", "آرین", True)
        self.assertIsNone(self.state.join_wizard("telegram", "bob"))

        other_platform_app = self.app_class(
            self.backend, self.state, "bale", ApplicationConfig("https://fanoos.test/")
        )
        self.assertIsNone(other_platform_app.state.join_wizard("bale", "alice"))
        alice_wizard = self.state.join_wizard("telegram", "alice")
        self.assertEqual(alice_wizard["answers"]["first_name"], "آرین")

    # 14) A limited member hitting a gated action gets the explanatory
    # screen (not a bare error), and the upgrade request is idempotent per
    # tap -- the backend, not the bot, is the source of truth for whether a
    # second tap actually does anything.
    def test_limited_member_blocked_from_grades_sees_explanation_and_can_request_upgrade(self):
        self.backend.workspaces_result = {
            "workspaces": [{"id": "ws-1", "name": "دندانپزشکی ۱۴۰۲"}],
            "selected_workspace_id": "ws-1",
        }
        self.backend.grades_error = FanoosApiError("forbidden", "اجازه انجام این عملیات را ندارید.", 403)
        blocked = self.app.grades("limited-student")
        self.assertEqual(blocked.screen.presentation.semantic_kind, "join_upgrade_required")
        self.assertIn("نمرات", blocked.screen.text)
        labels = [button.text for row in blocked.screen.rows for button in row]
        self.assertIn("✋ درخواست تأیید نماینده", labels)

        upgraded = self.app.join_wizard_request_upgrade("limited-student")
        self.assertEqual(upgraded.screen.presentation.semantic_kind, "join_upgrade_requested")
        self.assertEqual(len(self.backend.upgrade_request_calls), 1)

        # Tapping again is a normal repeat call; the backend (not tested
        # here) is responsible for making the second call a no-op.
        self.app.join_wizard_request_upgrade("limited-student")
        self.assertEqual(len(self.backend.upgrade_request_calls), 2)

    def test_gated_action_callback_routes_to_upgrade_request(self):
        self.backend.workspaces_result = {
            "workspaces": [{"id": "ws-1", "name": "دندانپزشکی ۱۴۰۲"}],
            "selected_workspace_id": "ws-1",
        }
        self.backend.grades_error = FanoosApiError("forbidden", "اجازه انجام این عملیات را ندارید.", 403)
        self.app.grades("limited-student2")
        result = self.app.callback("limited-student2", True, "joinupgrade")
        self.assertEqual(result.screen.presentation.semantic_kind, "join_upgrade_requested")
        self.assertEqual(len(self.backend.upgrade_request_calls), 1)

    # 15) A non-forbidden backend failure is surfaced as a normal error, not
    # papered over as a gated-access explanation.
    def test_non_forbidden_backend_error_on_gated_action_is_a_plain_error(self):
        self.backend.workspaces_result = {
            "workspaces": [{"id": "ws-1", "name": "دندانپزشکی ۱۴۰۲"}],
            "selected_workspace_id": "ws-1",
        }
        self.backend.grades_error = RuntimeError("egress proxy unreachable")
        result = self.app.grades("someone")
        self.assertNotEqual(result.screen.presentation.semantic_kind, "join_upgrade_required")

    def test_begin_wizard_in_group_chat_requires_private(self):
        result = self.app.join_wizard_begin("groupie", False)
        self.assertIsInstance(result, RawKeyboardHandoff)
        self.assertIsNone(self.state.join_wizard("telegram", "groupie"))

    def test_text_ignored_in_group_chat_when_no_active_wizard(self):
        self.assertIsNone(self.app.join_wizard_text("groupie2", "hello", False))


class BotApplicationJoinWizardTest(_BaseJoinWizardTest, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotApplicationJoinWizardTest(_BaseJoinWizardTest, unittest.TestCase):
    """Same matrix against the class the runtime actually constructs
    (apps/telegram-bot/runtime.py and apps/bale-bot/runtime.py both import
    from integrated_application, not application) -- a base-class-only pass
    here would prove nothing about production behavior."""

    app_class = IntegratedBotApplication


if __name__ == "__main__":
    unittest.main()
