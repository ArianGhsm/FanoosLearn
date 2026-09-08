import tempfile, unittest
from pathlib import Path
from fanoos_bot.api import FanoosApiError
from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.state import LocalState


class FakeBackend:
    JOB = '55555555-5555-4555-8555-555555555555'
    RESOURCE = '66666666-6666-4666-8666-666666666666'

    def __init__(self):
        self.selected = None
        self.orders = []
        self.receipts = []
        self.deploy = []
        self.consume_count = 0
        self.object_mode = False
        self.media_ready = False
        self.unlinked = []

    def consume_link(self, p, s, t):
        if t == 'expired':
            raise FanoosApiError('link_challenge_expired', 'provider detail must not leak', 410)
        return {'user_id': 'u'}

    def unlink(self, p, s):
        self.unlinked.append((p, s))
        return {'revoked': True}

    def workspaces(self, p, s):
        if s == 'unlinked':
            raise FanoosApiError('messaging_link_required', 'link', 403)
        return {
            'workspaces': [
                {'id': '11111111-1111-4111-8111-111111111111', 'name': 'دانشکده دندان‌پزشکی'},
                {'id': '22222222-2222-4222-8222-222222222222', 'name': 'پردیس علوم پزشکی'},
            ],
            'selected_workspace_id': self.selected,
        }

    def select_workspace(self, p, s, w):
        if w.startswith('9'):
            raise FanoosApiError('workspace_forbidden', 'no', 403)
        self.selected = w
        return {'selected_workspace_id': w}

    def schedule(self, p, s, w, f, t, limit, cursor):
        return {
            'timezone': 'Asia/Tehran',
            'items': [
                {
                    'id': 'e',
                    'title': 'کلاس ترمیمی',
                    'starts_at': f + 'T08:00:00+03:30',
                    'location_text': 'دانشکده',
                }
            ],
        }

    def grades(self, *a):
        return {'items': [{'result_id': 'g', 'item_title': 'کوییز', 'score': '18', 'max_score': '20', 'status': 'published'}]}

    def announcements(self, *a):
        return {
            'items': [
                {
                    'id': 'n',
                    'title': 'اطلاعیه',
                    'body': 'متن اطلاعیه',
                    'published_at': '2026-09-08T10:30:00+03:30',
                }
            ]
        }

    def resources(self, *a):
        return {
            'items': [
                {
                    'resource_id': self.RESOURCE,
                    'title': 'جزوه پریو',
                    'type': 'booklet',
                    'delivery_supported': True,
                    'resource_version_id': 'v',
                }
            ]
        }

    def create_order(self, p, s, w, prod, idem):
        self.orders.append(idem)
        return {
            'order_id': 'o',
            'title': 'دنت‌نوت',
            'amount_minor': 100,
            'currency': 'IRR',
            'status': 'pending',
            'payment_url': 'https://pay.test/x',
        }

    def order_status(self, *a):
        return {'status': 'paid', 'entitlement': {'granted': True}}

    def delivery_issue(self, p, s, w, r):
        return {'issuance_id': '33333333-3333-4333-8333-333333333333', 'delivery_token': 'tok'}

    def delivery_consume(self, p, s, w, t):
        self.consume_count += 1
        return {
            'issuance_id': '33333333-3333-4333-8333-333333333333',
            'content': None if self.object_mode else {'ok': 1},
            'forward_protection_required': True,
            'object_id': 'obj' if self.object_mode else None,
        }

    def delivery_receipt(self, *args):
        self.receipts.append(args)
        return {}

    def media_enqueue(self, *a):
        return {'job_id': self.JOB}

    def media_derivative_issue(self, p, s, w, j):
        if not self.media_ready:
            raise FanoosApiError('protected_media_artifact_unavailable', 'not ready', 404)
        return {'job_id': j, 'artifact_capability': 'artifact-cap', 'size': 13, 'mime': 'application/pdf'}

    def media_derivative_redeem(self, *a):
        return b'%PDF-derived'

    def deployment_overview(self, subject, target):
        if subject == 'normal':
            return {'can_manage_deployments': False}
        return {
            'can_manage_deployments': True,
            'current_release_sha': 'a' * 40,
            'candidate_sha': 'b' * 40,
            'update_available': True,
            'health': {'status': 'healthy'},
        }

    def request_deployment(self, subject, target, idem):
        self.deploy.append((subject, target, idem))
        return {'request_id': '44444444-4444-4444-8444-444444444444', 'state': 'REQUESTED'}

    def deployment_status(self, s, r):
        return {'request_id': r, 'state': 'HEALTHCHECK', 'candidate_sha': 'a' * 40}


class AppTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.state = LocalState(Path(self.tmp.name) / 's.db')
        self.backend = FakeBackend()
        self.app = BotApplication(
            self.backend,
            self.state,
            'telegram',
            ApplicationConfig('https://fanoos.test/', 'prod'),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    def select(self):
        self.backend.selected = '11111111-1111-4111-8111-111111111111'

    def test_start_bad_payload(self):
        result = self.app.start('1', 'bad!')
        self.assertIn('نامعتبر', result.screen.text)
        self.assertEqual(result.screen.presentation.semantic_kind, 'link_error')

    def test_link_and_unlink(self):
        linked = self.app.link('1', 'abc_DEF-1')
        self.assertIn('✅ اتصال انجام شد', linked.screen.text)
        self.assertIn('تلگرام', linked.screen.text)
        self.assertNotIn('telegram', linked.screen.text)
        unlinked = self.app.unlink('1')
        self.assertIn('حساب اصلی', unlinked.screen.text)
        self.assertEqual(self.backend.unlinked, [('telegram', '1')])

    def test_link_expired_is_actionable_and_hides_provider_detail(self):
        result = self.app.link('1', 'expired')
        self.assertIn('منقضی', result.screen.text)
        self.assertIn('کد اتصال تازه', result.screen.text)
        self.assertNotIn('provider detail', result.screen.text)
        self.assertNotIn('link_challenge_expired', result.screen.text)

    def test_workspace_select_callback_unchanged_and_no_uuid_in_copy(self):
        result = self.app.workspaces('1')
        callback = result.screen.rows[0][0].callback
        self.assertEqual(callback, 'ws:11111111-1111-4111-8111-111111111111')
        self.assertNotIn('11111111-1111-4111-8111-111111111111', result.screen.text)
        self.app.select_workspace('1', '11111111-1111-4111-8111-111111111111')
        self.assertTrue(self.backend.selected.startswith('1'))

    def test_cross_tenant_denied(self):
        result = self.app.select_workspace('1', '99999999-9999-4999-8999-999999999999')
        self.assertIn('در دسترس نیست', result.screen.text)
        self.assertNotIn('workspace_forbidden', result.screen.text)

    def test_home_and_account_are_human_labeled(self):
        self.select()
        home = self.app.home('1')
        self.assertIn('🏠 فانوس', home.screen.text)
        self.assertIn('دانشکده دندان‌پزشکی', home.screen.text)
        self.assertNotIn(self.backend.selected, home.screen.text)
        labels = [button.text for row in home.screen.rows for button in row]
        self.assertIn('📅 امروز', labels)
        self.assertIn('🏫 فضای آموزشی', labels)
        account = self.app.account('1')
        self.assertIn('پیام‌رسان: تلگرام', account.screen.text)
        self.assertIn('تعداد فضای آموزشی: ۲', account.screen.text)
        self.assertNotIn('telegram', account.screen.text)

    def test_native_reads_use_persian_fallbacks(self):
        self.select()
        schedule = self.app.day_schedule('1', 0)
        self.assertIn('📅 برنامه امروز', schedule.screen.text)
        self.assertIn('۰۸:۰۰', schedule.screen.text)
        self.assertNotIn('T08:00', schedule.screen.text)
        grades = self.app.grades('1')
        self.assertIn('🎓 نمرات من', grades.screen.text)
        self.assertIn('۱۸ از ۲۰', grades.screen.text)
        self.assertNotIn('published', grades.screen.text)
        announcements = self.app.announcements('1')
        self.assertIn('📢 اطلاعیه‌ها', announcements.screen.text)
        self.assertIn('۲۰۲۶/۰۹/۰۸', announcements.screen.text)
        resources = self.app.resources('1')
        self.assertIn('📚 منابع', resources.screen.text)
        self.assertIn('جزوه پریو', resources.screen.text)
        self.assertTrue(resources.screen.rows)
        self.assertTrue(resources.screen.rows[0][0].callback.startswith('resource:'))

    def test_payment_idempotency_and_localized_status(self):
        self.select()
        first = self.app.create_order('1', 'p', 'bot-order:telegram:5')
        self.app.create_order('1', 'p', 'bot-order:telegram:5')
        self.assertEqual(self.backend.orders, ['bot-order:telegram:5'] * 2)
        self.assertIn('۱۰۰ ریال', first.screen.text)
        self.assertIn('در انتظار پرداخت', first.screen.text)
        self.assertNotIn('pending', first.screen.text)
        self.assertNotIn('IRR', first.screen.text)
        paid = self.app.order_status('1', 'o')
        self.assertIn('پرداخت تأیید شده', paid.screen.text)
        self.assertIn('دسترسی: فعال', paid.screen.text)
        self.assertNotIn('paid', paid.screen.text)

    def test_protected_structured_telegram_reauthorizes(self):
        self.select()
        result = self.app.protected_resource('1', 'r')
        self.assertTrue(result.screen.protect_content)
        self.assertEqual(self.backend.consume_count, 1)
        self.assertIsNotNone(result.receipt)
        self.assertIsNone(result.document)
        self.assertNotIn("{'ok': 1}", result.screen.text)

    def test_protected_pdf_enqueue_then_derivative_delivery(self):
        self.select()
        self.backend.object_mode = True
        result = self.app.protected_resource('1', self.backend.RESOURCE)
        self.assertIn('در حال آماده‌سازی', result.screen.text)
        self.assertIsNotNone(self.app.media_state.get(self.backend.JOB, '1'))
        pending = self.app.protected_media_ready('1', self.backend.JOB)
        self.assertIn('هنوز آماده', pending.screen.text)
        self.backend.media_ready = True
        ready = self.app.protected_media_ready('1', self.backend.JOB)
        self.assertIsNotNone(ready.document)
        self.assertEqual(ready.document.data, b'%PDF-derived')
        self.assertIsNotNone(ready.receipt)
        self.assertEqual(ready.metadata['media_job_id'], self.backend.JOB)

    def test_media_job_is_subject_bound_locally(self):
        self.select()
        self.backend.object_mode = True
        self.app.protected_resource('1', self.backend.RESOURCE)
        result = self.app.protected_media_ready('2', self.backend.JOB)
        self.assertIn('این حساب', result.screen.text)

    def test_bale_fail_closed_and_receipt(self):
        app = BotApplication(self.backend, self.state, 'bale')
        self.select()
        result = app.protected_resource('1', 'r')
        self.assertIn('بله', result.screen.text)
        self.assertIn('ارسال انجام نشد', result.screen.text)
        self.assertTrue(any(row[-1] == 'unsupported_forward_protection' for row in self.backend.receipts))

    def test_update_overview_permission_gate_and_confirmation(self):
        denied = self.app.update_begin('normal', True)
        self.assertIn('اجازه', denied.screen.text)
        self.assertFalse(denied.screen.rows)
        result = self.app.update_begin('owner', True)
        self.assertIn('نسخه جدید موجود است', result.screen.text)
        self.assertIn('وضعیت فعلی: سالم', result.screen.text)
        self.assertNotIn('healthy', result.screen.text)
        self.assertNotIn('Update Server', result.screen.text)
        ref = result.screen.rows[0][0].callback.split(':', 1)[1]
        confirmed = self.app.update_confirm('owner', True, ref)
        self.assertIn('درخواست ثبت شده', confirmed.screen.text)
        self.assertNotIn('REQUESTED', confirmed.screen.text)
        self.assertEqual(self.backend.deploy[0][0], 'owner')

    def test_cancel_invalidates(self):
        result = self.app.update_begin('owner', True)
        ref = result.screen.rows[0][0].callback.split(':', 1)[1]
        self.app.update_cancel('owner', ref)
        self.assertIn('منقضی', self.app.update_confirm('owner', True, ref).screen.text)

    def test_update_not_advertised_and_bale_disabled(self):
        self.assertNotIn('update_server', self.app.help().screen.text)
        app = BotApplication(self.backend, self.state, 'bale', ApplicationConfig(deployment_target_key='prod'))
        result = app.update_begin('owner', True)
        self.assertIn('فعال نیست', result.screen.text)

    def test_restart_status_recovery_is_localized(self):
        self.state.record_deployment('owner', '44444444-4444-4444-8444-444444444444')
        result = self.app.update_status('owner', True)
        self.assertIn('بررسی سلامت', result.screen.text)
        self.assertNotIn('HEALTHCHECK', result.screen.text)
        self.assertNotIn('44444444-4444-4444-8444-444444444444', result.screen.text)

    def test_core_screens_have_semantic_metadata(self):
        self.select()
        for result in (self.app.home('1'), self.app.account('1'), self.app.day_schedule('1', 0), self.app.grades('1'), self.app.announcements('1'), self.app.resources('1')):
            self.assertIsNotNone(result.screen.presentation)
            self.assertTrue(result.screen.presentation.title)
            self.assertTrue(result.screen.presentation.rtl)


if __name__ == '__main__':
    unittest.main()
