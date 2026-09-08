from __future__ import annotations
import re
import secrets
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo
from .api import FanoosApiError
from .callbacks import CallbackCodec
from .formatting import format_datetime, format_human_number, format_money, format_score, format_time, humanize_slug, short_sha, truncate_text
from .localization import deployment_state_label, entitlement_label, error_message, health_status_label, order_status_label, platform_label, resource_type_label
from .media_state import ProtectedMediaLocalState
from .models import ActionResult, Button, DeliveryReceiptContext, DocumentPayload, Screen, SemanticSection
from .presentation import error_screen, semantic_screen, success_screen, warning_screen
UUID_RE = re.compile('^[0-9a-f-]{36}$', re.I)
START_RE = re.compile('^[A-Za-z0-9_-]{1,64}$')
SAFE_IDEM = re.compile('^[A-Za-z0-9._:-]{1,160}$')

@dataclass(frozen=True)
class ApplicationConfig:
    web_base_url: str = ''
    deployment_target_key: str = ''
    protected_renderer_version: str = 'fanoos-raster-v1'

class BotApplication:

    def __init__(self, backend, state, platform: str, config: ApplicationConfig=ApplicationConfig()):
        self.backend = backend
        self.state = state
        self.platform = platform
        self.config = config
        self.media_state = ProtectedMediaLocalState(state)

    def _error(self, exc: Exception):
        if isinstance(exc, FanoosApiError):
            return ActionResult(error_screen(error_message(exc.code, exc.status)))
        return ActionResult(error_screen('سرویس موقتاً پاسخ نمی‌دهد. دوباره امتحان کنید.'))

    @staticmethod
    def _workspace_label(workspace: dict) -> str:
        for key in ('name', 'title', 'label'):
            value = ' '.join(str(workspace.get(key) or '').split())
            if value:
                return truncate_text(value, 70)
        slug = humanize_slug(workspace.get('slug'))
        return truncate_text(slug, 70) if slug else 'فضای آموزشی'

    def _selected_workspace_label(self, projection: dict, selected: str | None) -> str:
        if not selected:
            return 'انتخاب نشده'
        for workspace in projection.get('workspaces') or []:
            if str(workspace.get('id') or '') == selected:
                return self._workspace_label(workspace)
        return 'فضای انتخاب‌شده'

    def start(self, subject: str, payload: str | None=None):
        if payload:
            if self.platform != 'telegram' or not START_RE.fullmatch(payload):
                return ActionResult(error_screen('درخواست اتصال نامعتبر است. از فانوس یک کد اتصال تازه بگیرید.', kind='link_error'))
            return self.link(subject, payload)
        return self.home(subject)

    def link(self, subject: str, token: str):
        if not token or len(token) > 128 or not re.fullmatch('[A-Za-z0-9_-]+', token):
            return ActionResult(error_screen('کد اتصال معتبر نیست. از فانوس یک کد اتصال تازه بگیرید.', kind='link_error'))
        try:
            self.backend.consume_link(self.platform, subject, token)
            return ActionResult(success_screen(f'این {platform_label(self.platform)} به حساب فانوس شما متصل شد.', title='✅ اتصال انجام شد', kind='link_success', rows=((Button('ادامه', CallbackCodec.encode('home')),),)))
        except Exception as exc:
            return self._error(exc)

    def unlink(self, subject: str):
        try:
            self.backend.unlink(self.platform, subject)
            return ActionResult(success_screen(f'اتصال {platform_label(self.platform)} قطع شد. حساب اصلی فانوس شما حذف نشده است.', title='✅ اتصال قطع شد', kind='unlink_success'))
        except Exception as exc:
            return self._error(exc)

    def _workspace_projection(self, subject: str):
        return self.backend.workspaces(self.platform, subject)

    def _selected(self, subject: str):
        projection = self._workspace_projection(subject)
        workspaces = projection.get('workspaces') or []
        selected = projection.get('selected_workspace_id')
        if not selected and len(workspaces) == 1:
            self.backend.select_workspace(self.platform, subject, workspaces[0]['id'])
            selected = workspaces[0]['id']
        return (projection, selected)

    def _workspace_or_result(self, subject: str):
        projection, selected = self._selected(subject)
        if not selected:
            return (projection, None, ActionResult(warning_screen('ابتدا یک فضای آموزشی فعال انتخاب کنید.', title='🏫 فضای آموزشی', kind='workspace_required')))
        return (projection, selected, None)

    def home(self, subject: str, notice: str=''):
        try:
            projection, selected = self._selected(subject)
            workspaces = projection.get('workspaces') or []
            if not workspaces:
                return ActionResult(semantic_screen('🏠 فانوس', 'home_empty', severity='warning', intro='حساب شما متصل است، اما فضای آموزشی فعالی برای این حساب وجود ندارد.'))
            rows = ((Button('📅 امروز', CallbackCodec.encode('today')), Button('📅 فردا', CallbackCodec.encode('tomorrow'))), (Button('🎓 نمرات من', CallbackCodec.encode('grades')), Button('📢 اطلاعیه‌ها', CallbackCodec.encode('ann'))), (Button('📚 منابع', CallbackCodec.encode('resources')), Button('💳 خرید و دسترسی', CallbackCodec.encode('payments'))), (Button('🏫 فضای آموزشی', CallbackCodec.encode('workspaces')), Button('👤 حساب', CallbackCodec.encode('account'))))
            if self.config.web_base_url:
                rows += ((Button('🌐 باز کردن فانوس', url=self.config.web_base_url),),)
            return ActionResult(semantic_screen('🏠 فانوس', 'home', intro='دسترسی سریع', facts=(('فضای فعال', self._selected_workspace_label(projection, selected)),), footer=f'✅ {notice}' if notice else '', rows=rows))
        except Exception as exc:
            return self._error(exc)

    def account(self, subject: str):
        try:
            projection, selected = self._selected(subject)
            return ActionResult(semantic_screen('👤 حساب', 'account', facts=(('پیام‌رسان', platform_label(self.platform)), ('وضعیت اتصال', 'متصل'), ('تعداد فضای آموزشی', format_human_number(len(projection.get('workspaces') or []))), ('فضای فعال', self._selected_workspace_label(projection, selected))), footer='قطع اتصال فقط همین پیام‌رسان را از حساب فانوس جدا می‌کند؛ حساب اصلی حذف نمی‌شود.', rows=((Button('قطع اتصال پیام‌رسان', CallbackCodec.encode('unlink')),),)))
        except Exception as exc:
            return self._error(exc)

    def workspaces(self, subject: str):
        try:
            projection = self._workspace_projection(subject)
            workspaces = projection.get('workspaces') or []
            selected = projection.get('selected_workspace_id')
            if not workspaces:
                return ActionResult(semantic_screen('🏫 فضای آموزشی', 'workspace_empty', severity='warning', intro='فضای آموزشی قابل‌دسترسی برای این حساب پیدا نشد.'))
            rows = []
            items = []
            for workspace in workspaces:
                wid = str(workspace.get('id') or '')
                if not UUID_RE.fullmatch(wid):
                    continue
                label = self._workspace_label(workspace)
                marker = '✓ ' if wid == selected else ''
                items.append(marker + label)
                rows.append((Button(marker + label, CallbackCodec.encode('ws', wid)),))
            if not rows:
                return ActionResult(semantic_screen('🏫 فضای آموزشی', 'workspace_empty', severity='warning', intro='فضای آموزشی قابل انتخابی پیدا نشد.'))
            return ActionResult(semantic_screen('🏫 فضای آموزشی', 'workspace_list', intro='فضای آموزشی فعال را انتخاب کنید.', list_items=items, rows=tuple(rows)))
        except Exception as exc:
            return self._error(exc)

    def select_workspace(self, subject: str, workspace_id: str):
        try:
            self.backend.select_workspace(self.platform, subject, workspace_id)
            return self.home(subject, 'فضای آموزشی فعال تغییر کرد.')
        except Exception as exc:
            return self._error(exc)

    def _schedule_for_date(self, subject: str, workspace_id: str, target_date):
        value = target_date.isoformat()
        projection = self.backend.schedule(self.platform, subject, workspace_id, value, value, 100, None)
        timezone_name = str(projection.get('timezone') or 'UTC')
        return (projection, timezone_name)

    def day_schedule(self, subject: str, offset: int):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            now_utc = datetime.now(timezone.utc)
            guessed = now_utc.date() + timedelta(days=offset)
            projection, timezone_name = self._schedule_for_date(subject, workspace_id, guessed)
            try:
                local_date = now_utc.astimezone(ZoneInfo(timezone_name)).date() + timedelta(days=offset)
            except Exception:
                local_date = guessed
            if local_date != guessed:
                projection, timezone_name = self._schedule_for_date(subject, workspace_id, local_date)
            items = projection.get('items') or []
            day_label = 'امروز' if offset == 0 else 'فردا'
            title = f'📅 برنامه {day_label}'
            if not items:
                return ActionResult(semantic_screen(title, 'schedule', intro=f'برای {day_label} برنامه‌ای ثبت نشده است.'))
            sections = []
            for item in items[:30]:
                start = format_time(item.get('starts_at'), timezone_name)
                item_title = truncate_text(item.get('title') or item.get('course_title') or 'رویداد', 100)
                location = truncate_text(item.get('location_text') or '', 100)
                facts = (f'زمان: {start}',) + ((f'مکان: {location}',) if location else ())
                sections.append(SemanticSection(title=item_title, items=facts))
            return ActionResult(semantic_screen(title, 'schedule', sections=sections))
        except Exception as exc:
            return self._error(exc)

    def grades(self, subject: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            items = self.backend.grades(self.platform, subject, workspace_id, 50, None).get('items') or []
            if not items:
                return ActionResult(semantic_screen('🎓 نمرات من', 'grades', intro='نمره منتشرشده‌ای برای شما پیدا نشد.'))
            display_items = []
            for item in items[:30]:
                name = truncate_text(item.get('item_title') or item.get('gradebook_title') or item.get('course_title') or 'نمره', 100)
                score = format_score(item.get('score'))
                maximum = item.get('max_score')
                if maximum is not None:
                    display_items.append(f'{name}: {score} از {format_score(maximum)}')
                else:
                    display_items.append(f'{name}: {score}')
            return ActionResult(semantic_screen('🎓 نمرات من', 'grades', list_items=display_items))
        except Exception as exc:
            return self._error(exc)

    def announcements(self, subject: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            items = self.backend.announcements(self.platform, subject, workspace_id, 20, None).get('items') or []
            if not items:
                return ActionResult(semantic_screen('📢 اطلاعیه‌ها', 'announcements', intro='اطلاعیه منتشرشده‌ای پیدا نشد.'))
            sections = []
            for item in items[:10]:
                title = truncate_text(item.get('title') or 'اطلاعیه', 120)
                body = truncate_text(str(item.get('body') or '').strip(), 700)
                published = item.get('published_at') or item.get('publishedAt') or item.get('effective_at') or item.get('effectiveAt') or item.get('created_at') or item.get('createdAt')
                time_text = format_datetime(published)
                section_items = (f'زمان: {time_text}',) if time_text else ()
                sections.append(SemanticSection(title=title, body=body, items=section_items))
            return ActionResult(semantic_screen('📢 اطلاعیه‌ها', 'announcements', sections=sections))
        except Exception as exc:
            return self._error(exc)

    def payments(self, subject: str):
        try:
            _, selected = self._selected(subject)
            if not selected:
                return ActionResult(warning_screen('ابتدا یک فضای آموزشی فعال انتخاب کنید.', title='💳 خرید و دسترسی', kind='payments'))
            rows = ((Button('🌐 باز کردن فانوس', url=self.config.web_base_url),),) if self.config.web_base_url else ()
            return ActionResult(semantic_screen('💳 خرید و دسترسی', 'payments', intro='برای ساخت سفارش، شناسه محصول را بعد از دستور /buy بفرستید.', footer='وضعیت پرداخت و دسترسی فقط از اطلاعات تأییدشده فانوس نمایش داده می‌شود.', rows=rows))
        except Exception as exc:
            return self._error(exc)

    def create_order(self, subject: str, product_id: str, idempotency_key: str | None=None):
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return ActionResult(warning_screen('ابتدا یک فضای آموزشی فعال انتخاب کنید.', title='💳 خرید و دسترسی', kind='payment_order'))
            if not product_id:
                return ActionResult(warning_screen('شناسه محصول را بعد از دستور /buy بفرستید.', title='💳 خرید و دسترسی', kind='payment_order'))
            idem = idempotency_key or 'bot-order-' + secrets.token_hex(12)
            if not SAFE_IDEM.fullmatch(idem):
                return ActionResult(error_screen('درخواست خرید معتبر نیست.', kind='payment_order'))
            order = self.backend.create_order(self.platform, subject, workspace_id, product_id, idem)
            status = order_status_label(order.get('status'))
            title = truncate_text(order.get('title') or 'سفارش فانوس', 100)
            rows = ()
            url = order.get('payment_url')
            if isinstance(url, str) and url.startswith('https://'):
                rows = ((Button('ادامه برای پرداخت', url=url),),)
            return ActionResult(semantic_screen('💳 سفارش', 'payment_order', facts=(('عنوان', title), ('مبلغ', format_money(order.get('amount_minor'), order.get('currency'))), ('وضعیت', status)), rows=rows))
        except Exception as exc:
            return self._error(exc)

    def order_status(self, subject: str, order_id: str):
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return ActionResult(warning_screen('ابتدا یک فضای آموزشی فعال انتخاب کنید.', title='💳 خرید و دسترسی', kind='payment_status'))
            order = self.backend.order_status(self.platform, subject, workspace_id, order_id)
            entitlement = order.get('entitlement') or {}
            status = order_status_label(order.get('status'))
            severity = 'success' if str(order.get('status') or '').lower() in {'paid', 'succeeded'} else 'info'
            return ActionResult(semantic_screen('💳 وضعیت سفارش', 'payment_status', severity=severity, facts=(('وضعیت پرداخت', status), ('دسترسی', entitlement_label(entitlement.get('granted'))))))
        except Exception as exc:
            return self._error(exc)

    def resources(self, subject: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            items = self.backend.resources(self.platform, subject, workspace_id, 20, None).get('items') or []
            if not items:
                return ActionResult(semantic_screen('📚 منابع', 'resources', intro='منبع قابل‌دسترسی‌ای پیدا نشد.'))
            rows = []
            display_items = []
            for item in items[:20]:
                resource_id = str(item.get('resource_id') or '')
                title = truncate_text(item.get('title') or 'منبع', 100)
                raw_type = item.get('resource_type') or item.get('type') or item.get('kind')
                type_label = resource_type_label(raw_type) if raw_type else ''
                display_items.append(f'{title} · {type_label}' if type_label else title)
                if UUID_RE.fullmatch(resource_id) and item.get('delivery_supported'):
                    rows.append((Button('دریافت ' + truncate_text(title, 28), CallbackCodec.encode('resource', resource_id)),))
            return ActionResult(semantic_screen('📚 منابع', 'resources', list_items=display_items, rows=tuple(rows)))
        except Exception as exc:
            return self._error(exc)

    def _failed_receipt(self, workspace_id: str, issuance_id: str, code: str):
        try:
            self.backend.delivery_receipt(self.platform, workspace_id, issuance_id, 'bot-delivery-' + secrets.token_hex(12), 'failed', None, code)
        except Exception:
            pass

    @staticmethod
    def _protected_text(content: object) -> str:
        if isinstance(content, str):
            return content
        if isinstance(content, dict):
            for key in ('text', 'body', 'content'):
                value = content.get(key)
                if isinstance(value, str) and value.strip():
                    return value
        return '✅ محتوای محافظت‌شده آماده است.'

    def protected_resource(self, subject: str, resource_id: str):
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return ActionResult(warning_screen('ابتدا یک فضای آموزشی فعال انتخاب کنید.', title='🔒 محتوای محافظت‌شده', kind='protected_delivery'))
            issued = self.backend.delivery_issue(self.platform, subject, workspace_id, resource_id)
            consumed = self.backend.delivery_consume(self.platform, subject, workspace_id, issued['delivery_token'])
            issuance = str(consumed.get('issuance_id') or issued.get('issuance_id') or '')
            forward = bool(consumed.get('forward_protection_required'))
            if forward and self.platform == 'bale':
                self._failed_receipt(workspace_id, issuance, 'unsupported_forward_protection')
                return ActionResult(warning_screen('این منبع باید با محدودیت بازنشر ارسال شود، اما این قابلیت در رابط رسمی بله در دسترس نیست. ارسال انجام نشد.', title='🔒 ارسال محافظت‌شده در بله', kind='protected_delivery_unsupported'))
            if consumed.get('object_id'):
                enqueued = self.backend.media_enqueue(workspace_id, issuance, self.config.protected_renderer_version, {})
                job_id = str(enqueued.get('job_id') or '')
                if not UUID_RE.fullmatch(job_id):
                    raise RuntimeError('protected media job id missing')
                self.media_state.remember(job_id, subject, workspace_id, issuance)
                return ActionResult(semantic_screen('🔒 در حال آماده‌سازی نسخه محافظت‌شده', 'protected_delivery_preparing', intro='درخواست ثبت شد. برای بررسی آماده‌شدن نسخه، دکمه زیر را بزنید.', rows=((Button('🔄 بررسی آماده‌شدن', CallbackCodec.encode('pm', job_id)),),)))
            text = self._protected_text(consumed.get('content'))
            receipt = DeliveryReceiptContext(workspace_id, issuance, 'bot-delivery-' + secrets.token_hex(12))
            return ActionResult(semantic_screen('🔒 محتوای محافظت‌شده', 'protected_delivery_ready', severity='success', intro='محتوا آماده است.', protect_content=forward, text=text), receipt)
        except Exception as exc:
            return self._error(exc)

    def protected_media_ready(self, subject: str, job_id: str):
        correlation = self.media_state.get(job_id, subject)
        if not correlation:
            return ActionResult(warning_screen('درخواست فایل منقضی شده یا برای این حساب نیست. دوباره از بخش منابع شروع کنید.', title='🔒 نسخه محافظت‌شده', kind='protected_delivery_expired'))
        try:
            _, selected = self._selected(subject)
            if selected != correlation['workspace_id']:
                return ActionResult(warning_screen('برای دریافت این فایل همان فضای آموزشی قبلی را فعال کنید.', title='🏫 فضای آموزشی نادرست', kind='protected_delivery_workspace'))
            try:
                issued = self.backend.media_derivative_issue(self.platform, subject, selected, job_id)
            except FanoosApiError as exc:
                if exc.code in {'protected_media_artifact_unavailable', 'protected_media_job_not_found'} and exc.status in {404, 409}:
                    return ActionResult(semantic_screen('🔒 نسخه هنوز آماده نشده است', 'protected_delivery_preparing', intro='هنوز نسخه قابل دریافت نیست.', rows=((Button('🔄 بررسی دوباره', CallbackCodec.encode('pm', job_id)),),)))
                raise
            size = int(issued.get('size') or 0)
            max_bytes = 50 * 1024 * 1024
            if size < 5 or size > max_bytes:
                self._failed_receipt(selected, correlation['issuance_id'], 'file_too_large')
                self.media_state.forget(job_id, subject)
                return ActionResult(warning_screen('نسخه آماده است، اما اندازه فایل از سقف ارسال مستقیم این پیام‌رسان بیشتر است.', title='⚠️ ارسال فایل ممکن نیست', kind='protected_delivery_size'))
            data = self.backend.media_derivative_redeem(self.platform, subject, selected, str(issued.get('artifact_capability') or ''), max_bytes)
            receipt = DeliveryReceiptContext(selected, correlation['issuance_id'], 'bot-delivery-media:' + job_id)
            document = DocumentPayload(data, 'fanoos-protected.pdf', '🔒 نسخه محافظت‌شده فانوس', True)
            return ActionResult(semantic_screen('✅ نسخه محافظت‌شده آماده است', 'protected_delivery_ready', severity='success', protect_content=True), receipt, document, {'media_job_id': job_id})
        except Exception as exc:
            return self._error(exc)

    def update_begin(self, subject: str, private: bool):
        if self.platform != 'telegram':
            return ActionResult(warning_screen('به‌روزرسانی سرور از این پیام‌رسان فعال نیست.', title='⚙️ به‌روزرسانی سرور', kind='deployment'))
        if not private:
            return ActionResult(warning_screen('این عملیات فقط در گفت‌وگوی خصوصی تلگرام انجام می‌شود.', title='⚙️ به‌روزرسانی سرور', kind='deployment'))
        if not self.config.deployment_target_key:
            return ActionResult(error_screen('هدف به‌روزرسانی برای این محیط تنظیم نشده است.', title='⚙️ به‌روزرسانی سرور', kind='deployment'))
        try:
            overview = self.backend.deployment_overview(subject, self.config.deployment_target_key)
            if not overview.get('can_manage_deployments'):
                return ActionResult(error_screen('اجازه مدیریت به‌روزرسانی سرور را ندارید.', title='⚙️ به‌روزرسانی سرور', kind='deployment'))
            current = str(overview.get('current_release_sha') or '')
            candidate = str(overview.get('candidate_sha') or '')
            available = overview.get('update_available')
            health = overview.get('health') or {}
            if available is True:
                intro = 'نسخه جدید موجود است.'
            elif available is False:
                intro = 'نسخه جدیدی مشاهده نشده است.'
            else:
                intro = 'وضعیت نسخه جدید هنوز قطعی نیست.'
            facts = [('وضعیت فعلی', health_status_label(health.get('status')))]
            current_short = short_sha(current)
            candidate_short = short_sha(candidate)
            if current_short:
                facts.append(('نسخه فعلی (SHA)', current_short))
            if candidate_short:
                facts.append(('نسخه جدید (SHA)', candidate_short))
            confirmation = self.state.create_confirmation(subject, self.config.deployment_target_key)
            return ActionResult(semantic_screen('⚙️ به‌روزرسانی سرور', 'deployment_confirmation', severity='warning', intro=intro, facts=facts, footer='⚠️ اجرای به‌روزرسانی ممکن است سرویس‌ها را راه‌اندازی مجدد کند.', rows=((Button('✅ تأیید به‌روزرسانی', CallbackCodec.encode('upd_c', confirmation['ref'])), Button('لغو', CallbackCodec.encode('upd_x', confirmation['ref']))),)))
        except Exception as exc:
            return self._error(exc)

    def update_confirm(self, subject: str, private: bool, ref: str):
        if self.platform != 'telegram' or not private:
            return ActionResult(error_screen('درخواست معتبر نیست.', kind='deployment'))
        confirmation = self.state.confirmation(ref, subject)
        if not confirmation:
            return ActionResult(warning_screen('تأیید منقضی، لغوشده یا قبلاً استفاده شده است.', title='⚙️ به‌روزرسانی سرور', kind='deployment'))
        try:
            result = self.backend.request_deployment(subject, confirmation['target_key'], confirmation['idempotency_key'])
            request_id = str(result['request_id'])
            self.state.record_deployment(subject, request_id)
            self.state.complete_confirmation(ref, subject)
            return self._deployment_screen(result)
        except Exception as exc:
            return self._error(exc)

    def update_cancel(self, subject: str, ref: str):
        self.state.cancel_confirmation(ref, subject)
        return ActionResult(semantic_screen('⚙️ به‌روزرسانی سرور', 'deployment_cancelled', intro='درخواست به‌روزرسانی لغو شد.'))

    def update_status(self, subject: str, private: bool, request_id: str | None=None):
        if self.platform != 'telegram' or not private:
            return ActionResult(warning_screen('این عملیات فقط در گفت‌وگوی خصوصی تلگرام فعال است.', title='⚙️ به‌روزرسانی سرور', kind='deployment'))
        request_id = request_id or self.state.latest_deployment(subject)
        if not request_id:
            return ActionResult(semantic_screen('⚙️ به‌روزرسانی سرور', 'deployment_empty', intro='درخواست به‌روزرسانی ثبت‌شده‌ای پیدا نشد.'))
        try:
            return self._deployment_screen(self.backend.deployment_status(subject, request_id))
        except Exception as exc:
            return self._error(exc)

    def _deployment_screen(self, deployment: dict):
        state = str(deployment.get('state') or 'UNKNOWN').upper()
        state_label = deployment_state_label(state)
        severity = 'success' if state == 'SUCCEEDED' else 'error' if state == 'FAILED' else 'warning' if state == 'ROLLED_BACK' else 'info'
        facts = [('وضعیت فعلی', state_label)]
        candidate = short_sha(deployment.get('candidate_sha'))
        if candidate:
            facts.append(('نسخه (SHA)', candidate))
        if deployment.get('failure_code'):
            facts.append(('نتیجه', 'به‌روزرسانی کامل نشد. جزئیات در گزارش مدیریتی سرور قابل بررسی است.'))
        request_id = str(deployment.get('request_id') or '')
        rows = ((Button('🔄 تازه‌سازی وضعیت', CallbackCodec.encode('upd_s', request_id)),),) if UUID_RE.fullmatch(request_id) else ()
        return ActionResult(semantic_screen('⚙️ به‌روزرسانی سرور', 'deployment_status', severity=severity, facts=facts, rows=rows, edit=True))

    def callback(self, subject: str, private: bool, value: str):
        try:
            action, ref = CallbackCodec.decode(value)
        except Exception:
            return ActionResult(warning_screen('این دکمه معتبر نیست. از منوی اصلی دوباره وارد بخش موردنظر شوید.', kind='callback_invalid'))
        if action == 'home':
            return self.home(subject)
        if action == 'account':
            return self.account(subject)
        if action == 'unlink':
            return self.unlink(subject)
        if action == 'workspaces':
            return self.workspaces(subject)
        if action == 'ws' and ref:
            return self.select_workspace(subject, ref)
        if action == 'today':
            return self.day_schedule(subject, 0)
        if action == 'tomorrow':
            return self.day_schedule(subject, 1)
        if action == 'grades':
            return self.grades(subject)
        if action == 'ann':
            return self.announcements(subject)
        if action == 'payments':
            return self.payments(subject)
        if action == 'resources':
            return self.resources(subject)
        if action == 'resource' and ref:
            return self.protected_resource(subject, ref)
        if action == 'pm' and ref:
            return self.protected_media_ready(subject, ref)
        if action == 'upd_c' and ref:
            return self.update_confirm(subject, private, ref)
        if action == 'upd_x' and ref:
            return self.update_cancel(subject, ref)
        if action == 'upd_s' and ref:
            return self.update_status(subject, private, ref)
        return ActionResult(warning_screen('این دکمه دیگر معتبر نیست. از منوی اصلی دوباره وارد بخش موردنظر شوید.', kind='callback_expired'))

    @staticmethod
    def help():
        return ActionResult(semantic_screen('ℹ️ راهنمای فانوس', 'help', list_items=('/start — شروع و خانه', '/link کد — اتصال این پیام‌رسان', '/unlink — قطع اتصال همین پیام‌رسان', '/workspaces — انتخاب فضای آموزشی', '/today — برنامه امروز', '/tomorrow — برنامه فردا', '/grades — نمرات من', '/announcements — اطلاعیه‌ها', '/resources — منابع', '/buy شناسه‌محصول — ساخت سفارش', '/order شناسه‌سفارش — وضعیت سفارش', '/resource شناسه‌منبع — دریافت محافظت‌شده', '/help — راهنما')))
