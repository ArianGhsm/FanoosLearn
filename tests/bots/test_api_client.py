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
    def test_contract_mismatch_fails(self):
        c=FanoosApiClient('https://f.test','kid','0123456789abcdef',sender=lambda *a:HttpResponse(200,b'{"ok":true,"data":{},"meta":{"api_version":"v1"}}',{}))
        with self.assertRaises(FanoosContractError):c.post('/api/internal/v1/x',{})
