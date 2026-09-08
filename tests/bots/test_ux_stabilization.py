import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.bale_presentation import BalePresentation
from fanoos_bot.botapi import BotApiError, JsonBotApiTransport
from fanoos_bot.capabilities import TELEGRAM
from fanoos_bot.localization import entitlement_label
from fanoos_bot.models import Screen
from fanoos_bot.runtime import NotificationPump
from fanoos_bot.state import LocalState
from fanoos_bot.telegram_presentation import TelegramPresentation

from test_application import FakeBackend


class InlineProtectedBackend(FakeBackend):
    def __init__(self, *, forward=False):
        super().__init__()
        self.forward = forward

    def delivery_consume(self, p, s, w, t):
        self.consume_count += 1
        return {
            'issuance_id': '33333333-3333-4333-8333-333333333333',
            'content': 'متن مجاز و اختصاصی این دریافت',
            'forward_protection_required': self.forward,
            'object_id': None,
        }


class RecordingTelegram(JsonBotApiTransport):
    def __init__(self, *, fail_plain=False):
        super().__init__(
            'https://example.invalid',
            'token',
            TELEGRAM,
            rich_ui_enabled=True,
            activity_ui_enabled=False,
        )
        self.calls = []
        self.fail_plain = fail_plain

    def _call(self, method, payload=None):
        self.calls.append((method, payload or {}))
        if self.fail_plain and method == 'sendMessage':
            raise BotApiError('network_unavailable', transient=True)
        return {'message_id': 77}


class NotificationBackend:
    def __init__(self):
        self.receipt_calls = 0

    def claim_notification(self, platform):
        return {
            'delivery': {
                'delivery_id': 'notification-1',
                'lease_token': 'lease-1',
                'subject': '42',
                'payload': {'title': 'اطلاعیه مهم', 'body': 'متن اعلان'},
            }
        }

    def notification_receipt(self, *args):
        self.receipt_calls += 1
        if self.receipt_calls == 1:
            raise RuntimeError('receipt backend temporarily unavailable')
        return {'recorded': True}


class NotificationTransport:
    def __init__(self):
        self.sent = 0
        self.capabilities = TELEGRAM

    def send_screen(self, chat_id, screen):
        self.sent += 1
        return {'message_id': 900 + self.sent}


class StabilizedBotUxTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.state = LocalState(Path(self.tmp.name) / 'state.db')
        self.backend = FakeBackend()
        self.backend.selected = '11111111-1111-4111-8111-111111111111'
        self.app = BotApplication(
            self.backend,
            self.state,
            'telegram',
            ApplicationConfig('https://fanoos.test/', 'prod'),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    def test_real_application_screen_matrix_uses_semantics_on_both_channels(self):
        screens = [
            self.app.home('owner').screen,
            self.app.workspaces('owner').screen,
            self.app.grades('owner').screen,
            self.app.announcements('owner').screen,
            self.app.resources('owner').screen,
            self.app.create_order('owner', 'product-1', 'stable-order-1').screen,
            self.app.update_begin('owner', True).screen,
            self.app.select_workspace('owner', '99999999-9999-4999-8999-999999999999').screen,
        ]
        for screen in screens:
            with self.subTest(kind=screen.presentation.semantic_kind):
                self.assertIsNotNone(screen.presentation)
                callbacks = [button.callback for row in screen.rows for button in row if button.callback]
                telegram = TelegramPresentation(enabled=True).render(screen)
                bale = BalePresentation().render(screen)
                self.assertIsNotNone(telegram.rich_message)
                self.assertTrue(telegram.rich_message['is_rtl'])
                self.assertIn(screen.presentation.title, telegram.rich_message['html'])
                self.assertIn(screen.presentation.title, bale.text)
                self.assertEqual(
                    callbacks,
                    [button.callback for row in screen.rows for button in row if button.callback],
                )
                plain = TelegramPresentation(enabled=False).render(screen)
                self.assertEqual(plain.plain_text, screen.text)
                self.assertIn(screen.presentation.title, screen.text)

    def test_inline_protected_content_is_never_replaced_by_ready_summary(self):
        telegram_backend = InlineProtectedBackend(forward=True)
        telegram_backend.selected = self.backend.selected
        telegram_app = BotApplication(
            telegram_backend,
            self.state,
            'telegram',
            ApplicationConfig('https://fanoos.test/', 'prod'),
        )
        screen = telegram_app.protected_resource('owner', telegram_backend.RESOURCE).screen
        self.assertTrue(screen.protect_content)
        self.assertEqual(screen.presentation.semantic_kind, 'protected_delivery_ready')
        rendered = TelegramPresentation(enabled=True).render(screen)
        self.assertIsNone(rendered.rich_message)
        self.assertEqual(rendered.plain_text, 'متن مجاز و اختصاصی این دریافت')

        bale_backend = InlineProtectedBackend(forward=False)
        bale_backend.selected = self.backend.selected
        bale_app = BotApplication(
            bale_backend,
            self.state,
            'bale',
            ApplicationConfig('https://fanoos.test/', 'prod'),
        )
        bale_screen = bale_app.protected_resource('owner', bale_backend.RESOURCE).screen
        self.assertEqual(
            BalePresentation().render(bale_screen).text,
            'متن مجاز و اختصاصی این دریافت',
        )

    def test_real_protected_telegram_delivery_is_one_provider_operation(self):
        backend = InlineProtectedBackend(forward=True)
        backend.selected = self.backend.selected
        app = BotApplication(backend, self.state, 'telegram')
        screen = app.protected_resource('owner', backend.RESOURCE).screen
        transport = RecordingTelegram()
        transport.send_screen('42', screen)
        self.assertEqual([method for method, _ in transport.calls], ['sendMessage'])
        self.assertTrue(transport.calls[0][1]['protect_content'])
        self.assertEqual(transport.calls[0][1]['text'], 'متن مجاز و اختصاصی این دریافت')

        failing = RecordingTelegram(fail_plain=True)
        with self.assertRaises(BotApiError):
            failing.send_screen('42', screen)
        self.assertEqual([method for method, _ in failing.calls], ['sendMessage'])

    def test_invalid_metadata_does_not_trigger_heuristic_reinterpretation(self):
        class InvalidMetadataScreen:
            text = 'عنوان حدسی\n\n• مورد'
            rows = ()
            edit = False
            protect_content = False
            presentation = {'unexpected': 'metadata'}

        rendered = TelegramPresentation(enabled=True).render(InvalidMetadataScreen())
        self.assertIsNone(rendered.rich_message)
        self.assertEqual(rendered.plain_text, InvalidMetadataScreen.text)

    def test_entitlement_unknown_is_not_invented_as_denied(self):
        self.assertEqual(entitlement_label(True), 'فعال')
        self.assertEqual(entitlement_label(False), 'فعال نیست')
        self.assertEqual(entitlement_label(None), 'نامشخص')
        self.assertEqual(entitlement_label(''), 'نامشخص')

    def test_notification_receipt_failure_does_not_resend_on_retry(self):
        backend = NotificationBackend()
        transport = NotificationTransport()
        pump = NotificationPump('telegram', backend, transport, self.state)
        with self.assertRaises(RuntimeError):
            pump.run_once()
        self.assertEqual(transport.sent, 1)
        self.assertEqual(self.state.sent_delivery('notification-1'), '901')
        self.assertTrue(pump.run_once())
        self.assertEqual(transport.sent, 1)
        self.assertEqual(backend.receipt_calls, 2)


if __name__ == '__main__':
    unittest.main()
