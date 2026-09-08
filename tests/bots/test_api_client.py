import hashlib,hmac,json,unittest
from fanoos_bot.api import FanoosApiClient,FanoosApiError,FanoosContractError,HttpResponse
class ApiClientTest(unittest.TestCase):
    def test_exact_signature(self):
        seen={}
        def sender(url,body,headers,timeout):seen.update(url=url,body=body,headers=headers);return HttpResponse(200,b'{"ok":true,"data":{"x":1},"meta":{"api_version":"internal-v1"}}',{})
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=sender,clock=lambda:100,nonce_factory=lambda:'nonce')
        self.assertEqual(c.post('/api/internal/v1/notifications/project',{}),{'x':1});digest=hashlib.sha256(b'{}').hexdigest();canonical=f'fanoos-service-v1\nPOST\n/api/internal/v1/notifications/project\n100\nnonce\n{digest}'.encode();self.assertEqual(seen['headers']['X-Fanoos-Signature'],hmac.new(b'0123456789abcdef',canonical,hashlib.sha256).hexdigest())
    def test_retry_gets_fresh_nonce(self):
        nonces=iter(['a','b']);headers=[]
        def sender(url,body,h,timeout):headers.append(h);return HttpResponse(503,b'{"ok":false,"error":{"code":"busy","message":"busy"},"meta":{}}',{}) if len(headers)==1 else HttpResponse(200,b'{"ok":true,"data":{},"meta":{"api_version":"internal-v1"}}',{})
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=sender,nonce_factory=lambda:next(nonces),sleep=lambda _:None)
        c.post('/api/internal/v1/notifications/project',{},safe_to_retry=True);self.assertEqual([h['X-Fanoos-Nonce'] for h in headers],['a','b']);self.assertEqual(headers[0]['X-Fanoos-Content-SHA256'],headers[1]['X-Fanoos-Content-SHA256'])
    def test_unsafe_not_retried(self):
        calls=0
        def sender(*args):
            nonlocal calls;calls+=1;return HttpResponse(503,b'{"ok":false,"error":{"code":"busy","message":"busy"},"meta":{}}',{})
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=sender)
        with self.assertRaises(FanoosApiError):c.post('/api/internal/v1/x',{},safe_to_retry=False)
        self.assertEqual(calls,1)
    def test_lease_claim_and_fail_transitions_are_not_auto_retried(self):
        calls=[]
        def sender(url,*args):calls.append(url);return HttpResponse(503,b'{"ok":false,"error":{"code":"busy","message":"busy"},"meta":{}}',{})
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=sender,sleep=lambda _:None)
        for operation in (lambda:c.claim_notification('telegram'),lambda:c.media_claim(),lambda:c.media_fail('j','lease','internal_error')):
            with self.assertRaises(FanoosApiError):operation()
        self.assertEqual(len(calls),3)
    def test_contract_mismatch_fails(self):
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=lambda *a:HttpResponse(200,b'{"ok":true,"data":{},"meta":{"api_version":"v1"}}',{}))
        with self.assertRaises(FanoosContractError):c.post('/api/internal/v1/x',{})
    def test_binary_pdf_is_bounded_and_signed(self):
        seen={};pdf=b'%PDF-test-bytes'
        def sender(url,body,headers,timeout):seen.update(url=url,body=body,headers=headers);return HttpResponse(200,pdf,{'Content-Type':'application/pdf','Content-Length':str(len(pdf))})
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=sender,clock=lambda:100,nonce_factory=lambda:'n')
        data=c.media_source_redeem('j','lease','cap',1024);self.assertEqual(data,pdf);self.assertEqual(seen['headers']['Accept'],'application/pdf')
        digest=hashlib.sha256(seen['body']).hexdigest();canonical=f'fanoos-service-v1\nPOST\n/api/internal/v1/protected-media/source/redeem\n100\nn\n{digest}'.encode();self.assertEqual(seen['headers']['X-Fanoos-Signature'],hmac.new(b'0123456789abcdef',canonical,hashlib.sha256).hexdigest())
    def test_binary_wrong_mime_or_oversize_fails_closed(self):
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=lambda *a:HttpResponse(200,b'%PDF-x',{'Content-Type':'text/plain'}))
        with self.assertRaises(FanoosContractError):c.media_source_redeem('j','l','c',1024)
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=lambda *a:HttpResponse(200,b'%PDF-'+b'x'*2000,{'Content-Type':'application/pdf'}))
        with self.assertRaises(FanoosContractError):c.media_source_redeem('j','l','c',1024)
    def test_raw_pdf_upload_signs_exact_bytes_and_capability(self):
        seen={};pdf=b'%PDF-rendered'
        def sender(url,body,headers,timeout):seen.update(url=url,body=body,headers=headers);return HttpResponse(200,b'{"ok":true,"data":{"artifact_ref":"pma:x"},"meta":{"api_version":"internal-v1"}}',{'Content-Type':'application/json'})
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=sender,clock=lambda:200,nonce_factory=lambda:'upload')
        c.media_publish_pdf('opaque-upload-cap',pdf);self.assertEqual(seen['body'],pdf);self.assertEqual(seen['headers']['Content-Type'],'application/pdf');self.assertEqual(seen['headers']['X-Fanoos-Upload-Capability'],'opaque-upload-cap')
        self.assertEqual(seen['headers']['X-Fanoos-Content-SHA256'],hashlib.sha256(pdf).hexdigest())
    def test_native_read_and_owner_paths_are_fixed(self):
        paths=[]
        def sender(url,body,headers,timeout):paths.append(url.split('https://f.test',1)[1]);return HttpResponse(200,b'{"ok":true,"data":{},"meta":{"api_version":"internal-v1"}}',{})
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=sender)
        c.schedule('telegram','1','w','2026-09-08','2026-09-08');c.grades('telegram','1','w');c.announcements('telegram','1','w');c.resources('telegram','1','w');c.unlink('telegram','1');c.deployment_overview('1','primary')
        self.assertEqual(paths,['/api/internal/v1/academics/schedule','/api/internal/v1/academics/grades','/api/internal/v1/announcements/list','/api/internal/v1/content/resources/list','/api/internal/v1/messaging/links/revoke','/api/internal/v1/deployments/overview'])
