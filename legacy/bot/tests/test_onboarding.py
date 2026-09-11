from __future__ import annotations

import tempfile
import threading
import time
import unittest
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.api import BotApiError
from dent_bot.onboarding import (
    BACK_STEP,
    CANCEL,
    CLASS_OTP,
    CONFIRM_PROFILE,
    SHARE_CONTACT,
    SKIP_STUDENT_NUMBER,
    START_CLASS,
    START_GENERIC,
)
from dent_bot.state import BotState


CATALOG = {
    "success": True,
    "contractVersion": "bot-onboarding-v1",
    "majors": ["دندانپزشکی", "پزشکی", "داروسازی"],
    "entryYears": ["۱۳۹۹", "۱۴۰۰", "۱۴۰۱", "۱۴۰۲", "۱۴۰۳", "۱۴۰۴", "۱۴۰۵"],
    "entryTerms": ["نیمسال اول", "نیمسال دوم"],
    "courseTypes": ["روزانه یا تعهدی", "شهریه پرداز", "بین الملل"],
    "provinces": ["تهران"],
    "institutions": [
        {"province": "تهران", "name": "دانشگاه علوم پزشکی تهران", "system": "public"},
        {"province": "تهران", "name": "دانشگاه علوم پزشکی آزاد اسلامی تهران", "system": "azad"},
    ],
}


class FakeApi:
    def __init__(self) -> None:
        self.sent = []
        self.edited = []
        self.answered = []
        self.removed = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id, message_id, text, keyboard):
        self.edited.append((chat_id, message_id, text, keyboard))
        return {"message_id": message_id}

    def answer_callback(self, callback_id, text="", *, show_alert=False):
        self.answered.append((callback_id, text, show_alert))
        return True

    def remove_reply_keyboard(self, chat_id):
        self.removed.append(chat_id)
        return {"message_id": 900 + len(self.removed)}


class OnboardingSite:
    def __init__(self) -> None:
        self.requested = []
        self.verified = []
        self.profile = None

    def account(self, _user_id):
        return {"success": True, "linked": False, "identity": {}, "onboardingProfile": self.profile}

    def onboarding_catalog(self, _user_id):
        return dict(CATALOG)

    def request_onboarding_otp(self, user_id, *, profile, phone_number):
        self.requested.append((user_id, dict(profile), phone_number))
        return {"success": True, "challengeRef": "challenge_abcdefghijklmnopqrstuvwxyz", "phoneMasked": "09*****0305"}

    def resend_onboarding_otp(self, _user_id, *, challenge_ref):
        return {"success": True, "challengeRef": challenge_ref, "phoneMasked": "09*****0305"}

    def verify_onboarding_otp(self, user_id, *, challenge_ref, code):
        self.verified.append((user_id, challenge_ref, code))
        profile = dict(self.requested[-1][1])
        profile["phoneMasked"] = "09*****0305"
        profile["verifiedAt"] = "2026-08-27T20:00:00+03:30"
        profile["isClassMember"] = False
        self.profile = profile
        return {"success": True, "complete": True, "profile": profile}


def message(user_id: int, text: str = "", *, contact: dict | None = None) -> dict:
    value = {
        "message_id": 1,
        "text": text,
        "chat": {"id": user_id, "type": "private"},
        "from": {"id": user_id, "first_name": "آرین"},
    }
    if contact is not None:
        value["contact"] = contact
    return {"message": value}


