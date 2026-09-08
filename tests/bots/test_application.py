import tempfile,unittest
from pathlib import Path
from fanoos_bot.api import FanoosApiError
from fanoos_bot.application import ApplicationConfig,BotApplication
from fanoos_bot.state import LocalState
class FakeBackend:
    JOB='55555555-5555-4555-8555-555555555555'
    RESOURCE='66666666-6666-4666-8666-666666666666'
    def __init__(self):self.selected=None;self.orders=[];self.receipts=[];self.deploy=[];self.consume_count=0;self.object_mode=False;self.media_ready=False;self.unlinked=[]
    def consume_link(self,p,s,t):return {'user_id':'u'}
    def unlink(self,p,s):self.unlinked.append((p,s));return {'revoked':True}
    def workspaces(self,p,s):
        if s=='unlinked':raise FanoosApiError('messaging_link_required','link',403)
        return {'workspaces':[{'id':'11111111-1111-4111-8111-111111111111','name':'A'},{'id':'22222222-2222-4222-8222-222222222222','name':'B'}],'selected_workspace_id':self.selected}
    def select_workspace(self,p,s,w):
        if w.startswith('9'):raise FanoosApiError('workspace_forbidden','no',403)
        self.selected=w;return {'selected_workspace_id':w}
    def schedule(self,p,s,w,f,t,limit,cursor):return {'timezone':'UTC','items':[{'id':'e','title':'کلاس','starts_at':f+'T08:00:00+00:00','location_text':'دانشکده'}]}
    def grades(self,*a):return {'items':[{'result_id':'g','item_title':'کوییز','score':'18','max_score':'20'}]}
    def announcements(self,*a):return {'items':[{'id':'n','title':'اطلاعیه','body':'متن'}]}
    def resources(self,*a):return {'items':[{'resource_id':self.RESOURCE,'title':'جزوه','delivery_supported':True,'resource_version_id':'v'}]}
    def create_order(self,p,s,w,prod,idem):self.orders.append(idem);return {'order_id':'o','title':'P','amount_minor':100,'currency':'IRR','status':'pending','payment_url':'https://pay.test/x'}
    def order_status(self,*a):return {'status':'paid','entitlement':{'granted':True}}
    def delivery_issue(self,p,s,w,r):return {'issuance_id':'33333333-3333-4333-8333-333333333333','delivery_token':'tok'}
    def delivery_consume(self,p,s,w,t):self.consume_count+=1;return {'issuance_id':'33333333-3333-4333-8333-333333333333','content':None if self.object_mode else {'ok':1},'forward_protection_required':True,'object_id':'obj' if self.object_mode else None}
    def delivery_receipt(self,*args):self.receipts.append(args);return {}
    def media_enqueue(self,*a):return {'job_id':self.JOB}
    def media_derivative_issue(self,p,s,w,j):
        if not self.media_ready:raise FanoosApiError('protected_media_artifact_unavailable','not ready',404)
        return {'job_id':j,'artifact_capability':'artifact-cap','size':13,'mime':'application/pdf'}
    def media_derivative_redeem(self,*a):return b'%PDF-derived'
    def deployment_overview(self,subject,target):
        if subject=='normal':return {'can_manage_deployments':False}
        return {'can_manage_deployments':True,'current_release_sha':'a'*40,'candidate_sha':'b'*40,'update_available':True,'health':{'status':'healthy'}}
    def request_deployment(self,subject,target,idem):self.deploy.append((subject,target,idem));return {'request_id':'44444444-4444-4444-8444-444444444444','state':'REQUESTED'}
    def deployment_status(self,s,r):return {'request_id':r,'state':'HEALTHCHECK','candidate_sha':'a'*40}
