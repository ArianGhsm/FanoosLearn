from __future__ import annotations

import hashlib
import html
import re
import threading
import time
import logging
import csv
import tempfile
from datetime import datetime, timedelta, timezone
from pathlib import Path
from urllib.parse import urlsplit

from .api import BotApiError, TelegramBotApi
from .keyboard_invariant import KeyboardInvariantApi
from .state import BotState
from .payments import identity_from_account
from .site_api import SiteApiClient, SiteApiError
from .persian_datetime import format_jalali_datetime, to_persian_digits
from .subscriptions import (
    billing_period_for,
    parse_jalali_date,
    policy_is_effective,
    subscription_identity_from_account,
    utc_iso,
)
from .ui import (
    Screen,
    bot_start_url,
    button,
    keyboard,
    account_screen,
    exam_screen,
    grades_screen,
    home,
    identity_mapping_remove_confirmation,
    identity_mapping_remove_screen,
    notification_audience_screen,
    notification_detail_screen,
    notification_list_screen,
    navid_screen,
    owner_identity_mappings_screen,
    profile_edit_fields_screen,
    profile_edit_prompt_screen,
    profile_edit_requests_screen,
    owner_grade_screen,
    owner_payment_offers_screen,
    payment_control_center_screen,
    payment_offers_screen,
    payment_offer_saved_screen,
    payment_offer_wizard_screen,
    payment_offer_preview_screen,
    payment_offer_admin_detail_screen,
    payment_offer_delete_confirmation,
    payment_confirm_screen,
    payment_created_screen,
    payment_status_screen,
    payment_product_report_screen,
    payment_transactions_screen,
    payment_transaction_filters_screen,
    payment_transaction_detail_screen,
    payment_people_screen,
    complimentary_access_list_screen,
    complimentary_search_results_screen,
    term_subscription_admin_screen,
    term_access_policies_screen,
    term_subscription_info_screen,
    term_subscription_screen,
    term_subscription_settings_screen,
    format_rials,
    required_channel_membership_screen,
    student_assistant_screen,
    integration_challenge_waiting_screen,
    section,
)
from .navid import local_now, send_daily_challenge
from .student_assistant import challenge_expired, clean_captcha_answer, send_private_challenge
from .booklets import (
    BOOKLET_BACK,
    BOOKLET_CANCEL,
    BOOKLET_HOME,
    COURSE_BY_CODE,
    RESOURCE_LABELS,
    bale_unavailable_screen,
    course_from_button,
    courses_screen as booklet_courses_screen,
    resources_screen as booklet_resources_screen,
    session_from_button,
    sessions_screen as booklet_sessions_screen,
    source_records_from_channel_post,
)
from .onboarding import (
    BACK_STEP,
    CANCEL,
    CLASS_OTP,
    CLASS_SITE,
    CHANGE_PHONE,
    CONFIRM_PROFILE,
    NEXT_PAGE,
    PREVIOUS_PAGE,
    RESEND_OTP,
    RESTART_PROFILE,
    SKIP_STUDENT_NUMBER,
    START_CLASS,
    START_GENERIC,
    gateway_screen,
    class_auth_screen,
    class_student_number_screen,
    class_otp_screen,
    prompt_screen as onboarding_prompt_screen,
    success_screen as onboarding_success_screen,
)