class OnboardingTests(unittest.TestCase):
    def test_required_telegram_channel_blocks_every_interaction_until_membership(self) -> None:
        class MembershipApi(FakeApi):
            def __init__(self) -> None:
                super().__init__()
                self.member = False
                self.checks = []

            def is_chat_member(self, chat_id, user_id):
                self.checks.append((chat_id, user_id))
                return self.member

        class CountingSite(OnboardingSite):
            def __init__(self) -> None:
                super().__init__()
                self.account_calls = 0

            def account(self, user_id):
                self.account_calls += 1
                return super().account(user_id)

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = MembershipApi()
            site = CountingSite()
            app = DentBotApp(
                api, state, owner_id=10, site_url="https://example.test", site_api=site,
                platform="telegram", required_channel_username="Dent1402Booklets",
            )
            try:
                app.handle(message(20, "/start"))
                self.assertEqual(site.account_calls, 0)
                self.assertIn("عضویت در کانال الزامی", api.sent[-1][1])
                self.assertIn("https://t.me/Dent1402Booklets", str(api.sent[-1][2]))
                app.handle({"callback_query": {
                    "id": "membership-recheck", "from": {"id": 20},
                    "data": "v1:membership-check",
                    "message": {"message_id": 9, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(site.account_calls, 0)
                self.assertIn("هنوز عضو کانال نیستی", api.answered[-1][1])
                self.assertIs(api.answered[-1][2], True)
                api.member = True
                answers_before_success = len(api.answered)
                app.handle({"callback_query": {
                    "id": "membership-accepted", "from": {"id": 20},
                    "data": "v1:membership-check",
                    "message": {"message_id": 9, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertGreater(site.account_calls, 0)
                self.assertIn("خوش آمدی به دنت‌یار", api.edited[-1][2])
                self.assertEqual(len(api.answered), answers_before_success + 1)
                self.assertIn("عضویت تأیید شد", api.answered[-1][1])
                self.assertIs(api.answered[-1][2], False)
                self.assertEqual(api.checks, [
                    ("@Dent1402Booklets", 20),
                    ("@Dent1402Booklets", 20),
                    ("@Dent1402Booklets", 20),
                ])
            finally:
                state.close()

    def test_repeated_start_resumes_saved_step_instead_of_resetting_intake(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=OnboardingSite())
            try:
                app.handle(message(20, START_GENERIC))
                app.handle(message(20, "آرین"))
                app.handle(message(20, "قاسم پور"))
                before = state.dialog(20)
                self.assertEqual(before["step"], "major")
                app.handle(message(20, "/start"))
                self.assertEqual(state.dialog(20), before)
                self.assertIn("رشته تحصیلی", api.sent[-1][1])
                self.assertNotIn("خوش آمدی به دنت‌یار", api.sent[-1][1])
            finally:
                state.close()

    def test_repeated_start_after_authentication_does_not_repeat_cleanup_message(self) -> None:
        class AuthenticatedSite(OnboardingSite):
            def account(self, _user_id):
                return {
                    "success": True,
                    "linked": True,
                    "authComplete": True,
                    "user": {"name": "کاربر تأییدشده"},
                    "identity": {"recognized": True},
                }

        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=AuthenticatedSite(), platform=platform,
                )
                try:
                    # Simulate the final auth reply keyboard still being active.
                    state.mark_reply_keyboard_active(20)
                    app.handle(message(20, "/start"))
                    self.assertEqual(api.removed, [20])
                    self.assertIn("دنت‌یار | ورودی", api.sent[-1][1])

                    app.handle(message(20, "/start"))
                    app.handle(message(20, "/start"))
                    self.assertEqual(api.removed, [20])
                    self.assertTrue(all("ورود کامل شد" not in item[1] for item in api.sent))
                finally:
                    state.close()

    def test_intake_survives_a_short_pause_and_corrupt_step_recovers_without_data_loss(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=OnboardingSite())
            try:
                payload = {"firstName": "آرین", "lastName": "قاسم پور"}
                state.start_dialog(20, "onboarding-v1", "obsolete-step", payload)
                with state._lock:
                    state.connection.execute(
                        "UPDATE bot_dialogs SET updated_at=datetime('now','-2 hours') WHERE user_id=?",
                        (20,),
                    )
                    state.connection.commit()
                self.assertIsNotNone(state.dialog(20))
                app.handle(message(20, "دکمهٔ قدیمی"))
                recovered = state.dialog(20)
                self.assertEqual(recovered["step"], "major")
                self.assertEqual(recovered["payload"], payload)
                self.assertIn("اطلاعاتت پاک نشد", api.sent[-1][1])

                state.start_dialog(21, "payment-offer", "title", {})
                with state._lock:
                    state.connection.execute(
                        "UPDATE bot_dialogs SET updated_at=datetime('now','-2 hours') WHERE user_id=?",
                        (21,),
                    )
                    state.connection.commit()
                self.assertIsNone(state.dialog(21))
            finally:
                state.close()

    def test_updates_for_one_user_are_serialized_before_state_mutation(self) -> None:
        class SlowProbeApp(DentBotApp):
            def __init__(self, *args, **kwargs):
                super().__init__(*args, **kwargs)
                self.guard = threading.Lock()
                self.active = 0
                self.maximum_active = 0

            def _handle_serialized(self, update, *, interaction_version=None):
                del interaction_version
                with self.guard:
                    self.active += 1
                    self.maximum_active = max(self.maximum_active, self.active)
                time.sleep(0.04)
                with self.guard:
                    self.active -= 1

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            app = SlowProbeApp(FakeApi(), state, owner_id=10, site_url="https://example.test")
            try:
                threads = [threading.Thread(target=app.handle, args=(message(20, str(index)),)) for index in range(2)]
                for thread in threads:
                    thread.start()
                for thread in threads:
                    thread.join(timeout=2)
                self.assertEqual(app.maximum_active, 1)
            finally:
                state.close()

    def test_required_channel_check_failure_is_fail_closed(self) -> None:
        class FailingMembershipApi(FakeApi):
            def is_chat_member(self, _chat_id, _user_id):
                raise BotApiError("must not leak", transient=True)

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FailingMembershipApi()
            app = DentBotApp(
                api, state, owner_id=10, site_url="https://example.test", site_api=OnboardingSite(),
                platform="telegram", required_channel_username="Dent1402Booklets",
            )
            try:
                app.handle(message(20, "/start"))
                self.assertIn("بررسی عضویت فعلاً", api.sent[-1][1])
                self.assertNotIn("must not leak", api.sent[-1][1])
            finally:
                state.close()

    def test_bale_does_not_claim_it_can_verify_a_telegram_channel_identity(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            app = DentBotApp(
                api, state, owner_id=10, site_url="https://example.test", site_api=OnboardingSite(),
                platform="bale", required_channel_username="Dent1402Booklets",
            )
            try:
                app.handle(message(20, "/start"))
                self.assertIn("خوش آمدی به دنت‌یار", api.sent[-1][1])
                self.assertNotIn("عضویت در کانال الزامی", api.sent[-1][1])
            finally:
                state.close()

    def test_gateway_uses_normal_keyboard_and_class_path_is_below_generic_path(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=OnboardingSite())
            try:
                app.handle(message(20, "/start"))
                keyboard = api.sent[-1][2]
                self.assertNotIn("inline_keyboard", keyboard)
                self.assertEqual(keyboard["keyboard"][0][0]["text"], START_GENERIC)
                self.assertEqual(keyboard["keyboard"][1][0]["text"], START_CLASS)
            finally:
                state.close()

    def test_exact_field_order_contact_last_and_otp_is_never_persisted(self) -> None:
        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                site = OnboardingSite()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test", site_api=site, platform=platform
                )
                try:
                    app.handle(message(20, START_GENERIC))
                    self.assertEqual(state.dialog(20)["step"], "first-name")
                    for value, expected_step in [
                        ("آرین", "last-name"),
                        ("قاسم پور", "major"),
                        ("دندانپزشکی", "province"),
                        ("تهران", "institution"),
                        ("دانشگاه علوم پزشکی تهران", "entry-year"),
                        ("۱۴۰۲", "entry-term"),
                        ("نیمسال اول", "course-type"),
                        ("روزانه یا تعهدی", "student-number"),
                        (SKIP_STUDENT_NUMBER, "review"),
                        (CONFIRM_PROFILE, "contact"),
                    ]:
                        app.handle(message(20, value))
                        self.assertEqual(state.dialog(20)["step"], expected_step)
                        self.assertNotIn("inline_keyboard", api.sent[-1][2])
                    contact = {"phone_number": "+989009840305"}
                    if platform == "telegram":
                        contact["user_id"] = 20
                    app.handle(message(20, contact=contact))
                    self.assertEqual(state.dialog(20)["step"], "otp")
                    persisted = state.dialog(20)["payload"]
                    self.assertNotIn("phoneNumber", persisted)
                    self.assertNotIn("+989009840305", str(persisted))
                    self.assertIn(SHARE_CONTACT, str(api.sent[-2][2]))
                    self.assertIn("کاملاً محفوظ", api.sent[-2][1])
                    self.assertIn(BACK_STEP, str(api.sent[-2][2]))
                    app.handle(message(20, "۱۲۳۴۵۶"))
                    self.assertIsNone(state.dialog(20))
                    self.assertEqual(site.verified[-1][2], "123456")
                    self.assertEqual(api.removed, [20])
                    self.assertEqual(api.sent[-1][2]["inline_keyboard"][0][0]["text"], "🏠 ورود به دنت‌یار")
                    app.handle({"callback_query": {
                        "id": "enter-home", "from": {"id": 20}, "data": "v1:home",
                        "message": {"message_id": 7, "chat": {"id": 20, "type": "private"}},
                    }})
                    self.assertIn("دنت‌یار | ورودی", api.edited[-1][2])
                    with state._lock:
                        raw = state.connection.execute("SELECT payload_json FROM bot_dialogs").fetchall()
                    self.assertNotIn("123456", str(raw))
                finally:
                    state.close()

    def test_class_site_transition_removes_reply_keyboard_on_both_platforms(self) -> None:
        class ClassSite(OnboardingSite):
            def start_link(self, _user_id, *, platform_profile):
                self.platform_profile = dict(platform_profile)
                return {"success": True, "linkUrl": "https://example.test/link/token"}

        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=ClassSite(), platform=platform,
                )
                try:
                    app.handle(message(20, START_CLASS))
                    app.handle(message(20, "🌐 ورود با نام کاربری و رمز سایت"))
                    self.assertIsNone(state.dialog(20))
                    self.assertEqual(api.removed, [20])
                    self.assertIn("inline_keyboard", api.sent[-1][2])
                finally:
                    state.close()

    def test_telegram_rejects_a_foreign_contact(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            site = OnboardingSite()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=site)
            try:
                state.start_dialog(20, "onboarding-v1", "contact", {
                    "firstName": "آرین", "lastName": "قاسم پور", "major": "دندانپزشکی",
                    "province": "تهران", "institution": "دانشگاه علوم پزشکی تهران",
                    "entryYear": "۱۴۰۲", "entryTerm": "نیمسال اول", "courseType": "روزانه", "studentNumber": "",
                })
                app.handle(message(20, contact={"phone_number": "+989001112233", "user_id": 21}))
                self.assertEqual(site.requested, [])
                self.assertEqual(state.dialog(20)["step"], "contact")
                self.assertIn("همین حساب", api.sent[-1][1])
            finally:
                state.close()

    def test_back_button_preserves_data_and_returns_one_real_step_on_both_platforms(self) -> None:
        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=OnboardingSite(), platform=platform,
                )
                try:
                    app.handle(message(20, START_GENERIC))
                    app.handle(message(20, "آرین"))
                    app.handle(message(20, "قاسم پور"))
                    self.assertEqual(state.dialog(20)["step"], "major")
                    app.handle(message(20, BACK_STEP))
                    dialog = state.dialog(20)
                    self.assertEqual(dialog["step"], "last-name")
                    self.assertEqual(dialog["payload"]["firstName"], "آرین")
                    self.assertEqual(dialog["payload"]["lastName"], "قاسم پور")
                    self.assertIn(BACK_STEP, str(api.sent[-1][2]))
                    self.assertIn(CANCEL, str(api.sent[-1][2]))
                finally:
                    state.close()

    def test_azad_university_skips_public_course_type_and_uses_term_only(self) -> None:
        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                site = OnboardingSite()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=site, platform=platform,
                )
                try:
                    app.handle(message(20, START_GENERIC))
                    for value in (
                        "آرین", "قاسم پور", "دندانپزشکی", "تهران",
                        "دانشگاه علوم پزشکی آزاد اسلامی تهران", "۱۴۰۲", "نیمسال دوم",
                    ):
                        app.handle(message(20, value))
                    dialog = state.dialog(20)
                    self.assertEqual(dialog["step"], "student-number")
                    self.assertEqual(dialog["payload"]["institutionSystem"], "azad")
                    self.assertEqual(dialog["payload"]["admissionType"], "نیمسال دوم")
                    self.assertEqual(dialog["payload"]["courseType"], "")
                    app.handle(message(20, BACK_STEP))
                    self.assertEqual(state.dialog(20)["step"], "entry-term")
                    self.assertIn("دانشگاه آزاد", api.sent[-1][1])
                finally:
                    state.close()

    def test_entry_year_is_required_between_institution_and_entry_term(self) -> None:
        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=OnboardingSite(), platform=platform,
                )
                try:
                    state.start_dialog(20, "onboarding-v1", "entry-year", {
                        "firstName": "آرین", "lastName": "قاسم پور", "major": "دندانپزشکی",
                        "province": "تهران", "institution": "دانشگاه علوم پزشکی تهران",
                        "institutionSystem": "public",
                    })
                    app.handle(message(20, "۱۳۹۸"))
                    self.assertEqual(state.dialog(20)["step"], "entry-year")
                    buttons = [
                        button["text"] for row in api.sent[-1][2]["keyboard"] for button in row
                    ]
                    self.assertEqual(
                        [item for item in buttons if item in CATALOG["entryYears"]],
                        CATALOG["entryYears"],
                    )
                    app.handle(message(20, "۱۴۰۲"))
                    self.assertEqual(state.dialog(20)["step"], "entry-term")
                    self.assertEqual(state.dialog(20)["payload"]["entryYear"], "۱۴۰۲")
                    app.handle(message(20, BACK_STEP))
                    self.assertEqual(state.dialog(20)["step"], "entry-year")
                finally:
                    state.close()

    def test_class_cancel_never_unlocks_home_or_private_sections(self) -> None:
        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                app = DentBotApp(
                    api,
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=OnboardingSite(),
                    platform=platform,
                )
                try:
                    app.handle(message(20, START_CLASS))
                    self.assertEqual(state.dialog(20)["kind"], "class-auth-v1")
                    app.handle(message(20, CANCEL))
                    self.assertIsNone(state.dialog(20))
                    app.handle(message(20, "/menu"))
                    self.assertIn("خوش آمدی به دنت‌یار", api.sent[-1][1])
                    self.assertNotIn("دنت‌یار | ورودی", api.sent[-1][1])
                    app.handle(message(20, "برگشت به منوی اصلی"))
                    self.assertIn("خوش آمدی به دنت‌یار", api.sent[-1][1])
                    self.assertNotIn("دنت‌یار | ورودی", api.sent[-1][1])
                    app.handle({"callback_query": {
                        "id": "cancel-home-regression",
                        "from": {"id": 20},
                        "data": "v1:home",
                        "message": {"message_id": 7, "chat": {"id": 20, "type": "private"}},
                    }})
                    self.assertIn("خوش آمدی به دنت‌یار", api.edited[-1][2])
                    self.assertNotIn("دنت‌یار | ورودی", api.edited[-1][2])
                finally:
                    state.close()

    def test_missing_site_api_fails_closed_after_class_cancel(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test")
            try:
                app.handle(message(20, START_CLASS))
                app.handle(message(20, CANCEL))
                app.handle(message(20, "/menu"))
                self.assertIn("هیچ بخشی باز نشد", api.sent[-1][1])
                self.assertNotIn("دنت‌یار | ورودی", api.sent[-1][1])
            finally:
                state.close()

    def test_owner_also_needs_a_canonical_site_link_for_private_sections(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(
                    FakeApi(), state, owner_id=10, site_url="https://example.test",
                    site_api=OnboardingSite(),
                )
                screen = app._dynamic_screen("admin", 10)
                self.assertIn("خوش آمدی به دنت‌یار", screen.text)
                self.assertNotIn("مدیریت دنت‌یار", screen.text)
            finally:
                state.close()

    def test_every_message_and_callback_is_fail_closed_before_intake_on_both_platforms(self) -> None:
        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                site = OnboardingSite()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=site, platform=platform,
                )
                try:
                    for text in ("/menu", "/help", "/verify", "/product", "پیام دلخواه"):
                        app.handle(message(20, text))
                        self.assertIn("خوش آمدی به دنت‌یار", api.sent[-1][1])
                        self.assertNotIn("دنت‌یار | ورودی", api.sent[-1][1])
                    self.assertIsNone(state.dialog(20))
                    app.handle({"callback_query": {
                        "id": "forged-admin", "from": {"id": 20}, "data": "v1:grades",
                        "message": {"message_id": 7, "chat": {"id": 20, "type": "private"}},
                    }})
                    self.assertIn("خوش آمدی به دنت‌یار", api.edited[-1][2])
                finally:
                    state.close()

    def test_commands_and_callbacks_cannot_abandon_a_partial_intake(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=OnboardingSite())
            try:
                app.handle(message(20, START_GENERIC))
                app.handle(message(20, "آرین"))
                before = state.dialog(20)
                app.handle(message(20, "/menu"))
                self.assertEqual(state.dialog(20), before)
                self.assertIn("نام خانوادگی", api.sent[-1][1])
                app.handle({"callback_query": {
                    "id": "partial-home", "from": {"id": 20}, "data": "v1:home",
                    "message": {"message_id": 8, "chat": {"id": 20, "type": "private"}},
                }})
                self.assertEqual(state.dialog(20), before)
                self.assertIn("نام خانوادگی", api.edited[-1][2])
            finally:
                state.close()

    def test_verified_generic_profile_opens_only_general_menu(self) -> None:
        class GenericSite(OnboardingSite):
            def __init__(self) -> None:
                super().__init__()
                self.grade_calls = 0
                self.profile = {
                    "firstName": "دانشجوی", "lastName": "عمومی", "major": "پزشکی",
                    "institution": "دانشگاه علوم پزشکی تهران", "admissionType": "نیمسال اول",
                    "verifiedAt": "2026-08-27T20:00:00+03:30", "isClassMember": False,
                }

            def grades(self, _user_id):
                self.grade_calls += 1
                raise AssertionError("private endpoint must not be called without a canonical link")

        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                site = GenericSite()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=site, platform=platform,
                )
                try:
                    app.handle(message(20, "/menu"))
                    self.assertIn("دنت‌یار | ورودی", api.sent[-1][1])
                    app.handle({"callback_query": {
                        "id": "generic-grades", "from": {"id": 20}, "data": "v1:grades",
                        "message": {"message_id": 9, "chat": {"id": 20, "type": "private"}},
                    }})
                    self.assertIn("مشخصات تأییدشده", api.edited[-1][2])
                    self.assertEqual(site.grade_calls, 0)
                finally:
                    state.close()

    def test_disconnected_class_and_owner_private_flows_remain_locked(self) -> None:
        class DisconnectedClassSite(OnboardingSite):
            def __init__(self) -> None:
                super().__init__()
                self.profile = {
                    "firstName": "دانشجوی", "lastName": "کلاس", "verifiedAt": "2026-08-27",
                    "isClassMember": True,
                }

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=DisconnectedClassSite())
            try:
                app.handle(message(20, "/menu"))
                self.assertIn("اتصال حساب کلاس کامل نیست", api.sent[-1][1])
                self.assertEqual(CLASS_OTP, api.sent[-1][2]["keyboard"][0][0]["text"])
                self.assertEqual(state.dialog(20)["kind"], "class-auth-v1")
                app.handle(message(20, CLASS_OTP))
                self.assertEqual(state.dialog(20)["step"], "student-number")
                app.handle(message(20, BACK_STEP))
                app.handle(message(20, START_GENERIC))
                self.assertEqual(state.dialog(20)["kind"], "class-auth-v1")
                self.assertIn("یکی از دو روش احراز هویت", api.sent[-1][1])
                app.handle(message(10, "/product"))
                self.assertIn("اتصال حساب کلاس کامل نیست", api.sent[-1][1])
                self.assertEqual(state.dialog(10)["kind"], "class-auth-v1")
                self.assertEqual(state.payment_offers(), [])
            finally:
                state.close()

    def test_legacy_link_is_not_authentication_and_class_entry_never_opens_home(self) -> None:
        class LegacyLinkedClassSite(OnboardingSite):
            def account(self, _user_id):
                return {
                    "success": True,
                    "linked": True,
                    "authComplete": False,
                    "user": {"name": "دانشجوی قدیمی", "studentNumber": "402000001"},
                    "identity": {"recognized": True, "claimStatus": "approved"},
                    "onboardingProfile": {
                        "firstName": "دانشجوی", "lastName": "قدیمی",
                        "verifiedAt": "2026-08-27", "isClassMember": True,
                    },
                }

        for platform in ("telegram", "bale"):
            with self.subTest(platform=platform), tempfile.TemporaryDirectory() as directory:
                state = BotState(Path(directory) / "state.sqlite3")
                api = FakeApi()
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=LegacyLinkedClassSite(), platform=platform,
                )
                try:
                    app.handle(message(20, START_CLASS))
                    self.assertEqual(state.dialog(20)["kind"], "class-auth-v1")
                    self.assertIn("احراز هویت ورودی ۱۴۰۲", api.sent[-1][1])
                    self.assertNotIn("دنت‌یار | ورودی", api.sent[-1][1])
                    app.handle(message(20, CANCEL))
                    app.handle(message(20, "/menu"))
                    self.assertEqual(state.dialog(20)["kind"], "class-auth-v1")
                    self.assertIn("اتصال حساب کلاس کامل نیست", api.sent[-1][1])
                    self.assertNotIn("دنت‌یار | ورودی", api.sent[-1][1])
                finally:
                    state.close()

    def test_private_dialog_cannot_continue_after_canonical_disconnect(self) -> None:
        class GenericSite(OnboardingSite):
            def __init__(self) -> None:
                super().__init__()
                self.profile = {
                    "firstName": "مالک", "lastName": "آزمایشی", "verifiedAt": "2026-08-27",
                    "isClassMember": False,
                }

        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            api = FakeApi()
            state.start_dialog(10, "payment-offer", "title", {})
            app = DentBotApp(api, state, owner_id=10, site_url="https://example.test", site_api=GenericSite())
            try:
                app.handle(message(10, "محصول نباید ساخته شود"))
                self.assertEqual(state.dialog(10)["step"], "title")
                self.assertEqual(state.payment_offers(), [])
                self.assertIn("مشخصات تأییدشده", api.sent[-1][1])
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
