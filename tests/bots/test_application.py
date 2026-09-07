import tempfile,unittest
from pathlib import Path
from fanoos_bot.api import FanoosApiError
from fanoos_bot.application import ApplicationConfig,BotApplication
from fanoos_bot.state import LocalState
class FakeBackend:
    def __init__(self):self.selected=None;self.orders=[];self.receipts=[];self.deploy=[];self.consume_count=0
    def consume_link(self,p,s,t):return {'user_id':'u'}
    def workspaces(self,p,s):
        if s=='unlinked':raise FanoosApiError('messaging_link_required','link',403)
        return {'workspaces':[{'id':'11111111-1111-4111-8111-111111111111','name':'A'},{'id':'22222222-2222-4222-8222-222222222222','name':'B'}],'selected_workspace_id':self.selected}
    def select_workspace(self,p,s,w):
        if w.startswith('9'):raise FanoosApiError('workspace_forbidden','no',403)
        self.selected=w;return {'selected_workspace_id':w}
    def create_order(self,p,s,w,prod,idem):self.orders.append(idem);return {'order_id':'o','title':'P','amount_minor':100,'currency':'IRR','status':'pending','payment_url':'https://pay.test/x'}
    def order_status(self,*a):return {'status':'paid','entitlement':{'granted':True}}
    def delivery_issue(self,p,s,w,r):return {'issuance_id':'33333333-3333-4333-8333-333333333333','delivery_token':'tok'}
    def delivery_consume(self,p,s,w,t):self.consume_count+=1;return {'issuance_id':'33333333-3333-4333-8333-333333333333','content':{'ok':1},'forward_protection_required':True,'object_id':None}
    def delivery_receipt(self,*args):self.receipts.append(args);return {}
    def media_enqueue(self,*a):return {'job_id':'j'}
    def request_deployment(self,subject,target,idem):
        if subject=='normal':raise FanoosApiError('permission_denied','deployment.manage required',403)
        self.deploy.append((subject,target,idem));return {'request_id':'44444444-4444-4444-8444-444444444444','state':'REQUESTED'}
    def deployment_status(self,s,r):return {'request_id':r,'state':'HEALTHCHECK','candidate_sha':'a'*40}
class AppTest(unittest.TestCase):
    def setUp(self):self.tmp=tempfile.TemporaryDirectory();self.state=LocalState(Path(self.tmp.name)/'s.db');self.backend=FakeBackend();self.app=BotApplication(self.backend,self.state,'telegram',ApplicationConfig('https://fanoos.test/','prod'))
    def tearDown(self):self.state.close();self.tmp.cleanup()
    def test_start_bad_payload(self):self.assertIn('معتبر نیست',self.app.start('1','bad!').screen.text)
    def test_link(self):self.assertIn('موفقیت',self.app.link('1','abc_DEF-1').screen.text)
    def test_workspace_select(self):self.app.select_workspace('1','11111111-1111-4111-8111-111111111111');self.assertTrue(self.backend.selected.startswith('1'))
    def test_cross_tenant_denied(self):self.assertIn('دسترسی',self.app.select_workspace('1','99999999-9999-4999-8999-999999999999').screen.text)
    def test_payment_idempotency(self):self.backend.selected='11111111-1111-4111-8111-111111111111';self.app.create_order('1','p','bot-order:telegram:5');self.app.create_order('1','p','bot-order:telegram:5');self.assertEqual(self.backend.orders,['bot-order:telegram:5']*2)
    def test_protected_telegram_reauthorizes(self):self.backend.selected='11111111-1111-4111-8111-111111111111';r=self.app.protected_resource('1','r');self.assertTrue(r.screen.protect_content);self.assertEqual(self.backend.consume_count,1);self.assertIsNotNone(r.receipt)
    def test_bale_fail_closed_and_receipt(self):
        b=BotApplication(self.backend,self.state,'bale');self.backend.selected='11111111-1111-4111-8111-111111111111';r=b.protected_resource('1','r');self.assertIn('بله',r.screen.text);self.assertTrue(any(x[-1]=='unsupported_forward_protection' for x in self.backend.receipts))
    def test_update_private_and_duplicate_idempotency(self):
        r=self.app.update_begin('owner',True);ref=r.screen.rows[0][0].callback.split(':',1)[1];a=self.app.update_confirm('owner',True,ref);self.assertIn('REQUESTED',a.screen.text);idem=self.backend.deploy[0][2];self.assertEqual(idem,self.backend.deploy[0][2])
    def test_cancel_invalidates(self):
        r=self.app.update_begin('owner',True);ref=r.screen.rows[0][0].callback.split(':',1)[1];self.app.update_cancel('owner',ref);self.assertIn('منقضی',self.app.update_confirm('owner',True,ref).screen.text)
    def test_update_not_advertised_and_normal_denied_and_bale_disabled(self):
        self.assertNotIn('update_server',self.app.help().screen.text);r=self.app.update_begin('normal',True);ref=r.screen.rows[0][0].callback.split(':',1)[1];self.assertIn('اجازه',self.app.update_confirm('normal',True,ref).screen.text);b=BotApplication(self.backend,self.state,'bale',ApplicationConfig(deployment_target_key='prod'));self.assertIn('فعال نیست',b.update_begin('owner',True).screen.text)
    def test_restart_status_recovery(self):
        self.state.record_deployment('owner','44444444-4444-4444-8444-444444444444');self.assertIn('HEALTHCHECK',self.app.update_status('owner',True).screen.text)