class DentBotApp:
    def __init__(
        self,
        api: TelegramBotApi,
        state: BotState,
        *,
        owner_id: int,
        site_url: str,
        site_api: SiteApiClient | None = None,
        platform: str = "telegram",
        bot_username: str = "Dent1402Bot",
        exams_v1_enabled: bool = False,
        payment_return_v1_enabled: bool = False,
        student_assistant_v1_enabled: bool = False,
        required_channel_username: str = "",
        booklet_source_channel_id: int = 0,
        media_dispatcher=None,
    ) -> None:
        self.api = KeyboardInvariantApi(api, state)
        self.state = state
        self.owner_id = owner_id
        self.site_url = site_url
        self.site_api = site_api
        self.platform = platform
        self.bot_username = bot_username
        self.exams_v1_enabled = exams_v1_enabled
        self.payment_return_v1_enabled = payment_return_v1_enabled
        self.student_assistant_v1_enabled = student_assistant_v1_enabled
        self.required_channel_username = required_channel_username.strip().lstrip("@")
        self.booklet_source_channel_id = int(booklet_source_channel_id)
        self.media_dispatcher = media_dispatcher
        self._interaction_lock = threading.Lock()
        self._interaction_versions: dict[int, int] = {}
        # Telegram/Bale may deliver two taps from one user close together while
        # different executor threads are active. Fixed stripes keep each user's
        # state transitions ordered without an unbounded per-user lock map.
        self._update_locks = tuple(threading.RLock() for _ in range(64))
        self._onboarding_catalog_cache: dict = {}
        self._onboarding_catalog_cached_at = 0.0
        self._account_cache: dict[int, tuple[float, dict]] = {}
        self._product_state_cache: dict[int, tuple[float, tuple[str, ...], dict]] = {}
        self._payment_filters: dict[int, dict] = {}

    def _account_snapshot(self, user_id: int, *, refresh: bool = False) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "account"):
            raise SiteApiError("بررسی امن اتصال حساب در دسترس نیست.", code="SITE_UNAVAILABLE")
        now = time.monotonic()
        cached = self._account_cache.get(int(user_id))
        if not refresh and cached is not None and now - cached[0] < 15:
            return dict(cached[1])
        account = self.site_api.account(user_id)
        self._account_cache[int(user_id)] = (now, dict(account))
        return dict(account)

    def _eligible_products(self, user_id: int, *, via_link: bool = False) -> list[dict]:
        account = self._account_snapshot(user_id)
        identity = identity_from_account(account)
        offers = [
            item for item in self.state.eligible_payment_offers(identity, via_link=via_link)
            if not self._is_term_subscription_offer(item)
        ]
        if not offers or self.site_api is None or not hasattr(self.site_api, "payment_product_states"):
            return offers
        states = self._payment_product_states(user_id, offers)
        result: list[dict] = []
        for offer in offers:
            state = dict(states.get(str(offer.get("ref") or "")) or {})
            max_per_user = max(0, int(offer.get("maxPurchasesPerUser") or 0))
            if max_per_user and int(state.get("successCount") or 0) >= max_per_user:
                # A paid one-time product remains visible in the product page so
                # the student can retrieve the canonical receipt.
                result.append(offer)
                continue
            capacity = max(0, int(offer.get("capacity") or 0))
            if capacity and int(state.get("reservedCount") or 0) >= capacity:
                continue
            result.append(offer)
        return result

    @staticmethod
    def _is_term_subscription_offer(offer: dict | None) -> bool:
        fulfillment = dict((offer or {}).get("fulfillment") or {})
        return str(fulfillment.get("kind") or "") == "term_subscription"

    def _ordinary_payment_offers(self, *, include_inactive: bool = True) -> list[dict]:
        return [
            item for item in self.state.payment_offers(include_inactive=include_inactive)
            if not self._is_term_subscription_offer(item)
        ]

    def _ordinary_payment_offer(self, offer_ref: str) -> dict | None:
        item = self.state.payment_offer(offer_ref, require_active=False)
        return None if self._is_term_subscription_offer(item) else item

    def _payment_product_states(self, user_id: int, offers: list[dict]) -> dict:
        refs = tuple(sorted(str(item.get("ref") or "") for item in offers if item.get("ref")))
        if not refs or self.site_api is None or not hasattr(self.site_api, "payment_product_states"):
            return {}
        now = time.monotonic()
        cached = self._product_state_cache.get(int(user_id))
        if cached is not None and now - cached[0] < 12 and cached[1] == refs:
            return dict(cached[2])
        payload = self.site_api.payment_product_states(user_id, list(refs))
        states = dict(payload.get("states") or {})
        self._product_state_cache[int(user_id)] = (now, refs, states)
        return states

    def _activate_subscription_from_payment_status(
        self, user_id: int, order_token: str, payload: dict
    ) -> dict:
        """Use only a canonical verified website status; a browser return flag is never sufficient."""
        checkout = self.state.term_subscription_checkout_by_order(order_token)
        if checkout is None or str(payload.get("status") or "") != "success":
            return payload
        if (
            str(checkout.get("platform") or "") != self.platform
            or int(checkout.get("platformUserId") or 0) != int(user_id)
            or not str(payload.get("verifiedAt") or "").strip()
            or int(payload.get("amountRials") or 0) != int(checkout.get("amountRials") or -1)
        ):
            return payload
        proof_id = "status-" + hashlib.sha256(order_token.encode("ascii")).hexdigest()[:48]
        try:
            entitlement = self.state.activate_paid_term_subscription(
                order_token=order_token, delivery_id=proof_id, platform=self.platform,
                platform_user_id=user_id, amount_rials=int(payload["amountRials"]),
                verified_at=str(payload["verifiedAt"]),
                payment_order_ref=str(payload.get("orderId") or payload.get("trackingRef") or ""),
            )
        except (TypeError, ValueError, RuntimeError):
            return payload
        result = dict(payload)
        result["fulfillment"] = {
            "text": (
                f"اشتراک {entitlement.get('billingPeriod') or ''} فعال شد؛ "
                "اکنون از بخش جزوات ادامه بده."
            )
        }
        return result

    def _owner_payment_summary(self, user_id: int) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "payment_owner_dashboard"):
            return {}
        try:
            return self.site_api.payment_owner_dashboard(user_id)
        except SiteApiError:
            return {}

    def _term_subscription_report(self, user_id: int, term: int) -> dict:
        report = self.state.term_subscription_report(term)
        if self.site_api is None or not hasattr(self.site_api, "payment_directory"):
            return report
        try:
            directory = [
                item for item in self.site_api.payment_directory(user_id, limit=500).get("items", [])
                if isinstance(item, dict) and str(item.get("studentNumber") or "").isdigit()
            ]
            total = len({str(item.get("studentNumber")) for item in directory})
            report["directoryCount"] = total
            report["withoutSubscription"] = max(0, total - int(report.get("totalActiveAccess") or 0))
        except SiteApiError:
            pass
        return report

    def _payment_people_report(
        self, user_id: int, item: dict, *, date_from: str = "", date_to: str = ""
    ) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "payment_product_report"):
            raise SiteApiError("گزارش پرداخت در دسترس نیست.", code="PAYMENT_REPORT_UNAVAILABLE")
        report = dict(self.site_api.payment_product_report(
            user_id, offer_ref=str(item.get("ref") or ""), date_from=date_from, date_to=date_to
        ))
        audience = dict(item.get("audience") or {})
        mode = str(audience.get("mode") or "all")
        targets: list[dict] = []
        if mode in {"cohorts", "users", "lists"} and hasattr(self.site_api, "payment_directory"):
            directory = [entry for entry in self.site_api.payment_directory(user_id, limit=500).get("items", []) if isinstance(entry, dict)]
            student_numbers = set(str(value) for value in audience.get("studentNumbers", []))
            if mode == "lists":
                student_numbers = set(self.state.saved_audience_members(list(audience.get("listRefs") or [])))
            cohorts = set(str(value) for value in audience.get("cohorts", []))
            for entry in directory:
                student = str(entry.get("studentNumber") or "")
                if (mode == "cohorts" and str(entry.get("cohortKey") or "") in cohorts) or (mode != "cohorts" and student in student_numbers):
                    targets.append(entry)
        payer_numbers = {str(entry.get("studentNumber") or "") for entry in report.get("payers", []) if isinstance(entry, dict)}
        report["unpaid"] = [entry for entry in targets if str(entry.get("studentNumber") or "") not in payer_numbers]
        report["targets"] = targets
        report["targetCount"] = len(targets) if mode in {"cohorts", "users", "lists"} else None
        report["unpaidCount"] = len(report["unpaid"]) if report["targetCount"] is not None else None
        return report

    @staticmethod
    def _payment_period_bounds(period: str) -> tuple[str, str]:
        now = local_now("Asia/Tehran")
        if period == "today":
            start = now.replace(hour=0, minute=0, second=0, microsecond=0)
        elif period == "7":
            start = now - timedelta(days=7)
        elif period == "30":
            start = now - timedelta(days=30)
        else:
            return "", ""
        return start.astimezone(timezone.utc).isoformat(), now.astimezone(timezone.utc).isoformat()

    def _payment_transaction_filter_state(self, user_id: int) -> dict:
        return dict(self._payment_filters.get(int(user_id)) or {})

    def _payment_transactions_payload(self, user_id: int, *, page: int = 0, limit: int = 10) -> dict:
        if self.site_api is None or not hasattr(self.site_api, "payment_transactions"):
            raise SiteApiError("گزارش تراکنش در دسترس نیست.", code="PAYMENT_REPORT_UNAVAILABLE")
        filters = self._payment_transaction_filter_state(user_id)
        return self.site_api.payment_transactions(
            user_id,
            query=str(filters.get("query") or ""),
            offer_ref=str(filters.get("offerRef") or ""),
            status=str(filters.get("status") or ""),
            platform=str(filters.get("platform") or ""),
            gateway=str(filters.get("gateway") or ""),
            date_from=str(filters.get("dateFrom") or ""),
            date_to=str(filters.get("dateTo") or ""),
            page=max(0, int(page)), limit=max(1, min(100, int(limit))),
        )

    def _all_payment_transactions(self, user_id: int, *, filtered: bool, offer_ref: str = "") -> list[dict]:
        if self.site_api is None or not hasattr(self.site_api, "payment_transactions"):
            raise SiteApiError("خروجی تراکنش در دسترس نیست.", code="PAYMENT_EXPORT_UNAVAILABLE")
        filters = self._payment_transaction_filter_state(user_id) if filtered else {}
        if offer_ref:
            filters["offerRef"] = offer_ref
        rows: list[dict] = []
        for page in range(100):
            payload = self.site_api.payment_transactions(
                user_id,
                query=str(filters.get("query") or ""), offer_ref=str(filters.get("offerRef") or ""),
                status=str(filters.get("status") or ""), platform=str(filters.get("platform") or ""),
                gateway=str(filters.get("gateway") or ""), date_from=str(filters.get("dateFrom") or ""),
                date_to=str(filters.get("dateTo") or ""), page=page, limit=100,
            )
            chunk = [item for item in payload.get("items", []) if isinstance(item, dict)]
            rows.extend(chunk)
            total = max(0, int(payload.get("total") or len(rows)))
            if not chunk or len(rows) >= total:
                break
        return rows

    def _payment_audience_search_results(self, payload: dict) -> Screen:
        selected = {str(value) for value in payload.get("selected", [])}
        candidates = [item for item in payload.get("candidates", []) if isinstance(item, dict)][:12]
        lines = ["<b>👥 انتخاب مخاطبان</b>", "", f"انتخاب‌شده: <b>{to_persian_digits(len(selected))}</b> نفر"]
        rows: list[list[dict]] = []
        for item in candidates:
            student = str(item.get("studentNumber") or "")
            if not student.isdigit():
                continue
            marker = "✅" if student in selected else "◻️"
            name = str(item.get("name") or student)
            rows.append([button(f"{marker} {name[:24]} · {to_persian_digits(student)}", action=f"payment-audience-select:{student}")])
        if not candidates:
            lines.append("نتیجه‌ای پیدا نشد.")
        rows.extend((
            [button("🔎 جستجوی بیشتر", action="payment-audience-search-more")],
            [button("💾 ذخیره فهرست", action="payment-audience-selection-save", style="success")],
            [button("انصراف", action="payment-audiences")],
        ))
        return Screen("\n".join(lines), keyboard(*rows))

    def _payment_export_rows(self, user_id: int, scope: str, kind: str) -> list[dict]:
        if scope in {"all", "filtered"}:
            return self._all_payment_transactions(user_id, filtered=scope == "filtered")
        item = self._ordinary_payment_offer(scope)
        if item is None:
            raise SiteApiError("محصول پیدا نشد.", code="PRODUCT_NOT_FOUND")
        if kind in {"paid", "unpaid"}:
            report = self._payment_people_report(user_id, item)
            people = report.get("payers" if kind == "paid" else "unpaid", [])
            return [
                {
                    "payerName": str(person.get("name") or ""),
                    "studentNumber": str(person.get("studentNumber") or ""),
                    "title": str(item.get("title") or ""),
                    "amountRials": int(person.get("amountRials") or item.get("amountRials") or 0) if kind == "paid" else 0,
                    "status": "success" if kind == "paid" else "unpaid",
                    "createdAt": str(person.get("paidAt") or ""),
                    "trackingRef": "",
                    "originPlatform": self.platform,
                    "gateway": "",
                }
                for person in people if isinstance(person, dict)
            ]
        return self._all_payment_transactions(user_id, filtered=False, offer_ref=scope)

    def _send_payment_export(self, chat_id: int, user_id: int, scope: str, kind: str) -> None:
        rows = self._payment_export_rows(user_id, scope, kind)
        fields = [
            ("نام", "payerName"), ("شماره دانشجویی", "studentNumber"), ("محصول", "title"),
            ("مبلغ ریال", "amountRials"), ("وضعیت", "status"), ("ایجاد", "createdAt"),
            ("پرداخت", "paidAt"), ("تأیید", "verifiedAt"), ("کد پیگیری", "trackingRef"),
            ("پلتفرم", "originPlatform"), ("درگاه", "gateway"),
        ]
        with tempfile.TemporaryDirectory(prefix="dent-payment-export-") as temp_dir:
            output = Path(temp_dir) / f"dent-payments-{kind}-{int(time.time())}.csv"
            with output.open("w", encoding="utf-8-sig", newline="") as stream:
                writer = csv.writer(stream)
                writer.writerow([label for label, _key in fields])
                for row in rows:
                    writer.writerow([row.get(key, "") for _label, key in fields])
            self.api.send_document_path(
                chat_id, output, caption=f"<b>📤 خروجی پرداخت‌ها</b>\n\n{to_persian_digits(len(rows))} ردیف · CSV سازگار با Excel",
                filename=output.name, content_type="text/csv; charset=utf-8",
            )
        self.state.record_payment_action(
            "payment-exported", actor_user_id=user_id, actor_platform=self.platform,
            offer_ref="" if scope in {"all", "filtered"} else scope, note=f"{scope}:{kind}:{len(rows)}",
        )

    def _send_term_subscription_export(self, chat_id: int, user_id: int, term: int, kind: str) -> None:
        rows = self.state.term_access_export_rows(term)
        with tempfile.TemporaryDirectory(prefix="dent-subscription-export-") as temp_dir:
            suffix = "csv" if kind == "csv" else "txt"
            output = Path(temp_dir) / f"term-{term}-subscription-{int(time.time())}.{suffix}"
            if kind == "csv":
                fields = (
                    ("نام", "displayName"), ("شماره دانشجویی", "studentNumber"),
                    ("نوع دسترسی", "accessType"), ("دوره", "billingPeriod"),
                    ("وضعیت", "status"), ("اعطا", "grantedAt"), ("انقضا", "expiresAt"),
                    ("یادداشت", "note"),
                )
                with output.open("w", encoding="utf-8-sig", newline="") as stream:
                    writer = csv.writer(stream)
                    writer.writerow([label for label, _key in fields])
                    for row in rows:
                        writer.writerow([row.get(key, "") for _label, key in fields])
            else:
                lines = [f"اشتراک جزوات ترم {term}", ""]
                for index, row in enumerate(rows, 1):
                    lines.append(
                        f"{index}. {row.get('displayName') or '—'} | {row.get('studentNumber') or '—'} | "
                        f"{row.get('accessType') or '—'} | {row.get('billingPeriod') or 'دسترسی رایگان'} | "
                        f"{row.get('status') or '—'}"
                    )
                output.write_text("\n".join(lines), encoding="utf-8")
            self.api.send_document_path(
                chat_id, output,
                caption=f"<b>📤 خروجی اشتراک جزوات ترم {to_persian_digits(term)}</b>\n\n{to_persian_digits(len(rows))} ردیف",
                filename=output.name,
                content_type="text/csv; charset=utf-8" if kind == "csv" else "text/plain; charset=utf-8",
            )

    def _send_payment_reminder_batch(self, chat_id: int, user_id: int, offer_ref: str, batch_ref: str) -> None:
        batch = self.state.payment_reminder(batch_ref)
        item = self._ordinary_payment_offer(offer_ref)
        if not batch or batch.get("status") != "preview" or batch.get("offerRef") != offer_ref or item is None:
            self.api.send(chat_id, frame_error("پیش‌نمایش یادآوری منقضی یا قبلاً استفاده شده است."), payment_control_center_screen([]).keyboard)
            return
        report = self._payment_people_report(user_id, item)
        recipients = [person for person in report.get("unpaid", []) if str(person.get("platformUserId") or "").isdigit()][:50]
        self.state.finish_payment_reminder(batch_ref, status="sending", sent_count=0)
        sent = 0
        for person in recipients:
            try:
                self.api.send(
                    int(person["platformUserId"]),
                    f"<b>🔔 یادآوری پرداخت</b>\n\n<b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"مبلغ: <code>{html.escape(format_rials(item.get('amountRials')))}</code>\n"
                    "این پیام فقط برای مخاطبان همین محصول و با تأیید مالک ارسال شده است.",
                    keyboard([button("مشاهده محصول", action=f"payment-confirm:{offer_ref}", style="success")]),
                )
                sent += 1
                time.sleep(0.05)
            except BotApiError:
                continue
        self.state.finish_payment_reminder(batch_ref, status="sent" if sent == len(recipients) else "failed", sent_count=sent)
        self.state.record_payment_action(
            "payment-reminder-sent", actor_user_id=user_id, actor_platform=self.platform,
            offer_ref=offer_ref, note=f"{sent}/{len(recipients)}",
        )
        self.api.send(
            chat_id,
            f"<b>🔔 یادآوری‌ها پردازش شد</b>\n\nارسال موفق: <b>{to_persian_digits(sent)}</b> از <b>{to_persian_digits(len(recipients))}</b>",
            keyboard([button("بازگشت به آمار", action=f"payment-offer-stats:{offer_ref}")]),
        )

    def _send_payment_product_batch(self, chat_id: int, user_id: int, offer_ref: str, batch_ref: str) -> None:
        batch = self.state.payment_reminder(batch_ref)
        item = self._ordinary_payment_offer(offer_ref)
        if not batch or batch.get("status") != "preview" or batch.get("offerRef") != offer_ref or item is None:
            self.api.send(chat_id, frame_error("پیش‌نمایش ارسال منقضی یا قبلاً استفاده شده است."), payment_control_center_screen([]).keyboard)
            return
        report = self._payment_people_report(user_id, item)
        recipients = [person for person in report.get("targets", []) if str(person.get("platformUserId") or "").isdigit()][:50]
        share_url = bot_start_url(self.bot_username, f"product_{str(item.get('shareToken') or '')}", platform=self.platform)
        if not share_url:
            self.state.finish_payment_reminder(batch_ref, status="failed", sent_count=0)
            self.api.send(chat_id, frame_error("لینک امن محصول ساخته نشد."), payment_control_center_screen([]).keyboard)
            return
        self.state.finish_payment_reminder(batch_ref, status="sending", sent_count=0)
        sent = 0
        for person in recipients:
            try:
                self.api.send(
                    int(person["platformUserId"]),
                    f"<b>🛍 محصول جدید</b>\n\n<b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"مبلغ: <code>{html.escape(format_rials(item.get('amountRials')))}</code>\n"
                    "این پیام فقط برای مخاطبان همین محصول و پس از تأیید مالک ارسال شده است.",
                    keyboard([button("مشاهده محصول", url=share_url, style="success")]),
                )
                sent += 1
                time.sleep(0.05)
            except BotApiError:
                continue
        self.state.finish_payment_reminder(batch_ref, status="sent" if sent == len(recipients) else "failed", sent_count=sent)
        self.state.record_payment_action(
            "payment-product-shared", actor_user_id=user_id, actor_platform=self.platform,
            offer_ref=offer_ref, note=f"{sent}/{len(recipients)}",
        )
        self.api.send(
            chat_id,
            f"<b>📤 ارسال محصول پردازش شد</b>\n\nارسال موفق: <b>{to_persian_digits(sent)}</b> از <b>{to_persian_digits(len(recipients))}</b>",
            keyboard([button("بازگشت به محصول", action=f"payment-offer:{offer_ref}")]),
        )

    def _screen(self, name: str, user_id: int) -> Screen:
        is_owner = user_id == self.owner_id
        has_products = False
        if name == "home" and not is_owner:
            try:
                has_products = bool(self._eligible_products(user_id))
            except SiteApiError:
                has_products = False
        return home(
            self.site_url,
            is_owner=is_owner,
            student_assistant_enabled=self.student_assistant_v1_enabled,
            has_products=has_products,
        ) if name == "home" else section(name, self.site_url, is_owner=is_owner)

    def _private_access_gate(self, user_id: int) -> Screen | None:
        """Return a blocking screen unless the canonical website link is live."""
        if self.site_api is None:
            return Screen(
                frame_error("بررسی امن اتصال حساب فعلاً در دسترس نیست؛ دسترسی خصوصی باز نشد."),
                gateway_screen().keyboard,
            )
        if not hasattr(self.site_api, "account"):
            return Screen(
                frame_error("بررسی امن اتصال حساب در دسترس نیست؛ دسترسی خصوصی باز نشد."),
                gateway_screen().keyboard,
            )
        try:
            account = self._account_snapshot(user_id)
        except (SiteApiError, AttributeError) as error:
            return Screen(frame_error(str(error)), gateway_screen().keyboard)
        if account.get("linked") is True and account.get("authComplete") is True:
            return None
        return self._unlinked_access_screen(user_id, account)

    def _unlinked_access_screen(self, user_id: int, account: dict) -> Screen:
        profile = (
            dict(account.get("onboardingProfile") or {})
            if isinstance(account.get("onboardingProfile"), dict)
            else None
        )
        if profile and profile.get("isClassMember"):
            dialog = self.state.dialog(user_id)
            if not dialog or str(dialog.get("kind") or "") != "class-auth-v1":
                self.state.start_dialog(user_id, "class-auth-v1", "method", {})
            return Screen(
                frame_error("اتصال حساب کلاس کامل نیست یا از سایت قطع شده است؛ دوباره با یکی از دو مسیر امن وارد شو."),
                class_auth_screen().keyboard,
            )
        if profile and str(profile.get("verifiedAt") or "").strip():
            return account_screen(
                self.site_url,
                platform=self.platform,
                identity_state=dict(account.get("identity") or {}),
                onboarding_profile=profile,
            )
        return gateway_screen()

    def _bot_entry_gate(self, user_id: int) -> Screen | None:
        """Block every bot option until one approved intake route is complete."""
        if self.site_api is None or not hasattr(self.site_api, "account"):
            return Screen(
                frame_error("بررسی امن وضعیت ثبت‌نام در دسترس نیست؛ هیچ بخشی باز نشد."),
                gateway_screen().keyboard,
            )
        try:
            account = self._account_snapshot(user_id)
        except (SiteApiError, AttributeError) as error:
            return Screen(frame_error(str(error)), gateway_screen().keyboard)
        if account.get("linked") is True and account.get("authComplete") is True:
            return None
        profile = account.get("onboardingProfile")
        if (
            isinstance(profile, dict)
            and str(profile.get("verifiedAt") or "").strip()
            and not profile.get("isClassMember")
        ):
            return None
        return self._unlinked_access_screen(user_id, account)

    @staticmethod
    def _requires_canonical_link(name: str) -> bool:
        exact = {
            "admin", "system-status", "admin-grades", "admin-payments", "navid", "navid-check",
            "identity-mappings", "identity-mapping-remove-cancel", "identity-mapping-remove-confirm",
            "profile-edit", "profile-edit-cancel", "profile-edit-requests", "grades", "notifications",
            "student-assistant", "exam-owner", "payment-offer-new", "payment-offer-cancel",
            "payment-offer-publish", "payment-offer-no-description", "payment-offer-custom-amount",
            "payment-products", "payment-stats", "payment-transactions", "payment-search",
            "payment-audiences", "payment-export", "payment-reminders", "payment-settings",
            "payment-audience-new", "payment-transaction-filters", "payment-tx-clear", "payment-tx-product",
            "payment-audience-search", "payment-audience-search-more", "payment-audience-selection-save",
            "term-subscription", "term-access-policies", "term-access-policy-add",
        }
        prefixes = (
            "profile-edit-field:", "profile-edit-approve:", "profile-edit-reject:",
            "identity-mapping-remove:", "payment-offer-status:", "payment-offer-amount:",
            "payment-offer-delete", "payment-offer:",
            "payment-products-page:", "payment-offer-audience:",
            "payment-offer-edit:", "payment-offer-schedule:", "payment-offer-advanced:",
            "payment-offer-stats:", "payment-offer-payers:", "payment-offer-unpaid:",
            "payment-offer-export:", "payment-offer-duplicate:", "payment-offer-rotate:",
            "payment-export-file:", "payment-export-text:", "payment-reminder-preview:",
            "prs:", "payment-product-copy:", "payment-product-share-preview:", "pps:",
            "payment-audience-select:",
            "payment-transactions-page:", "payment-tx-status:", "payment-tx-platform:",
            "payment-tx-gateway:", "payment-tx-period:", "payment-tx-product:",
            "payment-transaction:", "payment-transaction-status:",
            "notification-action:", "notification-read:", "notification:",
            "notification-audience:", "assistant-action:", "exam-action:",
            "term-subscription:", "term-subscription-buy:", "term-subscription-info:",
            "term-subscription-admin:", "term-subscription-settings:", "term-subscription-price:",
            "term-subscription-start:",
            "term-subscription-toggle:", "term-subscription-mode:", "term-subscription-reminders:",
            "term-subscription-grant:", "term-subscription-grant-select:", "term-subscription-free:",
            "term-subscription-revoke:", "term-subscription-export:",
        )
        return name in exact or name.startswith(prefixes)

    def _active_auth_dialog_screen(self, user_id: int) -> Screen | None:
        dialog = self.state.dialog(user_id)
        if not dialog:
            return None
        kind = str(dialog.get("kind") or "")
        step = str(dialog.get("step") or "")
        payload = dict(dialog.get("payload") or {})
        if kind == "class-auth-v1":
            if step == "student-number":
                return class_student_number_screen()
            if step == "otp":
                return class_otp_screen(str(payload.get("phoneMasked") or ""))
            return class_auth_screen()
        if kind == "onboarding-v1":
            try:
                return onboarding_prompt_screen(step, payload, self._onboarding_catalog(user_id))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), gateway_screen().keyboard)
        return None

    def handle(self, update: dict) -> None:
        inline_query = update.get("inline_query")
        if isinstance(inline_query, dict):
            self._handle_inline_query(dict(inline_query))
            return
        channel_post = update.get("channel_post") or update.get("edited_channel_post")
        if isinstance(channel_post, dict):
            self._handle_booklet_source_post(dict(channel_post))
            return
        callback = dict(update.get("callback_query") or {})
        message = dict(callback.get("message") or update.get("message") or {})
        sender = dict(callback.get("from") or message.get("from") or {})
        user_id = sender.get("id")
        if isinstance(user_id, int):
            interaction_version = self._claim_interaction(user_id) if callback else None
            with self._update_locks[user_id % len(self._update_locks)]:
                self._handle_serialized(update, interaction_version=interaction_version)
            return
        self._handle_serialized(update)

    def _handle_inline_query(self, query: dict) -> None:
        if self.platform != "telegram" or not hasattr(self.api, "answer_inline_query"):
            return
        query_id = str(query.get("id") or "")
        sender = dict(query.get("from") or {})
        user_id = sender.get("id")
        if not query_id or not isinstance(user_id, int) or user_id != self.owner_id:
            if query_id:
                self.api.answer_inline_query(query_id, [])
            return
        try:
            if self.required_channel_username and not self.api.is_chat_member(f"@{self.required_channel_username}", user_id):
                self.api.answer_inline_query(query_id, [])
                return
            if self._private_access_gate(user_id) is not None:
                self.api.answer_inline_query(query_id, [])
                return
        except (BotApiError, SiteApiError):
            self.api.answer_inline_query(query_id, [])
            return
        needle = " ".join(str(query.get("query") or "").lower().split())
        results: list[dict] = []
        for item in self._ordinary_payment_offers():
            if str(item.get("effectiveStatus") or "") != "active":
                continue
            title = str(item.get("title") or "محصول")
            if needle and needle not in title.lower() and needle not in str(item.get("description") or "").lower():
                continue
            share_url = f"https://t.me/{self.bot_username.lstrip('@')}?start=product_{item.get('shareToken', '')}"
            amount = to_persian_digits(format_rials(item.get("amountRials")))
            deadline = format_jalali_datetime(item.get("expiresAt"))
            body = f"<b>🛍 {html.escape(title)}</b>\n\nمبلغ: <code>{html.escape(amount)}</code>"
            if deadline:
                body += f"\nمهلت: {html.escape(deadline)}"
            description = str(item.get("description") or "").strip()
            if description:
                body += f"\n\n{html.escape(description[:300])}"
            results.append({
                "type": "article",
                "id": hashlib.sha256(str(item.get("shareToken") or "").encode()).hexdigest()[:32],
                "title": title[:80],
                "description": f"{amount}" + (f" · {deadline}" if deadline else ""),
                "input_message_content": {"message_text": body, "parse_mode": "HTML", "disable_web_page_preview": True},
                "reply_markup": {"inline_keyboard": [[{"text": "مشاهده / پرداخت", "url": share_url}]]},
            })
        self.api.answer_inline_query(query_id, results, cache_time=3)

    def _handle_booklet_source_post(self, message: dict) -> None:
        if self.platform != "telegram" or self.booklet_source_channel_id >= 0:
            return
        chat = dict(message.get("chat") or {})
        if int(chat.get("id") or 0) != self.booklet_source_channel_id:
            return
        message_id = int(message.get("message_id") or 0)
        if message_id <= 0:
            return
        records = source_records_from_channel_post(message)
        count = self.state.replace_protected_media_message(
            self.booklet_source_channel_id,
            message_id,
            records,
        )
        logging.info("booklet source catalog updated routes=%s", count)

    def _handle_serialized(self, update: dict, *, interaction_version: int | None = None) -> None:
        started = time.monotonic()
        kind = "callback" if isinstance(update.get("callback_query"), dict) else "message"
        if self._block_without_required_channel(update):
            return
        if isinstance(update.get("callback_query"), dict):
            self._callback(dict(update["callback_query"]), interaction_version=interaction_version)
        elif isinstance(update.get("message"), dict):
            self._message(dict(update["message"]))
        elapsed = time.monotonic() - started
        if elapsed >= 1.0:
            logging.warning("slow interactive update kind=%s elapsed_ms=%s", kind, int(elapsed * 1000))

    def _block_without_required_channel(self, update: dict) -> bool:
        """Fail closed before every Telegram private interaction."""
        if self.platform != "telegram" or not self.required_channel_username:
            return False
        callback = dict(update.get("callback_query") or {})
        message = dict(callback.get("message") or update.get("message") or {})
        sender = dict(callback.get("from") or message.get("from") or {})
        chat = dict(message.get("chat") or {})
        if chat.get("type") != "private" or not isinstance(sender.get("id"), int):
            return False
        user_id = int(sender["id"])
        check_unavailable = False
        try:
            member = bool(self.api.is_chat_member(f"@{self.required_channel_username}", user_id))
        except (BotApiError, AttributeError, TypeError, ValueError):
            member = False
            check_unavailable = True
        if member:
            if callback and str(callback.get("data") or "") == "v1:membership-check":
                callback_id = str(callback.get("id") or "")
                if callback_id:
                    self.api.answer_callback(callback_id, "عضویت تأیید شد؛ ربات باز شد.")
                    callback["_membership_answered"] = True
                callback["data"] = "v1:home"
                update["callback_query"] = callback
            return False
        screen = required_channel_membership_screen(
            self.required_channel_username,
            check_unavailable=check_unavailable,
        )
        if callback:
            callback_id = str(callback.get("id") or "")
            if callback_id:
                acknowledged = self.api.answer_callback(
                    callback_id,
                    "بررسی عضویت فعلاً ممکن نیست." if check_unavailable else "هنوز عضو کانال نیستی.",
                    show_alert=True,
                )
                if acknowledged is False:
                    self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            try:
                self.api.edit(int(chat["id"]), int(message["message_id"]), screen.text, screen.keyboard)
            except BotApiError as error:
                if "message is not modified" not in str(error).lower():
                    raise
        else:
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
        return True

    def _message(self, message: dict) -> None:
        chat = dict(message.get("chat") or {})
        sender = dict(message.get("from") or {})
        if chat.get("type") != "private" or not isinstance(sender.get("id"), int):
            return
        user_id = int(sender["id"])
        text = str(message.get("text") or "").strip()
        command, _, command_argument = text.partition(" ")
        command = command.split("@", 1)[0]
        if command == "/start":
            self._account_cache.pop(user_id, None)
            self._product_state_cache.pop(user_id, None)
        if command == "/start" and command_argument.startswith("product_"):
            blocked = self._bot_entry_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
            token = command_argument[8:]
            account = self._account_snapshot(user_id)
            item = self.state.payment_offer_by_share_token(token)
            allowed = None
            if item is not None:
                allowed = self.state.payment_offer_for_user(
                    str(item.get("ref") or ""), identity_from_account(account), via_link=True
                )
            if allowed is None:
                screen = Screen(
                    frame_error("این لینک در دسترس این حساب نیست یا اعتبارش پایان یافته است."),
                    self._screen("home", user_id).keyboard,
                )
            else:
                states = self._payment_product_states(user_id, [allowed])
                screen = payment_confirm_screen(
                    allowed,
                    action_ref=f"payment-create-link:{token}",
                    state=dict(states.get(str(allowed.get('ref') or '')) or {}),
                )
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        if command == "/start" and command_argument.startswith("pay_"):
            blocked = self._bot_entry_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
            account = self._account_snapshot(user_id)
            offer = self.state.payment_offer_for_user(command_argument[4:], identity_from_account(account))
            screen = payment_confirm_screen(offer) if offer else Screen(frame_error("این محصول فعال نیست."), gateway_screen().keyboard)
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        if command == "/start" and command_argument.startswith("receipt_"):
            blocked = self._bot_entry_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
            order_token = command_argument[8:]
            if self.site_api is None or re.fullmatch(r"[A-Za-z0-9_-]{20,46}", order_token) is None:
                screen = Screen(
                    frame_error("شناسهٔ بازگشت پرداخت معتبر نیست."),
                    home(self.site_url, is_owner=user_id == self.owner_id).keyboard,
                )
            else:
                try:
                    status_payload = self.site_api.payment_status(user_id, order_token)
                    status_payload = self._activate_subscription_from_payment_status(
                        user_id, order_token, dict(status_payload)
                    )
                    screen = payment_status_screen(
                        status_payload,
                        platform=self.platform,
                        order_token=order_token,
                        return_to_bot_enabled=True,
                    )
                except SiteApiError as error:
                    screen = (
                        account_screen(self.site_url, platform=self.platform)
                        if error.code == "ACCOUNT_LINK_REQUIRED"
                        else Screen(
                            frame_error(str(error)),
                            home(self.site_url, is_owner=user_id == self.owner_id).keyboard,
                        )
                    )
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        if command == "/start" and not command_argument:
            self._start_onboarding_gateway(int(chat["id"]), user_id)
            return
        if text == START_GENERIC and self.state.dialog(user_id) is None:
            self._begin_entry_route(int(chat["id"]), user_id, requested="generic")
            return
        if text == START_CLASS and self.state.dialog(user_id) is None:
            self._begin_entry_route(int(chat["id"]), user_id, requested="class")
            return
        auth_dialog = self._active_auth_dialog_screen(user_id)
        if auth_dialog is not None:
            if not text.startswith("/") and self._handle_dialog_message(
                int(chat["id"]), user_id, text, sender=sender, message=message
            ):
                return
            self.api.send(int(chat["id"]), auth_dialog.text, auth_dialog.keyboard)
            return
        blocked = self._bot_entry_gate(user_id)
        if blocked is not None:
            dialog = self.state.dialog(user_id)
            if dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(int(chat["id"]))
            self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
            return
        if command == "/menu":
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(int(chat["id"]))
            screen = self._dynamic_screen("home", user_id)
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        pending_dialog = self.state.dialog(user_id)
        private_message = (
            (self.student_assistant_v1_enabled and self._is_integration_captcha_reply(user_id, message))
            or (user_id == self.owner_id and text.split(maxsplit=1)[0].split("@", 1)[0] == "/navid")
            or (user_id == self.owner_id and self._is_navid_captcha_reply(message))
            or text.startswith("/setgrade")
            or command in {"/product", "/payform"}
            or (
                pending_dialog is not None
                and str(pending_dialog.get("kind") or "")
                not in {"onboarding-v1", "class-auth-v1", "booklets-v1"}
            )
        )
        if private_message:
            blocked = self._private_access_gate(user_id)
            if blocked is not None:
                self.api.send(int(chat["id"]), blocked.text, blocked.keyboard)
                return
        if self.student_assistant_v1_enabled and self._is_integration_captcha_reply(user_id, message):
            self._complete_integration_captcha(int(chat["id"]), user_id, message, text)
            return
        if user_id == self.owner_id and text.split(maxsplit=1)[0].split("@", 1)[0] == "/navid":
            self._send_navid_challenge(int(chat["id"]), refresh=True)
            return
        if user_id == self.owner_id and self._is_navid_captcha_reply(message):
            self._complete_navid_captcha(int(chat["id"]), text)
            return
        if text.startswith("/setgrade"):
            self._set_grade_message(int(chat["id"]), user_id, text)
            return
        if command in {"/product", "/payform"}:
            self._start_payment_offer_wizard(int(chat["id"]), user_id, command_argument)
            return
        if self._handle_dialog_message(int(chat["id"]), user_id, text, sender=sender, message=message):
            return
        command = text.split(maxsplit=1)[0].split("@", 1)[0]
        target = (
            "help" if command == "/help"
            else "account" if command in {"/verify", "/account"}
            else "student-assistant" if command == "/assistant" and self.student_assistant_v1_enabled
            else "home"
        )
        self.state.touch_user(user_id, target)
        screen = self._dynamic_screen(target, user_id)
        self.api.send(int(chat["id"]), screen.text, screen.keyboard)

    def _is_integration_captcha_reply(self, user_id: int, message: dict) -> bool:
        pending = self.state.integration_challenge(user_id)
        if not pending:
            return False
        reply = dict(message.get("reply_to_message") or {})
        return int(reply.get("message_id") or 0) == int(pending["message_id"])

    def _complete_integration_captcha(self, chat_id: int, user_id: int, message: dict, text: str) -> None:
        pending = self.state.integration_challenge(user_id)
        reply = dict(message.get("reply_to_message") or {})
        reply_message_id = int(reply.get("message_id") or 0)
        answer = clean_captcha_answer(text)
        if not pending or reply_message_id != int(pending["message_id"]):
            return
        if not answer:
            self.api.send(
                chat_id,
                frame_error("کد تصویر باید ۴ تا ۱۲ حرف یا عدد باشد و با Reply به همان تصویر ارسال شود."),
                self._screen("student-assistant", user_id).keyboard,
            )
            return
        if challenge_expired(str(pending.get("expires_at") or "")):
            self.state.clear_integration_challenge(user_id)
            self.api.send(
                chat_id,
                frame_error("اعتبار این تصویر تمام شده است؛ از دستیار دانشجو عملیات را دوباره باز کن."),
                self._screen("student-assistant", user_id).keyboard,
            )
            return
        consumed = self.state.take_integration_challenge(user_id, message_id=reply_message_id)
        if not consumed or self.site_api is None:
            return
        stable_request_id = hashlib.sha256(
            f"{self.platform}:{user_id}:{int(message.get('message_id') or 0)}".encode("ascii")
        ).hexdigest()
        try:
            result = self.site_api.integration_challenge_answer(
                user_id,
                challenge_ref=str(consumed["challenge_ref"]),
                job_ref=str(consumed["job_ref"]),
                answer=answer,
                request_id=stable_request_id,
            )
            if str(result.get("status") or "") == "challenge":
                send_private_challenge(api=self.api, state=self.state, user_id=user_id, payload=result)
                screen = integration_challenge_waiting_screen(result)
            else:
                screen = student_assistant_screen(result, is_owner=user_id == self.owner_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
        except SiteApiError as error:
            # The one-time local binding was consumed before the network call and
            # the answer is never retained. Restarting the job yields a fresh challenge.
            self.api.send(
                chat_id,
                frame_error(f"{str(error)} برای ادامه، عملیات را از دستیار دانشجو دوباره باز کن."),
                self._screen("student-assistant", user_id).keyboard,
            )
        except BotApiError:
            self.api.send(
                chat_id,
                frame_error("ارسال تصویر تازه انجام نشد؛ عملیات را از دستیار دانشجو دوباره باز کن."),
                self._screen("student-assistant", user_id).keyboard,
            )

    def _is_navid_captcha_reply(self, message: dict) -> bool:
        pending = self.state.navid_challenge()
        if not pending:
            return False
        reply = dict(message.get("reply_to_message") or {})
        return int(reply.get("message_id") or 0) == int(pending["message_id"])

    def _send_navid_challenge(self, chat_id: int, *, refresh: bool) -> None:
        if self.site_api is None:
            self.api.send(chat_id, frame_error("اتصال امن نوید در دسترس نیست."), home(self.site_url, is_owner=True).keyboard)
            return
        daily_date = local_now("Asia/Tehran").strftime("%Y-%m-%d")
        try:
            result = send_daily_challenge(
                api=self.api,
                state=self.state,
                site_api=self.site_api,
                owner_id=self.owner_id,
                daily_date=daily_date,
                refresh=refresh,
            )
            if str(result.get("status") or "") == "already-completed":
                self.api.send(
                    chat_id,
                    "<b>✅ بررسی امروز نوید انجام شده است</b>\n\nتکلیف جدیدی که شناسایی شده باشد در اعلان‌های سایت ثبت شده است.",
                    home(self.site_url, is_owner=True).keyboard,
                )
        except (SiteApiError, BotApiError) as error:
            self.api.send(chat_id, frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)

    def _complete_navid_captcha(self, chat_id: int, text: str) -> None:
        pending = self.state.navid_challenge()
        code = re.sub(r"[^A-Za-z0-9]", "", text).upper()
        if not pending or len(code) < 4 or len(code) > 10 or self.site_api is None:
            self.api.send(chat_id, frame_error("کد کپچا معتبر نیست؛ با /navid تصویر تازه بگیر."), home(self.site_url, is_owner=True).keyboard)
            return
        try:
            result = self.site_api.navid_daily_complete(
                self.owner_id,
                date=str(pending["date"]),
                captcha_code=code,
            )
            summary = dict(result.get("summary") or {})
            new_events = max(0, int(summary.get("newEvents") or 0))
            self.state.clear_navid_challenge()
            self.state.set_runtime_value("navid_completed_date", str(pending["date"]))
            self.api.send(
                chat_id,
                "<b>✅ بررسی روزانه نوید انجام شد</b>\n\n"
                f"تکلیف جدید: <b>{new_events}</b>\n"
                "<blockquote>موارد جدید از مسیر اعلان‌های سایت برای اعضای متصل به ربات‌ها توزیع می‌شوند.</blockquote>",
                home(self.site_url, is_owner=True).keyboard,
            )
        except SiteApiError as error:
            self.state.clear_navid_challenge()
            self.api.send(
                chat_id,
                frame_error(f"{str(error)} برای دریافت تصویر تازه /navid را بفرست."),
                home(self.site_url, is_owner=True).keyboard,
            )

    def _set_grade_message(self, chat_id: int, user_id: int, text: str) -> None:
        if user_id != self.owner_id or self.site_api is None:
            self.api.send(chat_id, frame_error("اجازه ثبت نمره را نداری."), home(self.site_url, is_owner=False).keyboard)
            return
        raw = text.split(maxsplit=1)[1] if len(text.split(maxsplit=1)) > 1 else ""
        parts = [item.strip() for item in raw.split("|")]
        if len(parts) != 4 or not all(parts):
            screen = owner_grade_screen(self.site_url)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        try:
            result = self.site_api.set_grade(
                user_id,
                student_number=parts[0],
                course_label=parts[1],
                max_score=parts[2],
                score=parts[3],
            )
            course = dict(result.get("course") or {})
            label = str(course.get("label") or parts[1])
            text_result = f"<b>🟢 نمره ذخیره شد</b>\n\n{label}\n<blockquote>{parts[3]} از {parts[2]}</blockquote>"
            self.api.send(chat_id, text_result, owner_grade_screen(self.site_url).keyboard)
        except SiteApiError as error:
            self.api.send(chat_id, frame_error(str(error)), owner_grade_screen(self.site_url).keyboard)

    def _start_payment_offer_wizard(self, chat_id: int, user_id: int, quick: str = "") -> None:
        if user_id != self.owner_id:
            self.api.send(chat_id, frame_error("اجازه ساخت محصول پرداختی را نداری."), home(self.site_url, is_owner=False).keyboard)
            return
        quick = " ".join(str(quick).split())
        match = re.fullmatch(r"([۰-۹٠-٩0-9][۰-۹٠-٩0-9,٬]*)\s+(.{3,160})", quick) if quick else None
        if match:
            amount_tomans = self._parse_tomans(match.group(1))
            if 1000 <= amount_tomans <= 10000000000:
                payload = {"title": match.group(2).strip(), "amountRials": amount_tomans * 10, "description": ""}
                dialog = self.state.start_dialog(user_id, "payment-offer", "audience", payload)
                screen = payment_offer_wizard_screen("audience", dialog["payload"])
            else:
                screen = Screen(frame_error("مبلغ دستور سریع معتبر نیست."), payment_offer_wizard_screen("title", {}).keyboard)
        else:
            dialog = self.state.start_dialog(user_id, "payment-offer", "title")
            screen = payment_offer_wizard_screen("title", dialog["payload"])
        self.api.send(chat_id, screen.text, screen.keyboard)

    @staticmethod
    def _parse_tomans(text: str) -> int:
        normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
        digits = re.sub(r"[^0-9]", "", normalized)
        return int(digits or "0")

    @staticmethod
    def _platform_profile(sender: dict) -> dict:
        first_name = " ".join(str(sender.get("first_name") or "").split())[:64]
        last_name = " ".join(str(sender.get("last_name") or "").split())[:64]
        return {
            "displayName": " ".join(value for value in (first_name, last_name) if value)[:128],
            "username": str(sender.get("username") or "").lstrip("@")[:32],
            "languageCode": str(sender.get("language_code") or "")[:16],
            "isPremium": bool(sender.get("is_premium")),
            "platformUserId": str(sender.get("id") or ""),
        }

    def _onboarding_catalog(self, user_id: int, *, refresh: bool = False) -> dict:
        if self.site_api is None:
            raise SiteApiError("اتصال امن سایت در دسترس نیست.", code="SITE_UNAVAILABLE")
        now = time.monotonic()
        if not refresh and self._onboarding_catalog_cache and now - self._onboarding_catalog_cached_at < 21600:
            return dict(self._onboarding_catalog_cache)
        catalog = self.site_api.onboarding_catalog(user_id)
        if str(catalog.get("contractVersion") or "") != "bot-onboarding-v1":
            raise SiteApiError("نسخه فهرست ثبت مشخصات معتبر نیست.", code="INVALID_ONBOARDING_CONTRACT")
        self._onboarding_catalog_cache = dict(catalog)
        self._onboarding_catalog_cached_at = now
        return dict(catalog)

    def _start_onboarding_gateway(self, chat_id: int, user_id: int) -> None:
        screen = self._bot_entry_gate(user_id)
        dialog = self.state.dialog(user_id)
        if screen is None:
            # A canonical completion supersedes any stale local intake state.
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(chat_id)
            screen = self._screen("home", user_id)
        else:
            # Repeated /start or the client's Start button resumes the exact
            # saved step. Only explicit cancel/restart may discard progress.
            active = self._active_auth_dialog_screen(user_id)
            if active is not None:
                screen = active
            elif dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(chat_id)
        self.api.send(chat_id, screen.text, screen.keyboard)

    def _remove_reply_keyboard(self, chat_id: int) -> None:
        """Best-effort transport cleanup when an auth reply flow ends."""
        remove = getattr(self.api, "remove_reply_keyboard", None)
        if not callable(remove):
            return
        try:
            remove(chat_id)
        except BotApiError:
            # Authentication has already completed. A transient presentation
            # cleanup failure must not roll back or duplicate that mutation.
            logging.warning("reply keyboard cleanup failed platform=%s", self.platform)

    def _begin_generic_onboarding(self, chat_id: int, user_id: int) -> None:
        try:
            catalog = self._onboarding_catalog(user_id)
            self.state.start_dialog(user_id, "onboarding-v1", "first-name", {})
            screen = onboarding_prompt_screen("first-name", {}, catalog)
        except SiteApiError as error:
            screen = Screen(frame_error(str(error)), gateway_screen().keyboard)
        self.api.send(chat_id, screen.text, screen.keyboard)

    def _begin_entry_route(self, chat_id: int, user_id: int, *, requested: str) -> None:
        if self.site_api is None or not hasattr(self.site_api, "account"):
            screen = Screen(frame_error("بررسی امن وضعیت ثبت‌نام در دسترس نیست؛ هیچ مسیری باز نشد."), gateway_screen().keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        try:
            account = self.site_api.account(user_id)
        except (SiteApiError, AttributeError) as error:
            self.api.send(chat_id, frame_error(str(error)), gateway_screen().keyboard)
            return
        profile = account.get("onboardingProfile")
        if account.get("linked") is True and account.get("authComplete") is True:
            screen = self._screen("home", user_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        if (
            isinstance(profile, dict)
            and str(profile.get("verifiedAt") or "").strip()
            and not profile.get("isClassMember")
        ):
            screen = self._screen("home", user_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return
        if requested == "class" or (isinstance(profile, dict) and profile.get("isClassMember")):
            self._begin_class_onboarding(chat_id, user_id)
            return
        self._begin_generic_onboarding(chat_id, user_id)

    def _begin_class_onboarding(self, chat_id: int, user_id: int) -> None:
        self.state.start_dialog(user_id, "class-auth-v1", "method", {})
        screen = class_auth_screen()
        self.api.send(chat_id, screen.text, screen.keyboard)

    @staticmethod
    def _institution_system(catalog: dict, province: str, institution: str) -> str:
        for item in catalog.get("institutions", []):
            if not isinstance(item, dict):
                continue
            if str(item.get("province") or "") == province and str(item.get("name") or "") == institution:
                return str(item.get("system") or "public")
        return "public"

    @staticmethod
    def _canonical_course_type(value: str) -> str:
        return {
            "روزانه": "روزانه یا تعهدی",
            "تعهدی": "روزانه یا تعهدی",
            "روزانه یا تعهدی": "روزانه یا تعهدی",
            "شهریه‌پرداز": "شهریه پرداز",
            "شهریه پرداز": "شهریه پرداز",
            "بین‌الملل": "بین الملل",
            "بین الملل": "بین الملل",
        }.get(str(value), str(value))

    def _back_onboarding(self, step: str, payload: dict) -> tuple[str, dict]:
        if step == "first-name":
            return "gateway", payload
        if step == "last-name":
            return "first-name", payload
        if step == "major":
            return "last-name", payload
        if step == "province":
            return "major", payload
        if step == "institution":
            return "province", payload
        if step == "entry-year":
            return "institution", payload
        if step == "entry-term":
            return "entry-year", payload
        if step == "course-type":
            return "entry-term", payload
        if step == "student-number":
            return (
                "entry-term" if str(payload.get("institutionSystem") or "") == "azad" else "course-type",
                payload,
            )
        if step == "review":
            return "student-number", payload
        if step == "contact":
            return "review", payload
        if step == "otp":
            return "contact", dict(payload.get("profile") or {})
        return "first-name", payload

    @staticmethod
    def _clean_person_name(value: str) -> str:
        value = str(value).translate(str.maketrans({"ي": "ی", "ى": "ی", "ك": "ک"}))
        value = re.sub(r"[^\w\s\u0600-\u06FF]", " ", value, flags=re.UNICODE)
        return " ".join(value.split())[:64]

    @staticmethod
    def _recover_onboarding_step(payload: dict, catalog: dict) -> str:
        """Find the first incomplete step without discarding submitted values."""
        nested_profile = payload.get("profile")
        if isinstance(nested_profile, dict):
            return "otp" if str(payload.get("challengeRef") or "") else "contact"
        if not str(payload.get("firstName") or "").strip():
            return "first-name"
        if not str(payload.get("lastName") or "").strip():
            return "last-name"
        if str(payload.get("major") or "") not in [str(item) for item in catalog.get("majors", [])]:
            return "major"
        province = str(payload.get("province") or "")
        if province not in [str(item) for item in catalog.get("provinces", [])]:
            return "province"
        institution = str(payload.get("institution") or "")
        allowed_institutions = [
            str(item.get("name") or "")
            for item in catalog.get("institutions", [])
            if isinstance(item, dict) and str(item.get("province") or "") == province
        ]
        if institution not in allowed_institutions:
            return "institution"
        if str(payload.get("entryYear") or "") not in [str(item) for item in catalog.get("entryYears", [])]:
            return "entry-year"
        if str(payload.get("entryTerm") or "") not in [str(item) for item in catalog.get("entryTerms", [])]:
            return "entry-term"
        if str(payload.get("institutionSystem") or "") != "azad" and not str(payload.get("courseType") or ""):
            return "course-type"
        if "studentNumber" not in payload:
            return "student-number"
        return "review"

    def _handle_onboarding_message(
        self,
        chat_id: int,
        user_id: int,
        text: str,
        dialog: dict,
        *,
        sender: dict,
        message: dict,
    ) -> bool:
        step = str(dialog.get("step") or "")
        payload = dict(dialog.get("payload") or {})
        try:
            catalog = self._onboarding_catalog(user_id)
        except SiteApiError as error:
            self.api.send(chat_id, frame_error(str(error)), onboarding_prompt_screen(step, payload, {}).keyboard)
            return True
        if text == CANCEL:
            self.state.clear_dialog(user_id)
            screen = gateway_screen()
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if text == BACK_STEP:
            step, payload = self._back_onboarding(step, payload)
            if step == "gateway":
                self.state.clear_dialog(user_id)
                screen = gateway_screen()
            else:
                self.state.update_dialog(user_id, step=step, payload=payload)
                screen = onboarding_prompt_screen(step, payload, catalog)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if step == "first-name":
            value = self._clean_person_name(text)
            if len(value) < 2:
                self.api.send(chat_id, frame_error("نام معتبر را وارد کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["firstName"] = value
            step = "last-name"
        elif step == "last-name":
            value = self._clean_person_name(text)
            if len(value) < 2:
                self.api.send(chat_id, frame_error("نام خانوادگی معتبر را وارد کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["lastName"] = value
            step = "major"
        elif step == "major":
            if text not in catalog.get("majors", []):
                self.api.send(chat_id, frame_error("رشته را با یکی از دکمه‌ها انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["major"] = text
            for key in ("province", "institution", "institutionSystem", "entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            payload["provincePage"] = 0
            step = "province"
        elif step == "province":
            provinces = [str(item) for item in catalog.get("provinces", [])]
            page_count = max(1, (len(provinces) + 9) // 10)
            page = max(0, min(int(payload.get("provincePage") or 0), page_count - 1))
            if text == NEXT_PAGE and page + 1 < page_count:
                payload["provincePage"] = page + 1
                self.state.update_dialog(user_id, step=step, payload=payload)
                screen = onboarding_prompt_screen(step, payload, catalog)
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            if text == PREVIOUS_PAGE and page > 0:
                payload["provincePage"] = page - 1
                self.state.update_dialog(user_id, step=step, payload=payload)
                screen = onboarding_prompt_screen(step, payload, catalog)
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
            if text not in provinces:
                self.api.send(chat_id, frame_error("استان را با یکی از دکمه‌های فهرست انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["province"] = text
            for key in ("institution", "institutionSystem", "entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            step = "institution"
        elif step == "institution":
            allowed = [
                str(item.get("name") or "") for item in catalog.get("institutions", [])
                if isinstance(item, dict) and str(item.get("province") or "") == str(payload.get("province") or "")
            ]
            if text not in allowed:
                self.api.send(chat_id, frame_error("دانشگاه را با یکی از دکمه‌های فهرست انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["institution"] = text
            payload["institutionSystem"] = self._institution_system(
                catalog,
                str(payload.get("province") or ""),
                text,
            )
            for key in ("entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            step = "entry-year"
        elif step == "entry-year":
            if text not in catalog.get("entryYears", []):
                self.api.send(chat_id, frame_error("سال ورود را با یکی از دکمه‌ها انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["entryYear"] = text
            for key in ("entryTerm", "courseType", "admissionType", "studentNumber"):
                payload.pop(key, None)
            step = "entry-term"
        elif step == "entry-term":
            if text not in catalog.get("entryTerms", []):
                self.api.send(chat_id, frame_error("نیمسال ورودی را با دکمه انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["entryTerm"] = text
            payload.pop("courseType", None)
            payload.pop("admissionType", None)
            if str(payload.get("institutionSystem") or "") == "azad":
                payload["courseType"] = ""
                payload["admissionType"] = text
                step = "student-number"
            else:
                step = "course-type"
        elif step == "course-type":
            if text not in catalog.get("courseTypes", []):
                self.api.send(chat_id, frame_error("نوع دوره را با دکمه انتخاب کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            course_type = self._canonical_course_type(text)
            payload["courseType"] = course_type
            payload["admissionType"] = f"{payload.get('entryTerm', '')} ({course_type})"
            step = "student-number"
        elif step == "student-number":
            normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
            student_number = "" if text == SKIP_STUDENT_NUMBER else re.sub(r"[^0-9]", "", normalized)[:20]
            if text != SKIP_STUDENT_NUMBER and not 5 <= len(student_number) <= 20:
                self.api.send(chat_id, frame_error("شماره دانشجویی باید بین ۵ تا ۲۰ رقم باشد؛ یا دکمه مرحله اختیاری را بزن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload["studentNumber"] = student_number
            step = "review"
        elif step == "review":
            if text == RESTART_PROFILE:
                payload = {}
                step = "first-name"
            elif text == CONFIRM_PROFILE:
                step = "contact"
            else:
                self.api.send(chat_id, frame_error("اطلاعات را تأیید کن یا از اول وارد کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
        elif step == "contact":
            contact = dict(message.get("contact") or {})
            contact_user_id = contact.get("user_id")
            if not contact or not str(contact.get("phone_number") or "").strip():
                self.api.send(chat_id, frame_error("شماره را تایپ نکن؛ Contact خودت را با دکمه ارسال کن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            if contact_user_id is not None and str(contact_user_id) != str(sender.get("id") or ""):
                self.api.send(chat_id, frame_error("فقط Contact متعلق به همین حساب قابل قبول است."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            if self.platform == "telegram" and contact_user_id is None:
                self.api.send(chat_id, frame_error("تلگرام این Contact را متعلق به حساب شما اعلام نکرد؛ دکمه ارسال شماره خودم را بزن."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            try:
                profile = {key: payload.get(key, "") for key in (
                    "firstName", "lastName", "major", "province", "institution", "institutionSystem",
                    "entryYear", "entryTerm", "courseType", "admissionType", "studentNumber"
                )}
                result = self.site_api.request_onboarding_otp(
                    user_id,
                    profile=profile,
                    phone_number=str(contact.get("phone_number") or ""),
                )
            except SiteApiError as error:
                message_text = str(error)
                if "مشخصات تحصیلی خارج از فهرست معتبر" in message_text:
                    message_text = "اطلاعات آموزشی با فهرست فعلی هم‌خوان نیست؛ «مرحله قبل» را بزن و نیمسال یا نوع دوره را دوباره انتخاب کن."
                self.api.send(chat_id, frame_error(message_text), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            payload = {
                "profile": profile,
                "challengeRef": str(result.get("challengeRef") or ""),
                "phoneMasked": str(result.get("phoneMasked") or ""),
            }
            step = "otp"
        elif step == "otp":
            if text == CHANGE_PHONE:
                payload = dict(payload.get("profile") or {})
                step = "contact"
            elif text == RESEND_OTP:
                try:
                    result = self.site_api.resend_onboarding_otp(user_id, challenge_ref=str(payload.get("challengeRef") or ""))
                    payload["phoneMasked"] = str(result.get("phoneMasked") or payload.get("phoneMasked") or "")
                    self.state.update_dialog(user_id, step=step, payload=payload)
                    screen = onboarding_prompt_screen(step, payload, catalog)
                    self.api.send(chat_id, "<b>✅ کد تازه ارسال شد</b>\n\n" + screen.text, screen.keyboard)
                except SiteApiError as error:
                    self.api.send(chat_id, frame_error(str(error)), onboarding_prompt_screen(step, payload, catalog).keyboard)
                return True
            else:
                normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
                code = re.sub(r"[^0-9]", "", normalized)
                if len(code) != 6:
                    self.api.send(chat_id, frame_error("کد تأیید باید ۶ رقم باشد."), onboarding_prompt_screen(step, payload, catalog).keyboard)
                    return True
                try:
                    result = self.site_api.verify_onboarding_otp(
                        user_id,
                        challenge_ref=str(payload.get("challengeRef") or ""),
                        code=code,
                    )
                except SiteApiError as error:
                    self.api.send(chat_id, frame_error(str(error)), onboarding_prompt_screen(step, payload, catalog).keyboard)
                    return True
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(chat_id)
                screen = onboarding_success_screen(dict(result.get("profile") or {}))
                self.api.send(chat_id, screen.text, screen.keyboard)
                return True
        else:
            step = self._recover_onboarding_step(payload, catalog)
            self.state.update_dialog(user_id, step=step, payload=payload)
            screen = Screen(
                frame_error("مرحلهٔ قبلی قابل ادامه نبود؛ اطلاعاتت پاک نشد و از نزدیک‌ترین مرحلهٔ معتبر ادامه می‌دهیم."),
                onboarding_prompt_screen(step, payload, catalog).keyboard,
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        self.state.update_dialog(user_id, step=step, payload=payload)
        screen = onboarding_prompt_screen(step, payload, catalog)
        self.api.send(chat_id, screen.text, screen.keyboard)
        return True

    def _handle_dialog_message(
        self,
        chat_id: int,
        user_id: int,
        text: str,
        *,
        sender: dict | None = None,
        message: dict | None = None,
    ) -> bool:
        dialog = self.state.dialog(user_id)
        if not dialog or text.startswith("/"):
            return False
        if dialog.get("kind") == "onboarding-v1":
            return self._handle_onboarding_message(
                chat_id,
                user_id,
                text,
                dialog,
                sender=dict(sender or {"id": user_id}),
                message=dict(message or {}),
            )
        if dialog.get("kind") == "class-auth-v1":
            step = str(dialog.get("step") or "method")
            payload = dict(dialog.get("payload") or {})
            if text == CANCEL:
                self.state.clear_dialog(user_id)
                screen = gateway_screen()
            elif text == BACK_STEP:
                if step == "method":
                    self.state.clear_dialog(user_id)
                    screen = gateway_screen()
                elif step == "student-number":
                    self.state.update_dialog(user_id, step="method", payload={})
                    screen = class_auth_screen()
                else:
                    self.state.update_dialog(user_id, step="student-number", payload={})
                    screen = class_student_number_screen()
            elif step == "method" and text == CLASS_OTP:
                self.state.update_dialog(user_id, step="student-number", payload={})
                screen = class_student_number_screen()
            elif step == "method" and text == CLASS_SITE:
                try:
                    result = self.site_api.start_link(
                        user_id,
                        platform_profile=self._platform_profile(dict(sender or {"id": user_id})),
                    )
                    self.state.clear_dialog(user_id)
                    self._remove_reply_keyboard(chat_id)
                    screen = account_screen(
                        self.site_url,
                        platform=self.platform,
                        linked_user=(
                            dict(result.get("user") or {})
                            if result.get("alreadyLinked") and result.get("authComplete")
                            else None
                        ),
                        link_url=str(result.get("linkUrl") or ""),
                    )
                except (SiteApiError, AttributeError) as error:
                    screen = Screen(frame_error(str(error)), class_auth_screen().keyboard)
            elif step == "student-number":
                normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
                student_number = re.sub(r"[^0-9]", "", normalized)[:20]
                if len(student_number) < 5:
                    screen = Screen(frame_error("شماره دانشجویی معتبر را بفرست."), class_student_number_screen().keyboard)
                else:
                    try:
                        result = self.site_api.start_class_auth_otp(
                            user_id,
                            student_number=student_number,
                            platform_profile=self._platform_profile(dict(sender or {"id": user_id})),
                        )
                        if result.get("alreadyLinked") and result.get("authComplete"):
                            self.state.clear_dialog(user_id)
                            self._remove_reply_keyboard(chat_id)
                            account = self.site_api.account(user_id)
                            screen = account_screen(
                                self.site_url, platform=self.platform,
                                linked_user=dict(account.get("user") or {}),
                                onboarding_profile=dict(account.get("onboardingProfile") or {}),
                            )
                        else:
                            payload = {
                                "challengeRef": str(result.get("challengeRef") or ""),
                                "phoneMasked": str(result.get("phoneMasked") or ""),
                                "studentNumber": student_number,
                            }
                            self.state.update_dialog(user_id, step="otp", payload=payload)
                            screen = class_otp_screen(payload["phoneMasked"])
                    except (SiteApiError, AttributeError) as error:
                        screen = Screen(frame_error(str(error)), class_student_number_screen().keyboard)
            elif step == "otp":
                normalized = text.translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789"))
                code = re.sub(r"[^0-9]", "", normalized)
                if len(code) != 6:
                    screen = Screen(frame_error("کد تأیید باید ۶ رقم باشد."), class_otp_screen(str(payload.get("phoneMasked") or "")).keyboard)
                else:
                    try:
                        self.site_api.verify_class_auth_otp(user_id, challenge_ref=str(payload.get("challengeRef") or ""), code=code)
                        self.state.clear_dialog(user_id)
                        self._remove_reply_keyboard(chat_id)
                        account = self.site_api.account(user_id)
                        screen = account_screen(
                            self.site_url, platform=self.platform,
                            linked_user=dict(account.get("user") or {}),
                            onboarding_profile=dict(account.get("onboardingProfile") or {}),
                        )
                    except (SiteApiError, AttributeError) as error:
                        screen = Screen(frame_error(str(error)), class_otp_screen(str(payload.get("phoneMasked") or "")).keyboard)
            else:
                screen = Screen(frame_error("یکی از دو روش احراز هویت را با دکمه انتخاب کن."), class_auth_screen().keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if dialog.get("kind") == "booklets-v1":
            return self._handle_booklet_dialog(chat_id, user_id, text, dialog)
        if dialog.get("kind") == "profile-edit-v1":
            payload = dict(dialog.get("payload") or {})
            value = " ".join(text.split())[:180]
            if not value:
                self.api.send(chat_id, frame_error("مقدار پیشنهادی خالی است."), profile_edit_prompt_screen(str(payload.get("fieldLabel") or "مشخصات")).keyboard)
                return True
            try:
                self.site_api.request_profile_edit(user_id, field=str(payload.get("field") or ""), value=value)
                self.state.clear_dialog(user_id)
                account = self.site_api.account(user_id)
                base = account_screen(
                    self.site_url, platform=self.platform,
                    linked_user=dict(account.get("user") or {}),
                    onboarding_profile=dict(account.get("onboardingProfile") or {}),
                )
                screen = Screen("<b>✅ درخواست ویرایش برای مالک ارسال شد</b>\n\nتا پیش از تأیید، مقدار فعلی بدون تغییر می‌ماند.\n\n" + base.text, base.keyboard)
                if user_id != self.owner_id:
                    try:
                        owner_screen = profile_edit_requests_screen(self.site_api.profile_edit_requests(self.owner_id))
                        self.api.send(self.owner_id, owner_screen.text, owner_screen.keyboard)
                    except (SiteApiError, BotApiError):
                        logging.warning("profile edit owner notification failed")
            except (SiteApiError, AttributeError) as error:
                screen = Screen(frame_error(str(error)), profile_edit_prompt_screen(str(payload.get("fieldLabel") or "مشخصات")).keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if dialog.get("kind") in {"identity-claim", "identity-mapping"}:
            self.state.clear_dialog(user_id)
            screen = Screen(
                frame_error("تأیید و اتصال دستی هویت غیرفعال شده است؛ فقط OTP سایت یا ورود امن سایت پذیرفته می‌شود."),
                class_auth_screen().keyboard,
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "identity-mapping-remove":
            payload = dict(dialog.get("payload") or {})
            reason = " ".join(text.split())[:240]
            if len(reason) < 5:
                self.api.send(chat_id, frame_error("دلیل حذف باید روشن و حداقل ۵ نویسه باشد."), identity_mapping_remove_screen(dict(payload.get("item") or {}), platform=self.platform).keyboard)
                return True
            payload["reason"] = reason
            self.state.update_dialog(user_id, step="preview", payload=payload)
            screen = identity_mapping_remove_confirmation(payload, platform=self.platform)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-access-policy-add":
            try:
                term = self._parse_tomans(text)
                if not 1 <= term <= 12:
                    raise ValueError("invalid term")
                if self.state.term_access_policy(term) is None:
                    self.state.update_term_access_policy(
                        term, {}, actor_user_id=user_id, actor_platform=self.platform,
                        note="owner created configurable term policy",
                    )
                self.state.clear_dialog(user_id)
                screen = term_access_policies_screen(self.state.term_access_policies())
            except (TypeError, ValueError):
                screen = Screen(
                    frame_error("شماره ترم باید بین ۱ تا ۱۲ باشد."),
                    keyboard([button("انصراف", action="term-access-policies")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-price":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            try:
                tomans = self._parse_tomans(text)
                if not 1_000 <= tomans <= 10_000_000_000:
                    raise ValueError("invalid price")
                self.state.update_term_access_policy(
                    term, {"monthlyPriceRials": tomans * 10}, actor_user_id=user_id,
                    actor_platform=self.platform, note="owner price change",
                )
                self.state.clear_dialog(user_id)
                screen = term_subscription_admin_screen(
                    self.state.term_access_policy(term) or {}, self._term_subscription_report(user_id, term)
                )
            except (TypeError, ValueError):
                screen = Screen(
                    frame_error("مبلغ را به تومان و فقط با رقم بفرست؛ نمونه: ۱۵۰۰۰۰"),
                    keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-start":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            try:
                normalized = text.strip().translate(str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789")).replace("/", "-")
                year, month, day = parse_jalali_date(normalized)
                active_from = f"{year:04d}-{month:02d}-{day:02d}"
                updated = self.state.update_term_access_policy(
                    term, {"activeFromJalali": active_from}, actor_user_id=user_id,
                    actor_platform=self.platform, note="owner activation boundary change",
                )
                self.state.clear_dialog(user_id)
                screen = term_subscription_settings_screen(updated)
            except (TypeError, ValueError):
                screen = Screen(
                    frame_error("تاریخ شمسی معتبر را به شکل ۱۴۰۵/۰۷/۰۱ بفرست."),
                    keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-grant-search":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            query = " ".join(text.split())[:120]
            if len(query) < 2 or self.site_api is None or not hasattr(self.site_api, "payment_directory"):
                screen = Screen(
                    frame_error("نام یا شماره دانشجویی را حداقل با دو نویسه بفرست."),
                    keyboard([button("انصراف", action=f"term-subscription-admin:{term}")]),
                )
            else:
                try:
                    candidates = [
                        item for item in self.site_api.payment_directory(user_id, query=query, limit=12).get("items", [])
                        if isinstance(item, dict) and str(item.get("studentNumber") or "").isdigit()
                    ]
                    for candidate in candidates:
                        candidate["termAccess"] = self.state.term_subject_entitlement_status(
                            student_number=str(candidate.get("studentNumber") or ""), term=term
                        )
                    payload["candidates"] = candidates
                    self.state.update_dialog(user_id, step="results", payload=payload)
                    screen = complimentary_search_results_screen(candidates, term=term)
                except SiteApiError as error:
                    screen = Screen(
                        frame_error(str(error)), keyboard([button("انصراف", action=f"term-subscription-admin:{term}")])
                    )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-grant-note":
            payload = dict(dialog.get("payload") or {})
            term = int(payload.get("term") or 0)
            student = str(payload.get("studentNumber") or "")
            name = str(payload.get("displayName") or "")
            note = "" if text == "-" else " ".join(text.split())[:240]
            try:
                granted = self.state.grant_complimentary_term_access(
                    term=term, student_number=student, display_name=name,
                    actor_user_id=user_id, actor_platform=self.platform, note=note,
                )
                self.state.clear_dialog(user_id)
                screen = Screen(
                    f"<b>✅ دسترسی رایگان فعال شد</b>\n\n"
                    f"👤 {html.escape(str(granted.get('displayName') or 'دانشجو'))}\n"
                    f"🎓 <code>{to_persian_digits(granted.get('studentNumber') or '—')}</code>\n"
                    f"📚 ترم {to_persian_digits(term)}",
                    complimentary_access_list_screen(self.state.complimentary_term_access(term), term=term).keyboard,
                )
            except ValueError as error:
                screen = Screen(
                    frame_error(str(error)), keyboard([button("انصراف", action=f"term-subscription-admin:{term}")])
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "term-subscription-revoke-note":
            payload = dict(dialog.get("payload") or {})
            entitlement_id = int(payload.get("entitlementId") or 0)
            term = int(payload.get("term") or 7)
            note = "" if text == "-" else " ".join(text.split())[:240]
            revoked = self.state.revoke_complimentary_term_access(
                entitlement_id, actor_user_id=user_id, actor_platform=self.platform, note=note,
            )
            self.state.clear_dialog(user_id)
            screen = (
                complimentary_access_list_screen(self.state.complimentary_term_access(term), term=term)
                if revoked is not None
                else Screen(frame_error("دسترسی رایگان پیدا نشد."), keyboard([button("بازگشت", action=f"term-subscription-admin:{term}")]))
            )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-audience-search":
            step = str(dialog.get("step") or "query")
            payload = dict(dialog.get("payload") or {})
            if step == "query":
                query = " ".join(text.split())[:120]
                if len(query) < 2 or self.site_api is None or not hasattr(self.site_api, "payment_directory"):
                    screen = Screen(frame_error("نام یا شماره را حداقل با دو نویسه بفرست."), keyboard([button("انصراف", action="payment-audiences")]))
                else:
                    try:
                        payload["candidates"] = [item for item in self.site_api.payment_directory(user_id, query=query, limit=12).get("items", []) if isinstance(item, dict)]
                        payload.setdefault("selected", [])
                        self.state.update_dialog(user_id, step="results", payload=payload)
                        screen = self._payment_audience_search_results(payload)
                    except SiteApiError as error:
                        screen = Screen(frame_error(str(error)), keyboard([button("انصراف", action="payment-audiences")]))
            elif step == "name":
                try:
                    self.state.save_payment_audience(text, list(payload.get("selected") or []), actor_user_id=user_id, actor_platform=self.platform)
                    self.state.clear_dialog(user_id)
                    screen = self._dynamic_screen("payment-audiences", user_id)
                except ValueError:
                    screen = Screen(frame_error("نام فهرست معتبر نیست."), keyboard([button("انصراف", action="payment-audiences")]))
            else:
                screen = self._payment_audience_search_results(payload)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-audience-new":
            parts = [part.strip() for part in text.split("|", 1)]
            if len(parts) != 2:
                self.api.send(chat_id, frame_error("قالب باید «نام فهرست | شماره‌ها» باشد."), keyboard([button("انصراف", action="payment-audiences")]))
                return True
            numbers = [part.strip() for part in re.split(r"[,،\s]+", parts[1]) if part.strip()]
            try:
                self.state.save_payment_audience(parts[0], numbers, actor_user_id=user_id, actor_platform=self.platform)
                self.state.clear_dialog(user_id)
                screen = self._dynamic_screen("payment-audiences", user_id)
            except ValueError:
                screen = Screen(frame_error("نام یا شماره‌های فهرست معتبر نیست."), keyboard([button("انصراف", action="payment-audiences")]))
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-offer-edit":
            payload = dict(dialog.get("payload") or {})
            offer_ref = str(payload.get("offerRef") or "")
            field = str(payload.get("field") or "")
            value: object = text.strip()
            if value == "-":
                value = ""
            try:
                if field == "amountRials":
                    tomans = self._parse_tomans(str(value))
                    value = tomans * 10
                elif field in {"capacity", "maxPurchasesPerUser"}:
                    value = self._parse_tomans(str(value))
                elif field == "audienceUsers":
                    numbers = [part.strip() for part in re.split(r"[,،\s]+", str(value)) if part.strip()]
                    field, value = "audience", {"mode": "users", "studentNumbers": numbers}
                elif field == "audienceCohorts":
                    cohorts = [part.strip() for part in re.split(r"[,،\s]+", str(value)) if re.fullmatch(r"[A-Za-z0-9_-]{2,80}", part.strip())]
                    field, value = "audience", {"mode": "cohorts", "cohorts": cohorts}
                elif field in {"fulfillmentText", "fulfillmentUrl"}:
                    current = self._ordinary_payment_offer(offer_ref)
                    if current is None:
                        raise ValueError("missing product")
                    fulfillment = dict(current.get("fulfillment") or {})
                    if field == "fulfillmentText":
                        fulfillment["text"] = " ".join(str(value).split())[:600]
                    else:
                        url = str(value).strip()
                        parsed = urlsplit(url) if url else None
                        if url and (parsed is None or parsed.scheme.lower() != "https" or not parsed.netloc):
                            raise ValueError("invalid fulfillment URL")
                        fulfillment["url"] = url
                    field, value = "fulfillment", fulfillment
                item = self.state.update_payment_offer(
                    offer_ref, {field: value}, actor_user_id=user_id, actor_platform=self.platform
                )
                self.state.clear_dialog(user_id)
                screen = payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            except (TypeError, ValueError):
                screen = Screen(frame_error("مقدار واردشده معتبر نیست."), keyboard([button("انصراف", action=f"payment-offer:{offer_ref}")]))
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-search":
            query = " ".join(text.split())[:120]
            self.state.clear_dialog(user_id)
            if len(query) < 2 or self.site_api is None or not hasattr(self.site_api, "payment_transactions"):
                screen = Screen(frame_error("عبارت جستجو حداقل دو نویسه باشد."), payment_transactions_screen({"items": []}).keyboard)
            else:
                try:
                    filters = self._payment_transaction_filter_state(user_id)
                    filters["query"] = query
                    self._payment_filters[user_id] = filters
                    screen = payment_transactions_screen(
                        self._payment_transactions_payload(user_id, limit=10), filters=filters
                    )
                except SiteApiError as error:
                    screen = Screen(frame_error(str(error)), payment_transactions_screen({"items": []}).keyboard)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-transaction-date":
            parts = [part.strip() for part in text.split("|", 1)]
            try:
                if len(parts) != 2:
                    raise ValueError("invalid date range")
                local_zone = local_now("Asia/Tehran").tzinfo
                start = datetime.strptime(parts[0], "%Y-%m-%d").replace(tzinfo=local_zone)
                end = datetime.strptime(parts[1], "%Y-%m-%d").replace(hour=23, minute=59, second=59, tzinfo=local_zone)
                if start > end:
                    raise ValueError("invalid date range")
                filters = dict(dialog.get("payload") or {})
                filters["dateFrom"] = start.astimezone(timezone.utc).isoformat()
                filters["dateTo"] = end.astimezone(timezone.utc).isoformat()
                self._payment_filters[user_id] = filters
                self.state.clear_dialog(user_id)
                screen = payment_transactions_screen(self._payment_transactions_payload(user_id), filters=filters)
            except (TypeError, ValueError, SiteApiError):
                screen = Screen(
                    frame_error("بازه معتبر نیست؛ نمونه: 2026-08-01 | 2026-08-31"),
                    keyboard([button("انصراف", action="payment-transaction-filters")]),
                )
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id == self.owner_id and dialog.get("kind") == "payment-transaction-status":
            payload = dict(dialog.get("payload") or {})
            order_id = max(0, int(payload.get("orderId") or 0))
            status = str(payload.get("status") or "")
            note = " ".join(text.split())[:240]
            if len(note) < 3 or self.site_api is None or not hasattr(self.site_api, "payment_update_transaction_status"):
                screen = Screen(frame_error("دلیل تغییر وضعیت را حداقل با ۳ نویسه بنویس."), keyboard([button("انصراف", action=f"payment-transaction:{order_id}")]))
            else:
                try:
                    updated = self.site_api.payment_update_transaction_status(
                        user_id, order_id=order_id, status=status, note=note
                    )
                    self.state.record_payment_action(
                        "transaction-status-updated", actor_user_id=user_id, actor_platform=self.platform,
                        note=f"order:{order_id}:{status}",
                    )
                    self.state.clear_dialog(user_id)
                    screen = payment_transaction_detail_screen(updated)
                except SiteApiError as error:
                    screen = Screen(frame_error(str(error)), keyboard([button("بازگشت", action=f"payment-transaction:{order_id}")]))
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if user_id != self.owner_id or dialog.get("kind") != "payment-offer":
            return False
        step = str(dialog.get("step") or "")
        payload = dict(dialog.get("payload") or {})
        if step == "title":
            title = " ".join(text.split())[:160]
            if len(title) < 3:
                self.api.send(chat_id, frame_error("عنوان باید حداقل ۳ نویسه باشد."), payment_offer_wizard_screen("title", payload).keyboard)
                return True
            payload["title"] = title
            self.state.update_dialog(user_id, step="amount", payload=payload)
            screen = payment_offer_wizard_screen("amount", payload)
        elif step == "custom-amount":
            amount_tomans = self._parse_tomans(text)
            if amount_tomans < 1000 or amount_tomans > 10000000000:
                self.api.send(chat_id, frame_error("مبلغ باید بین ۱٬۰۰۰ تا ۱۰ میلیارد تومان باشد."), payment_offer_wizard_screen(step, payload).keyboard)
                return True
            payload["amountRials"] = amount_tomans * 10
            self.state.update_dialog(user_id, step="audience", payload=payload)
            screen = payment_offer_wizard_screen("audience", payload)
        elif step == "description":
            payload["description"] = " ".join(text.split())[:360]
            self.state.update_dialog(user_id, step="preview", payload=payload)
            screen = payment_offer_preview_screen(payload)
        else:
            screen = payment_offer_wizard_screen(step, payload)
        self.api.send(chat_id, screen.text, screen.keyboard)
        return True

    def _handle_booklet_dialog(self, chat_id: int, user_id: int, text: str, dialog: dict) -> bool:
        step = str(dialog.get("step") or "course")
        payload = dict(dialog.get("payload") or {})
        if text in {BOOKLET_CANCEL, BOOKLET_HOME}:
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(chat_id)
            screen = self._screen("home", user_id)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if text == BOOKLET_BACK:
            if step == "course":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(chat_id)
                screen = self._screen("home", user_id)
            elif step == "session":
                self.state.update_dialog(user_id, step="course", payload={})
                screen = booklet_courses_screen()
            else:
                course_code = str(payload.get("courseCode") or "")
                self.state.update_dialog(user_id, step="session", payload={"courseCode": course_code})
                screen = booklet_sessions_screen(course_code)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if step == "course":
            course = course_from_button(text)
            if course is None:
                self.api.send(chat_id, frame_error("درس را فقط از دکمه‌های فهرست انتخاب کن."), booklet_courses_screen().keyboard)
                return True
            course_code = str(course["code"])
            self.state.update_dialog(user_id, step="session", payload={"courseCode": course_code})
            self.api.send(chat_id, booklet_sessions_screen(course_code).text, booklet_sessions_screen(course_code).keyboard)
            return True
        course_code = str(payload.get("courseCode") or "")
        if course_code not in COURSE_BY_CODE:
            self.state.update_dialog(user_id, step="course", payload={})
            screen = booklet_courses_screen()
            self.api.send(chat_id, frame_error("درس قبلی معتبر نبود؛ دوباره انتخاب کن.") + "\n\n" + screen.text, screen.keyboard)
            return True
        if step == "session":
            session = session_from_button(course_code, text)
            if session is None:
                screen = booklet_sessions_screen(course_code)
                self.api.send(chat_id, frame_error("جلسه را فقط از دکمه‌های طرح درس انتخاب کن."), screen.keyboard)
                return True
            session_no = int(session[0])
            payload = {"courseCode": course_code, "sessionNo": session_no}
            self.state.update_dialog(user_id, step="resource", payload=payload)
            screen = booklet_resources_screen(course_code, session_no)
            self.api.send(chat_id, screen.text, screen.keyboard)
            return True
        if step == "resource":
            content_kind = next((kind for kind, label in RESOURCE_LABELS.items() if label == text), "")
            session_no = int(payload.get("sessionNo") or 0)
            screen = booklet_resources_screen(course_code, session_no)
            if not content_kind:
                self.api.send(chat_id, frame_error("نوع فایل را از چهار دکمه انتخاب کن."), screen.keyboard)
                return True
            sources = self.state.protected_media_for(
                course_code=course_code,
                term=int(COURSE_BY_CODE[course_code]["term"]),
                session_no=session_no,
                content_kind=content_kind,
            )
            if not sources:
                self.api.send(chat_id, frame_error("برای این جلسه و این نوع، هنوز فایل معتبری ثبت نشده است."), screen.keyboard)
                return True
            if self.media_dispatcher is None:
                self.api.send(chat_id, frame_error("صف ارسال امن فعلاً در دسترس نیست."), screen.keyboard)
                return True
            statuses = [self.media_dispatcher.enqueue(user_id, int(source["id"])) for source in sources]
            if all(status == "full" for status in statuses):
                message = "صف ارسال پر است؛ چند لحظه بعد دوباره تلاش کن."
            elif all(status in {"rate-limited", "cooldown"} for status in statuses):
                message = "⏳ درخواست‌ها خیلی سریع تکرار شدند؛ چند لحظه بعد دوباره امتحان کن."
            elif all(status in {"denied", "missing"} for status in statuses):
                message = "⚠️ مجوز یا فایل معتبر این بخش پیدا نشد؛ دوباره از فهرست جزوات وارد شو."
            elif all(status == "duplicate" for status in statuses):
                message = "همین فایل هم‌اکنون در صف ارسال توست."
            else:
                queued = sum(status == "queued" for status in statuses)
                if queued:
                    message = f"✅ {queued} فایل در صف امن قرار گرفت و پس از بررسی دوبارهٔ مجوز ارسال می‌شود."
                elif any(status == "duplicate" for status in statuses):
                    message = "همین فایل هم‌اکنون در صف ارسال توست."
                elif any(status in {"rate-limited", "cooldown"} for status in statuses):
                    message = "⏳ درخواست‌ها خیلی سریع تکرار شدند؛ چند لحظه بعد دوباره امتحان کن."
                else:
                    message = "⚠️ فایل قابل ارسال پیدا نشد؛ دوباره از فهرست جزوات وارد شو."
            self.api.send(chat_id, message, screen.keyboard)
            return True
        self.state.update_dialog(user_id, step="course", payload={})
        screen = booklet_courses_screen()
        self.api.send(chat_id, frame_error("مرحلهٔ جزوات معتبر نبود؛ از فهرست درس‌ها ادامه بده."), screen.keyboard)
        return True

    def booklet_access_allowed(self, user_id: int, source: dict) -> bool:
        """Fresh identity, membership and term-entitlement check for every delivery path."""
        if self.platform != "telegram" or self.site_api is None:
            return False
        course = COURSE_BY_CODE.get(str(source.get("courseCode") or ""))
        if not course or int(source.get("term") or 0) != int(course["term"]):
            return False
        try:
            account = self.site_api.account(user_id)
            authorized = bool(
                account.get("linked") is True and account.get("authComplete") is True
            ) or bool(
                isinstance(account.get("onboardingProfile"), dict)
                and str(account["onboardingProfile"].get("verifiedAt") or "").strip()
                and not account["onboardingProfile"].get("isClassMember")
            )
            if not authorized:
                return False
            if self.required_channel_username:
                if not bool(self.api.is_chat_member(f"@{self.required_channel_username}", user_id)):
                    return False
            identity = subscription_identity_from_account(account)
            decision = self.state.term_access_decision(
                identity.subject_key if identity else "", int(source.get("term") or 0)
            )
            return bool(decision.get("allowed"))
        except (SiteApiError, BotApiError, AttributeError, TypeError, ValueError):
            return False

    def _claim_interaction(self, user_id: int) -> int:
        with self._interaction_lock:
            version = self._interaction_versions.get(user_id, 0) + 1
            self._interaction_versions[user_id] = version
            return version

    def _interaction_is_current(self, user_id: int, version: int) -> bool:
        with self._interaction_lock:
            return self._interaction_versions.get(user_id) == version

    def _callback(self, callback: dict, *, interaction_version: int | None = None) -> None:
        callback_id = str(callback.get("id") or "")
        sender = dict(callback.get("from") or {})
        message = dict(callback.get("message") or {})
        chat = dict(message.get("chat") or {})
        data = str(callback.get("data") or "")
        if not callback_id or not isinstance(sender.get("id"), int) or chat.get("type") != "private":
            return
        user_id = int(sender["id"])
        if interaction_version is None:
            interaction_version = self._claim_interaction(user_id)
        name = data[3:] if data.startswith("v1:") else "home"
        if name == "submit-note":
            name = "notes"
        owner_action = name in {
            "admin", "system-status", "admin-grades", "admin-payments", "navid", "navid-check",
            "identity-mappings", "identity-mapping-remove-cancel", "identity-mapping-remove-confirm",
            "profile-edit-requests", "payment-offer-new", "payment-offer-cancel", "payment-offer-publish",
            "payment-offer-no-description", "payment-offer-custom-amount", "payment-offer-description",
            "payment-products", "payment-stats", "payment-transactions", "payment-search", "payment-audiences",
            "payment-export", "payment-reminders", "payment-settings", "payment-audience-new",
            "payment-audience-search", "payment-audience-search-more", "payment-audience-selection-save",
            "payment-transaction-filters", "payment-tx-clear", "payment-tx-product",
            "term-subscription-admin", "term-subscription-settings", "term-subscription-price",
            "term-subscription-start",
            "term-subscription-toggle", "term-subscription-mode", "term-subscription-reminders",
            "term-subscription-grant", "term-subscription-free", "term-subscription-revoke",
            "term-subscription-export", "term-access-policies", "term-access-policy-add",
        } or name.startswith((
            "identity-approve:", "identity-reject:", "identity-mapping-remove:", "profile-edit-approve:",
            "profile-edit-reject:", "payment-offer-status:", "payment-offer-amount:", "payment-offer-delete",
            "payment-offer:", "payment-offer-", "payment-products-page:", "payment-export-",
            "payment-reminder-preview:",
            "prs:", "payment-product-copy:", "payment-product-share-preview:", "pps:",
            "payment-audience-select:",
            "payment-transactions-page:", "payment-tx-status:", "payment-tx-platform:",
            "payment-tx-gateway:", "payment-tx-period:", "payment-tx-product:",
            "payment-transaction:", "payment-transaction-status:",
            "term-subscription-admin:", "term-subscription-settings:", "term-subscription-price:",
            "term-subscription-start:",
            "term-subscription-toggle:", "term-subscription-mode:", "term-subscription-reminders:",
            "term-subscription-grant:", "term-subscription-grant-select:", "term-subscription-free:",
            "term-subscription-revoke:", "term-subscription-export:",
        ))
        if owner_action and user_id != self.owner_id:
            name = "home"
        if not callback.get("_membership_answered"):
            self.api.answer_callback(callback_id)
        time.sleep(0.08)
        if not self._interaction_is_current(user_id, interaction_version):
            return
        auth_dialog = self._active_auth_dialog_screen(user_id)
        blocked = auth_dialog or (
            self._private_access_gate(user_id)
            if self._requires_canonical_link(name)
            else self._bot_entry_gate(user_id)
        )
        if blocked is not None:
            dialog = self.state.dialog(user_id)
            if dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
                self.state.clear_dialog(user_id)
                self._remove_reply_keyboard(int(chat["id"]))
            try:
                self.api.edit(int(chat["id"]), int(message["message_id"]), blocked.text, blocked.keyboard)
            except BotApiError as error:
                if "message is not modified" not in str(error).lower():
                    raise
            return
        if name.startswith("payment-export-file:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3 and re.fullmatch(r"(?:all|filtered|[A-Za-z0-9_-]{16,80})", parts[1]) and parts[2] in {"csv", "paid", "unpaid", "transactions"}:
                try:
                    self._send_payment_export(int(chat["id"]), user_id, parts[1], "transactions" if parts[2] == "csv" else parts[2])
                except (SiteApiError, BotApiError) as error:
                    self.api.send(int(chat["id"]), frame_error(str(error)), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            return
        if name.startswith("term-subscription-export:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3 and parts[1].isdigit() and parts[2] in {"csv", "txt"}:
                try:
                    self._send_term_subscription_export(int(chat["id"]), user_id, int(parts[1]), parts[2])
                except BotApiError as error:
                    self.api.send(
                        int(chat["id"]), frame_error(str(error)),
                        term_subscription_admin_screen(
                            self.state.term_access_policy(int(parts[1])) or {},
                            self._term_subscription_report(user_id, int(parts[1])),
                        ).keyboard,
                    )
            return
        if name.startswith("prs:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3:
                try:
                    self._send_payment_reminder_batch(int(chat["id"]), user_id, parts[1], parts[2])
                except SiteApiError as error:
                    self.api.send(int(chat["id"]), frame_error(str(error)), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            return
        if name.startswith("pps:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) == 3:
                try:
                    self._send_payment_product_batch(int(chat["id"]), user_id, parts[1], parts[2])
                except SiteApiError as error:
                    self.api.send(int(chat["id"]), frame_error(str(error)), payment_control_center_screen([]).keyboard)
            return
        if name == "navid-check" and user_id == self.owner_id:
            self._send_navid_challenge(int(chat["id"]), refresh=True)
            return
        if name == "notes":
            if self.platform != "telegram":
                screen = bale_unavailable_screen()
            else:
                policy = self.state.term_access_policy(7) or {}
                account = self.site_api.account(user_id) if self.site_api is not None else {}
                identity = subscription_identity_from_account(account)
                decision = self.state.term_access_decision(identity.subject_key if identity else "", 7)
                if policy_is_effective(policy) and identity is None:
                    screen = self._unlinked_access_screen(user_id, account)
                elif decision.get("allowed"):
                    self.state.start_dialog(user_id, "booklets-v1", "course", {})
                    screen = booklet_courses_screen()
                else:
                    screen = term_subscription_screen(policy, decision, term=7)
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        dialog = self.state.dialog(user_id)
        if dialog is not None and str(dialog.get("kind") or "") == "booklets-v1":
            self.state.clear_dialog(user_id)
            self._remove_reply_keyboard(int(chat["id"]))
        self.state.touch_user(user_id, name)
        screen = self._dynamic_screen(name, user_id, request_id=callback_id, sender=sender)
        if not self._interaction_is_current(user_id, interaction_version):
            return
        # Telegram clients do not consistently repaint a regular text message
        # when editMessageText changes its content type to a native rich message.
        # Enter a structured report with sendRichMessage, then keep using edits
        # only when the callback already came from a rich message.
        is_native_rich = bool(getattr(screen.text, "rich_html", ""))
        source_is_native_rich = isinstance(message.get("rich_message"), dict)
        if self.platform == "telegram" and is_native_rich and not source_is_native_rich:
            self.api.send(int(chat["id"]), screen.text, screen.keyboard)
            return
        try:
            self.api.edit(int(chat["id"]), int(message["message_id"]), screen.text, screen.keyboard)
        except BotApiError as error:
            if "message is not modified" not in str(error).lower():
                raise

    def _dynamic_screen(
        self,
        name: str,
        user_id: int,
        *,
        request_id: str = "",
        sender: dict | None = None,
    ) -> Screen:
        blocked = (
            self._private_access_gate(user_id)
            if self._requires_canonical_link(name)
            else self._bot_entry_gate(user_id)
        )
        if blocked is not None:
            return blocked
        if name == "admin-grades" and user_id == self.owner_id:
            return owner_grade_screen(self.site_url)
        if name in {"term-subscription", "term-subscription:7"} or name.startswith("term-subscription-info:"):
            term = int(name.rsplit(":", 1)[1]) if ":" in name and name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            if policy is None:
                return Screen(frame_error("برای این ترم سیاست دسترسی تعریف نشده است."), self._screen("home", user_id).keyboard)
            account = self._account_snapshot(user_id, refresh=True)
            identity = subscription_identity_from_account(account)
            decision = self.state.term_access_decision(identity.subject_key if identity else "", term)
            return term_subscription_info_screen(policy, term=term) if name.startswith("term-subscription-info:") else term_subscription_screen(policy, decision, term=term)
        if name.startswith("term-subscription-buy:"):
            term_text = name.rsplit(":", 1)[1]
            if not term_text.isdigit() or self.site_api is None:
                return Screen(frame_error("مسیر خرید اشتراک معتبر نیست."), self._screen("home", user_id).keyboard)
            term = int(term_text)
            policy = self.state.term_access_policy(term)
            account = self._account_snapshot(user_id, refresh=True)
            identity = subscription_identity_from_account(account)
            if policy is None or identity is None:
                return self._unlinked_access_screen(user_id, account)
            decision = self.state.term_access_decision(identity.subject_key, term)
            if decision.get("allowed"):
                return term_subscription_screen(policy, decision, term=term)
            if not policy_is_effective(policy):
                return term_subscription_screen(policy, decision, term=term)
            offer = self.state.payment_offer(str(policy.get("offerRef") or ""), require_active=True)
            if offer is None:
                return Screen(frame_error("فروش اشتراک این ترم فعلاً متوقف است."), term_subscription_screen(policy, decision, term=term).keyboard)
            period = billing_period_for(term)
            stable_request_id = hashlib.sha256(
                f"term-subscription:{self.platform}:{user_id}:{identity.subject_key}:{period.key}".encode("utf-8")
            ).hexdigest()
            try:
                checkout = self.state.begin_term_subscription_checkout(
                    request_id=stable_request_id, platform=self.platform, platform_user_id=user_id,
                    subject_key=identity.subject_key, student_number=identity.student_number,
                    display_name=identity.display_name, term=term, billing_period=period.key,
                    amount_rials=int(policy["monthlyPriceRials"]), offer_ref=str(policy["offerRef"]),
                    offer_version=int(policy.get("version") or 1),
                )
                result = self.site_api.create_bot_payment(
                    user_id, offer_ref=str(policy["offerRef"]),
                    title=f"اشتراک جزوات ترم {term} · {period.month_label}",
                    description="اشتراک کامل ماه شمسی جاری؛ اعتبار فقط تا پایان همین ماه است.",
                    amount_rials=int(checkout["amountRials"]), request_id=stable_request_id,
                    product_version=int(checkout["offerVersion"]),
                    available_from="", expires_at=utc_iso(period.expires_at), capacity=0, max_per_user=0,
                    fulfillment={
                        "kind": "term_subscription", "term": term, "billingPeriod": period.key,
                        "text": "پس از تأیید درگاه، اشتراک همین ماه در ربات فعال می‌شود.",
                    },
                )
                self.state.bind_term_subscription_order(stable_request_id, str(result.get("orderToken") or ""))
                return payment_created_screen(
                    result, platform=self.platform, return_to_bot_enabled=self.payment_return_v1_enabled
                )
            except (SiteApiError, TypeError, ValueError) as error:
                return Screen(frame_error(str(error)), term_subscription_screen(policy, decision, term=term).keyboard)
        if name == "term-access-policies" and user_id == self.owner_id:
            return term_access_policies_screen(self.state.term_access_policies())
        if name == "term-access-policy-add" and user_id == self.owner_id:
            self.state.start_dialog(user_id, "term-access-policy-add", "term", {})
            return Screen(
                "<b>➕ افزودن policy ترم</b>\n\nشماره ترم را بین ۱ تا ۱۲ بفرست. policy جدید در حالت باز و غیرفعال ساخته می‌شود.",
                keyboard([button("انصراف", action="term-access-policies")]),
            )
        if name.startswith("term-subscription-admin:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            if policy is None:
                return Screen(frame_error("سیاست این ترم پیدا نشد."), payment_control_center_screen([]).keyboard)
            return term_subscription_admin_screen(policy, self._term_subscription_report(user_id, term))
        if name.startswith("term-subscription-settings:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            return term_subscription_settings_screen(policy) if policy else Screen(frame_error("سیاست پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("term-subscription-price:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            self.state.start_dialog(user_id, "term-subscription-price", "value", {"term": term})
            return Screen(
                f"<b>💰 مبلغ اشتراک ترم {to_persian_digits(term)}</b>\n\nمبلغ جدید را به <b>تومان</b> بفرست. سفارش‌های قبلاً ساخته‌شده تغییر نمی‌کنند.",
                keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
            )
        if name.startswith("term-subscription-start:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            self.state.start_dialog(user_id, "term-subscription-start", "value", {"term": term})
            return Screen(
                f"<b>📅 شروع سیاست ترم {to_persian_digits(term)}</b>\n\nتاریخ شمسی را به شکل <code>۱۴۰۵/۰۷/۰۱</code> بفرست. مرز اجرا نیمه‌شب تهران است.",
                keyboard([button("انصراف", action=f"term-subscription-settings:{term}")]),
            )
        if name.startswith(("term-subscription-toggle:", "term-subscription-mode:", "term-subscription-reminders:")) and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            policy = self.state.term_access_policy(term)
            if policy is None:
                return Screen(frame_error("سیاست پیدا نشد."), payment_control_center_screen([]).keyboard)
            changes = (
                {"enabled": not bool(policy.get("enabled"))}
                if name.startswith("term-subscription-toggle:")
                else {"mode": "open" if str(policy.get("mode")) == "subscription" else "subscription"}
                if name.startswith("term-subscription-mode:")
                else {"renewalRemindersEnabled": not bool(policy.get("renewalRemindersEnabled"))}
            )
            updated = self.state.update_term_access_policy(
                term, changes, actor_user_id=user_id, actor_platform=self.platform, note="owner settings change"
            )
            return term_subscription_settings_screen(updated)
        if name.startswith("term-subscription-grant:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            self.state.start_dialog(user_id, "term-subscription-grant-search", "query", {"term": term})
            return Screen(
                f"<b>🔎 اعطای دسترسی رایگان ترم {to_persian_digits(term)}</b>\n\nبخشی از نام یا شماره دانشجویی را بفرست. انتخاب فقط از حساب‌های canonical سایت انجام می‌شود.",
                keyboard([button("انصراف", action=f"term-subscription-admin:{term}")]),
            )
        if name.startswith("term-subscription-grant-select:") and user_id == self.owner_id:
            parts = name.split(":")
            dialog = self.state.dialog(user_id)
            if len(parts) != 3 or not parts[1].isdigit() or not parts[2].isdigit() or not dialog or dialog.get("kind") != "term-subscription-grant-search":
                return Screen(frame_error("جلسهٔ انتخاب دانشجو منقضی شده است."), payment_control_center_screen([]).keyboard)
            term, student = int(parts[1]), parts[2]
            candidates = [item for item in dict(dialog.get("payload") or {}).get("candidates", []) if isinstance(item, dict)]
            candidate = next((item for item in candidates if str(item.get("studentNumber") or "") == student), None)
            if candidate is None:
                return Screen(frame_error("دانشجو در نتیجهٔ canonical پیدا نشد."), keyboard([button("بازگشت", action=f"term-subscription-grant:{term}")]))
            payload = {"term": term, "studentNumber": student, "displayName": str(candidate.get("name") or "")}
            self.state.start_dialog(user_id, "term-subscription-grant-note", "note", payload)
            return Screen(
                f"<b>🎁 تأیید دسترسی رایگان</b>\n\n👤 {html.escape(payload['displayName'] or 'دانشجو')}\n"
                f"🎓 <code>{to_persian_digits(student)}</code>\n\nیادداشت/دلیل را بفرست؛ اگر لازم نیست فقط <code>-</code> بفرست.",
                keyboard([button("انصراف", action=f"term-subscription-admin:{term}")]),
            )
        if name.startswith("term-subscription-free:") and user_id == self.owner_id:
            term = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 7
            return complimentary_access_list_screen(self.state.complimentary_term_access(term), term=term)
        if name.startswith("term-subscription-revoke:") and user_id == self.owner_id:
            entitlement_id = int(name.rsplit(":", 1)[1]) if name.rsplit(":", 1)[1].isdigit() else 0
            item = self.state.complimentary_term_access_by_id(entitlement_id)
            if item is None:
                return Screen(frame_error("دسترسی رایگان فعال پیدا نشد."), complimentary_access_list_screen([], term=7).keyboard)
            term = int(item.get("term") or 7)
            self.state.start_dialog(user_id, "term-subscription-revoke-note", "note", {"term": term, "entitlementId": entitlement_id})
            return Screen(
                f"<b>❌ لغو دسترسی رایگان</b>\n\n👤 {html.escape(str(item.get('displayName') or 'دانشجو'))}\n"
                f"🎓 <code>{to_persian_digits(item.get('studentNumber') or '—')}</code>\n\nدلیل لغو را بنویس؛ اگر لازم نیست <code>-</code> بفرست.",
                keyboard([button("انصراف", action=f"term-subscription-free:{term}")]),
            )
        if name == "payments" or name.startswith("payments-page:"):
            page = int(name.rsplit(":", 1)[1]) if name.startswith("payments-page:") and name.rsplit(":", 1)[1].isdigit() else 0
            try:
                offers = self._eligible_products(user_id)
                states = self._payment_product_states(user_id, offers)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), self._screen("home", user_id).keyboard)
            return payment_offers_screen(offers, states=states, page=page)
        if name == "admin-payments" and user_id == self.owner_id:
            summary = {}
            if self.site_api is not None and hasattr(self.site_api, "payment_owner_dashboard"):
                try:
                    summary = self.site_api.payment_owner_dashboard(user_id)
                except SiteApiError:
                    summary = {}
            return payment_control_center_screen(self._ordinary_payment_offers(), summary)
        if (name == "payment-products" or name.startswith("payment-products-page:")) and user_id == self.owner_id:
            page = int(name.rsplit(":", 1)[1]) if name.startswith("payment-products-page:") and name.rsplit(":", 1)[1].isdigit() else 0
            return owner_payment_offers_screen(self._ordinary_payment_offers(), page=page)
        if name == "payment-stats" and user_id == self.owner_id:
            return payment_control_center_screen(self._ordinary_payment_offers(), self._owner_payment_summary(user_id))
        if name == "payment-export" and user_id == self.owner_id:
            return Screen(
                "<b>📤 خروجی پرداخت‌ها</b>\n\nخروجی CSV با UTF-8 BOM برای Excel و نسخه متنی کوتاه در دسترس است.",
                keyboard(
                    [button("📤 همه تراکنش‌ها CSV", action="payment-export-file:all:csv", style="success")],
                    [button("📝 خروجی متنی", action="payment-export-text:all")],
                    [button("مرکز پرداخت‌ها", action="admin-payments")],
                ),
            )
        if name == "payment-reminders" and user_id == self.owner_id:
            return Screen(
                "<b>🔔 یادآوری پرداخت</b>\n\nیادآوری فقط از صفحه آمار محصولِ دارای مخاطب محدود ساخته می‌شود: ابتدا فهرست پرداخت‌نکرده‌ها، سپس پیش‌نمایش و تأیید صریح. ارسال تکراری ۲۴ ساعته مسدود است.",
                keyboard([button("📦 انتخاب محصول", action="payment-products")], [button("مرکز پرداخت‌ها", action="admin-payments")]),
            )
        if name == "payment-settings" and user_id == self.owner_id:
            return Screen(
                "<b>⚙️ تنظیمات پرداخت</b>\n\nواحد canonical: <b>ریال</b>؛ نمایش ربات: <b>تومان</b>.\n"
                "درگاه، callback و reconciliation از تنظیم فعلی سایت استفاده می‌کنند و از ربات قابل تغییر نیستند.",
                keyboard([button("📦 مدیریت محصولات", action="payment-products")], [button("مرکز پرداخت‌ها", action="admin-payments")]),
            )
        if (name == "payment-transactions" or name.startswith("payment-transactions-page:")) and user_id == self.owner_id:
            page = int(name.rsplit(":", 1)[1]) if name.startswith("payment-transactions-page:") and name.rsplit(":", 1)[1].isdigit() else 0
            try:
                filters = self._payment_transaction_filter_state(user_id)
                return payment_transactions_screen(
                    self._payment_transactions_payload(user_id, page=page, limit=10), page=page, filters=filters
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
        if name == "payment-transaction-filters" and user_id == self.owner_id:
            return payment_transaction_filters_screen(
                self._payment_transaction_filter_state(user_id), self._ordinary_payment_offers()
            )
        if name == "payment-tx-clear" and user_id == self.owner_id:
            self._payment_filters.pop(user_id, None)
            try:
                return payment_transactions_screen(self._payment_transactions_payload(user_id), filters={})
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_control_center_screen([]).keyboard)
        if name.startswith(("payment-tx-status:", "payment-tx-platform:", "payment-tx-gateway:")) and user_id == self.owner_id:
            prefix, value = name.rsplit(":", 1)
            key = {"payment-tx-status": "status", "payment-tx-platform": "platform", "payment-tx-gateway": "gateway"}.get(prefix, "")
            allowed = {
                "status": {"success", "pending", "failed", "canceled", "expired"},
                "platform": {"telegram", "bale"},
                "gateway": {"zibal", "zarinpal", "mock"},
            }
            if not key or value not in allowed[key]:
                return Screen(frame_error("فیلتر معتبر نیست."), payment_transaction_filters_screen({}, []).keyboard)
            filters = self._payment_transaction_filter_state(user_id)
            filters[key] = value
            self._payment_filters[user_id] = filters
            return payment_transaction_filters_screen(filters, self._ordinary_payment_offers())
        if name.startswith("payment-tx-period:") and user_id == self.owner_id:
            period = name.rsplit(":", 1)[1]
            filters = self._payment_transaction_filter_state(user_id)
            if period == "custom":
                self.state.start_dialog(user_id, "payment-transaction-date", "value", filters)
                return Screen(
                    "<b>🗓 بازه سفارشی</b>\n\nتاریخ شروع و پایان را میلادی و با این قالب بفرست:\n<code>2026-08-01 | 2026-08-31</code>",
                    keyboard([button("انصراف", action="payment-transaction-filters")]),
                )
            date_from, date_to = self._payment_period_bounds(period)
            if not date_from:
                return Screen(frame_error("بازه زمانی معتبر نیست."), payment_transaction_filters_screen(filters, []).keyboard)
            filters["dateFrom"], filters["dateTo"] = date_from, date_to
            self._payment_filters[user_id] = filters
            return payment_transaction_filters_screen(filters, self._ordinary_payment_offers())
        if name == "payment-tx-product" and user_id == self.owner_id:
            return payment_transaction_filters_screen(
                self._payment_transaction_filter_state(user_id), self._ordinary_payment_offers()
            )
        if name.startswith("payment-tx-product:") and user_id == self.owner_id:
            offer_ref = name.split(":", 1)[1]
            if self._ordinary_payment_offer(offer_ref) is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_transaction_filters_screen({}, []).keyboard)
            filters = self._payment_transaction_filter_state(user_id)
            filters["offerRef"] = offer_ref
            self._payment_filters[user_id] = filters
            return payment_transaction_filters_screen(filters, self._ordinary_payment_offers())
        if name.startswith("payment-transaction-status:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3 or not parts[1].isdigit() or parts[2] not in {"pending", "failed", "canceled", "expired"}:
                return Screen(frame_error("تغییر وضعیت معتبر نیست."), payment_transactions_screen({"items": []}).keyboard)
            self.state.start_dialog(user_id, "payment-transaction-status", "note", {"orderId": int(parts[1]), "status": parts[2]})
            return Screen(
                "<b>✍️ دلیل تغییر وضعیت</b>\n\nاین تغییر دستی است و به‌عنوان تأیید درگاه ثبت نمی‌شود. دلیل کوتاه را بفرست.",
                keyboard([button("انصراف", action=f"payment-transaction:{parts[1]}")]),
            )
        if name.startswith("payment-transaction:") and user_id == self.owner_id:
            order_id = name.rsplit(":", 1)[1]
            if not order_id.isdigit() or self.site_api is None or not hasattr(self.site_api, "payment_transaction"):
                return Screen(frame_error("سفارش پیدا نشد."), payment_transactions_screen({"items": []}).keyboard)
            try:
                return payment_transaction_detail_screen(self.site_api.payment_transaction(user_id, order_id=int(order_id)))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_transactions_screen({"items": []}).keyboard)
        if name == "payment-search" and user_id == self.owner_id:
            self.state.start_dialog(user_id, "payment-search", "query", {})
            return Screen("<b>🔎 جستجوی تراکنش</b>\n\nنام، شماره دانشجویی، کد پیگیری، شناسه سفارش یا نام محصول را بفرست.", payment_transactions_screen({"items": []}).keyboard)
        if name == "payment-audiences" and user_id == self.owner_id:
            lists = self.state.saved_payment_audiences()
            lines = ["<b>👥 مخاطبان ذخیره‌شده</b>", ""]
            for entry in lists[:20]:
                lines.append(f"• <b>{html.escape(str(entry.get('name') or 'فهرست'))}</b> · {to_persian_digits(len(entry.get('studentNumbers') or []))} نفر")
            if not lists:
                lines.append("هنوز فهرستی ساخته نشده است.")
            return Screen(
                "\n".join(lines),
                keyboard(
                    [button("➕ فهرست جدید", action="payment-audience-new", style="success")],
                    [button("🔎 جستجو و انتخاب افراد", action="payment-audience-search", style="primary")],
                    [button("مرکز پرداخت‌ها", action="admin-payments")],
                ),
            )
        if name == "payment-audience-new" and user_id == self.owner_id:
            self.state.start_dialog(user_id, "payment-audience-new", "value", {})
            return Screen(
                "<b>➕ فهرست مخاطب</b>\n\nدر یک پیام بفرست:\n<code>نام فهرست | شماره۱، شماره۲، شماره۳</code>",
                keyboard([button("انصراف", action="payment-audiences")]),
            )
        if name in {"payment-audience-search", "payment-audience-search-more"} and user_id == self.owner_id:
            existing = self.state.dialog(user_id)
            payload = dict(existing.get("payload") or {}) if existing and existing.get("kind") == "payment-audience-search" else {"selected": []}
            self.state.start_dialog(user_id, "payment-audience-search", "query", payload)
            return Screen(
                "<b>🔎 جستجوی مخاطب</b>\n\nبخشی از نام یا شماره دانشجویی را بفرست. نتیجه از حساب‌های canonical سایت خوانده می‌شود.",
                keyboard([button("انصراف", action="payment-audiences")]),
            )
        if name.startswith("payment-audience-select:") and user_id == self.owner_id:
            student = name.rsplit(":", 1)[1]
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-audience-search" or not student.isdigit():
                return Screen(frame_error("جلسه انتخاب مخاطب منقضی شده است."), self._dynamic_screen("payment-audiences", user_id).keyboard)
            payload = dict(dialog.get("payload") or {})
            selected = [str(value) for value in payload.get("selected", []) if str(value).isdigit()]
            if student in selected:
                selected.remove(student)
            else:
                selected.append(student)
            payload["selected"] = selected[:500]
            self.state.update_dialog(user_id, step="results", payload=payload)
            return self._payment_audience_search_results(payload)
        if name == "payment-audience-selection-save" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            selected = list(dict(dialog.get("payload") or {}).get("selected") or []) if dialog and dialog.get("kind") == "payment-audience-search" else []
            if not selected:
                return Screen(frame_error("حداقل یک نفر را انتخاب کن."), self._dynamic_screen("payment-audiences", user_id).keyboard)
            payload = dict(dialog.get("payload") or {})
            self.state.update_dialog(user_id, step="name", payload=payload)
            return Screen("<b>💾 ذخیره فهرست</b>\n\nیک نام کوتاه برای این فهرست بفرست.", keyboard([button("انصراف", action="payment-audiences")]))
        if name.startswith("payment-export-text:") and user_id == self.owner_id:
            parts = name.split(":")
            scope = parts[1] if len(parts) > 1 else "all"
            try:
                rows = self._payment_export_rows(user_id, scope, "transactions")
                lines = ["<b>📝 خروجی متنی پرداخت‌ها</b>", ""]
                for index, row in enumerate(rows[:25], 1):
                    lines.append(
                        f"{to_persian_digits(index)}. {html.escape(str(row.get('payerName') or '—'))} · "
                        f"{html.escape(str(row.get('title') or 'محصول'))} · <code>{html.escape(format_rials(row.get('amountRials')))}</code> · "
                        f"{html.escape(str(row.get('statusLabel') or row.get('status') or ''))}"
                    )
                if len(rows) > 25:
                    lines.append("\nبرای ادامه، فایل CSV را بگیر.")
                return Screen("\n".join(lines), keyboard([button("📤 CSV", action=f"payment-export-file:{scope}:csv")], [button("مرکز پرداخت‌ها", action="admin-payments")]))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-export:") and user_id == self.owner_id:
            name = "payment-offer-stats:" + name.rsplit(":", 1)[1]
        if name.startswith("payment-product-copy:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            share_url = bot_start_url(self.bot_username, f"product_{str(item.get('shareToken') or '')}", platform=self.platform)
            return Screen(
                f"<b>📋 اطلاعات قابل کپی</b>\n\n<b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                f"مبلغ: <code>{html.escape(format_rials(item.get('amountRials')))}</code>\n"
                f"{html.escape(str(item.get('description') or ''))}\n\n<code>{html.escape(share_url)}</code>",
                keyboard([button("بازگشت به محصول", action=f"payment-offer:{offer_ref}")]),
            )
        if name.startswith("payment-product-share-preview:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            try:
                report = self._payment_people_report(user_id, item)
                recipients = [person for person in report.get("targets", []) if str(person.get("platformUserId") or "").isdigit()][:50]
                if not recipients:
                    return Screen(
                        frame_error("برای مخاطبان این محصول، حساب متصل و قابل ارسال در همین پلتفرم پیدا نشد."),
                        payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard,
                    )
                preview = self.state.create_payment_reminder_preview(
                    offer_ref, self.platform,
                    [str(person.get("studentNumber") or "") for person in recipients], purpose="product-share",
                )
                if preview.get("duplicate"):
                    return Screen(
                        frame_error("همین محصول در ۲۴ ساعت اخیر برای همین مخاطبان ارسال شده یا در حال ارسال است."),
                        payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard,
                    )
                return Screen(
                    f"<b>📤 پیش‌نمایش ارسال محصول</b>\n\nمحصول: <b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"گیرنده قابل دسترس در {('بله' if self.platform == 'bale' else 'تلگرام')}: <b>{to_persian_digits(min(50, len(recipients)))}</b>\n\n"
                    "بدون تأیید زیر هیچ پیامی ارسال نمی‌شود؛ هر اجرا حداکثر ۵۰ گیرنده دارد.",
                    keyboard(
                        [button("تأیید و ارسال", action=f"pps:{offer_ref}:{preview['ref']}", style="danger")],
                        [button("انصراف", action=f"payment-offer:{offer_ref}")],
                    ),
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name.startswith("payment-reminder-preview:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            try:
                report = self._payment_people_report(user_id, item)
                recipients = [person for person in report.get("unpaid", []) if str(person.get("platformUserId") or "").isdigit()][:50]
                if not recipients:
                    return Screen(
                        frame_error("دانشجوی پرداخت‌نکرده با حساب متصل و قابل ارسال در همین پلتفرم پیدا نشد."),
                        payment_product_report_screen(item, report).keyboard,
                    )
                preview = self.state.create_payment_reminder_preview(offer_ref, self.platform, [str(person.get("studentNumber") or "") for person in recipients])
                if preview.get("duplicate"):
                    return Screen(frame_error("همین یادآوری در ۲۴ ساعت اخیر ارسال شده یا در حال ارسال است."), payment_product_report_screen(item, report).keyboard)
                return Screen(
                    f"<b>🔔 پیش‌نمایش یادآوری</b>\n\nمحصول: <b>{html.escape(str(item.get('title') or 'محصول'))}</b>\n"
                    f"گیرنده قابل دسترس در {('بله' if self.platform == 'bale' else 'تلگرام')}: <b>{to_persian_digits(min(50, len(recipients)))}</b>\n\nبدون تأیید زیر هیچ پیامی ارسال نمی‌شود؛ هر اجرا حداکثر ۵۰ گیرنده دارد.",
                    keyboard(
                        [button("تأیید و ارسال", action=f"prs:{offer_ref}:{preview['ref']}", style="danger")],
                        [button("انصراف", action=f"payment-offer-stats:{offer_ref}")],
                    ),
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name in {"identity-claim", "identity-claim-cancel"}:
            self.state.clear_dialog(user_id)
            return Screen(
                frame_error("تأیید دستی هویت بازنشسته شده است؛ یکی از دو مسیر امن زیر را انتخاب کن."),
                class_auth_screen().keyboard,
            )
        if name == "class-auth":
            self.state.start_dialog(user_id, "class-auth-v1", "method", {})
            return class_auth_screen()
        if name == "profile-edit-cancel":
            self.state.clear_dialog(user_id)
            name = "account"
        if name == "profile-edit":
            return profile_edit_fields_screen()
        if name.startswith("profile-edit-field:"):
            field = name.split(":", 1)[1]
            labels = {
                "firstName": "نام", "lastName": "نام خانوادگی", "major": "رشته",
                "institution": "دانشگاه", "province": "استان دانشگاه",
                "entryYear": "سال ورود", "admissionType": "نیمسال و نوع پذیرش",
                "studentNumber": "شماره دانشجویی",
            }
            if field not in labels:
                return Screen(frame_error("بخش انتخاب‌شده معتبر نیست."), profile_edit_fields_screen().keyboard)
            self.state.start_dialog(user_id, "profile-edit-v1", "value", {"field": field, "fieldLabel": labels[field]})
            return profile_edit_prompt_screen(labels[field])
        if name == "identity-mapping-remove-cancel" and user_id == self.owner_id:
            self.state.clear_dialog(user_id)
            if self.site_api is None:
                return self._screen("admin", user_id)
            try:
                return owner_identity_mappings_screen(self.site_api.identity_mappings(user_id), platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name in {"identity-mapping-new", "identity-mapping-save", "identity-mapping-cancel", "identity-claims"}:
            self.state.clear_dialog(user_id)
            return Screen(
                frame_error("اتصال و تأیید دستی هویت بازنشسته شده است؛ اتصال تازه فقط با OTP یا ورود امن سایت ساخته می‌شود."),
                home(self.site_url, is_owner=user_id == self.owner_id).keyboard,
            )
        if name == "payment-offer-new" and user_id == self.owner_id:
            dialog = self.state.start_dialog(user_id, "payment-offer", "title")
            return payment_offer_wizard_screen("title", dialog["payload"])
        if name == "payment-offer-cancel" and user_id == self.owner_id:
            self.state.clear_dialog(user_id)
            return owner_payment_offers_screen(self._ordinary_payment_offers())
        if name.startswith("payment-offer-amount:") and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer" or dialog.get("step") != "amount":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            amount_tomans = self._parse_tomans(name.rsplit(":", 1)[1])
            payload = dict(dialog.get("payload") or {})
            payload["amountRials"] = amount_tomans * 10
            self.state.update_dialog(user_id, step="audience", payload=payload)
            return payment_offer_wizard_screen("audience", payload)
        if name == "payment-offer-custom-amount" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            payload = dict(dialog.get("payload") or {})
            self.state.update_dialog(user_id, step="custom-amount", payload=payload)
            return payment_offer_wizard_screen("custom-amount", payload)
        if name == "payment-offer-no-description" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            payload = dict(dialog.get("payload") or {})
            payload["description"] = ""
            self.state.update_dialog(user_id, step="preview", payload=payload)
            return payment_offer_preview_screen(payload)
        if name == "payment-offer-description" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            payload = dict(dialog.get("payload") or {})
            self.state.update_dialog(user_id, step="description", payload=payload)
            return payment_offer_wizard_screen("description", payload)
        if name.startswith("payment-offer-audience:") and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            if not dialog or dialog.get("kind") != "payment-offer":
                return Screen(frame_error("فرایند ساخت محصول منقضی شده است."), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            selection = name.rsplit(":", 1)[1]
            audience = {
                "all": {"mode": "all"},
                "open": {"mode": "open"},
                "primary": {"mode": "cohorts", "cohorts": ["dentistry-1402"]},
            }.get(selection)
            if audience is None:
                return Screen(
                    "<b>👥 مخاطب پیشرفته</b>\n\nبرای انتخاب فردی و فهرست ذخیره‌شده، ابتدا از بخش «مخاطبان» فهرست بساز؛ سپس در ویرایش محصول انتخابش کن.",
                    payment_offer_wizard_screen("audience", dict(dialog.get("payload") or {})).keyboard,
                )
            payload = dict(dialog.get("payload") or {})
            payload["audience"] = audience
            payload.setdefault("description", "")
            self.state.update_dialog(user_id, step="preview", payload=payload)
            return payment_offer_preview_screen(payload)
        if name == "payment-offer-publish" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            payload = dict(dialog.get("payload") or {}) if dialog else {}
            if not dialog or dialog.get("kind") != "payment-offer" or not payload.get("title") or not payload.get("amountRials"):
                return Screen(frame_error("اطلاعات محصول کامل نیست."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
            try:
                item = self.state.create_payment_offer(
                    str(payload["title"]), int(payload["amountRials"]), str(payload.get("description") or ""),
                    audience=dict(payload.get("audience") or {"mode": "all"}),
                    actor_user_id=user_id, actor_platform=self.platform,
                )
            except (TypeError, ValueError):
                return Screen(frame_error("اطلاعات محصول معتبر نیست."), payment_offer_preview_screen(payload).keyboard)
            self.state.clear_dialog(user_id)
            return payment_offer_saved_screen(item, bot_username=self.bot_username, platform=self.platform)
        if name.startswith("payment-offer-delete-confirm:") and user_id == self.owner_id:
            item = self._ordinary_payment_offer(name.rsplit(":", 1)[1])
            return payment_offer_delete_confirmation(item) if item else Screen(frame_error("محصول پیدا نشد."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
        if name.startswith("payment-offer-delete:") and user_id == self.owner_id:
            self.state.set_payment_offer_status(name.rsplit(":", 1)[1], "archived", actor_user_id=user_id, actor_platform=self.platform)
            return owner_payment_offers_screen(self._ordinary_payment_offers())
        if name.startswith("payment-offer:") and user_id == self.owner_id:
            item = self._ordinary_payment_offer(name.split(":", 1)[1])
            return payment_offer_admin_detail_screen(
                item,
                bot_username=self.bot_username,
                platform=self.platform,
            ) if item else Screen(frame_error("محصول پیدا نشد."), owner_payment_offers_screen(self._ordinary_payment_offers()).keyboard)
        if name.startswith("payment-offer-status:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3 or parts[2] not in {"active", "paused"}:
                return Screen(frame_error("درخواست تغییر وضعیت معتبر نیست."), home(self.site_url, is_owner=True).keyboard)
            self.state.set_payment_offer_status(parts[1], parts[2], actor_user_id=user_id, actor_platform=self.platform)
            return owner_payment_offers_screen(self._ordinary_payment_offers())
        if name.startswith("payment-offer-duplicate:") and user_id == self.owner_id:
            item = self.state.duplicate_payment_offer(name.rsplit(":", 1)[1], actor_user_id=user_id, actor_platform=self.platform)
            return payment_offer_saved_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-rotate:") and user_id == self.owner_id:
            item = self.state.rotate_payment_share_token(name.rsplit(":", 1)[1], actor_user_id=user_id, actor_platform=self.platform)
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-edit:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self._ordinary_payment_offer(offer_ref)
            return Screen(
                "<b>✏️ ویرایش محصول</b>\n\nفیلد موردنظر را انتخاب کن؛ تغییر پس از ثبت، نسخه محصول را افزایش می‌دهد و سفارش‌های قبلی را عوض نمی‌کند.",
                keyboard(
                    [button("عنوان", action=f"payment-offer-field:{offer_ref}:title"), button("مبلغ", action=f"payment-offer-field:{offer_ref}:amountRials")],
                    [button("توضیح", action=f"payment-offer-field:{offer_ref}:description")],
                    [button("بازگشت", action=f"payment-offer:{offer_ref}")],
                ),
            ) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-schedule:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            return Screen(
                "<b>🗓 زمان‌بندی محصول</b>\n\nزمان را ISO همراه timezone بفرست؛ نمونه: <code>2026-09-01T12:00:00+03:30</code>",
                keyboard(
                    [button("زمان شروع", action=f"payment-offer-field:{offer_ref}:availableFrom"), button("مهلت پایان", action=f"payment-offer-field:{offer_ref}:expiresAt")],
                    [button("حذف زمان‌بندی", action=f"payment-offer-clear-window:{offer_ref}")],
                    [button("بازگشت", action=f"payment-offer:{offer_ref}")],
                ),
            )
        if name.startswith("payment-offer-advanced:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            return Screen(
                "<b>⚙️ تنظیمات پیشرفته</b>\n\nصفر برای ظرفیت یا سقف خرید یعنی نامحدود.",
                keyboard(
                    [button("ظرفیت", action=f"payment-offer-field:{offer_ref}:capacity"), button("سقف خرید هر نفر", action=f"payment-offer-field:{offer_ref}:maxPurchasesPerUser")],
                    [button("متن تحویل پس از خرید", action=f"payment-offer-field:{offer_ref}:fulfillmentText")],
                    [button("لینک تحویل HTTPS", action=f"payment-offer-field:{offer_ref}:fulfillmentUrl")],
                    [button("بازگشت", action=f"payment-offer:{offer_ref}")],
                ),
            )
        if name.startswith("payment-offer-audience-edit:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            rows = [
                [button("همه احرازشده‌ها", action=f"payment-offer-audience-set:{offer_ref}:all")],
                [button("ورودی ۱۴۰۲ تهران", action=f"payment-offer-audience-set:{offer_ref}:primary")],
                [button("فقط دارندگان لینک", action=f"payment-offer-audience-set:{offer_ref}:open")],
                [button("شماره‌های دانشجویی", action=f"payment-offer-field:{offer_ref}:audienceUsers")],
                [button("یک یا چند cohort", action=f"payment-offer-field:{offer_ref}:audienceCohorts")],
            ]
            for saved in self.state.saved_payment_audiences()[:6]:
                rows.append([button(f"فهرست · {str(saved.get('name') or '')[:24]}", action=f"payment-offer-audience-list:{offer_ref}:{saved.get('ref', '')}")])
            rows.append([button("بازگشت", action=f"payment-offer:{offer_ref}")])
            return Screen(
                "<b>👥 مخاطب محصول</b>\n\nانتخاب جدید بلافاصله روی مشاهده و خریدهای بعدی اعمال می‌شود.",
                keyboard(*rows),
            )
        if name.startswith("payment-offer-audience-list:") and user_id == self.owner_id:
            parts = name.split(":")
            item = self.state.update_payment_offer(parts[1], {"audience": {"mode": "lists", "listRefs": [parts[2]]}}, actor_user_id=user_id, actor_platform=self.platform) if len(parts) == 3 else None
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("فهرست یا محصول معتبر نیست."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-audience-set:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3:
                return Screen(frame_error("مخاطب معتبر نیست."), payment_control_center_screen([]).keyboard)
            audience = {"all": {"mode": "all"}, "primary": {"mode": "cohorts", "cohorts": ["dentistry-1402"]}, "open": {"mode": "open"}}.get(parts[2])
            item = self.state.update_payment_offer(parts[1], {"audience": audience}, actor_user_id=user_id, actor_platform=self.platform) if audience else None
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-clear-window:") and user_id == self.owner_id:
            offer_ref = name.rsplit(":", 1)[1]
            item = self.state.update_payment_offer(offer_ref, {"availableFrom": "", "expiresAt": ""}, actor_user_id=user_id, actor_platform=self.platform)
            return payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform) if item else Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
        if name.startswith("payment-offer-field:") and user_id == self.owner_id:
            parts = name.split(":")
            if len(parts) != 3 or parts[2] not in {"title", "description", "amountRials", "availableFrom", "expiresAt", "capacity", "maxPurchasesPerUser", "audienceUsers", "audienceCohorts", "fulfillmentText", "fulfillmentUrl"}:
                return Screen(frame_error("فیلد ویرایش معتبر نیست."), payment_control_center_screen([]).keyboard)
            self.state.start_dialog(user_id, "payment-offer-edit", "value", {"offerRef": parts[1], "field": parts[2]})
            return Screen("<b>✏️ مقدار جدید</b>\n\nمقدار را در یک پیام بفرست. برای خالی‌کردن توضیح یا زمان، یک خط تیره بفرست.", keyboard([button("انصراف", action=f"payment-offer:{parts[1]}")]))
        if name.startswith("payment-offer-stats:") and user_id == self.owner_id:
            parts = name.split(":")
            offer_ref = parts[1] if len(parts) >= 2 else ""
            period = parts[2] if len(parts) >= 3 and parts[2] in {"today", "7", "30", "all"} else "all"
            item = self._ordinary_payment_offer(offer_ref)
            if item is None or self.site_api is None or not hasattr(self.site_api, "payment_product_report"):
                return Screen(frame_error("گزارش این محصول در دسترس نیست."), payment_control_center_screen(self._ordinary_payment_offers()).keyboard)
            try:
                date_from, date_to = self._payment_period_bounds(period)
                report = self._payment_people_report(user_id, item, date_from=date_from, date_to=date_to)
                return payment_product_report_screen(item, report, period=period)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name.startswith(("payment-offer-payers:", "payment-offer-unpaid:")) and user_id == self.owner_id:
            kind = "paid" if name.startswith("payment-offer-payers:") else "unpaid"
            parts = name.split(":")
            offer_ref = parts[1] if len(parts) >= 2 else ""
            page = int(parts[2]) if len(parts) >= 3 and parts[2].isdigit() else 0
            item = self._ordinary_payment_offer(offer_ref)
            if item is None:
                return Screen(frame_error("محصول پیدا نشد."), payment_control_center_screen([]).keyboard)
            try:
                report = self._payment_people_report(user_id, item)
                people = list(report.get("payers" if kind == "paid" else "unpaid") or [])
                return payment_people_screen(
                    "✅ پرداخت‌کنندگان" if kind == "paid" else "❌ پرداخت‌نکرده‌ها",
                    people, offer_ref=offer_ref, kind=kind, page=page,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), payment_offer_admin_detail_screen(item, bot_username=self.bot_username, platform=self.platform).keyboard)
        if name.startswith("payment-confirm:"):
            account = self._account_snapshot(user_id)
            item = self.state.payment_offer_for_user(name.split(":", 1)[1], identity_from_account(account))
            if item is None:
                return Screen(frame_error("این محصول در دسترس این حساب نیست یا اعتبارش پایان یافته است."), self._screen("home", user_id).keyboard)
            states = self._payment_product_states(user_id, [item])
            return payment_confirm_screen(item, state=dict(states.get(str(item.get("ref") or "")) or {}))
        if name == "student-assistant" and not self.student_assistant_v1_enabled:
            return self._screen("home", user_id)
        if name in {"exams", "exam-owner"} and not self.exams_v1_enabled:
            return self._screen("exams" if name == "exams" else "admin", user_id)
        if self.site_api is None:
            return self._screen(name, user_id)
        if name == "exams" and self.exams_v1_enabled:
            try:
                return exam_screen(
                    self.site_api.exam_hub(user_id),
                    site_url=self.site_url,
                    is_owner=user_id == self.owner_id,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "exam-owner" and self.exams_v1_enabled:
            if user_id != self.owner_id:
                return self._screen("home", user_id)
            try:
                return exam_screen(
                    self.site_api.exam_owner_hub(user_id),
                    site_url=self.site_url,
                    is_owner=True,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith("exam-action:") and self.exams_v1_enabled:
            action_ref = name.split(":", 1)[1]
            if not re.fullmatch(r"[A-Za-z0-9_-]{1,20}", action_ref):
                return Screen(frame_error("این عملیات آزمون منقضی یا نامعتبر است."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            stable_request_id = hashlib.sha256(request_id.encode("utf-8")).hexdigest()
            try:
                return exam_screen(
                    self.site_api.perform_exam_action(
                        user_id,
                        action_ref=action_ref,
                        request_id=stable_request_id,
                    ),
                    site_url=self.site_url,
                    is_owner=user_id == self.owner_id,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "student-assistant" and self.student_assistant_v1_enabled:
            try:
                return student_assistant_screen(
                    self.site_api.student_assistant_summary(user_id),
                    is_owner=user_id == self.owner_id,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), self._screen("home", user_id).keyboard)
        if name.startswith("assistant-action:") and self.student_assistant_v1_enabled:
            action_ref = name.split(":", 1)[1]
            if re.fullmatch(r"[A-Za-z0-9_-]{1,20}", action_ref) is None:
                return Screen(frame_error("این عملیات منقضی یا نامعتبر است."), self._screen("home", user_id).keyboard)
            stable_request_id = hashlib.sha256(request_id.encode("utf-8")).hexdigest()
            try:
                result = self.site_api.perform_integration_action(
                    user_id,
                    action_ref=action_ref,
                    request_id=stable_request_id,
                )
                if str(result.get("status") or "") == "challenge":
                    send_private_challenge(api=self.api, state=self.state, user_id=user_id, payload=result)
                    return integration_challenge_waiting_screen(result)
                return student_assistant_screen(result, is_owner=user_id == self.owner_id)
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), self._screen("home", user_id).keyboard)
            except BotApiError:
                return Screen(
                    frame_error("تصویر کپچا معتبر نبود یا در پیام‌رسان ارسال نشد."),
                    self._screen("student-assistant", user_id).keyboard,
                )
        if name in {"account", "check-link", "link-required"}:
            try:
                account = self.site_api.account(user_id)
                return account_screen(
                    self.site_url,
                    platform=self.platform,
                    linked_user=(
                        dict(account.get("user") or {})
                        if account.get("linked") and account.get("authComplete")
                        else None
                    ),
                    identity_state=dict(account.get("identity") or {}),
                    onboarding_profile=dict(account.get("onboardingProfile") or {}) if isinstance(account.get("onboardingProfile"), dict) else None,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "link-account":
            try:
                result = self.site_api.start_link(
                    user_id,
                    platform_profile=self._platform_profile(dict(sender or {"id": user_id})),
                )
                return account_screen(
                    self.site_url,
                    platform=self.platform,
                    linked_user=(
                        dict(result.get("user") or {})
                        if result.get("alreadyLinked") and result.get("authComplete")
                        else None
                    ),
                    link_url=str(result.get("linkUrl") or ""),
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "grades":
            try:
                return grades_screen(self.site_url, self.site_api.grades(user_id), platform=self.platform)
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name == "navid" and user_id == self.owner_id:
            try:
                return navid_screen(self.site_api.navid_status(user_id), platform=self.platform, site_url=self.site_url)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name == "profile-edit-requests" and user_id == self.owner_id:
            try:
                return profile_edit_requests_screen(self.site_api.profile_edit_requests(user_id))
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith(("profile-edit-approve:", "profile-edit-reject:")) and user_id == self.owner_id:
            decision = "approve" if name.startswith("profile-edit-approve:") else "reject"
            request_ref = name.rsplit(":", 1)[1]
            try:
                self.site_api.resolve_profile_edit(user_id, request_ref=request_ref, decision=decision)
                payload = self.site_api.profile_edit_requests(user_id)
                status = "تأیید و اعمال" if decision == "approve" else "رد"
                screen = profile_edit_requests_screen(payload)
                return Screen(f"<b>✅ درخواست {status} شد</b>\n\n" + screen.text, screen.keyboard)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name == "identity-mappings" and user_id == self.owner_id:
            try:
                return owner_identity_mappings_screen(self.site_api.identity_mappings(user_id), platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith("identity-mapping-remove:") and user_id == self.owner_id:
            mapping_ref = name.rsplit(":", 1)[1]
            try:
                payload = self.site_api.identity_mappings(user_id)
                item = next((entry for entry in payload.get("mappings", []) if str(entry.get("ref") or "") == mapping_ref), None)
                if not isinstance(item, dict):
                    return Screen(frame_error("اتصال موردنظر پیدا نشد."), owner_identity_mappings_screen(payload, platform=self.platform).keyboard)
                self.state.start_dialog(user_id, "identity-mapping-remove", "reason", {"mappingRef": mapping_ref, "item": item})
                return identity_mapping_remove_screen(item, platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name == "identity-mapping-remove-confirm" and user_id == self.owner_id:
            dialog = self.state.dialog(user_id)
            payload = dict(dialog.get("payload") or {}) if dialog else {}
            if not dialog or dialog.get("kind") != "identity-mapping-remove" or dialog.get("step") != "preview":
                return Screen(frame_error("فرایند حذف اتصال منقضی شده است."), home(self.site_url, is_owner=True).keyboard)
            try:
                self.site_api.delete_identity_mapping(
                    user_id,
                    mapping_ref=str(payload.get("mappingRef") or ""),
                    reason=str(payload.get("reason") or ""),
                )
                self.state.clear_dialog(user_id)
                return owner_identity_mappings_screen(self.site_api.identity_mappings(user_id), platform=self.platform)
            except SiteApiError as error:
                return Screen(frame_error(str(error)), identity_mapping_remove_confirmation(payload, platform=self.platform).keyboard)
        if (name.startswith("identity-approve:") or name.startswith("identity-reject:")) and user_id == self.owner_id:
            return Screen(
                frame_error("این دکمه متعلق به سامانهٔ قدیمی است و دیگر هیچ هویتی را تأیید یا رد نمی‌کند."),
                home(self.site_url, is_owner=True).keyboard,
            )
        if name == "notifications":
            try:
                payload = self.site_api.notifications(user_id, limit=20)
                items = [item for item in dict(payload.get("data") or {}).get("items", []) if isinstance(item, dict)]
                refs = {
                    str(item.get("id") or ""): self.state.remember_notification(str(item.get("id") or ""))
                    for item in items
                    if str(item.get("id") or "")
                }
                return notification_list_screen(
                    payload,
                    refs,
                    platform=self.platform,
                    site_url=self.site_url,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("notification-action:"):
            parts = name.split(":", 2)
            if len(parts) != 3:
                return Screen(frame_error("عملیات اعلان معتبر نیست."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            ref, action_ref = parts[1], parts[2]
            notification_id = self.state.notification_id(ref)
            if not notification_id or not re.fullmatch(r"[A-Za-z0-9_-]{1,20}", action_ref):
                return Screen(frame_error("این عملیات دیگر در دسترس نیست."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            try:
                result = self.site_api.perform_notification_action(user_id, notification_id, action_ref)
                payload = self.site_api.notifications(user_id, limit=40)
                items = [item for item in dict(payload.get("data") or {}).get("items", []) if isinstance(item, dict)]
                item = next((entry for entry in items if str(entry.get("id") or "") == notification_id), None)
                if not isinstance(item, dict):
                    raise SiteApiError("اعلان پیدا نشد.", code="NOTIFICATION_NOT_FOUND", status=404)
                screen = notification_detail_screen(
                    item,
                    ref,
                    platform=self.platform,
                    is_owner=user_id == self.owner_id,
                    site_url=self.site_url,
                    show_mark_read=False,
                )
                message = html.escape(str(result.get("message") or "انجام شد."))[:240]
                return Screen(f"<b>✅ {message}</b>\n\n{screen.text}", screen.keyboard)
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("notification-read:") or name.startswith("notification:"):
            ref = name.rsplit(":", 1)[1]
            notification_id = self.state.notification_id(ref)
            if not notification_id:
                return Screen(frame_error("این اعلان دیگر در دسترس نیست."), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
            try:
                self.site_api.mark_notification_read(user_id, notification_id)
                payload = self.site_api.notifications(user_id, limit=40)
                items = [item for item in dict(payload.get("data") or {}).get("items", []) if isinstance(item, dict)]
                item = next((entry for entry in items if str(entry.get("id") or "") == notification_id), None)
                if not isinstance(item, dict):
                    raise SiteApiError("اعلان پیدا نشد.", code="NOTIFICATION_NOT_FOUND", status=404)
                return notification_detail_screen(
                    item,
                    ref,
                    platform=self.platform,
                    is_owner=user_id == self.owner_id,
                    site_url=self.site_url,
                    show_mark_read=False,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("notification-audience:"):
            if user_id != self.owner_id:
                return self._screen("home", user_id)
            ref = name.rsplit(":", 1)[1]
            notification_id = self.state.notification_id(ref)
            if not notification_id:
                return Screen(frame_error("این اعلان دیگر در دسترس نیست."), home(self.site_url, is_owner=True).keyboard)
            try:
                return notification_audience_screen(
                    self.site_api.notification_audience(user_id, notification_id),
                    ref,
                    platform=self.platform,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=True).keyboard)
        if name.startswith(("payment-create:", "payment-create-link:")):
            via_link = name.startswith("payment-create-link:")
            account = self._account_snapshot(user_id)
            if via_link:
                raw_token = name.split(":", 1)[1]
                candidate = self.state.payment_offer_by_share_token(raw_token)
                offer = self.state.payment_offer_for_user(str(candidate.get("ref") or ""), identity_from_account(account), via_link=True) if candidate else None
            else:
                offer = self.state.payment_offer_for_user(name.split(":", 1)[1], identity_from_account(account))
            if offer is None:
                return Screen(frame_error("این محصول در دسترس این حساب نیست یا اعتبارش پایان یافته است."), self._screen("home", user_id).keyboard)
            stable_request_id = hashlib.sha256(request_id.encode("utf-8")).hexdigest()
            try:
                return payment_created_screen(
                    self.site_api.create_bot_payment(
                        user_id,
                        offer_ref=str(offer["ref"]),
                        title=str(offer["title"]),
                        description=str(offer["description"]),
                        amount_rials=int(offer["amountRials"]),
                        request_id=stable_request_id,
                        product_version=int(offer.get("version") or 1),
                        available_from=str(offer.get("availableFrom") or ""),
                        expires_at=str(offer.get("expiresAt") or ""),
                        capacity=int(offer.get("capacity") or 0),
                        max_per_user=int(offer.get("maxPurchasesPerUser") or 0),
                        fulfillment=dict(offer.get("fulfillment") or {}),
                    ),
                    platform=self.platform,
                    return_to_bot_enabled=self.payment_return_v1_enabled,
                )
            except SiteApiError as error:
                if error.code == "ACCOUNT_LINK_REQUIRED":
                    return account_screen(self.site_url, platform=self.platform)
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        if name.startswith("payment-status:"):
            order_token = name.split(":", 1)[1]
            try:
                status_payload = self.site_api.payment_status(user_id, order_token)
                status_payload = self._activate_subscription_from_payment_status(
                    user_id, order_token, dict(status_payload)
                )
                return payment_status_screen(
                    status_payload,
                    platform=self.platform,
                    order_token=order_token,
                    return_to_bot_enabled=self.payment_return_v1_enabled,
                )
            except SiteApiError as error:
                return Screen(frame_error(str(error)), home(self.site_url, is_owner=user_id == self.owner_id).keyboard)
        return self._screen(name, user_id)


def frame_error(message: str) -> str:
    return f"<b>🔴 انجام نشد</b>\n\n{html.escape(message)}"
