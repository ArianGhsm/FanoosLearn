from __future__ import annotations

import unittest

from dent_bot.classops_surface import (
    BaleAdapter,
    Capability,
    OWNER,
    STUDENT,
    SurfaceContractError,
    TelegramAdapter,
    build_intent,
    build_item_actions,
    build_menu,
    confirm_intent,
    foundation_capabilities,
    intent_from_callback,
    render_ai_draft_request,
    render_critical_ack,
    render_stale_revision,
    render_summary,
)


class ClassOpsSurfaceTests(unittest.TestCase):
    def setUp(self) -> None:
        self.caps = foundation_capabilities()

    def test_owner_student_menu_separation(self) -> None:
        owner = build_menu(OWNER, self.caps)
        student = build_menu(STUDENT, self.caps)
        owner_actions = {button.action for button in owner.buttons}
        student_actions = {button.action for button in student.buttons}
        self.assertIn("draft.validate", owner_actions)
        self.assertIn("ai.draft_create", owner_actions)
        self.assertNotIn("draft.validate", student_actions)
        self.assertNotIn("items.list", student_actions)
        self.assertIn("student.items", student_actions)
        self.assertTrue(next(button for button in student.buttons if button.action == "student.items").enabled)
        self.assertFalse(next(button for button in owner.buttons if button.action == "ai.draft_create").enabled)

    def test_telegram_bale_semantics_are_identical(self) -> None:
        view = build_item_actions(OWNER, "draft", self.caps)
        telegram = TelegramAdapter().render(view)
        bale = BaleAdapter().render(view)
        self.assertEqual(telegram.text, bale.text)
        self.assertEqual(telegram.semantic_actions, bale.semantic_actions)
        self.assertEqual(
            [[button["text"] for button in row] for row in telegram.keyboard["inline_keyboard"]],
            [[button["text"] for button in row] for row in bale.keyboard["inline_keyboard"]],
        )
        self.assertTrue(any("style" in row[0] for row in telegram.keyboard["inline_keyboard"]))
        self.assertFalse(any("style" in row[0] for row in bale.keyboard["inline_keyboard"]))

    def test_mutation_requires_confirmation_revision_and_idempotency(self) -> None:
        intent = build_intent(
            "item.archive", OWNER, item_id="item_abc", expected_revision=7,
            nonce="retry-stable-nonce-001",
        )
        self.assertTrue(intent.confirmation_required)
        self.assertFalse(intent.confirmed)
        self.assertEqual(intent.expected_revision, 7)
        self.assertRegex(intent.idempotency_key or "", r"^surface_[a-f0-9]{32}$")
        confirmed = confirm_intent(intent)
        self.assertTrue(confirmed.confirmed)
        self.assertEqual(confirmed.idempotency_key, intent.idempotency_key)

    def test_stale_revision_has_explicit_conflict_state(self) -> None:
        view = render_stale_revision()
        self.assertEqual(view.state, "conflict")
        self.assertIn("دوباره پیش‌نمایش", view.text)
        self.assertIn("تغییر کرده", view.text)

    def test_callback_only_produces_typed_unconfirmed_intent(self) -> None:
        intent = intent_from_callback(
            "classops:item.cancel", actor_role=OWNER, item_id="item_abc",
            expected_revision=3, payload={}, nonce="callback-nonce-001",
        )
        self.assertEqual(intent.action, "item.cancel")
        self.assertFalse(intent.confirmed)
        self.assertTrue(intent.confirmation_required)

    def test_ai_free_text_is_preview_only(self) -> None:
        view = render_ai_draft_request("فردا کلاس ترمیمی ساعت ۸ جابه‌جا شد")
        self.assertEqual(view.state, "preview")
        self.assertIn("هیچ mutation یا ارسال مستقیمی انجام نشده", view.text)
        intent = build_intent("ai.draft_create", OWNER, payload={"text": "نمونه"})
        self.assertIsNone(intent.idempotency_key)
        self.assertFalse(intent.confirmation_required)

    def test_critical_ack_is_student_only_and_enabled_after_stage2_promotion(self) -> None:
        view = render_critical_ack(STUDENT, "اطلاعیه امتحان", 4, self.caps)
        self.assertEqual(view.state, "ready")
        self.assertTrue(view.buttons[0].enabled)
        with self.assertRaises(ValueError):
            render_critical_ack(OWNER, "اطلاعیه امتحان", 4, self.caps)
        with self.assertRaises(SurfaceContractError):
            build_intent("student.critical_ack", OWNER, item_id="x", expected_revision=1, nonce="nonce-0001")

    def test_tomorrow_weekly_render_parity_and_capability_gate(self) -> None:
        disabled_caps = dict(self.caps)
        disabled_caps["summary.tomorrow"] = Capability(False, "fixture-disabled")
        disabled = render_summary("tomorrow", STUDENT, [], disabled_caps)
        self.assertEqual(disabled.state, "disabled")
        self.assertFalse(disabled.buttons[0].enabled)

        rows = [{"title": "کلاس پریو", "when": "08:00"}]
        for kind in ("tomorrow", "weekly"):
            view = render_summary(kind, STUDENT, rows, self.caps)
            tg = TelegramAdapter().render(view)
            bale = BaleAdapter().render(view)
            self.assertEqual(view.state, "ready")
            self.assertEqual(tg.text, bale.text)
            self.assertIn("کلاس پریو", tg.text)

    def test_no_raw_ids_or_secrets_in_payload(self) -> None:
        forbidden_payloads = [
            {"chat_id": 123}, {"telegramId": 123}, {"botToken": "x"},
            {"secret": "x"}, {"phone": "0912"}, {"nationalCode": "001"},
            {"nested": {"otp": "123456"}},
        ]
        for payload in forbidden_payloads:
            with self.subTest(payload=payload), self.assertRaises(SurfaceContractError):
                build_intent("ai.draft_create", OWNER, payload=payload)

    def test_disabled_capability_never_becomes_semantic_action(self) -> None:
        view = build_menu(OWNER, self.caps)
        rendered = TelegramAdapter().render(view)
        self.assertNotIn("ai.draft_create", rendered.semantic_actions)
        self.assertIn("items.list", rendered.semantic_actions)
        for button, row in zip(view.buttons, rendered.keyboard["inline_keyboard"], strict=True):
            callback = row[0]["callback_data"]
            if button.enabled:
                self.assertNotEqual(callback, "classops:disabled")
            else:
                self.assertEqual(callback, "classops:disabled")

    def test_persian_copy_is_deterministic(self) -> None:
        one = build_menu(OWNER, self.caps)
        two = build_menu(OWNER, self.caps)
        self.assertEqual(one, two)
        self.assertIn("مرکز عملیات کلاس", TelegramAdapter().render(one).text)


if __name__ == "__main__":
    unittest.main()