class AppTest(unittest.TestCase):
    def setUp(self):self.tmp=tempfile.TemporaryDirectory();self.state=LocalState(Path(self.tmp.name)/'s.db');self.backend=FakeBackend();self.app=BotApplication(self.backend,self.state,'telegram',ApplicationConfig('https://fanoos.test/','prod'))
    def tearDown(self):self.state.close();self.tmp.cleanup()
    def select(self):self.backend.selected='11111111-1111-4111-8111-111111111111'
    def test_start_bad_payload(self):self.assertIn('معتبر نیست',self.app.start('1','bad!').screen.text)
    def test_link_and_unlink(self):self.assertIn('موفقیت',self.app.link('1','abc_DEF-1').screen.text);self.assertIn('جدا شد',self.app.unlink('1').screen.text);self.assertEqual(self.backend.unlinked,[('telegram','1')])
    def test_workspace_select(self):self.app.select_workspace('1','11111111-1111-4111-8111-111111111111');self.assertTrue(self.backend.selected.startswith('1'))
    def test_cross_tenant_denied(self):self.assertIn('دسترسی',self.app.select_workspace('1','99999999-9999-4999-8999-999999999999').screen.text)
    def test_native_reads_replace_web_fallbacks(self):
        self.select();self.assertIn('کلاس',self.app.day_schedule('1',0).screen.text);self.assertIn('18',self.app.grades('1').screen.text);self.assertIn('اطلاعیه',self.app.announcements('1').screen.text)
        resources=self.app.resources('1');self.assertIn('جزوه',resources.screen.text);self.assertTrue(resources.screen.rows);self.assertTrue(resources.screen.rows[0][0].callback.startswith('resource:'))
    def test_payment_idempotency(self):self.select();self.app.create_order('1','p','bot-order:telegram:5');self.app.create_order('1','p','bot-order:telegram:5');self.assertEqual(self.backend.orders,['bot-order:telegram:5']*2)
    def test_protected_structured_telegram_reauthorizes(self):self.select();r=self.app.protected_resource('1','r');self.assertTrue(r.screen.protect_content);self.assertEqual(self.backend.consume_count,1);self.assertIsNotNone(r.receipt);self.assertIsNone(r.document)
    def test_protected_pdf_enqueue_then_derivative_delivery(self):
        self.select();self.backend.object_mode=True;r=self.app.protected_resource('1',self.backend.RESOURCE);self.assertIn('آماده',r.screen.text);self.assertIsNotNone(self.app.media_state.get(self.backend.JOB,'1'))
        pending=self.app.protected_media_ready('1',self.backend.JOB);self.assertIn('هنوز آماده',pending.screen.text);self.backend.media_ready=True;ready=self.app.protected_media_ready('1',self.backend.JOB);self.assertIsNotNone(ready.document);self.assertEqual(ready.document.data,b'%PDF-derived');self.assertIsNotNone(ready.receipt);self.assertEqual(ready.metadata['media_job_id'],self.backend.JOB)
    def test_media_job_is_subject_bound_locally(self):self.select();self.backend.object_mode=True;self.app.protected_resource('1',self.backend.RESOURCE);self.assertIn('متعلق',self.app.protected_media_ready('2',self.backend.JOB).screen.text)
    def test_bale_fail_closed_and_receipt(self):
        b=BotApplication(self.backend,self.state,'bale');self.select();r=b.protected_resource('1','r');self.assertIn('بله',r.screen.text);self.assertTrue(any(x[-1]=='unsupported_forward_protection' for x in self.backend.receipts))
    def test_update_overview_permission_gate_and_confirmation(self):
        denied=self.app.update_begin('normal',True);self.assertIn('اجازه',denied.screen.text);self.assertFalse(denied.screen.rows)
        r=self.app.update_begin('owner',True);self.assertIn('به‌روزرسانی موجود',r.screen.text);ref=r.screen.rows[0][0].callback.split(':',1)[1];a=self.app.update_confirm('owner',True,ref);self.assertIn('REQUESTED',a.screen.text);self.assertEqual(self.backend.deploy[0][0],'owner')
    def test_cancel_invalidates(self):
        r=self.app.update_begin('owner',True);ref=r.screen.rows[0][0].callback.split(':',1)[1];self.app.update_cancel('owner',ref);self.assertIn('منقضی',self.app.update_confirm('owner',True,ref).screen.text)
    def test_update_not_advertised_and_bale_disabled(self):self.assertNotIn('update_server',self.app.help().screen.text);b=BotApplication(self.backend,self.state,'bale',ApplicationConfig(deployment_target_key='prod'));self.assertIn('فعال نیست',b.update_begin('owner',True).screen.text)
    def test_restart_status_recovery(self):self.state.record_deployment('owner','44444444-4444-4444-8444-444444444444');self.assertIn('HEALTHCHECK',self.app.update_status('owner',True).screen.text)
