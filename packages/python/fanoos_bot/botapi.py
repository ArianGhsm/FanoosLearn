from __future__ import annotations
import json, mimetypes, secrets
from pathlib import Path
from typing import Any
from urllib import request, error
from .callbacks import CallbackCodec
from .capabilities import PlatformCapabilities
from .models import Screen

class BotApiError(RuntimeError):
    def __init__(self,code:str='bot_api_error',*,retry_after:float|None=None,transient:bool=False): super().__init__(code); self.code=code; self.retry_after=retry_after; self.transient=transient

class JsonBotApiTransport:
    def __init__(self,endpoint:str,token:str,capabilities:PlatformCapabilities,*,timeout:float=15):
        if not token or any(c.isspace() for c in token): raise ValueError('bot token missing')
        self.endpoint=endpoint.rstrip('/'); self.token=token; self.capabilities=capabilities; self.timeout=timeout
    def _url(self,method:str)->str:return f'{self.endpoint}/bot{self.token}/{method}'
    def _call(self,method:str,payload:dict[str,Any]|None=None)->Any:
        body=json.dumps(payload or {},ensure_ascii=False,separators=(',',':')).encode(); req=request.Request(self._url(method),data=body,headers={'Content-Type':'application/json','Accept':'application/json'},method='POST')
        try:
            with request.urlopen(req,timeout=self.timeout) as res:data=json.loads(res.read().decode())
        except error.HTTPError as exc:
            try:data=json.loads(exc.read().decode())
            except Exception:data={}
        except (error.URLError,TimeoutError) as exc: raise BotApiError('network_unavailable',transient=True) from exc
        if not isinstance(data,dict) or data.get('ok') is not True:
            params=data.get('parameters') if isinstance(data,dict) else {}; retry=params.get('retry_after') if isinstance(params,dict) else None
            raise BotApiError(str(data.get('error_code') if isinstance(data,dict) else 'bot_api_error'),retry_after=float(retry) if isinstance(retry,(int,float)) else None,transient=bool(retry) or (isinstance(data,dict) and int(data.get('error_code') or 0)>=500))
        return data.get('result')
    def get_me(self):return self._call('getMe')
    def get_updates(self,offset:int,timeout:int=20):return self._call('getUpdates',{'offset':offset,'timeout':timeout,'allowed_updates':['message','callback_query']})
    def answer_callback(self,callback_id:str,text:str=''):return self._call('answerCallbackQuery',{'callback_query_id':callback_id,'text':text} if text else {'callback_query_id':callback_id})
    def chat_action(self,chat_id:str,action:str='typing'):return self._call('sendChatAction',{'chat_id':chat_id,'action':action})
    def _markup(self,screen:Screen):
        if not screen.rows:return None
        rows=[]
        for row in screen.rows:
            r=[]
            for b in row:
                item={'text':b.text}
                if b.callback is not None:item['callback_data']=CallbackCodec.encode(*CallbackCodec.decode(b.callback))
                else:item['url']=b.url
                r.append(item)
            rows.append(r)
        return {'inline_keyboard':rows}
    def send_screen(self,chat_id:str,screen:Screen,*,reply_to:int|None=None):
        if len(screen.text)>self.capabilities.max_text_chars:raise ValueError('screen must be chunked before transport')
        if screen.protect_content and not self.capabilities.supports_forward_protection:raise BotApiError('forward_protection_unsupported')
        p={'chat_id':chat_id,'text':screen.text}
        m=self._markup(screen)
        if m:p['reply_markup']=m
        if screen.protect_content:p['protect_content']=True
        if reply_to is not None:p['reply_parameters']={'message_id':reply_to}
        return self._call('sendMessage',p)
    def edit_screen(self,chat_id:str,message_id:int,screen:Screen):
        if screen.protect_content: return self.send_screen(chat_id,screen)
        p={'chat_id':chat_id,'message_id':message_id,'text':screen.text}; m=self._markup(screen)
        if m:p['reply_markup']=m
        return self._call('editMessageText',p)
    def send_document(self,chat_id:str,path:str|Path,*,caption:str='',protect_content:bool=False):
        path=Path(path); size=path.stat().st_size
        if size>self.capabilities.max_document_upload_bytes:raise BotApiError('file_too_large')
        if protect_content and not self.capabilities.supports_forward_protection:raise BotApiError('forward_protection_unsupported')
        boundary='----fanoos'+secrets.token_hex(12); parts=[]
        def field(name,val):parts.extend([f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{val}\r\n'.encode()])
        field('chat_id',chat_id)
        if caption:field('caption',caption)
        if protect_content:field('protect_content','true')
        mime=mimetypes.guess_type(path.name)[0] or 'application/octet-stream'
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="document"; filename="document.pdf"\r\nContent-Type: {mime}\r\n\r\n'.encode());parts.append(path.read_bytes());parts.append(f'\r\n--{boundary}--\r\n'.encode())
        req=request.Request(self._url('sendDocument'),data=b''.join(parts),headers={'Content-Type':f'multipart/form-data; boundary={boundary}'},method='POST')
        try:
            with request.urlopen(req,timeout=self.timeout) as res:data=json.loads(res.read().decode())
        except Exception as exc:raise BotApiError('document_send_failed',transient=True) from exc
        if not data.get('ok'):raise BotApiError('document_send_failed')
        return data.get('result')
