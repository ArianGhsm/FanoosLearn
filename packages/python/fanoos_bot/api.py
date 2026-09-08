from __future__ import annotations
import hashlib, hmac, json, secrets, time
from dataclasses import dataclass
from typing import Any, Callable
from urllib import error, request

class FanoosApiError(RuntimeError):
    def __init__(self, code: str, message: str, status: int = 500, retry_after: float | None = None):
        super().__init__(message); self.code=code; self.status=status; self.retry_after=retry_after
class FanoosContractError(RuntimeError): pass

@dataclass(frozen=True)
class HttpResponse:
    status: int
    body: bytes
    headers: dict[str,str]

class UrllibSender:
    def __call__(self, url: str, body: bytes, headers: dict[str,str], timeout: float) -> HttpResponse:
        req=request.Request(url,data=body,headers=headers,method='POST')
        try:
            with request.urlopen(req, timeout=timeout) as res:
                return HttpResponse(int(res.status),res.read(),dict(res.headers.items()))
        except error.HTTPError as exc:
            return HttpResponse(int(exc.code),exc.read(),dict(exc.headers.items()))
        except (error.URLError,TimeoutError) as exc:
            raise FanoosApiError('network_unavailable','FANOOS backend is unavailable.',503) from exc

class FanoosApiClient:
    def __init__(self, origin: str, key_id: str, secret: str|bytes, *, timeout: float=10, sender: Callable[...,HttpResponse]|None=None, clock: Callable[[],float]=time.time, nonce_factory: Callable[[],str]|None=None, sleep: Callable[[float],None]=time.sleep):
        self.origin=origin.rstrip('/'); self.key_id=key_id.strip(); self.secret=secret.encode() if isinstance(secret,str) else bytes(secret)
        if not self.origin.startswith(('https://','http://localhost','http://127.0.0.1')): raise ValueError('backend origin must use HTTPS')
        if not self.key_id or len(self.secret)<16: raise ValueError('service credentials are not configured')
        self.timeout=timeout; self.sender=sender or UrllibSender(); self.clock=clock; self.nonce_factory=nonce_factory or (lambda: secrets.token_urlsafe(18)); self.sleep=sleep
    @staticmethod
    def encode(payload: dict[str,Any]) -> bytes:
        return json.dumps(payload,ensure_ascii=False,sort_keys=True,separators=(',',':'),allow_nan=False).encode('utf-8')
    @staticmethod
    def _header(headers: dict[str,str], name: str) -> str:
        target=name.lower()
        for key,value in headers.items():
            if key.lower()==target:return str(value)
        return ''
    def headers(self,path: str, body: bytes, *, content_type: str='application/json', accept: str='application/json', extra: dict[str,str]|None=None) -> dict[str,str]:
        timestamp=str(int(self.clock())); nonce=self.nonce_factory(); digest=hashlib.sha256(body).hexdigest()
        canonical='\n'.join(('fanoos-service-v1','POST',path,timestamp,nonce,digest)).encode()
        signature=hmac.new(self.secret,canonical,hashlib.sha256).hexdigest()
        result={'Accept':accept,'Content-Type':content_type,'X-Fanoos-Key-Id':self.key_id,'X-Fanoos-Timestamp':timestamp,'X-Fanoos-Nonce':nonce,'X-Fanoos-Content-SHA256':digest,'X-Fanoos-Signature':signature}
        if extra:
            for key,value in extra.items():
                if not isinstance(key,str) or not isinstance(value,str) or '\r' in key+value or '\n' in key+value:raise ValueError('invalid extra header')
                result[key]=value
        return result
    def _send(self,path:str,body:bytes,*,safe_to_retry:bool,content_type:str='application/json',accept:str='application/json',extra_headers:dict[str,str]|None=None)->HttpResponse:
        if not path.startswith('/api/internal/v1/'): raise ValueError('internal API path required')
        attempts=3 if safe_to_retry else 1
        for attempt in range(attempts):
            res=self.sender(self.origin+path,body,self.headers(path,body,content_type=content_type,accept=accept,extra=extra_headers),self.timeout)
            if 200 <= res.status < 300:return res
            data=self._decode(res); err=data.get('error') if isinstance(data,dict) and isinstance(data.get('error'),dict) else {}
            code=str(err.get('code') or 'backend_error'); message=str(err.get('message') or 'FANOOS request failed.')
            retry_after=None
            try: retry_after=float(self._header(res.headers,'Retry-After'))
            except (TypeError,ValueError): pass
            if safe_to_retry and res.status in {429,502,503,504} and attempt+1<attempts:
                self.sleep(max(0,min(5,retry_after if retry_after is not None else 0.2*(attempt+1)))); continue
            raise FanoosApiError(code,message,res.status,retry_after)
        raise AssertionError('unreachable')
    def post(self,path: str,payload: dict[str,Any],*,safe_to_retry: bool=False) -> Any:
        res=self._send(path,self.encode(payload),safe_to_retry=safe_to_retry)
        data=self._decode(res)
        if not isinstance(data,dict) or data.get('ok') is not True or not isinstance(data.get('meta'),dict) or data['meta'].get('api_version')!='internal-v1': raise FanoosContractError('invalid success envelope')
        return data.get('data')
    def post_binary(self,path:str,payload:dict[str,Any],*,max_bytes:int,safe_to_retry:bool=True)->bytes:
        max_bytes=max(1024,min(200*1024*1024,int(max_bytes)))
        res=self._send(path,self.encode(payload),safe_to_retry=safe_to_retry,accept='application/pdf')
        content_type=self._header(res.headers,'Content-Type').split(';',1)[0].strip().lower()
        length=self._header(res.headers,'Content-Length').strip()
        if content_type!='application/pdf':raise FanoosContractError('binary response is not application/pdf')
        if length.isdigit() and int(length)>max_bytes:raise FanoosContractError('binary response exceeds limit')
        if len(res.body)<5 or len(res.body)>max_bytes or not res.body.startswith(b'%PDF-'):raise FanoosContractError('invalid bounded PDF response')
        return res.body
    def post_pdf(self,path:str,pdf:bytes,upload_capability:str,*,safe_to_retry:bool=True)->Any:
        if not isinstance(pdf,(bytes,bytearray)) or len(pdf)<5 or not bytes(pdf).startswith(b'%PDF-'):raise ValueError('PDF body required')
        if not upload_capability or len(upload_capability)>4096:raise ValueError('upload capability required')
        res=self._send(path,bytes(pdf),safe_to_retry=safe_to_retry,content_type='application/pdf',extra_headers={'X-Fanoos-Upload-Capability':upload_capability})
        data=self._decode(res)
        if not isinstance(data,dict) or data.get('ok') is not True or not isinstance(data.get('meta'),dict) or data['meta'].get('api_version')!='internal-v1':raise FanoosContractError('invalid success envelope')
        return data.get('data')
    @staticmethod
    def _decode(res: HttpResponse) -> dict[str,Any]:
        try:
            value=json.loads(res.body.decode('utf-8'))
            return value if isinstance(value,dict) else {}
        except Exception:return {}
    def consume_link(self,platform,subject,token):return self.post('/api/internal/v1/messaging/link-challenges/consume',{'platform':platform,'subject':str(subject),'challenge_token':token},safe_to_retry=False)
    def unlink(self,platform,subject):return self.post('/api/internal/v1/messaging/links/revoke',{'platform':platform,'subject':str(subject)},safe_to_retry=True)
    def workspaces(self,platform,subject):return self.post('/api/internal/v1/messaging/workspaces/list',{'platform':platform,'subject':str(subject)},safe_to_retry=True)
    def select_workspace(self,platform,subject,workspace_id):return self.post('/api/internal/v1/messaging/workspaces/select',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id},safe_to_retry=False)
    def schedule(self,platform,subject,workspace_id,from_date,to_date,limit=100,cursor=None):return self.post('/api/internal/v1/academics/schedule',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'from_date':from_date,'to_date':to_date,'limit':limit,'cursor':cursor},safe_to_retry=True)
    def grades(self,platform,subject,workspace_id,limit=50,cursor=None):return self.post('/api/internal/v1/academics/grades',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'limit':limit,'cursor':cursor},safe_to_retry=True)
    def announcements(self,platform,subject,workspace_id,limit=20,cursor=None):return self.post('/api/internal/v1/announcements/list',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'limit':limit,'cursor':cursor},safe_to_retry=True)
    def resources(self,platform,subject,workspace_id,limit=20,cursor=None):return self.post('/api/internal/v1/content/resources/list',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'limit':limit,'cursor':cursor},safe_to_retry=True)
    def create_order(self,platform,subject,workspace_id,product_id,idempotency_key):return self.post('/api/internal/v1/commerce/orders',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'product_id':product_id,'idempotency_key':idempotency_key},safe_to_retry=True)
    def order_status(self,platform,subject,workspace_id,order_id):return self.post('/api/internal/v1/commerce/orders/status',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'order_id':order_id},safe_to_retry=True)
    def project_notifications(self):return self.post('/api/internal/v1/notifications/project',{},safe_to_retry=True)
    def claim_notification(self,platform):return self.post('/api/internal/v1/notifications/claim',{'platform':platform},safe_to_retry=True)
    def notification_receipt(self,platform,delivery_id,lease_token,idempotency_key,outcome,provider_message_ref=None,error_code=None):return self.post('/api/internal/v1/notifications/receipt',{'platform':platform,'delivery_id':delivery_id,'lease_token':lease_token,'idempotency_key':idempotency_key,'outcome':outcome,'provider_message_ref':provider_message_ref,'error_code':error_code},safe_to_retry=True)
    def delivery_issue(self,platform,subject,workspace_id,resource_id):return self.post('/api/internal/v1/deliveries/issue',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'resource_id':resource_id},safe_to_retry=False)
    def delivery_consume(self,platform,subject,workspace_id,delivery_token):return self.post('/api/internal/v1/deliveries/consume',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'delivery_token':delivery_token},safe_to_retry=False)
    def delivery_receipt(self,platform,workspace_id,issuance_id,idempotency_key,outcome,provider_message_ref=None,error_code=None):return self.post('/api/internal/v1/deliveries/receipt',{'platform':platform,'workspace_id':workspace_id,'issuance_id':issuance_id,'idempotency_key':idempotency_key,'outcome':outcome,'provider_message_ref':provider_message_ref,'error_code':error_code},safe_to_retry=True)
    def media_enqueue(self,workspace_id,issuance_id,renderer_algorithm_version,limits=None):return self.post('/api/internal/v1/protected-media/enqueue',{'workspace_id':workspace_id,'issuance_id':issuance_id,'renderer_algorithm_version':renderer_algorithm_version,'limits':limits or {}},safe_to_retry=True)
    def media_claim(self):return self.post('/api/internal/v1/protected-media/claim',{},safe_to_retry=True)
    def media_source_redeem(self,job_id,lease_token,object_capability,max_bytes):return self.post_binary('/api/internal/v1/protected-media/source/redeem',{'job_id':job_id,'lease_token':lease_token,'object_capability':object_capability},max_bytes=max_bytes,safe_to_retry=True)
    def media_authorize_publish(self,job_id,lease_token,completion_key,checksum_sha256,size,mime='application/pdf'):return self.post('/api/internal/v1/protected-media/artifacts/authorize-publish',{'job_id':job_id,'lease_token':lease_token,'completion_key':completion_key,'checksum_sha256':checksum_sha256,'size':int(size),'mime':mime},safe_to_retry=True)
    def media_publish_pdf(self,upload_capability,pdf):return self.post_pdf('/api/internal/v1/protected-media/artifacts/publish',pdf,upload_capability,safe_to_retry=True)
    def media_complete(self,job_id,lease_token,completion_key,result):return self.post('/api/internal/v1/protected-media/complete',{'job_id':job_id,'lease_token':lease_token,'completion_key':completion_key,'result':result},safe_to_retry=True)
    def media_fail(self,job_id,lease_token,failure_code):return self.post('/api/internal/v1/protected-media/fail',{'job_id':job_id,'lease_token':lease_token,'failure_code':failure_code},safe_to_retry=True)
    def media_derivative_issue(self,platform,subject,workspace_id,job_id):return self.post('/api/internal/v1/protected-media/derivatives/issue',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'job_id':job_id},safe_to_retry=True)
    def media_derivative_redeem(self,platform,subject,workspace_id,artifact_capability,max_bytes):return self.post_binary('/api/internal/v1/protected-media/derivatives/redeem',{'platform':platform,'subject':str(subject),'workspace_id':workspace_id,'artifact_capability':artifact_capability},max_bytes=max_bytes,safe_to_retry=True)
    def deployment_overview(self,subject,target_key):return self.post('/api/internal/v1/deployments/overview',{'platform':'telegram','subject':str(subject),'target_key':target_key},safe_to_retry=True)
    def request_deployment(self,subject,target_key,idempotency_key):return self.post('/api/internal/v1/deployments/request',{'platform':'telegram','subject':str(subject),'target_key':target_key,'idempotency_key':idempotency_key},safe_to_retry=True)
    def deployment_status(self,subject,request_id):return self.post('/api/internal/v1/deployments/status',{'platform':'telegram','subject':str(subject),'request_id':request_id},safe_to_retry=True)
