from __future__ import annotations
import re, secrets
from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo
from dataclasses import dataclass
from .api import FanoosApiError
from .callbacks import CallbackCodec
from .media_state import ProtectedMediaLocalState
from .models import ActionResult, Button, DeliveryReceiptContext, DocumentPayload, Screen

UUID_RE=re.compile(r'^[0-9a-f-]{36}$',re.I)
START_RE=re.compile(r'^[A-Za-z0-9_-]{1,64}$')
SAFE_IDEM=re.compile(r'^[A-Za-z0-9._:-]{1,160}$')
@dataclass(frozen=True)
class ApplicationConfig:
    web_base_url: str=''
    deployment_target_key: str=''
    protected_renderer_version: str='fanoos-raster-v1'

class BotApplication:
    def __init__(self,backend,state,platform:str,config:ApplicationConfig=ApplicationConfig()):
        self.backend=backend; self.state=state; self.platform=platform; self.config=config; self.media_state=ProtectedMediaLocalState(state)
    def _error(self,exc:Exception):
        if isinstance(exc,FanoosApiError):
            mapping={'messaging_link_required':'حساب پیام‌رسان هنوز به فانوس متصل نیست.','workspace_forbidden':'به این فضای آموزشی دسترسی ندارید.','permission_denied':'اجازه انجام این عملیات را ندارید.','forbidden':'اجازه انجام این عملیات را ندارید.','service_scope_denied':'اجازه انجام این عملیات را ندارید.','deployment_in_progress':'یک به‌روزرسانی دیگر در حال اجراست.'}
            return ActionResult(Screen('⚠️ '+mapping.get(exc.code,'این عملیات فعلاً قابل انجام نیست.')))
        return ActionResult(Screen('⚠️ این عملیات فعلاً قابل انجام نیست.'))
    def start(self,subject:str,payload:str|None=None):
        if payload:
            if self.platform!='telegram' or not START_RE.fullmatch(payload): return ActionResult(Screen('پارامتر اتصال /start معتبر نیست.'))
            return self.link(subject,payload)
        return self.home(subject)
    def link(self,subject:str,token:str):
        if not token or len(token)>128 or not re.fullmatch(r'[A-Za-z0-9_-]+',token): return ActionResult(Screen('کد اتصال معتبر نیست.'))
        try: self.backend.consume_link(self.platform,subject,token); return ActionResult(Screen('✅ حساب پیام‌رسان با موفقیت به فانوس متصل شد.',((Button('ادامه',CallbackCodec.encode('home')),),)))
        except Exception as exc: return self._error(exc)
    def unlink(self,subject:str):
        try:
            self.backend.unlink(self.platform,subject)
            return ActionResult(Screen('حساب این پیام‌رسان از فانوس جدا شد. حساب کاربری اصلی شما حذف نشده است.'))
        except Exception as exc:return self._error(exc)
    def _workspace_projection(self,subject:str): return self.backend.workspaces(self.platform,subject)
    def _selected(self,subject:str):
        p=self._workspace_projection(subject); ws=p.get('workspaces') or []; selected=p.get('selected_workspace_id')
        if not selected and len(ws)==1: self.backend.select_workspace(self.platform,subject,ws[0]['id']); selected=ws[0]['id']
        return p,selected
    def _workspace_or_result(self,subject:str):
        p,selected=self._selected(subject)
        if not selected:return p,None,ActionResult(Screen('ابتدا فضای فعال را انتخاب کنید.'))
        return p,selected,None
    def home(self,subject:str):
        try:
            p,selected=self._selected(subject); ws=p.get('workspaces') or []
            if not ws: return ActionResult(Screen('حساب شما متصل است، اما فضای آموزشی فعالی برای این حساب وجود ندارد.'))
            rows=(
                (Button('امروز',CallbackCodec.encode('today')),Button('فردا',CallbackCodec.encode('tomorrow'))),
                (Button('نمرات من',CallbackCodec.encode('grades')),Button('اطلاعیه‌ها',CallbackCodec.encode('announcements'))),
                (Button('منابع',CallbackCodec.encode('resources')),Button('خرید و دسترسی',CallbackCodec.encode('payments'))),
                (Button('فضای فعال',CallbackCodec.encode('workspaces')),Button('حساب',CallbackCodec.encode('account'))),
            )
            if self.config.web_base_url: rows += ((Button('باز کردن فانوس',url=self.config.web_base_url),),)
            return ActionResult(Screen('🏠 فانوس\nفضای فعال: '+(selected or 'انتخاب نشده'),rows))
        except Exception as exc: return self._error(exc)
    def account(self,subject:str):
        try:
            p,selected=self._selected(subject); return ActionResult(Screen(f'حساب پیام‌رسان: متصل\nکانال: {self.platform}\nتعداد فضاهای فعال: {len(p.get("workspaces") or [])}\nفضای فعال: {selected or "انتخاب نشده"}',((Button('قطع اتصال',CallbackCodec.encode('unlink')),),)))
        except Exception as exc: return self._error(exc)
    def workspaces(self,subject:str):
        try:
            p=self._workspace_projection(subject); ws=p.get('workspaces') or []; selected=p.get('selected_workspace_id')
            if not ws: return ActionResult(Screen('فضای فعالی پیدا نشد.'))
            rows=tuple((Button(('✓ ' if x.get('id')==selected else '')+str(x.get('name') or x.get('slug') or 'فضا'),CallbackCodec.encode('ws',str(x['id']))),) for x in ws)
            return ActionResult(Screen('فضای آموزشی را انتخاب کنید:',rows))
        except Exception as exc: return self._error(exc)
    def select_workspace(self,subject:str,workspace_id:str):
        try: self.backend.select_workspace(self.platform,subject,workspace_id); return self.home(subject)
        except Exception as exc: return self._error(exc)
    def _schedule_for_date(self,subject:str,workspace_id:str,target_date):
        value=target_date.isoformat(); projection=self.backend.schedule(self.platform,subject,workspace_id,value,value,100,None)
        timezone_name=str(projection.get('timezone') or 'UTC')
        return projection,timezone_name
    def day_schedule(self,subject:str,offset:int):
        try:
            _,ws,blocked=self._workspace_or_result(subject)
            if blocked:return blocked
            now_utc=datetime.now(timezone.utc); guessed=now_utc.date()+timedelta(days=offset)
            projection,tz_name=self._schedule_for_date(subject,ws,guessed)
            try:local_date=now_utc.astimezone(ZoneInfo(tz_name)).date()+timedelta(days=offset)
            except Exception:local_date=guessed
            if local_date!=guessed:projection,_=self._schedule_for_date(subject,ws,local_date)
            items=projection.get('items') or []
            if not items:return ActionResult(Screen(('امروز' if offset==0 else 'فردا')+' برنامه‌ای ثبت نشده است.'))
            lines=[('برنامه امروز' if offset==0 else 'برنامه فردا')]
            for item in items[:30]:
                start=str(item.get('starts_at') or '')[11:16]; title=str(item.get('title') or item.get('course_title') or 'رویداد'); location=str(item.get('location_text') or '')
                lines.append(f"• {start} — {title}"+(f" — {location}" if location else ''))
            return ActionResult(Screen('\n'.join(lines)))
        except Exception as exc:return self._error(exc)
    def grades(self,subject:str):
        try:
            _,ws,blocked=self._workspace_or_result(subject)
            if blocked:return blocked
            items=(self.backend.grades(self.platform,subject,ws,50,None).get('items') or [])
            if not items:return ActionResult(Screen('نمره منتشرشده‌ای پیدا نشد.'))
            lines=['نمرات من']
            for item in items[:30]:
                name=str(item.get('item_title') or item.get('gradebook_title') or item.get('course_title') or 'نمره'); score=item.get('score'); maximum=item.get('max_score')
                lines.append(f"• {name}: {score}"+(f" / {maximum}" if maximum is not None else ''))
            return ActionResult(Screen('\n'.join(lines)))
        except Exception as exc:return self._error(exc)
    def announcements(self,subject:str):
        try:
            _,ws,blocked=self._workspace_or_result(subject)
            if blocked:return blocked
            items=(self.backend.announcements(self.platform,subject,ws,20,None).get('items') or [])
            if not items:return ActionResult(Screen('اطلاعیه منتشرشده‌ای پیدا نشد.'))
            lines=['اطلاعیه‌ها']
            for item in items[:10]:
                title=str(item.get('title') or 'اطلاعیه'); body=str(item.get('body') or '').replace('\n',' ').strip(); lines.append(f"• {title}"+(f"\n  {body[:350]}" if body else ''))
            return ActionResult(Screen('\n'.join(lines)))
        except Exception as exc:return self._error(exc)
    def payments(self,subject:str):
        try: _,selected=self._selected(subject); return ActionResult(Screen('برای خرید، شناسه محصول را با دستور /buy <product_id> بفرستید.' if selected else 'ابتدا فضای فعال را انتخاب کنید.'))
        except Exception as exc: return self._error(exc)
    def create_order(self,subject:str,product_id:str,idempotency_key:str|None=None):
        try:
            _,ws=self._selected(subject)
            if not ws: return ActionResult(Screen('ابتدا فضای فعال را انتخاب کنید.'))
            idem=idempotency_key or ('bot-order-'+secrets.token_hex(12))
            if not SAFE_IDEM.fullmatch(idem): return ActionResult(Screen('شناسه درخواست خرید معتبر نیست.'))
            order=self.backend.create_order(self.platform,subject,ws,product_id,idem)
            text=f"سفارش: {order.get('title','')}\nمبلغ: {order.get('amount_minor','')} {order.get('currency','')}\nوضعیت: {order.get('status','')}"
            rows=(); url=order.get('payment_url')
            if isinstance(url,str) and url.startswith('https://'): rows=((Button('پرداخت',url=url),),)
            return ActionResult(Screen(text,rows))
        except Exception as exc: return self._error(exc)
    def order_status(self,subject:str,order_id:str):
        try:
            _,ws=self._selected(subject)
            if not ws:return ActionResult(Screen('ابتدا فضای فعال را انتخاب کنید.'))
            order=self.backend.order_status(self.platform,subject,ws,order_id); ent=order.get('entitlement') or {}
            return ActionResult(Screen(f"وضعیت سفارش: {order.get('status','')}\nدسترسی: {'فعال' if ent.get('granted') else 'هنوز فعال نشده'}"))
        except Exception as exc:return self._error(exc)
    def resources(self,subject:str):
        try:
            _,ws,blocked=self._workspace_or_result(subject)
            if blocked:return blocked
            items=(self.backend.resources(self.platform,subject,ws,20,None).get('items') or [])
            if not items:return ActionResult(Screen('منبع قابل‌دسترسی‌ای پیدا نشد.'))
            rows=[]; lines=['منابع قابل‌دسترسی']
            for item in items[:20]:
                rid=str(item.get('resource_id') or ''); title=str(item.get('title') or 'منبع')
                lines.append('• '+title)
                if UUID_RE.fullmatch(rid) and item.get('delivery_supported'):rows.append((Button('دریافت '+title[:32],CallbackCodec.encode('resource',rid)),))
            return ActionResult(Screen('\n'.join(lines),tuple(rows)))
        except Exception as exc:return self._error(exc)
    def _failed_receipt(self,workspace_id:str,issuance_id:str,code:str):
        try:self.backend.delivery_receipt(self.platform,workspace_id,issuance_id,'bot-delivery-'+secrets.token_hex(12),'failed',None,code)
        except Exception:pass
    def protected_resource(self,subject:str,resource_id:str):
        try:
            _,ws=self._selected(subject)
            if not ws:return ActionResult(Screen('ابتدا فضای فعال را انتخاب کنید.'))
            issued=self.backend.delivery_issue(self.platform,subject,ws,resource_id)
            consumed=self.backend.delivery_consume(self.platform,subject,ws,issued['delivery_token'])
            issuance=str(consumed.get('issuance_id') or issued.get('issuance_id') or '')
            forward=bool(consumed.get('forward_protection_required'))
            if forward and self.platform=='bale':
                self._failed_receipt(ws,issuance,'unsupported_forward_protection')
                return ActionResult(Screen('این فایل نیازمند محدودیت بازنشر است و ارسال مستقیم آن در بله فعال نیست.'))
            if consumed.get('object_id'):
                enqueued=self.backend.media_enqueue(ws,issuance,self.config.protected_renderer_version,{})
                job_id=str(enqueued.get('job_id') or '')
                if not UUID_RE.fullmatch(job_id):raise RuntimeError('protected media job id missing')
                self.media_state.remember(job_id,subject,ws,issuance)
                return ActionResult(Screen('نسخه شخصی‌سازی‌شده در حال آماده‌سازی است. پس از چند لحظه دریافت را بزنید.',((Button('دریافت نسخه محافظت‌شده',CallbackCodec.encode('pm',job_id)),),)))
            content=consumed.get('content'); text='محتوای محافظت‌شده آماده شد.' if content is None else str(content)
            receipt=DeliveryReceiptContext(ws,issuance,'bot-delivery-'+secrets.token_hex(12))
            return ActionResult(Screen(text,protect_content=forward),receipt)
        except Exception as exc:return self._error(exc)
    def protected_media_ready(self,subject:str,job_id:str):
        correlation=self.media_state.get(job_id,subject)
        if not correlation:return ActionResult(Screen('درخواست فایل منقضی یا متعلق به این حساب نیست.'))
        try:
            _,selected=self._selected(subject)
            if selected!=correlation['workspace_id']:return ActionResult(Screen('برای دریافت این فایل، همان فضای آموزشی را فعال کنید.'))
            try:issued=self.backend.media_derivative_issue(self.platform,subject,selected,job_id)
            except FanoosApiError as exc:
                if exc.code in {'protected_media_artifact_unavailable','protected_media_job_not_found'} and exc.status in {404,409}:
                    return ActionResult(Screen('نسخه هنوز آماده نشده است.',((Button('تلاش دوباره',CallbackCodec.encode('pm',job_id)),),)))
                raise
            size=int(issued.get('size') or 0); max_bytes=50*1024*1024
            if size<5 or size>max_bytes:
                self._failed_receipt(selected,correlation['issuance_id'],'file_too_large'); self.media_state.forget(job_id,subject)
                return ActionResult(Screen('نسخه آماده شد اما اندازه آن برای ارسال مستقیم در پیام‌رسان مناسب نیست.'))
            data=self.backend.media_derivative_redeem(self.platform,subject,selected,str(issued.get('artifact_capability') or ''),max_bytes)
            receipt=DeliveryReceiptContext(selected,correlation['issuance_id'],'bot-delivery-media:'+job_id)
            document=DocumentPayload(data,'fanoos-protected.pdf','نسخه شخصی‌سازی‌شده فانوس',True)
            return ActionResult(Screen('نسخه شخصی‌سازی‌شده فانوس',protect_content=True),receipt,document,{'media_job_id':job_id})
        except Exception as exc:return self._error(exc)
    def update_begin(self,subject:str,private:bool):
        if self.platform!='telegram':return ActionResult(Screen('به‌روزرسانی سرور از این کانال فعال نیست.'))
        if not private:return ActionResult(Screen('این عملیات فقط در گفت‌وگوی خصوصی تلگرام انجام می‌شود.'))
        if not self.config.deployment_target_key:return ActionResult(Screen('هدف به‌روزرسانی روی این runtime تنظیم نشده است.'))
        try:
            overview=self.backend.deployment_overview(subject,self.config.deployment_target_key)
            if not overview.get('can_manage_deployments'):return ActionResult(Screen('اجازه مدیریت به‌روزرسانی سرور را ندارید.'))
            current=str(overview.get('current_release_sha') or ''); candidate=str(overview.get('candidate_sha') or ''); available=overview.get('update_available'); health=overview.get('health') or {}
            summary='Update Server\n'
            if re.fullmatch(r'[0-9a-f]{40}',current):summary+=f'نسخه فعلی: {current[:12]}\n'
            if re.fullmatch(r'[0-9a-f]{40}',candidate):summary+=f'نسخه main: {candidate[:12]}\n'
            summary+=('به‌روزرسانی موجود است.' if available is True else 'نسخه جدیدی مشاهده نشده است.' if available is False else 'وضعیت نسخه هنوز قطعی نیست.')
            summary+=f"\nسلامت: {health.get('status','unknown')}"
            c=self.state.create_confirmation(subject,self.config.deployment_target_key)
            return ActionResult(Screen(summary+'\n\n⚠️ اجرای به‌روزرسانی ممکن است سرویس‌ها را restart کند.',((Button('تأیید Update Server',CallbackCodec.encode('upd_c',c['ref'])),Button('لغو',CallbackCodec.encode('upd_x',c['ref']))),)))
        except Exception as exc:return self._error(exc)
    def update_confirm(self,subject:str,private:bool,ref:str):
        if self.platform!='telegram' or not private:return ActionResult(Screen('درخواست معتبر نیست.'))
        c=self.state.confirmation(ref,subject)
        if not c:return ActionResult(Screen('تأیید منقضی، لغوشده یا قبلاً استفاده شده است.'))
        try:
            result=self.backend.request_deployment(subject,c['target_key'],c['idempotency_key']); rid=str(result['request_id']); self.state.record_deployment(subject,rid); self.state.complete_confirmation(ref,subject)
            return self._deployment_screen(result)
        except Exception as exc:return self._error(exc)
    def update_cancel(self,subject:str,ref:str):self.state.cancel_confirmation(ref,subject); return ActionResult(Screen('درخواست به‌روزرسانی لغو شد.'))
    def update_status(self,subject:str,private:bool,request_id:str|None=None):
        if self.platform!='telegram' or not private:return ActionResult(Screen('این عملیات فقط در گفت‌وگوی خصوصی تلگرام فعال است.'))
        rid=request_id or self.state.latest_deployment(subject)
        if not rid:return ActionResult(Screen('درخواست به‌روزرسانی ثبت‌شده‌ای پیدا نشد.'))
        try:return self._deployment_screen(self.backend.deployment_status(subject,rid))
        except Exception as exc:return self._error(exc)
    def _deployment_screen(self,d:dict):
        state=str(d.get('state') or 'UNKNOWN'); text=f'Update Server\nوضعیت: {state}\nشناسه: {d.get("request_id","")}'
        sha=d.get('candidate_sha')
        if isinstance(sha,str) and re.fullmatch(r'[0-9a-f]{40}',sha):text+=f'\nنسخه: {sha[:12]}'
        if d.get('failure_code'):text+=f'\nکد خطا: {d["failure_code"]}'
        rid=str(d.get('request_id') or ''); rows=((Button('تازه‌سازی وضعیت',CallbackCodec.encode('upd_s',rid)),),) if UUID_RE.fullmatch(rid) else ()
        return ActionResult(Screen(text,rows,edit=True))
    def callback(self,subject:str,private:bool,value:str):
        try:action,ref=CallbackCodec.decode(value)
        except Exception:return ActionResult(Screen('دکمه معتبر نیست.'))
        if action=='home':return self.home(subject)
        if action=='account':return self.account(subject)
        if action=='unlink':return self.unlink(subject)
        if action=='workspaces':return self.workspaces(subject)
        if action=='ws' and ref:return self.select_workspace(subject,ref)
        if action=='today':return self.day_schedule(subject,0)
        if action=='tomorrow':return self.day_schedule(subject,1)
        if action=='grades':return self.grades(subject)
        if action=='announcements':return self.announcements(subject)
        if action=='payments':return self.payments(subject)
        if action=='resources':return self.resources(subject)
        if action=='resource' and ref:return self.protected_resource(subject,ref)
        if action=='pm' and ref:return self.protected_media_ready(subject,ref)
        if action=='upd_c' and ref:return self.update_confirm(subject,private,ref)
        if action=='upd_x' and ref:return self.update_cancel(subject,ref)
        if action=='upd_s' and ref:return self.update_status(subject,private,ref)
        return ActionResult(Screen('این دکمه دیگر معتبر نیست.'))
    @staticmethod
    def help():
        return ActionResult(Screen('/start — شروع\n/link <code> — اتصال حساب\n/unlink — قطع اتصال همین پیام‌رسان\n/workspaces — انتخاب فضای آموزشی\n/today — برنامه امروز\n/tomorrow — برنامه فردا\n/grades — نمرات من\n/announcements — اطلاعیه‌ها\n/resources — منابع قابل‌دسترسی\n/buy <product_id> — ساخت سفارش\n/order <order_id> — وضعیت سفارش\n/resource <resource_id> — دریافت محافظت‌شده\n/help — راهنما'))
