from __future__ import annotations
from dataclasses import dataclass
from .botapi import BotApiError
from .chunking import chunks
from .models import ActionResult, Screen

@dataclass(frozen=True)
class UpdateContext:
    subject: str
    chat_id: str
    private: bool
    message_id: int|None=None
    callback_id: str|None=None
    event_id: str|None=None

class BotRuntime:
    def __init__(self,platform,transport,application,state):self.platform=platform;self.transport=transport;self.app=application;self.state=state
    def handle_message(self,ctx:UpdateContext,text:str):
        command,*rest=(text or '').strip().split(maxsplit=1); arg=rest[0] if rest else ''
        if command.startswith('/start'): result=self.app.start(ctx.subject,arg or None)
        elif command=='/link':result=self.app.link(ctx.subject,arg)
        elif command in {'/menu','/home'}:result=self.app.home(ctx.subject)
        elif command in {'/workspace','/workspaces'}:result=self.app.workspaces(ctx.subject)
        elif command=='/buy':result=self.app.create_order(ctx.subject,arg, f'bot-order:{self.platform}:{ctx.event_id}' if ctx.event_id else None)
        elif command=='/order':result=self.app.order_status(ctx.subject,arg)
        elif command=='/resource':result=self.app.protected_resource(ctx.subject,arg)
        elif command=='/update_server':result=self.app.update_begin(ctx.subject,ctx.private)
        elif command=='/update_status':result=self.app.update_status(ctx.subject,ctx.private,arg or None)
        elif command=='/help':result=self.app.help()
        else:result=self.app.home(ctx.subject)
        return self.deliver(ctx,result)
    def handle_callback(self,ctx:UpdateContext,value:str):
        if ctx.callback_id:self.transport.answer_callback(ctx.callback_id)
        result=self.app.callback(ctx.subject,ctx.private,value);return self.deliver(ctx,result)
    def deliver(self,ctx:UpdateContext,result:ActionResult):
        refs=[]; screen=result.screen; texts=chunks(screen.text,4096)
        try:
            for i,text in enumerate(texts):
                part=Screen(text,screen.rows if i==len(texts)-1 else (),edit=screen.edit and len(texts)==1,protect_content=screen.protect_content)
                if part.edit and ctx.message_id is not None:sent=self.transport.edit_screen(ctx.chat_id,ctx.message_id,part)
                else:sent=self.transport.send_screen(ctx.chat_id,part)
                if isinstance(sent,dict) and sent.get('message_id') is not None:refs.append(str(sent['message_id']))
            if result.receipt:
                self.app.backend.delivery_receipt(self.platform,result.receipt.workspace_id,result.receipt.issuance_id,result.receipt.idempotency_key,'delivered',refs[-1] if refs else None,None)
            return refs[-1] if refs else None
        except BotApiError as exc:
            if result.receipt:
                try:self.app.backend.delivery_receipt(self.platform,result.receipt.workspace_id,result.receipt.issuance_id,result.receipt.idempotency_key,'failed',None,exc.code)
                except Exception:pass
            raise

class NotificationPump:
    def __init__(self,platform,backend,transport,state):self.platform=platform;self.backend=backend;self.transport=transport;self.state=state
    def run_once(self):
        projection=self.backend.claim_notification(self.platform); delivery=projection.get('delivery') if isinstance(projection,dict) else None
        if not delivery:return False
        did=str(delivery['delivery_id']); prior=self.state.sent_delivery(did); idem='notification:'+did
        if prior:
            self.backend.notification_receipt(self.platform,did,delivery['lease_token'],idem,'delivered',prior,None);return True
        payload=delivery.get('payload') or {}; text=(str(payload.get('title') or '')+'\n\n'+str(payload.get('body') or '')).strip()
        try:
            ref=None
            for part in chunks(text,4096):
                sent=self.transport.send_screen(str(delivery['subject']),Screen(part)); ref=str(sent.get('message_id')) if isinstance(sent,dict) and sent.get('message_id') is not None else ref
            self.state.remember_delivery(did,ref or 'sent');self.backend.notification_receipt(self.platform,did,delivery['lease_token'],idem,'delivered',ref,None)
        except BotApiError as exc:
            self.backend.notification_receipt(self.platform,did,delivery['lease_token'],idem,'retry' if exc.transient else 'failed',None,exc.code)
        return True
